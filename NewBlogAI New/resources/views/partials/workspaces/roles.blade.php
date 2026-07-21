                <!-- 16. USER & ROLE MANAGEMENT WORKSPACE -->
                <div id="node-roles" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Identity &amp; Access Manager</h2>
                            <p class="text-xs text-muted">Assign user access privileges, configure security permissions, and audit system credentials.</p>
                        </div>
                        <button onclick="openUserAddModal()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                            <span class="material-symbols-outlined text-sm font-bold">person_add</span> Invite Platform Operator
                        </button>
                    </div>

                    <!-- Telemetry Cards -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Total Users</p>
                            <h3 class="text-3xl font-display font-bold text-accent" id="roles-total-users">24</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span> Identity Access Online
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Active Roles</p>
                            <h3 class="text-3xl font-display font-bold">3 Scopes</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Super Admin, Customer, Employee</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">SAML SSO Integration</p>
                            <h3 class="text-3xl font-display font-bold text-accent">Active</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Local AD Directory sync active</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Security Exceptions</p>
                            <h3 class="text-3xl font-display font-bold text-success">0</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">All session audits passed</div>
                        </div>
                    </div>

                    <!-- Users Directory Datagrid Table -->
                    <div class="glass-surface rounded-2xl overflow-hidden border border-border">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase">
                                    <th class="p-3 pl-5 w-8"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20"/></th>
                                    <th class="p-3">Operator Name</th>
                                    <th class="p-3">Email Address</th>
                                    <th class="p-3">Assigned Role</th>
                                    <th class="p-3">Two-Factor Auth</th>
                                    <th class="p-3 text-right pr-5">Operations</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-xs font-mono" id="roles-directory-body">
                                <!-- Populated dynamically from GET /api/v1/users -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-overlay" id="user-modal">
                    <div class="modal-container">
                        <div class="flex justify-between items-center mb-6 border-b border-border pb-3">
                            <h3 class="text-base font-bold font-display text-text" id="user-modal-title">Invite Platform Operator</h3>
                            <button class="text-muted hover:text-text text-xl transition" onclick="closeUserModal()">&times;</button>
                        </div>
                        <form id="user-form" onsubmit="saveUser(event)">
                            <input type="hidden" id="user-id">
                            
                            <div class="mb-4">
                                <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="user-name">Full Name</label>
                                <input type="text" id="user-name" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. Aarav Sharma" required>
                            </div>

                            <div class="mb-4">
                                <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="user-email">Email Address</label>
                                <input type="email" id="user-email" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. aarav@company.com" required>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="user-role-select">Assigned Role</label>
                                    <select id="user-role-select" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent">
                                        <option value="1">Super Admin</option>
                                        <option value="2">Customer</option>
                                        <option value="3" selected>Employee</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="user-password">Password</label>
                                    <input type="password" id="user-password" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="Set secure password">
                                </div>
                            </div>

                            <div class="flex justify-end gap-3 mt-6 border-t border-border pt-4">
                                <button type="button" class="border border-border text-muted hover:text-text hover:bg-white/5 px-4 py-2.5 rounded-xl font-medium transition" onclick="closeUserModal()">Cancel</button>
                                <button type="submit" class="bg-accent hover:bg-accent/80 text-background px-5 py-2.5 rounded-xl font-semibold transition">Save User</button>
                            </div>
                        </form>
                    </div>
                </div>

