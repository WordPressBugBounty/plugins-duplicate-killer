<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DuplicateKiller_FieldDuplicate_Checker {

	/**
	 * Checks submitted form data against the enabled duplicate-protected fields.
	 *
	 * This method does not stop the form submission directly.
	 * It only returns a structured result that tells the integration
	 * whether the submission should be blocked or allowed.
	 */
	public static function check(
		string $form_plugin,
		string $form_name,
		array $enabled_fields,
		array $data,
		string $form_cookie = 'NULL',
		bool $checked_cookie = false,
		array $form_config = array(),
		array $debug_context = array()
	): array {

		// Default response: no duplicate found, submission is allowed.
		$result = array(
			'blocked'     => false,
			'message'     => '',
			'field_key'   => '',
			'field_value' => '',
		);
		
		self::log( $form_plugin, 'field_duplicate_check_start', array_merge(
			$debug_context,
			array(
				'form_name'        => $form_name,
				'enabled_fields'   => $enabled_fields,
				'data_keys'        => array_keys( $data ),
				'form_cookie'      => $form_cookie,
				'checked_cookie'   => $checked_cookie ? 1 : 0,
			)
		) );
		// If there are no enabled fields or no submitted data, there is nothing to check.
		if ( empty( $enabled_fields ) || empty( $data ) ) {
			return $result;
		}

		// Check each field configured as duplicate-protected.
		foreach ( $enabled_fields as $field_key ) {

			// Skip fields that were not submitted by the current form.
			if ( ! array_key_exists( $field_key, $data ) ) {
				continue;
			}

			// Some form plugins may submit field values as arrays.
			// For duplicate checking, we use the first value.
			$submitted_value = is_array( $data[ $field_key ] )
				? reset( $data[ $field_key ] )
				: $data[ $field_key ];

			// Sanitize the submitted value before comparing it with stored data.
			$submitted_value = sanitize_text_field( $submitted_value );
			
			self::log( $form_plugin, 'field_inspected', array_merge(
				$debug_context,
				array(
					'form_name'   => $form_name,
					'field_key'   => $field_key,
					'field_value' => $submitted_value,
				)
			) );
			// Empty values should not trigger duplicate checks.
			if ( '' === $submitted_value ) {
				continue;
			}

			// Check if this field value already exists for the current plugin and form.
			$is_duplicate = self::check_duplicate_by_key_value(
				$form_plugin,
				$form_name,
				$field_key,
				$submitted_value,
				$form_cookie,
				$checked_cookie,
				$form_config
			);
			self::log( $form_plugin, 'field_duplicate_check_result', array_merge(
				$debug_context,
				array(
					'form_name'        => $form_name,
					'field_key'        => $field_key,
					'field_value'      => $submitted_value,
					'duplicate_result' => $is_duplicate ? 1 : 0,
					'form_cookie'      => $form_cookie,
					'checked_cookie'   => $checked_cookie ? 1 : 0,
				)
			) );
			// If no duplicate was found for this field, continue with the next one.
			if ( ! $is_duplicate ) {
				continue;
			}

			// Use the custom form error message if available.
			// Otherwise, fall back to the default plugin message.
			$message = ! empty( $form_config['error_message'] )
				? $form_config['error_message']
				: __( 'Please check all fields!', 'duplicate-killer' );
			
			self::log( $form_plugin, 'duplicate_found', array_merge(
				$debug_context,
				array(
					'form_name'   => $form_name,
					'field_key'   => $field_key,
					'field_value' => $submitted_value,
					'message'     => $message,
				)
			) );
			// Return immediately when the first duplicate field is found.
			return array(
				'blocked'     => true,
				'message'     => $message,
				'field_key'   => $field_key,
				'field_value' => $submitted_value,
			);
		}
		
		// No duplicate found after checking all enabled fields.
		self::log( $form_plugin, 'field_duplicate_check_passed', array_merge(
			$debug_context,
			array(
				'form_name' => $form_name,
			)
		) );
		
		return $result;
	}

	/**
	 * Checks if a specific field key and value already exist in the database.
	 *
	 * The query loads all stored submissions for the current plugin and form,
	 * then checks the submitted field value against the stored serialized data.
	 *
	 * A static request-level cache is used to avoid running the same SELECT
	 * multiple times during a single form submission.
	 */
	public static function check_duplicate_by_key_value(
		string $form_plugin,
		string $form_name,
		string $key,
		$value,
		string $form_cookie = 'NULL',
		bool $checked_cookie = false,
		array $form_config = array()
	): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'dk_forms_duplicate';

		// Cache database results during the current request.
		// Cache key is based on plugin + form name.
		static $dk_results_cache = array();

		$field_duplicate_block_days = isset( $form_config['field_duplicate_block_days'] )
			? absint( $form_config['field_duplicate_block_days'] )
			: 0;

		$cache_key = $form_plugin . '|' . $form_name . '|' . $field_duplicate_block_days . '|' . ( $checked_cookie ? 'cookie' : 'nocookie' ) . '|' . md5( $form_cookie );

		// Load stored submissions only once per plugin + form during this request.
		if ( ! isset( $dk_results_cache[ $cache_key ] ) ) {

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required duplicate check query on plugin-owned table.
			$sql = "SELECT form_value, form_cookie
				FROM " . esc_sql( $table_name ) . "
				WHERE form_plugin = %s
				AND form_name = %s";

			$params = array(
				$form_plugin,
				$form_name,
			);

			if ( true === $checked_cookie && $form_cookie !== 'NULL' && $form_cookie !== '' ) {
				$sql .= " AND form_cookie = %s";
				$params[] = $form_cookie;
			} elseif ( $field_duplicate_block_days > 0 ) {
				$sql .= " AND form_date >= DATE_SUB(NOW(), INTERVAL %d DAY)";
				$params[] = $field_duplicate_block_days;
			}

			$sql .= " ORDER BY form_id DESC";

			$results = $wpdb->get_results(
				$wpdb->prepare( $sql, $params )
			);

			// Always store an array in cache, even if the query fails or returns nothing.
			$dk_results_cache[ $cache_key ] = is_array( $results ) ? $results : array();
		}

		// Loop through previous submissions for this plugin + form.
		foreach ( $dk_results_cache[ $cache_key ] as $row ) {

			// Stored form values may be serialized arrays.
			$form_data = maybe_unserialize( $row->form_value );

			// Check if this database row contains the same field key and value.
			if ( self::row_has_duplicate_value( $form_data, $key, $value ) ) {

				// If cookie protection is enabled, duplicates are user-specific.
				// The same value is blocked only when it belongs to the same cookie.
				if ( true === $checked_cookie ) {
					return ( $row->form_cookie === $form_cookie );
				}

				// Without cookie protection, any matching value is considered a duplicate.
				return true;
			}
		}

		// No matching value found in previous submissions.
		return false;
	}

	/**
	 * Checks whether a stored submission row contains the submitted field key and value.
	 *
	 * Supports two stored data formats:
	 * 1. Associative array: field_id => value
	 * 2. Named input list: array( array( 'name' => ..., 'value' => ... ) )
	 */
	private static function row_has_duplicate_value( $form_data, string $key, $value ): bool {

		// Stored form data must be an array to be checked.
		if ( ! is_array( $form_data ) ) {
			return false;
		}

		// Format 1: associative array, for example:
		// array( 'your-email' => 'test@example.com' )
		if ( isset( $form_data[ $key ] ) ) {
			return self::values_match_case_insensitive( $form_data[ $key ], $value );
		}

		// Format 2: named value list, for example:
		// array( array( 'name' => 'your-email', 'value' => 'test@example.com' ) )
		if ( isset( $form_data[0]['name'] ) ) {
			foreach ( $form_data as $input ) {

				// Skip invalid input items.
				if ( ! isset( $input['name'], $input['value'] ) ) {
					continue;
				}

				// Only compare the stored input that matches the submitted field key.
				if ( $input['name'] !== $key ) {
					continue;
				}

				return self::values_match_case_insensitive( $input['value'], $value );
			}
		}

		// Field key was not found in this stored row.
		return false;
	}

	/**
	 * Compares two values in a case-insensitive way.
	 *
	 * Supports string-to-string comparison and array-to-array comparison.
	 */
	public static function values_match_case_insensitive( $var1, $var2 ): bool {

		// Compare arrays after converting all values to lowercase.
		if ( is_array( $var1 ) && is_array( $var2 ) ) {
			$var1 = array_map( 'strtolower', $var1 );
			$var2 = array_map( 'strtolower', $var2 );

			return $var1 === $var2;
		}

		// Compare scalar values as lowercase strings.
		if ( ! is_array( $var1 ) && ! is_array( $var2 ) ) {
			return strtolower( (string) $var1 ) === strtolower( (string) $var2 );
		}

		// Different data types are not considered equal.
		return false;
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