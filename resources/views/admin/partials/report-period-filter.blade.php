@php
    $action = $action ?? url()->current();
@endphp
<form class="dash-filter" method="get" action="{{ $action }}">
    <input type="hidden" name="period" id="period-preset" value="{{ $periodPreset ?? 'month' }}">
    <div class="btn-group btn-group-sm me-1" role="group">
        @foreach(['month' => 'Este mes', '7d' => '7 días', '30d' => '30 días', '90d' => '90 días'] as $key => $label)
            <button type="button"
                class="btn {{ ($periodPreset ?? 'month') === $key ? 'btn-success' : 'btn-outline-secondary' }} period-preset-btn"
                data-period="{{ $key }}">{{ $label }}</button>
        @endforeach
    </div>
    <input type="date" name="from" value="{{ $from->format('Y-m-d') }}">
    <span style="color:#adb5bd">—</span>
    <input type="date" name="to" value="{{ $to->format('Y-m-d') }}">
    <button type="submit"><i class="fas fa-filter me-1"></i> Filtrar</button>
</form>

@once
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.period-preset-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const form = this.closest('form');
                form.querySelector('#period-preset').value = this.dataset.period;
                form.querySelectorAll('input[name="from"], input[name="to"]').forEach(function (el) {
                    el.removeAttribute('name');
                });
                form.submit();
            });
        });
    });
    </script>
    @endpush
@endonce
