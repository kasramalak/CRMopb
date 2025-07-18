<?php
/**
 * Template for Lead Stages Management page.
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

// Nonce for security
$nonce = wp_create_nonce('opbcrm_save_stages_nonce');

// Get saved stages from the database
$saved_stages = get_option('opbcrm_lead_stages', [
    'initial' => [],
    'additional' => [],
    'success' => [],
    'failed' => []
]);
?>
<div class="crm-glass-panel crm-stages-panel" style="max-width:98vw;margin:0 auto 40px auto;padding:32px 28px 24px 28px;border-radius:22px;box-shadow:0 8px 32px 0 rgba(31,38,135,0.18);backdrop-filter:blur(12px);background:rgba(255,255,255,0.18);border:1.5px solid rgba(255,255,255,0.22);font-family:'Inter',sans-serif;">
    <div class="crm-header-row" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
        <span class="crm-title" style="font-size:2.1rem;font-weight:700;letter-spacing:-1px;">Manage Lead Stages</span>
        <span class="crm-desc" style="font-size:1.1rem;color:#666;font-weight:400;">Drag and drop to reorder stages. Click on a stage to edit its name and color.</span>
    </div>
    <form id="opbcrm-stages-form" method="POST" style="margin-bottom:0;">
        <input type="hidden" name="action" value="opbcrm_save_lead_stages">
        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
        <div class="stages-container" style="display:flex;gap:32px;flex-wrap:wrap;">
            <div class="funnel-preview-container glassy-panel" style="background:rgba(255,255,255,0.32);border:1px solid #e5e5e5;padding:18px 22px;border-radius:13px;min-width:260px;max-width:340px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);flex:1;">
                <h3 style="font-size:1.2rem;font-weight:600;margin-bottom:10px;">Funnel Preview</h3>
                <div class="funnel" id="funnel-preview" style="display:flex;gap:7px;align-items:center;min-height:38px;">
                    <!-- Funnel will be generated by JS -->
                </div>
            </div>
            <div class="stage-groups-container" style="display:flex;gap:32px;flex:3;flex-wrap:wrap;">
                <div class="stage-group glassy-panel" style="background:rgba(255,255,255,0.32);border:1px solid #e5e5e5;padding:18px 22px;border-radius:13px;min-width:260px;max-width:340px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);flex:1;">
                    <h2 style="font-size:1.1rem;font-weight:600;">Initial Stage</h2>
                    <p class="description" style="font-size:13px;color:#888;">This is the first stage for all new leads.</p>
                    <div id="initial-stage-list" class="stage-list" data-group="initial">
                        <?php
                        if (!empty($saved_stages['initial'])) {
                            foreach ($saved_stages['initial'] as $stage) {
                                echo opbcrm_render_stage_item($stage['id'], $stage['label'], $stage['color']);
                            }
                        }
                        ?>
                    </div>
                    <button type="button" class="crm-btn add-stage-btn" data-group="initial" style="font-size:14px;padding:6px 16px;margin-top:10px;">+ Add Stage</button>
                </div>
                <div class="stage-group glassy-panel" style="background:rgba(255,255,255,0.32);border:1px solid #e5e5e5;padding:18px 22px;border-radius:13px;min-width:260px;max-width:340px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);flex:1;">
                    <h2 style="font-size:1.1rem;font-weight:600;">Additional Stages</h2>
                    <p class="description" style="font-size:13px;color:#888;">These are the intermediate steps in your sales process.</p>
                    <div id="additional-stages-list" class="stage-list" data-group="additional">
                        <?php
                        if (!empty($saved_stages['additional'])) {
                            foreach ($saved_stages['additional'] as $stage) {
                                echo opbcrm_render_stage_item($stage['id'], $stage['label'], $stage['color']);
                            }
                        }
                        ?>
                    </div>
                    <button type="button" class="crm-btn add-stage-btn" data-group="additional" style="font-size:14px;padding:6px 16px;margin-top:10px;">+ Add Stage</button>
                </div>
                <div class="final-stages" style="display:flex;gap:32px;flex-wrap:wrap;">
                    <div class="stage-group glassy-panel success-group" style="background:rgba(40,180,80,0.13);border:1px solid #b2e5c7;padding:18px 22px;border-radius:13px;min-width:220px;max-width:300px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);flex:1;">
                        <h2 class="success-title" style="font-size:1.1rem;font-weight:600;color:#28a745;">Success Stage</h2>
                        <div id="success-stage-list" class="stage-list" data-group="success">
                            <?php
                            if (!empty($saved_stages['success'])) {
                                foreach ($saved_stages['success'] as $stage) {
                                    echo opbcrm_render_stage_item($stage['id'], $stage['label'], $stage['color'], false); // Not editable
                                }
                            } else {
                                // Default won stage if empty
                                echo opbcrm_render_stage_item('won_deal', 'Won Deal', '#28a745', false);
                            }
                            ?>
                        </div>
                    </div>
                    <div class="stage-group glassy-panel failed-group" style="background:rgba(52,58,64,0.13);border:1px solid #b2b2b2;padding:18px 22px;border-radius:13px;min-width:220px;max-width:300px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);flex:1;">
                        <h2 class="failed-title" style="font-size:1.1rem;font-weight:600;color:#343a40;">Failed Stage</h2>
                        <div id="failed-stage-list" class="stage-list" data-group="failed">
                            <?php
                             if (!empty($saved_stages['failed'])) {
                                foreach ($saved_stages['failed'] as $stage) {
                                    echo opbcrm_render_stage_item($stage['id'], $stage['label'], $stage['color'], false); // Not editable
                                }
                            } else {
                                // Default lost stage if empty
                                echo opbcrm_render_stage_item('close_lead', 'Close Lead', '#343a40', false);
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <p class="submit" style="margin-top:18px;">
            <button type="submit" name="submit" id="submit" class="crm-btn" style="font-size:15px;padding:7px 22px;">Save Changes</button>
            <span class="spinner"></span>
        </p>
    </form>
    <div id="crm-toast" style="display:none;position:fixed;bottom:32px;right:32px;z-index:9999;min-width:220px;padding:14px 22px;border-radius:12px;background:rgba(30,30,30,0.97);color:#fff;font-size:15px;font-weight:500;box-shadow:0 2px 12px 0 rgba(31,38,135,0.13);text-align:center;letter-spacing:0.2px;opacity:0.98;transition:all 0.3s;pointer-events:none;">Saved!</div>
</div>

<?php
/**
 * Helper function to render a single stage item.
 *
 * @param string $id
 * @param string $label
 * @param string $color
 * @param bool $is_editable
 * @return string
 */
function opbcrm_render_stage_item($id, $label, $color, $is_editable = true) {
    ob_start();
    ?>
    <div class="stage-item" data-id="<?php echo esc_attr($id); ?>" style="border-left-color: <?php echo esc_attr($color); ?>">
        <div class="stage-item-content">
            <span class="stage-label"><?php echo esc_html($label); ?></span>
            <input type="text" class="stage-label-input" value="<?php echo esc_attr($label); ?>" style="display:none;">
            <input type="color" class="stage-color-input" value="<?php echo esc_attr($color); ?>" style="display:none;">
        </div>
        <div class="stage-item-actions">
            <?php if ($is_editable) : ?>
                <button type="button" class="button button-small edit-stage-btn"><?php _e('Edit', 'opbcrm'); ?></button>
                <button type="button" class="button button-small save-stage-btn" style="display:none;"><?php _e('Save', 'opbcrm'); ?></button>
                <button type="button" class="button button-link-delete delete-stage-btn"><?php _e('Delete', 'opbcrm'); ?></button>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script> 