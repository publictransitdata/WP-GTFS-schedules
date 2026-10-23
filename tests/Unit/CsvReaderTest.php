<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Tests\Unit;

use GtfsTimetables\Feed\CsvReader;
use GtfsTimetables\Import\ImportError;
use PHPUnit\Framework\TestCase;

final class CsvReaderTest extends TestCase {

	/** @var string[] */
	private array $files = array();

	protected function tearDown(): void {
		array_map( 'unlink', $this->files );
	}

	public function test_reads_rows_by_column_name(): void {
		$rows = $this->read_all( "stop_id,stop_name\n1013,Dworzec Główny 18\n1014,Dworzec Główny 05\n" );

		$this->assertSame(
			array(
				array(
					'stop_id'   => '1013',
					'stop_name' => 'Dworzec Główny 18',
				),
				array(
					'stop_id'   => '1014',
					'stop_name' => 'Dworzec Główny 05',
				),
			),
			$rows
		);
	}

	public function test_ignores_a_byte_order_mark_and_the_case_of_column_names(): void {
		$rows = $this->read_all( "\xEF\xBB\xBFStop_ID , Stop_Name\n1013,Brama Wyżynna\n" );

		$this->assertSame( array( 'stop_id', 'stop_name' ), array_keys( $rows[0] ) );
	}

	public function test_reads_windows_line_endings(): void {
		$rows = $this->read_all( "stop_id,stop_name\r\n1013,Brama Wyżynna\r\n" );

		$this->assertSame( 'Brama Wyżynna', $rows[0]['stop_name'] );
	}

	public function test_skips_blank_lines(): void {
		$rows = $this->read_all( "stop_id\n\n1013\n\n\n1014\n" );

		$this->assertSame( array( '1013', '1014' ), array_column( $rows, 'stop_id' ) );
	}

	public function test_reads_quoted_values_with_commas_quotes_and_line_breaks(): void {
		$rows = $this->read_all( "stop_id,stop_name\n1,\"Plac Solidarności, peron \"\"A\"\"\"\n2,\"Two\nlines\"\n" );

		$this->assertSame( array( 'Plac Solidarności, peron "A"', "Two\nlines" ), array_column( $rows, 'stop_name' ) );
	}

	public function test_fills_in_missing_trailing_values_and_drops_extra_ones(): void {
		$rows = $this->read_all( "stop_id,stop_code,stop_name\n1013\n1014,14,Name,unexpected\n" );

		$this->assertSame(
			array(
				array(
					'stop_id'   => '1013',
					'stop_code' => '',
					'stop_name' => '',
				),
				array(
					'stop_id'   => '1014',
					'stop_code' => '14',
					'stop_name' => 'Name',
				),
			),
			$rows
		);
	}

	public function test_a_reader_opened_at_a_saved_position_continues_without_losing_or_repeating_rows(): void {
		$path = $this->file( "stop_id,stop_name\n1,One\n2,\"Two\nlines\"\n\n3,Three\n4,Four\n" );

		$rows_read_in_steps = array();
		$position           = 0;

		do {
			$reader = new CsvReader( $path );
			$reader->seek( $position );

			$row = $reader->next_row();
			if ( null !== $row ) {
				$rows_read_in_steps[] = $row;
			}

			$position = $reader->position();
		} while ( null !== $row );

		$this->assertSame( iterator_to_array( ( new CsvReader( $path ) )->rows(), false ), $rows_read_in_steps );
		$this->assertSame( array( '1', '2', '3', '4' ), array_column( $rows_read_in_steps, 'stop_id' ) );
	}

	public function test_a_file_without_a_header_row_is_an_import_error(): void {
		$this->expectException( ImportError::class );

		new CsvReader( $this->file( '' ) );
	}

	private function read_all( string $contents ): array {
		return iterator_to_array( ( new CsvReader( $this->file( $contents ) ) )->rows(), false );
	}

	private function file( string $contents ): string {
		$path = (string) tempnam( sys_get_temp_dir(), 'gtfs-test-' );
		file_put_contents( $path, $contents );

		$this->files[] = $path;

		return $path;
	}
}
