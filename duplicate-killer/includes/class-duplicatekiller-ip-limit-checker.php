<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DuplicateKiller_IP_Limit_Checker {

	public static function check(
		string $form_plugin,
		string $form_name,
		array $form_config,
		array $debug_context = array()
	): array {

		$result = array(
			'blocked' => false,
			'message' => '',
			'ip'      => '',
			'days'    => 0,
		);

		// IP limit must be explicitly enabled for this form.
		if ( empty( $form_config['user_ip'] ) || (string) $form_config['user_ip'] !== '1' ) {
			self::log( $form_plugin, 'ip_limit_skipped_disabled', array_merge(
				$debug_context,
				array(
					'form_name' => $form_name,
				)
			) );

			return $result;
		}

		$form_ip      = self::get_user_ip();
		$user_ip_days = ! empty( $form_config['user_ip_days'] ) ? (int) $form_config['user_ip_days'] : 7;

		self::log( $form_plugin, 'ip_limit_check_start', array_merge(
			$debug_context,
			array(
				'form_name'    => $form_name,
				'ip'           => $form_ip,
				'user_ip_days' => $user_ip_days,
			)
		) );

		$is_blocked = self::ip_exists_within_limit(
			$form_plugin,
			$form_name,
			$form_ip,
			$user_ip_days
		);

		if ( ! $is_blocked ) {
			self::log( $form_plugin, 'ip_limit_passed', array_merge(
				$debug_context,
				array(
					'form_name'    => $form_name,
					'ip'           => $form_ip,
					'user_ip_days' => $user_ip_days,
				)
			) );

			return $result;
		}

		$message = ! empty( $form_config['error_message_limit_ip_option'] )
			? $form_config['error_message_limit_ip_option']
			: __( 'This IP has been already submitted.', 'duplicate-killer' );

		self::log( $form_plugin, 'ip_limit_blocked', array_merge(
			$debug_context,
			array(
				'form_name' => $form_name,
				'ip'        => $form_ip,
				'days'      => $user_ip_days,
				'message'   => $message,
			)
		) );

		return array(
			'blocked' => true,
			'message' => $message,
			'ip'      => $form_ip,
			'days'    => $user_ip_days,
		);
	}

	private static function ip_exists_within_limit(
		string $form_plugin,
		string $form_name,
		string $form_ip,
		int $user_ip_days = 7
	): bool {
		global $wpdb;

		if ( '' === $form_ip || 'undefined' === $form_ip ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'dk_forms_duplicate';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required IP limit query on plugin-owned table.
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT form_ip, form_date
				 FROM " . esc_sql( $table_name ) . "
				 WHERE form_plugin = %s
				 AND form_name = %s
				 AND form_ip = %s
				 ORDER BY form_id DESC",
				$form_plugin,
				$form_name,
				$form_ip
			)
		);

		if ( ! $result || empty( $result->form_date ) ) {
			return false;
		}

		try {
			$created_at = new DateTime( $result->form_date, new DateTimeZone( 'UTC' ) );
			$threshold  = new DateTime( "-{$user_ip_days} days", new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			return false;
		}

		return $created_at > $threshold;
	}

	public static function get_user_ip(): string {
		$ip_valid = 'undefined';

		if (
			self::is_cloudflare_request()
			&& isset( $_SERVER['HTTP_CF_CONNECTING_IP'] )
		) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated via filter_var(FILTER_VALIDATE_IP).
			$raw = wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] );
			$raw = trim( $raw );

			if ( filter_var( $raw, FILTER_VALIDATE_IP ) ) {
				return apply_filters( 'duplicateKiller_get_user_ip', $raw );
			}
		}

		if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated via filter_var(FILTER_VALIDATE_IP).
			$raw = wp_unslash( $_SERVER['HTTP_CLIENT_IP'] );
			$raw = trim( $raw );

			if ( filter_var( $raw, FILTER_VALIDATE_IP ) ) {
				return apply_filters( 'duplicateKiller_get_user_ip', $raw );
			}
		}

		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated via filter_var(FILTER_VALIDATE_IP).
			$raw = wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$raw = trim( $raw );

			$parts    = array_map( 'trim', explode( ',', $raw ) );
			$first_ip = $parts[0] ?? '';

			if ( $first_ip && filter_var( $first_ip, FILTER_VALIDATE_IP ) ) {
				return apply_filters( 'duplicateKiller_get_user_ip', $first_ip );
			}
		}

		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated via filter_var(FILTER_VALIDATE_IP).
			$raw = wp_unslash( $_SERVER['REMOTE_ADDR'] );
			$raw = trim( $raw );

			if ( filter_var( $raw, FILTER_VALIDATE_IP ) ) {
				$ip_valid = $raw;
			}
		}

		return apply_filters( 'duplicateKiller_get_user_ip', $ip_valid );
	}

	private static function is_cloudflare_request(): bool {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by Cloudflare range check.
		$remote_ip = trim( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

		return self::cloudflare_check_ip( $remote_ip ) && self::cloudflare_headers_exist();
	}

	private static function cloudflare_headers_exist(): bool {
		return isset(
			$_SERVER['HTTP_CF_CONNECTING_IP'],
			$_SERVER['HTTP_CF_IPCOUNTRY'],
			$_SERVER['HTTP_CF_RAY'],
			$_SERVER['HTTP_CF_VISITOR']
		);
	}

	private static function cloudflare_check_ip( string $ip ): bool {
		$cf_ips = array(
			'173.245.48.0/20',
			'103.21.244.0/22',
			'103.22.200.0/22',
			'103.31.4.0/22',
			'141.101.64.0/18',
			'108.162.192.0/18',
			'190.93.240.0/20',
			'188.114.96.0/20',
			'197.234.240.0/22',
			'198.41.128.0/17',
			'162.158.0.0/15',
			'104.16.0.0/13',
			'104.24.0.0/14',
			'172.64.0.0/13',
			'131.0.72.0/22',
		);

		foreach ( $cf_ips as $cf_ip ) {
			if ( self::ip_in_range( $ip, $cf_ip ) ) {
				return true;
			}
		}

		return false;
	}

	private static function ip_in_range( string $ip, string $range ): bool {
		if ( false === strpos( $range, '/' ) ) {
			$range .= '/32';
		}

		list( $range, $netmask ) = explode( '/', $range, 2 );

		$range_decimal = ip2long( $range );
		$ip_decimal    = ip2long( $ip );

		if ( false === $range_decimal || false === $ip_decimal ) {
			return false;
		}

		$wildcard_decimal = pow( 2, ( 32 - (int) $netmask ) ) - 1;
		$netmask_decimal  = ~ $wildcard_decimal;

		return ( $ip_decimal & $netmask_decimal ) === ( $range_decimal & $netmask_decimal );
	}

	private static function log( string $form_plugin, string $stage, array $payload = array() ): void {
		if ( ! class_exists( 'duplicateKiller_Diagnostics' ) ) {
			return;
		}

		duplicateKiller_Diagnostics::log(
			strtolower( $form_plugin ),
			$stage,
			$payload
		);
	}
}