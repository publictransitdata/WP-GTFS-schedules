<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Collects rows and writes them with one multi-row INSERT, which is
 * far faster than inserting millions of stop times one by one.
 */
final class BatchInsert {

	private array $rows = array();

	public function __construct(
		private readonly string $table,
		private readonly array $columns,
		private readonly int $size = 500,
	) {}

	public function add( array $values ): void {
		$this->rows[] = '(' . implode( ',', array_map( array( $this, 'sql_value' ), $values ) ) . ')';
	}

	public function is_full(): bool {
		return count( $this->rows ) >= $this->size;
	}

	/**
	 * @return int Number of rows written.
	 * @throws ImportError When the database rejects the rows.
	 */
	public function flush(): int {
		global $wpdb;

		if ( ! $this->rows ) {
			return 0;
		}

		$sql = sprintf(
			'INSERT IGNORE INTO %s (%s) VALUES %s',
			$this->table,
			implode( ', ', $this->columns ),
			implode( ', ', $this->rows )
		);

		$this->rows = array();
		$written    = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- every value is escaped in sql_value().

		if ( false === $written ) {
			/* translators: 1: table name, 2: database error */
			throw new ImportError( esc_html( sprintf( __( 'Could not write to %1$s: %2$s', 'ptd-gtfs-schedules' ), $this->table, $wpdb->last_error ) ) );
		}

		return (int) $written;
	}

	private function sql_value( string|int|null $value ): string {
		if ( null === $value ) {
			return 'NULL';
		}

		if ( is_int( $value ) ) {
			return (string) $value;
		}

		return "'" . esc_sql( $value ) . "'";
	}
}
