<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page not found — {{ config('app.name', 'NewsBlogify AI') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-background p-6 text-text">
    <main class="w-full max-w-xl rounded-2xl border border-border bg-surface p-8 text-center shadow-2xl" aria-labelledby="error-title">
        <p class="font-mono text-sm font-semibold uppercase tracking-[0.3em] text-accent">Error 404</p>
        <h1 id="error-title" class="mt-3 font-display text-4xl font-bold">This page could not be found</h1>
        <p class="mx-auto mt-4 max-w-md text-sm leading-6 text-muted">
            The address may be incorrect, or the workspace may have moved. Return to a known page and continue your newsroom workflow.
        </p>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="mt-7 inline-flex items-center justify-center rounded-xl bg-accent px-5 py-3 text-sm font-semibold text-background transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 focus:ring-offset-background">
            {{ auth()->check() ? 'Return to dashboard' : 'Go to login' }}
        </a>
    </main>
</body>
</html>
