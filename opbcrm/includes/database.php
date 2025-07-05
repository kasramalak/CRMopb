<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class OPBCRM_DB {

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // Table for Leads
        $table_name = $wpdb->prefix . 'crm_leads';
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            phone VARCHAR(100) DEFAULT '' NOT NULL,
            email VARCHAR(100) DEFAULT '' NOT NULL,
            stage VARCHAR(100) NOT NULL,
            sub_stage VARCHAR(100) DEFAULT '' NOT NULL,
            agent_id BIGINT(20) UNSIGNED NOT NULL,
            source VARCHAR(100) DEFAULT '' NOT NULL,
            tags TEXT,
            custom_fields LONGTEXT,
            created_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            last_activity DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY agent_id (agent_id)
        ) $charset_collate;";
        dbDelta( $sql );
        
        // Table for Activities
        $table_name = $wpdb->prefix . 'crm_activities';
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            lead_id BIGINT(20) NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            activity_type VARCHAR(50) NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY lead_id (lead_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Table for Tasks
        $table_name = $wpdb->prefix . 'crm_tasks';
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            lead_id BIGINT(20) NOT NULL,
            agent_id BIGINT(20) UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            due_date DATETIME,
            status VARCHAR(50) DEFAULT 'pending' NOT NULL,
            created_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY lead_id (lead_id),
            KEY agent_id (agent_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Table for Proposals
        $table_name = $wpdb->prefix . 'crm_proposals';
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            lead_id BIGINT(20) NOT NULL,
            property_id BIGINT(20) NOT NULL,
            proposal_data LONGTEXT,
            created_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            status VARCHAR(50) DEFAULT 'draft' NOT NULL,
            PRIMARY KEY  (id),
            KEY lead_id (lead_id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Table for Proposal Templates
        $table_name = $wpdb->prefix . 'crm_templates';
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            content LONGTEXT,
            created_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Table for Global Settings
        $table_name = $wpdb->prefix . 'crm_settings';
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(100) NOT NULL,
            setting_value LONGTEXT,
            PRIMARY KEY  (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        dbDelta( $sql );

        // Table for CRM Users
        $table_name = $wpdb->prefix . 'opbcrm_users';
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'admin',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            last_login DATETIME DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta( $sql );

        // Insert default admin user if not exists
        $admin_username = 'admin';
        $admin_password = '12345';
        $admin_email = 'admin@crm.local';
        $admin_role = 'admin';
        $table_name = $wpdb->prefix . 'opbcrm_users';
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE username = %s", $admin_username ) );
        if ( ! $existing ) {
            $password_hash = password_hash( $admin_password, PASSWORD_DEFAULT );
            $wpdb->insert( $table_name, [
                'username' => $admin_username,
                'password_hash' => $password_hash,
                'email' => $admin_email,
                'role' => $admin_role,
                'created_at' => current_time( 'mysql' ),
            ] );
        }
    }
}

// Add CRM user authentication functions
class OPBCRM_Users {
    public static function get_user_by_username( $username ) {
        global $wpdb;
        $table = $wpdb->prefix . 'opbcrm_users';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE username = %s", $username ) );
    }
    public static function verify_login( $username, $password ) {
        $user = self::get_user_by_username( $username );
        if ( $user && password_verify( $password, $user->password_hash ) ) {
            return $user;
        }
        return false;
    }
} 