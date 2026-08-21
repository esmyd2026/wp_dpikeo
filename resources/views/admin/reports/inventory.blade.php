@extends('admin.layouts.app')

@section('header', 'Inventario')

@section('content')
<div class="container-fluid" style="max-width:1180px">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
        <div>
            <h2 class="mb-1 fw-bold">Inventario operativo</h2>
            <p class="text-muted mb-0">El stock se reserva al confirmar un pedido y se libera si se cancela.</p>
        </div>
        <a href="{{ route('admin.products.index') }}" class="btn btn-dark"><i class="fas fa-box-open me-1"></i> Gestionar productos</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted text-uppercase fw-bold">Unidades disponibles</small><div class="fs-3 fw-bold">{{ number_format($stats['units']) }}</div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted text-uppercase fw-bold">Stock bajo</small><div class="fs-3 fw-bold text-warning">{{ $stats['low'] }}</div><small class="text-muted">De 1 a 5 unidades</small></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted text-uppercase fw-bold">Agotados</small><div class="fs-3 fw-bold text-danger">{{ $stats['out'] }}</div><small class="text-muted">No se muestran al cliente</small></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted text-uppercase fw-bold">Valor referencial</small><div class="fs-3 fw-bold">${{ number_format($stats['value'], 2) }}</div><small class="text-muted">Según precio de venta</small></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3"><strong>Existencias por producto</strong></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Producto</th><th>Categoría</th><th>SKU</th><th class="text-end">Stock</th><th class="text-end">Precio</th><th class="text-end">Valor</th></tr></thead>
            <tbody>@forelse($products as $product)
                <tr>
                    <td class="fw-semibold">{{ $product->name }} @unless($product->is_active)<span class="badge bg-secondary ms-1">Inactivo</span>@endunless</td>
                    <td>{{ $product->menuCategory?->title ?? '—' }}</td><td><code>{{ $product->sku }}</code></td>
                    <td class="text-end"><span class="badge {{ $product->stock <= 0 ? 'bg-danger' : ($product->stock <= 5 ? 'bg-warning text-dark' : 'bg-success') }}">{{ $product->stock }}</span></td>
                    <td class="text-end">${{ number_format($product->price, 2) }}</td><td class="text-end">${{ number_format($product->stock * $product->price, 2) }}</td>
                </tr>
            @empty<tr><td colspan="6" class="text-center text-muted py-4">No hay productos registrados.</td></tr>@endforelse</tbody>
        </table></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3"><strong>Últimos movimientos</strong></div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Fecha</th><th>Producto</th><th>Movimiento</th><th class="text-end">Cambio</th><th class="text-end">Antes → después</th><th>Origen</th></tr></thead>
            <tbody>@forelse($movements as $movement)
                <tr><td>{{ $movement->created_at?->format('d/m/Y H:i') }}</td><td>{{ $movement->product?->name ?? 'Producto eliminado' }}</td>
                    <td>{{ ['sale_reservation' => 'Reserva por pedido', 'sale_release' => 'Liberación por cancelación', 'manual_adjustment' => 'Ajuste manual'][$movement->type] ?? $movement->type }}</td>
                    <td class="text-end fw-bold {{ $movement->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                    <td class="text-end">{{ $movement->stock_before }} → {{ $movement->stock_after }}</td>
                    <td>{{ $movement->order?->getOrderNumber() ?? ($movement->user?->name ?? 'Panel') }}</td></tr>
            @empty<tr><td colspan="6" class="text-center text-muted py-4">Aún no existen movimientos de inventario.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
</div>
@endsection
