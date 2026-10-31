<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Schedule;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * One stop of a trip, as riders see it: nobody boards at the last stop, nobody alights at the first.
 */
final class TripCall {

	public function __construct(
		public readonly string $stop_id,
		public readonly string $stop_name,
		public readonly DateTimeImmutable $arrival,
		public readonly DateTimeImmutable $departure,
		public readonly bool $is_first,
		public readonly bool $is_last,
		private readonly bool $can_board,
		private readonly bool $can_alight,
		public readonly bool $on_request,
	) {}

	/**
	 * At a layover; elsewhere the vehicle leaves when it arrives and one time is enough.
	 */
	public function shows_arrival_and_departure(): bool {
		return ! $this->is_first && ! $this->is_last && $this->arrival < $this->departure;
	}

	/**
	 * The one time to show: the arrival at the last stop, the departure everywhere else.
	 */
	public function time(): DateTimeImmutable {
		return $this->is_last ? $this->arrival : $this->departure;
	}

	public function has_left( DateTimeImmutable $now ): bool {
		return $this->time() < $now;
	}

	public function can_board(): bool {
		return $this->can_board && ! $this->is_last;
	}

	public function is_boarding_only(): bool {
		return ! $this->can_alight && ! $this->is_first;
	}

	public function is_alighting_only(): bool {
		return ! $this->can_board && ! $this->is_last;
	}
}
