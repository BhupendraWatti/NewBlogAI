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
                                <tr onclick="inspectElement('audit', 'UPDATE customer_subscriptions', 'info', 'Bhupendra Watti', '192.168.1.42')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5 text-muted">2026-07-01 17:53:02</td>
                                    <td class="p-3 text-text font-medium flex items-center gap-2">
                                        <div class="w-5 h-5 rounded-full bg-accent/20 border border-accent/40 flex items-center justify-center text-[9px] text-accent font-bold">BW</div>
                                        <span>Bhupendra Watti</span>
                                    </td>
                                    <td class="p-3 text-muted">customer_subscriptions</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-accent/20 text-accent border border-accent/30 text-[9px]">UPDATE</span></td>
                                    <td class="p-3 text-muted">192.168.1.42</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]">info</span></td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">Inspect</button>
                                    </td>
                                </tr>
                                <tr onclick="inspectElement('audit', 'DELETE customer_sites', 'warning', 'John Doe', '192.168.1.100')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5 text-muted">2026-07-01 17:48:12</td>
                                    <td class="p-3 text-text font-medium flex items-center gap-2">
                                        <div class="w-5 h-5 rounded-full bg-secondary/20 border border-secondary/40 flex items-center justify-center text-[9px] text-secondary font-bold">JD</div>
                                        <span>John Doe</span>
                                    </td>
                                    <td class="p-3 text-muted">customer_sites</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-danger/20 text-danger border border-danger/30 text-[9px]">DELETE</span></td>
                                    <td class="p-3 text-muted">192.168.1.100</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[9px]">warning</span></td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">Inspect</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

