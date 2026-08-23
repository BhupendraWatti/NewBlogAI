<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>NewsBlogify AI - Automation OS</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        // Prevent theme FOUC
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&amp;family=Inter:wght@400;500;600&amp;family=JetBrains+Mono:wght@400&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <style>
        :root {
            --app-background: #F8FAFC;
            --app-surface: #FFFFFF;
            --app-surface-rgb: 255, 255, 255;
            --app-workspace: #F1F5F9;
            --app-sidebar: #E2E8F0;
            --app-accent: #059669;
            --app-secondary: #0891B2;
            --app-highlight: #0D9488;
            --app-success: #16A34A;
            --app-warning: #D97706;
            --app-danger: #DC2626;
            --app-text: #0F172A;
            --app-muted: #475569;
            --app-border: rgba(0, 0, 0, 0.08);
        }

        .dark {
            --app-background: #071018;
            --app-surface: #0F172A;
            --app-surface-rgb: 15, 23, 42;
            --app-workspace: #111827;
            --app-sidebar: #0B1323;
            --app-accent: #00C896;
            --app-secondary: #22D3EE;
            --app-highlight: #2DD4BF;
            --app-success: #22C55E;
            --app-warning: #F59E0B;
            --app-danger: #EF4444;
            --app-text: #F8FAFC;
            --app-muted: #94A3B8;
            --app-border: rgba(255, 255, 255, 0.08);
        }

        body {
            background-color: var(--app-background);
            color: var(--app-text);
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .glass-surface {
            background: rgba(var(--app-surface-rgb), 0.65);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--app-border);
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        .cyber-glow-emerald {
            box-shadow: 0 0 20px rgba(0, 200, 150, 0.15);
        }
        .cyber-glow-cyan {
            box-shadow: 0 0 20px rgba(34, 211, 238, 0.15);
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .active-tab-glow {
            box-shadow: 0 -2px 10px rgba(34, 211, 238, 0.2) inset;
        }

        /* ── Light Theme: Form Controls ───────────────────────────────────────
           Scoped to :root:not(.dark) so dark theme is completely unaffected.
           Fixes: background, text colour, border, placeholder, focus state,
           select option background, scrollbar thumb.
        ──────────────────────────────────────────────────────────────────── */
        :root:not(.dark) input:not([type="checkbox"]):not([type="radio"]),
        :root:not(.dark) select,
        :root:not(.dark) textarea {
            background-color: #FFFFFF;
            color: #0F172A;
            border-color: rgba(0, 0, 0, 0.18);
        }

        /* Inputs that use Tailwind's bg-background will inherit #F8FAFC —
           override to white for better contrast against the page surface. */
        :root:not(.dark) input:not([type="checkbox"]):not([type="radio"])[class*="bg-background"],
        :root:not(.dark) select[class*="bg-background"],
        :root:not(.dark) textarea[class*="bg-background"] {
            background-color: #FFFFFF !important;
        }

        /* Fix hardcoded dark hex bg-[#071018] on inputs/selects/textareas */
        :root:not(.dark) input[style*="background"],
        :root:not(.dark) select[style*="background"],
        :root:not(.dark) textarea[style*="background"] {
            background-color: #FFFFFF;
        }

        /* Placeholder: accessible mid-grey, not washed-out */
        :root:not(.dark) input::placeholder,
        :root:not(.dark) textarea::placeholder {
            color: #64748B;
            opacity: 1;
        }

        /* Focus ring: consistent accent-coloured border */
        :root:not(.dark) input:focus,
        :root:not(.dark) select:focus,
        :root:not(.dark) textarea:focus {
            outline: none;
            border-color: var(--app-accent);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
        }

        /* Select option dropdown: white bg / dark text in light mode */
        :root:not(.dark) select option {
            background-color: #FFFFFF;
            color: #0F172A;
        }

        /* Tailwind bg-[#071018] override for all form controls in light mode.
           Since Tailwind CDN generates inline class rules, we must use a
           higher-specificity selector to override the hardcoded dark colour. */
        :root:not(.dark) input.bg-\[#071018\],
        :root:not(.dark) select.bg-\[#071018\],
        :root:not(.dark) textarea.bg-\[#071018\] {
            background-color: #FFFFFF !important;
            color: #0F172A !important;
        }

        /* border-border in light mode: make it clearly visible.
           Checkboxes and radios are excluded — their border is managed by Tailwind's
           focus/checked states and must NOT be overridden here. */
        :root:not(.dark) input.border-border:not([type="checkbox"]):not([type="radio"]),
        :root:not(.dark) select.border-border,
        :root:not(.dark) textarea.border-border {
            border-color: rgba(0, 0, 0, 0.2) !important;
        }

        /* Checkbox / radio tint in light mode — Tailwind's text-accent maps to
           accent-color, ensuring the checked fill colour is the brand accent green. */
        :root:not(.dark) input[type="checkbox"],
        :root:not(.dark) input[type="radio"] {
            accent-color: var(--app-accent, #059669);
            border-color: rgba(0, 0, 0, 0.3);
            width: 1rem;
            height: 1rem;
        }

        /* Scrollbar thumb: use dark tint in light mode instead of white tint */
        :root:not(.dark) .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.15);
        }
        :root:not(.dark) .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.25);
        }

        /* Code/mono output panes using bg-[#071018] in light mode */
        :root:not(.dark) [id="prompt-test-output-window"],
        :root:not(.dark) [id="gen-output"] {
            background-color: #F8FAFC;
            color: #0F172A;
            border-color: rgba(0, 0, 0, 0.15);
        }

        /* ─── Light Mode Specific Variable Overrides ─── */
        :root:not(.dark) .border-border {
            border-color: rgba(0, 0, 0, 0.12) !important;
        }
        :root:not(.dark) .bg-surface\/50 {
            background-color: #F8FAFC !important;
        }
        :root:not(.dark) .bg-background {
            background-color: #FFFFFF !important;
        }
        :root:not(.dark) .sheet-overlay {
            background: rgba(15, 23, 42, 0.35);
        }

        /* ── Global Modal Styling ────────────────────────────────────────── */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(12px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }
        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .modal-container {
            background: var(--app-surface);
            border: 1px solid var(--app-border);
            width: 550px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);
            transform: scale(0.95);
            transition: transform 0.25s ease;
        }
        :root:not(.dark) .modal-container {
            background: #FFFFFF;
            color: #0F172A;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }

        /* ─── Premium Slide-Over Sheet (Drawer) ────────────────────────────── */
        .sheet-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            display: flex;
            justify-content: flex-end;
            align-items: stretch;
            z-index: 100;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .sheet-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .sheet-container {
            background: var(--app-surface);
            border-left: 1px solid var(--app-border);
            width: 640px;
            max-width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
            box-shadow: -10px 0 35px rgba(0, 0, 0, 0.4);
            transform: translateX(100%);
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .sheet-overlay.active .sheet-container {
            transform: translateX(0);
        }
        :root:not(.dark) .sheet-container {
            background: #FFFFFF;
            color: #0F172A;
            box-shadow: -10px 0 35px rgba(0, 0, 0, 0.08);
        }
    </style>
</head>
