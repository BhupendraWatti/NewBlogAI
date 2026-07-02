                <!-- OVERVIEW DASHBOARD WORKSPACE -->
                <div id="node-dashboard" class="workspace-pane space-y-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Executive Command Center</h2>
                            <p class="text-xs text-muted">Platform overview — customers, AI generation, WordPress fleet, and system health at a glance.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2 px-3 py-1.5 bg-neutral-800/40 border border-border rounded-full text-muted text-[10px] font-mono transition-colors" id="system-health-badge">
                                <span class="w-1.5 h-1.5 rounded-full bg-muted animate-pulse" id="system-health-dot"></span>
                                <span id="system-health-text">HEALTH: UNKNOWN</span>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="switchWorkspace('providers')" class="bg-surface hover:bg-surface/80 border border-border text-text font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">neurology</span> Configure AI
                                </button>
                                <button onclick="switchWorkspace('pipeline')" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                                    <span class="material-symbols-outlined text-sm font-bold">auto_awesome</span> Generate Content
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Getting Started Notice (visible until data exists) -->
                    <div class="p-4 bg-accent/10 border border-accent/20 rounded-2xl flex items-start gap-3" id="getting-started-notice">
                        <span class="material-symbols-outlined text-accent text-lg mt-0.5">rocket_launch</span>
                        <div class="text-xs font-mono">
                            <p class="font-bold text-accent">Platform ready — configure your first AI provider to get started.</p>
                            <p class="text-muted mt-0.5">
                                Go to <button onclick="switchWorkspace('providers')" class="text-accent underline">AI Providers</button> to connect an API key, then create topics and generate your first article via the <button onclick="switchWorkspace('pipeline')" class="text-accent underline">Content Pipeline</button>.
                            </p>
                        </div>
                    </div>

                    <!-- KPI Cards — show dashes until real data loads -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Connected Sites</p>
                            <h3 class="text-3xl font-display font-bold text-muted" id="stats-fleet">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">
                                <button onclick="switchWorkspace('sites')" class="hover:text-text transition" id="manage-sites-btn-text">Manage Sites →</button>
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Articles Published</p>
                            <h3 class="text-3xl font-display font-bold text-muted" id="stats-published">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted"><!-- TODO: GET /api/v1/stats/published --></div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Active Topics</p>
                            <h3 class="text-3xl font-display font-bold text-muted" id="stats-topics">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">
                                <button onclick="switchWorkspace('topics')" class="hover:text-text transition">Manage topics →</button>
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Total Customers</p>
                            <h3 class="text-3xl font-display font-bold text-muted" id="stats-customers">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">
                                <button onclick="switchWorkspace('customers')" class="hover:text-text transition">Manage customers →</button>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Access Modules -->
                    <div class="grid grid-cols-3 gap-6">
                        <!-- Recent Activity -->
                        <div class="col-span-2 glass-surface rounded-2xl p-5 space-y-4">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-mono uppercase tracking-widest text-muted font-bold">Recent Activity</h4>
                                <button onclick="switchWorkspace('audit')" class="text-[10px] font-mono text-secondary hover:underline">View all logs →</button>
                            </div>
                            <div id="dashboard-activity-container">
                                <!-- Empty State -->
                                <div class="flex flex-col items-center justify-center py-10 text-center">
                                    <span class="material-symbols-outlined text-3xl text-muted/50 mb-2">history</span>
                                    <p class="text-xs text-muted">No activity yet.</p>
                                    <p class="text-[10px] text-muted/70 mt-1">Platform actions — generation runs, publishes, and configuration changes — will appear here.</p>
                                </div>
                            </div>
                            <p class="text-[10px] text-muted font-mono"><!-- TODO: GET /api/v1/audit-logs?limit=10 --></p>
                        </div>

                        <!-- Quick Actions -->
                        <div class="glass-surface rounded-2xl p-5 space-y-4">
                            <h4 class="text-xs font-mono uppercase tracking-widest text-muted font-bold">Quick Actions</h4>
                            <div class="space-y-2">
                                <button onclick="switchWorkspace('providers')" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl text-xs font-medium bg-surface hover:bg-white/5 border border-border transition">
                                    <span class="material-symbols-outlined text-accent text-base">neurology</span>
                                    <span>Connect AI Provider</span>
                                </button>
                                <button onclick="switchWorkspace('prompts')" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl text-xs font-medium bg-surface hover:bg-white/5 border border-border transition">
                                    <span class="material-symbols-outlined text-secondary text-base">book</span>
                                    <span>Create Prompt Template</span>
                                </button>
                                <button onclick="switchWorkspace('topics')" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl text-xs font-medium bg-surface hover:bg-white/5 border border-border transition">
                                    <span class="material-symbols-outlined text-warning text-base">topic</span>
                                    <span>Add Content Topic</span>
                                </button>
                                <button onclick="switchWorkspace('sites')" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl text-xs font-medium bg-surface hover:bg-white/5 border border-border transition">
                                    <span class="material-symbols-outlined text-muted text-base">language</span>
                                    <span>Connect WordPress Site</span>
                                </button>
                                <button onclick="switchWorkspace('pipeline')" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl text-xs font-medium bg-accent hover:bg-accent/80 text-background transition">
                                    <span class="material-symbols-outlined text-base">auto_awesome</span>
                                    <span>Generate Content Now</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
