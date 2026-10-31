<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Schedule;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

final class TimetableDeparture {

	/**
	 * @param DateTimeImmutable $time
	 * @param string            $minute Two digits, as printed in the timetable.
	 * @param string|null       $mark   Letter of the footnote that explains this trip, e.g. "a".
	 * @param string|null       $note   That footnote.
	 * @param string            $trip_id
	 * @param string            $stop_id The stop itself, also where the timetable is a station's.
	 */
	public function __construct(
		public readonly DateTimeImmutable $time,
		public readonly string $minute,
		public readonly ?string $mark = null,
		public readonly ?string $note = null,
		public readonly string $trip_id = '',
		public readonly string $stop_id = '',
	) {}

	public function has_left( DateTimeImmutable $now ): bool {
		return $this->time < $now;
	}
}
