<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Network;

defined( 'ABSPATH' ) || exit;

/**
 * One way along a line: where it goes, the stops of its usual route in order,
 * and how the trips that do not follow that route differ from it.
 */
final class LineDirection {

	/**
	 * @param int|null                                                                $direction_id   From trips.txt; null when the feed does not tell directions apart.
	 * @param string                                                                  $headsign
	 * @param LineStop[]                                                              $stops          The usual route. A loop may visit the same stop twice.
	 * @param LineBranch[]                                                            $branches       Detours, and stops before or after the usual route.
	 * @param int[]                                                                   $ends_early_at  Positions of usual stops where some trips end.
	 * @param int[]                                                                   $starts_late_at Positions of usual stops where some trips start.
	 * @param array<string, TripNote>                                                 $trip_notes     Trip id => how it differs; trips on the usual route have none.
	 * @param int[]                                                                   $partial_at     Positions of usual stops that not every trip calls at.
	 * @param array<string, array{boarding: bool, alighting: bool, on_request: bool}> $stop_rules Stop id => rules, for stops that are not regular.
	 */
	public function __construct(
		public readonly ?int $direction_id,
		public readonly string $headsign,
		public readonly array $stops,
		public readonly array $branches = array(),
		public readonly array $ends_early_at = array(),
		public readonly array $starts_late_at = array(),
		public readonly array $trip_notes = array(),
		public readonly array $partial_at = array(),
		private readonly array $stop_rules = array(),
	) {}

	/**
	 * @param array $data As LineDirections::describe() returns it, with stop_names.
	 */
	public static function from_array( array $data ): self {
		$stop = fn( string $stop_id ) => new LineStop( $stop_id, $data['stop_names'][ $stop_id ] ?? $stop_id );

		return new self(
			$data['direction_id'],
			$data['headsign'],
			array_map( $stop, $data['stop_ids'] ),
			array_map(
				fn( array $branch ) => new LineBranch( $branch['leaves_after'], $branch['rejoins_at'], array_map( $stop, $branch['stop_ids'] ) ),
				$data['branches']
			),
			$data['ends_early_at'],
			$data['starts_late_at'],
			array_map(
				fn( array $note ) => new TripNote( $note['to'], null === $note['via'] ? null : $stop( $note['via'] )->name ),
				$data['trip_notes']
			),
			$data['partial_at'] ?? array(),
			$data['stop_rules'] ?? array()
		);
	}

	public function can_board_at( string $stop_id ): bool {
		return $this->stop_rules[ $stop_id ]['boarding'] ?? true;
	}

	public function can_alight_at( string $stop_id ): bool {
		return $this->stop_rules[ $stop_id ]['alighting'] ?? true;
	}

	/**
	 * Riders signal the driver to stop.
	 */
	public function stops_on_request_at( string $stop_id ): bool {
		return $this->stop_rules[ $stop_id ]['on_request'] ?? false;
	}

	public function is_partial_at( int $position ): bool {
		return in_array( $position, $this->partial_at, true );
	}

	/**
	 * The stop a line page opens on: the first where riders can get on.
	 */
	public function first_boarding_stop(): ?LineStop {
		foreach ( $this->stops as $stop ) {
			if ( $this->can_board_at( $stop->stop_id ) ) {
				return $stop;
			}
		}

		return $this->stops[0] ?? null;
	}

	public function has_stop( string $stop_id ): bool {
		return null !== $this->stop( $stop_id );
	}

	/**
	 * A stop of the usual route or of a branch.
	 */
	public function stop( string $stop_id ): ?LineStop {
		foreach ( $this->all_stops() as $stop ) {
			if ( $stop->stop_id === $stop_id ) {
				return $stop;
			}
		}

		return null;
	}

	/**
	 * Riders cannot board at the end of the route, or at the end of a branch where some trips end
	 * instead, so these stops have no departures to show.
	 */
	public function ends_at( string $stop_id ): bool {
		$ends = array_map(
			fn( LineBranch $branch ) => $branch->stops[ array_key_last( $branch->stops ) ],
			array_filter( $this->branches, fn( LineBranch $branch ) => $branch->ends_the_trip() && $branch->stops )
		);

		if ( $this->stops ) {
			$ends[] = $this->stops[ array_key_last( $this->stops ) ];
		}

		return in_array( $stop_id, array_map( fn( LineStop $stop ) => $stop->stop_id, $ends ), true );
	}

	/**
	 * @return string One of the LineBranch kinds.
	 */
	public function kind_of( LineBranch $branch ): string {
		return $branch->kind( (int) array_key_last( $this->stops ) );
	}

	/**
	 * @return LineBranch[] Branches that leave the usual route right after the stop at $position.
	 */
	public function branches_after( int $position ): array {
		return array_values( array_filter( $this->branches, fn( LineBranch $branch ) => ! $branch->joins_route() && $branch->leaves_after === $position ) );
	}

	/**
	 * @return LineBranch[] Branches of trips that come from elsewhere and join the usual route at $position.
	 */
	public function branches_joining_at( int $position ): array {
		return array_values( array_filter( $this->branches, fn( LineBranch $branch ) => $branch->joins_route() && $branch->rejoins_at === $position ) );
	}

	public function note_for( string $trip_id ): ?TripNote {
		return $this->trip_notes[ $trip_id ] ?? null;
	}

	/**
	 * @return LineStop[] The usual stops and the stops of every branch.
	 */
	private function all_stops(): array {
		return array_merge( $this->stops, ...array_map( fn( LineBranch $branch ) => $branch->stops, $this->branches ) );
	}
}
