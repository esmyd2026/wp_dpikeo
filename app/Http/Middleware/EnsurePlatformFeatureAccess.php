<?php

namespace App\Http\Middleware;

use App\Services\PlatformBillingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformFeatureAccess
{
    public function __construct(private readonly PlatformBillingService $billing) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        $blocked = match ($feature) {
            'chat' => $this->billing->isChatSuspended($user),
            'orders' => $this->billing->isOrdersSuspended($user),
            default => false,
        };

        if (!$blocked) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Este módulo está suspendido. Revisa Parámetros de plataforma o tu billetera.',
                'suspended' => true,
            ], 403);
        }

        return redirect()
            ->route('admin.dashboard')
            ->with('error', 'El acceso a este módulo está suspendido. Desactiva la suspensión manual en Parámetros o regulariza el pago en Billetera.');
    }
}
