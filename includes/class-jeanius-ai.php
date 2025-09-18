<?php
namespace Jeanius;

/**
 * JeaniusAI - Enhanced staged implementation with improved rate limit handling
 * Integrates Python-based prompts with few-shot learning approach
 */
class JeaniusAI {
    /**
     * Stages of the report generation process
     */
    const STAGES = [
        'ownership_stakes',
        'life_messages',
        'transcendent_threads',
        'summary',
        'essays',
        'complete'
    ];

    /**
     * Initialize the class - register hooks
     */
    public static function init() {
        // Register cron hook for continuing report generation
        add_action('jeanius_continue_report', [__CLASS__, 'continue_report_generation'], 10, 1);
        
        // Register hook for processing ajax requests to check status
        add_action('wp_ajax_jeanius_check_status', [__CLASS__, 'ajax_check_status']);
        
        // Register hook for manually triggering report regeneration
        add_action('wp_ajax_jeanius_regenerate_report', [__CLASS__, 'ajax_regenerate_report']);
    }

    /**
     * Check the report generation status via AJAX
     */
    public static function ajax_check_status() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'jeanius_admin_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        if (!isset($_POST['post_id']) || !current_user_can('edit_post', intval($_POST['post_id']))) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $post_id = intval($_POST['post_id']);
        $status = self::get_generation_status($post_id);
        
        wp_send_json_success($status);
    }

    /**
     * Regenerate a report via AJAX
     */
    public static function ajax_regenerate_report() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'jeanius_admin_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        if (!isset($_POST['post_id']) || !current_user_can('edit_post', intval($_POST['post_id']))) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $post_id = intval($_POST['post_id']);
        
        // Reset progress and start fresh
        self::reset_generation_progress($post_id);
        
        // Start generation
        $result = self::generate_report($post_id);
        
        wp_send_json_success([
            'message' => 'Report generation started',
            'status' => self::get_generation_status($post_id),
            'result' => $result
        ]);
    }

    /**
     * Reset generation progress to start fresh
     */
    public static function reset_generation_progress($post_id) {
        delete_post_meta($post_id, '_jeanius_generation_stage');
        delete_post_meta($post_id, '_jeanius_generation_in_progress');
        delete_post_meta($post_id, '_jeanius_generation_errors');
        delete_post_meta($post_id, '_jeanius_generation_started');
        delete_post_meta($post_id, '_jeanius_generation_last_activity');
        delete_post_meta($post_id, '_jeanius_assessment_generated_pending');
        delete_post_meta($post_id, '_jeanius_assessment_generated_at');
        
        // Also delete temporary data from previous attempts
        delete_post_meta($post_id, '_jeanius_stakes');
        delete_post_meta($post_id, '_jeanius_life_messages');
        delete_post_meta($post_id, '_jeanius_threads');
        delete_post_meta($post_id, '_jeanius_summary');
        delete_post_meta($post_id, '_jeanius_summary_formatted');
    }

    /**
     * Get current generation status for a post
     */
    public static function get_generation_status($post_id) {
        $stage = get_post_meta($post_id, '_jeanius_generation_stage', true);
        $in_progress = get_post_meta($post_id, '_jeanius_generation_in_progress', true) === 'yes';
        $errors = get_post_meta($post_id, '_jeanius_generation_errors', true);
        $started = get_post_meta($post_id, '_jeanius_generation_started', true);
        $last_activity = get_post_meta($post_id, '_jeanius_generation_last_activity', true);
        
        // If no stage is set, check if we have a completed report
        if (empty($stage)) {
            if (get_field('jeanius_report_md', $post_id)) {
                return [
                    'status' => 'complete',
                    'progress' => 100,
                    'message' => 'Report generation complete',
                    'stage' => 'complete',
                    'stage_index' => count(self::STAGES) - 1,
                    'in_progress' => false,
                    'errors' => null,
                    'started' => null,
                    'last_activity' => null
                ];
            } else {
                return [
                    'status' => 'not_started',
                    'progress' => 0,
                    'message' => 'Report generation not started',
                    'stage' => null,
                    'stage_index' => -1,
                    'in_progress' => false,
                    'errors' => null,
                    'started' => null,
                    'last_activity' => null
                ];
            }
        }
        
        // Calculate progress percentage
        $stage_index = array_search($stage, self::STAGES);
        $progress = $stage_index !== false ? 
            round(($stage_index / (count(self::STAGES) - 1)) * 100) : 0;
        
        // If in complete stage, show 100%
        if ($stage === 'complete') {
            $progress = 100;
        }
        
        // Format stage name for display
        $stage_display = ucwords(str_replace('_', ' ', $stage));
        
        return [
            'status' => $in_progress ? 'in_progress' : ($errors ? 'error' : 'waiting'),
            'progress' => $progress,
            'message' => $in_progress ? 
                "Processing {$stage_display}..." : 
                ($errors ? "Error during {$stage_display}: {$errors}" : "Waiting to process {$stage_display}"),
            'stage' => $stage,
            'stage_index' => $stage_index,
            'in_progress' => $in_progress,
            'errors' => $errors,
            'started' => $started,
            'last_activity' => $last_activity
        ];
    }

    /**
     * Main entry point to generate or continue generating a report
     *
     * @param int $post_id The assessment post ID
     * @return array|WP_Error Result status or error
     */
    public static function generate_report($post_id) {
        // Get current stage and check if already in progress
        $current_stage = get_post_meta($post_id, '_jeanius_generation_stage', true);
        $in_progress = get_post_meta($post_id, '_jeanius_generation_in_progress', true) === 'yes';
        
        error_log("JeaniusAI: Starting/continuing report generation for post {$post_id}, stage: {$current_stage}, in_progress: {$in_progress}");
        
        // If no stage is set, we're starting fresh
        if (empty($current_stage)) {
            // Check if report is already generated
            if (get_field('jeanius_report_md', $post_id)) {
                error_log("JeaniusAI: Report already exists");
                return ['status' => 'ready'];
            }
            
            // Start with the first stage
            $current_stage = self::STAGES[0];
            update_post_meta($post_id, '_jeanius_generation_stage', $current_stage);
            update_post_meta($post_id, '_jeanius_generation_started', time());
        }
        
        // If currently processing, don't start another job
        if ($in_progress) {
            error_log("JeaniusAI: Report generation already in progress for post {$post_id}, stage: {$current_stage}");
            return ['status' => 'in_progress', 'stage' => $current_stage];
        }
        
        // Mark as in progress
        update_post_meta($post_id, '_jeanius_generation_in_progress', 'yes');
        update_post_meta($post_id, '_jeanius_generation_last_activity', time());
        
        // Process the current stage
        $result = self::process_current_stage($post_id, $current_stage);
        
        // If rate limited, schedule a cron job to continue later
        if (is_array($result) && isset($result['status']) && $result['status'] === 'rate_limited') {
            $wait_time = isset($result['wait_time']) ? $result['wait_time'] : 30;
            error_log("JeaniusAI: Rate limited, scheduling continuation in {$wait_time} seconds");
            
            // Schedule the continuation after the rate limit period
            wp_schedule_single_event(time() + $wait_time, 'jeanius_continue_report', [$post_id]);
            
            // Clear the in_progress flag to allow the cron job to run
            update_post_meta($post_id, '_jeanius_generation_in_progress', 'no');
            
            return ['status' => 'scheduled', 'stage' => $current_stage, 'resume_in' => $wait_time];
        }
        
        // If the stage completed successfully, move to the next stage
        if (is_array($result) && isset($result['status']) && $result['status'] === 'success') {
            $next_stage = self::get_next_stage($current_stage);
            
            if ($next_stage) {
                update_post_meta($post_id, '_jeanius_generation_stage', $next_stage);
                update_post_meta($post_id, '_jeanius_generation_in_progress', 'no');
                update_post_meta($post_id, '_jeanius_generation_last_activity', time());
                
                // If this is the last content stage, mark it as complete
                if ($next_stage === 'complete') {
                    error_log("JeaniusAI: All stages completed successfully");
                    
                    // Ensure the full report is created
                    self::finalize_report($post_id);
                    
                    return ['status' => 'ready'];
                }
                
                // Continue to next stage immediately
                return self::generate_report($post_id);
            } else {
                // All stages complete
                error_log("JeaniusAI: All stages completed successfully");
                
                // Mark as complete
                update_post_meta($post_id, '_jeanius_generation_stage', 'complete');
                update_post_meta($post_id, '_jeanius_generation_in_progress', 'no');
                update_post_meta($post_id, '_jeanius_generation_last_activity', time());
                
                // Ensure the full report is created
                self::finalize_report($post_id);
                
                return ['status' => 'ready'];
            }
        }
        
        // If we got here, there was an error
        update_post_meta($post_id, '_jeanius_generation_in_progress', 'no');
        update_post_meta($post_id, '_jeanius_generation_errors', is_wp_error($result) ? $result->get_error_message() : 'Unknown error');
        update_post_meta($post_id, '_jeanius_generation_last_activity', time());
        
        return $result;
    }
    
    /**
     * Continue report generation via cron
     */
    public static function continue_report_generation($post_id) {
        error_log("JeaniusAI: Continuing report generation via cron for post {$post_id}");
        
        // Check if already in progress (prevents duplicate processing)
        $in_progress = get_post_meta($post_id, '_jeanius_generation_in_progress', true) === 'yes';
        if ($in_progress) {
            error_log("JeaniusAI: Skipping cron job, already in progress");
            return;
        }
        
        // Continue from where we left off
        self::generate_report($post_id);
    }
    
    /**
     * Get the next stage in the sequence
     */
    private static function get_next_stage($current_stage) {
        $stage_index = array_search($current_stage, self::STAGES);
        
        if ($stage_index !== false && $stage_index < count(self::STAGES) - 1) {
            return self::STAGES[$stage_index + 1];
        }
        
        return null;
    }
    
    /**
     * Process the current stage
     */
    private static function process_current_stage($post_id, $stage) {
        $api_key = trim((string) get_field('openai_api_key', 'option'));
        if (empty($api_key)) {
            error_log("JeaniusAI: Missing OpenAI API key");
            return new \WP_Error('key', 'OpenAI key missing', ['status' => 500]);
        }
        
        // Get stage data from ACF
        $stage_data = json_decode(get_field('full_stage_data', $post_id) ?: '{}', true);
        if (empty($stage_data)) {
            error_log("JeaniusAI: No stage data available");
            return new \WP_Error('data', 'No stage data available', ['status' => 400]);
        }
        
        try {
            switch ($stage) {
                case 'ownership_stakes':
                    return self::process_ownership_stakes($post_id, $api_key, $stage_data);
                
                case 'life_messages':
                    return self::process_life_messages($post_id, $api_key, $stage_data);
                
                case 'transcendent_threads':
                    return self::process_transcendent_threads($post_id, $api_key, $stage_data);
                
                case 'summary':
                    return self::process_summary($post_id, $api_key, $stage_data);
                
                case 'essays':
                    return self::process_essays($post_id, $api_key, $stage_data);
                
                case 'complete':
                    return ['status' => 'success'];
                
                default:
                    return new \WP_Error('invalid_stage', 'Invalid stage: ' . $stage);
            }
        } catch (\Exception $e) {
            error_log("JeaniusAI: Exception during processing: " . $e->getMessage());
            return new \WP_Error('exception', 'Exception during processing: ' . $e->getMessage());
        }
    }
    
    /**
     * Finalize the full report by combining all parts
     */
    private static function finalize_report($post_id) {
        $stakes_md = get_post_meta($post_id, '_jeanius_stakes', true);
        $life_md = get_post_meta($post_id, '_jeanius_life_messages', true);
        $threads_md = get_post_meta($post_id, '_jeanius_threads', true);
        $sum_md = get_post_meta($post_id, '_jeanius_summary', true);
        $essay_md = get_field('essay_topics_md', $post_id);
        
        // Create full report
        $full = "## Ownership Stakes\n$stakes_md\n\n" .
                "## Life Messages\n$life_md\n\n" .
                "## Transcendent Threads\n$threads_md\n\n" .
                "## Sum of Your Jeanius\n$sum_md\n\n" .
                "## College Essay Topics\n$essay_md";
                
        update_field('jeanius_report_md', $full, $post_id);

        // Clean up temporary data
        delete_post_meta($post_id, '_jeanius_stakes');
        delete_post_meta($post_id, '_jeanius_life_messages');
        delete_post_meta($post_id, '_jeanius_threads');
        delete_post_meta($post_id, '_jeanius_summary');
        delete_post_meta($post_id, '_jeanius_summary_formatted');
        delete_post_meta($post_id, '_jeanius_generation_errors');


        // Flag that downstream automation should run when the results page is viewed
        update_post_meta($post_id, '_jeanius_assessment_generated_pending', '1');
        delete_post_meta($post_id, '_jeanius_assessment_generated_at');
    }
    
    /**
     * Process ownership stakes stage
     */
    private static function process_ownership_stakes($post_id, $api_key, $stage_data) {
    error_log("JeaniusAI: Processing ownership stakes");
    
    $stakes_md = self::define_ownership_stakes($api_key, $stage_data);
    if (is_wp_error($stakes_md)) {
        error_log("JeaniusAI ERROR: " . $stakes_md->get_error_message());
        
        // Check for rate limiting
        if (self::is_rate_limit_error($stakes_md)) {
            $wait_time = self::extract_wait_time($stakes_md->get_error_message());
            return ['status' => 'rate_limited', 'wait_time' => $wait_time];
        }
        
        return $stakes_md;
    }
    
    error_log("JeaniusAI: Successfully generated ownership stakes");
    
    // Strip any HTML tags from the response
    $stakes_md = strip_tags($stakes_md);
    
    // Update ACF fields
    update_field('ownership_stakes_md', $stakes_md, $post_id);
    
    // Convert to HTML - Check if it's already in bullet format
    $stakes_lines = explode("\n", trim($stakes_md));
    $stakes_html = '<ul>';
    foreach ($stakes_lines as $line) {
        // Remove any bullet markers or leading whitespace
        $clean = trim(ltrim($line, "-•*\t "));
        if (!empty($clean)) {
            $stakes_html .= '<li>' . esc_html($clean) . '</li>';
        }
    }
    $stakes_html .= '</ul>';
    update_field('ownership_stakes_md_copy', $stakes_html, $post_id);
    
    // Save for next stage
    update_post_meta($post_id, '_jeanius_stakes', $stakes_md);
    
    return ['status' => 'success'];
}
    
    /**
     * Process life messages stage
     */
    private static function process_life_messages($post_id, $api_key, $stage_data) {
        error_log("JeaniusAI: Processing life messages");
        
        $stakes_md = get_post_meta($post_id, '_jeanius_stakes', true);
        if (empty($stakes_md)) {
            return new \WP_Error('missing_data', 'Missing ownership stakes data');
        }
        
        $life_md = self::define_life_messages($api_key, $stage_data, $stakes_md);
        if (is_wp_error($life_md)) {
            error_log("JeaniusAI ERROR: " . $life_md->get_error_message());
            
            // Check for rate limiting
            if (self::is_rate_limit_error($life_md)) {
                $wait_time = self::extract_wait_time($life_md->get_error_message());
                return ['status' => 'rate_limited', 'wait_time' => $wait_time];
            }
            
            return $life_md;
        }
        
        error_log("JeaniusAI: Successfully generated life messages");
        
        // Format life messages as HTML table
        $formatted_life_md = self::format_life_messages_as_table($life_md);
        
        // Update ACF fields
        update_field('life_messages_md', $life_md, $post_id);
        update_field('life_messages_md_copy', $formatted_life_md, $post_id);
        
        // Save for next stage
        update_post_meta($post_id, '_jeanius_life_messages', $life_md);
        
        return ['status' => 'success'];
    }

    /**
     * Format life messages as HTML table
     */
    private static function format_life_messages_as_table($life_md) {
        // Parse the markdown content
        $lines = explode("\n", trim($life_md));
        $table = "<table>\n";
        $count = 1;
        
        foreach ($lines as $line) {
            // Extract the ownership stake and message
            if (preg_match('/^-\s*On\s+([^:]+):\s+(.+)$/', trim($line), $matches)) {
                $title = trim($matches[1]);
                $message = trim($matches[2]);
                
                $table .= "<tr>\n";
                $table .= "<td>{$count}.</td>\n";
                $table .= "<td class=\"title\">{$title}</td>\n";
                $table .= "<td class=\"information\">\"{$message}\"</td>\n";
                $table .= "</tr>\n";
                
                $count++;
            }
        }
        
        $table .= "</table>";
        return $table;
    }
    
    /**
     * Process transcendent threads stage
     */
    private static function process_transcendent_threads($post_id, $api_key, $stage_data) {
        error_log("JeaniusAI: Processing transcendent threads");
        
        $stakes_md = get_post_meta($post_id, '_jeanius_stakes', true);
        $life_md = get_post_meta($post_id, '_jeanius_life_messages', true);
        
        if (empty($stakes_md) || empty($life_md)) {
            return new \WP_Error('missing_data', 'Missing previous stage data');
        }
        
        $threads_md = self::define_transcendent_threads($api_key, $stage_data, $stakes_md, $life_md);
        if (is_wp_error($threads_md)) {
            error_log("JeaniusAI ERROR: " . $threads_md->get_error_message());
            
            // Check for rate limiting
            if (self::is_rate_limit_error($threads_md)) {
                $wait_time = self::extract_wait_time($threads_md->get_error_message());
                return ['status' => 'rate_limited', 'wait_time' => $wait_time];
            }
            
            return $threads_md;
        }
        
        error_log("JeaniusAI: Successfully generated transcendent threads");
        
        // Format transcendent threads
        $formatted_threads_md = $threads_md; // Keep original as backup
        
        // Check if the response already includes the HTML formatting we want
        if (strpos($threads_md, '<ul class="labels">') === false) {
            $formatted_threads_md = self::format_transcendent_threads($threads_md);
        }
        
        // Update ACF fields
        update_field('transcendent_threads_md', $threads_md, $post_id);
        update_field('transcendent_threads_md_copy', $formatted_threads_md, $post_id);
        
        // Save for next stage
        update_post_meta($post_id, '_jeanius_threads', $threads_md);
        
        return ['status' => 'success'];
    }

    /**
     * Format transcendent threads as HTML lists
     */
    private static function format_transcendent_threads($threads_md) {
        // Extract thread names and descriptions
        $pattern = '/Thread #(\d+):\s+([A-Z]+)\s+—\s+(.+?)(?=Thread #\d+:|$)/s';
        preg_match_all($pattern, $threads_md, $matches, PREG_SET_ORDER);
        
        if (empty($matches)) {
            // Try alternate pattern if the first one doesn't match
            $pattern = '/Thread #(\d+):\s+([A-Z]+)\s+(.+?)(?=Thread #\d+:|$)/s';
            preg_match_all($pattern, $threads_md, $matches, PREG_SET_ORDER);
        }
        
        if (empty($matches)) {
            return $threads_md; // Return original if no matches
        }
        
        // Build the labels list
        $output = "<ul class=\"labels\">\n";
        foreach ($matches as $match) {
            $thread_name = ucfirst(strtolower($match[2]));
            $output .= "\t<li>{$thread_name}</li>\n";
        }
        $output .= "</ul>\n";
        
        // Build the detailed list
        $output .= "<div class=\"labels-data\">\n<ul>\n";
        foreach ($matches as $match) {
            $num = $match[1];
            $thread_name = $match[2];
            $description = trim($match[3]);
            
            $output .= "\t<li>{$num}. <span class=\"color-blue\">{$thread_name}</span> {$description}</li>\n";
        }
        $output .= "</ul>\n</div>";
        
        return $output;
    }
    
    /**
     * Process summary stage
     */
    private static function process_summary($post_id, $api_key, $stage_data) {
        error_log("JeaniusAI: Processing summary");
        
        $stakes_md = get_post_meta($post_id, '_jeanius_stakes', true);
        $life_md = get_post_meta($post_id, '_jeanius_life_messages', true);
        $threads_md = get_post_meta($post_id, '_jeanius_threads', true);
        
        if (empty($stakes_md) || empty($life_md) || empty($threads_md)) {
            return new \WP_Error('missing_data', 'Missing previous stage data');
        }
        
        $sum_md = self::summarize_jeanius($api_key, $stage_data, $stakes_md, $life_md, $threads_md);
        if (is_wp_error($sum_md)) {
            error_log("JeaniusAI ERROR: " . $sum_md->get_error_message());
            
            // Check for rate limiting
            if (self::is_rate_limit_error($sum_md)) {
                $wait_time = self::extract_wait_time($sum_md->get_error_message());
                return ['status' => 'rate_limited', 'wait_time' => $wait_time];
            }
            
            return $sum_md;
        }
        
        error_log("JeaniusAI: Successfully generated summary");
        
        // Add heading if not present
        if (strpos(strtolower($sum_md), 'sum of your jeanius') === false) {
            $sum_md = "### Sum of Your Jeanius\n" . $sum_md;
        }
        
        // Update ACF fields
        update_field('sum_jeanius_md', $sum_md, $post_id);
        
        // Remove heading for display copy
        $sum_md_formatted = preg_replace('/^###\s*Sum of Your Jeanius\s*/i', '', trim($sum_md));
        update_field('sum_jeanius_md_copy', $sum_md_formatted, $post_id);
        
        // Save for next stage
        update_post_meta($post_id, '_jeanius_summary', $sum_md);
        update_post_meta($post_id, '_jeanius_summary_formatted', $sum_md_formatted);
        
        return ['status' => 'success'];
    }
    
    /**
     * Process essays stage
     */
    private static function process_essays($post_id, $api_key, $stage_data) {
        error_log("JeaniusAI: Processing essays");
        
        $stakes_md = get_post_meta($post_id, '_jeanius_stakes', true);
        $life_md = get_post_meta($post_id, '_jeanius_life_messages', true);
        $threads_md = get_post_meta($post_id, '_jeanius_threads', true);
        $sum_md_formatted = get_post_meta($post_id, '_jeanius_summary_formatted', true);
        
        if (empty($stakes_md) || empty($life_md) || empty($threads_md) || empty($sum_md_formatted)) {
            return new \WP_Error('missing_data', 'Missing previous stage data');
        }
        
        // Get colleges list
        $raw_colleges = get_field('target_colleges', $post_id);
        $colleges = [];
        if (is_string($raw_colleges)) {
            $parts = preg_split('/[\r\n,]+/', $raw_colleges);
            $colleges = array_unique(array_filter(array_map('trim', $parts)));
        }
        
        $essay_md = self::create_essays($api_key, $stage_data, $stakes_md, $life_md, $threads_md, $sum_md_formatted, $colleges);
        if (is_wp_error($essay_md)) {
            error_log("JeaniusAI ERROR: " . $essay_md->get_error_message());
            
            // Check for rate limiting
            if (self::is_rate_limit_error($essay_md)) {
                $wait_time = self::extract_wait_time($essay_md->get_error_message());
                return ['status' => 'rate_limited', 'wait_time' => $wait_time];
            }
            
            return $essay_md;
        }
        
        error_log("JeaniusAI: Successfully generated essays");
        
        // Update ACF fields
        update_field('essay_topics_md', $essay_md, $post_id);
        update_field('essay_topics_md_copy', $essay_md, $post_id);
        
        return ['status' => 'success'];
    }
    
    /**
     * Check if an error is a rate limit error
     */
    private static function is_rate_limit_error($error) {
        if (!is_wp_error($error)) {
            return false;
        }
        
        $message = $error->get_error_message();
        return (
            strpos($message, 'Rate limit') !== false || 
            strpos($message, '429') !== false ||
            strpos($message, 'exceeded your current quota') !== false ||
            strpos($message, 'Too many requests') !== false
        );
    }
    
    /**
     * Extract wait time from rate limit error message
     */
    private static function extract_wait_time($error_message) {
        $wait_time = 30; // Default wait time
        
        if (preg_match('/Please try again in (\d+\.?\d*)s/', $error_message, $matches)) {
            $wait_time = ceil(floatval($matches[1])) + 5; // Add a small buffer
        } elseif (preg_match('/retry after (\d+\.?\d*)s/', $error_message, $matches)) {
            $wait_time = ceil(floatval($matches[1])) + 5;
        } elseif (preg_match('/(\d+\.?\d*) seconds?/', $error_message, $matches)) {
            $wait_time = ceil(floatval($matches[1])) + 5;
        }
        
        // Cap at 5 minutes
        return min($wait_time, 300);
    }

    /**
     * Define ownership stakes using few-shot prompting (Python approach)
     */
    public static function define_ownership_stakes($api_key, $stage_data) {
        // Example data for few-shot prompting
        $example_data = self::get_example_ownership_stake_data();
        $extended_examples = self::get_extended_ownership_stakes_examples();
        
        // Add extended examples to the system prompt
        $system_prompt = "You are a storytelling and identity analysis expert. The user will input structured data about key life events by life stage.

            IMPORTANT: I will first show you TWO EXAMPLE ANALYSES that are NOT related to the current user. These examples are ONLY for understanding the format and should NOT influence your analysis of the actual user data, which will come after the examples.

            Analyze all events, weighing emotional intensity (rating), emotional direction (polarity), and themes in the description. Extract the 5 most dominant life experience categories and return them as \"Ownership Stakes\"—areas where the user holds deep lived experience and credibility.
            
            Use **general categories only** (e.g., \"Friendship,\" \"Resilience,\" \"Independence,\" \"Grief\"). See examples below for context. Avoid redundancy and do not combine multiple concepts in a single stake. Do not use narrative or story-like phrasing.

            Speak directly to the user using \"you\" or \"your\" if needed. Do not include any fictional names or characters from previous assessments in the output.
            
            The examples below are provided strictly for format and tone reference only—they must not appear in your answer unless it directly applies to the users story.

            *Examples of ownership stakes from other well-known narratives:
              Example 1: Bruce Springsteen owns…
                - blue-collar ethos
                - small-town sensibility
              Example 2: Mother Teresa owns…
                - extreme compassion
                - dignity in death
              Example 3: Abe Lincoln owns…
                - human equality
                - personal character
              Example 4: Rosa Parks owns…
                - personal conviction
                - social progress*
              
            VERY IMPORTANT: Your final output must ONLY analyze the actual user data provided in the final message. DO NOT include ANY information from the example data in your analysis.
            
            Additional powerful examples of ownership stakes:"
            . implode("\n", array_map(function($example) { return "• {$example}"; }, $extended_examples));

        // Format stage data as JSON
        $stage_data_flat = json_encode($stage_data);

        // Create messages array with few-shot examples
        $messages = [
                ["role" => "system", "content" => $system_prompt], // Add the system prompt here
                ["role" => "user", "content" => "EXAMPLE 1 (not the current user): " . json_encode($example_data[0])],
                ["role" => "assistant", "content" => "EXAMPLE OUTPUT 1: 
                    <ul>
                    <li>childhood conflict</li>
                    <li>family traditions</li>
                    <li>trauma recovery</li>
                    <li>team belonging</li>
                    <li>hard lessons</li>
                    <li>faith community</li>
                    </ul>"],
                ["role" => "user", "content" => "EXAMPLE 2 (not the current user): " . json_encode($example_data[1])],
                ["role" => "assistant", "content" => "EXAMPLE OUTPUT 2: 
                    <ul>
                    <li>Family dynamics</li>
                    <li>Cultural identity</li>
                    <li>Resilience</li>
                    <li>Music as expression</li>
                    <li>Friendship</li>
                    <li>Independence</li>
                    <li>Emotional struggles</li>
                    </ul>"],
                ["role" => "user", "content" => "ACTUAL USER DATA (analyze only this): {$stage_data_flat}. Return only 5 key categories in an HTML unordered list with each category as its own list item. Only return the list, not lable, not title, just the list."]
            ];

        // Call OpenAI API
        return self::call_openai(
            $api_key, 
            $messages, 
            ["model" => "gpt-4o-mini", "temperature" => 0.3, "max_tokens" => 900]
        );
    }

    /**
     * Define life messages using few-shot prompting (Python approach)
     */
    public static function define_life_messages($api_key, $stage_data, $ownership_stakes) {
        // Example data for few-shot prompting
        $example_data = self::get_example_ownership_stake_data();
        $extended_examples = self::get_extended_life_message_examples();
        
        $system_prompt = "You are helping someone articulate Life Messages - the truths they've earned the right to share because they've LIVED them.
        
        These are not advice or motivational quotes, but authentic insights from their actual experience.

        Think of it this way: If they were on a panel about one of their ownership stakes, what could they say with genuine credibility?
        
        Not what they WANT to say, but what their life gives them authority to say.

                Life Messages should:
                - Be grounded in lived experience, not theory or wishful thinking
                - Feel fresh and specific, avoiding clichés like \"Every setback is a setup for a comeback\"
                - Range from practical wisdom to deeper philosophical insights
                - Be memorable enough that someone might remember it 10 years later
                - Sound like something a real person would actually say after living through something

                Good Life Messages often:
                - State hard truths simply
                - Invert common wisdom
                - Ask provocative questions
                - Reveal paradoxes
                - Use unexpected metaphors

                Avoid:
                - Generic self-help language
                - TJ Maxx wall art phrases
                - Overly poetic or pretentious language
                - Clichés and platitudes

                Examples of authentic tone (create entirely original messages):
                - \"Adversity is just an ingredient\"
                - \"Money doesn't need a middleman\"
                - \"If you're gonna eat shit, don't nibble\"
                - \"Isolation is an underutilized practice\"
                - \"Identity is an ongoing choice\"
                - \"The only tired I was, was tired of giving in\"
                
                Additional powerful life message examples:
                " . implode("\n", array_map(function($example) { return "- {$example}"; }, $extended_examples));

        // Format stage data as JSON
        $stage_data_flat = json_encode($stage_data);

        // Example life messages
        $example1_stakes = "childhood conflict, family traditions, trauma recovery, team belonging, hard lessons, faith community";
        $example1_messages = "
                                - On childhood conflict: You can lose a fight and still win respect.
                                - On family traditions: Ordinary days can become sacred through tradition.
                                - On trauma recovery: The body heals faster than the memory.
                                - On team belonging: Belonging comes from trust, not from a jersey.
                                - On hard lessons: The hardest lessons don’t come with a diploma.
                                - On faith community: A small church can raise a big soul.             
                            ";

        $example2_stakes = "cultural tension, demanding father, relationships as lifelines, work ethic through hardship, music as escape, independence";
        $example2_messages = "- On cultural tension: Never fully fitting in shapes its own kind of strength.
                                - On demanding father: Living under constant pressure teaches you to push back.
                                - On relationships as lifelines: You figure out who you are through the people around you.
                                - On work ethic through hardship: Long days teach you to stick with what matters.
                                - On music as escape: Music holds what you can’t say.
                                - On independence: Leaving your place led you to find your peace.
                            ";

        // Create messages array with few-shot examples
        $messages = [
            ["role" => "system", "content" => $system_prompt],
            ["role" => "user", "content" => "Here is the original information used to develop this user's ownership stake list: " . json_encode($example_data[0]) . 
                                         "Here are the ownership stakes: {$example1_stakes}
                                          Develop a life message for each ownership stake based on the user's experiences. Make them fresh, unique, non-cliche, defendable, not something
                                          that sounds like an inspirational or motivational quote."],
            ["role" => "assistant", "content" => $example1_messages],
            ["role" => "user", "content" => "Here is the original information used to develop this user's ownership stake list: " . json_encode($example_data[1]) . 
                                         " Here are the ownership stakes: {$example2_stakes}
                                          Develop a life message for each ownership stake based on the user's experiences. Make them fresh, unique, non-cliche, defendable, not something
                                          that sounds like an inspirational or motivational quote."],
            ["role" => "assistant", "content" => $example2_messages],
            ["role" => "user", "content" => "Here is the original information used to develop this user's ownership stake list: {$stage_data_flat}
                                          Here are the ownership stakes: {$ownership_stakes}.
                                          Develop a life message for each ownership stake based on the user's experiences. Make them fresh, unique, non-cliche, defendable, not something
                                          that sounds like an inspirational or motivational quote."]
        ];

        // Call OpenAI API
        return self::call_openai(
            $api_key, 
            $messages, 
            ["model" => "gpt-4o-mini", "temperature" => 0.5, "max_tokens" => 1200]
        );
    }

    /**
     * Define transcendent threads using few-shot prompting (Python approach)
     */
    public static function define_transcendent_threads($api_key, $stage_data, $ownership_stakes, $life_messages) {
        // Example data for few-shot prompting
        $example_data = self::get_example_ownership_stake_data();
        
        $system_prompt = "You are identifying Transcendent Threads - universal themes threaded through this person's life that connect them to everyone else. These are the connective tissue between this person and their audience. Don't directly mention the events from their life, just talk about the thread and how they relate to and through them. 
              The 22 Universal Threads and their deeper meanings:
              1. **LOVE** - Profound presence OR absence of love. Often the absence (abuse, divorce, instability). Not common unless there's significant dysfunction.
              2. **LOSS** - Presence of adversity across the spectrum (death, breakups, job loss, injuries, dreams lost). 
              3. **FAMILY** - Family as source of decisions/encouragement. Tight-knit core or extended family. \"My grandparents mentored me every summer.\"
              4. **HOPE** - Constantly getting back up from adversity quickly. Optimistic mentality despite setbacks. \"I can do this.\" Often transferred from others.
              5. **TRUTH** - Where truth OR its absence was a prominent teacher. \"I wanted to know what was real.\" \"Control what you can control.\" Often follows loss.
              6. **MYSTERY** - Presence of unknown. Multiple moves, immigration, constant change. Lack of predictability. \"We moved 5 times before high school.\"
              7. **LOYALTY** - Sticking with something/someone OR someone sticking with them. Principled, stubborn. \"I'm not a quitter because I believe in this.\"
              8. **SIMPLICITY** - Engineering minds wanting to know WHY/HOW things work. Also those fleeing chaos. \"I wanted to distill it down to the essence.\"
              9. **REDEMPTION** - Wrong done BY them or TO them that gets righted. Carrying burdens that transform. \"Good came from the bad.\"
              10. **SECURITY** - \"I was really insecure.\" \"Didn't know who I was.\" Common with young people about relational safety and belonging.
              11. **TRIUMPH** - Type A personalities. \"I love winning, hate losing.\" Achievement after achievement. The hard chargers.
              12. **PROGRESS** - Resilient perseverers who keep moving forward regardless. Learners. \"I learned this, then traveled there, then served here.\"
              13. **FAITH** - Spiritual dimension as organizing principle. Trust in something greater. Can be religious or secular faith in process/people.
              14. **SACRIFICE** - Others-first posture. \"What we give up to gain.\" Often shows how they navigate challenges.
              15. **GRACE** - Unearned favor received or given. Space between justice and mercy. Often follows identity work.
              16. **BEAUTY** - Those who find extraordinary in ordinary. Artists, creatives, those who see differently.
              17. **JOY** - Deeper than happiness. Often emerges as result of journey through other threads.
              18. **IDENTITY** - \"Who am I?\" work. Very common in adolescents. Wrestling with self-definition.
              19. **FREEDOM** - Breaking chains internal/external. Response to feeling trapped. Quest for autonomy.
              20. **RESILIENCE** - Different from loyalty - no specific purpose, just \"I'm not a quitter.\" Getting back up because that's who they are.
              21. **INNOVATION** - Creating new from existing. Disruptors. \"I always wanted to build something different.\"
              22. **CONTRIBUTION** - Service orientation. Legacy mindset. \"To fight for others is transcendent.\"

              Select THREE threads forming this person's pattern. Look for threads appearing multiple times across their timeline.

              Common patterns (but find what's authentic to this person):
              - Loss → Truth → Progress (adversity leads to wisdom leads to forward movement)
              - Mystery → Progress → Joy (unknown becomes adventure becomes fulfillment)
              - Identity → Grace → Simplicity (self-discovery through acceptance leads to clarity)

              1. First output just the thread names inside:
              <ul class=\"labels\">
              <li>[Thread Name]</li>
              <li>[Thread Name]</li>
              <li>[Thread Name]</li>
              </ul>

              2. Then output the detailed numbered explanations inside:
              <div class=\"labels-data\">
              <ul>
              <li>1. <span class=\"color-blue\">[THREAD NAME]</span> [1–2 sentence explanation in 2nd person voice]</li>
              <li>2. <span class=\"color-blue\">[THREAD NAME]</span> [1–2 sentence explanation in 2nd person voice]</li>
              <li>3. <span class=\"color-blue\">[THREAD NAME]</span> [1–2 sentence explanation in 2nd person voice]</li>
              </ul>
              </div>

              Speak directly to the user using \"you\" and \"your.\" Do not use third-person language. Choose only from the 22 threads listed. Look for threads that appear multiple times across their story.";

        // Format stage data as JSON
        $stage_data_flat = json_encode($stage_data);

        // Example data for few-shot prompting
        $example1_stakes = "childhood conflict, family traditions, trauma recovery, team belonging, hard lessons, faith community";
        $example1_messages = "
                                - On childhood conflict: You can lose a fight and still win respect.
                                - On family traditions: Ordinary days can become sacred through tradition.
                                - On trauma recovery: The body heals faster than the memory.
                                - On team belonging: Belonging comes from trust, not from a jersey.
                                - On hard lessons: The hardest lessons don’t come with a diploma.
                                - On faith community: A small church can raise a big soul.             
                            ";
        $example1_threads = "Your life follows the Transcendent Pattern:
                                Family >>> Resilience >>> Contribution

                                Thread #1: FAMILY
                                • You have been shaped by strong familial bonds and cherished relationships, which have provided a foundation of love and support throughout your life. This deep connection fosters a sense of belonging and influences your interactions with others.

                                Thread #2: RESILIENCE
                                • You have faced challenges and setbacks, such as difficult transitions and personal mistakes, and have emerged stronger each time. This resilience not only helps you navigate your own struggles but also empowers you to uplift those around you.

                                Thread #3: CONTRIBUTION
                                • Your journey of growth and leadership within your community and family leads you to give back meaningfully. You find fulfillment in sharing your experiences and insights, inspiring others to embrace their own paths and foster strong connections.";

        $example2_stakes = "cultural tension, demanding father, relationships as lifelines, work ethic through hardship, music as escape, independence";
        $example2_messages = "
                                - On cultural tension: Never fully fitting in shapes its own kind of strength.
                                - On demanding father: Living under constant pressure teaches you to push back.
                                - On relationships as lifelines: You figure out who you are through the people around you.
                                - On work ethic through hardship: Long days teach you to stick with what matters.
                                - On music as escape: Music holds what you can’t say.
                                - On independence: Leaving your place led you to find your peace.
                            ";
        $example2_threads = "Your life follows the Transcendent Pattern:
                                Identity >>> Resilience >>> Creativity

                                Thread #1: IDENTITY – Growing up between cultures, in a fractured home, and never fully fitting in, you’ve had to wrestle with who you are and where you belong. That search has shaped everything.

                                Thread #2: RESILIENCE – From long days of labor to family pressure and leaving home at 16, you’ve faced weight that could have crushed you, but each time you found a way to keep going.

                                Thread #3: CREATIVITY – Music wasn’t a pastime—it was your anchor, your language, and your escape. It became how you turned struggle into expression and chaos into something you could share.";

        // Create messages array with few-shot examples
        $messages = [
            ["role" => "system", "content" => $system_prompt],
            ["role" => "user", "content" => "Here is the original information used to develop this user's ownership stake list: " . json_encode($example_data[0]) . 
                                         " Here are the ownership stakes: {$example1_stakes}
                                          Here are the life messages corresponding to each ownership stake: {$example1_messages}
                                          Create the transcendent threads from the list of 22 Universal Threads only."],
            ["role" => "assistant", "content" => $example1_threads],
            ["role" => "user", "content" => "Here is the original information used to develop this user's ownership stake list: " . json_encode($example_data[1]) . 
                                         " Here are the ownership stakes: {$example2_stakes}
                                          Here are the life messages corresponding to each ownership stake: {$example2_messages}
                                          Create the transcendent threads from the list of 22 Universal Threads only."],
            ["role" => "assistant", "content" => $example2_threads],
            ["role" => "user", "content" => "Here is the original information used to develop this user's ownership stake list: {$stage_data_flat}
                                          Here are the ownership stakes: {$ownership_stakes}.
                                          Here are the life messages corresponding to each ownership stake: {$life_messages}.
                                          Create the transcendent threads from the list of 22 Universal Threads only."]
        ];

        // Call OpenAI API
        return self::call_openai(
            $api_key, 
            $messages, 
            ["model" => "gpt-4o-mini", "temperature" => 0.5, "max_tokens" => 900]
        );
    }

    /**
     * Define sum of jeanius using few-shot prompting (Python approach)
     */
    public static function summarize_jeanius($api_key, $stage_data, $ownership_stakes, $life_messages, $transcendent_threads) {
        // Example data for few-shot prompting
        $example_data = self::get_example_ownership_stake_data();
        
        $system_prompt = "Write the \"Sum of Your Jeanius\" - the moment when everything clicks, when scattered experiences suddenly form a constellation that makes sense.
        
        This is recognition, not analysis or advice.

          Like the best moment in therapy when someone finally sees themselves clearly, help them understand:
          - Why their particular combination of experiences matters
          - What their life has been preparing them for (without being prescriptive)
          - The unique value only they can bring
          - How their threads weave into something larger

          This should feel like:
          - Coming home to themselves
          - Finally naming what they've always sensed but couldn't articulate
          - Understanding why the hard things happened
          - Seeing their life as preparation, not random events
          - The affirmation their life has mattered, even the bad stuff

          In 5-6 powerful sentences, weave together their Ownership Stakes, Life Messages, and Transcendent Threads into a coherent understanding of who they are and why that matters.
          
          Write with warmth, precision, and emotional intelligence. Make them feel deeply seen and understood. Use \"you/your\" throughout.
          
          This is not a summary of data points or a summary of their Ownership Stakes, Life Messages, and Transcendent Threads. It's a mirror showing them the core of their identity with clarity and specificity. Like a wise mentor who has studied their life and can finally articulate the most important truths they've felt but never fully named.
          
          Don't mention assessments, results, or this process. Just hold up the mirror and show them who they are.";

        // Format stage data as JSON
        $stage_data_flat = json_encode($stage_data);

        // Example data for few-shot prompting
        $example1_stakes = "childhood conflict, family traditions, trauma recovery, team belonging, hard lessons, faith community";
        $example1_messages = "
            - On childhood conflict: You can lose a fight and still win respect.
            - On family traditions: Ordinary days can become sacred through tradition.
            - On trauma recovery: The body heals faster than the memory.
            - On team belonging: Belonging comes from trust, not from a jersey.
            - On hard lessons: The hardest lessons don’t come with a diploma.
            - On faith community: A small church can raise a big soul.             
            ";
        $example1_threads = "Your life follows the Transcendent Pattern:
                Family >>> Resilience >>> Contribution

                Thread #1: FAMILY
                • You have been shaped by strong familial bonds and cherished relationships, which have provided a foundation of love and support throughout your life. This deep connection fosters a sense of belonging and influences your interactions with others.

                Thread #2: RESILIENCE
                • You have faced challenges and setbacks, such as difficult transitions and personal mistakes, and have emerged stronger each time. This resilience not only helps you navigate your own struggles but also empowers you to uplift those around you.

                Thread #3: CONTRIBUTION
                • Your journey of growth and leadership within your community and family leads you to give back meaningfully. You find fulfillment in sharing your experiences and insights, inspiring others to embrace their own paths and foster strong connections.";
        $example1_sum = "Sum of your Jeanius:
                        You come alive where trust, belonging, and shared purpose matter more than titles or appearances. Your story shows a steady pattern: learning that respect can outlast conflict, that ordinary traditions can become sacred anchors, and that even painful lessons can shape strength you didn’t know you had. You bring to every space the ability to build connection—whether through family, faith, or friendship—rooted in an instinct to make people feel they matter. At your core, your threads are family, resilience, and contribution, and they mark you as someone who helps create environments where others feel safe, seen, and part of something larger.";

        $example2_stakes = "cultural tension, demanding father, relationships as lifelines, work ethic through hardship, music as escape, independence";
        $example2_messages = "- On cultural tension: Never fully fitting in shapes its own kind of strength.
                        - On demanding father: Living under constant pressure teaches you to push back.
                        - On relationships as lifelines: You figure out who you are through the people around you.
                        - On work ethic through hardship: Long days teach you to stick with what matters.
                        - On music as escape: Music holds what you can’t say.
                        - On independence: Leaving your place led you to find your peace.";
                $example2_threads = "Your life follows the Transcendent Pattern:
                            Identity >>> Resilience >>> Creativity

                            Thread #1: IDENTITY – Growing up between cultures, in a fractured home, and never fully fitting in, you’ve had to wrestle with who you are and where you belong. That search has shaped everything.

                            Thread #2: RESILIENCE – From long days of labor to family pressure and leaving home at 16, you’ve faced weight that could have crushed you, but each time you found a way to keep going.

                            Thread #3: CREATIVITY – Music wasn’t a pastime—it was your anchor, your language, and your escape. It became how you turned struggle into expression and chaos into something you could share.";
        $example2_sum = "Sum of Your Jeanius: You’ve grown up learning what it means to live in the in-between—between cultures, between parents, between who people expected you to be and who you actually are. That tension taught you to search for identity, to test yourself against the weight of long days, and to trust the people who showed you who you were becoming. Resilience runs through your story—not as some polished trait, but as the muscle you built moving through conflict, pressure, and independence at an early age. And creativity gave you a voice when nothing else fit, a way to carry what couldn’t be said and turn it into something that mattered. Your life shows that identity is forged in struggle, resilience is born in repetition, and creativity is what turns survival into meaning.";

        // Create messages array with few-shot examples
        $messages = [
            ["role" => "system", "content" => $system_prompt],
            ["role" => "user", "content" => "Here is the original information used to develop this user's ownership stake list: " . json_encode($example_data[0]) . 
                                         " Here are the ownership stakes: {$example1_stakes}
                                          Here are the life messages corresponding to each ownership stake: {$example1_messages}
                                          Here are the transcendent threads derived from the information, ownership stakes, and life messages, based on the defined
                                          list of 22 universal threads: {$example1_threads}
                                          Create the summary of my Jeanius from all of this data."],
            ["role" => "assistant", "content" => $example1_sum],
            ["role" => "user", "content" => "Here is the original information used to develop this user's ownership stake list: " . json_encode($example_data[1]) . 
                                         " Here are the ownership stakes: {$example2_stakes}
                                          Here are the life messages corresponding to each ownership stake: {$example2_messages}
                                          Here are the transcendent threads derived from the information, ownership stakes, and life messages, based on the defined
                                          list of 22 universal threads: {$example2_threads}
                                          Create the summary of my Jeanius from all of this data."],
            ["role" => "assistant", "content" => $example2_sum],
            ["role" => "user", "content" => "Here is the original information used to develop this user's ownership stake list: {$stage_data_flat}
                                          Here are the ownership stakes: {$ownership_stakes}.
                                          Here are the life messages corresponding to each ownership stake: {$life_messages}.
                                          Here are the transcendent threads derived from the information, ownership stakes, and life messages, based on the defined
                                          list of 22 universal threads: {$transcendent_threads}.
                                          Create the summary of my Jeanius from all of this data."]
        ];

        // Call OpenAI API
        return self::call_openai(
            $api_key, 
            $messages, 
            ["model" => "gpt-4o-mini", "temperature" => 0.5, "max_tokens" => 900]
        );
    }

    /**
     * Create essays using few-shot prompting (Python approach)
     */
    public static function create_essays($api_key, $stage_data, $ownership_stakes, $life_messages, $transcendent_threads, $sum_of_jeanius, $colleges = []) {
    // Format stage data as JSON
    $stage_data_flat = json_encode($stage_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $college_line = empty($colleges)
        ? 'There are no target colleges.'
        : 'Target colleges: ' . implode(', ', $colleges) . '.';

    $system_prompt = "You are helping a student discover essay topics that emerge naturally from their Jeanius insights.

    These essays should reveal character through small, concrete moments that feel lived-in and specific—never a résumé recap.

    VOICE & FEEL (apply consistently):
    - Intimate second person: speak to \"you\" in rationale sections to keep the reader close.
    - Humble, reflective confidence: show growth without grandstanding.
    - Service-forward lens: growth often shows up in how you show up for others.
    - Independence through relationships: you find who you are in the push/pull of community.
    - Clear stakes, quiet insight: what shifted in how you think, decide, or care.

    Thread-to-tone guidance (blend as appropriate):
    - Loss/Mystery → reflective, searching voice (silences, unanswered questions, meaning-making)
    - Progress/Triumph → forward-moving, disciplined, quietly aspirational
    - Family/Loyalty → relationship-centered, interdependent growth
    - Identity/Truth → self-discovery arc, alignment between values and action
    - Resilience/Sacrifice → letting go, choosing long-term good over short-term comfort

    {$college_line}

    Create 5 DISTINCT essay topics that:
    - Follow a StoryBrand-like arc (Problem → Internal Tension → Guidance/Practice → Choice → Outcome).
    - Each draws from different Ownership Stakes or combinations (avoid repeating the same stake/scene).
    - Show growth through specific, situated moments; no abstractions or moral-of-the-story clichés.
    - Reveal character qualities colleges value (initiative, teachability, grit, empathy, intellectual curiosity).
    - Demonstrate how their threads prepare them to contribute on campus.
    - Point to who you're becoming, not just who you've been.

    For each of five essay topics include:
    ##Title

    ##Rationale (2–3 sentences, second-person voice, future-oriented)

    ##Writing outline (5 bullets, each step should advance the narrative arc and lead to what kind of student/person you'll be in college and beyond)

    ##Tailoring Tips – one sub-bullet per target college above

    OUTPUT ONLY VALID HTML (no Markdown) in the following structure:

    <div class=\"essay-topic no-break\">
        <p class=\"color-blue\">Topic #[number]</p>
        <h2 class=\"title\">[Specific, Intriguing Title]</h2>

        <p class=\"section-title\"><strong>Rationale:</strong></p>
        <p class=\"rationale-text\">[2–3 sentences, second-person voice, future-oriented]</p>

        <p class=\"section-title\"><strong>Writing Outline:</strong></p>
        <ul class=\"writing-outline\">
            <li><img class=\"bullet\" src=\"https://jeaniusdev.wpenginepowered.com/wp-content/themes/jeanius/assets/images/star-li.png\">Opening thesis that creates immediate engagement</li>
            <li><img class=\"bullet\" src=\"https://jeaniusdev.wpenginepowered.com/wp-content/themes/jeanius/assets/images/star-li.png\">The challenge, question, or tension you faced</li>
            <li><img class=\"bullet\" src=\"https://jeaniusdev.wpenginepowered.com/wp-content/themes/jeanius/assets/images/star-li.png\">The turning point or moment of realization</li>
            <li><img class=\"bullet\" src=\"https://jeaniusdev.wpenginepowered.com/wp-content/themes/jeanius/assets/images/star-li.png\">How this changed your approach or understanding</li>
            <li><img class=\"bullet\" src=\"https://jeaniusdev.wpenginepowered.com/wp-content/themes/jeanius/assets/images/star-li.png\">Connection to who you\'ll be in college</li>
        </ul>

        <p class=\"section-title\"><strong>Why This Works:</strong></p>
        <p class=\"rationale-text\">[1-2 sentences on what character qualities this reveals and why admissions officers would connect with it]</p>

        <p class=\"section-title\"><strong>College Connections:</strong></p>
        <ul class=\"tailoring-tips\">
            <li><span class=\"college-name\">[College Name]</span> - [Specific program, value, opportunity, or aspect of campus culture this connects to]</li>
            <!-- Repeat for each target college -->
        </ul>
    </div>

    Crafting notes to the model:
    - Opening scenes should be concrete (time, place, sensory anchor) and unique across topics.
    - Vary settings and relationship dynamics (home, work, team, community, faith, nature, making/building).
    - Use the provided Ownership Stakes, Life Messages, and Threads as ingredients—mix, don’t mirror.
    - Prefer verbs over adjectives; avoid platitudes and overused essay tropes.
    - Keep the emotional temperature warm but grounded; insight > drama.

    Avoid:
    - Generic topics (big game, mission trip, divorced parents) unless there’s a truly singular angle.
    - Stories about others more than yourself.
    - Trying to impress rather than connect.
    - Abstract philosophizing without concrete examples.
    - Lists of achievements disguised as narrative.

    STRICT RULES:
    - Always output exactly 5 <div class=\"essay-topic\"> blocks.
    - If there are no target colleges, omit the entire College Connections section for all topics.
    - College names must match those provided; do not invent new ones.
    - Speak in second person (\"you\") in rationale and journey lines.
    - No Markdown syntax—HTML only.";

    // Create messages array - Simplified to avoid token limits
    $messages = [
        ["role" => "system", "content" => $system_prompt],
        ["role" => "user", "content" => "Here is the original information (life experiences) used to develop this user's ownership stake list: {$stage_data_flat}
        Here are the ownership stakes: {$ownership_stakes}.
        Here are the life messages corresponding to each ownership stake: {$life_messages}.
        Here are the transcendent threads derived from the information, ownership stakes, and life messages, based on the defined
        list of 22 universal threads: {$transcendent_threads}.
        Here is the summary of their Jeanius: {$sum_of_jeanius}.

        Create 5 distinct essay topics from the life experience data, ownership stakes, life messages and transcendent threads.

        Each topic should reveal different aspects of character and draw from different combinations of their stakes and threads. Look at stakes and threads more than individual events/moments of their life. 
        Don't reuse the same concepts too much."]
    ];

    // Call OpenAI API
    return self::call_openai(
        $api_key,
        $messages,
        ["model" => "gpt-4o-mini", "temperature" => 0.5, "max_tokens" => 3500]
    );
}


    /**
     * Call OpenAI API with error handling
     */
    private static function call_openai($api_key, $messages, $opts = []) {
        // Normalize options with sensible defaults
        $model = $opts['model'] ?? 'gpt-4';
        $temperature = $opts['temperature'] ?? 0.5;
        $max_tokens = $opts['max_tokens'] ?? 900;
        
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ];
        
        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
        ];
        
        $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 220, // Increased timeout
            'headers' => $headers,
            'body' => wp_json_encode($body),
        ]);
        
        if (is_wp_error($resp)) {
            error_log("JeaniusAI: OpenAI API request error: " . $resp->get_error_message());
            return $resp;
        }
        
        $raw = wp_remote_retrieve_body($resp);
        $data = json_decode($raw, true);
        
        if (isset($data['error'])) {
            error_log("JeaniusAI: OpenAI API returned error: " . ($data['error']['message'] ?? 'Unknown error'));
            return new \WP_Error('openai_error', $data['error']['message'] ?? 'OpenAI error', ['status' => 502, 'body' => $data]);
        }
        
        $content = trim($data['choices'][0]['message']['content'] ?? '');
        if ($content === '') {
            error_log("JeaniusAI: OpenAI API returned empty content");
            return new \WP_Error('openai_empty', 'Empty response from OpenAI', ['status' => 502, 'body' => $data]);
        }
        
        return $content;
    }

    /**
     * Get additional ownership stakes examples
     */
    private static function get_extended_ownership_stakes_examples() {
        return [
            "These are good examples:",
            "Resilience",
            "Self-Education",
            "Integrity",
            "Peace in Crisis",
            "The Power of Words",
            "Faith and Spiritual Devotion",
            "Sacrifice",
            "Simplicity",
            "Love in Action",
            "Storytelling ",
            "Creative Expression",
            "Reinvention",
            "Empathy",
            "Vulnerability",
            "Curiosity",
            "Courage to Challenge Authority",
            "Belonging",
            "Resourcefulness",
            "Loyalty to Friends"
        ];
    }

    /**
     * Get additional life message examples
     */
    private static function get_extended_life_message_examples() {
        return [
            "Self-Education — \"Your education is as vast as your curiosity allows.\"",
            "Power of Words — \"Clarity and sincerity in speech can move people to hope and action.\"",
            "Humility — \"Greatness is not measured by power but by kindness.\"",
            "Moral Courage — \"Courage doesn't always shout; sometimes it simply refuses to move.\"",
            "Creativity — \"Stories, when told with honesty, can unite people who feel worlds apart.\"",
            "Resilience — \"Criticism and failure are not endings—they are invitations to grow stronger.\"",
            "Reinvention — \"You don't have to stay the person you once were—you can write your own next chapter.\"",
            "Empathy — \"True connection comes when you let people see your vulnerability.\"",
            "Courage — \"Challenge authority, not for rebellion's sake, but for truth's.\"",
            "Loyalty — \"Even those who seem aloof can be fiercely devoted to the ones they choose.\"",
            "Adventure — \"Sometimes the only way forward is to take the risk.\"",
            "Identity — \"Finding who you are often starts with losing what you thought you had.\""
        ];
    }

    /**
     * Get example ownership stake data for few-shot prompting
     */
    private static function get_example_ownership_stake_data() {
        return [
            // Example 1 - Jeremy's data
            [
                "early_childhood" => [
                    ["title" => "Great Family", "description" => "My parents loved me a lot and we had a dog. Good relationship with my sister.", "polarity" => "positive", "rating" => 5],
                    ["title" => "Lived next to grandparents", "description" => "My mom's parents lived next door. They had a pool and we spent a lot of time with them.", "polarity" => "positive", "rating" => 5],
                    ["title" => "First dogs", "description" => "We had 2 dogs as pets. We loved them and had to give them away eventually.", "polarity" => "positive", "rating" => 3],
                    ["title" => "Fight with Friend", "description" => "He hit my in the face with a rock, so I picked up a big stick and hit him in the head. It broke his Atlanta Braves batting helmet. I got in trouble at school.", "polarity" => "negative", "rating" => 1],
                    ["title" => "Private school", "description" => "I went to a private Christian school during these years. It was a part of a church and I made some good friends and received a good education.", "polarity" => "positive", "rating" => 2],
                    ["title" => "Awesome Church community", "description" => "I loved my church. The older I get, the more I realize that we had a good church. It was small and old school and I probably didn't appreciate it enough back then.", "polarity" => "positive", "rating" => 4]
                ],
                "elementary" => [
                    ["title" => "Played the Gingerbread man", "description" => "I don't remember this per se, but have seen pictures of it. My grandmother made the costume and I was the lead character in the play. My mom was proud.", "polarity" => "positive", "rating" => 2],
                    ["title" => "Fought with my friend", "description" => "I hit him in the head with a stick because he hit me int he face. I don't remember why we were fighting.", "polarity" => "negative", "rating" => 1],
                    ["title" => "Birthday parties", "description" => "I remember having fun birthday parties with my friends from church and school. My mom would make TMNT cakes and we would play video games.", "polarity" => "positive", "rating" => 3],
                    ["title" => "Started Playing basketball", "description" => "Iplayed basketball a lot through my life, and this is where it started. I played in a rec league with other friends and people I didn't know. I was tall and pretty good.", "polarity" => "positive", "rating" => 4],
                    ["title" => "Hit my first homerun in Baseball", "description" => "I remember feeling like a million bucks. My grandad went and found the ball and put it in a case like it was super important.", "polarity" => "positive", "rating" => 3],
                    ["title" => "Birthday breakfasts with Granddaddy", "description" => "On our brithday, he would take us to McDonalds before school. I always ordered the hot stacks.", "polarity" => "positive", "rating" => 4]
                ],
                "middle_school" => [
                    ["title" => "Homeschooled until 7th grade", "description" => "This was a period of my life I didn't like so much. I hated the school part of it and I wanted friends.", "polarity" => "negative", "rating" => 3],
                    ["title" => "Moved to Loganville", "description" => "We moved to a new town with my grandparents still living next door. I liked it.", "polarity" => "positive", "rating" => 3],
                    ["title" => "Made friends in neighborhood", "description" => "I liked these guys because they lived close and I thought they were cool. Looking back, I realize they were probably losers and I shouldn't have been very good friends with them.", "polarity" => "negative", "rating" => 1],
                    ["title" => "First time riding school bus", "description" => "This was a new experience for me. I remember being anxious being around kids that I didn't know who were so much older than me.", "polarity" => "negative", "rating" => 3],
                    ["title" => "Went to Public school for the first time", "description" => "This was actually a good experience for me. I made a lot of friends, enjoyed classes, liked my teachers, and had an overall great experience.", "polarity" => "positive", "rating" => 4],
                    ["title" => "Car accident on the way to church", "description" => "We were driving to church and someone pulled out and hit us. It flipped the car and closed down the major highway we were on. My elbow was injured pretty bad.", "polarity" => "negative", "rating" => 3],
                    ["title" => "Made school basketball team", "description" => "This was epic for me. I loved basketball and it helped me make friends with people who had something in common with me. The coach loved me.", "polarity" => "positive", "rating" => 4]
                ],
                "high_school" => [
                    ["title" => "Captain of basketball team", "description" => "The coaches and players voted me as the captain. It was my first experience as a leader.", "polarity" => "positive", "rating" => 5],
                    ["title" => "Leader in church youth group", "description" => "The other students looked to me as a leader. The teachers asked me to help teach with them.", "polarity" => "positive", "rating" => 4],
                    ["title" => "First girlfriend", "description" => "Was a rite of passage.", "polarity" => "positive", "rating" => 2],
                    ["title" => "Great memories playing video games with dad", "description" => "This was foundational for my relationship with him. We were together a lot and he went out of his way to connect with me over things I liked doing. My friends loved my parents and they were great examples for me in how I want to raise my kids.", "polarity" => "positive", "rating" => 5],
                    ["title" => "Speeding Ticket - Lost license", "description" => "I got caught going 100+ in a 55 and I lost my license for 6 months. It was one of the dumber things I've ever done in my life, but I learned a lot from it.", "polarity" => "negative", "rating" => 4],
                    ["title" => "Deep friendships", "description" => "I made several friends during that time period that meant a lot for me. They taught me loyalty and how to be a good friend.", "polarity" => "positive", "rating" => 4],
                    ["title" => "Epic vacations", "description" => "My family would go to the beach every year, and I was able to take a friend with me. We would play video games, spend time in the ocean, play basketball, and have so much fun with my family.", "polarity" => "positive", "rating" => 5]
                ]
            ],
            // Example 2 - Isaiah's data (simplified to prevent token issues)
            [
                "early_childhood" => [
                    ["title" => "family problems", "description" => "Father's culture didn't mingle with my mom's (interracial). Tough upbringing culturally.", "polarity" => "negative", "rating" => 3],
                    ["title" => "issues with dad", "description" => "My dad always wanted perfection, to be a leader, to be someone. He was hard on me even as a kid.", "polarity" => "negative", "rating" => 4],
                    ["title" => "sister and friends", "description" => "My sister and my friends were my lifeline. They kept me innocent and young and helped me grow up.", "polarity" => "positive", "rating" => 4],
                    ["title" => "guitar", "description" => "I started learning guitar and music as an expression of myself.", "polarity" => "positive", "rating" => 4]
                ],
                "elementary" => [
                    ["title" => "skin color", "description" => "I was in a mixed family, so never felt like I got along with my cultural roots, but never fit in with my white friends.", "polarity" => "negative", "rating" => 3],
                    ["title" => "music and writing", "description" => "This was my escape — writing music and expressing myself.", "polarity" => "positive", "rating" => 5],
                    ["title" => "landscaping", "description" => "I would work hard, 12-15 hour days with my dad making $2-6/hour.", "polarity" => "negative", "rating" => 4]
                ],
                "middle_school" => [
                    ["title" => "separation", "description" => "My parents started living in differnt rooms and were not romantically involved.", "polarity" => "negative", "rating" => 4],
                    ["title" => "music", "description" => "Music was always my anchor and escape. I could write amazing songs or express myself.", "polarity" => "positive", "rating" => 5],
                    ["title" => "friendship and community", "description" => "My closest friends were there when I was growing up.", "polarity" => "positive", "rating" => 4]
                ],
                "high_school" => [
                    ["title" => "unique", "description" => "I started to not fit into any sort of mold with career aspirations.", "polarity" => "positive", "rating" => 3],
                    ["title" => "moved out", "description" => "My dad and I fought a lot, and I moved out when I was 16.", "polarity" => "negative", "rating" => 4],
                    ["title" => "music album", "description" => "I released my first album in light of the breakup.", "polarity" => "positive", "rating" => 5]
                ]
            ]
        ];
    }
}