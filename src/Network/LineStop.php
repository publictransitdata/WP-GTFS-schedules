<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

namespace PtdGtfsSchedules\Network;

defined( 'ABSPATH' ) || exit;

final class LineStop {

	public function __construct(
		public readonly string $stop_id,
		public readonly string $name,
	) {}
}
