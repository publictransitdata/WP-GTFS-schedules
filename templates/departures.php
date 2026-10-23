<?php
/**
 * Departure board.
 *
 * Copy to your-theme/gtfs-timetables/departures.php to customise.
 * Keep the outer element and its data-refresh-url: the board refreshes itself through it.
 *
 * @var array $args {
 *     @type object                                     $stop        Row from the stops table.
 *     @type DateTimeImmutable                          $now
 *     @type \GtfsTimetables\Schedule\Departure[]       $departures
 *     @type string                                     $refresh_url
 *     @type string                                     $attribution Escaped HTML.
 * }
 */

defined( 'ABSPATH' ) || exit;

$now = $args['now'];
?>
<section class="gtfs-departures" data-refresh-url="<?php echo esc_url( $args['refresh_url'] ); ?>" aria-live="polite">
	<h3 class="gtfs-departures__stop"><?php echo esc_html( $args['stop']->stop_name ); ?></h3>

	<?php if ( ! $args['departures'] ) : ?>
		<p class="gtfs-departures__empty"><?php esc_html_e( 'No departures scheduled.', 'gtfs-timetables' ); ?></p>
	<?php else : ?>
		<table class="gtfs-departures__list">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Line', 'gtfs-timetables' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Direction', 'gtfs-timetables' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Departs', 'gtfs-timetables' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $args['departures'] as $departure ) : ?>
					<tr>
						<td><span class="gtfs-route" style="<?php echo esc_attr( $departure->route->inline_style() ); ?>"><?php echo esc_html( $departure->route->name ); ?></span></td>
						<td><?php echo esc_html( $departure->headsign ); ?></td>
						<td class="gtfs-departures__time">
							<time datetime="<?php echo esc_attr( $departure->time->format( DATE_ATOM ) ); ?>"><?php echo esc_html( $departure->label( $now ) ); ?></time>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php echo $args['attribution']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Templates::data_attribution(). ?>
</section>
