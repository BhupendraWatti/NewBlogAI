                <!-- ADVANCED ANALYTICS WORKSPACE -->
                <div id="node-analytics" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Analytics & Reports</h2>
                            <p class="text-xs text-muted">Platform-wide performance data, AI consumption, and publishing metrics. Data populates after AI requests are executed.</p>
                        </div>
                        <div class="flex gap-2">
                            <button class="bg-surface hover:bg-surface/80 border border-border text-text font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5" disabled>
                                <span class="material-symbols-outlined text-sm">download</span> Export Report
                            </button>
                        </div>
                    </div>

                    <!-- KPI Cards — empty/waiting state -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Total Customers</p>
                            <h3 class="text-3xl font-display font-bold text-muted">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted"><!-- TODO: GET /api/v1/analytics/summary --></div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Articles Generated</p>
                            <h3 class="text-3xl font-display font-bold text-muted">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted"><!-- TODO: GET /api/v1/analytics/summary --></div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">AI Requests (MTD)</p>
                            <h3 class="text-3xl font-display font-bold text-muted">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted"><!-- TODO: GET /api/v1/analytics/summary --></div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Active Sites</p>
                            <h3 class="text-3xl font-display font-bold text-muted">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted"><!-- TODO: GET /api/v1/analytics/summary --></div>
                        </div>
                    </div>

                    <!-- Usage Empty State -->
                    <div class="glass-surface rounded-2xl p-12 flex flex-col items-center justify-center text-center space-y-4">
                        <span class="material-symbols-outlined text-5xl text-muted/50">insert_chart</span>
                        <div>
                            <h3 class="font-display font-bold text-base mb-1">Usage data will appear after AI requests are executed.</h3>
                            <p class="text-xs text-muted max-w-md">Once content generation begins and workflows are run, platform-wide analytics — including token consumption, model usage breakdown, and publishing statistics — will display here.</p>
                        </div>
                        <div class="flex gap-3">
                            <button onclick="switchWorkspace('pipeline')" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">auto_awesome</span> Start Generating Content
                            </button>
                            <button onclick="switchWorkspace('providers')" class="bg-surface hover:bg-white/5 border border-border text-text font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">neurology</span> Configure Providers
                            </button>
                        </div>
                    </div>

                    <!-- Provider Distribution (empty) -->
                    <div class="grid grid-cols-12 gap-6">
                        <div class="col-span-7 glass-surface rounded-2xl p-5 space-y-3">
                            <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Provider Usage Distribution</h4>
                            <div class="flex items-center justify-center py-8">
                                <p class="text-xs text-muted">No data available yet.</p>
                            </div>
                            <p class="text-[10px] text-muted font-mono"><!-- TODO: GET /api/v1/analytics/provider-breakdown --></p>
                        </div>
                        <div class="col-span-5 glass-surface rounded-2xl p-5 space-y-3">
                            <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Top Performing Topics</h4>
                            <div class="flex items-center justify-center py-8">
                                <p class="text-xs text-muted">No data available yet.</p>
                            </div>
                            <p class="text-[10px] text-muted font-mono"><!-- TODO: GET /api/v1/analytics/top-topics --></p>
                        </div>
                    </div>
                </div>
