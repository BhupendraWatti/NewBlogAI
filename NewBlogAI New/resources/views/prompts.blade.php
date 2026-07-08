<!DOCTYPE html>
<html class="dark" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Prompt Engineering Lab - NewsBlogify AI</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&amp;family=Outfit:wght@600;700&amp;family=JetBrains+Mono:wght@400;500&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "tertiary": "#ffb2b7",
                        "outline": "#908fa0",
                        "secondary-fixed-dim": "#4edea3",
                        "surface-variant": "#313540",
                        "on-error-container": "#ffdad6",
                        "surface-container-low": "#171b26",
                        "error": "#ffb4ab",
                        "tertiary-container": "#ff516a",
                        "surface-container-high": "#262a35",
                        "on-background": "#dfe2f1",
                        "surface-dim": "#0f131d",
                        "surface-container-lowest": "#0a0e18",
                        "surface-container-highest": "#313540",
                        "surface-container": "#1c1f2a",
                        "primary-fixed": "#e1e0ff",
                        "error-container": "#93000a",
                        "on-tertiary": "#67001b",
                        "inverse-surface": "#dfe2f1",
                        "outline-variant": "#464554",
                        "surface": "#0f131d",
                        "on-primary": "#1000a9",
                        "on-tertiary-fixed-variant": "#92002a",
                        "on-secondary-container": "#00311f",
                        "secondary-container": "#00a572",
                        "on-surface-variant": "#c7c4d7",
                        "on-tertiary-fixed": "#40000d",
                        "primary-container": "#8083ff",
                        "on-surface": "#dfe2f1",
                        "surface-bright": "#353944",
                        "inverse-on-surface": "#2c303b",
                        "tertiary-fixed": "#ffdadb",
                        "on-secondary-fixed": "#002113",
                        "background": "#0f131d",
                        "on-secondary-fixed-variant": "#005236",
                        "on-error": "#690005",
                        "on-tertiary-container": "#5b0017",
                        "on-primary-fixed-variant": "#2f2ebe",
                        "surface-tint": "#c0c1ff",
                        "on-secondary": "#003824",
                        "on-primary-fixed": "#07006c",
                        "on-primary-container": "#0d0096",
                        "inverse-primary": "#494bd6",
                        "primary-fixed-dim": "#c0c1ff",
                        "primary": "#c0c1ff",
                        "secondary": "#4edea3",
                        "tertiary-fixed-dim": "#ffb2b7",
                        "secondary-fixed": "#6ffbbe"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "margin-page": "32px",
                        "inspector-width": "320px",
                        "stack-md": "16px",
                        "gutter": "24px",
                        "stack-xs": "4px",
                        "stack-sm": "8px",
                        "stack-lg": "24px",
                        "sidebar-width": "260px"
                    },
                    "fontFamily": {
                        "headline-lg": ["Outfit"],
                        "body-lg": ["Inter"],
                        "body-sm": ["Inter"],
                        "body-md": ["Inter"],
                        "headline-lg-mobile": ["Outfit"],
                        "label-md": ["Inter"],
                        "mono-sm": ["JetBrains Mono"],
                        "headline-md": ["Outfit"],
                        "label-sm": ["Inter"],
                        "display-lg": ["Outfit"]
                    }
                }
            }
        }
    </script>
    <style>
        #prompts-workspace { background-color: #0B0F19; color: #dfe2f1; }
        #prompts-workspace .glass-panel {
            background-color: rgba(17, 24, 39, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid #1F2937;
        }
        #prompts-workspace .code-input {
            background-color: #0B0F19;
            border: 1px solid #1F2937;
            color: #dfe2f1;
            font-family: 'JetBrains Mono', monospace;
        }
        #prompts-workspace .code-input:focus {
            border-color: #c0c1ff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(192, 193, 255, 0.2);
        }
        
        #prompts-workspace ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        #prompts-workspace ::-webkit-scrollbar-track {
            background: transparent;
        }
        #prompts-workspace ::-webkit-scrollbar-thumb {
            background: #464554;
            border-radius: 3px;
        }
        #prompts-workspace ::-webkit-scrollbar-thumb:hover {
            background: #908fa0;
        }
    </style>
</head>
<body id="prompts-workspace" class="font-body-md text-body-md antialiased overflow-hidden flex h-screen">

    <!-- Sidebar Navigation (Complex Sidebar Style) -->
    <aside class="w-sidebar-width h-screen fixed left-0 top-0 bg-surface dark:bg-surface border-r border-outline-variant flex flex-col h-full py-stack-lg px-stack-md z-40 hidden md:flex">
        <div class="mb-stack-lg px-2 flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-primary/20 flex items-center justify-center border border-primary/30">
                <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
            </div>
            <div>
                <h1 class="font-label-md text-label-md text-on-surface font-bold tracking-tight">NewsBlogify AI</h1>
                <p class="font-label-sm text-label-sm text-on-surface-variant">Enterprise Console</p>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto flex flex-col gap-1 space-y-1">
            <a class="flex items-center gap-3 text-on-surface-variant hover:text-on-surface p-2 hover:bg-surface-container-high transition-colors duration-200 rounded-lg scale-98 active:scale-95 transition-transform" href="/">
                <span class="material-symbols-outlined">dashboard</span> Dashboard
            </a>
            <a class="flex items-center gap-3 text-on-surface-variant hover:text-on-surface p-2 hover:bg-surface-container-high transition-colors duration-200 rounded-lg scale-98 active:scale-95 transition-transform" href="/customers">
                <span class="material-symbols-outlined">group</span> Customers
            </a>
            <a class="flex items-center gap-3 text-on-surface-variant hover:text-on-surface p-2 hover:bg-surface-container-high transition-colors duration-200 rounded-lg scale-98 active:scale-95 transition-transform" href="/fleet">
                <span class="material-symbols-outlined">cooking</span> Fleet Manager
            </a>
            <a class="flex items-center gap-3 text-on-surface-variant hover:text-on-surface p-2 hover:bg-surface-container-high transition-colors duration-200 rounded-lg scale-98 active:scale-95 transition-transform" href="/sites">
                <span class="material-symbols-outlined">language</span> Sites Manager
            </a>
            <a class="flex items-center gap-3 bg-primary-container text-on-primary-container rounded-lg p-2 border-l-2 border-primary shadow-[0_0_15px_rgba(192,193,255,0.3)] scale-98 active:scale-95 transition-transform" href="/prompts">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">book</span> Prompt Library
            </a>
        </nav>
        
        <div class="mt-auto pt-4 border-t border-outline-variant">
            <a class="flex items-center gap-3 text-on-surface-variant hover:text-on-surface p-2 hover:bg-surface-container-high transition-colors duration-200 rounded-lg scale-98 active:scale-95 transition-transform" href="#">
                <span class="material-symbols-outlined">help</span> Support
            </a>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="flex-1 flex flex-col ml-0 md:ml-sidebar-width h-screen overflow-hidden bg-background relative">
        <!-- TopNavBar -->
        <header class="sticky top-0 z-50 w-full flex justify-between items-center h-16 px-gutter bg-surface/80 dark:bg-surface/80 backdrop-blur-xl border-b border-outline-variant">
            <div class="flex items-center gap-4 w-1/3">
                <div class="relative w-full max-w-md hidden sm:block focus-within:ring-2 focus-within:ring-primary/20 rounded-md">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-sm">search</span>
                    <input id="search-prompts" oninput="searchPrompts(this.value)" class="w-full bg-surface-container-lowest border border-outline-variant rounded-md py-1.5 pl-9 pr-3 text-sm focus:border-primary focus:ring-0 transition-colors placeholder:text-on-surface-variant" placeholder="Search prompts..." type="text"/>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button class="w-8 h-8 flex items-center justify-center text-on-surface-variant hover:bg-surface-container-highest rounded-full transition-all"><span class="material-symbols-outlined text-[20px]">notifications</span></button>
                <button class="w-8 h-8 flex items-center justify-center text-on-surface-variant hover:bg-surface-container-highest rounded-full transition-all"><span class="material-symbols-outlined text-[20px]">help</span></button>
            </div>
        </header>

        <!-- Main Workspace Area -->
        <main class="flex-1 overflow-y-auto p-gutter flex gap-gutter relative h-full">
            
            <!-- Left Column: Prompt Library List -->
            <div class="w-1/4 flex flex-col gap-4 shrink-0 h-full overflow-y-auto pr-2 pb-8">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="font-headline-md text-headline-md text-on-surface">Library</h2>
                    <button onclick="newPrompt()" class="bg-primary text-on-primary-fixed flex items-center gap-1 px-3 py-1.5 rounded-md text-sm font-medium hover:bg-primary-fixed-dim transition-colors">
                        <span class="material-symbols-outlined text-sm">add</span> New
                    </button>
                </div>
                <div id="prompts-list" class="space-y-3">
                    <!-- Loaded dynamically -->
                    <div class="text-center text-outline py-6">Loading templates...</div>
                </div>
            </div>

            <!-- Center Column: Editor & Preview Split -->
            <div class="flex-1 flex flex-col gap-gutter min-w-0 h-full pb-8">
                <!-- Toolbar -->
                <div class="glass-panel rounded-lg p-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="font-label-md text-label-md text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[18px]">terminal</span>
                            <input id="prompt-name" class="bg-transparent border-none text-on-surface font-semibold p-0 focus:ring-0 w-48 text-sm" placeholder="Untitled Prompt" value="Untitled Prompt">
                        </span>
                        <span class="px-2 py-0.5 rounded-full bg-surface-container-highest text-xs text-on-surface-variant border border-outline-variant">Draft Mode</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button onclick="savePrompt()" class="p-1.5 text-on-surface-variant hover:text-on-surface rounded-md hover:bg-surface-container-high transition-colors" title="Save Prompt">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                        </button>
                        <button onclick="runPromptTest()" class="bg-primary/10 border border-primary/30 text-primary px-3 py-1.5 rounded-md text-sm font-medium hover:bg-primary/20 transition-colors flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">play_arrow</span> Run Test
                        </button>
                    </div>
                </div>

                <!-- Editor / Output Split Container -->
                <div class="flex-1 grid grid-cols-2 gap-4 h-[calc(100%-4rem)]">
                    <!-- Editor Pane -->
                    <div class="glass-panel rounded-lg flex flex-col overflow-hidden relative">
                        <div class="bg-surface-container-lowest border-b border-outline-variant px-4 py-2 flex items-center justify-between">
                            <span class="font-mono-sm text-mono-sm text-outline flex items-center gap-2"><span class="material-symbols-outlined text-sm">code</span> System Prompt</span>
                        </div>
                        <textarea id="prompt-text" class="code-input flex-1 p-4 w-full resize-none font-mono-sm text-mono-sm leading-relaxed" spellcheck="false">You are an expert technology journalist writing for {{site_name}}.
Your task is to summarize the following source text into a highly engaging, concise news update.

Focus on these key areas if present:
- {{keyword_list}}
- Major product announcements

Source Text:
{{source_article_content}}</textarea>
                    </div>

                    <!-- Preview/Output Pane -->
                    <div class="glass-panel rounded-lg flex flex-col overflow-hidden relative">
                        <div class="bg-surface-container-lowest border-b border-outline-variant px-4 py-2 flex items-center justify-between">
                            <span class="font-mono-sm text-mono-sm text-outline flex items-center gap-2"><span class="material-symbols-outlined text-sm">preview</span> Output Preview</span>
                            <div class="flex items-center gap-2">
                                <select id="model-select" class="bg-transparent text-xs text-on-surface-variant border-none focus:ring-0 cursor-pointer py-0">
                                    <option>GPT-4o (OpenAI)</option>
                                    <option>Claude 3.5 Sonnet</option>
                                    <option>Llama 3 70B</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex-1 p-4 overflow-y-auto font-body-sm text-body-sm text-on-surface-variant leading-relaxed bg-[#0B0F19]">
                            <!-- Loading Skeleton -->
                            <div class="animate-pulse flex space-x-4 mb-4 hidden" id="loading-skeleton">
                                <div class="flex-1 space-y-4 py-1">
                                    <div class="h-4 bg-surface-variant rounded w-3/4"></div>
                                    <div class="space-y-2">
                                        <div class="h-4 bg-surface-variant rounded"></div>
                                        <div class="h-4 bg-surface-variant rounded w-5/6"></div>
                                    </div>
                                </div>
                            </div>
                            <!-- Output Text Container -->
                            <div id="output-content">
                                <p class="text-outline">Run a test of the prompt matrix to verify responses.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Inspector (Variables & Settings) -->
            <aside class="w-inspector-width shrink-0 h-full overflow-y-auto glass-panel border-l-0 rounded-l-none border-t-0 border-b-0 border-r-0 pl-4 pb-8 hidden lg:block border-l border-outline-variant bg-surface/30">
                <div class="mb-6 mt-2">
                    <h3 class="font-label-md text-label-md text-on-surface mb-3 flex items-center gap-2 uppercase tracking-widest text-xs text-outline">
                        <span class="material-symbols-outlined text-sm">data_object</span> Variables Injection
                    </h3>
                    <div class="space-y-3">
                        <div class="bg-surface-container-lowest p-3 rounded border border-outline-variant/50 relative overflow-hidden group">
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-tertiary"></div>
                            <div class="flex justify-between items-center mb-1 pl-2">
                                <span class="font-mono-sm text-xs text-tertiary">{{site_name}}</span>
                            </div>
                            <input id="var-site-name" class="w-full bg-surface-dim border border-outline-variant/50 rounded px-2 py-1 text-xs text-on-surface mt-1 focus:border-tertiary focus:ring-0" type="text" value="TechCrunch Daily"/>
                        </div>
                        <div class="bg-surface-container-lowest p-3 rounded border border-outline-variant/50 relative overflow-hidden group">
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-secondary"></div>
                            <div class="flex justify-between items-center mb-1 pl-2">
                                <span class="font-mono-sm text-xs text-secondary">{{keyword_list}}</span>
                            </div>
                            <input id="var-keyword-list" class="w-full bg-surface-dim border border-outline-variant/50 rounded px-2 py-1 text-xs text-on-surface mt-1 focus:border-secondary focus:ring-0" type="text" value="AI, Hardware, Neural Engine"/>
                        </div>
                        <div class="bg-surface-container-lowest p-3 rounded border border-outline-variant/50 relative overflow-hidden group">
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-primary"></div>
                            <div class="flex justify-between items-center mb-1 pl-2">
                                <span class="font-mono-sm text-xs text-primary">{{source_article_content}}</span>
                            </div>
                            <textarea id="var-article-content" class="w-full bg-surface-dim border border-outline-variant/50 rounded px-2 py-1 text-xs text-on-surface mt-1 focus:border-primary focus:ring-0 h-24 resize-none">Apple today announced the M4, the next generation of Apple silicon that delivers phenomenal performance to the all-new iPad Pro. Built using second-generation 3-nanometer technology, M4 is a system on a chip (SoC) that advances the industry-leading power efficiency of Apple silicon...</textarea>
                        </div>
                    </div>
                </div>

                <div class="mb-6 pt-4 border-t border-outline-variant">
                    <h3 class="font-label-md text-label-md text-on-surface mb-3 flex items-center gap-2 uppercase tracking-widest text-xs text-outline">
                        <span class="material-symbols-outlined text-sm">tune</span> Model Parameters
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between mb-1">
                                <label class="text-xs text-on-surface-variant">Temperature</label>
                                <span class="text-xs font-mono-sm text-outline" id="temp-val">0.7</span>
                            </div>
                            <input id="param-temp" oninput="document.getElementById('temp-val').innerText = this.value" class="w-full h-1 bg-surface-variant rounded-lg appearance-none cursor-pointer accent-primary" max="2" min="0" step="0.1" type="range" value="0.7"/>
                        </div>
                        <div>
                            <div class="flex justify-between mb-1">
                                <label class="text-xs text-on-surface-variant">Max Tokens</label>
                                <span class="text-xs font-mono-sm text-outline" id="tokens-val">500</span>
                            </div>
                            <input id="param-tokens" oninput="document.getElementById('tokens-val').innerText = this.value" class="w-full h-1 bg-surface-variant rounded-lg appearance-none cursor-pointer accent-primary" max="4000" min="100" step="100" type="range" value="500"/>
                        </div>
                    </div>
                </div>
            </aside>
        </main>
    </div>

    <script>
        let loadedPrompts = [];
        let activePromptId = null;

        document.addEventListener('DOMContentLoaded', () => {
            fetchPrompts();
        });

        async function fetchPrompts() {
            try {
                const response = await apiFetch('/api/v1/prompts');
                loadedPrompts = await response.json();
                renderPrompts(loadedPrompts);
            } catch (err) {
                console.error("Error fetching prompts:", err);
            }
        }

        function renderPrompts(list) {
            const container = document.getElementById('prompts-list');
            container.innerHTML = '';

            if (list.length === 0) {
                container.innerHTML = '<div class="text-center text-outline py-6">No prompt templates registered.</div>';
                return;
            }

            list.forEach((p, idx) => {
                const item = document.createElement('div');
                const isActive = activePromptId === p.id;
                
                item.className = `glass-panel rounded-lg p-4 cursor-pointer border-l-2 ${isActive ? 'border-primary shadow-[inset_0_0_20px_rgba(192,193,255,0.05)]' : 'border-transparent hover:border-outline'} transition-all relative overflow-hidden group`;
                
                item.onclick = () => selectPrompt(p);

                item.innerHTML = `
                    <div class="flex justify-between items-start mb-2 relative z-10">
                        <span class="bg-surface-container-high px-2 py-0.5 rounded text-xs font-mono-sm text-outline-variant border border-outline-variant">ID-${p.id}</span>
                        <span onclick="event.stopPropagation(); deletePrompt(${p.id})" class="material-symbols-outlined text-outline hover:text-error text-sm">delete</span>
                    </div>
                    <h3 class="font-label-md text-label-md text-on-surface mb-1 relative z-10">${p.name}</h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant line-clamp-2 relative z-10">${p.promt}</p>
                `;
                container.appendChild(item);
            });

            if (!activePromptId && list.length > 0) {
                selectPrompt(list[0]);
            }
        }

        function selectPrompt(p) {
            activePromptId = p.id;
            document.getElementById('prompt-name').value = p.name;
            document.getElementById('prompt-text').value = p.promt;
            renderPrompts(loadedPrompts);
        }

        function newPrompt() {
            activePromptId = null;
            document.getElementById('prompt-name').value = 'New Prompt Template';
            document.getElementById('prompt-text').value = 'Enter your custom AI prompt structure here...';
            renderPrompts(loadedPrompts);
        }

        window.showWarning = window.showWarning || function(title, message) { alert(title + '\n' + message); };
        window.showSuccess = window.showSuccess || function(title, message) { alert(title + '\n' + message); };
        window.showError = window.showError || function(title, message) { alert(title + '\n' + message); };
        window.showConfirmation = window.showConfirmation || function(title, message, onConfirm) { if (confirm(title + '\n' + message)) { onConfirm?.(); } };

        async function savePrompt() {
            const name = document.getElementById('prompt-name').value;
            const promt = document.getElementById('prompt-text').value;

            if (!name || !promt) {
                showWarning("Input Required", "Prompt name and template content are required.");
                return;
            }

            try {
                let response;
                if (activePromptId) {
                    response = await apiFetch(`/api/v1/prompts/${activePromptId}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name, promt })
                    });
                } else {
                    response = await apiFetch('/api/v1/prompts', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name, promt })
                    });
                }

                if (response.ok) {
                    const result = await response.json();
                    if (!activePromptId) activePromptId = result.data.id;
                    await fetchPrompts();
                    showSuccess("Prompt Saved", "Prompt template saved successfully in the Library!");
                } else {
                    showError("Save Failed", "Error saving prompt template.");
                }
            } catch (err) {
                console.error("Error saving prompt:", err);
                showError("System Error", "Could not complete saving request.");
            }
        }

        async function deletePrompt(id) {
            showConfirmation(
                "Delete Prompt Template",
                "Are you sure you want to delete this prompt template?",
                async () => {
                    try {
                        const response = await apiFetch(`/api/v1/prompts/${id}`, { method: 'DELETE' });
                        if (response.ok) {
                            if (activePromptId === id) activePromptId = null;
                            await fetchPrompts();
                            showSuccess("Prompt Deleted", "Prompt template deleted successfully.");
                        } else {
                            showError("Deletion Failed", "Error deleting prompt.");
                        }
                    } catch (err) {
                        console.error("Error deleting prompt:", err);
                        showError("System Error", "Could not complete deletion request.");
                    }
                }
            );
        }

        function runPromptTest() {
            const skeleton = document.getElementById('loading-skeleton');
            const output = document.getElementById('output-content');
            const model = document.getElementById('model-select').value;

            const siteName = document.getElementById('var-site-name').value;
            const keywords = document.getElementById('var-keyword-list').value;
            const content = document.getElementById('var-article-content').value;

            skeleton.classList.remove('hidden');
            output.classList.add('hidden');

            setTimeout(() => {
                skeleton.classList.add('hidden');
                output.classList.remove('hidden');

                output.innerHTML = `
                    <h4 class="font-label-md text-label-md text-on-surface mb-2">Simulated Completion for ${siteName}</h4>
                    <p class="mb-4">Here is the compiled summary generated based on target focus keywords (<strong>${keywords}</strong>):</p>
                    <div class="bg-surface-container-high/40 p-4 rounded border border-outline-variant/30 text-on-surface font-sans mb-4">
                        "${content.substring(0, 150)}..." has been summarized successfully into 3 bullet points targeting key announcements.
                    </div>
                    <div class="mt-6 pt-4 border-t border-surface-variant flex items-center gap-2 text-xs text-outline">
                        <span class="material-symbols-outlined text-[14px] text-secondary">check_circle</span> Simulated execution completed in 0.85s by ${model}
                    </div>
                `;
            }, 1000);
        }

        function searchPrompts(val) {
            const query = val.toLowerCase();
            const filtered = loadedPrompts.filter(p => p.name.toLowerCase().includes(query) || p.promt.toLowerCase().includes(query));
            renderPrompts(filtered);
        }
    </script>
</body>
</html>
