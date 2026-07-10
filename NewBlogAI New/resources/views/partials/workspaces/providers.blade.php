                <!-- AI MODELS & PROVIDERS WORKSPACE -->
                <div id="node-providers" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">AI Providers</h2>
                            <p class="text-xs text-muted">Configure API credentials and models for each provider. API keys are managed here as the single source of truth.</p>
                        </div>
                        <button onclick="openAddProviderForm()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                            <span class="material-symbols-outlined text-sm font-bold">add</span> Connect Provider
                        </button>
                    </div>

                    <!-- Add Provider Modal -->
                    <div id="add-provider-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="add-provider-modal-title">
                        <!-- Backdrop -->
                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeAddProviderForm()"></div>
                        <!-- Dialog -->
                        <div class="relative glass-surface rounded-2xl p-6 w-full max-w-md space-y-5 border border-border shadow-2xl">
                            <div class="flex items-center justify-between">
                                <h3 id="add-provider-modal-title" class="font-display font-bold text-lg">Connect Provider</h3>
                                <button onclick="closeAddProviderForm()" class="text-muted hover:text-text transition" aria-label="Close">
                                    <span class="material-symbols-outlined text-xl">close</span>
                                </button>
                            </div>

                            <!-- Provider -->
                            <div class="space-y-1.5">
                                <label class="block text-[10px] font-mono text-muted uppercase tracking-widest" for="modal-provider-select">Provider</label>
                                <select id="modal-provider-select" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2 px-3 focus:outline-none focus:border-accent">
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
                            <div class="space-y-1.5">
                                <label class="block text-[10px] font-mono text-muted uppercase tracking-widest" for="modal-api-key">API Key</label>
                                <div class="relative">
                                    <input id="modal-api-key" type="password" autocomplete="new-password" class="w-full bg-background border border-border rounded-xl py-2 px-3 pr-10 text-xs font-mono text-text focus:outline-none focus:border-accent" placeholder="Paste your API key here..."/>
                                    <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-text transition" onclick="toggleKeyVisibility(this)" aria-label="Toggle key visibility">
                                        <span class="material-symbols-outlined text-sm">visibility</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Model -->
                            <div class="space-y-1.5">
                                <label class="block text-[10px] font-mono text-muted uppercase tracking-widest" for="modal-model">Model</label>
                                <input id="modal-model" type="text" class="w-full bg-background border border-border rounded-xl py-2 px-3 text-xs font-mono text-text focus:outline-none focus:border-accent" placeholder="e.g. gemini-2.5-flash, gpt-4o, claude-3-5-sonnet..."/>
                            </div>

                            <!-- Error message -->
                            <p id="modal-provider-error" class="text-[10px] text-danger font-mono hidden">Please fill in all fields before saving.</p>

                            <!-- Actions -->
                            <div class="flex gap-3 pt-1">
                                <button type="button" onclick="closeAddProviderForm()" class="flex-1 bg-surface hover:bg-surface/80 border border-border text-text font-medium text-xs py-2 rounded-xl transition">Cancel</button>
                                <button type="button" id="modal-save-btn" onclick="saveNewProvider()" class="flex-1 bg-accent hover:bg-accent/80 text-background font-medium text-xs py-2 rounded-xl transition cyber-glow-emerald">Save Provider</button>
                            </div>
                        </div>
                    </div>

                    <!-- Provider Cards Grid -->
                    <div class="grid grid-cols-3 gap-4" id="providers-grid">

                        <!-- Google Gemini -->
                        <div class="glass-surface rounded-2xl p-5 space-y-4 border border-border hover:border-accent transition" id="provider-card-gemini">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-xl text-secondary bg-secondary/10 p-2 rounded-xl">neurology</span>
                                    <div>
                                        <p class="text-sm font-semibold">Google Gemini</p>
                                    </div>
                                </div>
                                <span class="provider-status px-2 py-0.5 rounded bg-muted/10 text-muted border border-border text-[9px] font-mono">not configured</span>
                            </div>
                            <div class="space-y-3">
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">API Key</label>
                                    <div class="relative">
                                        <input type="password" autocomplete="new-password" class="w-full bg-background border border-border rounded-xl p-2 pr-8 text-xs font-mono text-text focus:outline-none focus:border-accent" placeholder="AIza..."/>
                                        <button class="absolute right-2 top-1/2 -translate-y-1/2 text-muted hover:text-text" onclick="toggleKeyVisibility(this)">
                                            <span class="material-symbols-outlined text-sm">visibility</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">Default Model</label>
                                    <select data-role="model" class="w-full bg-background border border-border text-text text-xs rounded-xl py-1.5 px-2 focus:outline-none focus:border-accent">
                                        <option value="gemini-2.5-flash" selected>gemini-2.5-flash (Free Tier)</option>
                                        <option value="gemini-2.5-pro">gemini-2.5-pro (Paid Tier)</option>
                                        <option value="gemini-2.0-flash">gemini-2.0-flash</option>
                                        <option value="gemini-flash-latest">gemini-flash-latest</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-2 pt-1">
                                    <input type="checkbox" id="chk-default-gemini" class="rounded bg-background border-border text-accent focus:ring-accent/20 provider-default-chk" onchange="setDefaultProvider('gemini')"/>
                                    <label for="chk-default-gemini" class="text-[10px] font-mono text-muted uppercase cursor-pointer select-none">Set as Default Provider</label>
                                </div>
                            </div>
                            <!-- Rate Limits & Credits Panel -->
                            <div class="provider-credits-panel hidden pt-3 border-t border-border/50 space-y-1.5 text-[11px] font-mono text-muted">
                                <div class="flex justify-between">
                                    <span>Daily/Monthly Limit:</span>
                                    <span class="credits-total text-text font-bold">—</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Remaining Credit:</span>
                                    <span class="credits-remaining text-text font-bold">—</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Reset Time:</span>
                                    <span class="credits-reset text-text font-bold">—</span>
                                </div>
                                <div class="provider-error-msg hidden text-danger text-[10px] whitespace-pre-wrap mt-1"></div>
                            </div>
                            <div class="flex gap-2 pt-2 border-t border-border">
                                <button onclick="saveProviderKey(this, 'gemini')" class="flex-1 bg-accent hover:bg-accent/80 text-background font-medium text-xs py-1.5 rounded-xl transition" disabled>Save Settings</button>
                                <button type="button" onclick="refreshProviderCredits(this, 'gemini')" class="px-3 bg-muted/20 hover:bg-muted/30 text-text font-medium text-xs py-1.5 rounded-xl transition provider-refresh-btn hidden">Refresh Credits</button>
                            </div>
                        </div>

                        <!-- OpenAI -->
                        <div class="glass-surface rounded-2xl p-5 space-y-4 border border-border hover:border-accent transition" id="provider-card-openai">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-xl text-accent bg-accent/10 p-2 rounded-xl">smart_toy</span>
                                    <div>
                                        <p class="text-sm font-semibold">OpenAI</p>
                                    </div>
                                </div>
                                <span class="provider-status px-2 py-0.5 rounded bg-muted/10 text-muted border border-border text-[9px] font-mono">not configured</span>
                            </div>
                            <div class="space-y-3">
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">API Key</label>
                                    <div class="relative">
                                        <input type="password" autocomplete="new-password" class="w-full bg-background border border-border rounded-xl p-2 pr-8 text-xs font-mono text-text focus:outline-none focus:border-accent" placeholder="sk-proj-..."/>
                                        <button class="absolute right-2 top-1/2 -translate-y-1/2 text-muted hover:text-text" onclick="toggleKeyVisibility(this)">
                                            <span class="material-symbols-outlined text-sm">visibility</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">Default Model</label>
                                    <select data-role="model" class="w-full bg-background border border-border text-text text-xs rounded-xl py-1.5 px-2 focus:outline-none focus:border-accent">
                                        <option value="gpt-4o" selected>gpt-4o</option>
                                        <option value="gpt-4-turbo">gpt-4-turbo</option>
                                        <option value="gpt-3.5-turbo">gpt-3.5-turbo</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-2 pt-1">
                                    <input type="checkbox" id="chk-default-openai" class="rounded bg-background border-border text-accent focus:ring-accent/20 provider-default-chk" onchange="setDefaultProvider('openai')"/>
                                    <label for="chk-default-openai" class="text-[10px] font-mono text-muted uppercase cursor-pointer select-none">Set as Default Provider</label>
                                </div>
                            </div>
                            <!-- Rate Limits & Credits Panel -->
                            <div class="provider-credits-panel hidden pt-3 border-t border-border/50 space-y-1.5 text-[11px] font-mono text-muted">
                                <div class="flex justify-between">
                                    <span>Daily/Monthly Limit:</span>
                                    <span class="credits-total text-text font-bold">—</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Remaining Credit:</span>
                                    <span class="credits-remaining text-text font-bold">—</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Reset Time:</span>
                                    <span class="credits-reset text-text font-bold">—</span>
                                </div>
                                <div class="provider-error-msg hidden text-danger text-[10px] whitespace-pre-wrap mt-1"></div>
                            </div>
                            <div class="flex gap-2 pt-2 border-t border-border">
                                <button onclick="saveProviderKey(this, 'openai')" class="flex-1 bg-accent hover:bg-accent/80 text-background font-medium text-xs py-1.5 rounded-xl transition" disabled>Save Settings</button>
                                <button type="button" onclick="refreshProviderCredits(this, 'openai')" class="px-3 bg-muted/20 hover:bg-muted/30 text-text font-medium text-xs py-1.5 rounded-xl transition provider-refresh-btn hidden">Refresh Credits</button>
                            </div>
                        </div>

                        <!-- Anthropic Claude -->
                        <div class="glass-surface rounded-2xl p-5 space-y-4 border border-border hover:border-accent transition" id="provider-card-claude">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-xl text-warning bg-warning/10 p-2 rounded-xl">psychology</span>
                                    <div>
                                        <p class="text-sm font-semibold">Claude</p>
                                    </div>
                                </div>
                                <span class="provider-status px-2 py-0.5 rounded bg-muted/10 text-muted border border-border text-[9px] font-mono">not configured</span>
                            </div>
                            <div class="space-y-3">
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">API Key</label>
                                    <div class="relative">
                                        <input type="password" autocomplete="new-password" class="w-full bg-background border border-border rounded-xl p-2 pr-8 text-xs font-mono text-text focus:outline-none focus:border-accent" placeholder="sk-ant-..."/>
                                        <button class="absolute right-2 top-1/2 -translate-y-1/2 text-muted hover:text-text" onclick="toggleKeyVisibility(this)">
                                            <span class="material-symbols-outlined text-sm">visibility</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">Default Model</label>
                                    <select data-role="model" class="w-full bg-background border border-border text-text text-xs rounded-xl py-1.5 px-2 focus:outline-none focus:border-accent">
                                        <option value="claude-3-5-sonnet-20241022" selected>claude-3-5-sonnet-20241022</option>
                                        <option value="claude-3-haiku-20240307">claude-3-haiku-20240307</option>
                                        <option value="claude-3-opus-20240229">claude-3-opus-20240229</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-2 pt-1">
                                    <input type="checkbox" id="chk-default-claude" class="rounded bg-background border-border text-accent focus:ring-accent/20 provider-default-chk" onchange="setDefaultProvider('claude')"/>
                                    <label for="chk-default-claude" class="text-[10px] font-mono text-muted uppercase cursor-pointer select-none">Set as Default Provider</label>
                                </div>
                            </div>
                            <!-- Rate Limits & Credits Panel -->
                            <div class="provider-credits-panel hidden pt-3 border-t border-border/50 space-y-1.5 text-[11px] font-mono text-muted">
                                <div class="flex justify-between">
                                    <span>Daily/Monthly Limit:</span>
                                    <span class="credits-total text-text font-bold">—</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Remaining Credit:</span>
                                    <span class="credits-remaining text-text font-bold">—</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Reset Time:</span>
                                    <span class="credits-reset text-text font-bold">—</span>
                                </div>
                                <div class="provider-error-msg hidden text-danger text-[10px] whitespace-pre-wrap mt-1"></div>
                            </div>
                            <div class="flex gap-2 pt-2 border-t border-border">
                                <button onclick="saveProviderKey(this, 'claude')" class="flex-1 bg-accent hover:bg-accent/80 text-background font-medium text-xs py-1.5 rounded-xl transition" disabled>Save Settings</button>
                                <button type="button" onclick="refreshProviderCredits(this, 'claude')" class="px-3 bg-muted/20 hover:bg-muted/30 text-text font-medium text-xs py-1.5 rounded-xl transition provider-refresh-btn hidden">Refresh Credits</button>
                            </div>
                        </div>

                        <!-- Groq -->
                        <div class="glass-surface rounded-2xl p-5 space-y-4 border border-border hover:border-accent transition" id="provider-card-groq">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-xl text-danger bg-danger/10 p-2 rounded-xl">bolt</span>
                                    <div>
                                        <p class="text-sm font-semibold">Groq</p>
                                    </div>
                                </div>
                                <span class="provider-status px-2 py-0.5 rounded bg-muted/10 text-muted border border-border text-[9px] font-mono">not configured</span>
                            </div>
                            <div class="space-y-3">
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">API Key</label>
                                    <div class="relative">
                                        <input type="password" autocomplete="new-password" class="w-full bg-background border border-border rounded-xl p-2 pr-8 text-xs font-mono text-text focus:outline-none focus:border-accent" placeholder="gsk_..."/>
                                        <button class="absolute right-2 top-1/2 -translate-y-1/2 text-muted hover:text-text" onclick="toggleKeyVisibility(this)">
                                            <span class="material-symbols-outlined text-sm">visibility</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">Default Model</label>
                                    <select data-role="model" class="w-full bg-background border border-border text-text text-xs rounded-xl py-1.5 px-2 focus:outline-none focus:border-accent">
                                        <option value="llama-3.3-70b-versatile" selected>llama-3.3-70b-versatile (Production)</option>
                                        <option value="llama-3.1-8b-instant">llama-3.1-8b-instant (Fast)</option>
                                        <option value="qwen/qwen3.6-27b">qwen3.6-27b (Reasoning/Coding)</option>
                                        <option value="openai/gpt-oss-20b">gpt-oss-20b (Production)</option>
                                    </select>
                                </div>

                                <div class="flex items-center gap-2 pt-1">
                                    <input type="checkbox" id="chk-default-groq" class="rounded bg-background border-border text-accent focus:ring-accent/20 provider-default-chk" onchange="setDefaultProvider('groq')"/>
                                    <label for="chk-default-groq" class="text-[10px] font-mono text-muted uppercase cursor-pointer select-none">Set as Default Provider</label>
                                </div>
                            </div>
                            <!-- Rate Limits & Credits Panel -->
                            <div class="provider-credits-panel hidden pt-3 border-t border-border/50 space-y-1.5 text-[11px] font-mono text-muted">
                                <div class="flex justify-between">
                                    <span>Daily/Monthly Limit:</span>
                                    <span class="credits-total text-text font-bold">—</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Remaining Credit:</span>
                                    <span class="credits-remaining text-text font-bold">—</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Reset Time:</span>
                                    <span class="credits-reset text-text font-bold">—</span>
                                </div>
                                <div class="provider-error-msg hidden text-danger text-[10px] whitespace-pre-wrap mt-1"></div>
                            </div>
                            <div class="flex gap-2 pt-2 border-t border-border">
                                <button onclick="saveProviderKey(this, 'groq')" class="flex-1 bg-accent hover:bg-accent/80 text-background font-medium text-xs py-1.5 rounded-xl transition" disabled>Save Settings</button>
                                <button type="button" onclick="refreshProviderCredits(this, 'groq')" class="px-3 bg-muted/20 hover:bg-muted/30 text-text font-medium text-xs py-1.5 rounded-xl transition provider-refresh-btn hidden">Refresh Credits</button>
                            </div>
                        </div>

                        <!-- OpenRouter -->
                        <div class="glass-surface rounded-2xl p-5 space-y-4 border border-border hover:border-accent transition" id="provider-card-openrouter">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-xl text-secondary bg-secondary/10 p-2 rounded-xl">route</span>
                                    <div>
                                        <p class="text-sm font-semibold">OpenRouter</p>
                                    </div>
                                </div>
                                <span class="provider-status px-2 py-0.5 rounded bg-muted/10 text-muted border border-border text-[9px] font-mono">not configured</span>
                            </div>
                            <div class="space-y-3">
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">API Key</label>
                                    <div class="relative">
                                        <input type="password" autocomplete="new-password" class="w-full bg-background border border-border rounded-xl p-2 pr-8 text-xs font-mono text-text focus:outline-none focus:border-accent" placeholder="sk-or-..."/>
                                        <button class="absolute right-2 top-1/2 -translate-y-1/2 text-muted hover:text-text" onclick="toggleKeyVisibility(this)">
                                            <span class="material-symbols-outlined text-sm">visibility</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">Default Model</label>
                                    <select data-role="model" class="w-full bg-background border border-border text-text text-xs rounded-xl py-1.5 px-2 focus:outline-none focus:border-accent">
                                        <option value="openai/gpt-4o" selected>openai/gpt-4o</option>
                                        <option value="anthropic/claude-3.5-sonnet">anthropic/claude-3.5-sonnet</option>
                                        <option value="google/gemini-2.5-flash">google/gemini-2.5-flash</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-2 pt-1">
                                    <input type="checkbox" id="chk-default-openrouter" class="rounded bg-background border-border text-accent focus:ring-accent/20 provider-default-chk" onchange="setDefaultProvider('openrouter')"/>
                                    <label for="chk-default-openrouter" class="text-[10px] font-mono text-muted uppercase cursor-pointer select-none">Set as Default Provider</label>
                                </div>
                            </div>
                            <!-- Rate Limits & Credits Panel -->
                            <div class="provider-credits-panel hidden pt-3 border-t border-border/50 space-y-1.5 text-[11px] font-mono text-muted">
                                <div class="flex justify-between">
                                    <span>Daily/Monthly Limit:</span>
                                    <span class="credits-total text-text font-bold">—</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Remaining Credit:</span>
                                    <span class="credits-remaining text-text font-bold">—</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Reset Time:</span>
                                    <span class="credits-reset text-text font-bold">—</span>
                                </div>
                                <div class="provider-error-msg hidden text-danger text-[10px] whitespace-pre-wrap mt-1"></div>
                            </div>
                            <div class="flex gap-2 pt-2 border-t border-border">
                                <button onclick="saveProviderKey(this, 'openrouter')" class="flex-1 bg-accent hover:bg-accent/80 text-background font-medium text-xs py-1.5 rounded-xl transition" disabled>Save Settings</button>
                                <button type="button" onclick="refreshProviderCredits(this, 'openrouter')" class="px-3 bg-muted/20 hover:bg-muted/30 text-text font-medium text-xs py-1.5 rounded-xl transition provider-refresh-btn hidden">Refresh Credits</button>
                            </div>
                        </div>

                        <!-- Ollama -->
                        <div class="glass-surface rounded-2xl p-5 space-y-4 border border-border hover:border-accent transition" id="provider-card-ollama">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-xl text-muted bg-white/5 p-2 rounded-xl">dns</span>
                                    <div>
                                        <p class="text-sm font-semibold">Ollama</p>
                                    </div>
                                </div>
                                <span class="provider-status px-2 py-0.5 rounded bg-muted/10 text-muted border border-border text-[9px] font-mono">not configured</span>
                            </div>
                            <div class="space-y-3">
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">Host URL</label>
                                    <input type="text" class="w-full bg-background border border-border rounded-xl p-2 text-xs font-mono text-text focus:outline-none focus:border-accent" placeholder="http://localhost:11434"/>
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">Default Model</label>
                                    <input type="text" data-role="model" class="w-full bg-background border border-border rounded-xl p-2 text-xs font-mono text-text focus:outline-none focus:border-accent" placeholder="llama3"/>
                                </div>
                                <div class="flex items-center gap-2 pt-1">
                                    <input type="checkbox" id="chk-default-ollama" class="rounded bg-background border-border text-accent focus:ring-accent/20 provider-default-chk" onchange="setDefaultProvider('ollama')"/>
                                    <label for="chk-default-ollama" class="text-[10px] font-mono text-muted uppercase cursor-pointer select-none">Set as Default Provider</label>
                                </div>
                            </div>
                            <!-- Rate Limits & Credits Panel -->
                            <div class="provider-credits-panel hidden pt-3 border-t border-border/50 space-y-1.5 text-[11px] font-mono text-muted">
                                <div class="flex justify-between">
                                    <span>Type:</span>
                                    <span class="text-text font-bold">Local / Offline</span>
                                </div>
                            </div>
                            <div class="flex gap-2 pt-2 border-t border-border">
                                <button onclick="saveProviderKey(this, 'ollama')" class="flex-1 bg-accent hover:bg-accent/80 text-background font-medium text-xs py-1.5 rounded-xl transition" disabled>Save Settings</button>
                            </div>
                        </div>

                    </div>
                </div>
