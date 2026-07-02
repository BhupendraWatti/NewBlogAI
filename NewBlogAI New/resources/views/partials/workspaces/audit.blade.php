                <!-- 19. AUDIT LOGS & OBSERVABILITY WORKSPACE -->
                <div id="node-audit" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Audit Logs &amp; Observability</h2>
                            <p class="text-xs text-muted">Observability stream tracking platform operations, user sessions, and database changes.</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="triggerLogPurgeSimulation()" class="bg-surface hover:bg-surface/80 border border-border text-text font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">delete_sweep</span> Purge Obsolete Logs
                            </button>
                            <button onclick="triggerLogExportSimulation()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                                <span class="material-symbols-outlined text-sm font-bold">download</span> Export CSV Log
                            </button>
                        </div>
                    </div>

                    <!-- Telemetry Cards -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Total Logs (24h)</p>
                            <h3 class="text-3xl font-display font-bold text-accent" id="audit-total-logs">14,842</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent">Active indexing stream online</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Error Rate (24h)</p>
                            <h3 class="text-3xl font-display font-bold text-danger">0.42%</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Well within 1.0% target SLA</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Average Response Latency</p>
                            <h3 class="text-3xl font-display font-bold">142ms</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Edge routing optimization</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Retention Policy</p>
                            <h3 class="text-3xl font-display font-bold">90 Days</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Archive cold backup enabled</div>
                        </div>
                    </div>

                    <!-- Audit Log Directory Datagrid Table -->
                    <div class="glass-surface rounded-2xl overflow-hidden border border-border">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase">
                                    <th class="p-3 pl-5">Timestamp</th>
                                    <th class="p-3">Operator</th>
                                    <th class="p-3">Resource Target</th>
                                    <th class="p-3">Action</th>
                                    <th class="p-3">IP Address</th>
                                    <th class="p-3">Severity</th>
                                    <th class="p-3 text-right pr-5">Operations</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-xs font-mono" id="audit-directory-body">
                                <!-- Populated dynamically from GET /api/v1/operations/audit -->
                            </tbody>
                        </table>
                    </div>
                </div>

