<?php
/**
 * Registration and cancellation emails.
 *
 * The emails are built on PMPro's email template system so that sites can edit
 * the subject and body under Memberships > Email Templates, and send test
 * copies from the same screen.
 *
 * @since 2.0
 */

/**
 * Load the email template classes.
 *
 * PMPro added the email template system in 3.4, so this is a no-op on older
 * versions and no emails are sent there.
 *
 * @since 2.0
 *
 * @return bool Whether the templates are available.
 */
function pmpro_events_load_email_templates() {
	if ( ! class_exists( 'PMPro_Email_Template' ) ) {
		return false;
	}

	if ( ! class_exists( 'PMProEvents_Email_Template_Registration' ) ) {
		require_once( PMPRO_EVENTS_DIR . '/modules/default/class-pmproevents-email-template-registration.php' );
	}

	if ( ! class_exists( 'PMProEvents_Email_Template_Cancellation' ) ) {
		require_once( PMPRO_EVENTS_DIR . '/modules/default/class-pmproevents-email-template-cancellation.php' );
	}

	return true;
}

/**
 * Register the event email templates.
 *
 * @since 2.0
 *
 * @param array $email_templates The registered templates (slug => class name).
 * @return array The filtered templates.
 */
function pmpro_events_register_email_templates( $email_templates ) {
	if ( ! pmpro_events_load_email_templates() ) {
		return $email_templates;
	}

	$email_templates['pmpro_events_registration']  = 'PMProEvents_Email_Template_Registration';
	$email_templates['pmpro_events_cancellation']  = 'PMProEvents_Email_Template_Cancellation';

	return $email_templates;
}
add_filter( 'pmpro_email_templates', 'pmpro_events_register_email_templates' );

/**
 * Build the shared template variables for an event email.
 *
 * Falls back to bracketed placeholders so that the test emails sent from the
 * Email Templates screen still render sensibly with no real registration.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return array The template variables.
 */
function pmpro_events_get_email_variables( $event ) {
	if ( empty( $event ) || ! $event->exists() ) {
		return array(
			'pmpro_events_event_title'       => '[' . esc_html( pmpro_events_get_label( 'singular' ) ) . ']',
			'pmpro_events_event_date'        => '[' . esc_html__( 'Date', 'pmpro-events' ) . ']',
			'pmpro_events_event_location'    => '[' . esc_html__( 'Location', 'pmpro-events' ) . ']',
			'pmpro_events_event_url'         => home_url(),
			'pmpro_events_event_link'        => '<a href="' . esc_url( home_url() ) . '">' . esc_html( pmpro_events_get_label( 'singular' ) ) . '</a>',
			'pmpro_events_event_meeting_url' => '',
		);
	}

	$date = $event->get_date_range();
	if ( ! empty( $date ) && ! $event->all_day ) {
		$date .= ' ' . pmpro_events_get_timezone_abbreviation( $event );
	}

	$location = $event->get_location_summary();

	// The meeting URL only goes to someone who holds a spot, which is exactly
	// who receives the registration email.
	$meeting_url = $event->is_virtual() ? $event->virtual_url : '';

	return array(
		'pmpro_events_event_title'       => $event->get_title(),
		'pmpro_events_event_date'        => empty( $date ) ? '' : $date,
		'pmpro_events_event_location'    => empty( $location ) ? '' : $location,
		'pmpro_events_event_url'         => $event->get_permalink(),
		'pmpro_events_event_link'        => '<a href="' . esc_url( $event->get_permalink() ) . '">' . esc_html( $event->get_title() ) . '</a>',
		'pmpro_events_event_meeting_url' => $meeting_url,
	);
}

/**
 * Get the shared variable descriptions shown when editing an event email.
 *
 * @since 2.0
 *
 * @param bool $include_meeting_url Whether to document the meeting URL variable.
 * @return array The variables paired with their descriptions.
 */
function pmpro_events_get_email_variable_descriptions( $include_meeting_url = false ) {
	$singular = pmpro_events_get_label( 'singular_lowercase' );
	$liquid   = class_exists( 'PMPro_Liquid_Renderer' );

	$variables = array(
		/* translators: %s: the singular event label, lowercased, e.g. "event". */
		'pmpro_events_event_title'    => sprintf( __( 'The title of the %s.', 'pmpro-events' ), $singular ),
		/* translators: %s: the singular event label, lowercased, e.g. "event". */
		'pmpro_events_event_date'     => sprintf( __( 'When the %s takes place, including the timezone.', 'pmpro-events' ), $singular ),
		/* translators: 1: the singular event label, lowercased, 2: the same label repeated. */
		'pmpro_events_event_location' => sprintf( __( 'Where the %1$s takes place, or "Online" for a virtual %2$s.', 'pmpro-events' ), $singular, $singular ),
		/* translators: %s: the singular event label, lowercased, e.g. "event". */
		'pmpro_events_event_url'      => sprintf( __( 'The URL of the %s.', 'pmpro-events' ), $singular ),
		/* translators: %s: the singular event label, lowercased, e.g. "event". */
		'pmpro_events_event_link'     => sprintf( __( 'A link to the %s, using its title as the link text.', 'pmpro-events' ), $singular ),
	);

	if ( $include_meeting_url ) {
		/* translators: 1: the singular event label, lowercased, 2: the plural event label, lowercased. */
		$variables['pmpro_events_event_meeting_url'] = sprintf( __( 'The meeting or stream URL for a virtual %1$s. Empty for in-person %2$s.', 'pmpro-events' ), $singular, pmpro_events_get_label( 'plural_lowercase' ) );
	}

	$described = array();
	foreach ( $variables as $key => $description ) {
		$token = $liquid ? '{{ ' . $key . ' }}' : '!!' . $key . '!!';
		$described[ $token ] = esc_html( $description );
	}

	return $described;
}

/**
 * Send the confirmation email when a member registers for an event.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event_Registration $registration The new registration.
 * @param PMProEvents_Event              $event        The event.
 */
function pmpro_events_send_registration_email( $registration, $event ) {
	/**
	 * Filter whether to send the registration confirmation email.
	 *
	 * @since 2.0
	 *
	 * @param bool                           $send         Whether to send the email. Default true.
	 * @param PMProEvents_Event_Registration $registration The registration.
	 * @param PMProEvents_Event              $event        The event.
	 */
	if ( ! apply_filters( 'pmpro_events_send_registration_email', true, $registration, $event ) ) {
		return;
	}

	if ( ! pmpro_events_load_email_templates() ) {
		return;
	}

	$email = new PMProEvents_Email_Template_Registration( $registration, $event );
	$email->send();
}
add_action( 'pmpro_events_registration_complete', 'pmpro_events_send_registration_email', 10, 2 );

/**
 * Send the cancellation email when a registration is cancelled.
 *
 * This fires for a member cancelling their own spot, an admin cancelling it,
 * and a registration released because the member lost access.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event_Registration $registration The cancelled registration.
 */
function pmpro_events_send_cancellation_email( $registration ) {
	/**
	 * Filter whether to send the cancellation email.
	 *
	 * @since 2.0
	 *
	 * @param bool                           $send         Whether to send the email. Default true.
	 * @param PMProEvents_Event_Registration $registration The registration.
	 */
	if ( ! apply_filters( 'pmpro_events_send_cancellation_email', true, $registration ) ) {
		return;
	}

	if ( ! pmpro_events_load_email_templates() ) {
		return;
	}

	$email = new PMProEvents_Email_Template_Cancellation( $registration, $registration->get_event() );
	$email->send();
}
add_action( 'pmpro_events_registration_cancelled', 'pmpro_events_send_cancellation_email' );
