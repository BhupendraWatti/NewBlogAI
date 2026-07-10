                <!-- 18. SYSTEM SETTINGS WORKSPACE -->
                <div id="node-settings" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Global System Settings</h2>
                            <p class="text-xs text-muted">Configure environment variables, adjust AI model routing fallbacks, and manage backup profiles.</p>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="triggerSystemHealthTestSimulation()" class="bg-surface hover:bg-surface/80 border border-border text-text font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">health_and_safety</span> Run Health Test
                            </button>
                            <button onclick="triggerSystemSaveSimulation(this)" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                                <span class="material-symbols-outlined text-sm font-bold">save</span> Save System Config
                            </button>
                        </div>
                    </div>

                    <!-- Tabs Sub navigation -->
                    <div class="flex items-center gap-1 p-1 bg-surface border border-border rounded-xl w-fit">
                        <button onclick="switchSettingsTab('general')" id="settings-tab-general" class="px-4 py-1.5 rounded-lg text-xs font-mono bg-white/5 text-accent font-semibold transition">General</button>
                        <button onclick="switchSettingsTab('ai')" id="settings-tab-ai" class="px-4 py-1.5 rounded-lg text-xs font-mono text-muted hover:text-text transition">AI Defaults</button>
                        <button onclick="switchSettingsTab('wp')" id="settings-tab-wp" class="px-4 py-1.5 rounded-lg text-xs font-mono text-muted hover:text-text transition">WordPress Fleet</button>
                    </div>

                    <!-- General Settings Pane -->
                    <div id="settings-pane-general" class="settings-tab-view space-y-4">
                        <div class="glass-surface rounded-2xl p-5 space-y-4 max-w-2xl bg-surface/30">
                            <h3 class="text-xs font-mono uppercase tracking-widest text-muted">Branding &amp; Domain Configurations</h3>
                            <div class="grid grid-cols-2 gap-4 font-mono text-xs">
                                <div class="space-y-1">
                                    <label class="text-[10px] text-muted uppercase">Platform App Title</label>
                                    <input class="w-full bg-background border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="text" value="NewsBlogify AI Platform"/>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] text-muted uppercase">System Admin Email</label>
                                    <input class="w-full bg-background border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="email" value="admin@newsblogify.ai"/>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Defaults Settings Pane -->
                    <div id="settings-pane-ai" class="settings-tab-view space-y-4 hidden">
                        <div class="glass-surface rounded-2xl p-5 space-y-4 max-w-2xl bg-surface/30">
                            <h3 class="text-xs font-mono uppercase tracking-widest text-muted">LLM Fallback Parameters</h3>
                            <div class="space-y-3 font-mono text-xs">
                                <div class="flex items-center justify-between p-3 bg-white/5 border border-border rounded-xl">
                                    <div>
                                        <p class="font-bold">Enable Provider Fallback Routing</p>
                                        <p class="text-[10px] text-muted">Automatically routes to Google Gemini if OpenAI/Anthropic fails.</p>
                                    </div>
                                    <input type="checkbox" checked class="rounded bg-background border-border text-accent focus:ring-accent/20"/>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <label class="text-[10px] text-muted uppercase">Primary Model Routing</label>
                                        <select class="w-full bg-[#071018] border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent">
                                            <option>OpenAI GPT-4o</option>
                                            <option>Anthropic Claude 3.5 Sonnet</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[10px] text-muted uppercase">Maximum token threshold</label>
                                        <input class="w-full bg-background border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="number" value="4096"/>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="glass-surface rounded-2xl p-5 space-y-4 max-w-2xl bg-surface/30">
                            <h3 class="text-xs font-mono uppercase tracking-widest text-accent">Image Generator Settings</h3>
                            <div class="space-y-3 font-mono text-xs">
                                <div class="flex items-center justify-between p-3 bg-white/5 border border-border rounded-xl mb-2">
                                    <div>
                                        <p class="font-bold">Enable Image Generation</p>
                                        <p class="text-[10px] text-muted">Generate featured and inline placeholder images for articles. If disabled, only text will be generated.</p>
                                    </div>
                                    <input type="checkbox" id="setting-enable-img-gen" class="rounded bg-background border-border text-accent focus:ring-accent/20"/>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <label class="text-[10px] text-muted uppercase">Image Generator Driver</label>
                                        <select id="setting-img-driver" onchange="toggleImageDriverKeyField()" class="w-full bg-[#071018] border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent">
                                            <option value="pollinations">Pollinations (Free / No Key)</option>
                                            <option value="unsplash">Unsplash API</option>
                                            <option value="dalle">OpenAI DALL-E</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1" id="img-driver-key-container">
                                        <label class="text-[10px] text-muted uppercase" id="img-driver-key-label">API Key / Access Key</label>
                                        <input id="setting-img-key" class="w-full bg-background border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="password" placeholder="Key value..."/>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- WordPress Defaults Settings Pane -->
                    <div id="settings-pane-wp" class="settings-tab-view space-y-4 hidden">
                        <div class="glass-surface rounded-2xl p-5 space-y-4 max-w-2xl bg-surface/30">
                            <h3 class="text-xs font-mono uppercase tracking-widest text-muted">WordPress Connection Defaults</h3>
                            <div class="space-y-3 font-mono text-xs">
                                <div class="flex items-center justify-between p-3 bg-white/5 border border-border rounded-xl">
                                    <div>
                                        <p class="font-bold">Auto-Publish to WordPress</p>
                                        <p class="text-[10px] text-muted">Bypasses human review and drafts status directly to publish.</p>
                                    </div>
                                    <input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20"/>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <label class="text-[10px] text-muted uppercase">Default Post Format</label>
                                        <select class="w-full bg-[#071018] border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent">
                                            <option>Standard Format</option>
                                            <option>Summary Bulletin</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[10px] text-muted uppercase">API Timeout latency limit</label>
                                        <input class="w-full bg-background border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="number" value="30"/>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

