document.addEventListener('DOMContentLoaded', function() {
    // Note: The drag and drop code for Kanban is currently in a separate script tag
    // in crm-dashboard.php. We might consolidate it here later.

    // Tab switching logic
    const kanbanTab = document.getElementById('kanban-tab');
    const listTab = document.getElementById('list-tab');
    const kanbanView = document.getElementById('kanban-view');
    const listView = document.getElementById('list-view');

    if (kanbanTab && listTab && kanbanView && listView) {
        kanbanTab.addEventListener('click', function(e) {
            e.preventDefault();
            kanbanTab.classList.add('active');
            listTab.classList.remove('active');
            kanbanView.style.display = 'block';
            listView.style.display = 'none';
        });

        listTab.addEventListener('click', function(e) {
            e.preventDefault();
            listTab.classList.add('active');
            kanbanTab.classList.remove('active');
            listView.style.display = 'block';
            kanbanView.style.display = 'none';
        });
    }

    // List view checkbox logic
    const selectAllCheckbox = document.getElementById('select-all-leads');
    const leadCheckboxes = document.querySelectorAll('.lead-checkbox');

    if (selectAllCheckbox && leadCheckboxes.length > 0) {
        selectAllCheckbox.addEventListener('change', function() {
            leadCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionsToolbar();
        });

        leadCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActionsToolbar);
        });
    }

    function updateBulkActionsToolbar() {
        const toolbar = document.getElementById('bulk-actions-toolbar');
        const selectedCountSpan = document.getElementById('selected-count');
        const selectedCheckboxes = document.querySelectorAll('.lead-checkbox:checked');
        const selectedCount = selectedCheckboxes.length;

        if (selectedCount > 0) {
            toolbar.style.display = 'flex';
            selectedCountSpan.textContent = selectedCount;
        } else {
            toolbar.style.display = 'none';
        }
    }

    // Bulk action buttons event listeners
    const applyBtn = document.getElementById('bulk-apply-btn');
    const deleteBtn = document.getElementById('bulk-delete-btn');

    if (applyBtn) {
        applyBtn.addEventListener('click', function() {
            const newStatus = document.getElementById('bulk-action-status').value;
            if (newStatus) {
                executeBulkAction('change_status', { 'new_status': newStatus });
            } else {
                alert('Please select a status to apply.');
            }
        });
    }

    if (deleteBtn) {
        deleteBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete the selected leads? This action cannot be undone.')) {
                executeBulkAction('delete');
            }
        });
    }

    function executeBulkAction(action, extra_data = {}) {
        const selectedCheckboxes = document.querySelectorAll('.lead-checkbox:checked');
        const lead_ids = Array.from(selectedCheckboxes).map(cb => cb.dataset.leadId);

        if (lead_ids.length === 0) {
            alert('Please select at least one lead.');
            return;
        }

        const data = new FormData();
        data.append('action', 'handle_bulk_actions');
        data.append('nonce', opbcrm_ajax.nonce);
        data.append('bulk_action', action);
        
        lead_ids.forEach(id => {
            data.append('lead_ids[]', id);
        });

        // Append extra data, like new_status
        for (const key in extra_data) {
            data.append(key, extra_data[key]);
        }

        fetch(opbcrm_ajax.ajax_url, {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Action completed successfully.');
                location.reload(); // Reload the page to see changes
            } else {
                alert('An error occurred: ' + result.data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred.');
        });
    }

    // Handle Add Comment
    const addCommentBtn = document.getElementById('add-comment-btn');
    if (addCommentBtn) {
        addCommentBtn.addEventListener('click', function() {
            const commentTextarea = document.getElementById('lead-comment-textarea');
            const comment = commentTextarea.value.trim();
            const leadId = this.dataset.leadId;

            if (!comment) {
                alert('Please enter a comment.');
                return;
            }

            this.disabled = true; // Prevent double clicks

            const data = new FormData();
            data.append('action', 'add_lead_comment');
            data.append('nonce', opbcrm_ajax.nonce);
            data.append('lead_id', leadId);
            data.append('comment', comment);

            fetch(opbcrm_ajax.ajax_url, {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Prepend the new comment to the timeline
                    const timeline = document.getElementById('activity-timeline');
                    const newActivityHtml = `
                        <div class="timeline-item">
                            <div class="item-icon ${result.data.icon_bg_class}">
                                <span><i class="${result.data.icon_class}"></i></span>
                            </div>
                            <div class="item-content">
                                <span class="item-meta">
                                    ${result.data.type} by ${result.data.user_name} - ${result.data.time_ago}
                                </span>
                                <p>${result.data.content}</p>
                            </div>
                        </div>
                    `;
                    timeline.insertAdjacentHTML('afterbegin', newActivityHtml);
                    
                    // Clear textarea
                    commentTextarea.value = '';
                } else {
                    alert('Error: ' + result.data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected error occurred.');
            })
            .finally(() => {
                this.disabled = false; // Re-enable button
            });
        });
    }

    // Handle Add Task
    const addTaskBtn = document.getElementById('add-task-btn');
    if (addTaskBtn) {
        addTaskBtn.addEventListener('click', function() {
            const taskTextarea = document.getElementById('lead-task-textarea');
            const taskContent = taskTextarea.value.trim();
            const dueDateInput = document.getElementById('task-due-date');
            const assigneeSelect = document.getElementById('task-assignee');
            const leadId = this.dataset.leadId;

            if (!taskContent) {
                alert('Please enter a task description.');
                return;
            }

            this.disabled = true;

            const data = new FormData();
            data.append('action', 'add_lead_task');
            data.append('nonce', opbcrm_ajax.nonce);
            data.append('lead_id', leadId);
            data.append('task_content', taskContent);
            data.append('due_date', dueDateInput.value);
            data.append('assignee_id', assigneeSelect.value);

            fetch(opbcrm_ajax.ajax_url, {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(result.data || 'Task added successfully!');
                    location.reload(); // Reload to see the new task
                } else {
                    alert('Error: ' + result.data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected error occurred.');
            })
            .finally(() => {
                this.disabled = false;
            });
        });
    }

    // Activity Tabs Logic
    const activityTabs = document.querySelectorAll('.activity-tab-btn');
    const formContainers = document.querySelectorAll('.activity-form-container');

    activityTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.dataset.tab;

            // Update active class on tabs
            activityTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            // Show the corresponding form
            formContainers.forEach(container => {
                if (container.id === tabName + '-form') {
                    container.style.display = 'block';
                } else {
                    // This logic needs refinement - we don't want to hide all other forms
                    // if the tab is 'activity' (history).
                }
            });

            // Special handling for history tab
            if (tabName === 'activity') {
                 formContainers.forEach(c => c.style.display = 'none');
            } else {
                 const targetForm = document.getElementById(tabName + '-form');
                 if(targetForm) {
                    formContainers.forEach(c => c.style.display = 'none');
                    targetForm.style.display = 'block';
                 }
            }
        });
    });

    // Handle Task Completion Checkbox
    const timeline = document.getElementById('activity-timeline');
    if (timeline) {
        timeline.addEventListener('change', function(e) {
            if (e.target.classList.contains('complete-task-cb')) {
                const checkbox = e.target;
                const taskId = checkbox.dataset.taskId;
                const isCompleted = checkbox.checked;

                const data = new FormData();
                data.append('action', 'update_task_status');
                data.append('nonce', opbcrm_ajax.nonce);
                data.append('task_id', taskId);
                data.append('is_completed', isCompleted);

                fetch(opbcrm_ajax.ajax_url, {
                    method: 'POST',
                    body: data
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        const taskItem = checkbox.closest('.timeline-item');
                        if (isCompleted) {
                            taskItem.classList.add('task-item-completed');
                            taskItem.classList.remove('task-item-pending');
                        } else {
                            taskItem.classList.remove('task-item-completed');
                            taskItem.classList.add('task-item-pending');
                        }
                    } else {
                        alert('Error updating task status.');
                        // Revert checkbox on failure
                        checkbox.checked = !isCompleted;
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });
    }

    // Lead Details Edit/Save Logic
    const editBtn = document.getElementById('edit-lead-details-btn');
    const saveBtn = document.getElementById('save-lead-details-btn');
    const detailsForm = document.getElementById('lead-details-form');

    if (editBtn && saveBtn && detailsForm) {
        editBtn.addEventListener('click', function() {
            detailsForm.querySelectorAll('.view-mode').forEach(el => el.style.display = 'none');
            detailsForm.querySelectorAll('.edit-mode').forEach(el => el.style.display = 'block');
            editBtn.style.display = 'none';
            saveBtn.style.display = 'inline-block';
        });

        saveBtn.addEventListener('click', function() {
            const leadId = document.getElementById('add-comment-btn').dataset.leadId; // Re-using lead id from another button
            const formData = new FormData(detailsForm);
            const serializedData = new URLSearchParams(formData).toString();

            const data = new FormData();
            data.append('action', 'save_lead_details');
            data.append('nonce', opbcrm_ajax.nonce);
            data.append('lead_id', leadId);
            data.append('form_data', serializedData);
            
            saveBtn.disabled = true;

            fetch(opbcrm_ajax.ajax_url, {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(result => {
                if(result.success) {
                    // Update view mode with new values
                    detailsForm.querySelectorAll('.edit-mode').forEach(editEl => {
                        const input = editEl.querySelector('.detail-input');
                        if (input) {
                            const newValue = input.value;
                            const viewEl = editEl.previousElementSibling;
                            viewEl.innerHTML = newValue ? newValue : '<em>empty</em>';
                        }
                    });

                    // Switch back to view mode
                    detailsForm.querySelectorAll('.view-mode').forEach(el => el.style.display = 'block');
                    detailsForm.querySelectorAll('.edit-mode').forEach(el => el.style.display = 'none');
                    editBtn.style.display = 'inline-block';
                    saveBtn.style.display = 'none';

                } else {
                    alert('Error: ' + result.data);
                }
            })
            .catch(error => console.error('Error:', error))
            .finally(() => {
                saveBtn.disabled = false;
            });
        });
    }

    // Initialize inline editing for lead details
    initLeadDetailsEditing();
    
    // Handle Proposal Generation
    $('#generate-proposal-btn').on('click', function() {
        var $button = $(this);
        var leadId = $button.data('lead-id');
        var propertyId = $('#proposal-property-select').val();
        var $statusDiv = $('#proposal-status');

        if (!propertyId) {
            $statusDiv.html('<span style="color: red;">Please select a property first.</span>');
            return;
        }

        $button.prop('disabled', true);
        $statusDiv.html('<i class="fas fa-spinner fa-spin"></i> Generating proposal...');

        $.post(opbcrm_ajax.ajax_url, {
            action: 'generate_crm_proposal',
            nonce: opbcrm_ajax.nonce,
            lead_id: leadId,
            property_id: propertyId
        }).done(function(response) {
            if (response.success) {
                $statusDiv.html('<span style="color: green;">Success!</span> <a href="' + response.data.pdf_url + '" target="_blank" class="opbcrm-btn opbcrm-btn-secondary opbcrm-btn-sm">View PDF</a>');
                // Reload the timeline to show the new entry
                $('#activity-timeline').load(location.href + ' #activity-timeline > *');
            } else {
                $statusDiv.html('<span style="color: red;">Error: ' + (response.data.message || 'Unknown error') + '</span>');
            }
        }).fail(function() {
            $statusDiv.html('<span style="color: red;">An unknown server error occurred.</span>');
        }).always(function() {
            $button.prop('disabled', false);
        });
    });

    // --- CRM Reporting Dashboard Logic ---
    const reportAgent = document.getElementById('crm-report-agent');
    const reportDateFrom = document.getElementById('crm-report-date-from');
    const reportDateTo = document.getElementById('crm-report-date-to');
    const reportRefresh = document.getElementById('crm-report-refresh');
    const totalLeadsEl = document.getElementById('crm-report-total-leads');
    const stagePieEl = document.getElementById('crm-report-stage-pie');
    const sourceBarEl = document.getElementById('crm-report-source-bar');
    const projectBarEl = document.getElementById('crm-report-project-bar');
    const campaignBarEl = document.getElementById('crm-report-campaign-bar');
    const agentBarEl = document.getElementById('crm-report-agent-bar');

    let stagePieChart, sourceBarChart, projectBarChart, campaignBarChart, agentBarChart;
    let lastReportData = null;

    function showAllReportLoaders(show) {
        document.querySelectorAll('.crm-report-loading').forEach(function(loader) {
            loader.style.display = show ? 'flex' : 'none';
        });
    }
    function showToast(msg, success) {
        var $toast = window.jQuery && jQuery('#crm-toast');
        if ($toast && $toast.length) {
            $toast.text(msg).css('background', success ? 'rgba(40,180,80,0.97)' : 'rgba(200,40,40,0.97)').fadeIn(180);
            setTimeout(function(){ $toast.fadeOut(350); }, 2200);
        } else {
            alert(msg);
        }
    }

    // Chart.js tooltip options for glassy, compact look
    const glassyTooltipOpts = {
        enabled: true,
        backgroundColor: 'rgba(255,255,255,0.85)',
        borderColor: 'rgba(130,160,220,0.25)',
        borderWidth: 1.5,
        titleColor: '#222',
        bodyColor: '#333',
        titleFont: { family: 'Inter, sans-serif', size: 14, weight: '600' },
        bodyFont: { family: 'Inter, sans-serif', size: 13, weight: '400' },
        padding: 10,
        caretSize: 6,
        cornerRadius: 10,
        displayColors: false,
        boxPadding: 4,
        callbacks: {}
    };

    function exportToCSV(filename, rows) {
        const process = v => '"' + String(v).replace(/"/g, '""') + '"';
        const csv = rows.map(row => row.map(process).join(",")).join("\r\n");
        const blob = new Blob([csv], {type: 'text/csv'});
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.crm-report-export-btn');
        if (!btn) return;
        if (!lastReportData) { showToast('No data to export', false); return; }
        const type = btn.getAttribute('data-report-type');
        const today = new Date();
        const ymd = today.getFullYear()+('0'+(today.getMonth()+1)).slice(-2)+('0'+today.getDate()).slice(-2);
        let rows = [], filename = 'opbcrm-report-'+type+'-'+ymd+'.csv';
        try {
            if (type === 'total') {
                rows = [['Total Leads'], [lastReportData.total_leads]];
            } else if (type === 'stage') {
                rows = [['Stage','Leads']];
                lastReportData.stage.labels.forEach((label,i)=>rows.push([label,lastReportData.stage.data[i]]));
            } else if (type === 'source') {
                rows = [['Source','Leads']];
                lastReportData.source.labels.forEach((label,i)=>rows.push([label,lastReportData.source.data[i]]));
            } else if (type === 'project') {
                rows = [['Project','Leads']];
                lastReportData.project.labels.forEach((label,i)=>rows.push([label,lastReportData.project.data[i]]));
            } else if (type === 'campaign') {
                rows = [['Campaign','Leads']];
                lastReportData.campaign.labels.forEach((label,i)=>rows.push([label,lastReportData.campaign.data[i]]));
            } else if (type === 'agent') {
                rows = [['Agent','Leads']];
                lastReportData.agent.labels.forEach((label,i)=>rows.push([label,lastReportData.agent.data[i]]));
            } else {
                showToast('Unknown report type', false); return;
            }
            exportToCSV(filename, rows);
            showToast('Exported as CSV', true);
        } catch (err) {
            showToast('Export failed', false);
        }
    });

    function fetchAndRenderReportingData() {
        showAllReportLoaders(true);
        if (totalLeadsEl) totalLeadsEl.textContent = '...';
        if (stagePieEl && stagePieEl.getContext) stagePieEl.getContext('2d').clearRect(0,0,stagePieEl.width,stagePieEl.height);
        if (sourceBarEl && sourceBarEl.getContext) sourceBarEl.getContext('2d').clearRect(0,0,sourceBarEl.width,sourceBarEl.height);
        if (projectBarEl && projectBarEl.getContext) projectBarEl.getContext('2d').clearRect(0,0,projectBarEl.width,projectBarEl.height);
        if (campaignBarEl && campaignBarEl.getContext) campaignBarEl.getContext('2d').clearRect(0,0,campaignBarEl.width,campaignBarEl.height);
        if (agentBarEl && agentBarEl.getContext) agentBarEl.getContext('2d').clearRect(0,0,agentBarEl.width,agentBarEl.height);

        const data = new FormData();
        data.append('action', 'opbcrm_get_reporting_data');
        data.append('agent_id', reportAgent ? reportAgent.value : '');
        data.append('date_from', reportDateFrom ? reportDateFrom.value : '');
        data.append('date_to', reportDateTo ? reportDateTo.value : '');
        data.append('campaign', $('#report-campaign-filter').val() || '');

        fetch(opbcrm_ajax.ajax_url, {
            method: 'POST',
            body: data
        })
        .then(res => res.json())
        .then(res => {
            showAllReportLoaders(false);
            if (!res.success) {
                if (totalLeadsEl) totalLeadsEl.textContent = 'Error';
                showToast('Failed to load reporting data', false);
                return;
            }
            lastReportData = res.data;
            const d = res.data;
            if (totalLeadsEl) totalLeadsEl.textContent = d.total_leads;
            // Stage Pie
            if (stagePieEl) {
                if (stagePieChart) stagePieChart.destroy();
                stagePieChart = new Chart(stagePieEl, {
                    type: 'pie',
                    data: {
                        labels: d.stage.labels,
                        datasets: [{ data: d.stage.data, backgroundColor: [
                            '#83A2DB','#48BB78','#9F7AEA','#38A169','#E55C60','#F6C23E','#007bff','#000000','#888'
                        ] }]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom' }, tooltip: glassyTooltipOpts } }
                });
            }
            // Source Bar
            if (sourceBarEl) {
                if (sourceBarChart) sourceBarChart.destroy();
                sourceBarChart = new Chart(sourceBarEl, {
                    type: 'bar',
                    data: {
                        labels: d.source.labels,
                        datasets: [{ label: 'Leads', data: d.source.data, backgroundColor: '#83A2DB' }]
                    },
                    options: { responsive: true, plugins: { legend: { display: false }, tooltip: glassyTooltipOpts } }
                });
            }
            // Project Bar
            if (projectBarEl) {
                if (projectBarChart) projectBarChart.destroy();
                projectBarChart = new Chart(projectBarEl, {
                    type: 'bar',
                    data: {
                        labels: d.project.labels,
                        datasets: [{ label: 'Leads', data: d.project.data, backgroundColor: '#9F7AEA' }]
                    },
                    options: { responsive: true, plugins: { legend: { display: false }, tooltip: glassyTooltipOpts } }
                });
            }
            // Campaign Bar
            if (campaignBarEl) {
                if (campaignBarChart) campaignBarChart.destroy();
                campaignBarChart = new Chart(campaignBarEl, {
                    type: 'bar',
                    data: {
                        labels: d.campaign.labels,
                        datasets: [{ label: 'Leads', data: d.campaign.data, backgroundColor: '#F6C23E' }]
                    },
                    options: { responsive: true, plugins: { legend: { display: false }, tooltip: glassyTooltipOpts } }
                });
            }
            // Agent Bar
            if (agentBarEl) {
                if (agentBarChart) agentBarChart.destroy();
                agentBarChart = new Chart(agentBarEl, {
                    type: 'bar',
                    data: {
                        labels: d.agent.labels,
                        datasets: [{ label: 'Leads', data: d.agent.data, backgroundColor: '#38A169' }]
                    },
                    options: { responsive: true, plugins: { legend: { display: false }, tooltip: glassyTooltipOpts } }
                });
            }
            if (d.campaign_conversion) {
                renderCampaignConversionChart(d.campaign_conversion);
            }
            if (d.campaign_source_breakdown) {
                renderCampaignSourceBreakdownDropdownAndChart(d.campaign_source_breakdown);
            }
            if (d.campaign_time_series) {
                renderCampaignTimeSeriesChart(d.campaign_time_series);
            }
            if (d.campaign_roi) {
                renderCampaignROIChart(d.campaign_roi);
            }
        })
        .catch(() => {
            showAllReportLoaders(false);
            if (totalLeadsEl) totalLeadsEl.textContent = 'Error';
            showToast('Failed to load reporting data', false);
        });
    }

    // Event listeners
    if (reportAgent) reportAgent.addEventListener('change', fetchAndRenderReportingData);
    if (reportDateFrom) reportDateFrom.addEventListener('change', fetchAndRenderReportingData);
    if (reportDateTo) reportDateTo.addEventListener('change', fetchAndRenderReportingData);
    if (reportRefresh) reportRefresh.addEventListener('click', fetchAndRenderReportingData);

    // Initial load
    if (totalLeadsEl && stagePieEl && sourceBarEl && projectBarEl && campaignBarEl && agentBarEl) {
        fetchAndRenderReportingData();
    }

    // --- Quick Filter Chips Logic ---
    document.addEventListener('click', function(e) {
        const chip = e.target.closest('.crm-report-chip');
        if (!chip) return;
        document.querySelectorAll('.crm-report-chip').forEach(btn => btn.classList.remove('active'));
        chip.classList.add('active');
        const range = chip.getAttribute('data-range');
        const dateFrom = document.getElementById('crm-report-date-from');
        const dateTo = document.getElementById('crm-report-date-to');
        const today = new Date();
        let from = '', to = '';
        if (range === 'all') {
            from = '';
            to = '';
        } else if (range === 'month') {
            from = today.getFullYear() + '-' + ('0'+(today.getMonth()+1)).slice(-2) + '-01';
            to = today.toISOString().slice(0,10);
        } else if (range === '30d') {
            const d = new Date(today.getTime() - 29*24*60*60*1000);
            from = d.toISOString().slice(0,10);
            to = today.toISOString().slice(0,10);
        } else if (range === 'year') {
            from = today.getFullYear() + '-01-01';
            to = today.toISOString().slice(0,10);
        }
        if (dateFrom) dateFrom.value = from;
        if (dateTo) dateTo.value = to;
        fetchAndRenderReportingData();
    });

    // --- Campaign Filter for Reporting Dashboard ---
    $('#report-campaign-filter').on('change', function() {
        fetchAndRenderReportingData();
    });

    // --- Campaign Management ---
    function getCampaignListItemHTML(campaign, lead_count = 0) {
        return `
        <li class="campaign-list-item" data-campaign="${escapeHTML(campaign)}" style="display:flex;align-items:center;justify-content:space-between;padding:12px 8px;border-bottom:1px solid rgba(255,255,255,0.1);">
            <div class="campaign-details">
                <span class="campaign-name-view" style="font-weight:500;color:#333;">${escapeHTML(campaign)}</span>
                <span class="campaign-lead-count" style="margin-left:12px;font-size:12px;color:#666;background:#f0f0f0;padding:2px 8px;border-radius:6px;">${lead_count} Leads</span>
                <div class="campaign-edit-view" style="display:none;align-items:center;gap:8px;">
                    <input type="text" class="campaign-name-input opbcrm-input" value="${escapeHTML(campaign)}" />
                    <button class="save-campaign-btn crm-btn crm-btn-primary" style="padding: 4px 10px;font-size:12px;">Save</button>
                    <button class="cancel-campaign-btn crm-btn" style="padding: 4px 10px;font-size:12px;">Cancel</button>
                </div>
            </div>
            <div class="campaign-actions" style="display:flex;gap:10px;">
                <button class="edit-campaign-btn crm-icon-btn" title="Rename Campaign"><i class="fas fa-edit"></i></button>
                <button class="archive-campaign-btn crm-icon-btn" title="Archive Campaign"><i class="fas fa-archive"></i></button>
            </div>
        </li>`;
    }

    // Add
    $(document).on('click', '#crm-add-campaign-btn', function() {
        const btn = $(this);
        const input = $('#new-campaign-name');
        const newCampaign = input.val().trim();
        if (!newCampaign) {
            showToast('error', 'Campaign name cannot be empty.');
            return;
        }

        btn.prop('disabled', true).text('Adding...');

        $.post(ajaxurl, {
            action: 'opbcrm_add_campaign',
            nonce: opbcrm_dashboard_vars.nonce,
            campaign_name: newCampaign
        }).done(function(response) {
            if (response.success) {
                showToast('success', 'Campaign added.');
                input.val('');
                if ($('.no-campaigns-found').length) {
                    $('.no-campaigns-found').remove();
                }
                $('#campaign-list').append(getCampaignListItemHTML(newCampaign));
            } else {
                showToast('error', response.data.message || 'Could not add campaign.');
            }
        }).fail(function() {
            showToast('error', 'Request failed.');
        }).always(function() {
            btn.prop('disabled', false).text('Add');
        });
    });

    // Edit - Show
    $(document).on('click', '.edit-campaign-btn', function() {
        const item = $(this).closest('.campaign-list-item');
        item.find('.campaign-name-view, .campaign-lead-count, .campaign-actions').hide();
        item.find('.campaign-edit-view').show();
    });

    // Edit - Cancel
    $(document).on('click', '.cancel-campaign-btn', function() {
        const item = $(this).closest('.campaign-list-item');
        item.find('.campaign-edit-view').hide();
        item.find('.campaign-name-view, .campaign-lead-count, .campaign-actions').show();
        // Reset input value to original
        item.find('.campaign-name-input').val(item.data('campaign'));
    });

    // Edit - Save (Rename)
    $(document).on('click', '.save-campaign-btn', function() {
        const btn = $(this);
        const item = btn.closest('.campaign-list-item');
        const oldName = item.data('campaign');
        const newName = item.find('.campaign-name-input').val().trim();

        if (!newName || newName === oldName) {
            item.find('.cancel-campaign-btn').click();
            return;
        }

        btn.prop('disabled', true).text('Saving...');

        $.post(ajaxurl, {
            action: 'opbcrm_rename_campaign',
            nonce: opbcrm_dashboard_vars.nonce,
            old_name: oldName,
            new_name: newName
        }).done(function(response) {
            if (response.success) {
                showToast('success', 'Campaign renamed.');
                item.data('campaign', newName);
                item.attr('data-campaign', newName);
                item.find('.campaign-name-view').text(newName);
                item.find('.cancel-campaign-btn').click();
            } else {
                showToast('error', response.data.message || 'Could not rename campaign.');
            }
        }).fail(function() {
            showToast('error', 'Request failed.');
        }).always(function() {
            btn.prop('disabled', false).text('Save');
        });
    });

    // Archive
    $(document).on('click', '.archive-campaign-btn', function() {
        if (!confirm('Are you sure you want to archive this campaign? This will remove it from all associated leads.')) {
            return;
        }

        const btn = $(this);
        const item = btn.closest('.campaign-list-item');
        const campaignName = item.data('campaign');

        btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'opbcrm_archive_campaign',
            nonce: opbcrm_dashboard_vars.nonce,
            campaign_name: campaignName
        }).done(function(response) {
            if (response.success) {
                showToast('success', 'Campaign archived.');
                item.fadeOut(300, function() { $(this).remove(); });
            } else {
                showToast('error', response.data.message || 'Could not archive campaign.');
                btn.prop('disabled', false);
            }
        }).fail(function() {
            showToast('error', 'Request failed.');
            btn.prop('disabled', false);
        });
    });

    function escapeHTML(str) {
        return str.replace(/[&<>"']/g, function(match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[match];
        });
    }

    // --- Campaign Conversion Rate Chart ---
    let campaignConversionChart;
    function renderCampaignConversionChart(data) {
        const ctx = document.getElementById('campaign-conversion-chart');
        if (!ctx) return;
        if (campaignConversionChart) {
            campaignConversionChart.destroy();
        }
        const labels = data.map(item => item.campaign);
        const rates = data.map(item => item.conversion);
        campaignConversionChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: '% Conversion',
                    data: rates,
                    backgroundColor: 'rgba(75,104,182,0.18)',
                    borderColor: '#4b68b6',
                    borderWidth: 2,
                    borderRadius: 8,
                    maxBarThickness: 28,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.x + '%';
                            }
                        },
                        backgroundColor: 'rgba(255,255,255,0.95)',
                        titleColor: '#4b68b6',
                        bodyColor: '#222',
                        borderColor: '#4b68b6',
                        borderWidth: 1,
                        padding: 10,
                        titleFont: { family: 'Inter', weight: 'bold', size: 14 },
                        bodyFont: { family: 'Inter', size: 13 }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { color: '#4b68b6', font: { family: 'Inter', size: 12 } },
                        grid: { color: 'rgba(75,104,182,0.08)' }
                    },
                    y: {
                        ticks: { color: '#222', font: { family: 'Inter', size: 13 } },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // --- Lead Source Breakdown by Campaign ---
    let campaignSourceBreakdownChart;
    function renderCampaignSourceBreakdownDropdownAndChart(breakdown) {
        const select = $('#source-breakdown-campaign-select');
        if (!select.length) return;
        select.empty();
        const campaigns = Object.keys(breakdown);
        if (!campaigns.length) {
            select.append('<option value="">No campaigns</option>');
            renderCampaignSourceBreakdownChart({});
            return;
        }
        campaigns.forEach(c => {
            select.append('<option value="'+escapeHTML(c)+'">'+escapeHTML(c)+'</option>');
        });
        // Initial chart
        renderCampaignSourceBreakdownChart(breakdown[campaigns[0]] || {});
        select.off('change').on('change', function() {
            const val = $(this).val();
            renderCampaignSourceBreakdownChart(breakdown[val] || {});
        });
    }
    function renderCampaignSourceBreakdownChart(data) {
        const ctx = document.getElementById('campaign-source-breakdown-chart');
        if (!ctx) return;
        if (campaignSourceBreakdownChart) campaignSourceBreakdownChart.destroy();
        const sources = Object.keys(data);
        const counts = Object.values(data);
        campaignSourceBreakdownChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: sources,
                datasets: [{
                    data: counts,
                    backgroundColor: [
                        '#4b68b6', '#83A2DB', '#F7B801', '#EA5455', '#2DCE98', '#FF6F61', '#6C63FF', '#F76E11', '#00B8A9', '#F6416C'
                    ],
                    borderWidth: 1.5
                }]
            },
            options: {
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { font: { family: 'Inter', size: 13 }, color: '#222' } },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label+': '+context.parsed+' leads';
                            }
                        },
                        backgroundColor: 'rgba(255,255,255,0.95)',
                        titleColor: '#4b68b6',
                        bodyColor: '#222',
                        borderColor: '#4b68b6',
                        borderWidth: 1,
                        padding: 10,
                        titleFont: { family: 'Inter', weight: 'bold', size: 14 },
                        bodyFont: { family: 'Inter', size: 13 }
                    }
                }
            }
        });
    }

    // --- Leads per Campaign Over Time ---
    let campaignTimeSeriesChart;
    function renderCampaignTimeSeriesChart(timeSeries) {
        const ctx = document.getElementById('campaign-time-series-chart');
        if (!ctx) return;
        if (campaignTimeSeriesChart) campaignTimeSeriesChart.destroy();
        // Collect all months
        const allMonthsSet = new Set();
        Object.values(timeSeries).forEach(camp => Object.keys(camp).forEach(m => allMonthsSet.add(m)));
        const allMonths = Array.from(allMonthsSet).sort();
        // Build datasets
        const colors = ['#4b68b6', '#83A2DB', '#F7B801', '#EA5455', '#2DCE98', '#FF6F61', '#6C63FF', '#F76E11', '#00B8A9', '#F6416C'];
        const campaigns = Object.keys(timeSeries);
        const datasets = campaigns.map((c, i) => ({
            label: c,
            data: allMonths.map(m => timeSeries[c][m] || 0),
            backgroundColor: colors[i % colors.length],
            borderColor: colors[i % colors.length],
            fill: false,
            tension: 0.2,
            pointRadius: 3,
            borderWidth: 2
        }));
        campaignTimeSeriesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: allMonths,
                datasets: datasets
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { font: { family: 'Inter', size: 13 }, color: '#222' } },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label+': '+context.parsed.y+' leads';
                            }
                        },
                        backgroundColor: 'rgba(255,255,255,0.95)',
                        titleColor: '#4b68b6',
                        bodyColor: '#222',
                        borderColor: '#4b68b6',
                        borderWidth: 1,
                        padding: 10,
                        titleFont: { family: 'Inter', weight: 'bold', size: 14 },
                        bodyFont: { family: 'Inter', size: 13 }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#4b68b6', font: { family: 'Inter', size: 12 } },
                        grid: { color: 'rgba(75,104,182,0.08)' }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#222', font: { family: 'Inter', size: 13 } },
                        grid: { color: 'rgba(75,104,182,0.08)' }
                    }
                }
            }
        });
    }

    // --- Inline editing for Campaign Spend ---
    $(document).on('click', '.edit-campaign-spend-btn', function() {
        var item = $(this).closest('.campaign-list-item');
        item.find('.campaign-spend-view').hide();
        item.find('.campaign-spend-edit-row').show();
    });
    $(document).on('click', '.cancel-campaign-spend-btn', function() {
        var item = $(this).closest('.campaign-list-item');
        item.find('.campaign-spend-edit-row').hide();
        item.find('.campaign-spend-view').show();
    });
    $(document).on('click', '.save-campaign-spend-btn', function() {
        var btn = $(this);
        var item = btn.closest('.campaign-list-item');
        var campaign = item.data('campaign');
        var spend = parseFloat(item.find('.campaign-spend-input').val());
        if (isNaN(spend) || spend < 0) {
            showToast('error', 'Please enter a valid spend amount.');
            return;
        }
        btn.prop('disabled', true).text('Saving...');
        $.post(ajaxurl, {
            action: 'opbcrm_update_campaign_spend',
            nonce: opbcrm_dashboard_vars.nonce,
            campaign_name: campaign,
            spend: spend
        }).done(function(response) {
            if (response.success) {
                showToast('success', 'Spend updated.');
                item.find('.campaign-spend-view').html('$'+spend.toFixed(2)+' <button class="edit-campaign-spend-btn crm-icon-btn" title="Edit Spend" style="margin-left:4px;"><i class="fas fa-edit"></i></button>');
                item.find('.campaign-spend-edit-row').hide();
                item.find('.campaign-spend-view').show();
            } else {
                showToast('error', response.data && response.data.message ? response.data.message : 'Could not update spend.');
            }
        }).fail(function() {
            showToast('error', 'Request failed.');
        }).always(function() {
            btn.prop('disabled', false).text('Save');
        });
    });

    // --- Campaign ROI Chart ---
    let campaignROIChart;
    function renderCampaignROIChart(data) {
        const ctx = document.getElementById('campaign-roi-chart');
        if (!ctx) return;
        if (campaignROIChart) campaignROIChart.destroy();
        const labels = data.map(item => item.campaign);
        const roi = data.map(item => item.roi);
        const spend = data.map(item => item.spend);
        const won = data.map(item => item.won_value);
        campaignROIChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ROI (%)',
                    data: roi,
                    backgroundColor: roi.map(v => v === null ? '#eee' : (v >= 0 ? 'rgba(75,182,104,0.18)' : 'rgba(234,84,85,0.18)')),
                    borderColor: roi.map(v => v === null ? '#bbb' : (v >= 0 ? '#2DCE98' : '#EA5455')),
                    borderWidth: 2,
                    borderRadius: 8,
                    maxBarThickness: 28,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const i = context.dataIndex;
                                let txt = 'ROI: ' + (roi[i] === null ? 'N/A' : roi[i] + '%');
                                txt += '\nSpend: $' + spend[i].toFixed(2);
                                txt += '\nWon Value: $' + won[i].toFixed(2);
                                return txt;
                            }
                        },
                        backgroundColor: 'rgba(255,255,255,0.95)',
                        titleColor: '#4b68b6',
                        bodyColor: '#222',
                        borderColor: '#4b68b6',
                        borderWidth: 1,
                        padding: 10,
                        titleFont: { family: 'Inter', weight: 'bold', size: 14 },
                        bodyFont: { family: 'Inter', size: 13 }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#4b68b6', font: { family: 'Inter', size: 12 } },
                        grid: { color: 'rgba(75,104,182,0.08)' }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#222', font: { family: 'Inter', size: 13 } },
                        grid: { color: 'rgba(75,104,182,0.08)' }
                    }
                }
            }
        });
    }
});

(function($) {
    // ... existing code ...
})(jQuery); 