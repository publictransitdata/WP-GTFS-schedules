<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require __DIR__ . '/src/autoload.php';

use PtdGtfsSchedules\Feed\FeedDownloader;
use PtdGtfsSchedules\FeedCache;
use PtdGtfsSchedules\FeedInfo;
use PtdGtfsSchedules\Import\ImportLock;
use PtdGtfsSchedules\Import\ImportScheduler;
use PtdGtfsSchedules\Import\ImportState;
use PtdGtfsSchedules\Schema;
use PtdGtfsSchedules\Settings;

ImportScheduler::unschedule_all();
( new FeedDownloader() )->clean_up();

Schema::drop_everything();
Schema::forget_version();

FeedInfo::clear();
ImportState::forget();
ImportLock::release();
FeedCache::forget_all();
delete_option( Settings::OPTION );
