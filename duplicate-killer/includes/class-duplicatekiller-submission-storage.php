<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DuplicateKiller_Submission_Storage {

	public static function save(
		string $form_plugin,
		string $form_name,
		array $data,
		string $form_cookie = 'NULL',
		bool $should_save_submission = false,
		bool $ip_limit_enabled = false,
		array $files = array(),
		array $storage_options = array(),
		array $debug_context = array()
	): array {
		global $wpdb;

		$result = array(
			'saved'         => false,
			'insert_id'     => 0,
			'form_ip'       => 'NULL',
			'data'          => $data,
			'wpdb_error'    => '',
			'files_saved'   => array(),
			'should_save'   => false,
		);

		$result['should_save'] = $should_save_submission;

		if ( ! $should_save_submission ) {
			self::log( $form_plugin, 'save_skipped', array_merge(
				$debug_context,
				array(
					'form_name'              => $form_name,
					'should_save_submission' => 0,
				)
			) );

			return $result;
		}

		$form_ip = 'NULL';

		if ( $ip_limit_enabled && class_exists( 'DuplicateKiller_IP_Limit_Checker' ) ) {
			$form_ip = DuplicateKiller_IP_Limit_Checker::get_user_ip();
		}

		$result['form_ip'] = $form_ip;

		self::log( $form_plugin, 'save_start', array_merge(
			$debug_context,
			array(
				'form_name'   => $form_name,
				'form_ip'     => $form_ip,
				'posted_data' => $data,
				'files'       => $files,
			)
		) );

		if ( ! empty( $files ) && self::should_save_files( $storage_options ) ) {
			$file_result = self::save_files_locally(
				$form_plugin,
				$form_name,
				$data,
				$files,
				$debug_context
			);

			$data                  = $file_result['data'];
			$result['data']        = $data;
			$result['files_saved'] = $file_result['files_saved'];
		}

		$table_name = $wpdb->prefix . 'dk_forms_duplicate';

		$form_value = serialize( $data );
		$form_date  = current_time( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Required for custom plugin table insert.
		$insert_result = $wpdb->insert(
			$table_name,
			array(
				'form_plugin' => $form_plugin,
				'form_name'   => $form_name,
				'form_value'  => $form_value,
				'form_cookie' => $form_cookie,
				'form_date'   => $form_date,
				'form_ip'     => $form_ip,
			)
		);

		$insert_ok = empty( $wpdb->last_error ) && false !== $insert_result;

		$result['saved']      = $insert_ok;
		$result['insert_id']  = $insert_ok ? (int) $wpdb->insert_id : 0;
		$result['wpdb_error'] = $wpdb->last_error;

		self::log( $form_plugin, 'save_after_insert', array_merge(
			$debug_context,
			array(
				'form_name'       => $form_name,
				'insert_ok'       => $insert_ok ? 1 : 0,
				'wpdb_last_error' => $wpdb->last_error,
				'insert_id'       => $wpdb->insert_id,
				'table_name'      => $table_name,
			)
		) );

		return $result;
	}

	private static function should_save_files( array $storage_options ): bool {
		if ( ! array_key_exists( 'save_files', $storage_options ) ) {
			return false;
		}

		return (string) $storage_options['save_files'] === '1';
	}

	private static function save_files_locally(
		string $form_plugin,
		string $form_name,
		array $data,
		array $files,
		array $debug_context = array()
	): array {
		$result = array(
			'data'        => $data,
			'files_saved' => array(),
		);

		$upload_dir = wp_upload_dir();

		$storage_folder     = trailingslashit( $upload_dir['basedir'] ) . 'duplicate-killer';
		$storage_folder_url = trailingslashit( $upload_dir['baseurl'] ) . 'duplicate-killer';

		if ( ! file_exists( $storage_folder ) ) {
			wp_mkdir_p( $storage_folder );
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			self::log( $form_plugin, 'file_save_skipped_no_filesystem', array_merge(
				$debug_context,
				array(
					'form_name' => $form_name,
				)
			) );

			return $result;
		}

		$random_number = uniqid( (string) time(), true );

		foreach ( $files as $file_key => $file ) {
			$file = is_array( $file ) ? reset( $file ) : $file;

			if ( empty( $file ) ) {
				continue;
			}

			$dest_name = sanitize_file_name( $file_key . '-' . $random_number . '-' . basename( $file ) );
			$file_path = trailingslashit( $storage_folder ) . $dest_name;
			$file_url  = trailingslashit( $storage_folder_url ) . rawurlencode( $dest_name );

			if ( ! file_exists( $file ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading validated temporary upload file from form plugin.
			$contents = file_get_contents( $file );

			if ( false === $contents ) {
				continue;
			}

			$wp_filesystem->put_contents( $file_path, $contents, FS_CHMOD_FILE );

			if ( array_key_exists( $file_key, $data ) ) {
				$data[ $file_key ] = $file_url;
			}

			$result['files_saved'][] = array(
				'file_key'  => $file_key,
				'file_path' => $file_path,
				'file_url'  => $file_url,
			);

			self::log( $form_plugin, 'file_saved_locally', array_merge(
				$debug_context,
				array(
					'form_name' => $form_name,
					'file_key'  => $file_key,
					'file_path' => $file_path,
					'file_url'  => $file_url,
				)
			) );
		}

		$result['data'] = $data;

		return $result;
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