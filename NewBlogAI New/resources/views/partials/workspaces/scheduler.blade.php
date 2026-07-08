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
                            
                            <button onclick="openScheduleAddModal()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                                <span class="material-symbols-outlined text-sm font-bold">add</span> Create Schedule
                            </button>
                            <button onclick="triggerManualSchedulerRelease(this)" class="bg-surface hover:bg-white/5 border border-border text-text font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-sm">send</span> Force Sync Release
                            </button>
                        </div>
                    </div>

                    <!-- Telemetry KPI row -->
                    <div class="grid grid-cols-4 gap-4">
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Queue Health</p>
                            <h3 class="text-3xl font-display font-bold text-accent" id="scheduler-kpi-health">Optimal</h3>
                            <div class="mt-2 text-[10px] font-mono text-accent flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span> Telemetry Online
                            </div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Active Schedules</p>
                            <h3 class="text-3xl font-display font-bold" id="scheduler-kpi-count">0 Runs</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Across connected domains</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Next Release</p>
                            <h3 class="text-3xl font-display font-bold text-accent" id="scheduler-kpi-next">—</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Scheduled release target</div>
                        </div>
                        <div class="glass-surface rounded-2xl p-5 relative overflow-hidden group transition hover:border-accent">
                            <p class="text-[10px] font-mono text-muted uppercase tracking-widest mb-1">Total Logs</p>
                            <h3 class="text-3xl font-display font-bold text-accent" id="scheduler-kpi-logs">0 Logs</h3>
                            <div class="mt-2 text-[10px] font-mono text-muted">Queue runs history</div>
                        </div>
                    </div>

                    <!-- Queue/Schedule List View -->
                    <div id="scheduler-queue-view" class="glass-surface rounded-2xl overflow-hidden border border-border">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface/50 border-b border-border text-muted font-mono text-[10px] uppercase">
                                    <th class="p-3 pl-5">Schedule Name</th>
                                    <th class="p-3">Target Domain</th>
                                    <th class="p-3">Frequency</th>
                                    <th class="p-3">Next Scheduled Run</th>
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
                            <h4 class="text-xs font-mono uppercase tracking-widest text-muted" id="calendar-month-title">July 2026</h4>
                            <span class="text-[10px] font-mono text-accent" id="calendar-events-count">0 Scheduled Events</span>
                        </div>
                        
                        <!-- 7 Column Day Headers -->
                        <div class="grid grid-cols-7 gap-2 text-center text-[10px] font-mono text-muted uppercase">
                            <div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div>
                        </div>

                        <!-- Dynamic Calendar Days Grid -->
                        <div class="grid grid-cols-7 gap-2 text-center text-xs font-mono" id="calendar-days-grid">
                            <!-- Populated dynamically -->
                        </div>
                    </div>
                </div>

                <!-- Create/Edit Schedule Modal -->
                <div class="modal-overlay" id="schedule-modal">
                    <div class="modal-container">
                        <div class="flex justify-between items-center mb-6 border-b border-outline-variant pb-3">
                            <h3 class="text-lg font-semibold font-headline-md text-primary" id="schedule-modal-title">Create Publishing Schedule</h3>
                            <button class="text-outline hover:text-on-surface text-xl" onclick="closeScheduleModal()">&times;</button>
                        </div>
                        <form id="schedule-form" onsubmit="saveSchedule(event)">
                            <input type="hidden" id="schedule-id">
                            
                            <div class="mb-4">
                                <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="schedule-name">Schedule Name</label>
                                <input type="text" id="schedule-name" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary" placeholder="e.g. Daily Tech Insights Post" required>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="schedule-site-id">Target Site</label>
                                    <select id="schedule-site-id" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary" onchange="populateSchedulePipelines(this.value)" required>
                                        <!-- Populated dynamically -->
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="schedule-pipeline-id">Content Pipeline</label>
                                    <select id="schedule-pipeline-id" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary">
                                        <option value="">No Pipeline (Sync Only)</option>
                                        <!-- Populated dynamically -->
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4 mb-4">
                                <div>
                                    <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="schedule-frequency">Frequency</label>
                                    <select id="schedule-frequency" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary" onchange="toggleScheduleDaysField(this.value)">
                                        <option value="daily" selected>Daily</option>
                                        <option value="hourly">Hourly</option>
                                        <option value="twice_daily">Twice Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="schedule-time-of-day">Time of Day (UTC)</label>
                                    <input type="text" id="schedule-time-of-day" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary" placeholder="14:30" value="09:00">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2" for="schedule-timezone">Timezone</label>
                                    <input type="text" id="schedule-timezone" class="w-full bg-[#071018] border border-outline-variant rounded-lg px-4 py-2.5 text-text focus:outline-none focus:border-primary" placeholder="UTC" value="UTC" required>
                                </div>
                            </div>

                            <div class="mb-4 hidden" id="schedule-days-container">
                                <label class="block text-xs font-semibold text-outline uppercase tracking-wider mb-2">Days of Week</label>
                                <div class="grid grid-cols-4 gap-2 text-xs font-mono">
                                    <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="days[]" value="monday"> Mon</label>
                                    <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="days[]" value="tuesday"> Tue</label>
                                    <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="days[]" value="wednesday"> Wed</label>
                                    <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="days[]" value="thursday"> Thu</label>
                                    <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="days[]" value="friday"> Fri</label>
                                    <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="days[]" value="saturday"> Sat</label>
                                    <label class="flex items-center gap-1.5 cursor-pointer"><input type="checkbox" name="days[]" value="sunday"> Sun</label>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-semibold text-outline uppercase tracking-wider">
                                    <input type="checkbox" id="schedule-active" checked>
                                    <span>Schedule Active</span>
                                </label>
                            </div>

                            <div class="flex justify-end gap-3 mt-6 border-t border-outline-variant pt-4">
                                <button type="button" class="border border-outline-variant text-outline hover:text-on-surface hover:bg-surface-container-high px-4 py-2.5 rounded-lg font-medium" onclick="closeScheduleModal()">Cancel</button>
                                <button type="submit" class="bg-accent text-background hover:bg-accent/80 px-5 py-2.5 rounded-lg font-semibold transition-colors">Save Schedule</button>
                            </div>
                        </form>
                    </div>
                </div>
