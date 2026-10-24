<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Schedule;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

final class TimetableDeparture {

	/**
	 * @param DateTimeImmutable $time
	 * @param string            $minute Two digits, as printed in the timetable.
	 * @param string|null       $mark   Letter of the footnote that explains this trip, e.g. "a".
	 * @param string|null       $note   That footnote.
	 */
	public function __construct(
		public readonly DateTimeImmutable $time,
		public readonly string $minute,
		public readonly ?string $mark = null,
		public readonly ?string $note = null,
	) {}

	public function has_left( DateTimeImmutable $now ): bool {
		return $this->time < $now;
	}
}
