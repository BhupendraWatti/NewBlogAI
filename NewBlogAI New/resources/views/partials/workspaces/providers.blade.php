                <!-- AI MODELS & PROVIDERS WORKSPACE -->
                <div id="node-providers" class="workspace-pane space-y-6 hidden">
                    <style>
                        .perspective-1000 {
                            perspective: 1000px;
                        }
                        .transform-style-3d {
                            transform-style: preserve-3d;
                            -webkit-transform-style: preserve-3d;
                        }
                        .backface-hidden {
                            backface-visibility: hidden;
                            -webkit-backface-visibility: hidden;
                        }
                        .flip-card-inner {
                            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
                        }
                        .flip-card.flipped .flip-card-inner {
                            transform: rotateY(180deg);
                        }
                    </style>
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">AI Providers</h2>
                            <p class="text-xs text-muted">Configure API credentials and models for each provider. API keys are managed here as the single source of truth.</p>
                        </div>
                    </div>

                    <!-- Provider Cards Grid -->
                    <div class="grid grid-cols-3 gap-4" id="providers-grid">
                        <!-- Creator Card (3D Flip Card) -->
                        <div class="flip-card perspective-1000 h-full min-h-[380px] z-10" id="creator-card">
                            <div class="flip-card-inner relative w-full h-full transform-style-3d">
                                
                                <!-- Front Face: The Call to Action -->
                                <div class="flip-card-front absolute inset-0 w-full h-full backface-hidden glass-surface rounded-2xl p-5 border border-dashed border-border/80 hover:border-accent/50 transition duration-300 flex flex-col items-center justify-center space-y-4 cursor-pointer group" onclick="flipCreatorCard(true)">
                                    <div class="w-12 h-12 rounded-full bg-accent/10 flex items-center justify-center border border-accent/20 group-hover:scale-110 transition duration-300">
                                        <span class="material-symbols-outlined text-accent text-2xl">add</span>
                                    </div>
                                    <div class="text-center">
                                        <h4 class="text-sm font-semibold text-text">Connect New Provider</h4>
                                        <p class="text-[10px] text-muted mt-1 max-w-[180px]">Add multiple credentials, tiers, and priorities to the orchestrator pool.</p>
                                    </div>
                                </div>

                                <!-- Back Face: The Form -->
                                <div class="flip-card-back absolute inset-0 w-full h-full backface-hidden glass-surface rounded-2xl p-5 border border-border dark:border-accent/30 bg-white dark:bg-[#0c1524]/95 shadow-2xl flex flex-col justify-between" style="transform: rotateY(180deg);">
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <h4 class="text-xs font-bold uppercase tracking-widest text-emerald-600 dark:text-accent flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-sm">settings</span>
                                                New Credential
                                            </h4>
                                            <button onclick="flipCreatorCard(false)" class="text-slate-400 hover:text-slate-600 dark:text-muted dark:hover:text-text transition">
                                                <span class="material-symbols-outlined text-sm">close</span>
                                            </button>
                                        </div>

                                        <!-- Select Provider -->
                                        <div class="space-y-1">
                                            <label class="block text-[9px] font-mono text-slate-500 dark:text-muted uppercase tracking-widest" for="modal-provider-select">Provider</label>
                                            <select id="modal-provider-select" class="w-full bg-slate-50 dark:bg-background border border-border text-slate-800 dark:text-text text-xs rounded-xl py-1.5 px-3 focus:outline-none focus:border-accent transition-colors">
                                                <option value="">— Select a provider —</option>
                                                <option value="gemini">Google Gemini</option>
                                                <option value="openai">OpenAI</option>
                                                <option value="claude">Claude (Anthropic)</option>
                                                <option value="groq">Groq</option>
                                                <option value="openrouter">OpenRouter</option>
                                                <option value="ollama">Ollama</option>
                                                <option value="custom">Custom</option>
                                            </select>
                                        </div>

                                        <!-- API Key -->
                                        <div class="space-y-1">
                                            <label class="block text-[9px] font-mono text-slate-500 dark:text-muted uppercase tracking-widest" for="modal-api-key">API Key</label>
                                            <input id="modal-api-key" type="password" class="w-full bg-slate-50 dark:bg-background border border-border rounded-xl py-1.5 px-3 text-xs font-mono text-slate-800 dark:text-text focus:outline-none focus:border-accent transition-colors" placeholder="Enter API key..."/>
                                        </div>

                                        <!-- Model -->
                                        <div class="space-y-1">
                                            <label class="block text-[9px] font-mono text-slate-500 dark:text-muted uppercase tracking-widest" for="modal-model">Model</label>
                                            <input id="modal-model" type="text" class="w-full bg-slate-50 dark:bg-background border border-border rounded-xl py-1.5 px-3 text-xs font-mono text-slate-800 dark:text-text focus:outline-none focus:border-accent transition-colors" placeholder="e.g. gemini-2.5-flash"/>
                                        </div>

                                        <!-- Tier & Priority -->
                                        <div class="grid grid-cols-2 gap-2">
                                            <div class="space-y-1">
                                                <label class="block text-[9px] font-mono text-slate-500 dark:text-muted uppercase tracking-widest" for="modal-tier">Tier</label>
                                                <select id="modal-tier" class="w-full bg-slate-50 dark:bg-background border border-border text-slate-800 dark:text-text text-xs rounded-xl py-1.5 px-2 focus:outline-none focus:border-accent transition-colors">
                                                    <option value="free" selected>Free</option>
                                                    <option value="paid">Paid</option>
                                                    <option value="local">Local</option>
                                                </select>
                                            </div>
                                            <div class="space-y-1">
                                                <label class="block text-[9px] font-mono text-slate-500 dark:text-muted uppercase tracking-widest" for="modal-priority">Priority</label>
                                                <input id="modal-priority" type="number" min="0" value="0" class="w-full bg-slate-50 dark:bg-background border border-border rounded-xl py-1.5 px-2 text-xs font-mono text-slate-800 dark:text-text focus:outline-none focus:border-accent transition-colors"/>
                                            </div>
                                        </div>
                                        <p id="modal-provider-error" class="text-[9px] text-danger font-mono hidden">Please fill in all fields.</p>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex gap-2 pt-2 border-t border-border/50">
                                        <button type="button" onclick="flipCreatorCard(false)" class="flex-1 bg-slate-100 hover:bg-slate-200 dark:bg-surface dark:hover:bg-surface/80 border border-slate-200 dark:border-border text-slate-700 dark:text-text font-medium text-xs py-1.5 rounded-xl transition">Cancel</button>
                                        <button type="button" id="modal-save-btn" onclick="saveNewProvider()" class="flex-1 bg-emerald-600 hover:bg-emerald-500 dark:bg-accent dark:hover:bg-accent/80 text-white dark:text-background font-medium text-xs py-1.5 rounded-xl transition cyber-glow-emerald">Save</button>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Dynamic provider cards will be injected here by fetchAIProviders() -->
                    </div>
                </div>
