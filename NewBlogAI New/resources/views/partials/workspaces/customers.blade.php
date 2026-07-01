                <!-- 2. CUSTOMERS WORKSPACE -->
                <div id="node-customers" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Customer Registry</h2>
                            <p class="text-xs text-muted">Manage active SaaS clients, adjust credit caps, and write billing notes.</p>
                        </div>
                        <button onclick="launchCreationWizard('customer')" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                            <span class="material-symbols-outlined text-sm font-bold">add</span> Register Customer
                        </button>
                    </div>

                    <!-- Datagrid Table -->
                    <div class="glass-surface rounded-2xl overflow-hidden border border-border">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase tracking-wider">
                                    <th class="p-3 pl-5">Company</th>
                                    <th class="p-3">Owner</th>
                                    <th class="p-3">Email</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3">Health Score</th>
                                    <th class="p-3 text-right pr-5">Operations</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-xs font-mono" id="customers-table-body">
                                <!-- TODO: Populate from GET /api/v1/customers -->
                            </tbody>
                        </table>
                        
                        <!-- Empty State -->
                        <div class="flex flex-col items-center justify-center py-16 text-center" id="customers-empty-state">
                            <span class="material-symbols-outlined text-4xl text-muted mb-3">group</span>
                            <h3 class="font-display font-bold text-base mb-1">No Customers Registered</h3>
                            <p class="text-xs text-muted max-w-xs">No SaaS customers found on this node. Register a new customer to start monitoring usage and limits.</p>
                            <button onclick="launchCreationWizard('customer')" class="mt-4 bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">add</span> Register Customer
                            </button>
                        </div>
                    </div>
                </div>
