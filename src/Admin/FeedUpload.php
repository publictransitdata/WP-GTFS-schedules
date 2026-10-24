<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Admin;

use PtdGtfsSchedules\Import\ImportError;

defined( 'ABSPATH' ) || exit;

/**
 * A feed .zip uploaded from the settings page, for sites that cannot or do not want to
 * import from a URL. It is moved out of PHP's upload folder, which is emptied after each request,
 * because the import may unpack it in a later request.
 */
final class FeedUpload {

	public const FIELD = 'ptd_gtfs_feed';

	public static function max_bytes(): int {
		return (int) wp_max_upload_size();
	}

	/**
	 * Call only after the request's nonce and capability have been checked.
	 *
	 * @return string Path to the archive in the temporary directory.
	 * @throws ImportError When the upload is missing, failed or is not a .zip file.
	 */
	public static function take_from_request(): string {
		$file = $_FILES[ self::FIELD ] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked by the caller; the file is validated below.

		if ( ! is_array( $file ) ) {
			// PHP drops the whole request body, file included, when it exceeds post_max_size.
			throw new ImportError( esc_html( self::too_large_message() ) );
		}

		self::assert_uploaded( (int) $file['error'] );
		self::assert_zip( (string) $file['tmp_name'], (string) $file['name'] );

		add_filter( 'upload_dir', array( self::class, 'temporary_directory' ) );

		$saved = wp_handle_upload(
			$file,
			array(
				'test_form'                => false,
				'mimes'                    => array( 'zip' => 'application/zip' ),
				'unique_filename_callback' => fn() => wp_generate_password( 16, false ) . '-gtfs-feed.zip',
			)
		);

		remove_filter( 'upload_dir', array( self::class, 'temporary_directory' ) );

		if ( isset( $saved['error'] ) ) {
			throw new ImportError( esc_html( $saved['error'] ) );
		}

		return $saved['file'];
	}

	/**
	 * Keeps the feed out of the media library and the public uploads folder.
	 *
	 * @param array<string, mixed> $directories Paths and URLs from wp_upload_dir().
	 * @return array<string, mixed>
	 */
	public static function temporary_directory( array $directories ): array {
		return array_merge(
			$directories,
			array(
				'path'   => untrailingslashit( get_temp_dir() ),
				'url'    => '',
				'subdir' => '',
				'error'  => false,
			)
		);
	}

	public static function too_large_message(): string {
		return sprintf(
			/* translators: %s: maximum upload size, e.g. "64 MB" */
			__( 'The file is larger than this site accepts (%s). Import it from a URL or with WP-CLI instead.', 'ptd-gtfs-schedules' ),
			size_format( self::max_bytes() )
		);
	}

	private static function assert_uploaded( int $error ): void {
		switch ( $error ) {
			case UPLOAD_ERR_OK:
				return;

			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				throw new ImportError( esc_html( self::too_large_message() ) );

			case UPLOAD_ERR_NO_FILE:
				throw new ImportError( esc_html__( 'Choose a GTFS .zip file first.', 'ptd-gtfs-schedules' ) );

			default:
				/* translators: %d: PHP upload error code */
				throw new ImportError( esc_html( sprintf( __( 'The upload failed (PHP error %d).', 'ptd-gtfs-schedules' ), $error ) ) );
		}
	}

	private static function assert_zip( string $path, string $name ): void {
		$type = wp_check_filetype_and_ext( $path, $name, array( 'zip' => 'application/zip' ) );

		if ( 'zip' !== $type['ext'] ) {
			throw new ImportError( esc_html__( 'The file is not a .zip archive. A GTFS feed is published as a .zip of .txt files.', 'ptd-gtfs-schedules' ) );
		}
	}
}
