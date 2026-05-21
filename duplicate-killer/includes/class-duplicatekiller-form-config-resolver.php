<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DuplicateKiller_Form_Config_Resolver {

	public static function resolve( array $plugin_options, array $current_form ) {
		$form_id    = isset( $current_form['form_id'] ) ? (int) $current_form['form_id'] : 0;
		$form_name  = isset( $current_form['form_name'] ) ? (string) $current_form['form_name'] : '';
		$field_keys = ! empty( $current_form['field_keys'] ) && is_array( $current_form['field_keys'] )
			? $current_form['field_keys']
			: array();

		if ( '' === $form_name || empty( $plugin_options[ $form_name ] ) || ! is_array( $plugin_options[ $form_name ] ) ) {
			return false;
		}

		$form_config = $plugin_options[ $form_name ];

		if (
			$form_id > 0
			&& ! empty( $form_config['form_id'] )
			&& (int) $form_config['form_id'] !== $form_id
		) {
			return false;
		}

		$reserved_keys = array(
			'form_id',
			'error_message',
			'field_duplicate_block_days',
			'error_message_limit_ip_option',
			'user_ip',
			'user_ip_days',
			'cookie_option',
			'cookie_option_days',
			'cross_form_option',
		);

		$enabled_fields = array();

		foreach ( $form_config as $config_key => $enabled ) {
			if ( in_array( $config_key, $reserved_keys, true ) ) {
				continue;
			}

			if ( false !== strpos( (string) $config_key, '_ck' ) ) {
				continue;
			}

			if ( empty( $enabled ) || (string) $enabled !== '1' ) {
				continue;
			}

			if ( ! in_array( $config_key, $field_keys, true ) ) {
				continue;
			}

			$enabled_fields[] = $config_key;
		}

		return array(
			'form_id'        => $form_id,
			'form_name'      => $form_name,
			'form_config'    => $form_config,
			'enabled_fields' => $enabled_fields,
			'has_fields'     => ! empty( $enabled_fields ),
		);
	}
	public static function resolve_by_form_id( array $plugin_options, array $current_form ) {
		$form_id = isset( $current_form['form_id'] ) ? (int) $current_form['form_id'] : 0;

		if ( $form_id <= 0 ) {
			return false;
		}

		foreach ( $plugin_options as $option_key => $form_config ) {
			if ( ! is_array( $form_config ) ) {
				continue;
			}

			if ( empty( $form_config['form_id'] ) ) {
				continue;
			}

			if ( (int) $form_config['form_id'] !== $form_id ) {
				continue;
			}

			$field_keys = ! empty( $current_form['field_keys'] ) && is_array( $current_form['field_keys'] )
				? $current_form['field_keys']
				: array();

			$reserved_keys = array(
				'form_id',
				'error_message',
				'field_duplicate_block_days',
				'error_message_limit_ip_option',
				'user_ip',
				'user_ip_days',
				'cookie_option',
				'cookie_option_days',
				'cross_form_option',
			);

			$enabled_fields = array();

			foreach ( $form_config as $config_key => $enabled ) {
				if ( in_array( $config_key, $reserved_keys, true ) ) {
					continue;
				}

				if ( false !== strpos( (string) $config_key, '_ck' ) ) {
					continue;
				}

				if ( empty( $enabled ) || (string) $enabled !== '1' ) {
					continue;
				}

				if ( ! in_array( $config_key, $field_keys, true ) ) {
					continue;
				}

				$enabled_fields[] = $config_key;
			}

			return array(
				'form_id'        => $form_id,
				'form_name'      => isset( $current_form['form_name'] ) ? (string) $current_form['form_name'] : '',
				'option_key'     => (string) $option_key,
				'form_config'    => $form_config,
				'enabled_fields' => $enabled_fields,
				'has_fields'     => ! empty( $enabled_fields ),
			);
		}

		return false;
	}
	public static function resolve_elementor( array $plugin_options, array $current_form ) {
		$form_id   = isset( $current_form['form_id'] ) ? (string) $current_form['form_id'] : '';
		$form_name = isset( $current_form['form_name'] ) ? (string) $current_form['form_name'] : '';
		$group_key = isset( $current_form['group_key'] ) ? (string) $current_form['group_key'] : '';

		$group_mode = (int) get_option( 'duplicateKiller_elementor_group_mode', 0 );

		$config_key  = '';
		$form_config = array();

		if (
			1 === $group_mode
			&& '' !== $group_key
			&& isset( $plugin_options[ $group_key ] )
			&& is_array( $plugin_options[ $group_key ] )
		) {
			$config_key  = $group_key;
			$form_config = $plugin_options[ $group_key ];
		} elseif (
			'' !== $form_name
			&& isset( $plugin_options[ $form_name ] )
			&& is_array( $plugin_options[ $form_name ] )
		) {
			$config_key  = $form_name;
			$form_config = $plugin_options[ $form_name ];
		} else {
			foreach ( $plugin_options as $option_key => $option_value ) {
				if ( ! is_array( $option_value ) ) {
					continue;
				}

				if ( empty( $option_value['form_id'] ) ) {
					continue;
				}

				if ( (string) $option_value['form_id'] !== $form_id ) {
					continue;
				}

				$config_key  = (string) $option_key;
				$form_config = $option_value;
				break;
			}
		}

		if ( '' === $config_key || empty( $form_config ) ) {
			return false;
		}

		$field_keys = ! empty( $current_form['field_keys'] ) && is_array( $current_form['field_keys'] )
			? $current_form['field_keys']
			: array();

		$reserved_keys = array(
			'form_id',
			'error_message',
			'field_duplicate_block_days',
			'error_message_limit_ip_option',
			'user_ip',
			'user_ip_days',
			'cookie_option',
			'cookie_option_days',
			'cross_form_option',
		);

		$enabled_fields = array();

		foreach ( $form_config as $config_field_key => $enabled ) {
			if ( in_array( $config_field_key, $reserved_keys, true ) ) {
				continue;
			}

			if ( false !== strpos( (string) $config_field_key, '_ck' ) ) {
				continue;
			}

			if ( empty( $enabled ) || (string) $enabled !== '1' ) {
				continue;
			}

			if ( ! in_array( $config_field_key, $field_keys, true ) ) {
				continue;
			}

			$enabled_fields[] = $config_field_key;
		}

		return array(
			'form_id'        => $form_id,
			'form_name'      => $config_key,
			'form_config'    => $form_config,
			'enabled_fields' => $enabled_fields,
			'has_fields'     => ! empty( $enabled_fields ),
			'group_mode'     => $group_mode,
			'group_key'      => $group_key,
		);
	}
	public static function resolve_formidable( array $plugin_options, array $current_form ) {
		$option_key = isset( $current_form['option_key'] ) ? (string) $current_form['option_key'] : '';

		if ( '' === $option_key || empty( $plugin_options[ $option_key ] ) || ! is_array( $plugin_options[ $option_key ] ) ) {
			return false;
		}

		$form_config = $plugin_options[ $option_key ];

		if ( empty( $form_config['form_id'] ) ) {
			return false;
		}

		$field_keys = ! empty( $current_form['field_keys'] ) && is_array( $current_form['field_keys'] )
			? $current_form['field_keys']
			: array();

		$reserved_keys = array(
			'form_id',
			'error_message',
			'field_duplicate_block_days',
			'error_message_limit_ip_option',
			'user_ip',
			'user_ip_days',
			'cookie_option',
			'cookie_option_days',
			'cross_form_option',
		);

		$enabled_fields = array();

		foreach ( $form_config as $config_key => $enabled ) {
			if ( in_array( $config_key, $reserved_keys, true ) ) {
				continue;
			}

			if ( false !== strpos( (string) $config_key, '_ck' ) ) {
				continue;
			}

			if ( is_array( $enabled ) ) {
				continue;
			}

			if ( empty( $enabled ) || (string) $enabled !== '1' ) {
				continue;
			}

			if ( ! in_array( (string) $config_key, $field_keys, true ) ) {
				continue;
			}

			$enabled_fields[] = (string) $config_key;
		}

		return array(
			'form_id'        => isset( $current_form['form_id'] ) ? (int) $current_form['form_id'] : 0,
			'form_name'      => (string) $form_config['form_id'],
			'option_key'     => $option_key,
			'form_config'    => $form_config,
			'enabled_fields' => $enabled_fields,
			'has_fields'     => ! empty( $enabled_fields ),
		);
	}
	public static function resolve_ninjaforms( array $plugin_options, array $current_form ) {
		$option_key = isset( $current_form['option_key'] ) ? (string) $current_form['option_key'] : '';

		if ( '' === $option_key || empty( $plugin_options[ $option_key ] ) || ! is_array( $plugin_options[ $option_key ] ) ) {
			return false;
		}

		$form_config = $plugin_options[ $option_key ];

		if ( empty( $form_config['form_id'] ) ) {
			return false;
		}

		$field_keys = ! empty( $current_form['field_keys'] ) && is_array( $current_form['field_keys'] )
			? $current_form['field_keys']
			: array();

		$reserved_keys = array(
			'form_id',
			'error_message',
			'field_duplicate_block_days',
			'error_message_limit_ip_option',
			'user_ip',
			'user_ip_days',
			'cookie_option',
			'cookie_option_days',
			'cross_form_option',
		);

		$enabled_fields = array();

		foreach ( $form_config as $config_key => $enabled ) {
			if ( in_array( $config_key, $reserved_keys, true ) ) {
				continue;
			}

			if ( false !== strpos( (string) $config_key, '_ck' ) ) {
				continue;
			}

			if ( is_array( $enabled ) ) {
				continue;
			}

			if ( empty( $enabled ) || (string) $enabled !== '1' ) {
				continue;
			}

			if ( ! in_array( (string) $config_key, $field_keys, true ) ) {
				continue;
			}

			$enabled_fields[] = (string) $config_key;
		}

		return array(
			'form_id'        => isset( $current_form['form_id'] ) ? (int) $current_form['form_id'] : 0,
			'form_name'      => (string) $form_config['form_id'],
			'option_key'     => $option_key,
			'form_config'    => $form_config,
			'enabled_fields' => $enabled_fields,
			'has_fields'     => ! empty( $enabled_fields ),
		);
	}
}