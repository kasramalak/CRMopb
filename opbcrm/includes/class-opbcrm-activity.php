<?php
/**
 * OPBCRM Activity Class.
 *
 * Handles database interactions for the activities.
 *
 * @package OPBCRM
 * @since   1.0.0
 */

class OPBCRM_Activity {

    /**
     * The activities table name.
     *
     * @var string
     */
    public $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'opbcrm_activities';
    }

    /**
     * Adds a new activity to the database.
     *
     * @param int    $lead_id       The ID of the lead.
     * @param string $activity_type The type of activity (e.g., 'comment', 'stage_change').
     * @param array  $args          Additional arguments like 'content' or 'user_id'.
     *
     * @return int|false The ID of the newly inserted activity, or false on failure.
     */
    public function add_activity( $lead_id, $activity_type, $args = [] ) {
        global $wpdb;

        $defaults = [
            'user_id'       => get_current_user_id(),
            'content'       => '',
            'activity_date' => current_time( 'mysql' ),
            'assigned_to_user_id' => 0,
            'due_date'      => null,
            'task_status'   => 'pending',
        ];
        $data = wp_parse_args( $args, $defaults );

        $result = $wpdb->insert(
            $this->table_name,
            [
                'lead_id'             => $lead_id,
                'user_id'             => $data['user_id'],
                'activity_type'       => $activity_type,
                'content'             => $data['content'],
                'activity_date'       => $data['activity_date'],
                'assigned_to_user_id' => $data['assigned_to_user_id'],
                'due_date'            => $data['due_date'],
                'task_status'         => $data['task_status'],
            ],
            [
                '%d', // lead_id
                '%d', // user_id
                '%s', // activity_type
                '%s', // content
                '%s', // activity_date
                '%d', // assigned_to_user_id
                '%s', // due_date
                '%s', // task_status
            ]
        );
        
        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Retrieves all activities for a specific lead.
     *
     * @param int $lead_id The ID of the lead.
     *
     * @return array An array of activity objects.
     */
    public function get_activities_for_lead( $lead_id ) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE lead_id = %d ORDER BY activity_date DESC",
            $lead_id
        );

        return $wpdb->get_results( $sql );
    }

    /**
     * Retrieves all reminders (activity_type = 'reminder') for all leads assigned to a user.
     *
     * @param int $user_id The user ID (agent/assignee).
     * @return array An array of reminder activity objects.
     */
    public function get_reminders_for_user($user_id) {
        global $wpdb;
        // Find all leads assigned to this user (post_author or agent_id meta)
        $lead_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'opbez_lead' AND post_status = 'publish' AND post_author = %d",
            $user_id
        ));
        // Also check agent_id meta (if used)
        $meta_lead_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'agent_id' AND meta_value = %d",
            $user_id
        ));
        $all_lead_ids = array_unique(array_merge($lead_ids, $meta_lead_ids));
        if (empty($all_lead_ids)) return [];
        $in = implode(',', array_map('intval', $all_lead_ids));
        $sql = "SELECT * FROM {$this->table_name} WHERE lead_id IN ($in) AND activity_type = 'reminder' ORDER BY due_date ASC";
        return $wpdb->get_results($sql);
    }

    /**
     * Mark a reminder as notified (adds notified=1 to content JSON or similar workaround).
     */
    public function mark_reminder_notified($activity_id) {
        global $wpdb;
        // Try to update content as JSON if possible
        $activity = $wpdb->get_row($wpdb->prepare("SELECT content FROM {$this->table_name} WHERE activity_id = %d", $activity_id));
        if ($activity) {
            $content = $activity->content;
            $data = @json_decode($content, true);
            if (is_array($data)) {
                $data['notified'] = 1;
                $new_content = wp_json_encode($data);
            } else {
                // Fallback: append marker
                $new_content = $content . ' [notified]';
            }
            $wpdb->update($this->table_name, ['content' => $new_content], ['activity_id' => $activity_id]);
        }
    }

    /**
     * Add a notification for a user.
     */
    public function add_notification($user_id, $type, $message, $link = '') {
        $notifs = get_user_meta($user_id, 'opbcrm_notifications', true);
        $notifs = is_array($notifs) ? $notifs : [];
        $notifs[] = [
            'id' => uniqid('notif_'),
            'type' => $type,
            'message' => $message,
            'link' => $link,
            'read' => 0,
            'date' => current_time('mysql'),
        ];
        update_user_meta($user_id, 'opbcrm_notifications', $notifs);
    }

    /**
     * Get recent notifications for a user.
     */
    public function get_notifications($user_id, $limit = 10) {
        $notifs = get_user_meta($user_id, 'opbcrm_notifications', true);
        $notifs = is_array($notifs) ? $notifs : [];
        usort($notifs, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
        return array_slice($notifs, 0, $limit);
    }

    /**
     * Mark a notification as read.
     */
    public function mark_notification_read($user_id, $notif_id) {
        $notifs = get_user_meta($user_id, 'opbcrm_notifications', true);
        $notifs = is_array($notifs) ? $notifs : [];
        foreach ($notifs as &$n) {
            if ($n['id'] === $notif_id) $n['read'] = 1;
        }
        update_user_meta($user_id, 'opbcrm_notifications', $notifs);
    }

    /**
     * Get all activities (optionally filtered by type) assigned to a user.
     * @param int $user_id
     * @param string|null $type
     * @return array
     */
    public function get_activities_for_user($user_id, $type = null) {
        global $wpdb;
        $sql = "SELECT * FROM {$this->table_name} WHERE assigned_to_user_id = %d";
        $params = [$user_id];
        if ($type) {
            $sql .= " AND activity_type = %s";
            $params[] = $type;
        }
        $sql .= " ORDER BY due_date ASC, activity_date DESC";
        return $wpdb->get_results($wpdb->prepare($sql, ...$params));
    }
} 