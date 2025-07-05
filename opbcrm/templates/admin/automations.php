<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['opbcrm_user_id'])) {
    wp_redirect(site_url('/crm-login'));
    exit;
}
if (!defined('WPINC')) die;
?>
<div class="crm-glass-panel" style="max-width:900px;margin:32px auto;padding:32px 28px 24px 28px;border-radius:22px;box-shadow:0 8px 32px 0 rgba(31,38,135,0.18);backdrop-filter:blur(12px);background:rgba(255,255,255,0.18);border:1.5px solid rgba(255,255,255,0.22);font-family:'Inter',sans-serif;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
        <span style="font-size:2.1rem;font-weight:700;letter-spacing:-1px;">Automations</span>
        <button id="add-automation-admin-btn" class="crm-btn crm-btn-primary" style="font-size:15px;padding:7px 22px;border-radius:12px;">+ Add Automation</button>
    </div>
    <div id="automations-list" style="margin-top:10px;display:flex;flex-direction:column;gap:12px;">
        <?php
        $automations = get_option('opbcrm_automations', []);
        if (empty($automations)) {
            echo '<span style="color:#888;font-size:15px;">No automations defined yet.</span>';
        } else {
            foreach ($automations as $auto) {
                $label = esc_html($auto['label'] ?? 'Untitled');
                $status = esc_html($auto['status'] ?? 'active');
                $triggers = esc_html($auto['trigger'] ?? '—');
                echo '<div class="automation-row" style="display:flex;align-items:center;gap:18px;background:rgba(255,255,255,0.32);border:1px solid #e5e5e5;padding:14px 18px;border-radius:13px;">';
                echo '<span style="font-size:1.1rem;font-weight:600;">'.$label.'</span>';
                echo '<span style="font-size:13px;color:#888;">Trigger: '.$triggers.'</span>';
                echo '<span style="font-size:13px;color:#888;">Status: '.$status.'</span>';
                echo '</div>';
            }
        }
        ?>
    </div>
</div>
<div id="automation-builder-modal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.18);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:18px;max-width:480px;width:96vw;padding:32px 24px;box-shadow:0 8px 32px 0 rgba(31,38,135,0.18);font-family:'Inter',sans-serif;">
        <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:18px;">Automation Builder</h2>
        <form id="automation-form">
            <input type="hidden" name="auto_id" id="auto_id">
            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-size:14px;font-weight:500;">Label</label>
                <input type="text" name="label" id="auto_label" class="crm-input" style="width:100%;font-size:15px;padding:7px 12px;border-radius:8px;border:1px solid #e0e0e0;" required>
            </div>
            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-size:14px;font-weight:500;">Trigger</label>
                <select name="trigger" id="auto_trigger" class="crm-input" style="width:100%;font-size:15px;padding:7px 12px;border-radius:8px;border:1px solid #e0e0e0;">
                    <option value="">-- Select Trigger --</option>
                    <option value="lead_stage_change">When lead stage changes</option>
                    <option value="lead_created">When lead is created</option>
                    <option value="task_completed">When task is completed</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:18px;">
                <label style="font-size:14px;font-weight:500;">Action</label>
                <select name="action" id="auto_action" class="crm-input" style="width:100%;font-size:15px;padding:7px 12px;border-radius:8px;border:1px solid #e0e0e0;">
                    <option value="">-- Select Action --</option>
                    <option value="send_email">Send Email</option>
                    <option value="assign_agent">Assign Agent</option>
                    <option value="create_task">Create Task</option>
                </select>
            </div>
            <div style="display:flex;gap:12px;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('automation-builder-modal').style.display='none'" class="crm-btn" style="background:#eee;color:#333;">Cancel</button>
                <button type="submit" class="crm-btn crm-btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
<script>
function reloadAutomationsList() {
    location.reload(); // For simplicity, reload page after add/edit/delete
}
document.getElementById('add-automation-admin-btn')?.addEventListener('click',function(){
    document.getElementById('auto_id').value = '';
    document.getElementById('auto_label').value = '';
    document.getElementById('auto_trigger').value = '';
    document.getElementById('auto_action').value = '';
    document.getElementById('automation-builder-modal').style.display='flex';
});
document.querySelectorAll('.automation-edit-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
        var row = btn.closest('.automation-row');
        document.getElementById('auto_id').value = row.dataset.id;
        document.getElementById('auto_label').value = row.dataset.label;
        document.getElementById('auto_trigger').value = row.dataset.trigger;
        document.getElementById('auto_action').value = row.dataset.action;
        document.getElementById('automation-builder-modal').style.display='flex';
    });
});
document.querySelectorAll('.automation-delete-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
        if(confirm('Delete this automation?')){
            var id = btn.closest('.automation-row').dataset.id;
            fetch(ajaxurl,{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=opbcrm_delete_automation&id='+encodeURIComponent(id)
            }).then(r=>r.json()).then(resp=>{ if(resp.success) reloadAutomationsList(); else alert('Error: '+resp.data); });
        }
    });
});
document.getElementById('automation-form')?.addEventListener('submit',function(e){
    e.preventDefault();
    var data = {
        action: 'opbcrm_save_automation',
        automation: JSON.stringify({
            id: document.getElementById('auto_id').value,
            label: document.getElementById('auto_label').value,
            trigger: document.getElementById('auto_trigger').value,
            action: document.getElementById('auto_action').value,
            status: 'active'
        })
    };
    fetch(ajaxurl,{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: Object.keys(data).map(k=>k+'='+encodeURIComponent(data[k])).join('&')
    }).then(r=>r.json()).then(resp=>{
        if(resp.success){ reloadAutomationsList(); }
        else{ alert('Error: '+resp.data); }
    });
});
</script> 