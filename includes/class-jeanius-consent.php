<?php
namespace Jeanius;

class Consent {

    const FORM_ID = 5;   // Gravity Form ID for consent

    public static function init() {
        add_action(
            'gform_after_submission_' . self::FORM_ID,
            [ __CLASS__, 'save_consent' ],
            10,
            2
        );
    }

    public static function save_consent( $entry, $form ) {

        /* -------------  IDs that come from your form ------------- */
        $post_id_field   = 1;  // hidden assessment-post ID
        $consent_field_adult = 15;  // radio "I agree / I decline" for adults
        $consent_field_minor = 16;  // radio "I agree / I decline" for minors
        $share_field     = 17;  // checkbox "I agree to share..."
        $parent_field    = 8;  // parent email
        /* --------------------------------------------------------- */

        $post_id = \Jeanius\current_assessment_id();     // ← replace old hidden-field lookup
        if ( ! $post_id ) {
            error_log( 'Jeanius: consent form missing post_id.' );
            return;
        }

        /** 1 ▸ Consent Granted - Always set to true **/
        \update_field( 'consent_granted', true, $post_id );

        /** 2 ▸ Share with Parent **/
        $consent_adult_value = rgar( $entry, strval( $consent_field_adult ) );
        $consent_minor_value = rgar( $entry, strval( $consent_field_minor ) );
        
        // If either adult or minor consent is "I agree", set share_with_parent to true
        $adult_granted = ( $consent_adult_value === 'I agree' );
        $minor_granted = ( $consent_minor_value === 'I agree' );
        $share_yes = $adult_granted || $minor_granted;
        
        \update_field( 'share_with_parent', $share_yes, $post_id );

        /** 3 ▸ Parent Email (minors only) **/
        $parent_email = rgar( $entry, strval( $parent_field ) );
        if ( ! empty( $parent_email ) ) {
            \update_field( 'parent_email', $parent_email, $post_id );
        }
    }
}