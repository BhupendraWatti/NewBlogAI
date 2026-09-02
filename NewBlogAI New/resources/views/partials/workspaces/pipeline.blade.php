                <!-- NEWSROOM PIPELINE WORKSPACE -->
                <div id="node-pipeline" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Newsroom Pipeline</h2>
                            <p class="text-xs text-muted">Step 1: Discover trending news → Step 2: Select a story → Step 3: Generate your article.</p>
                        </div>
                        <!-- Step indicator -->
                        <div class="flex items-center gap-2 text-[10px] font-mono">
                            <span id="step-badge-1" class="px-2.5 py-1 rounded-full bg-accent text-background font-bold">① Configure</span>
                            <span class="text-muted">→</span>
                            <span id="step-badge-2" class="px-2.5 py-1 rounded-full bg-surface border border-border text-muted font-bold">② Pick Story</span>
                            <span class="text-muted">→</span>
                            <span id="step-badge-3" class="px-2.5 py-1 rounded-full bg-surface border border-border text-muted font-bold">③ Article</span>
                        </div>
                    </div>

                    <!-- STEP 1: Configuration Form -->
                    <div id="newsroom-step-1" class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                        <!-- Left: Settings -->
                        <div class="space-y-4 lg:col-span-5">
                            <div class="glass-surface rounded-2xl p-5 space-y-4">
                                <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Generation Settings</h4>

                                <!-- Target Website -->
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest" for="gen-site">Target Website</label>
                                    <select id="gen-site" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2 px-3 focus:outline-none focus:border-accent">
                                        <option value="">— Select Target Site —</option>
                                    </select>
                                    <p class="text-[10px] text-muted">Configure websites in <button onclick="switchWorkspace('sites')" class="text-accent underline">Websites</button>.</p>
                                </div>

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

                                <!-- News Topic Dropdown -->
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-[10px] font-mono text-muted uppercase tracking-widest" for="gen-category">News Topic / Category</label>
                                        <button type="button" onclick="switchWorkspace('master-settings')" class="text-[10px] text-accent hover:underline flex items-center gap-0.5">
                                            <span class="material-symbols-outlined text-[11px]">tune</span> Manage
                                        </button>
                                    </div>
                                    <select id="gen-category" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2 px-3 focus:outline-none focus:border-accent">
                                        <option value="">— Select News Topic —</option>
                                    </select>
                                    <p class="text-[10px] text-muted">AI will find 9 real trending stories on this topic.</p>
                                </div>

                                <!-- Target Country Dropdown -->
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-[10px] font-mono text-muted uppercase tracking-widest" for="gen-country">Target Country</label>
                                        <button type="button" onclick="switchWorkspace('master-settings')" class="text-[10px] text-accent hover:underline flex items-center gap-0.5">
                                            <span class="material-symbols-outlined text-[11px]">tune</span> Manage
                                        </button>
                                    </div>
                                    <select id="gen-country" onchange="handlePipelineCountryChange()" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2 px-3 focus:outline-none focus:border-accent">
                                        <option value="">— Select Country (or Global) —</option>
                                    </select>
                                    <p class="text-[10px] text-muted">Filter news to this country/region.</p>
                                </div>

                                <!-- Target State Dropdown -->
                                <div class="space-y-1" id="gen-state-container">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-[10px] font-mono text-muted uppercase tracking-widest" for="gen-state">Target State / Region</label>
                                        <button type="button" onclick="switchWorkspace('master-settings')" class="text-[10px] text-accent hover:underline flex items-center gap-0.5">
                                            <span class="material-symbols-outlined text-[11px]">tune</span> Manage
                                        </button>
                                    </div>
                                    <select id="gen-state" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2 px-3 focus:outline-none focus:border-accent">
                                        <option value="">— All States / Regions —</option>
                                    </select>
                                    <p class="text-[10px] text-muted">Granular state targeting (filtered by selected country).</p>
                                </div>

                                <!-- Prompt Template -->
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">Prompt Template</label>
                                    <select id="gen-prompt" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2 px-3 focus:outline-none focus:border-accent">
                                        <option value="">— Select Template —</option>
                                    </select>
                                    <p class="text-[10px] text-muted">Create templates in <button onclick="switchWorkspace('prompts')" class="text-accent underline">Prompt Library</button>.</p>
                                </div>

                                <!-- Language -->
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest" for="gen-language">Output Language</label>
                                    <select id="gen-language" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2 px-3 focus:outline-none focus:border-accent">
                                        <option value="en">English</option>
                                        <option value="hi">Hindi</option>
                                    </select>
                                </div>

                                <!-- DISCOVER Button -->
                                <button id="discover-btn" onclick="triggerDiscover()" class="w-full bg-accent hover:bg-accent/80 text-background font-bold text-xs py-3 rounded-xl transition flex items-center justify-center gap-2 cyber-glow-emerald mt-2" disabled>
                                    <span class="material-symbols-outlined text-sm">travel_explore</span>
                                    Discover Top News Stories
                                </button>
                                <p class="text-[10px] text-muted text-center">Finds 9 real trending stories for you to choose from.</p>

                                <div id="discovery-telemetry" class="hidden rounded-xl border border-border bg-background/60 p-3 space-y-2" aria-live="polite">
                                    <div class="flex items-center justify-between gap-3">
                                        <span id="discovery-telemetry-stage" class="text-[10px] font-mono uppercase tracking-wider text-accent">Queued</span>
                                        <span id="discovery-telemetry-time" class="text-[10px] font-mono text-muted">0.0s / 300s</span>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2 text-center">
                                        <div class="rounded-lg bg-surface p-2">
                                            <div id="discovery-telemetry-provider" class="text-xs font-bold truncate">Auto</div>
                                            <div class="text-[9px] text-muted uppercase">Provider</div>
                                        </div>
                                        <div class="rounded-lg bg-surface p-2">
                                            <div id="discovery-telemetry-tokens" class="text-xs font-bold">0</div>
                                            <div class="text-[9px] text-muted uppercase">Reported tokens</div>
                                        </div>
                                        <div class="rounded-lg bg-surface p-2">
                                            <div id="discovery-telemetry-cost" class="text-xs font-bold">$0.000000</div>
                                            <div class="text-[9px] text-muted uppercase">Est. provider cost</div>
                                        </div>
                                    </div>
                                    <p id="discovery-telemetry-requests" class="text-[9px] text-muted font-mono">0 provider responses completed</p>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Info / Instructions -->
                        <div class="space-y-4 lg:col-span-7">
                            <div class="glass-surface rounded-2xl p-6 space-y-4 border border-accent/20">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-accent text-2xl">newspaper</span>
                                    <h3 class="font-display font-bold text-base">How the Newsroom Works</h3>
                                </div>
                                <div class="space-y-4 text-xs text-muted leading-relaxed">
                                    <div class="flex gap-3">
                                        <span class="w-6 h-6 rounded-full bg-accent/20 text-accent flex items-center justify-center text-[11px] font-bold shrink-0 mt-0.5">1</span>
                                        <div>
                                            <p class="text-text font-medium mb-0.5">Configure & Discover</p>
                                            <p>Fill in the form and click <strong class="text-accent">Discover Top News Stories</strong>. The AI searches for 9 real, current, trending news events on your topic in real-time.</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-3">
                                        <span class="w-6 h-6 rounded-full bg-accent/20 text-accent flex items-center justify-center text-[11px] font-bold shrink-0 mt-0.5">2</span>
                                        <div>
                                            <p class="text-text font-medium mb-0.5">Pick the Best Story</p>
                                            <p>Review the 9 candidate stories — each shows a headline, summary, trend score, and sources. Click <strong class="text-accent">Generate Article</strong> on the one you want.</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-3">
                                        <span class="w-6 h-6 rounded-full bg-accent/20 text-accent flex items-center justify-center text-[11px] font-bold shrink-0 mt-0.5">3</span>
                                        <div>
                                            <p class="text-text font-medium mb-0.5">Full Article Generated</p>
                                            <p>The AI writes a complete, structured news article anchored to that specific story using your prompt template, language, and tone settings.</p>
                                        </div>
                                    </div>
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
                                            <th class="p-3 pl-5">Category</th>
                                            <th class="p-3">Provider</th>
                                            <th class="p-3">Template</th>
                                            <th class="p-3">Status</th>
                                            <th class="p-3 text-right pr-5">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border text-xs font-mono" id="pipeline-runs-body">
                                    </tbody>
                                </table>
                                <div id="pipeline-runs-empty" class="flex flex-col items-center justify-center py-12 text-center">
                                    <span class="material-symbols-outlined text-3xl text-muted mb-2">history</span>
                                    <p class="text-xs text-muted">No generation history yet.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: Candidate News Stories Grid -->
                    <div id="newsroom-step-2" class="hidden space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-display font-bold text-lg">Choose a Story to Write</h3>
                                <p class="text-xs text-muted">AI found <span id="candidates-count" class="text-accent font-bold">9</span> trending stories. Click <strong>Generate Article</strong> on the one you want to publish.</p>
                            </div>
                            <button onclick="resetToStep1()" class="text-xs text-muted hover:text-text font-mono border border-border bg-surface hover:bg-white/5 px-4 py-2 rounded-xl transition flex items-center gap-2">
                                <span class="material-symbols-outlined text-sm">arrow_back</span>
                                New Discovery
                            </button>
                        </div>
                        <div id="candidates-grid" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <!-- Candidate cards injected here by JS -->
                        </div>
                    </div>

                    <!-- STEP 3: Generated Article Preview -->
                    <div id="newsroom-step-3" class="hidden space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-display font-bold text-lg">Generated Article</h3>
                                <p class="text-xs text-muted">Your article has been generated and saved. Review, copy, or send to publishing queue.</p>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="resetToStep2()" class="text-xs text-muted hover:text-text font-mono border border-border bg-surface hover:bg-white/5 px-4 py-2 rounded-xl transition flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                                    Back to Stories
                                </button>
                                <button onclick="resetToStep1()" class="text-xs text-muted hover:text-text font-mono border border-border bg-surface hover:bg-white/5 px-4 py-2 rounded-xl transition flex items-center gap-2">
                                    <span class="material-symbols-outlined text-sm">refresh</span>
                                    Start Over
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                            <div class="lg:col-span-8">
                                <div class="glass-surface rounded-2xl p-5 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Article Preview</h4>
                                        <div class="flex gap-2">
                                            <button id="btn-copy-gen" onclick="copyGeneratedArticle()" class="text-xs text-muted hover:text-text font-mono border border-border bg-surface hover:bg-white/5 px-3 py-1.5 rounded-xl transition">Copy</button>
                                            <button id="btn-queue-gen" onclick="queueGeneratedArticle()" class="text-xs bg-accent/10 text-accent hover:bg-accent/20 font-mono border border-accent/30 px-3 py-1.5 rounded-xl transition">Send to Queue</button>
                                        </div>
                                    </div>
                                    <div id="gen-output" class="min-h-[300px] bg-background border border-border rounded-xl p-4 text-xs text-text leading-relaxed overflow-y-auto max-h-[500px] custom-scrollbar select-text" style="user-select: text !important; -webkit-user-select: text !important; white-space: pre-wrap; font-family: inherit;">
                                        <!-- Article content injected here -->
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-4 lg:col-span-4">
                                <!-- Article Metadata -->
                                <div class="glass-surface rounded-2xl p-4 space-y-3">
                                    <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Article Info</h4>
                                    <div id="article-meta" class="space-y-2 text-xs">
                                        <!-- Injected by JS -->
                                    </div>
                                </div>
                                <!-- Source story -->
                                <div class="glass-surface rounded-2xl p-4 space-y-2">
                                    <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Source Story</h4>
                                    <div id="source-story-meta" class="text-xs text-muted leading-relaxed">
                                        <!-- Injected by JS -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
