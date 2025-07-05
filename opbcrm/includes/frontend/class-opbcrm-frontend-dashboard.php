<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

use Dompdf\Dompdf;

class Houzcrm_Frontend_Dashboard {

    public function __construct() {
        add_shortcode('opbcrm_dashboard', array($this, 'render_dashboard'));
        
        // AJAX handlers
        add_action('wp_ajax_handle_kanban_update', array($this, 'handle_kanban_update'));
        add_action('wp_ajax_handle_bulk_actions', array($this, 'handle_bulk_actions'));
        add_action('wp_ajax_add_lead_comment', array($this, 'handle_add_comment'));
        add_action('wp_ajax_add_lead_task', array($this, 'handle_add_task'));
        add_action('wp_ajax_update_task_status', array($this, 'handle_update_task_status'));
        add_action('wp_ajax_save_lead_details', array($this, 'handle_save_lead_details'));
        add_action('wp_ajax_search_opbez_properties', array($this, 'handle_property_search'));
        add_action('wp_ajax_generate_crm_proposal', array($this, 'handle_proposal_generation'));
        add_action('wp_ajax_add_lead_reminder', function() {
            check_ajax_referer('opbcrm_frontend_nonce', 'nonce');
            if (!current_user_can('edit_leads')) {
                wp_send_json_error('No permission.');
            }
            $lead_id = intval($_POST['lead_id'] ?? 0);
            $msg = sanitize_text_field($_POST['msg'] ?? '');
            $due = sanitize_text_field($_POST['due'] ?? '');
            $location = sanitize_text_field($_POST['location'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            if (!$lead_id || !$msg) {
                wp_send_json_error('Missing data.');
            }
            global $opbcrm;
            $content = wp_json_encode(['content'=>$msg,'location'=>$location,'notes'=>$notes]);
            $activity_args = [
                'content' => $content,
                'due_date' => $due,
                'task_status' => 'pending',
            ];
            $activity_id = $opbcrm->activity->add_activity($lead_id, 'reminder', $activity_args);
            if ($activity_id) {
                wp_send_json_success('Reminder added.');
            } else {
                wp_send_json_error('Failed to add reminder.');
            }
        });
        // Log email event
        add_action('wp_ajax_log_lead_email', function() {
            check_ajax_referer('opbcrm_frontend_nonce', 'nonce');
            if (!current_user_can('edit_leads')) wp_send_json_error('No permission.');
            $lead_id = intval($_POST['lead_id'] ?? 0);
            $subject = sanitize_text_field($_POST['subject'] ?? '');
            $to = sanitize_email($_POST['to'] ?? '');
            $body = sanitize_textarea_field($_POST['body'] ?? '');
            if (!$lead_id || !$to || !$subject) wp_send_json_error('Missing data.');
            global $opbcrm;
            $content = 'Email sent to ' . esc_html($to) . ' | Subject: ' . esc_html($subject);
            $opbcrm->activity->add_activity($lead_id, 'email', ['content'=>$content]);
            wp_send_json_success('Email event logged.');
        });
        // Log document event
        add_action('wp_ajax_log_lead_document', function() {
            check_ajax_referer('opbcrm_frontend_nonce', 'nonce');
            if (!current_user_can('edit_leads')) wp_send_json_error('No permission.');
            $lead_id = intval($_POST['lead_id'] ?? 0);
            $file = sanitize_text_field($_POST['file'] ?? '');
            $action = sanitize_text_field($_POST['action'] ?? 'uploaded');
            if (!$lead_id || !$file) wp_send_json_error('Missing data.');
            global $opbcrm;
            $content = 'Document ' . esc_html($action) . ': ' . esc_html($file);
            $opbcrm->activity->add_activity($lead_id, 'document', ['content'=>$content]);
            wp_send_json_success('Document event logged.');
        });
        // Log notification event
        add_action('wp_ajax_log_lead_notification', function() {
            check_ajax_referer('opbcrm_frontend_nonce', 'nonce');
            if (!current_user_can('edit_leads')) wp_send_json_error('No permission.');
            $lead_id = intval($_POST['lead_id'] ?? 0);
            $notif_type = sanitize_text_field($_POST['notif_type'] ?? '');
            $message = sanitize_text_field($_POST['message'] ?? '');
            if (!$lead_id || !$notif_type) wp_send_json_error('Missing data.');
            global $opbcrm;
            $content = 'Notification (' . esc_html($notif_type) . '): ' . esc_html($message);
            $opbcrm->activity->add_activity($lead_id, 'notification', ['content'=>$content]);
            wp_send_json_success('Notification event logged.');
        });
        // Document upload for lead
        add_action('wp_ajax_upload_lead_document', function() {
            check_ajax_referer('opbcrm_frontend_nonce', 'nonce');
            if (!current_user_can('upload_documents')) {
                wp_send_json_error('No permission.');
            }
            $lead_id = intval($_POST['lead_id'] ?? 0);
            if (!$lead_id || empty($_FILES['file'])) {
                wp_send_json_error('Missing data.');
            }
            $file = $_FILES['file'];
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_id = media_handle_upload('file', 0);
            if (is_wp_error($attachment_id)) {
                wp_send_json_error('Upload failed.');
            }
            $url = wp_get_attachment_url($attachment_id);
            $name = get_the_title($attachment_id);
            $docs = get_post_meta($lead_id, 'lead_documents', true);
            $docs = is_array($docs) ? $docs : [];
            $docs[] = [
                'id' => $attachment_id,
                'url' => $url,
                'name' => $name,
                'date' => current_time('mysql'),
            ];
            update_post_meta($lead_id, 'lead_documents', $docs);
            // Log activity
            global $opbcrm;
            $content = 'Document uploaded: <a href="'.esc_url($url).'" target="_blank">'.esc_html($name).'</a>';
            $opbcrm->activity->add_activity($lead_id, 'document', ['content'=>$content]);
            wp_send_json_success('Document uploaded.');
        });
        // AJAX: Fetch notifications for current user
        add_action('wp_ajax_opbcrm_get_notifications', function() {
            if (!is_user_logged_in()) wp_send_json_error('Not logged in');
            global $opbcrm;
            $notifs = $opbcrm->activity->get_notifications(get_current_user_id(), 15);
            wp_send_json_success(['notifications' => $notifs]);
        });
        // AJAX: Mark notification as read
        add_action('wp_ajax_opbcrm_mark_notification_read', function() {
            if (!is_user_logged_in()) wp_send_json_error('Not logged in');
            $notif_id = sanitize_text_field($_POST['notif_id'] ?? '');
            if (!$notif_id) wp_send_json_error('Missing id');
            global $opbcrm;
            $opbcrm->activity->mark_notification_read(get_current_user_id(), $notif_id);
            wp_send_json_success('Marked as read');
        });
        // AJAX: Add meeting event
        add_action('wp_ajax_add_lead_meeting', function() {
            check_ajax_referer('opbcrm_frontend_nonce', 'nonce');
            if (!current_user_can('edit_leads')) {
                wp_send_json_error('No permission.');
            }
            $lead_id = intval($_POST['lead_id'] ?? 0);
            $msg = sanitize_text_field($_POST['msg'] ?? '');
            $due = sanitize_text_field($_POST['due'] ?? '');
            $assignee_id = intval($_POST['assignee_id'] ?? get_current_user_id());
            $location = sanitize_text_field($_POST['location'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            if (!$msg) {
                wp_send_json_error('Missing data.');
            }
            $content = wp_json_encode(['content'=>$msg,'location'=>$location,'notes'=>$notes]);
            global $opbcrm;
            $activity_args = [
                'content' => $content,
                'due_date' => $due,
                'assigned_to_user_id' => $assignee_id,
                'task_status' => 'pending',
            ];
            $activity_id = $opbcrm->activity->add_activity($lead_id, 'meeting', $activity_args);
            if ($activity_id) {
                if (!empty($assignee_id)) {
                    $opbcrm->activity->add_notification($assignee_id, 'meeting', 'A new meeting has been scheduled.', '');
                }
                wp_send_json_success('Meeting added.');
            } else {
                wp_send_json_error('Failed to add meeting.');
            }
        });
        // AJAX: Update task event
        add_action('wp_ajax_update_lead_task', function() {
            check_ajax_referer('opbcrm_frontend_nonce', 'nonce');
            $event_id = intval($_POST['event_id'] ?? 0);
            $content = sanitize_text_field($_POST['task_content'] ?? '');
            $due = sanitize_text_field($_POST['due_date'] ?? '');
            $location = sanitize_text_field($_POST['location'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            $user_id = get_current_user_id();
            global $wpdb, $opbcrm;
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$opbcrm->activity->table_name} WHERE activity_id = %d", $event_id));
            if(!$row || $row->assigned_to_user_id != $user_id) wp_send_json_error('No permission.');
            $content_json = wp_json_encode(['content'=>$content,'location'=>$location,'notes'=>$notes]);
            $wpdb->update($opbcrm->activity->table_name, [
                'content' => $content_json,
                'due_date' => $due
            ], ['activity_id' => $event_id]);
            wp_send_json_success('Task updated.');
        });
        // AJAX: Update reminder event
        add_action('wp_ajax_update_lead_reminder', function() {
            check_ajax_referer('opbcrm_frontend_nonce', 'nonce');
            $event_id = intval($_POST['event_id'] ?? 0);
            $msg = sanitize_text_field($_POST['msg'] ?? '');
            $due = sanitize_text_field($_POST['due'] ?? '');
            $location = sanitize_text_field($_POST['location'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            $user_id = get_current_user_id();
            global $wpdb, $opbcrm;
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$opbcrm->activity->table_name} WHERE activity_id = %d", $event_id));
            if(!$row || $row->assigned_to_user_id != $user_id) wp_send_json_error('No permission.');
            $content_json = wp_json_encode(['content'=>$msg,'location'=>$location,'notes'=>$notes]);
            $wpdb->update($opbcrm->activity->table_name, [
                'content' => $content_json,
                'due_date' => $due
            ], ['activity_id' => $event_id]);
            wp_send_json_success('Reminder updated.');
        });
        // AJAX: Update meeting event
        add_action('wp_ajax_update_lead_meeting', function() {
            check_ajax_referer('opbcrm_frontend_nonce', 'nonce');
            $event_id = intval($_POST['event_id'] ?? 0);
            $msg = sanitize_text_field($_POST['msg'] ?? '');
            $due = sanitize_text_field($_POST['due'] ?? '');
            $assignee_id = intval($_POST['assignee_id'] ?? get_current_user_id());
            $location = sanitize_text_field($_POST['location'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            $user_id = get_current_user_id();
            global $wpdb, $opbcrm;
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$opbcrm->activity->table_name} WHERE activity_id = %d", $event_id));
            if(!$row || $row->assigned_to_user_id != $user_id) wp_send_json_error('No permission.');
            $content_json = wp_json_encode(['content'=>$msg,'location'=>$location,'notes'=>$notes]);
            $wpdb->update($opbcrm->activity->table_name, [
                'content' => $content_json,
                'due_date' => $due,
                'assigned_to_user_id' => $assignee_id
            ], ['activity_id' => $event_id]);
            wp_send_json_success('Meeting updated.');
        });
        // AJAX: Delete task event
        add_action('wp_ajax_delete_lead_task', function() {
            check_ajax_referer('opbcrm_frontend_nonce', 'nonce');
            $event_id = intval($_POST['event_id'] ?? 0);
            $user_id = get_current_user_id();
            global $wpdb, $opbcrm;
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$opbcrm->activity->table_name} WHERE activity_id = %d", $event_id));
            if(!$row || $row->assigned_to_user_id != $user_id) wp_send_json_error('No permission.');
            $wpdb->delete($opbcrm->activity->table_name, ['activity_id' => $event_id]);
            wp_send_json_success('Task deleted.');
        });
        // AJAX: Delete reminder event
        add_action('wp_ajax_delete_lead_reminder', function() {
            check_ajax_referer('opbcrm_frontend_nonce', 'nonce');
            $event_id = intval($_POST['event_id'] ?? 0);
            $user_id = get_current_user_id();
            global $wpdb, $opbcrm;
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$opbcrm->activity->table_name} WHERE activity_id = %d", $event_id));
            if(!$row || $row->assigned_to_user_id != $user_id) wp_send_json_error('No permission.');
            $wpdb->delete($opbcrm->activity->table_name, ['activity_id' => $event_id]);
            wp_send_json_success('Reminder deleted.');
        });
        // AJAX: Delete meeting event
        add_action('wp_ajax_delete_lead_meeting', function() {
            check_ajax_referer('opbcrm_frontend_nonce', 'nonce');
            $event_id = intval($_POST['event_id'] ?? 0);
            $user_id = get_current_user_id();
            global $wpdb, $opbcrm;
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$opbcrm->activity->table_name} WHERE activity_id = %d", $event_id));
            if(!$row || $row->assigned_to_user_id != $user_id) wp_send_json_error('No permission.');
            $wpdb->delete($opbcrm->activity->table_name, ['activity_id' => $event_id]);
            wp_send_json_success('Meeting deleted.');
        });
        // AJAX: Get CRM users for attendee dropdown
        add_action('wp_ajax_opbcrm_get_crm_users', function() {
            if (!is_user_logged_in() || !current_user_can('edit_leads')) wp_send_json_error('No permission.');
            $users = get_users(['role__in' => ['administrator', 'crm_manager', 'crm_agent']]);
            $result = [];
            foreach ($users as $user) {
                $result[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email
                ];
            }
            wp_send_json_success(['users' => $result]);
        });
        // Outlook Calendar OAuth: Start
        add_action('wp_ajax_opbcrm_outlook_oauth_start', function() {
            if (!is_user_logged_in()) wp_send_json_error('Not logged in');
            $client_id = 'OUTLOOK_CLIENT_ID'; // TODO: Replace with real client ID
            $redirect_uri = urlencode(site_url('/?opbcrm_outlook_oauth_callback=1'));
            $scope = urlencode('offline_access https://graph.microsoft.com/Calendars.ReadWrite https://graph.microsoft.com/User.Read');
            $state = wp_create_nonce('opbcrm_outlook_oauth');
            $auth_url = "https://login.microsoftonline.com/common/oauth2/v2.0/authorize?client_id={$client_id}&response_type=code&redirect_uri={$redirect_uri}&response_mode=query&scope={$scope}&state={$state}";
            wp_send_json_success(['url'=>$auth_url]);
        });
        // Outlook Calendar OAuth: Callback
        add_action('init', function() {
            if (isset($_GET['opbcrm_outlook_oauth_callback']) && isset($_GET['code']) && isset($_GET['state'])) {
                if (!wp_verify_nonce($_GET['state'], 'opbcrm_outlook_oauth')) die('Invalid state.');
                $code = sanitize_text_field($_GET['code']);
                $client_id = 'OUTLOOK_CLIENT_ID'; // TODO: Replace with real client ID
                $client_secret = 'OUTLOOK_CLIENT_SECRET'; // TODO: Replace with real client secret
                $redirect_uri = site_url('/?opbcrm_outlook_oauth_callback=1');
                $token_url = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
                $body = [
                    'client_id' => $client_id,
                    'scope' => 'offline_access https://graph.microsoft.com/Calendars.ReadWrite https://graph.microsoft.com/User.Read',
                    'code' => $code,
                    'redirect_uri' => $redirect_uri,
                    'grant_type' => 'authorization_code',
                    'client_secret' => $client_secret,
                ];
                $response = wp_remote_post($token_url, [
                    'body' => $body,
                ]);
                $json = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($json['access_token'])) {
                    if (is_user_logged_in()) {
                        update_user_meta(get_current_user_id(), 'opbcrm_outlook_tokens', $json);
                        wp_redirect(site_url('/crm-dashboard?outlook_connected=1'));
                        exit;
                    }
                }
                wp_die('Outlook OAuth failed.');
            }
        });
        // AJAX: Fetch Google Calendar events for current user
        add_action('wp_ajax_opbcrm_get_google_events', function() {
            if (!is_user_logged_in()) wp_send_json_error('Not logged in');
            $tokens = get_user_meta(get_current_user_id(), 'opbcrm_google_tokens', true);
            if (empty($tokens['access_token'])) {
                wp_send_json_error('Google not connected');
            }
            // TODO: Make real API call to Google Calendar
            // Placeholder: return mock events
            $mock_events = [
                [
                    'id' => 'gcal1',
                    'summary' => 'Google Meeting',
                    'start' => [ 'dateTime' => date('Y-m-d\TH:i:s', strtotime('+1 day 10:00')) ],
                    'end' => [ 'dateTime' => date('Y-m-d\TH:i:s', strtotime('+1 day 11:00')) ],
                    'location' => 'Googleplex',
                    'description' => 'Test event from Google Calendar.'
                ],
                [
                    'id' => 'gcal2',
                    'summary' => 'Google Call',
                    'start' => [ 'dateTime' => date('Y-m-d\TH:i:s', strtotime('+2 days 14:00')) ],
                    'end' => [ 'dateTime' => date('Y-m-d\TH:i:s', strtotime('+2 days 14:30')) ],
                    'location' => '',
                    'description' => 'Another test event.'
                ]
            ];
            wp_send_json_success(['events'=>$mock_events]);
        });
        // AJAX: Fetch Outlook Calendar events for current user
        add_action('wp_ajax_opbcrm_get_outlook_events', function() {
            if (!is_user_logged_in()) wp_send_json_error('Not logged in');
            $tokens = get_user_meta(get_current_user_id(), 'opbcrm_outlook_tokens', true);
            if (empty($tokens['access_token'])) {
                wp_send_json_error('Outlook not connected');
            }
            // TODO: Make real API call to Outlook Calendar
            // Placeholder: return mock events
            $mock_events = [
                [
                    'id' => 'outlook1',
                    'subject' => 'Outlook Meeting',
                    'start' => [ 'dateTime' => date('Y-m-d\TH:i:s', strtotime('+3 days 09:00')) ],
                    'end' => [ 'dateTime' => date('Y-m-d\TH:i:s', strtotime('+3 days 10:00')) ],
                    'location' => [ 'displayName' => 'Microsoft HQ' ],
                    'bodyPreview' => 'Test event from Outlook Calendar.'
                ],
                [
                    'id' => 'outlook2',
                    'subject' => 'Outlook Call',
                    'start' => [ 'dateTime' => date('Y-m-d\TH:i:s', strtotime('+4 days 15:00')) ],
                    'end' => [ 'dateTime' => date('Y-m-d\TH:i:s', strtotime('+4 days 15:30')) ],
                    'location' => [ 'displayName' => '' ],
                    'bodyPreview' => 'Another test event.'
                ]
            ];
            wp_send_json_success(['events'=>$mock_events]);
        });
        // AJAX: Push event to Google Calendar (real API)
        add_action('wp_ajax_opbcrm_push_google_event', function() {
            if (!is_user_logged_in()) wp_send_json_error('Not logged in');
            $tokens = get_user_meta(get_current_user_id(), 'opbcrm_google_tokens', true);
            if (empty($tokens['access_token'])) {
                wp_send_json_error('Google not connected');
            }
            $access_token = $tokens['access_token'];
            $title = sanitize_text_field($_POST['title'] ?? '');
            $date = sanitize_text_field($_POST['date'] ?? '');
            $location = sanitize_text_field($_POST['location'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');
            if (!$title || !$date) wp_send_json_error('Missing title or date');
            $start = date('c', strtotime($date));
            $end = date('c', strtotime($date) + 3600); // Default 1 hour
            $event = [
                'summary' => $title,
                'location' => $location,
                'description' => $notes,
                'start' => [ 'dateTime' => $start, 'timeZone' => wp_timezone_string() ],
                'end' => [ 'dateTime' => $end, 'timeZone' => wp_timezone_string() ],
            ];
            $response = wp_remote_post('https://www.googleapis.com/calendar/v3/calendars/primary/events', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($event),
                'timeout' => 15,
            ]);
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            if ($code === 401) {
                wp_send_json_error('Google token expired or invalid. Please reconnect.');
            }
            if ($code < 200 || $code >= 300) {
                wp_send_json_error('Google API error: ' . $body);
            }
            wp_send_json_success('Event pushed to Google Calendar!');
        });
    }

    /**
     * Renders the entire dashboard.
     * This is now the entry point when a template file calls for our dashboard.
     */
    public static function display_dashboard_content() {
        $instance = new self();
        // The enqueueing is now handled by a direct hook from the template file
        echo $instance->render_dashboard_content();
    }

    /**
     * Enqueues scripts and styles needed for the dashboard.
     * This method is hooked into 'wp_enqueue_scripts' from the template file itself.
     * Ensures assets are only loaded on the CRM dashboard page.
     */
    public static function enqueue_dashboard_assets() {
        // Only load assets on the page with our shortcode.
        if (is_singular() && has_shortcode(get_post(get_the_ID())->post_content, 'opbcrm_dashboard')) {
            // Main dashboard stylesheet (includes modal styles)
            wp_enqueue_style(
                'opbcrm-dashboard-styles',
                OPBCRM_PLUGIN_URL . 'assets/css/crm-dashboard.css',
                array(),
                OPBCRM_VERSION
            );
            // intl-tel-input CSS
            wp_enqueue_style(
                'intl-tel-input',
                OPBCRM_PLUGIN_URL . 'assets/css/intlTelInput.min.css',
                array(),
                '18.6.1'
            );
            // Dashboard JS for interactivity (modal, drag-drop, etc.)
            wp_enqueue_script(
                'opbcrm-dashboard-script',
                OPBCRM_PLUGIN_URL . 'assets/js/crm-dashboard.js',
                array('jquery', 'jquery-ui-sortable'), // Add dependencies
                OPBCRM_VERSION,
                true
            );
            // intl-tel-input JS
            wp_enqueue_script(
                'intl-tel-input',
                OPBCRM_PLUGIN_URL . 'assets/js/intlTelInput.min.js',
                array('jquery'),
                '18.6.1',
                true
            );
            wp_localize_script(
                'opbcrm-dashboard-script',
                'opbcrm_dashboard_ajax',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('opbcrm_dashboard_nonce')
                )
            );
        }
    }

    public function render_dashboard_content() {
        ob_start();
        
        if (!is_user_logged_in() || !current_user_can('view_leads')) {
            echo 'Access Denied. You do not have permission to view this page.';
            return ob_get_clean();
        }

        // Check if we are viewing a single lead
        if (isset($_GET['crm-lead-id']) && is_numeric($_GET['crm-lead-id'])) {
            $lead_id = intval($_GET['crm-lead-id']);

            // Security check: ensure user can view this lead
            if (!current_user_can('edit_post', $lead_id)) {
                echo 'You do not have permission to view this lead.';
                return ob_get_clean();
            }

            // Get all properties for the proposal generator
            $properties_query = new WP_Query(array(
                'post_type' => 'property',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'orderby' => 'title',
                'order' => 'ASC'
            ));
            $properties = $properties_query->get_posts();
            wp_reset_postdata();

            // Load the lead detail template, passing the lead_id to it
            include OPBCRM_TEMPLATES_PATH . 'lead-detail.php';

        } else {
            // Load the main dashboard template if no specific lead is requested
            $calendar_events = self::get_calendar_events($current_user_id);
            echo '<script>var opbcrm_calendar_events = '.json_encode($calendar_events).';</script>';
            include OPBCRM_TEMPLATES_PATH . 'crm-dashboard.php';
        }

        // Global overdue reminders for current user
        $overdue_reminders = [];
        if (isset($opbcrm) && method_exists($opbcrm->activity, 'get_reminders_for_user')) {
            $reminders = $opbcrm->activity->get_reminders_for_user($current_user_id);
            foreach ($reminders as $rem) {
                $is_overdue = $rem->due_date && strtotime($rem->due_date) < time() && $rem->task_status !== 'completed';
                $notified = false;
                $content = $rem->content;
                if (strpos($content, 'notified') !== false) $notified = true;
                if ($is_overdue) {
                    $overdue_reminders[] = $rem;
                    if (!$notified) {
                        // Send email notification
                        $user = get_userdata($current_user_id);
                        if ($user && $user->user_email) {
                            $subject = '[CRM] Overdue Reminder';
                            $message = 'You have an overdue reminder: '.(is_array(@json_decode($content,true)) ? @json_decode($content,true)['content'] : $content);
                            @wp_mail($user->user_email, $subject, $message);
                        }
                        // Mark as notified
                        $opbcrm->activity->mark_reminder_notified($rem->activity_id);
                        // Add notification for user
                        $opbcrm->activity->add_notification($current_user_id, 'reminder', 'You have an overdue reminder: '.(is_array(@json_decode($content,true)) ? @json_decode($content,true)['content'] : $content), '');
                    }
                }
            }
        }

        // Fetch notifications for bell UI
        $notifications = [];
        if (isset($opbcrm) && method_exists($opbcrm->activity, 'get_notifications')) {
            $notifications = $opbcrm->activity->get_notifications($current_user_id, 10);
        }

        return ob_get_clean();
    }

    private function localize_ajax_script() {
        // This logic is now inside enqueue_dashboard_assets()
    }

    public function handle_bulk_actions() {
        check_ajax_referer('opbcrm_frontend_nonce', 'nonce');

        if (!current_user_can('edit_leads')) {
            wp_send_json_error('Permission denied.');
        }

        if (!isset($_POST['bulk_action']) || !isset($_POST['lead_ids'])) {
            wp_send_json_error('Invalid data.');
        }

        $action = sanitize_text_field($_POST['bulk_action']);
        
        if ($action === 'delete' && !current_user_can('delete_leads')) {
            wp_send_json_error('Permission denied to delete leads.');
        }
        if ($action === 'change_status' && !current_user_can('edit_leads')) {
            wp_send_json_error('Permission denied to edit leads.');
        }

        $lead_ids = array_map('intval', $_POST['lead_ids']);
        
        if (empty($lead_ids) || empty($action)) {
            wp_send_json_error('Missing data.');
        }

        foreach ($lead_ids as $lead_id) {
            if (!current_user_can('edit_post', $lead_id)) {
                continue;
            }

            if ($action === 'delete') {
                wp_delete_post($lead_id, true);
            } elseif ($action === 'change_status') {
                if (isset($_POST['new_status'])) {
                    $new_status = sanitize_text_field($_POST['new_status']);
                    if (!empty($new_status)) {
                        $old_status_key = get_post_meta($lead_id, 'lead_status', true);
                        update_post_meta($lead_id, 'lead_status', $new_status);

                        // Log this activity
                        $stages = get_option('opbez_crm_stages', array());
                        $old_status_label = isset($stages[$old_status_key]) ? $stages[$old_status_key] : $old_status_key;
                        $new_status_label = isset($stages[$new_status]) ? $stages[$new_status] : $new_status;
                        $activity_content = sprintf('%s → %s', $old_status_label, $new_status_label);
                        global $opbcrm;
                        $opbcrm->activity->add_activity($lead_id, 'stage_change', ['content' => $activity_content]);

                        // --- Run automations for lead stage change ---
                        if (class_exists('OPBCRM_Settings')) {
                            OPBCRM_Settings::run_automations('lead_stage_change', [
                                'lead_id' => $lead_id,
                                'old_stage' => $old_status_key,
                                'new_stage' => $new_status,
                                'user_id' => get_current_user_id(),
                            ]);
                        }
                    }
                }
            }
        }

        wp_send_json_success('Actions completed successfully.');
    }

    public function handle_kanban_update() {
        check_ajax_referer('opbcrm_frontend_nonce', 'nonce');

        if (!current_user_can('edit_leads')) {
            wp_send_json_error('Permission denied.');
        }

        if (!isset($_POST['lead_id']) || !isset($_POST['new_status'])) {
            wp_send_json_error('Missing lead data.');
        }

        $lead_id = intval($_POST['lead_id']);
        $new_status_key = sanitize_text_field($_POST['new_status']);
        $new_status_key = str_replace('col-', '', $new_status_key); // Clean up the status from Kanban

        if (current_user_can('edit_post', $lead_id)) {
            $old_status_key = get_post_meta($lead_id, 'lead_status', true);
            
            update_post_meta($lead_id, 'lead_status', $new_status_key);

            // Log this activity
            $stages = get_option('opbez_crm_stages', array());
            $old_status_label = isset($stages[$old_status_key]) ? $stages[$old_status_key] : $old_status_key;
            $new_status_label = isset($stages[$new_status_key]) ? $stages[$new_status_key] : $new_status_key;
            $activity_content = sprintf('%s → %s', $old_status_label, $new_status_label);

            global $opbcrm;
            $opbcrm->activity->add_activity($lead_id, 'stage_change', ['content' => $activity_content]);

            wp_send_json_success(array('message' => 'Lead status updated successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
    }

    public function handle_property_search() {
        check_ajax_referer('opbcrm_frontend_nonce', 'nonce');

        $search_term = sanitize_text_field($_GET['term']);

        $args = array(
            'post_type' => 'property',
            'posts_per_page' => 10,
            's' => $search_term
        );
        $query = new WP_Query($args);

        $properties = array();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $properties[] = array(
                    'id' => get_the_ID(),
                    'label' => get_the_title(),
                    'value' => get_the_title()
                );
            }
        }
        wp_reset_postdata();

        wp_send_json($properties);
    }

    public function handle_proposal_generation() {
        check_ajax_referer('opbcrm_frontend_nonce', 'nonce');

        if (!current_user_can('edit_leads')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        if (!isset($_POST['lead_id']) || !isset($_POST['property_id'])) {
            wp_send_json_error(['message' => 'Missing data.']);
        }

        $lead_id = intval($_POST['lead_id']);
        $property_id = intval($_POST['property_id']);

        $property = get_post($property_id);
        if (!$property || 'property' !== $property->post_type) {
             wp_send_json_error(['message' => 'Invalid property selected.']);
        }

        // Load Dompdf
        require_once OPBCRM_PLUGIN_DIR . 'vendor/dompdf/autoload.inc.php';
        
        // Use Dompdf
        $dompdf = new Dompdf();
        
        // --- GATHER DATA ---
        $lead = get_post($lead_id);
        $agent = wp_get_current_user();
        
        // Get agent meta - using placeholder keys, assuming Houzez theme structure
        $agent_meta = [
            'phone' => get_user_meta($agent->ID, 'fave_agent_mobile', true),
            'whatsapp' => get_user_meta($agent->ID, 'fave_agent_whatsapp', true),
            'email' => $agent->user_email,
            'photo' => get_avatar_url($agent->ID),
            'company_logo_url' => wp_get_attachment_image_url(get_user_meta($agent->ID, 'fave_agent_logo', true), 'medium'),
            'socials' => [
                'facebook' => get_user_meta($agent->ID, 'fave_agent_facebook', true),
                'twitter' => get_user_meta($agent->ID, 'fave_agent_twitter', true),
                'linkedin' => get_user_meta($agent->ID, 'fave_agent_linkedin', true),
            ]
        ];

        $client_meta = [
            'name' => $lead->post_title,
            'email' => get_post_meta($lead_id, 'lead_email', true),
            'phone' => get_post_meta($lead_id, 'lead_phone', true),
        ];

        // --- GENERATE HTML ---
        // Pass data to an external template file to keep it clean
        ob_start();
        include OPBCRM_PLUGIN_DIR . 'templates/proposal-template.php';
        $html = ob_get_clean();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // --- SAVE PDF ---
        $upload_dir = wp_upload_dir();
        $proposal_dir = $upload_dir['basedir'] . '/opbcrm_proposals';
        if (!file_exists($proposal_dir)) {
            wp_mkdir_p($proposal_dir);
        }
        $filename = 'proposal-' . $lead_id . '-' . time() . '.pdf';
        $filepath = $proposal_dir . '/' . $filename;
        $file_url = $upload_dir['baseurl'] . '/opbcrm_proposals/' . $filename;

        file_put_contents($filepath, $dompdf->output());

        // --- LOG ACTIVITY ---
        global $opbcrm;
        $activity_content = 'Generated a proposal for property: <a href="' . get_permalink($property_id) . '" target="_blank">' . esc_html($property->post_title) . '</a>. <a href="' . esc_url($file_url) . '" target="_blank">View PDF</a>';
        
        // Let's add the proposal link as a comment to make it more visible
        $opbcrm->activity->add_activity($lead_id, 'comment', ['content' => $activity_content]);

        wp_send_json_success(['message' => 'Proposal generated successfully!', 'pdf_url' => $file_url, 'activity_html' => '']);
    }

    public function handle_add_comment() {
        check_ajax_referer('opbcrm_frontend_nonce', 'nonce');

        if (!current_user_can('edit_leads')) {
            wp_send_json_error('Permission denied.');
        }

        if (!isset($_POST['lead_id']) || !isset($_POST['comment'])) {
            wp_send_json_error('Missing data.');
        }

        $lead_id = intval($_POST['lead_id']);
        $comment_content = sanitize_textarea_field($_POST['comment']);

        if (empty($comment_content)) {
            wp_send_json_error('Comment cannot be empty.');
        }

        global $opbcrm;
        $activity_id = $opbcrm->activity->add_activity($lead_id, 'comment', ['content' => $comment_content]);

        if ($activity_id) {
            // Prepare data for the new timeline item to be rendered on the frontend
            $user_info = get_userdata(get_current_user_id());
            $new_activity_data = [
                'type'      => 'Comment',
                'user_name' => $user_info->display_name,
                'time_ago'  => 'Just now',
                'content'   => nl2br(esc_html($comment_content)),
                'icon_class' => 'fas fa-comment',
                'icon_bg_class' => 'comment'
            ];
            wp_send_json_success($new_activity_data);
        } else {
            wp_send_json_error('Failed to save comment.');
        }
    }

    public function handle_add_task() {
        check_ajax_referer('opbcrm_frontend_nonce', 'nonce');
        
        if (!current_user_can('edit_leads')) {
            wp_send_json_error('Permission denied.');
        }

        if (!isset($_POST['lead_id']) || !isset($_POST['task_content'])) {
            wp_send_json_error('Missing data.');
        }

        $lead_id = intval($_POST['lead_id']);
        $content = sanitize_textarea_field($_POST['task_content']);
        $assignee_id = intval($_POST['assignee_id']);
        $due_date = sanitize_text_field($_POST['due_date']);
        // Reformat date for MySQL
        $due_date_mysql = date('Y-m-d H:i:s', strtotime($due_date));


        if (empty($content) || empty($assignee_id)) {
            wp_send_json_error('Task description and assignee are required.');
        }

        global $opbcrm;
        $activity_args = [
            'content'             => $content,
            'assigned_to_user_id' => $assignee_id,
            'due_date'            => $due_date_mysql,
            'task_status'         => 'pending'
        ];

        $activity_id = $opbcrm->activity->add_activity($lead_id, 'task', $activity_args);

        if ($activity_id) {
            // Add notification for assignee
            if (!empty($activity_args['assigned_to_user_id'])) {
                $opbcrm->activity->add_notification($activity_args['assigned_to_user_id'], 'task', 'A new task has been assigned to you for lead #'.$lead_id, '');
            }
            wp_send_json_success('Task added successfully.');
        } else {
            wp_send_json_error('Failed to save task.');
        }
    }

    public function handle_update_task_status() {
        check_ajax_referer('opbcrm_frontend_nonce', 'nonce');

        if (!current_user_can('edit_leads')) {
            wp_send_json_error('Permission denied.');
        }

        if (!isset($_POST['task_id']) || !isset($_POST['is_completed'])) {
            wp_send_json_error('Missing data.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'opbcrm_activities';
        
        $task_id = intval($_POST['task_id']);
        $is_completed = rest_sanitize_boolean($_POST['is_completed']);
        $new_status = $is_completed ? 'completed' : 'pending';

        $updated = $wpdb->update(
            $table_name,
            ['task_status' => $new_status],
            ['activity_id' => $task_id],
            ['%s'],
            ['%d']
        );

        if ($updated !== false) {
            wp_send_json_success('Task status updated.');
        } else {
            wp_send_json_error('Failed to update task status.');
        }
    }

    public function handle_save_lead_details() {
        check_ajax_referer('opbcrm_frontend_nonce', 'nonce');

        if (!current_user_can('edit_leads') || !isset($_POST['lead_id']) || !isset($_POST['form_data'])) {
            wp_send_json_error('Permission denied or missing data.');
        }
        
        $lead_id = intval($_POST['lead_id']);
        parse_str($_POST['form_data'], $form_data);

        foreach ($form_data as $key => $value) {
            $sanitized_value = sanitize_text_field($value);
            
            // our convention is that custom fields start with 'cf_'
            if (strpos($key, 'cf_') === 0) {
                 update_post_meta($lead_id, '_' . $key, $sanitized_value);
            } else {
                 // Potentially handle standard fields here if needed in the future
                 // For now, we only save custom fields.
                 // Example: update_post_meta($lead_id, 'lead_phone', $sanitized_value);
            }
        }
        
        // After creating a new lead (in save_lead_details), run automations
        if (!$lead_id && $new_lead_id) {
            if (class_exists('OPBCRM_Settings')) {
                OPBCRM_Settings::run_automations('lead_created', [
                    'lead_id' => $new_lead_id,
                    'user_id' => get_current_user_id(),
                ]);
            }
        }
        
        wp_send_json_success('Details updated successfully.');
    }

    /**
     * Get all calendar events (tasks, reminders, meetings) for the current user.
     * Returns array of events for FullCalendar.
     */
    public static function get_calendar_events($user_id) {
        global $opbcrm;
        $events = [];
        // Tasks assigned to user
        $tasks = $opbcrm->activity->get_activities_for_user($user_id, 'task');
        foreach ($tasks as $t) {
            $c = @json_decode($t->content,true);
            $events[] = [
                'id' => 'task_'.$t->activity_id,
                'title' => 'Task: '.($c['content'] ?? $t->content),
                'start' => $t->due_date ?: $t->activity_date,
                'end' => $t->due_date ?: $t->activity_date,
                'color' => '#83A2DB',
                'lead_id' => $t->lead_id,
                'type' => 'task',
                'assigned_to_user_id' => $t->assigned_to_user_id,
                'location' => $c['location'] ?? '',
                'notes' => $c['notes'] ?? '',
            ];
        }
        // Reminders assigned to user
        $reminders = $opbcrm->activity->get_activities_for_user($user_id, 'reminder');
        foreach ($reminders as $r) {
            $c = @json_decode($r->content,true);
            $events[] = [
                'id' => 'reminder_'.$r->activity_id,
                'title' => 'Reminder: '.($c['content'] ?? $r->content),
                'start' => $r->due_date ?: $r->activity_date,
                'end' => $r->due_date ?: $r->activity_date,
                'color' => '#ea5455',
                'lead_id' => $r->lead_id,
                'type' => 'reminder',
                'assigned_to_user_id' => $r->assigned_to_user_id,
                'location' => $c['location'] ?? '',
                'notes' => $c['notes'] ?? '',
            ];
        }
        // Meetings assigned to user
        $meetings = $opbcrm->activity->get_activities_for_user($user_id, 'meeting');
        foreach ($meetings as $m) {
            $attendee_name = '';
            if($m->assigned_to_user_id){
                $u = get_userdata($m->assigned_to_user_id);
                if($u) $attendee_name = $u->display_name;
            }
            $c = @json_decode($m->content,true);
            $events[] = [
                'id' => 'meeting_'.$m->activity_id,
                'title' => 'Meeting: '.($c['content'] ?? $m->content),
                'start' => $m->due_date ?: $m->activity_date,
                'end' => $m->due_date ?: $m->activity_date,
                'color' => '#00b894',
                'lead_id' => $m->lead_id,
                'type' => 'meeting',
                'assigned_to_user_id' => $m->assigned_to_user_id,
                'assigned_to_user_name' => $attendee_name,
                'location' => $c['location'] ?? '',
                'notes' => $c['notes'] ?? '',
            ];
        }
        return $events;
    }
}

new Houzcrm_Frontend_Dashboard(); 