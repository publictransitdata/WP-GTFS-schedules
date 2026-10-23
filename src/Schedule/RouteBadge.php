<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Schedule;

defined( 'ABSPATH' ) || exit;

/**
 * The route name as riders see it on vehicles, in the operator's colours.
 */
final class RouteBadge {

	public function __construct(
		public readonly string $route_id,
		public readonly string $name,
		public readonly string $color,
		public readonly string $text_color,
	) {}

	public static function from_row( object $row ): self {
		$name = '' !== $row->route_short_name ? $row->route_short_name : $row->route_long_name;

		return new self( $row->route_id, $name, $row->route_color, $row->route_text_color );
	}

	/**
	 * Colours were validated as six hex digits during import.
	 */
	public function inline_style(): string {
		if ( '' === $this->color ) {
			return '';
		}

		$text_color = '' !== $this->text_color ? $this->text_color : '000000';

		return "background-color:#{$this->color};color:#{$text_color}";
	}
}
