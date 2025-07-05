<?php
/**
 * Manages CRM Roles and Capabilities.
 */
if (!defined('WPINC')) {
	die;
}

class OPBCRM_Roles {

	public function __construct() {
		// This class will now primarily be a helper for capabilities.
		// Role creation can be done, but assigning caps will be manual via the settings page.
		add_action('init', array($this, 'register_custom_roles'));
		add_action('init', function() {
			$caps = array_keys(self::get_all_crm_capabilities());
			foreach (['administrator', 'crm_manager', 'crm_agent'] as $role_name) {
				$role = get_role($role_name);
				if ($role) {
					foreach ($caps as $cap) {
						$role->add_cap($cap, true);
					}
				}
			}
		});
	}

	/**
	 * Register custom roles if they don't exist.
	 * The capabilities for these roles will be managed from the 'Permissions' page.
	 */
	public function register_custom_roles() {
		if (!get_role('crm_manager')) {
			add_role('crm_manager', __('CRM Manager', 'opbcrm'), ['read' => true]);
		}
		if (!get_role('crm_agent')) {
			add_role('crm_agent', __('CRM Agent', 'opbcrm'), ['read' => true]);
		}
	}

	/**
	 * Returns a comprehensive list of all possible CRM capabilities.
	 * This is the master list used to build the permissions settings page.
	 *
	 * @return array
	 */
	public static function get_all_crm_capabilities() {
		return [
			'access_crm_dashboard' => __('Access CRM Dashboard', 'opbcrm'),
			
			'view_own_leads'    => __('View Own Leads', 'opbcrm'),
			'view_others_leads' => __('View Others\' Leads', 'opbcrm'),
			
			'add_leads'         => __('Add New Leads', 'opbcrm'),
			
			'edit_own_leads'    => __('Edit Own Leads', 'opbcrm'),
			'edit_others_leads' => __('Edit Others\' Leads', 'opbcrm'),

			'delete_own_leads'  => __('Delete Own Leads', 'opbcrm'),
			'delete_others_leads' => __('Delete Others\' Leads', 'opbcrm'),
			
			'change_lead_stage' => __('Change Lead Stage', 'opbcrm'),
			'assign_leads'      => __('Assign Leads to Others', 'opbcrm'),
			'add_lead_comments' => __('Add Comments to Leads', 'opbcrm'),
			
			'manage_crm_settings' => __('Manage CRM Settings (Stages, Permissions, etc.)', 'opbcrm'),
			'view_lead_history' => __('View Lead History (Timeline)', 'opbcrm'),
			'view_agent_comment' => __('View Agent Comment', 'opbcrm'),
			'edit_agent_comment' => __('Edit Agent Comment', 'opbcrm'),
			'delete_agent_comment' => __('Delete Agent Comment', 'opbcrm'),
			'view_lead_source' => __('View Lead Source', 'opbcrm'),
			'edit_lead_source' => __('Edit Lead Source', 'opbcrm'),
			'delete_lead_source' => __('Delete Lead Source', 'opbcrm'),
			'view_developer' => __('View Developer', 'opbcrm'),
			'edit_developer' => __('Edit Developer', 'opbcrm'),
			'delete_developer' => __('Delete Developer', 'opbcrm'),
			'view_project' => __('View Project', 'opbcrm'),
			'edit_project' => __('Edit Project', 'opbcrm'),
			'delete_project' => __('Delete Project', 'opbcrm'),
			'view_bedrooms' => __('View Bedrooms', 'opbcrm'),
			'edit_bedrooms' => __('Edit Bedrooms', 'opbcrm'),
			'delete_bedrooms' => __('Delete Bedrooms', 'opbcrm'),
			'view_property_type' => __('View Property Type', 'opbcrm'),
			'edit_property_type' => __('Edit Property Type', 'opbcrm'),
			'delete_property_type' => __('Delete Property Type', 'opbcrm'),
			'view_location' => __('View Location', 'opbcrm'),
			'edit_location' => __('Edit Location', 'opbcrm'),
			'delete_location' => __('Delete Location', 'opbcrm'),
			'view_whatsapp' => __('View WhatsApp', 'opbcrm'),
			'edit_whatsapp' => __('Edit WhatsApp', 'opbcrm'),
			'delete_whatsapp' => __('Delete WhatsApp', 'opbcrm'),
			'view_create_date' => __('View Create Date', 'opbcrm'),
			'edit_create_date' => __('Edit Create Date', 'opbcrm'),
			'delete_create_date' => __('Delete Create Date', 'opbcrm'),
			'view_modify_date' => __('View Modify Date', 'opbcrm'),
			'edit_modify_date' => __('Edit Modify Date', 'opbcrm'),
			'delete_modify_date' => __('Delete Modify Date', 'opbcrm'),
			'view_campaigns' => __('View Campaigns', 'opbcrm'),
			'add_campaigns' => __('Add Campaigns', 'opbcrm'),
			'edit_campaigns' => __('Edit Campaigns', 'opbcrm'),
			'archive_campaigns' => __('Archive Campaigns', 'opbcrm'),
			'view_deals' => __('View Deals', 'opbcrm'),
			'add_deals' => __('Add Deals', 'opbcrm'),
			'edit_deals' => __('Edit Deals', 'opbcrm'),
			'delete_deals' => __('Delete Deals', 'opbcrm'),
			'view_deal_value' => __('View Deal Value', 'opbcrm'),
			'edit_deal_value' => __('Edit Deal Value', 'opbcrm'),
			'view_deal_stage' => __('View Deal Stage', 'opbcrm'),
			'edit_deal_stage' => __('Edit Deal Stage', 'opbcrm'),
			'view_deal_owner' => __('View Deal Owner', 'opbcrm'),
			'edit_deal_owner' => __('Edit Deal Owner', 'opbcrm'),
		];
	}

	/**
	 * Add all core capabilities to the Administrator role upon plugin activation.
	 * This ensures the admin can always manage the CRM.
	 */
	public static function add_caps_to_admin() {
		$admin_role = get_role('administrator');
		if (!empty($admin_role)) {
			foreach (array_keys(self::get_all_crm_capabilities()) as $cap) {
				$admin_role->add_cap($cap);
			}
		}
	}


	/**
	 * Remove all CRM capabilities from all roles.
	 * This is used on plugin deactivation to clean up the database.
	 */
	public static function remove_all_crm_caps() {
		$all_caps = array_keys(self::get_all_crm_capabilities());
		
		global $wp_roles;
		foreach (array_keys($wp_roles->roles) as $role_name) {
			$role = get_role($role_name);
			if (!empty($role)) {
				foreach ($all_caps as $cap) {
					$role->remove_cap($cap);
				}
			}
		}
	}

	/**
	 * Remove custom roles on deactivation.
	 */
	public static function remove_custom_roles() {
		remove_role('crm_manager');
		remove_role('crm_agent');
	}

	/**
	 * Remove all CRM caps and custom roles on plugin deactivation.
	 */
	public static function remove_roles() {
		self::remove_all_crm_caps();
		self::remove_custom_roles();
	}
}

new OPBCRM_Roles(); 