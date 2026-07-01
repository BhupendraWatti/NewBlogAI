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
                            <h4 class="text-2xl font-bold text-muted">—</h4>
                        </div>
                        <div class="bg-surface rounded-xl p-4 border border-border">
                            <p class="text-[10px] font-mono text-muted uppercase">Uptime Avg</p>
                            <h4 class="text-2xl font-bold text-muted">—</h4>
                        </div>
                        <div class="bg-surface rounded-xl p-4 border border-border">
                            <p class="text-[10px] font-mono text-muted uppercase">Errors Today</p>
                            <h4 class="text-2xl font-bold text-muted">—</h4>
                        </div>
                        <div class="bg-surface rounded-xl p-4 border border-border">
                            <p class="text-[10px] font-mono text-muted uppercase">Sync Duration</p>
                            <h4 class="text-2xl font-bold text-muted">—</h4>
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
                            <tbody class="divide-y divide-border text-xs font-mono" id="fleet-table-body">
                                <!-- TODO: Populate from GET /api/v1/fleet/status -->
                            </tbody>
                        </table>
                        
                        <!-- Empty State -->
                        <div class="flex flex-col items-center justify-center py-16 text-center" id="fleet-empty-state">
                            <span class="material-symbols-outlined text-4xl text-muted mb-3">cooking</span>
                            <h3 class="font-display font-bold text-base mb-1">No Connected Websites</h3>
                            <p class="text-xs text-muted max-w-xs">No active WordPress domains found in the fleet catalog. Connect a site to start automated publishing synchronization.</p>
                            <button onclick="switchWorkspace('sites')" class="mt-4 bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">language</span> Manage Sites
                            </button>
                        </div>
                    </div>
                </div>
