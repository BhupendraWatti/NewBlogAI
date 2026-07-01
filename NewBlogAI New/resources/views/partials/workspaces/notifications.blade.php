                <!-- 15. NOTIFICATION HUB WORKSPACE -->
                <div id="node-notifications" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Operations Control Hub</h2>
                            <p class="text-xs text-muted">Dispatch platform event streams, view system alert loops, and manage quiet-hours configs.</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="triggerNotificationMuteSimulation()" class="bg-surface hover:bg-surface/80 border border-border text-text font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">notifications_off</span> Quiet Hours Active
                            </button>
                            <button onclick="triggerNotificationClearSimulation()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                                <span class="material-symbols-outlined text-sm font-bold">done_all</span> Mark All Resolved
                            </button>
                        </div>
                    </div>

                    <!-- Telemetry Cards -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Unresolved Alerts</p>
                            <h3 class="text-3xl font-display font-bold text-danger" id="notifications-count">3</h3>
                            <div class="mt-2 text-[10px] font-mono text-danger flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-danger animate-pulse"></span> Action Required
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Avg Resolution Time</p>
                            <h3 class="text-3xl font-display font-bold">14 mins</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent">99.2% SLA Compliance</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Slack Webhook status</p>
                            <h3 class="text-3xl font-display font-bold text-accent">Active</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">2 channel streams synced</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Daily Dispatched Events</p>
                            <h3 class="text-3xl font-display font-bold">1,842</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Across all channels</div>
                        </div>
                    </div>

                    <!-- Operations Timeline Feed -->
                    <div class="glass-surface rounded-2xl p-6 border border-border space-y-6">
                        <h4 class="text-xs font-mono uppercase tracking-widest text-muted">System Log Timeline (Active Stream)</h4>
                        
                        <div class="space-y-4 relative pl-6 border-l border-border font-mono text-xs">
                            <!-- Timeline Event 1 -->
                            <div class="relative" id="event-row-1">
                                <span class="absolute -left-[31px] top-0 w-4 h-4 rounded-full bg-success/20 border border-success/40 flex items-center justify-center text-[8px] text-success">✓</span>
                                <div class="p-3 bg-white/5 border border-border rounded-xl space-y-1">
                                    <div class="flex justify-between items-center">
                                        <span class="text-text font-bold">WordPress Publish Sync Complete</span>
                                        <span class="text-[10px] text-muted">Just now</span>
                                    </div>
                                    <p class="text-[10px] text-muted">Post "Quantum Computing Breakthrough" synced successfully to techcrunch.com via API.</p>
                                </div>
                            </div>

                            <!-- Timeline Event 2 -->
                            <div class="relative" id="event-row-2">
                                <span class="absolute -left-[31px] top-0 w-4 h-4 rounded-full bg-danger/20 border border-danger/40 flex items-center justify-center text-[8px] text-danger">!</span>
                                <div class="p-3 bg-white/5 border border-border rounded-xl space-y-1">
                                    <div class="flex justify-between items-center">
                                        <span class="text-text font-bold">LLM Pipeline Request Timeout</span>
                                        <span class="text-[10px] text-muted">12 mins ago</span>
                                    </div>
                                    <p class="text-[10px] text-muted">GPT-4o text generation failed on engadget.com. Error: REST Gateway connection timeout.</p>
                                </div>
                            </div>

                            <!-- Timeline Event 3 -->
                            <div class="relative" id="event-row-3">
                                <span class="absolute -left-[31px] top-0 w-4 h-4 rounded-full bg-warning/20 border border-warning/40 flex items-center justify-center text-[8px] text-warning">?</span>
                                <div class="p-3 bg-white/5 border border-border rounded-xl space-y-1">
                                    <div class="flex justify-between items-center">
                                        <span class="text-text font-bold">SSL Certificate Expiration Warning</span>
                                        <span class="text-[10px] text-muted">1 hour ago</span>
                                    </div>
                                    <p class="text-[10px] text-muted">Domain mashable.com SSL is active but expiring soon. Automated renewal scheduled.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

