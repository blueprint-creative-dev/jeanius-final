<?php
namespace Jeanius;

class Rest {

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes() {
		register_rest_route( 'jeanius/v1', '/stage', [
			'methods'             => 'POST',
			'permission_callback' => function () { 
				return is_user_logged_in(); 
			},
			'callback'            => [ __CLASS__, 'save_stage' ],
		] );

		register_rest_route( 'jeanius/v1', '/review', [
			'methods'             => 'POST',
			'permission_callback' => function() { 
				return is_user_logged_in(); 
			},
			'callback'            => [ __CLASS__, 'save_order' ],
		] );

		// Save description for one word
		register_rest_route( 'jeanius/v1', '/describe', [
			'methods'             => 'POST',
			'permission_callback' => function() { 
				return is_user_logged_in(); 
			},
			'callback'            => [ __CLASS__, 'save_description' ],
		] );

		// Generate Jeanius report (OpenAI)
		// POST /wp-json/jeanius/v1/generate
		register_rest_route( 'jeanius/v1', '/generate', [
			'methods'             => 'POST',
			'permission_callback' => fn() => is_user_logged_in(),
			'callback'            => [ __CLASS__, 'generate_report' ],
		] );
		
		// Report generation status endpoint
		register_rest_route( 'jeanius/v1', '/status', [
			'methods'             => 'GET',
			'permission_callback' => fn() => is_user_logged_in(),
			'callback'            => [ __CLASS__, 'get_report_status' ],
		] );
	}

	public static function save_stage( \WP_REST_Request $r ) {
		$post_id = \Jeanius\current_assessment_id();
		
		if ( ! $post_id ) {
			return new \WP_Error( 'login_required', 'Login first', [ 'status' => 401 ] );
		}

		$stage_key = sanitize_text_field( $r->get_param( 'stage' ) );
		$entries   = $r->get_param( 'entries' );

		if ( ! $stage_key || empty( $entries ) ) {
			return new \WP_Error( 'missing', 'Missing data', [ 'status' => 400 ] );
		}

		$data = json_decode( get_field( 'stage_data', $post_id ) ?: '{}', true );
		$data[ $stage_key ] = array_values(
			array_filter( array_map( 'sanitize_text_field', $entries ) )
		);

		\update_field( 'stage_data', wp_json_encode( $data ), $post_id );

		return [ 'success' => true ];
	}

	/**
	 * Save reordered words during 5-minute review
	 * POST /jeanius/v1/review
	 * Body: { "ordered": { "early_childhood":[...], "elementary":[...] ... } }
	 */
	public static function save_order( \WP_REST_Request $r ) {
		$post_id = \Jeanius\current_assessment_id();
		
		if ( ! $post_id ) {
			return new \WP_Error( 'login', 'Login required', [ 'status' => 401 ] );
		}

		$ordered = $r->get_param( 'ordered' );
		
		if ( ! is_array( $ordered ) ) {
			return new \WP_Error( 'bad', 'Missing ordered data', [ 'status' => 400 ] );
		}

		\update_field( 'stage_data', wp_json_encode( $ordered ), $post_id );
		
		return [ 'success' => true ];
	}

	/**
	 * Helper to read/write progress count
	 */
	private static function stage_counter( int $post_id, string $stage, ?int $set = null ) {
		$key = "_{$stage}_done";
		
		if ( $set !== null ) {
			update_post_meta( $post_id, $key, $set );
		}
		
		return (int) get_post_meta( $post_id, $key, true );
	}

	public static function save_description( \WP_REST_Request $r ) {
		$post_id = \Jeanius\current_assessment_id();
		
		if ( ! $post_id ) {
			return new \WP_Error( 'login', 'Login', [ 'status' => 401 ] );
		}

		$stage   = sanitize_text_field( $r['stage'] );
		$index   = (int) $r['index'];
		$desc    = sanitize_textarea_field( $r['description'] );
		$pol     = $r['polarity'] === 'negative' ? 'negative' : 'positive';
		$rating  = min( 5, max( 1, (int) $r['rating'] ) );

		// Append to full_stage_data
		$full = json_decode( get_field( 'full_stage_data', $post_id ) ?: '{}', true );
		$full[ $stage ][] = [
			'title'       => $r['title'],
			'description' => $desc,
			'polarity'    => $pol,
			'rating'      => $rating,
		];
		
		update_field( 'full_stage_data', wp_json_encode( $full ), $post_id );

		// Bump progress counter
		$done = self::stage_counter( $post_id, $stage );
		self::stage_counter( $post_id, $stage, $done + 1 );

		return [ 'success' => true ];
	}

	public static function get_timeline_data( int $post_id ): array {
		$raw   = json_decode( get_field( 'full_stage_data', $post_id ) ?: '{}', true );
		$out   = [];
		$order = [ 'early_childhood', 'elementary', 'middle_school', 'high_school' ];
		
		foreach ( $order as $stage_idx => $stage_key ) {
			if ( empty( $raw[ $stage_key ] ) ) {
				continue;
			}
			
			foreach ( $raw[ $stage_key ] as $seq => $item ) {
				// Safeguard: cast plain strings (shouldn't exist now) to minimal object
				if ( ! is_array( $item ) ) {
					$item = [
						'title'       => $item,
						'description' => '',
						'polarity'    => 'positive',
						'rating'      => 3
					];
				}
				
				$out[] = [
					'label'       => $item['title'],
					'stage'       => $stage_key,
					'stage_order' => $stage_idx,
					'seq'         => $seq,
					'description' => $item['description'],
					'polarity'    => $item['polarity'],
					'rating'      => (int) $item['rating'],
				];
			}
		}
		
		return $out;
	}

	/**
	 * Get report generation status via REST
	 */
	public static function get_report_status( \WP_REST_Request $r ) {
		$post_id = isset( $r['post_id'] ) ? intval( $r['post_id'] ) : \Jeanius\current_assessment_id();
		
		if ( ! $post_id ) {
			return new \WP_Error( 'login', 'Login required', [ 'status' => 401 ] );
		}

		return JeaniusAI::get_generation_status( $post_id );
	}

	/**
	 * Generate Jeanius report via REST
	 * Now uses the enhanced JeaniusAI class with staged processing
	 */
	public static function generate_report( \WP_REST_Request $r ) {
		$post_id = isset( $r['post_id'] ) ? intval( $r['post_id'] ) : \Jeanius\current_assessment_id();
		
		if ( ! $post_id ) {
			return new \WP_Error( 'login', 'Login required', [ 'status' => 401 ] );
		}

		// If report is already generated and no force parameter, return it
		if ( get_field( 'jeanius_report_md', $post_id ) && empty( $r['force'] ) ) {
			return [ 'status' => 'ready' ];
		}

		// Check for OpenAI API key
		$api_key = trim( (string) get_field( 'openai_api_key', 'option' ) );
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'key', 'OpenAI key missing', [ 'status' => 500 ] );
		}

		// Use the new JeaniusAI class for staged generation
		// Reset progress if forcing regeneration
		if ( ! empty( $r['force'] ) ) {
			JeaniusAI::reset_generation_progress( $post_id );
		}
		
		// Start/continue the report generation
		$result = JeaniusAI::generate_report( $post_id );
		
		return $result;
	}

	/**
	 * Markdown to HTML conversion - kept for backward compatibility
	 */
	private static function markdown_to_html( string $markdown ): string {
		$api_key = trim( (string) get_field( 'openai_api_key', 'option' ) );
		
		if ( ! $api_key ) {
			return $markdown; // fallback: leave md unchanged
		}

		$body = [
			'model'       => 'o3-mini',
			'max_tokens'  => 2048,
			'temperature' => 0,
			'messages'    => [
				[ 
					'role'    => 'system', 
					'content' => 'You are a Markdown to HTML converter. Return ONLY valid HTML inside <section> without additional commentary.' 
				],
				[ 
					'role'    => 'user', 
					'content' => $markdown 
				]
			],
		];

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			[
				'timeout' => 40,
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key
				],
				'body' => wp_json_encode( $body )
			]
		);

		if ( is_wp_error( $response ) ) {
			return $markdown;
		}

		$parsed = json_decode( wp_remote_retrieve_body( $response ), true );
		
		return $parsed['choices'][0]['message']['content'] ?? $markdown;
	}
}