<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Feed;

use GtfsTimetables\Import\ImportError;
use SplFileObject;

defined( 'ABSPATH' ) || exit;

/**
 * Streams a GTFS text file row by row, so the whole file never sits in memory,
 * and can continue from a byte position saved by an earlier request.
 */
final class CsvReader {

	private SplFileObject $file;

	/** @var string[] */
	private array $header;

	public function __construct( string $path ) {
		$this->file   = new SplFileObject( $path, 'r' );
		$this->header = $this->read_header();
	}

	public function seek( int $position ): void {
		if ( $position > 0 ) {
			$this->file->fseek( $position );
		}
	}

	public function position(): int {
		return (int) $this->file->ftell();
	}

	public function size(): int {
		return (int) $this->file->getSize();
	}

	/**
	 * @return array<string, string>|null Column name => value, or null at the end of the file.
	 */
	public function next_row(): ?array {
		while ( ! $this->file->eof() ) {
			$values = $this->read_values();

			if ( $this->is_blank( $values ) ) {
				continue;
			}

			return $this->combine( $values );
		}

		return null;
	}

	private function read_header(): array {
		$names = $this->read_values();

		if ( $this->is_blank( $names ) ) {
			/* translators: %s: file name */
			throw new ImportError( sprintf( __( '%s has no header row.', 'gtfs-timetables' ), $this->file->getFilename() ) );
		}

		$names[0] = $this->strip_byte_order_mark( (string) $names[0] );

		return array_map( fn( $name ) => strtolower( trim( (string) $name ) ), $names );
	}

	private function read_values(): array|false {
		return $this->file->fgetcsv( ',', '"', '' );
	}

	private function is_blank( array|false $values ): bool {
		return false === $values || array( null ) === $values || array( '' ) === $values;
	}

	private function combine( array $values ): array {
		$column_count = count( $this->header );
		$values       = array_slice( array_pad( $values, $column_count, '' ), 0, $column_count );

		return array_combine( $this->header, $values );
	}

	private function strip_byte_order_mark( string $text ): string {
		return str_starts_with( $text, "\xEF\xBB\xBF" ) ? substr( $text, 3 ) : $text;
	}
}
