<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Get CSS styles for PDF
 * 
 * @return string The CSS styles
 */
function get_assessment_pdf_styles() {
    return '
    <style>
        @font-face {
            font-family: "Bebas Neu Pro Regular";
            font-weight: 400;
            font-style: normal;
            font-display: swap;
            src: url("fonts/bebasneuepro-regular.ttf") format("truetype");
        }
        @font-face {
            font-family: "Bebas Neu Pro EX EB";
            font-weight: 800;
            font-style: normal;
            font-display: swap;
            src: url("fonts/bebasneuepro-expeb.ttf") format("truetype");
        }
        @font-face {
            font-family: "Bebas Neu Pro EX EB Italic";
            font-weight: 800;
            font-style: italic;
            font-display: swap;
            src: url("fonts/bebasneuepro-expebit.ttf") format("truetype");
        }
        @font-face {
            font-family: "Bebas Neu Pro EX MD";
            font-weight: 500;
            font-style: normal;
            font-display: swap;
            src: url("fonts/bebasneuepro-expmd.ttf") format("truetype");
        }
        @font-face {
            font-family: "Macklin Sans Bold Italic";
            font-weight: 700;
            font-style: italic;
            font-display: swap;
            src: url("fonts/MacklinSans-BoldItalic.ttf") format("truetype");
        }
        @font-face {
            font-family: "Macklin Sans EX Bold Italic";
            font-weight: 800;
            font-style: italic;
            font-display: swap;
            src: url("fonts/MacklinSans-ExtraBoldIt.ttf") format("truetype");
        }
        @page {
            margin-top: 150px;
            margin-left: 0;
            margin-right: 0;
            margin-bottom: 70px;
        }

        .no-pdf {
            display: none !important;
        }

        body {
            margin: 0;
            padding: 0;
        }
        #result-page-header {
            background-color: #87B9E1;
            width: 100%;
            margin: 0;
            padding: 0;
        }
        #result-page-header .header-container {
            padding: 20px 20px 20px 60px;
        }
        #result-page-header td {
            vertical-align: middle;
        }
        .pdf-fixed-header {
            position: fixed;
            top: -150px;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        .pdf-footer {
            position: fixed;
            bottom: -70px;
            left: 0;
            right: 0;
            height: 30px;
            font-size: 12px;
            color: #555;
            text-align: center;
            border-top: 1px solid #ccc;
            line-height: 30px;
        }
        .no-break {
            page-break-inside: avoid;
        }
        .page-break {
            page-break-after: always;
        }
        h2:not(.title) {
            color: #003d7c;
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 4px;
            margin-bottom: 0;
        }

        #loading {
            font-size: 1.1rem;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 0;
        }

        th,
        td {
            border: none;
            padding: 6px;
        }

        ul {
            margin-left: 1.2em;
        }

        header {
            background-color: #8bb9dc;
        }

        .logo img {
            width: 75%;
        }

        .header-container {
            padding: 20px 20px 20px 60px;
        }

        .header-container th,
        .header-container td {
            border: none;
        }

        .info {
            text-align: right;
        }

        .info .info-item {
            border-bottom: 3px dotted #1c76bd;
            padding: 5px 0;
        }

        .info .info-item:last-child {
            margin-bottom: 0;
        }

        .info .item-title {
            font-family: "Bebas Neu Pro Regular";
            text-align: right;
            color: #1c76bd;
            font-size: 12px;
            font-weight: 400;
        }

        .info .name,
        .info .date,
        .info .advisor {
            color: #1c76bd;
            font-size: 12px;
            padding-left: 0;
            font-family: "Bebas Neu Pro EX EB";
            font-weight: 800;
        }

        .intro-section .stake-content {
            position: relative;
            width: 700px;
            margin: 40px auto -40px;
            text-align: center
        }

        .intro-section .stake-content img {
            width: 700px;
            height: 330px;
            object-fit: contain;
            margin: 0 auto;
        }

        .intro-section .stake-content ul {
            padding: 0;
            list-style: none;
            width: 100%;
            margin: 0;
            position: absolute;
            top: 0
        }

        .intro-section .stake-content ul li {
            position: absolute;
            text-align: center;
            color: #ef4136;
            font-weight: 700;
            font-style: italic;
            display: inline-block
            width: 100px;
            font-family: "Macklin Sans Bold Italic";
            font-weight: 700;
            font-style: italic;
        }

        .stake-content li {
            padding: 5px;
            width: 130px !important;
            border-radius: 5px;
            transform: translate(-15px, -20px);
            line-height: 1.2 !important;
        }

        /* top good */
        .intro-section .stake-content ul li:first-child {
            top: 0px; 
            left: -5px;
        }
        
        /* top good */
        .intro-section .stake-content ul li:nth-child(2) {
            top: -25px;
            left: 145px;
        }


        .intro-section .stake-content ul li:nth-child(3) {
            top: -45px;
            left: 290px; 
        }
        
        /* top good */
        .intro-section .stake-content ul li:nth-child(4) {
            top: -25px;
            left: 440px;
        }
        
        /* top good */
        .intro-section .stake-content ul li:nth-child(5) {
            top: 0px;
            left: 590px;
        }

        .results-page .intro-section .stake-content img {
            position: relative;
            top: 20px;
        }

        .info-over-pillar p {
            color: #00406c;
            font-weight: 700;
            font-style: italic;
            font-size: 27px;
            max-width: 345px;
            margin: 0 auto;
            line-height: 1;
            z-index: 99999;
            text-align: center;
            font-family: "Macklin Sans Bold Italic";
            position: absolute;
            top: 100px;
            left: 100px;
        }


        .transcendent-section .transcendent-content {
            position: relative;
        }

        .transcendent-section .transcendent-content .bg-img {
            height: 330px;
            margin-bottom: 30px
        }

        .transcendent-section .transcendent-content .bg-img img {
            width: 100%;
            height: 100%;
            object-fit: contain
        }

        .transcendent-section .transcendent-content .hidden {
            display: none
        }

        .transcendent-section .transcendent-content .labels {
            position: absolute;
            top: 100px;
            left: 0;
            padding: 0;
            list-style: none;
            margin: 0;
            width: 100%
        }

        .transcendent-section .transcendent-content .labels li {
            position: absolute;
            color: #00406c;
            font-weight: 700;
            font-style: italic;
            font-size: 27px;
            font-family: "Macklin Sans Bold Italic";
            font-weight: 700;
            font-style: italic;
        }

        .transcendent-section .transcendent-content .labels li:first-child {
            left: 60%
        }

        .transcendent-section .transcendent-content .labels li:nth-child(2) {
            left: 42%;
            top: 38px
        }

        .transcendent-section .transcendent-content .labels li:last-child {
            left: 25%;
            top: 95px
        }

        .sum-of-jeanious-section .bg-wrapper {
            width: 500px;
            height: 500px;
            margin: 20px auto 0;
        }

        .sum-of-jeanious-section .bg-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .ribbon-bar h2 {
            background-color: #00406b;
            color: white;
            font-family: "Bebas Neu Pro EX EB Italic";
            padding: 0 20px 6px 60px;
            max-width: 50%;
            border-top-right-radius: 25px;
            font-weight: 800;
            font-style: italic;
            font-size: 26px;
        }

        .report-section {
            margin-bottom: 25px;
            font-family: "Bebas Neu Pro EX MD";
            font-size: 14px;
            color: #000000;
            font-weight: 500;
        }

        .report-section p {
            padding: 0 60px;
            margin-top: 0;
            margin-bottom: 12px;
        }

        .report-section i,
        .report-section em,
        .report-section p:not(.section-title) strong {
            font-size: 16px;
            font-family: "Macklin Sans EX Bold Italic";
            color: #231F20;
            font-weight: 800;
            font-style: italic;
        }

        .report-section p.center-align {
            text-align: center;
        }

        .report-section h2.title,
        .report-section h3,
        .report-section h4,
        .report-section h5,
        .report-section h6 {
            padding: 0 60px;
            color: #231F20;
            margin: 0;
            font-family: "Macklin Sans EX Bold Italic";
            font-weight: 800;
            font-style: italic;
        }

        .report-section h2.title {
            font-size: 21px;
        }

        .report-section h5 {
            font-size: 16px;
            line-height: 1.14;
            letter-spacing: -0.03px;
        }

        .labels-data ul {
            padding: 0 60px;
            margin: 0;
            list-style: none;
        }

        .blue-box {
            padding: 30px;
            background-color: #bbd6eb;
            border-radius: 20px;
            margin: 0 20px;
            border: 2px solid #1c75bb;
            margin-top: 30px;
        }

        .blue-box .title {
            font-size: 16px;
            color: #231F20;
            font-family: "Macklin Sans EX Bold Italic";
            text-transform: capitalize;
            font-weight: 800;
            font-style: italic;
        }

        .college-info-wrapper h2.title {
            font-size: 24px;
            color: #000000;
            font-family: "Macklin Sans EX Bold Italic";
            font-weight: 800;
            font-style: italic;
        }

        .college-info-wrapper .essay-topic {
            margin-top: 30px;
        }

        .college-info-wrapper .essay-topic .color-blue {
            margin-bottom: 0;
            font-family: "Macklin Sans Bold Italic";
            Text Transform: Capitalize;
            color: #1c76bd;
            font-size: 15px;
            line-height: 1.2;
            font-weight: 700;
            font-style: italic;
        }

        .college-info-wrapper .essay-topic .section-title {
            color: #f04136;
            font-family: "Macklin Sans Bold Italic";
            font-size: 15px;
            padding-left: 80px;
            margin-bottom: 10px;
            margin-top: 10px;
            font-weight: 700;
            font-style: italic;
        }

        .college-info-wrapper .essay-topic .rationale-text {
            padding-left: 80px;
        }

        .college-info-wrapper .essay-topic .writing-outline {
            margin: 15px 0 20px 0;
            padding: 0 0 0 150px;
            list-style: none;
        }

        .college-info-wrapper .essay-topic .writing-outline li {
            position: relative;
            margin-bottom: 25px;
        }

        .college-info-wrapper .essay-topic .writing-outline li .bullet {
            position: absolute;
            left: -35px;
            top: 0;
            height: 25px;
            width: 25px;
        }

        .college-info-wrapper .essay-topic .writing-outline li:last-child {
            margin-bottom: 0;
        }

        .college-info-wrapper .essay-topic .tailoring-tips {
            margin: 15px 0 20px 0;
            padding: 0 0 0 150px;
            list-style: none;
        }

        #result-footer {
            background-color: #a4c2e1;
            padding: 30px 0;
            text-align: center;
        }

        .help-box {
            background-color: #00406b;
            color: #fff;
            width: 480px;
            margin: 0 auto;
            border-radius: 25px;
            padding: 45px;
            position: relative;
        }

        .speech-bubble {
            border: 5px solid #9dc1ec;
            border-radius: 25px;
            padding: 15px 20px 25px;
            position: relative;
            margin-bottom: 50px;
        }

        .speech-bubble h2 {
            color: #ff5c1b;
            margin: 0 0 15px;
            font-size: 80px;
            padding: 0;
            border: none;
            line-height: 1;
        }

        .speech-bubble p {
            font-size: 22px;
            line-height: 1.1;
            margin: 0;
            color: #ffffff;
            font-family: "Bebas Neu Pro EX EB Italic";
            font-weight: 800;
            font-style: italic;
        }

        .call-section p {
            margin: 0 0 5px;
            line-height: 1;
        }

        .call-section p.call {
            color: #9ec2ee;
            font-family: "Macklin Sans Bold Italic";
            font-size: 30px;
            font-weight: 700;
            font-style: italic;
        }

        .call-section .phone-number {
            font-size: 46px;
            font-family: "Bebas Neu Pro EX EB";
            color: #ffffff;
            line-height: 1;
            font-weight: 800;
        }

        .call-section .advising-text {
            font-size: 22px;
            line-height: 1.1;
            color: #ffffff;
            margin: 30px 0;
        }

        .call-section .advising-text strong {
            font-family: "Bebas Neu Pro EX EB Italic";
            font-weight: 800;
            font-style: italic;
        }

        .result-footer-logo {
            max-width: 60%;
            margin: 0 auto;
        }

        .color-blue i,
        .color-blue em,
        .color-blue strong {
            color: #1c76c3;
            font-size: 15px;
            font-family: "Macklin Sans Bold Italic";
            font-weight: 700;
            font-style: italic;
        }

        .color-red i,
        .color-red em,
        .color-red strong {
            color: #f04136;
            font-size: 15px;
            font-family: "Macklin Sans Bold Italic";
            font-weight: 700;
            font-style: italic;
        }

        span.bold {
            font-family: "Macklin Sans EX Bold Italic";
            font-size: 16px;
            color: #000000;
            font-weight: 800;
            font-style: italic;
        }

        .cta-wrapper {
            text-align: center;
            margin-bottom: 20px;
        }

        aside#moove_gdpr_cookie_info_bar { 
        display: none !important;
        }
    </style>
    ';
}

/**
 * Helper function to properly update the PDF file in ACF
 * 
 * @param int $attachment_id The attachment ID to set
 * @param int $post_id The post ID to update
 * @return bool Whether the update was successful
 */
function jeanius_update_pdf_acf_field($attachment_id, $post_id) {
    if (!$attachment_id || !$post_id) {
        error_log('Invalid parameters for updating ACF field: attachment_id=' . $attachment_id . ', post_id=' . $post_id);
        return false;
    }
    
    // Use the correct field name
    $field_name = 'jeanius_report_pdf';
    
    // Get field object to determine type
    $field_obj = get_field_object($field_name, $post_id);
    error_log('ACF field type: ' . ($field_obj ? $field_obj['type'] : 'unknown'));
    
    // Attempt standard update first
    $updated = update_field($field_name, $attachment_id, $post_id);
    error_log('Standard ACF update result: ' . ($updated ? 'success' : 'failed'));
    
    // If that didn't work, try alternative methods based on field type
    if (!$updated && $field_obj) {
        if ($field_obj['type'] === 'file') {
            // For file fields, try both ID and array format
            $updated = update_field($field_name, ['ID' => $attachment_id], $post_id);
            error_log('ACF file array update result: ' . ($updated ? 'success' : 'failed'));
            
            if (!$updated) {
                // Try directly updating post meta
                $meta_key = '_' . $field_obj['name'];
                update_post_meta($post_id, $meta_key, $attachment_id);
                update_post_meta($post_id, $field_obj['name'], $attachment_id);
                error_log('Direct post meta update attempted for ' . $field_obj['name']);
                
                // Force ACF to refresh its cache
                if (function_exists('acf_flush_value_cache')) {
                    acf_flush_value_cache($post_id, $field_obj['key']);
                    error_log('ACF value cache flushed');
                }
                
                $updated = true;
            }
        }
    }
    
    // Verify field was updated
    $current_value = get_field($field_name, $post_id);
    if ($current_value) {
        error_log('Field verification - current value: ' . (is_array($current_value) ? 'array' : $current_value));
    } else {
        error_log('Field verification failed - no value found after update');
    }
    
    // If the field was updated successfully, send the PDF link to ActiveCampaign
    if ($updated && $attachment_id) {
        $pdf_url = wp_get_attachment_url($attachment_id);
        if ($pdf_url) {
            error_log('PDF URL for ActiveCampaign: ' . $pdf_url);
            
            // Get post author and parent email
            $student_email = '';
            $parent_email = '';
            
            $post = get_post($post_id);
            if ($post) {
                $user_id = $post->post_author;
                $user = get_user_by('id', $user_id);
                if ($user) {
                    $student_email = $user->user_email;
                }
                
                $parent_email = get_field('parent_email', $post_id);
            }
            
            // Send PDF URL to ActiveCampaign
            if (!empty($student_email)) {
                jeanius_send_pdf_to_activecampaign($student_email, $pdf_url);
                
                if (!empty($parent_email)) {
                    jeanius_send_pdf_to_activecampaign($parent_email, $pdf_url);
                }
            }
        }
    }
    
    return $updated;
}

/**
 * Send PDF URL to ActiveCampaign
 * 
 * @param string $email The contact's email address
 * @param string $pdf_url The PDF URL to send
 * @return bool Success or failure
 */
function jeanius_send_pdf_to_activecampaign($email, $pdf_url) {
    // Hard-coded ActiveCampaign API credentials
    $api_url = 'https://jeanius.api-us1.com';
    $api_key = '9894fe769165e0b980a18805453f80104af4b44a68038a7f3bf0831e197328571c688b45';
    
    error_log('Sending PDF URL to ActiveCampaign for email: ' . $email);
    
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
    
    // Step 1: Find the custom field ID for ASSESSMENT_LINK
    $fields_endpoint = $api_url . '/api/3/fields';
    $fields_response = wp_remote_get($fields_endpoint, array(
        'headers' => array(
            'Api-Token' => $api_key
        )
    ));
    
    if (is_wp_error($fields_response)) {
        error_log('Failed to get custom fields: ' . $fields_response->get_error_message());
        return false;
    }
    
    $fields_data = json_decode(wp_remote_retrieve_body($fields_response), true);
    $field_id = null;
    
    // Debug output all fields to find the correct one
    error_log('Searching for ASSESSMENT_LINK field among ' . count($fields_data['fields']) . ' fields');
    
    foreach ($fields_data['fields'] as $field) {
        error_log('Field: ' . $field['title'] . ' (ID: ' . $field['id'] . ')');
        // Look for the field with title ASSESSMENT_LINK or similar
        if (stripos($field['title'], 'ASSESSMENT_LINK') !== false || 
            stripos($field['title'], 'Assessment Link') !== false ||
            (isset($field['perstag']) && $field['perstag'] == '%ASSESSMENT_LINK%')) {
            $field_id = $field['id'];
            error_log('Found ASSESSMENT_LINK field with ID: ' . $field_id);
            break;
        }
    }
    
    if (!$field_id) {
        error_log('Could not find ASSESSMENT_LINK field ID');
        return false;
    }
    
    // Step 2: Create a new field value directly (simplifying our approach)
    $field_value_endpoint = $api_url . '/api/3/fieldValues';
    
    // Create new field value - this will overwrite any existing value
    $create_value_response = wp_remote_post($field_value_endpoint, array(
        'headers' => array(
            'Api-Token' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'fieldValue' => array(
                'contact' => $contact_id,
                'field' => $field_id,
                'value' => $pdf_url
            )
        ))
    ));
    
    // Debug the request
    error_log('Sending request to create field value: ' . json_encode(array(
        'fieldValue' => array(
            'contact' => $contact_id,
            'field' => $field_id, 
            'value' => $pdf_url
        )
    )));
    
    if (is_wp_error($create_value_response)) {
        error_log('Failed to create field value: ' . $create_value_response->get_error_message());
        return false;
    }
    
    $create_result = json_decode(wp_remote_retrieve_body($create_value_response), true);
    
    // Log the response for debugging
    error_log('Field value creation response: ' . wp_remote_retrieve_body($create_value_response));
    
    if (isset($create_result['fieldValue'])) {
        error_log('Successfully created/updated field value with PDF URL');
        return true;
    } else {
        error_log('Failed to create/update field value.');
        return false;
    }
}

/**
 * Prepare HTML content for PDF generation
 * 
 * @param string $content The raw content
 * @param int $post_id The assessment post ID
 * @return string The processed HTML
 */
function prepare_assessment_html_for_pdf($content, $post_id) {
    // Debug logging
    error_log('Preparing HTML for PDF for assessment #' . $post_id);
    
    // Remove any "Send PDF" buttons
    $content = preg_replace('/<a[^>]*id=["\']sendPdfBtn["\'][^>]*>.*?<\/a>/is', '', $content);
    
    // Add any other content processing logic here
    
    return $content;
}

/**
 * Generate PDF from HTML and save to media library
 * 
 * @param string $raw_html The HTML content for the PDF
 * @param int $post_id The assessment post ID
 * @return int|bool The attachment ID if successful, false if failed
 */
function generate_assessment_pdf($raw_html, $post_id) {
    // Debug logging
    error_log('Starting PDF generation for assessment #' . $post_id);
    
    // Extract <header id="result-page-header"> from HTML
    if (preg_match('/<header[^>]*id=["\']result-page-header["\'][^>]*>.*?<\/header>/is', $raw_html, $matches)) {
        $pdf_header_html = $matches[0];
    } else {
        $pdf_header_html = '';
        error_log('No header found in HTML for PDF generation');
    }

    // Remove original header from normal flow so it's not duplicated
    $raw_html = preg_replace('/<header[^>]*id=["\']result-page-header["\'][^>]*>.*?<\/header>/is', '', $raw_html);

    // Get the CSS styles
    $extra_css = get_assessment_pdf_styles();

    $pdf_header_block = '<div class="pdf-fixed-header">' . $pdf_header_html . '</div>';
    $pdf_footer_block = '<div class="pdf-footer"></div>';

    $html = '
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        ' . $extra_css . '
    </head>
    <body>
        ' . $pdf_header_block . $pdf_footer_block . $raw_html . '
    </body>
    </html>';

    try {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('fontDir', __DIR__ . '/fonts');
        $options->set('isPhpEnabled', true);
        $options->setChroot(get_home_path());
    
        $dompdf = new Dompdf($options);
    
        // Important: This makes relative paths in CSS work
        $dompdf->setBasePath(home_url());
    
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
    
        // Page numbers
        $canvas = $dompdf->getCanvas();
        $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
            $text = "Page $pageNumber of $pageCount";
            $font = $fontMetrics->get_font("Arial", "normal");
            $size = 10;
            $width = $fontMetrics->get_text_width($text, $font, $size);
            $x = ($canvas->get_width() - $width) / 2; // center horizontally
            $y = $canvas->get_height() - 17; // distance from bottom
            $canvas->text($x, $y, $text, $font, $size);
        });
    } catch (Exception $e) {
        error_log('Error rendering PDF: ' . $e->getMessage());
        return false;
    }

    // Get post author
    $post = get_post($post_id);
    $user_id = $post->post_author;
    $user = get_user_by('id', $user_id);
    
    // Generate a unique filename with timestamp
    $timestamp = date('Y-m-d-H-i-s');
    $filename = 'jeanius-report-' . $user_id . '-' . $timestamp . '.pdf';
    
    // Create the uploads directory path
    $upload_dir = wp_upload_dir();
    $pdf_path = $upload_dir['basedir'] . '/' . $filename;
    
    // Save the PDF file
    try {
        $pdf_content = $dompdf->output();
        $bytes_written = file_put_contents($pdf_path, $pdf_content);
        
        if ($bytes_written === false) {
            error_log('Failed to write PDF file to ' . $pdf_path);
            return false;
        }
        
        error_log('Successfully saved PDF to ' . $pdf_path . ' (' . $bytes_written . ' bytes)');
    } catch (Exception $e) {
        error_log('Error saving PDF file: ' . $e->getMessage());
        return false;
    }
    
    // Get file info
    $filetype = wp_check_filetype($filename, null);
    
    // Prepare attachment data
    $attachment = array(
        'guid'           => $upload_dir['url'] . '/' . $filename,
        'post_mime_type' => $filetype['type'],
        'post_title'     => 'Jeanius Report - ' . ($user ? $user->display_name : 'User ' . $user_id),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );
    
    // Make sure we have required functions
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    // Insert attachment
    try {
        $attach_id = wp_insert_attachment($attachment, $pdf_path, $post_id);
        
        if (!$attach_id || is_wp_error($attach_id)) {
            error_log('Failed to create attachment for PDF: ' . (is_wp_error($attach_id) ? $attach_id->get_error_message() : 'Unknown error'));
            return false;
        }
        
        // Generate metadata for the attachment
        $attach_data = wp_generate_attachment_metadata($attach_id, $pdf_path);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        error_log('Successfully created PDF attachment #' . $attach_id . ' for assessment #' . $post_id);
        
        // Update the ACF field with our improved function
        $updated = jeanius_update_pdf_acf_field($attach_id, $post_id);
        error_log('Advanced ACF field update for jeanius_report_pdf: ' . ($updated ? 'success' : 'failed'));
        
        return $attach_id;
    } catch (Exception $e) {
        error_log('Error creating attachment: ' . $e->getMessage());
        return false;
    }
}

/**
 * Automatically generate and save PDF when assessment is created or regenerated
 * 
 * @param int $post_id The assessment post ID
 * @return int|bool The attachment ID if successful, false if failed
 */
function jeanius_auto_generate_pdf($post_id) {
    // Make sure this is a jeanius_assessment post
    if (get_post_type($post_id) !== 'jeanius_assessment') {
        return false;
    }
    
    error_log('Starting automatic PDF generation for assessment #' . $post_id);
    
    // First check if PDF already exists
    $existing_pdf = get_field('jeanius_report_pdf', $post_id);
    if ($existing_pdf) {
        error_log('PDF already exists for assessment #' . $post_id . '. Skipping generation.');
        return $existing_pdf;
    }
    
    $post = get_post($post_id);
    if ( ! $post ) {
        error_log('Assessment post not found for #' . $post_id);
        return false;
    }

    $results_html = \Jeanius\Wizard_Page::get_results_body_html($post_id, false);

    if ( '' === trim( $results_html ) ) {
        error_log('Results HTML is empty for assessment #' . $post_id);
        return false;
    }

    error_log('Captured results HTML for assessment #' . $post_id . ' (' . strlen( $results_html ) . ' bytes)');

    $raw_html = prepare_assessment_html_for_pdf($results_html, $post_id);
    
    // Create the PDF
    $attachment_id = generate_assessment_pdf($raw_html, $post_id);
    
    // If successful, update ACF field with the attachment
    if ($attachment_id) {
        // Update using our improved function
        $updated = jeanius_update_pdf_acf_field($attachment_id, $post_id);
        error_log('Auto-generation PDF field update: ' . ($updated ? 'success' : 'failed'));
        
        // Also update a custom field to indicate PDF was generated
        update_field('pdf_generated', true, $post_id);
        
        // Get student email to send PDF URL to ActiveCampaign
        $student_email = '';
        $parent_email = '';
        
        $user_id = $post->post_author;
        $user = get_user_by('id', $user_id);
        if ($user) {
            $student_email = $user->user_email;
            $parent_email = get_field('parent_email', $post_id);
            
            // Update the PDF URL in ActiveCampaign for student only
            $pdf_url = wp_get_attachment_url($attachment_id);
            if ($pdf_url && !empty($student_email)) {
                jeanius_send_pdf_to_activecampaign($student_email, $pdf_url);
                
                // Apply appropriate tags
                $api_url = 'https://jeanius.api-us1.com';
                $api_key = '9894fe769165e0b980a18805453f80104af4b44a68038a7f3bf0831e197328571c688b45';
                
                // Apply tags
                jeanius_apply_activecampaign_tag($api_url, $api_key, $student_email, 'Assessment_Completed_Student');
                
                if (!empty($parent_email)) {
                    jeanius_apply_activecampaign_tag($api_url, $api_key, $parent_email, 'Assessment_Completed_Parent');
                }
            }
        }
        
        return $attachment_id;
    }
    
    return false;
}

/**
 * Hook into assessment creation and regeneration
 */
function jeanius_assessment_hooks() {
    // Hook into after assessment content is generated
    add_action('jeanius_assessment_generated', 'jeanius_auto_generate_pdf', 10, 1);
    
    // Add hook for assessment completion
    add_action('acf/save_post', 'jeanius_check_assessment_complete', 30, 1);
    
    // Add direct hook for assessment publishing
    add_action('publish_jeanius_assessment', 'jeanius_handle_assessment_publish', 10, 1);
    
    // Add hook for status transitions
    add_action('transition_post_status', 'jeanius_check_assessment_status_change', 10, 3);
    
    // Add hook for when the final stage is set
    add_action('acf/update_value/name=stage_data', 'jeanius_check_stage_completion', 10, 3);
}
add_action('init', 'jeanius_assessment_hooks');

/**
 * Check if stage data indicates completion
 * 
 * @param mixed $value The field value
 * @param int $post_id The post ID
 * @param array $field The field array
 * @return mixed The field value (unchanged)
 */
function jeanius_check_stage_completion($value, $post_id, $field) {
    // Check if we have stage data and it indicates completion
    if (is_array($value) && isset($value['current_stage']) && $value['current_stage'] === 'complete') {
        error_log('Stage data indicates assessment #' . $post_id . ' is complete. Triggering PDF generation.');
        
        // Schedule PDF generation with a slight delay to ensure all other fields are saved
        wp_schedule_single_event(time() + 5, 'jeanius_delayed_pdf_generation', array($post_id));
    }
    
    // Return the value unchanged
    return $value;
}

/**
 * Check if assessment is complete after ACF fields are saved
 * 
 * @param int $post_id The post ID
 */
function jeanius_check_assessment_complete($post_id) {
    // Only run for jeanius_assessment post type
    if (get_post_type($post_id) !== 'jeanius_assessment') {
        return;
    }
    
    // Check if assessment is complete via the assessment_complete field
    $assessment_complete = get_field('assessment_complete', $post_id);
    
    // Check stage data properly
    $stage_data = get_field('stage_data', $post_id);
    $stage_complete = false;
    
    // Make sure stage_data is an array before trying to access keys
    if (is_array($stage_data) && isset($stage_data['current_stage'])) {
        $stage_complete = ($stage_data['current_stage'] === 'complete');
    }
    
    // Determine if assessment is complete
    $is_complete = $assessment_complete || $stage_complete;
    
    if ($is_complete) {
        // Generate the PDF
        jeanius_auto_generate_pdf($post_id);
    }
}

/**
 * Additional hook for when assessment is published
 * 
 * @param int $post_id The post ID
 */
function jeanius_handle_assessment_publish($post_id) {
    error_log('Assessment #' . $post_id . ' was published, checking for PDF generation');
    jeanius_auto_generate_pdf($post_id);
}

/**
 * Check for status changes that might indicate completion
 * 
 * @param string $new_status New post status
 * @param string $old_status Old post status
 * @param WP_Post $post The post object
 */
function jeanius_check_assessment_status_change($new_status, $old_status, $post) {
    // Only run for our post type
    if ($post->post_type !== 'jeanius_assessment') {
        return;
    }
    
    // If status is changing to publish
    if ($new_status === 'publish' && $old_status !== 'publish') {
        error_log('Assessment #' . $post->ID . ' status changed from ' . $old_status . ' to ' . $new_status);
        jeanius_auto_generate_pdf($post->ID);
    }
}

/**
 * Modify the existing regenerate_assessment function to trigger PDF generation
 */
function jeanius_extend_regenerate_assessment($post_id) {
    // Only run for our custom post type
    if (get_post_type($post_id) !== 'jeanius_assessment') {
        return;
    }

    // Ensure the regenerate button was pressed
    if (empty($_POST['acf']['field_68af564391c91'])) {
        return;
    }
    
    error_log('Regeneration button pressed for assessment #' . $post_id);

    // Reset downstream automation flags so they can run after regeneration completes
    delete_post_meta($post_id, '_jeanius_assessment_generated_pending');
    delete_post_meta($post_id, '_jeanius_assessment_generated_at');

    $keep = [
        'dob',
        'consent_granted',
        'share_with_parent',
        'parent_email',
        'stage_data',
        'full_stage_data',
        'target_colleges',
    ];

    // Remove all other ACF fields for this post
    $fields = get_field_objects($post_id);
    if ($fields) {
        foreach ($fields as $field) {
            if (!in_array($field['name'], $keep, true)) {
                delete_field($field['key'], $post_id);
            }
        }
    }

    // Call helper to regenerate the assessment contents
    if (function_exists('\\Jeanius\\regenerate_assessment')) {
        try {
            \Jeanius\regenerate_assessment($post_id);
            error_log('Assessment #' . $post_id . ' regenerated successfully');
            
            // Schedule PDF generation to run after page load completes
            wp_schedule_single_event(time() + 5, 'jeanius_delayed_pdf_generation', array($post_id));
            error_log('Scheduled delayed PDF generation for assessment #' . $post_id);
            
        } catch (Exception $e) {
            error_log('Error during assessment regeneration: ' . $e->getMessage());
        }
    } else {
        error_log('\\Jeanius\\regenerate_assessment function not found');
    }

    // Optional admin notice confirming regeneration
    add_action('admin_notices', function () {
        echo '<div class="notice notice-success is-dismissible"><p>Assessment regeneration triggered.</p></div>';
    });
}

/**
 * Callback for delayed PDF generation
 * 
 * @param int $post_id The post ID
 */
function jeanius_delayed_pdf_generation($post_id) {
    error_log('Running delayed PDF generation for assessment #' . $post_id);
    jeanius_auto_generate_pdf($post_id);
}
add_action('jeanius_delayed_pdf_generation', 'jeanius_delayed_pdf_generation');

function send_results_pdf_from_dom()
{
    if (empty($_POST['html'])) {
        wp_send_json_error("No HTML received.");
    }

    // Get post ID from the AJAX request
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
    error_log('PDF button pressed for assessment #' . $post_id);
    
    $raw_html = stripslashes($_POST['html']);

    $raw_html = preg_replace('/<a[^>]*id=["\']sendPdfBtn["\'][^>]*>.*?<\/a>/is', '', $raw_html);

    // Extract <header id="result-page-header"> from HTML
    if (preg_match('/<header[^>]*id=["\']result-page-header["\'][^>]*>.*?<\/header>/is', $raw_html, $matches)) {
        $pdf_header_html = $matches[0];
    } else {
        $pdf_header_html = '';
    }

    // Remove original header from normal flow so it's not duplicated
    $raw_html = preg_replace('/<header[^>]*id=["\']result-page-header["\'][^>]*>.*?<\/header>/is', '', $raw_html);

    $extra_css = get_assessment_pdf_styles();

    $pdf_header_block = '<div class="pdf-fixed-header">' . $pdf_header_html . '</div>';

    $pdf_footer_block = '<div class="pdf-footer"></div>';

    $html = '
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        ' . $extra_css . '
    </head>
    <body>
        ' . $pdf_header_block . $pdf_footer_block . $raw_html . '
    </body>
    </html>';

    try {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('fontDir', __DIR__ . '/fonts');
        $options->set('isPhpEnabled', true);
        $options->setChroot(get_home_path());
    
        $dompdf = new Dompdf($options);
    
        // Important: This makes relative paths in CSS work
        $dompdf->setBasePath(home_url());
    
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
    
        // Page numbers
        $canvas = $dompdf->getCanvas();
        $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
            $text = "Page $pageNumber of $pageCount";
            $font = $fontMetrics->get_font("Arial", "normal");
            $size = 10;
            $width = $fontMetrics->get_text_width($text, $font, $size);
            $x = ($canvas->get_width() - $width) / 2; // center horizontally
            $y = $canvas->get_height() - 17; // distance from bottom
            $canvas->text($x, $y, $text, $font, $size);
        });
    } catch (Exception $e) {
        error_log('Error rendering PDF: ' . $e->getMessage());
        wp_send_json_error("Failed to generate PDF: " . $e->getMessage());
        return;
    }

    // Get current user
    $current_user = wp_get_current_user();
    
    // Generate a unique filename with timestamp
    $timestamp = date('Y-m-d-H-i-s');
    $filename = 'jeanius-report-' . $current_user->ID . '-' . $timestamp . '.pdf';
    
    // Create the uploads directory path
    $upload_dir = wp_upload_dir();
    $pdf_path = $upload_dir['basedir'] . '/' . $filename;
    
    try {
        // Save the PDF file
        $pdf_content = $dompdf->output();
        $bytes_written = file_put_contents($pdf_path, $pdf_content);
        
        if ($bytes_written === false) {
            error_log('Failed to write PDF file to ' . $pdf_path);
            wp_send_json_error("Failed to save PDF file.");
            return;
        }
        
        error_log('Successfully saved PDF to ' . $pdf_path . ' (' . $bytes_written . ' bytes)');
    } catch (Exception $e) {
        error_log('Error saving PDF file: ' . $e->getMessage());
        wp_send_json_error("Error saving PDF: " . $e->getMessage());
        return;
    }
    
    // Get parent email from ACF field if post_id is available
    $parent_email = '';
    if ($post_id > 0) {
        $parent_email = get_field('parent_email', $post_id);
    }
    
    // Set up email parameters
    $to = $current_user->user_email;
    $subject = "Your Jeanius Report PDF";
    $body = "Please find the attached report.";
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    add_filter('wp_mail_from_name', function () {
        return 'Jeanius';
    });

    // Send to current user
    $sent = wp_mail($to, $subject, $body, $headers, [$pdf_path]);
    
    // Also send to parent email if available
    $parent_sent = false;
    if (!empty($parent_email)) {
        $parent_sent = wp_mail($parent_email, $subject, $body, $headers, [$pdf_path]);
    }
    
    // SAVE TO MEDIA LIBRARY
    
    // Make sure we have required functions
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    // Get file info
    $filetype = wp_check_filetype($filename, null);
    
    // Prepare attachment data
    $attachment = array(
        'guid'           => $upload_dir['url'] . '/' . $filename,
        'post_mime_type' => $filetype['type'],
        'post_title'     => 'Jeanius Report - ' . $current_user->display_name,
        'post_content'   => '',
        'post_status'    => 'inherit'
    );
    
    try {
        // Insert attachment
        $attach_id = wp_insert_attachment($attachment, $pdf_path, $post_id);
        
        if (!$attach_id || is_wp_error($attach_id)) {
            error_log('Failed to create attachment for PDF: ' . (is_wp_error($attach_id) ? $attach_id->get_error_message() : 'Unknown error'));
            wp_send_json_error("Failed to add PDF to media library.");
            return;
        }
        
        // Generate metadata for the attachment
        $attach_data = wp_generate_attachment_metadata($attach_id, $pdf_path);
        wp_update_attachment_metadata($attach_id, $attach_data);
        
        error_log('Successfully created PDF attachment #' . $attach_id . ' for assessment #' . $post_id);
        
        // Update the ACF field with the PDF attachment
        if ($post_id > 0) {
            $updated = jeanius_update_pdf_acf_field($attach_id, $post_id);
            error_log('ACF field update for jeanius_report_pdf: ' . ($updated ? 'success' : 'failed'));
        }
    } catch (Exception $e) {
        error_log('Error creating attachment: ' . $e->getMessage());
        wp_send_json_error("Error adding PDF to media library: " . $e->getMessage());
        return;
    }
    
    if ($sent) {
        $response = array(
            "message" => "PDF emailed successfully!",
            "attachment_id" => $attach_id,
            "file_url" => wp_get_attachment_url($attach_id)
        );
        
        if (!empty($parent_email)) {
            $response["parent_email_sent"] = $parent_sent;
        }
        
        wp_send_json_success($response);
    } else {
        wp_send_json_error("Failed to send PDF email.");
    }
}

add_action('wp_ajax_send_results_pdf_from_dom', 'send_results_pdf_from_dom');
add_action('wp_ajax_nopriv_send_results_pdf_from_dom', 'send_results_pdf_from_dom');

/**
 * Send ActiveCampaign tags after assessment completion
 * 
 * @param string $student_email The student's email address
 * @param string $parent_email The parent's email address
 * @param int $post_id The assessment post ID
 */
function jeanius_send_activecampaign_tags($student_email, $parent_email, $post_id = 0) {
    // Hard-coded ActiveCampaign API credentials
    $api_url = 'https://jeanius.api-us1.com';
    $api_key = '9894fe769165e0b980a18805453f80104af4b44a68038a7f3bf0831e197328571c688b45';
    
    // Hard-coded tag to apply
    $tag_name_student = 'Assessment_Completed_Student';
    $tag_name_parent = 'Assessment_Completed_Parent';
    
    // Send tag to student contact
    jeanius_apply_activecampaign_tag($api_url, $api_key, $student_email, $tag_name_student);
    
    // Send tag to parent contact if available
    if (!empty($parent_email)) {
        jeanius_apply_activecampaign_tag($api_url, $api_key, $parent_email, $tag_name_parent);
    }
    
    // Also send PDF URL to student only if it exists
    if ($post_id > 0) {
        // Get the PDF attachment ID
        $attachment_id = get_field('jeanius_report_pdf', $post_id);
        
        if ($attachment_id) {
            $pdf_url = wp_get_attachment_url($attachment_id);
            
            if ($pdf_url) {
                // Send PDF URL to student contact only
                jeanius_send_pdf_to_activecampaign($student_email, $pdf_url);
            }
        }
    }
    
    // Log the API calls
    error_log('ActiveCampaign tags sent for assessment #' . $post_id);
    
    return true;
}

/**
 * Apply an ActiveCampaign tag to a specific contact
 * 
 * @param string $api_url The ActiveCampaign API URL
 * @param string $api_key The ActiveCampaign API key
 * @param string $email The contact's email address
 * @param string $tag_name The tag to apply
 * @return bool Success or failure
 */
function jeanius_apply_activecampaign_tag($api_url, $api_key, $email, $tag_name) {
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
    
    // Find the tag ID by name
    $tag_endpoint = $api_url . '/api/3/tags';
    $tag_response = wp_remote_get($tag_endpoint . '?search=' . urlencode($tag_name), array(
        'headers' => array(
            'Api-Token' => $api_key
        )
    ));
    
    if (is_wp_error($tag_response)) {
        error_log('ActiveCampaign API error: ' . $tag_response->get_error_message());
        return false;
    }
    
    $tag_data = json_decode(wp_remote_retrieve_body($tag_response), true);
    
    // If tag doesn't exist, create it
    $tag_id = null;
    if (empty($tag_data['tags']) || count($tag_data['tags']) === 0) {
        // Create tag
        $create_tag_response = wp_remote_post($tag_endpoint, array(
            'headers' => array(
                'Api-Token' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'tag' => array(
                    'tag' => $tag_name,
                    'tagType' => 'contact',
                    'description' => 'Jeanius Assessment Completed'
                )
            ))
        ));
        
        if (is_wp_error($create_tag_response)) {
            error_log('Failed to create tag: ' . $create_tag_response->get_error_message());
            return false;
        }
        
        $created_tag = json_decode(wp_remote_retrieve_body($create_tag_response), true);
        $tag_id = $created_tag['tag']['id'];
    } else {
        $tag_id = $tag_data['tags'][0]['id'];
    }
    
    // Apply tag to contact
    $contact_tag_endpoint = $api_url . '/api/3/contactTags';
    $apply_tag_response = wp_remote_post($contact_tag_endpoint, array(
        'headers' => array(
            'Api-Token' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'contactTag' => array(
                'contact' => $contact_id,
                'tag' => $tag_id
            )
        ))
    ));
    
    if (is_wp_error($apply_tag_response)) {
        error_log('Failed to apply tag: ' . $apply_tag_response->get_error_message());
        return false;
    }
    
    return true;
}

// don'show admin bar
add_action('after_setup_theme', 'remove_admin_bar_for_customers');
function remove_admin_bar_for_customers() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if (in_array('customer', (array) $current_user->roles)) {
            show_admin_bar(false);
        }
    }
}

// Replace the existing hook with our extended version
remove_action('acf/save_post', 'jeanius_maybe_regenerate_assessment', 20);
add_action('acf/save_post', 'jeanius_extend_regenerate_assessment', 20);
