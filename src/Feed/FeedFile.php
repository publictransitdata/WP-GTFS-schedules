<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Feed;

use Closure;

defined( 'ABSPATH' ) || exit;

/**
 * One text file of a GTFS feed and how its rows become rows of our table.
 */
final class FeedFile {

	/**
	 * @param string   $name    File name inside the feed archive, e.g. "stops.txt".
	 * @param string   $table   Table name without prefix.
	 * @param string[] $columns Table columns, in the order the mapper returns them.
	 * @param Closure  $mapper  Turns a CSV row (column => value) into a table row, or null to skip it.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $table,
		public readonly array $columns,
		private readonly Closure $mapper,
	) {}

	public function map( array $row ): ?array {
		return ( $this->mapper )( $row );
	}
}
