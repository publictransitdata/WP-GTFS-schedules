<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace GtfsTimetables\Network;

defined( 'ABSPATH' ) || exit;

final class LineGroup {

	public const NIGHT = 'night';

	/**
	 * @param string $key   A transport mode, or NIGHT.
	 * @param string $label
	 * @param Line[] $lines In network order.
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly array $lines,
	) {}
}
