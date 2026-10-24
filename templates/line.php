<?php
/**
 * The page of one line: its directions, the stops of the chosen direction and the timetable of the chosen stop.
 * Every choice is a plain link, so each stop's timetable has its own address.
 *
 * Copy to your-theme/gtfs-timetables/line.php to customise.
 *
 * @var array $args {
 *     @type \GtfsTimetables\Network\Line                $line
 *     @type \GtfsTimetables\Network\LineDirection[]     $directions
 *     @type \GtfsTimetables\Network\LineDirection|null  $direction          The chosen one.
 *     @type string                                      $stop_id            The chosen stop.
 *     @type string                                      $timetable          HTML of the chosen stop's timetable, '' at the last stop.
 *     @type callable                                    $direction_url      Position in $directions => address.
 *     @type callable                                    $stop_url           Stop id => address.
 *     @type string                                      $wrapper_attributes Escaped HTML attributes.
 *     @type string                                      $attribution        Escaped HTML.
 * }
 */

defined( 'ABSPATH' ) || exit;

$line      = $args['line'];
$direction = $args['direction'];
?>
<div <?php echo $args['wrapper_attributes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes() or Renderer. ?>>
	<header class="gtfs-line__header">
		<span class="gtfs-route gtfs-line__badge" style="<?php echo esc_attr( $line->badge->inline_style() ); ?>"><?php echo esc_html( $line->name() ); ?></span>
		<div>
			<p class="gtfs-line__mode"><?php echo esc_html( $line->mode->line_label() ); ?></p>
			<?php if ( $line->destinations ) : ?>
				<p class="gtfs-line__destinations"><?php echo esc_html( implode( ' – ', $line->destinations ) ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( null === $direction ) : ?>
		<p class="gtfs-notice"><?php esc_html_e( 'This line has no trips in the current timetable.', 'gtfs-timetables' ); ?></p>
		<?php echo $args['attribution']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Templates::data_attribution(). ?>
	<?php else : ?>
		<nav class="gtfs-line__directions" aria-label="<?php esc_attr_e( 'Directions', 'gtfs-timetables' ); ?>">
			<?php foreach ( $args['directions'] as $position => $option ) : ?>
				<a class="gtfs-line__direction" href="<?php echo esc_url( $args['direction_url']( $position ) ); ?>" <?php echo $option === $direction ? 'aria-current="page"' : ''; ?>>
					<?php
					/* translators: %s: where the line goes, e.g. "Jelitkowo" */
					echo esc_html( sprintf( __( 'Towards %s', 'gtfs-timetables' ), $option->headsign ) );
					?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="gtfs-line__body">
			<ol class="gtfs-line__stops" aria-label="<?php esc_attr_e( 'Stops', 'gtfs-timetables' ); ?>">
				<?php foreach ( $direction->stops as $stop ) : ?>
					<?php $is_chosen = $stop->stop_id === $args['stop_id']; ?>
					<li class="gtfs-line__stop<?php echo $is_chosen ? ' is-chosen' : ''; ?>">
						<?php if ( $direction->ends_at( $stop->stop_id ) ) : ?>
							<span><?php echo esc_html( $stop->name ); ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( $args['stop_url']( $stop->stop_id ) ); ?>" <?php echo $is_chosen ? 'aria-current="location"' : ''; ?>><?php echo esc_html( $stop->name ); ?></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>

			<div class="gtfs-line__timetable">
				<?php if ( '' !== $args['timetable'] ) : ?>
					<?php echo $args['timetable']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered and escaped by the timetable template. ?>
				<?php else : ?>
					<p class="gtfs-notice"><?php esc_html_e( 'This is the last stop of the line in this direction, so it has no departures.', 'gtfs-timetables' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
