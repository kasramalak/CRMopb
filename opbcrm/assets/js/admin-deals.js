// Deals UI logic
jQuery(function($){
    // Open modal
    $(document).on('click', '#add-deal-btn', function(){
        $('#deal-form')[0].reset();
        $('#deal-id').val('');
        $('#deal-modal-bg').show();
    });
    $(document).on('click', '#close-deal-modal, #cancel-deal-btn', function(){
        $('#deal-modal-bg').hide();
    });
    // Load deals
    function loadDeals(){
        $.post(ajaxurl, {action:'opbcrm_get_deals', nonce:opbcrm_admin_vars.nonce}, function(res){
            if(res.success && res.data.length){
                var rows = '';
                res.data.forEach(function(deal){
                    rows += '<tr data-id="'+deal.id+'">'+
                        '<td>'+escapeHTML(deal.title)+'</td>'+
                        '<td>'+(deal.value ? '$'+parseFloat(deal.value).toFixed(2) : '-')+'</td>'+
                        '<td>'+escapeHTML(deal.stage)+'</td>'+
                        '<td>'+escapeHTML(deal.owner)+'</td>'+
                        '<td>'+(deal.close_date||'-')+'</td>'+
                        '<td>'+escapeHTML(deal.status||'-')+'</td>'+
                        '<td>'+
                          '<button class="crm-btn edit-deal-btn" style="font-size:12px;padding:2px 8px;">Edit</button>'+
                          (opbcrm_admin_vars.can_delete_deals ? ' <button class="crm-btn delete-deal-btn" style="font-size:12px;padding:2px 8px;background:#eee;color:#c82828;">Delete</button>' : '')+
                        '</td>'+
                    '</tr>';
                });
                $('#deals-tbody').html(rows);
            }else{
                $('#deals-tbody').html('<tr><td colspan="7" style="text-align:center;color:#888;padding:32px;">No deals yet.</td></tr>');
            }
        });
    }
    loadDeals();
    // Edit deal
    $(document).on('click', '.edit-deal-btn', function(){
        var row = $(this).closest('tr');
        var id = row.data('id');
        $.post(ajaxurl, {action:'opbcrm_get_deal', nonce:opbcrm_admin_vars.nonce, id:id}, function(res){
            if(res.success){
                var d = res.data;
                $('#deal-id').val(d.id);
                $('#deal-title').val(d.title);
                $('#deal-value').val(d.value);
                $('#deal-stage').val(d.stage);
                $('#deal-owner').val(d.owner);
                $('#deal-close-date').val(d.close_date);
                $('#deal-status').val(d.status);
                $('#deal-notes').val(d.notes);
                $('#deal-modal-bg').show();
            }else{
                showToast('error','Could not load deal.');
            }
        });
    });
    // Save deal
    $(document).on('submit', '#deal-form', function(e){
        e.preventDefault();
        var data = $(this).serializeArray();
        data.push({name:'action',value:'opbcrm_save_deal'});
        data.push({name:'nonce',value:opbcrm_admin_vars.nonce});
        $.post(ajaxurl, data, function(res){
            if(res.success){
                showToast('success','Deal saved.');
                $('#deal-modal-bg').hide();
                loadDeals();
            }else{
                showToast('error',res.data && res.data.message ? res.data.message : 'Could not save deal.');
            }
        });
    });
    // Delete deal
    $(document).on('click', '.delete-deal-btn', function(){
        var row = $(this).closest('tr');
        var id = row.data('id');
        if(!confirm('Delete this deal?')) return;
        $.post(ajaxurl, {action:'opbcrm_delete_deal', nonce:opbcrm_admin_vars.nonce, id:id}, function(res){
            if(res.success){
                showToast('success','Deal deleted.');
                row.fadeOut(200,function(){$(this).remove();});
            }else{
                showToast('error',res.data && res.data.message ? res.data.message : 'Could not delete deal.');
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
    function loadDealsKanban() {
        $.post(ajaxurl, {action:'opbcrm_get_deals', nonce:opbcrm_admin_vars.nonce}, function(res){
            if(res.success && res.data.length){
                var stages = ['new','proposal','negotiation','won','lost'];
                stages.forEach(function(stage){
                    $('#kanban-cards-'+stage).empty();
                });
                res.data.forEach(function(deal){
                    var card = $('<div class="crm-kanban-card" draggable="true" style="background:rgba(255,255,255,0.82);border-radius:10px;padding:10px 12px;box-shadow:0 2px 8px 0 rgba(31,38,135,0.07);font-family:Inter,sans-serif;cursor:grab;">'+
                        '<div style="font-weight:600;font-size:1.01rem;color:#4b68b6;">'+escapeHTML(deal.title)+'</div>'+
                        '<div style="font-size:13px;color:#666;display:flex;gap:12px;align-items:center;margin-top:2px;">'+
                            (deal.value ? '<span><i class="fas fa-dollar-sign"></i> $'+parseFloat(deal.value).toFixed(2)+'</span>' : '')+
                            (deal.owner ? '<span><i class="fas fa-user"></i> '+escapeHTML(deal.owner)+'</span>' : '')+
                            (deal.close_date ? '<span><i class="fas fa-calendar"></i> '+escapeHTML(deal.close_date)+'</span>' : '')+
                        '</div>'+
                        '<div style="font-size:12px;color:#888;margin-top:2px;">'+escapeHTML(deal.status||'')+'</div>'+
                    '</div>').attr('data-id',deal.id);
                    $('#kanban-cards-'+(deal.stage||'new')).append(card);
                });
            }else{
                $('.kanban-cards').empty();
            }
            // Re-init SortableJS
            if (window.Sortable) {
                $('.kanban-cards').each(function(){
                    if (!this._sortable) {
                        this._sortable = Sortable.create(this, {
                            group: 'deals',
                            animation: 150,
                            onAdd: function(evt){
                                var card = $(evt.item);
                                var dealId = card.data('id');
                                var newStage = $(evt.to).closest('.kanban-column').data('stage');
                                $.post(ajaxurl, {action:'opbcrm_update_deal_stage', nonce:opbcrm_admin_vars.nonce, id:dealId, stage:newStage}, function(res){
                                    if(res.success){
                                        showToast('success','Stage updated.');
                                    }else{
                                        showToast('error',res.data && res.data.message ? res.data.message : 'Could not update stage.');
                                        loadDealsKanban();
                                    }
                                });
                            }
                        });
                    }
                });
            }
        });
    }
    // --- Filtering for Deals Table and Kanban ---
    let lastDealAnalytics = null;
    let dealChartFilter = null; // {key, label, values: []}
    function applyDealChartFilter() {
        let chip = document.getElementById('deal-chart-filter-chip');
        if (chip) chip.remove();
        if (!dealChartFilter || !dealChartFilter.values.length) { dealChartFilter = null; return filterDealsUI(); }
        chip = document.createElement('div');
        chip.id = 'deal-chart-filter-chip';
        chip.style = 'display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,0.7);border-radius:9px;padding:4px 13px;font-size:13px;font-family:Inter;margin-bottom:8px;margin-right:8px;box-shadow:0 1px 6px 0 rgba(31,38,135,0.07);';
        chip.innerHTML = `<span>Filtered by <b>${dealChartFilter.label}</b>: <b>${dealChartFilter.values.map(v=>v).join(', ')}</b></span> <button style=\"background:none;border:none;font-size:15px;cursor:pointer;color:#ea5455;\" title=\"Clear\">&times;</button>`;
        chip.querySelector('button').onclick = function(){ dealChartFilter=null; applyDealChartFilter(); };
        const filtersRow = document.querySelector('.crm-filters-row');
        if (filtersRow) filtersRow.parentNode.insertBefore(chip, filtersRow.nextSibling);
        filterDealsUI();
    }
    function filterDealsUI() {
        var q = $('#deals-search-input').val().toLowerCase();
        var stage = $('#deals-stage-filter').val();
        $('#deals-tbody tr').each(function(){
            var row = $(this);
            var title = row.find('td').eq(0).text().toLowerCase();
            var value = row.find('td').eq(1).text().toLowerCase();
            var stg = row.find('td').eq(2).text().toLowerCase();
            var owner = row.find('td').eq(3).text().toLowerCase();
            var status = row.find('td').eq(5).text().toLowerCase();
            let matchesChart = true;
            if (dealChartFilter && dealChartFilter.values.length) {
                if (dealChartFilter.key === 'stage') matchesChart = dealChartFilter.values.map(v=>v.toLowerCase()).includes(stg);
                else if (dealChartFilter.key === 'agent') matchesChart = dealChartFilter.values.map(v=>v.toLowerCase()).includes(owner);
                else if (dealChartFilter.key === 'date') {
                    // Not implemented for table
                }
            }
            var match = (!q || title.includes(q) || owner.includes(q) || status.includes(q) || value.includes(q)) && (!stage || stg === stage.toLowerCase()) && matchesChart;
            row.toggle(match);
        });
        // Kanban
        $('.crm-kanban-card').each(function(){
            var card = $(this);
            var col = card.closest('.kanban-column').data('stage');
            var title = card.find('div').eq(0).text().toLowerCase();
            var meta = card.text().toLowerCase();
            let matchesChart = true;
            if (dealChartFilter && dealChartFilter.values.length) {
                if (dealChartFilter.key === 'stage') matchesChart = dealChartFilter.values.map(v=>v.toLowerCase()).includes(col);
                else if (dealChartFilter.key === 'agent') matchesChart = dealChartFilter.values.map(v=>v.toLowerCase()).some(a=>meta.includes(a));
            }
            var match = (!q || title.includes(q) || meta.includes(q)) && (!stage || col === stage) && matchesChart;
            card.toggle(match);
        });
    }
    $(document).on('input change', '#deals-search-input, #deals-stage-filter', filterDealsUI);
    // Re-filter after loading
    var origLoadDeals = loadDeals;
    loadDeals = function(){ origLoadDeals(); setTimeout(filterDealsUI, 100); };
    var origLoadDealsKanban = loadDealsKanban;
    loadDealsKanban = function(){ origLoadDealsKanban(); setTimeout(filterDealsUI, 100); };
    // --- Deal Analytics Dashboard ---
    function addDealChartClickHandlers() {
        function handleMultiSelect(chart, key, label) {
            chart.options.onClick = function(e, els) {
                if (!dealChartFilter || dealChartFilter.key !== key) dealChartFilter = {key, label, values: []};
                if (els.length) {
                    const idx = els[0].index;
                    const val = this.data.labels[idx];
                    const i = dealChartFilter.values.indexOf(val);
                    if (e.ctrlKey || e.metaKey) {
                        if (i === -1) dealChartFilter.values.push(val); else dealChartFilter.values.splice(i,1);
                    } else {
                        dealChartFilter.values = (i === -1) ? [val] : [];
                    }
                    applyDealChartFilter();
                }
            };
            chart.update();
        }
        if (window.dealStagePie) handleMultiSelect(window.dealStagePie, 'stage', 'Stage');
        if (window.dealAgentBar) handleMultiSelect(window.dealAgentBar, 'agent', 'Agent');
        if (window.dealWonLine) handleMultiSelect(window.dealWonLine, 'date', 'Won Date');
    }
    function renderDealAnalytics(data) {
        lastDealAnalytics = data;
        $('#deal-total-count').text(data.total);
        $('#deal-pipeline-value').text('$'+parseFloat(data.pipeline||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}));
        // Pie: By Stage
        if(window.dealStagePie) window.dealStagePie.destroy();
        window.dealStagePie = new Chart(document.getElementById('deal-stage-pie').getContext('2d'), {
            type:'doughnut',
            data:{
                labels:Object.keys(data.by_stage),
                datasets:[{data:Object.values(data.by_stage),backgroundColor:['#4b68b6','#83A2DB','#F7B801','#2DCE98','#EA5455']}]
            },
            options:{plugins:{legend:{labels:{font:{family:'Inter',size:12}}}},cutout:'65%'}
        });
        // Bar: By Agent
        if(window.dealAgentBar) window.dealAgentBar.destroy();
        window.dealAgentBar = new Chart(document.getElementById('deal-agent-bar').getContext('2d'), {
            type:'bar',
            data:{
                labels:Object.keys(data.by_agent),
                datasets:[{data:Object.values(data.by_agent),backgroundColor:'#4b68b6'}]
            },
            options:{plugins:{legend:{display:false}},scales:{x:{ticks:{font:{family:'Inter',size:12}}},y:{beginAtZero:true}}}
        });
        // Line: Won Over Time
        if(window.dealWonLine) window.dealWonLine.destroy();
        window.dealWonLine = new Chart(document.getElementById('deal-won-line').getContext('2d'), {
            type:'line',
            data:{
                labels:Object.keys(data.won_over_time),
                datasets:[{data:Object.values(data.won_over_time),label:'Won',borderColor:'#28a745',backgroundColor:'rgba(40,180,80,0.13)',tension:0.3,fill:true}]
            },
            options:{plugins:{legend:{labels:{font:{family:'Inter',size:12}}}},scales:{x:{ticks:{font:{family:'Inter',size:12}}},y:{beginAtZero:true}}}
        });
        addDealChartClickHandlers();
    }
    function fetchDealAnalytics(start, end) {
        const overlay = document.createElement('div');
        overlay.className = 'crm-analytics-loading';
        overlay.style = 'position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.7);z-index:10;backdrop-filter:blur(2px);display:flex;align-items:center;justify-content:center;font-family:Inter;font-size:1.2rem;';
        overlay.innerHTML = '<span>Loading...</span>';
        const panel = document.querySelector('.crm-glass-panel');
        panel.appendChild(overlay);
        $.post(ajaxurl, {action:'opbcrm_get_deal_analytics', nonce:opbcrm_admin_vars.nonce, start:start||'', end:end||''}, function(res){
            overlay.remove();
            if(res.success){
                renderDealAnalytics(res.data);
            }else{
                showToast('error',res.data && res.data.message ? res.data.message : 'Error loading analytics');
            }
        }).fail(function(){
            overlay.remove();
            showToast('error','Error loading analytics');
        });
    }
    $(document).on('click','.crm-csv-btn',function(){
        const btn = this;
        const type = btn.dataset.csv;
        if (!lastDealAnalytics) return;
        let csv = '', filename = 'deals-analytics-'+type+'-'+(new Date().toISOString().slice(0,10))+'.csv';
        if (type === 'total') {
            csv = 'Total Deals\n'+lastDealAnalytics.total+'\n';
        } else if (type === 'pipeline') {
            csv = 'Pipeline Value\n'+lastDealAnalytics.pipeline+'\n';
        } else if (type === 'stage') {
            csv = 'Stage,Count\n';
            Object.entries(lastDealAnalytics.by_stage).forEach(([k,v])=>{csv+=`"${k}",${v}\n`;});
        } else if (type === 'agent') {
            csv = 'Agent,Count\n';
            Object.entries(lastDealAnalytics.by_agent).forEach(([k,v])=>{csv+=`"${k}",${v}\n`;});
        } else if (type === 'won') {
            csv = 'Date,Won\n';
            Object.entries(lastDealAnalytics.won_over_time).forEach(([k,v])=>{csv+=`"${k}",${v}\n`;});
        }
        const blob = new Blob([csv], {type:'text/csv'});
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        a.click();
    });
    function getDealRange(range) {
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
    let currentDealRange = 'all';
    $(document).on('click','.deal-report-chip',function(){
        $('.deal-report-chip').removeClass('active');
        $(this).addClass('active');
        currentDealRange = this.dataset.range;
        const {start, end} = getDealRange(currentDealRange);
        $('#deal-report-start').val(start||'');
        $('#deal-report-end').val(end||'');
        fetchDealAnalytics(start, end);
    });
    $('#deal-report-start').on('change', function(){
        fetchDealAnalytics(this.value, $('#deal-report-end').val());
    });
    $('#deal-report-end').on('change', function(){
        fetchDealAnalytics($('#deal-report-start').val(), this.value);
    });
    // Initial load
    fetchDealAnalytics('', '');
}); 