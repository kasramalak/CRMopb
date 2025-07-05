<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['opbcrm_user_id'])) {
    wp_redirect(site_url('/crm-login'));
    exit;
}
?>
<style>
@media (max-width: 900px) {
  .crm-reporting-panel { padding: 18px 6vw 12px 6vw !important; }
  .crm-reporting-widgets { gap: 18px !important; }
  .crm-report-widget-wrap { min-width: 180px !important; max-width: 100% !important; }
  .crm-report-widget { padding: 12px 10px 12px 10px !important; }
  .crm-report-chip { font-size: 12px !important; padding: 4px 10px !important; }
}
@media (max-width: 600px) {
  .crm-reporting-panel { padding: 8px 2vw 6px 2vw !important; }
  .crm-header-row { flex-direction: column !important; gap: 6px !important; }
  .crm-reporting-toolbar { flex-direction: column !important; gap: 8px !important; }
  #crm-report-chips-row { flex-wrap: wrap !important; gap: 6px !important; }
  .crm-reporting-widgets { flex-direction: column !important; gap: 12px !important; }
  .crm-report-widget-wrap { min-width: 100% !important; max-width: 100% !important; }
  .crm-report-widget { padding: 8px 4px 8px 4px !important; font-size: 12px !important; }
  .crm-report-chip { font-size: 11px !important; padding: 3px 7px !important; }
  .crm-report-export-btn { font-size: 12px !important; padding: 2px 7px !important; }
}
</style>
<div class="crm-glass-panel crm-reporting-panel" style="max-width:99vw;margin:0 auto 40px auto;padding:32px 28px 24px 28px;border-radius:22px;box-shadow:0 8px 32px 0 rgba(31,38,135,0.18);backdrop-filter:blur(12px);background:rgba(255,255,255,0.18);border:1.5px solid rgba(255,255,255,0.22);font-family:'Inter',sans-serif;">
  <div class="crm-header-row" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
    <span class="crm-title" style="font-size:2.1rem;font-weight:700;letter-spacing:-1px;">CRM Reporting & Analytics</span>
    <span class="crm-desc" style="font-size:1.1rem;color:#666;font-weight:400;">Analyze agent performance, lead sources, and campaign success.</span>
  </div>
  <div class="crm-reporting-toolbar" style="display:flex;gap:18px;align-items:center;margin-bottom:18px;flex-wrap:wrap;">
    <select id="crm-report-agent" class="crm-input" style="min-width:180px;font-size:14px;padding:7px 14px;border-radius:10px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.5);">
      <option value="">All Agents</option>
      <?php foreach (get_users(['role__in'=>['administrator','crm_manager','crm_agent']]) as $agent): ?>
        <option value="<?php echo esc_attr($agent->ID); ?>"><?php echo esc_html($agent->display_name); ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" id="crm-report-date-from" class="crm-input" style="font-size:14px;padding:7px 14px;border-radius:10px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.5);">
    <input type="date" id="crm-report-date-to" class="crm-input" style="font-size:14px;padding:7px 14px;border-radius:10px;border:1px solid #e0e0e0;background:rgba(255,255,255,0.5);">
    <button class="crm-btn" id="crm-report-refresh" style="font-size:15px;padding:7px 18px;">Refresh</button>
  </div>
  <div id="crm-report-chips-row" style="display:flex;gap:10px;align-items:center;margin-bottom:18px;">
    <button class="crm-report-chip active" data-range="all" style="font-family:'Inter',sans-serif;font-size:13px;padding:5px 16px;border-radius:18px;background:rgba(255,255,255,0.7);border:1px solid #e0e0e0;color:#007bff;font-weight:500;box-shadow:0 1px 4px 0 rgba(31,38,135,0.04);cursor:pointer;transition:background 0.2s;">All Time</button>
    <button class="crm-report-chip" data-range="month" style="font-family:'Inter',sans-serif;font-size:13px;padding:5px 16px;border-radius:18px;background:rgba(255,255,255,0.7);border:1px solid #e0e0e0;color:#007bff;font-weight:500;box-shadow:0 1px 4px 0 rgba(31,38,135,0.04);cursor:pointer;transition:background 0.2s;">This Month</button>
    <button class="crm-report-chip" data-range="30d" style="font-family:'Inter',sans-serif;font-size:13px;padding:5px 16px;border-radius:18px;background:rgba(255,255,255,0.7);border:1px solid #e0e0e0;color:#007bff;font-weight:500;box-shadow:0 1px 4px 0 rgba(31,38,135,0.04);cursor:pointer;transition:background 0.2s;">Last 30 Days</button>
    <button class="crm-report-chip" data-range="year" style="font-family:'Inter',sans-serif;font-size:13px;padding:5px 16px;border-radius:18px;background:rgba(255,255,255,0.7);border:1px solid #e0e0e0;color:#007bff;font-weight:500;box-shadow:0 1px 4px 0 rgba(31,38,135,0.04);cursor:pointer;transition:background 0.2s;">This Year</button>
  </div>
  <div class="crm-reporting-filters-row" style="display:flex;align-items:center;gap:18px;margin-bottom:18px;">
    <div class="crm-filter-group">
      <label for="report-campaign-filter" style="font-size:13px;font-family:'Inter',sans-serif;color:#333;font-weight:500;margin-right:7px;">Campaign:</label>
      <select id="report-campaign-filter" class="crm-filter-select" style="font-size:13px;padding:5px 18px 5px 8px;border-radius:8px;background:rgba(255,255,255,0.7);border:1.2px solid #e0e0e0;font-family:'Inter',sans-serif;min-width:120px;">
        <option value="">All Campaigns</option>
        <?php
        global $wpdb;
        $campaigns = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'lead_campaign' AND meta_value != '' ORDER BY meta_value ASC");
        foreach ($campaigns as $c) {
          echo '<option value="'.esc_attr($c).'">'.esc_html($c).'</option>';
        }
        ?>
      </select>
    </div>
  </div>
  <div class="crm-reporting-widgets-row" style="display:flex;flex-wrap:wrap;gap:28px;margin-bottom:32px;">
    <div class="crm-widget glassy-panel" style="flex:1;min-width:320px;max-width:420px;padding:22px 18px 18px 18px;border-radius:16px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.10);backdrop-filter:blur(8px);background:rgba(255,255,255,0.22);border:1.2px solid rgba(255,255,255,0.18);font-family:'Inter',sans-serif;">
      <div class="crm-widget-title-row" style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
        <i class="fas fa-percentage" style="color:#4b68b6;"></i>
        <span class="crm-widget-title" style="font-size:1.08rem;font-weight:600;">Campaign Conversion Rate</span>
      </div>
      <canvas id="campaign-conversion-chart" height="180"></canvas>
      <div class="crm-widget-footer" style="font-size:12px;color:#888;margin-top:8px;">% of leads per campaign that reached 'Won Deal'</div>
    </div>
    <div class="crm-report-widget-wrap" style="position:relative;flex:1;min-width:220px;max-width:320px;">
      <div class="crm-report-widget glassy-panel" style="background:rgba(255,255,255,0.32);border:1px solid #e5e5e5;padding:18px 22px 18px 22px;border-radius:13px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);position:relative;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">
          <div style="font-size:1.1rem;font-weight:600;">Total Leads</div>
          <?php if (current_user_can('export_reports')): ?>
            <button class="crm-btn crm-report-export-btn" data-report-type="total" title="Export CSV" style="background:rgba(255,255,255,0.7);border:1px solid #e0e0e0;color:#007bff;font-size:14px;padding:3px 12px;border-radius:8px;box-shadow:0 1px 4px 0 rgba(31,38,135,0.04);font-family:'Inter',sans-serif;display:flex;align-items:center;gap:6px;"><i class="fas fa-download"></i> Export</button>
          <?php endif; ?>
        </div>
        <div id="crm-report-total-leads" style="font-size:2.2rem;font-weight:700;margin-top:8px;">0</div>
      </div>
      <div class="crm-report-loading" style="display:none;position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.55);backdrop-filter:blur(6px);border-radius:13px;z-index:2;align-items:center;justify-content:center;flex-direction:column;font-family:'Inter',sans-serif;font-size:15px;font-weight:500;color:#333;"><i class="fas fa-spinner fa-spin" style="font-size:22px;margin-bottom:8px;"></i>Loading...</div>
    </div>
    <div class="crm-report-widget-wrap" style="position:relative;flex:2;min-width:320px;max-width:520px;">
      <div class="crm-report-widget glassy-panel" style="background:rgba(255,255,255,0.32);border:1px solid #e5e5e5;padding:18px 22px 18px 22px;border-radius:13px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);position:relative;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">
          <div style="font-size:1.1rem;font-weight:600;">Leads by Stage (%)</div>
          <?php if (current_user_can('export_reports')): ?>
            <button class="crm-btn crm-report-export-btn" data-report-type="stage" title="Export CSV" style="background:rgba(255,255,255,0.7);border:1px solid #e0e0e0;color:#007bff;font-size:14px;padding:3px 12px;border-radius:8px;box-shadow:0 1px 4px 0 rgba(31,38,135,0.04);font-family:'Inter',sans-serif;display:flex;align-items:center;gap:6px;"><i class="fas fa-download"></i> Export</button>
          <?php endif; ?>
        </div>
        <canvas id="crm-report-stage-pie" height="120"></canvas>
      </div>
      <div class="crm-report-loading" style="display:none;position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.55);backdrop-filter:blur(6px);border-radius:13px;z-index:2;align-items:center;justify-content:center;flex-direction:column;font-family:'Inter',sans-serif;font-size:15px;font-weight:500;color:#333;"><i class="fas fa-spinner fa-spin" style="font-size:22px;margin-bottom:8px;"></i>Loading...</div>
    </div>
    <div class="crm-report-widget-wrap" style="position:relative;flex:2;min-width:320px;max-width:520px;">
      <div class="crm-report-widget glassy-panel" style="background:rgba(255,255,255,0.32);border:1px solid #e5e5e5;padding:18px 22px 18px 22px;border-radius:13px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);position:relative;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">
          <div style="font-size:1.1rem;font-weight:600;">Source Breakdown</div>
          <?php if (current_user_can('export_reports')): ?>
            <button class="crm-btn crm-report-export-btn" data-report-type="source" title="Export CSV" style="background:rgba(255,255,255,0.7);border:1px solid #e0e0e0;color:#007bff;font-size:14px;padding:3px 12px;border-radius:8px;box-shadow:0 1px 4px 0 rgba(31,38,135,0.04);font-family:'Inter',sans-serif;display:flex;align-items:center;gap:6px;"><i class="fas fa-download"></i> Export</button>
          <?php endif; ?>
        </div>
        <canvas id="crm-report-source-bar" height="120"></canvas>
      </div>
      <div class="crm-report-loading" style="display:none;position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.55);backdrop-filter:blur(6px);border-radius:13px;z-index:2;align-items:center;justify-content:center;flex-direction:column;font-family:'Inter',sans-serif;font-size:15px;font-weight:500;color:#333;"><i class="fas fa-spinner fa-spin" style="font-size:22px;margin-bottom:8px;"></i>Loading...</div>
    </div>
    <div class="crm-report-widget-wrap" style="position:relative;flex:2;min-width:320px;max-width:520px;">
      <div class="crm-report-widget glassy-panel" style="background:rgba(255,255,255,0.32);border:1px solid #e5e5e5;padding:18px 22px 18px 22px;border-radius:13px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);position:relative;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">
          <div style="font-size:1.1rem;font-weight:600;">Project Breakdown</div>
          <?php if (current_user_can('export_reports')): ?>
            <button class="crm-btn crm-report-export-btn" data-report-type="project" title="Export CSV" style="background:rgba(255,255,255,0.7);border:1px solid #e0e0e0;color:#007bff;font-size:14px;padding:3px 12px;border-radius:8px;box-shadow:0 1px 4px 0 rgba(31,38,135,0.04);font-family:'Inter',sans-serif;display:flex;align-items:center;gap:6px;"><i class="fas fa-download"></i> Export</button>
          <?php endif; ?>
        </div>
        <canvas id="crm-report-project-bar" height="120"></canvas>
      </div>
      <div class="crm-report-loading" style="display:none;position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.55);backdrop-filter:blur(6px);border-radius:13px;z-index:2;align-items:center;justify-content:center;flex-direction:column;font-family:'Inter',sans-serif;font-size:15px;font-weight:500;color:#333;"><i class="fas fa-spinner fa-spin" style="font-size:22px;margin-bottom:8px;"></i>Loading...</div>
    </div>
    <div class="crm-report-widget-wrap" style="position:relative;flex:2;min-width:320px;max-width:520px;">
      <div class="crm-report-widget glassy-panel" style="background:rgba(255,255,255,0.32);border:1px solid #e5e5e5;padding:18px 22px 18px 22px;border-radius:13px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);position:relative;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">
          <div style="font-size:1.1rem;font-weight:600;">Top Campaigns</div>
          <?php if (current_user_can('export_reports')): ?>
            <button class="crm-btn crm-report-export-btn" data-report-type="campaign" title="Export CSV" style="background:rgba(255,255,255,0.7);border:1px solid #e0e0e0;color:#007bff;font-size:14px;padding:3px 12px;border-radius:8px;box-shadow:0 1px 4px 0 rgba(31,38,135,0.04);font-family:'Inter',sans-serif;display:flex;align-items:center;gap:6px;"><i class="fas fa-download"></i> Export</button>
          <?php endif; ?>
        </div>
        <canvas id="crm-report-campaign-bar" height="120"></canvas>
      </div>
      <div class="crm-report-loading" style="display:none;position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.55);backdrop-filter:blur(6px);border-radius:13px;z-index:2;align-items:center;justify-content:center;flex-direction:column;font-family:'Inter',sans-serif;font-size:15px;font-weight:500;color:#333;"><i class="fas fa-spinner fa-spin" style="font-size:22px;margin-bottom:8px;"></i>Loading...</div>
    </div>
    <div class="crm-report-widget-wrap" style="position:relative;flex:2;min-width:320px;max-width:520px;">
      <div class="crm-report-widget glassy-panel" style="background:rgba(255,255,255,0.32);border:1px solid #e5e5e5;padding:18px 22px 18px 22px;border-radius:13px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.07);position:relative;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">
          <div style="font-size:1.1rem;font-weight:600;">Agent Performance</div>
          <?php if (current_user_can('export_reports')): ?>
            <button class="crm-btn crm-report-export-btn" data-report-type="agent" title="Export CSV" style="background:rgba(255,255,255,0.7);border:1px solid #e0e0e0;color:#007bff;font-size:14px;padding:3px 12px;border-radius:8px;box-shadow:0 1px 4px 0 rgba(31,38,135,0.04);font-family:'Inter',sans-serif;display:flex;align-items:center;gap:6px;"><i class="fas fa-download"></i> Export</button>
          <?php endif; ?>
        </div>
        <canvas id="crm-report-agent-bar" height="120"></canvas>
      </div>
      <div class="crm-report-loading" style="display:none;position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.55);backdrop-filter:blur(6px);border-radius:13px;z-index:2;align-items:center;justify-content:center;flex-direction:column;font-family:'Inter',sans-serif;font-size:15px;font-weight:500;color:#333;"><i class="fas fa-spinner fa-spin" style="font-size:22px;margin-bottom:8px;"></i>Loading...</div>
    </div>
    <div class="crm-widget glassy-panel" style="flex:1;min-width:320px;max-width:420px;padding:22px 18px 18px 18px;border-radius:16px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.10);backdrop-filter:blur(8px);background:rgba(255,255,255,0.22);border:1.2px solid rgba(255,255,255,0.18);font-family:'Inter',sans-serif;">
      <div class="crm-widget-title-row" style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
        <i class="fas fa-dollar-sign" style="color:#4b68b6;"></i>
        <span class="crm-widget-title" style="font-size:1.08rem;font-weight:600;">Campaign ROI</span>
      </div>
      <canvas id="campaign-roi-chart" height="180"></canvas>
      <div class="crm-widget-footer" style="font-size:12px;color:#888;margin-top:8px;">ROI = (Total Won Value − Spend) / Spend × 100%</div>
    </div>
    <div class="crm-widget glassy-panel" style="flex:2;min-width:420px;max-width:700px;padding:22px 18px 18px 18px;border-radius:16px;box-shadow:0 2px 12px 0 rgba(31,38,135,0.10);backdrop-filter:blur(8px);background:rgba(255,255,255,0.22);border:1.2px solid rgba(255,255,255,0.18);font-family:'Inter',sans-serif;">
      <div class="crm-widget-title-row" style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
        <i class="fas fa-chart-line" style="color:#4b68b6;"></i>
        <span class="crm-widget-title" style="font-size:1.08rem;font-weight:600;">Leads per Campaign Over Time</span>
      </div>
      <canvas id="campaign-time-series-chart" height="220"></canvas>
      <div class="crm-widget-footer" style="font-size:12px;color:#888;margin-top:8px;">Monthly trend of leads for each campaign</div>
    </div>
  </div>
</div>
<div class="crm-campaigns-panel glassy-panel" style="max-width:700px;margin:40px auto 0 auto;padding:28px 24px 22px 24px;border-radius:18px;box-shadow:0 4px 24px 0 rgba(31,38,135,0.10);backdrop-filter:blur(10px);background:rgba(255,255,255,0.22);border:1.5px solid rgba(255,255,255,0.22);font-family:'Inter',sans-serif;">
  <div class="crm-header-row" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
    <span class="crm-title" style="font-size:1.3rem;font-weight:700;letter-spacing:-0.5px;">Campaign Management</span>
    <?php if (current_user_can('manage_options')): ?>
    <div class="crm-add-new-campaign-row" style="display:flex;gap:8px;">
        <input type="text" id="new-campaign-name" placeholder="Add New Campaign" class="opbcrm-input" style="font-size:13px;padding: 6px 12px;"/>
        <button id="crm-add-campaign-btn" class="crm-btn crm-btn-primary" style="padding: 6px 16px;font-size:13px;">Add</button>
    </div>
    <?php endif; ?>
  </div>
  <ul id="campaign-list" class="crm-campaign-list" style="list-style:none;padding:0;margin:0;max-height: 400px;overflow-y:auto;">
    <?php
        global $wpdb;
        $campaigns_from_meta = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'lead_campaign' AND meta_value IS NOT NULL AND meta_value != ''");
        $managed_campaigns = get_option('opbcrm_managed_campaigns', []);
        // Build a lookup for spend per campaign
        $managed_campaigns_data = [];
        if (is_array($managed_campaigns)) {
            foreach ($managed_campaigns as $c) {
                if (is_array($c)) {
                    $managed_campaigns_data[$c['name']] = $c;
                } else {
                    $managed_campaigns_data[$c] = ['spend' => 0];
                }
            }
        }
        $all_campaigns = array_unique(array_merge($campaigns_from_meta, array_keys($managed_campaigns_data)));
        sort($all_campaigns);

        if (empty($all_campaigns)) {
            echo '<li class="no-campaigns-found" style="text-align:center;padding:20px;color:#777;">No campaigns found.</li>';
        } else {
            foreach ($all_campaigns as $campaign) {
                if (empty($campaign)) continue;
                $lead_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'lead_campaign' AND meta_value = %s", $campaign));
                $spend = isset($managed_campaigns_data[$campaign]['spend']) ? $managed_campaigns_data[$campaign]['spend'] : 0;
    ?>
    <li class="campaign-list-item" data-campaign="<?php echo esc_attr($campaign); ?>" style="display:flex;align-items:center;justify-content:space-between;padding:12px 8px;border-bottom:1px solid rgba(255,255,255,0.1);">
        <div class="campaign-details">
            <span class="campaign-name-view" style="font-weight:500;color:#333;"><?php echo esc_html($campaign); ?></span>
            <span class="campaign-lead-count" style="margin-left:12px;font-size:12px;color:#666;background:#f0f0f0;padding:2px 8px;border-radius:6px;"><?php echo esc_html($lead_count); ?> Leads</span>
            <span class="campaign-spend-view" style="margin-left:12px;font-size:12px;color:#4b68b6;background:rgba(75,104,182,0.08);padding:2px 8px;border-radius:6px;">
                $<?php echo number_format((float)$spend, 2); ?>
                <?php if (current_user_can('edit_campaigns')): ?>
                <button class="edit-campaign-spend-btn crm-icon-btn" title="Edit Spend" style="margin-left:4px;"><i class="fas fa-edit"></i></button>
                <?php endif; ?>
            </span>
            <?php if (current_user_can('edit_campaigns')): ?>
            <span class="campaign-spend-edit-row" style="display:none;margin-left:12px;align-items:center;gap:6px;">
                <input type="number" class="campaign-spend-input opbcrm-input" min="0" step="0.01" style="width:80px;font-size:12px;" value="<?php echo esc_attr($spend); ?>" />
                <button class="save-campaign-spend-btn crm-btn crm-btn-primary" style="padding: 2px 8px;font-size:12px;">Save</button>
                <button class="cancel-campaign-spend-btn crm-btn" style="padding: 2px 8px;font-size:12px;">Cancel</button>
            </span>
            <?php endif; ?>
            <div class="campaign-edit-view" style="display:none;align-items:center;gap:8px;">
                <input type="text" class="campaign-name-input opbcrm-input" value="<?php echo esc_attr($campaign); ?>" />
                <button class="save-campaign-btn crm-btn crm-btn-primary" style="padding: 4px 10px;font-size:12px;">Save</button>
                <button class="cancel-campaign-btn crm-btn" style="padding: 4px 10px;font-size:12px;">Cancel</button>
            </div>
        </div>
        <?php if (current_user_can('manage_options')): ?>
        <div class="campaign-actions" style="display:flex;gap:10px;">
            <button class="edit-campaign-btn crm-icon-btn" title="Rename Campaign"><i class="fas fa-edit"></i></button>
            <button class="archive-campaign-btn crm-icon-btn" title="Archive Campaign"><i class="fas fa-archive"></i></button>
        </div>
        <?php endif; ?>
    </li>
    <?php
            }
        }
    ?>
  </ul>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// JS to fetch and render reporting data will be added here
jQuery(document).ready(function($){
  $('#add-campaign-btn').on('click', function(){
    $('#crm-campaigns-add-row').show();
    $('#crm-campaigns-add-input').val('').focus();
  });
  $('#crm-campaigns-add-cancel-btn').on('click', function(){
    $('#crm-campaigns-add-row').hide();
  });
  $('#crm-campaigns-add-save-btn').on('click', function(){
    var val = $('#crm-campaigns-add-input').val().trim();
    if (!val) { showToast('Enter campaign name', false); return; }
    $.post(ajaxurl, {action:'opbcrm_add_campaign', campaign:val}, function(resp){
      if (resp.success) { location.reload(); }
      else { showToast(resp.data && resp.data.message ? resp.data.message : 'Error adding campaign', false); }
    });
  });
  $(document).on('click','.crm-campaign-edit-btn',function(){
    var row = $(this).closest('.crm-campaign-row');
    row.find('.crm-campaign-label,.crm-campaign-edit-btn,.crm-campaign-delete-btn').hide();
    row.find('.crm-campaign-edit-row').show();
    row.find('.crm-campaign-edit-input').focus();
  });
  $(document).on('click','.crm-campaign-cancel-btn',function(){
    var row = $(this).closest('.crm-campaign-row');
    row.find('.crm-campaign-edit-row').hide();
    row.find('.crm-campaign-label,.crm-campaign-edit-btn,.crm-campaign-delete-btn').show();
  });
  $(document).on('click','.crm-campaign-save-btn',function(){
    var row = $(this).closest('.crm-campaign-row');
    var oldVal = row.data('campaign');
    var newVal = row.find('.crm-campaign-edit-input').val().trim();
    if (!newVal) { showToast('Enter campaign name', false); return; }
    $.post(ajaxurl, {action:'opbcrm_rename_campaign', old_campaign:oldVal, new_campaign:newVal}, function(resp){
      if (resp.success) { location.reload(); }
      else { showToast(resp.data && resp.data.message ? resp.data.message : 'Error renaming campaign', false); }
    });
  });
  $(document).on('click','.crm-campaign-delete-btn',function(){
    var row = $(this).closest('.crm-campaign-row');
    var val = row.data('campaign');
    if (!confirm('Archive this campaign?')) return;
    $.post(ajaxurl, {action:'opbcrm_archive_campaign', campaign:val}, function(resp){
      if (resp.success) { location.reload(); }
      else { showToast(resp.data && resp.data.message ? resp.data.message : 'Error archiving campaign', false); }
    });
  });
});
</script> 