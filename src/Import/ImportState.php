<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Progress of an import, saved between requests so a large feed can be
 * loaded in many short steps.
 */
final class ImportState {

	public const IDLE        = 'idle';
	public const DOWNLOADING = 'downloading';
	public const LOADING     = 'loading';
	public const DONE        = 'done';
	public const FAILED      = 'failed';

	private const OPTION              = 'gtfs_timetables_import_state';
	private const STALE_AFTER_SECONDS = HOUR_IN_SECONDS;

	public string $stage     = self::IDLE;
	public bool $forced      = false;
	public string $archive   = '';
	public string $directory = '';
	public string $version   = '';
	public int $file_index   = 0;
	public int $offset       = 0;
	public array $rows       = array();
	public string $message   = '';
	public int $started_at   = 0;
	public int $updated_at   = 0;

	public static function load(): self {
		$state = new self();

		foreach ( (array) get_option( self::OPTION, array() ) as $property => $value ) {
			if ( property_exists( $state, $property ) ) {
				$state->$property = $value;
			}
		}

		return $state;
	}

	/**
	 * With an archive (an uploaded feed .zip) the import uses it instead of downloading the feed URL.
	 */
	public static function begin( bool $forced, string $archive = '' ): self {
		$state             = new self();
		$state->stage      = self::DOWNLOADING;
		$state->forced     = $forced;
		$state->archive    = $archive;
		$state->started_at = time();

		return $state;
	}

	public static function forget(): void {
		delete_option( self::OPTION );
	}

	public function save(): void {
		$this->updated_at = time();
		update_option( self::OPTION, get_object_vars( $this ), false );
	}

	public function is_from_upload(): bool {
		return '' !== $this->archive;
	}

	public function start_loading( string $directory, string $version ): void {
		$this->stage      = self::LOADING;
		$this->archive    = '';
		$this->directory  = $directory;
		$this->version    = $version;
		$this->file_index = 0;
		$this->offset     = 0;
	}

	public function move_to_next_file(): void {
		++$this->file_index;
		$this->offset = 0;
	}

	public function count_rows( string $table, int $count ): void {
		$this->rows[ $table ] = ( $this->rows[ $table ] ?? 0 ) + $count;
	}

	public function finish( string $message ): void {
		$this->stage     = self::DONE;
		$this->message   = $message;
		$this->archive   = '';
		$this->directory = '';
	}

	public function fail( string $message ): void {
		$this->stage     = self::FAILED;
		$this->message   = $message;
		$this->archive   = '';
		$this->directory = '';
	}

	public function is_running(): bool {
		return in_array( $this->stage, array( self::DOWNLOADING, self::LOADING ), true );
	}

	public function is_stale(): bool {
		return $this->is_running() && time() - $this->updated_at > self::STALE_AFTER_SECONDS;
	}
}
