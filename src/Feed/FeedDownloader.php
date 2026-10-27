<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Feed;

use PtdGtfsSchedules\Import\ImportError;
use ZipArchive;

defined( 'ABSPATH' ) || exit;

final class FeedDownloader {

	private const DOWNLOAD_TIMEOUT_SECONDS = 300;

	/**
	 * @return string Path to a temporary copy of the feed archive.
	 * @throws ImportError When the server cannot fetch the URL.
	 */
	public function download( string $url ): string {
		$this->load_file_api();

		$archive = download_url( $url, self::DOWNLOAD_TIMEOUT_SECONDS );

		if ( is_wp_error( $archive ) ) {
			/* translators: %s: error message */
			throw new ImportError( esc_html( sprintf( __( 'Could not download the feed: %s', 'ptd-gtfs-schedules' ), $archive->get_error_message() ) ) );
		}

		return $archive;
	}

	/**
	 * @return string Directory that holds the feed's .txt files.
	 * @throws ImportError When the archive is broken or the uploads folder is not writable.
	 */
	public function extract( string $archive ): string {
		$this->clean_up();

		$target = $this->work_directory();
		$this->unzip_read_files( $archive, $target );

		return FeedFormat::locate_root( $target );
	}

	public function clean_up(): void {
		global $wp_filesystem;

		$directory = $this->work_directory();

		if ( is_dir( $directory ) && $this->connect_filesystem() ) {
			$wp_filesystem->delete( $directory, true );
		}
	}

	/**
	 * @throws ImportError When the archive cannot be opened or written out.
	 */
	private function unzip_read_files( string $archive, string $target ): void {
		if ( ! class_exists( ZipArchive::class ) ) {
			self::fail_to_unpack( __( 'the PHP zip extension is not installed', 'ptd-gtfs-schedules' ) );
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $archive ) ) {
			self::fail_to_unpack( __( 'The file is not a .zip archive. A GTFS feed is published as a .zip of .txt files.', 'ptd-gtfs-schedules' ) );
		}

		$entries = array();
		$count   = $zip->count();
		for ( $index = 0; $index < $count; $index++ ) {
			$name = (string) $zip->getNameIndex( $index );

			if ( FeedFormat::is_read_file( $name ) ) {
				$entries[] = $name;
			}
		}

		$extracted = ! $entries || $zip->extractTo( $target, $entries );
		$zip->close();

		if ( ! $extracted ) {
			self::fail_to_unpack( __( 'filesystem is not writable', 'ptd-gtfs-schedules' ) );
		}
	}

	/**
	 * @throws ImportError Always.
	 */
	private static function fail_to_unpack( string $reason ): never {
		/* translators: %s: error message */
		throw new ImportError( esc_html( sprintf( __( 'Could not unpack the feed: %s', 'ptd-gtfs-schedules' ), $reason ) ) );
	}

	private function work_directory(): string {
		return trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'ptd-gtfs-schedules-import';
	}

	private function connect_filesystem(): bool {
		$this->load_file_api();

		return (bool) WP_Filesystem();
	}

	private function load_file_api(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
}
