<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Network;

defined( 'ABSPATH' ) || exit;

/**
 * How one way of running a line differs from its usual route: the stops both have in common
 * are matched in order (longest common subsequence), and whatever is left over becomes a branch.
 */
final class RouteComparison {

	/**
	 * @param string[] $usual   Stop ids of the usual route.
	 * @param string[] $variant Stop ids of another route of the same direction.
	 * @return array{
	 *     branches: list<array{leaves_after: int, rejoins_at: int|null, stop_ids: string[]}>,
	 *     ends_early_at: int|null,
	 *     starts_late_at: int|null,
	 *     shared: int[]
	 * } Positions refer to $usual. A branch that leaves after -1 comes before the first stop;
	 *   one that never rejoins carries on past the usual route. Shared are the usual stops the variant calls at too.
	 */
	public static function compare( array $usual, array $variant ): array {
		$matches  = self::common_stops( $usual, $variant );
		$branches = array();
		$detached = array();
		$previous = -1;

		foreach ( $variant as $position => $stop_id ) {
			if ( ! isset( $matches[ $position ] ) ) {
				$detached[] = $stop_id;
				continue;
			}

			if ( $detached ) {
				$branches[] = self::branch( $previous, $matches[ $position ], $detached );
				$detached   = array();
			}

			$previous = $matches[ $position ];
		}

		if ( $detached ) {
			$branches[] = self::branch( $previous, null, $detached );
		}

		$first_shared = $matches ? reset( $matches ) : null;
		$last_shared  = $matches ? end( $matches ) : null;

		return array(
			'branches'       => $branches,
			'ends_early_at'  => null !== $last_shared && $last_shared < count( $usual ) - 1 && isset( $matches[ count( $variant ) - 1 ] ) ? $last_shared : null,
			'starts_late_at' => null !== $first_shared && $first_shared > 0 && isset( $matches[0] ) ? $first_shared : null,
			'shared'         => array_values( $matches ),
		);
	}

	/**
	 * @param string[] $usual
	 * @param string[] $variant
	 * @return array<int, int> Position in $variant => position in $usual, both increasing.
	 */
	private static function common_stops( array $usual, array $variant ): array {
		$usual_count   = count( $usual );
		$variant_count = count( $variant );
		$longest       = array_fill( 0, $usual_count + 1, array_fill( 0, $variant_count + 1, 0 ) );

		for ( $i = $usual_count - 1; $i >= 0; $i-- ) {
			for ( $j = $variant_count - 1; $j >= 0; $j-- ) {
				$longest[ $i ][ $j ] = $usual[ $i ] === $variant[ $j ]
					? $longest[ $i + 1 ][ $j + 1 ] + 1
					: max( $longest[ $i + 1 ][ $j ], $longest[ $i ][ $j + 1 ] );
			}
		}

		$matches = array();
		for ( $i = 0, $j = 0; $i < $usual_count && $j < $variant_count; ) {
			if ( $usual[ $i ] === $variant[ $j ] ) {
				$matches[ $j++ ] = $i++;
			} elseif ( $longest[ $i + 1 ][ $j ] >= $longest[ $i ][ $j + 1 ] ) {
				++$i;
			} else {
				++$j;
			}
		}

		return $matches;
	}

	/**
	 * @param int      $leaves_after
	 * @param int|null $rejoins_at
	 * @param string[] $stop_ids
	 * @return array{leaves_after: int, rejoins_at: int|null, stop_ids: string[]}
	 */
	private static function branch( int $leaves_after, ?int $rejoins_at, array $stop_ids ): array {
		return array(
			'leaves_after' => $leaves_after,
			'rejoins_at'   => $rejoins_at,
			'stop_ids'     => $stop_ids,
		);
	}
}
