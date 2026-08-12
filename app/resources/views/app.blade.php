<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#432f44">
    <meta name="description" content="A review-gated multilingual maternity learning resource router with transparent AI evidence.">
    <meta name="robots" content="index,follow">
    <link rel="icon" href="/navigator-icon.svg" type="image/svg+xml">
    <title inertia>{{ config('app.name', 'Explainable Maternity Information Router') }}</title>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
