<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Visibility module.
 *
 * Tracks AI bot visits to the site and provides analytics
 * on crawl activity, page coverage, and bot diversity.
 */
class AI_SEO_Pilot_AI_Visibility {

	/** @var string Database table name (set in constructor). */
	private $table;

	/** @var array<string, string> Known AI bot identifiers mapped to display names. */
	private $known_bots = array(
		'GPTBot'           => 'GPTBot',
		'ChatGPT-User'    => 'ChatGPT-User',
		'Claude-Web'      => 'Claude-Web',
		'ClaudeBot'       => 'ClaudeBot',
		'PerplexityBot'   => 'PerplexityBot',
		'Google-Extended' => 'Google-Extended',
		'Amazonbot'       => 'Amazonbot',
		'Bytespider'      => 'Bytespider',
		'cohere-ai'       => 'cohere-ai',
		'YouBot'          => 'YouBot',
	);

	public function __construct() {
		global $wpdb;

		$this->table = $wpdb->prefix . 'ai_seo_pilot_bot_visits';

		// Track bot visits on every frontend request (early priority).
		add_action( 'init', array( $this, 'track_visit' ), 1 );

		// Cleanup cron.
		add_action( 'ai_seo_pilot_cleanup_bot_visits', array( $this, 'cleanup_old_visits' ) );

		if ( ! wp_next_scheduled( 'ai_seo_pilot_cleanup_bot_visits' ) ) {
			wp_schedule_event( time(), 'daily', 'ai_seo_pilot_cleanup_bot_visits' );
		}
	}

	/**
	 * Detect AI bot visits and log them to the database.
	 */
	public function track_visit() {
		if ( 'yes' !== get_option( 'ai_seo_pilot_ai_visibility_enabled', 'yes' ) ) {
			return;
		}

		// Only track frontend requests.
		if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) || wp_doing_cron() ) {
			return;
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';

		if ( empty( $user_agent ) ) {
			return;
		}

		$bot_name = $this->detect_bot( $user_agent );

		if ( false === $bot_name ) {
			return;
		}

		global $wpdb;

		$url        = $this->get_current_url();
		$ip_address = $this->anonymize_ip( $this->get_client_ip() );

		$wpdb->insert(
			$this->table,
			array(
				'bot_name'    => $bot_name,
				'user_agent'  => substr( $user_agent, 0, 500 ),
				'url'         => substr( $url, 0, 2048 ),
				'ip_address'  => $ip_address,
				'status_code' => 200,
				'visited_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Get aggregated visit statistics for the given period.
	 *
	 * @param int $days Number of days to look back.
	 * @return array{total_visits: int, unique_bots: int, top_bot: string, avg_daily: float, visits_by_bot: array<string, int>}
	 */
	public function get_visit_stats( $days = 30 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Visits grouped by bot.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot_name, COUNT(*) AS cnt FROM {$this->table} WHERE visited_at >= %s GROUP BY bot_name ORDER BY cnt DESC",
				$since
			)
		);

		$visits_by_bot = array();
		$total_visits  = 0;

		foreach ( $rows as $row ) {
			$visits_by_bot[ $row->bot_name ] = (int) $row->cnt;
			$total_visits += (int) $row->cnt;
		}

		$unique_bots = count( $visits_by_bot );
		$top_bot     = $unique_bots > 0 ? array_key_first( $visits_by_bot ) : '';
		$avg_daily   = $days > 0 ? round( $total_visits / $days, 2 ) : 0;

		return array(
			'total_visits'  => $total_visits,
			'unique_bots'   => $unique_bots,
			'top_bot'       => $top_bot,
			'avg_daily'     => $avg_daily,
			'visits_by_bot' => $visits_by_bot,
		);
	}

	/**
	 * Get daily visit counts for chart display.
	 *
	 * @param int $days Number of days to look back.
	 * @return array<int, array{date: string, count: int}>
	 */
	public function get_daily_visits( $days = 30 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(visited_at) AS visit_date, COUNT(*) AS cnt FROM {$this->table} WHERE visited_at >= %s GROUP BY visit_date ORDER BY visit_date ASC",
				$since
			)
		);

		$results = array();
		foreach ( $rows as $row ) {
			$results[] = array(
				'date'  => $row->visit_date,
				'count' => (int) $row->cnt,
			);
		}

		return $results;
	}

	/**
	 * Get the most visited pages by AI bots.
	 *
	 * @param int $days  Number of days to look back.
	 * @param int $limit Maximum number of pages to return.
	 * @return array<int, array{url: string, visits: int, bots: string}>
	 */
	public function get_top_pages( $days = 30, $limit = 10 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT url, COUNT(*) AS visits, GROUP_CONCAT(DISTINCT bot_name ORDER BY bot_name SEPARATOR ', ') AS bots FROM {$this->table} WHERE visited_at >= %s GROUP BY url ORDER BY visits DESC LIMIT %d",
				$since,
				$limit
			)
		);

		$results = array();
		foreach ( $rows as $row ) {
			$results[] = array(
				'url'    => $row->url,
				'visits' => (int) $row->visits,
				'bots'   => $row->bots,
			);
		}

		return $results;
	}

	/**
	 * Calculate a crawl health score from 0 to 100.
	 *
	 * Scoring breakdown:
	 * - Bot diversity (0-40): up to 10 points per distinct bot, max 4 bots for full score.
	 * - Visit frequency (0-30): based on average daily visits in the last 7 days.
	 * - Page coverage (0-30): unique pages crawled / total published posts.
	 *
	 * @return int Score between 0 and 100.
	 */
	public function get_crawl_health_score() {
		global $wpdb;

		$seven_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		// Bot diversity: distinct bots in the last 7 days.
		$distinct_bots = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT bot_name) FROM {$this->table} WHERE visited_at >= %s",
				$seven_days_ago
			)
		);

		// Cap at 4 bots for max score.
		$diversity_score = min( $distinct_bots, 4 ) * 10;

		// Visit frequency: average daily visits in the last 7 days.
		$total_visits_7d = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE visited_at >= %s",
				$seven_days_ago
			)
		);

		$avg_daily = $total_visits_7d / 7;

		if ( $avg_daily >= 50 ) {
			$frequency_score = 30;
		} elseif ( $avg_daily >= 20 ) {
			$frequency_score = 20;
		} elseif ( $avg_daily >= 5 ) {
			$frequency_score = 10;
		} else {
			$frequency_score = 0;
		}

		// Page coverage: unique crawled pages / total published posts+pages.
		$unique_pages = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT url) FROM {$this->table} WHERE visited_at >= %s",
				$seven_days_ago
			)
		);

		$total_published = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post', 'page')"
		);

		if ( $total_published > 0 ) {
			$coverage_ratio = $unique_pages / $total_published;
			$coverage_score = min( (int) round( $coverage_ratio * 30 ), 30 );
		} else {
			$coverage_score = 0;
		}

		return $diversity_score + $frequency_score + $coverage_score;
	}

	/**
	 * Delete visits older than the configured retention period.
	 */
	public function cleanup_old_visits() {
		global $wpdb;

		$retention_days = (int) get_option( 'ai_seo_pilot_bot_retention_days', 90 );
		$cutoff         = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE visited_at < %s",
				$cutoff
			)
		);
	}

	/* ── Private helpers ──────────────────────────────────────────── */

	/**
	 * Detect if a user agent belongs to a known AI bot.
	 *
	 * @param string $user_agent The raw user agent string.
	 * @return string|false Bot display name, or false if not a known bot.
	 */
	private function detect_bot( $user_agent ) {
		foreach ( $this->known_bots as $identifier => $name ) {
			if ( false !== stripos( $user_agent, $identifier ) ) {
				return $name;
			}
		}

		return false;
	}

	/**
	 * Build the full URL of the current request.
	 *
	 * @return string
	 */
	private function get_current_url() {
		$scheme = is_ssl() ? 'https' : 'http';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';

		return esc_url_raw( "{$scheme}://{$host}{$uri}" );
	}

	/**
	 * Get the client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// Take the first IP in the chain.
			$ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			return trim( $ips[0] );
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
	}

	/**
	 * Anonymize an IP address by zeroing the last segment.
	 *
	 * IPv4: X.X.X.0
	 * IPv6: truncate last segment.
	 *
	 * @param string $ip The raw IP address.
	 * @return string The anonymized IP address.
	 */
	private function anonymize_ip( $ip ) {
		if ( empty( $ip ) ) {
			return '';
		}

		// IPv4.
		if ( false !== strpos( $ip, '.' ) && false === strpos( $ip, ':' ) ) {
			$parts = explode( '.', $ip );
			if ( 4 === count( $parts ) ) {
				$parts[3] = '0';
				return implode( '.', $parts );
			}
			return $ip;
		}

		// IPv6.
		if ( false !== strpos( $ip, ':' ) ) {
			$parts = explode( ':', $ip );
			if ( count( $parts ) > 1 ) {
				array_pop( $parts );
				return implode( ':', $parts ) . ':0';
			}
		}

		return $ip;
	}
}
