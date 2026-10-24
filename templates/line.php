<?php
/**
 * The page of one line: its directions, a diagram of the chosen direction's route and the
 * timetable of the chosen stop. Every choice is a plain link, so each stop's timetable has its own address.
 *
 * Icons are hidden from screen readers; the text next to them, visible or visually hidden, says the same.
 * timetable-clock.js keeps the greyed-out departures and the next departure current; it relies on
 * data-gtfs-timetable, data-gtfs-next-departure and the datetime and data-clock of each <time>.
 * route-map.js draws the lines of the diagram from the classes of .gtfs-route-map and the position
 * of each .gtfs-route-map__dot; without it, the stylesheet draws simpler lines.
 *
 * Copy to your-theme/ptd-gtfs-schedules/line.php to customise.
 *
 * @var array $args {
 *     @type \PtdGtfsSchedules\Network\Line                    $line
 *     @type \PtdGtfsSchedules\Network\LineDirection[]         $directions
 *     @type \PtdGtfsSchedules\Network\LineDirection|null      $direction          The chosen one.
 *     @type \PtdGtfsSchedules\Network\LineStop|null           $stop               The chosen stop.
 *     @type DateTimeImmutable                               $date               The day the timetable shows.
 *     @type DateTimeImmutable                               $now
 *     @type \PtdGtfsSchedules\Schedule\MarkedTimetable|null   $timetable          Null at the last stop.
 *     @type callable                                        $direction_url      Position in $directions => address.
 *     @type callable                                        $stop_url           Stop id => address.
 *     @type callable                                        $day_url            DateTimeImmutable => address.
 *     @type string                                          $wrapper_attributes Escaped HTML attributes.
 *     @type string                                          $attribution        Escaped HTML.
 * }
 */

use PtdGtfsSchedules\Frontend\Icons;
use PtdGtfsSchedules\Network\LineBranch;
use PtdGtfsSchedules\Network\LineStop;

defined( 'ABSPATH' ) || exit;

$line      = $args['line'];
$direction = $args['direction'];
$stop      = $args['stop'];
$timetable = $args['timetable'];

$note = function ( string $icon, string $text ): void {
	?>
	<span class="gtfs-route-map__note"><?php echo Icons::svg( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?> <?php echo esc_html( $text ); ?></span>
	<?php
};

/**
 * One stop on the diagram, with what riders need to know about it.
 *
 * @param LineStop $route_stop
 * @param int|null $position     On the usual route; null on a branch.
 * @param bool     $branch_start Whether the trips of its branch start here.
 */
$stop_item = function ( LineStop $route_stop, ?int $position = null, bool $branch_start = false ) use ( $args, $direction, $stop, $note ): void {
	$stop_id       = $route_stop->stop_id;
	$is_chosen     = $stop && $stop->stop_id === $stop_id;
	$is_first      = 0 === $position || $branch_start;
	$is_last       = $direction->ends_at( $stop_id );
	$can_board     = $direction->can_board_at( $stop_id );
	$can_alight    = $direction->can_alight_at( $stop_id );
	$has_timetable = $can_board && ! $is_last;
	?>
	<span class="gtfs-route-map__name<?php echo $is_chosen ? ' is-chosen' : ''; ?>">
		<span class="gtfs-route-map__dot"></span>
		<?php if ( $has_timetable ) : ?>
			<a href="<?php echo esc_url( $args['stop_url']( $stop_id ) ); ?>" <?php echo $is_chosen ? 'aria-current="location"' : ''; ?>><?php echo esc_html( $route_stop->name ); ?></a>
		<?php else : ?>
			<?php echo esc_html( $route_stop->name ); ?>
		<?php endif; ?>
	</span>
	<?php
	if ( $direction->stops_on_request_at( $stop_id ) ) {
		$note( 'request', __( 'on request', 'ptd-gtfs-schedules' ) );
	}

	// Stops where nobody gets on or off are left off the diagram altogether (LineDirections::describe()).
	if ( ! $can_board && ! $is_last ) {
		$note( 'alight', __( 'getting off only', 'ptd-gtfs-schedules' ) );
	} elseif ( ! $can_alight && ! $is_first ) {
		$note( 'board', __( 'getting on only', 'ptd-gtfs-schedules' ) );
	}

	if ( null !== $position && $direction->is_partial_at( $position ) && ( $is_first || $is_last ) ) {
		$note( 'turn-back', $is_first ? __( 'not every trip starts here', 'ptd-gtfs-schedules' ) : __( 'not every trip goes this far', 'ptd-gtfs-schedules' ) );
	}

	$starts_here = $branch_start || ( null !== $position && in_array( $position, $direction->starts_late_at, true ) );
	$ends_here   = null === $position ? $is_last : in_array( $position, $direction->ends_early_at, true );

	if ( $starts_here ) {
		$note( 'turn-back', __( 'some trips start here', 'ptd-gtfs-schedules' ) );
	}

	if ( $ends_here ) {
		$note( 'turn-back', __( 'some trips end here', 'ptd-gtfs-schedules' ) );
	}
};

/**
 * Stops that only some trips call at. Branches before the first stop and after the last one carry
 * the route's line on; the others are drawn beside it, joined to it where the trips leave or join it.
 */
$branch = function ( LineBranch $branch ) use ( $direction, $stop_item ): void {
	$kind      = $direction->kind_of( $branch );
	$last_stop = $branch->stops[ array_key_last( $branch->stops ) ];
	$caption   = match ( $kind ) {
		LineBranch::DETOUR        => sprintf(
			/* translators: %s: stop where the trips are back on the usual route */
			__( 'Some trips go this way and are back on the route at %s', 'ptd-gtfs-schedules' ),
			$direction->stops[ (int) $branch->rejoins_at ]->name
		),
		LineBranch::ENDS          => sprintf(
			/* translators: %s: stop off the usual route where the trips end */
			__( 'Some trips leave the route here and end at %s', 'ptd-gtfs-schedules' ),
			$last_stop->name
		),
		LineBranch::JOINS         => sprintf(
			/* translators: %s: stop on the usual route where the trips join it */
			__( 'Some trips start elsewhere and join the route at %s', 'ptd-gtfs-schedules' ),
			$direction->stops[ (int) $branch->rejoins_at ]->name
		),
		LineBranch::CONTINUES     => __( 'Some trips carry on to', 'ptd-gtfs-schedules' ),
		default                   => __( 'Some trips start further away, at', 'ptd-gtfs-schedules' ),
	};
	?>
	<div class="gtfs-route-map__branch is-<?php echo esc_attr( $kind ); ?>">
		<p class="gtfs-route-map__caption"><span><?php echo esc_html( $caption ); ?></span></p>
		<ol class="gtfs-route-map__branch-stops">
			<?php foreach ( $branch->stops as $index => $branch_stop ) : ?>
				<li class="gtfs-route-map__stop"><?php $stop_item( $branch_stop, null, 0 === $index && $branch->joins_route() ); ?></li>
			<?php endforeach; ?>
		</ol>
	</div>
	<?php
};

$last_position = $direction ? (int) array_key_last( $direction->stops ) : 0;

/**
 * Whether only some trips run between the usual stops at these positions; positions off the route count as such.
 *
 * @param int $from
 * @param int $to
 */
$some_trips_between = fn( int $from, int $to ): bool => $from < 0 || $to > $last_position || $direction->is_partial_at( $from ) || $direction->is_partial_at( $to );

/**
 * Classes for the line through a usual stop: the part coming in and the part going on,
 * either missing or thinner where only some trips run.
 *
 * @param int $position
 */
$line_classes = function ( int $position ) use ( $direction, $last_position, $some_trips_between ): string {
	$classes = array(
		'is-start'          => 0 === $position && ! $direction->branches_joining_at( 0 ),
		'is-end'            => $last_position === $position && ! $direction->branches_after( $last_position ),
		'is-partial-before' => $some_trips_between( $position - 1, $position ),
		'is-partial-after'  => $some_trips_between( $position, $position + 1 ),
	);

	return implode( ' ', array_keys( array_filter( $classes ) ) );
};
?>
<div <?php echo $args['wrapper_attributes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes() or Renderer. ?>>
	<header class="gtfs-line__header">
		<span class="gtfs-route gtfs-line__badge" style="<?php echo esc_attr( $line->badge->inline_style() ); ?>"><?php echo esc_html( $line->name() ); ?></span>
		<div>
			<p class="gtfs-line__mode"><?php echo Icons::for_mode( $line->mode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?> <?php echo esc_html( $line->mode->line_label() ); ?></p>
			<?php if ( $line->destinations ) : ?>
				<p class="gtfs-line__destinations"><?php echo esc_html( implode( ' – ', $line->destinations ) ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( null === $direction ) : ?>
		<p class="gtfs-notice"><?php esc_html_e( 'This line has no trips in the current timetable.', 'ptd-gtfs-schedules' ); ?></p>
		<?php echo $args['attribution']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Templates::data_attribution(). ?>
	<?php else : ?>
		<nav class="gtfs-line__directions" aria-label="<?php esc_attr_e( 'Directions', 'ptd-gtfs-schedules' ); ?>">
			<?php foreach ( $args['directions'] as $position => $option ) : ?>
				<a class="gtfs-line__direction" href="<?php echo esc_url( $args['direction_url']( $position ) ); ?>" <?php echo $option === $direction ? 'aria-current="page"' : ''; ?>>
					<span class="gtfs-visually-hidden">
						<?php
						/* translators: %s: where the line goes, e.g. "Jelitkowo" */
						echo esc_html( sprintf( __( 'Towards %s', 'ptd-gtfs-schedules' ), $option->headsign ) );
						?>
					</span>
					<span aria-hidden="true"><?php echo Icons::svg( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?> <?php echo esc_html( $option->headsign ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="gtfs-line__body">
			<div class="gtfs-line__route">
				<?php if ( $direction->partial_at || $direction->branches ) : ?>
					<ul class="gtfs-route-map__legend" aria-hidden="true">
						<li class="is-every-trip"><?php esc_html_e( 'every trip', 'ptd-gtfs-schedules' ); ?></li>
						<li class="is-some-trips"><?php esc_html_e( 'some trips only', 'ptd-gtfs-schedules' ); ?></li>
					</ul>
				<?php endif; ?>

				<ol class="gtfs-route-map" aria-label="<?php esc_attr_e( 'Stops', 'ptd-gtfs-schedules' ); ?>">
					<?php foreach ( $direction->branches_joining_at( 0 ) as $starting ) : ?>
						<li class="gtfs-route-map__extension"><?php $branch( $starting ); ?></li>
					<?php endforeach; ?>

					<?php foreach ( $direction->stops as $position => $route_stop ) : ?>
						<?php if ( $position > 0 && $direction->branches_joining_at( $position ) ) : ?>
							<li class="gtfs-route-map__beside<?php echo $some_trips_between( $position - 1, $position ) ? ' is-partial' : ''; ?>">
								<?php foreach ( $direction->branches_joining_at( $position ) as $joining ) : ?>
									<?php $branch( $joining ); ?>
								<?php endforeach; ?>
							</li>
						<?php endif; ?>

						<li class="gtfs-route-map__stop <?php echo esc_attr( $line_classes( $position ) ); ?>">
							<?php $stop_item( $route_stop, $position ); ?>

							<?php if ( $position < $last_position ) : ?>
								<?php foreach ( $direction->branches_after( $position ) as $leaving ) : ?>
									<?php $branch( $leaving ); ?>
								<?php endforeach; ?>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>

					<?php foreach ( $direction->branches_after( $last_position ) as $continuing ) : ?>
						<li class="gtfs-route-map__extension"><?php $branch( $continuing ); ?></li>
					<?php endforeach; ?>
				</ol>
			</div>

			<section class="gtfs-line-timetable" data-gtfs-timetable aria-label="<?php esc_attr_e( 'Timetable', 'ptd-gtfs-schedules' ); ?>">
				<h3 class="gtfs-line-timetable__stop"><?php echo esc_html( $stop ? $stop->name : '' ); ?></h3>

				<nav class="gtfs-line-timetable__days" aria-label="<?php esc_attr_e( 'Choose a day', 'ptd-gtfs-schedules' ); ?>">
					<a href="<?php echo esc_url( $args['day_url']( $args['date']->modify( '-1 day' ) ) ); ?>" rel="nofollow"><?php echo Icons::svg( 'arrow', 'gtfs-icon--back' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?> <?php esc_html_e( 'Previous day', 'ptd-gtfs-schedules' ); ?></a>
					<strong><?php echo esc_html( wp_date( get_option( 'date_format' ), $args['date']->getTimestamp(), $args['date']->getTimezone() ) ); ?></strong>
					<a href="<?php echo esc_url( $args['day_url']( $args['date']->modify( '+1 day' ) ) ); ?>" rel="nofollow"><?php esc_html_e( 'Next day', 'ptd-gtfs-schedules' ); ?> <?php echo Icons::svg( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?></a>
				</nav>

				<?php if ( null === $timetable && $stop && $direction->ends_at( $stop->stop_id ) ) : ?>
					<p class="gtfs-notice"><?php esc_html_e( 'This is the last stop of the line in this direction, so it has no departures.', 'ptd-gtfs-schedules' ); ?></p>
				<?php elseif ( null === $timetable ) : ?>
					<p class="gtfs-notice"><?php esc_html_e( 'Riders cannot get on this line at this stop, so it has no departures.', 'ptd-gtfs-schedules' ); ?></p>
				<?php elseif ( $timetable->is_empty() ) : ?>
					<p class="gtfs-notice"><?php esc_html_e( 'No departures on this day.', 'ptd-gtfs-schedules' ); ?></p>
				<?php else : ?>
					<?php $next = $timetable->next_departure( $args['now'] ); ?>
					<p class="gtfs-line-timetable__next" data-gtfs-next-departure <?php echo $next ? '' : 'hidden'; ?>>
						<?php esc_html_e( 'Next departure:', 'ptd-gtfs-schedules' ); ?>
						<strong><?php echo esc_html( $next ? $next->time->format( 'H:i' ) : '' ); ?></strong>
					</p>

					<table class="gtfs-line-timetable__hours">
						<tbody>
							<?php foreach ( $timetable->rows() as list( $hour, $departures ) ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( $hour ); ?></th>
									<td>
										<?php foreach ( $departures as $departure ) : ?>
											<span class="gtfs-line-timetable__departure">
												<time class="<?php echo esc_attr( trim( ( $departure->has_left( $args['now'] ) ? 'has-left' : '' ) . ( $departure === $next ? ' is-next' : '' ) ) ); ?>" datetime="<?php echo esc_attr( $departure->time->format( DATE_ATOM ) ); ?>" data-clock="<?php echo esc_attr( $departure->time->format( 'H:i' ) ); ?>"><?php echo esc_html( $departure->minute ); ?></time>
												<?php if ( $departure->mark ) : ?>
													<span class="gtfs-mark" aria-hidden="true"><?php echo esc_html( $departure->mark ); ?></span>
													<span class="gtfs-visually-hidden">(<?php echo esc_html( (string) $departure->note ); ?>)</span>
												<?php endif; ?>
											</span>
										<?php endforeach; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( $timetable->legend() ) : ?>
						<dl class="gtfs-legend">
							<?php foreach ( $timetable->legend() as $mark => $note ) : ?>
								<div>
									<dt><?php echo esc_html( $mark ); ?></dt>
									<dd><?php echo esc_html( $note ); ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>
				<?php endif; ?>

				<?php echo $args['attribution']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Templates::data_attribution(). ?>
			</section>
		</div>
	<?php endif; ?>
</div>
