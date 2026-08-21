<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesReportPeriod;
use App\Http\Controllers\Controller;
use App\Models\WhatsappCart;
use App\Models\WhatsappMessage;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ResolvesReportPeriod;

    public function index(Request $request)
    {
        $quickStats = [
            'orders_today' => WhatsappCart::reportable()->whereDate('created_at', today())->count(),
            'messages_today' => WhatsappMessage::whereDate('created_at', today())->count(),
            'pending_orders' => WhatsappCart::reportable()->where('status', WhatsappCart::STATUS_PENDING)->count(),
        ];

        return view('admin.dashboard', compact('quickStats'));
    }
}
