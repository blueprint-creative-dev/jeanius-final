<?php
namespace Jeanius;

/**
 * JeaniusAdmin - Handles admin-specific functionality
 * Integrates with ACF resubmit button and displays report generation status
 */
class JeaniusAdmin {
    /**
     * Initialize the class - register hooks
     */
public static function init() {
    // Hook into admin scripts
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
    
    // Add action for the regenerate button
    add_action('acf/render_field/key=field_68af564391c91', [__CLASS__, 'render_regenerate_button_js']);
    
    // Add a metabox to display generation status
    add_action('add_meta_boxes', [__CLASS__, 'add_generation_status_metabox']);
    
    // Register AJAX handlers
    add_action('wp_ajax_jeanius_check_status', [__CLASS__, 'ajax_check_status']);
    add_action('wp_ajax_jeanius_regenerate_report', [__CLASS__, 'ajax_regenerate_report']);
}

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_admin_scripts($hook) {
        $screen = get_current_screen();
    
    // Only load on assessment edit screens
    if ($screen->post_type !== 'assessment') {
        return;
    }
    
    // Get absolute plugin URL
    $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
    $plugin_path = plugin_dir_path(dirname(dirname(__FILE__)));
    
    // Register and enqueue the admin script with ABSOLUTE paths
    wp_register_script(
        'jeanius-admin',
        $plugin_url . 'public/js/jeanius-admin.js',
        ['jquery'],
        JEANIUS_VERSION,
        true
    );
        
        // Add localized data
        wp_localize_script('jeanius-admin', 'JeaniusAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('jeanius_admin_nonce'),
            'postId' => get_the_ID(),
            'statusCheckInterval' => 5000, // 5 seconds
            'labels' => [
                'regenerating' => __('Regenerating report...', 'jeanius'),
                'statusUpdating' => __('Updating status...', 'jeanius'),
                'errorOccurred' => __('Error occurred', 'jeanius'),
                'reportComplete' => __('Report generation complete!', 'jeanius'),
                'rateLimited' => __('Rate limited by OpenAI, will resume shortly...', 'jeanius'),
                'stages' => [
                    'ownership_stakes' => __('Ownership Stakes', 'jeanius'),
                    'life_messages' => __('Life Messages', 'jeanius'),
                    'transcendent_threads' => __('Transcendent Threads', 'jeanius'),
                    'summary' => __('Sum of Jeanius', 'jeanius'),
                    'essays' => __('Essay Topics', 'jeanius'),
                    'complete' => __('Complete', 'jeanius')
                ]
            ]
        ]);
        
       // Enqueue the script
    wp_enqueue_script('jeanius-admin'); 
    
    // Enqueue styles with ABSOLUTE paths
    wp_enqueue_style(
        'jeanius-admin-styles',
        $plugin_url . 'public/css/jeanius-admin.css',
        [],
        JEANIUS_VERSION
    );
    }
    

    public static function render_regenerate_button_js($field) {
    ?>
    <script type="text/javascript">
    (function($) {
        // Wait for document ready
        $(document).ready(function() {
            console.log('Jeanius regenerate button initializing...');
            
            // Find the ACF field and add our custom handler
            const $button = $('.acf-field[data-key="field_68af564391c91"] button');
            
            if ($button.length === 0) {
                console.error('Button not found');
                return;
            }
            
            console.log('Button found, adding handler');
            
            // Add the click handler - DIRECTLY DEFINE FUNCTION HERE
            $button.on('click', function(e) {
                e.preventDefault();
                console.log('Regenerate button clicked');
                
                // Define regeneration function inline
                const regenerateReport = function() {
                    console.log('Starting regeneration');
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'jeanius_regenerate_report',
                            post_id: <?php echo get_the_ID(); ?>,
                            nonce: '<?php echo wp_create_nonce('jeanius_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            console.log('Regeneration started', response);
                            alert('Report regeneration started. Check the status in the sidebar.');
                        },
                        error: function(xhr, status, error) {
                            console.error('Regeneration error', error);
                            alert('Error starting report regeneration: ' + error);
                        }
                    });
                };
                
                // Call the function
                regenerateReport();
            });
            
            console.log('Button handler added');
        });
    })(jQuery);
    </script>
    <?php
}

    /**
     * Add metabox to display generation status
     */
    public static function add_generation_status_metabox() {
        add_meta_box(
            'jeanius_generation_status',
            __('Jeanius Report Generation Status', 'jeanius'),
            [__CLASS__, 'render_generation_status_metabox'],
            'assessment',
            'side',
            'high'
        );
    }

    /**
     * Render the generation status metabox
     */
    public static function render_generation_status_metabox($post) {
        // Get current status
        $status = JeaniusAI::get_generation_status($post->ID);
        
        // Default status classes
        $status_class = 'status-unknown';
        
        if ($status['status'] === 'complete') {
            $status_class = 'status-complete';
        } elseif ($status['status'] === 'in_progress') {
            $status_class = 'status-in-progress';
        } elseif ($status['status'] === 'error') {
            $status_class = 'status-error';
        } elseif ($status['status'] === 'waiting') {
            $status_class = 'status-waiting';
        } elseif ($status['status'] === 'not_started') {
            $status_class = 'status-not-started';
        }
        
        // Format last activity time if available
        $last_activity = '';
        if (!empty($status['last_activity'])) {
            $last_activity = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $status['last_activity']);
        }
        
        // Create a user-friendly message
        $message = $status['message'];
        
        // Render the metabox
        ?>
        <div id="jeanius-status-metabox" class="jeanius-status-container <?php echo esc_attr($status_class); ?>">
            <div class="jeanius-status-header">
                <span class="jeanius-status-indicator"></span>
                <span class="jeanius-status-label"><?php echo esc_html($status['status']); ?></span>
            </div>
            
            <?php if ($status['progress'] > 0) : ?>
            <div class="jeanius-progress-bar-container">
                <div class="jeanius-progress-bar" style="width: <?php echo esc_attr($status['progress']); ?>%"></div>
            </div>
            <?php endif; ?>
            
            <div class="jeanius-status-message">
                <?php echo esc_html($message); ?>
            </div>
            
            <?php if (!empty($last_activity)) : ?>
            <div class="jeanius-status-last-activity">
                <?php echo esc_html(__('Last activity:', 'jeanius')); ?> <?php echo esc_html($last_activity); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($status['errors'])) : ?>
            <div class="jeanius-status-errors">
                <?php echo esc_html($status['errors']); ?>
            </div>
            <?php endif; ?>
            
            <div class="jeanius-status-actions">
                <button type="button" id="jeanius-check-status" class="button button-secondary">
                    <?php echo esc_html(__('Check Status', 'jeanius')); ?>
                </button>
                
                <button type="button" id="jeanius-regenerate-report" class="button button-primary">
                    <?php echo esc_html(__('Regenerate Report', 'jeanius')); ?>
                </button>
            </div>
        </div>
        <?php
    }
}