        <!-- HEADER PANEL -->
        <header class="h-14 border-b border-border px-6 flex justify-between items-center shrink-0">
            <!-- Breadcrumbs -->
            <div class="flex items-center gap-2 text-xs font-mono text-muted">
                <span class="hover:text-text cursor-pointer">Automation OS</span>
                <span class="material-symbols-outlined text-xs">chevron_right</span>
                <span id="breadcrumb-active" class="text-secondary font-medium">Overview</span>
            </div>

            <!-- Quick Controls -->
            <div class="flex items-center gap-4">
                <!-- Search & Command Palette Hook -->
                <div class="relative w-72 focus-within:w-80 transition-all duration-300">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted text-lg">search</span>
                    <input id="global-search" onkeydown="handleSearchShortcut(event)" class="w-full bg-surface border border-border rounded-xl py-1.5 pl-10 pr-8 text-xs font-mono text-text placeholder-muted focus:outline-none focus:border-accent focus:ring-0" placeholder="Search commands... (Ctrl+K)" type="text"/>
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[9px] font-mono text-muted border border-border px-1 rounded">⌘K</span>
                </div>

                <!-- Theme Toggle Button -->
                <button onclick="toggleTheme()" class="p-2 text-muted hover:text-text bg-white/5 rounded-xl border border-border transition flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg" id="theme-toggle-icon">light_mode</span>
                </button>

                <div class="h-5 w-px bg-border"></div>

                <!-- Notifications Button -->
                <button onclick="switchWorkspace('notifications')" class="p-2 text-muted hover:text-text bg-white/5 rounded-xl border border-border transition relative" aria-label="Notifications">
                    <span class="material-symbols-outlined text-lg">notifications</span>
                    <span id="header-notification-badge" class="absolute top-1.5 right-1.5 w-1.5 h-1.5 bg-accent rounded-full hidden"></span>
                    <span id="header-notification-badge-ping" class="absolute top-1.5 right-1.5 w-1.5 h-1.5 bg-accent rounded-full animate-ping hidden"></span>
                </button>
            </div>
        </header>
