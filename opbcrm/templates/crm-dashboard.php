<?php
/**
 * Template for the CRM Dashboard.
 * This template is used for both the frontend user dashboard and the admin-side dashboard.
 */

// Protect CRM dashboard: require CRM login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['opbcrm_user_id'])) {
    wp_redirect(site_url('/crm-login'));
    exit;
}

if (!defined('WPINC')) {
    die;
}

$current_user_id = get_current_user_id();

// Get stages from our new management system
$stage_groups = get_option('opbcrm_lead_stages', [
    'initial' => [['id' => 'fresh_leads', 'label' => 'Fresh Leads', 'color' => '#00ff00']],
    'additional' => [
        ['id' => 'hold_call_again', 'label' => 'Hold/Call Again', 'color' => '#ffff00'],
        ['id' => 'ongoing_in_progress', 'label' => 'Ongoing-In Progress', 'color' => '#ff0000'],
    ],
    'success' => [['id' => 'won_deal', 'label' => 'Won Deal', 'color' => '#007bff']],
    'failed' => [['id' => 'close_lead', 'label' => 'Close Lead', 'color' => '#000000']],
]);

// Combine all stages into a single flat array for easier lookup
$all_stages = array_merge(
    $stage_groups['initial'],
    $stage_groups['additional'],
    $stage_groups['success'],
    $stage_groups['failed']
);

$stages_map = [];
foreach ($all_stages as $stage) {
    $stages_map[$stage['id']] = $stage;
}


// Setup WP_Query to get leads
$args = array(
    'post_type' => 'opbez_lead',
    'post_status' => 'publish',
    'posts_per_page' => -1,
);

// ACCESS CONTROL: Check if user can view all leads or only their own.
if (!current_user_can('view_others_leads')) {
    $args['author'] = $current_user_id;
}

$leads_query = new WP_Query($args);
$leads_by_stage = [];

// Group leads by their stage
if ($leads_query->have_posts()) {
    while ($leads_query->have_posts()) {
        $leads_query->the_post();
        $lead_id = get_the_ID();
        $lead_status = get_post_meta($lead_id, 'lead_status', true);

        if (empty($lead_status)) {
            // If lead has no status, assign it to the initial stage
            $lead_status = !empty($stage_groups['initial'][0]['id']) ? $stage_groups['initial'][0]['id'] : 'new';
        }
        
        if (!isset($leads_by_stage[$lead_status])) {
            $leads_by_stage[$lead_status] = [];
        }
        $leads_by_stage[$lead_status][] = get_post();
    }
    wp_reset_postdata();
}

// Global overdue reminders for current user
$overdue_reminders = [];
if (isset($opbcrm) && method_exists($opbcrm->activity, 'get_reminders_for_user')) {
    $reminders = $opbcrm->activity->get_reminders_for_user($current_user_id);
    foreach ($reminders as $rem) {
        if ($rem->due_date && strtotime($rem->due_date) < time() && $rem->task_status !== 'completed') {
            $overdue_reminders[] = $rem;
        }
    }
}

?>
<style>
.crm-logout-btn {
    position: absolute;
    top: 24px;
    right: 32px;
    background: rgba(255,255,255,0.25);
    border: 1px solid #e0e7ff;
    color: #374151;
    font-family: 'Inter', sans-serif;
    font-weight: 500;
    border-radius: 8px;
    padding: 8px 20px;
    font-size: 1rem;
    box-shadow: 0 2px 8px 0 rgba(31,38,135,0.08);
    backdrop-filter: blur(4px);
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
    z-index: 1000;
}
.crm-logout-btn:hover {
    background: #6366f1;
    color: #fff;
}
</style>
<a href="<?php echo esc_url(site_url('/crm-logout')); ?>" class="crm-logout-btn">Logout</a>
<div class="crm-main">
  <div class="crm-header" style="display:flex;align-items:center;justify-content:space-between;">
    <span class="crm-title" style="display:flex;align-items:center;gap:18px;">Leads
      <?php if (!empty($overdue_reminders)): ?>
        <span class="reminder-badge" style="background:#ea5455;color:#fff;font-size:12px;padding:3px 12px;border-radius:9px;font-weight:600;letter-spacing:0.2px;margin-left:12px;">Overdue Reminders</span>
      <?php endif; ?>
      <span id="crm-bell" style="margin-left:18px;cursor:pointer;position:relative;">
        <i class="fas fa-bell" style="font-size:1.5em;color:#4b68b6;"></i>
        <?php $unread = 0; foreach ($notifications as $n) if (empty($n['read'])) $unread++; ?>
        <?php if ($unread): ?><span class="crm-bell-badge" style="position:absolute;top:-6px;right:-6px;background:#ea5455;color:#fff;font-size:11px;padding:2px 7px;border-radius:8px;font-weight:600;"><?php echo $unread; ?></span><?php endif; ?>
      </span>
    </span>
    <?php if (current_user_can('add_leads')): ?>
      <button class="crm-btn" id="add-lead-btn">+ Add New Lead</button>
    <?php endif; ?>
    <div id="crm-bell-dropdown" style="display:none;position:absolute;top:54px;right:32px;min-width:320px;max-width:420px;background:rgba(255,255,255,0.98);border-radius:14px;box-shadow:0 8px 32px 0 rgba(31,38,135,0.13);border:1.2px solid rgba(255,255,255,0.18);z-index:9999;padding:0 0 0 0;font-family:Inter,sans-serif;">
      <div style="padding:14px 18px 10px 18px;border-bottom:1px solid #eee;font-size:1.08rem;font-weight:600;">Notifications</div>
      <div id="crm-bell-list" style="max-height:340px;overflow-y:auto;">
        <?php if (empty($notifications)): ?>
          <div style="padding:18px;color:#888;font-size:14px;">No notifications.</div>
        <?php else: ?>
          <?php foreach ($notifications as $n): ?>
            <div class="crm-bell-item<?php if (empty($n['read'])) echo ' unread'; ?>" data-id="<?php echo esc_attr($n['id']); ?>" style="padding:13px 18px;border-bottom:1px solid #f2f2f2;cursor:pointer;background:<?php echo empty($n['read']) ? 'rgba(131,162,219,0.13)' : 'transparent'; ?>;font-size:14px;display:flex;align-items:center;gap:10px;">
              <i class="fas fa-<?php echo $n['type']==='reminder'?'bell':($n['type']==='task'?'check-circle':'info-circle'); ?>" style="color:#4b68b6;"></i>
              <span style="flex:1;"><?php echo esc_html($n['message']); ?></span>
              <?php if (!empty($n['link'])): ?><a href="<?php echo esc_url($n['link']); ?>" target="_blank" style="color:#007bff;font-size:13px;">View</a><?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="crm-filters-row" style="display:flex;align-items:center;gap:18px;margin-bottom:18px;">
    <div class="crm-filter-group">
      <label for="campaign-filter" style="font-size:13px;font-family:'Inter',sans-serif;color:#333;font-weight:500;margin-right:7px;">Campaign:</label>
      <select id="campaign-filter" class="crm-filter-select" style="font-size:13px;padding:5px 18px 5px 8px;border-radius:8px;background:rgba(255,255,255,0.7);border:1.2px solid #e0e0e0;font-family:'Inter',sans-serif;min-width:120px;">
        <option value="">All Campaigns</option>
        <?php
        global $wpdb;
        $campaigns = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'lead_campaign' AND meta_value != '' ORDER BY meta_value ASC");
        foreach ($campaigns as $c) {
          echo '<option value="'.esc_attr($c).'">'.esc_html($c).'</option>';
        }
        ?>
      </select>
    </div>
  </div>
  <?php if (current_user_can('edit_campaigns')): ?>
  <div class="crm-bulk-actions-row" style="display:flex;align-items:center;gap:14px;margin-bottom:10px;">
    <input type="checkbox" id="leads-bulk-select-all" style="margin-right:6px;">
    <label for="leads-bulk-select-all" style="font-size:13px;color:#333;font-family:'Inter',sans-serif;">Select All</label>
    <select id="bulk-campaign-select" class="crm-filter-select" style="font-size:13px;padding:5px 18px 5px 8px;border-radius:8px;background:rgba(255,255,255,0.7);border:1.2px solid #e0e0e0;font-family:'Inter',sans-serif;min-width:120px;">
      <option value="">Assign Campaign...</option>
      <?php
      global $wpdb;
      $campaigns = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'lead_campaign' AND meta_value IS NOT NULL AND meta_value != ''");
      foreach ($campaigns as $c) {
        echo '<option value="'.esc_attr($c).'">'.esc_html($c).'</option>';
      }
      ?>
    </select>
    <button id="bulk-assign-campaign-btn" class="crm-btn crm-btn-primary" style="font-size:13px;padding:5px 16px;">Assign Campaign</button>
    <span id="bulk-assign-campaign-msg" style="margin-left:10px;font-size:13px;color:#4b68b6;display:none;"></span>
  </div>
  <?php endif; ?>
  <table class="leads-table">
    <thead>
      <tr>
        <?php if (current_user_can('edit_campaigns')): ?><th><input type="checkbox" id="leads-table-select-all"></th><?php endif; ?>
        <th>Name</th>
        <th>Phone(s)</th>
        <th>WhatsApp</th>
        <th>Agent</th>
        <th>Stage</th>
        <th>Sub-Stage</th>
        <th>Source</th>
        <th>Developer</th>
        <th>Project</th>
        <th>Bedrooms</th>
        <th>Type</th>
        <th>Location</th>
        <th>Campaign</th>
        <th>Agent Comment</th>
        <th>Create Date</th>
        <th>Last Modified</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="leads-tbody">
      <!-- Leads will be rendered here by PHP/JS, each field wrapped in permission checks -->
    </tbody>
  </table>
</div>
<!-- Modal for Add/Edit Lead -->
<div class="modal-bg" id="lead-modal-bg">
  <div class="modal-panel">
    <button class="modal-close" id="close-lead-modal">&times;</button>
    <form id="lead-form" autocomplete="off">
      <div class="floating-label-group">
        <input type="text" id="lead_name" name="lead_name" required placeholder=" " />
        <label for="lead_name">Full Name <span style="color:#83A2DB">*</span></label>
      </div>
      <div id="phone-fields">
        <div class="form-row phone-row">
          <div class="floating-label-group form-col">
            <select name="country_code[]" class="country-code-select" required>
              <option value="" disabled selected hidden></option>
              <option value="+971">🇦🇪 +971</option>
              <option value="+98">🇮🇷 +98</option>
              <option value="+1">🇺🇸 +1</option>
              <option value="+44">🇬🇧 +44</option>
              <option value="+49">🇩🇪 +49</option>
            </select>
            <label>Code</label>
          </div>
          <div class="floating-label-group form-col">
            <input type="tel" name="lead_phone[]" required placeholder=" " />
            <label>Phone <span style="color:#83A2DB">*</span></label>
          </div>
        </div>
      </div>
      <button type="button" class="crm-btn" id="add-phone-btn" style="margin-bottom:10px;">+ Add Another Number</button>
      <div class="floating-label-group">
        <input type="text" id="lead_whatsapp" name="lead_whatsapp" placeholder=" " />
        <label for="lead_whatsapp">WhatsApp Number</label>
      </div>
      <div class="form-row">
        <div class="floating-label-group form-col">
          <select id="agent_id" name="agent_id" required>
            <option value="" disabled selected hidden></option>
            <!-- Agents loaded dynamically -->
          </select>
          <label for="agent_id">Agent <span style="color:#83A2DB">*</span></label>
        </div>
        <div class="floating-label-group form-col">
          <select id="lead_stage" name="lead_stage" required>
            <option value="" disabled selected hidden></option>
            <!-- Stages loaded dynamically -->
          </select>
          <label for="lead_stage">Stage <span style="color:#83A2DB">*</span></label>
        </div>
      </div>
      <div class="floating-label-group">
        <select id="lead_sub_stage" name="lead_sub_stage">
          <option value="" disabled selected hidden></option>
          <!-- Sub-stages loaded dynamically -->
        </select>
        <label for="lead_sub_stage">Sub-Stage</label>
      </div>
      <div class="floating-label-group">
        <select id="lead_source" name="lead_source">
          <option value="" disabled selected hidden></option>
          <option value="Facebook">Facebook</option>
          <option value="Website">Website</option>
          <option value="Property Finder">Property Finder</option>
          <option value="AddNew">+ Add new source...</option>
        </select>
        <label for="lead_source">Source</label>
      </div>
      <div class="floating-label-group" id="new-source-group" style="display:none;">
        <input type="text" id="new_source" name="new_source" placeholder=" " />
        <label for="new_source">New Source</label>
      </div>
      <div class="floating-label-group">
        <input type="text" id="lead_developer" name="lead_developer" placeholder=" " />
        <label for="lead_developer">Developer</label>
      </div>
      <div class="floating-label-group">
        <input type="text" id="lead_project" name="lead_project" placeholder=" " />
        <label for="lead_project">Project</label>
      </div>
      <div class="form-row">
        <div class="floating-label-group form-col">
          <select id="lead_bedrooms" name="lead_bedrooms">
            <option value="" disabled selected hidden></option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
            <option value="5+">5+</option>
          </select>
          <label for="lead_bedrooms">Bedrooms</label>
        </div>
        <div class="floating-label-group form-col">
          <select id="lead_property_type" name="lead_property_type">
            <option value="" disabled selected hidden></option>
            <option value="Apartment">Apartment</option>
            <option value="Villa">Villa</option>
            <option value="AddNew">+ Add new type...</option>
          </select>
          <label for="lead_property_type">Type</label>
        </div>
      </div>
      <div class="floating-label-group" id="new-type-group" style="display:none;">
        <input type="text" id="new_property_type" name="new_property_type" placeholder=" " />
        <label for="new_property_type">New Type</label>
      </div>
      <div class="floating-label-group">
        <input type="text" id="lead_location" name="lead_location" placeholder=" " />
        <label for="lead_location">Location</label>
      </div>
      <div class="floating-label-group">
        <input type="text" id="lead_campaign" name="lead_campaign" placeholder=" " />
        <label for="lead_campaign">Campaign</label>
      </div>
      <div class="floating-label-group">
        <textarea id="agent_comment" name="agent_comment" rows="2" placeholder=" "></textarea>
        <label for="agent_comment">Agent Comment</label>
      </div>
      <div class="form-row">
        <div class="floating-label-group form-col">
          <input type="text" id="create_date" name="create_date" placeholder=" " readonly />
          <label for="create_date">Create Date</label>
        </div>
        <div class="floating-label-group form-col">
          <input type="text" id="modify_date" name="modify_date" placeholder=" " readonly />
          <label for="modify_date">Last Modified</label>
        </div>
      </div>
      <div class="btn-row">
        <button type="button" class="crm-btn" id="cancel-lead-btn">Cancel</button>
        <button type="submit" class="crm-btn">Save Lead</button>
      </div>
    </form>
  </div>
</div>
<script>
// Dynamic JS for multi-phone, source/type add, and permission-aware fields
// ... (implementation will be added in the next step) ...
</script>

<?php
// Include the modal for adding a new lead
if (current_user_can('add_leads')) {
    if (file_exists(OPBCRM_PLUGIN_DIR . 'templates/partials/add-new-lead-modal.php')) {
        include_once OPBCRM_PLUGIN_DIR . 'templates/partials/add-new-lead-modal.php';
    }
}
?>

<!-- User List Section (CRM Team) -->
<div class="opbcrm-users-section" style="margin-top:40px;">
    <h2 style="margin-bottom:18px;">CRM Users</h2>
    <table class="opbcrm-users-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Mobile</th>
                <th>Role(s)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $crm_users = get_users(array('role__in' => array('administrator', 'crm_manager', 'crm_agent')));
            foreach ($crm_users as $user) {
                $mobile = get_user_meta($user->ID, 'phone', true);
                if (!$mobile) $mobile = get_user_meta($user->ID, 'mobile', true);
                if (!$mobile) $mobile = get_user_meta($user->ID, 'user_mobile', true);
                echo '<tr>';
                echo '<td>' . esc_html($user->display_name) . '</td>';
                echo '<td>' . esc_html($user->user_email) . '</td>';
                echo '<td>' . esc_html($mobile ? $mobile : '-') . '</td>';
                echo '<td>' . esc_html(implode(", ", $user->roles)) . '</td>';
                echo '</tr>';
            }
            ?>
        </tbody>
    </table>
</div>

<?php
// Add new columns and fields for all new lead attributes, and wrap each with permission checks using current_user_can('view_FIELD') or 'edit_FIELD' as appropriate. Add dynamic dropdowns and multi-phone logic. Only show Add/Edit/Delete buttons if user has permission for the field/action.

// Localize field-level permissions for JS
$field_permissions = array(
  'name' => array('view' => current_user_can('view_name'), 'edit' => current_user_can('edit_name'), 'delete' => current_user_can('delete_name')),
  'phone' => array('view' => current_user_can('view_phone'), 'edit' => current_user_can('edit_phone'), 'delete' => current_user_can('delete_phone')),
  'whatsapp' => array('view' => current_user_can('view_whatsapp'), 'edit' => current_user_can('edit_whatsapp'), 'delete' => current_user_can('delete_whatsapp')),
  'agent' => array('view' => current_user_can('view_agent'), 'edit' => current_user_can('edit_agent'), 'delete' => current_user_can('delete_agent')),
  'stage' => array('view' => current_user_can('view_stage'), 'edit' => current_user_can('edit_stage'), 'delete' => current_user_can('delete_stage')),
  'sub_stage' => array('view' => current_user_can('view_sub_stage'), 'edit' => current_user_can('edit_sub_stage'), 'delete' => current_user_can('delete_sub_stage')),
  'source' => array('view' => current_user_can('view_source'), 'edit' => current_user_can('edit_source'), 'delete' => current_user_can('delete_source')),
  'developer' => array('view' => current_user_can('view_developer'), 'edit' => current_user_can('edit_developer'), 'delete' => current_user_can('delete_developer')),
  'project' => array('view' => current_user_can('view_project'), 'edit' => current_user_can('edit_project'), 'delete' => current_user_can('delete_project')),
  'bedrooms' => array('view' => current_user_can('view_bedrooms'), 'edit' => current_user_can('edit_bedrooms'), 'delete' => current_user_can('delete_bedrooms')),
  'property_type' => array('view' => current_user_can('view_property_type'), 'edit' => current_user_can('edit_property_type'), 'delete' => current_user_can('delete_property_type')),
  'location' => array('view' => current_user_can('view_location'), 'edit' => current_user_can('edit_location'), 'delete' => current_user_can('delete_location')),
  'agent_comment' => array('view' => current_user_can('view_agent_comment'), 'edit' => current_user_can('edit_agent_comment'), 'delete' => current_user_can('delete_agent_comment')),
  'create_date' => array('view' => current_user_can('view_create_date'), 'edit' => false, 'delete' => false),
  'modify_date' => array('view' => current_user_can('view_modify_date'), 'edit' => false, 'delete' => false),
  'add_leads' => current_user_can('add_leads'),
);
?>
<script>
var opbcrm_field_permissions = <?php echo json_encode($field_permissions); ?>;
</script>
?>

<div class="crm-glass-panel crm-kanban-panel" style="max-width:99vw;margin:0 auto 40px auto;padding:32px 28px 24px 28px;border-radius:22px;box-shadow:0 8px 32px 0 rgba(31,38,135,0.18);backdrop-filter:blur(12px);background:rgba(255,255,255,0.18);border:1.5px solid rgba(255,255,255,0.22);font-family:'Inter',sans-serif;">
  <div class="crm-header-row" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
    <span class="crm-title" style="font-size:2.1rem;font-weight:700;letter-spacing:-1px;">Leads Pipeline</span>
    <?php if (current_user_can('add_leads')): ?>
      <button class="crm-btn" id="add-lead-btn">+ Add New Lead</button>
    <?php endif; ?>
  </div>
  <div class="crm-kanban-board pipeline-board" style="display:flex;gap:24px;overflow-x:auto;padding-bottom:18px;">
    <?php foreach ($all_stages as $stage):
      $stage_id = $stage['id'];
      $stage_label = $stage['label'];
      $stage_color = $stage['color'];
      $leads = isset($leads_by_stage[$stage_id]) ? $leads_by_stage[$stage_id] : [];
    ?>
    <div class="crm-kanban-stage pipeline-stage glassy-panel" data-stage-id="<?php echo esc_attr($stage_id); ?>" style="background:rgba(<?php echo implode(',', sscanf($stage_color, '#%02x%02x%02x')); ?>,0.13);border:1.5px solid <?php echo esc_attr($stage_color); ?>;border-radius:18px;min-width:320px;max-width:360px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);flex:1;">
      <div class="crm-kanban-stage-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <span class="crm-stage-badge" style="background:<?php echo esc_attr($stage_color); ?>;color:#fff;padding:5px 16px;border-radius:12px;font-size:15px;font-weight:600;letter-spacing:0.5px;box-shadow:0 1px 4px 0 rgba(31,38,135,0.07);">
          <?php echo esc_html($stage_label); ?>
        </span>
        <span class="crm-stage-count" style="font-size:15px;font-weight:600;color:#333;background:rgba(255,255,255,0.7);padding:3px 12px;border-radius:10px;box-shadow:0 1px 4px 0 rgba(31,38,135,0.07);">
          <?php echo count($leads); ?>
        </span>
      </div>
      <div class="crm-kanban-leads pipeline-leads-list" data-stage-id="<?php echo esc_attr($stage_id); ?>" style="min-height:120px;display:flex;flex-direction:column;gap:12px;">
        <?php foreach ($leads as $lead):
          $lead_id = $lead->ID;
          $lead_name = get_the_title($lead_id);
          $agent_id = $lead->post_author;
          $agent = get_userdata($agent_id);
          $agent_avatar = get_avatar_url($agent_id, ['size'=>32]);
          $lead_phone = get_post_meta($lead_id, 'lead_phone', true);
          $lead_whatsapp = get_post_meta($lead_id, 'lead_whatsapp', true);
          $lead_email = get_post_meta($lead_id, 'lead_email', true);
          $lead_source = get_post_meta($lead_id, 'lead_source', true);
          $lead_project = get_post_meta($lead_id, 'lead_project', true);
          $lead_agent_comment = get_post_meta($lead_id, 'agent_comment', true);
          $lead_campaign = get_post_meta($lead_id, 'lead_campaign', true);
          $all_sources = get_option('opbcrm_lead_sources', []);
        ?>
        <div class="crm-kanban-card lead-card glassy-panel" data-lead-id="<?php echo esc_attr($lead_id); ?>" style="background:rgba(255,255,255,0.92);border-radius:14px;padding:13px 16px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.10);display:flex;flex-direction:column;gap:7px;">
          <div style="display:flex;align-items:center;gap:10px;">
            <img src="<?php echo esc_url($agent_avatar); ?>" alt="Agent" style="width:32px;height:32px;border-radius:50%;box-shadow:0 1px 4px 0 rgba(31,38,135,0.07);">
            <span style="font-weight:600;font-size:1.05rem;"> <?php echo esc_html($lead_name); ?> </span>
          </div>
          <div style="font-size:13px;color:#666;display:flex;gap:12px;align-items:center;">
            <span><i class="fas fa-phone"></i> <?php echo esc_html($lead_phone); ?></span>
            <?php if (current_user_can('view_campaign')): ?>
              <span class="kanban-campaign-badge" style="background:rgba(131,162,219,0.13);padding:2px 10px;border-radius:8px;font-size:12px;color:#4b68b6;">
                <i class="fas fa-bullhorn"></i> 
                <span class="kanban-campaign-view"><?php echo esc_html($lead_campaign ?: '—'); ?></span>
                <?php if (current_user_can('edit_campaign')): ?>
                  <button class="kanban-edit-campaign-btn" title="Edit Campaign" style="background:none;border:none;color:#83A2DB;font-size:1em;cursor:pointer;padding:2px 4px;"><i class="fas fa-edit"></i></button>
                <?php endif; ?>
                <span class="kanban-edit-campaign-row" style="display:none;">
                  <input type="text" class="kanban-edit-campaign-input" value="<?php echo esc_attr($lead_campaign); ?>" style="font-size:12.5px;padding:3px 8px;border-radius:7px;border:1px solid #e0e0e0;" />
                  <button class="kanban-save-campaign-btn crm-btn" style="font-size:12px;padding:3px 10px;">Save</button>
                  <button class="kanban-cancel-campaign-btn crm-btn" style="font-size:12px;padding:3px 10px;background:#eee;color:#333;">Cancel</button>
                </span>
              </span>
            <?php endif; ?>
            <span><i class="fas fa-building"></i> 
              <span class="kanban-project-view"><?php echo esc_html($lead_project); ?></span>
              <?php if (current_user_can('edit_project')): ?>
                <button class="kanban-edit-project-btn" title="Edit Project" style="background:none;border:none;color:#83A2DB;font-size:1em;cursor:pointer;padding:2px 4px;"><i class="fas fa-edit"></i></button>
              <?php endif; ?>
              <span class="kanban-edit-project-row" style="display:none;">
                <input type="text" class="kanban-edit-project-input" value="<?php echo esc_attr($lead_project); ?>" style="font-size:12.5px;padding:3px 8px;border-radius:7px;border:1px solid #e0e0e0;" />
                <button class="kanban-save-project-btn crm-btn" style="font-size:12px;padding:3px 10px;">Save</button>
                <button class="kanban-cancel-project-btn crm-btn" style="font-size:12px;padding:3px 10px;background:#eee;color:#333;">Cancel</button>
              </span>
            </span>
          </div>
          <div class="kanban-quick-actions" style="display:flex;gap:10px;align-items:center;margin:2px 0 2px 0;">
            <?php if ($lead_phone && current_user_can('view_phone')): ?>
              <a href="tel:<?php echo esc_attr($lead_phone); ?>" class="kanban-action-btn" title="Call" style="color:#4b68b6;font-size:1.1em;"><i class="fas fa-phone-alt"></i></a>
            <?php endif; ?>
            <?php if ($lead_whatsapp && current_user_can('view_whatsapp')): ?>
              <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $lead_whatsapp); ?>" target="_blank" class="kanban-action-btn" title="WhatsApp" style="color:#25d366;font-size:1.1em;"><i class="fab fa-whatsapp"></i></a>
            <?php endif; ?>
            <?php if ($lead_email && current_user_can('view_email')): ?>
              <a href="mailto:<?php echo esc_attr($lead_email); ?>" class="kanban-action-btn" title="Email" style="color:#007bff;font-size:1.1em;"><i class="fas fa-envelope"></i></a>
            <?php endif; ?>
          </div>
          <div class="kanban-agent-comment" style="font-size:12.5px;color:#444;margin:2px 0 2px 0;display:flex;align-items:center;gap:6px;">
            <?php if (current_user_can('view_agent_comment')): ?>
              <span class="kanban-agent-comment-text"><?php echo $lead_agent_comment ? esc_html($lead_agent_comment) : '<em>No comment</em>'; ?></span>
              <?php if (current_user_can('edit_agent_comment')): ?>
                <button class="kanban-edit-comment-btn" title="Edit Comment" style="background:none;border:none;color:#83A2DB;font-size:1em;cursor:pointer;padding:2px 4px;"><i class="fas fa-edit"></i></button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <div class="kanban-edit-comment-row" style="display:none;flex-direction:column;gap:4px;margin:2px 0 2px 0;">
            <textarea class="kanban-edit-comment-textarea" style="font-size:12.5px;padding:5px 8px;border-radius:7px;border:1px solid #e0e0e0;resize:vertical;"></textarea>
            <div style="display:flex;gap:6px;justify-content:flex-end;">
              <button class="kanban-save-comment-btn crm-btn" style="font-size:12px;padding:3px 12px;">Save</button>
              <button class="kanban-cancel-comment-btn crm-btn" style="font-size:12px;padding:3px 12px;background:#eee;color:#333;">Cancel</button>
            </div>
          </div>
          <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end;">
            <?php if (current_user_can('edit_leads')): ?>
              <button class="crm-btn crm-btn-edit" style="font-size:13px;padding:4px 12px;">Edit</button>
            <?php endif; ?>
            <?php if (current_user_can('delete_leads')): ?>
              <button class="crm-btn crm-btn-delete" style="font-size:13px;padding:4px 12px;background:rgba(200,40,40,0.13);color:#c82828;">Delete</button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <!-- Reporting widgets will be added here -->
</div>

<div id="global-reminder-toast" style="display:none;position:fixed;top:32px;right:32px;z-index:9999;min-width:220px;padding:14px 22px;border-radius:12px;background:rgba(234,84,85,0.97);color:#fff;font-size:15px;font-weight:500;box-shadow:0 2px 12px 0 rgba(31,38,135,0.13);font-family:Inter,sans-serif;text-align:center;letter-spacing:0.2px;opacity:0.98;transition:all 0.3s;pointer-events:none;">You have overdue reminders!</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
  <?php if (!empty($overdue_reminders)): ?>
    setTimeout(function(){
      var toast=document.getElementById('global-reminder-toast');
      if(toast){ toast.style.display='block'; setTimeout(function(){ toast.style.display='none'; }, 4200); }
    }, 600);
  <?php endif; ?>
});
</script>

<script>
document.getElementById('crm-bell').addEventListener('click',function(e){
  e.stopPropagation();
  var dd = document.getElementById('crm-bell-dropdown');
  dd.style.display = dd.style.display==='block' ? 'none' : 'block';
});
document.body.addEventListener('click',function(){
  var dd = document.getElementById('crm-bell-dropdown');
  if(dd) dd.style.display='none';
});
document.querySelectorAll('.crm-bell-item').forEach(function(item){
  item.addEventListener('click',function(){
    var id = this.dataset.id;
    if(this.classList.contains('unread')){
      fetch(opbcrm_ajax.ajax_url,{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=opbcrm_mark_notification_read&notif_id='+encodeURIComponent(id)
      }).then(r=>r.json()).then(resp=>{ if(resp.success) this.classList.remove('unread'); });
    }
  });
});
</script>

<div class="crm-glass-panel" style="margin:0 0 24px 0;padding:18px 22px 14px 22px;border-radius:16px;background:rgba(255,255,255,0.92);box-shadow:0 4px 24px 0 rgba(31,38,135,0.13);font-family:Inter,sans-serif;display:flex;align-items:center;gap:18px;">
  <span style="font-size:1.08rem;font-weight:600;">Calendar Sync</span>
  <button id="connect-google-calendar" class="opbcrm-btn" style="font-size:14px;padding:6px 18px;background:#4285F4;color:#fff;border:none;border-radius:8px;">Connect Google Calendar</button>
  <span id="google-calendar-status" style="font-size:13px;color:#4285F4;margin-left:6px;">Not Connected</span>
  <button id="connect-outlook-calendar" class="opbcrm-btn" style="font-size:14px;padding:6px 18px;background:#0078D4;color:#fff;border:none;border-radius:8px;">Connect Outlook Calendar</button>
  <span id="outlook-calendar-status" style="font-size:13px;color:#0078D4;margin-left:6px;">Not Connected</span>
</div>

<script>
document.getElementById('connect-google-calendar').addEventListener('click',function(){
  fetch(opbcrm_ajax.ajax_url,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=opbcrm_google_oauth_start'
  }).then(r=>r.json()).then(resp=>{
    if(resp.success && resp.url){ window.location = resp.url; }
    else{ alert('Failed to start Google OAuth'); }
  });
});
document.getElementById('connect-outlook-calendar').addEventListener('click',function(){
  fetch(opbcrm_ajax.ajax_url,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=opbcrm_outlook_oauth_start'
  }).then(r=>r.json()).then(resp=>{
    if(resp.success && resp.url){ window.location = resp.url; }
    else{ alert('Failed to start Outlook OAuth'); }
  });
});
// On load, update status if connected
if(window.location.search.indexOf('google_connected=1')!==-1){
  document.getElementById('google-calendar-status').textContent = 'Connected';
  document.getElementById('google-calendar-status').style.color = '#43b04a';
}
if(window.location.search.indexOf('outlook_connected=1')!==-1){
  document.getElementById('outlook-calendar-status').textContent = 'Connected';
  document.getElementById('outlook-calendar-status').style.color = '#43b04a';
}
</script>

<div class="crm-calendar-panel glassy-panel" style="margin:32px auto 0 auto;max-width:1100px;padding:24px 18px 18px 18px;border-radius:18px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.10);backdrop-filter:blur(8px);background:rgba(255,255,255,0.22);border:1.2px solid rgba(255,255,255,0.18);font-family:Inter,sans-serif;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
    <h2 style="font-size:1.3rem;font-weight:700;margin:0;">Calendar</h2>
    <button id="calendar-sync-btn" class="crm-btn crm-btn-secondary" style="font-size:14px;padding:6px 18px;border-radius:10px;">Sync with Google/Outlook</button>
  </div>
  <div id="crm-calendar" style="background:#fff;border-radius:12px;box-shadow:0 1px 6px 0 rgba(31,38,135,0.07);padding:8px;"></div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
// Helper: Add Google events to calendar
function addGoogleEventsToCalendar(calendar) {
  fetch(opbcrm_ajax.ajax_url,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=opbcrm_get_google_events'
  }).then(r=>r.json()).then(resp=>{
    if(resp.success && resp.events){
      var gEvents = resp.events.map(function(ev){
        return {
          id: 'google_'+ev.id,
          title: ev.summary,
          start: ev.start.dateTime,
          end: ev.end.dateTime,
          color: '#fff',
          borderColor: '#4285F4',
          textColor: '#222',
          extendedProps: {
            location: ev.location,
            notes: ev.description,
            isGoogle: true
          }
        };
      });
      gEvents.forEach(function(ev){ calendar.addEvent(ev); });
    }
  });
}
// Helper: Add Outlook events to calendar
function addOutlookEventsToCalendar(calendar) {
  fetch(opbcrm_ajax.ajax_url,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=opbcrm_get_outlook_events'
  }).then(r=>r.json()).then(resp=>{
    if(resp.success && resp.events){
      var oEvents = resp.events.map(function(ev){
        return {
          id: 'outlook_'+ev.id,
          title: ev.subject,
          start: ev.start.dateTime,
          end: ev.end.dateTime,
          color: '#fff',
          borderColor: '#0078D4',
          textColor: '#222',
          extendedProps: {
            location: ev.location.displayName,
            notes: ev.bodyPreview,
            isOutlook: true
          }
        };
      });
      oEvents.forEach(function(ev){ calendar.addEvent(ev); });
    }
  });
}
document.addEventListener('DOMContentLoaded', function() {
  var calendarEl = document.getElementById('crm-calendar');
  if (!calendarEl) return;
  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    height: 540,
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
    events: opbcrm_calendar_events || [],
    eventDisplay: 'block',
    eventContent: function(arg) {
      var title = arg.event.title;
      var type = arg.event.extendedProps.type;
      if(type==='meeting' && arg.event.extendedProps.assigned_to_user_name){
        title += ' — ' + arg.event.extendedProps.assigned_to_user_name;
      }
      var badge = '';
      if(arg.event.extendedProps.isGoogle){
        badge = '<span style="background:#4285F4;color:#fff;font-size:11px;padding:1px 6px;border-radius:8px;margin-right:4px;">G</span>';
      }
      if(arg.event.extendedProps.isOutlook){
        badge = '<span style="background:#0078D4;color:#fff;font-size:11px;padding:1px 6px;border-radius:8px;margin-right:4px;">O</span>';
      }
      return { html: badge+'<div style="font-family:Inter,sans-serif;font-size:13px;font-weight:500;display:inline;">'+title+'</div>' };
    },
    eventClick: function(info) {
      openCalendarModal(info.event.startStr, info.event);
    },
    eventBackgroundColor: '#83A2DB',
    eventBorderColor: '#4b68b6',
    eventTextColor: '#fff',
    dayMaxEvents: 3,
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    slotMinTime: '07:00:00',
    slotMaxTime: '21:00:00',
    editable: false,
    selectable: false,
    eventMouseEnter: function(info) {
      showCalendarTooltip(info.jsEvent, info.event);
    },
    eventMouseLeave: function(info) {
      hideCalendarTooltip();
    },
  });
  calendar.render();
  // If Google is connected, fetch and add Google events
  if(document.getElementById('google-calendar-status').textContent==='Connected'){
    addGoogleEventsToCalendar(calendar);
  }
  // If Outlook is connected, fetch and add Outlook events
  if(document.getElementById('outlook-calendar-status').textContent==='Connected'){
    addOutlookEventsToCalendar(calendar);
  }
});
</script>

<div id="crm-calendar-modal" class="opbcrm-modal" style="display:none;">
  <div class="opbcrm-modal-content" style="max-width:400px;">
    <span class="opbcrm-modal-close" id="close-calendar-modal">&times;</span>
    <h3 style="font-family:Inter,sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:12px;">Add Event</h3>
    <form id="crm-calendar-form">
      <label style="font-size:13px;font-weight:500;">Type</label>
      <select name="event_type" id="calendar_event_type" class="detail-input" style="width:100%;margin-bottom:12px;">
        <option value="task">Task</option>
        <option value="reminder">Reminder</option>
        <option value="meeting">Meeting</option>
      </select>
      <div id="calendar_attendee_wrap" style="display:none;margin-bottom:12px;">
        <label style="font-size:13px;font-weight:500;">Attendee</label>
        <select name="event_attendee" id="calendar_event_attendee" class="detail-input" style="width:100%;"></select>
      </div>
      <label style="font-size:13px;font-weight:500;">Title/Message</label>
      <input type="text" name="event_title" id="calendar_event_title" class="detail-input" style="width:100%;margin-bottom:12px;" required>
      <label style="font-size:13px;font-weight:500;">Date & Time</label>
      <input type="datetime-local" name="event_date" id="calendar_event_date" class="detail-input" style="width:100%;margin-bottom:18px;" required>
      <label style="font-size:13px;font-weight:500;">Location</label>
      <input type="text" name="event_location" id="calendar_event_location" class="detail-input" style="width:100%;margin-bottom:12px;">
      <label style="font-size:13px;font-weight:500;">Notes</label>
      <textarea name="event_notes" id="calendar_event_notes" class="detail-input" style="width:100%;margin-bottom:18px;" rows="2"></textarea>
      <input type="hidden" id="calendar_event_id" name="event_id">
      <input type="hidden" id="calendar_event_mode" name="event_mode" value="add">
      <button type="submit" class="opbcrm-btn opbcrm-btn-primary" style="width:100%;font-size:15px;">Add Event</button>
      <button type="button" id="calendar_event_delete" class="opbcrm-btn" style="width:100%;font-size:15px;background:#ff4d4f;color:#fff;display:none;margin-top:8px;">Delete Event</button>
      <button type="button" id="calendar_event_sync_google" class="opbcrm-btn" style="width:100%;font-size:15px;background:#4285F4;color:#fff;display:none;margin-top:8px;">Sync to Google Calendar</button>
      <button type="button" id="calendar_event_sync_outlook" class="opbcrm-btn" style="width:100%;font-size:15px;background:#0078D4;color:#fff;display:none;margin-top:8px;">Sync to Outlook Calendar</button>
    </form>
  </div>
</div>
<script>
var calendarModal = document.getElementById('crm-calendar-modal');
var closeCalendarModal = document.getElementById('close-calendar-modal');
closeCalendarModal?.addEventListener('click',function(){calendarModal.style.display='none';});
window.addEventListener('click',function(e){if(e.target===calendarModal)calendarModal.style.display='none';});
function showAttendeeDropdown(show, selectedId) {
  var wrap = document.getElementById('calendar_attendee_wrap');
  var sel = document.getElementById('calendar_event_attendee');
  if(show){
    wrap.style.display = 'block';
    // Fetch users if not loaded
    if(!sel.options.length){
      fetch(opbcrm_ajax.ajax_url+'?action=opbcrm_get_crm_users&nonce='+opbcrm_ajax.nonce)
        .then(r=>r.json()).then(resp=>{
          if(resp.success){
            sel.innerHTML = '';
            resp.users.forEach(function(u){
              var opt = document.createElement('option');
              opt.value = u.id;
              opt.text = u.name+' ('+u.email+')';
              sel.appendChild(opt);
            });
            if(selectedId) sel.value = selectedId;
          }
        });
    }else{
      if(selectedId) sel.value = selectedId;
    }
  }else{
    wrap.style.display = 'none';
    sel.innerHTML = '';
  }
}
function openCalendarModal(dateStr, event) {
  var type = event ? event.extendedProps.type : 'task';
  document.getElementById('calendar_event_mode').value = event ? 'edit' : 'add';
  document.getElementById('calendar_event_id').value = event ? event.id : '';
  document.getElementById('calendar_event_type').value = type;
  document.getElementById('calendar_event_title').value = event ? event.title.replace(/^(Task|Reminder|Meeting): /, '') : '';
  document.getElementById('calendar_event_date').value = event ? event.startStr.slice(0,16) : dateStr.replace('T',' ').slice(0,16).replace(' ','T');
  document.getElementById('calendar_event_delete').style.display = event ? 'block' : 'none';
  document.getElementById('calendar_event_location').value = event && event.extendedProps.location ? event.extendedProps.location : '';
  document.getElementById('calendar_event_notes').value = event && event.extendedProps.notes ? event.extendedProps.notes : '';
  if(type==='meeting'){
    showAttendeeDropdown(true, event ? event.extendedProps.assigned_to_user_id : opbcrm_ajax.current_user_id);
  }else{
    showAttendeeDropdown(false);
  }
  calendarModal.style.display='block';
  // Show Google sync button if connected
  document.getElementById('calendar_event_sync_google').style.display = (document.getElementById('google-calendar-status').textContent==='Connected') ? 'block' : 'none';
  // Show Outlook sync button if connected
  document.getElementById('calendar_event_sync_outlook').style.display = (document.getElementById('outlook-calendar-status').textContent==='Connected') ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', function() {
  var calendarEl = document.getElementById('crm-calendar');
  if (!calendarEl) return;
  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    height: 540,
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
    events: opbcrm_calendar_events || [],
    eventDisplay: 'block',
    eventClick: function(info) {
      openCalendarModal(info.event.startStr, info.event);
    },
    dateClick: function(info) {
      openCalendarModal(info.dateStr+'T09:00');
    },
    eventBackgroundColor: '#83A2DB',
    eventBorderColor: '#4b68b6',
    eventTextColor: '#fff',
    dayMaxEvents: 3,
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    slotMinTime: '07:00:00',
    slotMaxTime: '21:00:00',
    editable: false,
    selectable: false
  });
  calendar.render();
  document.getElementById('crm-calendar-form')?.addEventListener('submit',function(e){
    e.preventDefault();
    var mode = document.getElementById('calendar_event_mode').value;
    var eventId = document.getElementById('calendar_event_id').value;
    var type = document.getElementById('calendar_event_type').value;
    var title = document.getElementById('calendar_event_title').value;
    var date = document.getElementById('calendar_event_date').value;
    var attendee = (type==='meeting') ? document.getElementById('calendar_event_attendee').value : '';
    var location = document.getElementById('calendar_event_location').value;
    var notes = document.getElementById('calendar_event_notes').value;
    var data = new URLSearchParams();
    var action = '';
    if(mode==='edit'){
      if(type==='task') action = 'update_lead_task';
      else if(type==='reminder') action = 'update_lead_reminder';
      else if(type==='meeting') action = 'update_lead_meeting';
      data.append('event_id', eventId);
    }else{
      if(type==='task') action = 'add_lead_task';
      else if(type==='reminder') action = 'add_lead_reminder';
      else if(type==='meeting') action = 'add_lead_meeting';
    }
    data.append('action', action);
    data.append('nonce', opbcrm_ajax.nonce);
    data.append('lead_id', ''); // Optionally select lead
    if(type==='task'){
      data.append('task_content', title);
      data.append('due_date', date);
      data.append('assignee_id', opbcrm_ajax.current_user_id);
      data.append('location', location);
      data.append('notes', notes);
    }else if(type==='reminder'){
      data.append('msg', title);
      data.append('due', date);
      data.append('location', location);
      data.append('notes', notes);
    }else if(type==='meeting'){
      data.append('msg', title);
      data.append('due', date);
      data.append('assignee_id', attendee||opbcrm_ajax.current_user_id);
      data.append('location', location);
      data.append('notes', notes);
    }
    fetch(opbcrm_ajax.ajax_url, {method:'POST',body:data}).then(r=>r.json()).then(resp=>{
      if(resp.success){ location.reload(); }
      else{ alert(resp.data||'Error saving event'); }
    });
  });
});
document.getElementById('calendar_event_sync_google')?.addEventListener('click',function(){
  var type = document.getElementById('calendar_event_type').value;
  var title = document.getElementById('calendar_event_title').value;
  var date = document.getElementById('calendar_event_date').value;
  var location = document.getElementById('calendar_event_location').value;
  var notes = document.getElementById('calendar_event_notes').value;
  var data = new URLSearchParams();
  data.append('action','opbcrm_push_google_event');
  data.append('title',title);
  data.append('date',date);
  data.append('location',location);
  data.append('notes',notes);
  fetch(opbcrm_ajax.ajax_url,{method:'POST',body:data}).then(r=>r.json()).then(resp=>{
    if(resp.success){ alert('Event synced to Google Calendar!'); }
    else{ alert(resp.data||'Failed to sync event.'); }
  });
});
document.getElementById('calendar_event_sync_outlook')?.addEventListener('click',function(){
  var type = document.getElementById('calendar_event_type').value;
  var title = document.getElementById('calendar_event_title').value;
  var date = document.getElementById('calendar_event_date').value;
  var location = document.getElementById('calendar_event_location').value;
  var notes = document.getElementById('calendar_event_notes').value;
  var data = new URLSearchParams();
  data.append('action','opbcrm_push_outlook_event');
  data.append('title',title);
  data.append('date',date);
  data.append('location',location);
  data.append('notes',notes);
  fetch(opbcrm_ajax.ajax_url,{method:'POST',body:data}).then(r=>r.json()).then(resp=>{
    if(resp.success){ alert('Event synced to Outlook Calendar!'); }
    else{ alert(resp.data||'Failed to sync event.'); }
  });
});
</script>

<div id="crm-calendar-tooltip" style="display:none;position:absolute;z-index:99999;min-width:180px;max-width:320px;padding:12px 16px;border-radius:14px;background:rgba(255,255,255,0.92);box-shadow:0 4px 24px 0 rgba(31,38,135,0.13);backdrop-filter:blur(8px);font-family:Inter,sans-serif;font-size:13px;color:#222;pointer-events:none;transition:opacity 0.12s;opacity:0.98;"></div>

<script>
function showCalendarTooltip(e, event) {
  var tooltip = document.getElementById('crm-calendar-tooltip');
  var loc = event.extendedProps.location;
  var notes = event.extendedProps.notes;
  if(!loc && !notes){ tooltip.style.display='none'; return; }
  var html = '';
  if(loc) html += '<div style="margin-bottom:4px;"><b>Location:</b> '+loc+'</div>';
  if(notes) html += '<div><b>Notes:</b> '+notes.replace(/\n/g,'<br>')+'</div>';
  tooltip.innerHTML = html;
  tooltip.style.display = 'block';
  tooltip.style.left = (e.pageX+12)+'px';
  tooltip.style.top = (e.pageY-8)+'px';
}
function hideCalendarTooltip(){
  var tooltip = document.getElementById('crm-calendar-tooltip');
  tooltip.style.display = 'none';
}
</script>
?> 