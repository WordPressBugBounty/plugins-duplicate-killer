<?php
defined( 'ABSPATH' ) || exit;

class DuplicateKiller_Submission_Value_Renderer {

	public function render( string $plugin, string $form_name, $form_value ): string {
		$form_value = maybe_unserialize( $form_value );

		if ( 'CF7' === $plugin || 'elementor' === $plugin ) {
			return $this->render_cf7( $form_value );
		}

		if ( 'Forminator' === $plugin ) {
			return $this->render_forminator( $form_value );
		}

		if ( 'WPForms' === $plugin ) {
			return $this->render_wpforms( $form_value );
		}

		if ( 'breakdance' === $plugin ) {
			return $this->render_breakdance( $form_value );
		}

		if ( 'Formidable' === $plugin ) {
			return $this->render_formidable( $form_value, $form_name );
		}

		if ( 'NinjaForms' === $plugin || 'Ninja Forms' === $plugin ) {
			return $this->render_ninjaforms( $form_value, $form_name );
		}

		if ( 'WooCommerce' === $plugin ) {
			return $this->render_woocommerce( $form_value );
		}

		return is_string( $form_value )
			? esc_html( $form_value )
			: esc_html( (string) wp_json_encode( $form_value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	}
	public function render_preview( string $plugin, string $form_name, $form_value ): string {
		$form_value = maybe_unserialize( $form_value );

		if ( ! is_array( $form_value ) || empty( $form_value ) ) {
			return '<span class="dk-db-muted">No preview</span>';
		}
		$flat = $this->flatten_preview_values( $form_value, $plugin, $form_name );

		if ( empty( $flat ) ) {
			return '<span class="dk-db-muted">No preview</span>';
		}

		$primary_key   = '';
		$primary_value = '';

		foreach ( $flat as $key => $item ) {
			$value = isset( $item['value'] ) ? (string) $item['value'] : '';

			if ( is_email( $value ) || false !== stripos( $key, 'email' ) ) {
				$primary_key   = $key;
				$primary_value = $value;
				break;
			}
		}

		if ( '' === $primary_value ) {
			foreach ( $flat as $key => $item ) {
				$value = isset( $item['value'] ) ? (string) $item['value'] : '';
				$key_lc   = strtolower( remove_accents( (string) $key ) );
				$value_lc = preg_replace( '/\s+/', '', (string) $value );

				$looks_like_phone_key = (
					false !== strpos( $key_lc, 'phone' )
					|| false !== strpos( $key_lc, 'tel' )
					|| false !== strpos( $key_lc, 'telefon' )
					|| false !== strpos( $key_lc, 'mobile' )
					|| false !== strpos( $key_lc, 'mobil' )
					|| false !== strpos( $key_lc, 'number' )
					|| false !== strpos( $key_lc, 'numar' )
				);

				$looks_like_phone_value = (bool) preg_match( '/^\+?[0-9().\-]{7,18}$/', $value_lc );

				if ( $looks_like_phone_key || $looks_like_phone_value ) {
					$primary_key   = $key;
					$primary_value = $value;
					break;
				}
			}
		}

		if ( '' === $primary_value ) {
			$primary_key   = array_key_first( $flat );
			$primary_value = isset( $flat[ $primary_key ]['value'] ) ? (string) $flat[ $primary_key ]['value'] : '';
		}

		unset( $flat[ $primary_key ] );

		$out  = '<div class="dk-db-preview">';

		$out .= '<div class="dk-db-preview__primary">';
		$out .= '<strong>' . esc_html( $primary_value ) . '</strong>';
		$out .= '</div>';

		$preview_rows = array();

		$preview_rows[ $primary_key ] = isset( $flat[ $primary_key ] )
			? $flat[ $primary_key ]
			: array(
				'type'  => 'text',
				'label' => $primary_key,
				'value' => $primary_value,
				'url'   => '',
			);

		foreach ( $flat as $key => $item ) {
			$preview_rows[ $key ] = $item;
		}

		$total_rows = count( $preview_rows );
		$count      = 0;

		$out .= '<div class="dk-db-preview__rows">';

		foreach ( $preview_rows as $key => $item ) {
			if ( $count >= 5 ) {
				break;
			}

			$type  = isset( $item['type'] ) ? (string) $item['type'] : 'text';
			$value = isset( $item['value'] ) ? (string) $item['value'] : '';
			$url   = isset( $item['url'] ) ? (string) $item['url'] : '';

			$out .= '<span class="dk-db-preview__row dk-db-preview__row--' . esc_attr( $type ) . '">';
			$out .= '<em>' . esc_html( $key ) . ':</em>';

			if ( 'url' === $type && '' !== $url ) {
				$out .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $value ) . '</a>';
			} else {
				$out .= '<strong>' . esc_html( $value ) . '</strong>';
			}

			$out .= '</span>';

			$count++;
		}

		$remaining = $total_rows - $count;

		if ( $remaining > 0 ) {
			$out .= '<span class="dk-db-preview__more">+' . esc_html( (string) $remaining ) . ' more fields</span>';
		}

		$out .= '</div>';
		$out .= '</div>';

		return $out;
	}
	private function flatten_preview_values(
		array $form_value,
		string $plugin = '',
		string $form_name = ''
	): array {
		
		$flat = array();

		foreach ( $form_value as $key => $value ) {
			$label = (string) $key;
			$final = '';
			
			if (
				( 'NinjaForms' === $plugin || 'Ninja Forms' === $plugin )
				&& is_numeric( $key )
			) {
				$ninjaforms_page = get_option( 'NinjaForms_page', array() );

				if (
					isset( $ninjaforms_page[ $form_name ]['labels'][ (int) $key ] )
				) {
					$label = (string) $ninjaforms_page[ $form_name ]['labels'][ (int) $key ];
				}
			}

			if (
				'Formidable' === $plugin
				&& is_numeric( $key )
			) {
				$formidable_page = get_option( 'Formidable_page', array() );

				if (
					isset( $formidable_page[ $form_name ]['labels'][ (int) $key ] )
				) {
					$label = (string) $formidable_page[ $form_name ]['labels'][ (int) $key ];
				}
			}
			if ( is_array( $value ) ) {
				if ( isset( $value['name'], $value['value'] ) ) {
					$label = (string) $value['name'];
					$value = $value['value'];
				}
			}

			if ( is_array( $value ) ) {
				$final = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			} elseif ( is_scalar( $value ) ) {
				$final = trim( (string) $value );
			}

			if ( '' === $label || '' === $final ) {
				continue;
			}

			$flat[ $label ] = $this->normalize_preview_value( $label, $final );
		}

		return $flat;
	}
	private function normalize_preview_value( string $label, string $value ): array {
		$value = trim( $value );

		$result = array(
			'type'  => 'text',
			'label' => $label,
			'value' => $value,
			'url'   => '',
		);

		if ( '' === $value ) {
			return $result;
		}

		$label_lc = strtolower( remove_accents( $label ) );

		if (
			false !== strpos( $value, 'signature_data' )
			|| false !== strpos( $value, 'data:image' )
			|| false !== strpos( $label_lc, 'signature' )
			|| false !== strpos( $label_lc, 'semnatura' )
		) {
			$result['type']  = 'signature';
			$result['value'] = __( 'View in details', 'duplicate-killer' );

			return $result;
		}

		if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			$result['type'] = 'url';
			$result['url']  = $value;

			$name = basename( strtok( $value, '?' ) );

			$name = urldecode( trim( (string) $name ) );

			if ( '' === $name || false === strpos( $name, '.' ) ) {
				$name = $this->safe_substr( $value, 0, 38 ) . '...';
			}

			$result['value'] = $name;

			return $result;
		}

		if ( strlen( $value ) > 120 && $this->looks_like_json( $value ) ) {
			$result['type']  = 'data';
			$result['value'] = __( 'View in details', 'duplicate-killer' );

			return $result;
		}

		if ( strlen( $value ) > 90 ) {
			$result['value'] = $this->safe_substr( $value, 0, 87 ) . '...';
		}

		return $result;
	}
	
	private function looks_like_json( string $value ): bool {
		$value = trim( $value );

		if ( '' === $value ) {
			return false;
		}

		if ( ! in_array( $value[0], array( '{', '[' ), true ) ) {
			return false;
		}

		json_decode( $value, true );

		return JSON_ERROR_NONE === json_last_error();
	}
	private function render_modal_row( string $label, $value ): string {
		$label = trim( $label );

		if ( '' === $label ) {
			$label = __( 'Field', 'duplicate-killer' );
		}

		$rendered_value = $this->render_modal_value( $value );

		if ( '' === $rendered_value ) {
			$rendered_value = '<em>' . esc_html__( 'Empty value', 'duplicate-killer' ) . '</em>';
		}

		return '<p><strong>' . esc_html( $label ) . ':</strong> ' . $rendered_value . '</p>';
	}

	private function render_modal_value( $value ): string {
		if ( is_array( $value ) ) {
			$items = array();

			foreach ( $value as $item ) {
				$rendered = $this->render_modal_value( $item );

				if ( '' !== $rendered ) {
					$items[] = $rendered;
				}
			}

			return ! empty( $items ) ? implode( ', ', $items ) : '';
		}

		if ( is_object( $value ) ) {
			$value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( $this->dk_looks_like_signature_payload( $value ) ) {
			$sig = $this->dk_extract_signature_from_payload( $value );

			if ( ! empty( $sig['data_url'] ) ) {
				$meta = '';

				if ( ! empty( $sig['width'] ) && ! empty( $sig['height'] ) ) {
					$meta = ' <small style="opacity:.7;">(' . (int) $sig['width'] . '×' . (int) $sig['height'] . ')</small>';
				}

				return $meta . '<img src="' . esc_attr( $sig['data_url'] ) . '" style="max-width:220px;height:auto;display:block;margin-top:6px;border:1px solid #ddd;padding:6px;background:#fff;" alt="Signature" />';
			}

			return '<em>' . esc_html__( 'Signature data stored.', 'duplicate-killer' ) . '</em>';
		}
		
		if ( strlen( $value ) > 120 && $this->looks_like_json( $value ) ) {
			$decoded = json_decode( $value, true );

			if ( is_array( $decoded ) ) {
				return $this->render_modal_structured_value( $decoded );
			}

			return esc_html( $this->safe_substr( $value, 0, 500 ) . '...' );
		}

		if ( strlen( $value ) > 800 ) {
			return esc_html( $this->safe_substr( $value, 0, 800 ) . '...' );
		}
		if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			$text = $this->get_modal_link_text( $value );

			return '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $text ) . '</a>';
		}

		return esc_html( $value );
	}
	
	private function render_modal_structured_value( array $value ): string {
		$out = '<div class="dk-modal-structured-value">';

		foreach ( $value as $key => $item ) {
			$key = is_scalar( $key ) ? (string) $key : 'item';

			if ( is_array( $item ) || is_object( $item ) ) {
				$item = wp_json_encode( $item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}

			if ( ! is_scalar( $item ) ) {
				continue;
			}

			$item = trim( (string) $item );

			if ( '' === $item ) {
				continue;
			}

			if ( strlen( $item ) > 180 ) {
				$item = $this->safe_substr( $item, 0, 177 ) . '...';
			}

			$out .= '<span><em>' . esc_html( $key ) . ':</em> <strong>' . esc_html( $item ) . '</strong></span>';
		}

		$out .= '</div>';

		return $out;
	}
	private function get_modal_link_text( string $url ): string {
		$name = basename( strtok( $url, '?' ) );
		$name = urldecode( trim( (string) $name ) );

		if ( '' === $name || '/' === $name ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			$name = is_string( $host ) && '' !== $host ? $host : __( 'Link', 'duplicate-killer' );
		}

		if ( $this->safe_strlen( $name ) > 48 ) {
			$ext  = pathinfo( $name, PATHINFO_EXTENSION );
			$base = pathinfo( $name, PATHINFO_FILENAME );

			$name = '' !== $ext
				? $this->safe_substr( $base, 0, 36 ) . '….' . $ext
				: $this->safe_substr( $name, 0, 44 ) . '…';
		}

		return $name;
	}

	private function safe_substr( string $value, int $start, int $length ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, $start, $length );
		}

		return substr( $value, $start, $length );
	}

	private function safe_strlen( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value );
		}

		return strlen( $value );
	}

	private function render_cf7( $form_value ): string {
		$store = '';

		foreach ( (array) $form_value as $arr => $value ) {
			$store .= $this->render_modal_row( (string) $arr, $value );
		}

		return $store;
	}

	private function render_forminator( $form_value ): string {
		$store = '';

		foreach ( (array) $form_value as $arr => $value ) {
			$label = (string) $arr;
			$final = $value;

			if ( is_array( $value ) && isset( $value['name'] ) ) {
				$label = (string) $value['name'];
			}

			if ( is_array( $value ) && array_key_exists( 'value', $value ) ) {
				$final = $value['value'];
			}

			$store .= $this->render_modal_row( $label, $final );
		}

		return $store;
	}

	private function render_wpforms( $form_value ): string {
		$store = '';

		foreach ( (array) $form_value as $arr => $value ) {
			$label = (string) $arr;
			$final = $value;

			if ( is_array( $value ) && isset( $value['name'] ) ) {
				$label = (string) $value['name'];
			}

			if ( is_array( $value ) && array_key_exists( 'value', $value ) ) {
				$final = $value['value'];
			}

			$store .= $this->render_modal_row( $label, $final );
		}

		return $store;
	}

	private function render_breakdance( $form_value ): string {
		$store = '';

		if ( empty( $form_value ) ) {
			return $store;
		}

		$uploads = wp_get_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? rtrim( (string) $uploads['baseurl'], '/' ) : '';
		$basedir = isset( $uploads['basedir'] ) ? rtrim( (string) $uploads['basedir'], DIRECTORY_SEPARATOR ) : '';

		foreach ( (array) $form_value as $arr => $value ) {
			$key = (string) $arr;

			// Normalize nested arrays (e.g. multi-select).
			if ( is_array( $value ) ) {
				$flat = array();
				foreach ( $value as $row ) {
					if ( is_scalar( $row ) ) {
						$flat[] = (string) $row;
					} else {
						$flat[] = wp_json_encode( $row );
					}
				}
				$store .= '<p><strong>' . esc_html( $key ) . ':</strong> ' . esc_html( implode( ', ', $flat ) ) . '</p>';
				continue;
			}

			$val = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
			$val = trim( $val );

			// Breakdance uploads can be multiple signed URLs in a single string (comma-separated).
			if ( $val !== '' && strpos( $val, 'breakdance_download=' ) !== false ) {
				$parts = array_map( 'trim', explode( ',', $val ) );
				$links = array();

				foreach ( $parts as $one ) {
					if ( $one === '' || ! filter_var( $one, FILTER_VALIDATE_URL ) ) {
						continue;
					}

					$parsed = wp_parse_url( $one );
					$q      = array();
					if ( ! empty( $parsed['query'] ) ) {
						parse_str( $parsed['query'], $q );
					}

					$form_id       = isset( $q['formId'] ) ? (int) $q['formId'] : 0;
					$download_path = isset( $q['breakdance_download'] ) ? urldecode( (string) $q['breakdance_download'] ) : '';
					$download_path = '/' . ltrim( $download_path, '/' );

					// Link text = filename when possible.
					$link_text = 'Download file';
					if ( $download_path !== '/' ) {
						$file = basename( $download_path );
						if ( $file !== '' ) {
							$link_text = $file;
						}
					}

					// Try to resolve to a direct URL under uploads/breakdance/submissions/{formId}-*/YYYY/MM/file
					$href = $one; // fallback to signed url
					if ( $form_id > 0 && $download_path !== '/' && $baseurl !== '' && $basedir !== '' ) {
						$sub_path = str_replace( '/', DIRECTORY_SEPARATOR, $download_path );

						$pattern = $basedir
							. DIRECTORY_SEPARATOR . 'breakdance'
							. DIRECTORY_SEPARATOR . 'submissions'
							. DIRECTORY_SEPARATOR . $form_id . '-*'
							. $sub_path;

						$matches = glob( $pattern );
						if ( ! empty( $matches ) && is_array( $matches ) ) {
							$real_file = (string) $matches[0];

							// Absolute path -> URL relative to uploads.
							$rel = ltrim( str_replace( $basedir, '', $real_file ), DIRECTORY_SEPARATOR );
							$rel = str_replace( DIRECTORY_SEPARATOR, '/', $rel );

							$href = $baseurl . '/' . $rel;
						}
					}

					$links[] = '<a href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $link_text ) . '</a>';
				}

				if ( ! empty( $links ) ) {
					$store .= '<p><strong>' . esc_html( $key ) . ':</strong> ' . implode( ', ', $links ) . '</p>';
					continue;
				}
			}

			// Optional: any other URL becomes clickable.
			if ( $val !== '' && filter_var( $val, FILTER_VALIDATE_URL ) ) {
				$text = ( strlen( $val ) > 80 ) ? ( substr( $val, 0, 60 ) . '…' ) : $val;
				$link = '<a href="' . esc_url( $val ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $text ) . '</a>';
				$store .= '<p><strong>' . esc_html( $key ) . ':</strong> ' . $link . '</p>';
				continue;
			}

			// Plain text fallback.
			//$store .= esc_html( $key ) . ' - ' . esc_html( $val ) . '<br>';
			$store .= $this->render_modal_row( $key, $val );
		}

		return $store;
	}

	private function render_formidable( $form_value, string $form_name ): string {
		// Accept legacy stored values (string) safely
		$form_value = maybe_unserialize($form_value);
		$formidable_page = get_option( 'Formidable_page', array() );

		if ( ! is_array( $formidable_page ) ) {
			$formidable_page = array();
		}

		if (!is_array($form_value) || empty($form_value)) {
			return '';
		}

		// Fetch config and labels map (new structure: Formidable_page[$form_name]['labels'])
		$cfg = array();
		if (isset($formidable_page[$form_name]) && is_array($formidable_page[$form_name])) {
			$cfg = $formidable_page[$form_name];
		} else {
			// Fallback: sometimes DB has "contact-us.2" but option key might be different
			// Try to match by normalizing whitespace
			$normalized = trim((string)$form_name);
			if ($normalized !== $form_name && isset($formidable_page[$normalized]) && is_array($formidable_page[$normalized])) {
				$cfg = $formidable_page[$normalized];
			}
		}

		$labels = (isset($cfg['labels']) && is_array($cfg['labels'])) ? $cfg['labels'] : array();

		$out = '<div class="dk-form-values dk-form-values--formidable">';

		foreach ($form_value as $fid => $val) {

			// Formidable field IDs are numeric
			if (!is_numeric($fid)) {
				continue;
			}

			$fid = (int) $fid;
			if ($fid <= 0) {
				continue;
			}

			// Normalize value (arrays -> JSON, strings -> unslash + trim)
			if (is_array($val)) {
				$val = wp_json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			} else {
				$val = trim(wp_unslash((string) $val));
			}

			// Skip empty values
			if ($val === '') {
				continue;
			}

			// Resolve label by field ID (saved keys are usually ints, so $labels[$fid] works)
			$label = isset($labels[$fid]) ? (string) $labels[$fid] : ('Field ' . $fid);
			$label = trim($label);
			if ($label === '') {
				$label = 'Field ' . $fid;
			}

			$out .= $this->render_modal_row( $label, $val );
		}

		$out .= '</div>';

		return $out;
	}

	private function render_ninjaforms( $form_value, string $form_name ): string {
		$form_value = maybe_unserialize($form_value);
		$ninjaforms_page = get_option( 'NinjaForms_page', array() );

		if ( ! is_array( $ninjaforms_page ) ) {
			$ninjaforms_page = array();
		}

		if (!is_array($form_value) || empty($form_value)) {
			return '';
		}

		// Fetch config and labels map
		$cfg    = (isset($ninjaforms_page[$form_name]) && is_array($ninjaforms_page[$form_name])) ? $ninjaforms_page[$form_name] : [];
		$labels = (isset($cfg['labels']) && is_array($cfg['labels'])) ? $cfg['labels'] : [];

		$out = '<div class="dk-form-values dk-form-values--ninjaforms">';

		foreach ($form_value as $fid => $val) {

			// Ninja Forms field IDs are typically numeric, but be tolerant.
			$fid_key_int = is_numeric($fid) ? (int)$fid : null;

			// Normalize value
			if (is_array($val)) {
				$val = wp_json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			} else {
				$val = trim(wp_unslash((string) $val));
			}

			if ($val === '') {
				continue;
			}

			// Resolve label: prefer int key when possible (matches how you saved labels)
			if ($fid_key_int !== null && isset($labels[$fid_key_int])) {
				$label = (string) $labels[$fid_key_int];
			} elseif (isset($labels[$fid])) {
				$label = (string) $labels[$fid];
			} else {
				$label = 'Field ' . (string) $fid;
			}

			$label = trim($label);
			if ($label === '') {
				$label = 'Field ' . (string) $fid;
			}

			// Special: signature field (NF often stores JSON with signature_data as base64 data URL)
			if (is_string($val) && $val !== '' && $this->dk_looks_like_signature_payload($val)) {

				$sig = $this->dk_extract_signature_from_payload($val);

				if (!empty($sig['data_url'])) {

					$thumb = '<img src="' . esc_attr($sig['data_url']) . '" style="max-width:220px; height:auto; display:block; margin-top:6px; border:1px solid #ddd; padding:6px; background:#fff;" alt="Signature" />';

					$meta = '';
					if (!empty($sig['width']) && !empty($sig['height'])) {
						$meta = ' <small style="opacity:.7;">(' . (int) $sig['width'] . '×' . (int) $sig['height'] . ')</small>';
					}

					$out .= '<p><strong>' . esc_html($label) . ':</strong>' . $meta . $thumb . '</p>';
					continue;
				}

				// Fallback: if it’s signature-ish but we can’t parse, don’t dump the whole payload
				$out .= '<p><strong>' . esc_html($label) . ':</strong> <em>Signature data stored (hidden to keep table readable).</em></p>';
				continue;
			}

			// Default rendering
			$out .= $this->render_modal_row( $label, $val );
		}

		$out .= '</div>';

		return $out;
	}

	private function render_woocommerce( $form_value ): string {
		$form_value = maybe_unserialize( $form_value );

		if ( ! is_array( $form_value ) ) {
			return '';
		}

		$type = isset( $form_value['type'] ) ? (string) $form_value['type'] : '';
		if ( 'wc_checkout_duplicate' !== $type ) {
			return '<p><em>' . esc_html__( 'WooCommerce duplicate entry.', 'duplicate-killer' ) . '</em></p>';
		}

		$fingerprint = isset( $form_value['fingerprint'] ) ? (string) $form_value['fingerprint'] : '';
		$email       = isset( $form_value['email'] ) ? (string) $form_value['email'] : '';
		$total       = isset( $form_value['total'] ) ? (string) $form_value['total'] : '';
		$currency    = isset( $form_value['currency'] ) ? (string) $form_value['currency'] : '';
		$products    = isset( $form_value['products'] ) && is_array( $form_value['products'] ) ? $form_value['products'] : array();

		$fp_short = '';
		if ( $fingerprint !== '' ) {
			$fp_short = substr( preg_replace( '/[^a-f0-9]/i', '', $fingerprint ), 0, 12 );
		}

		$products = array_map( 'absint', $products );
		$products = array_filter( $products );

		$pro_url = admin_url( 'admin.php?page=duplicateKiller&tab=pro' );

		// Unique wrapper id (so multiple rows can toggle independently)
		$wrap_id = 'dk-pro-wc-' . wp_rand( 10000, 99999 );

		// ---- Extract extra PRO fields (safe defaults) ----
		$order_id = isset( $form_value['order_id'] ) ? absint( $form_value['order_id'] ) : 0;
		$source = isset( $form_value['mode'] ) ? sanitize_key( (string) $form_value['mode'] ) : '';
		$ts       = isset( $form_value['timestamp'] ) ? absint( $form_value['timestamp'] ) : 0;

		// ---- Small badges (no <span> needed, to keep your wp_kses allowlist unchanged) ----
		$badge = '';
		if ( $source !== '' ) {
			$label = ( 'blocks' === $source ) ? 'Blocks' : ( ( 'classic' === $source ) ? 'Classic' : ucfirst( $source ) );
			$badge = '<small style="display:inline-block;margin-left:8px;padding:2px 8px;border:1px solid #dcdcde;border-radius:999px;background:#f6f7f7;opacity:.9;">'
				. esc_html( $label )
				. '</small>';
		}

		$out  = '<div class="dk-wc-dup">';

		$out .= '<p style="margin:0 0 8px 0;">';
		$out .= '<strong>' . esc_html__( 'WooCommerce Duplicate Checkout', 'duplicate-killer' ) . '</strong>';
		$out .= $badge;
		$out .= '</p>';

		// Order link (enterprise)
		if ( $order_id > 0 ) {
			$order_edit = admin_url( 'post.php?post=' . $order_id . '&action=edit' );
			$out .= '<p><strong>' . esc_html__( 'Order:', 'duplicate-killer' ) . '</strong> ';
			$out .= '<a href="' . esc_url( $order_edit ) . '">' . esc_html( '#' . $order_id ) . '</a>';
			$out .= '</p>';
		}

		// Fingerprint (short + full in small)
		if ( $fp_short !== '' ) {
			$out .= '<p><strong>' . esc_html__( 'Fingerprint:', 'duplicate-killer' ) . '</strong> ' . esc_html( $fp_short );
			if ( $fingerprint !== '' ) {
				$out .= '<br><small style="opacity:.75;">' . esc_html( $fingerprint ) . '</small>';
			}
			$out .= '</p>';
		}

		if ( $email !== '' ) {
			$out .= '<p><strong>' . esc_html__( 'Email:', 'duplicate-killer' ) . '</strong> ' . esc_html( $email ) . '</p>';
		}

		if ( $total !== '' ) {
			$line = $total . ( $currency !== '' ? ' ' . $currency : '' );
			$out .= '<p><strong>' . esc_html__( 'Total:', 'duplicate-killer' ) . '</strong> ' . esc_html( $line ) . '</p>';
		}
		// PRO: show richer details when available (kept compact for readability).
		if ( ! empty( $form_value['mode'] ) ) {
			$out .= '<p><strong>' . esc_html__( 'Checkout:', 'duplicate-killer' ) . '</strong> ' . esc_html( (string) $form_value['mode'] ) . '</p>';
		}

		if ( ! empty( $form_value['order_id'] ) ) {
			$out .= '<p><strong>' . esc_html__( 'Order ID:', 'duplicate-killer' ) . '</strong> #' . esc_html( (string) absint( $form_value['order_id'] ) ) . '</p>';
		}

		if ( ! empty( $form_value['payment_method'] ) ) {
			$pm_id = (string) $form_value['payment_method'];
			$pm_label = $this->duplicateKiller_wc_gateway_label( $pm_id );
			$out .= '<p><strong>' . esc_html__( 'Payment:', 'duplicate-killer' ) . '</strong> ' . esc_html( $pm_label ) . '</p>';
		}

		if ( ! empty( $form_value['customer_id'] ) ) {
			$out .= '<p><strong>' . esc_html__( 'Customer ID:', 'duplicate-killer' ) . '</strong> ' . esc_html( (string) absint( $form_value['customer_id'] ) ) . '</p>';
		}

		if ( ! empty( $form_value['ip'] ) ) {
			$out .= '<p><strong>' . esc_html__( 'IP:', 'duplicate-killer' ) . '</strong> ' . esc_html( (string) $form_value['ip'] ) . '</p>';
		}

		// Optional: show a direct “order received” link if we have it.
		if ( ! empty( $form_value['order_received_url'] ) ) {
			$out .= '<p><strong>' . esc_html__( 'Confirmation:', 'duplicate-killer' ) . '</strong> ';
			$out .= '<a href="' . esc_url( (string) $form_value['order_received_url'] ) . '" target="_blank" rel="noopener noreferrer">';
			$out .= esc_html__( 'Open order confirmation →', 'duplicate-killer' );
			$out .= '</a></p>';
		}
		// Products: show names + edit links (admin-friendly)
		if ( ! empty( $products ) ) {
			$items = array();

			foreach ( $products as $pid ) {
				$pid = absint( $pid );
				if ( $pid <= 0 ) {
					continue;
				}

				$title = get_the_title( $pid );
				if ( ! is_string( $title ) || $title === '' ) {
					$title = '#' . $pid;
				}

				$edit = admin_url( 'post.php?post=' . $pid . '&action=edit' );
				$items[] = '<a href="' . esc_url( $edit ) . '">' . esc_html( $title ) . '</a>';
			}

			if ( ! empty( $items ) ) {
				$out .= '<p><strong>' . esc_html__( 'Products:', 'duplicate-killer' ) . '</strong> ' . implode( ', ', $items ) . '</p>';
			}
		}

		if ( $ts > 0 ) {
			$out .= '<p><small style="opacity:.75;">' . esc_html__( 'Logged:', 'duplicate-killer' ) . ' ' . esc_html( wp_date( 'Y-m-d H:i', $ts ) ) . '</small></p>';
		}

		$out .= '</div>';

		return $out;
	}
	private function dk_looks_like_signature_payload( string $val ): bool {
		if ( strpos( $val, 'signature_data' ) === false ) {
			return false;
		}

		if ( strpos( $val, 'data:image' ) === false ) {
			return false;
		}

		return true;
	}

	private function dk_extract_signature_from_payload( string $val ): array {
		$out = array(
			'data_url' => '',
			'width'    => 0,
			'height'   => 0,
		);

		$decoded = json_decode( $val, true );

		if ( ! is_array( $decoded ) ) {
			return $out;
		}

		if ( ! empty( $decoded['signature_data'] ) && is_string( $decoded['signature_data'] ) ) {
			$out['data_url'] = $decoded['signature_data'];
		}

		if ( ! empty( $decoded['canvas_dimensions'] ) && is_array( $decoded['canvas_dimensions'] ) ) {
			$width  = $decoded['canvas_dimensions']['width'] ?? 0;
			$height = $decoded['canvas_dimensions']['height'] ?? 0;

			$out['width']  = is_numeric( $width ) ? (int) $width : 0;
			$out['height'] = is_numeric( $height ) ? (int) $height : 0;
		}

		return $out;
	}

	private function duplicateKiller_wc_gateway_label( string $gateway_id ): string {
		$gateway_id = sanitize_key( $gateway_id );

		if ( '' === $gateway_id ) {
			return '';
		}

		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			$gateways = WC()->payment_gateways()->payment_gateways();

			if ( is_array( $gateways ) && isset( $gateways[ $gateway_id ] ) && is_object( $gateways[ $gateway_id ] ) ) {
				$title = method_exists( $gateways[ $gateway_id ], 'get_title' )
					? (string) $gateways[ $gateway_id ]->get_title()
					: '';

				$title = trim( $title );

				if ( '' !== $title ) {
					return $title;
				}
			}
		}

		return $gateway_id;
	}
}
