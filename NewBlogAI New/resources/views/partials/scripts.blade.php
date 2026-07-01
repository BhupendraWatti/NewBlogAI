    <script>
        let currentWorkspace = "{{ $activeView ?? 'dashboard' }}";
        let currentTab = 'overview';
        let wizardStep = 1;
        let wizardType = 'customer';

        // Keyboard Shortcuts
        document.addEventListener('keydown', function(event) {
            // Ctrl+K or Cmd+K focuses search
            if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
                event.preventDefault();
                document.getElementById('global-search').focus();
            }
        });

        function handleSearchShortcut(e) {
            if (e.key === 'Escape') {
                e.target.blur();
            }
        }

        // Initialize view state
        window.addEventListener('DOMContentLoaded', () => {
            switchWorkspace(currentWorkspace);
            switchTab(currentTab);
        });

        // Workspace Node Router
        function switchWorkspace(node) {
            currentWorkspace = node;
            
            // Hide all workspaces
            document.querySelectorAll('.workspace-pane').forEach(el => {
                el.classList.add('hidden');
            });

            // Show selected node
            const activeNode = document.getElementById('node-' + node);
            if (activeNode) {
                activeNode.classList.remove('hidden');
            }

            // Highlight Active Sidebar Menu Option
            document.querySelectorAll('#sidebar-menu button').forEach(btn => {
                const isSelected = btn.getAttribute('data-node') === node;
                if (isSelected) {
                    btn.classList.add('text-accent', 'bg-white/5', 'cyber-glow-emerald');
                    btn.classList.remove('text-muted');
                } else {
                    btn.classList.remove('text-accent', 'bg-white/5', 'cyber-glow-emerald');
                    btn.classList.add('text-muted');
                }
            });

            // Update Breadcrumb & URL (pushState)
            document.getElementById('breadcrumb-active').innerText = node.toUpperCase();
            window.history.pushState(null, '', node === 'dashboard' ? '/' : '/' + node);

            // Close context inspector upon navigation
            closeInspector();
        }

        // Tab Switcher inside Node Workspace
        function switchTab(tab) {
            currentTab = tab;
            
            document.querySelectorAll('#workspace-tabs button').forEach(btn => {
                const isSelected = btn.getAttribute('data-tab') === tab;
                if (isSelected) {
                    btn.classList.add('text-secondary', 'border-secondary', 'active-tab-glow');
                    btn.classList.remove('text-muted', 'border-transparent');
                } else {
                    btn.classList.remove('text-secondary', 'border-secondary', 'active-tab-glow');
                    btn.classList.add('text-muted', 'border-transparent');
                }
            });

            // Simulate high-density telemetry reloading
            if (tab === 'logs') {
                console.log("Telemetry logs reconnected.");
            }
        }

        // Dynamic Floating Context Inspector Panel
        function inspectElement(type, title, status, priority, owner) {
            const panel = document.getElementById('inspector-panel');
            panel.classList.remove('hidden', 'translate-x-full');
            
            // Set properties
            document.getElementById('inspector-type').innerText = type.toUpperCase() + ' NODE';
            document.getElementById('inspector-title').innerText = title;
            
            if (type === 'topic') {
                document.getElementById('inspector-priority').innerText = 'SEO Rating: ' + priority;
                document.getElementById('inspector-owner').innerText = 'LLM Model: ' + owner;
            } else if (type === 'site') {
                document.getElementById('inspector-priority').innerText = 'Core Version: ' + priority;
                document.getElementById('inspector-owner').innerText = 'API Key ID: ' + owner;
            } else if (type === 'workflow') {
                document.getElementById('inspector-priority').innerText = 'Telemetry: ' + priority;
                document.getElementById('inspector-owner').innerText = 'Trigger Rule: ' + owner;
            } else if (type === 'job') {
                document.getElementById('inspector-priority').innerText = 'Planned Release: ' + priority;
                document.getElementById('inspector-owner').innerText = 'Target Domain: ' + owner;
            } else if (type === 'provider') {
                document.getElementById('inspector-priority').innerText = 'Context limit: ' + priority;
                document.getElementById('inspector-owner').innerText = 'Authorized role: ' + owner;
            } else if (type === 'media') {
                document.getElementById('inspector-priority').innerText = 'Aspect Ratio: ' + priority;
                document.getElementById('inspector-owner').innerText = 'Inference Engine: ' + owner;
            } else if (type === 'seo') {
                document.getElementById('inspector-priority').innerText = 'SEO Grade: ' + priority;
                document.getElementById('inspector-owner').innerText = 'Target keyword: ' + owner;
            } else if (type === 'analytics') {
                document.getElementById('inspector-priority').innerText = 'Metric value: ' + priority;
                document.getElementById('inspector-owner').innerText = 'Telemetry type: ' + owner;
            } else if (type === 'notifications') {
                document.getElementById('inspector-priority').innerText = 'Severity scope: ' + priority;
                document.getElementById('inspector-owner').innerText = 'Timestamp log: ' + owner;
            } else if (type === 'user') {
                document.getElementById('inspector-priority').innerText = 'Access scope: ' + priority;
                document.getElementById('inspector-owner').innerText = 'Auth permissions: ' + owner;
            } else if (type === 'billing') {
                document.getElementById('inspector-priority').innerText = 'Accrued usage: ' + priority;
                document.getElementById('inspector-owner').innerText = 'Rate details: ' + owner;
            } else if (type === 'settings') {
                document.getElementById('inspector-priority').innerText = 'Setting value: ' + priority;
                document.getElementById('inspector-owner').innerText = 'Variable type: ' + owner;
            } else if (type === 'audit') {
                document.getElementById('inspector-priority').innerText = 'Operator name: ' + priority;
                document.getElementById('inspector-owner').innerText = 'IP Address: ' + owner;
            } else {
                document.getElementById('inspector-priority').innerText = priority;
                document.getElementById('inspector-owner').innerText = owner;
            }
            
            const statusBadge = document.getElementById('inspector-status');
            statusBadge.innerText = status;
            
            // Adjust colors based on status
            statusBadge.className = 'ml-2 px-2 py-0.5 rounded text-[10px] border ';
            if (status === 'active' || status === 'online') {
                statusBadge.classList.add('bg-success/20', 'text-success', 'border-success/30');
            } else if (status === 'trial' || status === 'paused') {
                statusBadge.classList.add('bg-warning/20', 'text-warning', 'border-warning/30');
            } else {
                statusBadge.classList.add('bg-danger/20', 'text-danger', 'border-danger/30');
            }
        }

        // Close context inspector panel
        function closeInspector() {
            const panel = document.getElementById('inspector-panel');
            panel.classList.add('translate-x-full');
            setTimeout(() => {
                panel.classList.add('hidden');
            }, 300);
        }

        // Toggle Grid vs Table View for Topics
        function toggleTopicsView(viewType) {
            const tableView = document.getElementById('topics-table-view');
            const gridView = document.getElementById('topics-grid-view');
            const tableBtn = document.getElementById('topics-view-table-btn');
            const gridBtn = document.getElementById('topics-view-grid-btn');

            if (viewType === 'table') {
                tableView.classList.remove('hidden');
                gridView.classList.add('hidden');
                tableBtn.classList.add('bg-white/5', 'text-accent');
                tableBtn.classList.remove('text-muted');
                gridBtn.classList.remove('bg-white/5', 'text-accent');
                gridBtn.classList.add('text-muted');
            } else {
                tableView.classList.add('hidden');
                gridView.classList.remove('hidden');
                gridBtn.classList.add('bg-white/5', 'text-accent');
                gridBtn.classList.remove('text-muted');
                tableBtn.classList.remove('bg-white/5', 'text-accent');
                tableBtn.classList.add('text-muted');
            }
        }

        // Dedicated Creation Workspace Step transitions
        function launchCreationWizard(type) {
            wizardType = type;
            wizardStep = 1;
            
            document.getElementById('wizard-title').innerText = "Register " + type.charAt(0).toUpperCase() + type.slice(1) + " Pipeline";
            
            // Switch Workspace View to creation pane
            document.querySelectorAll('.workspace-pane').forEach(el => el.classList.add('hidden'));
            document.getElementById('node-creation').classList.remove('hidden');

            renderWizardStep();
        }

        function cancelCreation() {
            switchWorkspace('dashboard');
        }

        function renderWizardStep() {
            document.querySelectorAll('.wizard-pane').forEach(el => el.classList.add('hidden'));
            document.getElementById('wizard-step-' + wizardStep).classList.remove('hidden');

            // Set indicators
            for (let i = 1; i <= 4; i++) {
                const ind = document.getElementById('step-ind-' + i);
                if (i === wizardStep) {
                    ind.className = 'text-accent font-bold';
                } else if (i < wizardStep) {
                    ind.className = 'text-secondary';
                } else {
                    ind.className = 'text-muted';
                }
            }

            // Adjust back button visibility
            const backBtn = document.getElementById('wizard-back-btn');
            if (wizardStep === 1) {
                backBtn.classList.add('hidden');
            } else {
                backBtn.classList.remove('hidden');
            }

            // Set text on Next button for final step
            const nextBtn = document.getElementById('wizard-next-btn');
            if (wizardStep === 4) {
                nextBtn.innerText = "Commit Pipeline";
            } else {
                nextBtn.innerText = "Next";
            }
        }

        function wizardNext() {
            if (wizardStep < 4) {
                wizardStep++;
                renderWizardStep();
            } else {
                // Done!
                alert(wizardType.charAt(0).toUpperCase() + wizardType.slice(1) + " pipeline committed successfully!");
                if (wizardType === 'customer') {
                    switchWorkspace('customers');
                } else if (wizardType === 'topic') {
                    switchWorkspace('topics');
                } else {
                    switchWorkspace('sites');
                }
            }
        }

        function wizardBack() {
            if (wizardStep > 1) {
                wizardStep--;
                renderWizardStep();
            }
        }

        // Prompt Workspace Sub-tab switching
        function switchPromptSubTab(tab) {
            document.querySelectorAll('.prompt-tab-view').forEach(el => {
                el.classList.add('hidden');
            });
            document.getElementById('prompt-pane-' + tab).classList.remove('hidden');

            document.querySelectorAll('#prompt-sub-tabs text, #prompt-sub-tabs button').forEach(btn => {
                const id = btn.getAttribute('id');
                if (id === 'prompt-tab-' + tab) {
                    btn.classList.add('text-accent', 'border-accent');
                    btn.classList.remove('text-muted', 'border-transparent');
                } else {
                    btn.classList.remove('text-accent', 'border-accent');
                    btn.classList.add('text-muted', 'border-transparent');
                }
            });
        }

        // Prompt Template selector
        let activePromptId = 'promt_001';
        function selectPromptTemplate(id, name, category, version, status) {
            activePromptId = id;
            
            // Highlight selected item in list
            document.querySelectorAll('.prompt-list-item').forEach(item => {
                item.classList.remove('bg-white/5', 'border-accent');
                item.classList.add('bg-transparent', 'border-border');
            });
            const selectedItem = document.getElementById('prompt-item-' + id);
            if (selectedItem) {
                selectedItem.classList.remove('bg-transparent', 'border-border');
                selectedItem.classList.add('bg-white/5', 'border-accent');
            }

            document.getElementById('prompt-editor-id').innerText = "Active: " + id;
            document.getElementById('prompt-edit-name').value = name;
            document.getElementById('prompt-edit-category').value = category;
            document.getElementById('prompt-edit-version').value = version;
            document.getElementById('prompt-edit-status').value = status;
            
            let text = "";
            const ob = '{' + '{', cb = '}' + '}';
            if (id === 'promt_001') {
                text = "You are a senior tech reporter. Summarize the following news details regarding " + ob + "topic" + cb + " in a professional, engaging format with key bullet points. Target keyword: " + ob + "keyword" + cb + ".";
            } else if (id === 'promt_002') {
                text = "Compose a structured bulletin highlighting key developments in " + ob + "topic" + cb + ". Use tone: " + ob + "tone" + cb + ". Language should target " + ob + "language" + cb + ".";
            } else {
                text = "Analyze financial reports and output a trend summary for " + ob + "topic" + cb + ". Extract core indicators and model predictions.";
            }
            document.getElementById('prompt-editor-textarea').value = text;
        }

        // Live edit local sync logic
        function updatePromptField(field) {
            const listItem = document.getElementById('prompt-item-' + activePromptId);
            if (!listItem) return;

            if (field === 'name') {
                const val = document.getElementById('prompt-edit-name').value;
                listItem.querySelector('p').innerText = val;
            } else if (field === 'category') {
                const val = document.getElementById('prompt-edit-category').value;
                listItem.querySelector('.font-mono div span:first-child').innerText = 'Category: ' + val;
            } else if (field === 'version') {
                const val = document.getElementById('prompt-edit-version').value;
                listItem.querySelector('span[class*="font-mono"]').innerText = val;
            } else if (field === 'status') {
                const val = document.getElementById('prompt-edit-status').value;
                const statusSpan = listItem.querySelector('.font-mono div span:last-child');
                if (statusSpan) {
                    statusSpan.innerText = val;
                    if (val === 'active') {
                        statusSpan.className = 'text-success';
                    } else {
                        statusSpan.className = 'text-warning';
                    }
                }
            }
        }

        function saveActivePrompt() {
            // TODO: POST /api/v1/prompts with name, category, version, status, content
            alert('Prompt template saved successfully in the Library!');
        }


        // Simulate live testing outputs
        function runPromptTestSimulation() {
            const outWindow = document.getElementById('prompt-test-output-window');
            outWindow.innerText = "Connecting to pipeline stub...\n";
            
            let lines = [
                "Sending test payload...",
                "Running validation checks...",
                "Received model completion response:\n",
                "### IBM releases new 433-qubit Osprey processor.",
                "- Highlights massive quantum computing performance improvements.",
                "- Increases noise protection structures.",
                "- Employs standard multi-layered layout architectures."
            ];
            
            let i = 0;
            let timer = setInterval(() => {
                if (i < lines.length) {
                    outWindow.innerText += lines[i] + "\n";
                    i++;
                } else {
                    clearInterval(timer);
                }
            }, 300);
        }

        // Simulate real-time pipeline run loops
        function simulatePipelineRun() {
            const progressBar = document.getElementById('pipeline-progress-bar');
            const rowStatus = document.getElementById('pipeline-row-status');
            const stageIcon = document.getElementById('pipeline-stage-gen-icon');
            const stageStatus = document.getElementById('pipeline-stage-gen-status');

            if (!progressBar) return;

            progressBar.style.width = '45%';
            rowStatus.innerText = 'processing';
            rowStatus.className = 'px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[9px] animate-pulse';
            stageIcon.className = 'material-symbols-outlined text-warning bg-warning/10 p-2.5 rounded-xl border border-warning/30 animate-pulse';
            stageIcon.innerText = 'psychology';
            stageStatus.innerText = 'Running...';
            stageStatus.className = 'text-[8px] font-mono text-warning';

            setTimeout(() => {
                progressBar.style.width = '100%';
                rowStatus.innerText = 'completed';
                rowStatus.className = 'px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]';
                stageIcon.className = 'material-symbols-outlined text-success bg-success/10 p-2.5 rounded-xl border border-success/30';
                stageIcon.innerText = 'task_alt';
                stageStatus.innerText = 'Complete';
                stageStatus.className = 'text-[8px] font-mono text-success';
                alert("AI content generation pipeline run completed successfully!");
            }, 3000);
        }

        // Toggle Scheduler Queue vs Calendar view
        function toggleSchedulerView(viewType) {
            const queueView = document.getElementById('scheduler-queue-view');
            const calendarView = document.getElementById('scheduler-calendar-view');
            const queueBtn = document.getElementById('scheduler-view-queue-btn');
            const calendarBtn = document.getElementById('scheduler-view-calendar-btn');

            if (viewType === 'queue') {
                queueView.classList.remove('hidden');
                calendarView.classList.add('hidden');
                queueBtn.classList.add('bg-white/5', 'text-accent');
                queueBtn.classList.remove('text-muted');
                calendarBtn.classList.remove('bg-white/5', 'text-accent');
                calendarBtn.classList.add('text-muted');
            } else {
                queueView.classList.add('hidden');
                calendarView.classList.remove('hidden');
                calendarBtn.classList.add('bg-white/5', 'text-accent');
                calendarBtn.classList.remove('text-muted');
                queueBtn.classList.remove('bg-white/5', 'text-accent');
                queueBtn.classList.add('text-muted');
            }
        }

        // Simulate Manual Time Release for Scheduler Job
        function triggerManualSchedulerRelease() {
            const jobTime = document.getElementById('scheduler-job-time');
            const jobStatus = document.getElementById('scheduler-job-status');

            if (!jobTime) return;

            jobTime.innerText = "Releasing...";
            jobStatus.innerText = "publishing";
            jobStatus.className = "px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[9px] animate-pulse";

            setTimeout(() => {
                jobTime.innerText = "JUST NOW";
                jobStatus.innerText = "completed";
                jobStatus.className = "px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]";
                alert("Job successfully published to WordPress destination site techcrunch.com!");
            }, 2000);
        }



        // Simulate SEO Audit / Optimization run loop
        function triggerSEOSweepSimulation() {
            const avgScore = document.getElementById('seo-avg-score');
            const missingAlts = document.getElementById('seo-missing-alts');
            const altTechRow = document.getElementById('seo-alt-tech-row');

            if (!avgScore) return;

            avgScore.innerText = "Scanning...";
            avgScore.className = "text-3xl font-display font-bold text-warning animate-pulse";

            setTimeout(() => {
                avgScore.innerText = "96 / 100";
                avgScore.className = "text-3xl font-display font-bold text-accent";
                missingAlts.innerText = "0";
                missingAlts.className = "text-3xl font-display font-bold text-accent";
                if (altTechRow) {
                    altTechRow.innerText = "4 / 4 verified";
                    altTechRow.className = "p-3 text-muted";
                }
                alert("SEO optimization validation sweep completed successfully! Alt texts auto-generated for missing tags.");
            }, 3000);
        }

        // Simulate cost forecasting telemetry update
        function triggerCostForecastSimulation() {
            const mrr = document.getElementById('analytics-mrr');
            const costs = document.getElementById('analytics-costs');

            if (!mrr) return;

            mrr.innerText = "Recalculating...";
            mrr.className = "text-3xl font-display font-bold text-warning animate-pulse";
            costs.innerText = "Estimating...";
            costs.className = "text-3xl font-display font-bold text-warning animate-pulse";

            setTimeout(() => {
                mrr.innerText = "$48,942";
                mrr.className = "text-3xl font-display font-bold text-accent";
                costs.innerText = "$682.12";
                costs.className = "text-3xl font-display font-bold text-accent";
                alert("Cost forecast audit sync completed successfully! Expected MRR: $48.9K, Expected cost reductions: $160.00.");
            }, 3000);
        }

        // Simulate reports PDF exporter
        function triggerReportExportSimulation() {
            alert("Export simulation initiated. Compiling platform analytics... System report ready! PDF downloaded to local storage file path.");
        }

        // Simulate Notification marking all resolved
        function triggerNotificationClearSimulation() {
            const count = document.getElementById('notifications-count');
            const row1 = document.getElementById('event-row-1');
            const row2 = document.getElementById('event-row-2');
            const row3 = document.getElementById('event-row-3');

            if (!count) return;

            count.innerText = "0";
            count.className = "text-3xl font-display font-bold text-accent";

            if (row1) row1.style.opacity = '0.4';
            if (row2) row2.style.opacity = '0.4';
            if (row3) row3.style.opacity = '0.4';

            alert("All active platform notifications resolved successfully!");
        }

        // Simulate mute settings quiet hours toggle
        function triggerNotificationMuteSimulation() {
            alert("Quiet Hours parameters toggled. Deliveries snoozed until 08:00 Local time.");
        }

        // Simulate inviting platform operator
        function triggerOperatorInviteSimulation() {
            const count = document.getElementById('roles-total-users');
            const directoryBody = document.getElementById('roles-directory-body');

            if (!count) return;

            count.innerText = "25";
            
            // Add a new row to table
            const newRow = document.createElement('tr');
            newRow.className = "hover:bg-white/5 transition cursor-pointer";
            newRow.onclick = function() {
                inspectElement('user', 'Alice Smith', 'online', 'Editor (Level 2)', 'Content Management Scopes');
            };
            newRow.innerHTML = `
                <td class="p-3 pl-5"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20" onclick="event.stopPropagation()"/></td>
                <td class="p-3 text-text font-medium flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full bg-accent/20 border border-accent/40 flex items-center justify-center text-[10px] text-accent font-bold">AS</div>
                    <span>Alice Smith</span>
                </td>
                <td class="p-3"><span class="px-2 py-0.5 rounded bg-accent/20 text-accent border border-accent/30 text-[9px]">Editor</span></td>
                <td class="p-3 text-muted">Local only</td>
                <td class="p-3 text-accent font-bold">Enabled</td>
                <td class="p-3"><span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]">online</span></td>
                <td class="p-3 text-right pr-5">
                    <button class="text-secondary hover:underline">Inspect</button>
                </td>
            `;
            directoryBody.appendChild(newRow);

            alert("Invitation sent successfully! Temporary access link generated and copied to clipboard.");
        }

        // Simulate invoice sync
        function triggerInvoiceSyncSimulation() {
            const gross = document.getElementById('billing-gross-volume');
            const outstanding = document.getElementById('billing-unpaid-invoices');
            const targetRow = document.getElementById('billing-target-row');
            const targetBadge = document.getElementById('billing-target-badge');

            if (!gross) return;

            gross.innerText = "Syncing...";
            gross.className = "text-3xl font-display font-bold text-warning animate-pulse";

            setTimeout(() => {
                gross.innerText = "$42,921.00";
                gross.className = "text-3xl font-display font-bold text-accent";
                outstanding.innerText = "0";
                outstanding.className = "text-3xl font-display font-bold text-accent";
                if (targetBadge) {
                    targetBadge.innerText = "paid";
                    targetBadge.className = "px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]";
                }
                alert("Stripe invoice sync complete! Unpaid professional tier invoice resolved successfully.");
            }, 3000);
        }

        // Simulate billing locks verification
        function triggerBillingLockSimulation() {
            alert("Billing limitations verified. All customer overage thresholds are set to auto-lock.");
        }

        // Settings inner tab switcher
        function switchSettingsTab(tab) {
            document.querySelectorAll('.settings-tab-view').forEach(view => {
                view.classList.add('hidden');
            });
            const targetView = document.getElementById('settings-pane-' + tab);
            if (targetView) targetView.classList.remove('hidden');

            document.querySelectorAll('[id^="settings-tab-"]').forEach(btn => {
                btn.className = "px-4 py-1.5 rounded-lg text-xs font-mono text-muted hover:text-text transition";
            });
            const targetBtn = document.getElementById('settings-tab-' + tab);
            if (targetBtn) {
                targetBtn.className = "px-4 py-1.5 rounded-lg text-xs font-mono bg-white/5 text-accent font-semibold transition";
            }
        }

        // Simulate settings save
        function triggerSystemSaveSimulation() {
            alert("Global system configurations saved successfully! Environment variables sync dispatched to runtime container fleet.");
        }

        // Simulate settings health verification test
        function triggerSystemHealthTestSimulation() {
            alert("Health test scan initiated... Connection to Stripe API, OpenAI REST Gateways, and Azure AD directory verified successfully (Latency: 14ms).");
        }

        // Simulate logs purge
        function triggerLogPurgeSimulation() {
            const totalLogs = document.getElementById('audit-total-logs');
            const directoryBody = document.getElementById('audit-directory-body');

            if (!totalLogs) return;

            totalLogs.innerText = "Purging...";
            totalLogs.className = "text-3xl font-display font-bold text-warning animate-pulse";

            setTimeout(() => {
                totalLogs.innerText = "0";
                totalLogs.className = "text-3xl font-display font-bold text-accent";
                if (directoryBody) {
                    directoryBody.innerHTML = `
                        <tr>
                            <td colspan="7" class="p-8 text-center text-muted font-mono text-xs">
                                <span class="material-symbols-outlined text-4xl block mb-2 text-muted/30">find_in_page</span>
                                No system activity logs found.
                            </td>
                        </tr>
                    `;
                }
                alert("Obsolete platform audit logs purged successfully!");
            }, 3000);
        }

        // Simulate audit log CSV export
        function triggerLogExportSimulation() {
            alert("Export simulation initiated. Compiling active audit logs stream... CSV report ready and downloaded to local storage file path.");
        }

        // Simulate overview manual heartbeat check
        function triggerHeartbeatSimulation() {
            const statsFleet = document.getElementById('stats-fleet');
            if (!statsFleet) return;

            statsFleet.innerText = "Syncing...";
            statsFleet.className = "text-3xl font-display font-bold text-warning animate-pulse";

            setTimeout(() => {
                statsFleet.innerText = "482";
                statsFleet.className = "text-3xl font-display font-bold text-text";
                alert("Manual heartbeat diagnostics complete! Uptime checked successfully across all 482 client container nodes.");
            }, 3000);
        }

        // Simulate lock overages action
        function triggerOveragesLockSimulation() {
            alert("Lock command dispatched. Accounts exceeding tier credits limitations locked.");
        }

        // Simulate failed task connection retry
        function triggerFailedTaskRetrySimulation() {
            const failedList = document.getElementById('overview-failed-tasks-list');
            if (!failedList) return;

            const button = failedList.querySelector('button');
            button.innerText = "Retrying...";
            button.disabled = true;

            setTimeout(() => {
                failedList.innerHTML = `
                    <div class="p-8 text-center text-muted font-mono text-xs">
                        <span class="material-symbols-outlined text-4xl block mb-2 text-muted/30">task_alt</span>
                        All tasks resolved successfully!
                    </div>
                `;
                alert("WordPress connection resolved successfully for engadget.com! Task cleared.");
            }, 3000);
        }

        // Simulate CSS design tokens exporter
        function triggerDesignExportSimulation() {
            alert("Export simulation initiated. Generating variables JSON/CSS map... Design system tokens successfully exported to local build directory!");
        }

        // Handle Popstate (browser Back/Forward)
        window.onpopstate = function() {
            const path = window.location.pathname.replace('/', '');
            switchWorkspace(path === '' ? 'dashboard' : path);
        };

        // Theme Toggle Handler
        function updateThemeUI() {
            const html = document.documentElement;
            const icon = document.getElementById('theme-toggle-icon');
            if (html.classList.contains('dark')) {
                icon.innerText = 'light_mode';
            } else {
                icon.innerText = 'dark_mode';
            }
        }

        function toggleTheme() {
            const html = document.documentElement;
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                html.classList.add('dark');
                localStorage.theme = 'dark';
            }
            updateThemeUI();
        }

        // Apply theme indicators on init
        updateThemeUI();

        // ─── AI Providers: toggle API key visibility ────────────────────────
        function toggleKeyVisibility(btn) {
            const input = btn.closest('.relative').querySelector('input[type="password"], input[type="text"]');
            if (!input) return;
            const icon = btn.querySelector('.material-symbols-outlined');
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                if (icon) icon.textContent = 'visibility';
            }
        }

        // ─── AI Providers: Add Provider modal ────────────────────────────────
        const _providerMeta = {
            gemini:      { label: 'Google Gemini',     icon: 'neurology',    colour: 'text-secondary', bg: 'bg-secondary/10' },
            openai:      { label: 'OpenAI',            icon: 'smart_toy',    colour: 'text-accent',    bg: 'bg-accent/10'    },
            claude:      { label: 'Claude (Anthropic)',icon: 'psychology',   colour: 'text-warning',   bg: 'bg-warning/10'   },
            groq:        { label: 'Groq',              icon: 'bolt',         colour: 'text-danger',    bg: 'bg-danger/10'    },
            openrouter:  { label: 'OpenRouter',        icon: 'route',        colour: 'text-secondary', bg: 'bg-secondary/10' },
            ollama:      { label: 'Ollama',            icon: 'dns',          colour: 'text-muted',     bg: 'bg-white/5'      },
            custom:      { label: 'Custom',            icon: 'extension',    colour: 'text-highlight', bg: 'bg-highlight/10' },
        };

        function openAddProviderForm() {
            const modal = document.getElementById('add-provider-modal');
            if (!modal) return;
            // Reset fields
            document.getElementById('modal-provider-select').value = '';
            document.getElementById('modal-api-key').value = '';
            document.getElementById('modal-model').value = '';
            document.getElementById('modal-provider-error').classList.add('hidden');
            modal.classList.remove('hidden');
            document.getElementById('modal-provider-select').focus();
        }

        function closeAddProviderForm() {
            const modal = document.getElementById('add-provider-modal');
            if (modal) modal.classList.add('hidden');
        }

        // Dismiss on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeAddProviderForm();
        });

        function saveNewProvider() {
            const providerVal = document.getElementById('modal-provider-select').value.trim();
            const apiKey      = document.getElementById('modal-api-key').value.trim();
            const model       = document.getElementById('modal-model').value.trim();
            const errEl       = document.getElementById('modal-provider-error');

            if (!providerVal || !apiKey || !model) {
                errEl.classList.remove('hidden');
                return;
            }
            errEl.classList.add('hidden');

            const meta    = _providerMeta[providerVal] || _providerMeta.custom;
            const label   = providerVal === 'custom' ? (model || 'Custom') : meta.label;
            const cardId  = 'provider-card-' + providerVal + '-' + Date.now();
            const maskedKey = apiKey.substring(0, 6) + '••••••••••••••';

            const cardHTML = `
                <div class="glass-surface rounded-2xl p-5 space-y-4 border border-border hover:border-accent transition" id="${cardId}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-xl ${meta.colour} ${meta.bg} p-2 rounded-xl">${meta.icon}</span>
                            <div>
                                <p class="text-sm font-semibold">${label}</p>
                                <p class="text-[10px] font-mono text-muted">${model}</p>
                            </div>
                        </div>
                        <span class="provider-status px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px] font-mono">configured</span>
                    </div>
                    <div class="space-y-2">
                        <div class="space-y-1">
                            <label class="block text-[10px] font-mono text-muted uppercase tracking-widest">API Key</label>
                            <div class="relative">
                                <input type="password" class="w-full bg-background border border-border rounded-xl p-2 pr-8 text-xs font-mono text-text focus:outline-none focus:border-accent" value="${maskedKey}" readonly/>
                                <button class="absolute right-2 top-1/2 -translate-y-1/2 text-muted hover:text-text" onclick="toggleKeyVisibility(this)">
                                    <span class="material-symbols-outlined text-sm">visibility</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2 pt-2 border-t border-border">
                        <button onclick="document.getElementById('${cardId}').remove()" class="flex-1 bg-surface hover:bg-surface/80 border border-border text-text font-medium text-xs py-1.5 rounded-xl transition">Remove</button>
                    </div>
                </div>`;

            const grid = document.getElementById('providers-grid');
            if (grid) grid.insertAdjacentHTML('beforeend', cardHTML);

            closeAddProviderForm();
        }


        // Enable save button when API key input changes
        document.addEventListener('input', function(e) {
            if (!e.target.matches('#node-providers input')) return;
            const card = e.target.closest('.glass-surface');
            if (!card) return;
            const saveBtn = card.querySelector('button[onclick^="saveProviderKey"]');
            if (saveBtn) saveBtn.disabled = e.target.value.trim().length < 4;
        });

        function saveProviderKey(btn, provider) {
            const card = btn.closest('.glass-surface');
            const keyInput = card.querySelector('input[type="password"], input[type="text"]');
            const statusBadge = card.querySelector('.provider-status');
            if (!keyInput || !keyInput.value.trim()) return;

            btn.textContent = 'Saving...';
            btn.disabled = true;

            // TODO: POST /api/v1/providers with { provider, api_key, model }
            setTimeout(() => {
                btn.textContent = 'Saved ✓';
                if (statusBadge) {
                    statusBadge.textContent = 'configured';
                    statusBadge.className = 'provider-status px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px] font-mono';
                }
                // Mask the key for display
                keyInput.type = 'password';
                keyInput.value = keyInput.value.substring(0, 6) + '••••••••••••••';
            }, 800);
        }

        // Handle single selection of default provider checkbox
        function setDefaultProvider(selectedProvider) {
            document.querySelectorAll('.provider-default-chk').forEach(chk => {
                const id = chk.getAttribute('id');
                if (id !== 'chk-default-' + selectedProvider) {
                    chk.checked = false;
                }
            });
            // TODO: POST /api/v1/providers/default with { provider: selectedProvider }
        }


        // ─── Content Generation form validation ─────────────────────────────
        function triggerContentGeneration() {
            const provider = document.getElementById('gen-provider')?.value;
            const prompt   = document.getElementById('gen-prompt')?.value;
            const topic    = document.getElementById('gen-topic')?.value;
            const lang     = document.getElementById('gen-language')?.value;
            const output   = document.getElementById('gen-output');
            const badge    = document.getElementById('gen-status-badge');
            const container = document.getElementById('gen-preview-container');
            const generateBtn = document.getElementById('generate-btn');
            const copyBtn = document.getElementById('btn-copy-gen');
            const queueBtn = document.getElementById('btn-queue-gen');

            if (!provider || !prompt || !topic) return;

            if (container) container.classList.remove('hidden');
            if (badge) badge.classList.remove('hidden');
            if (generateBtn) generateBtn.disabled = true;
            if (copyBtn) copyBtn.disabled = true;
            if (queueBtn) queueBtn.disabled = true;

            if (output) output.innerHTML = '<div class="flex items-center gap-2"><span class="material-symbols-outlined text-warning animate-spin text-base">autorenew</span><span>Generating article...</span></div>';

            // TODO: POST /api/v1/pipeline/generate with { provider, prompt_id, topic_id, language }
            setTimeout(() => {
                if (badge) badge.classList.add('hidden');
                if (generateBtn) generateBtn.disabled = false;
                if (copyBtn) copyBtn.disabled = false;
                if (queueBtn) queueBtn.disabled = false;
                if (output) output.innerHTML = '<div class="space-y-2"><p class="text-text leading-relaxed font-sans font-semibold">Simulated Article Draft</p><p class="text-text leading-relaxed font-sans">This is a generated content preview draft designed to show how content appears on screen. Connect the backend API to generate actual live articles using your AI providers and prompts.</p><p class="text-muted mt-2 text-[10px]">API: POST /api/v1/pipeline/generate</p></div>';
            }, 2000);
        }

        // ─── Prompt Library: copy variable chip to clipboard ─────────────────
        document.addEventListener('click', function(e) {
            const chip = e.target.closest('.prompt-var-chip');
            if (!chip) return;
            const varName = chip.dataset.var;
            if (varName) navigator.clipboard.writeText('{' + '{' + varName + '}' + '}');
        });

        // Enable generate button only when all required fields are set
        document.addEventListener('change', function(e) {
            if (!e.target.matches('#node-pipeline select')) return;
            const provider = document.getElementById('gen-provider')?.value;
            const prompt   = document.getElementById('gen-prompt')?.value;
            const topic    = document.getElementById('gen-topic')?.value;
            const btn      = document.getElementById('generate-btn');
            if (btn) btn.disabled = !(provider && prompt && topic);
        });

        // ─── Media Studio: category tab switcher ────────────────────────────
        function switchMediaCategory(cat) {
            document.querySelectorAll('.media-category-pane').forEach(p => p.classList.add('hidden'));
            const pane = document.getElementById('media-cat-' + cat);
            if (pane) pane.classList.remove('hidden');

            document.querySelectorAll('.media-cat-btn').forEach(btn => {
                const active = btn.dataset.cat === cat;
                btn.classList.toggle('text-accent', active);
                btn.classList.toggle('bg-white/5', active);
                btn.classList.toggle('text-muted', !active);
                btn.classList.toggle('hover:text-text', !active);
                btn.classList.toggle('hover:bg-white/5', !active);
            });
        }


    </script>

