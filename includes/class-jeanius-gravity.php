<?php
/**
 * Gravity-Forms integration for the Jeanius plugin.
 */
namespace Jeanius;

class Gravity {

	/**  <<<—---------  SET THIS TO YOUR FORM'S ID  */
	const FORM_ID = 4;	// 5 is just an example — look in Gravity Forms list.

	/**
	 * Called once from the main plugin class to register hooks.
	 */
	public static function init() {

        /**
         * Fires right after Gravity Forms creates the new WP user.
         *
         * @param int   $user_id  The ID of the user just created.
         * @param array $feed     The User-Registration feed object.
         * @param array $entry    The full GF entry array.
         * @param array $user_pass Unused here.
         */
        add_action(
            'gform_user_registered',
            [ __CLASS__, 'create_assessment_after_user_created' ],
            10,
            4
        );
    }
    

	/**
	 * Create (or reset) the Jeanius CPT right after the form is submitted.
	 *
	 * @param array $entry Gravity Forms entry.
	 * @param array $form  Gravity Forms form object.
	 */
    public static function create_assessment_after_user_created( $user_id, $feed, $entry, $user_pass ) {

        if ( (int) $entry['form_id'] !== self::FORM_ID ) {
            return;
        }
    
        // 1. Ensure CPT exists and grab its ID
        $post_id = Provisioner::create_or_reset_assessment( $user_id );
    
        // 2. Pull raw values from the entry (update IDs to your own)
        $dob_raw      = rgar( $entry, '4' );   // <- DOB field ID
        $colleges_raw = rgar( $entry, '7' );   // <- List field ID 
		$parent_email = rgar( $entry, '8' );   // <- Parent Email 
    
        // 3. Save to ACF
        //    (ACF will handle date formatting and repeater rows)
        update_field( 'dob', $dob_raw, $post_id );
        update_field( 'parent_email', $parent_email, $post_id );
        update_field( 'target_colleges', $colleges_raw, $post_id );   

        // 4. Get the user object to retrieve their email
        $user = get_userdata($user_id);
        if ($user) {
            $student_email = $user->user_email;

            // 5. Generate the password reset link
            $key = get_password_reset_key($user);
            if (!is_wp_error($key)) {
                // Build the password reset URL - using custom format
                $reset_url = site_url("/password-setting/?key=$key&user=" . rawurlencode($user->user_email));


                // 6. Send the password reset URL to ActiveCampaign
                self::send_password_link_to_activecampaign($student_email, $reset_url);

                // 7. Send custom onboarding email with the same reset link
                self::send_onboarding_email($user, $reset_url);
            }
        }

        // Notify admin only, avoid triggering default user reset email
        wp_new_user_notification( $user_id, null, 'admin' );
    }
    
    /**
     * Send the password reset link to ActiveCampaign
     * 
     * @param string $email The student's email address
     * @param string $reset_url The password reset URL
     * @return bool Success or failure
     */
    private static function send_password_link_to_activecampaign($email, $reset_url) {
        // Hard-coded ActiveCampaign API credentials
        $api_url = 'https://jeanius.api-us1.com';
        $api_key = '9894fe769165e0b980a18805453f80104af4b44a68038a7f3bf0831e197328571c688b45';
        
        error_log('Sending password reset link to ActiveCampaign for email: ' . $email);
        
        // First, find the contact by email
        $contact_endpoint = $api_url . '/api/3/contacts';
        
        // Search for the contact
        $contact_response = wp_remote_get($contact_endpoint . '?email=' . urlencode($email), array(
            'headers' => array(
                'Api-Token' => $api_key
            )
        ));
        
        if (is_wp_error($contact_response)) {
            error_log('ActiveCampaign API error: ' . $contact_response->get_error_message());
            return false;
        }
        
        $contact_data = json_decode(wp_remote_retrieve_body($contact_response), true);
        
        // If contact doesn't exist, create it
        $contact_id = null;
        if (empty($contact_data['contacts']) || count($contact_data['contacts']) === 0) {
            // Create the contact
            $create_contact_response = wp_remote_post($contact_endpoint, array(
                'headers' => array(
                    'Api-Token' => $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'contact' => array(
                        'email' => $email,
                        'firstName' => '',
                        'lastName' => ''
                    )
                ))
            ));
            
            if (is_wp_error($create_contact_response)) {
                error_log('Failed to create contact: ' . $create_contact_response->get_error_message());
                return false;
            }
            
            $created_contact = json_decode(wp_remote_retrieve_body($create_contact_response), true);
            $contact_id = $created_contact['contact']['id'];
        } else {
            $contact_id = $contact_data['contacts'][0]['id'];
        }
        
        if (!$contact_id) {
            error_log('No contact ID found for email: ' . $email);
            return false;
        }
        
        // Update the contact's custom field directly with the password reset link
        // The field ID 13 is for "Student Account Activation Link"
        $field_id = 13;
        $field_value_endpoint = $api_url . '/api/3/fieldValues';
        
        // Create new field value (this will overwrite any existing value)
        $create_value_response = wp_remote_post($field_value_endpoint, array(
            'headers' => array(
                'Api-Token' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'fieldValue' => array(
                    'contact' => $contact_id,
                    'field' => $field_id,
                    'value' => $reset_url
                )
            ))
        ));
        
        if (is_wp_error($create_value_response)) {
            error_log('Failed to create field value: ' . $create_value_response->get_error_message());
            return false;
        }

        $create_result = json_decode(wp_remote_retrieve_body($create_value_response), true);

        if (isset($create_result['fieldValue'])) {
            error_log('Successfully sent password reset link to ActiveCampaign for email: ' . $email);
            return true;
        } else {
            error_log('Failed to send password reset link to ActiveCampaign. Response: ' . wp_remote_retrieve_body($create_value_response));
            return false;
        }
    }

    /**
     * Send a custom onboarding email that re-uses the generated reset URL.
     *
     * @param \WP_User $user The user who just registered.
     * @param string   $reset_url The password reset URL generated for the user.
     *
     * @return void
     */
    private static function send_onboarding_email($user, $reset_url) {
        if (empty($user->user_email)) {
            return;
        }

        $student_name = !empty($user->display_name) ? $user->display_name : $user->user_login;

        $subject = 'Welcome to Jeanius – Activate Your Account';
        $message = sprintf(
            "Hi %s,\n\nWelcome to Jeanius! To get started, please set your password using the link below:\n%s\n\nIf you didn\'t request this account, please ignore this email.\n\nThanks,\nThe Jeanius Team",
            $student_name,
            $reset_url
        );

        wp_mail($user->user_email, $subject, $message);
    }
}
