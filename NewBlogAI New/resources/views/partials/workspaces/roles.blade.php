                <!-- 16. USER & ROLE MANAGEMENT WORKSPACE -->
                <div id="node-roles" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Identity &amp; Access Manager</h2>
                            <p class="text-xs text-muted">Assign user access privileges, configure security permissions, and audit system credentials.</p>
                        </div>
                        <button onclick="triggerOperatorInviteSimulation()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                            <span class="material-symbols-outlined text-sm font-bold">person_add</span> Invite Platform Operator
                        </button>
                    </div>

                    <!-- Telemetry Cards -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Total Users</p>
                            <h3 class="text-3xl font-display font-bold text-accent" id="roles-total-users">24</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span> Active Sessions: 4
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Active Roles</p>
                            <h3 class="text-3xl font-display font-bold">5 Scopes</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Super, Editor, SEO, Support, Custom</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">SAML SSO Integration</p>
                            <h3 class="text-3xl font-display font-bold text-accent">Enabled</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Azure AD Directory sync active</div>
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
                                    <th class="p-3">Assigned Role</th>
                                    <th class="p-3">SSO Status</th>
                                    <th class="p-3">Two-Factor Auth</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3 text-right pr-5">Operations</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-xs font-mono" id="roles-directory-body">
                                <tr onclick="inspectElement('user', 'Bhupendra Watti', 'online', 'Super Admin (Level 1)', 'Full Scopes')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20" onclick="event.stopPropagation()"/></td>
                                    <td class="p-3 text-text font-medium flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-accent/20 border border-accent/40 flex items-center justify-center text-[10px] text-accent font-bold">BW</div>
                                        <span>Bhupendra Watti</span>
                                    </td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-accent/20 text-accent border border-accent/30 text-[9px]">Super Admin</span></td>
                                    <td class="p-3 text-muted">Local / Azure AD</td>
                                    <td class="p-3 text-accent font-bold">Enabled</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]">online</span></td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">Inspect</button>
                                    </td>
                                </tr>
                                <tr onclick="inspectElement('user', 'John Doe', 'offline', 'SEO Specialist (Level 3)', 'Topics Read/Write')" class="hover:bg-white/5 transition cursor-pointer">
                                    <td class="p-3 pl-5"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20" onclick="event.stopPropagation()"/></td>
                                    <td class="p-3 text-text font-medium flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-secondary/20 border border-secondary/40 flex items-center justify-center text-[10px] text-secondary font-bold">JD</div>
                                        <span>John Doe</span>
                                    </td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-secondary/20 text-secondary border border-secondary/30 text-[9px]">SEO Specialist</span></td>
                                    <td class="p-3 text-muted">Azure AD Only</td>
                                    <td class="p-3 text-accent font-bold">Enabled</td>
                                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-danger/20 text-danger border border-danger/30 text-[9px]">offline</span></td>
                                    <td class="p-3 text-right pr-5">
                                        <button class="text-secondary hover:underline">Inspect</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

