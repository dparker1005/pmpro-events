<?php
/**
 * The "My Events" section on the PMPro account page.
 *
 * @since 2.0
 */

/**
 * Whether the current request is the PMPro account page.
 *
 * @since 2.0
 *
 * @return bool Whether this is the account page.
 */
function pmpro_events_is_account_page() {
	$account_page_id = (int) get_option( 'pmpro_account_page_id' );

	return ! empty( $account_page_id ) && is_page( $account_page_id );
}

/**
 * Append the My Events section to the pmpro_account shortcode.
 *
 * PMPro's account shortcode has no hook for adding a whole section, so we
 * append to its rendered output instead.
 *
 * @since 2.0
 *
 * @param string       $output The shortcode output.
 * @param string       $tag    The shortcode name.
 * @param array|string $attr   The shortcode attributes.
 * @return string The filtered output.
 */
function pmpro_events_account_shortcode( $output, $tag, $attr ) {
	if ( 'pmpro_account' !== $tag || ! is_user_logged_in() ) {
		return $output;
	}

	// Respect an explicit sections list, the way the built-in sections do.
	$attr = is_array( $attr ) ? $attr : array();
	if ( isset( $attr['sections'] ) || isset( $attr['section'] ) ) {
		$sections = isset( $attr['sections'] ) ? $attr['sections'] : $attr['section'];
		$sections = array_map( 'trim', explode( ',', (string) $sections ) );

		if ( ! in_array( 'events', $sections, true ) ) {
			return $output;
		}
	}

	$section = pmpro_events_get_my_events_html();

	if ( empty( $section ) ) {
		return $output;
	}

	// The section belongs inside the .pmpro wrapper the shortcode closes with,
	// since every style it uses is nested under that class. Match the closing
	// tag loosely so a whitespace change in PMPro doesn't move the section.
	$closing = '/<\/div>\s*(<!--\s*end pmpro\s*-->)?\s*$/';

	if ( preg_match( $closing, $output, $matches, PREG_OFFSET_CAPTURE ) ) {
		$position = $matches[0][1];

		return substr( $output, 0, $position ) . $section . substr( $output, $position );
	}

	// No wrapper to splice into, so bring our own rather than lose the styles.
	return $output . sprintf(
		'<div class="%s">%s</div>',
		esc_attr( pmpro_get_element_class( 'pmpro' ) ),
		$section
	);
}
add_filter( 'do_shortcode_tag', 'pmpro_events_account_shortcode', 10, 3 );

/**
 * Build the My Events section.
 *
 * @since 2.0
 *
 * @return string The markup, or an empty string when the member has no upcoming events.
 */
function pmpro_events_get_my_events_html() {
	$registrations = PMProEvents_Event_Registration::get_registrations( array(
		'user_id' => get_current_user_id(),
		'status'  => 'active',
	) );

	if ( empty( $registrations ) ) {
		return '';
	}

	// Only upcoming events, in the order they happen.
	$events = array();
	foreach ( $registrations as $registration ) {
		$event = $registration->get_event();

		if ( $event->exists() && ! $event->has_passed() && 'publish' === get_post_status( $event->get_id() ) ) {
			$events[] = $event;
		}
	}

	if ( empty( $events ) ) {
		return '';
	}

	usort( $events, function( $a, $b ) {
		return strcmp( (string) $a->start_utc, (string) $b->start_utc );
	} );

	$plural = pmpro_events_get_label( 'plural' );

	ob_start();
	?>
	<section id="pmpro_account-events" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_section', 'pmpro_account-events' ) ); ?>">
		<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_section_title pmpro_font-x-large' ) ); ?>">
			<?php
			/* translators: %s: the plural event label, e.g. "Events". */
			echo esc_html( sprintf( __( 'My %s', 'pmpro-events' ), $plural ) );
			?>
		</h2>
		<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card' ) ); ?>">
			<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
				<ul class="pmpro_events_account_list">
					<?php foreach ( $events as $event ) { ?>
						<li>
							<a href="<?php echo esc_url( $event->get_permalink() ); ?>"><?php echo esc_html( $event->get_title() ); ?></a>
							<?php
							$date_range = $event->get_date_range();
							if ( ! empty( $date_range ) ) {
								?>
								<span class="pmpro_events_account_date"><?php echo esc_html( $date_range ); ?></span>
								<?php
							}
							?>
						</li>
					<?php } ?>
				</ul>
			</div> <!-- end pmpro_card_content -->
		</div> <!-- end pmpro_card -->
	</section> <!-- end pmpro_account-events -->
	<?php

	return ob_get_clean();
}
