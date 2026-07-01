<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>NewsBlogify AI - WordPress Fleet Manager</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&amp;family=Outfit:wght@400;600;700&amp;family=JetBrains+Mono:wght@400&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "tertiary": "#ffb2b7",
                        "outline": "#908fa0",
                        "secondary-fixed-dim": "#4edea3",
                        "surface-variant": "#313540",
                        "on-error-container": "#ffdad6",
                        "surface-container-low": "#171b26",
                        "error": "#ffb4ab",
                        "tertiary-container": "#ff516a",
                        "surface-container-high": "#262a35",
                        "on-background": "#dfe2f1",
                        "surface-dim": "#0f131d",
                        "surface-container-lowest": "#0a0e18",
                        "surface-container-highest": "#313540",
                        "surface-container": "#1c1f2a",
                        "primary-fixed": "#e1e0ff",
                        "error-container": "#93000a",
                        "on-tertiary": "#67001b",
                        "inverse-surface": "#dfe2f1",
                        "outline-variant": "#464554",
                        "surface": "#0f131d",
                        "on-primary": "#1000a9",
                        "on-tertiary-fixed-variant": "#92002a",
                        "on-secondary-container": "#00311f",
                        "secondary-container": "#00a572",
                        "on-surface-variant": "#c7c4d7",
                        "on-tertiary-fixed": "#40000d",
                        "primary-container": "#8083ff",
                        "on-surface": "#dfe2f1",
                        "surface-bright": "#353944",
                        "inverse-on-surface": "#2c303b",
                        "tertiary-fixed": "#ffdadb",
                        "on-secondary-fixed": "#002113",
                        "background": "#0f131d",
                        "on-secondary-fixed-variant": "#005236",
                        "on-error": "#690005",
                        "on-tertiary-container": "#5b0017",
                        "on-primary-fixed-variant": "#2f2ebe",
                        "surface-tint": "#c0c1ff",
                        "on-secondary": "#003824",
                        "on-primary-fixed": "#07006c",
                        "on-primary-container": "#0d0096",
                        "inverse-primary": "#494bd6",
                        "primary-fixed-dim": "#c0c1ff",
                        "primary": "#c0c1ff",
                        "secondary": "#4edea3",
                        "tertiary-fixed-dim": "#ffb2b7",
                        "secondary-fixed": "#6ffbbe"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "margin-page": "32px",
                        "inspector-width": "320px",
                        "stack-md": "16px",
                        "gutter": "24px",
                        "stack-xs": "4px",
                        "stack-sm": "8px",
                        "stack-lg": "24px",
                        "sidebar-width": "260px"
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
        #fleet-workspace .pulse-emerald { animation: pulse-emerald 2s infinite; }
        @keyframes pulse-emerald {
            0% { box-shadow: 0 0 0 0 rgba(78, 222, 163, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(78, 222, 163, 0); }
            100% { box-shadow: 0 0 0 0 rgba(78, 222, 163, 0); }
        }
        #fleet-workspace .pulse-error { animation: pulse-error 2s infinite; }
        @keyframes pulse-error {
            0% { box-shadow: 0 0 0 0 rgba(255, 180, 171, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(255, 180, 171, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 180, 171, 0); }
        }
        #fleet-workspace .glass-panel { background: rgba(31, 41, 55, 0.8); backdrop-filter: blur(12px); }
        #fleet-workspace ::-webkit-scrollbar { width: 6px; }
        #fleet-workspace ::-webkit-scrollbar-track { background: transparent; }
        #fleet-workspace ::-webkit-scrollbar-thumb { background-color: #313540; border-radius: 20px; }
    </style>
</head>
<body id="fleet-workspace" class="bg-background text-on-background min-h-screen flex font-body-md overflow-hidden">

    <!-- Sidebar Navigation (Complex Sidebar Style) -->
    <nav class="w-sidebar-width h-screen fixed left-0 top-0 bg-surface dark:bg-surface border-r border-outline-variant flex flex-col py-stack-lg px-stack-md z-40">
        <div class="mb-8 px-2 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-primary-container flex items-center justify-center text-on-primary-container">
                <span class="material-symbols-outlined">language</span>
            </div>
            <div>
                <div class="font-headline-md text-headline-md font-bold text-on-surface">NewsBlogify AI</div>
                <div class="font-label-sm text-label-sm text-on-surface-variant">Enterprise Console</div>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto custom-scrollbar flex flex-col gap-1">
            <a class="flex items-center gap-3 text-on-surface-variant hover:text-on-surface p-2 hover:bg-surface-container-high transition-colors duration-200 rounded-lg" href="/">
                <span class="material-symbols-outlined">dashboard</span> Dashboard
            </a>
            <a class="flex items-center gap-3 text-on-surface-variant hover:text-on-surface p-2 hover:bg-surface-container-high transition-colors duration-200 rounded-lg" href="/customers">
                <span class="material-symbols-outlined">group</span> Customers
            </a>
            <a class="flex items-center gap-3 bg-primary-container text-on-primary-container rounded-lg p-2 border-l-2 border-primary shadow-[0_0_15px_rgba(192,193,255,0.3)] hover:bg-surface-container-high transition-colors duration-200" href="/fleet">
                <span class="material-symbols-outlined">cooking</span> Fleet Manager
            </a>
            <a class="flex items-center gap-3 text-on-surface-variant hover:text-on-surface p-2 hover:bg-surface-container-high transition-colors duration-200 rounded-lg" href="/sites">
                <span class="material-symbols-outlined">language</span> Sites Manager
            </a>
            <a class="flex items-center gap-3 text-on-surface-variant hover:text-on-surface p-2 hover:bg-surface-container-high transition-colors duration-200 rounded-lg" href="/prompts">
                <span class="material-symbols-outlined">book</span> Prompt Library
            </a>
        </div>
        <div class="mt-auto pt-4 border-t border-outline-variant flex flex-col gap-2">
            <div class="font-label-sm text-label-sm text-outline">© 2026 NewsBlogify AI.</div>
        </div>
    </nav>

    <!-- Main Workspace -->
    <main class="flex-1 ml-sidebar-width flex flex-col h-screen relative mr-[320px]">
        <!-- TopNavBar -->
        <header class="sticky top-0 z-50 w-full bg-surface/80 dark:bg-surface/80 backdrop-blur-xl border-b border-outline-variant flex justify-between items-center h-16 px-gutter">
            <div class="flex items-center gap-4 flex-1">
                <div class="relative w-96 focus-within:ring-2 focus-within:ring-primary/20 rounded-full transition-all">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
                    <input id="fleet-search" oninput="searchFleet(this.value)" class="w-full bg-surface-container-lowest border border-outline-variant rounded-full pl-10 pr-4 py-2 text-body-sm font-body-sm text-on-surface focus:outline-none focus:border-primary placeholder-on-surface-variant" placeholder="Search fleet..." type="text"/>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="fetchFleet()" class="w-10 h-10 rounded-full flex items-center justify-center text-on-surface-variant hover:bg-surface-container-highest transition-all">
                    <span class="material-symbols-outlined">refresh</span>
                </button>
            </div>
        </header>

        <!-- Canvas -->
        <div class="flex-1 overflow-y-auto custom-scrollbar p-margin-page pb-24">
            <div class="flex justify-between items-end mb-8">
                <div>
                    <h1 class="font-display-lg text-display-lg text-on-surface mb-2">Fleet Manager</h1>
                    <p class="font-body-lg text-body-lg text-on-surface-variant">Monitor and control all connected WordPress instances.</p>
                </div>
            </div>

            <!-- KPI Grid -->
            <div class="grid grid-cols-4 gap-gutter mb-8">
                <div class="bg-surface-container border border-outline-variant rounded-xl p-6 relative overflow-hidden group hover:border-primary transition-colors">
                    <div class="font-label-md text-label-md text-on-surface-variant mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px]">language</span> Total Sites
                    </div>
                    <div class="font-headline-lg text-headline-lg text-on-surface" id="fleet-total-count">0</div>
                </div>
                <div class="bg-surface-container border border-outline-variant rounded-xl p-6 relative overflow-hidden group hover:border-primary transition-colors">
                    <div class="font-label-md text-label-md text-on-surface-variant mb-2 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-secondary pulse-emerald"></span> Online
                    </div>
                    <div class="font-headline-lg text-headline-lg text-on-surface" id="fleet-online-count">0</div>
                </div>
                <div class="bg-surface-container border border-outline-variant rounded-xl p-6 relative overflow-hidden group hover:border-primary transition-colors">
                    <div class="font-label-md text-label-md text-on-surface-variant mb-2 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-error pulse-error"></span> Offline
                    </div>
                    <div class="font-headline-lg text-headline-lg text-on-surface" id="fleet-offline-count">0</div>
                </div>
                <div class="bg-surface-container border border-outline-variant rounded-xl p-6 relative overflow-hidden group hover:border-primary transition-colors">
                    <div class="font-label-md text-label-md text-on-surface-variant mb-2 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px] animate-spin">sync</span> Syncing Now
                    </div>
                    <div class="font-headline-lg text-headline-lg text-on-surface" id="fleet-syncing-count">0</div>
                </div>
            </div>

            <!-- Fleet Table -->
            <div class="bg-surface-container border border-outline-variant rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-outline-variant flex justify-between items-center bg-surface-container-low">
                    <h2 class="font-headline-md text-headline-md text-on-surface">Active Fleet</h2>
                </div>
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-outline-variant text-on-surface-variant font-label-sm text-label-sm uppercase tracking-wider bg-surface-container-lowest/50">
                            <th class="px-6 py-3">Website</th>
                            <th class="px-6 py-3">Connection Details</th>
                            <th class="px-6 py-3">Plugin v.</th>
                            <th class="px-6 py-3">Last Sync</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="font-body-sm text-body-sm divide-y divide-outline-variant" id="fleet-table-body">
                        <!-- Loaded dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Right Inspector Panel -->
    <aside class="w-inspector-width h-screen fixed right-0 top-0 bg-surface border-l border-outline-variant flex flex-col z-40">
        <div class="px-6 py-5 border-b border-outline-variant flex justify-between items-center bg-surface-container-low">
            <h3 class="font-headline-md text-headline-md text-on-surface">Site Inspector</h3>
        </div>
        <div class="flex-1 overflow-y-auto custom-scrollbar p-6" id="fleet-inspector-content">
            <div class="text-center text-outline py-12">Select a fleet node to view details</div>
        </div>
    </aside>

    <script>
        let fleetSites = [];
        let selectedFleetSite = null;

        document.addEventListener('DOMContentLoaded', () => {
            fetchFleet();
        });

        async function fetchFleet() {
            try {
                const response = await fetch('/api/v1/sites');
                const result = await response.json();
                fleetSites = result.data || result;
                renderFleet(fleetSites);
            } catch (err) {
                console.error("Error loading fleet:", err);
            }
        }

        function renderFleet(sites) {
            const tbody = document.getElementById('fleet-table-body');
            tbody.innerHTML = '';

            let online = 0;
            let offline = 0;
            let syncing = 0;

            if (sites.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center text-outline py-12">No fleet nodes registered.</td></tr>`;
                updateFleetKPIs(0, 0, 0, 0);
                return;
            }

            sites.forEach((site, index) => {
                const tr = document.createElement('tr');
                tr.className = `hover:bg-surface-container-highest transition-colors cursor-pointer group ${selectedFleetSite && selectedFleetSite.id === site.id ? 'bg-surface-container-high/30' : ''}`;
                
                tr.onclick = (e) => {
                    if (e.target.closest('button')) return;
                    selectFleetSite(site);
                };

                if (site.last_sync_status === 'success') online++;
                else if (site.last_sync_status === 'failed') offline++;
                else if (site.last_sync_status === 'syncing') syncing++;
                else online++;

                let statusBadge = '';
                if (site.last_sync_status === 'success') {
                    statusBadge = `
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-secondary pulse-emerald"></span>
                            <span class="text-secondary font-label-sm text-label-sm">Connected</span>
                        </div>`;
                } else if (site.last_sync_status === 'failed') {
                    statusBadge = `
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-error pulse-error"></span>
                            <span class="text-error font-label-sm text-label-sm">Auth Failed</span>
                        </div>`;
                } else {
                    statusBadge = `
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                            <span class="text-primary font-label-sm text-label-sm">Syncing</span>
                        </div>`;
                }

                const cleanUrl = site.domain_url.replace(/https?:\/\/(www\.)?/, '');

                tr.innerHTML = `
                    <td class="px-6 py-3">
                        <div>
                            <div class="font-label-md text-label-md text-on-surface group-hover:text-primary transition-colors">${cleanUrl}</div>
                            <div class="font-mono-sm text-mono-sm text-on-surface-variant">ID: site_00${site.id}</div>
                        </div>
                    </td>
                    <td class="px-6 py-3 text-on-surface-variant">Slot: ${site.slot || '12:00'}</td>
                    <td class="px-6 py-3 font-mono-sm text-mono-sm text-on-surface">v2.4.1</td>
                    <td class="px-6 py-3 text-on-surface-variant">${site.last_synced_at ? timeSince(site.last_synced_at) : 'Never'}</td>
                    <td class="px-6 py-3">${statusBadge}</td>
                    <td class="px-6 py-3 text-right">
                        <button onclick="triggerSync(${site.id}, this)" class="text-on-surface-variant hover:text-primary transition-colors">
                            <span class="material-symbols-outlined text-[20px]">sync</span>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            updateFleetKPIs(sites.length, online, offline, syncing);

            if (selectedFleetSite) {
                const refreshed = sites.find(s => s.id === selectedFleetSite.id);
                if (refreshed) selectFleetSite(refreshed);
            }
        }

        function updateFleetKPIs(total, online, offline, syncing) {
            document.getElementById('fleet-total-count').innerText = total;
            document.getElementById('fleet-online-count').innerText = online;
            document.getElementById('fleet-offline-count').innerText = offline;
            document.getElementById('fleet-syncing-count').innerText = syncing;
        }

        function timeSince(dateStr) {
            const date = new Date(dateStr);
            const seconds = Math.floor((new Date() - date) / 1000);
            let interval = seconds / 60;
            if (interval > 1) return Math.floor(interval) + " mins ago";
            return "just now";
        }

        function selectFleetSite(site) {
            selectedFleetSite = site;
            const content = document.getElementById('fleet-inspector-content');
            
            const cleanUrl = site.domain_url.replace(/https?:\/\/(www\.)?/, '');
            
            let logsHtml = '';
            if (site.last_sync_status === 'success') {
                logsHtml = `
                    <div class="flex gap-3 items-start">
                        <span class="material-symbols-outlined text-secondary text-[16px] mt-0.5">check_circle</span>
                        <div>
                            <div class="font-body-sm text-on-surface">Successfully pushed article templates</div>
                            <div class="font-mono-sm text-on-surface-variant text-[11px] mt-1">Today, healthy connection.</div>
                        </div>
                    </div>
                `;
            } else if (site.last_sync_status === 'failed') {
                logsHtml = `
                    <div class="flex gap-3 items-start">
                        <span class="material-symbols-outlined text-error text-[16px] mt-0.5">warning</span>
                        <div>
                            <div class="font-body-sm text-on-surface">Error: ${site.error_log || 'Authentication credentials refused.'}</div>
                        </div>
                    </div>
                `;
            } else {
                logsHtml = `<div class="text-outline text-xs">Waiting for connection log stream...</div>`;
            }

            content.innerHTML = `
                <div class="mb-8 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-lg bg-primary-container flex items-center justify-center font-bold text-primary text-xl">
                        ${cleanUrl.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="font-label-md text-label-md text-on-surface text-lg">${cleanUrl}</div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="w-2 h-2 rounded-full ${site.last_sync_status === 'success' ? 'bg-secondary pulse-emerald' : 'bg-error pulse-error'}"></span>
                            <span class="${site.last_sync_status === 'success' ? 'text-secondary' : 'text-error'} font-label-sm text-label-sm">${site.last_sync_status || 'Offline'}</span>
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <h4 class="font-label-md text-label-md text-on-surface-variant mb-3 uppercase tracking-wider">Plugin Health</h4>
                    <div class="bg-surface-container rounded-lg border border-outline-variant p-4">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-on-surface font-body-sm">Version</span>
                            <span class="font-mono-sm text-primary bg-primary/10 px-2 py-1 rounded">v2.4.1 (Latest)</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-on-surface font-body-sm">REST API Reach</span>
                            <span class="text-secondary flex items-center gap-1 font-body-sm"><span class="material-symbols-outlined text-[16px]">check_circle</span> Reachable</span>
                        </div>
                    </div>
                </div>

                <div>
                    <h4 class="font-label-md text-label-md text-on-surface-variant mb-3 uppercase tracking-wider">Recent Sync Logs</h4>
                    <div class="space-y-3">
                        ${logsHtml}
                    </div>
                </div>
            `;
        }

        async function triggerSync(id, btn) {
            btn.classList.add('animate-spin');
            try {
                await fetch(`/api/v1/sites/${id}/sync`, { method: 'POST' });
                fetchFleet();
            } catch (err) {
                console.error("Sync failed:", err);
            } finally {
                btn.classList.remove('animate-spin');
            }
        }

        function searchFleet(val) {
            const query = val.toLowerCase();
            const filtered = fleetSites.filter(s => s.domain_url.toLowerCase().includes(query));
            renderFleet(filtered);
        }
    </script>
</body>
</html>
