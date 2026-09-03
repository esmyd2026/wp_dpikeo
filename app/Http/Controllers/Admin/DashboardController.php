<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesReportPeriod;
use App\Http\Controllers\Controller;
use App\Models\WhatsappCart;
use App\Models\WhatsappMessage;
use App\Support\CompanyContext;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ResolvesReportPeriod;

    public function index(Request $request)
    {
        $context = CompanyContext::current();
        $businessProfileId = $context->businessProfileId();

        $quickStats = [
            'orders_today' => WhatsappCart::reportable()->forActiveCompany()->whereDate('created_at', today())->count(),
            'messages_today' => WhatsappMessage::whereDate('created_at', today())
                ->when($businessProfileId, fn ($q) => $q->whereHas(
                    'contact',
                    fn ($c) => $c->where('business_profile_id', $businessProfileId)
                ))
                ->count(),
            'pending_orders' => WhatsappCart::reportable()->forActiveCompany()->where('status', WhatsappCart::STATUS_PENDING)->count(),
        ];

        $chatbotConfig = $context->chatbotConfig();
        $dashboardTitle = $chatbotConfig?->dashboard_title
            ?? (($context->company?->name ?? 'Panel') . ' · Centro de operación');
        $dashboardSubtitle = $chatbotConfig?->dashboard_subtitle
            ?? 'Gestiona pedidos, catálogo y atención por WhatsApp.';

        return view('admin.dashboard', compact('quickStats', 'dashboardTitle', 'dashboardSubtitle'));
    }
}
