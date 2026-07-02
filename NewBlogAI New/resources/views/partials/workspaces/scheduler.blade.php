                <!-- 10. PUBLISHING SCHEDULER WORKSPACE -->
                <div id="node-scheduler" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Publishing Scheduler</h2>
                            <p class="text-xs text-muted">Orchestrate temporal publishing intervals, manage cron slots, and track upcoming releases.</p>
                        </div>
                        <div class="flex gap-2">
                            <!-- Switcher between Queue & Calendar -->
                            <div class="bg-surface p-1 border border-border rounded-xl flex items-center gap-1">
                                <button onclick="toggleSchedulerView('queue')" id="scheduler-view-queue-btn" class="p-1.5 rounded-lg bg-white/5 text-accent flex items-center justify-center transition">
                                    <span class="material-symbols-outlined text-sm">list</span>
                                </button>
                                <button onclick="toggleSchedulerView('calendar')" id="scheduler-view-calendar-btn" class="p-1.5 rounded-lg text-muted hover:text-text flex items-center justify-center transition">
                                    <span class="material-symbols-outlined text-sm">calendar_today</span>
                                </button>
                            </div>
                            
                            <button onclick="triggerManualSchedulerRelease()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                                <span class="material-symbols-outlined text-sm font-bold">send</span> Force Sync Release
                            </button>
                        </div>
                    </div>

                    <!-- Telemetry KPI row -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Queue Health</p>
                            <h3 class="text-3xl font-display font-bold text-accent">Optimal</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span> Telemetry Online
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Scheduled Runs</p>
                            <h3 class="text-3xl font-display font-bold">142 Jobs</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Across next 7 days</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Avg Posting Delay</p>
                            <h3 class="text-3xl font-display font-bold text-accent">14.2s</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent">Queue-to-API latency</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Failed Releases</p>
                            <h3 class="text-3xl font-display font-bold text-danger">0</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Zero errors in 48h</div>
                        </div>
                    </div>

                    <!-- Queue List View -->
                    <div id="scheduler-queue-view" class="glass-surface rounded-2xl overflow-hidden border border-border">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase">
                                    <th class="p-3 pl-5">Job ID</th>
                                    <th class="p-3">Target Domain</th>
                                    <th class="p-3">Topic Cluster</th>
                                    <th class="p-3">Planned Release</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3 text-right pr-5">Operations</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border text-xs font-mono" id="scheduler-jobs-table-body">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Calendar View -->
                    <div id="scheduler-calendar-view" class="glass-surface rounded-2xl p-5 border border-border hidden space-y-4">
                        <div class="flex justify-between items-center pb-2 border-b border-border">
                            <h4 class="text-xs font-mono uppercase tracking-widest text-muted">July 2026</h4>
                            <span class="text-[10px] font-mono text-accent">5 Scheduled Events</span>
                        </div>
                        
                        <!-- 7 Column Day Headers -->
                        <div class="grid grid-cols-7 gap-2 text-center text-[10px] font-mono text-muted uppercase">
                            <div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div>
                        </div>

                        <!-- 31 Day Grid -->
                        <div class="grid grid-cols-7 gap-2 text-center text-xs font-mono">
                            <!-- First week blank offset -->
                            <div class="p-4 bg-transparent border border-transparent"></div>
                            <div class="p-4 bg-transparent border border-transparent"></div>
                            <!-- Day 1 -->
                            <div class="p-4 bg-white/5 border border-accent rounded-xl relative hover:border-accent transition group cursor-pointer">
                                <span class="text-text font-bold">1</span>
                                <span class="absolute bottom-1.5 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span>
                            </div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">2</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">3</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">4</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">5</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">6</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">7</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">8</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">9</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">10</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">11</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">12</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">13</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">14</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">15</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">16</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">17</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">18</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">19</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">20</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">21</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">22</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">23</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">24</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">25</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">26</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">27</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">28</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">29</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">30</div>
                            <div class="p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer">31</div>
                        </div>
                    </div>
                </div>

