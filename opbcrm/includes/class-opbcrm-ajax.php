<?php
if (!defined('WPINC')) {
    die;
}

class OPBCRM_Ajax {

    public function __construct() {
        // AJAX action for adding a new lead
        add_action('wp_ajax_opbcrm_add_new_lead', array($this, 'add_new_lead'));
        
        // AJAX action for updating a lead's stage via drag-and-drop
        add_action('wp_ajax_opbcrm_update_lead_stage', array($this, 'update_lead_stage'));

        // AJAX handler for lead migration
        add_action('wp_ajax_opbcrm_migrate_old_leads', function() {
            check_ajax_referer('opbcrm_migrate_leads_nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error('You do not have permission.');
            }
            global $wpdb;
            $table = $wpdb->prefix . 'opbez_crm_leads';
            $results = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
            if (!$results) {
                wp_send_json_error('No old leads found.');
            }
            $migrated = 0;
            foreach ($results as $row) {
                $lead_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                $lead_email = $row['email'] ?? '';
                $lead_phone = $row['mobile'] ?? '';
                $lead_source = $row['source'] ?? '';
                $lead_tags = $row['tags'] ?? '';
                $lead_status = $row['status'] ?? '';
                $lead_comment = $row['notes'] ?? '';
                // Check for duplicate by email/phone
                $existing = get_posts([
                    'post_type' => 'opbez_lead',
                    'meta_query' => [
                        'relation' => 'OR',
                        [ 'key' => 'lead_email', 'value' => $lead_email, 'compare' => '=' ],
                        [ 'key' => 'lead_phone', 'value' => $lead_phone, 'compare' => '=' ]
                    ],
                    'fields' => 'ids',
                    'posts_per_page' => 1
                ]);
                if ($existing) continue;
                $post_id = wp_insert_post([
                    'post_title' => $lead_name ?: 'Lead',
                    'post_content' => $lead_comment,
                    'post_type' => 'opbez_lead',
                    'post_status' => 'publish',
                    'post_author' => $row['user_id'] ?? 1,
                ]);
                if ($post_id && !is_wp_error($post_id)) {
                    update_post_meta($post_id, 'lead_email', $lead_email);
                    update_post_meta($post_id, 'lead_phone', $lead_phone);
                    update_post_meta($post_id, 'lead_source', $lead_source);
                    update_post_meta($post_id, 'lead_tags', $lead_tags);
                    update_post_meta($post_id, 'lead_status', $lead_status);
                    update_post_meta($post_id, 'opbez_lead_author', $row['user_id'] ?? 1);
                    $migrated++;
                }
            }
            wp_send_json_success("Migration complete. $migrated leads migrated.");
        });

        // AJAX handler for saving (add/edit) CRM user (opbcrm)
        add_action('wp_ajax_opbcrm_save_user', function() {
            check_ajax_referer('opbcrm_save_user_nonce');
            if (empty($_SESSION['opbcrm_user_id']) || $_SESSION['opbcrm_role'] !== 'admin') {
                wp_send_json_error('No permission.', 403);
            }
            $user_id = intval($_POST['user_id'] ?? 0);
            $username = sanitize_text_field($_POST['username'] ?? '');
            $email = sanitize_email($_POST['email'] ?? '');
            $role = sanitize_text_field($_POST['role'] ?? 'agent');
            $password = $_POST['password'] ?? '';
            if (!$username || !$email) {
                wp_send_json_error('Username and email are required.');
            }
            global $wpdb;
            $table = $wpdb->prefix . 'opbcrm_users';
            if ($user_id) {
                // Edit user
                $update = [ 'username' => $username, 'email' => $email, 'role' => $role ];
                if ($password) {
                    $update['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $wpdb->update($table, $update, ['id' => $user_id]);
                wp_send_json_success(['id' => $user_id, 'message' => 'User updated successfully.']);
            } else {
                // Add user
                if (!$password) {
                    wp_send_json_error('Password is required for new users.');
                }
                $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE username = %s", $username));
                if ($exists) {
                    wp_send_json_error('Username already exists.');
                }
                $wpdb->insert($table, [
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'email' => $email,
                    'role' => $role,
                    'created_at' => current_time('mysql'),
                ]);
                $id = $wpdb->insert_id;
                wp_send_json_success(['id' => $id, 'message' => 'User added successfully.']);
            }
        });
        // AJAX handler for deleting CRM user (opbcrm)
        add_action('wp_ajax_opbcrm_delete_user', function() {
            check_ajax_referer('opbcrm_delete_user_nonce');
            if (empty($_SESSION['opbcrm_user_id']) || $_SESSION['opbcrm_role'] !== 'admin') {
                wp_send_json_error('No permission.', 403);
            }
            $user_id = intval($_POST['user_id'] ?? 0);
            if (!$user_id) wp_send_json_error('Missing user ID.');
            global $wpdb;
            $table = $wpdb->prefix . 'opbcrm_users';
            $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $user_id));
            if (!$user) wp_send_json_error('User not found.');
            if ($user->username === 'admin') wp_send_json_error('Cannot delete default admin.');
            $wpdb->delete($table, ['id' => $user_id]);
            wp_send_json_success(['message' => 'User deleted.']);
        });

        // Add substages to the AJAX response for lead stages
        add_action('wp_ajax_opbcrm_get_lead_stages', function() {
            if (!current_user_can('read')) {
                wp_send_json_error(['message' => 'Access denied']);
            }
            $stages = get_option('opbcrm_lead_stages', []);
            $sub_stages = get_option('opbcrm_lead_substages', []);
            $result = [];
            foreach ($stages as $stage) {
                $stage_name = is_array($stage) ? $stage['name'] : $stage;
                $result[] = [
                    'name' => $stage_name,
                    'sub_stages' => isset($sub_stages[$stage_name]) ? $sub_stages[$stage_name] : [],
                ];
            }
            wp_send_json_success(['stages' => $result]);
        });

        // AJAX handler for saving/creating a lead from the admin modal
        add_action('wp_ajax_opbcrm_save_lead', function() {
            check_ajax_referer('opbcrm_save_lead_nonce');
            if (!current_user_can('add_leads')) {
                wp_send_json_error(['message' => 'You do not have permission to add leads.'], 403);
            }
            $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
            $lead_name = sanitize_text_field($_POST['lead_name'] ?? '');
            $lead_phone = sanitize_text_field($_POST['lead_phone'] ?? '');
            $lead_email = sanitize_email($_POST['lead_email'] ?? '');
            $lead_whatsapp = sanitize_text_field($_POST['lead_whatsapp'] ?? '');
            $lead_stage = sanitize_text_field($_POST['lead_stage'] ?? '');
            $lead_sub_stage = sanitize_text_field($_POST['lead_sub_stage'] ?? '');
            $lead_source = sanitize_text_field($_POST['lead_source'] ?? '');
            $lead_tags = sanitize_text_field($_POST['lead_tags'] ?? '');
            $agent_id = intval($_POST['agent_id'] ?? 0);
            $lead_proposal = sanitize_text_field($_POST['lead_proposal'] ?? '');
            $lead_comment = sanitize_textarea_field($_POST['lead_comment'] ?? '');
            $lead_campaign = sanitize_text_field($_POST['lead_campaign'] ?? '');
            $required_substage_stages = ['hold_call_again', 'disqualified', 'follow_up'];
            if (in_array($lead_stage, $required_substage_stages) && empty($lead_sub_stage)) {
                wp_send_json_error(['message' => 'Please select a sub-stage (reason) for this stage.']);
            }
            // Collect custom fields (cf_*)
            $custom_fields = array();
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'cf_') === 0) {
                    $custom_fields[$key] = sanitize_text_field($value);
                }
            }
            if (!$lead_name) {
                wp_send_json_error(['message' => 'Name is required.']);
            }
            $lead_data = [
                'post_title' => $lead_name,
                'post_type' => 'opbez_lead',
                'post_status' => 'publish',
            ];
            if ($lead_id) {
                $lead_data['ID'] = $lead_id;
                $new_lead_id = wp_update_post($lead_data);
            } else {
                $lead_data['post_author'] = $agent_id ? $agent_id : get_current_user_id();
                $new_lead_id = wp_insert_post($lead_data);
            }
            if (is_wp_error($new_lead_id) || !$new_lead_id) {
                wp_send_json_error(['message' => 'Error saving lead.']);
            }
            // Save all fields to post meta
            update_post_meta($new_lead_id, 'lead_phone', $lead_phone);
            update_post_meta($new_lead_id, 'lead_email', $lead_email);
            update_post_meta($new_lead_id, 'lead_whatsapp', $lead_whatsapp);
            update_post_meta($new_lead_id, 'lead_status', $lead_stage);
            update_post_meta($new_lead_id, 'lead_sub_stage', $lead_sub_stage);
            update_post_meta($new_lead_id, 'lead_source', $lead_source);
            update_post_meta($new_lead_id, 'lead_tags', $lead_tags);
            update_post_meta($new_lead_id, 'lead_proposal', $lead_proposal);
            update_post_meta($new_lead_id, 'lead_comment', $lead_comment);
            update_post_meta($new_lead_id, 'lead_campaign', $lead_campaign);
            foreach ($custom_fields as $cf_key => $cf_value) {
                update_post_meta($new_lead_id, $cf_key, $cf_value);
            }
            if ($agent_id) {
                wp_update_post(['ID' => $new_lead_id, 'post_author' => $agent_id]);
                update_post_meta($new_lead_id, 'agent_id', $agent_id);
            }
            // Log changes to stage, substage, agent in activity/history (if changed)
            $old_stage = get_post_meta($new_lead_id, 'lead_status', true);
            $old_substage = get_post_meta($new_lead_id, 'lead_sub_stage', true);
            $old_agent = get_post_field('post_author', $new_lead_id);
            $activity_msgs = [];
            if ($lead_id) {
                if ($old_stage !== $lead_stage) {
                    $activity_msgs[] = 'Stage changed to: ' . $lead_stage;
                }
                if ($old_substage !== $lead_sub_stage) {
                    $activity_msgs[] = 'Substage changed to: ' . $lead_sub_stage;
                }
                if ($old_agent != $agent_id) {
                    $user = get_userdata($agent_id);
                    $activity_msgs[] = 'Agent changed to: ' . ($user ? $user->display_name : $agent_id);
                }
                if (!empty($activity_msgs)) {
                    $activity_content = implode(' | ', $activity_msgs);
                    if (function_exists('opbcrm_add_activity')) {
                        opbcrm_add_activity($new_lead_id, 'update', $activity_content);
                    }
                }
            }
            wp_send_json_success(['message' => 'Lead saved successfully.']);
        });

        // AJAX handler for getting CRM agents
        add_action('wp_ajax_opbcrm_get_crm_agents', function() {
            if (!current_user_can('add_leads')) {
                wp_send_json_error(['message' => 'No permission.'], 403);
            }
            $users = get_users(['role__in' => ['administrator', 'crm_manager', 'crm_agent']]);
            $result = [];
            foreach ($users as $user) {
                $result[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name . ' (' . $user->user_email . ')',
                ];
            }
            wp_send_json_success(['agents' => $result]);
        });

        // AJAX handler for getting a lead's data for editing
        add_action('wp_ajax_opbcrm_get_lead', function() {
            if (!current_user_can('edit_others_leads') && !current_user_can('edit_own_leads')) {
                wp_send_json_error(['message' => 'No permission.'], 403);
            }
            $lead_id = intval($_POST['lead_id'] ?? 0);
            if (!$lead_id) wp_send_json_error(['message' => 'Invalid lead ID.']);
            $post = get_post($lead_id);
            if (!$post || $post->post_type !== 'opbez_lead') wp_send_json_error(['message' => 'Lead not found.']);
            $lead = [
                'id' => $post->ID,
                'name' => $post->post_title,
                'phone' => get_post_meta($post->ID, 'lead_phone', true),
                'email' => get_post_meta($post->ID, 'lead_email', true),
                'stage' => get_post_meta($post->ID, 'lead_status', true),
                'agent_id' => $post->post_author,
                'proposal' => get_post_meta($post->ID, 'lead_proposal', true),
                'comment' => get_post_meta($post->ID, 'lead_comment', true),
                'campaign' => get_post_meta($post->ID, 'lead_campaign', true),
            ];
            wp_send_json_success(['lead' => $lead]);
        });

        // AJAX handler for getting substages for a stage (for Add/Edit Lead modal)
        add_action('wp_ajax_opbcrm_get_substages', function() {
            if (!current_user_can('add_leads')) {
                wp_send_json_error(['message' => 'No permission.'], 403);
            }
            $stage_id = sanitize_text_field($_POST['stage_id'] ?? '');
            if (!$stage_id) {
                wp_send_json_error(['message' => 'No stage specified.'], 400);
            }
            $sub_stages = get_option('opbcrm_lead_substages', []);
            $required_stages = ['Hold/Call Again', 'Disqualified', 'Follow Up']; // Stages that require substage
            $substages = isset($sub_stages[$stage_id]) ? $sub_stages[$stage_id] : [];
            $result = [];
            foreach ($substages as $i => $label) {
                $result[] = [
                    'id' => $i,
                    'label' => $label
                ];
            }
            $is_required = in_array($stage_id, $required_stages);
            wp_send_json_success(['substages' => $result, 'required' => $is_required]);
        });

        // AJAX handler for getting all published properties for the property picker
        add_action('wp_ajax_opbcrm_get_properties', function() {
            if (!current_user_can('add_leads')) {
                wp_send_json_error(['message' => 'No permission.'], 403);
            }
            $args = [
                'post_type' => 'property',
                'post_status' => 'publish',
                'posts_per_page' => 100,
                'orderby' => 'title',
                'order' => 'ASC',
                'fields' => 'ids',
            ];
            $property_ids = get_posts($args);
            $properties = [];
            foreach ($property_ids as $pid) {
                $properties[] = [
                    'id' => $pid,
                    'title' => get_the_title($pid)
                ];
            }
            wp_send_json_success(['properties' => $properties]);
        });

        // AJAX: Update agent comment
        add_action('wp_ajax_opbcrm_update_agent_comment', function() {
            if (!current_user_can('edit_agent_comment')) {
                wp_send_json_error(['message' => 'No permission.'], 403);
            }
            $lead_id = intval($_POST['lead_id'] ?? 0);
            $comment = sanitize_textarea_field($_POST['comment'] ?? '');
            if (!$lead_id) wp_send_json_error(['message' => 'Invalid lead ID.']);
            update_post_meta($lead_id, 'agent_comment', $comment);
            wp_send_json_success(['message' => 'Comment updated.']);
        });

        // AJAX: Add new Source
        add_action('wp_ajax_opbcrm_add_lead_source', function() {
            if (!current_user_can('edit_lead_source')) {
                wp_send_json_error(['message' => 'No permission.'], 403);
            }
            $source = sanitize_text_field($_POST['source'] ?? '');
            if (!$source) wp_send_json_error(['message' => 'No source provided.']);
            $sources = get_option('opbcrm_lead_sources', []);
            if (!in_array($source, $sources)) {
                $sources[] = $source;
                update_option('opbcrm_lead_sources', $sources);
            }
            wp_send_json_success(['message' => 'Source added.', 'sources' => $sources]);
        });

        // AJAX: Add new Property Type
        add_action('wp_ajax_opbcrm_add_property_type', function() {
            if (!current_user_can('edit_property_type')) {
                wp_send_json_error(['message' => 'No permission.'], 403);
            }
            $type = sanitize_text_field($_POST['property_type'] ?? '');
            if (!$type) wp_send_json_error(['message' => 'No type provided.']);
            $types = get_option('opbcrm_property_types', []);
            if (!in_array($type, $types)) {
                $types[] = $type;
                update_option('opbcrm_property_types', $types);
            }
            wp_send_json_success(['message' => 'Type added.', 'types' => $types]);
        });

        // AJAX: Get reporting dashboard data
        add_action('wp_ajax_opbcrm_get_reporting_data', [$this, 'get_reporting_data_callback']);
        add_action('wp_ajax_nopriv_opbcrm_get_reporting_data', [$this, 'get_reporting_data_callback']);

        // AJAX: Update single lead field (for Kanban inline editing)
        add_action('wp_ajax_opbcrm_update_lead_field', function() {
            $lead_id = intval($_POST['lead_id'] ?? 0);
            $field = sanitize_text_field($_POST['field'] ?? '');
            $value = sanitize_text_field($_POST['value'] ?? '');
            if (!$lead_id || !$field) wp_send_json_error(['message' => 'Missing data.']);
            $field_map = [
                'lead_project' => 'edit_project',
                'lead_source' => 'edit_source',
                // Add more fields as needed
            ];
            if (!isset($field_map[$field]) || !current_user_can($field_map[$field])) {
                wp_send_json_error(['message' => 'No permission.'], 403);
            }
            update_post_meta($lead_id, $field, $value);
            wp_send_json_success(['message' => 'Field updated.']);
        });

        // AJAX: Inline update user field (for Users table inline editing)
        add_action('wp_ajax_opbcrm_inline_update_user_field', function() {
            $user_id = intval($_POST['user_id'] ?? 0);
            $field = sanitize_text_field($_POST['field'] ?? '');
            $value = sanitize_text_field($_POST['value'] ?? '');
            if (!$user_id || !$field) wp_send_json_error(['message' => 'Missing data.']);
            $user = get_userdata($user_id);
            if (!$user) wp_send_json_error(['message' => 'User not found.']);
            $field_map = [
                'display_name' => 'edit_user_name',
                'user_email' => 'edit_user_email',
                'mobile' => 'edit_user_mobile',
                'role' => 'edit_user_role',
            ];
            if (!isset($field_map[$field]) || !current_user_can($field_map[$field])) {
                wp_send_json_error(['message' => 'No permission.'], 403);
            }
            if ($field === 'role') {
                $editable_roles = get_editable_roles();
                if (!isset($editable_roles[$value])) wp_send_json_error(['message' => 'Invalid role.']);
                foreach ($user->roles as $role) {
                    $user->remove_role($role);
                }
                $user->add_role($value);
                // Return new badge HTML
                $color = $value === 'administrator' ? '#007bff' : ($value === 'crm_manager' ? '#28a745' : '#ffc107');
                $icon = $value === 'administrator' ? 'fas fa-user-shield' : ($value === 'crm_manager' ? 'fas fa-user-tie' : 'fas fa-user');
                $badge = '<span class="crm-role-badge" style="background:'.$color.';color:#fff;padding:2px 8px;border-radius:12px;margin-right:4px;font-size:12px;"><i class="'.$icon.'" style="margin-right:4px;"></i>'.ucwords(str_replace('_',' ',$value)).'</span>';
                wp_send_json_success(['message' => 'Role updated.', 'role_badge_html' => $badge]);
            } elseif ($field === 'mobile') {
                update_user_meta($user_id, 'mobile', $value);
                wp_send_json_success(['message' => 'Mobile updated.']);
            } elseif ($field === 'display_name' || $field === 'user_email') {
                $args = ['ID' => $user_id, $field => $value];
                $result = wp_update_user($args);
                if (is_wp_error($result)) wp_send_json_error(['message' => 'Error updating user.']);
                wp_send_json_success(['message' => 'User updated.']);
            } else {
                wp_send_json_error(['message' => 'Invalid field.']);
            }
        });

        // AJAX: Delete custom field
        add_action('wp_ajax_opbcrm_delete_custom_field', function() {
            if (!current_user_can('delete_custom_field')) {
                wp_send_json_error(['message' => 'No permission.'], 403);
            }
            global $wpdb;
            $field_id = intval($_POST['field_id'] ?? 0);
            if (!$field_id) wp_send_json_error(['message' => 'Missing field ID.']);
            $table = $wpdb->prefix . 'opbcrm_custom_fields';
            $deleted = $wpdb->delete($table, ['id' => $field_id], ['%d']);
            if ($deleted) {
                wp_send_json_success(['message' => 'Field deleted.']);
            } else {
                wp_send_json_error(['message' => 'Delete failed.']);
            }
        });

        // AJAX: Inline update custom field (label, type, options)
        add_action('wp_ajax_opbcrm_inline_update_custom_field', function() {
            if (!current_user_can('edit_custom_field')) {
                wp_send_json_error(['message' => 'No permission.'], 403);
            }
            global $wpdb;
            $field_id = intval($_POST['field_id'] ?? 0);
            $field = sanitize_text_field($_POST['field'] ?? '');
            $value = isset($_POST['value']) ? $_POST['value'] : '';
            if (!$field_id || !$field) wp_send_json_error(['message' => 'Missing data.']);
            $table = $wpdb->prefix . 'opbcrm_custom_fields';
            $allowed = ['field_label','field_type','field_options'];
            if (!in_array($field, $allowed, true)) wp_send_json_error(['message' => 'Invalid field.']);
            if ($field === 'field_label') $value = sanitize_text_field($value);
            if ($field === 'field_type') $value = sanitize_text_field($value);
            if ($field === 'field_options') $value = sanitize_textarea_field($value);
            $updated = $wpdb->update($table, [$field => $value], ['id' => $field_id], ['%s'], ['%d']);
            if ($updated !== false) {
                wp_send_json_success(['message' => 'Field updated.']);
            } else {
                wp_send_json_error(['message' => 'Update failed.']);
            }
        });

        // AJAX: Add custom field (AJAX, returns row HTML)
        add_action('wp_ajax_opbcrm_add_custom_field_ajax', function() {
            if (!current_user_can('add_custom_field')) {
                wp_send_json_error(['message' => 'No permission.'], 403);
            }
            check_ajax_referer('opbcrm_add_custom_field_nonce');
            global $wpdb;
            $label = sanitize_text_field($_POST['field_label'] ?? '');
            $type = sanitize_text_field($_POST['field_type'] ?? '');
            $options = ($type === 'select') ? sanitize_textarea_field($_POST['field_options'] ?? '') : '';
            $name = sanitize_title($label);
            $name = str_replace('-', '_', $name);
            if (!$label || !$type || !$name) {
                wp_send_json_error(['message' => 'Label and type are required.']);
            }
            $table = $wpdb->prefix . 'opbcrm_custom_fields';
            $inserted = $wpdb->insert($table, [
                'field_label' => $label,
                'field_name' => 'cf_' . $name,
                'field_type' => $type,
                'field_options' => $options,
            ], ['%s', '%s', '%s', '%s']);
            if ($inserted) {
                $id = $wpdb->insert_id;
                // Build row HTML (same as in admin table)
                ob_start();
                echo '<tr data-field-id="' . esc_attr($id) . '">';
                echo '<td>';
                echo '<span class="cf-label-view">' . esc_html($label) . '</span>';
                if (current_user_can('edit_custom_field')) {
                    echo '<button class="crm-btn crm-btn-icon crm-edit-field-btn" title="Edit" style="background:rgba(40,120,220,0.10);color:#227;padding:4px 8px;border-radius:7px;font-size:15px;margin-left:6px;"><i class="fas fa-pen"></i></button>';
                }
                echo '<div class="cf-label-edit-row" style="display:none;gap:6px;align-items:center;">';
                echo '<input type="text" class="cf-edit-label-input crm-input" value="' . esc_attr($label) . '" style="width:110px;font-size:13px;">';
                echo '<button class="crm-btn crm-btn-xs cf-save-label-btn" style="padding:2px 10px;font-size:12px;">Save</button>';
                echo '<button class="crm-btn crm-btn-xs cf-cancel-label-btn" style="padding:2px 10px;font-size:12px;background:#eee;color:#333;">Cancel</button>';
                echo '</div>';
                echo '</td>';
                echo '<td>' . esc_html('cf_' . $name) . '</td>';
                echo '<td>';
                echo '<span class="cf-type-view">' . esc_html(ucfirst($type)) . '</span>';
                if (current_user_can('edit_custom_field')) {
                    echo '<button class="crm-btn crm-btn-icon crm-edit-type-btn" title="Edit Type" style="background:rgba(40,120,220,0.10);color:#227;padding:4px 8px;border-radius:7px;font-size:15px;margin-left:6px;"><i class="fas fa-pen"></i></button>';
                }
                echo '<div class="cf-type-edit-row" style="display:none;gap:6px;align-items:center;">';
                echo '<select class="cf-edit-type-select crm-input" style="font-size:13px;width:90px;">';
                foreach (["text","textarea","number","date","select"] as $t) {
                    $sel = $type === $t ? 'selected' : '';
                    echo '<option value="' . esc_attr($t) . '" ' . $sel . '>' . ucfirst($t) . '</option>';
                }
                echo '</select>';
                echo '<button class="crm-btn crm-btn-xs cf-save-type-btn" style="padding:2px 10px;font-size:12px;">Save</button>';
                echo '<button class="crm-btn crm-btn-xs cf-cancel-type-btn" style="padding:2px 10px;font-size:12px;background:#eee;color:#333;">Cancel</button>';
                echo '</div>';
                echo '</td>';
                echo '<td>';
                if ($type === 'select') {
                    echo '<span class="cf-options-view">' . esc_html($options) . '</span>';
                    if (current_user_can('edit_custom_field')) {
                        echo '<button class="crm-btn crm-btn-icon crm-edit-options-btn" title="Edit Options" style="background:rgba(40,120,220,0.10);color:#227;padding:4px 8px;border-radius:7px;font-size:15px;margin-left:6px;"><i class="fas fa-pen"></i></button>';
                    }
                    echo '<div class="cf-options-edit-row" style="display:none;gap:6px;align-items:center;">';
                    echo '<textarea class="cf-edit-options-input crm-input" style="font-size:13px;width:120px;min-height:28px;">' . esc_textarea($options) . '</textarea>';
                    echo '<button class="crm-btn crm-btn-xs cf-save-options-btn" style="padding:2px 10px;font-size:12px;">Save</button>';
                    echo '<button class="crm-btn crm-btn-xs cf-cancel-options-btn" style="padding:2px 10px;font-size:12px;background:#eee;color:#333;">Cancel</button>';
                    echo '</div>';
                } else {
                    echo '<span class="cf-options-view" style="color:#aaa;font-size:12px;">-</span>';
                }
                echo '</td>';
                if (current_user_can('delete_custom_field')) {
                    echo '<td><button class="crm-btn crm-btn-icon crm-delete-field-btn" title="Delete" style="background:rgba(220,40,40,0.12);color:#c22;padding:4px 8px;border-radius:7px;font-size:15px;"><i class="fas fa-trash"></i></button></td>';
                }
                echo '</tr>';
                $row_html = ob_get_clean();
                wp_send_json_success(['row_html' => $row_html]);
            } else {
                wp_send_json_error(['message' => 'Failed to add custom field.']);
            }
        });

        // Hooks for Campaign Management
        add_action('wp_ajax_opbcrm_add_campaign', [$this, 'add_campaign_callback']);
        add_action('wp_ajax_opbcrm_rename_campaign', [$this, 'rename_campaign_callback']);
        add_action('wp_ajax_opbcrm_archive_campaign', [$this, 'archive_campaign_callback']);

        // Bulk assign campaign to leads
        add_action('wp_ajax_opbcrm_bulk_assign_campaign', function() {
            check_ajax_referer('opbcrm_dashboard_nonce', 'nonce');
            if (!current_user_can('edit_campaigns')) {
                wp_send_json_error(['message' => 'No permission.'], 403);
            }
            $lead_ids = isset($_POST['lead_ids']) ? (array)$_POST['lead_ids'] : [];
            $campaign = isset($_POST['campaign']) ? sanitize_text_field($_POST['campaign']) : '';
            if (empty($lead_ids) || !$campaign) {
                wp_send_json_error(['message' => 'Missing data.']);
            }
            foreach ($lead_ids as $lead_id) {
                update_post_meta((int)$lead_id, 'lead_campaign', $campaign);
            }
            wp_send_json_success(['message' => 'Campaign assigned.']);
        });

        // Update campaign spend
        add_action('wp_ajax_opbcrm_update_campaign_spend', function() {
            check_ajax_referer('opbcrm_dashboard_nonce', 'nonce');
            if (!current_user_can('edit_campaigns')) {
                wp_send_json_error(['message' => 'No permission.'], 403);
            }
            $campaign = isset($_POST['campaign_name']) ? sanitize_text_field($_POST['campaign_name']) : '';
            $spend = isset($_POST['spend']) ? floatval($_POST['spend']) : 0;
            if (!$campaign) {
                wp_send_json_error(['message' => 'Missing campaign.']);
            }
            $managed_campaigns = get_option('opbcrm_managed_campaigns', []);
            // Store as array: [ 'Campaign Name' => [ 'spend' => X ] ]
            if (!is_array($managed_campaigns)) $managed_campaigns = [];
            if (!isset($managed_campaigns[$campaign]) || !is_array($managed_campaigns[$campaign])) {
                $managed_campaigns[$campaign] = [];
            }
            $managed_campaigns[$campaign]['spend'] = $spend;
            update_option('opbcrm_managed_campaigns', $managed_campaigns);
            wp_send_json_success(['message' => 'Spend updated.']);
        });

        // Get all deals
        add_action('wp_ajax_opbcrm_get_deals', function() {
            check_ajax_referer('opbcrm_admin_nonce', 'nonce');
            if (!current_user_can('view_deals')) wp_send_json_error(['message'=>'No permission.']);
            $args = [
                'post_type' => 'opbcrm_deal',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids',
            ];
            $ids = get_posts($args);
            $deals = [];
            foreach ($ids as $id) {
                $deals[] = [
                    'id' => $id,
                    'title' => get_the_title($id),
                    'value' => get_post_meta($id,'deal_value',true),
                    'stage' => get_post_meta($id,'deal_stage',true),
                    'owner' => get_post_meta($id,'deal_owner',true),
                    'close_date' => get_post_meta($id,'deal_close_date',true),
                    'status' => get_post_meta($id,'deal_status',true),
                ];
            }
            wp_send_json_success($deals);
        });
        // Get one deal
        add_action('wp_ajax_opbcrm_get_deal', function() {
            check_ajax_referer('opbcrm_admin_nonce', 'nonce');
            if (!current_user_can('view_deals')) wp_send_json_error(['message'=>'No permission.']);
            $id = intval($_POST['id']??0);
            $post = get_post($id);
            if (!$post || $post->post_type!=='opbcrm_deal') wp_send_json_error(['message'=>'Not found.']);
            $deal = [
                'id' => $id,
                'title' => $post->post_title,
                'value' => get_post_meta($id,'deal_value',true),
                'stage' => get_post_meta($id,'deal_stage',true),
                'owner' => get_post_meta($id,'deal_owner',true),
                'close_date' => get_post_meta($id,'deal_close_date',true),
                'status' => get_post_meta($id,'deal_status',true),
                'notes' => get_post_meta($id,'deal_notes',true),
            ];
            wp_send_json_success($deal);
        });
        // Save (add/edit) deal
        add_action('wp_ajax_opbcrm_save_deal', function() {
            check_ajax_referer('opbcrm_admin_nonce', 'nonce');
            $id = intval($_POST['deal_id']??0);
            $title = sanitize_text_field($_POST['deal_title']??'');
            $value = floatval($_POST['deal_value']??0);
            $stage = sanitize_text_field($_POST['deal_stage']??'');
            $owner = sanitize_text_field($_POST['deal_owner']??'');
            $close_date = sanitize_text_field($_POST['deal_close_date']??'');
            $status = sanitize_text_field($_POST['deal_status']??'');
            $notes = sanitize_textarea_field($_POST['deal_notes']??'');
            if (!$title) wp_send_json_error(['message'=>'Title required.']);
            if ($id) {
                if (!current_user_can('edit_deals')) wp_send_json_error(['message'=>'No permission.']);
                wp_update_post(['ID'=>$id,'post_title'=>$title]);
            } else {
                if (!current_user_can('add_deals')) wp_send_json_error(['message'=>'No permission.']);
                $id = wp_insert_post(['post_type'=>'opbcrm_deal','post_status'=>'publish','post_title'=>$title]);
            }
            update_post_meta($id,'deal_value',$value);
            update_post_meta($id,'deal_stage',$stage);
            update_post_meta($id,'deal_owner',$owner);
            update_post_meta($id,'deal_close_date',$close_date);
            update_post_meta($id,'deal_status',$status);
            update_post_meta($id,'deal_notes',$notes);
            wp_send_json_success(['id'=>$id,'message'=>'Deal saved.']);
        });
        // Delete deal
        add_action('wp_ajax_opbcrm_delete_deal', function() {
            check_ajax_referer('opbcrm_admin_nonce', 'nonce');
            if (!current_user_can('delete_deals')) wp_send_json_error(['message'=>'No permission.']);
            $id = intval($_POST['id']??0);
            $post = get_post($id);
            if (!$post || $post->post_type!=='opbcrm_deal') wp_send_json_error(['message'=>'Not found.']);
            wp_trash_post($id);
            wp_send_json_success(['message'=>'Deal deleted.']);
        });
        // Update deal stage
        add_action('wp_ajax_opbcrm_update_deal_stage', function() {
            check_ajax_referer('opbcrm_admin_nonce', 'nonce');
            if (!current_user_can('edit_deal_stage')) wp_send_json_error(['message'=>'No permission.']);
            $id = intval($_POST['id']??0);
            $stage = sanitize_text_field($_POST['stage']??'');
            $post = get_post($id);
            if (!$post || $post->post_type!=='opbcrm_deal') wp_send_json_error(['message'=>'Not found.']);
            update_post_meta($id,'deal_stage',$stage);
            wp_send_json_success(['message'=>'Stage updated.']);
        });
        // Deal analytics dashboard
        add_action('wp_ajax_opbcrm_get_deal_analytics', function() {
            check_ajax_referer('opbcrm_admin_nonce', 'nonce');
            if (!current_user_can('view_deals')) wp_send_json_error(['message'=>'No permission.']);
            $start = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : '';
            $end = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : '';
            $args = [
                'post_type' => 'opbcrm_deal',
                'post_status' => 'publish',
                'posts_per_page' => -1,
            ];
            $deals = get_posts($args);
            $total = 0; $pipeline = 0; $by_stage = []; $by_agent = []; $won_over_time = [];
            $start_ts = $start ? strtotime($start) : 0;
            $end_ts = $end ? strtotime($end) : 9999999999;
            foreach ($deals as $deal) {
                $stage = get_post_meta($deal->ID, 'deal_stage', true) ?: 'new';
                $value = floatval(get_post_meta($deal->ID, 'deal_value', true));
                $agent = get_post_meta($deal->ID, 'deal_owner', true) ?: 'Unassigned';
                $status = get_post_meta($deal->ID, 'deal_status', true) ?: '';
                $created = strtotime($deal->post_date);
                if (($start_ts && $created < $start_ts) || ($end_ts && $created > $end_ts)) continue;
                $total++;
                $pipeline += $value;
                $by_stage[$stage] = ($by_stage[$stage]??0)+1;
                $by_agent[$agent] = ($by_agent[$agent]??0)+1;
                if ($stage === 'won' || $status === 'won') {
                    $date = date('Y-m-d', $created);
                    $won_over_time[$date] = ($won_over_time[$date]??0)+1;
                }
            }
            ksort($won_over_time);
            wp_send_json_success([
                'total' => $total,
                'pipeline' => $pipeline,
                'by_stage' => $by_stage,
                'by_agent' => $by_agent,
                'won_over_time' => $won_over_time,
            ]);
        });
        // Get all tasks
        add_action('wp_ajax_opbcrm_get_tasks', function() {
            check_ajax_referer('opbcrm_admin_nonce', 'nonce');
            if (!current_user_can('view_tasks')) wp_send_json_error(['message'=>'No permission.']);
            $args = [
                'post_type' => 'opbcrm_task',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids',
            ];
            $ids = get_posts($args);
            $tasks = [];
            foreach ($ids as $id) {
                $tasks[] = [
                    'id' => $id,
                    'title' => get_the_title($id),
                    'type' => get_post_meta($id,'task_type',true),
                    'due_date' => get_post_meta($id,'task_due_date',true),
                    'assigned_to' => get_post_meta($id,'task_assigned_to',true),
                    'related' => get_post_meta($id,'task_related',true),
                    'status' => get_post_meta($id,'task_status',true),
                ];
            }
            wp_send_json_success($tasks);
        });
        // Get one task
        add_action('wp_ajax_opbcrm_get_task', function() {
            check_ajax_referer('opbcrm_admin_nonce', 'nonce');
            if (!current_user_can('view_tasks')) wp_send_json_error(['message'=>'No permission.']);
            $id = intval($_POST['id']??0);
            $post = get_post($id);
            if (!$post || $post->post_type!=='opbcrm_task') wp_send_json_error(['message'=>'Not found.']);
            $task = [
                'id' => $id,
                'title' => $post->post_title,
                'type' => get_post_meta($id,'task_type',true),
                'due_date' => get_post_meta($id,'task_due_date',true),
                'assigned_to' => get_post_meta($id,'task_assigned_to',true),
                'related' => get_post_meta($id,'task_related',true),
                'status' => get_post_meta($id,'task_status',true),
                'notes' => get_post_meta($id,'task_notes',true),
            ];
            wp_send_json_success($task);
        });
        // Save (add/edit) task
        add_action('wp_ajax_opbcrm_save_task', function() {
            check_ajax_referer('opbcrm_admin_nonce', 'nonce');
            $id = intval($_POST['task_id']??0);
            $title = sanitize_text_field($_POST['task_title']??'');
            $type = sanitize_text_field($_POST['task_type']??'');
            $due_date = sanitize_text_field($_POST['task_due_date']??'');
            $assigned_to = sanitize_text_field($_POST['task_assigned_to']??'');
            $related = sanitize_text_field($_POST['task_related']??'');
            $status = sanitize_text_field($_POST['task_status']??'');
            $notes = sanitize_textarea_field($_POST['task_notes']??'');
            if (!$title) wp_send_json_error(['message'=>'Title required.']);
            if ($id) {
                if (!current_user_can('edit_tasks')) wp_send_json_error(['message'=>'No permission.']);
                wp_update_post(['ID'=>$id,'post_title'=>$title]);
            } else {
                if (!current_user_can('add_tasks')) wp_send_json_error(['message'=>'No permission.']);
                $id = wp_insert_post(['post_type'=>'opbcrm_task','post_status'=>'publish','post_title'=>$title]);
            }
            update_post_meta($id,'task_type',$type);
            update_post_meta($id,'task_due_date',$due_date);
            update_post_meta($id,'task_assigned_to',$assigned_to);
            update_post_meta($id,'task_related',$related);
            update_post_meta($id,'task_status',$status);
            update_post_meta($id,'task_notes',$notes);
            wp_send_json_success(['id'=>$id,'message'=>'Task saved.']);
        });
        // Delete task
        add_action('wp_ajax_opbcrm_delete_task', function() {
            check_ajax_referer('opbcrm_admin_nonce', 'nonce');
            if (!current_user_can('delete_tasks')) wp_send_json_error(['message'=>'No permission.']);
            $id = intval($_POST['id']??0);
            $post = get_post($id);
            if (!$post || $post->post_type!=='opbcrm_task') wp_send_json_error(['message'=>'Not found.']);
            wp_trash_post($id);
            wp_send_json_success(['message'=>'Task deleted.']);
        });
        // Mark task complete
        add_action('wp_ajax_opbcrm_complete_task', function() {
            check_ajax_referer('opbcrm_admin_nonce', 'nonce');
            if (!current_user_can('edit_tasks')) wp_send_json_error(['message'=>'No permission.']);
            $id = intval($_POST['id']??0);
            $post = get_post($id);
            if (!$post || $post->post_type!=='opbcrm_task') wp_send_json_error(['message'=>'Not found.']);
            update_post_meta($id,'task_status','completed');
            wp_send_json_success(['message'=>'Task marked complete.']);
        });
        // Bulk edit task
        add_action('wp_ajax_opbcrm_bulk_edit_task', function() {
            check_ajax_referer('opbcrm_admin_nonce', 'nonce');
            if (!current_user_can('edit_tasks')) wp_send_json_error(['message'=>'No permission.']);
            $id = intval($_POST['id']??0);
            $post = get_post($id);
            if (!$post || $post->post_type!=='opbcrm_task') wp_send_json_error(['message'=>'Not found.']);
            if (isset($_POST['assignee']) && $_POST['assignee']!=='') {
                update_post_meta($id,'task_assigned_to',sanitize_text_field($_POST['assignee']));
            }
            if (isset($_POST['type']) && $_POST['type']!=='') {
                update_post_meta($id,'task_type',sanitize_text_field($_POST['type']));
            }
            if (isset($_POST['due_date']) && $_POST['due_date']!=='') {
                update_post_meta($id,'task_due_date',sanitize_text_field($_POST['due_date']));
            }
            if (isset($_POST['status']) && $_POST['status']!=='') {
                update_post_meta($id,'task_status',sanitize_text_field($_POST['status']));
            }
            wp_send_json_success(['message'=>'Task updated.']);
        });
        // Get task analytics
        add_action('wp_ajax_opbcrm_get_task_analytics', function() {
            check_ajax_referer('opbcrm_admin_nonce', 'nonce');
            if (!current_user_can('view_tasks')) wp_send_json_error(['message'=>'No permission.']);
            $start = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : '';
            $end = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : '';
            $args = [
                'post_type' => 'opbcrm_task',
                'post_status' => 'publish',
                'posts_per_page' => -1,
            ];
            $tasks = get_posts($args);
            $total = 0; $overdue = 0; $by_status = []; $by_type = []; $by_assignee = []; $completed_over_time = [];
            $now = strtotime(date('Y-m-d'));
            $start_ts = $start ? strtotime($start) : 0;
            $end_ts = $end ? strtotime($end) : 9999999999;
            foreach ($tasks as $task) {
                $due = get_post_meta($task->ID, 'task_due_date', true);
                $due_ts = $due ? strtotime($due) : 0;
                $status = get_post_meta($task->ID, 'task_status', true) ?: 'open';
                $type = get_post_meta($task->ID, 'task_type', true) ?: 'other';
                $assignee = get_post_meta($task->ID, 'task_assigned_to', true) ?: 'Unassigned';
                $completed = ($status === 'completed');
                $created = strtotime($task->post_date);
                // Date range filter: created or due in range
                if (($start_ts && $created < $start_ts) || ($end_ts && $created > $end_ts)) continue;
                $total++;
                if ($due && $status !== 'completed' && $due_ts < $now) $overdue++;
                $by_status[$status] = ($by_status[$status]??0)+1;
                $by_type[$type] = ($by_type[$type]??0)+1;
                $by_assignee[$assignee] = ($by_assignee[$assignee]??0)+1;
                if ($completed) {
                    $date = date('Y-m-d', $created);
                    $completed_over_time[$date] = ($completed_over_time[$date]??0)+1;
                }
            }
            ksort($completed_over_time);
            wp_send_json_success([
                'total' => $total,
                'overdue' => $overdue,
            'total_leads' => $total_leads,
            'stage' => [
                'labels' => $stage_labels,
                'data' => $stage_counts
            ],
            'source' => [
                'labels' => $source_labels,
                'data' => $source_data
            ],
            'project' => [
                'labels' => $project_labels,
                'data' => $project_data
            ],
            'campaign' => [
                'labels' => $campaign_labels,
                'data' => $campaign_data
            ],
            'campaign_conversion' => $campaign_conversion,
            'agent' => [
                'labels' => $agent_labels,
                'data' => $agent_counts
            ],
            'campaign_source_breakdown' => $campaign_source_breakdown,
            'campaign_time_series' => $campaign_time_series,
            'campaign_roi' => $campaign_roi,
        ]);

        add_action('rest_api_init', function() {
            register_rest_route('opbcrm/v1', '/fb-lead', array(
                'methods' => ['POST', 'GET'],
                'callback' => function($request) {
                    // Facebook Webhook Verification (GET)
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        $verify_token = 'Kasraopb@2026100M$%AVA'; // Your verify token
                        $mode = isset($_GET['hub_mode']) ? $_GET['hub_mode'] : (isset($_GET['hub.mode']) ? $_GET['hub.mode'] : '');
                        $token = isset($_GET['hub_verify_token']) ? $_GET['hub_verify_token'] : (isset($_GET['hub.verify_token']) ? $_GET['hub.verify_token'] : '');
                        $challenge = isset($_GET['hub_challenge']) ? $_GET['hub_challenge'] : (isset($_GET['hub.challenge']) ? $_GET['hub.challenge'] : '');
                        if ($mode === 'subscribe' && $token === $verify_token) {
                            header('Content-Type: text/plain');
                            echo $challenge;
                            exit;
                        } else {
                            return new WP_REST_Response(['message'=>'Invalid verification'], 403);
                        }
                    }
                    // ... existing POST logic ...
                    $headers = getallheaders();
                    $fb_sig = isset($headers['X-Hub-Signature']) ? $headers['X-Hub-Signature'] : '';
                    // TODO: Add your Facebook App Secret and verify signature if needed
                    // $app_secret = 'YOUR_FB_APP_SECRET';
                    // ... signature verification logic ...
                    $body = $request->get_body();
                    $data = json_decode($body, true);
                    if (!$data || !isset($data['entry'][0]['changes'][0]['value']['leadgen_id'])) {
                        return new WP_REST_Response(['message'=>'Invalid payload'], 400);
                    }
                    $lead_data = $data['entry'][0]['changes'][0]['value'];
                    // Map Facebook fields to CRM fields
                    $fb_fields = [];
                    foreach ($lead_data['field_data'] as $field) {
                        $fb_fields[$field['name']] = $field['values'][0];
                    }
                    // Example mapping (customize as needed)
                    $lead_name = $fb_fields['full_name'] ?? ($fb_fields['first_name'] ?? '') . ' ' . ($fb_fields['last_name'] ?? '');
                    $lead_email = $fb_fields['email'] ?? '';
                    $lead_phone = $fb_fields['phone_number'] ?? '';
                    $lead_source = 'Facebook Lead Ad';
                    // Create new lead post
                    $post_id = wp_insert_post([
                        'post_type' => 'opbez_lead',
                        'post_status' => 'publish',
                        'post_title' => $lead_name,
                        'post_content' => '',
                    ]);
                    if ($post_id && !is_wp_error($post_id)) {
                        update_post_meta($post_id, 'lead_email', $lead_email);
                        update_post_meta($post_id, 'lead_phone', $lead_phone);
                        update_post_meta($post_id, 'lead_source', $lead_source);
                        update_post_meta($post_id, 'fb_lead_id', $lead_data['leadgen_id']);
                        // Log import
                        error_log('FB Lead imported: '.$post_id.' ('.$lead_name.')');
                        return new WP_REST_Response(['message'=>'Lead imported','lead_id'=>$post_id], 200);
                    } else {
                        return new WP_REST_Response(['message'=>'Failed to create lead'], 500);
                    }
                },
                'permission_callback' => '__return_true', // Public endpoint for webhook
            ));
        });
    }
}

new OPBCRM_Ajax(); 