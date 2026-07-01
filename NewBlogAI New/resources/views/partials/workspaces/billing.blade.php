                <!-- 17. BILLING & USAGE WORKSPACE -->
                <div id="node-billing" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Billing &amp; Usage Ledger</h2>
                            <p class="text-xs text-muted">Audit customer usage transactions, define custom pricing plans, and inspect OpenAI/Anthropic API balances.</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="triggerBillingLockSimulation()" class="bg-surface hover:bg-surface/80 border border-border text-text font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5" disabled>
                                <span class="material-symbols-outlined text-sm">lock_open</span> Verify Billing Locks
                            </button>
                            <button onclick="triggerInvoiceSyncSimulation()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald" disabled>
                                <span class="material-symbols-outlined text-sm font-bold">sync</span> Manual Invoice Sync
                            </button>
                        </div>
                    </div>

                    <!-- Telemetry Cards -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Gross Volume (MTD)</p>
                            <h3 class="text-3xl font-display font-bold text-muted" id="billing-gross-volume">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Stripe integration pending</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Outstanding Invoices</p>
                            <h3 class="text-3xl font-display font-bold text-muted" id="billing-unpaid-invoices">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">No pending retries</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Monthly API Cost (MTD)</p>
                            <h3 class="text-3xl font-display font-bold text-muted">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Cost calculations dynamic</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Overage Alerts</p>
                            <h3 class="text-3xl font-display font-bold text-muted">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">No threshold breaches</div>
                        </div>
                    </div>

                    <!-- Usage Ledger table -->
                    <div class="glass-surface rounded-2xl overflow-hidden border border-border">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase">
                                    <th class="p-3 pl-5">Customer Target</th>
                                    <th class="p-3">Plan Subscribed</th>
                                    <th class="p-3">Token Utilization</th>
                                    <th class="p-3">Accrued Spend</th>
                                    <th class="p-3">Billing Status</th>
                                    <th class="p-3 text-right pr-5">Operations</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-xs font-mono" id="billing-ledger-body">
                                <!-- TODO: Populate from GET /api/v1/billing/ledger -->
                            </tbody>
                        </table>
                        
                        <!-- Empty State -->
                        <div class="flex flex-col items-center justify-center py-16 text-center" id="billing-empty-state">
                            <span class="material-symbols-outlined text-4xl text-muted mb-3">credit_card</span>
                            <h3 class="font-display font-bold text-base mb-1">Usage data will appear after AI requests are executed.</h3>
                            <p class="text-xs text-muted max-w-sm">No transactions or API credit usage data are currently available. Connect customer organizations and initiate content generations to see ledger entries.</p>
                        </div>
                    </div>
                </div>
