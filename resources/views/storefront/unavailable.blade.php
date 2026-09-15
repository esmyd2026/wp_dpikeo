<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="{{ $settings->primary_color }}">
    <link rel="icon" href="{{ $settings->faviconUrl() ?: asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ $settings->faviconUrl() ?: asset('favicon.svg') }}">
    <title>{{ $company->name }}</title>
</head>
<body style="margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#fafafa;font-family:system-ui;color:#222">
<main style="width:min(520px,100%);text-align:center;background:#fff;border-radius:20px;padding:36px;box-shadow:0 14px 45px rgba(0,0,0,.08);border-top:6px solid {{ $settings->primary_color }}">
    @if($settings->logoUrl())<img src="{{ $settings->logoUrl() }}" alt="{{ $company->name }}" style="width:100px;height:100px;object-fit:contain">@endif
    <h1 style="margin:18px 0 10px">Estamos preparando la tienda</h1>
    <p style="margin:0;color:#666;line-height:1.55">El catálogo de {{ $company->name }} estará disponible muy pronto.</p>
    <a href="{{ route('login') }}" style="display:inline-block;margin-top:24px;padding:12px 18px;border-radius:9px;background:{{ $settings->accent_color }};color:#222;text-decoration:none;font-weight:750">Acceso del personal</a>
</main>
</body>
</html>
