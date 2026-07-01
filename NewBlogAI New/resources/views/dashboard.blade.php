<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>NewsBlogify AI - Automation OS</title>
    <script>
        // Prevent theme FOUC
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
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
    </style>
</head>
<body class="font-sans antialiased overflow-hidden min-h-screen flex h-screen w-full select-none bg-background text-text">

    <!-- PERSISTENT LEFT SIDEBAR NAVIGATION -->
    <aside class="w-64 bg-sidebar border-r border-border flex flex-col justify-between py-6 px-4 shrink-0 z-40">
        <div class="space-y-8">
            <!-- Brand Identity -->
            <div class="flex items-center gap-3 px-2">
                <div class="w-9 h-9 rounded-xl bg-accent flex items-center justify-center text-background cyber-glow-emerald">
                    <span class="material-symbols-outlined font-bold text-lg">terminal</span>
                </div>
                <div>
                    <h1 class="font-display font-bold text-lg leading-tight tracking-tight">Automation OS</h1>
                    <p class="text-[11px] text-muted tracking-widest uppercase font-semibold">NewsBlogify AI</p>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="space-y-1.5" id="sidebar-menu">
                <p class="px-2 text-[10px] font-bold text-muted uppercase tracking-widest mb-2">Workspace Nodes</p>
                
                <button onclick="switchWorkspace('dashboard')" data-node="dashboard" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                    <span class="material-symbols-outlined text-lg">grid_view</span>
                    Overview
                </button>

                <button onclick="switchWorkspace('customers')" data-node="customers" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                    <span class="material-symbols-outlined text-lg">group</span>
                    Customers
                </button>

                <button onclick="switchWorkspace('topics')" data-node="topics" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                    <span class="material-symbols-outlined text-lg">topic</span>
                    Topic Engine
                </button>

                <button onclick="switchWorkspace('pipeline')" data-node="pipeline" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                    <span class="material-symbols-outlined text-lg">route</span>
                    Content Pipeline
                </button>

                <button onclick="switchWorkspace('scheduler')" data-node="scheduler" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                    <span class="material-symbols-outlined text-lg">calendar_month</span>
                    Publishing Scheduler
                </button>

                <button onclick="switchWorkspace('fleet')" data-node="fleet" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                    <span class="material-symbols-outlined text-lg">cooking</span>
                    Fleet Monitor
                </button>

                <button onclick="switchWorkspace('sites')" data-node="sites" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                    <span class="material-symbols-outlined text-lg">language</span>
                    WordPress Sites
                </button>

                <button onclick="switchWorkspace('prompts')" data-node="prompts" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                    <span class="material-symbols-outlined text-lg">book</span>
                    Prompt Library
                </button>

                <button onclick="switchWorkspace('providers')" data-node="providers" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                    <span class="material-symbols-outlined text-lg">neurology</span>
                    AI Models &amp; Providers
                </button>

                <button onclick="switchWorkspace('media')" data-node="media" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                    <span class="material-symbols-outlined text-lg">photo_library</span>
                    Creative Media Studio
                </button>

                <button onclick="switchWorkspace('rules')" data-node="rules" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                    <span class="material-symbols-outlined text-lg">settings_suggest</span>
                    Automation Rules
                </button>
            </nav>
        </div>

        <!-- Sidebar Footer -->
        <div class="pt-4 border-t border-border space-y-3">
            <div class="flex items-center gap-3 px-2">
                <div class="w-8 h-8 rounded-full bg-surface border border-border flex items-center justify-center text-accent text-xs font-semibold">
                    SA
                </div>
                <div>
                    <p class="text-xs font-medium">Super Admin</p>
                    <p class="text-[10px] text-muted">Active Node</p>
                </div>
            </div>
            <p class="text-[10px] text-muted px-2 font-mono">v2.4.1-stable</p>
        </div>
    </aside>

    <!-- CORE WORKSPACE CONTAINER -->
    <main class="flex-1 flex flex-col overflow-hidden min-w-0 bg-background relative">
        
        <!-- HEADER PANEL -->
        <header class="h-14 border-b border-border px-6 flex justify-between items-center shrink-0">
            <!-- Breadcrumbs -->
            <div class="flex items-center gap-2 text-xs font-mono text-muted">
                <span class="hover:text-text cursor-pointer">Automation OS</span>
                <span class="material-symbols-outlined text-xs">chevron_right</span>
                <span id="breadcrumb-active" class="text-secondary font-medium">Overview</span>
            </div>

            <!-- Quick Controls -->
            <div class="flex items-center gap-4">
                <!-- Search & Command Palette Hook -->
                <div class="relative w-72 focus-within:w-80 transition-all duration-300">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted text-lg">search</span>
                    <input id="global-search" onkeydown="handleSearchShortcut(event)" class="w-full bg-surface border border-border rounded-xl py-1.5 pl-10 pr-8 text-xs font-mono text-text placeholder-muted focus:outline-none focus:border-accent focus:ring-0" placeholder="Search commands... (Ctrl+K)" type="text"/>
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[9px] font-mono text-muted border border-border px-1 rounded">⌘K</span>
                </div>

                <!-- Theme Toggle Button -->
                <button onclick="toggleTheme()" class="p-2 text-muted hover:text-text bg-white/5 rounded-xl border border-border transition flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg" id="theme-toggle-icon">light_mode</span>
                </button>

                <div class="h-5 w-px bg-border"></div>

                <!-- Notifications Button -->
                <button class="p-2 text-muted hover:text-text bg-white/5 rounded-xl border border-border transition relative">
                    <span class="material-symbols-outlined text-lg">notifications</span>
                    <span class="absolute top-1.5 right-1.5 w-1.5 h-1.5 bg-accent rounded-full animate-ping"></span>
                </button>
            </div>
        </header>

        <!-- HORIZONTAL WORKSPACE NAVIGATION TABS -->
        <section class="h-11 border-b border-border px-6 bg-surface/30 flex items-center justify-between shrink-0">
            <div class="flex gap-1 h-full" id="workspace-tabs">
                <button onclick="switchTab('overview')" data-tab="overview" class="px-4 h-full text-xs font-medium text-muted hover:text-text border-b-2 border-transparent transition-all flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">visibility</span> Overview
                </button>
                <button onclick="switchTab('config')" data-tab="config" class="px-4 h-full text-xs font-medium text-muted hover:text-text border-b-2 border-transparent transition-all flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">tune</span> Configuration
                </button>
                <button onclick="switchTab('history')" data-tab="history" class="px-4 h-full text-xs font-medium text-muted hover:text-text border-b-2 border-transparent transition-all flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">history</span> History
                </button>
                <button onclick="switchTab('logs')" data-tab="logs" class="px-4 h-full text-xs font-medium text-muted hover:text-text border-b-2 border-transparent transition-all flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">code</span> Logs &amp; Events
                </button>
                <button onclick="switchTab('settings')" data-tab="settings" class="px-4 h-full text-xs font-medium text-muted hover:text-text border-b-2 border-transparent transition-all flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">settings</span> Settings
                </button>
            </div>
            
            <div class="text-[10px] text-muted font-mono flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span> SYSTEM: ONLINE
            </div>
        </section>

        <!-- MAIN VIEW WRAPPER -->
        <div class="flex-1 flex overflow-hidden relative">
            
            <!-- CENTRAL WORKSPACE SPACE -->
            <div class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6" id="workspace-content">
                
                <!-- 1. OVERVIEW DASHBOARD WORKSPACE -->
                <div id="node-dashboard" class="workspace-pane space-y-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Console Terminal</h2>
                            <p class="text-xs text-muted">System node metrics, credits telemetry, and automated tasks status.</p>
                        </div>
                    </div>

                    <!-- Telemetry KPI Cards -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">WordPress Fleet</p>
                            <h3 class="text-3xl font-display font-bold" id="stats-fleet">482</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent flex items-center gap-1">
                                <span class="w-1 h-1 rounded-full bg-accent"></span> 99.8% Connection Uptime
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Published Today</p>
                            <h3 class="text-3xl font-display font-bold">1,842</h3>
                            <div class="mt-2 text-[10px] font-mono text-secondary flex items-center gap-1">
                                <span class="w-1 h-1 rounded-full bg-secondary"></span> Average 4.2 mins/article
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">AI Tokens Balance</p>
                            <h3 class="text-3xl font-display font-bold">84.2M</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Renewal due in 12 days</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Health Status</p>
                            <h3 class="text-3xl font-display font-bold text-accent">98%</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent">Optimal pipeline capacity</div>
                        </div>
                    </div>

                    <!-- Main Grid -->
                    <div class="grid grid-cols-3 gap-6">
                        <!-- Uptime Telemetry Chart Mock -->
                        <div class="col-span-2 glass-surface rounded-2xl p-5 space-y-4">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Fleet Sync Telemetry (24h)</h4>
                                <span class="text-[10px] font-mono text-secondary">Active nodes</span>
                            </div>
                            <div class="h-44 bg-[#071018] rounded-xl border border-border flex items-end justify-between p-4 relative overflow-hidden">
                                <!-- Graph bars -->
                                <div class="w-8 bg-accent/20 h-2/3 hover:bg-accent transition rounded-t"></div>
                                <div class="w-8 bg-accent/20 h-4/5 hover:bg-accent transition rounded-t"></div>
                                <div class="w-8 bg-accent/20 h-1/2 hover:bg-accent transition rounded-t"></div>
                                <div class="w-8 bg-accent/20 h-3/4 hover:bg-accent transition rounded-t"></div>
                                <div class="w-8 bg-accent/20 h-5/6 hover:bg-accent transition rounded-t"></div>
                                <div class="w-8 bg-accent/20 h-full hover:bg-accent transition rounded-t"></div>
                                <div class="w-8 bg-accent/20 h-2/3 hover:bg-accent transition rounded-t"></div>
                                <div class="w-8 bg-accent/20 h-4/5 hover:bg-accent transition rounded-t"></div>
                                <div class="w-8 bg-accent/20 h-3/4 hover:bg-accent transition rounded-t"></div>
                            </div>
                        </div>

                        <!-- Recent Timeline Events -->
                        <div class="glass-surface rounded-2xl p-5 space-y-4">
                            <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Pipeline Activities</h4>
                            <div class="space-y-3 font-mono text-[11px]">
                                <div class="flex gap-2">
                                    <span class="text-accent">●</span>
                                    <div>
                                        <p class="text-text">Site techcrunch.com synced topics</p>
                                        <p class="text-muted">12 seconds ago</p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <span class="text-secondary">●</span>
                                    <div>
                                        <p class="text-text">Stripe billing renewal completed</p>
                                        <p class="text-muted">3 mins ago</p>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <span class="text-danger">●</span>
                                    <div>
                                        <p class="text-text">WordPress sync connection timeout</p>
                                        <p class="text-muted">15 mins ago</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. CUSTOMERS WORKSPACE -->
                <div id="node-customers" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Customer Registry</h2>
                            <p class="text-xs text-muted">Manage active SaaS clients, adjust credit caps, and write billing notes.</p>
                        </div>
                        <button onclick="launchCreationWizard('customer')" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                            <span class="material-symbols-outlined text-sm font-bold">add</span> Register Customer
                        </button>
                    </div>

                    <!-- Datagrid Table -->
                    <div class="glass-surface rounded-2xl overflow-hidden border border-border">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase tracking-wider">
                                    <th class="p-3 pl-5">Company</th>
                                    <th class="p-3">Owner</th>
                                    <th class="p-3">Email</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3">Health Score</th>
                                    <th class="p-3 text-right pr-5">Operations</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-xs font-mono">
                                <tr onclick="inspectElement('customer', 'Acme Corp', 'trial', 'High', 'system_admin')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5 font-medium text-text">Acme Corp</td>
                                    <td class="p-3 text-muted">John Doe</td>
                                    <td class="p-3 text-muted">john@acme.com</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[9px]">trial</span></td>
                                    <td class="p-3 text-accent">98/100</td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">View Pipeline</button>
                                    </td>
                                </tr>
                                <tr onclick="inspectElement('customer', 'Stark Industries', 'active', 'High', 'system_admin')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5 font-medium text-text">Stark Industries</td>
                                    <td class="p-3 text-muted">Tony Stark</td>
                                    <td class="p-3 text-muted">tony@stark.com</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]">active</span></td>
                                    <td class="p-3 text-accent">100/100</td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">View Pipeline</button>
                                    </td>
                                </tr>
                                <tr onclick="inspectElement('customer', 'Cyberdyne Systems', 'suspended', 'Low', 'security_officer')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5 font-medium text-text">Cyberdyne Systems</td>
                                    <td class="p-3 text-muted">Miles Dyson</td>
                                    <td class="p-3 text-muted">dyson@cyberdyne.com</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-danger/20 text-danger border border-danger/30 text-[9px]">suspended</span></td>
                                    <td class="p-3 text-danger">35/100</td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">View Pipeline</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 3. WORDPRESS FLEET MONITOR -->
                <div id="node-fleet" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Fleet Telemetry Console</h2>
                            <p class="text-xs text-muted">Live synchronization status and REST endpoints for connected blog networks.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-4 gap-4">
                        <div class="bg-surface rounded-xl p-4 border border-border">
                            <p class="text-[10px] font-mono text-muted uppercase">Connected</p>
                            <h4 class="text-2xl font-bold">124</h4>
                        </div>
                        <div class="bg-surface rounded-xl p-4 border border-border">
                            <p class="text-[10px] font-mono text-muted uppercase">Uptime Avg</p>
                            <h4 class="text-2xl font-bold text-accent">99.98%</h4>
                        </div>
                        <div class="bg-surface rounded-xl p-4 border border-border">
                            <p class="text-[10px] font-mono text-muted uppercase">Errors Today</p>
                            <h4 class="text-2xl font-bold text-danger">3</h4>
                        </div>
                        <div class="bg-surface rounded-xl p-4 border border-border">
                            <p class="text-[10px] font-mono text-muted uppercase">Sync Duration</p>
                            <h4 class="text-2xl font-bold text-secondary">3.4s</h4>
                        </div>
                    </div>

                    <div class="glass-surface rounded-2xl overflow-hidden border border-border">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase">
                                    <th class="p-3 pl-5">WordPress Domain</th>
                                    <th class="p-3">Sync Status</th>
                                    <th class="p-3">Last Synced</th>
                                    <th class="p-3">Error Log</th>
                                    <th class="p-3 text-right pr-5">Control</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-xs font-mono">
                                <tr onclick="inspectElement('site', 'techcrunch.com', 'online', 'High', 'system_admin')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5 text-text font-medium">techcrunch.com</td>
                                    <td class="p-3 text-accent">● Online</td>
                                    <td class="p-3 text-muted">2 mins ago</td>
                                    <td class="p-3 text-muted">None</td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-accent hover:underline">Sync Now</button>
                                    </td>
                                </tr>
                                <tr onclick="inspectElement('site', 'mashable.com', 'online', 'High', 'system_admin')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5 text-text font-medium">mashable.com</td>
                                    <td class="p-3 text-accent">● Online</td>
                                    <td class="p-3 text-muted">14 mins ago</td>
                                    <td class="p-3 text-muted">None</td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-accent hover:underline">Sync Now</button>
                                    </td>
                                </tr>
                                <tr onclick="inspectElement('site', 'engadget.com', 'offline', 'Low', 'system_admin')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5 text-text font-medium">engadget.com</td>
                                    <td class="p-3 text-danger">● Connection Error</td>
                                    <td class="p-3 text-muted">1 hour ago</td>
                                    <td class="p-3 text-danger">REST Timeout</td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-danger hover:underline">Retry</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. WORDPRESS SITES MANAGEMENT -->
                <div id="node-sites" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Ecosystem Site Manager</h2>
                            <p class="text-xs text-muted">Connect, synchronize, and monitor client WordPress sites receiving automated content streams.</p>
                        </div>
                        <button onclick="launchCreationWizard('site')" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                            <span class="material-symbols-outlined text-sm font-bold">add</span> Register Website
                        </button>
                    </div>

                    <!-- Cloud Telemetry Grid -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Connected Sites</p>
                            <h3 class="text-3xl font-display font-bold">482</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span> 479 Online Mappings
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">SSL Health</p>
                            <h3 class="text-3xl font-display font-bold">100%</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent">All domains secured</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Avg Plugin Sync</p>
                            <h3 class="text-3xl font-display font-bold text-accent">v2.4.1</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">All nodes updated</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">API Errors (24h)</p>
                            <h3 class="text-3xl font-display font-bold text-danger">3</h3>
                            <div class="mt-2 text-[10px] font-mono text-danger">REST Timeout exceptions</div>
                        </div>
                    </div>

                    <!-- Search & Filter Options -->
                    <div class="flex flex-wrap items-center gap-3 p-3 bg-surface border border-border rounded-2xl">
                        <div class="relative w-64">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted text-sm">search</span>
                            <input class="w-full bg-background border border-border rounded-xl py-1.5 pl-10 pr-4 text-xs font-mono text-text placeholder-muted focus:outline-none focus:border-accent focus:ring-0" placeholder="Search domains..." type="text"/>
                        </div>
                        <select class="bg-background border border-border text-text text-xs rounded-xl py-1.5 pl-2 pr-6 cursor-pointer focus:ring-accent">
                            <option>All Plugin Versions</option>
                            <option>v2.4.1</option>
                            <option>v2.4.0</option>
                        </select>
                        <select class="bg-background border border-border text-text text-xs rounded-xl py-1.5 pl-2 pr-6 cursor-pointer focus:ring-accent">
                            <option>SSL Secured</option>
                            <option>secured</option>
                            <option>expired</option>
                        </select>
                        <button class="text-xs text-muted hover:text-text font-mono ml-auto">Reset</button>
                    </div>

                    <!-- Sites Datagrid Table -->
                    <div class="glass-surface rounded-2xl overflow-hidden border border-border">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase">
                                    <th class="p-3 pl-5 w-8"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20"/></th>
                                    <th class="p-3">Domain URL</th>
                                    <th class="p-3">WP Version</th>
                                    <th class="p-3">Plugin Version</th>
                                    <th class="p-3">SSL Check</th>
                                    <th class="p-3">Last Sync</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3 text-right pr-5">Operations</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-xs font-mono">
                                <tr onclick="inspectElement('site', 'https://techcrunch.com', 'online', 'v6.2.1', 'key_00294')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20" onclick="event.stopPropagation()"/></td>
                                    <td class="p-3 text-text font-medium">https://techcrunch.com</td>
                                    <td class="p-3 text-muted">WP 6.2.1</td>
                                    <td class="p-3 text-muted">v2.4.1</td>
                                    <td class="p-3 text-accent font-bold">Valid SSL</td>
                                    <td class="p-3 text-muted">8 mins ago</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]">online</span></td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">Inspect</button>
                                    </td>
                                </tr>
                                <tr onclick="inspectElement('site', 'https://mashable.com', 'online', 'v6.1.0', 'key_00122')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20" onclick="event.stopPropagation()"/></td>
                                    <td class="p-3 text-text font-medium">https://mashable.com</td>
                                    <td class="p-3 text-muted">WP 6.1.0</td>
                                    <td class="p-3 text-muted">v2.4.1</td>
                                    <td class="p-3 text-accent font-bold">Valid SSL</td>
                                    <td class="p-3 text-muted">14 mins ago</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]">online</span></td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">Inspect</button>
                                    </td>
                                </tr>
                                <tr onclick="inspectElement('site', 'https://engadget.com', 'offline', 'v5.9.3', 'key_00109')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20" onclick="event.stopPropagation()"/></td>
                                    <td class="p-3 text-text font-medium">https://engadget.com</td>
                                    <td class="p-3 text-muted">WP 5.9.3</td>
                                    <td class="p-3 text-danger font-bold">v2.3.8 (Outdated)</td>
                                    <td class="p-3 text-accent font-bold">Valid SSL</td>
                                    <td class="p-3 text-muted">1 hour ago</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-danger/20 text-danger border border-danger/30 text-[9px]">offline</span></td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">Inspect</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 5. PROMPT ENGINEERING LAB -->
                <div id="node-prompts" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Prompt Engineering Lab</h2>
                            <p class="text-xs text-muted">Create, validate, version, and preview prompt instructions mapping automated generative engines.</p>
                        </div>
                        <button onclick="launchCreationWizard('prompt')" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                            <span class="material-symbols-outlined text-sm font-bold">add</span> Create Template
                        </button>
                    </div>

                    <!-- Split Workspace Panel -->
                    <div class="grid grid-cols-12 gap-6 h-[calc(100vh-220px)] overflow-hidden">
                        
                        <!-- Left Panel: Template List & Search -->
                        <div class="col-span-4 glass-surface rounded-2xl p-4 flex flex-col space-y-4 h-full overflow-hidden bg-surface/30">
                            <!-- Search & Provider Filter -->
                            <div class="space-y-2 shrink-0">
                                <div class="relative">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted text-sm">search</span>
                                    <input class="w-full bg-background border border-border rounded-xl py-1.5 pl-9 pr-4 text-xs font-mono text-text placeholder-muted focus:outline-none focus:border-accent focus:ring-0" placeholder="Filter library..." type="text"/>
                                </div>
                                <div class="flex gap-1.5 text-[10px] font-mono">
                                    <span class="px-2 py-0.5 rounded bg-white/5 border border-border text-text cursor-pointer">OpenAI</span>
                                    <span class="px-2 py-0.5 rounded bg-transparent border border-border text-muted cursor-pointer hover:text-text">Anthropic</span>
                                    <span class="px-2 py-0.5 rounded bg-transparent border border-border text-muted cursor-pointer hover:text-text">Gemini</span>
                                </div>
                            </div>

                            <!-- Templates Stream -->
                            <div class="flex-1 overflow-y-auto custom-scrollbar space-y-2 pr-1">
                                <div onclick="selectPromptTemplate('promt_001', 'Standard Tech Summarizer', 'gpt-4o', 'Summarizer', 'openai')" class="p-3 bg-white/5 border border-accent rounded-xl cursor-pointer hover:border-accent transition group relative">
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-xs font-medium text-text">Tech Summarizer</p>
                                        <span class="text-[9px] font-mono bg-accent/20 text-accent border border-accent/30 px-1.5 py-0.5 rounded">v1.2</span>
                                    </div>
                                    <p class="text-[10px] text-muted line-clamp-1 font-mono">ID: promt_001 | OpenAI GPT-4o</p>
                                </div>

                                <div onclick="selectPromptTemplate('promt_002', 'News Bullet-point Writer', 'claude-3-5', 'Bulletins', 'anthropic')" class="p-3 bg-transparent border border-border rounded-xl cursor-pointer hover:bg-white/5 transition group relative">
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-xs font-medium text-text">News Bullet Writer</p>
                                        <span class="text-[9px] font-mono bg-white/10 text-muted border border-border px-1.5 py-0.5 rounded">v2.0</span>
                                    </div>
                                    <p class="text-[10px] text-muted line-clamp-1 font-mono">ID: promt_002 | Claude 3.5 Sonnet</p>
                                </div>

                                <div onclick="selectPromptTemplate('promt_003', 'Financial Trends Analyst', 'gpt-4', 'Analysis', 'openai')" class="p-3 bg-transparent border border-border rounded-xl cursor-pointer hover:bg-white/5 transition group relative">
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-xs font-medium text-text">Financial Trends Analyst</p>
                                        <span class="text-[9px] font-mono bg-white/10 text-muted border border-border px-1.5 py-0.5 rounded">v1.0</span>
                                    </div>
                                    <p class="text-[10px] text-muted line-clamp-1 font-mono">ID: promt_003 | OpenAI GPT-4</p>
                                </div>
                            </div>
                        </div>

                        <!-- Right Panel: Editor, Testing, Versioning, Analytics -->
                        <div class="col-span-8 glass-surface rounded-2xl flex flex-col h-full overflow-hidden bg-surface/30">
                            <!-- Workspace Navigation Sub-Tabs -->
                            <div class="h-10 border-b border-border px-4 bg-surface/50 flex items-center justify-between shrink-0">
                                <div class="flex gap-2 h-full" id="prompt-sub-tabs">
                                    <button onclick="switchPromptSubTab('editor')" id="prompt-tab-editor" class="px-3 h-full text-xs font-medium text-accent border-b-2 border-accent transition flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-sm">edit</span> Editor
                                    </button>
                                    <button onclick="switchPromptSubTab('tester')" id="prompt-tab-tester" class="px-3 h-full text-xs font-medium text-muted hover:text-text border-b-2 border-transparent transition flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-sm">smart_toy</span> Live Tester
                                    </button>
                                    <button onclick="switchPromptSubTab('history')" id="prompt-tab-history" class="px-3 h-full text-xs font-medium text-muted hover:text-text border-b-2 border-transparent transition flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-sm">history_edu</span> Rollback History
                                    </button>
                                    <button onclick="switchPromptSubTab('analytics')" id="prompt-tab-analytics" class="px-3 h-full text-xs font-medium text-muted hover:text-text border-b-2 border-transparent transition flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-sm">analytics</span> Usage Analytics
                                    </button>
                                </div>
                                <span class="text-[9px] font-mono text-muted uppercase" id="prompt-editor-id">Active: promt_001</span>
                            </div>

                            <!-- Panel Contents Container -->
                            <div class="flex-1 overflow-y-auto custom-scrollbar p-5 space-y-4" id="prompt-pane-content">
                                
                                <!-- Editor Pane -->
                                <div id="prompt-pane-editor" class="prompt-tab-view space-y-4">
                                    <!-- Meta Config Controls -->
                                    <div class="grid grid-cols-3 gap-4 p-4 bg-background border border-border rounded-xl">
                                        <div class="space-y-1">
                                            <span class="text-[9px] font-mono text-muted uppercase">Model Compatibility</span>
                                            <p class="text-xs font-medium text-text" id="prompt-meta-model">GPT-4o</p>
                                        </div>
                                        <div class="space-y-1">
                                            <span class="text-[9px] font-mono text-muted uppercase">Provider</span>
                                            <p class="text-xs font-medium text-accent" id="prompt-meta-provider">OpenAI Core</p>
                                        </div>
                                        <div class="space-y-1">
                                            <span class="text-[9px] font-mono text-muted uppercase">Target Category</span>
                                            <p class="text-xs font-medium text-text" id="prompt-meta-category">Summarizer</p>
                                        </div>
                                    </div>

                                    <!-- Variables Toolbar -->
                                    <div class="space-y-1.5">
                                        <span class="text-[9px] font-mono text-muted uppercase">Placeholders Variables</span>
                                        <div class="flex flex-wrap gap-1">
                                            <span class="px-2 py-0.5 rounded bg-white/5 border border-border text-[9px] font-mono text-muted cursor-pointer hover:border-accent hover:text-text transition">&#123;&#123;topic&#125;&#125;</span>
                                            <span class="px-2 py-0.5 rounded bg-white/5 border border-border text-[9px] font-mono text-muted cursor-pointer hover:border-accent hover:text-text transition">&#123;&#123;keyword&#125;&#125;</span>
                                            <span class="px-2 py-0.5 rounded bg-white/5 border border-border text-[9px] font-mono text-muted cursor-pointer hover:border-accent hover:text-text transition">&#123;&#123;tone&#125;&#125;</span>
                                            <span class="px-2 py-0.5 rounded bg-white/5 border border-border text-[9px] font-mono text-muted cursor-pointer hover:border-accent hover:text-text transition">&#123;&#123;language&#125;&#125;</span>
                                        </div>
                                    </div>

                                    <!-- Code Editor Input Box -->
                                    <div class="space-y-1.5 flex-1 flex flex-col">
                                        <span class="text-[9px] font-mono text-muted uppercase">Instructions Code</span>
                                        <textarea id="prompt-editor-textarea" class="w-full h-56 bg-background border border-border rounded-xl p-4 font-mono text-xs text-text focus:outline-none focus:border-accent focus:ring-0 leading-relaxed" placeholder="System instructions prompt...">You are a senior tech reporter. Summarize the following news details regarding @{{topic}} in a professional, engaging format with key bullet points. Target keyword: @{{keyword}}.</textarea>
                                    </div>

                                    <!-- Footer Telemetry -->
                                    <div class="flex justify-between items-center pt-2">
                                        <div class="flex gap-4 text-[10px] font-mono text-muted">
                                            <span>Est. Tokens: <strong class="text-text">142</strong></span>
                                            <span>Est. Generation Cost: <strong class="text-accent">$0.0028</strong></span>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="text-[10px] font-mono text-muted flex items-center">Autosaved 10s ago</span>
                                            <button class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-1.5 rounded-xl transition">Publish v1.2</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tester Pane -->
                                <div id="prompt-pane-tester" class="prompt-tab-view space-y-4 hidden">
                                    <div class="grid grid-cols-2 gap-4">
                                        <!-- Test Inputs -->
                                        <div class="space-y-4">
                                            <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Variable Mock Inputs</h4>
                                            <div class="space-y-3">
                                                <div>
                                                    <label class="block text-[10px] font-mono text-muted mb-1">&#123;&#123;topic&#125;&#125;</label>
                                                    <input class="w-full bg-background border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="text" value="Quantum Computing Breakthrough"/>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-mono text-muted mb-1">&#123;&#123;keyword&#125;&#125;</label>
                                                    <input class="w-full bg-background border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="text" value="Quantum Processor IBM"/>
                                                </div>
                                                <button onclick="runPromptTestSimulation()" class="w-full bg-secondary hover:bg-secondary/80 text-background font-medium text-xs py-2 rounded-xl transition">Execute Prompt Dry-Run</button>
                                            </div>
                                        </div>

                                        <!-- Test Outputs -->
                                        <div class="space-y-4">
                                            <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Generated Preview Output</h4>
                                            <div id="prompt-test-output-window" class="h-44 bg-[#071018] border border-border rounded-xl p-4 font-mono text-[11px] text-muted overflow-y-auto leading-relaxed">
                                                Click "Execute Prompt Dry-Run" to trigger local AI generation pipeline preview...
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Rollback History Pane -->
                                <div id="prompt-pane-history" class="prompt-tab-view space-y-4 hidden">
                                    <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Revision Rollback Trees</h4>
                                    <div class="space-y-3 font-mono text-xs">
                                        <div class="p-3 bg-white/5 border border-border rounded-xl flex justify-between items-center">
                                            <div>
                                                <p class="text-text font-medium">v1.2 (Active Version)</p>
                                                <p class="text-[10px] text-muted">Adjusted keyword context and instruction clarity. Updated by system_admin.</p>
                                            </div>
                                            <span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]">active</span>
                                        </div>
                                        <div class="p-3 bg-transparent border border-border rounded-xl flex justify-between items-center hover:bg-white/5 transition">
                                            <div>
                                                <p class="text-text font-medium">v1.1</p>
                                                <p class="text-[10px] text-muted">Added custom formatting tags parameters. Updated by tech_team.</p>
                                            </div>
                                            <button class="text-secondary hover:underline">Rollback</button>
                                        </div>
                                        <div class="p-3 bg-transparent border border-border rounded-xl flex justify-between items-center hover:bg-white/5 transition">
                                            <div>
                                                <p class="text-text font-medium">v1.0</p>
                                                <p class="text-[10px] text-muted">Initial system prompt registry setup.</p>
                                            </div>
                                            <button class="text-secondary hover:underline">Rollback</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Analytics Pane -->
                                <div id="prompt-pane-analytics" class="prompt-tab-view space-y-4 hidden">
                                    <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Token Consumption Telemetry</h4>
                                    <div class="grid grid-cols-3 gap-4">
                                        <div class="bg-background rounded-xl p-4 border border-border">
                                            <p class="text-[9px] font-mono text-muted uppercase">Usage Calls (30d)</p>
                                            <p class="text-xl font-bold">14,842</p>
                                        </div>
                                        <div class="bg-background rounded-xl p-4 border border-border">
                                            <p class="text-[9px] font-mono text-muted uppercase">Avg Token count</p>
                                            <p class="text-xl font-bold text-accent">342 tokens</p>
                                        </div>
                                        <div class="bg-background rounded-xl p-4 border border-border">
                                            <p class="text-[9px] font-mono text-muted uppercase">Accumulated Costs</p>
                                            <p class="text-xl font-bold text-secondary">$42.84</p>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>


                <!-- 6. AUTOMATION RULES / WORKFLOWS -->
                <div id="node-rules" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Automation Workflow Builder</h2>
                            <p class="text-xs text-muted">Model trigger rules, conditional branching, and automatic publishing sync steps.</p>
                        </div>
                        <button onclick="launchCreationWizard('workflow')" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                            <span class="material-symbols-outlined text-sm font-bold">add</span> Register Workflow
                        </button>
                    </div>

                    <!-- Split Canvas Board layout -->
                    <div class="grid grid-cols-12 gap-6 h-[calc(100vh-220px)] overflow-hidden">
                        
                        <!-- Left Board: Drag & Drop Node Canvas Simulator -->
                        <div class="col-span-8 glass-surface rounded-2xl relative overflow-hidden bg-surface/30 p-5 flex flex-col justify-between" style="background-image: radial-gradient(rgba(255,255,255,0.05) 1px, transparent 1px); background-size: 16px 16px;">
                            <span class="text-[9px] font-mono text-muted uppercase absolute top-4 left-4">Visual Logic Canvas</span>
                            
                            <!-- Connected Nodes Stream -->
                            <div class="flex flex-col items-center justify-center space-y-4 my-auto">
                                <!-- Trigger Node -->
                                <div class="glass-surface rounded-xl p-3 border border-accent w-48 text-center bg-background/80 hover:scale-105 transition cursor-pointer">
                                    <p class="text-[9px] font-mono text-accent uppercase tracking-widest">Trigger</p>
                                    <p class="text-xs font-semibold text-text mt-0.5">Cron Loop @6h</p>
                                </div>
                                <span class="material-symbols-outlined text-muted text-sm animate-bounce">arrow_downward</span>

                                <!-- Stage 1 Node -->
                                <div class="glass-surface rounded-xl p-3 border border-border w-48 text-center bg-background/80 hover:scale-105 transition cursor-pointer">
                                    <p class="text-[9px] font-mono text-secondary uppercase tracking-widest">Stage 1</p>
                                    <p class="text-xs font-semibold text-text mt-0.5">Fetch Topic Nodes</p>
                                </div>
                                <span class="material-symbols-outlined text-muted text-sm animate-bounce">arrow_downward</span>

                                <!-- Stage 2 Node -->
                                <div class="glass-surface rounded-xl p-3 border border-border w-48 text-center bg-background/80 hover:scale-105 transition cursor-pointer">
                                    <p class="text-[9px] font-mono text-secondary uppercase tracking-widest">Stage 2</p>
                                    <p class="text-xs font-semibold text-text mt-0.5">GPT-4o Text Generator</p>
                                </div>
                                <span class="material-symbols-outlined text-muted text-sm animate-bounce">arrow_downward</span>

                                <!-- Stage 3 Node -->
                                <div class="glass-surface rounded-xl p-3 border border-border w-48 text-center bg-background/80 hover:scale-105 transition cursor-pointer">
                                    <p class="text-[9px] font-mono text-secondary uppercase tracking-widest">Stage 3</p>
                                    <p class="text-xs font-semibold text-text mt-0.5">WP Fleet Publishing</p>
                                </div>
                            </div>
                        </div>

                        <!-- Right Panel: Reusable Workflows Registry -->
                        <div class="col-span-4 glass-surface rounded-2xl p-4 flex flex-col space-y-4 h-full overflow-hidden bg-surface/30">
                            <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Reusable Configurations</h4>
                            
                            <div class="flex-1 overflow-y-auto custom-scrollbar space-y-2 pr-1">
                                <div onclick="inspectElement('workflow', 'Auto-Sync Blog Fleet', 'active', 'Success: 98.2%', 'Cron @6h')" class="p-3 bg-white/5 border border-accent rounded-xl cursor-pointer hover:border-accent transition group">
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-xs font-medium text-text">Auto-Sync Blog Fleet</p>
                                        <span class="text-[9px] font-mono bg-success/20 text-success border border-success/30 px-1.5 py-0.5 rounded">active</span>
                                    </div>
                                    <p class="text-[10px] text-muted line-clamp-1 font-mono">Triggers: Cron @6h | Success: 98.2%</p>
                                </div>

                                <div onclick="inspectElement('workflow', 'Enterprise Content Loop', 'active', 'Success: 100%', 'Cron @daily')" class="p-3 bg-transparent border border-border rounded-xl cursor-pointer hover:bg-white/5 transition group">
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-xs font-medium text-text">Enterprise Content Loop</p>
                                        <span class="text-[9px] font-mono bg-success/20 text-success border border-success/30 px-1.5 py-0.5 rounded">active</span>
                                    </div>
                                    <p class="text-[10px] text-muted line-clamp-1 font-mono">Triggers: New Topic Node | Success: 100%</p>
                                </div>

                                <div onclick="inspectElement('workflow', 'Manual Review Flow', 'paused', 'Success: 94%', 'Trigger API')" class="p-3 bg-transparent border border-border rounded-xl cursor-pointer hover:bg-white/5 transition group">
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-xs font-medium text-text">Manual Review Flow</p>
                                        <span class="text-[9px] font-mono bg-warning/20 text-warning border border-warning/30 px-1.5 py-0.5 rounded">paused</span>
                                    </div>
                                    <p class="text-[10px] text-muted line-clamp-1 font-mono">Triggers: Manual Hook | Success: 94%</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- 8. TOPIC MANAGEMENT WORKSPACE -->
                <div id="node-topics" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Topic Control Center</h2>
                            <p class="text-xs text-muted">Configure automation pipelines, SEO targets, and WordPress integration endpoints.</p>
                        </div>
                        <div class="flex gap-2">
                            <!-- Toggle Grid / Table -->
                            <div class="bg-surface p-1 border border-border rounded-xl flex items-center gap-1">
                                <button onclick="toggleTopicsView('table')" id="topics-view-table-btn" class="p-1.5 rounded-lg bg-white/5 text-accent flex items-center justify-center transition">
                                    <span class="material-symbols-outlined text-sm">table_rows</span>
                                </button>
                                <button onclick="toggleTopicsView('grid')" id="topics-view-grid-btn" class="p-1.5 rounded-lg text-muted hover:text-text flex items-center justify-center transition">
                                    <span class="material-symbols-outlined text-sm">grid_view</span>
                                </button>
                            </div>

                            <button onclick="launchCreationWizard('topic')" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                                <span class="material-symbols-outlined text-sm font-bold">add</span> Create Topic
                            </button>
                        </div>
                    </div>

                    <!-- Statistics Cards Row -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Total Topics</p>
                            <h3 class="text-3xl font-display font-bold">142</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span> 98 Active Nodes
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Articles Generated</p>
                            <h3 class="text-3xl font-display font-bold">12.4K</h3>
                            <div class="mt-2 text-[10px] font-mono text-secondary flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span> +12% this week
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">AI Success Rate</p>
                            <h3 class="text-3xl font-display font-bold text-accent">99.8%</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent">Optimal pipeline performance</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Avg SEO Score</p>
                            <h3 class="text-3xl font-display font-bold text-secondary">92/100</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Optimized Schema structures</div>
                        </div>
                    </div>

                    <!-- Search & Filter Options -->
                    <div class="flex flex-wrap items-center gap-3 p-3 bg-surface border border-border rounded-2xl">
                        <div class="relative w-64">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted text-lg">search</span>
                            <input class="w-full bg-background border border-border rounded-xl py-1.5 pl-10 pr-4 text-xs font-mono text-text placeholder-muted focus:outline-none focus:border-accent focus:ring-0" placeholder="Filter topics..." type="text"/>
                        </div>
                        <select class="bg-background border border-border text-text text-xs rounded-xl py-1.5 pl-2 pr-6 cursor-pointer focus:ring-accent">
                            <option>All Categories</option>
                            <option>Artificial Intelligence</option>
                            <option>Finance</option>
                            <option>Technology</option>
                        </select>
                        <select class="bg-background border border-border text-text text-xs rounded-xl py-1.5 pl-2 pr-6 cursor-pointer focus:ring-accent">
                            <option>Active Status</option>
                            <option>active</option>
                            <option>paused</option>
                        </select>
                        <button class="text-xs text-muted hover:text-text font-mono ml-auto">Reset Filters</button>
                    </div>

                    <!-- Topics Table View -->
                    <div id="topics-table-view" class="glass-surface rounded-2xl overflow-hidden border border-border">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase">
                                    <th class="p-3 pl-5 w-8"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20"/></th>
                                    <th class="p-3">Topic Name</th>
                                    <th class="p-3">Category</th>
                                    <th class="p-3">Model</th>
                                    <th class="p-3">Interval</th>
                                    <th class="p-3">SEO Score</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3 text-right pr-5">Operations</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-xs font-mono">
                                <tr onclick="inspectElement('topic', 'Artificial Intelligence', 'active', '98/100', 'GPT-4o')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20" onclick="event.stopPropagation()"/></td>
                                    <td class="p-3 text-text font-medium flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-accent animate-pulse"></span>
                                        Artificial Intelligence
                                    </td>
                                    <td class="p-3 text-muted">AI &amp; Coding</td>
                                    <td class="p-3 text-muted">GPT-4o</td>
                                    <td class="p-3 text-muted">Daily (3)</td>
                                    <td class="p-3 text-accent font-bold">98/100</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]">active</span></td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">Inspect</button>
                                    </td>
                                </tr>
                                <tr onclick="inspectElement('topic', 'SaaS Automations', 'active', '94/100', 'Claude 3.5')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20" onclick="event.stopPropagation()"/></td>
                                    <td class="p-3 text-text font-medium flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-accent animate-pulse"></span>
                                        SaaS Automations
                                    </td>
                                    <td class="p-3 text-muted">Cloud Technology</td>
                                    <td class="p-3 text-muted">Claude 3.5 Sonnet</td>
                                    <td class="p-3 text-muted">Daily (1)</td>
                                    <td class="p-3 text-accent font-bold">94/100</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]">active</span></td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">Inspect</button>
                                    </td>
                                </tr>
                                <tr onclick="inspectElement('topic', 'Cryptocurrency Markets', 'paused', '92/100', 'GPT-4o')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20" onclick="event.stopPropagation()"/></td>
                                    <td class="p-3 text-text font-medium flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-warning"></span>
                                        Cryptocurrency Markets
                                    </td>
                                    <td class="p-3 text-muted">Finance &amp; Stocks</td>
                                    <td class="p-3 text-muted">GPT-4o</td>
                                    <td class="p-3 text-muted">Weekly (2)</td>
                                    <td class="p-3 text-secondary font-bold">92/100</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[9px]">paused</span></td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">Inspect</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Topics Grid View -->
                    <div id="topics-grid-view" class="grid grid-cols-3 gap-6 hidden">
                        <div onclick="inspectElement('topic', 'Artificial Intelligence', 'active', '98/100', 'GPT-4o')" class="glass-surface rounded-2xl p-5 space-y-4 hover:border-accent transition cursor-pointer relative overflow-hidden group">
                            <div class="flex justify-between items-center">
                                <span class="material-symbols-outlined text-accent bg-accent/10 p-2 rounded-xl">smart_toy</span>
                                <span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px] font-mono">active</span>
                            </div>
                            <div>
                                <h3 class="font-display font-bold text-base">Artificial Intelligence</h3>
                                <p class="text-xs text-muted mt-1">Generates automated research articles around deep learning and LLMs.</p>
                            </div>
                            <div class="pt-4 border-t border-border flex justify-between text-[10px] font-mono text-muted">
                                <span>Daily (3)</span>
                                <span class="text-accent font-bold">SEO: 98/100</span>
                            </div>
                        </div>

                        <div onclick="inspectElement('topic', 'SaaS Automations', 'active', '94/100', 'Claude 3.5')" class="glass-surface rounded-2xl p-5 space-y-4 hover:border-accent transition cursor-pointer relative overflow-hidden group">
                            <div class="flex justify-between items-center">
                                <span class="material-symbols-outlined text-accent bg-accent/10 p-2 rounded-xl">cloud_sync</span>
                                <span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px] font-mono">active</span>
                            </div>
                            <div>
                                <h3 class="font-display font-bold text-base">SaaS Automations</h3>
                                <p class="text-xs text-muted mt-1">Updates on system pipelines and automation logic.</p>
                            </div>
                            <div class="pt-4 border-t border-border flex justify-between text-[10px] font-mono text-muted">
                                <span>Daily (1)</span>
                                <span class="text-accent font-bold">SEO: 94/100</span>
                            </div>
                        </div>

                        <div onclick="inspectElement('topic', 'Cryptocurrency Markets', 'paused', '92/100', 'GPT-4o')" class="glass-surface rounded-2xl p-5 space-y-4 hover:border-accent transition cursor-pointer relative overflow-hidden group">
                            <div class="flex justify-between items-center">
                                <span class="material-symbols-outlined text-warning bg-warning/10 p-2 rounded-xl">currency_bitcoin</span>
                                <span class="px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[9px] font-mono">paused</span>
                            </div>
                            <div>
                                <h3 class="font-display font-bold text-base">Cryptocurrency Markets</h3>
                                <p class="text-xs text-muted mt-1">Updates on blockchain validation metrics and token valuations.</p>
                            </div>
                            <div class="pt-4 border-t border-border flex justify-between text-[10px] font-mono text-muted">
                                <span>Weekly (2)</span>
                                <span class="text-secondary font-bold">SEO: 92/100</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 9. AI CONTENT GENERATION PIPELINE -->
                <div id="node-pipeline" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Generative Content Pipeline</h2>
                            <p class="text-xs text-muted">Monitor, configure, and inspect automated content generation pipeline runs.</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="simulatePipelineRun()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                                <span class="material-symbols-outlined text-sm font-bold">play_arrow</span> Run Test Pipeline
                            </button>
                        </div>
                    </div>

                    <!-- Telemetry Cards -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Pipeline Status</p>
                            <h3 class="text-3xl font-display font-bold text-accent">Active</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span> Queue Sync Online
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Queue Load</p>
                            <h3 class="text-3xl font-display font-bold">12 Jobs</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Estimated wait: 4.2 mins</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Daily Run Count</p>
                            <h3 class="text-3xl font-display font-bold">1,842</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent">99.4% Success Rate</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Estimated Cost</p>
                            <h3 class="text-3xl font-display font-bold text-secondary">$34.12</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Based on GPT-4o usage</div>
                        </div>
                    </div>

                    <!-- Visual Progress Pipeline Stream -->
                    <div class="glass-surface rounded-2xl p-6 space-y-4">
                        <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Visual Process Telemetry</h4>
                        <div class="flex items-center justify-between gap-2 overflow-x-auto py-2">
                            <!-- Stage 1 -->
                            <div class="flex flex-col items-center space-y-2 shrink-0 w-24">
                                <span class="material-symbols-outlined text-success bg-success/10 p-2.5 rounded-xl border border-success/30">topic</span>
                                <span class="text-[10px] font-mono text-text">Topic Pick</span>
                                <span class="text-[8px] font-mono text-muted">Complete</span>
                            </div>
                            <div class="flex-1 h-0.5 bg-success/20 min-w-4"></div>
                            
                            <!-- Stage 2 -->
                            <div class="flex flex-col items-center space-y-2 shrink-0 w-24">
                                <span class="material-symbols-outlined text-success bg-success/10 p-2.5 rounded-xl border border-success/30">terminal</span>
                                <span class="text-[10px] font-mono text-text">Prompt Build</span>
                                <span class="text-[8px] font-mono text-muted">Complete</span>
                            </div>
                            <div class="flex-1 h-0.5 bg-success/20 min-w-4"></div>

                            <!-- Stage 3 -->
                            <div class="flex flex-col items-center space-y-2 shrink-0 w-24">
                                <span id="pipeline-stage-gen-icon" class="material-symbols-outlined text-warning bg-warning/10 p-2.5 rounded-xl border border-warning/30 animate-pulse">psychology</span>
                                <span class="text-[10px] font-mono text-text">AI Article Gen</span>
                                <span id="pipeline-stage-gen-status" class="text-[8px] font-mono text-warning">Running...</span>
                            </div>
                            <div class="flex-1 h-0.5 bg-border min-w-4"></div>

                            <!-- Stage 4 -->
                            <div class="flex flex-col items-center space-y-2 shrink-0 w-24">
                                <span class="material-symbols-outlined text-muted bg-white/5 p-2.5 rounded-xl border border-border">search</span>
                                <span class="text-[10px] font-mono text-muted">SEO Tuning</span>
                                <span class="text-[8px] font-mono text-muted">Pending</span>
                            </div>
                            <div class="flex-1 h-0.5 bg-border min-w-4"></div>

                            <!-- Stage 5 -->
                            <div class="flex flex-col items-center space-y-2 shrink-0 w-24">
                                <span class="material-symbols-outlined text-muted bg-white/5 p-2.5 rounded-xl border border-border">image</span>
                                <span class="text-[10px] font-mono text-muted">Image Craft</span>
                                <span class="text-[8px] font-mono text-muted">Pending</span>
                            </div>
                            <div class="flex-1 h-0.5 bg-border min-w-4"></div>

                            <!-- Stage 6 -->
                            <div class="flex flex-col items-center space-y-2 shrink-0 w-24">
                                <span class="material-symbols-outlined text-muted bg-white/5 p-2.5 rounded-xl border border-border">publish</span>
                                <span class="text-[10px] font-mono text-muted">WP Publish</span>
                                <span class="text-[8px] font-mono text-muted">Pending</span>
                            </div>
                        </div>
                    </div>

                    <!-- Generation Runs History Table -->
                    <div class="glass-surface rounded-2xl overflow-hidden border border-border">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase">
                                    <th class="p-3 pl-5">Request ID</th>
                                    <th class="p-3">Topic Node</th>
                                    <th class="p-3">Model</th>
                                    <th class="p-3">Cost</th>
                                    <th class="p-3">Progress</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3 text-right pr-5">Operations</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-xs font-mono">
                                <tr onclick="inspectElement('run', 'req_00294', 'processing', 'AI Gen Stage', 'system_admin')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5 text-text">req_00294</td>
                                    <td class="p-3 text-muted">Quantum Computing</td>
                                    <td class="p-3 text-muted">GPT-4o</td>
                                    <td class="p-3 text-muted">$0.0034</td>
                                    <td class="p-3 text-muted">
                                        <div class="w-24 bg-white/10 rounded-full h-1.5 overflow-hidden">
                                            <div id="pipeline-progress-bar" class="bg-accent h-full w-[45%] transition-all duration-300"></div>
                                        </div>
                                    </td>
                                    <td class="p-3"><span id="pipeline-row-status" class="px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[9px] animate-pulse">processing</span></td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">Inspect</button>
                                    </td>
                                </tr>
                                <tr onclick="inspectElement('run', 'req_00293', 'online', '100% Success', 'system_admin')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5 text-text">req_00293</td>
                                    <td class="p-3 text-muted">SaaS Integrations</td>
                                    <td class="p-3 text-muted">Claude 3.5 Sonnet</td>
                                    <td class="p-3 text-muted">$0.0048</td>
                                    <td class="p-3 text-muted">
                                        <div class="w-24 bg-white/10 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-accent h-full w-[100%]"></div>
                                        </div>
                                    </td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]">completed</span></td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">Inspect</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 10. PUBLISHING SCHEDULER WORKSPACE -->
                <div id="node-scheduler" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Publishing Scheduler</h2>
                            <p class="text-xs text-muted">Orchestrate temporal publishing intervals, manage cron slots, and track upcoming releases.</p>
                        </div>
                        <div class="flex gap-2">
                            <!-- Switcher between Queue & Calendar -->
                            <div class="bg-surface p-1 border border-border rounded-xl flex items-center gap-1">
                                <button onclick="toggleSchedulerView('queue')" id="scheduler-view-queue-btn" class="p-1.5 rounded-lg bg-white/5 text-accent flex items-center justify-center transition">
                                    <span class="material-symbols-outlined text-sm">list</span>
                                </button>
                                <button onclick="toggleSchedulerView('calendar')" id="scheduler-view-calendar-btn" class="p-1.5 rounded-lg text-muted hover:text-text flex items-center justify-center transition">
                                    <span class="material-symbols-outlined text-sm">calendar_today</span>
                                </button>
                            </div>
                            
                            <button onclick="triggerManualSchedulerRelease()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                                <span class="material-symbols-outlined text-sm font-bold">send</span> Force Sync Release
                            </button>
                        </div>
                    </div>

                    <!-- Telemetry KPI row -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Queue Health</p>
                            <h3 class="text-3xl font-display font-bold text-accent">Optimal</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span> Telemetry Online
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Scheduled Runs</p>
                            <h3 class="text-3xl font-display font-bold">142 Jobs</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Across next 7 days</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Avg Posting Delay</p>
                            <h3 class="text-3xl font-display font-bold text-accent">14.2s</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent">Queue-to-API latency</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Failed Releases</p>
                            <h3 class="text-3xl font-display font-bold text-danger">0</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Zero errors in 48h</div>
                        </div>
                    </div>

                    <!-- Queue List View -->
                    <div id="scheduler-queue-view" class="glass-surface rounded-2xl overflow-hidden border border-border">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase">
                                    <th class="p-3 pl-5">Job ID</th>
                                    <th class="p-3">Target Domain</th>
                                    <th class="p-3">Topic Cluster</th>
                                    <th class="p-3">Planned Release</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3 text-right pr-5">Operations</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-xs font-mono">
                                <tr onclick="inspectElement('job', 'job_00294', 'scheduled', '2026-07-02 08:00 UTC', 'techcrunch.com')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5 text-text">job_00294</td>
                                    <td class="p-3 text-muted">https://techcrunch.com</td>
                                    <td class="p-3 text-muted">Artificial Intelligence</td>
                                    <td class="p-3 text-text" id="scheduler-job-time">2026-07-02 08:00 UTC</td>
                                    <td class="p-3"><span id="scheduler-job-status" class="px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[9px]">scheduled</span></td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">Inspect</button>
                                    </td>
                                </tr>
                                <tr onclick="inspectElement('job', 'job_00293', 'scheduled', '2026-07-02 14:30 UTC', 'mashable.com')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5 text-text">job_00293</td>
                                    <td class="p-3 text-muted">https://mashable.com</td>
                                    <td class="p-3 text-muted">SaaS Automations</td>
                                    <td class="p-3 text-text">2026-07-02 14:30 UTC</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[9px]">scheduled</span></td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">Inspect</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Calendar View -->
                    <div id="scheduler-calendar-view" class="glass-surface rounded-2xl p-5 border border-border hidden space-y-4">
                        <div class="flex justify-between items-center pb-2 border-b border-border">
                            <h4 class="text-xs font-mono uppercase tracking-widest text-muted">July 2026</h4>
                            <span class="text-[10px] font-mono text-accent">5 Scheduled Events</span>
                        </div>
                        
                        <!-- 7 Column Day Headers -->
                        <div class="grid grid-cols-7 gap-2 text-center text-[10px] font-mono text-muted uppercase">
                            <div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div>
                        </div>

                        <!-- 31 Day Grid -->
                        <div class="grid grid-cols-7 gap-2 text-center text-xs font-mono">
                            <!-- First week blank offset -->
                            <div class="p-4 bg-transparent border border-transparent"></div>
                            <div class="p-4 bg-transparent border border-transparent"></div>
                            <!-- Day 1 -->
                            <div class="p-4 bg-white/5 border border-accent rounded-xl relative hover:border-accent transition group cursor-pointer">
                                <span class="text-text font-bold">1</span>
                                <span class="absolute bottom-1.5 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span>
                            </div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">2</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">3</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">4</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">5</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">6</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">7</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">8</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">9</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">10</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">11</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">12</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">13</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">14</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">15</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">16</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">17</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">18</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">19</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">20</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">21</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">22</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">23</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">24</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">25</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">26</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">27</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">28</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">29</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">30</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">31</div>
                        </div>
                    </div>
                </div>

                <!-- 11. AI MODELS & PROVIDERS WORKSPACE -->
                <div id="node-providers" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">AI Models &amp; Providers</h2>
                            <p class="text-xs text-muted">Register API credentials, manage context limits, and establish prompt routing rules.</p>
                        </div>
                        <button onclick="launchCreationWizard('provider')" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                            <span class="material-symbols-outlined text-sm font-bold">add</span> Register Provider
                        </button>
                    </div>

                    <!-- Telemetry Cards -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Active Providers</p>
                            <h3 class="text-3xl font-display font-bold text-accent">5 Connected</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span> Fallback Route: Active
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Avg Request Latency</p>
                            <h3 class="text-3xl font-display font-bold">1.2s</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent">99.9% Provider Uptime</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Daily Token Volume</p>
                            <h3 class="text-3xl font-display font-bold">84.2M</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Across all client pipelines</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Infrastructure Costs</p>
                            <h3 class="text-3xl font-display font-bold text-secondary">$142.12</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">This billing cycle</div>
                        </div>
                    </div>

                    <!-- Model List & Registry details -->
                    <div class="grid grid-cols-12 gap-6 h-[calc(100vh-340px)] overflow-hidden">
                        <!-- Left: Model Deck -->
                        <div class="col-span-7 glass-surface rounded-2xl p-4 flex flex-col space-y-3 h-full overflow-hidden bg-surface/30">
                            <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Supported Model Engine Catalog</h4>
                            
                            <div class="flex-1 overflow-y-auto custom-scrollbar space-y-2 pr-1">
                                <div onclick="inspectElement('provider', 'OpenAI GPT-4o', 'online', '128K context', 'system_admin')" class="p-3 bg-white/5 border border-accent rounded-xl cursor-pointer hover:border-accent transition flex justify-between items-center">
                                    <div>
                                        <p class="text-xs font-semibold">GPT-4o (Default)</p>
                                        <p class="text-[10px] text-muted font-mono mt-0.5">Input: $5.00/M | Output: $15.00/M</p>
                                    </div>
                                    <span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px] font-mono">online</span>
                                </div>

                                <div onclick="inspectElement('provider', 'Anthropic Claude 3.5 Sonnet', 'online', '200K context', 'system_admin')" class="p-3 bg-transparent border border-border rounded-xl cursor-pointer hover:bg-white/5 transition flex justify-between items-center">
                                    <div>
                                        <p class="text-xs font-semibold">Claude 3.5 Sonnet</p>
                                        <p class="text-[10px] text-muted font-mono mt-0.5">Input: $3.00/M | Output: $15.00/M</p>
                                    </div>
                                    <span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px] font-mono">online</span>
                                </div>

                                <div onclick="inspectElement('provider', 'Google Gemini 1.5 Pro', 'online', '2M context', 'system_admin')" class="p-3 bg-transparent border border-border rounded-xl cursor-pointer hover:bg-white/5 transition flex justify-between items-center">
                                    <div>
                                        <p class="text-xs font-semibold">Gemini 1.5 Pro</p>
                                        <p class="text-[10px] text-muted font-mono mt-0.5">Input: $7.00/M | Output: $21.00/M</p>
                                    </div>
                                    <span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px] font-mono">online</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Credentials Security Form -->
                        <div class="col-span-5 glass-surface rounded-2xl p-5 space-y-4 bg-surface/30">
                            <h4 class="text-xs font-mono uppercase tracking-widest text-muted">API Credentials Vault</h4>
                            <div class="space-y-3 font-mono text-xs">
                                <div class="space-y-1">
                                    <label class="text-[10px] text-muted uppercase">OpenAI Secret Key</label>
                                    <input class="w-full bg-background border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="password" value="sk-proj-........................" disabled/>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] text-muted uppercase">Anthropic Secret Key</label>
                                    <input class="w-full bg-background border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="password" value="sk-ant-........................" disabled/>
                                </div>
                                <button onclick="alert('API Credentials Vault credentials synced successfully!')" class="w-full bg-accent hover:bg-accent/80 text-background font-medium text-xs py-2 rounded-xl transition">Update Credentials Vault</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 12. AI MEDIA & IMAGE GENERATION WORKSPACE -->
                <div id="node-media" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Creative Media Studio</h2>
                            <p class="text-xs text-muted">Generate, manage, and optimize AI-powered illustrations, banner layouts, and article thumbnails.</p>
                        </div>
                        <button onclick="triggerAssetGenerationSimulation()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                            <span class="material-symbols-outlined text-sm font-bold">palette</span> Generate Creative Asset
                        </button>
                    </div>

                    <!-- Telemetry Cards -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Generated Assets</p>
                            <h3 class="text-3xl font-display font-bold">1,482</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span> Storage Synced
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Storage Usage</p>
                            <h3 class="text-3xl font-display font-bold">42.8 GB</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Web-optimized image paths</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Daily Generation Cost</p>
                            <h3 class="text-3xl font-display font-bold text-accent">$8.42</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Based on DALL-E 3 usage</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Avg Rendering latency</p>
                            <h3 class="text-3xl font-display font-bold text-secondary">6.4s</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">High quality inference sync</div>
                        </div>
                    </div>

                    <!-- Creative Assets Gallery Masonry -->
                    <div class="grid grid-cols-3 gap-6 h-[calc(100vh-340px)] overflow-hidden">
                        <div onclick="inspectElement('media', 'Cyberpunk Processor Illustration', 'online', '16:9 aspect', 'DALL-E 3')" class="glass-surface rounded-2xl p-4 bg-surface/30 cursor-pointer border border-border hover:border-accent transition group flex flex-col justify-between">
                            <div class="h-32 bg-white/5 border border-border rounded-xl flex items-center justify-center relative overflow-hidden">
                                <span class="material-symbols-outlined text-4xl text-muted group-hover:scale-110 transition">developer_board</span>
                                <span class="absolute top-2 right-2 px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[8px] font-mono">optimized</span>
                            </div>
                            <div class="mt-3">
                                <h4 class="text-xs font-semibold text-text">Cyberpunk Processor Illustration</h4>
                                <p class="text-[10px] font-mono text-muted mt-1">Provider: DALL-E 3 | Style: Vector Art</p>
                            </div>
                        </div>

                        <div onclick="inspectElement('media', 'Futuristic Cityscape Banner', 'online', '16:9 aspect', 'Midjourney v6')" class="glass-surface rounded-2xl p-4 bg-surface/30 cursor-pointer border border-border hover:border-accent transition group flex flex-col justify-between">
                            <div class="h-32 bg-white/5 border border-border rounded-xl flex items-center justify-center relative overflow-hidden">
                                <span class="material-symbols-outlined text-4xl text-muted group-hover:scale-110 transition">location_city</span>
                                <span class="absolute top-2 right-2 px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[8px] font-mono">optimized</span>
                            </div>
                            <div class="mt-3">
                                <h4 class="text-xs font-semibold text-text">Futuristic Cityscape Banner</h4>
                                <p class="text-[10px] font-mono text-muted mt-1">Provider: Midjourney | Style: Photoreal</p>
                            </div>
                        </div>

                        <div id="media-gen-slot" onclick="inspectElement('media', 'Quantum Teleportation Thumbnail', 'online', '1:1 aspect', 'DALL-E 3')" class="glass-surface rounded-2xl p-4 bg-surface/30 cursor-pointer border border-border hover:border-accent transition group flex flex-col justify-between">
                            <div id="media-preview-box" class="h-32 bg-white/5 border border-border rounded-xl flex items-center justify-center relative overflow-hidden">
                                <span id="media-preview-icon" class="material-symbols-outlined text-4xl text-muted group-hover:scale-110 transition">blur_on</span>
                                <span id="media-preview-badge" class="absolute top-2 right-2 px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[8px] font-mono">optimized</span>
                            </div>
                            <div class="mt-3">
                                <h4 id="media-preview-title" class="text-xs font-semibold text-text">Quantum Teleportation Thumbnail</h4>
                                <p id="media-preview-meta" class="text-[10px] font-mono text-muted mt-1">Provider: DALL-E 3 | Style: Flat Minimal</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 7. CREATION WIZARD WORKSPACE -->
                <div id="node-creation" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl" id="wizard-title">Register Customer Pipeline</h2>
                            <p class="text-xs text-muted">Automation setup wizard</p>
                        </div>
                        <button onclick="cancelCreation()" class="text-muted hover:text-text text-xs font-mono">Cancel</button>
                    </div>

                    <!-- Progress Step Indicator -->
                    <div class="flex items-center gap-2 justify-center py-4 bg-surface/30 border border-border rounded-xl">
                        <div class="flex items-center gap-2 text-xs font-mono" id="wizard-steps-indicator">
                            <span class="text-accent" id="step-ind-1">● General</span>
                            <span class="text-muted">➔</span>
                            <span class="text-muted" id="step-ind-2">● Configuration</span>
                            <span class="text-muted">➔</span>
                            <span class="text-muted" id="step-ind-3">● Validation</span>
                            <span class="text-muted">➔</span>
                            <span class="text-muted" id="step-ind-4">● Preview</span>
                        </div>
                    </div>

                    <!-- Wizard Panels -->
                    <div class="glass-surface rounded-2xl p-6 max-w-xl mx-auto space-y-6">
                        <!-- Step 1 Pane -->
                        <div id="wizard-step-1" class="wizard-pane space-y-4">
                            <h3 class="text-sm font-medium font-mono text-secondary">Step 1: General Information</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-mono text-muted mb-1">COMPANY NAME</label>
                                    <input class="w-full bg-[#071018] border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="text" placeholder="Enter name..."/>
                                </div>
                                <div>
                                    <label class="block text-xs font-mono text-muted mb-1">EMAIL ADDRESS</label>
                                    <input class="w-full bg-[#071018] border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="email" placeholder="owner@company.com"/>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2 Pane -->
                        <div id="wizard-step-2" class="wizard-pane space-y-4 hidden">
                            <h3 class="text-sm font-medium font-mono text-secondary">Step 2: Configuration</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-mono text-muted mb-1">SELECT INITIAL PLAN</label>
                                    <select class="w-full bg-[#071018] border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent">
                                        <option>Starter - $29/mo</option>
                                        <option>Professional - $79/mo</option>
                                        <option>Business - $199/mo</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3 Pane -->
                        <div id="wizard-step-3" class="wizard-pane space-y-4 hidden">
                            <h3 class="text-sm font-medium font-mono text-secondary">Step 3: Validation</h3>
                            <div class="p-4 bg-accent/10 border border-accent/20 rounded-xl space-y-2">
                                <p class="text-xs text-accent">✔ Email address unique checks passed.</p>
                                <p class="text-xs text-accent">✔ Selected plan constraints registered.</p>
                            </div>
                        </div>

                        <!-- Step 4 Pane -->
                        <div id="wizard-step-4" class="wizard-pane space-y-4 hidden">
                            <h3 class="text-sm font-medium font-mono text-secondary">Step 4: Preview Details</h3>
                            <div class="space-y-2 text-xs font-mono">
                                <p><span class="text-muted">Target Entity:</span> Customer Node</p>
                                <p><span class="text-muted">Billing Cycle:</span> Trial</p>
                            </div>
                        </div>

                        <!-- Controls -->
                        <div class="flex justify-between pt-4 border-t border-border">
                            <button id="wizard-back-btn" onclick="wizardBack()" class="text-xs font-mono text-muted hover:text-text hidden">Back</button>
                            <div class="flex-1"></div>
                            <button id="wizard-next-btn" onclick="wizardNext()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition">Next</button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- DYNAMIC CONTEXT INSPECTOR PANEL -->
            <aside id="inspector-panel" class="w-80 bg-sidebar border-l border-border flex flex-col justify-between py-6 px-4 shrink-0 transition-all duration-300 transform translate-x-full hidden">
                <div class="space-y-6">
                    <!-- Title -->
                    <div class="flex justify-between items-center">
                        <h4 class="text-xs font-mono uppercase tracking-widest text-muted" id="inspector-type">Customer Entity</h4>
                        <button onclick="closeInspector()" class="p-1 hover:bg-white/5 rounded-lg border border-transparent hover:border-border text-muted hover:text-text">
                            <span class="material-symbols-outlined text-sm font-bold">close</span>
                        </button>
                    </div>

                    <!-- Inspector Body -->
                    <div class="space-y-4">
                        <h3 class="text-xl font-display font-bold" id="inspector-title">Acme Corp</h3>
                        
                        <div class="space-y-3 font-mono text-xs">
                            <div>
                                <span class="text-muted">STATUS:</span>
                                <span id="inspector-status" class="ml-2 px-2 py-0.5 rounded text-[10px] bg-warning/20 text-warning">trial</span>
                            </div>
                            <div>
                                <span class="text-muted">PRIORITY:</span>
                                <span id="inspector-priority" class="ml-2 text-text font-medium">High</span>
                            </div>
                            <div>
                                <span class="text-muted">OWNER:</span>
                                <span id="inspector-owner" class="ml-2 text-text">system_admin</span>
                            </div>
                            <div>
                                <span class="text-muted">LAST UPDATE:</span>
                                <span class="ml-2 text-muted">2 mins ago</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div class="space-y-2">
                        <h5 class="text-[10px] font-mono uppercase tracking-widest text-muted">Metadata Tags</h5>
                        <div class="flex flex-wrap gap-1 text-[10px] font-mono">
                            <span class="bg-white/5 border border-border px-2 py-0.5 rounded text-muted">US-Region</span>
                            <span class="bg-white/5 border border-border px-2 py-0.5 rounded text-muted">SaaS</span>
                        </div>
                    </div>
                </div>

                <!-- Inspector Actions -->
                <div class="pt-4 border-t border-border space-y-2">
                    <button class="w-full bg-accent text-background font-medium text-xs py-2 rounded-xl transition hover:bg-accent/80">
                        Trigger Operations Sync
                    </button>
                    <button class="w-full bg-transparent hover:bg-white/5 text-danger border border-danger/30 font-medium text-xs py-2 rounded-xl transition">
                        Force Termination
                    </button>
                </div>
            </aside>

        </div>
    </main>

    <!-- WORKSPACE STATE ROUTING SCRIPT -->
    <script>
        let currentWorkspace = "{{ $activeView ?? 'dashboard' }}";
        let currentTab = 'overview';
        let wizardStep = 1;
        let wizardType = 'customer';

        // Keyboard Shortcuts
        document.addEventListener('keydown', function(event) {
            // Ctrl+K or Cmd+K focuses search
            if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
                event.preventDefault();
                document.getElementById('global-search').focus();
            }
        });

        function handleSearchShortcut(e) {
            if (e.key === 'Escape') {
                e.target.blur();
            }
        }

        // Initialize view state
        window.addEventListener('DOMContentLoaded', () => {
            switchWorkspace(currentWorkspace);
            switchTab(currentTab);
        });

        // Workspace Node Router
        function switchWorkspace(node) {
            currentWorkspace = node;
            
            // Hide all workspaces
            document.querySelectorAll('.workspace-pane').forEach(el => {
                el.classList.add('hidden');
            });

            // Show selected node
            const activeNode = document.getElementById('node-' + node);
            if (activeNode) {
                activeNode.classList.remove('hidden');
            }

            // Highlight Active Sidebar Menu Option
            document.querySelectorAll('#sidebar-menu button').forEach(btn => {
                const isSelected = btn.getAttribute('data-node') === node;
                if (isSelected) {
                    btn.classList.add('text-accent', 'bg-white/5', 'cyber-glow-emerald');
                    btn.classList.remove('text-muted');
                } else {
                    btn.classList.remove('text-accent', 'bg-white/5', 'cyber-glow-emerald');
                    btn.classList.add('text-muted');
                }
            });

            // Update Breadcrumb & URL (pushState)
            document.getElementById('breadcrumb-active').innerText = node.toUpperCase();
            window.history.pushState(null, '', node === 'dashboard' ? '/' : '/' + node);

            // Close context inspector upon navigation
            closeInspector();
        }

        // Tab Switcher inside Node Workspace
        function switchTab(tab) {
            currentTab = tab;
            
            document.querySelectorAll('#workspace-tabs button').forEach(btn => {
                const isSelected = btn.getAttribute('data-tab') === tab;
                if (isSelected) {
                    btn.classList.add('text-secondary', 'border-secondary', 'active-tab-glow');
                    btn.classList.remove('text-muted', 'border-transparent');
                } else {
                    btn.classList.remove('text-secondary', 'border-secondary', 'active-tab-glow');
                    btn.classList.add('text-muted', 'border-transparent');
                }
            });

            // Simulate high-density telemetry reloading
            if (tab === 'logs') {
                console.log("Telemetry logs reconnected.");
            }
        }

        // Dynamic Floating Context Inspector Panel
        function inspectElement(type, title, status, priority, owner) {
            const panel = document.getElementById('inspector-panel');
            panel.classList.remove('hidden', 'translate-x-full');
            
            // Set properties
            document.getElementById('inspector-type').innerText = type.toUpperCase() + ' NODE';
            document.getElementById('inspector-title').innerText = title;
            
            if (type === 'topic') {
                document.getElementById('inspector-priority').innerText = 'SEO Rating: ' + priority;
                document.getElementById('inspector-owner').innerText = 'LLM Model: ' + owner;
            } else if (type === 'site') {
                document.getElementById('inspector-priority').innerText = 'Core Version: ' + priority;
                document.getElementById('inspector-owner').innerText = 'API Key ID: ' + owner;
            } else if (type === 'workflow') {
                document.getElementById('inspector-priority').innerText = 'Telemetry: ' + priority;
                document.getElementById('inspector-owner').innerText = 'Trigger Rule: ' + owner;
            } else if (type === 'job') {
                document.getElementById('inspector-priority').innerText = 'Planned Release: ' + priority;
                document.getElementById('inspector-owner').innerText = 'Target Domain: ' + owner;
            } else if (type === 'provider') {
                document.getElementById('inspector-priority').innerText = 'Context limit: ' + priority;
                document.getElementById('inspector-owner').innerText = 'Authorized role: ' + owner;
            } else if (type === 'media') {
                document.getElementById('inspector-priority').innerText = 'Aspect Ratio: ' + priority;
                document.getElementById('inspector-owner').innerText = 'Inference Engine: ' + owner;
            } else {
                document.getElementById('inspector-priority').innerText = priority;
                document.getElementById('inspector-owner').innerText = owner;
            }
            
            const statusBadge = document.getElementById('inspector-status');
            statusBadge.innerText = status;
            
            // Adjust colors based on status
            statusBadge.className = 'ml-2 px-2 py-0.5 rounded text-[10px] border ';
            if (status === 'active' || status === 'online') {
                statusBadge.classList.add('bg-success/20', 'text-success', 'border-success/30');
            } else if (status === 'trial' || status === 'paused') {
                statusBadge.classList.add('bg-warning/20', 'text-warning', 'border-warning/30');
            } else {
                statusBadge.classList.add('bg-danger/20', 'text-danger', 'border-danger/30');
            }
        }

        // Close context inspector panel
        function closeInspector() {
            const panel = document.getElementById('inspector-panel');
            panel.classList.add('translate-x-full');
            setTimeout(() => {
                panel.classList.add('hidden');
            }, 300);
        }

        // Toggle Grid vs Table View for Topics
        function toggleTopicsView(viewType) {
            const tableView = document.getElementById('topics-table-view');
            const gridView = document.getElementById('topics-grid-view');
            const tableBtn = document.getElementById('topics-view-table-btn');
            const gridBtn = document.getElementById('topics-view-grid-btn');

            if (viewType === 'table') {
                tableView.classList.remove('hidden');
                gridView.classList.add('hidden');
                tableBtn.classList.add('bg-white/5', 'text-accent');
                tableBtn.classList.remove('text-muted');
                gridBtn.classList.remove('bg-white/5', 'text-accent');
                gridBtn.classList.add('text-muted');
            } else {
                tableView.classList.add('hidden');
                gridView.classList.remove('hidden');
                gridBtn.classList.add('bg-white/5', 'text-accent');
                gridBtn.classList.remove('text-muted');
                tableBtn.classList.remove('bg-white/5', 'text-accent');
                tableBtn.classList.add('text-muted');
            }
        }

        // Dedicated Creation Workspace Step transitions
        function launchCreationWizard(type) {
            wizardType = type;
            wizardStep = 1;
            
            document.getElementById('wizard-title').innerText = "Register " + type.charAt(0).toUpperCase() + type.slice(1) + " Pipeline";
            
            // Switch Workspace View to creation pane
            document.querySelectorAll('.workspace-pane').forEach(el => el.classList.add('hidden'));
            document.getElementById('node-creation').classList.remove('hidden');

            renderWizardStep();
        }

        function cancelCreation() {
            switchWorkspace('dashboard');
        }

        function renderWizardStep() {
            document.querySelectorAll('.wizard-pane').forEach(el => el.classList.add('hidden'));
            document.getElementById('wizard-step-' + wizardStep).classList.remove('hidden');

            // Set indicators
            for (let i = 1; i <= 4; i++) {
                const ind = document.getElementById('step-ind-' + i);
                if (i === wizardStep) {
                    ind.className = 'text-accent font-bold';
                } else if (i < wizardStep) {
                    ind.className = 'text-secondary';
                } else {
                    ind.className = 'text-muted';
                }
            }

            // Adjust back button visibility
            const backBtn = document.getElementById('wizard-back-btn');
            if (wizardStep === 1) {
                backBtn.classList.add('hidden');
            } else {
                backBtn.classList.remove('hidden');
            }

            // Set text on Next button for final step
            const nextBtn = document.getElementById('wizard-next-btn');
            if (wizardStep === 4) {
                nextBtn.innerText = "Commit Pipeline";
            } else {
                nextBtn.innerText = "Next";
            }
        }

        function wizardNext() {
            if (wizardStep < 4) {
                wizardStep++;
                renderWizardStep();
            } else {
                // Done!
                alert(wizardType.charAt(0).toUpperCase() + wizardType.slice(1) + " pipeline committed successfully!");
                if (wizardType === 'customer') {
                    switchWorkspace('customers');
                } else if (wizardType === 'topic') {
                    switchWorkspace('topics');
                } else {
                    switchWorkspace('sites');
                }
            }
        }

        function wizardBack() {
            if (wizardStep > 1) {
                wizardStep--;
                renderWizardStep();
            }
        }

        // Prompt Workspace Sub-tab switching
        function switchPromptSubTab(tab) {
            document.querySelectorAll('.prompt-tab-view').forEach(el => {
                el.classList.add('hidden');
            });
            document.getElementById('prompt-pane-' + tab).classList.remove('hidden');

            document.querySelectorAll('#prompt-sub-tabs text, #prompt-sub-tabs button').forEach(btn => {
                const id = btn.getAttribute('id');
                if (id === 'prompt-tab-' + tab) {
                    btn.classList.add('text-accent', 'border-accent');
                    btn.classList.remove('text-muted', 'border-transparent');
                } else {
                    btn.classList.remove('text-accent', 'border-accent');
                    btn.classList.add('text-muted', 'border-transparent');
                }
            });
        }

        // Prompt Template selector
        function selectPromptTemplate(id, name, model, category, provider) {
            document.getElementById('prompt-editor-id').innerText = "Active: " + id;
            document.getElementById('prompt-meta-model').innerText = model.toUpperCase();
            document.getElementById('prompt-meta-provider').innerText = provider.toUpperCase() === 'OPENAI' ? 'OpenAI Core' : 'Anthropic API';
            document.getElementById('prompt-meta-category').innerText = category;
            
            if (id === 'promt_001') {
                text = "You are a senior tech reporter. Summarize the following news details regarding @{{topic}} in a professional, engaging format with key bullet points. Target keyword: @{{keyword}}.";
            } else if (id === 'promt_002') {
                text = "Compose a structured bulletin highlighting key developments in @{{topic}}. Use tone: @{{tone}}. Language should target @{{language}}.";
            } else {
                text = "Analyze financial reports and output a trend summary for @{{topic}}. Extract core indicators and model predictions.";
            }
            document.getElementById('prompt-editor-textarea').value = text;
        }

        // Simulate live testing outputs
        function runPromptTestSimulation() {
            const outWindow = document.getElementById('prompt-test-output-window');
            outWindow.innerText = "Connecting to pipeline stub...\n";
            
            let lines = [
                "Sending test payload...",
                "Running validation checks...",
                "Received model completion response:\n",
                "### IBM releases new 433-qubit Osprey processor.",
                "- Highlights massive quantum computing performance improvements.",
                "- Increases noise protection structures.",
                "- Employs standard multi-layered layout architectures."
            ];
            
            let i = 0;
            let timer = setInterval(() => {
                if (i < lines.length) {
                    outWindow.innerText += lines[i] + "\n";
                    i++;
                } else {
                    clearInterval(timer);
                }
            }, 300);
        }

        // Simulate real-time pipeline run loops
        function simulatePipelineRun() {
            const progressBar = document.getElementById('pipeline-progress-bar');
            const rowStatus = document.getElementById('pipeline-row-status');
            const stageIcon = document.getElementById('pipeline-stage-gen-icon');
            const stageStatus = document.getElementById('pipeline-stage-gen-status');

            if (!progressBar) return;

            progressBar.style.width = '45%';
            rowStatus.innerText = 'processing';
            rowStatus.className = 'px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[9px] animate-pulse';
            stageIcon.className = 'material-symbols-outlined text-warning bg-warning/10 p-2.5 rounded-xl border border-warning/30 animate-pulse';
            stageIcon.innerText = 'psychology';
            stageStatus.innerText = 'Running...';
            stageStatus.className = 'text-[8px] font-mono text-warning';

            setTimeout(() => {
                progressBar.style.width = '100%';
                rowStatus.innerText = 'completed';
                rowStatus.className = 'px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]';
                stageIcon.className = 'material-symbols-outlined text-success bg-success/10 p-2.5 rounded-xl border border-success/30';
                stageIcon.innerText = 'task_alt';
                stageStatus.innerText = 'Complete';
                stageStatus.className = 'text-[8px] font-mono text-success';
                alert("AI content generation pipeline run completed successfully!");
            }, 3000);
        }

        // Toggle Scheduler Queue vs Calendar view
        function toggleSchedulerView(viewType) {
            const queueView = document.getElementById('scheduler-queue-view');
            const calendarView = document.getElementById('scheduler-calendar-view');
            const queueBtn = document.getElementById('scheduler-view-queue-btn');
            const calendarBtn = document.getElementById('scheduler-view-calendar-btn');

            if (viewType === 'queue') {
                queueView.classList.remove('hidden');
                calendarView.classList.add('hidden');
                queueBtn.classList.add('bg-white/5', 'text-accent');
                queueBtn.classList.remove('text-muted');
                calendarBtn.classList.remove('bg-white/5', 'text-accent');
                calendarBtn.classList.add('text-muted');
            } else {
                queueView.classList.add('hidden');
                calendarView.classList.remove('hidden');
                calendarBtn.classList.add('bg-white/5', 'text-accent');
                calendarBtn.classList.remove('text-muted');
                queueBtn.classList.remove('bg-white/5', 'text-accent');
                queueBtn.classList.add('text-muted');
            }
        }

        // Simulate Manual Time Release for Scheduler Job
        function triggerManualSchedulerRelease() {
            const jobTime = document.getElementById('scheduler-job-time');
            const jobStatus = document.getElementById('scheduler-job-status');

            if (!jobTime) return;

            jobTime.innerText = "Releasing...";
            jobStatus.innerText = "publishing";
            jobStatus.className = "px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[9px] animate-pulse";

            setTimeout(() => {
                jobTime.innerText = "JUST NOW";
                jobStatus.innerText = "completed";
                jobStatus.className = "px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]";
                alert("Job successfully published to WordPress destination site techcrunch.com!");
            }, 2000);
        }

        // Simulate AI Media / Image generation run loop
        function triggerAssetGenerationSimulation() {
            const previewBox = document.getElementById('media-preview-box');
            const previewIcon = document.getElementById('media-preview-icon');
            const previewBadge = document.getElementById('media-preview-badge');
            const previewTitle = document.getElementById('media-preview-title');
            const previewMeta = document.getElementById('media-preview-meta');

            if (!previewBox) return;

            // Step 1: Processing State
            previewIcon.innerText = "pending";
            previewIcon.className = "material-symbols-outlined text-4xl text-warning animate-spin";
            previewBadge.innerText = "generating";
            previewBadge.className = "absolute top-2 right-2 px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[8px] font-mono animate-pulse";
            previewTitle.innerText = "Rendering Quantum Supercomputer Visual...";
            previewMeta.innerText = "Inference in progress...";

            setTimeout(() => {
                // Step 2: Done!
                previewIcon.innerText = "wallpaper";
                previewIcon.className = "material-symbols-outlined text-4xl text-accent";
                previewBadge.innerText = "optimized";
                previewBadge.className = "absolute top-2 right-2 px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[8px] font-mono";
                previewTitle.innerText = "Quantum Teleportation Thumbnail (Rendered)";
                previewMeta.innerText = "Provider: DALL-E 3 | Style: Flat Minimal | Size: 1024x1024";
                alert("Creative media asset successfully generated and optimized!");
            }, 3000);
        }

        // Handle Popstate (browser Back/Forward)
        window.onpopstate = function() {
            const path = window.location.pathname.replace('/', '');
            switchWorkspace(path === '' ? 'dashboard' : path);
        };

        // Theme Toggle Handler
        function updateThemeUI() {
            const html = document.documentElement;
            const icon = document.getElementById('theme-toggle-icon');
            if (html.classList.contains('dark')) {
                icon.innerText = 'light_mode';
            } else {
                icon.innerText = 'dark_mode';
            }
        }

        function toggleTheme() {
            const html = document.documentElement;
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                html.classList.add('dark');
                localStorage.theme = 'dark';
            }
            updateThemeUI();
        }

        // Apply theme indicators on init
        updateThemeUI();
    </script>
</body>
</html>
