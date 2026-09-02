<!-- SETTING MASTER NODE WORKSPACE -->
<div id="node-master-settings" class="workspace-pane space-y-6 hidden">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-accent text-2xl">tune</span>
                <h2 class="font-display font-bold text-2xl">Setting Master</h2>
            </div>
            <p class="text-xs text-muted mt-1">Manage dynamic options for News Topics, Countries, and States used in Content Generation. Zero hardcoded options.</p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="fetchMasterOptions()" class="bg-surface hover:bg-surface/80 border border-border text-text font-medium text-xs px-3 py-2 rounded-xl transition flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm">refresh</span> Refresh
            </button>
            <button onclick="openMasterOptionModal()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                <span class="material-symbols-outlined text-sm font-bold">add</span> Add New Option
            </button>
        </div>
    </div>

    <!-- Master Options Sub-tabs -->
    <div class="flex items-center gap-1 p-1 bg-surface border border-border rounded-xl w-fit">
        <button onclick="switchMasterTab('topic')" id="master-tab-topic" class="px-4 py-1.5 rounded-lg text-xs font-mono bg-white/5 text-accent font-semibold transition flex items-center gap-1.5">
            <span class="material-symbols-outlined text-sm">category</span> Topics / Categories
        </button>
        <button onclick="switchMasterTab('country')" id="master-tab-country" class="px-4 py-1.5 rounded-lg text-xs font-mono text-muted hover:text-text transition flex items-center gap-1.5">
            <span class="material-symbols-outlined text-sm">public</span> Countries
        </button>
        <button onclick="switchMasterTab('state')" id="master-tab-state" class="px-4 py-1.5 rounded-lg text-xs font-mono text-muted hover:text-text transition flex items-center gap-1.5">
            <span class="material-symbols-outlined text-sm">location_city</span> States / Regions
        </button>
    </div>

    <!-- Search and Filter Bar -->
    <div class="flex flex-wrap items-center gap-3 p-3 bg-surface border border-border rounded-2xl">
        <div class="relative flex-1 min-w-[200px] max-w-md">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted text-lg">search</span>
            <input id="master-search-input" onkeyup="filterMasterOptionsTable()" class="w-full bg-background border border-border rounded-xl py-1.5 pl-10 pr-4 text-xs font-mono text-text placeholder-muted focus:outline-none focus:border-accent" placeholder="Search by name or code..." type="text"/>
        </div>
        <div id="master-country-filter-container" class="hidden">
            <select id="master-filter-country" onchange="filterMasterOptionsTable()" class="bg-background border border-border text-text text-xs rounded-xl py-1.5 px-3 cursor-pointer focus:outline-none focus:border-accent">
                <option value="">All Countries</option>
            </select>
        </div>
        <div class="text-[11px] font-mono text-muted ml-auto" id="master-count-badge">
            Loading options...
        </div>
    </div>

    <!-- Master Options Table -->
    <div class="glass-surface rounded-2xl overflow-hidden border border-border">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface/60 border-b border-border text-muted font-mono text-[10px] uppercase tracking-wider">
                        <th class="p-3.5 pl-5">Name</th>
                        <th class="p-3.5">Code / Identifier</th>
                        <th class="p-3.5 master-col-parent hidden">Parent Country</th>
                        <th class="p-3.5">Order</th>
                        <th class="p-3.5">Status</th>
                        <th class="p-3.5 text-right pr-5">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border text-xs font-mono" id="master-table-body">
                    <tr>
                        <td colspan="6" class="p-8 text-center text-muted">Loading options...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Empty State -->
        <div class="hidden flex-col items-center justify-center py-16 text-center" id="master-empty-state">
            <span class="material-symbols-outlined text-4xl text-muted mb-2">inbox</span>
            <h4 class="font-display font-bold text-sm">No options found</h4>
            <p class="text-xs text-muted max-w-xs mt-1">No master options match your filter. Add a new option above to get started.</p>
        </div>
    </div>
</div>

<!-- Modal: Add/Edit Master Option -->
<div id="master-option-modal" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-sidebar border border-border rounded-2xl w-full max-w-md overflow-hidden shadow-2xl animate-fade-in">
        <div class="flex items-center justify-between p-5 border-b border-border bg-surface/30">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-accent text-xl" id="master-modal-icon">tune</span>
                <h3 class="font-display font-bold text-sm" id="master-modal-title">Add Master Option</h3>
            </div>
            <button onclick="closeMasterOptionModal()" class="text-muted hover:text-text rounded-lg p-1 transition">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <form id="master-option-form" onsubmit="saveMasterOption(event)" class="p-5 space-y-4">
            <input type="hidden" id="master-option-id" value="">

            <!-- Type -->
            <div class="space-y-1">
                <label class="block text-[10px] font-mono text-muted uppercase tracking-widest" for="master-form-type">Option Type</label>
                <select id="master-form-type" onchange="handleMasterTypeChange()" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2 px-3 focus:outline-none focus:border-accent">
                    <option value="topic">Topic / Category</option>
                    <option value="country">Country</option>
                    <option value="state">State / Region</option>
                </select>
            </div>

            <!-- Name -->
            <div class="space-y-1">
                <label class="block text-[10px] font-mono text-muted uppercase tracking-widest" for="master-form-name">Option Name *</label>
                <input type="text" id="master-form-name" required placeholder="e.g. Indian Startups, Maharashtra, India" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2.5 px-3 focus:outline-none focus:border-accent">
            </div>

            <!-- Code / Slug -->
            <div class="space-y-1">
                <label class="block text-[10px] font-mono text-muted uppercase tracking-widest" for="master-form-code">Code / Identifier (Optional)</label>
                <input type="text" id="master-form-code" placeholder="e.g. IN, MH, tech-startups" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2.5 px-3 focus:outline-none focus:border-accent font-mono">
            </div>

            <!-- Parent Country (shown when type is state) -->
            <div class="space-y-1 hidden" id="master-form-parent-container">
                <label class="block text-[10px] font-mono text-muted uppercase tracking-widest" for="master-form-parent">Parent Country *</label>
                <select id="master-form-parent" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2 px-3 focus:outline-none focus:border-accent">
                    <option value="">— Select Parent Country —</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <!-- Sort Order -->
                <div class="space-y-1">
                    <label class="block text-[10px] font-mono text-muted uppercase tracking-widest" for="master-form-order">Sort Order</label>
                    <input type="number" id="master-form-order" value="0" min="0" class="w-full bg-background border border-border text-text text-xs rounded-xl py-2 px-3 focus:outline-none focus:border-accent font-mono">
                </div>

                <!-- Active Status -->
                <div class="space-y-1 flex flex-col justify-end">
                    <label class="flex items-center gap-2 cursor-pointer pb-2">
                        <input type="checkbox" id="master-form-active" checked class="rounded bg-background border-border text-accent focus:ring-accent/20">
                        <span class="text-xs font-medium">Active (Visible in Generator)</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
                <button type="button" onclick="closeMasterOptionModal()" class="bg-surface hover:bg-surface/80 border border-border text-text font-medium text-xs px-4 py-2 rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" id="master-save-btn" class="bg-accent hover:bg-accent/80 text-background font-bold text-xs px-5 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                    <span class="material-symbols-outlined text-sm">save</span> Save Option
                </button>
            </div>
        </form>
    </div>
</div>
