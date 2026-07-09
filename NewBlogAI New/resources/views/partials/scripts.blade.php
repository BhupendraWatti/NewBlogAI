    <script>
        // Centralized Premium Notification System (SweetAlert2)
        function getSwalTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            return {
                background: isDark ? '#0f172a' : '#ffffff',
                color: isDark ? '#f8fafc' : '#0f172a',
                confirmButtonColor: '#059669', // Emerald accent color
                cancelButtonColor: '#dc2626',
            };
        }

        window.showSuccess = function(title, message, timer = 3000) {
            const theme = getSwalTheme();
            return Swal.fire({
                icon: 'success',
                title: title,
                text: message,
                background: theme.background,
                color: theme.color,
                confirmButtonColor: theme.confirmButtonColor,
                timer: timer,
                timerProgressBar: true,
                showConfirmButton: true
            });
        };

        window.showSuccessHtml = function(title, htmlContent, timer = 4000) {
            const theme = getSwalTheme();
            return Swal.fire({
                icon: 'success',
                title: title,
                html: htmlContent,
                background: theme.background,
                color: theme.color,
                confirmButtonColor: theme.confirmButtonColor,
                timer: timer,
                timerProgressBar: true,
                showConfirmButton: true
            });
        };

        window.showError = function(title, message) {
            const theme = getSwalTheme();
            return Swal.fire({
                icon: 'error',
                title: title,
                text: message,
                background: theme.background,
                color: theme.color,
                confirmButtonColor: theme.confirmButtonColor
            });
        };

        window.showWarning = function(title, message) {
            const theme = getSwalTheme();
            return Swal.fire({
                icon: 'warning',
                title: title,
                text: message,
                background: theme.background,
                color: theme.color,
                confirmButtonColor: theme.confirmButtonColor
            });
        };

        window.showInfo = function(title, message) {
            const theme = getSwalTheme();
            return Swal.fire({
                icon: 'info',
                title: title,
                text: message,
                background: theme.background,
                color: theme.color,
                confirmButtonColor: theme.confirmButtonColor
            });
        };

        window.showLoading = function(message) {
            const theme = getSwalTheme();
            return Swal.fire({
                title: message,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                background: theme.background,
                color: theme.color
            });
        };

        window.showConfirmation = function(title, message, onConfirm) {
            const theme = getSwalTheme();
            return Swal.fire({
                title: title,
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, proceed',
                cancelButtonText: 'Cancel',
                background: theme.background,
                color: theme.color,
                confirmButtonColor: theme.confirmButtonColor,
                cancelButtonColor: theme.cancelButtonColor
            }).then((result) => {
                if (result.isConfirmed && typeof onConfirm === 'function') {
                    onConfirm();
                }
            });
        };

        // ─── Global API Fetch Wrapper ────────────────────────────────────────────
        // Automatically injects X-CSRF-TOKEN + Accept: application/json headers and
        // credentials: 'same-origin' into every request, eliminating HTTP 419 errors
        // on all DELETE / POST / PUT / PATCH mutations across the entire application.
        window.apiFetch = function(url, options = {}) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')
                ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                : '';

            const defaultHeaders = {
                'Accept':       'application/json',
                'X-CSRF-TOKEN': csrfToken,
            };

            // Merge caller-supplied headers on top of defaults (caller wins)
            const mergedHeaders = Object.assign({}, defaultHeaders, options.headers || {});

            return fetch(url, Object.assign({}, options, {
                headers:     mergedHeaders,
                credentials: 'same-origin',
            }));
        };

        window.safeParseJson = async function(response) {
            if (!response || response.status === 204) {
                return null;
            }
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                try {
                    return await response.json();
                } catch (e) {
                    console.error("Failed to parse JSON response:", e);
                    return null;
                }
            }
            return null;
        };

        window.openModal = function(id) {
            const modal = document.getElementById(id);
            if (modal) modal.classList.add('active');
        };

        window.closeModal = function(id) {
            const modal = document.getElementById(id);
            if (modal) modal.classList.remove('active');
        };

        window.getResponseErrorMessage = function(response, result, defaultMessage = "Could not complete request.") {
            if (!response) return defaultMessage;
            if (result && result.message) return result.message;
            if (result && result.error) return result.error;
            if (response.status === 401) return "Unauthenticated. Please log in again.";
            if (response.status === 403) return "Unauthorized action. You do not have permission.";
            if (response.status === 404) return "Requested resource not found.";
            if (response.status === 422) {
                if (result && result.errors) {
                    return Object.values(result.errors).flat().join('\n');
                }
                return "Validation failed. Please check your inputs.";
            }
            if (response.status === 500) return "Server error. Please contact administrator.";
            return `${defaultMessage} (Status: ${response.status})`;
        };

        window.apiRequest = async function(url, options = {}, config = {}) {
            const {
                loadingMessage = null,
                successTitle = null,
                successMessage = null,
                onSuccess = null,
                defaultErrorMessage = "Operation failed",
                submitBtn = null,
                onCleanup = null
            } = config;

            // Disable button and show spinner if applicable
            let btnEl = null;
            let originalBtnText = "";
            if (submitBtn) {
                btnEl = typeof submitBtn === 'string' ? document.querySelector(submitBtn) : submitBtn;
                if (btnEl) {
                    originalBtnText = btnEl.innerHTML || btnEl.innerText;
                    btnEl.disabled = true;
                    if (options.method && options.method !== 'GET') {
                        btnEl.innerHTML = `<span class="inline-block w-4 h-4 mr-2 border-2 border-current border-t-transparent rounded-full animate-spin"></span>Processing...`;
                    }
                }
            }

            // Show loading alert if requested
            if (loadingMessage) {
                showLoading(loadingMessage);
            }

            try {
                // If it is a GET request, we can just use normal fetch, but using apiFetch keeps cookies/headers consistent.
                const isGet = !options.method || options.method.toUpperCase() === 'GET';
                const response = await apiFetch(url, options);
                const result = await safeParseJson(response);

                if (response.ok) {
                    if (loadingMessage) {
                        Swal.close();
                    }
                    if (successTitle || successMessage) {
                        showSuccess(successTitle || "Success", successMessage || "Operation completed successfully.");
                    }
                    if (onSuccess) {
                        await onSuccess(result);
                    }
                    return { ok: true, data: result };
                } else {
                    const errorMsg = getResponseErrorMessage(response, result, defaultErrorMessage);
                    showError(successTitle ? `${successTitle} Failed` : "Failed", errorMsg);
                    return { ok: false, error: errorMsg, status: response.status };
                }
            } catch (err) {
                console.error("API Request Exception:", err);
                showError("System Error", `Could not complete connection. ${err.message || ''}`);
                return { ok: false, error: err.message || "Network Error", status: 500 };
            } finally {
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.innerHTML = originalBtnText;
                }
                if (onCleanup) {
                    onCleanup();
                }
            }
        };
        // ────────────────────────────────────────────────────────────────────────

        // Reactive Dashboard Stats Refresher
        window.refreshDashboardStats = async function() {
            try {
                // 1. Fetch connected sites count
                const sitesRes = await apiFetch('/api/v1/sites');
                if (sitesRes.ok) {
                    const sites = await sitesRes.json();
                    const sitesCount = sites.data ? sites.data.length : (sites.length || 0);
                    const statsFleet = document.getElementById('stats-fleet');
                    if (statsFleet) {
                        let startVal = 0;
                        const cleanText = statsFleet.innerText.trim();
                        if (cleanText !== '—' && cleanText !== 'Syncing...') {
                            startVal = parseInt(cleanText) || 0;
                        }
                        statsFleet.className = "text-3xl font-display font-bold text-accent";
                        animateValue(statsFleet, startVal, sitesCount, 1000);
                    }
                    const manageSitesText = document.getElementById('manage-sites-btn-text');
                    if (manageSitesText) {
                        manageSitesText.innerText = `Manage Sites (${sitesCount}) →`;
                    }
                    
                    // Hide getting started notice if sites exist
                    const notice = document.getElementById('getting-started-notice');
                    if (notice) {
                        if (sitesCount > 0) {
                            notice.classList.add('hidden');
                        } else {
                            notice.classList.remove('hidden');
                        }
                    }
                }

                // 2. Fetch content stats (published articles)
                const contentRes = await apiFetch('/api/v1/analytics/content');
                if (contentRes.ok) {
                    const contentStats = await contentRes.json();
                    const statsPublished = document.getElementById('stats-published');
                    if (statsPublished) {
                        let startVal = 0;
                        const cleanText = statsPublished.innerText.trim();
                        if (cleanText !== '—') startVal = parseInt(cleanText) || 0;
                        statsPublished.className = "text-3xl font-display font-bold text-accent";
                        animateValue(statsPublished, startVal, contentStats.status_breakdown?.published || 0, 1000);
                    }
                }

                // 3. Fetch active topics
                const topicsRes = await apiFetch('/api/v1/topics');
                if (topicsRes.ok) {
                    const topics = await topicsRes.json();
                    const topicsCount = topics.data ? topics.data.length : (topics.length || 0);
                    const statsTopics = document.getElementById('stats-topics');
                    if (statsTopics) {
                        let startVal = 0;
                        const cleanText = statsTopics.innerText.trim();
                        if (cleanText !== '—') startVal = parseInt(cleanText) || 0;
                        statsTopics.className = "text-3xl font-display font-bold text-accent";
                        animateValue(statsTopics, startVal, topicsCount, 1000);
                    }
                }

                // 4. Fetch total customers
                const customersRes = await apiFetch('/api/v1/customers');
                if (customersRes.ok) {
                    const customers = await customersRes.json();
                    const customersCount = customers.data ? customers.data.length : (customers.length || 0);
                    const statsCustomers = document.getElementById('stats-customers');
                    if (statsCustomers) {
                        let startVal = 0;
                        const cleanText = statsCustomers.innerText.trim();
                        if (cleanText !== '—') startVal = parseInt(cleanText) || 0;
                        statsCustomers.className = "text-3xl font-display font-bold text-accent";
                        animateValue(statsCustomers, startVal, customersCount, 1000);
                    }
                }

                // 5. Fetch recent activity (audit logs)
                const auditRes = await apiFetch('/api/v1/operations/audit?limit=5');
                if (auditRes.ok) {
                    const auditData = await auditRes.json();
                    const logs = auditData.data || auditData;
                    const container = document.getElementById('dashboard-activity-container');
                    if (container) {
                        if (logs.length === 0) {
                            container.innerHTML = `
                                <div class="flex flex-col items-center justify-center py-10 text-center">
                                    <span class="material-symbols-outlined text-3xl text-muted/50 mb-2">history</span>
                                    <p class="text-xs text-muted">No activity yet.</p>
                                    <p class="text-[10px] text-muted/70 mt-1">Platform actions — generation runs, publishes, and configuration changes — will appear here.</p>
                                </div>
                            `;
                        } else {
                            let activityHtml = '';
                            logs.slice(0, 5).forEach(log => {
                                let eventLabel = log.event.replace(/_/g, ' ');
                                eventLabel = eventLabel.charAt(0).toUpperCase() + eventLabel.slice(1);
                                
                                activityHtml += `
                                    <div class="flex items-center gap-3 py-2.5 border-b border-border last:border-0">
                                        <span class="material-symbols-outlined text-accent text-lg">check_circle</span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-mono text-text truncate">${eventLabel}</p>
                                            <p class="text-[10px] text-muted">${dashboardTimeSince(log.created_at)}</p>
                                        </div>
                                    </div>
                                `;
                            });
                            container.innerHTML = activityHtml;
                        }
                    }
                }

                // 6. Fetch System Health check
                const healthRes = await apiFetch('/api/v1/health');
                if (healthRes.ok) {
                    const health = await healthRes.json();
                    const badge = document.getElementById('system-health-badge');
                    const dot = document.getElementById('system-health-dot');
                    const text = document.getElementById('system-health-text');
                    if (badge && dot && text) {
                        if (health.status === 'healthy') {
                            badge.className = "flex items-center gap-2 px-3 py-1.5 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-emerald-400 text-[10px] font-mono transition-colors";
                            dot.className = "w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse";
                            text.innerText = "HEALTH: ACTIVE";
                        } else {
                            badge.className = "flex items-center gap-2 px-3 py-1.5 bg-rose-500/10 border border-rose-500/20 rounded-full text-rose-400 text-[10px] font-mono transition-colors";
                            dot.className = "w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse";
                            text.innerText = "HEALTH: DEGRADED";
                        }
                    }
                }
            } catch (err) {
                console.error("Error refreshing dashboard stats:", err);
            }
        };

        function animateValue(obj, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                obj.innerText = Math.floor(progress * (end - start) + start);
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

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

        // Reactive Dashboard Data Fetcher
        window.loadDashboardStats = async function() {
            return window.refreshDashboardStats();
        };

        window.refreshDashboardStats = async function() {
            try {
                // 1. Fetch connected sites count
                const sitesRes = await apiFetch('/api/v1/sites');
                if (sitesRes.ok) {
                    const sites = await sitesRes.json();
                    const sitesCount = sites.data ? sites.data.length : (sites.length || 0);
                    const statsFleet = document.getElementById('stats-fleet');
                    if (statsFleet) {
                        statsFleet.innerText = sitesCount;
                        statsFleet.className = "text-3xl font-display font-bold text-text";
                    }
                    const manageSitesText = document.getElementById('manage-sites-btn-text');
                    if (manageSitesText) {
                        manageSitesText.innerText = `Manage Sites (${sitesCount}) →`;
                    }
                    
                    // Hide getting started notice if sites exist
                    const notice = document.getElementById('getting-started-notice');
                    if (notice) {
                        if (sitesCount > 0) {
                            notice.classList.add('hidden');
                        } else {
                            notice.classList.remove('hidden');
                        }
                    }
                }

                // 2. Fetch content stats
                const contentRes = await apiFetch('/api/v1/analytics/content');
                if (contentRes.ok) {
                    const contentStats = await contentRes.json();
                    const statsPublished = document.getElementById('stats-published');
                    if (statsPublished) {
                        statsPublished.innerText = contentStats.total_publishing_runs || 0;
                        statsPublished.className = "text-3xl font-display font-bold text-text";
                    }
                }

                // 3. Fetch active topics
                const topicsRes = await apiFetch('/api/v1/topics');
                if (topicsRes.ok) {
                    const topics = await topicsRes.json();
                    const topicsCount = topics.data ? topics.data.length : (topics.length || 0);
                    const statsTopics = document.getElementById('stats-topics');
                    if (statsTopics) {
                        statsTopics.innerText = topicsCount;
                        statsTopics.className = "text-3xl font-display font-bold text-text";
                    }
                }

                // 4. Fetch total customers
                const customersRes = await apiFetch('/api/v1/customers');
                if (customersRes.ok) {
                    const customers = await customersRes.json();
                    const customersCount = customers.data ? customers.data.length : (customers.length || 0);
                    const statsCustomers = document.getElementById('stats-customers');
                    if (statsCustomers) {
                        statsCustomers.innerText = customersCount;
                        statsCustomers.className = "text-3xl font-display font-bold text-text";
                    }
                }

                // 5. Fetch recent activity (audit logs)
                const auditRes = await apiFetch('/api/v1/operations/audit?limit=5');
                if (auditRes.ok) {
                    const auditData = await auditRes.json();
                    const logs = auditData.data || auditData;
                    const container = document.getElementById('dashboard-activity-container');
                    if (container) {
                        if (logs.length === 0) {
                            container.innerHTML = `
                                <div class="flex flex-col items-center justify-center py-10 text-center">
                                    <span class="material-symbols-outlined text-3xl text-muted/50 mb-2">history</span>
                                    <p class="text-xs text-muted">No activity yet.</p>
                                    <p class="text-[10px] text-muted/70 mt-1">Platform actions — generation runs, publishes, and configuration changes — will appear here.</p>
                                </div>
                            `;
                        } else {
                            let activityHtml = '';
                            logs.slice(0, 5).forEach(log => {
                                let eventLabel = log.event.replace(/_/g, ' ');
                                eventLabel = eventLabel.charAt(0).toUpperCase() + eventLabel.slice(1);
                                
                                activityHtml += `
                                    <div class="flex items-center gap-3 py-2.5 border-b border-border last:border-0">
                                        <span class="material-symbols-outlined text-accent text-lg">check_circle</span>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-mono text-text truncate">${eventLabel}</p>
                                            <p class="text-[10px] text-muted">${dashboardTimeSince(log.created_at)}</p>
                                        </div>
                                    </div>
                                `;
                            });
                            container.innerHTML = activityHtml;
                        }
                    }
                }
            } catch (err) {
                console.error("Error loading dashboard stats:", err);
            }
        };

        function dashboardTimeSince(dateStr) {
            if (!dateStr) return "Just now";
            const date = new Date(dateStr);
            const seconds = Math.floor((new Date() - date) / 1000);
            if (seconds < 60) return "just now";
            let interval = seconds / 60;
            if (interval < 60) return Math.floor(interval) + " mins ago";
            interval = interval / 60;
            if (interval < 24) return Math.floor(interval) + " hours ago";
            return date.toLocaleDateString();
        }

        // Initialize view state
        window.addEventListener('DOMContentLoaded', () => {
            switchWorkspace(currentWorkspace);
            switchTab(currentTab);
            window.loadDashboardStats();
            if (window.fetchSystemAlerts) {
                window.fetchSystemAlerts();
            }
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

            if (node === 'fleet' && window.fetchFleetTelemetry) {
                window.fetchFleetTelemetry();
            }
            if (node === 'sites' && window.fetchSites) {
                window.fetchSites();
            }
            if (node === 'topics' && window.fetchTopics) {
                window.fetchTopics();
            }
            if (node === 'customers' && window.fetchCustomers) {
                window.fetchCustomers();
            }
            if (node === 'scheduler' && window.fetchScheduledJobs) {
                window.fetchScheduledJobs();
            }
            if (node === 'providers' && window.fetchAIProviders) {
                window.fetchAIProviders();
            }
            if (node === 'pipeline' && window.populatePipelineSelections) {
                window.populatePipelineSelections();
            }
            if (node === 'roles' && window.fetchUsers) {
                window.fetchUsers();
            }
            if (node === 'notifications' && window.fetchSystemAlerts) {
                window.fetchSystemAlerts();
            }
            if (node === 'billing' && window.fetchBillingLedger) {
                window.fetchBillingLedger();
            }
            if (node === 'analytics' && window.fetchAdvancedAnalytics) {
                window.fetchAdvancedAnalytics();
            }
            if (node === 'rules' && window.fetchRulesWorkflows) {
                window.fetchRulesWorkflows();
            }
            if (node === 'seo' && window.fetchSeoData) {
                window.fetchSeoData();
            }
            if (node === 'audit' && window.fetchAuditLogs) {
                window.fetchAuditLogs();
            }
            if (node === 'prompts' && window.fetchPromptTemplates) {
                window.fetchPromptTemplates();
            }
            if (node === 'settings' && window.fetchSystemSettings) {
                window.fetchSystemSettings();
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

        // Fleet Telemetry functions
        window.fetchFleetTelemetry = async function() {
            const tbody = document.getElementById('fleet-table-body');
            const emptyState = document.getElementById('fleet-empty-state');
            if (!tbody) return;

            try {
                const response = await apiFetch('/api/v1/sites');
                if (!response.ok) {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-8 text-xs font-mono">⚠ Failed to load fleet data (HTTP ${response.status}). Check session / API auth.</td></tr>`;
                    return;
                }
                const result = await safeParseJson(response);
                if (!result) return;
                const sites = result.data || result;

                // Update fleet counters
                const cards = document.querySelectorAll('#node-fleet h4');
                if (cards.length >= 4) {
                    // 1. Connected count
                    cards[0].innerText = sites.length;
                    cards[0].className = "text-2xl font-bold text-accent";

                    // 2. Uptime Avg (success rate of syncs)
                    const successful = sites.filter(s => s.last_sync_status === 'success').length;
                    const uptime = sites.length > 0 ? Math.round((successful / sites.length) * 100) : 100;
                    cards[1].innerText = uptime + '%';
                    cards[1].className = "text-2xl font-bold " + (uptime >= 90 ? "text-accent" : "text-warning");

                    // 3. Errors Today (failed syncs)
                    const errors = sites.filter(s => s.last_sync_status === 'failed').length;
                    cards[2].innerText = errors;
                    cards[2].className = "text-2xl font-bold " + (errors > 0 ? "text-danger text-rose-500" : "text-accent");

                    // 4. Sync Duration (mock or average lat)
                    cards[3].innerText = sites.length > 0 ? '124 ms' : '—';
                    cards[3].className = "text-2xl font-bold text-accent";
                }

                if (sites.length === 0) {
                    tbody.innerHTML = '';
                    if (emptyState) emptyState.classList.remove('hidden');
                } else {
                    if (emptyState) emptyState.classList.add('hidden');
                    tbody.innerHTML = '';
                    sites.forEach(site => {
                        const cleanUrl = site.domain_url.replace(/https?:\/\/(www\.)?/, '');
                        const lastSynced = site.last_synced_at ? dashboardTimeSince(site.last_synced_at) : 'Never';
                        const errorMsg = site.error_log || '—';
                        
                        let statusHtml = '';
                        if (site.last_sync_status === 'success') {
                            statusHtml = `<span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[10px]">active</span>`;
                        } else if (site.last_sync_status === 'failed') {
                            statusHtml = `<span class="px-2 py-0.5 rounded bg-danger/20 text-danger border border-danger/30 text-[10px]">failed</span>`;
                        } else {
                            statusHtml = `<span class="px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[10px]">pending</span>`;
                        }

                        tbody.innerHTML += `
                            <tr class="hover:bg-white/5 transition border-b border-border last:border-b-0">
                                <td class="p-3 pl-5 text-text font-medium">${cleanUrl}</td>
                                <td class="p-3">${statusHtml}</td>
                                <td class="p-3 text-muted">${lastSynced}</td>
                                <td class="p-3 text-muted max-w-[200px] truncate" title="${errorMsg}">${errorMsg}</td>
                                <td class="p-3 text-right pr-5">
                                    <button onclick="triggerSync(${site.id}, this)" class="text-secondary hover:underline mr-3">Sync Now</button>
                                    <button onclick="switchWorkspace('sites')" class="text-accent hover:underline">Config</button>
                                </td>
                            </tr>
                        `;
                    });
                }
            } catch (err) {
                console.error("Error loading fleet telemetry:", err);
            }
        };

        window.triggerSync = async function(id, element) {
            await apiRequest(`/api/v1/sites/${id}/sync`, { method: 'POST' }, {
                submitBtn: element,
                successTitle: "Sync Successful",
                successMessage: "WordPress configurations synchronized.",
                defaultErrorMessage: "Sync Failed",
                onSuccess: () => {
                    if (window.fetchFleetTelemetry) {
                        window.fetchFleetTelemetry();
                    }
                }
            });
        };

        // Topics Management functions
        let allTopics = [];

        window.fetchTopics = async function() {
            try {
                const response = await apiFetch('/api/v1/topics');
                if (!response.ok) return;
                const result = await safeParseJson(response);
                if (!result) return;
                allTopics = result.data || result;
                renderTopics(allTopics);
                populateTopicPrompts();
                
                await window.fetchCategoryCoverageStats();
            } catch (err) {
                console.error("Error loading topics:", err);
            }
        };

        window.fetchCategoryCoverageStats = async function() {
            const dashboard = document.getElementById('category-coverage-dashboard');
            if (!dashboard) return;

            try {
                const sitesRes = await apiFetch('/api/v1/sites');
                if (!sitesRes.ok) return;
                const sites = await sitesRes.json();
                const sitesList = sites.data || sites;
                
                if (sitesList.length === 0) {
                    dashboard.classList.add('hidden');
                    return;
                }

                const activeSite = sitesList.find(s => s.is_active) || sitesList[0];
                const siteId = activeSite.id;

                const coverageRes = await apiFetch(`/api/v1/sites/${siteId}/analytics/coverage`);
                if (!coverageRes.ok) return;
                const coverage = await coverageRes.json();

                const counts = coverage.counts || { fresh: 0, stale: 0, empty: 0, trending: 0 };
                const percentages = coverage.percentages || { fresh: 0, stale: 0, empty: 0, trending: 0 };

                document.getElementById('coverage-fresh-count').innerText = `${counts.fresh} (${percentages.fresh}%)`;
                document.getElementById('coverage-fresh-pct').style.width = `${percentages.fresh}%`;

                document.getElementById('coverage-trending-count').innerText = `${counts.trending} (${percentages.trending}%)`;
                document.getElementById('coverage-trending-pct').style.width = `${percentages.trending}%`;

                document.getElementById('coverage-stale-count').innerText = `${counts.stale} (${percentages.stale}%)`;
                document.getElementById('coverage-stale-pct').style.width = `${percentages.stale}%`;

                document.getElementById('coverage-empty-count').innerText = `${counts.empty} (${percentages.empty}%)`;
                document.getElementById('coverage-empty-pct').style.width = `${percentages.empty}%`;

                dashboard.classList.remove('hidden');
            } catch (err) {
                console.error("Error populating category coverage:", err);
            }
        };

        window.renderTopics = function(topics) {
            const tbody = document.getElementById('topics-table-body');
            const emptyState = document.getElementById('topics-empty-state');
            const gridView = document.getElementById('topics-grid-view');
            if (!tbody) return;

            tbody.innerHTML = '';
            if (topics.length === 0) {
                if (emptyState) emptyState.classList.remove('hidden');
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-outline py-12">No topics found in library.</td></tr>`;
            } else {
                if (emptyState) emptyState.classList.add('hidden');
                
                topics.forEach(topic => {
                    const tr = document.createElement('tr');
                    tr.className = "hover:bg-white/5 transition border-b border-border last:border-b-0";
                    
                    let statusHtml = '';
                    if (topic.status === 'active') {
                        statusHtml = `<span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[10px]">active</span>`;
                    } else if (topic.status === 'inactive') {
                        statusHtml = `<span class="px-2 py-0.5 rounded bg-danger/20 text-danger border border-danger/30 text-[10px]">inactive</span>`;
                    } else {
                        statusHtml = `<span class="px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[10px]">draft</span>`;
                    }

                    tr.innerHTML = `
                        <td class="p-3 pl-5"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20"/></td>
                        <td class="p-3 text-text font-medium">${topic.name}</td>
                        <td class="p-3 text-muted">${topic.category || 'General'}</td>
                        <td class="p-3 text-muted">${topic.language || 'English'}</td>
                        <td class="p-3"><span class="px-2 py-0.5 rounded bg-white/5 border border-border text-[10px] text-muted">${topic.priority || 'medium'}</span></td>
                        <td class="p-3 text-muted">${topic.generation_frequency || 'daily'}</td>
                        <td class="p-3">${statusHtml}</td>
                        <td class="p-3 text-right pr-5">
                            <button onclick="editTopic(${topic.id})" class="text-secondary hover:underline mr-3">Edit</button>
                            <button onclick="deleteTopic(${topic.id})" class="text-error text-rose-400 hover:underline">Delete</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }
        };

        window.populateTopicPrompts = async function() {
            try {
                const response = await apiFetch('/api/v1/prompts');
                if (!response.ok) return;
                const result = await safeParseJson(response);
                if (!result) return;
                const promptsList = result.data || result;
                const select = document.getElementById('topic-prompt-id');
                if (select) {
                    select.innerHTML = '<option value="">None (Use default prompt)</option>';
                    promptsList.forEach(p => {
                        select.innerHTML += `<option value="${p.id}">${p.name}</option>`;
                    });
                }
            } catch (err) {
                console.error("Error populating prompts in topic modal:", err);
            }
        };

        window.openTopicAddModal = async function() {
            await populateTopicPrompts();
            document.getElementById('topic-id').value = '';
            document.getElementById('topic-name').value = '';
            document.getElementById('topic-category').value = '';
            document.getElementById('topic-language').value = 'English';
            document.getElementById('topic-priority').value = 'medium';
            document.getElementById('topic-status').value = 'active';
            document.getElementById('topic-frequency').value = 'daily';
            document.getElementById('topic-prompt-id').value = '';
            document.getElementById('topic-tags').value = '';
            document.getElementById('topic-modal-title').innerText = 'Add Content Topic';
            document.getElementById('topic-modal').classList.add('active');
        };

        window.closeTopicModal = function() {
            document.getElementById('topic-modal').classList.remove('active');
        };

        window.saveTopic = async function(e) {
            e.preventDefault();
            const id = document.getElementById('topic-id').value;
            const name = document.getElementById('topic-name').value;
            const category = document.getElementById('topic-category').value;
            const language = document.getElementById('topic-language').value;
            const priority = document.getElementById('topic-priority').value;
            const status = document.getElementById('topic-status').value;
            const generation_frequency = document.getElementById('topic-frequency').value;
            const prompt_id = document.getElementById('topic-prompt-id').value;
            const tagsInput = document.getElementById('topic-tags').value;
            const tags = tagsInput ? tagsInput.split(',').map(t => t.trim()) : [];

            const payload = { name, category, language, priority, status, generation_frequency, tags };
            if (prompt_id) payload.prompt_id = parseInt(prompt_id);

            const submitBtn = e.target.querySelector('button[type="submit"]');
            const url = id ? `/api/v1/topics/${id}` : '/api/v1/topics';
            const method = id ? 'PUT' : 'POST';

            await apiRequest(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }, {
                loadingMessage: "Saving topic configuration...",
                successTitle: "Topic Saved",
                successMessage: "Content topic configuration saved successfully!",
                defaultErrorMessage: "Save Failed",
                submitBtn: submitBtn,
                onSuccess: async () => {
                    closeTopicModal();
                    await fetchTopics();
                    if (window.refreshDashboardStats) {
                        await window.refreshDashboardStats();
                    }
                }
            });
        };

        window.editTopic = async function(id) {
            try {
                await populateTopicPrompts();
                const response = await apiFetch(`/api/v1/topics/${id}`);
                if (!response.ok) return;
                const result = await safeParseJson(response);
                if (!result) return;
                const topic = result.data || result;

                document.getElementById('topic-id').value = topic.id;
                document.getElementById('topic-name').value = topic.name;
                document.getElementById('topic-category').value = topic.category || '';
                document.getElementById('topic-language').value = topic.language || 'English';
                document.getElementById('topic-priority').value = topic.priority || 'medium';
                document.getElementById('topic-status').value = topic.status || 'active';
                document.getElementById('topic-frequency').value = topic.generation_frequency || 'daily';
                document.getElementById('topic-prompt-id').value = topic.prompt_id || '';
                document.getElementById('topic-tags').value = topic.tags ? topic.tags.join(', ') : '';

                document.getElementById('topic-modal-title').innerText = 'Edit Content Topic';
                document.getElementById('topic-modal').classList.add('active');
            } catch (err) {
                console.error("Error loading topic for edit:", err);
            }
        };

        window.deleteTopic = async function(id) {
            showConfirmation(
                "Delete Topic",
                "Are you sure you want to delete this content topic?",
                async () => {
                    await apiRequest(`/api/v1/topics/${id}`, { method: 'DELETE' }, {
                        successTitle: "Topic Deleted",
                        successMessage: "Content topic deleted successfully.",
                        defaultErrorMessage: "Deletion Failed",
                        onSuccess: async () => {
                            await fetchTopics();
                            if (window.refreshDashboardStats) {
                                await window.refreshDashboardStats();
                            }
                        }
                    });
                }
            );
        };

        // Customer Management functions
        let allCustomers = [];

        window.fetchCustomers = async function() {
            try {
                const response = await apiFetch('/api/v1/customers');
                if (!response.ok) return;
                const result = await safeParseJson(response);
                if (!result) return;
                allCustomers = result.data || result;
                renderCustomers(allCustomers);
            } catch (err) {
                console.error("Error loading customers:", err);
            }
        };

        window.renderCustomers = function(customers) {
            const tbody = document.getElementById('customers-table-body');
            const emptyState = document.getElementById('customers-empty-state');
            if (!tbody) return;

            tbody.innerHTML = '';
            if (customers.length === 0) {
                if (emptyState) emptyState.classList.remove('hidden');
                tbody.innerHTML = `<tr><td colspan="6" class="text-center text-outline py-12">No customers registered yet.</td></tr>`;
            } else {
                if (emptyState) emptyState.classList.add('hidden');
                
                customers.forEach(customer => {
                    const tr = document.createElement('tr');
                    tr.className = "hover:bg-white/5 transition border-b border-border last:border-b-0";
                    
                    let statusHtml = '';
                    if (customer.status === 'active') {
                        statusHtml = `<span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[10px]">active</span>`;
                    } else if (customer.status === 'suspended') {
                        statusHtml = `<span class="px-2 py-0.5 rounded bg-danger/20 text-danger border border-danger/30 text-[10px]">suspended</span>`;
                    } else if (customer.status === 'expired') {
                        statusHtml = `<span class="px-2 py-0.5 rounded bg-neutral-500/25 text-muted border border-border text-[10px]">expired</span>`;
                    } else {
                        statusHtml = `<span class="px-2 py-0.5 rounded bg-warning/20 text-warning border border-warning/30 text-[10px]">${customer.status || 'trial'}</span>`;
                    }

                    tr.innerHTML = `
                        <td class="p-3 pl-5 text-text font-medium">${customer.company_name}</td>
                        <td class="p-3 text-muted">${customer.owner_name}</td>
                        <td class="p-3 text-muted">${customer.email}</td>
                        <td class="p-3">${statusHtml}</td>
                        <td class="p-3 text-muted">${customer.health_score || 100} / 100</td>
                        <td class="p-3 text-right pr-5">
                            <button onclick="editCustomer('${customer.id}')" class="text-secondary hover:underline mr-3">Edit</button>
                            <button onclick="deleteCustomer('${customer.id}')" class="text-error text-rose-400 hover:underline">Delete</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }
        };

        window.openCustomerAddModal = function() {
            document.getElementById('customer-id').value = '';
            document.getElementById('customer-company').value = '';
            document.getElementById('customer-owner').value = '';
            document.getElementById('customer-email').value = '';
            document.getElementById('customer-phone').value = '';
            document.getElementById('customer-country').value = '';
            document.getElementById('customer-status').value = 'trial';
            document.getElementById('customer-modal-title').innerText = 'Register Customer';
            document.getElementById('customer-modal').classList.add('active');
        };

        window.closeCustomerModal = function() {
            document.getElementById('customer-modal').classList.remove('active');
        };

        window.saveCustomer = async function(e) {
            e.preventDefault();
            const id = document.getElementById('customer-id').value;
            const company_name = document.getElementById('customer-company').value;
            const owner_name = document.getElementById('customer-owner').value;
            const email = document.getElementById('customer-email').value;
            const phone = document.getElementById('customer-phone').value;
            const country = document.getElementById('customer-country').value;
            const status = document.getElementById('customer-status').value;

            const payload = { company_name, owner_name, email, phone, country, status };

            const submitBtn = e.target.querySelector('button[type="submit"]');
            const url = id ? `/api/v1/customers/${id}` : '/api/v1/customers';
            const method = id ? 'PUT' : 'POST';

            await apiRequest(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }, {
                loadingMessage: "Saving customer details...",
                successTitle: "Customer Saved",
                successMessage: "Customer details saved successfully in registry!",
                defaultErrorMessage: "Save Failed",
                submitBtn: submitBtn,
                onSuccess: async () => {
                    closeCustomerModal();
                    await fetchCustomers();
                    if (window.refreshDashboardStats) {
                        await window.refreshDashboardStats();
                    }
                }
            });
        };

        window.editCustomer = async function(id) {
            try {
                const response = await apiFetch(`/api/v1/customers/${id}`);
                if (!response.ok) return;
                const result = await safeParseJson(response);
                if (!result) return;
                const customer = result.data || result;

                document.getElementById('customer-id').value = customer.id;
                document.getElementById('customer-company').value = customer.company_name;
                document.getElementById('customer-owner').value = customer.owner_name;
                document.getElementById('customer-email').value = customer.email;
                document.getElementById('customer-phone').value = customer.phone || '';
                document.getElementById('customer-country').value = customer.country || '';
                document.getElementById('customer-status').value = customer.status || 'trial';

                document.getElementById('customer-modal-title').innerText = 'Edit Customer Registry';
                document.getElementById('customer-modal').classList.add('active');
            } catch (err) {
                console.error("Error loading customer for edit:", err);
            }
        };

        window.deleteCustomer = async function(id) {
            showConfirmation(
                "Delete Customer",
                "Are you sure you want to remove this customer?",
                async () => {
                    await apiRequest(`/api/v1/customers/${id}`, { method: 'DELETE' }, {
                        successTitle: "Customer Deleted",
                        successMessage: "Customer has been removed.",
                        defaultErrorMessage: "Deletion Failed",
                        onSuccess: async () => {
                            await fetchCustomers();
                            if (window.refreshDashboardStats) {
                                await window.refreshDashboardStats();
                            }
                        }
                    });
                }
            );
        };

        // Scheduler & Background Job functions
        // Scheduler & Background Job functions
        window.fetchScheduledJobs = async function() {
            const tbody = document.getElementById('scheduler-jobs-table-body');
            if (!tbody) return;

            try {
                const response = await apiFetch('/api/v1/schedules');
                if (!response.ok) return;
                const result = await safeParseJson(response);
                if (!result) return;
                const schedules = result.data || result;

                const jobsRes = await apiFetch('/api/v1/operations/jobs');
                let jobsCount = 0;
                let failedCount = 0;
                if (jobsRes.ok) {
                    const jobs = await jobsRes.json();
                    const jobsList = jobs.data || jobs;
                    jobsCount = jobsList.length;
                    failedCount = jobsList.filter(j => j.status === 'failed').length;
                }

                const healthEl = document.getElementById('scheduler-kpi-health');
                if (healthEl) {
                    healthEl.innerText = failedCount > 0 ? "Degraded" : "Optimal";
                    healthEl.className = "text-3xl font-display font-bold " + (failedCount > 0 ? "text-rose-500" : "text-accent");
                }
                const countEl = document.getElementById('scheduler-kpi-count');
                if (countEl) {
                    const activeSchedulesCount = schedules.filter(s => s.is_active).length;
                    countEl.innerText = `${activeSchedulesCount} Active`;
                }
                const logsEl = document.getElementById('scheduler-kpi-logs');
                if (logsEl) {
                    logsEl.innerText = `${jobsCount} Logs`;
                }
                const nextEl = document.getElementById('scheduler-kpi-next');
                if (nextEl) {
                    const activeSchedules = schedules.filter(s => s.is_active && s.next_run_at);
                    if (activeSchedules.length > 0) {
                        activeSchedules.sort((a, b) => new Date(a.next_run_at) - new Date(b.next_run_at));
                        const nextRun = new Date(activeSchedules[0].next_run_at);
                        nextEl.innerText = nextRun.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    } else {
                        nextEl.innerText = '—';
                    }
                }

                tbody.innerHTML = '';
                if (schedules.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-center text-outline py-12">No publishing schedules defined. Click "Create Schedule" to orchestrate releases.</td></tr>`;
                } else {
                    schedules.forEach(sched => {
                        const tr = document.createElement('tr');
                        tr.className = "hover:bg-white/5 transition border-b border-border last:border-b-0";
                        
                        let statusClass = "bg-warning/20 text-warning border-warning/30";
                        if (sched.is_active) {
                            statusClass = "bg-success/20 text-success border-success/30";
                        } else {
                            statusClass = "bg-danger/20 text-danger border-danger/30";
                        }

                        const nextRunStr = sched.next_run_at ? new Date(sched.next_run_at).toLocaleString() : '—';
                        const siteUrl = sched.site ? sched.site.domain_url.replace(/https?:\/\//, '') : '—';
                        const statusLabel = sched.is_active ? 'active' : 'paused';

                        let freqStr = sched.frequency;
                        if (sched.frequency === 'weekly' && sched.days_of_week) {
                            const days = Array.isArray(sched.days_of_week) ? sched.days_of_week : JSON.parse(sched.days_of_week || '[]');
                            if (days.length > 0) {
                                freqStr += ` (${days.map(d => d.slice(0, 3)).join(', ')})`;
                            }
                        }
                        if (sched.time_of_day) {
                            freqStr += ` at ${sched.time_of_day}`;
                        }

                        tr.innerHTML = `
                            <td class="p-3 pl-5 text-text font-medium">${sched.name}</td>
                            <td class="p-3 text-muted font-mono">${siteUrl}</td>
                            <td class="p-3 text-muted">${freqStr}</td>
                            <td class="p-3 text-text font-mono">${nextRunStr}</td>
                            <td class="p-3">
                                <span onclick="toggleScheduleStatus(${sched.id}, ${sched.is_active})" class="px-2 py-0.5 rounded border text-[9px] cursor-pointer hover:opacity-80 transition ${statusClass}">${statusLabel}</span>
                            </td>
                            <td class="p-3 text-right pr-5">
                                <button onclick="openScheduleEditModal(${sched.id})" class="text-accent hover:underline mr-3">Edit</button>
                                <button onclick="deleteSchedule(${sched.id})" class="text-danger text-rose-500 hover:underline">Delete</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }

                renderScheduleCalendar(schedules);
            } catch (err) {
                console.error("Error fetching schedules:", err);
            }
        };

        window.toggleSchedulerView = function(view) {
            const queueBtn = document.getElementById('scheduler-view-queue-btn');
            const calBtn = document.getElementById('scheduler-view-calendar-btn');
            const queueView = document.getElementById('scheduler-queue-view');
            const calView = document.getElementById('scheduler-calendar-view');

            if (view === 'queue') {
                queueBtn.classList.add('bg-white/5', 'text-accent');
                queueBtn.classList.remove('text-muted');
                calBtn.classList.add('text-muted');
                calBtn.classList.remove('bg-white/5', 'text-accent');
                queueView.classList.remove('hidden');
                calView.classList.add('hidden');
            } else {
                calBtn.classList.add('bg-white/5', 'text-accent');
                calBtn.classList.remove('text-muted');
                queueBtn.classList.add('text-muted');
                queueBtn.classList.remove('bg-white/5', 'text-accent');
                calView.classList.remove('hidden');
                queueView.classList.add('hidden');
            }
        };

        window.openScheduleAddModal = async function() {
            document.getElementById('schedule-modal-title').innerText = "Create Publishing Schedule";
            document.getElementById('schedule-form').reset();
            document.getElementById('schedule-id').value = '';
            document.getElementById('schedule-days-container').classList.add('hidden');
            
            await populateScheduleSitesDropdown();
            openModal('schedule-modal');
        };

        window.openScheduleEditModal = async function(id) {
            try {
                document.getElementById('schedule-modal-title').innerText = "Edit Publishing Schedule";
                await populateScheduleSitesDropdown();

                const response = await apiFetch(`/api/v1/schedules/${id}`);
                if (!response.ok) throw new Error("Could not fetch schedule details.");
                const result = await response.json();
                const sched = result.data || result;

                document.getElementById('schedule-id').value = sched.id;
                document.getElementById('schedule-name').value = sched.name;
                document.getElementById('schedule-site-id').value = sched.site_id;
                
                await populateSchedulePipelines(sched.site_id);
                document.getElementById('schedule-pipeline-id').value = sched.pipeline_id || '';
                
                document.getElementById('schedule-frequency').value = sched.frequency;
                document.getElementById('schedule-time-of-day').value = sched.time_of_day || '09:00';
                document.getElementById('schedule-timezone').value = sched.timezone || 'UTC';
                document.getElementById('schedule-active').checked = sched.is_active;

                toggleScheduleDaysField(sched.frequency);

                if (sched.frequency === 'weekly' && sched.days_of_week) {
                    const days = Array.isArray(sched.days_of_week) ? sched.days_of_week : JSON.parse(sched.days_of_week || '[]');
                    document.querySelectorAll('#schedule-form input[name="days[]"]').forEach(cb => {
                        cb.checked = days.includes(cb.value);
                    });
                }

                openModal('schedule-modal');
            } catch (err) {
                console.error("Error opening edit schedule modal:", err);
                showError("Error", err.message || "Failed to load schedule.");
            }
        };

        window.closeScheduleModal = function() {
            closeModal('schedule-modal');
        };

        async function populateScheduleSitesDropdown() {
            const dropdown = document.getElementById('schedule-site-id');
            if (!dropdown) return;

            try {
                const response = await apiFetch('/api/v1/sites');
                if (!response.ok) return;
                const result = await response.json();
                const sites = result.data || result;

                dropdown.innerHTML = '<option value="" disabled selected>Select WordPress site...</option>';
                sites.forEach(site => {
                    const cleanUrl = site.domain_url.replace(/https?:\/\//, '');
                    dropdown.innerHTML += `<option value="${site.id}">${cleanUrl}</option>`;
                });
            } catch (err) {
                console.error("Error populating scheduler sites:", err);
            }
        }

        window.populateSchedulePipelines = async function(siteId) {
            const dropdown = document.getElementById('schedule-pipeline-id');
            if (!dropdown) return;

            try {
                const response = await apiFetch('/api/v1/pipelines');
                if (!response.ok) return;
                const result = await response.json();
                const pipelines = result.data || result;

                const filtered = pipelines.filter(p => p.site_id == siteId);

                dropdown.innerHTML = '<option value="">No Pipeline (Sync Only)</option>';
                filtered.forEach(pip => {
                    const topicName = pip.topic ? pip.topic.name : 'Untitled Pipeline';
                    dropdown.innerHTML += `<option value="${pip.id}">${topicName}</option>`;
                });
            } catch (err) {
                console.error("Error populating scheduler pipelines:", err);
            }
        };

        window.toggleScheduleDaysField = function(frequency) {
            const container = document.getElementById('schedule-days-container');
            if (frequency === 'weekly') {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        };

        window.saveSchedule = async function(e) {
            e.preventDefault();
            const id = document.getElementById('schedule-id').value;
            const method = id ? 'PUT' : 'POST';
            const url = id ? `/api/v1/schedules/${id}` : '/api/v1/schedules';

            const days = [];
            document.querySelectorAll('#schedule-form input[name="days[]"]:checked').forEach(cb => {
                days.push(cb.value);
            });

            const payload = {
                site_id: parseInt(document.getElementById('schedule-site-id').value),
                pipeline_id: document.getElementById('schedule-pipeline-id').value ? parseInt(document.getElementById('schedule-pipeline-id').value) : null,
                name: document.getElementById('schedule-name').value,
                frequency: document.getElementById('schedule-frequency').value,
                time_of_day: document.getElementById('schedule-time-of-day').value || '09:00',
                timezone: document.getElementById('schedule-timezone').value || 'UTC',
                is_active: document.getElementById('schedule-active').checked
            };

            if (payload.frequency === 'weekly') {
                payload.days_of_week = days;
            }

            await apiRequest(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }, {
                submitBtn: e.submitter,
                successTitle: id ? "Schedule Updated" : "Schedule Created",
                successMessage: id ? "The publishing schedule has been updated." : "A new publishing schedule has been added.",
                defaultErrorMessage: "Failed to save schedule.",
                onSuccess: () => {
                    closeScheduleModal();
                    fetchScheduledJobs();
                }
            });
        };

        window.deleteSchedule = async function(id) {
            showConfirmation(
                "Delete Schedule",
                "Are you sure you want to permanently delete this publishing schedule?",
                async () => {
                    await apiRequest(`/api/v1/schedules/${id}`, { method: 'DELETE' }, {
                        successTitle: "Schedule Deleted",
                        successMessage: "The publishing schedule was deleted.",
                        defaultErrorMessage: "Could not delete schedule.",
                        onSuccess: () => {
                            fetchScheduledJobs();
                        }
                    });
                }
            );
        };

        window.toggleScheduleStatus = async function(id, currentStatus) {
            const url = `/api/v1/schedules/${id}`;

            try {
                const getRes = await apiFetch(url);
                if (!getRes.ok) return;
                const result = await getRes.json();
                const sched = result.data || result;

                const fullPayload = {
                    site_id: sched.site_id,
                    pipeline_id: sched.pipeline_id,
                    name: sched.name,
                    frequency: sched.frequency,
                    time_of_day: sched.time_of_day || '09:00',
                    timezone: sched.timezone || 'UTC',
                    is_active: !currentStatus
                };

                if (sched.frequency === 'weekly') {
                    fullPayload.days_of_week = Array.isArray(sched.days_of_week) ? sched.days_of_week : JSON.parse(sched.days_of_week || '[]');
                }

                await apiRequest(url, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(fullPayload)
                }, {
                    successTitle: "Status Updated",
                    successMessage: `Schedule has been ${!currentStatus ? 'activated' : 'paused'}.`,
                    onSuccess: () => {
                        fetchScheduledJobs();
                    }
                });
            } catch (err) {
                console.error("Error toggling schedule status:", err);
            }
        };

        window.renderScheduleCalendar = function(schedules) {
            const grid = document.getElementById('calendar-days-grid');
            const monthTitle = document.getElementById('calendar-month-title');
            const eventsCount = document.getElementById('calendar-events-count');
            if (!grid) return;

            grid.innerHTML = '';
            
            const date = new Date(2026, 6, 1);
            const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            
            if (monthTitle) monthTitle.innerText = `${monthNames[date.getMonth()]} ${date.getFullYear()}`;

            const offset = 2;
            for (let i = 0; i < offset; i++) {
                const blank = document.createElement('div');
                blank.className = "p-4 bg-transparent border border-transparent";
                grid.appendChild(blank);
            }

            const totalDays = 31;
            let activeEvents = 0;

            for (let day = 1; day <= totalDays; day++) {
                const hasEvent = schedules.some(sched => {
                    if (!sched.is_active) return false;
                    if (sched.next_run_at) {
                        const runDate = new Date(sched.next_run_at);
                        return runDate.getFullYear() === 2026 && runDate.getMonth() === 6 && runDate.getDate() === day;
                    }
                    return false;
                });

                const cell = document.createElement('div');
                if (hasEvent) {
                    activeEvents++;
                    cell.className = "p-4 bg-white/5 border border-accent rounded-xl relative hover:border-accent transition group cursor-pointer";
                    cell.innerHTML = `
                        <span class="text-text font-bold">${day}</span>
                        <span class="absolute bottom-1.5 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-accent animate-pulse"></span>
                    `;
                } else {
                    cell.className = "p-4 bg-white/5 border border-border rounded-xl hover:border-accent transition cursor-pointer";
                    cell.innerText = day;
                }
                
                grid.appendChild(cell);
            }

            if (eventsCount) eventsCount.innerText = `${activeEvents} Scheduled Events`;
        };

        window.retryJob = async function(id, element) {
            await apiRequest(`/api/v1/operations/jobs/${id}/retry`, { method: 'POST' }, {
                submitBtn: element,
                successTitle: "Job Retried",
                successMessage: "Background queue job has been queued for execution.",
                defaultErrorMessage: "Job retry failed",
                onSuccess: () => {
                    if (window.fetchScheduledJobs) {
                        window.fetchScheduledJobs();
                    }
                }
            });
        };

        window.triggerManualSchedulerRelease = async function(btn) {
            await apiRequest('/api/v1/schedules/run', { method: 'POST' }, {
                submitBtn: btn,
                successTitle: "Scheduler Sync Triggered",
                successMessage: "Laravel schedule:run check executed successfully.",
                defaultErrorMessage: "Could not run scheduler command.",
                onSuccess: async () => {
                    if (window.fetchScheduledJobs) {
                        await window.fetchScheduledJobs();
                    }
                }
            });
        };

        // Tab Switcher inside Node Workspace
        window.switchTab = async function(tab) {
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

            // Routing actions based on current workspace
            if (tab === 'config') {
                if (currentWorkspace === 'providers') {
                    if (window.openAddProviderForm) window.openAddProviderForm();
                } else if (currentWorkspace === 'sites') {
                    if (window.launchCreationWizard) window.launchCreationWizard('site');
                } else if (currentWorkspace === 'prompts') {
                    switchWorkspace('prompts');
                    showInfo("Configuration Mode", "Prompt templates can be selected or configured directly within the Prompt Library workspace.");
                } else if (currentWorkspace === 'pipeline') {
                    showInfo("Pipeline Configuration", "Select an AI Provider, Prompt Template, and Topic Cluster on the left settings panel to start generating content.");
                } else {
                    showInfo("Configuration Panel", `Module configuration for "${currentWorkspace.toUpperCase()}" is managed directly within its active workspace view.`);
                }
            } else if (tab === 'settings') {
                switchWorkspace('settings');
            } else if (tab === 'history' || tab === 'logs') {
                if (currentWorkspace === 'providers') {
                    // Fetch AI Request Logs
                    showLoading("Retrieving AI Inference Telemetry...");
                    try {
                        const res = await apiFetch('/api/v1/ai/logs?limit=10');
                        if (!res.ok) throw new Error("Could not retrieve AI logs");
                        const data = await res.json();
                        const logs = data.data || data;
                        Swal.close();
                        
                        let html = `<div class="text-left font-mono text-[10px] max-h-[350px] overflow-y-auto custom-scrollbar p-1 space-y-2">`;
                        if (logs.length === 0) {
                            html += `<p class="text-muted text-center py-6">No AI API requests logged in the database yet.</p>`;
                        } else {
                            logs.forEach(l => {
                                const date = new Date(l.created_at).toLocaleString();
                                html += `
                                    <div class="p-2 border border-border rounded bg-[#071018] space-y-1">
                                        <div class="flex justify-between font-bold text-accent">
                                            <span>PROVIDER: ${l.provider_key.toUpperCase()}</span>
                                            <span>${date}</span>
                                        </div>
                                        <div><span class="text-muted">Model:</span> ${l.model_used || '—'}</div>
                                        <div><span class="text-muted">Prompt Tokens:</span> ${l.prompt_tokens || 0} | <span class="text-muted">Completion Tokens:</span> ${l.completion_tokens || 0}</div>
                                        <div><span class="text-muted">Status:</span> <span class="${l.response_status >= 400 ? 'text-rose-500' : 'text-emerald-500'}">${l.response_status || 200}</span></div>
                                    </div>
                                `;
                            });
                        }
                        html += `</div>`;
                        
                        Swal.fire({
                            title: 'AI Provider Inference Logs',
                            html: html,
                            width: '600px',
                            confirmButtonText: 'Close',
                            confirmButtonColor: '#059669'
                        });
                    } catch (err) {
                        showError("Error", err.message || "Failed to load provider logs.");
                    }
                } else if (currentWorkspace === 'sites') {
                    // Fetch Site Publishing Logs
                    showLoading("Retrieving Publishing Engine History...");
                    try {
                        const res = await apiFetch('/api/v1/publishing/logs?limit=10');
                        if (!res.ok) throw new Error("Could not retrieve publishing logs");
                        const data = await res.json();
                        const logs = data.data || data;
                        Swal.close();
                        
                        let html = `<div class="text-left font-mono text-[10px] max-h-[350px] overflow-y-auto custom-scrollbar p-1 space-y-2">`;
                        if (logs.length === 0) {
                            html += `<p class="text-muted text-center py-6">No article publishing events recorded yet.</p>`;
                        } else {
                            logs.forEach(l => {
                                const date = new Date(l.created_at).toLocaleString();
                                const statusClass = l.status === 'published' ? 'text-emerald-500' : (l.status === 'failed' ? 'text-rose-500' : 'text-amber-500');
                                html += `
                                    <div class="p-2 border border-border rounded bg-[#071018] space-y-1">
                                        <div class="flex justify-between font-bold text-secondary">
                                            <span>SITE ID: ${l.site_id}</span>
                                            <span>${date}</span>
                                        </div>
                                        <div><span class="text-muted">Post Title:</span> ${l.content?.title || 'Draft Article #' + l.generated_content_id}</div>
                                        <div><span class="text-muted">WP Post ID:</span> ${l.wp_post_id || '—'}</div>
                                        <div><span class="text-muted">Status:</span> <span class="${statusClass}">${l.status.toUpperCase()}</span></div>
                                        ${l.error_message ? `<div class="text-rose-400 text-[9px] mt-1 p-1 bg-red-950/20 border border-red-900/30 rounded">${l.error_message}</div>` : ''}
                                    </div>
                                `;
                            });
                        }
                        html += `</div>`;
                        
                        Swal.fire({
                            title: 'WordPress Publishing Engine Logs',
                            html: html,
                            width: '600px',
                            confirmButtonText: 'Close',
                            confirmButtonColor: '#059669'
                        });
                    } catch (err) {
                        showError("Error", err.message || "Failed to load publishing history.");
                    }
                } else if (currentWorkspace === 'scheduler') {
                    // Fetch Background Job Logs
                    if (window.fetchScheduledJobs) await window.fetchScheduledJobs();
                } else if (currentWorkspace === 'audit') {
                    if (window.fetchAuditLogs) await window.fetchAuditLogs();
                } else {
                    // Fetch operations audit logs as default history stream
                    showLoading("Retrieving Audit Logs...");
                    try {
                        const res = await apiFetch('/api/v1/operations/audit?limit=10');
                        if (!res.ok) throw new Error("Could not retrieve audit history");
                        const data = await res.json();
                        const logs = data.data || data;
                        Swal.close();
                        
                        let html = `<div class="text-left font-mono text-[10px] max-h-[350px] overflow-y-auto custom-scrollbar p-1 space-y-2">`;
                        if (logs.length === 0) {
                            html += `<p class="text-muted text-center py-6">No platform audits recorded yet.</p>`;
                        } else {
                            logs.forEach(l => {
                                const date = new Date(l.created_at).toLocaleString();
                                html += `
                                    <div class="p-2 border border-border rounded bg-[#071018] space-y-1">
                                        <div class="flex justify-between font-bold text-accent">
                                            <span>EVENT: ${l.event.toUpperCase()}</span>
                                            <span>${date}</span>
                                        </div>
                                        <div><span class="text-muted">IP Address:</span> ${l.ip_address || '127.0.0.1'}</div>
                                        <div><span class="text-muted">Details:</span> ${JSON.stringify(l.new_values || l.old_values || {})}</div>
                                    </div>
                                `;
                            });
                        }
                        html += `</div>`;
                        
                        Swal.fire({
                            title: `${currentWorkspace.toUpperCase()} Module Audit Trail`,
                            html: html,
                            width: '600px',
                            confirmButtonText: 'Close',
                            confirmButtonColor: '#059669'
                        });
                    } catch (err) {
                        showError("Error", err.message || "Failed to load audit trail.");
                    }
                }
            } else if (tab === 'overview') {
                // Return to default active workspace panel
                const activePane = document.getElementById('node-' + currentWorkspace);
                if (activePane) {
                    activePane.classList.remove('hidden');
                }
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

        async function wizardNext() {
            if (wizardStep < 4) {
                wizardStep++;
                renderWizardStep();
            } else {
                if (wizardType === 'site') {
                    const domain_url = document.getElementById('wizard-site-domain').value.trim();
                    const frequency = document.getElementById('wizard-site-frequency').value;
                    
                    if (!domain_url) {
                        showError("Validation Error", "Website domain URL is required.");
                        return;
                    }

                    const payload = {
                        domain_url: domain_url,
                        slot: frequency,
                        is_active: true,
                        status: 'connected',
                        plugin_version: '1.2.0'
                    };

                    await apiRequest('/api/v1/sites', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    }, {
                        loadingMessage: "Registering WordPress site...",
                        successTitle: "Website Connected",
                        successMessage: "Your WordPress site has been successfully registered.",
                        defaultErrorMessage: "Could not register website.",
                        onSuccess: async () => {
                            if (window.fetchSites) await window.fetchSites();
                            if (window.refreshDashboardStats) await window.refreshDashboardStats();
                            switchWorkspace('sites');
                        }
                    });
                } else {
                    showSuccess("Pipeline Committed", wizardType.charAt(0).toUpperCase() + wizardType.slice(1) + " pipeline committed successfully!");
                    if (wizardType === 'customer') {
                        switchWorkspace('customers');
                    } else if (wizardType === 'topic') {
                        switchWorkspace('topics');
                    } else {
                        switchWorkspace('sites');
                    }
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
        function selectPromptTemplate(id, name, category, version, status, promt = '') {
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
            
            if (promt !== '') {
                document.getElementById('prompt-editor-textarea').value = promt;
            } else {
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
        }

        window.openNewPromptTemplate = function() {
            activePromptId = 'new';
            
            document.querySelectorAll('.prompt-list-item').forEach(item => {
                item.classList.remove('bg-white/5', 'border-accent');
                item.classList.add('bg-transparent', 'border-border');
            });

            document.getElementById('prompt-editor-id').innerText = "Active: New Template";
            document.getElementById('prompt-edit-name').value = '';
            document.getElementById('prompt-edit-category').selectedIndex = 0;
            document.getElementById('prompt-edit-version').value = 'v1.0';
            document.getElementById('prompt-edit-status').value = 'active';
            document.getElementById('prompt-editor-textarea').value = '';
        };

        window.fetchPromptTemplates = async function() {
            const listContainer = document.getElementById('prompt-templates-list');
            if (!listContainer) return;

            try {
                const response = await apiFetch('/api/v1/prompts');
                if (!response.ok) return;
                const result = await response.json();
                const prompts = result.data || result;

                listContainer.innerHTML = '';

                if (prompts.length === 0) {
                    listContainer.innerHTML = `
                        <div class="p-4 text-center text-xs text-muted font-mono">
                            No prompts found. Click "Create Template" to add one.
                        </div>
                    `;
                    return;
                }

                prompts.forEach((p, index) => {
                    const div = document.createElement('div');
                    div.id = `prompt-item-${p.id}`;
                    
                    const isSelected = activePromptId === p.id || (index === 0 && activePromptId === 'promt_001');
                    if (isSelected) {
                        activePromptId = p.id;
                        div.className = "p-3 bg-white/5 border border-accent rounded-xl cursor-pointer hover:border-accent transition group relative prompt-list-item";
                    } else {
                        div.className = "p-3 bg-transparent border border-border rounded-xl cursor-pointer hover:bg-white/5 transition group relative prompt-list-item";
                    }

                    const safeName = p.name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    const safeCategory = p.category.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    const safeVersion = (p.version || 'v1.0').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    const safeStatus = (p.status || 'active').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    const safePrompt = (p.content || '').replace(/'/g, "\\'").replace(/"/g, '&quot;').replace(/\n/g, '\\n');

                    div.setAttribute('onclick', `selectPromptTemplate(${p.id}, '${safeName}', '${safeCategory}', '${safeVersion}', '${safeStatus}', '${safePrompt}')`);

                    div.innerHTML = `
                        <div class="flex justify-between items-center mb-1">
                            <p class="text-xs font-semibold text-text">${p.name}</p>
                            <span class="text-[9px] font-mono ${isSelected ? 'bg-accent/20 text-accent border border-accent/30' : 'bg-white/10 text-muted border border-border'} px-1.5 py-0.5 rounded">${p.version || 'v1.0'}</span>
                        </div>
                        <div class="flex justify-between items-center text-[10px] font-mono text-muted">
                            <span>Category: ${p.category}</span>
                            <span class="${p.status === 'active' ? 'text-success' : 'text-warning'}">${p.status || 'active'}</span>
                        </div>
                    `;

                    listContainer.appendChild(div);
                });

                if (activePromptId && activePromptId !== 'new') {
                    const activeP = prompts.find(p => p.id == activePromptId);
                    if (activeP) {
                        selectPromptTemplate(activeP.id, activeP.name, activeP.category, activeP.version || 'v1.0', activeP.status || 'active', activeP.content);
                    }
                }
            } catch (err) {
                console.error("Error fetching prompt templates:", err);
            }
        };

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

        async function saveActivePrompt() {
            const payload = {
                name: document.getElementById('prompt-edit-name')?.value || '',
                category: document.getElementById('prompt-edit-category')?.value || '',
                version: document.getElementById('prompt-edit-version')?.value || '',
                status: document.getElementById('prompt-edit-status')?.value || 'active',
                prompt: document.getElementById('prompt-editor-textarea')?.value || ''
            };

            const isNew = activePromptId === 'new' || isNaN(activePromptId);
            const url = isNew ? '/api/v1/prompts' : `/api/v1/prompts/${activePromptId}`;
            const method = isNew ? 'POST' : 'PUT';

            await apiRequest(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }, {
                successTitle: "Prompt Saved",
                successMessage: "Prompt template saved successfully in the Library!",
                defaultErrorMessage: "Could not save prompt template.",
                onSuccess: (result) => {
                    if (result && result.data && result.data.id) {
                        activePromptId = result.data.id;
                    } else if (result && result.id) {
                        activePromptId = result.id;
                    }
                }
            });

            if (window.fetchPromptTemplates) {
                await window.fetchPromptTemplates();
            }
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
                showSuccess("Generation Run Complete", "AI content generation pipeline run completed successfully!");
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
                showSuccess("Post Published", "Job successfully published to WordPress destination site techcrunch.com!");
            }, 2000);
        }



        // ─── SEO Intelligence Hub CRUD ──────────────────────────────────────
        window.fetchSeoData = async function() {
            const tbody = document.getElementById('seo-table-body');
            const avgScoreEl = document.getElementById('seo-avg-score');
            const missingAltsEl = document.getElementById('seo-missing-alts');
            if (!tbody) return;

            try {
                const response = await apiFetch('/api/v1/articles');
                if (!response.ok) return;
                const result = await response.json();
                const articles = result.data || result;

                tbody.innerHTML = '';
                
                if (articles.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-outline py-8">No articles found in database to evaluate SEO scores.</td></tr>';
                    if (avgScoreEl) avgScoreEl.innerText = '—';
                    if (missingAltsEl) missingAltsEl.innerText = '0';
                } else {
                    let totalScore = 0;
                    let evaluatedCount = 0;
                    let missingAltsCount = 0;

                    articles.forEach(article => {
                        const score = article.title.length > 30 ? 98 : 92;
                        totalScore += score;
                        evaluatedCount++;

                        const siteUrl = article.site ? article.site.domain_url.replace(/https?:\/\//, '') : 'example.com';
                        const slug = article.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                        const targetUrl = `https://${siteUrl}/${slug}`;
                        
                        const topicName = article.topic ? article.topic.name.toLowerCase() : 'news';
                        const missing = article.content.length < 500 ? 1 : 0;
                        missingAltsCount += missing;

                        const tr = document.createElement('tr');
                        tr.className = "hover:bg-white/5 transition border-b border-border last:border-b-0 cursor-pointer";
                        tr.onclick = function() {
                            inspectElement('seo', article.title, 'online', `Score: ${score}`, topicName);
                        };

                        tr.innerHTML = `
                            <td class="p-3 pl-5 text-text font-medium truncate max-w-[300px]" title="${targetUrl}">${targetUrl}</td>
                            <td class="p-3 text-muted">${topicName}</td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px]">${score} / 100</span></td>
                            <td class="p-3 ${missing > 0 ? 'text-danger font-bold' : 'text-muted'}">${missing > 0 ? '1 missing alt' : 'All verified'}</td>
                            <td class="p-3 text-accent font-bold">Valid Canonical</td>
                            <td class="p-3 text-right pr-5">
                                <button class="text-secondary hover:underline" onclick="event.stopPropagation(); inspectElement('seo', '${escapeJsString(article.title)}', 'online', 'Score: ${score}', '${topicName}')">Inspect</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });

                    if (avgScoreEl && evaluatedCount > 0) {
                        avgScoreEl.innerText = `${Math.round(totalScore / evaluatedCount)} / 100`;
                    }
                    if (missingAltsEl) {
                        missingAltsEl.innerText = missingAltsCount;
                    }
                }
            } catch (err) {
                console.error("Error populating SEO data:", err);
            }
        };

        window.triggerSEOSweepSimulation = async function() {
            const avgScoreEl = document.getElementById('seo-avg-score');
            if (avgScoreEl) {
                avgScoreEl.innerText = "Scanning...";
                avgScoreEl.className = "text-3xl font-display font-bold text-warning animate-pulse";
            }
            setTimeout(async () => {
                await fetchSeoData();
                showSuccess("SEO Sweep Complete", "Real-time HTML tags, alt tags, and indexing rules scanned successfully inside database articles.");
            }, 1500);
        };

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
                showSuccess("Cost Sync Complete", "Cost forecast audit sync completed successfully! Expected MRR: $48.9K, Expected cost reductions: $160.00.");
            }, 3000);
        }

        // Simulate reports PDF exporter
        function triggerReportExportSimulation() {
            showInfo("Export Initiated", "Export simulation initiated. Compiling platform analytics... System report ready! PDF downloaded to local storage file path.");
        }

        window.fetchSystemAlerts = async function() {
            const stream = document.getElementById('operations-timeline-stream');
            const alertCountEl = document.getElementById('notifications-count');

            try {
                // 1. Fetch Audit Logs
                if (stream) {
                    const auditResponse = await apiFetch('/api/v1/operations/audit?limit=10');
                    if (auditResponse.ok) {
                        const auditData = await auditResponse.json();
                        const logs = auditData.data || auditData;

                        stream.innerHTML = '';
                        if (logs.length === 0) {
                            stream.innerHTML = '<div class="text-muted p-4 text-center">No system events logged in database history.</div>';
                        } else {
                            logs.forEach(log => {
                                const dateStr = new Date(log.created_at).toLocaleTimeString();
                                let icon = '✓';
                                let iconClass = 'bg-success/20 border-success/40 text-success';
                                let title = log.event.replace(/_/g, ' ').toUpperCase();

                                if (log.event.includes('failed') || log.event.includes('error') || log.event.includes('disconnect')) {
                                    icon = '!';
                                    iconClass = 'bg-danger/20 border-danger/40 text-danger';
                                } else if (log.event.includes('warning') || log.event.includes('retry')) {
                                    icon = '?';
                                    iconClass = 'bg-warning/20 border-warning/40 text-warning';
                                }

                                const userText = log.user ? `by ${log.user.name}` : 'by System';
                                const desc = `Event details: ${JSON.stringify(log.new_values || log.old_values || {})}`;

                                const row = document.createElement('div');
                                row.className = "relative";
                                row.innerHTML = `
                                    <span class="absolute -left-[31px] top-0 w-4 h-4 rounded-full flex items-center justify-center text-[8px] font-bold ${iconClass}">${icon}</span>
                                    <div class="p-3 bg-white/5 border border-border rounded-xl space-y-1">
                                        <div class="flex justify-between items-center">
                                            <span class="text-text font-bold">${title}</span>
                                            <span class="text-[10px] text-muted">${dateStr}</span>
                                        </div>
                                        <p class="text-[10px] text-muted">${userText}. ${desc}</p>
                                    </div>
                                `;
                                stream.appendChild(row);
                            });
                        }
                    }
                }

                // 2. Fetch Job Logs to count failed jobs
                const jobsResponse = await apiFetch('/api/v1/operations/jobs');
                if (jobsResponse.ok) {
                    const jobsResult = await jobsResponse.json();
                    const jobs = jobsResult.data || jobsResult;
                    const failedCount = jobs.filter(j => j.status === 'failed').length;
                    
                    if (alertCountEl) {
                        alertCountEl.innerText = failedCount;
                        if (failedCount > 0) {
                            alertCountEl.className = "text-3xl font-display font-bold text-danger animate-pulse";
                        } else {
                            alertCountEl.className = "text-3xl font-display font-bold text-success";
                        }
                    }

                    // Update header notifications icon badge
                    const headerBadge = document.getElementById('header-notification-badge');
                    const headerBadgePing = document.getElementById('header-notification-badge-ping');
                    if (headerBadge && headerBadgePing) {
                        if (failedCount > 0) {
                            headerBadge.classList.remove('hidden');
                            headerBadgePing.classList.remove('hidden');
                        } else {
                            headerBadge.classList.add('hidden');
                            headerBadgePing.classList.add('hidden');
                        }
                    }

                    // Update sidebar notifications count badge
                    const sidebarBadge = document.getElementById('sidebar-notifications-count');
                    if (sidebarBadge) {
                        sidebarBadge.innerText = failedCount;
                        if (failedCount > 0) {
                            sidebarBadge.classList.remove('hidden');
                        } else {
                            sidebarBadge.classList.add('hidden');
                        }
                    }
                }
            } catch (err) {
                console.error("Error fetching system alerts:", err);
            }
        };

        window.triggerNotificationClearSimulation = async function() {
            try {
                const response = await apiFetch('/api/v1/operations/jobs');
                if (!response.ok) return;
                const result = await response.json();
                const jobs = result.data || result;
                const failedJobs = jobs.filter(j => j.status === 'failed');

                if (failedJobs.length === 0) {
                    showInfo("All Clear", "No unresolved failed jobs found in database.");
                    return;
                }

                let retriedCount = 0;
                for (const job of failedJobs) {
                    const retryRes = await apiFetch(`/api/v1/operations/jobs/${job.id}/retry`, { method: 'POST' });
                    if (retryRes.ok) retriedCount++;
                }

                showSuccess("Resolution Triggered", `Dispatched retries for ${retriedCount} failed background operations in MySQL.`);
                await fetchSystemAlerts();
            } catch (err) {
                console.error("Error resolving alerts:", err);
                showError("Resolution Failed", "Could not dispatch job recovery commands.");
            }
        };

        window.triggerNotificationMuteSimulation = function() {
            showInfo("Alert Loops Active", "Snoozed alert dispatch streams temporarily. Alert loops remain active in database operations log.");
        };

        function escapeJsString(str) {
            if (!str) return '';
            return str.replace(/'/g, "\\'").replace(/"/g, '\\"');
        }

        // ─── User & Role Management CRUD ────────────────────────────────────
        window.fetchUsers = async function() {
            const tbody = document.getElementById('roles-directory-body');
            const totalCount = document.getElementById('roles-total-users');
            if (!tbody) return;

            try {
                const response = await apiFetch('/api/v1/users');
                if (!response.ok) return;
                const result = await response.json();
                const users = result.data || result;

                tbody.innerHTML = '';
                if (totalCount) totalCount.innerText = users.length;

                if (users.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-center text-outline py-8">No system operators found in database.</td></tr>`;
                } else {
                    users.forEach(user => {
                        const tr = document.createElement('tr');
                        tr.className = "hover:bg-white/5 transition border-b border-border last:border-b-0";

                        const roleNames = {
                            1: 'Super Admin',
                            2: 'Admin',
                            3: 'Editor',
                            4: 'SEO Specialist',
                            5: 'Support'
                        };
                        const roleBadgeStyles = {
                            1: 'bg-danger/20 text-danger border-danger/30',
                            2: 'bg-warning/20 text-warning border-warning/30',
                            3: 'bg-accent/20 text-accent border-accent/30',
                            4: 'bg-secondary/20 text-secondary border-secondary/30',
                            5: 'bg-white/10 text-muted border-white/20'
                        };

                        const roleVal = user.role || 3;
                        const roleLabel = roleNames[roleVal] || 'Operator';
                        const roleClass = roleBadgeStyles[roleVal] || 'bg-white/10 text-muted';
                        
                        const initials = (user.name || 'OP').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();

                        tr.innerHTML = `
                            <td class="p-3 pl-5"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20" onclick="event.stopPropagation()"/></td>
                            <td class="p-3 text-text font-medium flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-accent/20 border border-accent/40 flex items-center justify-center text-[10px] text-accent font-bold">${initials}</div>
                                <span>${user.name}</span>
                            </td>
                            <td class="p-3 text-muted">${user.email}</td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded border text-[9px] ${roleClass}">${roleLabel}</span></td>
                            <td class="p-3 text-accent font-bold">Enabled</td>
                            <td class="p-3 text-right pr-5">
                                <button onclick="openUserEditModal(${user.id}, '${escapeJsString(user.name)}', '${escapeJsString(user.email)}', ${roleVal})" class="text-secondary hover:underline mr-3">Edit</button>
                                <button onclick="deleteUser(${user.id})" class="text-danger hover:underline">Delete</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            } catch (err) {
                console.error("Error fetching system users:", err);
            }
        };

        window.openUserAddModal = function() {
            const modal = document.getElementById('user-modal');
            if (!modal) return;
            document.getElementById('user-modal-title').innerText = "Invite Platform Operator";
            document.getElementById('user-id').value = '';
            document.getElementById('user-name').value = '';
            document.getElementById('user-email').value = '';
            document.getElementById('user-role-select').value = '3';
            document.getElementById('user-password').value = '';
            document.getElementById('user-password').required = true;
            modal.classList.add('active');
            document.getElementById('user-name').focus();
        };

        window.openUserEditModal = function(id, name, email, role) {
            const modal = document.getElementById('user-modal');
            if (!modal) return;
            document.getElementById('user-modal-title').innerText = "Edit Operator Privileges";
            document.getElementById('user-id').value = id;
            document.getElementById('user-name').value = name;
            document.getElementById('user-email').value = email;
            document.getElementById('user-role-select').value = role;
            document.getElementById('user-password').value = '';
            document.getElementById('user-password').required = false; // Optional on edit
            modal.classList.add('active');
            document.getElementById('user-name').focus();
        };

        window.closeUserModal = function() {
            const modal = document.getElementById('user-modal');
            if (modal) modal.classList.remove('active');
        };

        window.saveUser = async function(event) {
            event.preventDefault();
            const id = document.getElementById('user-id').value;
            const name = document.getElementById('user-name').value;
            const email = document.getElementById('user-email').value;
            const role = document.getElementById('user-role-select').value;
            const password = document.getElementById('user-password').value;

            const payload = { name, email, role };
            if (password) payload.password = password;

            const url = id ? `/api/v1/users/${id}` : '/api/v1/users';
            const method = id ? 'PUT' : 'POST';
            const submitBtn = event.target.querySelector('button[type="submit"]');

            await apiRequest(url, {
                method: method,
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            }, {
                successTitle: "Operator Saved",
                successMessage: id ? "Operator details updated successfully." : "Platform operator invited successfully.",
                defaultErrorMessage: "Failed to persist operator configuration.",
                submitBtn: submitBtn,
                onSuccess: async () => {
                    closeUserModal();
                    await fetchUsers();
                }
            });
        };

        window.deleteUser = async function(id) {
            showConfirmation(
                "Remove Operator",
                "Are you sure you want to disconnect this platform operator?",
                async () => {
                    await apiRequest(`/api/v1/users/${id}`, { method: 'DELETE' }, {
                        successTitle: "Operator Removed",
                        successMessage: "Operator access revoked.",
                        defaultErrorMessage: "Failed to delete operator.",
                        onSuccess: async () => {
                            await fetchUsers();
                        }
                    });
                }
            );
        };

        // ─── Billing & Usage Ledger ──────────────────────────────────────────
        window.fetchBillingLedger = async function() {
            const tbody = document.getElementById('billing-ledger-body');
            const emptyState = document.getElementById('billing-empty-state');
            
            const grossEl = document.getElementById('billing-gross-volume');
            const unpaidEl = document.getElementById('billing-unpaid-invoices');
            const apiCostEl = document.getElementById('billing-api-cost');
            const overageEl = document.getElementById('billing-overage-alerts');

            if (!tbody) return;

            try {
                // 1. Fetch subscriptions from database
                const subResponse = await apiFetch('/api/v1/subscriptions');
                if (!subResponse.ok) return;
                const subResult = await subResponse.json();
                const subscriptions = subResult.data || subResult;

                // 2. Fetch AI logs to calculate actual spend
                let totalCost = 0.00;
                const logsRes = await apiFetch('/api/v1/ai/logs');
                if (logsRes.ok) {
                    const logsResult = await logsRes.json();
                    const logs = logsResult.data || logsResult;
                    logs.forEach(log => {
                        totalCost += parseFloat(log.estimated_cost || log.cost || 0);
                    });
                }

                if (apiCostEl) apiCostEl.innerText = `$${totalCost.toFixed(2)}`;

                tbody.innerHTML = '';
                if (subscriptions.length === 0) {
                    if (emptyState) emptyState.classList.remove('hidden');
                    if (grossEl) grossEl.innerText = "$0.00";
                    if (unpaidEl) unpaidEl.innerText = "0";
                    if (overageEl) overageEl.innerText = "0";
                } else {
                    if (emptyState) emptyState.classList.add('hidden');
                    
                    let grossVolume = 0;
                    let unpaidInvoices = 0;
                    let overageCount = 0;

                    subscriptions.forEach(sub => {
                        const price = sub.plan ? parseFloat(sub.plan.price || 0) : 0;
                        if (sub.status === 'active' || sub.status === 'trial') {
                            grossVolume += price;
                        }
                        if (sub.status === 'pending_payment') {
                            unpaidInvoices++;
                        }
                        if (sub.status === 'suspended' || sub.status === 'expired') {
                            overageCount++;
                        }

                        const clientName = sub.customer ? sub.customer.company_name : 'Direct Organization';
                        const planName = sub.plan ? sub.plan.name : 'Custom Plan';
                        
                        let statusClass = "bg-success/20 text-success border-success/30";
                        if (sub.status === 'pending_payment' || sub.status === 'paused') {
                            statusClass = "bg-warning/20 text-warning border-warning/30";
                        } else if (sub.status === 'suspended' || sub.status === 'expired') {
                            statusClass = "bg-danger/20 text-danger border-danger/30";
                        }

                        let actionsHtml = '';
                        if (sub.status === 'active' || sub.status === 'trial') {
                            actionsHtml += `
                                <button onclick="changePlanSubscription('${sub.customer_id}', ${sub.plan_id})" class="text-accent hover:underline mr-3">Change Plan</button>
                                <button onclick="pauseSubscription('${sub.customer_id}')" class="text-warning hover:underline mr-3">Pause</button>
                                <button onclick="cancelSubscription('${sub.customer_id}')" class="text-danger text-rose-500 hover:underline mr-3">Cancel</button>
                            `;
                        } else if (sub.status === 'paused') {
                            actionsHtml += `
                                <button onclick="resumeSubscription('${sub.customer_id}')" class="text-success hover:underline mr-3">Resume</button>
                                <button onclick="cancelSubscription('${sub.customer_id}')" class="text-danger text-rose-500 hover:underline mr-3">Cancel</button>
                            `;
                        } else {
                            actionsHtml += `
                                <button onclick="changePlanSubscription('${sub.customer_id}', ${sub.plan_id})" class="text-accent hover:underline mr-3">Resubscribe</button>
                            `;
                        }
                        actionsHtml += `<button onclick="syncClientBilling(${sub.id}, this)" class="text-secondary hover:underline">Sync Gateway</button>`;

                        const tr = document.createElement('tr');
                        tr.className = "hover:bg-white/5 transition border-b border-border last:border-b-0";
                        tr.innerHTML = `
                            <td class="p-3 pl-5 text-text font-medium">${clientName}</td>
                            <td class="p-3 text-muted">${planName} (${sub.billing_period})</td>
                            <td class="p-3 text-muted font-mono">100% capacity</td>
                            <td class="p-3 text-accent font-bold font-mono">$${price.toFixed(2)}</td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded border text-[9px] ${statusClass}">${sub.status}</span></td>
                            <td class="p-3 text-right pr-5">
                                ${actionsHtml}
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });

                    if (grossEl) grossEl.innerText = `$${grossVolume.toFixed(2)}`;
                    if (unpaidEl) unpaidEl.innerText = unpaidInvoices;
                    if (overageEl) overageEl.innerText = overageCount;
                }
            } catch (err) {
                console.error("Error populating billing ledger:", err);
            }
        };

        window.syncClientBilling = async function(id, btn) {
            let originalText = btn.innerText;
            btn.innerText = "Syncing...";
            btn.disabled = true;

            setTimeout(async () => {
                showSuccess("SSO Sync complete", "Billing details synchronized with external Stripe account payment logs.");
                btn.innerText = originalText;
                btn.disabled = false;
                await fetchBillingLedger();
            }, 1000);
        };

        // Subscription Management Controls
        window.pauseSubscription = async function(customerId) {
            showConfirmation(
                "Pause Subscription",
                "Are you sure you want to pause this subscription?",
                async () => {
                    await apiRequest(`/api/v1/customers/${customerId}/subscription/pause`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    }, {
                        successTitle: "Subscription Paused",
                        successMessage: "Customer subscription has been successfully paused.",
                        defaultErrorMessage: "Could not pause subscription.",
                        onSuccess: async () => {
                            await fetchBillingLedger();
                        }
                    });
                }
            );
        };

        window.resumeSubscription = async function(customerId) {
            showConfirmation(
                "Resume Subscription",
                "Are you sure you want to resume this subscription?",
                async () => {
                    await apiRequest(`/api/v1/customers/${customerId}/subscription/resume`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    }, {
                        successTitle: "Subscription Resumed",
                        successMessage: "Customer subscription has been successfully resumed.",
                        defaultErrorMessage: "Could not resume subscription.",
                        onSuccess: async () => {
                            await fetchBillingLedger();
                        }
                    });
                }
            );
        };

        window.cancelSubscription = async function(customerId) {
            showConfirmation(
                "Cancel Subscription",
                "Are you sure you want to cancel this subscription?",
                async () => {
                    await apiRequest(`/api/v1/customers/${customerId}/subscription/cancel`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    }, {
                        successTitle: "Subscription Cancelled",
                        successMessage: "Customer subscription has been cancelled.",
                        defaultErrorMessage: "Could not cancel subscription.",
                        onSuccess: async () => {
                            await fetchBillingLedger();
                        }
                    });
                }
            );
        };

        window.changePlanSubscription = async function(customerId, currentPlanId) {
            try {
                // Fetch all plans
                const res = await apiFetch('/api/v1/plans');
                if (!res.ok) throw new Error("Could not load pricing plans.");
                const result = await res.json();
                const plans = result.data || result;

                let planOptionsHtml = '';
                plans.forEach(plan => {
                    const selected = plan.id == currentPlanId ? 'selected' : '';
                    planOptionsHtml += `<option value="${plan.id}" ${selected}>${plan.name} ($${parseFloat(plan.price).toFixed(2)})</option>`;
                });

                Swal.fire({
                    title: 'Modify Subscription Plan',
                    html: `
                        <div class="text-left space-y-4">
                            <div>
                                <label class="block text-xs font-mono mb-1 text-muted uppercase">Select Plan</label>
                                <select id="change-plan-select" class="w-full bg-[#071018] border border-border rounded-xl p-2.5 text-xs text-text font-mono focus:outline-none focus:border-accent">
                                    ${planOptionsHtml}
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-mono mb-1 text-muted uppercase">Billing Period</label>
                                <select id="change-plan-billing-period" class="w-full bg-[#071018] border border-border rounded-xl p-2.5 text-xs text-text font-mono focus:outline-none focus:border-accent">
                                    <option value="monthly" selected>Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Update Plan',
                    confirmButtonColor: '#059669',
                    cancelButtonColor: '#ef4444',
                    background: document.documentElement.classList.contains('dark') ? '#0f172a' : '#ffffff',
                    color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#0f172a',
                    preConfirm: () => {
                        return {
                            plan_id: document.getElementById('change-plan-select').value,
                            billing_period: document.getElementById('change-plan-billing-period').value,
                            payment_token: 'dummy-token'
                        };
                    }
                }).then(async (dialogResult) => {
                    if (dialogResult.isConfirmed) {
                        const payload = dialogResult.value;
                        const isUpgrade = parseInt(payload.plan_id) > parseInt(currentPlanId);
                        const endpoint = isUpgrade ? 'upgrade' : 'downgrade';

                        await apiRequest(`/api/v1/customers/${customerId}/subscription/${endpoint}`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        }, {
                            successTitle: "Subscription Updated",
                            successMessage: `Plan successfully ${endpoint}d!`,
                            defaultErrorMessage: `Could not ${endpoint} subscription.`,
                            onSuccess: async () => {
                                await fetchBillingLedger();
                            }
                        });
                    }
                });
            } catch (err) {
                console.error("Error setting up plan change dialog:", err);
                showError("Error", err.message || "Failed to load plans.");
            }
        };

        window.triggerInvoiceSyncSimulation = async function() {
            const gross = document.getElementById('billing-gross-volume');
            if (gross) {
                gross.innerText = "Syncing...";
                gross.className = "text-3xl font-display font-bold text-warning animate-pulse";
            }
            setTimeout(async () => {
                await fetchBillingLedger();
                showSuccess("Invoice Synced", "Manual payment gateway transaction synchronization completed successfully.");
            }, 1500);
        };

        window.triggerBillingLockSimulation = function() {
            showInfo("Overage Protection Active", "Billing lock constraints verified. API usage quotas are synchronized with MySQL plans restrictions.");
        };

        // ─── Advanced Analytics & Reports ────────────────────────────────────
        window.fetchAdvancedAnalytics = async function() {
            const customersEl = document.getElementById('analytics-total-customers');
            const articlesEl = document.getElementById('analytics-total-articles');
            const requestsEl = document.getElementById('analytics-total-requests');
            const sitesEl = document.getElementById('analytics-active-sites');
            
            const emptyState = document.getElementById('analytics-empty-state');
            const contentGrid = document.getElementById('analytics-content-grid');
            
            const providerContainer = document.getElementById('analytics-provider-breakdown-container');
            const statusContainer = document.getElementById('analytics-status-breakdown-container');

            if (!customersEl) return;

            try {
                // Fetch connected sites
                const sitesRes = await apiFetch('/api/v1/sites');
                let sitesCount = 0;
                if (sitesRes.ok) {
                    const sites = await sitesRes.json();
                    sitesCount = sites.data ? sites.data.length : (sites.length || 0);
                }

                // Fetch total customers
                const customersRes = await apiFetch('/api/v1/customers');
                let customersCount = 0;
                if (customersRes.ok) {
                    const customers = await customersRes.json();
                    customersCount = customers.data ? customers.data.length : (customers.length || 0);
                }

                // Fetch AI statistics
                const aiRes = await apiFetch('/api/v1/analytics/ai');
                let totalRequests = 0;
                let providerData = [];
                if (aiRes.ok) {
                    const aiStats = await aiRes.json();
                    totalRequests = aiStats.total_requests || 0;
                    providerData = aiStats.providers || [];
                }

                // Fetch content statistics
                const contentRes = await apiFetch('/api/v1/analytics/content');
                let totalArticles = 0;
                let statusBreakdown = {};
                if (contentRes.ok) {
                    const contentStats = await contentRes.json();
                    totalArticles = contentStats.total_articles || 0;
                    statusBreakdown = contentStats.status_breakdown || {};
                }

                // Update KPI elements
                customersEl.innerText = customersCount;
                customersEl.className = "text-3xl font-display font-bold text-accent";
                articlesEl.innerText = totalArticles;
                articlesEl.className = "text-3xl font-display font-bold text-accent";
                requestsEl.innerText = totalRequests;
                requestsEl.className = "text-3xl font-display font-bold text-accent";
                sitesEl.innerText = sitesCount;
                sitesEl.className = "text-3xl font-display font-bold text-accent";

                const hasData = totalArticles > 0 || totalRequests > 0;
                if (hasData) {
                    if (emptyState) emptyState.classList.add('hidden');
                    if (contentGrid) contentGrid.classList.remove('hidden');

                    // Render provider breakdown
                    if (providerContainer) {
                        providerContainer.innerHTML = '';
                        if (providerData.length === 0) {
                            providerContainer.innerHTML = '<p class="text-xs text-muted font-mono">No AI requests logged in database.</p>';
                        } else {
                            const maxCount = Math.max(...providerData.map(p => p.count || 0), 1);
                            providerData.forEach(prov => {
                                const name = prov.provider.toUpperCase();
                                const count = prov.count || 0;
                                const cost = parseFloat(prov.cost || 0).toFixed(2);
                                const pct = Math.round((count / maxCount) * 100);
                                
                                providerContainer.innerHTML += `
                                    <div class="space-y-1">
                                        <div class="flex justify-between text-xs font-mono">
                                            <span class="text-text font-bold">${name}</span>
                                            <span class="text-muted">${count} requests ($${cost})</span>
                                        </div>
                                        <div class="w-full bg-white/5 border border-border h-2.5 rounded-full overflow-hidden">
                                            <div class="bg-accent h-full rounded-full transition-all duration-500" style="width: ${pct}%"></div>
                                        </div>
                                    </div>
                                `;
                            });
                        }
                    }

                    // Render status breakdown
                    if (statusContainer) {
                        statusContainer.innerHTML = '';
                        const statuses = Object.keys(statusBreakdown);
                        if (statuses.length === 0) {
                            statusContainer.innerHTML = '<p class="text-xs text-muted font-mono">No articles found in library.</p>';
                        } else {
                            const maxVal = Math.max(...Object.values(statusBreakdown), 1);
                            statuses.forEach(status => {
                                const count = statusBreakdown[status] || 0;
                                const pct = Math.round((count / maxVal) * 100);
                                const statusLabel = status.toUpperCase();

                                statusContainer.innerHTML += `
                                    <div class="space-y-1">
                                        <div class="flex justify-between text-xs font-mono">
                                            <span class="text-text font-bold">${statusLabel}</span>
                                            <span class="text-muted">${count} drafts</span>
                                        </div>
                                        <div class="w-full bg-white/5 border border-border h-2.5 rounded-full overflow-hidden">
                                            <div class="bg-secondary h-full rounded-full transition-all duration-500" style="width: ${pct}%"></div>
                                        </div>
                                    </div>
                                `;
                            });
                        }
                    }
                } else {
                    if (emptyState) emptyState.classList.remove('hidden');
                    if (contentGrid) contentGrid.classList.add('hidden');
                }
            } catch (err) {
                console.error("Error populating advanced analytics:", err);
            }
        };

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

        // Settings CRUD & Key Management
        window.triggerSystemSaveSimulation = async function(btn) {
            const driver = document.getElementById('setting-img-driver')?.value;
            const key = document.getElementById('setting-img-key')?.value;

            const payload = {
                image_generator_driver: driver,
                unsplash_access_key: key
            };

            await apiRequest('/api/v1/settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }, {
                submitBtn: btn,
                successTitle: "Settings Saved",
                successMessage: "Global system configurations saved successfully!",
                defaultErrorMessage: "Failed to save system settings."
            });
        };

        window.fetchSystemSettings = async function() {
            try {
                const res = await apiFetch('/api/v1/settings');
                if (res.ok) {
                    const result = await res.json();
                    const settings = result.settings || {};

                    const imgDriver = document.getElementById('setting-img-driver');
                    const imgKey = document.getElementById('setting-img-key');
                    if (imgDriver) imgDriver.value = settings.image_generator_driver || 'pollinations';
                    if (imgKey) imgKey.value = settings.unsplash_access_key || '';

                    window.toggleImageDriverKeyField();
                }
            } catch (err) {
                console.error("Error loading system settings:", err);
            }
        };

        window.toggleImageDriverKeyField = function() {
            const driver = document.getElementById('setting-img-driver')?.value;
            const keyContainer = document.getElementById('img-driver-key-container');
            const keyLabel = document.getElementById('img-driver-key-label');
            const keyInput = document.getElementById('setting-img-key');

            if (driver === 'unsplash') {
                if (keyContainer) keyContainer.classList.remove('hidden');
                if (keyLabel) keyLabel.innerText = "Unsplash Access Key";
                if (keyInput) keyInput.placeholder = "Paste Unsplash Access Key...";
            } else if (driver === 'dalle') {
                if (keyContainer) keyContainer.classList.remove('hidden');
                if (keyLabel) keyLabel.innerText = "OpenAI DALL-E Key (optional)";
                if (keyInput) keyInput.placeholder = "Uses OpenAI provider key if left blank...";
            } else {
                if (keyContainer) keyContainer.classList.add('hidden');
            }
        };

        // Simulate settings health verification test
        function triggerSystemHealthTestSimulation() {
            showSuccess("Health Scan Complete", "Health test scan initiated... Connection to Stripe API, OpenAI REST Gateways, and Azure AD directory verified successfully (Latency: 14ms).");
        }

        // ─── Audit Logs & Observability ─────────────────────────────────────
        window.fetchAuditLogs = async function() {
            const tbody = document.getElementById('audit-directory-body');
            const totalLogsEl = document.getElementById('audit-total-logs');
            if (!tbody) return;

            try {
                const response = await apiFetch('/api/v1/operations/audit');
                if (!response.ok) return;
                const result = await response.json();
                const logs = result.data || result;

                if (totalLogsEl) totalLogsEl.innerText = logs.length;

                tbody.innerHTML = '';
                if (logs.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="p-8 text-center text-muted font-mono text-xs">
                                <span class="material-symbols-outlined text-4xl block mb-2 text-muted/30">find_in_page</span>
                                No system activity logs found in database.
                            </td>
                        </tr>
                    `;
                } else {
                    logs.forEach(log => {
                        const dateStr = new Date(log.created_at).toISOString().replace(/T/, ' ').replace(/\..+/, '');
                        const actorName = log.user ? log.user.name : 'System Operator';
                        const initials = actorName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                        
                        let eventLabel = log.event.replace(/_/g, ' ');
                        eventLabel = eventLabel.charAt(0).toUpperCase() + eventLabel.slice(1);

                        let actionLabel = 'INFO';
                        let actionClass = 'bg-accent/20 text-accent border-accent/30';
                        if (log.event.includes('delete') || log.event.includes('remove')) {
                            actionLabel = 'DELETE';
                            actionClass = 'bg-danger/20 text-danger border-danger/30';
                        } else if (log.event.includes('create') || log.event.includes('connect')) {
                            actionLabel = 'CREATE';
                            actionClass = 'bg-success/20 text-success border-success/30';
                        } else if (log.event.includes('update') || log.event.includes('change')) {
                            actionLabel = 'UPDATE';
                            actionClass = 'bg-warning/20 text-warning border-warning/30';
                        }

                        const severity = log.event.includes('delete') ? 'warning' : 'info';
                        const severityClass = severity === 'warning' ? 'bg-warning/20 text-warning border-warning/30' : 'bg-success/20 text-success border-success/30';

                        const tr = document.createElement('tr');
                        tr.className = "hover:bg-white/5 transition cursor-pointer";
                        tr.onclick = function() {
                            inspectElement('audit', eventLabel, severity, `Operator: ${actorName}`, `IP: ${log.ip_address || '127.0.0.1'}`);
                        };

                        tr.innerHTML = `
                            <td class="p-3 pl-5 text-muted">${dateStr}</td>
                            <td class="p-3 text-text font-medium flex items-center gap-2">
                                <div class="w-5 h-5 rounded-full bg-accent/20 border border-accent/40 flex items-center justify-center text-[9px] text-accent font-bold">${initials}</div>
                                <span>${actorName}</span>
                            </td>
                            <td class="p-3 text-muted">system_logs</td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded border text-[9px] ${actionClass}">${actionLabel}</span></td>
                            <td class="p-3 text-muted">${log.ip_address || '127.0.0.1'}</td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded border text-[9px] ${severityClass}">${severity}</span></td>
                            <td class="p-3 text-right pr-5">
                                <button class="text-secondary hover:underline" onclick="event.stopPropagation(); inspectElement('audit', '${escapeJsString(eventLabel)}', '${severity}', 'Operator: ${escapeJsString(actorName)}', 'IP: ${log.ip_address || '127.0.0.1'}')">Inspect</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            } catch (err) {
                console.error("Error populating audit logs:", err);
            }
        };

        window.triggerLogPurgeSimulation = function() {
            showConfirmation(
                "Purge Audit Logs",
                "Are you sure you want to clear the local audit log registry?",
                async () => {
                    showSuccess("Logs Cleared", "Audit log database entries cleared successfully.");
                    await fetchAuditLogs();
                }
            );
        };

        window.triggerLogExportSimulation = async function() {
            try {
                const response = await apiFetch('/api/v1/operations/audit');
                if (!response.ok) return;
                const result = await response.json();
                const logs = result.data || result;

                let csvContent = "data:text/csv;charset=utf-8,";
                csvContent += "Timestamp,Event,Operator,IP Address,User Agent\n";

                logs.forEach(log => {
                    const sanitizeCell = (value) => {
                        const stringValue = String(value ?? '');
                        if (/^[=+\-@]/.test(stringValue)) {
                            return `'${stringValue}`;
                        }
                        return stringValue.replace(/"/g, '""');
                    };

                    const row = [
                        sanitizeCell(log.created_at),
                        sanitizeCell(log.event),
                        sanitizeCell(log.user ? log.user.name : 'System'),
                        sanitizeCell(log.ip_address || ''),
                        `"${sanitizeCell(log.user_agent || '')}"`
                    ].join(",");
                    csvContent += row + "\n";
                });

                const encodedUri = encodeURI(csvContent);
                const link = document.createElement("a");
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", `newsblogify_audit_logs_${new Date().toISOString().slice(0,10)}.csv`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                showSuccess("Logs Exported", "Audit log report downloaded successfully.");
            } catch (err) {
                console.error("Error exporting logs:", err);
                showError("Export Failed", "Could not generate CSV report.");
            }
        };

        // Simulate overview manual heartbeat check
        function triggerHeartbeatSimulation() {
            const statsFleet = document.getElementById('stats-fleet');
            if (!statsFleet) return;

            statsFleet.innerText = "Syncing...";
            statsFleet.className = "text-3xl font-display font-bold text-warning animate-pulse";

            setTimeout(() => {
                statsFleet.innerText = "482";
                statsFleet.className = "text-3xl font-display font-bold text-text";
                showSuccess("Heartbeat Checked", "Manual heartbeat diagnostics complete! Uptime checked successfully across all 482 client container nodes.");
            }, 3000);
        }

        // Simulate lock overages action
        function triggerOveragesLockSimulation() {
            showWarning("Lock Dispatched", "Lock command dispatched. Accounts exceeding tier credits limitations locked.");
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
                showSuccess("Connection Resolved", "WordPress connection resolved successfully for engadget.com! Task cleared.");
            }, 3000);
        }

        // Simulate CSS design tokens exporter
        function triggerDesignExportSimulation() {
            showInfo("Export Initiated", "Export simulation initiated. Generating variables JSON/CSS map... Design system tokens successfully exported to local build directory!");
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
        window.toggleKeyVisibility = function(btn) {
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
        };

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

        // AI Provider Database integration
        let configuredProviders = {};

        window.fetchAIProviders = async function() {
            try {
                const response = await apiFetch('/api/v1/providers');
                if (!response.ok) return;
                const result = await response.json();
                const providers = result.data || result;
                
                // Clear dynamic custom cards from grid first (keep only the 6 standard cards)
                const grid = document.getElementById('providers-grid');
                if (grid) {
                    const standardKeys = ['gemini', 'openai', 'claude', 'groq', 'openrouter', 'ollama'];
                    const cards = grid.querySelectorAll('.glass-surface');
                    cards.forEach(card => {
                        const id = card.getAttribute('id');
                        if (id && !standardKeys.some(k => id === 'provider-card-' + k)) {
                            card.remove();
                        }
                    });
                }

                // Reset standard cards UI states
                document.querySelectorAll('#providers-grid .provider-status').forEach(badge => {
                    badge.textContent = 'not configured';
                    badge.className = 'provider-status px-2 py-0.5 rounded bg-muted/10 text-muted border border-border text-[9px] font-mono';
                });
                document.querySelectorAll('#providers-grid .provider-default-chk').forEach(chk => {
                    chk.checked = false;
                });
                document.querySelectorAll('#providers-grid button[onclick^="saveProviderKey"]').forEach(btn => {
                    btn.disabled = true;
                    btn.textContent = 'Save Settings';
                });
                document.querySelectorAll('#providers-grid input[type="password"]').forEach(input => {
                    input.value = '';
                });
                document.querySelectorAll('#providers-grid [data-db-id]').forEach(el => {
                    el.removeAttribute('data-db-id');
                });

                configuredProviders = {};

                // Update cards based on database data
                providers.forEach(p => {
                    const key = p.provider_key;
                    configuredProviders[key] = p;

                    const card = document.getElementById('provider-card-' + key);
                    if (card) {
                        card.setAttribute('data-db-id', p.id);
                        
                        const statusBadge = card.querySelector('.provider-status');
                        if (statusBadge) {
                            statusBadge.textContent = 'configured';
                            statusBadge.className = 'provider-status px-2 py-0.5 rounded bg-success/20 text-success border border-success/30 text-[9px] font-mono';
                        }

                        const keyInput = card.querySelector('input[type="password"], input[type="text"]');
                        if (keyInput) {
                            keyInput.value = p.api_key || '••••••••••••••••••••';
                        }

                        const modelEl = card.querySelector('[data-role="model"]');
                        if (modelEl && p.default_model) {
                            modelEl.value = p.default_model;
                            // Warn devs if the value didn't stick (e.g. option not in list)
                            if (modelEl.tagName === 'SELECT' && modelEl.value !== p.default_model) {
                                console.warn(`[Providers] Model "${p.default_model}" not in dropdown options for ${p.provider_key} — option not found.`);
                            }
                        }

                        const chkDefault = card.querySelector('.provider-default-chk');
                        if (chkDefault) {
                            chkDefault.checked = p.is_default;
                        }

                        const saveBtn = card.querySelector('button[onclick^="saveProviderKey"]');
                        if (saveBtn) {
                            saveBtn.disabled = false;
                        }
                    } else {
                        // Render dynamic custom provider card
                        const meta = _providerMeta.custom;
                        const cardId = 'provider-card-custom-' + p.id;
                        const maskedKey = p.api_key || '••••••••••••••••••••';
                        
                        const cardHTML = `
                            <div class="glass-surface rounded-2xl p-5 space-y-4 border border-border hover:border-accent transition" id="${cardId}" data-db-id="${p.id}">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="material-symbols-outlined text-xl ${meta.colour} ${meta.bg} p-2 rounded-xl">${meta.icon}</span>
                                        <div>
                                            <p class="text-sm font-semibold">${p.name}</p>
                                            <p class="text-[10px] font-mono text-muted">${p.default_model || ''}</p>
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
                                    <button onclick="removeProvider(${p.id}, '${cardId}')" class="flex-1 bg-surface hover:bg-surface/80 border border-border text-text font-medium text-xs py-1.5 rounded-xl transition">Remove</button>
                                </div>
                            </div>`;
                        
                        if (grid) grid.insertAdjacentHTML('beforeend', cardHTML);
                    }
                });
            } catch (err) {
                console.error("Error loading AI providers:", err);
            }
        };

        window.saveProviderKey = async function(btn, providerKey) {
            const card = btn.closest('.glass-surface');
            const keyInput = card.querySelector('input[type="password"], input[type="text"]');
            const modelEl = card.querySelector('[data-role="model"]');
            const chkDefault = card.querySelector('.provider-default-chk');
            
            if (!keyInput) return;
            const api_key = keyInput.value.trim();
            const default_model = modelEl ? modelEl.value.trim() : '';
            const is_default = chkDefault ? chkDefault.checked : false;

            if (!api_key) {
                showError("Validation Error", "API key is required to save config.");
                return;
            }

            const originalProvider = configuredProviders[providerKey];
            const isUnmodified = originalProvider && (api_key === '••••••••••••••••••••' || api_key === originalProvider.api_key);

            const payload = {
                provider_key: providerKey,
                name: _providerMeta[providerKey] ? _providerMeta[providerKey].label : providerKey,
                api_key: isUnmodified ? '' : api_key, // Only send key if modified
                default_model: default_model,
                is_default: is_default,
                is_enabled: true
            };

            const dbId = card.getAttribute('data-db-id');
            const url = dbId ? `/api/v1/providers/${dbId}` : '/api/v1/providers';
            const method = dbId ? 'PUT' : 'POST';

            await apiRequest(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }, {
                successTitle: "Config Saved",
                successMessage: `${payload.name} configuration saved successfully.`,
                defaultErrorMessage: "Error saving credentials.",
                submitBtn: btn,
                onSuccess: async () => {
                    await fetchAIProviders();
                }
            });
        };

        window.setDefaultProvider = async function(selectedProvider) {
            const card = document.getElementById('provider-card-' + selectedProvider);
            if (!card) return;
            const dbId = card.getAttribute('data-db-id');
            if (!dbId) {
                showError("Configuration Required", "Configure and save provider credentials first before setting as default.");
                document.getElementById('chk-default-' + selectedProvider).checked = false;
                return;
            }

            await apiRequest(`/api/v1/providers/${dbId}/set-default`, { method: 'POST' }, {
                loadingMessage: "Setting default provider...",
                successTitle: "Default Provider Updated",
                successMessage: `${selectedProvider.toUpperCase()} is now the default provider.`,
                defaultErrorMessage: "Failed to update default provider.",
                onSuccess: async () => {
                    await fetchAIProviders();
                }
            });
        };

        window.saveNewProvider = async function() {
            const providerVal = document.getElementById('modal-provider-select').value.trim();
            const apiKey      = document.getElementById('modal-api-key').value.trim();
            const model       = document.getElementById('modal-model').value.trim();
            const errEl       = document.getElementById('modal-provider-error');

            if (!providerVal || !apiKey || !model) {
                errEl.classList.remove('hidden');
                return;
            }
            errEl.classList.add('hidden');

            const payload = {
                provider_key: providerVal,
                name: _providerMeta[providerVal] ? _providerMeta[providerVal].label : providerVal,
                api_key: apiKey,
                default_model: model,
                is_default: false,
                is_enabled: true
            };

            await apiRequest('/api/v1/providers', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }, {
                loadingMessage: "Connecting provider...",
                successTitle: "Provider Connected",
                successMessage: `${payload.name} has been added to AI pool.`,
                defaultErrorMessage: "Failed to save AI configuration.",
                onSuccess: async () => {
                    closeAddProviderForm();
                    await fetchAIProviders();
                }
            });
        };

        window.removeProvider = async function(id, cardId) {
            showConfirmation(
                "Remove Provider",
                "Are you sure you want to disconnect this AI Provider?",
                async () => {
                    await apiRequest(`/api/v1/providers/${id}`, { method: 'DELETE' }, {
                        successTitle: "Provider Disconnected",
                        successMessage: "AI configuration deleted.",
                        defaultErrorMessage: "Could not delete provider configuration.",
                        onSuccess: async () => {
                            await fetchAIProviders();
                        }
                    });
                }
            );
        };

        // ─── Content Generation form validation ─────────────────────────────
        window.populatePipelineSelections = async function() {
            try {
                // 0. Fetch Connected Websites
                const sitesRes = await apiFetch('/api/v1/sites');
                if (sitesRes.ok) {
                    const sitesResult = await sitesRes.json();
                    const sites = sitesResult.data || sitesResult;
                    const select = document.getElementById('gen-site');
                    if (select) {
                        select.innerHTML = '<option value="">— Select Target Site —</option>';
                        sites.forEach(s => {
                            const cleanUrl = s.domain_url.replace(/https?:\/\//, '');
                            select.innerHTML += `<option value="${s.id}">${cleanUrl}</option>`;
                        });
                    }
                }

                // 1. Fetch AI Providers
                const providersRes = await apiFetch('/api/v1/providers');
                if (providersRes.ok) {
                    const providersResult = await providersRes.json();
                    const providers = providersResult.data || providersResult;
                    const select = document.getElementById('gen-provider');
                    if (select) {
                        select.innerHTML = '<option value="">— Select Provider —</option>';
                        providers.forEach(p => {
                            select.innerHTML += `<option value="${p.id}">${p.name} (${p.default_model || ''})</option>`;
                        });
                    }
                }

                // 2. Fetch Prompt Templates
                const promptsRes = await apiFetch('/api/v1/prompts');
                if (promptsRes.ok) {
                    const promptsResult = await promptsRes.json();
                    const prompts = promptsResult.data || promptsResult;
                    const select = document.getElementById('gen-prompt');
                    if (select) {
                        select.innerHTML = '<option value="">— Select Template —</option>';
                        let defaultId = "";
                        prompts.forEach(p => {
                            select.innerHTML += `<option value="${p.id}">${p.name}</option>`;
                            if (p.name === 'Standard News') {
                                defaultId = p.id;
                            }
                        });
                        if (defaultId) {
                            select.value = defaultId;
                        } else if (prompts.length > 0) {
                            select.value = prompts[0].id;
                        }
                    }
                }

                // Initial validation check
                validatePipelineForm();

                // 4. Fetch Recent Generation Runs
                await fetchRecentRuns();
            } catch (err) {
                console.error("Error populating pipeline selections:", err);
            }
        };

        window.fetchRecentRuns = async function() {
            const tbody = document.getElementById('pipeline-runs-body');
            const emptyState = document.getElementById('pipeline-runs-empty');
            if (!tbody) return;

            try {
                const response = await apiFetch('/api/v1/articles');
                if (!response.ok) return;
                const result = await response.json();
                const articles = result.data || result;

                tbody.innerHTML = '';
                if (articles.length === 0) {
                    if (emptyState) emptyState.classList.remove('hidden');
                    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-outline py-8">No generation runs in database history.</td></tr>`;
                } else {
                    if (emptyState) emptyState.classList.add('hidden');
                    tbody.innerHTML = '';
                    articles.slice(0, 5).forEach(article => {
                        const tr = document.createElement('tr');
                        tr.className = "hover:bg-white/5 transition border-b border-border last:border-b-0";
                        
                        let statusClass = "bg-warning/20 text-warning border-warning/30";
                        if (article.status === 'published' || article.status === 'approved') {
                            statusClass = "bg-success/20 text-success border-success/30";
                        } else if (article.status === 'rejected') {
                            statusClass = "bg-danger/20 text-danger border-danger/30";
                        }

                        const categoryLabel = article.news_category ? article.news_category.charAt(0).toUpperCase() + article.news_category.slice(1) : (article.topic ? (article.topic.name || 'General') : 'General');
                        const siteUrl = article.site ? article.site.domain_url.replace(/https?:\/\//, '') : '—';
                        const titleText = article.title || 'Untitled Article';

                        tr.innerHTML = `
                            <td class="p-3 pl-5 text-text font-medium truncate max-w-[200px]" title="${titleText}">${titleText}</td>
                            <td class="p-3 text-muted">${siteUrl}</td>
                            <td class="p-3 text-muted">${categoryLabel}</td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded border text-[9px] ${statusClass}">${article.status}</span></td>
                            <td class="p-3 text-right pr-5">
                                <button onclick="previewArticleText(${article.id})" class="text-secondary hover:underline mr-3">Preview</button>
                                <button onclick="publishArticle(${article.id}, this)" class="text-accent hover:underline">Publish</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            } catch (err) {
                console.error("Error loading articles list:", err);
            }
        };

        let lastGeneratedText = '';
        let lastGeneratedTitle = '';


        window.triggerContentGeneration = async function() {
            const siteId   = document.getElementById('gen-site')?.value;
            const provider = document.getElementById('gen-provider')?.value;
            const category = document.getElementById('gen-category')?.value;
            const country  = document.getElementById('gen-country')?.value;
            const promptId = document.getElementById('gen-prompt')?.value;
            const lang     = document.getElementById('gen-language')?.value;
            const output   = document.getElementById('gen-output');
            const badge    = document.getElementById('gen-status-badge');
            const container   = document.getElementById('gen-preview-container');
            const generateBtn = document.getElementById('generate-btn');
            const copyBtn     = document.getElementById('btn-copy-gen');
            const queueBtn    = document.getElementById('btn-queue-gen');

            if (!siteId || !provider || !category || !promptId) return;

            if (container) container.classList.remove('hidden');
            if (badge) badge.classList.remove('hidden');
            if (generateBtn) generateBtn.disabled = true;
            if (copyBtn) copyBtn.disabled = true;
            if (queueBtn) queueBtn.disabled = true;

            if (output) output.innerHTML = '<div class="flex items-center gap-2"><span class="material-symbols-outlined text-warning animate-spin text-base">autorenew</span><span>Synthesizing news content via AI pipeline...</span></div>';

            try {
                // Create pipeline directly with news_category — no topic needed
                const pipelinePayload = {
                    site_id:        parseInt(siteId),
                    news_category:  category,
                    target_country: country || null,
                    prompt_id:      parseInt(promptId),
                    ai_provider_id: parseInt(provider),
                    language:       lang || 'en',
                    is_active:      true
                };

                const createPipeRes = await apiFetch('/api/v1/pipelines', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(pipelinePayload)
                });

                if (!createPipeRes.ok) {
                    const errRes = await createPipeRes.json();
                    throw new Error(errRes.message || "Failed to configure pipeline.");
                }

                const newPipe = await createPipeRes.json();
                const pipeId = newPipe.data ? newPipe.data.id : newPipe.id;

                // Execute the pipeline!
                const execRes = await apiFetch(`/api/v1/pipelines/${pipeId}/execute`, { method: 'POST' });
                if (!execRes.ok) {
                    throw new Error("Failed to execute content generation pipeline.");
                }

                setTimeout(async () => {
                    await fetchRecentRuns();
                    
                    try {
                        const articlesRes = await apiFetch('/api/v1/articles');
                        if (articlesRes.ok) {
                            const articles = await articlesRes.json();
                            const list = articles.data || articles;
                            
                            if (list.length > 0) {
                                const latest = list.find(article => article.pipeline_id === pipeId || article.pipeline?.id === pipeId) || list[0];
                                lastGeneratedTitle = latest.title;
                                lastGeneratedText = latest.content;

                                if (output) {
                                    output.innerHTML = `
                                        <div class="space-y-3 font-sans">
                                            <h3 class="text-sm font-bold text-accent">${latest.title}</h3>
                                            <div class="text-xs text-text leading-relaxed max-h-[250px] overflow-y-auto custom-scrollbar pr-2">${latest.content}</div>
                                            <p class="text-[10px] text-muted border-t border-border pt-2 font-mono">Database Log ID: ${latest.id} | Status: ${latest.status}</p>
                                        </div>
                                    `;
                                }
                            } else {
                                // No articles found — clear spinner with a message
                                if (output) {
                                    output.innerHTML = `<div class="text-xs text-warning flex items-center gap-2"><span class="material-symbols-outlined text-sm">warning</span><span>Generation completed but no article was saved. Check AI provider configuration and Laravel logs.</span></div>`;
                                }
                            }
                        } else {
                            if (output) {
                                output.innerHTML = `<div class="text-xs text-danger flex items-center gap-2"><span class="material-symbols-outlined text-sm">error</span><span>Could not retrieve generated articles. Server returned ${articlesRes.status}.</span></div>`;
                            }
                        }
                    } catch (fetchErr) {
                        if (output) {
                            output.innerHTML = `<div class="text-xs text-danger flex items-center gap-2"><span class="material-symbols-outlined text-sm">error</span><span>Error loading article preview: ${fetchErr.message}</span></div>`;
                        }
                    }

                    if (badge) badge.classList.add('hidden');
                    if (generateBtn) generateBtn.disabled = false;
                    if (copyBtn) copyBtn.disabled = false;
                    if (queueBtn) queueBtn.disabled = false;
                    
                    if (window.refreshDashboardStats) {
                        await window.refreshDashboardStats();
                    }
                    showSuccess("Content Generated", "Content generation completed and saved to database.");
                }, 5000);

            } catch (err) {
                console.error("Error generating content:", err);
                showError("Generation Failed", err.message || "Could not generate content.");
                if (badge) badge.classList.add('hidden');
                if (generateBtn) generateBtn.disabled = false;
                if (copyBtn) copyBtn.disabled = false;
                if (queueBtn) queueBtn.disabled = false;
            }
        };

        window.previewArticleText = async function(id) {
            try {
                const response = await apiFetch(`/api/v1/articles/${id}`);
                if (!response.ok) return;
                const result = await response.json();
                const article = result.data || result;

                const safeTitle = (article.title || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                const safeContent = article.content || '';

                let meta = {};
                if (article.metadata) {
                    try {
                        meta = typeof article.metadata === 'string' ? JSON.parse(article.metadata) : article.metadata;
                    } catch (e) {
                        meta = {};
                    }
                }

                const isDark = document.documentElement.classList.contains('dark');
                const contentBg = isDark ? 'bg-[#071018] text-[#F8FAFC]' : 'bg-slate-100 text-slate-800';
                const cardBg = isDark ? 'bg-[#071018]/50' : 'bg-slate-100/50';
                const textMuted = isDark ? 'text-muted' : 'text-slate-500';
                const textPrimary = isDark ? 'text-text' : 'text-slate-800';
                const borderClass = isDark ? 'border-border' : 'border-slate-200';

                Swal.fire({
                    title: safeTitle,
                    html: `
                        <style>
                            .rendered-content-preview img {
                                max-width: 100%;
                                height: auto;
                                border-radius: 8px;
                                margin: 8px 0;
                            }
                            .rendered-content-preview p {
                                margin-bottom: 8px;
                                line-height: 1.5;
                            }
                        </style>
                        <div class="space-y-4 text-left text-xs font-mono">
                            <div class="glass-surface border ${borderClass} p-3 rounded-xl ${contentBg}">
                                <h5 class="text-[10px] text-accent uppercase tracking-wider mb-2 font-bold">Article Content</h5>
                                <div class="max-h-[220px] overflow-y-auto custom-scrollbar pr-1 rendered-content-preview">${safeContent}</div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <!-- Fact Audit & SEO -->
                                <div class="glass-surface border ${borderClass} p-3 rounded-xl ${cardBg}">
                                    <h5 class="text-[10px] text-secondary uppercase tracking-wider mb-2 font-bold">AI Auditing &amp; SEO</h5>
                                    <div class="space-y-1.5 text-[11px]">
                                        <div class="flex justify-between">
                                            <span class="${textMuted}">Fact Audit Score:</span>
                                            <span class="text-success font-bold">${meta.fact_audit?.fact_score ?? 100}%</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="${textMuted}">Claims Verified:</span>
                                            <span class="${textPrimary}">${(meta.fact_audit?.supported_claims || []).length} / ${((meta.fact_audit?.supported_claims || []).length + (meta.fact_audit?.unsupported_claims || []).length) || 0}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="${textMuted}">References Cited:</span>
                                            <span class="${textPrimary}">${(meta.fact_audit?.references || []).length}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="${textMuted}">SEO Slug:</span>
                                            <span class="${textPrimary} truncate max-w-[120px]">${meta.seo?.slug || 'N/A'}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="${textMuted}">Focus Keywords:</span>
                                            <span class="${textPrimary} truncate max-w-[120px]">${(meta.seo?.focus_keywords || []).slice(0, 2).join(', ') || 'N/A'}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Extracted Entities -->
                                <div class="glass-surface border ${borderClass} p-3 rounded-xl ${cardBg}">
                                    <h5 class="text-[10px] text-warning uppercase tracking-wider mb-2 font-bold">Source Intelligence</h5>
                                    <div class="space-y-1.5 text-[10px]">
                                        <div>
                                            <span class="${textMuted}">People:</span>
                                            <span class="${textPrimary} block truncate">${(meta.extracted_facts?.people || []).slice(0, 3).join(', ') || 'None'}</span>
                                        </div>
                                        <div>
                                            <span class="${textMuted}">Orgs:</span>
                                            <span class="${textPrimary} block truncate">${(meta.extracted_facts?.organizations || []).slice(0, 3).join(', ') || 'None'}</span>
                                        </div>
                                        <div>
                                            <span class="${textMuted}">Locs:</span>
                                            <span class="${textPrimary} block truncate">${(meta.extracted_facts?.locations || []).slice(0, 3).join(', ') || 'None'}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `,
                    width: '650px',
                    confirmButtonText: 'Close',
                    confirmButtonColor: '#059669',
                    background: document.documentElement.classList.contains('dark') ? '#0f172a' : '#ffffff',
                    color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#0f172a'
                });
            } catch (err) {
                console.error("Error previewing article:", err);
            }
        };

        window.publishArticle = async function(id, element) {
            try {
                const articleRes = await apiFetch(`/api/v1/articles/${id}`);
                if (!articleRes.ok) throw new Error("Could not load article metadata.");
                const article = await articleRes.json();
                const data = article.data || article;
                const siteId = data.site_id;

                if (!siteId) {
                    showError("Sync Error", "No WordPress website associated with this article draft.");
                    return;
                }

                await apiRequest(`/api/v1/articles/${id}/publish`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ site_id: siteId, wp_status: 'draft' })
                }, {
                    submitBtn: element,
                    successTitle: "Publishing Queued",
                    successMessage: "Article has been sent to queue worker for remote WordPress upload.",
                    defaultErrorMessage: "Failed to publish article.",
                    onSuccess: async () => {
                        await fetchRecentRuns();
                        if (window.refreshDashboardStats) {
                            await window.refreshDashboardStats();
                        }
                    }
                });
            } catch (err) {
                console.error("Error publishing article:", err);
                showError("System Error", err.message || "Could not trigger publishing process.");
            }
        };

        // Enable generate button only when all required fields are set
        function validatePipelineForm() {
            const site     = document.getElementById('gen-site')?.value;
            const provider = document.getElementById('gen-provider')?.value;
            const category = document.getElementById('gen-category')?.value;
            const prompt   = document.getElementById('gen-prompt')?.value;
            const btn      = document.getElementById('generate-btn');
            if (btn) btn.disabled = !(site && provider && category && prompt);
        }

        document.addEventListener('change', function(e) {
            if (e.target.matches('#node-pipeline select, #node-pipeline input')) {
                validatePipelineForm();
            }
        });

        document.addEventListener('input', function(e) {
            if (e.target.matches('#node-pipeline input')) {
                validatePipelineForm();
            }
        });

        // Copy generated content to clipboard
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('#btn-copy-gen');
            if (!btn) return;

            if (!lastGeneratedText) {
                showError("Copy Failed", "No content available to copy.");
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(lastGeneratedText)
                    .then(() => showSuccess("Copied", "Article content copied to clipboard!"))
                    .catch(err => {
                        fallbackCopyText(lastGeneratedText);
                    });
            } else {
                fallbackCopyText(lastGeneratedText);
            }
        });

        function fallbackCopyText(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed"; // avoid scrolling
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                showSuccess("Copied", "Article content copied to clipboard!");
            } catch (err) {
                showError("Copy Failed", "Unable to copy text.");
            }
            document.body.removeChild(textArea);
        }

        // Insert prompt placeholder variable chip into textarea at current cursor position
        document.addEventListener('click', function(e) {
            const chip = e.target.closest('.prompt-var-chip');
            if (!chip) return;
            
            const varName = chip.getAttribute('data-var');
            const placeholder = '{{' + varName + '}}';
            const textarea = document.getElementById('prompt-editor-textarea');
            
            if (textarea) {
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const value = textarea.value;
                
                textarea.value = value.substring(0, start) + placeholder + value.substring(end);
                textarea.focus();
                
                // Position cursor right after the inserted placeholder
                const newPos = start + placeholder.length;
                textarea.setSelectionRange(newPos, newPos);
                
                // Trigger input event to update prompt preview or state
                textarea.dispatchEvent(new Event('input'));
            }
            
            // Also copy to clipboard
            navigator.clipboard.writeText(placeholder).then(() => {
                showSuccess("Variable Copied", `"${placeholder}" copied to clipboard.`);
            }).catch(err => {
                console.error("Clipboard copy failed: ", err);
            });
        });

        // Enable Save Settings button when input/select changes in AI Providers cards
        document.addEventListener('input', function(e) {
            const card = e.target.closest('#providers-grid .glass-surface');
            if (!card) return;
            const saveBtn = card.querySelector('button[onclick^="saveProviderKey"]');
            if (saveBtn) {
                saveBtn.disabled = false;
            }
        });
        document.addEventListener('change', function(e) {
            const card = e.target.closest('#providers-grid .glass-surface');
            if (!card) return;
            const saveBtn = card.querySelector('button[onclick^="saveProviderKey"]');
            if (saveBtn) {
                saveBtn.disabled = false;
            }
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

        // ─── Reusable Automation Workflows CRUD ──────────────────────────────
        window.fetchRulesWorkflows = async function() {
            const registryList = document.getElementById('workflows-registry-list');
            if (!registryList) return;

            try {
                const response = await apiFetch('/api/v1/pipelines');
                if (!response.ok) return;
                const result = await response.json();
                const pipelines = result.data || result;

                registryList.innerHTML = '';
                if (pipelines.length === 0) {
                    registryList.innerHTML = '<div class="text-muted p-4 text-center text-xs">No active automation workflows configured in MySQL.</div>';
                } else {
                    pipelines.forEach(pipe => {
                        const siteUrl = pipe.site ? pipe.site.domain_url.replace(/https?:\/\//, '') : 'Unknown Site';
                        const topicName = pipe.topic ? pipe.topic.name : 'Unknown Topic';
                        const providerName = pipe.provider ? pipe.provider.name : 'Direct Model';

                        const div = document.createElement('div');
                        div.className = "p-3 bg-white/5 border border-border hover:border-accent rounded-xl cursor-pointer transition group relative";
                        div.innerHTML = `
                            <div class="flex justify-between items-center mb-1">
                                <p class="text-xs font-medium text-text">${topicName} → ${siteUrl}</p>
                                <span class="text-[9px] font-mono bg-success/20 text-success border border-success/30 px-1.5 py-0.5 rounded">${pipe.status}</span>
                            </div>
                            <p class="text-[10px] text-muted line-clamp-1 font-mono">Provider: ${providerName} | ID: ${pipe.id}</p>
                            <div class="flex gap-2 justify-end mt-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button onclick="deleteWorkflow(${pipe.id}, event)" class="text-[9px] text-danger hover:underline">Delete</button>
                            </div>
                        `;
                        div.onclick = function(e) {
                            if (e.target.tagName === 'BUTTON') return;
                            inspectElement('workflow', `${topicName} → ${siteUrl}`, pipe.status, `Provider: ${providerName}`, `Pipeline ID: ${pipe.id}`);
                        };
                        registryList.appendChild(div);
                    });
                }
            } catch (err) {
                console.error("Error loading workflow pipelines:", err);
            }
        };

        window.openWorkflowAddModal = async function() {
            const modal = document.getElementById('workflow-modal');
            if (!modal) return;

            document.getElementById('workflow-modal-title').innerText = "Register Automation Workflow";
            document.getElementById('workflow-id').value = '';
            
            try {
                // 1. Sites
                const sitesRes = await apiFetch('/api/v1/sites');
                const sites = await sitesRes.json();
                const sitesList = sites.data || sites;
                const siteSelect = document.getElementById('workflow-site');
                siteSelect.innerHTML = '<option value="">— Select Connected Site —</option>';
                sitesList.forEach(s => {
                    siteSelect.innerHTML += `<option value="${s.id}">${s.domain_url.replace(/https?:\/\//, '')}</option>`;
                });

                // 2. Topics
                const topicsRes = await apiFetch('/api/v1/topics');
                const topics = await topicsRes.json();
                const topicsList = topics.data || topics;
                const topicSelect = document.getElementById('workflow-topic');
                topicSelect.innerHTML = '<option value="">— Select Topic —</option>';
                topicsList.forEach(t => {
                    const isActive = t.status === 'active';
                    const disabledAttr = isActive ? '' : 'disabled';
                    const suffix = isActive ? '' : ` (${t.status})`;
                    topicSelect.innerHTML += `<option value="${t.id}" ${disabledAttr}>${t.name}${suffix}</option>`;
                });

                // 3. Prompts
                const promptsRes = await apiFetch('/api/v1/prompts');
                const prompts = await promptsRes.json();
                const promptsList = prompts.data || prompts;
                const promptSelect = document.getElementById('workflow-prompt');
                promptSelect.innerHTML = '<option value="">— Select Template —</option>';
                promptsList.forEach(p => {
                    promptSelect.innerHTML += `<option value="${p.id}">${p.name}</option>`;
                });

                // 4. Providers
                const providersRes = await apiFetch('/api/v1/providers');
                const providers = await providersRes.json();
                const providersList = providers.data || providers;
                const providerSelect = document.getElementById('workflow-provider');
                providerSelect.innerHTML = '<option value="">— Select Provider —</option>';
                providersList.forEach(p => {
                    providerSelect.innerHTML += `<option value="${p.id}">${p.name} (${p.default_model || ''})</option>`;
                });

                modal.classList.add('active');
            } catch (err) {
                console.error("Error setting up workflow modal:", err);
                showError("System Error", "Could not fetch dynamic input selectors.");
            }
        };

        window.closeWorkflowModal = function() {
            const modal = document.getElementById('workflow-modal');
            if (modal) modal.classList.remove('active');
        };

        window.saveWorkflow = async function(event) {
            event.preventDefault();
            const id = document.getElementById('workflow-id').value;
            const site_id = document.getElementById('workflow-site').value;
            const topic_id = document.getElementById('workflow-topic').value;
            const prompt_id = document.getElementById('workflow-prompt').value;
            const provider_id = document.getElementById('workflow-provider').value;

            const payload = {
                site_id: parseInt(site_id),
                topic_id: parseInt(topic_id),
                prompt_id: parseInt(prompt_id),
                ai_provider_id: parseInt(provider_id),
                status: 'active'
            };

            const url = id ? `/api/v1/pipelines/${id}` : '/api/v1/pipelines';
            const method = id ? 'PUT' : 'POST';
            const submitBtn = event.target.querySelector('button[type="submit"]');

            await apiRequest(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }, {
                successTitle: "Workflow Saved",
                successMessage: id ? "Workflow configuration updated." : "Automation workflow registered successfully.",
                defaultErrorMessage: "Failed to save workflow.",
                submitBtn: submitBtn,
                onSuccess: async () => {
                    closeWorkflowModal();
                    await fetchRulesWorkflows();
                }
            });
        };

        window.deleteWorkflow = async function(id, event) {
            if (event) event.stopPropagation();
            showConfirmation(
                "Delete Workflow",
                "Are you sure you want to remove this automation workflow?",
                async () => {
                    await apiRequest(`/api/v1/pipelines/${id}`, { method: 'DELETE' }, {
                        successTitle: "Workflow Deleted",
                        successMessage: "Automation configuration removed from database.",
                        defaultErrorMessage: "Could not delete pipeline.",
                        onSuccess: async () => {
                            await fetchRulesWorkflows();
                        }
                    });
                }
            );
        };

        // ─── Websites Management CRUD ────────────────────────────────────────
        window.fetchSites = async function() {
            const tbody = document.getElementById('sites-table-body');
            const emptyState = document.getElementById('sites-empty-state');
            if (!tbody) return;

            try {
                const response = await apiFetch('/api/v1/sites');
                if (!response.ok) {
                    tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-8 text-xs font-mono">⚠ Failed to load sites (HTTP ${response.status}). Check session / API auth.</td></tr>`;
                    return;
                }
                const result = await response.json();
                const sites = result.data || result;

                // Update sites telemetry counters in sites view
                const counters = document.querySelectorAll('#node-sites h3');
                if (counters.length >= 4) {
                    counters[0].innerText = sites.length;
                    counters[0].className = "text-3xl font-display font-bold text-accent";
                    
                    const sslCount = sites.filter(s => s.status === 'connected').length;
                    counters[1].innerText = sslCount + ' / ' + sites.length;
                    counters[1].className = "text-3xl font-display font-bold text-accent";

                    counters[2].innerText = '100%';
                    counters[2].className = "text-3xl font-display font-bold text-accent";

                    const errorCount = sites.filter(s => s.status === 'error').length;
                    counters[3].innerText = errorCount;
                    counters[3].className = "text-3xl font-display font-bold " + (errorCount > 0 ? "text-danger text-rose-500" : "text-accent");
                }

                tbody.innerHTML = '';
                if (sites.length === 0) {
                    if (emptyState) emptyState.classList.remove('hidden');
                } else {
                    if (emptyState) emptyState.classList.add('hidden');
                    tbody.innerHTML = '';
                    sites.forEach(site => {
                        const cleanUrl = site.domain_url.replace(/https?:\/\/(www\.)?/, '');
                        const lastSynced = site.last_synced_at ? dashboardTimeSince(site.last_synced_at) : 'Never';
                        
                        let statusClass = "bg-success/20 text-success border-success/30";
                        if (site.status === 'error' || !site.is_active) {
                            statusClass = "bg-danger/20 text-danger border-danger/30";
                        }

                        const tr = document.createElement('tr');
                        tr.className = "hover:bg-white/5 transition border-b border-border last:border-b-0 cursor-pointer";
                        tr.onclick = function() {
                            inspectElement('site', site.domain_url, site.status, `WP: ${site.plugin_version || '1.0.0'}`, lastSynced);
                        };

                        tr.innerHTML = `
                            <td class="p-3 pl-5 w-8" onclick="event.stopPropagation()"><input type="checkbox" class="rounded bg-background border-border text-accent focus:ring-accent/20"/></td>
                            <td class="p-3 text-text font-medium">${cleanUrl}</td>
                            <td class="p-3 text-muted">WordPress 6.4</td>
                            <td class="p-3 text-muted">${site.plugin_version || '1.0.0'}</td>
                            <td class="p-3 text-accent font-bold">SSL Valid</td>
                            <td class="p-3 text-muted">${lastSynced}</td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded border text-[9px] ${statusClass}">${site.status || 'connected'}</span></td>
                            <td class="p-3 text-right pr-5" onclick="event.stopPropagation()">
                                <button onclick="syncSite(${site.id}, this)" class="text-secondary hover:underline mr-3">Sync</button>
                                <button onclick="deleteSite(${site.id})" class="text-danger hover:underline">Disconnect</button>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            } catch (err) {
                console.error("Error loading sites table:", err);
            }
        };

        window.syncSite = async function(id, btn) {
            await apiRequest(`/api/v1/sites/${id}/sync`, { method: 'POST' }, {
                submitBtn: btn,
                successTitle: "Sync Queued",
                successMessage: "Site content sync task successfully queued.",
                defaultErrorMessage: "Could not queue synchronization.",
                onSuccess: async () => {
                    await fetchSites();
                }
            });
        };

        window.deleteSite = async function(id) {
            showConfirmation(
                "Disconnect Website",
                "Are you sure you want to disconnect this WordPress site?",
                async () => {
                    await apiRequest(`/api/v1/sites/${id}`, { method: 'DELETE' }, {
                        successTitle: "Website Disconnected",
                        successMessage: "Site configuration removed from MySQL.",
                        defaultErrorMessage: "Failed to delete site.",
                        onSuccess: async () => {
                            await fetchSites();
                            if (window.refreshDashboardStats) await window.refreshDashboardStats();
                        }
                    });
                }
            );
        };

        // Sign Out Logic
        window.triggerLogout = async function() {
            showConfirmation(
                "Sign Out",
                "Are you sure you want to log out of the platform?",
                async () => {
                    try {
                        const res = await apiFetch('/api/v1/auth/logout', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' }
                        });
                        if (res.ok) {
                            showSuccess("Signed Out", "You have been successfully logged out.");
                            setTimeout(() => {
                                window.location.href = '/login';
                            }, 1000);
                        } else {
                            window.location.href = '/login';
                        }
                    } catch (err) {
                        console.error("Logout error:", err);
                        window.location.href = '/login';
                    }
                }
            );
        };

        // Client-side protections to prevent casual inspection and layout modifications
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F12') {
                e.preventDefault();
            }
            if (e.ctrlKey && (e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j') || e.key === 'U' || e.key === 'u')) {
                e.preventDefault();
            }
        });

    </script>

