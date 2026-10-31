<?php
// SPDX-License-Identifier: GPL-2.0-or-later
// Copyright (C) 2026 PUBLICTRANSITDATA.NET


use PtdGtfsSchedules\Frontend\Icons;
use PtdGtfsSchedules\Schedule\TripCall;

defined( 'ABSPATH' ) || exit;

$trip = $args['trip'];
$now  = $args['now'];

$note = function ( string $icon, string $text ): void {
	?>
	<span class="gtfs-trip__note"><?php echo Icons::svg( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?> <?php echo esc_html( $text ); ?></span>
	<?php
};

$clock = function ( DateTimeImmutable $time, bool $is_tracked ) use ( $now ): void {
	if ( ! $is_tracked ) {
		echo '<time>' . esc_html( $time->format( 'H:i' ) ) . '</time>';
		return;
	}
	?>
	<time class="<?php echo $time < $now ? 'has-left' : ''; ?>" datetime="<?php echo esc_attr( $time->format( DATE_ATOM ) ); ?>" data-clock="<?php echo esc_attr( $time->format( 'H:i' ) ); ?>"><?php echo esc_html( $time->format( 'H:i' ) ); ?></time>
	<?php
};

$time_at = function ( TripCall $call ) use ( $clock ): void {
	if ( $call->shows_arrival_and_departure() ) {
		esc_html_e( 'arr.', 'ptd-gtfs-schedules' );
		echo ' ';
		$clock( $call->arrival, false );
		echo ' · ';
		esc_html_e( 'dep.', 'ptd-gtfs-schedules' );
		echo ' ';
		$clock( $call->departure, true );
		return;
	}

	$clock( $call->time(), true );
};
?>
<div <?php echo $args['wrapper_attributes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes() or Renderer. ?>>
	<a class="gtfs-trip__back" href="<?php echo esc_url( $args['back_url'] ); ?>">
		<?php echo Icons::svg( 'arrow', 'gtfs-icon--back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?>
		<?php
		echo esc_html(
			$args['back_stop']
				/* translators: %s: stop name */
				? sprintf( __( 'Timetable at %s', 'ptd-gtfs-schedules' ), $args['back_stop']->stop_name )
				/* translators: %s: line name, e.g. "N8" */
				: sprintf( __( 'Line %s', 'ptd-gtfs-schedules' ), $args['line']->name() )
		);
		?>
	</a>

	<header class="gtfs-line__header gtfs-trip__header">
		<span class="gtfs-route gtfs-line__badge" style="<?php echo esc_attr( $trip->route->inline_style() ); ?>"><?php echo esc_html( $trip->route->name ); ?></span>
		<div>
			<p class="gtfs-trip__destination">
				<span class="gtfs-visually-hidden">
					<?php
					/* translators: %s: where the trip goes, e.g. "Jelitkowo" */
					echo esc_html( sprintf( __( 'Towards %s', 'ptd-gtfs-schedules' ), $trip->destination() ) );
					?>
				</span>
				<span aria-hidden="true"><?php echo Icons::svg( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?> <?php echo esc_html( $trip->destination() ); ?></span>
			</p>
			<p class="gtfs-trip__date"><?php echo esc_html( wp_date( get_option( 'date_format' ), $trip->service_date->getTimestamp(), $trip->service_date->getTimezone() ) ); ?></p>
			<?php if ( $args['note'] ) : ?>
				<p class="gtfs-trip__differs"><?php echo esc_html( $args['note'] ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<ol class="gtfs-trip__stops" data-gtfs-timetable aria-label="<?php esc_attr_e( 'Stops', 'ptd-gtfs-schedules' ); ?>">
		<?php foreach ( $trip->calls as $position => $call ) : ?>
			<?php
			$is_from       = $args['from'] === $position;
			$timetable_url = $args['timetable_url']( $call->stop_id );
			?>
			<li class="gtfs-trip__stop<?php echo $is_from ? ' is-chosen' : ''; ?>" <?php echo $is_from ? 'id="gtfs-trip-from" aria-current="location"' : ''; ?>>
				<span class="gtfs-trip__time"><?php $time_at( $call ); ?></span>
				<span class="gtfs-trip__name">
					<?php if ( '' !== $timetable_url ) : ?>
						<a href="<?php echo esc_url( $timetable_url ); ?>"><?php echo esc_html( $call->stop_name ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $call->stop_name ); ?>
					<?php endif; ?>
				</span>
				<?php
				if ( $call->on_request ) {
					$note( 'request', __( 'on request', 'ptd-gtfs-schedules' ) );
				}

				if ( $call->is_alighting_only() ) {
					$note( 'alight', __( 'getting off only', 'ptd-gtfs-schedules' ) );
				} elseif ( $call->is_boarding_only() ) {
					$note( 'board', __( 'getting on only', 'ptd-gtfs-schedules' ) );
				}
				?>
			</li>
		<?php endforeach; ?>
	</ol>

	<?php echo $args['attribution']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Templates::data_attribution(). ?>
</div>
