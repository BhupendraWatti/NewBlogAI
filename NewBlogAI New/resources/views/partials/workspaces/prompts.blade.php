                 <div id="node-prompts" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Prompt Library</h2>
                            <p class="text-xs text-muted">Create and manage reusable prompts. Content automation pipelines select prompts directly from this library.</p>
                        </div>
                        <button onclick="openNewPromptTemplate()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                            <span class="material-symbols-outlined text-sm font-bold">add</span> Create Template
                        </button>
                    </div>

                    <!-- Split Workspace Panel -->
                    <div class="grid grid-cols-12 gap-6 h-[calc(100vh-220px)] overflow-hidden">
                        
                        <!-- Left Panel: Template List & Search -->
                        <div class="col-span-4 glass-surface rounded-2xl p-4 flex flex-col space-y-4 h-full overflow-hidden bg-surface/30">
                            <!-- Search & Category Filter -->
                            <div class="space-y-2 shrink-0">
                                <div class="relative">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted text-sm">search</span>
                                    <input class="w-full bg-background border border-border rounded-xl py-1.5 pl-9 pr-4 text-xs font-mono text-text placeholder-muted focus:outline-none focus:border-accent focus:ring-0" placeholder="Filter library..." type="text"/>
                                </div>
                            </div>

                            <!-- Templates Stream -->
                            <div class="flex-1 overflow-y-auto custom-scrollbar space-y-2 pr-1" id="prompt-templates-list">
                                <div onclick="selectPromptTemplate('promt_001', 'Tech Summarizer', 'Summarizer', 'v1.2', 'active')" class="p-3 bg-white/5 border border-accent rounded-xl cursor-pointer hover:border-accent transition group relative prompt-list-item" id="prompt-item-promt_001">
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-xs font-semibold text-text">Tech Summarizer</p>
                                        <span class="text-[9px] font-mono bg-accent/20 text-accent border border-accent/30 px-1.5 py-0.5 rounded">v1.2</span>
                                    </div>
                                    <div class="flex justify-between items-center text-[10px] font-mono text-muted">
                                        <span>Category: Summarizer</span>
                                        <span class="text-success">active</span>
                                    </div>
                                </div>

                                <div onclick="selectPromptTemplate('promt_002', 'News Bullet Writer', 'Bulletins', 'v2.0', 'active')" class="p-3 bg-transparent border border-border rounded-xl cursor-pointer hover:bg-white/5 transition group relative prompt-list-item" id="prompt-item-promt_002">
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-xs font-semibold text-text">News Bullet Writer</p>
                                        <span class="text-[9px] font-mono bg-white/10 text-muted border border-border px-1.5 py-0.5 rounded">v2.0</span>
                                    </div>
                                    <div class="flex justify-between items-center text-[10px] font-mono text-muted">
                                        <span>Category: Bulletins</span>
                                        <span class="text-success">active</span>
                                    </div>
                                </div>

                                <div onclick="selectPromptTemplate('promt_003', 'Financial Trends Analyst', 'Analysis', 'v1.0', 'draft')" class="p-3 bg-transparent border border-border rounded-xl cursor-pointer hover:bg-white/5 transition group relative prompt-list-item" id="prompt-item-promt_003">
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-xs font-semibold text-text">Financial Trends Analyst</p>
                                        <span class="text-[9px] font-mono bg-white/10 text-muted border border-border px-1.5 py-0.5 rounded">v1.0</span>
                                    </div>
                                    <div class="flex justify-between items-center text-[10px] font-mono text-muted">
                                        <span>Category: Analysis</span>
                                        <span class="text-warning">draft</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Panel: Editor & Testing -->
                        <div class="col-span-8 glass-surface rounded-2xl flex flex-col h-full overflow-hidden bg-surface/30">
                            <!-- Workspace Navigation Sub-Tabs -->
                            <div class="h-10 border-b border-border px-4 bg-surface/50 flex items-center justify-between shrink-0">
                                <div class="flex gap-2 h-full" id="prompt-sub-tabs">
                                    <button onclick="switchPromptSubTab('editor')" id="prompt-tab-editor" class="px-3 h-full text-xs font-medium text-accent border-b-2 border-accent transition flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-sm">edit</span> Prompt Editor
                                    </button>
                                    <button onclick="switchPromptSubTab('tester')" id="prompt-tab-tester" class="px-3 h-full text-xs font-medium text-muted hover:text-text border-b-2 border-transparent transition flex items-center gap-1.5">
                                        <span class="material-symbols-outlined text-sm">smart_toy</span> Live Tester
                                    </button>
                                </div>
                                <span class="text-[9px] font-mono text-muted uppercase" id="prompt-editor-id">Active: promt_001</span>
                            </div>

                            <!-- Panel Contents Container -->
                            <div class="flex-1 overflow-y-auto custom-scrollbar p-5 space-y-4" id="prompt-pane-content">
                                
                                <!-- Editor Pane -->
                                <div id="prompt-pane-editor" class="prompt-tab-view space-y-4">
                                    <div class="grid grid-cols-2 gap-4 p-4 bg-background border border-border rounded-xl">
                                        <div class="space-y-1.5">
                                            <label class="block text-[9px] font-mono text-muted uppercase">Prompt Name</label>
                                            <input id="prompt-edit-name" class="w-full bg-[#071018] border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="text" value="Tech Summarizer" oninput="updatePromptField('name')"/>
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="block text-[9px] font-mono text-muted uppercase">Target Category</label>
                                            <select id="prompt-edit-category" class="w-full bg-[#071018] border border-border text-text text-xs rounded-xl p-2 focus:outline-none focus:border-accent" onchange="updatePromptField('category')">
                                                <option>Summarizer</option>
                                                <option>Bulletins</option>
                                                <option>Analysis</option>
                                            </select>
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="block text-[9px] font-mono text-muted uppercase">Version</label>
                                            <input id="prompt-edit-version" class="w-full bg-[#071018] border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="text" value="v1.2" oninput="updatePromptField('version')"/>
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="block text-[9px] font-mono text-muted uppercase">Status</label>
                                            <select id="prompt-edit-status" class="w-full bg-[#071018] border border-border text-text text-xs rounded-xl p-2 focus:outline-none focus:border-accent" onchange="updatePromptField('status')">
                                                <option value="active">Active</option>
                                                <option value="draft">Draft</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Variables Toolbar -->
                                    <div class="space-y-1.5">
                                        <span class="text-[9px] font-mono text-muted uppercase">Placeholder Variables (Click to copy)</span>
                                        <div class="flex flex-wrap gap-1" id="prompt-variables-container">
                                            <span data-var="category" class="prompt-var-chip px-2 py-0.5 rounded bg-white/5 border border-border text-[9px] font-mono text-muted cursor-pointer hover:border-accent hover:text-text transition">&#123;&#123;category&#125;&#125;</span>
                                            <span data-var="keywords" class="prompt-var-chip px-2 py-0.5 rounded bg-white/5 border border-border text-[9px] font-mono text-muted cursor-pointer hover:border-accent hover:text-text transition">&#123;&#123;keywords&#125;&#125;</span>
                                            <span data-var="tone" class="prompt-var-chip px-2 py-0.5 rounded bg-white/5 border border-border text-[9px] font-mono text-muted cursor-pointer hover:border-accent hover:text-text transition">&#123;&#123;tone&#125;&#125;</span>
                                            <span data-var="language" class="prompt-var-chip px-2 py-0.5 rounded bg-white/5 border border-border text-[9px] font-mono text-muted cursor-pointer hover:border-accent hover:text-text transition">&#123;&#123;language&#125;&#125;</span>
                                            <span data-var="website" class="prompt-var-chip px-2 py-0.5 rounded bg-white/5 border border-border text-[9px] font-mono text-muted cursor-pointer hover:border-accent hover:text-text transition">&#123;&#123;website&#125;&#125;</span>
                                            <span data-var="date" class="prompt-var-chip px-2 py-0.5 rounded bg-white/5 border border-border text-[9px] font-mono text-muted cursor-pointer hover:border-accent hover:text-text transition">&#123;&#123;date&#125;&#125;</span>
                                        </div>
                                    </div>

                                    <!-- Code Editor Input Box -->
                                    <div class="space-y-1.5 flex-1 flex flex-col">
                                        <label class="block text-[9px] font-mono text-muted uppercase">Prompt Template Instructions</label>
                                        <textarea id="prompt-editor-textarea" class="w-full h-56 bg-background border border-border rounded-xl p-4 font-mono text-xs text-text focus:outline-none focus:border-accent focus:ring-0 leading-relaxed" placeholder="News article prompt instructions..." oninput="updatePromptField('prompt')">You are a professional news journalist. Write a comprehensive, factual, and engaging @{{category}} news article based on the latest headlines and research provided. Tone: @{{tone}}. Language: @{{language}}. Focus keywords: @{{keywords}}. Include source attribution where applicable. Date: @{{date}}.</textarea>
                                    </div>

                                    <!-- Footer Actions -->
                                    <div class="flex justify-between items-center pt-2">
                                        <div class="flex gap-4 text-[10px] font-mono text-muted">
                                            <span class="text-muted">Cost: calculated at runtime</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <button onclick="saveActivePrompt()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-1.5 rounded-xl transition">Save Template Settings</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tester Pane -->
                                <div id="prompt-pane-tester" class="prompt-tab-view space-y-4 hidden">
                                    <div class="grid grid-cols-2 gap-4">
                                        <!-- Test Inputs -->
                                        <div class="space-y-4">
                                            <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Variable Mock Inputs</h4>
                                            <div class="space-y-3">
                                                <div>
                                                    <label class="block text-[10px] font-mono text-muted mb-1" for="test-category">&#123;&#123;category&#125;&#125;</label>
                                                    <input id="test-category" class="w-full bg-background border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="text" value="Technology"/>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-mono text-muted mb-1" for="test-keywords">&#123;&#123;keywords&#125;&#125;</label>
                                                    <input id="test-keywords" class="w-full bg-background border border-border rounded-xl p-2 text-xs text-text focus:outline-none focus:border-accent" type="text" value="AI, machine learning, OpenAI"/>
                                                </div>
                                                <button onclick="runPromptTestSimulation()" class="w-full bg-secondary hover:bg-secondary/80 text-background font-medium text-xs py-2 rounded-xl transition">Execute Prompt Dry-Run</button>
                                            </div>
                                        </div>

                                        <!-- Test Outputs -->
                                        <div class="space-y-4">
                                            <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Generated Preview Output</h4>
                                            <div id="prompt-test-output-window" class="h-44 bg-[#071018] border border-border rounded-xl p-4 font-mono text-[11px] text-muted overflow-y-auto leading-relaxed">
                                                Click "Execute Prompt Dry-Run" to trigger local AI generation pipeline preview...
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>
