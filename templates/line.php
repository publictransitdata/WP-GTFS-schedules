<?php
/**
 * The page of one line.
 *
 * Copy to your-theme/gtfs-timetables/line.php to customise.
 *
 * @var array $args {
 *     @type \GtfsTimetables\Network\Line $line
 *     @type string                       $wrapper_attributes Escaped HTML attributes.
 *     @type string                       $attribution        Escaped HTML.
 * }
 */

defined( 'ABSPATH' ) || exit;

$line = $args['line'];
?>
<div <?php echo $args['wrapper_attributes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes() or Renderer. ?>>
	<header class="gtfs-line__header">
		<span class="gtfs-route gtfs-line__badge" style="<?php echo esc_attr( $line->badge->inline_style() ); ?>"><?php echo esc_html( $line->name() ); ?></span>
		<div>
			<p class="gtfs-line__mode"><?php echo esc_html( $line->mode->label() ); ?></p>
			<?php if ( $line->destinations ) : ?>
				<p class="gtfs-line__destinations"><?php echo esc_html( implode( ' – ', $line->destinations ) ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<?php echo $args['attribution']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Templates::data_attribution(). ?>
</div>
