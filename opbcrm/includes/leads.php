<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class OPBCRM_Leads {

    /**
     * Create a new lead.
     *
     * @param array $data Lead data.
     * @return int|false The ID of the new lead, or false on failure.
     */
    public static function create( $data ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_leads';

        // Set default values
        $defaults = array(
            'name'          => '',
            'phone'         => '',
            'email'         => '',
            'stage'         => '',
            'sub_stage'     => '',
            'agent_id'      => 0,
            'source'        => '',
            'tags'          => '',
            'custom_fields' => '',
            'created_at'    => current_time( 'mysql' ),
            'last_activity' => current_time( 'mysql' ),
        );

        $data = wp_parse_args( $data, $defaults );

        // TODO: Add validation for required fields like name, email/phone

        $result = $wpdb->insert( $table_name, $data );

        if ( $result ) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get a single lead by ID.
     */
    public static function get_lead( $lead_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_leads';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $lead_id ) );
    }

    /**
     * Get a list of leads.
     */
    public static function get_leads( $args = array() ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_leads';

        $defaults = array(
            'agent_id' => 0,
            'stage' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'number' => 20,
            'offset' => 0,
        );
        $args = wp_parse_args( $args, $defaults );

        $where = '';
        if ( ! empty( $args['agent_id'] ) ) {
            $where .= $wpdb->prepare( " AND agent_id = %d", $args['agent_id'] );
        }
        if ( ! empty( $args['stage'] ) ) {
            $where .= $wpdb->prepare( " AND stage = %s", $args['stage'] );
        }

        $sql = "SELECT * FROM $table_name WHERE 1=1 $where ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";

        return $wpdb->get_results( $wpdb->prepare( $sql, $args['number'], $args['offset'] ) );
    }

    /**
     * Update a lead.
     */
    public static function update( $lead_id, $data ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_leads';

        // always update last_activity timestamp
        $data['last_activity'] = current_time( 'mysql' );

        return $wpdb->update( $table_name, $data, array( 'id' => $lead_id ) );
    }

    /**
     * Delete a lead.
     */
    public static function delete( $lead_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_leads';
        return $wpdb->delete( $table_name, array( 'id' => $lead_id ), array( '%d' ) );
    }
} 