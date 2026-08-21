<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DemoResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DemoResetController extends Controller
{
    public function store(Request $request, DemoResetService $demoReset): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'accepted'],
        ]);

        $stats = $demoReset->reset();

        return redirect()
            ->back()
            ->with('success', sprintf(
                'Demo reiniciada: %d mensajes, %d pedidos y %d conversaciones eliminados. %d contactos restablecidos.',
                $stats['messages_deleted'],
                $stats['carts_deleted'],
                $stats['conversations_deleted'],
                $stats['contacts_reset'],
            ));
    }
}
