<?php
/**
 * Bitrix-style Add New Lead modal with full fields and modern UI.
 */
if (!defined('WPINC')) { die; }

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['opbcrm_user_id'])) {
    wp_redirect(site_url('/crm-login'));
    exit;
}

// Get available stages
$stage_groups = get_option('opbcrm_lead_stages', []);
$all_stages = array_merge($stage_groups['initial'] ?? [], $stage_groups['additional'] ?? []);

// Get custom fields
$custom_fields = [];
global $wpdb;
$cf_table = $wpdb->prefix . 'opbcrm_custom_fields';
if ($wpdb->get_var("SHOW TABLES LIKE '$cf_table'") === $cf_table) {
    $custom_fields = $wpdb->get_results("SELECT * FROM $cf_table ORDER BY field_label ASC");
}
?>
<div id="add-new-lead-panel" class="opbcrm-offcanvas-panel glassmorphism hidden fixed top-0 right-0 h-full w-full max-w-lg z-50 transition-transform duration-300 transform translate-x-full shadow-2xl backdrop-blur-lg bg-gradient-to-br from-gray-900/90 to-gray-800/80 border-l border-gray-700">
    <div class="flex flex-col h-full">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-700">
            <h3 class="text-xl font-bold text-white flex items-center gap-2"><i class="fas fa-user-plus text-accent"></i> <?php _e('Add New Lead', 'opbcrm'); ?></h3>
            <button class="opbcrm-offcanvas-close text-3xl text-gray-400 hover:text-accent transition">&times;</button>
        </div>
        <div class="flex-1 overflow-y-auto px-6 py-4">
            <form id="add-new-lead-form" class="space-y-4">
                <?php wp_nonce_field('opbcrm_add_lead_nonce', '_wpnonce_add_lead'); ?>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="lead-name" class="text-gray-300 font-semibold flex items-center gap-2"><i class="fas fa-user"></i> <?php _e('Lead Name', 'opbcrm'); ?> <span class="text-accent">*</span></label>
                        <input type="text" id="lead-name" name="lead_name" required class="opbcrm-input" autocomplete="off">
                    </div>
                    <div>
                        <label for="lead-phone" class="text-gray-300 font-semibold flex items-center gap-2"><i class="fas fa-phone"></i> <?php _e('Phone Number', 'opbcrm'); ?> <span class="text-accent">*</span></label>
                        <input type="tel" id="lead-phone" name="lead_phone" required class="opbcrm-input">
                    </div>
                    <div>
                        <label for="lead-email" class="text-gray-300 font-semibold flex items-center gap-2"><i class="fas fa-envelope"></i> <?php _e('Email Address', 'opbcrm'); ?></label>
                        <input type="email" id="lead-email" name="lead_email" class="opbcrm-input">
                    </div>
                    <div>
                        <label for="agent_id" class="text-gray-300 font-semibold flex items-center gap-2"><i class="fas fa-user-tie"></i> <?php _e('Agent (Responsible)', 'opbcrm'); ?> <span class="text-accent">*</span></label>
                        <select id="agent_id" name="agent_id" required class="opbcrm-input">
                            <option value=""><?php _e('Select Agent', 'opbcrm'); ?></option>
                        </select>
                    </div>
                    <div>
                        <label for="lead-stage" class="text-gray-300 font-semibold flex items-center gap-2"><i class="fas fa-flag"></i> <?php _e('Stage', 'opbcrm'); ?> <span class="text-accent">*</span></label>
                        <select id="lead-stage" name="lead_stage" required class="opbcrm-input">
                            <option value=""><?php _e('Select Stage', 'opbcrm'); ?></option>
                            <?php foreach ($all_stages as $stage): ?>
                                <option value="<?php echo esc_attr($stage['id']); ?>" <?php echo ($stage['label'] === 'Fresh Leads') ? 'selected' : ''; ?>><?php echo esc_html($stage['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="lead-substage-group" class="hidden">
                        <label for="lead-sub-stage" class="text-gray-300 font-semibold flex items-center gap-2"><i class="fas fa-list"></i> <?php _e('Sub-Stage', 'opbcrm'); ?> <span class="text-accent" id="substage-required" style="display:none;">*</span></label>
                        <select id="lead-sub-stage" name="lead_sub_stage" class="opbcrm-input"></select>
                    </div>
                    <div>
                        <label for="lead-source" class="text-gray-300 font-semibold flex items-center gap-2"><i class="fas fa-globe"></i> <?php _e('Source', 'opbcrm'); ?></label>
                        <input type="text" id="lead-source" name="lead_source" placeholder="e.g., Website, Instagram, Referral" class="opbcrm-input">
                    </div>
                    <div>
                        <label for="lead-tags" class="text-gray-300 font-semibold flex items-center gap-2"><i class="fas fa-tags"></i> <?php _e('Tags', 'opbcrm'); ?></label>
                        <input type="text" id="lead-tags" name="lead_tags" placeholder="e.g., VIP, Hot, Investor" class="opbcrm-input">
                    </div>
                    <div>
                        <label for="lead-campaign" class="text-gray-300 font-semibold flex items-center gap-2"><i class="fas fa-bullhorn"></i> <?php _e('Campaign', 'opbcrm'); ?></label>
                        <input type="text" id="lead-campaign" name="lead_campaign" placeholder="e.g., Ramadan Promo, Facebook Ad" class="opbcrm-input">
                    </div>
                    <?php if (!empty($custom_fields)): ?>
                        <?php foreach ($custom_fields as $field): ?>
                            <div>
                                <label for="cf_<?php echo esc_attr($field->field_name); ?>" class="text-gray-300 font-semibold flex items-center gap-2"><?php echo esc_html($field->field_label); ?></label>
                                <?php if ($field->field_type === 'select'): ?>
                                    <select id="cf_<?php echo esc_attr($field->field_name); ?>" name="cf_<?php echo esc_attr($field->field_name); ?>" class="opbcrm-input">
                                        <?php foreach (explode("\n", $field->field_options) as $option): ?>
                                            <option value="<?php echo esc_attr(trim($option)); ?>"><?php echo esc_html(trim($option)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="text" id="cf_<?php echo esc_attr($field->field_name); ?>" name="cf_<?php echo esc_attr($field->field_name); ?>" class="opbcrm-input">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="mt-6 flex justify-end gap-4">
                    <button type="submit" class="opbcrm-btn-primary px-6 py-2 rounded-lg font-bold text-white bg-accent hover:bg-accent-dark shadow-lg transition"><i class="fas fa-save"></i> <?php _e('Save Lead', 'opbcrm'); ?></button>
                    <span class="spinner"></span>
                </div>
            </form>
        </div>
    </div>
</div>
<?php /* Add intl-tel-input initialization for the phone field */ ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.intlTelInput) {
        var input = document.querySelector('#lead-phone');
        if (input) {
            window.intlTelInput(input, {
                initialCountry: 'auto',
                preferredCountries: ['us','gb','ae','ca','in','ru','de','fr','it','es','tr','sa','cn','br','za','au','pk','eg','id','ir'], // Top countries, rest are available
                utilsScript: '
    // Sub-stage logic
    var stageSelect = document.getElementById('lead-stage');
    var substageGroup = document.getElementById('lead-substage-group');
    var substageSelect = document.getElementById('lead-sub-stage');
    var substageRequired = document.getElementById('substage-required');
    var substages = {
        'hold_call_again': ['Switch Off', 'No Answer', 'Call Later', 'Call Rejected'],
        'disqualified': ["Don't Call Again", 'Not interested', 'Register By Mistake', 'Wrong Number/Contact', "He Didn't Register", 'Agent/Broker', 'Job Seeker', 'invalid/Unreachable Number', 'Never Answer', 'Low Budget', 'Stop Answering', 'Already Bought/ Investor', '-None-', 'Long Future Prospect'],
        'follow_up': ['Need More Time', 'Short Future Prospect', 'Low Budget']
    };
    function updateSubstage() {
        var val = stageSelect.value;
        if (substages[val]) {
            substageGroup.style.display = '';
            substageSelect.innerHTML = '<option value="">Select Sub-Stage</option>' + substages[val].map(function(opt){return '<option value="'+opt+'">'+opt+'</option>';}).join('');
            substageRequired.style.display = '';
            substageSelect.required = true;
        } else {
            substageGroup.style.display = 'none';
            substageSelect.innerHTML = '';
            substageRequired.style.display = 'none';
            substageSelect.required = false;
        }
    }
    stageSelect.addEventListener('change', updateSubstage);
    updateSubstage(); // Run on load in case of edit
});
</script> 