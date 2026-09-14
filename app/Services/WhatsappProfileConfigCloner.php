<?php

namespace App\Services;

use App\Models\BusinessBranch;
use App\Models\BusinessBranchDeliveryFeeTier;
use App\Models\BusinessBranchHour;
use App\Models\BusinessFaq;
use App\Models\ChatbotKeywordReply;
use App\Models\DeliveryDriver;
use App\Models\Franchise;
use App\Models\MarketingFlow;
use App\Models\MarketingFlowEdge;
use App\Models\MarketingFlowNode;
use App\Models\MarketingFlowStep;
use App\Models\MarketingFlowVersion;
use App\Models\WhatsappBusinessProfile;
use App\Models\WhatsappButton;
use App\Models\WhatsappChatbotConfig;
use App\Models\WhatsappContact;
use App\Models\WhatsappContactNote;
use App\Models\WhatsappMenu;
use App\Models\WhatsappMenuItem;
use App\Models\WhatsappPrice;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Copia toda la configuración de un número de WhatsApp (categorías,
 * productos/inventario, sucursales, franquicias, palabras clave, config del
 * bot + flujo visual, FAQs, botones, repartidores y clientes) a otro número
 * de la MISMA empresa recién conectado -- pedido explícito en vivo del
 * usuario para no tener que rearmar todo a mano al pasar el bot a un número
 * nuevo. Nunca copia conversaciones/mensajes ni pedidos: eso queda ligado al
 * número donde ocurrió de verdad.
 */
class WhatsappProfileConfigCloner
{
    public function clone(WhatsappBusinessProfile $source, WhatsappBusinessProfile $target): void
    {
        $this->assertTargetIsClean($target);

        DB::transaction(function () use ($source, $target) {
            $this->cloneChatbotConfig($source, $target);
            $franchiseMap = $this->cloneFranchises($source, $target);
            $menuMap = $this->cloneMenus($source, $target);
            $menuItemMap = $this->cloneMenuItems($source, $target, $menuMap, $franchiseMap);
            $this->clonePrices($source, $target, $menuItemMap, $franchiseMap);
            $branchMap = $this->cloneBranches($source, $target);
            $this->cloneBranchHours($branchMap);
            $this->cloneBranchDeliveryFeeTiers($branchMap);
            $keywordReplyMap = $this->cloneKeywordReplies($source, $target);
            $this->cloneKeywordReplyBranches($branchMap, $keywordReplyMap);
            $this->cloneFaqs($source, $target);
            $this->cloneButtons($source, $target);
            $this->cloneDeliveryDrivers($source, $target);
            $this->cloneMarketingFlows($source, $target);
            $this->cloneContacts($source, $target);
        });
    }

    /**
     * Solo se permite copiar hacia un número "limpio": sin categorías,
     * productos, franquicias, palabras clave ni flujo visual propios, y sin
     * más sucursales que la única MATRIZ que se autogenera al conectar un
     * número (BusinessBranch::ensureDefaultForProfile()). Evita duplicar o
     * pisar configuración real por accidente -- nada de merges parciales.
     */
    private function assertTargetIsClean(WhatsappBusinessProfile $target): void
    {
        $targetBranches = BusinessBranch::where('business_profile_id', $target->id)->get();
        $hasExtraBranches = $targetBranches->count() > 1 || $targetBranches->contains(fn ($branch) => $branch->code !== 'MATRIZ');

        $hasOwnConfig = $hasExtraBranches
            || WhatsappMenu::where('business_profile_id', $target->id)->exists()
            || WhatsappPrice::where('business_profile_id', $target->id)->exists()
            || Franchise::where('business_profile_id', $target->id)->exists()
            || ChatbotKeywordReply::where('business_profile_id', $target->id)->exists()
            || MarketingFlow::where('business_profile_id', $target->id)->exists()
            || BusinessFaq::where('business_profile_id', $target->id)->exists()
            || WhatsappButton::where('business_profile_id', $target->id)->exists();

        if ($hasOwnConfig) {
            throw new InvalidArgumentException(
                'Este número ya tiene su propia configuración (categorías, productos, sucursales, palabras clave o flujo propios). '
                .'Para copiarle la de otro número, primero hay que desconectarlo o borrar esa configuración.'
            );
        }
    }

    private function cloneChatbotConfig(WhatsappBusinessProfile $source, WhatsappBusinessProfile $target): void
    {
        $config = WhatsappChatbotConfig::where('business_profile_id', $source->id)->first();
        if (! $config) {
            return;
        }

        WhatsappChatbotConfig::updateOrCreate(
            ['business_profile_id' => $target->id],
            $config->only([
                'welcome_message', 'default_response', 'greetings', 'menu_commands', 'metadata',
                'is_active', 'monitoring_enabled', 'monitoring_phone_number', 'monitoring_email',
                'chatgpt_enabled', 'chatgpt_api_key', 'chatgpt_model', 'chatgpt_system_prompt',
                'chatgpt_max_tokens', 'chatgpt_temperature', 'chatgpt_additional_params',
            ])
        );
    }

    /** @return array<int,int> id viejo => id nuevo */
    private function cloneFranchises(WhatsappBusinessProfile $source, WhatsappBusinessProfile $target): array
    {
        $map = [];
        foreach (Franchise::where('business_profile_id', $source->id)->get() as $franchise) {
            $new = Franchise::create([
                'business_profile_id' => $target->id,
                'name' => $franchise->name,
                'slug' => $franchise->slug,
                'description' => $franchise->description,
                'is_default' => $franchise->is_default,
                'is_active' => $franchise->is_active,
            ]);
            $map[$franchise->id] = $new->id;
        }

        return $map;
    }

    /** @return array<int,int> */
    private function cloneMenus(WhatsappBusinessProfile $source, WhatsappBusinessProfile $target): array
    {
        $map = [];
        foreach (WhatsappMenu::where('business_profile_id', $source->id)->get() as $menu) {
            $new = WhatsappMenu::create([
                'business_profile_id' => $target->id,
                'title' => $menu->title,
                'description' => $menu->description,
                'type' => $menu->type,
                'content' => $menu->content,
                'button_text' => $menu->button_text,
                'icon' => $menu->icon,
                'action_id' => $menu->action_id,
                'order' => $menu->order,
                'is_active' => $menu->is_active,
                'metadata' => $menu->metadata,
                'image' => $menu->image,
            ]);
            $map[$menu->id] = $new->id;
        }

        return $map;
    }

    /**
     * Crea todos los items primero con parent_id vacío y recién en una
     * segunda pasada arma el árbol con los ids nuevos -- así no importa el
     * orden ni la profundidad de las subcategorías.
     *
     * @return array<int,int>
     */
    private function cloneMenuItems(WhatsappBusinessProfile $source, WhatsappBusinessProfile $target, array $menuMap, array $franchiseMap): array
    {
        $items = WhatsappMenuItem::where('business_profile_id', $source->id)->orderBy('id')->get();
        $map = [];
        foreach ($items as $item) {
            $new = WhatsappMenuItem::create([
                'menu_id' => $menuMap[$item->menu_id] ?? null,
                'business_profile_id' => $target->id,
                'franchise_id' => $item->franchise_id ? ($franchiseMap[$item->franchise_id] ?? null) : null,
                'parent_id' => null,
                'title' => $item->title,
                'description' => $item->description,
                'action_id' => $item->action_id,
                'icon' => $item->icon,
                'image' => $item->image,
                'order' => $item->order,
                'is_active' => $item->is_active,
                'demo_cliente' => $item->demo_cliente,
            ]);
            $map[$item->id] = $new->id;
        }

        foreach ($items as $item) {
            if ($item->parent_id && isset($map[$item->parent_id])) {
                WhatsappMenuItem::whereKey($map[$item->id])->update(['parent_id' => $map[$item->parent_id]]);
            }
        }

        return $map;
    }

    private function clonePrices(WhatsappBusinessProfile $source, WhatsappBusinessProfile $target, array $menuItemMap, array $franchiseMap): void
    {
        foreach (WhatsappPrice::where('business_profile_id', $source->id)->get() as $price) {
            WhatsappPrice::create([
                'menu_item_id' => $menuItemMap[$price->menu_item_id] ?? null,
                'business_profile_id' => $target->id,
                'franchise_id' => $price->franchise_id ? ($franchiseMap[$price->franchise_id] ?? null) : null,
                'category' => $price->category,
                'sku' => $price->sku,
                'name' => $price->name,
                'description' => $price->description,
                'benefits' => $price->benefits,
                'characteristics' => $price->characteristics,
                'price' => $price->price,
                'promo_price' => $price->promo_price,
                'currency' => $price->currency,
                'is_promo' => $price->is_promo,
                'promo_start_date' => $price->promo_start_date,
                'promo_end_date' => $price->promo_end_date,
                'is_active' => $price->is_active,
                'demo_cliente' => $price->demo_cliente,
                'stock' => $price->stock,
                'allow_quantity_selection' => $price->allow_quantity_selection,
                'min_quantity' => $price->min_quantity,
                'max_quantity' => $price->max_quantity,
                'image' => $price->image,
                'metadata' => $price->metadata,
            ]);
        }
    }

    /**
     * Reemplaza (no duplica) la sucursal MATRIZ que se autogenera al
     * conectar un número nuevo -- assertTargetIsClean() ya garantizó que no
     * hay ninguna otra sucursal real bajo el destino.
     *
     * @return array<int,int>
     */
    private function cloneBranches(WhatsappBusinessProfile $source, WhatsappBusinessProfile $target): array
    {
        BusinessBranch::where('business_profile_id', $target->id)->delete();

        $map = [];
        foreach (BusinessBranch::where('business_profile_id', $source->id)->get() as $branch) {
            $new = BusinessBranch::create([
                'business_profile_id' => $target->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'phone' => $branch->phone,
                'address' => $branch->address,
                'reservations_info' => $branch->reservations_info,
                'is_default' => $branch->is_default,
                'is_active' => $branch->is_active,
                'orders_enabled' => $branch->orders_enabled,
                'latitude' => $branch->latitude,
                'longitude' => $branch->longitude,
                'delivery_fee_per_unit' => $branch->delivery_fee_per_unit,
                'delivery_fee_km_unit' => $branch->delivery_fee_km_unit,
                'delivery_fee_minimum' => $branch->delivery_fee_minimum,
                'dine_in_enabled' => $branch->dine_in_enabled,
            ]);
            $map[$branch->id] = $new->id;
        }

        return $map;
    }

    private function cloneBranchHours(array $branchMap): void
    {
        if (! $branchMap) {
            return;
        }

        foreach (BusinessBranchHour::whereIn('business_branch_id', array_keys($branchMap))->get() as $hour) {
            BusinessBranchHour::create([
                'business_branch_id' => $branchMap[$hour->business_branch_id],
                'day_of_week' => $hour->day_of_week,
                'is_closed' => $hour->is_closed,
                'opens_at' => $hour->opens_at,
                'closes_at' => $hour->closes_at,
            ]);
        }
    }

    private function cloneBranchDeliveryFeeTiers(array $branchMap): void
    {
        if (! $branchMap) {
            return;
        }

        foreach (BusinessBranchDeliveryFeeTier::whereIn('business_branch_id', array_keys($branchMap))->get() as $tier) {
            BusinessBranchDeliveryFeeTier::create([
                'business_branch_id' => $branchMap[$tier->business_branch_id],
                'from_km' => $tier->from_km,
                'to_km' => $tier->to_km,
                'price' => $tier->price,
            ]);
        }
    }

    /** @return array<int,int> */
    private function cloneKeywordReplies(WhatsappBusinessProfile $source, WhatsappBusinessProfile $target): array
    {
        $map = [];
        foreach (ChatbotKeywordReply::where('business_profile_id', $source->id)->get() as $reply) {
            $new = ChatbotKeywordReply::create([
                'business_profile_id' => $target->id,
                'keywords' => $reply->keywords,
                'all_branches' => $reply->all_branches,
                'response_text' => $reply->response_text,
                'is_active' => $reply->is_active,
                'sort_order' => $reply->sort_order,
            ]);
            $map[$reply->id] = $new->id;
        }

        return $map;
    }

    private function cloneKeywordReplyBranches(array $branchMap, array $keywordReplyMap): void
    {
        if (! $keywordReplyMap) {
            return;
        }

        $rows = DB::table('keyword_reply_branch')
            ->whereIn('chatbot_keyword_reply_id', array_keys($keywordReplyMap))
            ->get();

        foreach ($rows as $row) {
            if (! isset($branchMap[$row->business_branch_id])) {
                continue;
            }

            DB::table('keyword_reply_branch')->insert([
                'business_branch_id' => $branchMap[$row->business_branch_id],
                'chatbot_keyword_reply_id' => $keywordReplyMap[$row->chatbot_keyword_reply_id],
            ]);
        }
    }

    private function cloneFaqs(WhatsappBusinessProfile $source, WhatsappBusinessProfile $target): void
    {
        foreach (BusinessFaq::where('business_profile_id', $source->id)->get() as $faq) {
            BusinessFaq::create([
                'business_profile_id' => $target->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'sort_order' => $faq->sort_order,
                'is_active' => $faq->is_active,
            ]);
        }
    }

    private function cloneButtons(WhatsappBusinessProfile $source, WhatsappBusinessProfile $target): void
    {
        foreach (WhatsappButton::where('business_profile_id', $source->id)->get() as $button) {
            WhatsappButton::create([
                'business_profile_id' => $target->id,
                'action_id' => $button->action_id,
                'title' => $button->title,
                'icon' => $button->icon,
                'type' => $button->type,
                'is_active' => $button->is_active,
                'order' => $button->order,
                'metadata' => $button->metadata,
            ]);
        }
    }

    private function cloneDeliveryDrivers(WhatsappBusinessProfile $source, WhatsappBusinessProfile $target): void
    {
        foreach (DeliveryDriver::where('business_profile_id', $source->id)->get() as $driver) {
            DeliveryDriver::create([
                'business_profile_id' => $target->id,
                'first_name' => $driver->first_name,
                'last_name' => $driver->last_name,
                'phone_number' => $driver->phone_number,
                'is_active' => $driver->is_active,
                // Sin historial de despacho todavía bajo el número nuevo.
                'last_dispatched_at' => null,
            ]);
        }
    }

    private function cloneMarketingFlows(WhatsappBusinessProfile $source, WhatsappBusinessProfile $target): void
    {
        foreach (MarketingFlow::where('business_profile_id', $source->id)->get() as $flow) {
            $newFlow = MarketingFlow::create([
                'business_profile_id' => $target->id,
                'name' => $flow->name,
                'is_active' => $flow->is_active,
                'is_default' => $flow->is_default,
            ]);

            foreach (MarketingFlowStep::where('flow_id', $flow->id)->get() as $step) {
                MarketingFlowStep::create([
                    'flow_id' => $newFlow->id,
                    'step_key' => $step->step_key,
                    'name' => $step->name,
                    'message_template' => $step->message_template,
                    'sort_order' => $step->sort_order,
                    'is_enabled' => $step->is_enabled,
                    'config' => $step->config,
                ]);
            }

            // node_uuid se reutiliza tal cual: la unicidad es por flow_id, y
            // los edges/snapshot de abajo referencian nodos por ese uuid, no
            // por el id de fila -- así el grafo sigue siendo válido bajo el
            // flow_id nuevo sin tener que reescribir nada.
            foreach (MarketingFlowNode::where('flow_id', $flow->id)->get() as $node) {
                MarketingFlowNode::create([
                    'flow_id' => $newFlow->id,
                    'node_uuid' => $node->node_uuid,
                    'node_type' => $node->node_type,
                    'name' => $node->name,
                    'message_template' => $node->message_template,
                    'config' => $node->config,
                    'position_x' => $node->position_x,
                    'position_y' => $node->position_y,
                    'is_enabled' => $node->is_enabled,
                    'is_start' => $node->is_start,
                ]);
            }

            foreach (MarketingFlowEdge::where('flow_id', $flow->id)->get() as $edge) {
                MarketingFlowEdge::create([
                    'flow_id' => $newFlow->id,
                    'source_node_uuid' => $edge->source_node_uuid,
                    'source_handle' => $edge->source_handle,
                    'target_node_uuid' => $edge->target_node_uuid,
                ]);
            }

            // Solo la versión publicada actual viaja (como v1 del flujo
            // nuevo) -- el historial de versiones viejas es más "historia de
            // ediciones" que configuración vigente, y no se copia.
            $currentVersion = MarketingFlowVersion::where('flow_id', $flow->id)->where('is_current', true)->first();
            if ($currentVersion) {
                MarketingFlowVersion::create([
                    'flow_id' => $newFlow->id,
                    'version_number' => 1,
                    'snapshot' => $currentVersion->snapshot,
                    'published_by' => $currentVersion->published_by,
                    'published_at' => now(),
                    'is_current' => true,
                ]);
            }
        }
    }

    /**
     * Por cada contacto del origen, se reutiliza uno ya existente bajo el
     * destino con el mismo teléfono (alguien ya le escribió al número nuevo)
     * en vez de tocarlo -- no se le pisa ni se le agregan notas del origen,
     * para no mezclar con actividad real que ya haya ocurrido ahí.
     */
    private function cloneContacts(WhatsappBusinessProfile $source, WhatsappBusinessProfile $target): void
    {
        foreach (WhatsappContact::where('business_profile_id', $source->id)->get() as $contact) {
            $alreadyExists = WhatsappContact::where('business_profile_id', $target->id)
                ->where('phone_number', $contact->phone_number)
                ->exists();
            if ($alreadyExists) {
                continue;
            }

            $newContact = WhatsappContact::create([
                'business_profile_id' => $target->id,
                'phone_number' => $contact->phone_number,
                'name' => $contact->name,
                'national_id' => $contact->national_id,
                'address' => $contact->address,
                'birth_date' => $contact->birth_date,
                'billing_type' => $contact->billing_type,
                'billing_id' => $contact->billing_id,
                'billing_legal_name' => $contact->billing_legal_name,
                'billing_email' => $contact->billing_email,
                'status' => $contact->status,
                'bot_enabled' => $contact->bot_enabled,
                // Sin mensajes todavía bajo el número nuevo.
                'last_inbound_message_id' => null,
                'last_inbound_at' => null,
                'metadata' => $contact->metadata,
                'password' => $contact->password,
            ]);

            foreach (WhatsappContactNote::where('contact_id', $contact->id)->get() as $note) {
                WhatsappContactNote::create([
                    'contact_id' => $newContact->id,
                    'user_id' => $note->user_id,
                    'body' => $note->body,
                ]);
            }
        }
    }
}
