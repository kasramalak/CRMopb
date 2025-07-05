<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class OPBCRM_Admin_Menu {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    }

    public function register_menu() {
        // Main CRM Menu
        add_menu_page(
            __( 'OPBCRM', 'opbcrm' ),
            __( 'OPBCRM', 'opbcrm' ),
            'manage_crm', // Capability
            'opbcrm_dashboard',
            array( $this, 'render_dashboard_page' ),
            'dashicons-groups', // Icon
            26 // Position
        );

        // Dashboard Sub-menu
        add_submenu_page(
            'opbcrm_dashboard',
            'Dashboard',
            'Dashboard',
            'manage_crm_settings',
            'opbcrm_dashboard',
            array($this, 'render_dashboard_page')
        );

        // Leads Sub-menu
        add_submenu_page(
            'opbcrm_dashboard',
            __( 'Leads', 'opbcrm' ),
            __( 'Leads', 'opbcrm' ),
            'manage_crm',
            'opbcrm_leads',
            array( $this, 'render_leads_page' )
        );

        // Settings Sub-menu
        add_submenu_page(
            'opbcrm_dashboard',
            __( 'Settings', 'opbcrm' ),
            __( 'Settings', 'opbcrm' ),
            'manage_options', // Only admins should see this
            'opbcrm_settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function render_dashboard_page() {
        echo '<div class="wrap">';
        // We will now render the same dashboard used on the frontend
        
        // Enqueue scripts and styles needed for our dashboard
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

        // Include the dashboard template
        include_once OPBCRM_TEMPLATES_PATH . 'crm-dashboard.php';
        
        echo '</div>';
    }

    public function render_leads_page() {
        echo '<h1>Leads</h1><p>Here you can manage all leads.</p>';
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>OPBCRM Settings</h1>
            <form method="post" action="options.php">
                <?php
                // This prints out all hidden setting fields
                settings_fields( 'opbcrm_settings_group' );
                do_settings_sections( 'opbcrm-settings-admin' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

new OPBCRM_Admin_Menu(); 