<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessBranch;
use App\Models\ChatbotKeywordReply;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Palabras clave que el bot responde con un texto fijo (ver
 * ChatbotKeywordReply::findMatch(), enganchado en
 * WhatsappService::generateChatbotResponse()). Mismo patrón de listado con
 * formularios embebidos que FaqController/BusinessBranchController.
 */
class ChatbotKeywordController extends Controller
{
    public function index(): View
    {
        $profile = CompanyContext::current()->businessProfile;
        $businessProfileId = $profile?->id;

        return view('admin.chatbot.keywords.index', [
            'entries' => ChatbotKeywordReply::query()
                ->when($businessProfileId, fn ($q) => $q->where('business_profile_id', $businessProfileId))
                ->with('branches')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'branches' => $businessProfileId
                ? BusinessBranch::query()->where('business_profile_id', $businessProfileId)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = CompanyContext::current()->businessProfile;
        abort_unless($profile, 422, 'Esta empresa todavía no tiene un número de WhatsApp conectado.');

        $data = $this->validated($request, $profile->id);

        $entry = ChatbotKeywordReply::create([
            'business_profile_id' => $profile->id,
            'keywords' => $data['keywords'],
            'all_branches' => $data['all_branches'],
            'response_text' => $data['response_text'],
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'],
        ]);

        $this->syncBranches($entry, $data['branch_ids']);

        return back()->with('success', 'Palabra clave creada.');
    }

    public function update(Request $request, ChatbotKeywordReply $keyword): RedirectResponse
    {
        $this->authorizeEntry($keyword);
        $data = $this->validated($request, $keyword->business_profile_id, $keyword->id);

        $keyword->update([
            'keywords' => $data['keywords'],
            'all_branches' => $data['all_branches'],
            'response_text' => $data['response_text'],
            'is_active' => $data['is_active'],
            'sort_order' => $data['sort_order'],
        ]);

        $this->syncBranches($keyword, $data['branch_ids']);

        return back()->with('success', 'Palabra clave actualizada.');
    }

    public function destroy(ChatbotKeywordReply $keyword): RedirectResponse
    {
        $this->authorizeEntry($keyword);
        $keyword->delete();

        return back()->with('success', 'Palabra clave eliminada.');
    }

    /** El id llega en la URL: sin este chequeo se podría editar/borrar la de otra empresa. */
    private function authorizeEntry(ChatbotKeywordReply $keyword): void
    {
        $businessProfileId = CompanyContext::current()->businessProfileId();
        abort_unless($businessProfileId && (int) $keyword->business_profile_id === (int) $businessProfileId, 404);
    }

    private function validated(Request $request, int $businessProfileId, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'keywords' => ['required', 'string', 'max:1000'],
            'response_text' => ['required', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'all_branches' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer'],
        ]);

        $keywords = collect(explode(',', $data['keywords']))
            ->map(fn ($k) => trim($k))
            ->filter()
            ->unique(fn ($k) => ChatbotKeywordReply::normalize($k))
            ->values()
            ->all();

        if (empty($keywords)) {
            throw ValidationException::withMessages([
                'keywords' => 'Escribe al menos una palabra clave.',
            ]);
        }

        $duplicate = $this->findDuplicateKeyword($businessProfileId, $keywords, $ignoreId);
        if ($duplicate) {
            throw ValidationException::withMessages([
                'keywords' => "La palabra clave \"{$duplicate}\" ya está usada en otra configuración. Quítala de ahí primero.",
            ]);
        }

        $manageableIds = BusinessBranch::where('business_profile_id', $businessProfileId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
        $selectedBranchIds = collect($data['branch_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        abort_if($selectedBranchIds->diff($manageableIds)->isNotEmpty(), 403, 'Hay sucursales que no pertenecen a esta empresa.');

        $allBranches = $request->boolean('all_branches') || $manageableIds->isEmpty();

        if (! $allBranches && $selectedBranchIds->isEmpty()) {
            throw ValidationException::withMessages([
                'branch_ids' => 'Selecciona al menos una sucursal o marca "todas las sucursales".',
            ]);
        }

        return [
            'keywords' => $keywords,
            'response_text' => trim($data['response_text']),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
            'all_branches' => $allBranches,
            'branch_ids' => $selectedBranchIds->all(),
        ];
    }

    /** Un mismo bocablo no puede repetirse en dos configuraciones de la misma empresa: si no, ¿cuál gana? */
    private function findDuplicateKeyword(int $businessProfileId, array $keywords, ?int $ignoreId): ?string
    {
        $normalizedNew = array_map(fn ($k) => ChatbotKeywordReply::normalize($k), $keywords);

        $existing = ChatbotKeywordReply::query()
            ->where('business_profile_id', $businessProfileId)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->get(['id', 'keywords']);

        foreach ($existing as $entry) {
            foreach ((array) $entry->keywords as $keyword) {
                if (in_array(ChatbotKeywordReply::normalize((string) $keyword), $normalizedNew, true)) {
                    return $keyword;
                }
            }
        }

        return null;
    }

    private function syncBranches(ChatbotKeywordReply $entry, array $branchIds): void
    {
        $entry->branches()->sync($entry->all_branches ? [] : $branchIds);
    }
}
