<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DuplicateKiller_Form_Normalizer {

	public static function cf7( $contact_form ): array {
		$field_keys = array();

		if ( is_object( $contact_form ) && method_exists( $contact_form, 'scan_form_tags' ) ) {
			$tags = $contact_form->scan_form_tags();

			foreach ( $tags as $tag ) {
				if ( empty( $tag->name ) ) {
					continue;
				}

				$field_keys[] = (string) $tag->name;
			}
		}

		return array(
			'plugin'     => 'CF7',
			'form_id'    => is_object( $contact_form ) && method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0,
			'form_name'  => is_object( $contact_form ) && method_exists( $contact_form, 'title' ) ? (string) $contact_form->title() : '',
			'field_keys' => array_values( array_unique( $field_keys ) ),
		);
	}
	
	public static function forminator( $form_id, $field_data_array ): array {
		$field_keys = array();

		if ( is_array( $field_data_array ) ) {
			foreach ( $field_data_array as $field ) {
				if ( empty( $field['name'] ) ) {
					continue;
				}

				$field_keys[] = (string) $field['name'];
			}
		}

		return array(
			'plugin'     => 'Forminator',
			'form_id'    => (int) $form_id,
			'form_name'  => get_the_title( $form_id ),
			'field_keys' => array_values( array_unique( $field_keys ) ),
		);
	}

	public static function forminator_data( $field_data_array ): array {
		$data = array();

		if ( ! is_array( $field_data_array ) ) {
			return $data;
		}

		foreach ( $field_data_array as $field ) {
			if ( empty( $field['name'] ) ) {
				continue;
			}

			$field_name  = (string) $field['name'];
			$field_value = $field['value'] ?? '';

			$data[ $field_name ] = $field_value;
		}

		return $data;
	}

	public static function forminator_storage_fields( $field_data_array ): array {
		$storage_fields = array();

		if ( ! is_array( $field_data_array ) ) {
			return $storage_fields;
		}

		foreach ( $field_data_array as $field ) {
			$name  = isset( $field['name'] ) ? (string) $field['name'] : '';
			$value = $field['value'] ?? null;

			if ( '' === $name ) {
				continue;
			}

			if ( is_array( $value ) && isset( $value['file'] ) && is_array( $value['file'] ) && ! empty( $value['file'] ) ) {
				$file = $value['file'];

				$file_name = $file['file_name'] ?? $file['filename'] ?? $file['name'] ?? '';
				$file_url_raw = $file['file_url'] ?? $file['url'] ?? '';

				$file_url = is_array( $file_url_raw )
					? array_values( array_filter( $file_url_raw ) )
					: $file_url_raw;

				$storage_fields[] = array(
					'name'  => $name,
					'value' => array(
						'file_name' => is_string( $file_name ) ? $file_name : '',
						'file_url'  => $file_url,
					),
				);

				continue;
			}

			$storage_fields[] = array(
				'name'  => $name,
				'value' => $value,
			);
		}

		return $storage_fields;
	}
	public static function wpforms( array $form_data, array $fields = array() ): array {
		$field_keys = array();

		foreach ( $fields as $field ) {
			if ( empty( $field['name'] ) ) {
				continue;
			}

			$field_keys[] = (string) $field['name'];
		}

		return array(
			'plugin'     => 'WPForms',
			'form_id'    => isset( $form_data['id'] ) ? (int) $form_data['id'] : 0,
			'form_name'  => isset( $form_data['settings']['form_title'] ) ? (string) $form_data['settings']['form_title'] : '',
			'field_keys' => array_values( array_unique( $field_keys ) ),
		);
	}

	public static function wpforms_data( array $fields = array() ): array {
		$data = array();

		foreach ( $fields as $field ) {
			if ( empty( $field['name'] ) ) {
				continue;
			}

			$field_name  = (string) $field['name'];
			$field_value = $field['value'] ?? '';

			$data[ $field_name ] = $field_value;
		}

		return $data;
	}

	public static function wpforms_storage_fields( array $fields = array() ): array {
		$storage_fields = array();

		foreach ( $fields as $field ) {
			if ( empty( $field['name'] ) ) {
				continue;
			}

			$storage_fields[] = array(
				'name'  => (string) $field['name'],
				'value' => $field['value'] ?? '',
			);
		}

		return $storage_fields;
	}

	public static function wpforms_field_id_map( array $fields = array() ): array {
		$map = array();

		foreach ( $fields as $field ) {
			if ( empty( $field['name'] ) || ! isset( $field['id'] ) ) {
				continue;
			}

			$map[ (string) $field['name'] ] = $field['id'];
		}

		return $map;
	}
	
	public static function breakdance( array $extra, array $form, array $settings ): array {
		$post_id = (int) ( $extra['postId'] ?? 0 );
		$node_id = (int) ( $extra['formId'] ?? 0 );

		$bd_form   = $settings['form'] ?? array();
		$base_name = trim( (string) ( $bd_form['form_name'] ?? '' ) );

		if ( '' === $base_name && $post_id > 0 ) {
			$post      = get_post( $post_id );
			$base_name = $post ? $post->post_title : 'Breakdance Form';
		}

		$db_form_name        = $base_name . '.' . $post_id . '.' . $node_id;
		$legacy_db_form_name = $base_name . '.' . $node_id;

		$field_keys   = array();
		$field_values = array();
		$field_labels = array();

		foreach ( $form as $field ) {
			if ( ! function_exists( '\Breakdance\Forms\getIdFromField' ) ) {
				continue;
			}

			$field_id = \Breakdance\Forms\getIdFromField( $field );

			if ( '' === $field_id || null === $field_id ) {
				continue;
			}

			$field_id = (string) $field_id;

			$field_keys[]                = $field_id;
			$field_values[ $field_id ]   = $field['value'] ?? '';
			$field_labels[ $field_id ]   = isset( $field['label'] ) ? (string) $field['label'] : $field_id;
		}

		return array(
			'plugin'              => 'breakdance',
			'form_id'             => $node_id,
			'post_id'             => $post_id,
			'base_name'           => $base_name,
			'form_name'           => $db_form_name,
			'legacy_form_name'    => $legacy_db_form_name,
			'form_names_to_check' => array_values( array_unique( array_filter( array(
				$db_form_name,
				$legacy_db_form_name,
			) ) ) ),
			'field_keys'          => array_values( array_unique( $field_keys ) ),
			'field_values'        => $field_values,
			'field_labels'        => $field_labels,
			'storage_payload'     => $extra['fields'] ?? array(),
		);
	}
	public static function elementor( $record ): array {
		$post_id = (int) ( $record->get_form_settings( 'post_id' ) ?? 0 );

		$base_form_name = trim( (string) ( $record->get_form_settings( 'form_name' ) ?? '' ) );

		$node_id = (string) ( $record->get_form_settings( 'id' ) ?? '' );

		if ( '' === $node_id ) {
			$node_id = (string) ( $record->get_form_settings( 'form_id' ) ?? '' );
		}

		if ( '' === $base_form_name && $post_id > 0 ) {
			$post           = get_post( $post_id );
			$base_form_name = $post ? $post->post_title : 'Elementor Form';
		}

		$field_keys = array();
		$fields     = $record->get( 'fields' );

		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		foreach ( $fields as $key => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field_id = (string) ( $field['id'] ?? $key );

			if ( '' === $field_id ) {
				continue;
			}

			$field_keys[] = $field_id;
		}

		$form_name = $base_form_name . '.' . $node_id;
		$group_key = $base_form_name . '.__group__';

		return array(
			'plugin'         => 'elementor',
			'post_id'        => $post_id,
			'form_id'        => $node_id,
			'base_form_name' => $base_form_name,
			'form_name'      => $form_name,
			'group_key'      => $group_key,
			'field_keys'     => array_values( array_unique( $field_keys ) ),
		);
	}

	public static function elementor_data( $record ): array {
		$data   = array();
		$fields = $record->get( 'fields' );

		if ( ! is_array( $fields ) ) {
			return $data;
		}

		foreach ( $fields as $key => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field_id = (string) ( $field['id'] ?? $key );
			$value    = $field['value'] ?? '';

			if ( is_array( $value ) ) {
				$value = implode( ' ', array_map( 'strval', $value ) );
			}

			$data[ $field_id ] = sanitize_text_field( (string) $value );
		}

		return $data;
	}
	public static function formidable( array $values ): array {
		$form_id  = ! empty( $values['form_id'] ) ? (int) $values['form_id'] : 0;
		$form_key = ! empty( $values['form_key'] ) ? trim( (string) $values['form_key'] ) : '';

		$field_keys = array();

		if ( ! empty( $values['item_meta'] ) && is_array( $values['item_meta'] ) ) {
			foreach ( $values['item_meta'] as $field_id => $value ) {
				if ( ! is_numeric( $field_id ) ) {
					continue;
				}

				$field_keys[] = (string) (int) $field_id;
			}
		}

		return array(
			'plugin'      => 'Formidable',
			'form_id'     => $form_id,
			'form_key'    => $form_key,
			'option_key'  => $form_key . '.' . $form_id,
			'form_name'   => $form_key . '.' . $form_id,
			'field_keys'  => array_values( array_unique( $field_keys ) ),
		);
	}

	public static function formidable_data( array $values ): array {
		$data = array();

		$posted = ! empty( $values['item_meta'] ) && is_array( $values['item_meta'] )
			? $values['item_meta']
			: array();

		foreach ( $posted as $field_id => $value ) {
			if ( ! is_numeric( $field_id ) ) {
				continue;
			}

			$field_id = (string) (int) $field_id;

			if ( is_array( $value ) ) {
				$value = reset( $value );
			}

			$value = is_string( $value ) ? wp_unslash( $value ) : $value;
			$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';

			$data[ $field_id ] = $value;
		}

		return $data;
	}
	public static function ninjaforms( array $form_data ): array {
		$form_id = 0;

		if ( isset( $form_data['id'] ) ) {
			$form_id = (int) $form_data['id'];
		} elseif ( isset( $form_data['form_id'] ) ) {
			$form_id = (int) $form_data['form_id'];
		}

		$settings = isset( $form_data['settings'] ) && is_array( $form_data['settings'] )
			? $form_data['settings']
			: array();

		$form_key = ! empty( $settings['key'] ) ? trim( (string) $settings['key'] ) : '';
		$title    = ! empty( $settings['title'] ) ? trim( (string) $settings['title'] ) : '';

		if ( '' === $form_key ) {
			$form_key = sanitize_key( '' !== $title ? $title : ( 'form-' . $form_id ) );
		}

		$field_keys = array();

		if ( ! empty( $form_data['fields'] ) && is_array( $form_data['fields'] ) ) {
			foreach ( $form_data['fields'] as $key => $field ) {
				$field_id = 0;

				if ( is_numeric( $key ) ) {
					$field_id = (int) $key;
				} elseif ( is_array( $field ) && isset( $field['id'] ) && is_numeric( $field['id'] ) ) {
					$field_id = (int) $field['id'];
				}

				if ( $field_id > 0 ) {
					$field_keys[] = (string) $field_id;
				}
			}
		}

		return array(
			'plugin'     => 'NinjaForms',
			'form_id'    => $form_id,
			'form_key'   => $form_key,
			'option_key' => $form_key . '.' . $form_id,
			'form_name'  => $form_key . '.' . $form_id,
			'field_keys' => array_values( array_unique( $field_keys ) ),
		);
	}

	public static function ninjaforms_data( array $form_data ): array {
		$data   = array();
		$fields = ! empty( $form_data['fields'] ) && is_array( $form_data['fields'] )
			? $form_data['fields']
			: array();

		foreach ( $fields as $key => $field ) {
			$field_id = 0;

			if ( is_numeric( $key ) ) {
				$field_id = (int) $key;
			} elseif ( is_array( $field ) && isset( $field['id'] ) && is_numeric( $field['id'] ) ) {
				$field_id = (int) $field['id'];
			}

			if ( $field_id <= 0 ) {
				continue;
			}

			$value = '';

			if ( is_array( $field ) && array_key_exists( 'value', $field ) ) {
				$value = $field['value'];
			}

			if ( is_array( $value ) ) {
				$value = reset( $value );
			}

			$value = is_string( $value ) ? wp_unslash( $value ) : $value;
			$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';

			$data[ (string) $field_id ] = $value;
		}

		return $data;
	}
}