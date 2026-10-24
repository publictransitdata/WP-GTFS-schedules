<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Network;

defined( 'ABSPATH' ) || exit;

/**
 * Stops that only some trips call at, off the usual route of a direction.
 */
final class LineBranch {

	public const STARTS_BEFORE = 'starts-before';
	public const JOINS         = 'joins';
	public const DETOUR        = 'detour';
	public const ENDS          = 'ends';
	public const CONTINUES     = 'continues';

	/**
	 * @param int        $leaves_after Position of the usual stop it leaves after; -1 before the first stop.
	 * @param int|null   $rejoins_at   Position of the usual stop it comes back to; null when it never does.
	 * @param LineStop[] $stops
	 */
	public function __construct(
		public readonly int $leaves_after,
		public readonly ?int $rejoins_at,
		public readonly array $stops,
	) {}

	/**
	 * Trips that come from somewhere else and join the route on the way.
	 */
	public function joins_route(): bool {
		return -1 === $this->leaves_after;
	}

	/**
	 * Trips that leave the route and come back to it.
	 */
	public function is_detour(): bool {
		return ! $this->joins_route() && null !== $this->rejoins_at;
	}

	/**
	 * @param int $last_position Position of the last stop of the usual route.
	 * @return string STARTS_BEFORE: the trips start before the first stop of the route; JOINS: they start
	 *                elsewhere and join it on the way; DETOUR: they leave it and come back; CONTINUES: they
	 *                carry on past its last stop; ENDS: they leave it on the way and end somewhere else.
	 */
	public function kind( int $last_position ): string {
		return match ( true ) {
			$this->joins_route() && 0 === $this->rejoins_at => self::STARTS_BEFORE,
			$this->joins_route()                            => self::JOINS,
			$this->is_detour()                              => self::DETOUR,
			$this->leaves_after === $last_position          => self::CONTINUES,
			default                                         => self::ENDS,
		};
	}

	/**
	 * Whether the trips on this branch end at its last stop.
	 */
	public function ends_the_trip(): bool {
		return ! $this->joins_route() && null === $this->rejoins_at;
	}
}
