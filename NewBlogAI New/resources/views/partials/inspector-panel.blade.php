            <!-- DYNAMIC CONTEXT INSPECTOR PANEL -->
            <aside id="inspector-panel" class="w-80 bg-sidebar border-l border-border flex flex-col justify-between py-6 px-4 shrink-0 transition-all duration-300 transform translate-x-full hidden">
                <div class="space-y-6">
                    <!-- Title -->
                    <div class="flex justify-between items-center">
                        <h4 class="text-xs font-mono uppercase tracking-widest text-muted" id="inspector-type">Customer Entity</h4>
                        <button onclick="closeInspector()" class="p-1 hover:bg-white/5 rounded-lg border border-transparent hover:border-border text-muted hover:text-text">
                            <span class="material-symbols-outlined text-sm font-bold">close</span>
                        </button>
                    </div>

                    <!-- Inspector Body -->
                    <div class="space-y-4">
                        <h3 class="text-xl font-display font-bold" id="inspector-title">Acme Corp</h3>
                        
                        <div class="space-y-3 font-mono text-xs">
                            <div>
                                <span class="text-muted">STATUS:</span>
                                <span id="inspector-status" class="ml-2 px-2 py-0.5 rounded text-[10px] bg-warning/20 text-warning">trial</span>
                            </div>
                            <div>
                                <span class="text-muted">PRIORITY:</span>
                                <span id="inspector-priority" class="ml-2 text-text font-medium">High</span>
                            </div>
                            <div>
                                <span class="text-muted">OWNER:</span>
                                <span id="inspector-owner" class="ml-2 text-text">system_admin</span>
                            </div>
                            <div>
                                <span class="text-muted">LAST UPDATE:</span>
                                <span class="ml-2 text-muted">2 mins ago</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div class="space-y-2">
                        <h5 class="text-[10px] font-mono uppercase tracking-widest text-muted">Metadata Tags</h5>
                        <div class="flex flex-wrap gap-1 text-[10px] font-mono">
                            <span class="bg-white/5 border border-border px-2 py-0.5 rounded text-muted">US-Region</span>
                            <span class="bg-white/5 border border-border px-2 py-0.5 rounded text-muted">SaaS</span>
                        </div>
                    </div>
                </div>

                <!-- Inspector Actions -->
                <div class="pt-4 border-t border-border space-y-2">
                    <button class="w-full bg-accent text-background font-medium text-xs py-2 rounded-xl transition hover:bg-accent/80">
                        Trigger Operations Sync
                    </button>
                    <button class="w-full bg-transparent hover:bg-white/5 text-danger border border-danger/30 font-medium text-xs py-2 rounded-xl transition">
                        Force Termination
                    </button>
                </div>
            </aside>
