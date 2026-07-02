                <!-- 13. SEO INTELLIGENCE WORKSPACE -->
                <div id="node-seo" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">SEO Intelligence Hub</h2>
                            <p class="text-xs text-muted">Conduct technical audits, view readability ratings, and scan real-time article optimizations.</p>
                        </div>
                        <button onclick="triggerSEOSweepSimulation()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                            <span class="material-symbols-outlined text-sm font-bold">query_stats</span> Run SEO Sweep
                        </button>
                    </div>

                    <!-- Telemetry Cards -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Avg SEO Score</p>
                            <h3 class="text-3xl font-display font-bold text-accent" id="seo-avg-score">94 / 100</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span> Indexing Health Optimal
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Audited Pages</p>
                            <h3 class="text-3xl font-display font-bold">1,842</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Across connected domains</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Duplicate Content Check</p>
                            <h3 class="text-3xl font-display font-bold text-accent">0%</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">All posts verified unique</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Missing Alt Texts</p>
                            <h3 class="text-3xl font-display font-bold text-danger" id="seo-missing-alts">2</h3>
                            <div class="mt-2 text-[10px] font-mono text-danger">Awaiting generation</div>
                        </div>
                    </div>

                    <!-- SEO Datagrid Table -->
                    <div class="glass-surface rounded-2xl overflow-hidden border border-border">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase">
                                    <th class="p-3 pl-5">Article Target</th>
                                    <th class="p-3">Focus Keyword</th>
                                    <th class="p-3">SEO Score</th>
                                    <th class="p-3">Alt Text Stats</th>
                                    <th class="p-3">Canonical Checks</th>
                                    <th class="p-3 text-right pr-5">Operations</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-xs font-mono" id="seo-table-body">
                                <!-- Populated dynamically from GET /api/v1/articles -->
                            </tbody>
                        </table>
                    </div>
                </div>

