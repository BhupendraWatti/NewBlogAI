                <div id="node-rules" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Automation Workflow Builder</h2>
                            <p class="text-xs text-muted">Model trigger rules, conditional branching, and automatic publishing sync steps.</p>
                        </div>
                        <button onclick="openWorkflowAddModal()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
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
                            
                            <div class="flex-1 overflow-y-auto custom-scrollbar space-y-2 pr-1" id="workflows-registry-list">
                                <!-- Populated dynamically from GET /api/v1/pipelines -->
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Add/Edit Pipeline Workflow Modal -->
                <div class="modal-overlay" id="workflow-modal">
                    <div class="modal-container">
                        <div class="flex justify-between items-center mb-6 border-b border-outline-variant pb-3">
                            <h3 class="text-lg font-semibold font-headline-md text-primary" id="workflow-modal-title">Register Automation Workflow</h3>
                            <button class="text-outline hover:text-on-surface text-xl" onclick="closeWorkflowModal()">&times;</button>
                        </div>
                        <form id="workflow-form" onsubmit="saveWorkflow(event)">
                            <input type="hidden" id="workflow-id">
                            
                            <div class="mb-4">
                                <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="workflow-site">Target WordPress Site</label>
                                <select id="workflow-site" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary" required>
                                    <option value="">— Select Connected Site —</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="workflow-topic">Content Topic Target</label>
                                <select id="workflow-topic" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary" required>
                                    <option value="">— Select Topic —</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="workflow-prompt">Prompt Template</label>
                                <select id="workflow-prompt" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary" required>
                                    <option value="">— Select Template —</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="workflow-provider">AI Provider Credentials</label>
                                <select id="workflow-provider" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary" required>
                                    <option value="">— Select Provider —</option>
                                </select>
                            </div>

                            <div class="flex justify-end gap-3 mt-6 border-t border-outline-variant pt-4">
                                <button type="button" class="border border-outline-variant text-outline hover:text-on-surface hover:bg-surface-container-high px-4 py-2.5 rounded-lg font-medium" onclick="closeWorkflowModal()">Cancel</button>
                                <button type="submit" class="bg-primary text-on-primary hover:bg-primary-fixed px-5 py-2.5 rounded-lg font-semibold transition-colors">Save Workflow</button>
                            </div>
                        </form>
                    </div>
                </div>

