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
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&amp;family=Inter:wght@400;500;600&amp;family=JetBrains+Mono:wght@400&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        background: "var(--color-background)",
                        surface: "var(--color-surface)",
                        workspace: "var(--color-workspace)",
                        sidebar: "var(--color-sidebar)",
                        accent: "var(--color-accent)",
                        secondary: "var(--color-secondary)",
                        highlight: "var(--color-highlight)",
                        success: "var(--color-success)",
                        warning: "var(--color-warning)",
                        danger: "var(--color-danger)",
                        text: "var(--color-text)",
                        muted: "var(--color-muted)",
                        border: "var(--color-border)"
                    },
                    borderRadius: {
                        "2xl": "24px"
                    },
                    fontFamily: {
                        sans: ["Inter", "sans-serif"],
                        display: ["Outfit", "sans-serif"],
                        mono: ["JetBrains Mono", "monospace"]
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --color-background: #F8FAFC;
            --color-surface: #FFFFFF;
            --color-surface-rgb: 255, 255, 255;
            --color-workspace: #F1F5F9;
            --color-sidebar: #E2E8F0;
            --color-accent: #059669;
            --color-secondary: #0891B2;
            --color-highlight: #0D9488;
            --color-success: #16A34A;
            --color-warning: #D97706;
            --color-danger: #DC2626;
            --color-text: #0F172A;
            --color-muted: #475569;
            --color-border: rgba(0, 0, 0, 0.08);
        }

        .dark {
            --color-background: #071018;
            --color-surface: #0F172A;
            --color-surface-rgb: 15, 23, 42;
            --color-workspace: #111827;
            --color-sidebar: #0B1323;
            --color-accent: #00C896;
            --color-secondary: #22D3EE;
            --color-highlight: #2DD4BF;
            --color-success: #22C55E;
            --color-warning: #F59E0B;
            --color-danger: #EF4444;
            --color-text: #F8FAFC;
            --color-muted: #94A3B8;
            --color-border: rgba(255, 255, 255, 0.08);
        }

        body {
            background-color: var(--color-background);
            color: var(--color-text);
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .glass-surface {
            background: rgba(var(--color-surface-rgb), 0.65);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--color-border);
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
        :root:not(.dark) input,
        :root:not(.dark) select,
        :root:not(.dark) textarea {
            background-color: #FFFFFF;
            color: #0F172A;
            border-color: rgba(0, 0, 0, 0.18);
        }

        /* Inputs that use Tailwind's bg-background will inherit #F8FAFC —
           override to white for better contrast against the page surface. */
        :root:not(.dark) input[class*="bg-background"],
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
            border-color: var(--color-accent);
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

        /* border-border in light mode: make it clearly visible */
        :root:not(.dark) input.border-border,
        :root:not(.dark) select.border-border,
        :root:not(.dark) textarea.border-border {
            border-color: rgba(0, 0, 0, 0.2) !important;
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
            background: var(--color-surface);
            border: 1px solid var(--color-border);
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
        .modal-overlay.active .modal-container {
            transform: scale(1);
        }
        :root:not(.dark) .modal-container {
            background: #FFFFFF;
            color: #0F172A;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
    </style>
</head>
