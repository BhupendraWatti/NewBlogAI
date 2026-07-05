    <!-- PERSISTENT LEFT SIDEBAR NAVIGATION -->
    <aside class="w-64 bg-sidebar border-r border-border flex flex-col py-6 px-4 shrink-0 z-40 h-full overflow-hidden">

        <!-- Brand Identity - always visible at top -->
        <div class="flex items-center gap-3 px-2 shrink-0">
            <div class="w-9 h-9 rounded-xl bg-accent flex items-center justify-center text-background cyber-glow-emerald">
                <span class="material-symbols-outlined font-bold text-lg">terminal</span>
            </div>
            <div>
                <h1 class="font-display font-bold text-lg leading-tight tracking-tight">Automation OS</h1>
                <p class="text-[11px] text-muted tracking-widest uppercase font-semibold">NewsBlogify AI</p>
            </div>
        </div>

        <!-- Navigation Links - scrollable, fills remaining height -->
        <div class="flex-1 overflow-y-auto custom-scrollbar mt-8 min-h-0">
            <nav class="space-y-4" id="sidebar-menu">
                
                <!-- Dashboard (Non-collapsible main item) -->
                <div class="px-1">
                    <button onclick="switchWorkspace('dashboard')" data-node="dashboard" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                        <span class="material-symbols-outlined text-lg">grid_view</span>
                        Dashboard
                    </button>
                </div>

                <!-- Content Group -->
                <details class="group/menu" open>
                    <summary class="flex items-center justify-between px-3 text-[10px] font-bold text-muted uppercase tracking-widest cursor-pointer select-none outline-none list-none [&::-webkit-details-marker]:hidden">
                        <span>Content</span>
                        <span class="material-symbols-outlined text-xs transition-transform duration-200 group-open/menu:rotate-180 text-muted/60">expand_more</span>
                    </summary>
                    <div class="space-y-1 mt-2 pl-1">
                        <button onclick="switchWorkspace('topics')" data-node="topics" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                            <span class="material-symbols-outlined text-lg">topic</span>
                            Topics
                        </button>
                        <button onclick="switchWorkspace('pipeline')" data-node="pipeline" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                            <span class="material-symbols-outlined text-lg">route</span>
                            Content Pipeline
                        </button>
                        <button onclick="switchWorkspace('fleet')" data-node="fleet" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                            <span class="material-symbols-outlined text-lg">article</span>
                            Generated Articles
                        </button>
                        <button onclick="switchWorkspace('prompts')" data-node="prompts" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                            <span class="material-symbols-outlined text-lg">book</span>
                            Prompt Library
                        </button>
                    </div>
                </details>

                <!-- AI Group -->
                <details class="group/menu" open>
                    <summary class="flex items-center justify-between px-3 text-[10px] font-bold text-muted uppercase tracking-widest cursor-pointer select-none outline-none list-none [&::-webkit-details-marker]:hidden">
                        <span>AI</span>
                        <span class="material-symbols-outlined text-xs transition-transform duration-200 group-open/menu:rotate-180 text-muted/60">expand_more</span>
                    </summary>
                    <div class="space-y-1 mt-2 pl-1">
                        <button onclick="switchWorkspace('providers')" data-node="providers" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                            <span class="material-symbols-outlined text-lg">neurology</span>
                            AI Providers
                        </button>
                        <button onclick="switchWorkspace('media')" data-node="media" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                            <span class="material-symbols-outlined text-lg">photo_library</span>
                            Image Generation
                        </button>
                    </div>
                </details>

                <!-- Publishing Group -->
                <details class="group/menu" open>
                    <summary class="flex items-center justify-between px-3 text-[10px] font-bold text-muted uppercase tracking-widest cursor-pointer select-none outline-none list-none [&::-webkit-details-marker]:hidden">
                        <span>Publishing</span>
                        <span class="material-symbols-outlined text-xs transition-transform duration-200 group-open/menu:rotate-180 text-muted/60">expand_more</span>
                    </summary>
                    <div class="space-y-1 mt-2 pl-1">
                        <button onclick="switchWorkspace('sites')" data-node="sites" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                            <span class="material-symbols-outlined text-lg">language</span>
                            Websites
                        </button>
                        <button onclick="switchWorkspace('scheduler')" data-node="scheduler" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                            <span class="material-symbols-outlined text-lg">calendar_month</span>
                            Publishing Queue
                        </button>
                    </div>
                </details>

                <!-- Analytics Group -->
                <details class="group/menu" open>
                    <summary class="flex items-center justify-between px-3 text-[10px] font-bold text-muted uppercase tracking-widest cursor-pointer select-none outline-none list-none [&::-webkit-details-marker]:hidden">
                        <span>Analytics</span>
                        <span class="material-symbols-outlined text-xs transition-transform duration-200 group-open/menu:rotate-180 text-muted/60">expand_more</span>
                    </summary>
                    <div class="space-y-1 mt-2 pl-1">
                        <button onclick="switchWorkspace('analytics')" data-node="analytics" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                            <span class="material-symbols-outlined text-lg">insert_chart</span>
                            Analytics
                        </button>
                        <button onclick="switchWorkspace('billing')" data-node="billing" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                            <span class="material-symbols-outlined text-lg">bar_chart</span>
                            Usage
                        </button>
                    </div>
                </details>

                <!-- Administration Group -->
                <details class="group/menu" open>
                    <summary class="flex items-center justify-between px-3 text-[10px] font-bold text-muted uppercase tracking-widest cursor-pointer select-none outline-none list-none [&::-webkit-details-marker]:hidden">
                        <span>Administration</span>
                        <span class="material-symbols-outlined text-xs transition-transform duration-200 group-open/menu:rotate-180 text-muted/60">expand_more</span>
                    </summary>
                    <div class="space-y-1 mt-2 pl-1">
                        <button onclick="switchWorkspace('roles')" data-node="roles" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                            <span class="material-symbols-outlined text-lg">admin_panel_settings</span>
                            Users &amp; Roles
                        </button>
                        <!-- Reusing the billing node for administration billing -->
                        <button onclick="switchWorkspace('billing')" data-node="billing" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                            <span class="material-symbols-outlined text-lg">credit_card</span>
                            Billing
                        </button>
                    </div>
                </details>

                <!-- System Group -->
                <details class="group/menu" open>
                    <summary class="flex items-center justify-between px-3 text-[10px] font-bold text-muted uppercase tracking-widest cursor-pointer select-none outline-none list-none [&::-webkit-details-marker]:hidden">
                        <span>System</span>
                        <span class="material-symbols-outlined text-xs transition-transform duration-200 group-open/menu:rotate-180 text-muted/60">expand_more</span>
                    </summary>
                    <div class="space-y-1 mt-2 pl-1">
                        <button onclick="switchWorkspace('notifications')" data-node="notifications" class="w-full flex items-center justify-between text-left px-3 py-2 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-lg">notifications</span>
                                <span>Notifications</span>
                            </div>
                            <span id="sidebar-notifications-count" class="hidden px-1.5 py-0.5 rounded-full bg-rose-500 text-white text-[9px] font-mono font-bold leading-none animate-pulse">0</span>
                        </button>
                        <button onclick="switchWorkspace('audit')" data-node="audit" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                            <span class="material-symbols-outlined text-lg">history</span>
                            Audit Logs
                        </button>
                        <button onclick="switchWorkspace('settings')" data-node="settings" class="w-full flex items-center gap-3 text-left px-3 py-2 rounded-xl font-medium text-sm transition-all duration-200 text-muted hover:text-text hover:bg-white/5">
                            <span class="material-symbols-outlined text-lg">settings</span>
                            System Settings
                        </button>
                    </div>
                </details>
            </nav>
        </div>

        <!-- Sidebar Footer - always pinned at bottom, never scrolls away -->
        <div class="pt-4 mt-2 border-t border-border space-y-3 shrink-0">
            <div class="flex items-center gap-3 px-2">
                <div class="w-8 h-8 rounded-full bg-surface border border-border flex items-center justify-center text-accent text-xs font-semibold">
                    SA
                </div>
                <div>
                    <p class="text-xs font-medium">Super Admin</p>
                    <p class="text-[10px] text-muted">Active Node</p>
                </div>
            </div>
            <p class="text-[10px] text-muted px-2 font-mono">v2.4.1-stable</p>
        </div>
    </aside>