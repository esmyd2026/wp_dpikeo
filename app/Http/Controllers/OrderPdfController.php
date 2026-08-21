<?php

namespace App\Http\Controllers;

use App\Models\WhatsappCart;
use App\Services\OrderPdfService;
use Illuminate\Http\Response;

class OrderPdfController extends Controller
{
    public function downloadAdmin(int $id, OrderPdfService $pdf): Response
    {
        $order = WhatsappCart::reportable()->findOrFail($id);

        return $pdf->download($order);
    }

    public function downloadSigned(int $order, OrderPdfService $pdf): Response
    {
        $record = WhatsappCart::reportable()->findOrFail($order);

        return $pdf->download($record);
    }
}
