<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    OPBCRM
 * @subpackage OPBCRM/admin
 */
class Houzcrm_Admin {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function setup_admin_menu() {
        add_menu_page(
            'OPB CRM',
            'OPB CRM',
            'manage_crm',
            'opbcrm',
            array($this, 'render_settings_page'),
            'dashicons-businessperson'
        );

        add_submenu_page(
            'opbcrm',
            'Settings',
            'Settings',
            'manage_crm',
            'opbcrm'
        );

        add_submenu_page(
            'opbcrm',
            'Access Permissions',
            'Access',
            'manage_options', // Only top-level admins should manage permissions
            'opbcrm-permissions',
            array($this, 'render_permissions_page')
        );

        add_submenu_page(
            'opbcrm',
            'Custom Fields',
            'Custom Fields',
            'manage_options',
            'opbcrm-custom-fields',
            array($this, 'render_custom_fields_page')
        );
    }

    public function render_settings_page() {
        // Settings page logic will go here
        include_once(OPBCRM_ADMIN_PATH . 'partials/settings-page.php');
    }

    public function render_custom_fields_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'opbcrm_custom_fields';

        // Handle form submission for new fields
        if (isset($_POST['submit']) && isset($_POST['field_label'])) {
             if (check_admin_referer('opbcrm_add_custom_field_nonce')) {
                $label = sanitize_text_field($_POST['field_label']);
                $type = sanitize_text_field($_POST['field_type']);
                $options = ($type === 'select') ? sanitize_textarea_field($_POST['field_options']) : '';
                // Generate a unique name/slug from the label
                $name = sanitize_title($label);
                $name = str_replace('-', '_', $name);
                if ($label && $type && $name) {
                    $wpdb->insert(
                        $table_name,
                        [
                            'field_label' => $label,
                            'field_name' => 'cf_' . $name, // prefix to avoid conflicts
                            'field_type' => $type,
                            'field_options' => $options,
                        ],
                        ['%s', '%s', '%s', '%s']
                    );
                    echo '<div class="crm-toast-success" style="background:rgba(40,180,80,0.97);color:#fff;padding:12px 20px;border-radius:10px;margin-bottom:18px;">Custom field added successfully.</div>';
                } else {
                     echo '<div class="crm-toast-error" style="background:rgba(200,40,40,0.97);color:#fff;padding:12px 20px;border-radius:10px;margin-bottom:18px;">Failed to add custom field. Label is required.</div>';
                }
            }
        }

        // Display the page content
        ?>
        <div class="crm-glass-panel crm-custom-fields-panel" style="max-width:1100px;margin:0 auto 40px auto;padding:32px 28px 24px 28px;border-radius:22px;box-shadow:0 8px 32px 0 rgba(31,38,135,0.18);backdrop-filter:blur(12px);background:rgba(255,255,255,0.18);border:1.5px solid rgba(255,255,255,0.22);font-family:'Inter',sans-serif;">
            <div class="crm-header-row" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
                <span class="crm-title" style="font-size:2.1rem;font-weight:700;letter-spacing:-1px;">Custom Fields</span>
                <span class="crm-desc" style="font-size:1.1rem;color:#666;font-weight:400;">Add and manage custom fields for your leads.</span>
            </div>
            <div class="crm-custom-fields-wrap" style="display:flex;gap:32px;flex-wrap:wrap;">
                <div class="crm-custom-fields-table glassy-panel" style="background:rgba(255,255,255,0.32);border:1px solid #e5e5e5;padding:18px 22px;border-radius:13px;min-width:320px;max-width:520px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);flex:2;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                        <h2 style="font-size:1.1rem;font-weight:600;margin-bottom:0;">Existing Fields</h2>
                        <input id="crm-custom-fields-search" type="text" class="crm-input" placeholder="Search fields..." style="font-family:'Inter',sans-serif;font-size:13px;padding:5px 12px;border-radius:8px;background:rgba(255,255,255,0.7);border:1px solid #e5e5e5;box-shadow:0 1px 4px 0 rgba(31,38,135,0.04);width:160px;max-width:100%;outline:none;">
                    </div>
                    <table class="crm-table" style="width:100%;font-size:13px;border-radius:12px;overflow:hidden;">
                        <thead style="background:rgba(255,255,255,0.85);">
                            <tr>
                                <th>Label</th>
                                <th>Name/Slug</th>
                                <th>Type</th>
                                <th>Options</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="crm-custom-fields-tbody">
                            <?php
                            $fields = $wpdb->get_results("SELECT * FROM $table_name ORDER BY field_label ASC");
                            if ($fields) {
                                foreach ($fields as $field) {
                                    echo '<tr data-field-id="' . esc_attr($field->id) . '">';
                                    echo '<td>';
                                    echo '<span class="cf-label-view">' . esc_html($field->field_label) . '</span>';
                                    if (current_user_can('edit_custom_field')) {
                                        echo '<button class="crm-btn crm-btn-icon crm-edit-field-btn" title="Edit" style="background:rgba(40,120,220,0.10);color:#227;padding:4px 8px;border-radius:7px;font-size:15px;margin-left:6px;"><i class="fas fa-pen"></i></button>';
                                    }
                                    echo '<div class="cf-label-edit-row" style="display:none;gap:6px;align-items:center;">';
                                    echo '<input type="text" class="cf-edit-label-input crm-input" value="' . esc_attr($field->field_label) . '" style="width:110px;font-size:13px;">';
                                    echo '<button class="crm-btn crm-btn-xs cf-save-label-btn" style="padding:2px 10px;font-size:12px;">Save</button>';
                                    echo '<button class="crm-btn crm-btn-xs cf-cancel-label-btn" style="padding:2px 10px;font-size:12px;background:#eee;color:#333;">Cancel</button>';
                                    echo '</div>';
                                    echo '</td>';
                                    echo '<td>' . esc_html($field->field_name) . '</td>';
                                    echo '<td>';
                                    echo '<span class="cf-type-view">' . esc_html(ucfirst($field->field_type)) . '</span>';
                                    if (current_user_can('edit_custom_field')) {
                                        echo '<button class="crm-btn crm-btn-icon crm-edit-type-btn" title="Edit Type" style="background:rgba(40,120,220,0.10);color:#227;padding:4px 8px;border-radius:7px;font-size:15px;margin-left:6px;"><i class="fas fa-pen"></i></button>';
                                    }
                                    echo '<div class="cf-type-edit-row" style="display:none;gap:6px;align-items:center;">';
                                    echo '<select class="cf-edit-type-select crm-input" style="font-size:13px;width:90px;">';
                                    foreach (["text","textarea","number","date","select"] as $type) {
                                        $sel = $field->field_type === $type ? 'selected' : '';
                                        echo '<option value="' . esc_attr($type) . '" ' . $sel . '>' . ucfirst($type) . '</option>';
                                    }
                                    echo '</select>';
                                    echo '<button class="crm-btn crm-btn-xs cf-save-type-btn" style="padding:2px 10px;font-size:12px;">Save</button>';
                                    echo '<button class="crm-btn crm-btn-xs cf-cancel-type-btn" style="padding:2px 10px;font-size:12px;background:#eee;color:#333;">Cancel</button>';
                                    echo '</div>';
                                    echo '</td>';
                                    echo '<td>';
                                    if ($field->field_type === 'select') {
                                        echo '<span class="cf-options-view">' . esc_html($field->field_options) . '</span>';
                                        if (current_user_can('edit_custom_field')) {
                                            echo '<button class="crm-btn crm-btn-icon crm-edit-options-btn" title="Edit Options" style="background:rgba(40,120,220,0.10);color:#227;padding:4px 8px;border-radius:7px;font-size:15px;margin-left:6px;"><i class="fas fa-pen"></i></button>';
                                        }
                                        echo '<div class="cf-options-edit-row" style="display:none;gap:6px;align-items:center;">';
                                        echo '<textarea class="cf-edit-options-input crm-input" style="font-size:13px;width:120px;min-height:28px;">' . esc_textarea($field->field_options) . '</textarea>';
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
                                }
                            } else {
                                echo '<tr><td colspan="5">No custom fields found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <div class="crm-custom-fields-form glassy-panel" style="background:rgba(255,255,255,0.32);border:1px solid #e5e5e5;padding:18px 22px;border-radius:13px;min-width:320px;max-width:340px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);flex:1;">
                    <h2 style="font-size:1.1rem;font-weight:600;margin-bottom:10px;">Add New Field</h2>
                    <form method="post" action="" id="crm-add-custom-field-form" autocomplete="off">
                        <?php wp_nonce_field('opbcrm_add_custom_field_nonce'); ?>
                        <div class="floating-label-group">
                            <input name="field_label" id="field_label" type="text" value="" required class="crm-input" placeholder=" ">
                            <label for="field_label">Label</label>
                        </div>
                        <div class="floating-label-group">
                            <select name="field_type" id="field_type" class="crm-input" style="font-size:14px;">
                                <option value="text">Text</option>
                                <option value="textarea">Textarea</option>
                                <option value="number">Number</option>
                                <option value="date">Date</option>
                                <option value="select">Select List</option>
                            </select>
                            <label for="field_type">Field Type</label>
                        </div>
                        <div class="floating-label-group" id="field-options-container" style="display:none;">
                            <textarea name="field_options" id="field_options" rows="4" class="crm-input" style="font-size:14px;"></textarea>
                            <label for="field_options">Options (one per line)</label>
                        </div>
                        <button type="submit" name="submit" class="crm-btn" style="font-size:15px;padding:7px 22px;margin-top:10px;">Add New Field</button>
                    </form>
                </div>
            </div>
            <div id="crm-toast" style="display:none;position:fixed;bottom:32px;right:32px;z-index:9999;min-width:220px;padding:14px 22px;border-radius:12px;background:rgba(30,30,30,0.97);color:#fff;font-size:15px;font-weight:500;box-shadow:0 2px 12px 0 rgba(31,38,135,0.13);text-align:center;letter-spacing:0.2px;opacity:0.98;transition:all 0.3s;pointer-events:none;">Saved!</div>
            <script>
                jQuery(document).ready(function($) {
                    $('#field_type').on('change', function() {
                        if ($(this).val() === 'select') {
                            $('#field-options-container').show();
                        } else {
                            $('#field-options-container').hide();
                        }
                    }).trigger('change');
                    // Toast feedback for add
                    $('#crm-add-custom-field-form').on('submit', function(e){
                        var $btn = $(this).find('button[type="submit"]');
                        $btn.prop('disabled', true);
                        setTimeout(function(){ $btn.prop('disabled', false); }, 1200);
                        var $toast = $('#crm-toast');
                        setTimeout(function(){ $toast.fadeOut(350); }, 1800);
                    });
                });
            </script>
        </div>
        <?php
    }

    public function render_permissions_page() {
        if (isset($_POST['submit']) && isset($_POST['crm_permissions_nonce'])) {
            if (wp_verify_nonce($_POST['crm_permissions_nonce'], 'crm_permissions_update')) {
                $this->save_permissions();
                echo '<div class="updated"><p>Permissions saved successfully!</p></div>';
            }
        }

        // Define our custom CRM capabilities
        $crm_caps = [
            'view_leads' => 'View Leads',
            'edit_leads' => 'Edit Leads',
            'delete_leads' => 'Delete Leads',
            'assign_leads' => 'Assign Leads',
            'view_reports' => 'View Reports',
            'manage_crm_settings' => 'Manage CRM Settings',
        ];

        // Get the roles we want to manage
        $roles_to_manage = ['crm_manager', 'crm_agent'];

        ?>
        <div class="wrap">
            <h1>Access Permissions</h1>
            <p>Control what different user roles can do within the CRM.</p>
            <form method="post" action="">
                <?php wp_nonce_field('crm_permissions_update', 'crm_permissions_nonce'); ?>
                
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>Capability</th>
                            <?php foreach ($roles_to_manage as $role_name) : 
                                $role_obj = get_role($role_name);
                                if (!$role_obj) continue;
                            ?>
                                <th class="manage-column"><?php echo esc_html($role_obj->name); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($crm_caps as $cap => $label) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($label); ?></strong>
                                    <p class="description"><?php echo esc_html($cap); ?></p>
                                </td>
                                <?php foreach ($roles_to_manage as $role_name) : 
                                    $role_obj = get_role($role_name);
                                    if (!$role_obj) continue;
                                    $is_checked = $role_obj->has_cap($cap);
                                ?>
                                    <td>
                                        <input type="checkbox" 
                                               name="crm_permissions[<?php echo esc_attr($role_name); ?>][<?php echo esc_attr($cap); ?>]" 
                                               value="1"
                                               <?php checked($is_checked); ?>>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function save_permissions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $permissions = isset($_POST['crm_permissions']) ? $_POST['crm_permissions'] : [];
        $roles_to_manage = ['crm_manager', 'crm_agent'];
        $crm_caps = [
            'view_leads' => 'View Leads', 'edit_leads' => 'Edit Leads',
            'delete_leads' => 'Delete Leads', 'assign_leads' => 'Assign Leads',
            'view_reports' => 'View Reports', 'manage_crm_settings' => 'Manage CRM Settings',
        ];
        $valid_caps = array_keys($crm_caps);

        foreach ($roles_to_manage as $role_name) {
            $role_obj = get_role($role_name);
            if (!$role_obj) continue;

            foreach ($valid_caps as $cap) {
                if (isset($permissions[$role_name][$cap]) && $permissions[$role_name][$cap] == '1') {
                    $role_obj->add_cap($cap, true);
                } else {
                    $role_obj->remove_cap($cap);
                }
            }
        }
    }

    public function register_settings() {
        register_setting('opbcrm_options_group', 'opbcrm_pipeline_stages');
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/opbcrm-admin.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/opbcrm-admin.js', array('jquery', 'jquery-ui-sortable'), $this->version, false);
        wp_localize_script($this->plugin_name, 'opbcrm_admin_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
    }
} 