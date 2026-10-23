<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET

/*
 * Fails when a bundled translation leaves a string of the template untranslated,
 * or when its compiled files no longer match the .po file:
 * .mo and .l10n.php hold the strings used in PHP, one .json per script the strings used in JavaScript.
 * Fix both with "composer i18n".
 */

use Gettext\Translation;
use Gettext\Translations;

require dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * Keyed the way WordPress keys compiled translations: "context\4original" => [translation, plural forms…].
 *
 * @return array<string, string[]>
 */
function gtfs_timetables_messages( iterable $translations, callable $is_wanted ): array {
	$messages = array();

	foreach ( $translations as $translation ) {
		if ( $translation->hasTranslation() && $is_wanted( $translation ) ) {
			$key              = ( $translation->hasContext() ? $translation->getContext() . "\4" : '' ) . $translation->getOriginal();
			$messages[ $key ] = array_merge( array( $translation->getTranslation() ), $translation->getPluralTranslations() );
		}
	}

	ksort( $messages );

	return $messages;
}

/**
 * @return string[] Files the string appears in.
 */
function gtfs_timetables_sources( Translation $translation ): array {
	return array_unique( array_map( fn( array $reference ) => $reference[0], $translation->getReferences() ) );
}

function gtfs_timetables_is_script( string $file ): bool {
	return str_ends_with( $file, '.js' );
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

/**
 * @return array<string, string[]>|null
 */
function gtfs_timetables_compiled_messages( string $file ): ?array {
	if ( ! is_readable( $file ) ) {
		return null;
	}

	$messages = match ( pathinfo( $file, PATHINFO_EXTENSION ) ) {
		'mo'   => gtfs_timetables_messages( Translations::fromMoFile( $file ), fn() => true ),
		'php'  => array_map( fn( string $value ) => explode( "\0", $value ), ( require $file )['messages'] ),
		'json' => json_decode( (string) file_get_contents( $file ), true )['locale_data']['messages'],
	};
	unset( $messages[''] );
	ksort( $messages );

	return $messages;
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

	$used_in_php = fn( Translation $translation ) => ! $translation->getReferences()
		|| array_filter( gtfs_timetables_sources( $translation ), fn( string $file ) => ! gtfs_timetables_is_script( $file ) );

	$expected = array(
		"{$name}.mo"       => gtfs_timetables_messages( $translations, $used_in_php ),
		"{$name}.l10n.php" => gtfs_timetables_messages( $translations, $used_in_php ),
	);

	$scripts = array();
	foreach ( $translations as $translation ) {
		$scripts = array_merge( $scripts, array_filter( gtfs_timetables_sources( $translation ), 'gtfs_timetables_is_script' ) );
	}

	foreach ( array_unique( $scripts ) as $script ) {
		$expected[ "{$name}-" . md5( $script ) . '.json' ] = gtfs_timetables_messages(
			$translations,
			fn( Translation $translation ) => in_array( $script, gtfs_timetables_sources( $translation ), true )
		);
	}

	foreach ( $expected as $file => $messages ) {
		$compiled = gtfs_timetables_compiled_messages( "{$languages}/{$file}" );

		if ( $compiled !== $messages ) {
			$problems[] = null === $compiled ? "{$file} is missing." : "{$file} does not match {$name}.po.";
		}
	}
}

if ( $problems ) {
	fwrite( STDERR, implode( PHP_EOL, $problems ) . PHP_EOL . 'Translate the strings in languages/*.po, then run: composer i18n' . PHP_EOL );
	exit( 1 );
}

echo 'Translations are complete and compiled.' . PHP_EOL;
