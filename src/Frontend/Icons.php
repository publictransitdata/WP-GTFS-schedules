<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Frontend;

use PtdGtfsSchedules\Network\LineGroup;
use PtdGtfsSchedules\Network\TransportMode;

defined( 'ABSPATH' ) || exit;

final class Icons {

	private const PATHS = array(
		'arrow'      => '<path d="M7.293 1.293a1 1 0 0 1 1.414 0l6 6a1 1 0 0 1 0 1.414l-6 6a1 1 0 0 1-1.414-1.414L11.586 9H2a1 1 0 0 1 0-2h9.586L7.293 2.707a1 1 0 0 1 0-1.414"/>',
		'turn-back'  => '<path d="M6.7 1.3a1 1 0 0 1 0 1.4L5.4 4H10a5 5 0 0 1 0 10H6a1 1 0 1 1 0-2h4a3 3 0 0 0 0-6H5.4l1.3 1.3a1 1 0 0 1-1.4 1.4l-3-3a1 1 0 0 1 0-1.4l3-3a1 1 0 0 1 1.4 0"/>',
		'board'      => '<path d="M9 2h5v12H9v-2h3V4H9z"/><path d="M5.7 4.3 9.4 8l-3.7 3.7-1.4-1.4L5.6 9H1V7h4.6L4.3 5.7z"/>',
		'alight'     => '<path d="M2 2h5v2H4v8h3v2H2z"/><path d="M10.7 4.3 14.4 8l-3.7 3.7-1.4-1.4 1.3-1.3H6V7h4.6L9.3 5.7z"/>',
		'request'    => '<g transform="scale(.6667)" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M20 16V8.5c0-.828-.641-1.5-1.48-1.5C18 7 17 7.3 17 8.5v-3c0-.828-.641-1.5-1.48-1.5c-.507 0-1.52.3-1.52 1.5v-2c0-.828-.641-1.5-1.48-1.5c-.84 0-1.52.672-1.52 1.5v2C11 4.3 10.007 4 9.5 4C8.66 4 8 4.691 8 5.52V14m3-8.5V11m3-5.5V11m3-5.5V11"/><path d="M20 16c0 4-3.134 6-7 6s-5.196-1-8.196-6l-1.571-2.605c-.536-.868-.107-1.994.881-2.314a1.66 1.66 0 0 1 1.818.552L8 14.033"/></g>',
		'bus'        => '<path fill-rule="evenodd" d="M4 1h8a2 2 0 0 1 2 2v9a1 1 0 0 1-1 1v1.5a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V13H5v1.5a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V13a1 1 0 0 1-1-1V3a2 2 0 0 1 2-2m0 2v5h8V3zm.5 7a1 1 0 1 0 0 2 1 1 0 0 0 0-2m7 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2"/>',
		'trolleybus' => '<path d="M5.6 4 8.4.3l.8.6L6.8 4zm2.5 0L10.9.3l.8.6L9.3 4z"/><path fill-rule="evenodd" d="M4 4h8a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1v1.5a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V13H5v1.5a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V13a1 1 0 0 1-1-1V6a2 2 0 0 1 2-2m0 2v3h8V6zm.5 4a1 1 0 1 0 0 2 1 1 0 0 0 0-2m7 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2"/>',
		'tram'       => '<path d="M4 0h8v1H8.6l1 2H8.5l-1-2H4z"/><path fill-rule="evenodd" d="M5 3h6a2 2 0 0 1 2 2v7a2 2 0 0 1-1.3 1.9L13 16h-1.5l-1.2-2H5.7l-1.2 2H3l1.3-2.1A2 2 0 0 1 3 12V5a2 2 0 0 1 2-2m0 2v4h6V5zm.5 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2m5 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2"/>',
		'metro'      => '<path fill-rule="evenodd" d="M3 1h10a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2m1 3v8h1.7V7.3L8 10.4l2.3-3.1V12H12V4h-1.6L8 7.4 5.6 4z"/>',
		'rail'       => '<path fill-rule="evenodd" d="M5 1h6a3 3 0 0 1 3 3v7a2 2 0 0 1-1.4 1.9L14 16h-1.7l-1.2-3H4.9l-1.2 3H2l1.4-3.1A2 2 0 0 1 2 11V4a3 3 0 0 1 3-3M4 4v3h8V4zm1 5a1 1 0 1 0 0 2 1 1 0 0 0 0-2m6 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2"/>',
		'ferry'      => '<path d="M7 1h2v2h3l1 3.6 2 .6L13 12H3L1 7.2l2-.6L4 3h3zM5.6 5l-.4 1.4L8 5.6l2.8.8L10.4 5z"/><path d="M1 14.2c1.2 0 1.6-.9 2.8-.9s1.6.9 2.8.9 1.6-.9 2.8-.9 1.6.9 2.8.9 1.6-.9 2.8-.9v1.4c-1.2 0-1.6.9-2.8.9s-1.6-.9-2.8-.9-1.6.9-2.8.9-1.6-.9-2.8-.9-1.6.9-2.8.9z"/>',
		'cable'      => '<path d="M.9 3.2 15 .7l.2 1L1.1 4.2z"/><path fill-rule="evenodd" d="M7.5 2.4h1V5H11a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2.5zM5 7v3h6V7z"/>',
		'night'      => '<path d="M6.5 1.2A7 7 0 1 0 14.8 9.5 5.6 5.6 0 0 1 6.5 1.2"/>',
		'other'      => '<path d="M8 2a6 6 0 1 1 0 12A6 6 0 0 1 8 2"/>',
	);

	public static function svg( string $name, string $extra_class = '' ): string {
		return sprintf(
			'<svg class="gtfs-icon %s" viewBox="0 0 16 16" width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false">%s</svg>',
			esc_attr( $extra_class ),
			self::PATHS[ $name ] ?? self::PATHS['other']
		);
	}

	public static function for_mode( TransportMode $mode ): string {
		return self::svg( $mode->value );
	}

	public static function for_group( string $group_key ): string {
		return self::svg( LineGroup::NIGHT === $group_key ? 'night' : $group_key );
	}
}
