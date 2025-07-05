// Tasks UI logic
jQuery(function($){
    // Open modal
    $(document).on('click', '#add-task-btn', function(){
        $('#task-form')[0].reset();
        $('#task-id').val('');
        $('#task-modal-bg').show();
    });
    $(document).on('click', '#close-task-modal, #cancel-task-btn', function(){
        $('#task-modal-bg').hide();
    });
    // Load tasks
    function loadTasks(){
        $.post(ajaxurl, {action:'opbcrm_get_tasks', nonce:opbcrm_admin_vars.nonce}, function(res){
            if(res.success && res.data.length){
                var rows = '';
                res.data.forEach(function(task){
                    rows += '<tr data-id="'+task.id+'" data-type="'+task.type+'" data-status="'+task.status+'">'+
                        '<td><input type="checkbox" class="task-row-checkbox" data-id="'+task.id+'"></td>'+
                        '<td>'+escapeHTML(task.title)+'</td>'+
                        '<td>'+escapeHTML(task.type)+'</td>'+
                        '<td>'+(task.due_date||'-')+'</td>'+
                        '<td>'+escapeHTML(task.assigned_to)+'</td>'+
                        '<td>'+escapeHTML(task.related)+'</td>'+
                        '<td>'+escapeHTML(task.status||'-')+'</td>'+
                        '<td>'+
                          '<button class="crm-btn edit-task-btn" style="font-size:12px;padding:2px 8px;">Edit</button>'+
                          (opbcrm_admin_vars.can_edit_tasks ? ' <button class="crm-btn complete-task-btn" style="font-size:12px;padding:2px 8px;background:#e0ffe0;color:#28a745;">Complete</button>' : '')+
                          (opbcrm_admin_vars.can_delete_tasks ? ' <button class="crm-btn delete-task-btn" style="font-size:12px;padding:2px 8px;background:#eee;color:#c82828;">Delete</button>' : '')+
                        '</td>'+
                    '</tr>';
                });
                $('#tasks-tbody').html(rows);
            }else{
                $('#tasks-tbody').html('<tr><td colspan="7" style="text-align:center;color:#888;padding:32px;">No tasks yet.</td></tr>');
            }
        });
    }
    loadTasks();
    // Edit task
    $(document).on('click', '.edit-task-btn', function(){
        var row = $(this).closest('tr');
        var id = row.data('id');
        $.post(ajaxurl, {action:'opbcrm_get_task', nonce:opbcrm_admin_vars.nonce, id:id}, function(res){
            if(res.success){
                var t = res.data;
                $('#task-id').val(t.id);
                $('#task-title').val(t.title);
                $('#task-type').val(t.type);
                $('#task-due-date').val(t.due_date);
                $('#task-assigned-to').val(t.assigned_to);
                $('#task-related').val(t.related);
                $('#task-status').val(t.status);
                $('#task-notes').val(t.notes);
                $('#task-modal-bg').show();
            }else{
                showToast('error','Could not load task.');
            }
        });
    });
    // Save task
    $(document).on('submit', '#task-form', function(e){
        e.preventDefault();
        var data = $(this).serializeArray();
        data.push({name:'action',value:'opbcrm_save_task'});
        data.push({name:'nonce',value:opbcrm_admin_vars.nonce});
        $.post(ajaxurl, data, function(res){
            if(res.success){
                showToast('success','Task saved.');
                $('#task-modal-bg').hide();
                loadTasks();
            }else{
                showToast('error',res.data && res.data.message ? res.data.message : 'Could not save task.');
            }
        });
    });
    // Delete task
    $(document).on('click', '.delete-task-btn', function(){
        var row = $(this).closest('tr');
        var id = row.data('id');
        if(!confirm('Delete this task?')) return;
        $.post(ajaxurl, {action:'opbcrm_delete_task', nonce:opbcrm_admin_vars.nonce, id:id}, function(res){
            if(res.success){
                showToast('success','Task deleted.');
                row.fadeOut(200,function(){$(this).remove();});
            }else{
                showToast('error',res.data && res.data.message ? res.data.message : 'Could not delete task.');
            }
        });
    });
    // Mark complete
    $(document).on('click', '.complete-task-btn', function(){
        var row = $(this).closest('tr');
        var id = row.data('id');
        $.post(ajaxurl, {action:'opbcrm_complete_task', nonce:opbcrm_admin_vars.nonce, id:id}, function(res){
            if(res.success){
                showToast('success','Task marked complete.');
                row.find('td').eq(5).text('Completed');
            }else{
                showToast('error',res.data && res.data.message ? res.data.message : 'Could not complete task.');
            }
        });
    });
    function escapeHTML(str){
        return (str||'').replace(/[&<>"']/g,function(m){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];});
    }
    function showToast(type,msg){
        var t = $('#crm-toast');
        if(!t.length){
            t = $('<div id="crm-toast"></div>').appendTo('body');
        }
        t.text(msg).css({background:type==='success'?'rgba(40,180,80,0.97)':'rgba(234,84,85,0.97)',color:'#fff'}).fadeIn(150).delay(1800).fadeOut(400);
    }
    let chartFilter = null; // {key, label, values: []}
    function applyChartFilter() {
        let chip = document.getElementById('chart-filter-chip');
        if (chip) chip.remove();
        if (!chartFilter || !chartFilter.values.length) { chartFilter = null; return filterTasksTable(); }
        chip = document.createElement('div');
        chip.id = 'chart-filter-chip';
        chip.style = 'display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,0.7);border-radius:9px;padding:4px 13px;font-size:13px;font-family:Inter;margin-bottom:8px;margin-right:8px;box-shadow:0 1px 6px 0 rgba(31,38,135,0.07);';
        chip.innerHTML = `<span>Filtered by <b>${chartFilter.label}</b>: <b>${chartFilter.values.map(v=>v).join(', ')}</b></span> <button style="background:none;border:none;font-size:15px;cursor:pointer;color:#ea5455;" title="Clear">&times;</button>`;
        chip.querySelector('button').onclick = function(){ chartFilter=null; applyChartFilter(); };
        const filtersRow = document.querySelector('.crm-filters-row');
        if (filtersRow) filtersRow.parentNode.insertBefore(chip, filtersRow.nextSibling);
        filterTasksTable();
    }
    function filterTasksTable() {
        const search = (document.getElementById('tasks-search-input')?.value || '').toLowerCase();
        const type = document.getElementById('tasks-type-filter')?.value || '';
        const status = document.getElementById('tasks-status-filter')?.value || '';
        const assignee = document.getElementById('tasks-assignee-filter')?.value || '';
        const due = document.querySelector('.due-chip.active')?.dataset.due || '';
        const today = new Date();
        today.setHours(0,0,0,0);
        function parseDate(str) {
            if (!str) return null;
            const d = new Date(str);
            return isNaN(d) ? null : d;
        }
        document.querySelectorAll('.crm-tasks-table tbody tr').forEach(row => {
            let text = row.innerText.toLowerCase();
            let matchesSearch = !search || text.includes(search);
            let matchesType = !type || (row.dataset.type === type);
            let matchesStatus = !status || (row.dataset.status === status);
            let matchesAssignee = !assignee || (row.querySelector('.task-assignee') && row.querySelector('.task-assignee').innerText.trim() === assignee);
            let matchesDue = true;
            if (due) {
                const dueCell = row.querySelector('.task-due');
                const dueDate = dueCell ? parseDate(dueCell.innerText.trim()) : null;
                if (due === 'overdue') {
                    matchesDue = dueDate && dueDate < today;
                } else if (due === 'today') {
                    matchesDue = dueDate && dueDate.getTime() === today.getTime();
                } else if (due === 'week') {
                    const weekEnd = new Date(today); weekEnd.setDate(today.getDate()+7);
                    matchesDue = dueDate && dueDate >= today && dueDate <= weekEnd;
                } else if (due === 'future') {
                    matchesDue = dueDate && dueDate > today;
                }
            }
            let matchesChart = true;
            if (chartFilter && chartFilter.values.length) {
                if (chartFilter.key === 'status') matchesChart = chartFilter.values.includes(row.dataset.status);
                else if (chartFilter.key === 'type') matchesChart = chartFilter.values.includes(row.dataset.type);
                else if (chartFilter.key === 'assignee') matchesChart = chartFilter.values.includes(row.querySelector('.task-assignee')?.innerText.trim());
                else if (chartFilter.key === 'date') {
                    const dueCell = row.querySelector('.task-due');
                    matchesChart = dueCell && chartFilter.values.includes(dueCell.innerText.trim());
                }
            }
            row.style.display = (matchesSearch && matchesType && matchesStatus && matchesAssignee && matchesDue && matchesChart) ? '' : 'none';
        });
    }
    document.addEventListener('DOMContentLoaded', function() {
        const search = document.getElementById('tasks-search-input');
        const type = document.getElementById('tasks-type-filter');
        const status = document.getElementById('tasks-status-filter');
        const assignee = document.getElementById('tasks-assignee-filter');
        if (search) search.addEventListener('input', filterTasksTable);
        if (type) type.addEventListener('change', filterTasksTable);
        if (status) status.addEventListener('change', filterTasksTable);
        if (assignee) assignee.addEventListener('change', filterTasksTable);
        document.querySelectorAll('.due-chip').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.due-chip').forEach(b=>b.classList.remove('active'));
                this.classList.add('active');
                filterTasksTable();
            });
        });
        // Delegate for row checkboxes
        document.body.addEventListener('change', function(e) {
            if (e.target.classList.contains('task-row-checkbox')) {
                updateBulkCount();
            }
        });
        // Select all
        const selectAll = document.getElementById('tasks-select-all');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                document.querySelectorAll('.task-row-checkbox').forEach(cb => { cb.checked = selectAll.checked; });
                updateBulkCount();
            });
        }
        // Bulk Complete
        document.querySelector('.crm-bulk-complete')?.addEventListener('click', function() {
            const ids = Array.from(document.querySelectorAll('.task-row-checkbox:checked')).map(cb => cb.dataset.id);
            if (!ids.length) return;
            if (!confirm('Mark selected tasks as complete?')) return;
            ids.forEach(id => {
                fetch(ajaxurl, {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=opbcrm_complete_task&nonce='+opbcrm_admin_vars.nonce+'&id='+id})
                    .then(r=>r.json()).then(()=>{ /* Optionally update UI */ location.reload(); });
            });
        });
        // Bulk Delete
        document.querySelector('.crm-bulk-delete')?.addEventListener('click', function() {
            const ids = Array.from(document.querySelectorAll('.task-row-checkbox:checked')).map(cb => cb.dataset.id);
            if (!ids.length) return;
            if (!confirm('Delete selected tasks? This cannot be undone.')) return;
            ids.forEach(id => {
                fetch(ajaxurl, {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=opbcrm_delete_task&nonce='+opbcrm_admin_vars.nonce+'&id='+id})
                    .then(r=>r.json()).then(()=>{ /* Optionally update UI */ location.reload(); });
            });
        });
        // Bulk Export CSV
        document.querySelector('.crm-bulk-export')?.addEventListener('click', function() {
            const rows = Array.from(document.querySelectorAll('.task-row-checkbox:checked')).map(cb => cb.closest('tr'));
            if (!rows.length) return;
            let csv = 'Title,Type,Due Date,Assigned To,Related,Status\n';
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                csv += [1,2,3,4,5,6].map(i=>`"${cells[i]?.innerText.replace(/"/g,'""')||''}"`).join(',')+'\n';
            });
            const blob = new Blob([csv], {type:'text/csv'});
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'tasks.csv';
            a.click();
        });
        // Bulk Edit modal logic
        const bulkEditBtn = document.querySelector('.crm-bulk-edit');
        const bulkEditModal = document.getElementById('crm-bulk-edit-modal');
        const bulkEditCancel = document.getElementById('bulk-edit-cancel');
        const bulkEditApply = document.getElementById('bulk-edit-apply');
        if (bulkEditBtn && bulkEditModal) {
            bulkEditBtn.addEventListener('click', function() {
                bulkEditModal.style.display = 'block';
            });
        }
        if (bulkEditCancel && bulkEditModal) {
            bulkEditCancel.addEventListener('click', function() {
                bulkEditModal.style.display = 'none';
            });
        }
        if (bulkEditApply) {
            bulkEditApply.addEventListener('click', function() {
                const ids = Array.from(document.querySelectorAll('.task-row-checkbox:checked')).map(cb => cb.dataset.id);
                if (!ids.length) return;
                const assignee = document.getElementById('bulk-edit-assignee').value;
                const type = document.getElementById('bulk-edit-type').value;
                const due_date = document.getElementById('bulk-edit-due-date').value;
                const status = document.getElementById('bulk-edit-status').value;
                if (!assignee && !type && !due_date && !status) { alert('Select at least one field to update.'); return; }
                bulkEditApply.disabled = true;
                let completed = 0;
                ids.forEach(id => {
                    const data = new URLSearchParams();
                    data.append('action', 'opbcrm_bulk_edit_task');
                    data.append('nonce', opbcrm_admin_vars.nonce);
                    data.append('id', id);
                    if (assignee) data.append('assignee', assignee);
                    if (type) data.append('type', type);
                    if (due_date) data.append('due_date', due_date);
                    if (status) data.append('status', status);
                    fetch(ajaxurl, {method:'POST',body:data})
                        .then(r=>r.json()).then(resp=>{
                            completed++;
                            if (completed === ids.length) {
                                bulkEditModal.style.display = 'none';
                                bulkEditApply.disabled = false;
                                showToast('Tasks updated!', 'success');
                                setTimeout(()=>location.reload(), 800);
                            }
                        });
                });
            });
        }
        // Task analytics: quick chips and date pickers
        function getRange(range) {
            const today = new Date();
            let start = '', end = '';
            if (range === 'month') {
                start = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0,10);
                end = new Date(today.getFullYear(), today.getMonth()+1, 0).toISOString().slice(0,10);
            } else if (range === '30d') {
                const d = new Date(today); d.setDate(today.getDate()-29);
                start = d.toISOString().slice(0,10);
                end = today.toISOString().slice(0,10);
            } else if (range === 'year') {
                start = new Date(today.getFullYear(), 0, 1).toISOString().slice(0,10);
                end = new Date(today.getFullYear(), 11, 31).toISOString().slice(0,10);
            }
            return {start, end};
        }
        let currentRange = 'all';
        document.querySelectorAll('.task-report-chip').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.task-report-chip').forEach(b=>b.classList.remove('active'));
                this.classList.add('active');
                currentRange = this.dataset.range;
                const {start, end} = getRange(currentRange);
                document.getElementById('task-report-start').value = start||'';
                document.getElementById('task-report-end').value = end||'';
                fetchTaskAnalytics(start, end);
            });
        });
        document.getElementById('task-report-start').addEventListener('change', function() {
            fetchTaskAnalytics(this.value, document.getElementById('task-report-end').value);
        });
        document.getElementById('task-report-end').addEventListener('change', function() {
            fetchTaskAnalytics(document.getElementById('task-report-start').value, this.value);
        });
        // Initial load
        fetchTaskAnalytics('', '');
        document.body.addEventListener('click', function(e) {
            if (e.target.closest('.crm-csv-btn')) {
                const btn = e.target.closest('.crm-csv-btn');
                const type = btn.dataset.csv;
                if (!lastTaskAnalytics) return;
                let csv = '', filename = 'tasks-analytics-'+type+'-'+(new Date().toISOString().slice(0,10))+'.csv';
                if (type === 'total') {
                    csv = 'Total Tasks\n'+lastTaskAnalytics.total+'\n';
                } else if (type === 'overdue') {
                    csv = 'Overdue Tasks\n'+lastTaskAnalytics.overdue+'\n';
                } else if (type === 'status') {
                    csv = 'Status,Count\n';
                    Object.entries(lastTaskAnalytics.by_status).forEach(([k,v])=>{csv+=`"${k}",${v}\n`;});
                } else if (type === 'type') {
                    csv = 'Type,Count\n';
                    Object.entries(lastTaskAnalytics.by_type).forEach(([k,v])=>{csv+=`"${k}",${v}\n`;});
                } else if (type === 'assignee') {
                    csv = 'Assignee,Count\n';
                    Object.entries(lastTaskAnalytics.by_assignee).forEach(([k,v])=>{csv+=`"${k}",${v}\n`;});
                } else if (type === 'completed') {
                    csv = 'Date,Completed\n';
                    Object.entries(lastTaskAnalytics.completed_over_time).forEach(([k,v])=>{csv+=`"${k}",${v}\n`;});
                }
                const blob = new Blob([csv], {type:'text/csv'});
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = filename;
                a.click();
            }
        });
        addChartClickHandlers();
    });
    // --- Bulk Actions Logic ---
    function updateBulkCount() {
        const checked = document.querySelectorAll('.task-row-checkbox:checked');
        document.querySelector('.crm-bulk-count').textContent = checked.length + ' selected';
        document.querySelectorAll('.crm-bulk-complete, .crm-bulk-delete, .crm-bulk-export').forEach(btn => {
            btn.disabled = checked.length === 0;
        });
    }
    // --- Tasks Analytics Dashboard ---
    let lastTaskAnalytics = null;
    function addChartClickHandlers() {
        function handleMultiSelect(chart, key, label) {
            chart.options.onClick = function(e, els) {
                if (!chartFilter || chartFilter.key !== key) chartFilter = {key, label, values: []};
                if (els.length) {
                    const idx = els[0].index;
                    const val = this.data.labels[idx];
                    const i = chartFilter.values.indexOf(val);
                    if (e.ctrlKey || e.metaKey) {
                        if (i === -1) chartFilter.values.push(val); else chartFilter.values.splice(i,1);
                    } else {
                        chartFilter.values = (i === -1) ? [val] : [];
                    }
                    applyChartFilter();
                }
            };
            chart.update();
        }
        if (window.taskStatusPie) handleMultiSelect(window.taskStatusPie, 'status', 'Status');
        if (window.taskTypeBar) handleMultiSelect(window.taskTypeBar, 'type', 'Type');
        if (window.taskAssigneeBar) handleMultiSelect(window.taskAssigneeBar, 'assignee', 'Assignee');
        if (window.taskCompletedLine) handleMultiSelect(window.taskCompletedLine, 'date', 'Completed Date');
    }
    function renderTaskAnalytics(data) {
        lastTaskAnalytics = data;
        document.getElementById('task-total-count').textContent = data.total;
        document.getElementById('task-overdue-count').textContent = data.overdue;
        // Pie: By Status
        if (window.taskStatusPie) window.taskStatusPie.destroy();
        window.taskStatusPie = new Chart(document.getElementById('task-status-pie').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(data.by_status),
                datasets: [{ data: Object.values(data.by_status), backgroundColor: ['#83A2DB','#ea5455','#ffc107','#28a745','#888'] }]
            },
            options: { plugins: { legend: { labels: { font: { family: 'Inter', size: 12 } } } }, cutout: '65%' }
        });
        // Bar: By Type
        if (window.taskTypeBar) window.taskTypeBar.destroy();
        window.taskTypeBar = new Chart(document.getElementById('task-type-bar').getContext('2d'), {
            type: 'bar',
            data: {
                labels: Object.keys(data.by_type),
                datasets: [{ data: Object.values(data.by_type), backgroundColor: '#83A2DB' }]
            },
            options: { plugins: { legend: { display: false } }, scales: { x: { ticks: { font: { family: 'Inter', size: 12 } } }, y: { beginAtZero: true } } }
        });
        // Bar: By Assignee
        if (window.taskAssigneeBar) window.taskAssigneeBar.destroy();
        window.taskAssigneeBar = new Chart(document.getElementById('task-assignee-bar').getContext('2d'), {
            type: 'bar',
            data: {
                labels: Object.keys(data.by_assignee),
                datasets: [{ data: Object.values(data.by_assignee), backgroundColor: '#ffc107' }]
            },
            options: { plugins: { legend: { display: false } }, scales: { x: { ticks: { font: { family: 'Inter', size: 12 } } }, y: { beginAtZero: true } } }
        });
        // Line: Completed Over Time
        if (window.taskCompletedLine) window.taskCompletedLine.destroy();
        window.taskCompletedLine = new Chart(document.getElementById('task-completed-line').getContext('2d'), {
            type: 'line',
            data: {
                labels: Object.keys(data.completed_over_time),
                datasets: [{ data: Object.values(data.completed_over_time), label: 'Completed', borderColor: '#28a745', backgroundColor: 'rgba(40,180,80,0.13)', tension: 0.3, fill: true }]
            },
            options: { plugins: { legend: { labels: { font: { family: 'Inter', size: 12 } } } }, scales: { x: { ticks: { font: { family: 'Inter', size: 12 } } }, y: { beginAtZero: true } } }
        });
        addChartClickHandlers();
    }
    function fetchTaskAnalytics(start, end) {
        const overlay = document.createElement('div');
        overlay.className = 'crm-analytics-loading';
        overlay.style = 'position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.7);z-index:10;backdrop-filter:blur(2px);display:flex;align-items:center;justify-content:center;font-family:Inter;font-size:1.2rem;';
        overlay.innerHTML = '<span>Loading...</span>';
        const panel = document.querySelector('.crm-glass-panel');
        panel.appendChild(overlay);
        fetch(ajaxurl, {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=opbcrm_get_task_analytics&nonce='+opbcrm_admin_vars.nonce+'&start='+encodeURIComponent(start||'')+'&end='+encodeURIComponent(end||'')})
            .then(r=>r.json()).then(resp=>{
                overlay.remove();
                if (resp.success) {
                    renderTaskAnalytics(resp.data);
                } else {
                    showToast(resp.data && resp.data.message ? resp.data.message : 'Error loading analytics', 'error');
                }
            }).catch(()=>{
                overlay.remove();
                showToast('Error loading analytics', 'error');
            });
    }
}); 