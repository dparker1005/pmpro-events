/**
 * The My Events block.
 *
 * A dynamic block rendered by pmpro_events_render_my_events(), so the editor
 * preview shows the real output for the logged-in user. Written against the
 * wp globals rather than built — it is small enough that a build step would
 * be more code than the block.
 */
( function ( blocks, element, serverSideRender, i18n ) {
	var __ = i18n.__;

	blocks.registerBlockType( 'pmpro-events/my-events', {
		title: __( 'My Events', 'pmpro-events' ),
		description: __(
			'The upcoming events that the current member is registered for.',
			'pmpro-events'
		),
		icon: 'calendar-alt',
		category: 'pmpro',
		keywords: [
			__( 'events', 'pmpro-events' ),
			__( 'registrations', 'pmpro-events' ),
		],
		supports: {
			html: false,
			multiple: false,
		},
		edit: function () {
			return element.createElement( serverSideRender, {
				block: 'pmpro-events/my-events',
			} );
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender, window.wp.i18n );
