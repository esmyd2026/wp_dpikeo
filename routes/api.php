<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsappWebhookController;
use App\Http\Controllers\WhatsappFlowEndpointController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// WhatsApp Webhook Routes
Route::prefix('whatsapp')->group(function () {
    // Ruta para la verificación del webhook
    Route::get('webhook', [WhatsappWebhookController::class, 'verify']);

    // Ruta para recibir las actualizaciones. Límite generoso: Meta reenvía el
    // tráfico de muchos usuarios desde pocas IPs, pero sigue siendo un tope
    // ante abuso (payloads forjados, reintentos en bucle, etc.).
    Route::post('webhook', [WhatsappWebhookController::class, 'webhook'])
        ->middleware('throttle:300,1');

    // Endpoint cifrado para validaciones/health checks de WhatsApp Flows.
    Route::post('flows/endpoint', WhatsappFlowEndpointController::class)
        ->middleware('throttle:30,1');
});
