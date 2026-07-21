                <!-- TOPIC MANAGEMENT WORKSPACE -->
                <div id="node-topics" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Topic Management</h2>
                            <p class="text-xs text-muted">Define and manage content topics. Each topic drives automated article generation for connected WordPress sites.</p>
                        </div>
                        <div class="flex gap-2">
                            <!-- Toggle Grid / Table -->
                            <div class="bg-surface p-1 border border-border rounded-xl flex items-center gap-1">
                                <button onclick="toggleTopicsView('table')" id="topics-view-table-btn" class="p-1.5 rounded-lg bg-white/5 text-accent flex items-center justify-center transition">
                                    <span class="material-symbols-outlined text-sm">table_rows</span>
                                </button>
                                <button onclick="toggleTopicsView('grid')" id="topics-view-grid-btn" class="p-1.5 rounded-lg text-muted hover:text-text flex items-center justify-center transition">
                                    <span class="material-symbols-outlined text-sm">grid_view</span>
                                </button>
                            </div>
                            <button onclick="openTopicAddModal()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                                <span class="material-symbols-outlined text-sm font-bold">add</span> Add Topic
                            </button>
                        </div>
                    </div>

                    <!-- Category Coverage Dashboard -->
                    <div class="grid grid-cols-4 gap-4 hidden" id="category-coverage-dashboard">
                        <div class="glass-surface rounded-2xl p-4 border border-border">
                            <p class="text-[10px] font-mono text-muted uppercase">Fresh Categories</p>
                            <h4 class="text-2xl font-bold text-success" id="coverage-fresh-count">—</h4>
                            <div class="w-full bg-white/5 border border-border h-1.5 rounded-full overflow-hidden mt-2">
                                <div class="bg-success h-full rounded-full transition-all" id="coverage-fresh-pct" style="width: 0%"></div>
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-4 border border-border">
                            <p class="text-[10px] font-mono text-muted uppercase">Trending Categories</p>
                            <h4 class="text-2xl font-bold text-accent" id="coverage-trending-count">—</h4>
                            <div class="w-full bg-white/5 border border-border h-1.5 rounded-full overflow-hidden mt-2">
                                <div class="bg-accent h-full rounded-full transition-all" id="coverage-trending-pct" style="width: 0%"></div>
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-4 border border-border">
                            <p class="text-[10px] font-mono text-muted uppercase">Stale Categories</p>
                            <h4 class="text-2xl font-bold text-warning" id="coverage-stale-count">—</h4>
                            <div class="w-full bg-white/5 border border-border h-1.5 rounded-full overflow-hidden mt-2">
                                <div class="bg-warning h-full rounded-full transition-all" id="coverage-stale-pct" style="width: 0%"></div>
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-4 border border-border">
                            <p class="text-[10px] font-mono text-muted uppercase">Empty Categories</p>
                            <h4 class="text-2xl font-bold text-danger" id="coverage-empty-count">—</h4>
                            <div class="w-full bg-white/5 border border-border h-1.5 rounded-full overflow-hidden mt-2">
                                <div class="bg-danger h-full rounded-full transition-all" id="coverage-empty-pct" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Search & Filter -->
                    <div class="flex flex-wrap items-center gap-3 p-3 bg-surface border border-border rounded-2xl">
                        <div class="relative w-64">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted text-lg">search</span>
                            <input class="w-full bg-background border border-border rounded-xl py-1.5 pl-10 pr-4 text-xs font-mono text-text placeholder-muted focus:outline-none focus:border-accent focus:ring-0" placeholder="Search topics..." type="text"/>
                        </div>
                         <select class="bg-background border border-border text-text text-xs rounded-xl py-1.5 pl-2 pr-6 cursor-pointer focus:ring-accent" id="topic-filter-category" onchange="filterTopics()">
                            <option value="">All Categories</option>
                        </select>
                        <button class="text-xs text-muted hover:text-text font-mono ml-auto">Reset Filters</button>
                    </div>

                    <!-- Topics Table -->
                    <div id="topics-table-view" class="glass-surface rounded-2xl overflow-hidden border border-border">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase">
                                    <th class="p-3 pl-5 w-8"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20"/></th>
                                    <th class="p-3">Topic Name</th>
                                    <th class="p-3">Category</th>
                                    <th class="p-3">Language</th>
                                    <th class="p-3">Priority</th>
                                    <th class="p-3">Frequency</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3 text-right pr-5">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-xs font-mono" id="topics-table-body">
                                <!-- TODO: Populate from GET /api/v1/topics -->
                            </tbody>
                        </table>
                        <!-- Empty State -->
                        <div class="flex flex-col items-center justify-center py-16 text-center" id="topics-empty-state">
                            <span class="material-symbols-outlined text-4xl text-muted mb-3">topic</span>
                            <h3 class="font-display font-bold text-base mb-1">No Topics Found</h3>
                            <p class="text-xs text-muted max-w-xs">No topics have been created yet. Add your first topic to start generating content automatically.</p>
                            <button onclick="openTopicAddModal()" class="mt-4 bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">add</span> Add First Topic
                            </button>
                        </div>
                    </div>

                    <!-- Topics Grid View -->
                    <div id="topics-grid-view" class="hidden">
                        <!-- Empty State -->
                        <div class="flex flex-col items-center justify-center py-16 text-center">
                            <span class="material-symbols-outlined text-4xl text-muted mb-3">topic</span>
                            <h3 class="font-display font-bold text-base mb-1">No Topics Found</h3>
                            <p class="text-xs text-muted max-w-xs">Topics you create will appear here as cards.</p>
                        </div>
                    </div>
                </div>

                <div class="modal-overlay" id="topic-modal">
                    <div class="modal-container">
                        <div class="flex justify-between items-center mb-6 border-b border-border pb-3">
                            <h3 class="text-base font-bold font-display text-text" id="topic-modal-title">Add Content Topic</h3>
                            <button class="text-muted hover:text-text text-xl transition" onclick="closeTopicModal()">&times;</button>
                        </div>
                        <form id="topic-form" onsubmit="saveTopic(event)">
                            <input type="hidden" id="topic-id">
                            
                            <div class="mb-4">
                                <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="topic-name">Topic Name</label>
                                <input type="text" id="topic-name" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. Quantum Computing Innovations" required>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="topic-category">Category</label>
                                    <input type="text" id="topic-category" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. Technology" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="topic-language">Language</label>
                                    <input type="text" id="topic-language" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. English" required>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4 mb-4">
                                <div>
                                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="topic-priority">Priority</label>
                                    <select id="topic-priority" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent">
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="topic-status">Status</label>
                                    <select id="topic-status" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent">
                                        <option value="active" selected>Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="draft">Draft</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="topic-frequency">Frequency</label>
                                    <input type="text" id="topic-frequency" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. daily" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="topic-prompt-id">Associated Prompt</label>
                                <select id="topic-prompt-id" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent">
                                    <!-- Populated dynamically -->
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="topic-tags">Tags (Comma-separated)</label>
                                <input type="text" id="topic-tags" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. tech, quantum, ai">
                            </div>

                            <div class="flex justify-end gap-3 mt-6 border-t border-border pt-4">
                                <button type="button" class="border border-border text-muted hover:text-text hover:bg-white/5 px-4 py-2.5 rounded-xl font-medium transition" onclick="closeTopicModal()">Cancel</button>
                                <button type="submit" class="bg-accent hover:bg-accent/80 text-background px-5 py-2.5 rounded-xl font-semibold transition">Save Topic</button>
                            </div>
                        </form>
                    </div>
                </div>
