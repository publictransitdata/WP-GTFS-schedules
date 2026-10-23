<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

/*
 * Fails when a bundled translation leaves a string of the template untranslated,
 * or when its compiled .mo / .l10n.php files no longer match the .po file.
 * Fix both with "composer i18n".
 */

use Gettext\Translation;
use Gettext\Translations;

require dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * The same shape WordPress uses in .l10n.php files: "context\4original" => "translation\0plural…".
 *
 * @return array<string, string>
 */
function gtfs_timetables_messages( Translations $translations ): array {
	$messages = array();

	foreach ( $translations as $translation ) {
		if ( ! $translation->hasTranslation() ) {
			continue;
		}

		$key = ( $translation->hasContext() ? $translation->getContext() . "\4" : '' ) . $translation->getOriginal();

		$messages[ $key ] = implode( "\0", array_merge( array( $translation->getTranslation() ), $translation->getPluralTranslations() ) );
	}

	ksort( $messages );

	return $messages;
}

function gtfs_timetables_is_translated( ?Translation $translation, Translation $original, int $plural_forms ): bool {
	if ( ! $translation || ! $translation->hasTranslation() ) {
		return false;
	}

	if ( ! $original->hasPlural() ) {
		return true;
	}

	return count( array_filter( $translation->getPluralTranslations( $plural_forms - 1 ), 'strlen' ) ) === $plural_forms - 1;
}

$languages = dirname( __DIR__ ) . '/languages';
$template  = Translations::fromPoFile( "{$languages}/gtfs-timetables.pot" );
$problems  = array();

foreach ( glob( "{$languages}/*.po" ) as $po_file ) {
	$name         = basename( $po_file, '.po' );
	$translations = Translations::fromPoFile( $po_file );
	$plural_forms = (int) ( $translations->getPluralForms()[0] ?? 2 );

	foreach ( $template as $original ) {
		if ( ! gtfs_timetables_is_translated( $translations->find( $original ), $original, $plural_forms ) ) {
			$problems[] = "{$name}.po: not translated: " . $original->getOriginal();
		}
	}

	$expected = gtfs_timetables_messages( $translations );
	$compiled = array(
		"{$name}.mo"       => is_readable( "{$languages}/{$name}.mo" ) ? gtfs_timetables_messages( Translations::fromMoFile( "{$languages}/{$name}.mo" ) ) : null,
		"{$name}.l10n.php" => is_readable( "{$languages}/{$name}.l10n.php" ) ? ( require "{$languages}/{$name}.l10n.php" )['messages'] : null,
	);

	foreach ( $compiled as $file => $messages ) {
		if ( is_array( $messages ) ) {
			ksort( $messages );
		}

		if ( $messages !== $expected ) {
			$problems[] = null === $messages ? "{$file} is missing." : "{$file} does not match {$name}.po.";
		}
	}
}

if ( $problems ) {
	fwrite( STDERR, implode( PHP_EOL, $problems ) . PHP_EOL . 'Translate the strings in languages/*.po, then run: composer i18n' . PHP_EOL );
	exit( 1 );
}

echo 'Translations are complete and compiled.' . PHP_EOL;
