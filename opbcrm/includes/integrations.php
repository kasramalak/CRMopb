<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class OPBCRM_Integrations {

    public function __construct() {
        // Hook into Houzez form submission
        add_action('opbez_after_contact_form_submit', array($this, 'capture_opbez_inquiry') );
    }

    /**
     * Capture lead from Houzez inquiry form.
     *
     * @param array $submission Data from the form.
     */
    public function capture_opbez_inquiry( $submission ) {
        $property_id = intval($submission['property_id']);
        $agent_id = 0;

        if ($property_id) {
            $agent_id = get_post_field( 'post_author', $property_id );
        }

        $lead_data = array(
            'name'          => sanitize_text_field( $submission['name'] ),
            'phone'         => sanitize_text_field( $submission['phone'] ),
            'email'         => sanitize_email( $submission['email'] ),
            'source'        => 'opbez_form',
            'stage'         => 'new_lead', // Default stage for new leads
            'agent_id'      => $agent_id,
            'custom_fields' => json_encode(array(
                'property_id' => $property_id,
                'message' => sanitize_textarea_field( $submission['message'] )
            ))
        );

        // Call the lead creation function
        OPBCRM_Leads::create( $lead_data );
    }
}

new OPBCRM_Integrations(); 