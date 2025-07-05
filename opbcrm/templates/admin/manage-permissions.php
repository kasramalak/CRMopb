<?php
/**
 * Template for Permissions Management page.
 */

if (!defined('WPINC')) {
    die;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['opbcrm_user_id'])) {
    wp_redirect(site_url('/crm-login'));
    exit;
}

global $wp_roles;
$all_roles = $wp_roles->roles;
$crm_caps = OPBCRM_Roles::get_all_crm_capabilities();
$editable_roles = apply_filters('editable_roles', $all_roles);

?>
<div class="crm-glass-panel crm-permissions-panel" style="max-width:98vw;margin:0 auto 40px auto;padding:32px 28px 24px 28px;border-radius:22px;box-shadow:0 8px 32px 0 rgba(31,38,135,0.18);backdrop-filter:blur(12px);background:rgba(255,255,255,0.18);border:1.5px solid rgba(255,255,255,0.22);font-family:'Inter',sans-serif;">
    <div class="crm-header-row" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
        <span class="crm-title" style="font-size:2.1rem;font-weight:700;letter-spacing:-1px;">Permissions Management</span>
        <span class="crm-desc" style="font-size:1.1rem;color:#666;font-weight:400;">Assign CRM capabilities to different user roles.</span>
    </div>
    <div class="crm-toolbar-row" style="display:flex;gap:18px;align-items:center;margin-bottom:18px;">
        <input type="text" id="crm-role-search" class="crm-input" placeholder="Search roles..." style="min-width:180px;font-size:14px;padding:7px 14px;border-radius:10px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.5);">
        <input type="text" id="crm-cap-search" class="crm-input" placeholder="Search capabilities..." style="min-width:180px;font-size:14px;padding:7px 14px;border-radius:10px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.5);">
    </div>
    <div class="crm-add-role-glass" style="background:rgba(255,255,255,0.32);border:1px solid #e5e5e5;padding:16px 20px;margin-bottom:22px;border-radius:13px;max-width:600px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);">
        <form id="opbcrm-add-role-form" method="post" style="display:flex;gap:15px;align-items:flex-end;">
            <div class="floating-label-group">
                <input type="text" id="new_role_key" name="new_role_key" required pattern="[a-zA-Z0-9_]+" maxlength="32" class="crm-input" placeholder=" " style="font-size:14px;">
                <label for="new_role_key">Role Key (English, no spaces)</label>
            </div>
            <div class="floating-label-group">
                <input type="text" id="new_role_name" name="new_role_name" required maxlength="40" class="crm-input" placeholder=" " style="font-size:14px;">
                <label for="new_role_name">Display Name</label>
            </div>
            <div>
                <button type="submit" class="crm-btn" style="font-size:15px;padding:7px 18px;">+ Add Role</button>
            </div>
            <?php wp_nonce_field('opbcrm_add_role_nonce', '_wpnonce_add_role'); ?>
        </form>
        <div id="opbcrm-add-role-msg" style="margin-top:10px;color:#b00;display:none;"></div>
    </div>
    <form id="opbcrm-permissions-form" method="post" style="margin-bottom:0;">
        <input type="hidden" name="action" value="opbcrm_save_permissions">
        <?php wp_nonce_field('opbcrm_save_permissions_nonce'); ?>
        <div class="crm-table-wrap" style="overflow-x:auto;max-width:100vw;">
        <table class="crm-table crm-permissions-table" style="min-width:900px;font-size:13px;border-radius:12px;overflow:hidden;">
            <thead style="position:sticky;top:0;background:rgba(255,255,255,0.85);z-index:2;">
                <tr>
                    <th class="crm-th-role" style="min-width:120px;">Role</th>
                    <?php foreach ($crm_caps as $cap_key => $cap_name) : ?>
                        <th class="crm-th-cap" style="min-width:120px;">
                            <span class="crm-cap-badge" style="background:#f3f6fa;color:#1a1a1a;padding:2px 10px;border-radius:8px;font-size:12px;font-weight:500;display:inline-block;"> <?php echo esc_html($cap_name); ?> </span>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php foreach ($editable_roles as $role_key => $role_details) : ?>
                    <?php if ('administrator' === $role_key) continue; ?>
                    <tr id="role-<?php echo esc_attr($role_key); ?>">
                        <td class="role-name">
                            <span class="crm-role-badge" style="background:#007bff;color:#fff;padding:3px 12px;border-radius:12px;font-size:13px;font-weight:600;letter-spacing:0.5px;box-shadow:0 1px 4px 0 rgba(31,38,135,0.07);">
                                <?php echo esc_html($role_details['name']); ?>
                            </span>
                        </td>
                        <?php foreach ($crm_caps as $cap_key => $cap_name) : ?>
                            <td class="capability" style="text-align:center;">
                                <?php
                                $role_obj = get_role($role_key);
                                $has_cap = !empty($role_obj) && $role_obj->has_cap($cap_key);
                                ?>
                                <input type="checkbox"
                                       name="role_caps[<?php echo esc_attr($role_key); ?>][<?php echo esc_attr($cap_key); ?>]"
                                       id="cap-<?php echo esc_attr($role_key . '-' . $cap_key); ?>"
                                       value="1"
                                       <?php checked($has_cap); ?>
                                       class="crm-perm-checkbox"
                                >
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th class="crm-th-role">Role</th>
                    <?php foreach ($crm_caps as $cap_key => $cap_name) : ?>
                        <th class="crm-th-cap">
                            <span class="crm-cap-badge"> <?php echo esc_html($cap_name); ?> </span>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </tfoot>
        </table>
        </div>
        <p class="submit" style="margin-top:18px;">
            <button type="submit" name="submit" id="submit" class="crm-btn" style="font-size:15px;padding:7px 22px;">Save Permissions</button>
            <span class="spinner"></span>
        </p>
    </form>
    <div id="crm-toast" style="display:none;position:fixed;bottom:32px;right:32px;z-index:9999;min-width:220px;padding:14px 22px;border-radius:12px;background:rgba(30,30,30,0.97);color:#fff;font-size:15px;font-weight:500;box-shadow:0 2px 12px 0 rgba(31,38,135,0.13);text-align:center;letter-spacing:0.2px;opacity:0.98;transition:all 0.3s;pointer-events:none;">Saved!</div>
</div> 