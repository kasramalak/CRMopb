<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class OPBCRM_Settings {

    public function __construct() {
        // Enqueue scripts and styles for the admin area
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Register AJAX action for saving lead stages
        add_action('wp_ajax_opbcrm_save_lead_stages', array($this, 'save_lead_stages'));
        
        // Register AJAX action for saving permissions
        add_action('wp_ajax_opbcrm_save_permissions', array($this, 'save_permissions'));
        // Register AJAX action for adding a new role
        add_action('wp_ajax_opbcrm_add_role', array($this, 'add_role'));
        // Add sub-stages support to lead stages settings
        add_action('wp_ajax_opbcrm_save_lead_substages', array($this, 'save_lead_substages'));

        // On plugin init, set default sub-stages if not set
        add_action('init', function() {
            if (!get_option('opbcrm_lead_substages')) {
                update_option('opbcrm_lead_substages', \opbcrm_Settings::get_default_lead_substages());
            }
        });
    }

    /**
     * Enqueue admin-specific scripts and styles.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_assets($hook) {
        $screen_id = get_current_screen()->id;

        // Load assets for Lead Stages page
        if ($screen_id === 'opbcrm_page_opbcrm-lead-stages') {
            wp_enqueue_style(
                'opbcrm-admin-stages-style',
                OPBCRM_PLUGIN_URL . 'assets/css/admin-stages.css',
                array(),
                OPBCRM_VERSION
            );
            wp_enqueue_script(
                'opbcrm-admin-stages-script',
                OPBCRM_PLUGIN_URL . 'assets/js/admin-stages.js',
                array('jquery', 'sortable-js'),
                OPBCRM_VERSION,
                true
            );
            wp_register_script(
                'sortable-js',
                'https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js',
                array(), '1.15.0', true
            );
        }

        // Load assets for Permissions page
        if ($screen_id === 'opbcrm_page_opbcrm-permissions') {
             wp_enqueue_script(
                'opbcrm-admin-permissions-script',
                OPBCRM_PLUGIN_URL . 'assets/js/admin-permissions.js',
                array('jquery'),
                OPBCRM_VERSION,
                true
            );
        }

        // Enqueue intl-tel-input for all admin pages where modals/forms are used
        wp_enqueue_style(
            'intl-tel-input',
            OPBCRM_PLUGIN_URL . 'assets/css/intlTelInput.min.css',
            array(),
            '18.6.1'
        );
        wp_enqueue_script(
            'intl-tel-input',
            OPBCRM_PLUGIN_URL . 'assets/js/intlTelInput.min.js',
            array('jquery'),
            '18.6.1',
            true
        );
        // Ensure flag images are accessible (handled by CSS path)

        wp_enqueue_style(
            'opbcrm-modern',
            OPBCRM_PLUGIN_URL . 'assets/css/opbcrm-modern.css',
            array(),
            OPBCRM_VERSION
        );
        wp_enqueue_script(
            'opbcrm-offcanvas',
            OPBCRM_PLUGIN_URL . 'assets/js/opbcrm-offcanvas.js',
            array('jquery'),
            OPBCRM_VERSION,
            true
        );
        wp_localize_script('opbcrm-offcanvas', 'ajaxurl', admin_url('admin-ajax.php'));
    }

    /**
     * AJAX handler for saving permissions.
     */
    public function save_permissions() {
        if (!check_ajax_referer('opbcrm_save_permissions_nonce', '_wpnonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
            return;
        }

        $all_crm_caps = OPBCRM_Roles::get_all_crm_capabilities();
        $roles_data = isset($_POST['role_caps']) ? $_POST['role_caps'] : [];

        global $wp_roles;
        $editable_roles = apply_filters('editable_roles', $wp_roles->roles);

        foreach ($editable_roles as $role_key => $role_details) {
            if ($role_key === 'administrator') {
                continue; // Do not modify admin role from here
            }

            $role_obj = get_role($role_key);
            if (!$role_obj) {
                continue;
            }

            foreach ($all_crm_caps as $cap_key => $cap_name) {
                // If the checkbox was checked for this role/cap pair, add the cap.
                if (isset($roles_data[$role_key][$cap_key])) {
                    $role_obj->add_cap($cap_key);
                } else {
                    // If the checkbox was not checked, remove the cap.
                    $role_obj->remove_cap($cap_key);
                }
            }
        }

        wp_send_json_success(['message' => 'Permissions saved successfully.']);
    }

    /**
     * AJAX handler for saving lead stages.
     */
    public function save_lead_stages() {
        if (!check_ajax_referer('opbcrm_save_stages_nonce', '_wpnonce', false) || !current_user_can('manage_crm_settings')) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
            return;
        }

        $stages_json = isset($_POST['stages']) ? stripslashes($_POST['stages']) : '';
        if (empty($stages_json)) {
            wp_send_json_error(['message' => 'No stages data received.'], 400);
            return;
        }
        
        $stages_data = json_decode($stages_json, true);
        if (!is_array($stages_data) || !isset($stages_data['initial'])) {
             wp_send_json_error(['message' => 'Invalid data format.'], 400);
            return;
        }

        $sanitized_stages = [];
        $allowed_groups = ['initial', 'additional', 'success', 'failed'];
        foreach ($stages_data as $group => $stages) {
            if (!in_array($group, $allowed_groups) || !is_array($stages)) continue;
            
            $sanitized_stages[$group] = [];
            foreach($stages as $stage) {
                if (empty($stage['id']) || empty($stage['label'])) continue;
                $sanitized_stages[$group][] = [
                    'id'    => sanitize_text_field($stage['id']),
                    'label' => sanitize_text_field($stage['label']),
                    'color' => sanitize_hex_color($stage['color']),
                ];
            }
        }
        
        update_option('opbcrm_lead_stages', $sanitized_stages);

        wp_send_json_success(['message' => 'Stages saved successfully.']);
    }

    /**
     * AJAX handler for adding a new custom role.
     */
    public function add_role() {
        if (!check_ajax_referer('opbcrm_add_role_nonce', '_wpnonce_add_role', false) || !current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
        }
        $role_key = isset($_POST['new_role_key']) ? sanitize_key($_POST['new_role_key']) : '';
        $role_name = isset($_POST['new_role_name']) ? sanitize_text_field($_POST['new_role_name']) : '';
        if (empty($role_key) || empty($role_name)) {
            wp_send_json_error(['message' => 'Both fields are required.'], 400);
        }
        if (get_role($role_key)) {
            wp_send_json_error(['message' => 'This role key already exists.'], 400);
        }
        $reserved = ['administrator', 'subscriber', 'editor', 'author', 'contributor'];
        if (in_array($role_key, $reserved)) {
            wp_send_json_error(['message' => 'This role key is reserved.'], 400);
        }
        add_role($role_key, $role_name, ['read' => true]);
        wp_send_json_success(['message' => 'Role added successfully!']);
    }

    /**
     * AJAX handler for saving lead substages.
     */
    public function save_lead_substages() {
        check_ajax_referer('opbcrm_settings_nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Access denied']);
        }
        $sub_stages = isset($_POST['sub_stages']) ? (array)$_POST['sub_stages'] : [];
        update_option('opbcrm_lead_substages', $sub_stages);
        wp_send_json_success(['message' => 'Sub-stages saved']);
    }

    public static function get_default_lead_substages() {
        return [
            'Hold/Call Again' => [
                'Switch Off',
                'No Answer',
                'Call Later',
                'Call Rejected',
            ],
            'Disqualified' => [
                "Don't Call Again",
                'Not interested',
                'Register By Mistake',
                'Wrong Number/Contact',
                "He Didn't Register",
                'Agent/Broker',
                'Job Seeker',
                'invalid/Unreachable Number',
                'Never Answer',
                'Low Budget',
                'Stop Answering',
                'Already Bought/ Investor',
                '-None-',
                'Long Future Prospect',
            ],
            'Follow Up' => [
                'Need More Time',
                'Short Future Prospect',
                'Low Budget',
            ],
        ];
    }

    public static function get_default_lead_stages() {
        return [
            'initial' => [
                ['id' => 'fresh_leads', 'label' => 'Fresh Leads', 'color' => '#00ff00'], // Green
            ],
            'additional' => [
                ['id' => 'hold_call_again', 'label' => 'Hold/Call Again', 'color' => '#ffff00'], // Yellow
                ['id' => 'ongoing_in_progress', 'label' => 'Ongoing-In Progress', 'color' => '#ff0000'], // Red
                ['id' => 'follow_up', 'label' => 'Follow Up', 'color' => '#ff8000'], // Orange
                ['id' => 'meeting', 'label' => 'Meeting', 'color' => '#ff00ff'], // Magenta
            ],
            'success' => [
                ['id' => 'won_deal', 'label' => 'Won Deal', 'color' => '#00cfff'], // Light Blue
            ],
            'failed' => [
                ['id' => 'close_lead', 'label' => 'Close Lead', 'color' => '#000000'], // Black
            ],
        ];
    }

    public function render_lead_substages_settings() {
        $sub_stages = get_option('opbcrm_lead_substages', self::get_default_lead_substages());
        $stages = get_option('opbcrm_lead_stages', []);
        ?>
        <div class="crm-glass-panel crm-substages-panel" style="max-width:900px;margin:0 auto 40px auto;padding:32px 28px 24px 28px;border-radius:22px;box-shadow:0 8px 32px 0 rgba(31,38,135,0.18);backdrop-filter:blur(12px);background:rgba(255,255,255,0.18);border:1.5px solid rgba(255,255,255,0.22);font-family:'Inter',sans-serif;">
            <div class="crm-header-row" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
                <span class="crm-title" style="font-size:2.1rem;font-weight:700;letter-spacing:-1px;">Lead Sub-Stages (Reasons)</span>
                <span class="crm-desc" style="font-size:1.1rem;color:#666;font-weight:400;">Define reasons for each stage. These are required for certain stages.</span>
            </div>
            <form id="opbcrm-lead-substages-form" style="margin-bottom:0;">
                <div class="crm-substage-groups" style="display:flex;flex-direction:column;gap:22px;">
                <?php foreach ($stages as $group => $stage_list) :
                    foreach ($stage_list as $stage) :
                        $stage_name = $stage['label'];
                        $stage_id = $stage['id'];
                        $stage_color = $stage['color'];
                        $stage_subs = isset($sub_stages[$stage_name]) ? $sub_stages[$stage_name] : [];
                ?>
                <div class="crm-substage-group glassy-panel" style="background:rgba(255,255,255,0.32);border:1px solid #e5e5e5;padding:18px 22px;border-radius:13px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);">
                    <label style="font-weight:600;font-size:1.08rem;display:flex;align-items:center;gap:10px;">
                        <span class="crm-stage-badge" style="background:<?php echo esc_attr($stage_color); ?>;color:#fff;padding:3px 12px;border-radius:12px;font-size:13px;font-weight:600;letter-spacing:0.5px;box-shadow:0 1px 4px 0 rgba(31,38,135,0.07);">
                            <?php echo esc_html($stage_name); ?>
                        </span>
                    </label>
                    <ul class="crm-substage-list" data-stage="<?php echo esc_attr($stage_name); ?>" style="list-style:none;padding:0;margin-top:10px;">
                        <?php foreach ($stage_subs as $sub) : ?>
                            <li style="margin-bottom:7px;display:flex;align-items:center;gap:8px;">
                                <input type="text" name="sub_stages[<?php echo esc_attr($stage_name); ?>][]" value="<?php echo esc_attr($sub); ?>" class="crm-input" style="font-size:14px;min-width:220px;">
                                <button type="button" class="crm-btn crm-btn-delete crm-remove-substage" style="font-size:15px;padding:3px 10px;background:rgba(200,40,40,0.13);color:#c82828;">&times;</button>
                            </li>
                        <?php endforeach; ?>
                        <li style="margin-bottom:7px;display:flex;align-items:center;gap:8px;">
                            <input type="text" placeholder="Add new reason..." class="crm-input crm-new-substage" style="font-size:14px;min-width:220px;">
                            <button type="button" class="crm-btn crm-btn-add crm-add-substage" style="font-size:15px;padding:3px 10px;background:rgba(40,180,80,0.13);color:#28a745;">+</button>
                        </li>
                    </ul>
                </div>
                <?php endforeach; endforeach; ?>
                </div>
                <p class="submit" style="margin-top:18px;">
                    <button type="submit" class="crm-btn" style="font-size:15px;padding:7px 22px;">Save Sub-Stages</button>
                    <span class="spinner"></span>
                </p>
            </form>
            <div id="crm-toast" style="display:none;position:fixed;bottom:32px;right:32px;z-index:9999;min-width:220px;padding:14px 22px;border-radius:12px;background:rgba(30,30,30,0.97);color:#fff;font-size:15px;font-weight:500;box-shadow:0 2px 12px 0 rgba(31,38,135,0.13);text-align:center;letter-spacing:0.2px;opacity:0.98;transition:all 0.3s;pointer-events:none;">Saved!</div>
        </div>
        <script>
        jQuery(document).ready(function($){
            // Add new substage
            $('.crm-add-substage').on('click', function(){
                var $li = $(this).closest('li');
                var val = $li.find('.crm-new-substage').val();
                if(val){
                    var $ul = $li.closest('ul');
                    $('<li style="margin-bottom:7px;display:flex;align-items:center;gap:8px;"><input type="text" name="sub_stages['+$ul.data('stage')+'][]" value="'+val+'" class="crm-input" style="font-size:14px;min-width:220px;"><button type="button" class="crm-btn crm-btn-delete crm-remove-substage" style="font-size:15px;padding:3px 10px;background:rgba(200,40,40,0.13);color:#c82828;">&times;</button></li>').insertBefore($li);
                    $li.find('.crm-new-substage').val('');
                }
            });
            // Remove substage
            $(document).on('click', '.crm-remove-substage', function(){
                $(this).closest('li').remove();
            });
            // Save substages
            $('#opbcrm-lead-substages-form').on('submit', function(e){
                e.preventDefault();
                var spinner = $(this).find('.spinner');
                spinner.addClass('is-active');
                var data = $(this).serializeArray();
                var sub_stages = {};
                data.forEach(function(item){
                    var match = item.name.match(/^sub_stages\[(.+)\]\[\]$/);
                    if(match){
                        var stage = match[1];
                        if(!sub_stages[stage]) sub_stages[stage] = [];
                        sub_stages[stage].push(item.value);
                    }
                });
                $.post(ajaxurl, {
                    action: 'opbcrm_save_lead_substages',
                    sub_stages: sub_stages,
                    _ajax_nonce: '<?php echo wp_create_nonce('opbcrm_settings_nonce'); ?>'
                }, function(resp){
                    spinner.removeClass('is-active');
                    var $toast = $('#crm-toast');
                    if(resp.success){
                        $toast.text('Saved!').css('background','rgba(40,180,80,0.97)').fadeIn(180);
                        setTimeout(function(){ $toast.fadeOut(350); }, 1800);
                    } else {
                        $toast.text('Error: '+resp.data.message).css('background','rgba(200,40,40,0.97)').fadeIn(180);
                        setTimeout(function(){ $toast.fadeOut(350); }, 1800);
                    }
                });
            });
        });
        </script>
        <style>
        .opbcrm-lead-substage-group { margin-bottom: 24px; }
        .opbcrm-substage-list { list-style: none; padding: 0; }
        .opbcrm-substage-list li { margin-bottom: 6px; }
        .opbcrm-remove-substage { color: red; border: none; background: none; font-size: 18px; cursor: pointer; }
        .opbcrm-add-substage { color: green; border: none; background: none; font-size: 18px; cursor: pointer; }
        </style>
        <?php
    }

    /**
     * Get all automations.
     */
    public static function get_automations() {
        $autos = get_option('opbcrm_automations', []);
        return is_array($autos) ? $autos : [];
    }

    /**
     * Save or update an automation rule.
     * If $automation['id'] exists, update; else, add new with unique id.
     */
    public static function save_automation($automation) {
        $autos = self::get_automations();
        if (!isset($automation['id']) || !$automation['id']) {
            $automation['id'] = uniqid('auto_');
        }
        $found = false;
        foreach ($autos as &$a) {
            if ($a['id'] === $automation['id']) {
                $a = $automation;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $autos[] = $automation;
        }
        update_option('opbcrm_automations', $autos);
        return $automation['id'];
    }

    /**
     * Delete an automation by id.
     */
    public static function delete_automation($id) {
        $autos = self::get_automations();
        $autos = array_filter($autos, function($a) use ($id) { return $a['id'] !== $id; });
        update_option('opbcrm_automations', $autos);
    }

    /**
     * Run all automations for a given trigger and context.
     * @param string $trigger
     * @param array $context (e.g. lead_id, user_id, etc.)
     */
    public static function run_automations($trigger, $context = []) {
        $autos = self::get_automations();
        foreach ($autos as $auto) {
            if (($auto['status'] ?? 'active') !== 'active') continue;
            if (($auto['trigger'] ?? '') !== $trigger) continue;
            $action = $auto['action'] ?? '';
            $label = $auto['label'] ?? 'Automation';
            $lead_id = $context['lead_id'] ?? 0;
            // --- Action logic ---
            if ($action === 'send_email') {
                // Send email to agent or lead
                $to = '';
                if (!empty($context['agent_id'])) {
                    $user = get_userdata($context['agent_id']);
                    $to = $user ? $user->user_email : '';
                } elseif (!empty($lead_id)) {
                    $to = get_post_meta($lead_id, 'lead_email', true);
                }
                if ($to) {
                    $subject = '[CRM Automation] ' . $label;
                    $message = 'This is an automated email triggered by: ' . $label;
                    wp_mail($to, $subject, $message);
                }
            } elseif ($action === 'assign_agent') {
                // Assign agent to lead
                if (!empty($lead_id) && !empty($context['agent_id'])) {
                    update_post_meta($lead_id, 'agent_id', $context['agent_id']);
                }
            } elseif ($action === 'create_task') {
                // Create a task for the lead
                if (!empty($lead_id)) {
                    global $opbcrm;
                    $task_args = [
                        'content' => 'Automated Task: ' . $label,
                        'assigned_to_user_id' => $context['agent_id'] ?? get_current_user_id(),
                        'due_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
                        'task_status' => 'pending',
                    ];
                    $opbcrm->activity->add_activity($lead_id, 'task', $task_args);
                }
            }
            // Log automation run
            if (!empty($lead_id) && isset($opbcrm)) {
                $log_msg = 'Automation "' . esc_html($label) . '" triggered by ' . esc_html($trigger) . ' and action: ' . esc_html($action);
                $opbcrm->activity->add_activity($lead_id, 'automation', ['content'=>$log_msg]);
                // Add notification for relevant user
                $user_id = $context['agent_id'] ?? get_post_field('post_author', $lead_id) ?? get_current_user_id();
                $notif_msg = 'Automation "' . $label . '" triggered: ' . $action;
                $opbcrm->activity->add_notification($user_id, 'automation', $notif_msg, '');
            }
        }
    }
}

new OPBCRM_Settings(); 