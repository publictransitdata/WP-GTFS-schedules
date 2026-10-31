<?php
/**
 * Stop timetable for one day.
 *
 * Copy to your-theme/ptd-gtfs-schedules/timetable.php to customise.
 *
 * @var array $args {
 *     @type object                                        $stop        Row from the stops table.
 *     @type DateTimeImmutable                             $date
 *     @type \PtdGtfsSchedules\Schedule\TimetableSection[]   $sections
 *     @type callable                                      $trip_url    TimetableSection, TimetableDeparture => address of its trip; empty without a line page.
 *     @type string                                        $attribution Escaped HTML.
 * }
 */

use PtdGtfsSchedules\Frontend\Shortcodes;

defined( 'ABSPATH' ) || exit;

$date     = $args['date'];
$day_link = fn( DateTimeImmutable $day ) => add_query_arg( Shortcodes::DATE_QUERY_ARG, $day->format( 'Y-m-d' ) );
?>
<section class="gtfs-timetable">
	<h3 class="gtfs-timetable__stop"><?php echo esc_html( $args['stop']->stop_name ); ?></h3>

	<nav class="gtfs-timetable__days" aria-label="<?php esc_attr_e( 'Choose a day', 'ptd-gtfs-schedules' ); ?>">
		<a href="<?php echo esc_url( $day_link( $date->modify( '-1 day' ) ) ); ?>" rel="nofollow">&larr; <?php esc_html_e( 'Previous day', 'ptd-gtfs-schedules' ); ?></a>
		<strong><?php echo esc_html( wp_date( get_option( 'date_format' ), $date->getTimestamp(), $date->getTimezone() ) ); ?></strong>
		<a href="<?php echo esc_url( $day_link( $date->modify( '+1 day' ) ) ); ?>" rel="nofollow"><?php esc_html_e( 'Next day', 'ptd-gtfs-schedules' ); ?> &rarr;</a>
	</nav>

	<?php if ( ! $args['sections'] ) : ?>
		<p class="gtfs-timetable__empty"><?php esc_html_e( 'No departures on this day.', 'ptd-gtfs-schedules' ); ?></p>
	<?php endif; ?>

	<?php foreach ( $args['sections'] as $section ) : ?>
		<div class="gtfs-timetable__section">
			<h4>
				<span class="gtfs-route" style="<?php echo esc_attr( $section->route->inline_style() ); ?>"><?php echo esc_html( $section->route->name ); ?></span>
				<?php echo esc_html( $section->headsign ); ?>
			</h4>
			<table class="gtfs-timetable__hours">
				<tbody>
					<?php foreach ( $section->departure_rows() as list( $hour, $departures ) ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $hour ); ?></th>
							<td>
								<?php foreach ( $departures as $departure ) : ?>
									<?php $trip_url = $args['trip_url']( $section, $departure ); ?>
									<?php if ( '' !== $trip_url ) : ?>
										<a class="gtfs-trip-link" href="<?php echo esc_url( $trip_url ); ?>" rel="nofollow"><?php echo esc_html( $departure->minute ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $departure->minute ); ?>
									<?php endif; ?>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endforeach; ?>

	<?php echo $args['attribution']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Templates::data_attribution(). ?>
</section>
