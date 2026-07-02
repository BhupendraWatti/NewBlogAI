                <!-- 2. CUSTOMERS WORKSPACE -->
                <div id="node-customers" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Customer Registry</h2>
                            <p class="text-xs text-muted">Manage active SaaS clients, adjust credit caps, and write billing notes.</p>
                        </div>
                        <button onclick="openCustomerAddModal()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
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
                            <button onclick="openCustomerAddModal()" class="mt-4 bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">add</span> Register Customer
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Add/Edit Customer Modal -->
                <div class="modal-overlay" id="customer-modal">
                    <div class="modal-container">
                        <div class="flex justify-between items-center mb-6 border-b border-outline-variant pb-3">
                            <h3 class="text-lg font-semibold font-headline-md text-primary" id="customer-modal-title">Register Customer</h3>
                            <button class="text-outline hover:text-on-surface text-xl" onclick="closeCustomerModal()">&times;</button>
                        </div>
                        <form id="customer-form" onsubmit="saveCustomer(event)">
                            <input type="hidden" id="customer-id">
                            
                            <div class="mb-4">
                                <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="customer-company">Company Name</label>
                                <input type="text" id="customer-company" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary" placeholder="e.g. Acme Corporation" required>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="customer-owner">Owner Name</label>
                                    <input type="text" id="customer-owner" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary" placeholder="e.g. John Doe" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="customer-email">Email Address</label>
                                    <input type="email" id="customer-email" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary" placeholder="e.g. john@acme.com" required>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4 mb-4">
                                <div>
                                    <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="customer-phone">Phone Number</label>
                                    <input type="text" id="customer-phone" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary" placeholder="e.g. +1-555-0199">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="customer-country">Country</label>
                                    <input type="text" id="customer-country" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary" placeholder="e.g. United States">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="customer-status">Status</label>
                                    <select id="customer-status" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary">
                                        <option value="trial" selected>Trial</option>
                                        <option value="active">Active</option>
                                        <option value="suspended">Suspended</option>
                                        <option value="expired">Expired</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 mt-6 border-t border-outline-variant pt-4">
                                <button type="button" class="border border-outline-variant text-outline hover:text-on-surface hover:bg-surface-container-high px-4 py-2.5 rounded-lg font-medium" onclick="closeCustomerModal()">Cancel</button>
                                <button type="submit" class="bg-primary text-on-primary hover:bg-primary-fixed px-5 py-2.5 rounded-lg font-semibold transition-colors">Save Customer</button>
                            </div>
                        </form>
                    </div>
                </div>
