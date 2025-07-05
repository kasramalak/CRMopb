<?php
/**
 * Plugin Name: OPBCRM
 * Plugin URI:        https://offplanbazaar.ae
 * Description:       Modular, enterprise-grade CRM for WordPress.
 * Version:           0.2
 * Author:            Kasra Malakouti
 * Author URI:        https://offplanbazaar.ae
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       opbcrm
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
class OPBCRM {

    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    private function define_constants() {
        define( 'OPBCRM_VERSION', '1.1.0' );
        if (!defined('OPBCRM_PLUGIN_DIR')) {
            define('OPBCRM_PLUGIN_DIR', plugin_dir_path(__FILE__));
        }
        define( 'OPBCRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
        define( 'OPBCRM_ASSETS_URL', OPBCRM_PLUGIN_URL . 'assets/' );
        if (!defined('OPBCRM_TEMPLATES_PATH')) {
            define('OPBCRM_TEMPLATES_PATH', OPBCRM_PLUGIN_DIR . 'templates/');
        }
    }

    private function includes() {
        // Core files
        require_once OPBCRM_PLUGIN_DIR . 'includes/database.php';
        require_once OPBCRM_PLUGIN_DIR . 'includes/class-opbcrm-roles.php';
        require_once OPBCRM_PLUGIN_DIR . 'includes/class-opbcrm-activity.php';
        require_once OPBCRM_PLUGIN_DIR . 'includes/leads.php';
        require_once OPBCRM_PLUGIN_DIR . 'includes/integrations.php';
        require_once OPBCRM_PLUGIN_DIR . 'includes/mobile.php';
        
        // Admin-specific files
        if ( is_admin() ) {
            require_once OPBCRM_PLUGIN_DIR . 'includes/admin/class-opbcrm-admin-menus.php';
            require_once OPBCRM_PLUGIN_DIR . 'includes/admin/class-opbcrm-settings.php';
        }
        require_once plugin_dir_path(__FILE__) . 'includes/class-opbcrm-crm-login-page.php';
    }

    private function init_hooks() {
        // Activation hook for creating tables
        register_activation_hook( __FILE__, array( 'OPBCRM_DB', 'create_tables' ) );

        // Deactivation hook for removing roles
        register_deactivation_hook( __FILE__, array( 'OPBCRM_Roles', 'remove_roles' ) );
        
        // Add custom post type for proposal templates
        add_action( 'init', array( $this, 'register_proposal_template_cpt' ) );
        add_action( 'init', array( $this, 'load_frontend_dashboard_class' ), 1 );

        // Global safeguard: Only load CRM assets for CRM users
        add_action('wp_enqueue_scripts', function() {
            if (!current_user_can('crm_manager') && !current_user_can('crm_agent')) {
                wp_dequeue_style('opbcrm-dashboard-styles');
                wp_dequeue_script('opbcrm-dashboard-script');
                // Add more asset handles here if needed
            }
        }, 100);

        // Register AJAX endpoints for tasks
        add_action('wp_ajax_opbcrm_get_tasks', function() {
            $lead_id = intval($_POST['lead_id']);
            $tasks = get_post_meta($lead_id, '_opbcrm_tasks', true) ?: [];
            wp_send_json_success(['tasks' => $tasks]);
        });
        add_action('wp_ajax_opbcrm_add_task', function() {
            $lead_id = intval($_POST['lead_id']);
            $tasks = get_post_meta($lead_id, '_opbcrm_tasks', true) ?: [];
            $task = [
                'id' => uniqid('task_'),
                'title' => sanitize_text_field($_POST['title']),
                'description' => sanitize_textarea_field($_POST['description']),
                'assignee' => intval($_POST['assignee']),
                'deadline' => sanitize_text_field($_POST['deadline']),
                'status' => 'open',
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ];
            $tasks[] = $task;
            update_post_meta($lead_id, '_opbcrm_tasks', $tasks);
            // Log to history
            opbcrm_log_history($lead_id, 'Task Added', $task['title']);
            wp_send_json_success(['task' => $task]);
        });
        add_action('wp_ajax_opbcrm_update_task', function() {
            $lead_id = intval($_POST['lead_id']);
            $task_id = sanitize_text_field($_POST['task_id']);
            $tasks = get_post_meta($lead_id, '_opbcrm_tasks', true) ?: [];
            foreach ($tasks as &$task) {
                if ($task['id'] === $task_id) {
                    $task['title'] = sanitize_text_field($_POST['title']);
                    $task['description'] = sanitize_textarea_field($_POST['description']);
                    $task['assignee'] = intval($_POST['assignee']);
                    $task['deadline'] = sanitize_text_field($_POST['deadline']);
                    $task['status'] = sanitize_text_field($_POST['status']);
                    $task['updated_at'] = current_time('mysql');
                    // Log to history
                    opbcrm_log_history($lead_id, 'Task Updated', $task['title']);
                }
            }
            update_post_meta($lead_id, '_opbcrm_tasks', $tasks);
            wp_send_json_success(['tasks' => $tasks]);
        });
        add_action('wp_ajax_opbcrm_delete_task', function() {
            $lead_id = intval($_POST['lead_id']);
            $task_id = sanitize_text_field($_POST['task_id']);
            $tasks = get_post_meta($lead_id, '_opbcrm_tasks', true) ?: [];
            $tasks = array_filter($tasks, fn($t) => $t['id'] !== $task_id);
            update_post_meta($lead_id, '_opbcrm_tasks', $tasks);
            // Log to history
            opbcrm_log_history($lead_id, 'Task Deleted', $task_id);
            wp_send_json_success(['tasks' => $tasks]);
        });
    }

    public function load_frontend_dashboard_class() {
        require_once OPBCRM_PLUGIN_DIR . 'includes/frontend/class-opbcrm-frontend-dashboard.php';
    }

    /**
     * Register the Custom Post Type for Proposal Templates.
     */
    public function register_proposal_template_cpt() {
        $labels = array(
            'name'                  => _x( 'Proposal Templates', 'Post Type General Name', 'opbcrm' ),
            'singular_name'         => _x( 'Proposal Template', 'Post Type Singular Name', 'opbcrm' ),
            'menu_name'             => __( 'Proposal Templates', 'opbcrm' ),
            'name_admin_bar'        => __( 'Proposal Template', 'opbcrm' ),
            'archives'              => __( 'Template Archives', 'opbcrm' ),
            'attributes'            => __( 'Template Attributes', 'opbcrm' ),
            'parent_item_colon'     => __( 'Parent Template:', 'opbcrm' ),
            'all_items'             => __( 'All Templates', 'opbcrm' ),
            'add_new_item'          => __( 'Add New Template', 'opbcrm' ),
            'add_new'               => __( 'Add New', 'opbcrm' ),
            'new_item'              => __( 'New Template', 'opbcrm' ),
            'edit_item'             => __( 'Edit Template', 'opbcrm' ),
            'update_item'           => __( 'Update Template', 'opbcrm' ),
            'view_item'             => __( 'View Template', 'opbcrm' ),
            'view_items'            => __( 'View Templates', 'opbcrm' ),
            'search_items'          => __( 'Search Template', 'opbcrm' ),
        );
        $args = array(
            'label'                 => __( 'Proposal Template', 'opbcrm' ),
            'description'           => __( 'Templates for generating sales proposals.', 'opbcrm' ),
            'labels'                => $labels,
            'supports'              => array( 'title', 'editor' ),
            'hierarchical'          => false,
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => 'opbcrm_dashboard', // Add to our CRM menu
            'menu_position'         => 80,
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'page',
            'show_in_rest'          => true,
        );
        register_post_type( 'crm_proposal_tmpl', $args );
    }

}

/**
 * Begins execution of the plugin.
 */
function opbcrm_run() {
	return OPBCRM::get_instance();
}
add_action( 'plugins_loaded', 'opbcrm_run' );

function opbcrm_activate() {
    // Add the 'manage_crm' capability to administrators
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('manage_crm', true);
    }
    
    // Create custom database tables
    opbcrm_create_db_tables();
}

/**
 * Create custom database tables needed for the plugin.
 */
function opbcrm_create_db_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Table for Activities
    $table_name = $wpdb->prefix . 'opbcrm_activities';
    $sql = "CREATE TABLE $table_name (
        activity_id mediumint(9) NOT NULL AUTO_INCREMENT,
        lead_id bigint(20) UNSIGNED NOT NULL,
        user_id bigint(20) UNSIGNED NOT NULL,
        activity_type varchar(50) NOT NULL,
        content text,
        activity_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        assigned_to_user_id bigint(20) UNSIGNED DEFAULT 0 NOT NULL,
        due_date datetime,
        task_status varchar(20) DEFAULT 'pending' NOT NULL,
        PRIMARY KEY  (activity_id),
        KEY lead_id (lead_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Table for Custom Fields Definitions
    $table_name_cf = $wpdb->prefix . 'opbcrm_custom_fields';
    $sql_cf = "CREATE TABLE $table_name_cf (
        field_id mediumint(9) NOT NULL AUTO_INCREMENT,
        field_label varchar(255) NOT NULL,
        field_name varchar(100) NOT NULL,
        field_type varchar(50) NOT NULL,
        field_options text,
        PRIMARY KEY  (field_id),
        UNIQUE KEY field_name (field_name)
    ) $charset_collate;";

    dbDelta($sql_cf);
}

register_activation_hook(__FILE__, 'opbcrm_activate');

function opbcrm_log_history($lead_id, $event, $desc) {
    $history = get_post_meta($lead_id, '_opbcrm_history', true) ?: [];
    $history[] = [
        'date' => current_time('mysql'),
        'user' => get_current_user_id(),
        'event' => $event,
        'desc' => $desc,
    ];
    update_post_meta($lead_id, '_opbcrm_history', $history);
} 

// Register /CRM endpoint (case-sensitive)
add_action('init', function() {
    add_rewrite_rule('^CRM/?$', 'index.php?opbcrm_crm_login=1', 'top');
    add_rewrite_tag('%opbcrm_crm_login%', '1');
});

// Template loader for /CRM (case-sensitive)
add_filter('template_include', function($template) {
    if (get_query_var('opbcrm_crm_login') == 1) {
        $custom = plugin_dir_path(__FILE__) . 'templates/crm-login.php';
        if (file_exists($custom)) return $custom;
    }
    return $template;
});

// Redirect /crm (lowercase) to home
add_action('template_redirect', function() {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#^/crm/?$#i', $request_uri) && !preg_match('#^/CRM/?$#', $request_uri)) {
        wp_redirect(home_url('/'));
        exit;
    }
}); 

add_filter('the_content', function($content) {
    if (is_page('CRM')) {
        return '<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#e0e7ef 0%,#c9d6ff 100%);font-family:Inter,sans-serif;">
        <div style="backdrop-filter:blur(16px) saturate(180%);-webkit-backdrop-filter:blur(16px) saturate(180%);background:rgba(255,255,255,0.55);border-radius:16px;box-shadow:0 8px 32px 0 rgba(31,38,135,0.37);padding:48px 32px;max-width:350px;width:100%;text-align:center;">
            <h2 style="font-weight:700;font-size:2rem;margin-bottom:24px;color:#222;">CRM Login</h2>
            <form>
                <input type="text" placeholder="Username" style="width:100%;margin-bottom:16px;padding:12px;border-radius:8px;border:1px solid #e0e7ef;background:rgba(255,255,255,0.7);font-size:1rem;outline:none;">
                <input type="password" placeholder="Password" style="width:100%;margin-bottom:24px;padding:12px;border-radius:8px;border:1px solid #e0e7ef;background:rgba(255,255,255,0.7);font-size:1rem;outline:none;">
                <button type="submit" style="width:100%;padding:12px 0;border:none;border-radius:8px;background:linear-gradient(90deg,#667eea 0%,#764ba2 100%);color:#fff;font-weight:600;font-size:1rem;cursor:pointer;box-shadow:0 2px 8px rgba(102,126,234,0.15);transition:background 0.2s;">Login</button>
            </form>
        </div>
    </div>';
    }
    return $content;
});

register_activation_hook(__FILE__, function() {
    OPBCRM_CRM_Login_Page::create_or_get_page();
    flush_rewrite_rules();
}); 