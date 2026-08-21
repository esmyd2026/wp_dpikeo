<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\WhatsappPrice;
use Illuminate\View\View;

class InventoryReportController extends Controller
{
    public function index(): View
    {
        $products = WhatsappPrice::query()
            ->with('menuCategory:id,title')
            ->orderBy('stock')
            ->orderBy('name')
            ->get();

        $stats = [
            'units' => (int) $products->sum('stock'),
            'out' => $products->where('stock', '<=', 0)->count(),
            'low' => $products->filter(fn (WhatsappPrice $p) => $p->stock > 0 && $p->stock <= 5)->count(),
            'value' => (float) $products->sum(fn (WhatsappPrice $p) => $p->stock * $p->price),
        ];

        $movements = InventoryMovement::query()
            ->with(['product:id,name,sku', 'order:id,metadata', 'user:id,name'])
            ->latest()
            ->limit(40)
            ->get();

        return view('admin.reports.inventory', compact('products', 'stats', 'movements'));
    }
}
