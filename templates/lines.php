<?php
/**
 * Lines of the network, grouped by transport mode, with night lines last.
 *
 * Copy to your-theme/ptd-gtfs-schedules/lines.php to customise.
 * With tabs on, line-tabs.js turns each group into a tab; it relies on the classes and ids below.
 * In the compact layout destinations stay in the markup for screen readers, hidden by CSS.
 *
 * @var array $args {
 *     @type \PtdGtfsSchedules\Network\LineGroup[]          $groups
 *     @type \PtdGtfsSchedules\Frontend\LineListSettings    $settings
 *     @type callable                                     $line_url           Line => address of its page, '' when the site has none.
 *     @type string                                       $wrapper_attributes Escaped HTML attributes.
 *     @type string                                       $id_prefix          Unique on the page.
 *     @type string                                       $attribution        Escaped HTML.
 * }
 */

use PtdGtfsSchedules\Frontend\Icons;
use PtdGtfsSchedules\Frontend\LineListSettings;

defined( 'ABSPATH' ) || exit;

$heading_tag = 'h' . $args['settings']->heading_level;
$compact     = LineListSettings::COMPACT === $args['settings']->layout;
?>
<div <?php echo $args['wrapper_attributes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes() or Renderer. ?>>
	<?php foreach ( $args['groups'] as $group ) : ?>
		<?php $group_id = "{$args['id_prefix']}-{$group->key}"; ?>
		<section class="gtfs-lines__group gtfs-lines__group--<?php echo esc_attr( $group->key ); ?>" id="<?php echo esc_attr( $group_id ); ?>" aria-labelledby="<?php echo esc_attr( "{$group_id}-heading" ); ?>">
			<<?php echo tag_escape( $heading_tag ); ?> class="gtfs-lines__heading" id="<?php echo esc_attr( "{$group_id}-heading" ); ?>"><?php echo Icons::for_group( $group->key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG markup. ?> <?php echo esc_html( $group->label ); ?></<?php echo tag_escape( $heading_tag ); ?>>

			<ul class="gtfs-lines__list">
				<?php foreach ( $group->lines as $line ) : ?>
					<?php
					$destinations = implode( ' – ', $line->destinations );
					$url          = $args['line_url']( $line );
					?>
					<li class="gtfs-lines__line">
						<?php if ( $url ) : ?>
							<a class="gtfs-lines__link" href="<?php echo esc_url( $url ); ?>">
						<?php endif; ?>

						<span class="gtfs-route" style="<?php echo esc_attr( $line->badge->inline_style() ); ?>" <?php echo $compact ? 'title="' . esc_attr( $destinations ) . '"' : ''; ?>><?php echo esc_html( $line->name() ); ?></span>
						<?php if ( '' !== $destinations ) : ?>
							<span class="gtfs-lines__destinations"><?php echo esc_html( $destinations ); ?></span>
						<?php endif; ?>

						<?php if ( $url ) : ?>
							</a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endforeach; ?>

	<?php echo $args['attribution']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Templates::data_attribution(). ?>
</div>
