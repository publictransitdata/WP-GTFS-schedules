<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Feed;

use GtfsTimetables\Import\ImportError;

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
			throw new ImportError( esc_html( sprintf( __( 'Could not download the feed: %s', 'gtfs-timetables' ), $archive->get_error_message() ) ) );
		}

		return $archive;
	}

	/**
	 * @return string Directory that holds the feed's .txt files.
	 * @throws ImportError When the archive is broken or the uploads folder is not writable.
	 */
	public function extract( string $archive ): string {
		$this->load_file_api();
		$this->clean_up();

		$target = $this->work_directory();
		$result = $this->connect_filesystem() ? unzip_file( $archive, $target ) : false;

		if ( true !== $result ) {
			$reason = is_wp_error( $result ) ? $result->get_error_message() : __( 'filesystem is not writable', 'gtfs-timetables' );
			/* translators: %s: error message */
			throw new ImportError( esc_html( sprintf( __( 'Could not unpack the feed: %s', 'gtfs-timetables' ), $reason ) ) );
		}

		return FeedFormat::locate_root( $target );
	}

	public function clean_up(): void {
		global $wp_filesystem;

		$directory = $this->work_directory();

		if ( is_dir( $directory ) && $this->connect_filesystem() ) {
			$wp_filesystem->delete( $directory, true );
		}
	}

	private function work_directory(): string {
		return trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'gtfs-timetables-import';
	}

	private function connect_filesystem(): bool {
		$this->load_file_api();

		return (bool) WP_Filesystem();
	}

	private function load_file_api(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
}
