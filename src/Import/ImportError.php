<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Import;

defined( 'ABSPATH' ) || exit;

/**
 * The message is HTML-escaped when thrown, as WordPress requires of exception messages.
 */
final class ImportError extends \RuntimeException {

	public function plain_text(): string {
		return wp_specialchars_decode( $this->getMessage(), ENT_QUOTES );
	}
}
