                <!-- 7. CREATION WIZARD WORKSPACE -->
                <div id="node-creation" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl" id="wizard-title">Connection Wizard</h2>
                            <p class="text-xs text-muted">Initialize automated content nodes and WordPress plugins.</p>
                        </div>
                        <button onclick="cancelCreation()" class="text-muted hover:text-text text-xs font-mono">Cancel</button>
                    </div>

                    <!-- Progress Step Indicator -->
                    <div class="flex items-center gap-2 justify-center py-4 bg-surface/30 border border-border rounded-xl">
                        <div class="flex items-center gap-2 text-xs font-mono" id="wizard-steps-indicator">
                            <span class="text-accent" id="step-ind-1">● General</span>
                            <span class="text-muted">➔</span>
                            <span class="text-muted" id="step-ind-2">● Configuration</span>
                            <span class="text-muted">➔</span>
                            <span class="text-muted" id="step-ind-3">● Validation</span>
                            <span class="text-muted">➔</span>
                            <span class="text-muted" id="step-ind-4">● Preview</span>
                        </div>
                    </div>

                    <!-- Wizard Panels -->
                    <div class="glass-surface rounded-2xl p-6 max-w-xl mx-auto space-y-6">
                        <!-- Step 1 Pane -->
                        <div id="wizard-step-1" class="wizard-pane space-y-4">
                            <h3 class="text-sm font-medium font-mono text-secondary">Step 1: Domain Information</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-mono text-muted mb-1">WEBSITE DOMAIN</label>
                                    <input class="w-full bg-[#071018] border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="text" placeholder="https://example.com"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-mono text-muted mb-1">ADMINISTRATOR EMAIL</label>
                                    <input class="w-full bg-[#071018] border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="email" placeholder="admin@example.com"/>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2 Pane -->
                        <div id="wizard-step-2" class="wizard-pane space-y-4 hidden">
                            <h3 class="text-sm font-medium font-mono text-secondary">Step 2: Sync settings</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-mono text-muted mb-1">SYNC FREQUENCY</label>
                                    <select class="w-full bg-[#071018] border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent">
                                        <option>Hourly</option>
                                        <option>Daily</option>
                                        <option>Weekly</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3 Pane -->
                        <div id="wizard-step-3" class="wizard-pane space-y-4 hidden">
                            <h3 class="text-sm font-medium font-mono text-secondary">Step 3: API Key Verification</h3>
                            <div class="p-4 bg-accent/10 border border-accent/20 rounded-xl space-y-2">
                                <p class="text-xs text-accent">✔ REST endpoint online check passed.</p>
                                <p class="text-xs text-accent">✔ SSL validation check passed.</p>
                            </div>
                        </div>

                        <!-- Step 4 Pane -->
                        <div id="wizard-step-4" class="wizard-pane space-y-4 hidden">
                            <h3 class="text-sm font-medium font-mono text-secondary">Step 4: Confirm Setup</h3>
                            <div class="space-y-2 text-xs font-mono">
                                <p><span class="text-muted">Target Entity:</span> Connected Website Node</p>
                                <p><span class="text-muted">SSL Status:</span> Valid SSL Certificate</p>
                            </div>
                        </div>

                        <!-- Controls -->
                        <div class="flex justify-between pt-4 border-t border-border">
                            <button id="wizard-back-btn" onclick="wizardBack()" class="text-xs font-mono text-muted hover:text-text hidden">Back</button>
                            <div class="flex-1"></div>
                            <button id="wizard-next-btn" onclick="wizardNext()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition">Next</button>
                        </div>
                    </div>
                </div>
