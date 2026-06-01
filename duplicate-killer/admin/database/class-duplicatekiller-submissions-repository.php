<?php
defined( 'ABSPATH' ) || exit;

class DuplicateKiller_Submissions_Repository {

	private string $table_name;

	public function __construct() {
		global $wpdb;

		$dkdb = apply_filters( 'duplicate_killer_database', $wpdb );

		$this->table_name = $dkdb->prefix . 'dk_forms_duplicate';
	}
	
	public function form_exists_for_plugin( string $form_plugin, string $form_name ): bool {
		global $wpdb;

		if ( '' === $form_plugin || '' === $form_name ) {
			return false;
		}

		$sql = "SELECT COUNT(form_id)
				FROM {$this->table_name}
				WHERE form_plugin = %s
				AND form_name = %s";

		$prepared = $wpdb->prepare( $sql, $form_plugin, $form_name );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $prepared ) > 0;
	}
	public function get_items( string $search, string $view, int $per_page, int $page, string $form_plugin = '', string $form_name = '' ): array{
		global $wpdb;

		$page   = max( 1, $page );
		$offset = ( $page - 1 ) * $per_page;

		$where  = array();
		$params = array();

		if ( 'wc' === $view ) {
			$where[]  = 'form_plugin = %s';
			$params[] = 'WooCommerce';
		} else {
			$where[]  = 'form_plugin <> %s';
			$params[] = 'WooCommerce';
		}
		
		if ( '' !== $form_plugin ) {
			$where[]  = 'form_plugin = %s';
			$params[] = $form_plugin;
		}

		if ( '' !== $form_name ) {
			$where[]  = 'form_name = %s';
			$params[] = $form_name;
		}
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			$where[] = '(form_value LIKE %s OR form_plugin LIKE %s OR form_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT form_id, form_plugin, form_name, form_value, form_date, form_ip FROM {$this->table_name}";

		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql .= ' ORDER BY form_id DESC LIMIT %d OFFSET %d';

		$params[] = $per_page;
		$params[] = $offset;

		$prepared = $wpdb->prepare( $sql, $params );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $prepared, OBJECT );

		return is_array( $rows ) ? $rows : array();
	}

	public function count_items( string $search, string $view, string $form_plugin = '', string $form_name = '' ): int{
		global $wpdb;

		$where  = array();
		$params = array();

		if ( 'wc' === $view ) {
			$where[]  = 'form_plugin = %s';
			$params[] = 'WooCommerce';
		} else {
			$where[]  = 'form_plugin <> %s';
			$params[] = 'WooCommerce';
		}
		if ( '' !== $form_plugin ) {
			$where[]  = 'form_plugin = %s';
			$params[] = $form_plugin;
		}

		if ( '' !== $form_name ) {
			$where[]  = 'form_name = %s';
			$params[] = $form_name;
		}
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			$where[] = '(form_value LIKE %s OR form_plugin LIKE %s OR form_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT COUNT(form_id) FROM {$this->table_name}";

		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$prepared = $wpdb->prepare( $sql, $params );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $prepared );
	}
	
	public function get_sidebar_groups(): array {
		global $wpdb;

		$sql = "SELECT form_plugin, form_name, COUNT(form_id) AS total
				FROM {$this->table_name}
				WHERE form_plugin <> %s
				GROUP BY form_plugin, form_name
				ORDER BY form_plugin ASC, form_name ASC";

		$prepared = $wpdb->prepare( $sql, 'WooCommerce' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $prepared, ARRAY_A );

		$groups = array();

		foreach ( (array) $rows as $row ) {
			$plugin = isset( $row['form_plugin'] ) ? sanitize_text_field( (string) $row['form_plugin'] ) : '';
			$name   = isset( $row['form_name'] ) ? sanitize_text_field( (string) $row['form_name'] ) : '';
			$total  = isset( $row['total'] ) ? absint( $row['total'] ) : 0;

			if ( '' === $plugin || '' === $name ) {
				continue;
			}

			if ( ! isset( $groups[ $plugin ] ) ) {
				$groups[ $plugin ] = array(
					'total' => 0,
					'forms' => array(),
				);
			}

			$groups[ $plugin ]['total'] += $total;
			$groups[ $plugin ]['forms'][] = array(
				'name'  => $name,
				'total' => $total,
			);
		}

		return $groups;
	}
	public function delete_items( array $ids ): int {
		global $wpdb;

		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$sql = "DELETE FROM {$this->table_name} WHERE form_id IN ({$placeholders})";

		$prepared = $wpdb->prepare( $sql, $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query( $prepared );
	}
}