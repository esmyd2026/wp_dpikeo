<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappMessageFailure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageFailuresController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'unresolved');

        $query = WhatsappMessageFailure::with(['contact:id,name,phone_number', 'resolvedByUser:id,name'])
            ->latest();

        if ($status === 'unresolved') {
            $query->unresolved();
        } elseif ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        $failures = $query->paginate(20)->withQueryString();

        $stats = [
            'unresolved' => WhatsappMessageFailure::unresolved()->count(),
            'total_24h' => WhatsappMessageFailure::where('created_at', '>=', now()->subDay())->count(),
        ];

        return view('admin.message-failures.index', compact('failures', 'stats', 'status'));
    }

    public function resolve(Request $request, WhatsappMessageFailure $failure): RedirectResponse
    {
        if (!$failure->isResolved()) {
            $failure->markResolved($request->user()?->id);
        }

        return redirect()->back()->with('success', 'Fallo marcado como resuelto.');
    }

    /**
     * Sondeo liviano para la campana global del panel (sin websockets).
     */
    public function poll()
    {
        $failures = WhatsappMessageFailure::unresolved()
            ->with('contact:id,name,phone_number')
            ->latest()
            ->limit(15)
            ->get();

        return response()->json([
            'success' => true,
            'count' => WhatsappMessageFailure::unresolved()->count(),
            'failures' => $failures->map(fn (WhatsappMessageFailure $f) => [
                'id' => $f->id,
                'contact_name' => $f->contact->name ?? null,
                'phone_number' => $f->contact->phone_number ?? $f->phone_number,
                'message_type' => $f->message_type,
                'source' => $f->source,
                'error_message' => $f->error_message,
                'created_at' => $f->created_at?->toIso8601String(),
                'alert_token' => (string) $f->id,
            ])->values(),
        ]);
    }
}
