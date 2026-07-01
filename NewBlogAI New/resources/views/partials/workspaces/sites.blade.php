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
                            <h3 class="text-3xl font-display font-bold text-muted">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">No active endpoints</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">SSL Health</p>
                            <h3 class="text-3xl font-display font-bold text-muted">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">No domains checked</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Avg Plugin Sync</p>
                            <h3 class="text-3xl font-display font-bold text-muted">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">No plugin synchronized</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">API Errors (24h)</p>
                            <h3 class="text-3xl font-display font-bold text-muted">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">No communication logs</div>
                        </div>
                    </div>

                    <!-- Search & Filter Options -->
                    <div class="flex flex-wrap items-center gap-3 p-3 bg-surface border border-border rounded-2xl">
                        <div class="relative w-64">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted text-sm">search</span>
                            <input class="w-full bg-background border border-border rounded-xl py-1.5 pl-10 pr-4 text-xs font-mono text-text placeholder-muted focus:outline-none focus:border-accent focus:ring-0" placeholder="Search domains..." type="text"/>
                        </div>
                        <select class="bg-background border border-border text-text text-xs rounded-xl py-1.5 pl-2 pr-6 cursor-pointer focus:ring-accent" disabled>
                            <option>All Plugin Versions</option>
                        </select>
                        <select class="bg-background border border-border text-text text-xs rounded-xl py-1.5 pl-2 pr-6 cursor-pointer focus:ring-accent" disabled>
                            <option>SSL Secured</option>
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
                            <tbody class="divide-y divide-border text-xs font-mono" id="sites-table-body">
                                <!-- TODO: Populate from GET /api/v1/sites -->
                            </tbody>
                        </table>
                        
                        <!-- Empty State -->
                        <div class="flex flex-col items-center justify-center py-16 text-center" id="sites-empty-state">
                            <span class="material-symbols-outlined text-4xl text-muted mb-3">language</span>
                            <h3 class="font-display font-bold text-base mb-1">No Connected Websites</h3>
                            <p class="text-xs text-muted max-w-xs">No WordPress sites have been registered to this dashboard yet.</p>
                            <button onclick="launchCreationWizard('site')" class="mt-4 bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">add</span> Register Website
                            </button>
                        </div>
                    </div>
                </div>
