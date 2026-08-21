<?php



namespace App\Http\Controllers;



use App\Services\PricingService;

use Illuminate\Http\RedirectResponse;

use Illuminate\View\View;



class PricingController extends Controller

{

    public function __construct(

        private readonly PricingService $pricing

    ) {}



    public function index(): View

    {

        return view('pricing.planes', $this->pricingViewData());

    }



    public function checkout(string $plan): RedirectResponse

    {

        $plans = config('pricing.plans', []);



        if (!isset($plans[$plan])) {

            abort(404);

        }



        return redirect()->away($this->whatsappUrl($plan));

    }



    private function pricingViewData(): array

    {

        $plans = config('pricing.plans', []);

        $rates = $this->pricing->allRatesWithMarkup();

        $scenarios = config('pricing.meta_scenarios', []);



        foreach ($scenarios as &$scenario) {

            $scenarioCounts = [

                'service' => $scenario['service_conv'],

                'utility' => $scenario['utility_conv'],

                'marketing' => $scenario['marketing_conv'],

                'authentication' => 0,

            ];

            $scenario['estimate_min'] = $this->pricing->estimateCost($scenarioCounts, 'min');

            $scenario['estimate_max'] = $this->pricing->estimateCost($scenarioCounts, 'max');

        }

        unset($scenario);

        $enabledCategories = $this->pricing->enabledCategories();
        $marketingEnabled = $this->pricing->isCategoryEnabled('marketing');

        $metaScenarios = collect($scenarios)
            ->when(!$marketingEnabled, fn ($c) => $c->reject(fn ($s) => ($s['id'] ?? '') === 'active'))
            ->values()
            ->all();

        return [
            'plans' => $plans,
            'metaRates' => $rates,
            'metaScenarios' => $metaScenarios,
            'enabledCategories' => $enabledCategories,
            'marketingEnabled' => $marketingEnabled,
            'authenticationEnabled' => $this->pricing->isCategoryEnabled('authentication'),

            'whatsappNumber' => config('pricing.sales_whatsapp'),

            'whatsappUrls' => collect($plans)->mapWithKeys(fn ($_, $key) => [$key => $this->whatsappUrl($key)])->all(),

            'demoBotUrl' => $this->demoBotWhatsappUrl(),

            'demoPanelUrl' => route('login', ['demo' => 1]),

            'demoPanelUser' => config('pricing.demo.panel_user', 'gosorio'),

            'demoPanelPassword' => config('pricing.demo.panel_password', 'go123'),

        ];

    }



    private function demoBotWhatsappUrl(): ?string

    {

        $number = preg_replace(

            '/\D/',

            '',

            config('pricing.demo.whatsapp_number', config('whatsapp.demo_whatsapp_number', ''))

        );



        if ($number === '') {

            return null;

        }



        $message = config('pricing.demo.whatsapp_message', '¡Hola! Quiero probar el demo del bot');



        return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);

    }



    private function whatsappUrl(string $plan): string

    {

        $plans = config('pricing.plans', []);

        $planData = $plans[$plan] ?? null;



        if (!$planData) {

            return 'https://wa.me/' . config('pricing.sales_whatsapp');

        }



        $message = match ($plan) {

            'starter' => "Hola! Me interesa el *Plan Starter* (\$60/mes sin IVA) del bot WhatsApp con panel admin. Quisiera más información para contratar.",

            'pro' => "Hola! Me interesa el *Plan Pro* (\$90/mes sin IVA) del bot WhatsApp con panel admin. Quisiera más información para contratar.",

            'enterprise' => "Hola! Me interesa el *Plan Enterprise* (desde \$130/mes sin IVA) del bot WhatsApp con panel admin. Quisiera una cotización personalizada.",

            default => "Hola! Me interesa contratar el bot WhatsApp con panel admin. Quisiera más información.",

        };



        $number = preg_replace('/\D/', '', config('pricing.sales_whatsapp'));



        return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);

    }

}

