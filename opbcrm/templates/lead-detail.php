<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['opbcrm_user_id'])) {
    wp_redirect(site_url('/crm-login'));
    exit;
}
/**
 * The template for displaying a single lead's details.
 *
 * This template is loaded from class-opbcrm-frontend-dashboard.php.
 * The $lead_id variable is available in this scope.
 */

if (!isset($lead_id)) {
    echo 'No lead specified.';
    return;
}

$lead = get_post($lead_id);
if (!$lead) {
    echo 'Lead not found.';
    return;
}

// Get all lead meta data
$lead_meta = get_post_meta($lead_id);

// Helper to get meta value safely
function get_lead_meta_value($key, $meta_array, $default = '<em>empty</em>') {
    return isset($meta_array[$key][0]) ? esc_html($meta_array[$key][0]) : $default;
}

$stages = get_option('opbez_crm_stages', array());
$current_stage_key = get_post_meta($lead_id, 'lead_status', true);
$current_stage_label = isset($stages[$current_stage_key]) ? $stages[$current_stage_key] : ucfirst(str_replace('-', ' ', $current_stage_key));

$all_stage_keys = array_keys($stages);
$current_stage_index = array_search($current_stage_key, $all_stage_keys);

?>

<div class="opbcrm-single-lead-wrapper">
    <div class="lead-header">
        <h1><?php echo esc_html($lead->post_title); ?></h1>
        <a href="<?php echo esc_url(remove_query_arg('crm-lead-id')); ?>" class="back-to-dashboard-link">&larr; Back to Dashboard</a>
    </div>

    <div class="lead-stages-progress">
        <?php foreach ($stages as $key => $label) : 
            $stage_index = array_search($key, $all_stage_keys);
            $class = '';
            if ($stage_index < $current_stage_index) {
                $class = 'done';
            } elseif ($stage_index == $current_stage_index) {
                $class = 'active';
            }
        ?>
            <div class="stage-item <?php echo $class; ?>"><?php echo esc_html($label); ?></div>
        <?php endforeach; ?>
    </div>

    <div class="opbcrm-lead-tabs glassy-panel" style="margin-top:24px;padding:0 0 0 0;border-radius:16px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.10);backdrop-filter:blur(8px);background:rgba(255,255,255,0.22);border:1.2px solid rgba(255,255,255,0.18);font-family:Inter,sans-serif;">
        <ul class="lead-tab-list" style="display:flex;gap:2px;margin:0 0 18px 0;padding:0;list-style:none;">
            <li class="lead-tab-btn active" data-tab="info" style="padding:10px 22px;cursor:pointer;border-radius:14px 14px 0 0;font-weight:600;font-size:15px;">Lead Information</li>
            <li class="lead-tab-btn" data-tab="history" style="padding:10px 22px;cursor:pointer;border-radius:14px 14px 0 0;font-weight:600;font-size:15px;">History</li>
            <li class="lead-tab-btn" data-tab="recommend" style="padding:10px 22px;cursor:pointer;border-radius:14px 14px 0 0;font-weight:600;font-size:15px;">Recommend</li>
            <li class="lead-tab-btn" data-tab="task" style="padding:10px 22px;cursor:pointer;border-radius:14px 14px 0 0;font-weight:600;font-size:15px;">Task</li>
        </ul>
        <div class="lead-tab-content active" id="tab-info" style="padding:24px;">
            <div class="lead-content-area">
                <div class="lead-details-main">
                    <div class="details-header">
                         <h3>Lead Details</h3>
                         <button id="edit-lead-details-btn" class="opbcrm-btn opbcrm-btn-secondary opbcrm-btn-sm">Edit</button>
                         <button id="save-lead-details-btn" class="opbcrm-btn opbcrm-btn-primary opbcrm-btn-sm" style="display:none;">Save</button>
                    </div>
                    <form id="lead-details-form">
                        <div class="details-grid">
                            <?php
                            // Re-structure to include both view and edit modes
                            $all_fields = [
                                // Standard Fields
                                'lead_status' => ['label' => 'Stage', 'type' => 'text', 'readonly' => true],
                                'lead_phone' => ['label' => 'Phone', 'type' => 'text'],
                                'lead_email' => ['label' => 'Email', 'type' => 'email'],
                                'lead_whatsapp' => ['label' => 'WhatsApp', 'type' => 'text'],
                                'lead_source' => ['label' => 'Source', 'type' => 'text'],
                                'lead_tags' => ['label' => 'Tags', 'type' => 'text'],
                                'lead_sub_stage' => ['label' => 'Sub-Stage / Details', 'type' => 'text'],
                                'lead_campaign' => ['label' => 'Campaign', 'type' => 'text'],
                            ];

                            global $wpdb;
                            $cf_table = $wpdb->prefix . 'opbcrm_custom_fields';
                            $custom_fields = $wpdb->get_results("SELECT * FROM $cf_table ORDER BY field_label ASC");

                            if ($custom_fields) {
                                foreach ($custom_fields as $field) {
                                    $all_fields[$field->field_name] = [
                                        'label' => $field->field_label,
                                        'type' => $field->field_type,
                                        'options' => $field->field_options
                                    ];
                                }
                            }

                            foreach ($all_fields as $name => $props) {
                                $meta_key = strpos($name, 'cf_') === 0 ? '_' . $name : $name;
                                $value = get_post_meta($lead_id, $meta_key, true);
                                if ($name === 'lead_status') { // Special case for stage
                                     $stages = get_option('opbez_crm_stages', array());
                                     $value = isset($stages[$value]) ? $stages[$value] : $value;
                                }
                                if ($name === 'lead_campaign' && !current_user_can('view_campaign')) continue;
                                ?>
                                <div class="detail-item">
                                    <span class="detail-label"><?php echo esc_html($props['label']); ?></span>
                                    <div class="detail-value-wrapper">
                                        <span class="view-mode"><?php echo $value ? esc_html($value) : '<em>empty</em>'; ?></span>
                                        <div class="edit-mode" style="display:none;">
                                            <?php
                                            $field_name = esc_attr($name);
                                            $field_value = esc_attr($value);
                                            $field_type = $props['type'];

                                            if ($props['readonly'] ?? false) {
                                                echo '<span>' . esc_html($value) . '</span>';
                                            } elseif ($field_type === 'textarea') {
                                                echo '<textarea name="' . $field_name . '" class="detail-input">' . $field_value . '</textarea>';
                                            } elseif ($field_type === 'select') {
                                                $options = explode("\n", $props['options']);
                                                echo '<select name="' . $field_name . '" class="detail-input">';
                                                foreach ($options as $option) {
                                                    $option = trim($option);
                                                    echo '<option value="' . esc_attr($option) . '" ' . selected($value, $option, false) . '>' . esc_html($option) . '</option>';
                                                }
                                                echo '</select>';
                                            } else {
                                                $input_type = ($field_type === 'date') ? 'date' : (($field_type === 'number') ? 'number' : 'text');
                                                echo '<input type="' . $input_type . '" name="' . $field_name . '" value="' . $field_value . '" class="detail-input">';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="lead-tab-content" id="tab-history" style="display:none;padding:24px;">
            <div class="lead-activity-feed">
                 <div class="activity-tabs">
                    <button class="activity-tab-btn active" data-tab="comment">Comment</button>
                    <button class="activity-tab-btn" data-tab="task">Task</button>
                    <button class="activity-tab-btn" data-tab="proposal">Proposal</button>
                    <button class="activity-tab-btn" data-tab="activity">History</button>
                    <?php if (current_user_can('view_lead_history')) : ?>
                        <button class="activity-tab-btn" data-tab="history">History</button>
                    <?php endif; ?>
                </div>
                <div class="activity-content">
                    <?php if (current_user_can('edit_leads')) : ?>
                        <!-- Comment Form -->
                        <div id="comment-form" class="activity-form-container active">
                            <div class="add-activity-form">
                                <textarea id="lead-comment-textarea" placeholder="Add a new comment..."></textarea>
                                <button id="add-comment-btn" class="opbcrm-btn opbcrm-btn-primary" data-lead-id="<?php echo esc_attr($lead_id); ?>">Add</button>
                            </div>
                        </div>

                        <!-- Task Form -->
                        <div id="task-form" class="activity-form-container" style="display: none;">
                            <div class="add-activity-form">
                                <textarea id="lead-task-textarea" placeholder="Task description..."></textarea>
                                <div class="task-meta-inputs">
                                    <div class="task-meta-item">
                                        <label for="task-due-date">Due Date:</label>
                                        <input type="datetime-local" id="task-due-date">
                                    </div>
                                    <div class="task-meta-item">
                                         <label for="task-assignee">Assign to:</label>
                                        <select id="task-assignee" name="agent_id">
                                            <?php
                                            $users = get_users(array('role__in' => array('administrator', 'crm_manager', 'crm_agent')));
                                            foreach ($users as $user) {
                                                echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($user->display_name) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <button id="add-task-btn" class="opbcrm-btn opbcrm-btn-primary" data-lead-id="<?php echo esc_attr($lead_id); ?>">Add Task</button>
                            </div>
                        </div>

                        <!-- Proposal Form -->
                        <div id="proposal-form" class="activity-form-container" style="display: none;">
                            <div class="add-activity-form">
                                <h4>Generate New Proposal</h4>
                                <div class="form-group">
                                    <label for="proposal-property-select">Select a Property:</label>
                                    <select id="proposal-property-select" class="detail-input">
                                        <option value="">-- Choose a property --</option>
                                        <?php 
                                        if (!empty($properties)) {
                                            foreach ($properties as $property_post) {
                                                echo '<option value="' . esc_attr($property_post->ID) . '">' . esc_html($property_post->post_title) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <button id="generate-proposal-btn" class="opbcrm-btn opbcrm-btn-primary" data-lead-id="<?php echo esc_attr($lead_id); ?>">Generate & Log Proposal</button>
                                <div id="proposal-status" style="margin-top: 10px;"></div>
                            </div>
                        </div>

                    <?php endif; // end of permission check ?>

                    <!-- Timeline / History -->
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                        <h3 style="font-size:1.1rem;font-weight:600;margin:0;">Activity Timeline</h3>
                        <button id="export-timeline-csv" class="opbcrm-btn opbcrm-btn-secondary opbcrm-btn-sm" style="font-size:13px;padding:6px 18px;border-radius:10px;">Export Timeline (CSV)</button>
                    </div>
                    <div class="activity-timeline" id="activity-timeline">
                        <?php
                        global $opbcrm;
                        $activities = $opbcrm->activity->get_activities_for_lead($lead_id);

                        if (empty($activities)) {
                            echo '<p>No activities recorded yet.</p>';
                        } else {
                            foreach ($activities as $activity) {
                                $user_info = get_userdata($activity->user_id);
                                $user_name = ($user_info) ? $user_info->display_name : 'System';
                                $activity_time = human_time_diff(strtotime($activity->activity_date), current_time('timestamp')) . ' ago';
                                
                                $icon_class = 'fas fa-info-circle'; // Default icon
                                $icon_bg_class = 'comment'; // Default color
                                if ($activity->activity_type === 'stage_change') {
                                    $icon_class = 'fas fa-flag-checkered';
                                    $icon_bg_class = 'stage-change';
                                } elseif ($activity->activity_type === 'comment') {
                                     $icon_class = 'fas fa-comment';
                                     $icon_bg_class = 'comment';
                                } elseif ($activity->activity_type === 'task') {
                                    $icon_class = 'fas fa-check-circle';
                                    $icon_bg_class = 'task';
                                }
                                ?>
                                <div class="timeline-item task-item-<?php echo esc_attr($activity->task_status); ?>">
                                    <div class="item-icon <?php echo esc_attr($icon_bg_class); ?>">
                                        <span><i class="<?php echo esc_attr($icon_class); ?>"></i></span>
                                    </div>
                                    <div class="item-content">
                                        <span class="item-meta">
                                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $activity->activity_type))); ?> by <?php echo esc_html($user_name); ?> - <?php echo esc_html($activity_time); ?>
                                        </span>
                                        <div class="item-body">
                                            <p><?php echo nl2br(esc_html($activity->content)); ?></p>
                                            <?php if ($activity->activity_type === 'task'): ?>
                                                <div class="task-details">
                                                    <?php 
                                                        $assignee = get_userdata($activity->assigned_to_user_id);
                                                        $due_date_formatted = $activity->due_date ? date('M j, Y H:i', strtotime($activity->due_date)) : 'No due date';
                                                    ?>
                                                    <span class="task-assignee">Assigned to: <strong><?php echo esc_html($assignee->display_name); ?></strong></span>
                                                    <span class="task-due-date">Due: <strong><?php echo esc_html($due_date_formatted); ?></strong></span>
                                                </div>
                                                <div class="task-actions">
                                                    <input type="checkbox" class="complete-task-cb" data-task-id="<?php echo esc_attr($activity->activity_id); ?>" <?php checked($activity->task_status, 'completed'); ?>>
                                                    <label>Mark as complete</label>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>
                    <?php if (current_user_can('view_lead_history')) : ?>
                    <div id="history-form" class="activity-form-container" style="display: none;">
                        <div class="lead-history-timeline">
                            <h4>Lead History</h4>
                            <?php
                            global $opbcrm;
                            $activities = $opbcrm->activity->get_activities_for_lead($lead_id);
                            if (empty($activities)) {
                                echo '<p>No history recorded yet.</p>';
                            } else {
                                foreach ($activities as $activity) {
                                    $user_info = get_userdata($activity->user_id);
                                    $user_name = ($user_info) ? $user_info->display_name : 'System';
                                    $activity_time = date('Y-m-d H:i', strtotime($activity->activity_date));
                                    $desc = '';
                                    if ($activity->activity_type === 'stage_change') {
                                        $desc = '<b>Stage changed</b>: ' . esc_html($activity->content);
                                    } elseif ($activity->activity_type === 'assign') {
                                        $desc = '<b>Assigned</b> to: ' . esc_html($activity->content);
                                    } elseif ($activity->activity_type === 'comment') {
                                        $desc = '<b>Comment</b>: ' . esc_html($activity->content);
                                    } elseif ($activity->activity_type === 'task') {
                                        $desc = '<b>Task</b>: ' . esc_html($activity->content);
                                    } else {
                                        $desc = esc_html($activity->content);
                                    }
                                    echo '<div class="history-item">';
                                    echo '<span class="history-date">' . esc_html($activity_time) . '</span> ';
                                    echo '<span class="history-user">' . esc_html($user_name) . '</span> - ';
                                    echo $desc;
                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="lead-tab-content" id="tab-recommend" style="display:none;padding:24px;">
            <!-- Recommend content (property recommendations logic) -->
            // ... property recommendation logic ...
        </div>
        <div class="lead-tab-content" id="tab-task" style="display:none;padding:24px;">
            <!-- Task content (existing task/calendar logic) -->
            // ... existing task/calendar logic ...
        </div>
    </div>
</div>

<div class="lead-notes">
    <h3>Message</h3>
    <p><?php echo isset($custom_fields['message']) ? nl2br(esc_html($custom_fields['message'])) : 'No message provided.'; ?></p>
</div>

<div class="lead-reminders glassy-panel" style="margin-bottom:18px;padding:18px 24px;border-radius:14px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.10);backdrop-filter:blur(8px);background:rgba(255,255,255,0.22);border:1.2px solid rgba(255,255,255,0.18);font-family:Inter,sans-serif;">
    <div style="display:flex;align-items:center;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:8px;">
            <h3 style="font-size:1.1rem;font-weight:600;margin:0;">Reminders</h3>
            <?php
            $has_overdue = false;
            $reminders = array_filter($opbcrm->activity->get_activities_for_lead($lead_id), function($a){ return $a->activity_type==='reminder'; });
            foreach ($reminders as $rem) {
                if ($rem->due_date && strtotime($rem->due_date) < time()) {
                    $has_overdue = true; break;
                }
            }
            if ($has_overdue) {
                echo '<span class="reminder-badge" style="background:#ea5455;color:#fff;font-size:11px;padding:2px 10px;border-radius:8px;font-weight:600;letter-spacing:0.2px;">Overdue</span>';
            }
            ?>
        </div>
        <?php if (current_user_can('edit_leads')): ?>
        <button id="add-reminder-btn" class="opbcrm-btn opbcrm-btn-primary opbcrm-btn-sm" style="font-size:13px;padding:6px 18px;border-radius:10px;">+ Add Reminder</button>
        <?php endif; ?>
    </div>
    <div id="reminders-list" style="margin-top:10px;display:flex;flex-direction:column;gap:8px;">
        <?php
        if (empty($reminders)) {
            echo '<span style="color:#888;font-size:13px;">No reminders set.</span>';
        } else {
            foreach ($reminders as $rem) {
                $due = $rem->due_date ? date('M j, Y H:i', strtotime($rem->due_date)) : 'No due date';
                $is_overdue = $rem->due_date && strtotime($rem->due_date) < time();
                echo '<div class="reminder-item" style="display:flex;align-items:center;gap:10px;">';
                echo '<span class="reminder-msg" style="font-size:13.5px;">'.esc_html($rem->content).'</span>';
                echo '<span class="reminder-due" style="font-size:12px;color:'.($is_overdue?'#ea5455':'#888').';">'.$due.($is_overdue?' (Overdue)':'').'</span>';
                echo '</div>';
            }
        }
        ?>
    </div>
</div>
<div id="reminder-toast" style="display:none;position:fixed;top:32px;right:32px;z-index:9999;min-width:220px;padding:14px 22px;border-radius:12px;background:rgba(234,84,85,0.97);color:#fff;font-size:15px;font-weight:500;box-shadow:0 2px 12px 0 rgba(31,38,135,0.13);font-family:Inter,sans-serif;text-align:center;letter-spacing:0.2px;opacity:0.98;transition:all 0.3s;pointer-events:none;">You have overdue reminders!</div>
<div id="add-reminder-modal" class="opbcrm-modal" style="display:none;">
    <div class="opbcrm-modal-content" style="max-width:400px;">
        <span class="opbcrm-modal-close" id="close-reminder-modal">&times;</span>
        <h3 style="font-family:Inter,sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:12px;">Add Reminder</h3>
        <form id="add-reminder-form">
            <label style="font-size:13px;font-weight:500;">Reminder Message</label>
            <input type="text" name="reminder_msg" class="detail-input" style="width:100%;margin-bottom:12px;" required>
            <label style="font-size:13px;font-weight:500;">Due Date & Time</label>
            <input type="datetime-local" name="reminder_due" class="detail-input" style="width:100%;margin-bottom:18px;">
            <button type="submit" class="opbcrm-btn opbcrm-btn-primary" style="width:100%;font-size:15px;">Add Reminder</button>
        </form>
    </div>
</div>

<?php if (current_user_can('upload_documents')): ?>
<div class="lead-documents glassy-panel" style="margin-bottom:18px;padding:18px 24px;border-radius:14px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.10);backdrop-filter:blur(8px);background:rgba(255,255,255,0.22);border:1.2px solid rgba(255,255,255,0.18);font-family:Inter,sans-serif;">
    <div style="display:flex;align-items:center;justify-content:space-between;">
        <h3 style="font-size:1.1rem;font-weight:600;margin:0;">Documents</h3>
        <form id="lead-document-upload-form" enctype="multipart/form-data" style="display:flex;align-items:center;gap:10px;">
            <input type="file" name="lead_document" id="lead_document" style="font-size:13px;" required>
            <button type="submit" class="opbcrm-btn opbcrm-btn-primary opbcrm-btn-sm" style="font-size:13px;padding:6px 18px;border-radius:10px;">Upload</button>
        </form>
    </div>
    <div id="lead-documents-list" style="margin-top:10px;display:flex;flex-direction:column;gap:8px;">
        <?php
        $docs = get_post_meta($lead_id, 'lead_documents', true);
        $docs = is_array($docs) ? $docs : [];
        if (empty($docs)) {
            echo '<span style="color:#888;font-size:13px;">No documents uploaded.</span>';
        } else {
            foreach ($docs as $doc) {
                $file_url = esc_url($doc['url']);
                $file_name = esc_html($doc['name']);
                $uploaded = date('M j, Y H:i', strtotime($doc['date']));
                echo '<div class="document-item" style="display:flex;align-items:center;gap:10px;">';
                echo '<a href="'.$file_url.'" target="_blank" class="opbcrm-btn opbcrm-btn-secondary opbcrm-btn-sm" style="font-size:13px;">'.$file_name.'</a>';
                echo '<span style="font-size:12px;color:#888;">Uploaded: '.$uploaded.'</span>';
                echo '</div>';
            }
        }
        ?>
    </div>
</div>
<script>
document.getElementById('lead-document-upload-form')?.addEventListener('submit',function(e){
    e.preventDefault();
    var form = this;
    var fileInput = form.lead_document;
    if(!fileInput.files.length) return;
    var data = new FormData();
    data.append('action','upload_lead_document');
    data.append('nonce',opbcrm_ajax.nonce);
    data.append('lead_id','<?php echo esc_js($lead_id); ?>');
    data.append('file',fileInput.files[0]);
    fetch(opbcrm_ajax.ajax_url,{
        method:'POST',
        body:data
    }).then(r=>r.json()).then(resp=>{
        if(resp.success){ location.reload(); }
        else{ alert(resp.data||'Error uploading document'); }
    });
});
</script>
<?php endif; ?>

<?php if (current_user_can('manage_automations')): ?>
<div class="lead-automations glassy-panel" style="margin-bottom:18px;padding:18px 24px;border-radius:14px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.10);backdrop-filter:blur(8px);background:rgba(255,255,255,0.22);border:1.2px solid rgba(255,255,255,0.18);font-family:Inter,sans-serif;">
    <div style="display:flex;align-items:center;justify-content:space-between;">
        <h3 style="font-size:1.1rem;font-weight:600;margin:0;">Automations</h3>
        <button id="add-automation-btn" class="opbcrm-btn opbcrm-btn-primary opbcrm-btn-sm" style="font-size:13px;padding:6px 18px;border-radius:10px;">+ Add Automation</button>
    </div>
    <div id="lead-automations-list" style="margin-top:10px;display:flex;flex-direction:column;gap:8px;">
        <?php
        $automations = get_post_meta($lead_id, 'lead_automations', true);
        $automations = is_array($automations) ? $automations : [];
        if (empty($automations)) {
            echo '<span style="color:#888;font-size:13px;">No automations triggered for this lead.</span>';
        } else {
            foreach ($automations as $auto) {
                $label = esc_html($auto['label']);
                $status = esc_html($auto['status']);
                $date = date('M j, Y H:i', strtotime($auto['date']));
                echo '<div class="automation-item" style="display:flex;align-items:center;gap:10px;">';
                echo '<span class="automation-label" style="font-size:13.5px;font-weight:500;">'.$label.'</span>';
                echo '<span class="automation-status" style="font-size:12px;color:#888;">Status: '.$status.'</span>';
                echo '<span class="automation-date" style="font-size:12px;color:#888;">'.$date.'</span>';
                echo '</div>';
            }
        }
        ?>
    </div>
</div>
<script>
document.getElementById('add-automation-btn')?.addEventListener('click',function(){
    alert('Automation builder coming soon!');
});
</script>
<?php endif; ?>

<style>
/* ... existing styles ... */
.opbcrm-modal {
    display: none; 
    position: fixed; 
    z-index: 1000; 
    left: 0;
    top: 0;
    width: 100%; 
    height: 100%; 
    overflow: auto; 
    background-color: rgba(0,0,0,0.4);
}
.opbcrm-modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 600px;
    position: relative;
}
.opbcrm-modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
</style>

<script>
document.querySelectorAll('.lead-tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.lead-tab-btn').forEach(b=>b.classList.remove('active'));
        this.classList.add('active');
        document.querySelectorAll('.lead-tab-content').forEach(tab=>tab.classList.remove('active'));
        document.getElementById('tab-'+this.dataset.tab).classList.add('active');
        document.querySelectorAll('.lead-tab-content').forEach(tab=>tab.style.display='none');
        document.getElementById('tab-'+this.dataset.tab).style.display='block';
    });
});
document.getElementById('add-reminder-btn')?.addEventListener('click',function(){
    document.getElementById('add-reminder-modal').style.display='block';
});
document.getElementById('close-reminder-modal')?.addEventListener('click',function(){
    document.getElementById('add-reminder-modal').style.display='none';
});
document.getElementById('add-reminder-form')?.addEventListener('submit',function(e){
    e.preventDefault();
    const msg = this.reminder_msg.value.trim();
    const due = this.reminder_due.value;
    if(!msg) return;
    fetch(opbcrm_ajax.ajax_url,{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=add_lead_reminder&nonce='+opbcrm_ajax.nonce+'&lead_id=<?php echo esc_js($lead_id); ?>&msg='+encodeURIComponent(msg)+'&due='+encodeURIComponent(due)
    }).then(r=>r.json()).then(resp=>{
        if(resp.success){
            location.reload();
        }else{
            alert(resp.data||'Error adding reminder');
        }
    });
});
document.getElementById('export-timeline-csv')?.addEventListener('click',function(){
    const rows = [];
    document.querySelectorAll('.activity-timeline .timeline-item').forEach(function(item){
        const type = item.querySelector('.item-meta')?.textContent.split(' by ')[0]?.trim()||'';
        const user = item.querySelector('.item-meta')?.textContent.split(' by ')[1]?.split('-')[0]?.trim()||'';
        const date = item.querySelector('.item-meta')?.textContent.split('-')[1]?.trim()||'';
        const content = item.querySelector('.item-body p')?.textContent||'';
        const due = item.querySelector('.reminder-due')?.textContent||'';
        rows.push([type,user,date,content,due]);
    });
    let csv = 'Type,User,Date,Content,Due Date\n';
    rows.forEach(r=>{csv+=r.map(x=>`"${(x||'').replace(/"/g,'""')}"`).join(',')+'\n';});
    const blob = new Blob([csv],{type:'text/csv'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'lead-timeline-<?php echo esc_js($lead_id); ?>.csv';
    a.click();
});
window.addEventListener('DOMContentLoaded',function(){
    var hasOverdue = <?php echo $has_overdue ? 'true' : 'false'; ?>;
    if(hasOverdue){
        var toast = document.getElementById('reminder-toast');
        toast.style.display = 'block';
        setTimeout(function(){ toast.style.display = 'none'; }, 3200);
    }
});
</script> 