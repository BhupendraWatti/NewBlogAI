<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Customer Success Workspace - NewsBlogify AI</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&amp;family=Inter:wght@400;500;600&amp;family=JetBrains+Mono:wght@400&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
        #customers-workspace .glass-panel { 
            background: rgba(31, 41, 55, 0.8); 
            backdrop-filter: blur(12px); 
            -webkit-backdrop-filter: blur(12px); 
            border: 1px solid #1F2937; 
        }
        #customers-workspace ::-webkit-scrollbar { width: 6px; height: 6px; }
        #customers-workspace ::-webkit-scrollbar-track { background: transparent; }
        #customers-workspace ::-webkit-scrollbar-thumb { background: #313540; border-radius: 3px; }
        #customers-workspace ::-webkit-scrollbar-thumb:hover { background: #464554; }
    </style>
</head>
<body id="customers-workspace" class="bg-surface font-body-md text-on-surface antialiased overflow-hidden flex h-screen w-full">

    <!-- Sidebar Navigation (Complex Sidebar Style) -->
    <nav class="bg-surface dark:bg-surface font-body-md text-body-md w-sidebar-width h-screen fixed left-0 top-0 border-r border-outline-variant z-40 hidden md:flex flex-col h-full py-stack-lg px-stack-md">
        <div class="mb-stack-lg px-2 flex items-center gap-3">
            <div class="w-8 h-8 rounded bg-primary flex items-center justify-center text-on-primary font-headline-md font-bold">N</div>
            <div>
                <h1 class="font-headline-md text-headline-md font-bold text-on-surface leading-none">NewsBlogify AI</h1>
                <p class="font-label-sm text-label-sm text-outline mt-1">Enterprise Console</p>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto pr-2 flex flex-col gap-1">
            <a class="flex items-center gap-3 text-on-surface-variant hover:text-on-surface p-2 hover:bg-surface-container-high transition-colors duration-200 rounded-lg" href="/">
                <span class="material-symbols-outlined text-xl">dashboard</span> Dashboard
            </a>
            <a class="flex items-center gap-3 bg-primary-container text-on-primary-container rounded-lg p-2 border-l-2 border-primary shadow-[0_0_15px_rgba(192,193,255,0.3)] hover:bg-surface-container-high transition-colors duration-200" href="/customers">
                <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">group</span> Customers
            </a>
            <a class="flex items-center gap-3 text-on-surface-variant hover:text-on-surface p-2 hover:bg-surface-container-high transition-colors duration-200 rounded-lg" href="/fleet">
                <span class="material-symbols-outlined text-xl">cooking</span> Fleet Manager
            </a>
            <a class="flex items-center gap-3 text-on-surface-variant hover:text-on-surface p-2 hover:bg-surface-container-high transition-colors duration-200 rounded-lg" href="/sites">
                <span class="material-symbols-outlined text-xl">language</span> Sites Manager
            </a>
            <a class="flex items-center gap-3 text-on-surface-variant hover:text-on-surface p-2 hover:bg-surface-container-high transition-colors duration-200 rounded-lg" href="/prompts">
                <span class="material-symbols-outlined text-xl">book</span> Prompt Library
            </a>
        </div>
        <div class="mt-auto flex flex-col gap-2 pt-4 border-t border-outline-variant/30">
            <div class="font-label-sm text-label-sm text-outline">© 2026 NewsBlogify AI.</div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col md:ml-sidebar-width h-screen overflow-hidden bg-background">
        <!-- TopNavBar -->
        <header class="bg-surface/80 dark:bg-surface/80 backdrop-blur-xl font-body-sm text-body-sm sticky top-0 z-30 w-full border-b border-outline-variant flex justify-between items-center h-16 px-gutter shrink-0">
            <div class="flex items-center gap-4 flex-1">
                <div class="relative w-64 md:w-96 focus-within:ring-2 focus-within:ring-primary/20 rounded-full">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
                    <input class="w-full bg-[#0B0F19] border border-[#1F2937] rounded-full py-1.5 pl-10 pr-4 text-on-surface placeholder-outline focus:outline-none focus:border-primary transition-colors text-sm" placeholder="Search customers..." type="text"/>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button class="p-2 text-on-surface-variant hover:bg-surface-container-highest rounded-full transition-all">
                    <span class="material-symbols-outlined">notifications</span>
                </button>
                <button class="p-2 text-on-surface-variant hover:bg-surface-container-highest rounded-full transition-all">
                    <span class="material-symbols-outlined">help</span>
                </button>
            </div>
        </header>

        <!-- Workspace Content -->
        <main class="flex-1 flex overflow-hidden">
            <!-- Central Data Area -->
            <div class="flex-1 flex flex-col h-full overflow-hidden p-gutter">
                <!-- Page Header & Actions -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-stack-lg gap-4 shrink-0">
                    <div>
                        <h2 class="font-headline-lg text-headline-lg text-on-surface">Customer Success Workspace</h2>
                        <p class="text-on-surface-variant text-sm mt-1">Manage enterprise clients and monitor API usage health.</p>
                    </div>
                    <button class="bg-primary hover:bg-primary-fixed text-on-primary font-label-md px-4 py-2 rounded-lg transition-colors flex items-center gap-2 shadow-[0_0_10px_rgba(192,193,255,0.2)]">
                        <span class="material-symbols-outlined text-sm">add</span> Add Customer
                    </button>
                </div>

                <!-- Filters & Controls -->
                <div class="flex flex-wrap items-center gap-3 mb-stack-md shrink-0 p-3 bg-[#111827] border border-[#1F2937] rounded-xl">
                    <div class="flex items-center gap-2 text-on-surface-variant font-label-sm border-r border-[#1F2937] pr-3">
                        <span class="material-symbols-outlined text-sm">filter_list</span>
                        <span>Filters</span>
                    </div>
                    <select class="bg-[#0B0F19] border border-[#1F2937] text-on-surface text-sm rounded-md py-1.5 pl-3 pr-8 focus:ring-primary cursor-pointer hover:border-outline-variant transition-colors appearance-none">
                        <option>By Plan: All</option>
                        <option>Enterprise</option>
                        <option>Pro</option>
                        <option>Standard</option>
                    </select>
                </div>

                <!-- High-Density Data Table Container -->
                <div class="flex-1 bg-[#111827] border border-[#1F2937] rounded-xl overflow-hidden flex flex-col relative">
                    <div class="overflow-x-auto flex-1 h-full">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            <thead class="bg-[#171b26] border-b border-[#1F2937] sticky top-0 z-10">
                                <tr>
                                    <th class="py-3 px-4 font-label-sm text-outline font-medium w-12 text-center">
                                        <input class="rounded bg-[#0B0F19] border-[#1F2937] text-primary focus:ring-primary/20" type="checkbox"/>
                                    </th>
                                    <th class="py-3 px-4 font-label-sm text-outline font-medium">Customer</th>
                                    <th class="py-3 px-4 font-label-sm text-outline font-medium">Company</th>
                                    <th class="py-3 px-4 font-label-sm text-outline font-medium">Plan</th>
                                    <th class="py-3 px-4 font-label-sm text-outline font-medium text-right">Sites</th>
                                    <th class="py-3 px-4 font-label-sm text-outline font-medium text-right">Articles (Today)</th>
                                    <th class="py-3 px-4 font-label-sm text-outline font-medium text-center">Health</th>
                                    <th class="py-3 px-4 font-label-sm text-outline font-medium">Renewal Date</th>
                                </tr>
                            </thead>
                            <tbody class="font-body-sm text-on-surface" id="customers-table-body">
                                <!-- Row 1 -->
                                <tr onclick="selectCustomer('Sarah Jenkins', 'GlobalTech Media', 'Enterprise Tier 2', '$4,500.00', 'Oct 12, 2025', 82)" class="border-b border-[#1F2937] hover:bg-[#1F2937] transition-colors h-12 cursor-pointer bg-primary/5 border-l-2 border-l-primary">
                                    <td class="py-2 px-4 text-center">
                                        <input checked class="rounded bg-[#0B0F19] border-[#1F2937] text-primary focus:ring-primary/20" type="checkbox"/>
                                    </td>
                                    <td class="py-2 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-6 h-6 rounded-full bg-primary flex items-center justify-center text-xs text-on-primary font-bold">SJ</div>
                                            <span class="font-medium text-white">Sarah Jenkins</span>
                                        </div>
                                    </td>
                                    <td class="py-2 px-4 text-on-surface-variant">GlobalTech Media</td>
                                    <td class="py-2 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary/10 text-primary border border-primary/20">Enterprise</span>
                                    </td>
                                    <td class="py-2 px-4 font-mono-sm text-right text-on-surface-variant">142</td>
                                    <td class="py-2 px-4 font-mono-sm text-right">4,291</td>
                                    <td class="py-2 px-4 text-center">
                                        <span class="inline-flex items-center w-2 h-2 rounded-full bg-secondary shadow-[0_0_5px_#4edea3]"></span>
                                    </td>
                                    <td class="py-2 px-4 font-mono-sm text-on-surface-variant">Oct 12, 2025</td>
                                </tr>
                                <!-- Row 2 -->
                                <tr onclick="selectCustomer('Marcus Reed', 'Daily News Co.', 'Pro Tier 1', '$1,200.00', 'Nov 01, 2024', 45)" class="border-b border-[#1F2937] hover:bg-[#1F2937] transition-colors h-12 cursor-pointer">
                                    <td class="py-2 px-4 text-center">
                                        <input class="rounded bg-[#0B0F19] border-[#1F2937] text-primary focus:ring-primary/20" type="checkbox"/>
                                    </td>
                                    <td class="py-2 px-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-6 h-6 rounded-full bg-surface-bright flex items-center justify-center text-xs font-medium text-on-surface">MR</div>
                                            <span class="font-medium">Marcus Reed</span>
                                        </div>
                                    </td>
                                    <td class="py-2 px-4 text-on-surface-variant">Daily News Co.</td>
                                    <td class="py-2 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-surface-bright text-on-surface border border-outline-variant">Pro</span>
                                    </td>
                                    <td class="py-2 px-4 font-mono-sm text-right text-on-surface-variant">45</td>
                                    <td class="py-2 px-4 font-mono-sm text-right">892</td>
                                    <td class="py-2 px-4 text-center">
                                        <span class="inline-flex items-center w-2 h-2 rounded-full bg-[#eab308] shadow-[0_0_5px_#eab308]"></span>
                                    </td>
                                    <td class="py-2 px-4 font-mono-sm text-on-surface-variant">Nov 01, 2024</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Inspector Panel -->
            <aside class="w-inspector-width bg-[#111827] border-l border-[#1F2937] hidden lg:flex flex-col shrink-0 h-full overflow-hidden">
                <div class="p-4 border-b border-[#1F2937] flex items-start justify-between bg-[#171b26]">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded bg-[#0B0F19] flex items-center justify-center font-bold text-primary border border-[#1F2937]" id="inspector-initials">SJ</div>
                        <div>
                            <h3 class="font-label-md text-on-surface" id="inspector-name">Sarah Jenkins</h3>
                            <p class="font-label-sm text-on-surface-variant text-xs" id="inspector-company">GlobalTech Media</p>
                        </div>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-4 flex flex-col gap-6">
                    <div>
                        <h4 class="font-label-sm text-outline mb-3 uppercase tracking-wider text-[10px]">Subscription</h4>
                        <div class="bg-[#0B0F19] rounded-lg border border-[#1F2937] p-3 flex flex-col gap-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-on-surface-variant">Plan</span>
                                <span class="font-medium text-primary text-sm" id="inspector-plan">Enterprise Tier 2</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-on-surface-variant">MRR</span>
                                <span class="font-mono-sm text-on-surface" id="inspector-mrr">$4,500.00</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-on-surface-variant">Renewal</span>
                                <span class="font-mono-sm text-on-surface" id="inspector-renewal">Oct 12, 2025</span>
                            </div>
                            <div class="pt-2 border-t border-[#1F2937]">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-xs text-on-surface-variant">AI Credits</span>
                                    <span class="text-xs font-mono-sm text-on-surface" id="inspector-credits-percent">82%</span>
                                </div>
                                <div class="w-full bg-[#1F2937] rounded-full h-1.5">
                                    <div class="bg-primary h-1.5 rounded-full" id="inspector-credits-bar" style="width: 82%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </main>
    </div>

    <script>
        function selectCustomer(name, company, plan, mrr, renewal, creditUsage) {
            document.getElementById('inspector-name').innerText = name;
            document.getElementById('inspector-company').innerText = company;
            document.getElementById('inspector-plan').innerText = plan;
            document.getElementById('inspector-mrr').innerText = mrr;
            document.getElementById('inspector-renewal').innerText = renewal;
            document.getElementById('inspector-credits-percent').innerText = `${creditUsage}%`;
            document.getElementById('inspector-credits-bar').style.width = `${creditUsage}%`;
            
            const initials = name.split(' ').map(n => n[0]).join('');
            document.getElementById('inspector-initials').innerText = initials;
        }
    </script>
</body>
</html>
