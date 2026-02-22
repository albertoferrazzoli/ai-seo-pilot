<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_SEO_Pilot_Migrator {

	const DB_VERSION_OPTION  = 'ai_seo_pilot_db_version';
	const CURRENT_DB_VERSION = '1.0.0';

	public static function run() {
		$installed = get_option( self::DB_VERSION_OPTION, '0.0.0' );

		if ( version_compare( $installed, self::CURRENT_DB_VERSION, '>=' ) ) {
			return;
		}

		self::ensure_tables_exist();

		if ( version_compare( $installed, '1.0.0', '<' ) ) {
			self::migrate_to_1_0_0();
		}

		update_option( self::DB_VERSION_OPTION, self::CURRENT_DB_VERSION );
	}

	private static function ensure_tables_exist() {
		global $wpdb;

		$table           = $wpdb->prefix . 'ai_seo_pilot_bot_visits';
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			bot_name varchar(50) NOT NULL,
			user_agent varchar(500) NOT NULL DEFAULT '',
			url varchar(2048) NOT NULL DEFAULT '',
			ip_address varchar(45) NOT NULL DEFAULT '',
			status_code smallint(5) UNSIGNED NOT NULL DEFAULT 200,
			visited_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY bot_name (bot_name),
			KEY visited_at (visited_at),
			KEY url (url(191))
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private static function migrate_to_1_0_0() {
		do_action( 'ai_seo_pilot_migrated_1_0_0' );
	}

	public static function drop_tables() {
		global $wpdb;
		$table = $wpdb->prefix . 'ai_seo_pilot_bot_visits';
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		delete_option( self::DB_VERSION_OPTION );
	}
}
