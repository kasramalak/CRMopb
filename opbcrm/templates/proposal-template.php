<?php
/**
 * Proposal PDF Template
 * 
 * This file is included by the proposal generation logic.
 * It has access to the following variables:
 * 
 * @var WP_Post $lead
 * @var WP_Post $property
 * @var WP_User $agent
 * @var array   $agent_meta
 * @var array   $client_meta
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Property Proposal</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; color: #333; }
        .container { width: 100%; margin: 0 auto; padding: 20px; }
        .header, .footer { text-align: center; }
        .header img { max-width: 200px; margin-bottom: 20px; }
        h1, h2, h3 { color: #005A9C; }
        h1 { font-size: 24px; border-bottom: 2px solid #005A9C; padding-bottom: 10px; }
        .content { margin-top: 30px; }
        .section { margin-bottom: 20px; padding: 15px; border: 1px solid #eee; border-radius: 5px; }
        .section h2 { margin-top: 0; font-size: 18px; }
        .details-grid { display: block; }
        .detail-item { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .detail-item strong { color: #555; }
        .agent-card { margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 5px; }
        .agent-card img.agent-photo { float: left; width: 100px; height: 100px; border-radius: 50%; margin-right: 20px; }
        .footer { margin-top: 40px; font-size: 12px; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <?php if (!empty($agent_meta['company_logo_url'])): ?>
                <img src="<?php echo esc_url($agent_meta['company_logo_url']); ?>" alt="Company Logo">
            <?php endif; ?>
            <h1>Property Proposal</h1>
        </div>

        <div class="content">
            <div class="section">
                <h2>Client Information</h2>
                <div class="details-grid">
                    <div class="detail-item"><strong>Name:</strong> <?php echo esc_html($client_meta['name']); ?></div>
                    <div class="detail-item"><strong>Email:</strong> <?php echo esc_html($client_meta['email']); ?></div>
                    <div class="detail-item"><strong>Phone:</strong> <?php echo esc_html($client_meta['phone']); ?></div>
                </div>
            </div>

            <div class="section">
                <h2>Property Details</h2>
                <h3><?php echo esc_html($property->post_title); ?></h3>
                <div>
                    <?php 
                        // Using strip_tags to remove any HTML from the content
                        echo wp_strip_all_tags($property->post_content); 
                    ?>
                </div>
            </div>

            <div class="agent-card section">
                <h2>Your Consultant</h2>
                <?php if (!empty($agent_meta['photo'])): ?>
                     <img src="<?php echo esc_url($agent_meta['photo']); ?>" alt="Agent Photo" class="agent-photo">
                <?php endif; ?>
                <h3><?php echo esc_html($agent->display_name); ?></h3>
                <div class="details-grid">
                    <div class="detail-item"><strong>Email:</strong> <?php echo esc_html($agent_meta['email']); ?></div>
                    <div class="detail-item"><strong>Phone:</strong> <?php echo esc_html($agent_meta['phone']); ?></div>
                    <div class="detail-item"><strong>WhatsApp:</strong> <?php echo esc_html($agent_meta['whatsapp']); ?></div>
                    <?php if (!empty($agent_meta['socials']['linkedin'])): ?>
                        <div class="detail-item"><strong>LinkedIn:</strong> <?php echo esc_html($agent_meta['socials']['linkedin']); ?></div>
                    <?php endif; ?>
                </div>
                <div style="clear: both;"></div>
            </div>
        </div>

        <div class="footer">
            <p>This proposal was generated on <?php echo date('F j, Y'); ?>. All information is subject to change.</p>
        </div>
    </div>
</body>
</html> 