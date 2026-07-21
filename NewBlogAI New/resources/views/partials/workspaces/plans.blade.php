<!-- 18. PLANS WORKSPACE -->
<div id="node-plans" class="workspace-pane space-y-6 hidden">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="font-display font-bold text-2xl">Subscription Plan Manager</h2>
            <p class="text-xs text-muted">Configure subscription pricing tiers, credit quotas, storage allocation, and premium entitlement flags.</p>
        </div>
        <button onclick="openPlanAddModal()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
            <span class="material-symbols-outlined text-sm font-bold">add</span> Create Plan
        </button>
    </div>

    <!-- Datagrid Table -->
    <div class="glass-surface rounded-2xl overflow-hidden border border-border">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase tracking-wider">
                    <th class="p-3 pl-5">Plan Name</th>
                    <th class="p-3">Pricing (INR)</th>
                    <th class="p-3">Limits (Sites/Topics)</th>
                    <th class="p-3">Storage / AI Quota</th>
                    <th class="p-3">Features</th>
                    <th class="p-3">Status</th>
                    <th class="p-3 text-right pr-5">Operations</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border text-xs font-mono" id="plans-table-body">
                <!-- Populated dynamically from GET /api/v1/plans -->
            </tbody>
        </table>
        
        <!-- Empty State -->
        <div class="flex flex-col items-center justify-center py-16 text-center hidden" id="plans-empty-state">
            <span class="material-symbols-outlined text-4xl text-muted mb-3">card_membership</span>
            <h3 class="font-display font-bold text-base mb-1">No Subscription Plans Found</h3>
            <p class="text-xs text-muted max-w-xs">No billing plans configured in system. Define a master subscription plan to enable customer onboardings.</p>
            <button onclick="openPlanAddModal()" class="mt-4 bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm">add</span> Create Plan
            </button>
        </div>
    </div>
</div>

<!-- Add/Edit Plan slide-over Sheet (Drawer) -->
<div class="sheet-overlay" id="plan-modal">
    <div class="sheet-container">
        <!-- Header -->
        <div class="flex justify-between items-center p-6 border-b border-border shrink-0">
            <div>
                <h3 class="text-base font-bold font-display text-text" id="plan-modal-title">Define Pricing Plan</h3>
                <p class="text-[11px] text-muted font-mono mt-0.5">Define billing rules & entitlements</p>
            </div>
            <button class="text-muted hover:text-text text-xl transition" onclick="closePlanModal()">&times;</button>
        </div>

        <!-- Form container (scrollable body) -->
        <form id="plan-form" onsubmit="savePlan(event)" class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-6 pb-24">
            <input type="hidden" id="plan-id">
            
            <div class="grid grid-cols-3 gap-4">
                <div class="col-span-2">
                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="plan-name">Plan Name</label>
                    <input type="text" id="plan-name" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. Professional Plan" required>
                </div>
                <div>
                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="plan-status">Status</label>
                    <select id="plan-status" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent">
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="plan-monthly-price">Monthly Price (₹)</label>
                    <input type="number" step="0.01" id="plan-monthly-price" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. 79" required>
                </div>
                <div>
                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="plan-yearly-price">Yearly Price (₹)</label>
                    <input type="number" step="0.01" id="plan-yearly-price" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. 790" required>
                </div>
            </div>

            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="plan-max-wordpress-sites">Max Sites</label>
                    <input type="number" id="plan-max-wordpress-sites" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. 10" required>
                </div>
                <div>
                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="plan-max-topics">Max Topics</label>
                    <input type="number" id="plan-max-topics" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. 50" required>
                </div>
                <div>
                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="plan-publishing-schedule-limit">Max Schedules</label>
                    <input type="number" id="plan-publishing-schedule-limit" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. 20" required>
                </div>
                <div>
                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="plan-max-articles-per-day">Articles / Day</label>
                    <input type="number" id="plan-max-articles-per-day" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. 50" required>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="plan-monthly-generation-limit">Monthly Gen Limit</label>
                    <input type="number" id="plan-monthly-generation-limit" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. 500" required>
                </div>
                <div>
                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="plan-minimum-publishing-frequency">Min Frequency</label>
                    <select id="plan-minimum-publishing-frequency" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent">
                        <option value="hourly">Hourly</option>
                        <option value="twice_daily">Twice Daily</option>
                        <option value="daily" selected>Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="plan-prompt-templates-allowed">Allowed Prompts</label>
                    <input type="number" id="plan-prompt-templates-allowed" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. 20" required>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="plan-api-keys-allowed">Max API Keys</label>
                    <input type="number" id="plan-api-keys-allowed" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. 5" required>
                </div>
                <div>
                    <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-1.5" for="plan-storage-limit">Storage Limit (MB)</label>
                    <input type="number" id="plan-storage-limit" class="w-full bg-background border border-border rounded-xl px-4 py-2.5 text-text focus:outline-none focus:border-accent" placeholder="e.g. 5120" required>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-2">Available AI Providers</label>
                <div class="grid grid-cols-3 gap-3 bg-surface/50 border border-border rounded-xl p-4">
                    <label class="flex items-center gap-2.5 text-xs text-text cursor-pointer select-none">
                        <input type="checkbox" name="plan-ai-providers" value="openai" class="rounded bg-background border-border text-accent focus:ring-accent/20"> OpenAI
                    </label>
                    <label class="flex items-center gap-2.5 text-xs text-text cursor-pointer select-none">
                        <input type="checkbox" name="plan-ai-providers" value="gemini" class="rounded bg-background border-border text-accent focus:ring-accent/20"> Google Gemini
                    </label>
                    <label class="flex items-center gap-2.5 text-xs text-text cursor-pointer select-none">
                        <input type="checkbox" name="plan-ai-providers" value="claude" class="rounded bg-background border-border text-accent focus:ring-accent/20"> Claude
                    </label>
                    <label class="flex items-center gap-2.5 text-xs text-text cursor-pointer select-none">
                        <input type="checkbox" name="plan-ai-providers" value="groq" class="rounded bg-background border-border text-accent focus:ring-accent/20"> Groq
                    </label>
                    <label class="flex items-center gap-2.5 text-xs text-text cursor-pointer select-none">
                        <input type="checkbox" name="plan-ai-providers" value="openrouter" class="rounded bg-background border-border text-accent focus:ring-accent/20"> OpenRouter
                    </label>
                    <label class="flex items-center gap-2.5 text-xs text-text cursor-pointer select-none">
                        <input type="checkbox" name="plan-ai-providers" value="ollama" class="rounded bg-background border-border text-accent focus:ring-accent/20"> Ollama
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-mono font-bold text-muted uppercase tracking-widest mb-2">Premium Entitlements &amp; Features</label>
                <div class="grid grid-cols-2 gap-4 bg-surface/50 border border-border rounded-xl p-4">
                    <label class="flex items-center gap-2.5 text-xs text-text cursor-pointer select-none">
                        <input type="checkbox" id="plan-analytics-access" class="rounded bg-background border-border text-accent focus:ring-accent/20"> Analytics Access
                    </label>
                    <label class="flex items-center gap-2.5 text-xs text-text cursor-pointer select-none">
                        <input type="checkbox" id="plan-priority-support" class="rounded bg-background border-border text-accent focus:ring-accent/20"> Priority Support
                    </label>
                    <label class="flex items-center gap-2.5 text-xs text-text cursor-pointer select-none">
                        <input type="checkbox" id="plan-ff-seo" class="rounded bg-background border-border text-accent focus:ring-accent/20"> SEO Optimization Suite
                    </label>
                    <label class="flex items-center gap-2.5 text-xs text-text cursor-pointer select-none">
                        <input type="checkbox" id="plan-ff-loc" class="rounded bg-background border-border text-accent focus:ring-accent/20"> Multi-language Localization
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-6 border-t border-border">
                <button type="button" class="border border-border text-muted hover:text-text hover:bg-white/5 px-4 py-2.5 rounded-xl font-medium text-xs transition" onclick="closePlanModal()">Cancel</button>
                <button type="submit" class="bg-accent hover:bg-accent/80 text-background px-5 py-2.5 rounded-xl font-semibold text-xs transition cyber-glow-emerald">Save Plan</button>
            </div>
        </form>
    </div>
</div>
