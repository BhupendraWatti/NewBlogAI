<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>WordPress Client Manager - NewsBlogify AI</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=JetBrains+Mono:wght@400;500&amp;family=Outfit:wght@400;600;700;800&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "on-tertiary-fixed": "#40000d",
                        "outline": "#908fa0",
                        "surface-container-highest": "#313540",
                        "primary-container": "#8083ff",
                        "on-secondary-fixed": "#002113",
                        "tertiary-fixed-dim": "#ffb2b7",
                        "primary-fixed-dim": "#c0c1ff",
                        "on-error": "#690005",
                        "surface-bright": "#353944",
                        "error-container": "#93000a",
                        "outline-variant": "#464554",
                        "surface-container": "#1c1f2a",
                        "primary": "#c0c1ff",
                        "on-tertiary-fixed-variant": "#92002a",
                        "background": "#0f131d",
                        "secondary": "#4edea3",
                        "on-primary-fixed": "#07006c",
                        "on-tertiary": "#67001b",
                        "surface-dim": "#0f131d",
                        "inverse-primary": "#494bd6",
                        "primary-fixed": "#e1e0ff",
                        "tertiary": "#ffb2b7",
                        "on-primary": "#1000a9",
                        "tertiary-container": "#ff516a",
                        "on-secondary-container": "#00311f",
                        "tertiary-fixed": "#ffdadb",
                        "on-primary-container": "#0d0096",
                        "secondary-fixed-dim": "#4edea3",
                        "surface-container-high": "#262a35",
                        "surface": "#0f131d",
                        "on-secondary-fixed-variant": "#005236",
                        "inverse-surface": "#dfe2f1",
                        "surface-variant": "#313540",
                        "on-primary-fixed-variant": "#2f2ebe",
                        "on-background": "#dfe2f1",
                        "surface-container-lowest": "#0a0e18",
                        "on-surface-variant": "#c7c4d7",
                        "on-error-container": "#ffdad6",
                        "on-secondary": "#003824",
                        "secondary-fixed": "#6ffbbe",
                        "surface-tint": "#c0c1ff",
                        "on-tertiary-container": "#5b0017",
                        "secondary-container": "#00a572",
                        "on-surface": "#dfe2f1",
                        "surface-container-low": "#171b26",
                        "error": "#ffb4ab",
                        "inverse-on-surface": "#2c303b"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "sidebar-width": "260px",
                        "margin-page": "32px",
                        "gutter": "24px",
                        "inspector-width": "320px",
                        "stack-lg": "24px",
                        "stack-md": "16px"
                    },
                    "fontFamily": {
                        "headline-lg": ["Outfit"],
                        "body-lg": ["Inter"],
                        "body-sm": ["Inter"],
                        "body-md": ["Inter"],
                        "headline-lg-mobile": ["Outfit"],
                        "label-md": ["Inter"],
                        "mono-sm": ["JetBrains Mono"],
                        "headline-md": ["Outfit"],
                        "label-sm": ["Inter"],
                        "display-lg": ["Outfit"]
                    }
                }
            }
        }
    </script>
    <style>
        #sites-workspace .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        #sites-workspace .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        #sites-workspace .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #313540;
            border-radius: 9999px;
        }
        #sites-workspace .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #464554;
        }
        
        #sites-workspace .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(12px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }
        #sites-workspace .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        #sites-workspace .modal-container {
            background: #1c1f2a;
            border: 1px solid rgba(255, 255, 255, 0.08);
            width: 550px;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);
            transform: scale(0.95);
            transition: transform 0.25s ease;
        }
        #sites-workspace .modal-overlay.active .modal-container {
            transform: scale(1);
        }
    </style>
</head>
<body id="sites-workspace" class="bg-background text-on-background font-body-md text-body-md antialiased overflow-hidden w-full h-screen flex">

    <!-- Sidebar Navigation (Simple Sidebar Style) -->
    <nav class="bg-surface-container dark:bg-surface-container font-headline-md text-headline-md font-label-md text-label-md fixed left-0 top-0 h-full w-[260px] border-r border-outline-variant flat no shadows flex flex-col py-stack-lg z-30">
        <!-- Logo Header -->
        <div class="px-margin-page mb-stack-lg flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-primary/20 flex items-center justify-center border border-primary/30">
                <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
            </div>
            <div>
                <h1 class="font-headline-md text-headline-md font-bold text-primary">NewsBlogify AI</h1>
                <p class="font-label-sm text-label-sm text-outline">Enterprise Admin</p>
            </div>
        </div>

        <!-- Navigation Links -->
        <div class="flex-1 overflow-y-auto custom-scrollbar px-stack-md flex flex-col gap-1">
            <a class="flex items-center gap-3 px-stack-md py-2 rounded-lg text-outline hover:text-on-surface hover:bg-surface-container-high transition-all duration-200 scale-98 active:scale-95 transition-transform group" href="/">
                <span class="material-symbols-outlined text-lg group-hover:text-primary transition-colors">dashboard</span>
                <span class="font-label-md text-label-md">Dashboard</span>
            </a>
            <a class="flex items-center gap-3 px-stack-md py-2 rounded-lg text-outline hover:text-on-surface hover:bg-surface-container-high transition-all duration-200 scale-98 active:scale-95 transition-transform group" href="/customers">
                <span class="material-symbols-outlined text-lg group-hover:text-primary transition-colors">group</span>
                <span class="font-label-md text-label-md">Customers</span>
            </a>
            <a class="flex items-center gap-3 px-stack-md py-2 rounded-lg text-outline hover:text-on-surface hover:bg-surface-container-high transition-all duration-200 scale-98 active:scale-95 transition-transform group" href="/fleet">
                <span class="material-symbols-outlined text-lg group-hover:text-primary transition-colors">cooking</span>
                <span class="font-label-md text-label-md">Fleet Manager</span>
            </a>
            <a class="flex items-center gap-3 px-stack-md py-2 rounded-lg text-primary border-l-2 border-primary bg-primary/10 shadow-[0_0_15px_rgba(192,193,255,0.15)] scale-98 active:scale-95 transition-transform" href="/sites">
                <span class="material-symbols-outlined text-lg" style="font-variation-settings: 'FILL' 1;">language</span>
                <span class="font-label-md text-label-md">Sites Manager</span>
            </a>
            <a class="flex items-center gap-3 px-stack-md py-2 rounded-lg text-outline hover:text-on-surface hover:bg-surface-container-high transition-all duration-200 scale-98 active:scale-95 transition-transform group" href="/prompts">
                <span class="material-symbols-outlined text-lg group-hover:text-primary transition-colors">book</span>
                <span class="font-label-md text-label-md">Prompts Library</span>
            </a>
        </div>

        <div class="mt-auto px-stack-md flex flex-col gap-4 pt-4 border-t border-outline-variant/30">
            <div class="flex flex-col gap-1">
                <a class="flex items-center gap-3 px-stack-md py-2 rounded-lg text-outline hover:text-on-surface-variant hover:bg-surface-container-high transition-all duration-200 group" href="#">
                    <span class="material-symbols-outlined text-lg group-hover:text-on-surface transition-colors">help</span>
                    <span class="font-label-md text-label-md">Support</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Workspace Area -->
    <div class="flex-1 flex flex-col relative ml-[260px] mr-[320px] h-full overflow-hidden z-10">
        <!-- Top Nav Bar -->
        <header class="bg-background/80 backdrop-blur-md text-primary font-headline-md text-headline-md border-b border-outline-variant flex justify-between items-center h-16 px-margin-page absolute w-full z-20">
            <div class="flex items-center gap-4">
                <a class="text-primary border-b-2 border-primary pb-1 font-label-md text-label-md" href="#">Overview</a>
            </div>

            <div class="flex items-center gap-gutter">
                <!-- Search bar -->
                <div class="relative hidden lg:flex items-center bg-surface-container-high border border-outline-variant rounded-lg px-3 py-1.5 focus-within:ring-1 focus-within:ring-primary transition-shadow">
                    <span class="material-symbols-outlined text-outline text-sm mr-2">search</span>
                    <input id="search-input" oninput="searchSites(this.value)" class="bg-transparent border-none text-on-surface font-body-sm text-body-sm placeholder:text-outline focus:ring-0 p-0 w-48" placeholder="Search resources..." type="text"/>
                </div>

                <!-- Add Site Button -->
                <button onclick="openAddModal()" class="bg-primary text-on-primary font-label-md text-label-md px-4 py-2 rounded-lg hover:bg-primary-fixed transition-colors flex items-center gap-2 shadow-[0_0_10px_rgba(192,193,255,0.2)]">
                    <span class="material-symbols-outlined text-sm font-bold">add</span>
                    + Add WordPress Site
                </button>
            </div>
        </header>

        <!-- Scrollable Workspace -->
        <main class="flex-1 overflow-y-auto custom-scrollbar pt-20 px-margin-page pb-margin-page w-full">
            <div class="mb-gutter">
                <h2 class="font-headline-lg text-headline-lg text-on-surface">WordPress Client Manager</h2>
                <p class="font-body-md text-body-md text-outline mt-1">Monitor and orchestrate automated content syncs across your fleet.</p>
            </div>

            <!-- System Health KPIs -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter mb-gutter">
                <div class="bg-surface-container-high border border-outline-variant rounded-xl p-stack-lg relative overflow-hidden group hover:border-primary/50 transition-colors">
                    <div class="flex justify-between items-start mb-2">
                        <span class="font-label-md text-label-md text-outline">Total Websites</span>
                        <span class="material-symbols-outlined text-outline group-hover:text-primary transition-colors text-sm">domain</span>
                    </div>
                    <div class="font-display-lg text-display-lg text-on-surface" id="kpi-total-sites">0</div>
                </div>

                <div class="bg-surface-container-high border border-outline-variant rounded-xl p-stack-lg relative overflow-hidden group hover:border-secondary/50 transition-colors">
                    <div class="absolute top-stack-lg right-stack-lg w-2 h-2 rounded-full bg-secondary shadow-[0_0_8px_rgba(78,222,163,0.8)] animate-pulse"></div>
                    <div class="flex justify-between items-start mb-2">
                        <span class="font-label-md text-label-md text-outline">Active Syncs</span>
                    </div>
                    <div class="font-display-lg text-display-lg text-on-surface" id="kpi-active-syncs">0</div>
                </div>

                <div class="bg-surface-container-high border border-outline-variant rounded-xl p-stack-lg relative overflow-hidden group hover:border-outline transition-colors">
                    <div class="flex justify-between items-start mb-2">
                        <span class="font-label-md text-label-md text-outline">Pending Syncs</span>
                        <span class="material-symbols-outlined text-outline group-hover:text-on-surface transition-colors text-sm">list_alt</span>
                    </div>
                    <div class="font-display-lg text-display-lg text-on-surface" id="kpi-pending-syncs">0</div>
                </div>
            </div>

            <!-- Connected Ecosystem Table -->
            <div class="bg-surface-container border border-outline-variant rounded-xl overflow-hidden shadow-lg">
                <div class="flex justify-between items-center p-stack-md border-b border-outline-variant bg-surface-container-high">
                    <h3 class="font-label-md text-label-md text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-sm">dns</span>
                        Connected Ecosystem
                    </h3>
                    <div class="flex gap-2">
                        <button onclick="fetchSites()" class="bg-surface-container-lowest border border-outline-variant text-outline hover:text-on-surface rounded p-1.5 transition-colors">
                            <span class="material-symbols-outlined text-sm">refresh</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead>
                            <tr class="bg-surface-container-high border-b border-outline-variant">
                                <th class="p-stack-md font-label-sm text-label-sm text-outline">Website</th>
                                <th class="p-stack-md font-label-sm text-label-sm text-outline">Sync Slot</th>
                                <th class="p-stack-md font-label-sm text-label-sm text-outline">Last Sync</th>
                                <th class="p-stack-md font-label-sm text-label-sm text-outline">Prompt Template</th>
                                <th class="p-stack-md font-label-sm text-label-sm text-outline">Status</th>
                                <th class="p-stack-md font-label-sm text-label-sm text-outline text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sites-table-body" class="font-body-sm text-body-sm text-on-surface-variant divide-y divide-outline-variant">
                            <tr>
                                <td colspan="6" class="text-center text-outline p-stack-lg">Loading ecosystems...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Inspector Panel (Right fixed) -->
    <aside id="inspector-panel" class="fixed right-0 top-0 h-full w-[320px] bg-surface-container border-l border-outline-variant flex flex-col z-30 shadow-2xl">
        <!-- Inspector Header -->
        <div class="h-16 flex items-center justify-between px-stack-lg border-b border-outline-variant bg-surface-container-highest">
            <h2 class="font-label-md text-label-md text-on-surface">Site Inspector</h2>
            <button class="text-outline hover:text-on-surface transition-colors" onclick="closeInspector()">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>

        <div id="inspector-content" class="flex-1 overflow-y-auto custom-scrollbar p-stack-lg flex flex-col gap-stack-lg">
            <div class="text-center text-outline py-12">Select a site to view details</div>
        </div>
    </aside>

    <!-- Add/Edit Modal -->
    <div class="modal-overlay" id="site-modal">
        <div class="modal-container">
            <div class="flex justify-between items-center mb-6 border-b border-outline-variant pb-3">
                <h3 class="text-lg font-semibold font-headline-md text-primary" id="modal-title">Add WordPress Site</h3>
                <button class="text-outline hover:text-on-surface text-xl" onclick="closeModal()">&times;</button>
            </div>
            <form id="site-form" onsubmit="saveSite(event)">
                <input type="hidden" id="site-id">
                
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="domain_url">Domain URL</label>
                    <input type="url" id="domain_url" class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg px-4 py-2.5 text-on-surface focus:outline-none focus:border-primary" placeholder="https://tech-insider.com" required>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="api_key">API Key (OpenAI / Anthropic / Custom)</label>
                    <input type="password" id="api_key" class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg px-4 py-2.5 text-on-surface focus:outline-none focus:border-primary" placeholder="Leave empty to keep existing key">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="slot">Sync Slot Time</label>
                        <input type="text" id="slot" class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg px-4 py-2.5 text-on-surface focus:outline-none focus:border-primary" placeholder="12:00" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="promt_id">Prompt Template</label>
                        <select id="promt_id" class="w-full bg-surface-container-lowest border border-outline-variant rounded-lg px-4 py-2.5 text-on-surface focus:outline-none focus:border-primary">
                            <!-- Populated dynamically -->
                        </select>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6 border-t border-outline-variant pt-4">
                    <button type="button" class="border border-outline-variant text-outline hover:text-on-surface hover:bg-surface-container-high px-4 py-2.5 rounded-lg font-medium" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="bg-primary text-on-primary hover:bg-primary-fixed px-5 py-2.5 rounded-lg font-semibold transition-colors">Save Configurations</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let allSites = [];
        let selectedSite = null;
        let prompts = [];

        document.addEventListener('DOMContentLoaded', () => {
            fetchPrompts();
            fetchSites();
        });

        async function fetchPrompts() {
            try {
                prompts = [
                    { id: 1, name: 'Newsletter Compiler' },
                    { id: 2, name: 'Tech Blog Writer' }
                ];
                
                const select = document.getElementById('promt_id');
                select.innerHTML = '<option value="">Select Prompt Template</option>';
                prompts.forEach(p => {
                    select.innerHTML += `<option value="${p.id}">${p.name}</option>`;
                });
            } catch (err) {
                console.error("Error fetching prompts:", err);
            }
        }

        async function fetchSites() {
            try {
                const response = await fetch('/api/v1/sites');
                const result = await response.json();
                allSites = result.data || result;
                renderSites(allSites);
            } catch (err) {
                console.error("Error loading sites:", err);
            }
        }

        function renderSites(sites) {
            const tbody = document.getElementById('sites-table-body');
            tbody.innerHTML = '';

            let activeCount = 0;
            let pendingCount = 0;

            if (sites.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center text-outline py-12">No client ecosystems registered yet.</td></tr>`;
                updateKPIs(0, 0, 0);
                return;
            }

            sites.forEach((site, index) => {
                const tr = document.createElement('tr');
                tr.className = `h-14 hover:bg-surface-container-high transition-colors cursor-pointer group border-l-2 ${selectedSite && selectedSite.id === site.id ? 'bg-primary/5 border-l-primary' : 'border-l-transparent'}`;
                tr.id = `site-row-${site.id}`;

                tr.onclick = (e) => {
                    if (e.target.closest('button') || e.target.closest('a')) return;
                    selectSite(site);
                };

                if (site.last_sync_status === 'syncing') activeCount++;
                if (!site.last_synced_at) pendingCount++;

                let statusBadge = '';
                if (site.last_sync_status === 'success') {
                    statusBadge = `
                        <span class="flex items-center gap-1.5 text-secondary font-label-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-secondary shadow-[0_0_5px_rgba(78,222,163,0.6)]"></span> Healthy
                        </span>`;
                } else if (site.last_sync_status === 'failed') {
                    statusBadge = `
                        <span class="flex items-center gap-1.5 text-error font-label-sm">
                            <span class="material-symbols-outlined text-[14px]">error</span> API Limit
                        </span>`;
                } else if (site.last_sync_status === 'syncing') {
                    statusBadge = `
                        <span class="flex items-center gap-1.5 text-primary-container font-label-sm">
                            <span class="material-symbols-outlined text-[14px] animate-spin">sync</span> Syncing
                        </span>`;
                } else {
                    statusBadge = `
                        <span class="flex items-center gap-1.5 text-outline font-label-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-outline"></span> Pending
                        </span>`;
                }

                const dummyImgs = [
                    'https://lh3.googleusercontent.com/aida-public/AB6AXuBIAzTgbMCZWeGnFcP5bNnIFkwrY9Hp-pJHXQTeN1PbqLuykUX3v-bZ4_ukCs06vIArgysk7AmceQuwxsQV9E953bs3jbd2EZ7rzPQphHFQ3JkO9xuggUPuk8Zg4AWBYgAbHVGpVqTBzK8qAr0Qp8qA4xzs4yOq5EtEwuZg_m4PQ1WUx-sJQv2bkLBUZnebfdWsNlv61VR7MUlmAwefdQrtY001PATpkz-3SMlXQ-rCWtP5SHVXBwYfA6U5lma_MQbC5JIiKHG2iRs',
                    'https://lh3.googleusercontent.com/aida-public/AB6AXuAHtGmprWWQDYyuHN6KkFtdPsjoKZvtSda5J3iLtW_ZJHCn9hUlkIb7wq24B3Bo5w_H3Wlg6IY_B2pZRF5xmv10VsTTuGdIcNPWjA3QICcZWH_GPZm7k1K5UJOqQzhrQyFUp0SpZy9pKUQQ0ZXdQbgAfTM943zD9JF69D2tBgnOylwzYGjKri9qO3XS4az8Lel6WraZEQEMgDpqSVUKLLqDERPl05UtzQFJgbQyoWdCBbG8Jy11QHXvnqbKZH48_pujWOCYg_PjC00',
                    'https://lh3.googleusercontent.com/aida-public/AB6AXuAQnUofjkDSvCdeGkW5i9AAn_K8PvaKzimr6DfNcOKDmkCMB511LeuJP9NoFt_H-FX35E7pyrpSzsaUQTs1OyOITL9Ez3MVfBTdE3WJ39Ly45_XnG8grbcIcgKL-Oj2j324HoiGi6H2Gri8d0rsysBc9n6hi01eZcCb8HXrH5zgopoR2zmCXBi0yuFwJ9V3HFOWTUm9IVyN76_3DV3XBGnKWmKV2_gqGGZLs-sTsJ9DTepunh0MBkBlWy7mkO62uMXU55VBqoj-YEo'
                ];
                const siteImg = dummyImgs[index % dummyImgs.length];
                const displayName = site.domain_url.replace(/https?:\/\/(www\.)?/, '');

                tr.innerHTML = `
                    <td class="px-stack-md py-2">
                        <div class="flex items-center gap-3">
                            <img class="w-6 h-6 rounded bg-surface-container-highest border border-outline-variant/50 object-cover" src="${siteImg}"/>
                            <span class="font-label-md text-on-surface hover:text-primary transition-colors">${displayName}</span>
                            <span class="font-mono-sm text-outline text-[11px] bg-surface-container-lowest px-1.5 py-0.5 rounded border border-outline-variant">ID-${site.id}</span>
                        </div>
                    </td>
                    <td class="px-stack-md py-2">${site.slot || '12:00'}</td>
                    <td class="px-stack-md py-2 font-mono-sm">${site.last_synced_at ? timeSince(site.last_synced_at) : 'Never'}</td>
                    <td class="px-stack-md py-2">
                        <span class="px-2 py-1 rounded border border-outline-variant bg-surface-container-lowest text-outline font-label-sm text-[11px] flex items-center gap-1 w-fit">
                            <span class="material-symbols-outlined text-[12px]">description</span> ${site.promt ? site.promt.name : 'Default'}
                        </span>
                    </td>
                    <td class="px-stack-md py-2">${statusBadge}</td>
                    <td class="px-stack-md py-2 text-right">
                        <button onclick="editSite(${site.id})" class="text-outline hover:text-primary transition-colors p-1 mr-2">
                            <span class="material-symbols-outlined text-sm">settings</span>
                        </button>
                        <button onclick="triggerSync(${site.id}, this)" class="text-outline hover:text-secondary transition-colors p-1">
                            <span class="material-symbols-outlined text-sm">sync</span>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            updateKPIs(sites.length, activeCount, pendingCount);

            if (selectedSite) {
                const refreshed = sites.find(s => s.id === selectedSite.id);
                if (refreshed) selectSite(refreshed);
            }
        }

        function updateKPIs(total, active, pending) {
            document.getElementById('kpi-total-sites').innerText = total;
            document.getElementById('kpi-active-syncs').innerText = active;
            document.getElementById('kpi-pending-syncs').innerText = pending;
        }

        function timeSince(dateStr) {
            const date = new Date(dateStr);
            const seconds = Math.floor((new Date() - date) / 1000);
            let interval = seconds / 60;
            if (interval > 1) return Math.floor(interval) + " mins ago";
            return "just now";
        }

        function selectSite(site) {
            selectedSite = site;
            fetchSitesTableSelectState();

            const inspector = document.getElementById('inspector-content');
            let logsHtml = '';
            if (site.last_sync_status === 'success') {
                logsHtml = `
                    <div class="text-secondary"><span class="text-primary">[SUCCESS]</span> Connection healthy.</div>
                    <div class="text-outline-variant"><span class="text-primary">[INFO]</span> Successfully synced settings.</div>
                `;
            } else if (site.last_sync_status === 'failed') {
                logsHtml = `
                    <div class="text-error"><span class="text-danger">[ERROR]</span> Sync failed: ${site.error_log || 'Unknown connection error.'}</div>
                `;
            } else if (site.last_sync_status === 'syncing') {
                logsHtml = `
                    <div class="text-primary-container animate-pulse"><span class="text-primary">[WAIT]</span> Awaiting response stream...</div>
                `;
            } else {
                logsHtml = `
                    <div class="text-outline-variant"><span class="text-primary">[PENDING]</span> Connection pending.</div>
                `;
            }

            const cleanUrl = site.domain_url.replace(/https?:\/\/(www\.)?/, '');

            inspector.innerHTML = `
                <div class="flex flex-col items-center text-center gap-2 pb-stack-md border-b border-outline-variant/50">
                    <div class="relative">
                        <div class="w-16 h-16 rounded-xl bg-primary/20 border border-primary/30 flex items-center justify-center font-bold text-primary text-xl">
                            ${cleanUrl.charAt(0).toUpperCase()}
                        </div>
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full ${site.last_sync_status === 'success' ? 'bg-secondary' : 'bg-error'} border-2 border-surface-container"></div>
                    </div>
                    <div>
                        <h3 class="font-headline-md text-[20px] text-on-surface font-semibold mt-2">${cleanUrl}</h3>
                        <a class="font-body-sm text-body-sm text-primary hover:underline flex items-center justify-center gap-1 mt-1" href="${site.domain_url}" target="_blank">
                            Open Site URL <span class="material-symbols-outlined text-[12px]">open_in_new</span>
                        </a>
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <div class="flex justify-between items-center">
                        <span class="font-body-sm text-outline">Sync Status</span>
                        <span class="px-2 py-0.5 rounded font-label-sm text-[11px] capitalize ${site.last_sync_status === 'success' ? 'bg-secondary/10 border border-secondary/30 text-secondary' : 'bg-error-container/20 border border-error-container/40 text-error'}">
                            ${site.last_sync_status || 'Pending'}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="font-body-sm text-outline">Schedule Slot</span>
                        <span class="font-mono-sm text-on-surface bg-surface-container-high px-2 py-0.5 rounded border border-outline-variant">${site.slot || '12:00'}</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 mt-2">
                    <button onclick="triggerSync(${site.id}, this)" class="bg-surface-container-high border border-outline-variant hover:border-primary/50 text-on-surface-variant hover:text-primary transition-all py-2 rounded-lg font-label-sm flex flex-col items-center gap-1">
                        <span class="material-symbols-outlined text-sm">sync</span> Force Sync
                    </button>
                    <button onclick="deleteSite(${site.id})" class="bg-surface-container-high border border-outline-variant hover:border-error/50 text-on-surface-variant hover:text-error transition-all py-2 rounded-lg font-label-sm flex flex-col items-center gap-1">
                        <span class="material-symbols-outlined text-sm">delete</span> Delete Site
                    </button>
                </div>

                <div class="mt-4 flex-1 flex flex-col">
                    <h4 class="font-label-sm text-outline uppercase tracking-wider mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">terminal</span> Console Logs
                    </h4>
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-lg p-3 flex-1 font-mono-sm text-[11px] leading-relaxed text-outline-variant flex flex-col gap-2 overflow-y-auto custom-scrollbar min-h-[150px]">
                        ${logsHtml}
                    </div>
                </div>
            `;
        }

        function fetchSitesTableSelectState() {
            const rows = document.querySelectorAll('#sites-table-body tr');
            rows.forEach(row => {
                if (row.id === `site-row-${selectedSite.id}`) {
                    row.classList.add('bg-primary/5', 'border-l-primary');
                } else {
                    row.classList.remove('bg-primary/5', 'border-l-primary');
                }
            });
        }

        function closeInspector() {
            selectedSite = null;
            document.getElementById('inspector-content').innerHTML = `<div class="text-center text-outline py-12">Select a site to view details</div>`;
            fetchSites();
        }

        async function triggerSync(id, element) {
            element.classList.add('animate-spin');
            try {
                const response = await fetch(`/api/v1/sites/${id}/sync`, { method: 'POST' });
                const result = await response.json();
                if (response.ok) {
                    fetchSites();
                } else {
                    alert("Sync failed: " + result.message);
                }
            } catch (err) {
                console.error("Error triggering sync:", err);
            } finally {
                element.classList.remove('animate-spin');
            }
        }

        function searchSites(val) {
            const query = val.toLowerCase();
            const filtered = allSites.filter(site => site.domain_url.toLowerCase().includes(query));
            renderSites(filtered);
        }

        function openAddModal() {
            document.getElementById('site-id').value = '';
            document.getElementById('domain_url').value = '';
            document.getElementById('api_key').value = '';
            document.getElementById('slot').value = '12:00';
            document.getElementById('promt_id').value = '';
            document.getElementById('modal-title').innerText = 'Add WordPress Site';
            document.getElementById('site-modal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('site-modal').classList.remove('active');
        }

        async function editSite(id) {
            try {
                const response = await fetch(`/api/v1/sites/${id}`);
                const result = await response.json();
                const site = result.data;

                document.getElementById('site-id').value = site.id;
                document.getElementById('domain_url').value = site.domain_url;
                document.getElementById('api_key').value = ''; 
                document.getElementById('slot').value = site.slot || '12:00';
                document.getElementById('promt_id').value = site.promt_id || '';
                
                document.getElementById('modal-title').innerText = 'Edit WordPress Site';
                document.getElementById('site-modal').classList.add('active');
            } catch (err) {
                console.error("Error editing site:", err);
            }
        }

        async function saveSite(e) {
            e.preventDefault();
            const id = document.getElementById('site-id').value;
            const domain_url = document.getElementById('domain_url').value;
            const api_key = document.getElementById('api_key').value;
            const slot = document.getElementById('slot').value;
            const promt_id = document.getElementById('promt_id').value;

            const payload = { domain_url, slot };
            if (api_key) payload.api_key = api_key;
            if (promt_id) payload.promt_id = parseInt(promt_id);

            try {
                let response;
                if (id) {
                    response = await fetch(`/api/v1/sites/${id}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                } else {
                    response = await fetch('/api/v1/sites', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                }

                if (response.ok) {
                    closeModal();
                    fetchSites();
                } else {
                    const result = await response.json();
                    alert("Error: " + JSON.stringify(result.errors || result.message));
                }
            } catch (err) {
                console.error("Error saving site:", err);
            }
        }

        async function deleteSite(id) {
            if (!confirm("Are you sure you want to disconnect this WordPress site?")) return;

            try {
                const response = await fetch(`/api/v1/sites/${id}`, { method: 'DELETE' });
                if (response.ok) {
                    if (selectedSite && selectedSite.id === id) selectedSite = null;
                    fetchSites();
                } else {
                    alert("Error deleting site.");
                }
            } catch (err) {
                console.error("Error deleting site:", err);
            }
        }
    </script>
</body>
</html>
