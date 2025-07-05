<?php
// opbcrm/includes/admin/class-opbcrm-admin-menus.php

if (!defined('WPINC')) {
    die;
}

class OPBCRM_Admin_Menus {

    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menus'));
        add_action('init', array($this, 'register_deal_post_type'));
        add_action('init', array($this, 'register_task_post_type'));
        add_action('wp_ajax_opbcrm_save_automation', function() {
            if (!current_user_can('manage_automations')) {
                wp_send_json_error('No permission.');
            }
            $automation = $_POST['automation'] ?? [];
            if (!is_array($automation)) {
                $automation = json_decode(stripslashes($automation), true);
            }
            if (!$automation || !is_array($automation)) {
                wp_send_json_error('Invalid data.');
            }
            $id = OPBCRM_Settings::save_automation($automation);
            wp_send_json_success(['id'=>$id]);
        });
        add_action('wp_ajax_opbcrm_delete_automation', function() {
            if (!current_user_can('manage_automations')) {
                wp_send_json_error('No permission.');
            }
            $id = sanitize_text_field($_POST['id'] ?? '');
            if (!$id) wp_send_json_error('Missing id.');
            OPBCRM_Settings::delete_automation($id);
            wp_send_json_success('Deleted');
        });
    }

    public function register_admin_menus() {
        // Main Menu Page
        add_menu_page(
            __('OPBCRM Dashboard', 'opbcrm'),
            __('OPBCRM', 'opbcrm'),
            'manage_options', // Capability
            'opbcrm', // Menu slug
            array($this, 'render_dashboard_page'),
            'dashicons-universal-access-alt', // Icon
            25 // Position
        );

        // Sub-menu for Lead Stages
        add_submenu_page(
            'opbcrm', // Parent slug
            __('Lead Stages', 'opbcrm'), // Page title
            __('Lead Stages', 'opbcrm'), // Menu title
            'manage_options', // Capability
            'opbcrm-lead-stages', // Menu slug
            array($this, 'render_lead_stages_page') // Callback function
        );

        // Sub-menu for Permissions
        add_submenu_page(
            'opbcrm', // Parent slug
            __('Permissions', 'opbcrm'), // Page title
            __('Permissions', 'opbcrm'), // Menu title
            'manage_options', // For now, only admin can manage permissions
            'opbcrm-permissions', // Menu slug
            array($this, 'render_permissions_page') // Callback function
        );

        // Sub-menu for All Leads
        add_submenu_page(
            'opbcrm',
            __('All Leads', 'opbcrm'),
            __('All Leads', 'opbcrm'),
            'view_others_leads',
            'opbcrm-all-leads',
            array($this, 'render_all_leads_page')
        );

        // Sub-menu for CRM Users
        add_submenu_page(
            'opbcrm',
            __('CRM Users', 'opbcrm'),
            __('CRM Users', 'opbcrm'),
            'manage_options',
            'opbcrm-users',
            array($this, 'render_crm_users_page')
        );

        // Sub-menu for Lead Migration
        add_submenu_page(
            'opbcrm',
            __('Migrate Old Leads', 'opbcrm'),
            __('Migrate Old Leads', 'opbcrm'),
            'manage_options',
            'opbcrm-migrate-leads',
            array($this, 'render_migrate_leads_page')
        );

        // Sub-menu for Deals
        add_submenu_page(
            'opbcrm',
            __('Deals', 'opbcrm'),
            __('Deals', 'opbcrm'),
            'view_deals',
            'opbcrm-deals',
            array($this, 'render_deals_page')
        );

        // Sub-menu for Tasks
        add_submenu_page(
            'opbcrm',
            __('Tasks', 'opbcrm'),
            __('Tasks', 'opbcrm'),
            'view_tasks',
            'opbcrm-tasks',
            array($this, 'render_tasks_page')
        );

        // Sub-menu for Automations
        add_submenu_page(
            'opbcrm',
            __('Automations', 'opbcrm'),
            __('Automations', 'opbcrm'),
            'manage_automations',
            'opbcrm_automations',
            array($this, 'render_automations_page')
        );
    }

    public function register_deal_post_type() {
        $labels = array(
            'name' => __('Deals', 'opbcrm'),
            'singular_name' => __('Deal', 'opbcrm'),
            'add_new' => __('Add New Deal', 'opbcrm'),
            'add_new_item' => __('Add New Deal', 'opbcrm'),
            'edit_item' => __('Edit Deal', 'opbcrm'),
            'new_item' => __('New Deal', 'opbcrm'),
            'view_item' => __('View Deal', 'opbcrm'),
            'search_items' => __('Search Deals', 'opbcrm'),
            'not_found' => __('No deals found', 'opbcrm'),
            'not_found_in_trash' => __('No deals found in Trash', 'opbcrm'),
            'all_items' => __('All Deals', 'opbcrm'),
        );
        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => array('title'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'has_archive' => false,
        );
        register_post_type('opbcrm_deal', $args);
    }

    public function register_task_post_type() {
        $labels = array(
            'name' => __('Tasks', 'opbcrm'),
            'singular_name' => __('Task', 'opbcrm'),
            'add_new' => __('Add New Task', 'opbcrm'),
            'add_new_item' => __('Add New Task', 'opbcrm'),
            'edit_item' => __('Edit Task', 'opbcrm'),
            'new_item' => __('New Task', 'opbcrm'),
            'view_item' => __('View Task', 'opbcrm'),
            'search_items' => __('Search Tasks', 'opbcrm'),
            'not_found' => __('No tasks found', 'opbcrm'),
            'not_found_in_trash' => __('No tasks found in Trash', 'opbcrm'),
            'all_items' => __('All Tasks', 'opbcrm'),
        );
        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => array('title'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'has_archive' => false,
        );
        register_post_type('opbcrm_task', $args);
    }

    public function render_dashboard_page() {
        // Only enqueue dashboard assets for this page
        add_action('admin_enqueue_scripts', function($hook) {
            if ($hook === 'toplevel_page_opbcrm') {
                wp_enqueue_style('font-awesome-5', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4');
                wp_enqueue_script('sortable-js', 'https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js', array(), '1.14.0', true);
                wp_enqueue_script('opbcrm-frontend-js', OPBCRM_ASSETS_URL . 'js/frontend-dashboard.js', array('jquery', 'sortable-js'), OPBCRM_VERSION, true);
                wp_enqueue_style('opbcrm-frontend-css', OPBCRM_ASSETS_URL . 'css/frontend-dashboard.css', array(), OPBCRM_VERSION);
                wp_enqueue_style('opbcrm-modern-css', OPBCRM_ASSETS_URL . 'css/opbcrm-modern.css', array(), OPBCRM_VERSION);
                // Localize script for AJAX
                $ajax_object = 'var opbcrm_ajax = ' . wp_json_encode(array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('opbcrm_frontend_nonce')
                ));
                wp_add_inline_script('opbcrm-frontend-js', $ajax_object, 'before');
            }
        });
        include_once OPBCRM_TEMPLATES_PATH . 'admin-dashboard-wrapper.php';
    }

    public function render_lead_stages_page() {
        // This will render the settings page for lead stages
        if (file_exists(OPBCRM_TEMPLATES_PATH . 'admin/manage-lead-stages.php')) {
            include_once OPBCRM_TEMPLATES_PATH . 'admin/manage-lead-stages.php';
        } else {
            echo '<div class="wrap"><h1>' . __('Error: Template not found.', 'opbcrm') . '</h1></div>';
        }
    }

    public function render_permissions_page() {
        // This will render the settings page for role permissions
        if (file_exists(OPBCRM_TEMPLATES_PATH . 'admin/manage-permissions.php')) {
            include_once OPBCRM_TEMPLATES_PATH . 'admin/manage-permissions.php';
        } else {
            echo '<div class="wrap"><h1>' . __('Error: Permissions template not found.', 'opbcrm') . '</h1></div>';
        }
    }

    public function render_all_leads_page() {
        echo '<div class="wrap opbcrm-admin-leads-wrap">';
        echo '<h1 style="display:flex;align-items:center;justify-content:space-between;">All Leads <button id="opbcrm-create-lead-btn" class="opbcrm-btn opbcrm-btn-primary" style="font-size:15px;padding:7px 18px;"><i class="fas fa-plus"></i> Create New Lead</button></h1>';
        echo '<div class="opbcrm-leads-toolbar" style="margin-bottom:18px;display:flex;align-items:center;gap:16px;">';
        echo '<input type="text" id="opbcrm-leads-search" class="opbcrm-search-input" placeholder="Search by name, phone, email, stage, agent..." style="min-width:300px;">';
        echo '</div>';
        echo '<table class="opbcrm-leads-table widefat fixed striped"><thead><tr><th>ID</th><th>Name</th><th>Stage</th><th>Phone</th><th>Email</th><th>Agent</th><th>Actions</th></tr></thead><tbody id="opbcrm-leads-tbody">';
        $args = array('post_type' => 'opbez_lead','post_status' => 'publish','posts_per_page' => -1,);
        $leads = get_posts($args);
        foreach ($leads as $lead) {
            $stage = get_post_meta($lead->ID, 'lead_status', true);
            $phone = get_post_meta($lead->ID, 'lead_phone', true);
            $email = get_post_meta($lead->ID, 'lead_email', true);
            $agent = get_userdata($lead->post_author);
            echo '<tr>';
            echo '<td>' . esc_html($lead->ID) . '</td>';
            echo '<td>' . esc_html($lead->post_title) . '</td>';
            echo '<td>' . esc_html($stage) . '</td>';
            echo '<td>' . esc_html($phone) . '</td>';
            echo '<td>' . esc_html($email) . '</td>';
            echo '<td>' . esc_html($agent ? $agent->display_name : '-') . '</td>';
            echo '<td><button class="opbcrm-btn opbcrm-btn-secondary opbcrm-edit-lead-btn" data-lead-id="' . esc_attr($lead->ID) . '"><i class="fas fa-edit"></i> Edit</button></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        // Modal for creating/editing lead (Bitrix-style)
        echo '<div id="opbcrm-lead-modal" class="opbcrm-modal bitrix-lead-modal" style="display:none;">';
        echo '<div class="opbcrm-modal-content">';
        echo '<div class="opbcrm-modal-header"><h3 id="opbcrm-lead-modal-title"><i class="fas fa-user-plus"></i> Create New Lead</h3><button class="opbcrm-modal-close">&times;</button></div>';
        echo '<div class="opbcrm-modal-body">';
        echo '<form id="opbcrm-lead-form">';
        echo '<input type="hidden" name="lead_id" id="opbcrm-lead-id" value="">';
        echo '<div class="lead-form-grid">';
        echo '<div class="lead-form-left">';
        echo '<div class="form-group"><label for="opbcrm-lead-name"><i class="fas fa-user"></i> Name <span class="required">*</span></label><input type="text" name="lead_name" id="opbcrm-lead-name" required></div>';
        echo '<div class="form-group"><label for="opbcrm-lead-phone"><i class="fas fa-phone"></i> Phone <span class="required">*</span></label><input type="text" name="lead_phone" id="opbcrm-lead-phone" required></div>';
        echo '<div class="form-group"><label for="opbcrm-lead-email"><i class="fas fa-envelope"></i> Email</label><input type="email" name="lead_email" id="opbcrm-lead-email"></div>';
        echo '<div class="form-group"><label for="opbcrm-lead-agent"><i class="fas fa-user-tie"></i> Agent <span class="required">*</span></label><select name="lead_agent" id="opbcrm-lead-agent" required><option value="">Loading...</option></select></div>';
        echo '<div class="form-group"><label for="opbcrm-lead-stage"><i class="fas fa-flag"></i> Stage <span class="required">*</span></label><select name="lead_stage" id="opbcrm-lead-stage" required><option value="">Loading...</option></select></div>';
        echo '<div class="form-group" id="opbcrm-lead-substage-group" style="display:none;"><label for="opbcrm-lead-sub-stage"><i class="fas fa-list"></i> Sub-Stage <span class="required" id="opbcrm-substage-required" style="display:none;">*</span></label><select name="lead_sub_stage" id="opbcrm-lead-sub-stage"></select></div>';
        echo '<div class="form-group"><label for="opbcrm-lead-source"><i class="fas fa-globe"></i> Source</label><input type="text" name="lead_source" id="opbcrm-lead-source"></div>';
        echo '<div class="form-group"><label for="opbcrm-lead-tags"><i class="fas fa-tags"></i> Tags</label><input type="text" name="lead_tags" id="opbcrm-lead-tags"></div>';
        echo '<div class="form-group"><label for="opbcrm-lead-campaign"><i class="fas fa-bullhorn"></i> Campaign</label><input type="text" name="lead_campaign" id="opbcrm-lead-campaign"></div>';
        // Custom fields placeholder (can be loaded via PHP or JS)
        echo '<div id="opbcrm-lead-custom-fields"></div>';
        echo '</div>';
        echo '<div class="lead-form-right">';
        echo '<div class="lead-tabs">';
        echo '<ul class="lead-tab-list">';
        echo '<li class="active" data-tab="activity"><i class="fas fa-history"></i> Activity</li>';
        echo '<li data-tab="proposal"><i class="fas fa-file-signature"></i> Proposal</li>';
        echo '<li data-tab="comment"><i class="fas fa-comments"></i> Comment</li>';
        echo '</ul>';
        echo '<div class="lead-tab-content active" id="tab-activity"><div class="lead-activity-list"></div></div>';
        echo '<div class="lead-tab-content" id="tab-proposal">';
        echo '<div class="proposal-property-picker"><label><i class="fas fa-home"></i> Property</label><select id="opbcrm-lead-proposal" name="lead_proposal"><option value="">Loading...</option></select></div>';
        echo '<div class="proposal-preview"></div>';
        echo '</div>';
        echo '<div class="lead-tab-content" id="tab-comment">';
        echo '<label for="opbcrm-lead-comment"><i class="fas fa-comment"></i> Initial Comment</label>';
        echo '<textarea name="lead_comment" id="opbcrm-lead-comment" rows="4"></textarea>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div class="form-actions"><button type="submit" class="opbcrm-btn opbcrm-btn-primary"><i class="fas fa-save"></i> Save Lead</button><span class="spinner"></span></div>';
        echo '</form>';
        echo '</div></div></div>';
        // Modal and table scripts for Create New Lead
        echo <<<EOT
<script>
jQuery(document).ready(function($){
    // Open Create Lead modal
    $("#opbcrm-create-lead-btn").on("click", function(){
        $("#opbcrm-lead-modal-title").text("Create New Lead");
        $("#opbcrm-lead-form")[0].reset();
        $("#opbcrm-lead-id").val("");
        // Populate Stage dropdown
        $.post(ajaxurl, {action: "opbcrm_get_lead_stages"}, function(response){
            if(response.success){
                var options = '';
                $.each(response.stages, function(i, stage){
                    options += '<option value="'+stage.id+'">'+stage.label+'</option>';
                });
                $("#opbcrm-lead-stage").html(options);
            }
        });
        // Populate Agent dropdown
        $.post(ajaxurl, {action: "opbcrm_get_crm_agents"}, function(response){
            if(response.success){
                var options = '';
                $.each(response.agents, function(i, agent){
                    options += '<option value="'+agent.id+'">'+agent.name+'</option>';
                });
                $("#opbcrm-lead-agent").html(options);
            }
        });
        $("#opbcrm-lead-modal").show();
    });
    // Close modal
    $(".opbcrm-modal-close").on("click", function(){
        $("#opbcrm-lead-modal").hide();
    });
    // Toast function
    function showToast(msg, success=true) {
        var toast = $('<div class="opbcrm-toast '+(success?'success':'error')+'">'+msg+'</div>');
        $("body").append(toast);
        toast.fadeIn(200).delay(2000).fadeOut(400, function(){ $(this).remove(); });
    }
    // Edit Lead button logic
    $(document).on("click", ".opbcrm-edit-lead-btn", function(){
        var leadId = $(this).data("lead-id");
        $.post(ajaxurl, {action: "opbcrm_get_lead", lead_id: leadId}, function(response){
            if(response.success){
                var lead = response.lead;
                $("#opbcrm-lead-modal-title").text("Edit Lead");
                $("#opbcrm-lead-id").val(lead.id);
                $("#opbcrm-lead-name").val(lead.name);
                $("#opbcrm-lead-phone").val(lead.phone);
                $("#opbcrm-lead-email").val(lead.email);
                // Populate Stage dropdown
                $.post(ajaxurl, {action: "opbcrm_get_lead_stages"}, function(resp2){
                    if(resp2.success){
                        var options = '';
                        $.each(resp2.stages, function(i, stage){
                            options += '<option value="'+stage.id+'"'+(stage.id==lead.stage?' selected':'')+'>'+stage.label+'</option>';
                        });
                        $("#opbcrm-lead-stage").html(options);
                    }
                });
                // Populate Agent dropdown
                $.post(ajaxurl, {action: "opbcrm_get_crm_agents"}, function(resp3){
                    if(resp3.success){
                        var options = '';
                        $.each(resp3.agents, function(i, agent){
                            options += '<option value="'+agent.id+'"'+(agent.id==lead.agent?' selected':'')+'>'+agent.name+'</option>';
                        });
                        $("#opbcrm-lead-agent").html(options);
                    }
                });
                $("#opbcrm-lead-proposal").val(lead.proposal);
                $("#opbcrm-lead-comment").val(lead.comment);
                $("#opbcrm-lead-modal").show();
            } else {
                showToast(response.data.message||'Error loading lead', false);
            }
        });
    });
    // On save, update row if editing
    $("#opbcrm-lead-form").on("submit", function(e){
        e.preventDefault();
        var isEdit = $("#opbcrm-lead-id").val() != '';
        var data = $(this).serialize()+'&action=opbcrm_save_lead';
        var spinner = $(this).find('.spinner');
        spinner.addClass('is-active');
        $.post(ajaxurl, data, function(response){
            spinner.removeClass('is-active');
            if(response.success){
                showToast('Lead saved successfully!', true);
                $("#opbcrm-lead-modal").hide();
                // Refresh or update the leads table dynamically
                $.post(ajaxurl, {action: 'opbcrm_get_lead', lead_id: response.lead_id}, function(resp2){
                    if(resp2.success && resp2.lead){
                        var lead = resp2.lead;
                        var rowHtml = '<tr>'+
                            '<td>'+lead.id+'</td>'+
                            '<td>'+lead.name+'</td>'+
                            '<td>'+lead.stage+'</td>'+
                            '<td>'+lead.phone+'</td>'+
                            '<td>'+lead.email+'</td>'+
                            '<td>'+lead.agent_name+'</td>'+
                            '<td><button class="opbcrm-btn opbcrm-btn-secondary opbcrm-edit-lead-btn" data-lead-id="'+lead.id+'"><i class="fas fa-edit"></i> Edit</button></td>'+
                        '</tr>';
                        if(isEdit){
                            $("#opbcrm-leads-tbody tr").each(function(){
                                if($(this).find('td:first').text() == lead.id){
                                    $(this).replaceWith(rowHtml);
                                }
                            });
                        }else{
                            $("#opbcrm-leads-tbody").prepend(rowHtml);
                        }
                    }
                });
            }else{
                showToast(response.data && response.data.message ? response.data.message : 'Error saving lead', false);
            }
        }).fail(function(){
            spinner.removeClass('is-active');
            showToast('An unexpected error occurred.', false);
        });
    });
});
</script>
EOT;
        echo '</div>';
    }

    public function render_crm_users_page() {
        // Enqueue JS and localize nonces
        add_action('admin_enqueue_scripts', function($hook) {
            if ($hook === 'opbcrm_page_opbcrm-users') {
                wp_enqueue_script('opbcrm-crm-dashboard', plugins_url('../../assets/js/crm-dashboard.js', __FILE__), array('jquery'), null, true);
                wp_localize_script('opbcrm-crm-dashboard', 'opbcrm_save_user_nonce', wp_create_nonce('opbcrm_save_user_nonce'));
                wp_localize_script('opbcrm-crm-dashboard', 'opbcrm_delete_user_nonce', wp_create_nonce('opbcrm_delete_user_nonce'));
            }
        });
        // Localize user field permissions for JS (keep as is for now)
        $user_field_permissions = array(
            'username' => array('view' => true, 'edit' => true, 'delete' => true),
            'email' => array('view' => true, 'edit' => true, 'delete' => true),
            'role' => array('view' => true, 'edit' => true, 'delete' => true),
            'add_user' => true,
            'edit_user' => true,
            'delete_user' => true,
        );
        echo '<script>var opbcrm_user_field_permissions = '.json_encode($user_field_permissions).';</script>';
        echo '<div class="crm-glass-panel crm-users-panel" style="margin-top:40px;">';
        echo '<div class="crm-header-row">';
        echo '<span class="crm-title">CRM Users</span>';
        echo '<button id="opbcrm-add-user-btn" class="crm-btn" style="font-size:15px;padding:7px 18px;">+ Add User</button>';
        echo '</div>';
        echo '<div class="crm-toolbar-row">';
        echo '<input type="text" id="opbcrm-users-search" class="crm-input" placeholder="Search by username, email, role..." style="min-width:220px;">';
        echo '</div>';
        echo '<table class="crm-table users-table"><thead><tr>';
        echo '<th>Username</th>';
        echo '<th>Email</th>';
        echo '<th>Role</th>';
        echo '<th>Created</th>';
        echo '<th>Actions</th>';
        echo '</tr></thead><tbody id="opbcrm-users-tbody">';
        global $wpdb;
        $table = $wpdb->prefix . 'opbcrm_users';
        $users = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
        foreach ($users as $user) {
            echo '<tr data-user-id="'.esc_attr($user->id).'">';
            echo '<td>'.esc_html($user->username).'</td>';
            echo '<td>'.esc_html($user->email).'</td>';
            echo '<td>'.esc_html(ucfirst($user->role)).'</td>';
            echo '<td>'.esc_html($user->created_at).'</td>';
            echo '<td>';
            echo '<button class="crm-btn edit-user-btn" data-user-id="'.esc_attr($user->id).'">Edit</button> ';
            if ($user->username !== 'admin') {
                echo '<button class="crm-btn delete-user-btn" data-user-id="'.esc_attr($user->id).'">Delete</button>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        // Modal for add/edit user
        echo '<div id="opbcrm-user-modal" class="crm-modal-bg" style="display:none;">';
        echo '<div class="crm-modal-panel">';
        echo '<button class="modal-close" id="close-user-modal">&times;</button>';
        echo '<form id="opbcrm-user-form" autocomplete="off">';
        echo '<input type="hidden" name="user_id" id="opbcrm-user-id" value="">';
        echo '<div class="floating-label-group"><input type="text" name="username" id="opbcrm-user-username" required placeholder=" "><label>Username <span style="color:#83A2DB">*</span></label></div>';
        echo '<div class="floating-label-group"><input type="email" name="email" id="opbcrm-user-email" required placeholder=" "><label>Email <span style="color:#83A2DB">*</span></label></div>';
        echo '<div class="floating-label-group"><select name="role" id="opbcrm-user-role"><option value="admin">Admin</option><option value="agent">Agent</option></select><label>Role</label></div>';
        echo '<div class="floating-label-group"><input type="password" name="password" id="opbcrm-user-password" placeholder=" "><label>Password</label></div>';
        echo '<div class="btn-row">';
        echo '<button type="button" class="crm-btn" id="cancel-user-btn">Cancel</button>';
        echo '<button type="submit" class="crm-btn">Save User</button>';
        echo '</div>';
        echo '</form>';
        echo '</div></div>';
        echo '</div>';
        // JS for modal, AJAX, and UI will be updated in the next step
    }

    public function render_migrate_leads_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Migrate Old Leads from Houzez CRM', 'opbcrm'); ?></h1>
            <p><?php _e('Click the button below to migrate all leads from the old Houzez CRM system to the new CRM post type. This will not delete any old data.', 'opbcrm'); ?></p>
            <button id="opbcrm-migrate-leads-btn" class="button button-primary">Run Migration</button>
            <div id="opbcrm-migrate-leads-status" style="margin-top:20px;"></div>
        </div>
        <script>
        jQuery(document).ready(function($){
            $('#opbcrm-migrate-leads-btn').on('click', function(){
                var btn = $(this);
                btn.prop('disabled', true).text('Migrating...');
                $('#opbcrm-migrate-leads-status').html('');
                $.post(ajaxurl, {action: 'opbcrm_migrate_old_leads', _wpnonce: '<?php echo wp_create_nonce('opbcrm_migrate_leads_nonce'); ?>'}, function(response){
                    if(response.success){
                        $('#opbcrm-migrate-leads-status').html('<span style="color:green">'+response.data+'</span>');
                    }else{
                        $('#opbcrm-migrate-leads-status').html('<span style="color:red">'+(response.data || 'Migration failed')+'</span>');
                    }
                    btn.prop('disabled', false).text('Run Migration');
                });
            });
        });
        </script>
        <?php
    }

    public function render_deals_page() {
        // Glassy panel wrapper
        echo '<div class="crm-glass-panel" style="margin:40px auto;max-width:1100px;padding:32px 28px 24px 28px;border-radius:18px;box-shadow:0 4px 24px 0 rgba(31,38,135,0.10);backdrop-filter:blur(10px);background:rgba(255,255,255,0.22);border:1.5px solid rgba(255,255,255,0.22);font-family:Inter,sans-serif;">';
        echo '<div class="crm-header-row" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">';
        echo '<span class="crm-title" style="font-size:1.5rem;font-weight:700;letter-spacing:-0.5px;">Deals</span>';
        echo '<button class="crm-btn" id="add-deal-btn" style="font-size:15px;padding:7px 18px;">+ Add Deal</button>';
        echo '</div>';
        // Filters row
        echo '<div class="crm-filters-row" style="display:flex;align-items:center;gap:18px;margin-bottom:18px;">';
        echo '<input type="text" id="deals-search-input" class="crm-input" placeholder="Search deals..." style="min-width:180px;font-size:14px;padding:7px 14px;border-radius:10px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.5);">';
        echo '<select id="deals-stage-filter" class="crm-filter-select" style="font-size:13px;padding:5px 18px 5px 8px;border-radius:8px;background:rgba(255,255,255,0.7);border:1.2px solid #e0e0e0;font-family:Inter,sans-serif;min-width:120px;">';
        echo '<option value="">All Stages</option>';
        foreach ([
            'new' => 'New',
            'proposal' => 'Proposal',
            'negotiation' => 'Negotiation',
            'won' => 'Won',
            'lost' => 'Lost',
        ] as $key => $label) {
            echo '<option value="'.esc_attr($key).'">'.esc_html($label).'</option>';
        }
        echo '</select>';
        echo '</div>';
        // Deals Analytics Dashboard
        echo '<div class="crm-glass-panel" style="margin-bottom:28px;padding:18px 14px 14px 14px;border-radius:16px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.10);backdrop-filter:blur(8px);background:rgba(255,255,255,0.22);border:1.2px solid rgba(255,255,255,0.18);font-family:Inter,sans-serif;">';
        echo '<div class="crm-header-row" style="display:flex;align-items:center;gap:18px;margin-bottom:10px;">';
        echo '<span class="crm-title" style="font-size:1.15rem;font-weight:700;letter-spacing:-0.5px;">Deal Analytics</span>';
        // Quick filter chips
        echo '<div class="crm-report-chips" style="display:flex;gap:7px;align-items:center;margin-left:18px;">';
        foreach ([
            'all' => 'All Time',
            'month' => 'This Month',
            '30d' => 'Last 30 Days',
            'year' => 'This Year',
        ] as $key => $label) {
            echo '<button type="button" class="crm-chip-btn deal-report-chip" data-range="'.esc_attr($key).'" style="font-size:12.5px;padding:4px 13px;border-radius:8px;background:rgba(255,255,255,0.32);border:1px solid #e0e0e0;font-family:Inter,sans-serif;cursor:pointer;">'.esc_html($label).'</button>';
        }
        echo '</div>';
        // Date range picker
        echo '<input type="date" id="deal-report-start" style="margin-left:18px;font-size:12.5px;padding:4px 8px;border-radius:7px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.7);">';
        echo '<span style="margin:0 6px;">-</span>';
        echo '<input type="date" id="deal-report-end" style="font-size:12.5px;padding:4px 8px;border-radius:7px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.7);">';
        echo '</div>';
        // Widgets row
        echo '<div class="crm-reporting-widgets-row" style="display:flex;flex-wrap:wrap;gap:22px;margin-top:10px;">';
        // Total Deals
        echo '<div class="crm-widget glassy-panel" style="flex:1;min-width:140px;max-width:200px;padding:14px 10px 10px 10px;border-radius:12px;position:relative;">';
        echo '<div style="font-size:1.05rem;font-weight:600;display:flex;align-items:center;justify-content:space-between;">Total Deals <button class="crm-csv-btn" data-csv="total" title="Export CSV" style="background:rgba(255,255,255,0.7);border:none;border-radius:7px;padding:2px 8px;font-size:13px;cursor:pointer;margin-left:6px;"><i class="fas fa-download"></i></button></div>';
        echo '<div id="deal-total-count" style="font-size:1.7rem;font-weight:700;margin-top:6px;">0</div>';
        echo '</div>';
        // Pipeline Value
        echo '<div class="crm-widget glassy-panel" style="flex:1;min-width:140px;max-width:200px;padding:14px 10px 10px 10px;border-radius:12px;position:relative;">';
        echo '<div style="font-size:1.05rem;font-weight:600;display:flex;align-items:center;justify-content:space-between;">Pipeline Value <button class="crm-csv-btn" data-csv="pipeline" title="Export CSV" style="background:rgba(255,255,255,0.7);border:none;border-radius:7px;padding:2px 8px;font-size:13px;cursor:pointer;margin-left:6px;"><i class="fas fa-download"></i></button></div>';
        echo '<div id="deal-pipeline-value" style="font-size:1.7rem;font-weight:700;margin-top:6px;color:#28a745;">$0</div>';
        echo '</div>';
        // Deals by Stage
        echo '<div class="crm-widget glassy-panel" style="flex:1;min-width:180px;max-width:260px;padding:14px 10px 10px 10px;border-radius:12px;position:relative;">';
        echo '<div style="font-size:1.05rem;font-weight:600;display:flex;align-items:center;justify-content:space-between;">By Stage <button class="crm-csv-btn" data-csv="stage" title="Export CSV" style="background:rgba(255,255,255,0.7);border:none;border-radius:7px;padding:2px 8px;font-size:13px;cursor:pointer;margin-left:6px;"><i class="fas fa-download"></i></button></div>';
        echo '<canvas id="deal-stage-pie" height="70"></canvas>';
        echo '</div>';
        // Deals by Agent
        echo '<div class="crm-widget glassy-panel" style="flex:2;min-width:220px;max-width:340px;padding:14px 10px 10px 10px;border-radius:12px;position:relative;">';
        echo '<div style="font-size:1.05rem;font-weight:600;display:flex;align-items:center;justify-content:space-between;">By Agent <button class="crm-csv-btn" data-csv="agent" title="Export CSV" style="background:rgba(255,255,255,0.7);border:none;border-radius:7px;padding:2px 8px;font-size:13px;cursor:pointer;margin-left:6px;"><i class="fas fa-download"></i></button></div>';
        echo '<canvas id="deal-agent-bar" height="70"></canvas>';
        echo '</div>';
        // Deals Won Over Time
        echo '<div class="crm-widget glassy-panel" style="flex:2;min-width:220px;max-width:340px;padding:14px 10px 10px 10px;border-radius:12px;position:relative;">';
        echo '<div style="font-size:1.05rem;font-weight:600;display:flex;align-items:center;justify-content:space-between;">Won Over Time <button class="crm-csv-btn" data-csv="won" title="Export CSV" style="background:rgba(255,255,255,0.7);border:none;border-radius:7px;padding:2px 8px;font-size:13px;cursor:pointer;margin-left:6px;"><i class="fas fa-download"></i></button></div>';
        echo '<canvas id="deal-won-line" height="70"></canvas>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        // Kanban Board
        echo '<div id="deals-kanban-board" class="crm-kanban-board" style="display:flex;gap:22px;margin-bottom:32px;">';
        $stages = [
            'new' => 'New',
            'proposal' => 'Proposal',
            'negotiation' => 'Negotiation',
            'won' => 'Won',
            'lost' => 'Lost',
        ];
        foreach ($stages as $stage_key => $stage_label) {
            echo '<div class="kanban-column" data-stage="'.esc_attr($stage_key).'" style="flex:1;min-width:180px;background:rgba(255,255,255,0.18);border-radius:14px;padding:12px 8px;box-shadow:0 2px 8px 0 rgba(31,38,135,0.07);">';
            echo '<div class="kanban-column-header" style="font-weight:600;font-size:1.05rem;color:#4b68b6;margin-bottom:8px;">'.esc_html($stage_label).'</div>';
            echo '<div class="kanban-cards" id="kanban-cards-'.esc_attr($stage_key).'" style="min-height:48px;display:flex;flex-direction:column;gap:10px;"></div>';
            echo '</div>';
        }
        echo '</div>';
        // Deals Table
        echo '<div id="deals-table-wrap">';
        echo '<table class="crm-table deals-table" style="width:100%;font-size:13px;border-radius:12px;overflow:hidden;">';
        echo '<thead><tr>';
        echo '<th>Title</th><th>Value</th><th>Stage</th><th>Owner</th><th>Close Date</th><th>Status</th><th>Actions</th>';
        echo '</tr></thead>';
        echo '<tbody id="deals-tbody">';
        // Placeholder row
        echo '<tr><td colspan="7" style="text-align:center;color:#888;padding:32px;">No deals yet.</td></tr>';
        echo '</tbody></table>';
        echo '</div>';
        // Add/Edit Deal Modal
        echo '<div id="deal-modal-bg" class="crm-modal-bg" style="display:none;">';
        echo '<div class="crm-modal-panel" style="max-width:480px;">';
        echo '<button class="modal-close" id="close-deal-modal">&times;</button>';
        echo '<form id="deal-form" autocomplete="off">';
        echo '<input type="hidden" name="deal_id" id="deal-id" value="">';
        echo '<div class="floating-label-group"><input type="text" name="deal_title" id="deal-title" required placeholder=" "><label for="deal-title">Title <span style="color:#83A2DB">*</span></label></div>';
        echo '<div class="floating-label-group"><input type="number" name="deal_value" id="deal-value" min="0" step="0.01" placeholder=" "><label for="deal-value">Value</label></div>';
        echo '<div class="floating-label-group"><select name="deal_stage" id="deal-stage"><option value="">Select Stage</option><option value="new">New</option><option value="proposal">Proposal</option><option value="negotiation">Negotiation</option><option value="won">Won</option><option value="lost">Lost</option></select><label for="deal-stage">Stage</label></div>';
        echo '<div class="floating-label-group"><input type="text" name="deal_owner" id="deal-owner" placeholder=" "><label for="deal-owner">Owner</label></div>';
        echo '<div class="floating-label-group"><input type="date" name="deal_close_date" id="deal-close-date" placeholder=" "><label for="deal-close-date">Expected Close Date</label></div>';
        echo '<div class="floating-label-group"><input type="text" name="deal_status" id="deal-status" placeholder=" "><label for="deal-status">Status</label></div>';
        echo '<div class="floating-label-group"><textarea name="deal_notes" id="deal-notes" rows="3" placeholder=" "></textarea><label for="deal-notes">Notes</label></div>';
        echo '<div class="btn-row" style="margin-top:18px;">';
        echo '<button type="submit" class="crm-btn crm-btn-primary" id="save-deal-btn" style="font-size:15px;padding:7px 22px;">Save Deal</button>';
        echo '<button type="button" class="crm-btn" id="cancel-deal-btn" style="font-size:15px;padding:7px 22px;background:#eee;color:#333;">Cancel</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function render_tasks_page() {
        // Glassy panel wrapper
        echo '<div class="crm-glass-panel" style="margin:40px auto;max-width:1100px;padding:32px 28px 24px 28px;border-radius:18px;box-shadow:0 4px 24px 0 rgba(31,38,135,0.10);backdrop-filter:blur(10px);background:rgba(255,255,255,0.22);border:1.5px solid rgba(255,255,255,0.22);font-family:Inter,sans-serif;">';
        echo '<div class="crm-header-row" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">';
        echo '<span class="crm-title" style="font-size:1.5rem;font-weight:700;letter-spacing:-0.5px;">Tasks</span>';
        echo '<button class="crm-btn" id="add-task-btn" style="font-size:15px;padding:7px 18px;">+ Add Task</button>';
        echo '</div>';
        // Filters row
        echo '<div class="crm-filters-row" style="display:flex;align-items:center;gap:18px;margin-bottom:18px;">';
        echo '<input type="text" id="tasks-search-input" class="crm-input" placeholder="Search tasks..." style="min-width:180px;font-size:14px;padding:7px 14px;border-radius:10px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.5);">';
        echo '<select id="tasks-type-filter" class="crm-filter-select" style="font-size:13px;padding:5px 18px 5px 8px;border-radius:8px;background:rgba(255,255,255,0.7);border:1.2px solid #e0e0e0;font-family:Inter,sans-serif;min-width:120px;">';
        echo '<option value="">All Types</option>';
        foreach ([
            'call' => 'Call',
            'meeting' => 'Meeting',
            'followup' => 'Follow-up',
            'email' => 'Email',
            'other' => 'Other',
        ] as $key => $label) {
            echo '<option value="'.esc_attr($key).'">'.esc_html($label).'</option>';
        }
        echo '</select>';
        echo '<select id="tasks-status-filter" class="crm-filter-select" style="font-size:13px;padding:5px 18px 5px 8px;border-radius:8px;background:rgba(255,255,255,0.7);border:1.2px solid #e0e0e0;font-family:Inter,sans-serif;min-width:120px;">';
        echo '<option value="">All Statuses</option>';
        echo '<option value="open">Open</option>';
        echo '<option value="completed">Completed</option>';
        echo '</select>';
        echo '</div>';
        // Bulk actions bar
        echo '<div class="crm-bulk-actions-bar" style="display:flex;align-items:center;gap:12px;margin-bottom:10px;background:rgba(255,255,255,0.32);border-radius:10px;padding:7px 14px 7px 7px;box-shadow:0 1px 6px 0 rgba(31,38,135,0.07);font-family:Inter,sans-serif;">';
        echo '<span style="font-size:13px;font-weight:500;">Bulk Actions:</span>';
        if (current_user_can('edit_tasks')) {
            echo '<button type="button" class="crm-btn crm-bulk-complete" style="font-size:13px;padding:4px 13px;">Complete</button>';
        }
        if (current_user_can('delete_tasks')) {
            echo '<button type="button" class="crm-btn crm-bulk-delete" style="font-size:13px;padding:4px 13px;background:rgba(234,84,85,0.13);color:#ea5455;">Delete</button>';
        }
        echo '<button type="button" class="crm-btn crm-bulk-edit" style="font-size:13px;padding:4px 13px;background:rgba(0,123,255,0.13);color:#007bff;">Bulk Edit</button>';
        echo '<button type="button" class="crm-btn crm-bulk-export" style="font-size:13px;padding:4px 13px;background:rgba(40,180,80,0.13);color:#28a745;">Export CSV</button>';
        echo '<span class="crm-bulk-count" style="font-size:12px;color:#888;margin-left:8px;">0 selected</span>';
        echo '</div>';
        // Tasks Analytics Dashboard
        echo '<div class="crm-glass-panel" style="margin-bottom:28px;padding:18px 14px 14px 14px;border-radius:16px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.10);backdrop-filter:blur(8px);background:rgba(255,255,255,0.22);border:1.2px solid rgba(255,255,255,0.18);font-family:Inter,sans-serif;">';
        echo '<div class="crm-header-row" style="display:flex;align-items:center;gap:18px;margin-bottom:10px;">';
        echo '<span class="crm-title" style="font-size:1.15rem;font-weight:700;letter-spacing:-0.5px;">Task Analytics</span>';
        // Quick filter chips
        echo '<div class="crm-report-chips" style="display:flex;gap:7px;align-items:center;margin-left:18px;">';
        foreach ([
            'all' => 'All Time',
            'month' => 'This Month',
            '30d' => 'Last 30 Days',
            'year' => 'This Year',
        ] as $key => $label) {
            echo '<button type="button" class="crm-chip-btn task-report-chip" data-range="'.esc_attr($key).'" style="font-size:12.5px;padding:4px 13px;border-radius:8px;background:rgba(255,255,255,0.32);border:1px solid #e0e0e0;font-family:Inter,sans-serif;cursor:pointer;">'.esc_html($label).'</button>';
        }
        echo '</div>';
        // Date range picker
        echo '<input type="date" id="task-report-start" style="margin-left:18px;font-size:12.5px;padding:4px 8px;border-radius:7px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.7);">';
        echo '<span style="margin:0 6px;">-</span>';
        echo '<input type="date" id="task-report-end" style="font-size:12.5px;padding:4px 8px;border-radius:7px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.7);">';
        echo '</div>';
        // Widgets row
        echo '<div class="crm-reporting-widgets-row" style="display:flex;flex-wrap:wrap;gap:22px;margin-top:10px;">';
        // Total Tasks
        echo '<div class="crm-widget glassy-panel" style="flex:1;min-width:140px;max-width:200px;padding:14px 10px 10px 10px;border-radius:12px;position:relative;">';
        echo '<div style="font-size:1.05rem;font-weight:600;display:flex;align-items:center;justify-content:space-between;">Total Tasks <button class="crm-csv-btn" data-csv="total" title="Export CSV" style="background:rgba(255,255,255,0.7);border:none;border-radius:7px;padding:2px 8px;font-size:13px;cursor:pointer;margin-left:6px;"><i class="fas fa-download"></i></button></div>';
        echo '<div id="task-total-count" style="font-size:1.7rem;font-weight:700;margin-top:6px;">0</div>';
        echo '</div>';
        // Overdue Tasks
        echo '<div class="crm-widget glassy-panel" style="flex:1;min-width:140px;max-width:200px;padding:14px 10px 10px 10px;border-radius:12px;position:relative;">';
        echo '<div style="font-size:1.05rem;font-weight:600;display:flex;align-items:center;justify-content:space-between;">Overdue <button class="crm-csv-btn" data-csv="overdue" title="Export CSV" style="background:rgba(255,255,255,0.7);border:none;border-radius:7px;padding:2px 8px;font-size:13px;cursor:pointer;margin-left:6px;"><i class="fas fa-download"></i></button></div>';
        echo '<div id="task-overdue-count" style="font-size:1.7rem;font-weight:700;margin-top:6px;color:#ea5455;">0</div>';
        echo '</div>';
        // Tasks by Status
        echo '<div class="crm-widget glassy-panel" style="flex:1;min-width:180px;max-width:260px;padding:14px 10px 10px 10px;border-radius:12px;position:relative;">';
        echo '<div style="font-size:1.05rem;font-weight:600;display:flex;align-items:center;justify-content:space-between;">By Status <button class="crm-csv-btn" data-csv="status" title="Export CSV" style="background:rgba(255,255,255,0.7);border:none;border-radius:7px;padding:2px 8px;font-size:13px;cursor:pointer;margin-left:6px;"><i class="fas fa-download"></i></button></div>';
        echo '<canvas id="task-status-pie" height="70"></canvas>';
        echo '</div>';
        // Tasks by Type
        echo '<div class="crm-widget glassy-panel" style="flex:1;min-width:180px;max-width:260px;padding:14px 10px 10px 10px;border-radius:12px;position:relative;">';
        echo '<div style="font-size:1.05rem;font-weight:600;display:flex;align-items:center;justify-content:space-between;">By Type <button class="crm-csv-btn" data-csv="type" title="Export CSV" style="background:rgba(255,255,255,0.7);border:none;border-radius:7px;padding:2px 8px;font-size:13px;cursor:pointer;margin-left:6px;"><i class="fas fa-download"></i></button></div>';
        echo '<canvas id="task-type-bar" height="70"></canvas>';
        echo '</div>';
        // Tasks by Assignee
        echo '<div class="crm-widget glassy-panel" style="flex:2;min-width:220px;max-width:340px;padding:14px 10px 10px 10px;border-radius:12px;position:relative;">';
        echo '<div style="font-size:1.05rem;font-weight:600;display:flex;align-items:center;justify-content:space-between;">By Assignee <button class="crm-csv-btn" data-csv="assignee" title="Export CSV" style="background:rgba(255,255,255,0.7);border:none;border-radius:7px;padding:2px 8px;font-size:13px;cursor:pointer;margin-left:6px;"><i class="fas fa-download"></i></button></div>';
        echo '<canvas id="task-assignee-bar" height="70"></canvas>';
        echo '</div>';
        // Tasks Completed Over Time
        echo '<div class="crm-widget glassy-panel" style="flex:2;min-width:220px;max-width:340px;padding:14px 10px 10px 10px;border-radius:12px;position:relative;">';
        echo '<div style="font-size:1.05rem;font-weight:600;display:flex;align-items:center;justify-content:space-between;">Completed Over Time <button class="crm-csv-btn" data-csv="completed" title="Export CSV" style="background:rgba(255,255,255,0.7);border:none;border-radius:7px;padding:2px 8px;font-size:13px;cursor:pointer;margin-left:6px;"><i class="fas fa-download"></i></button></div>';
        echo '<canvas id="task-completed-line" height="70"></canvas>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        // Tasks Table with checkboxes
        echo '<div id="tasks-table-wrap">';
        echo '<table class="crm-table tasks-table" style="width:100%;font-size:13px;border-radius:12px;overflow:hidden;">';
        echo '<thead><tr>';
        echo '<th style="width:32px;"><input type="checkbox" id="tasks-select-all"></th>';
        echo '<th>Title</th><th>Type</th><th>Due Date</th><th>Assigned To</th><th>Related</th><th>Status</th><th>Actions</th>';
        echo '</tr></thead>';
        echo '<tbody id="tasks-tbody">';
        // Placeholder row
        echo '<tr><td colspan="7" style="text-align:center;color:#888;padding:32px;">No tasks yet.</td></tr>';
        echo '</tbody></table>';
        echo '</div>';
        // Add/Edit Task Modal
        echo '<div id="task-modal-bg" class="crm-modal-bg" style="display:none;">';
        echo '<div class="crm-modal-panel" style="max-width:480px;">';
        echo '<button class="modal-close" id="close-task-modal">&times;</button>';
        echo '<form id="task-form" autocomplete="off">';
        echo '<input type="hidden" name="task_id" id="task-id" value="">';
        echo '<div class="floating-label-group"><input type="text" name="task_title" id="task-title" required placeholder=" "><label for="task-title">Title <span style="color:#83A2DB">*</span></label></div>';
        echo '<div class="floating-label-group"><select name="task_type" id="task-type"><option value="">Select Type</option><option value="call">Call</option><option value="meeting">Meeting</option><option value="followup">Follow-up</option><option value="email">Email</option><option value="other">Other</option></select><label for="task-type">Type</label></div>';
        echo '<div class="floating-label-group"><input type="date" name="task_due_date" id="task-due-date" placeholder=" "><label for="task-due-date">Due Date</label></div>';
        echo '<div class="floating-label-group"><input type="text" name="task_assigned_to" id="task-assigned-to" placeholder=" "><label for="task-assigned-to">Assigned To</label></div>';
        echo '<div class="floating-label-group"><input type="text" name="task_related" id="task-related" placeholder=" "><label for="task-related">Related Lead/Deal</label></div>';
        echo '<div class="floating-label-group"><select name="task_status" id="task-status"><option value="open">Open</option><option value="completed">Completed</option></select><label for="task-status">Status</label></div>';
        echo '<div class="floating-label-group"><textarea name="task_notes" id="task-notes" rows="3" placeholder=" "></textarea><label for="task-notes">Notes</label></div>';
        echo '<div class="btn-row" style="margin-top:18px;">';
        echo '<button type="submit" class="crm-btn crm-btn-primary" id="save-task-btn" style="font-size:15px;padding:7px 22px;">Save Task</button>';
        echo '<button type="button" class="crm-btn" id="cancel-task-btn" style="font-size:15px;padding:7px 22px;background:#eee;color:#333;">Cancel</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        // Bulk Edit Modal
        echo '<div id="crm-bulk-edit-modal" class="crm-modal-bg" style="display:none;position:fixed;z-index:9999;top:0;left:0;width:100vw;height:100vh;background:rgba(30,30,30,0.18);backdrop-filter:blur(2px);">';
        echo '<div class="crm-modal-panel" style="max-width:340px;margin:80px auto;padding:28px 22px 18px 22px;border-radius:16px;background:rgba(255,255,255,0.92);box-shadow:0 4px 24px 0 rgba(31,38,135,0.13);font-family:Inter,sans-serif;">';
        echo '<div style="font-size:1.15rem;font-weight:600;margin-bottom:12px;">Bulk Edit Tasks</div>';
        // Assignee dropdown
        echo '<div class="floating-label-group" style="margin-bottom:14px;">';
        echo '<select id="bulk-edit-assignee" style="width:100%;font-size:13px;padding:7px 10px;border-radius:8px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.7);">';
        echo '<option value="">No Change (Assignee)</option>';
        foreach ($users as $user) {
            echo '<option value="'.esc_attr($user->display_name).'">'.esc_html($user->display_name).'</option>';
        }
        echo '</select>';
        echo '<label style="font-size:12px;">Assignee</label>';
        echo '</div>';
        // Type dropdown
        echo '<div class="floating-label-group" style="margin-bottom:18px;">';
        echo '<select id="bulk-edit-type" style="width:100%;font-size:13px;padding:7px 10px;border-radius:8px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.7);">';
        echo '<option value="">No Change (Type)</option>';
        foreach ([
            'call' => 'Call',
            'meeting' => 'Meeting',
            'followup' => 'Follow-up',
            'email' => 'Email',
            'other' => 'Other',
        ] as $key => $label) {
            echo '<option value="'.esc_attr($key).'">'.esc_html($label).'</option>';
        }
        echo '</select>';
        echo '<label style="font-size:12px;">Type</label>';
        echo '</div>';
        // Due Date input
        echo '<div class="floating-label-group" style="margin-bottom:14px;">';
        echo '<input type="date" id="bulk-edit-due-date" style="width:100%;font-size:13px;padding:7px 10px;border-radius:8px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.7);">';
        echo '<label style="font-size:12px;">Due Date (No Change if empty)</label>';
        echo '</div>';
        // Status dropdown
        echo '<div class="floating-label-group" style="margin-bottom:18px;">';
        echo '<select id="bulk-edit-status" style="width:100%;font-size:13px;padding:7px 10px;border-radius:8px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.7);">';
        echo '<option value="">No Change (Status)</option>';
        echo '<option value="open">Open</option>';
        echo '<option value="completed">Completed</option>';
        echo '</select>';
        echo '<label style="font-size:12px;">Status</label>';
        echo '</div>';
        echo '<div style="display:flex;gap:12px;justify-content:flex-end;">';
        echo '<button type="button" class="crm-btn" id="bulk-edit-cancel" style="font-size:13px;padding:5px 16px;background:#eee;color:#333;">Cancel</button>';
        echo '<button type="button" class="crm-btn crm-btn-primary" id="bulk-edit-apply" style="font-size:13px;padding:5px 16px;">Apply to Selected</button>';
        echo '</div>';
        echo '</div></div>';
        echo '</div>';
    }
}

new OPBCRM_Admin_Menus(); 