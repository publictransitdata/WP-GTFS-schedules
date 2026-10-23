<?php
/**
 * Lines of the network, grouped by transport mode, with night lines last.
 *
 * Copy to your-theme/gtfs-timetables/lines.php to customise.
 * With tabs on, line-tabs.js turns each group into a tab; it relies on the classes and ids below.
 *
 * @var array $args {
 *     @type \GtfsTimetables\Network\LineGroup[]          $groups
 *     @type \GtfsTimetables\Frontend\LineListSettings    $settings
 *     @type string                                       $wrapper_attributes Escaped HTML attributes.
 *     @type string                                       $id_prefix          Unique on the page.
 *     @type string                                       $attribution        Escaped HTML.
 * }
 */

use GtfsTimetables\Frontend\LineListSettings;

defined( 'ABSPATH' ) || exit;

$heading_tag       = 'h' . $args['settings']->heading_level;
$show_destinations = LineListSettings::DESTINATIONS === $args['settings']->layout;
?>
<div <?php echo $args['wrapper_attributes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes() or Renderer. ?>>
	<?php foreach ( $args['groups'] as $group ) : ?>
		<?php $group_id = "{$args['id_prefix']}-{$group->key}"; ?>
		<section class="gtfs-lines__group gtfs-lines__group--<?php echo esc_attr( $group->key ); ?>" id="<?php echo esc_attr( $group_id ); ?>" aria-labelledby="<?php echo esc_attr( "{$group_id}-heading" ); ?>">
			<<?php echo tag_escape( $heading_tag ); ?> class="gtfs-lines__heading" id="<?php echo esc_attr( "{$group_id}-heading" ); ?>"><?php echo esc_html( $group->label ); ?></<?php echo tag_escape( $heading_tag ); ?>>

			<ul class="gtfs-lines__list">
				<?php foreach ( $group->lines as $line ) : ?>
					<?php $destinations = implode( ' – ', $line->destinations ); ?>
					<li class="gtfs-lines__line">
						<span class="gtfs-route" style="<?php echo esc_attr( $line->badge->inline_style() ); ?>" <?php echo $show_destinations ? '' : 'title="' . esc_attr( $destinations ) . '"'; ?>><?php echo esc_html( $line->name() ); ?></span>
						<?php if ( $show_destinations && '' !== $destinations ) : ?>
							<span class="gtfs-lines__destinations"><?php echo esc_html( $destinations ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endforeach; ?>

	<?php echo $args['attribution']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Templates::data_attribution(). ?>
</div>
