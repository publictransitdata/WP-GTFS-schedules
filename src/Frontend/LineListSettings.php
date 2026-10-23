<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Frontend;

use GtfsTimetables\Network\TransportMode;

defined( 'ABSPATH' ) || exit;

/**
 * How a list of lines is shown, from the block's attributes or the [gtfs_lines] shortcode.
 */
final class LineListSettings {

	public const COMPACT      = 'compact';
	public const DESTINATIONS = 'destinations';

	/**
	 * @param TransportMode[] $modes Only these modes; every mode when empty.
	 * @param string          $layout COMPACT (line numbers) or DESTINATIONS (numbers with where they go).
	 * @param bool            $tabs One tab per group instead of all groups one under the other.
	 */
	public function __construct(
		public readonly array $modes = array(),
		public readonly string $layout = self::COMPACT,
		public readonly bool $tabs = false,
		public readonly bool $night_lines_apart = true,
		public readonly int $heading_level = 2,
	) {}

	public static function from_block_attributes( array $attributes ): self {
		return new self(
			self::modes( (array) ( $attributes['modes'] ?? array() ) ),
			self::layout( (string) ( $attributes['layout'] ?? '' ) ),
			(bool) ( $attributes['tabs'] ?? false ),
			(bool) ( $attributes['nightLinesApart'] ?? true ),
			self::heading_level( (int) ( $attributes['headingLevel'] ?? 2 ) )
		);
	}

	/**
	 * [gtfs_lines modes="tram,bus" layout="destinations" tabs="yes" night="mixed" heading="3"]
	 */
	public static function from_shortcode( array $attributes ): self {
		return new self(
			self::modes( explode( ',', (string) ( $attributes['modes'] ?? '' ) ) ),
			self::layout( (string) ( $attributes['layout'] ?? '' ) ),
			in_array( strtolower( (string) ( $attributes['tabs'] ?? '' ) ), array( '1', 'yes', 'true' ), true ),
			'mixed' !== strtolower( (string) ( $attributes['night'] ?? '' ) ),
			self::heading_level( (int) ( $attributes['heading'] ?? 2 ) )
		);
	}

	/**
	 * @param string[] $values
	 * @return TransportMode[]
	 */
	private static function modes( array $values ): array {
		return array_values( array_filter( array_map( fn( $value ) => TransportMode::tryFrom( trim( (string) $value ) ), $values ) ) );
	}

	private static function layout( string $value ): string {
		return self::DESTINATIONS === $value ? self::DESTINATIONS : self::COMPACT;
	}

	private static function heading_level( int $level ): int {
		return min( 6, max( 2, $level ) );
	}
}
