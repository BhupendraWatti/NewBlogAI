        <!-- HORIZONTAL WORKSPACE NAVIGATION TABS -->
        <section class="h-11 border-b border-border px-6 bg-surface/30 flex items-center justify-between shrink-0">
            <div class="flex gap-1 h-full" id="workspace-tabs">
                <button onclick="switchTab('overview')" data-tab="overview" class="px-4 h-full text-xs font-medium text-muted hover:text-text border-b-2 border-transparent transition-all flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">visibility</span> Overview
                </button>
                <button onclick="switchTab('config')" data-tab="config" class="px-4 h-full text-xs font-medium text-muted hover:text-text border-b-2 border-transparent transition-all flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">tune</span> Configuration
                </button>
                <button onclick="switchTab('history')" data-tab="history" class="px-4 h-full text-xs font-medium text-muted hover:text-text border-b-2 border-transparent transition-all flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">history</span> History
                </button>
                <button onclick="switchTab('logs')" data-tab="logs" class="px-4 h-full text-xs font-medium text-muted hover:text-text border-b-2 border-transparent transition-all flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">code</span> Logs &amp; Events
                </button>
                <button onclick="switchTab('settings')" data-tab="settings" class="px-4 h-full text-xs font-medium text-muted hover:text-text border-b-2 border-transparent transition-all flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm">settings</span> Settings
                </button>
            </div>
            
            <div class="text-[10px] text-muted font-mono flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span> SYSTEM: ONLINE
            </div>
        </section>
