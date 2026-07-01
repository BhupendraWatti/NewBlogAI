                <!-- CONTENT GENERATION PIPELINE WORKSPACE -->
                <div id="node-pipeline" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Content Generation Pipeline</h2>
                            <p class="text-xs text-muted">Generate AI articles by selecting a provider, prompt template, and topic. Manage providers and prompts in their respective modules.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-12 gap-6">
                        <!-- Left: Generation Form -->
                        <div class="col-span-5 space-y-4">
                            <div class="glass-surface rounded-2xl p-5 space-y-4">
                                <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Generation Settings</h4>

                                <!-- Provider -->
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">AI Provider</label>
                                    <select id="gen-provider" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2 px-3 focus:outline-none focus:border-accent">
                                        <option value="">— Select Provider —</option>
                                        <option value="gemini">Google Gemini</option>
                                        <option value="openai">OpenAI</option>
                                        <option value="claude">Anthropic Claude</option>
                                        <option value="groq">Groq</option>
                                        <option value="openrouter">OpenRouter</option>
                                        <option value="ollama">Ollama</option>
                                    </select>
                                    <p class="text-[10px] text-muted">Configure providers in <button onclick="switchWorkspace('providers')" class="text-accent underline">AI Providers</button>.</p>
                                </div>

                                <!-- Prompt Template -->
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">Prompt Template</label>
                                    <select id="gen-prompt" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2 px-3 focus:outline-none focus:border-accent">
                                        <option value="">— Select Template —</option>
                                        <option value="tech_summarizer">Standard Tech Summarizer</option>
                                        <option value="bullet_writer">News Bullet-point Writer</option>
                                        <option value="trend_analyst">Financial Trends Analyst</option>
                                    </select>
                                    <p class="text-[10px] text-muted">Create templates in <button onclick="switchWorkspace('prompts')" class="text-accent underline">Prompt Library</button>.</p>
                                </div>

                                <!-- Topic -->
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">Topic</label>
                                    <select id="gen-topic" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2 px-3 focus:outline-none focus:border-accent">
                                        <option value="">— Select Topic —</option>
                                        <option value="ai">Artificial Intelligence</option>
                                        <option value="saas">SaaS Automations</option>
                                        <option value="crypto">Cryptocurrency Markets</option>
                                        <option value="tech_trends">Tech Trends</option>
                                    </select>
                                    <p class="text-[10px] text-muted">Manage topics in <button onclick="switchWorkspace('topics')" class="text-accent underline">Topic Management</button>.</p>
                                </div>

                                <!-- Language -->
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">Output Language</label>
                                    <select id="gen-language" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2 px-3 focus:outline-none focus:border-accent">
                                        <option value="en">English</option>
                                        <option value="es">Spanish</option>
                                        <option value="fr">French</option>
                                        <option value="de">German</option>
                                        <option value="pt">Portuguese</option>
                                        <option value="ar">Arabic</option>
                                        <option value="zh">Chinese (Simplified)</option>
                                    </select>
                                </div>

                                <!-- Generate Button -->
                                <button id="generate-btn" onclick="triggerContentGeneration()" class="w-full bg-accent hover:bg-accent/80 text-background font-medium text-xs py-2.5 rounded-xl transition flex items-center justify-center gap-2 cyber-glow-emerald mt-2" disabled>
                                    <span class="material-symbols-outlined text-sm">auto_awesome</span>
                                    Generate Article
                                </button>
                                <p class="text-[10px] text-muted text-center">Select provider, template, and topic to enable generation.</p>
                            </div>
                        </div>

                        <!-- Right: Output Area + Recent Runs -->
                        <div class="col-span-7 space-y-4">
                            <!-- Output preview -->
                            <div id="gen-preview-container" class="glass-surface rounded-2xl p-5 space-y-3 hidden">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Output Preview</h4>
                                    <span id="gen-status-badge" class="hidden px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[9px] font-mono">generating...</span>
                                </div>
                                <div id="gen-output" class="min-h-[180px] bg-background border border-border rounded-xl p-4 text-xs font-mono text-text flex items-center justify-center">
                                    <div class="text-center space-y-2">
                                        <span class="material-symbols-outlined text-3xl text-muted/50">article</span>
                                        <p>Generated content will appear here.</p>
                                    </div>
                                </div>
                                <div class="flex gap-2 justify-end">
                                    <button class="text-xs text-muted hover:text-text font-mono border border-border bg-surface hover:bg-white/5 px-3 py-1.5 rounded-xl transition" id="btn-copy-gen" disabled>Copy</button>
                                    <button class="text-xs text-muted hover:text-text font-mono border border-border bg-surface hover:bg-white/5 px-3 py-1.5 rounded-xl transition" id="btn-queue-gen" disabled>Send to Queue</button>
                                </div>
                            </div>

                            <!-- Recent Runs -->
                            <div class="glass-surface rounded-2xl overflow-hidden border border-border">
                                <div class="p-4 border-b border-border">
                                    <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Recent Generation Runs</h4>
                                </div>
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase">
                                            <th class="p-3 pl-5">Topic</th>
                                            <th class="p-3">Provider</th>
                                            <th class="p-3">Template</th>
                                            <th class="p-3">Status</th>
                                            <th class="p-3 text-right pr-5">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border text-xs font-mono" id="pipeline-runs-body">
                                        <!-- TODO: Populate from GET /api/v1/pipeline/runs -->
                                    </tbody>
                                </table>
                                <!-- Empty State -->
                                <div class="flex flex-col items-center justify-center py-12 text-center">
                                    <span class="material-symbols-outlined text-3xl text-muted mb-2">history</span>
                                    <p class="text-xs text-muted">No generation history.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
