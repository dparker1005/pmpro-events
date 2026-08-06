<?php
/**
 * The My Events block and shortcode.
 *
 * Lists the upcoming events that the current member is registered for. Sites
 * place it wherever it fits — the Membership Account page is the natural home,
 * but nothing is appended anywhere automatically.
 *
 * @since 2.0
 */

/**
 * Register the My Events block.
 *
 * A dynamic block: the editor preview and the frontend both render through
 * pmpro_events_render_my_events().
 *
 * @since 2.0
 */
function pmpro_events_register_my_events_block() {
	wp_register_script(
		'pmpro-events-my-events-block',
		PMPRO_EVENTS_URL . '/js/my-events-block.js',
		array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-server-side-render', 'wp-i18n' ),
		PMPRO_EVENTS_VERSION,
		true
	);
	wp_set_script_translations( 'pmpro-events-my-events-block', 'pmpro-events', PMPRO_EVENTS_DIR . '/languages' );

	// The frontend styles under an editor-only handle, injected into the
	// editor iframe so the preview isn't unstyled. Registering an already
	// registered handle is a no-op, so both blocks can claim it.
	wp_register_style(
		'pmpro-events-editor-preview',
		PMPRO_EVENTS_URL . '/css/frontend.css',
		array(),
		PMPRO_EVENTS_VERSION
	);

	register_block_type(
		'pmpro-events/my-events',
		array(
			'api_version'     => 3,
			'editor_script'   => 'pmpro-events-my-events-block',
			'editor_style'    => 'pmpro-events-editor-preview',
			'render_callback' => 'pmpro_events_render_my_events',
		)
	);
}
add_action( 'init', 'pmpro_events_register_my_events_block' );
add_shortcode( 'pmpro_events_my_events', 'pmpro_events_render_my_events' );

/**
 * Render the My Events block and shortcode.
 *
 * @since 2.0
 *
 * @return string The markup, or an empty string for logged-out visitors.
 */
function pmpro_events_render_my_events() {
	$section = pmpro_events_get_my_events_html();

	if ( empty( $section ) ) {
		return '';
	}

	// PMPro nests all of its styles under the .pmpro class, so the section has
	// to be wrapped in that container to pick them up.
	return sprintf(
		'<div class="%s">%s</div>',
		esc_attr( pmpro_get_element_class( 'pmpro' ) ),
		$section
	);
}

/**
 * Build the My Events section.
 *
 * @since 2.0
 *
 * @return string The markup, or an empty string for logged-out visitors.
 */
function pmpro_events_get_my_events_html() {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$registrations = PMProEvents_Event_Registration::get_registrations( array(
		'user_id' => get_current_user_id(),
		'status'  => 'active',
	) );

	// Only upcoming events, in the order they happen.
	$events = array();
	foreach ( $registrations as $registration ) {
		$event = $registration->get_event();

		if ( $event->exists() && ! $event->has_passed() && 'publish' === get_post_status( $event->get_id() ) ) {
			$events[] = $event;
		}
	}

	usort( $events, function( $a, $b ) {
		return strcmp( (string) $a->start_utc, (string) $b->start_utc );
	} );

	$plural = pmpro_events_get_label( 'plural' );

	ob_start();
	?>
	<section id="pmpro_events_my_events" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_section', 'pmpro_events_my_events' ) ); ?>">
		<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_section_title pmpro_font-x-large' ) ); ?>">
			<?php
			/* translators: %s: the plural event label, e.g. "Events". */
			echo esc_html( sprintf( __( 'My %s', 'pmpro-events' ), $plural ) );
			?>
		</h2>
		<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card' ) ); ?>">
			<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
				<?php if ( empty( $events ) ) { ?>
					<p>
						<?php
						/* translators: %s: the plural event label, lowercased, e.g. "events". */
						echo esc_html( sprintf( __( 'You are not registered for any upcoming %s.', 'pmpro-events' ), pmpro_events_get_label( 'plural_lowercase' ) ) );
						?>
					</p>
				<?php } else { ?>
					<ul class="pmpro_events_my_events_list">
						<?php foreach ( $events as $event ) { ?>
							<li>
								<a href="<?php echo esc_url( $event->get_permalink() ); ?>"><?php echo esc_html( $event->get_title() ); ?></a>
								<?php
								$date_range = $event->get_date_range();
								if ( ! empty( $date_range ) ) {
									?>
									<span class="pmpro_events_my_events_date"><?php echo esc_html( $date_range ); ?></span>
									<?php
								}
								?>
							</li>
						<?php } ?>
					</ul>
				<?php } ?>
			</div> <!-- end pmpro_card_content -->
		</div> <!-- end pmpro_card -->
	</section> <!-- end pmpro_events_my_events -->
	<?php

	return ob_get_clean();
}
