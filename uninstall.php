<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require __DIR__ . '/src/autoload.php';

use GtfsTimetables\Feed\FeedDownloader;
use GtfsTimetables\FeedInfo;
use GtfsTimetables\Import\ImportLock;
use GtfsTimetables\Import\ImportScheduler;
use GtfsTimetables\Import\ImportState;
use GtfsTimetables\Schema;
use GtfsTimetables\Settings;

ImportScheduler::unschedule_all();
( new FeedDownloader() )->clean_up();

Schema::drop_everything();
Schema::forget_version();

FeedInfo::clear();
ImportState::forget();
ImportLock::release();
delete_option( Settings::OPTION );
