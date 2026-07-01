                <div id="node-rules" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Automation Workflow Builder</h2>
                            <p class="text-xs text-muted">Model trigger rules, conditional branching, and automatic publishing sync steps.</p>
                        </div>
                        <button onclick="launchCreationWizard('workflow')" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                            <span class="material-symbols-outlined text-sm font-bold">add</span> Register Workflow
                        </button>
                    </div>

                    <!-- Split Canvas Board layout -->
                    <div class="grid grid-cols-12 gap-6 h-[calc(100vh-220px)] overflow-hidden">
                        
                        <!-- Left Board: Drag & Drop Node Canvas Simulator -->
                        <div class="col-span-8 glass-surface rounded-2xl relative overflow-hidden bg-surface/30 p-5 flex flex-col justify-between" style="background-image: radial-gradient(rgba(255,255,255,0.05) 1px, transparent 1px); background-size: 16px 16px;">
                            <span class="text-[9px] font-mono text-muted uppercase absolute top-4 left-4">Visual Logic Canvas</span>
                            
                            <!-- Connected Nodes Stream -->
                            <div class="flex flex-col items-center justify-center space-y-4 my-auto">
                                <!-- Trigger Node -->
                                <div class="glass-surface rounded-xl p-3 border border-accent w-48 text-center bg-background/80 hover:scale-105 transition cursor-pointer">
                                    <p class="text-[9px] font-mono text-accent uppercase tracking-widest">Trigger</p>
                                    <p class="text-xs font-semibold text-text mt-0.5">Cron Loop @6h</p>
                                </div>
                                <span class="material-symbols-outlined text-muted text-sm animate-bounce">arrow_downward</span>

                                <!-- Stage 1 Node -->
                                <div class="glass-surface rounded-xl p-3 border border-border w-48 text-center bg-background/80 hover:scale-105 transition cursor-pointer">
                                    <p class="text-[9px] font-mono text-secondary uppercase tracking-widest">Stage 1</p>
                                    <p class="text-xs font-semibold text-text mt-0.5">Fetch Topic Nodes</p>
                                </div>
                                <span class="material-symbols-outlined text-muted text-sm animate-bounce">arrow_downward</span>

                                <!-- Stage 2 Node -->
                                <div class="glass-surface rounded-xl p-3 border border-border w-48 text-center bg-background/80 hover:scale-105 transition cursor-pointer">
                                    <p class="text-[9px] font-mono text-secondary uppercase tracking-widest">Stage 2</p>
                                    <p class="text-xs font-semibold text-text mt-0.5">GPT-4o Text Generator</p>
                                </div>
                                <span class="material-symbols-outlined text-muted text-sm animate-bounce">arrow_downward</span>

                                <!-- Stage 3 Node -->
                                <div class="glass-surface rounded-xl p-3 border border-border w-48 text-center bg-background/80 hover:scale-105 transition cursor-pointer">
                                    <p class="text-[9px] font-mono text-secondary uppercase tracking-widest">Stage 3</p>
                                    <p class="text-xs font-semibold text-text mt-0.5">WP Fleet Publishing</p>
                                </div>
                            </div>
                        </div>

                        <!-- Right Panel: Reusable Workflows Registry -->
                        <div class="col-span-4 glass-surface rounded-2xl p-4 flex flex-col space-y-4 h-full overflow-hidden bg-surface/30">
                            <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Reusable Configurations</h4>
                            
                            <div class="flex-1 overflow-y-auto custom-scrollbar space-y-2 pr-1">
                                <div onclick="inspectElement('workflow', 'Auto-Sync Blog Fleet', 'active', 'Success: 98.2%', 'Cron @6h')" class="p-3 bg-white/5 border border-accent rounded-xl cursor-pointer hover:border-accent transition group">
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-xs font-medium text-text">Auto-Sync Blog Fleet</p>
                                        <span class="text-[9px] font-mono bg-success/20 text-success border border-success/30 px-1.5 py-0.5 rounded">active</span>
                                    </div>
                                    <p class="text-[10px] text-muted line-clamp-1 font-mono">Triggers: Cron @6h | Success: 98.2%</p>
                                </div>

                                <div onclick="inspectElement('workflow', 'Enterprise Content Loop', 'active', 'Success: 100%', 'Cron @daily')" class="p-3 bg-transparent border border-border rounded-xl cursor-pointer hover:bg-white/5 transition group">
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-xs font-medium text-text">Enterprise Content Loop</p>
                                        <span class="text-[9px] font-mono bg-success/20 text-success border border-success/30 px-1.5 py-0.5 rounded">active</span>
                                    </div>
                                    <p class="text-[10px] text-muted line-clamp-1 font-mono">Triggers: New Topic Node | Success: 100%</p>
                                </div>

                                <div onclick="inspectElement('workflow', 'Manual Review Flow', 'paused', 'Success: 94%', 'Trigger API')" class="p-3 bg-transparent border border-border rounded-xl cursor-pointer hover:bg-white/5 transition group">
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-xs font-medium text-text">Manual Review Flow</p>
                                        <span class="text-[9px] font-mono bg-warning/20 text-warning border border-warning/30 px-1.5 py-0.5 rounded">paused</span>
                                    </div>
                                    <p class="text-[10px] text-muted line-clamp-1 font-mono">Triggers: Manual Hook | Success: 94%</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

