<?php
/**
 * Curly Events settings: contact link (URL + text) and calendar default state.
 *
 * The settings page lives under Events > Settings and is editable by anyone
 * with `edit_pages` (Administrators and Editors). The contact link is used in
 * the "no upcoming events" notices; the calendar toggle itself is an Oxygen
 * element (.calendar-toggle / .calendar-toggle-wrap) whose open/close behavior
 * is driven by this plugin's front-end script.
 *
 * @package CurlyEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CURLY_EVENTS_SETTINGS_GROUP', 'curly_events_settings' );
define( 'CURLY_EVENTS_OPTION_CONTACT_URL', 'curly_events_contact_url' );
define( 'CURLY_EVENTS_OPTION_CONTACT_TEXT', 'curly_events_contact_text' );
define( 'CURLY_EVENTS_OPTION_CALENDAR_OPEN', 'curly_events_calendar_open' );
define( 'CURLY_EVENTS_DEFAULT_CONTACT_TEXT', 'sign up for our email list on the Contact page' );

add_action( 'admin_menu', 'custom_events_register_settings_page' );
add_action( 'admin_init', 'custom_events_register_settings' );

/**
 * Add the Events > Settings submenu.
 */
function custom_events_register_settings_page() {
	add_submenu_page(
		'edit.php?post_type=events',
		'Curly Events Settings',
		'Settings',
		'edit_pages',
		'curly-events-settings',
		'custom_events_render_settings_page'
	);
}

/**
 * Register the settings and their sanitizers.
 */
function custom_events_register_settings() {
	register_setting(
		CURLY_EVENTS_SETTINGS_GROUP,
		CURLY_EVENTS_OPTION_CONTACT_URL,
		array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		)
	);

	register_setting(
		CURLY_EVENTS_SETTINGS_GROUP,
		CURLY_EVENTS_OPTION_CONTACT_TEXT,
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => CURLY_EVENTS_DEFAULT_CONTACT_TEXT,
		)
	);

	register_setting(
		CURLY_EVENTS_SETTINGS_GROUP,
		CURLY_EVENTS_OPTION_CALENDAR_OPEN,
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => 1,
		)
	);
}

/**
 * Render the Events > Settings page.
 */
function custom_events_render_settings_page() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}

	$contact_url   = (string) get_option( CURLY_EVENTS_OPTION_CONTACT_URL, '' );
	$contact_text  = (string) get_option( CURLY_EVENTS_OPTION_CONTACT_TEXT, CURLY_EVENTS_DEFAULT_CONTACT_TEXT );
	$calendar_open = (int) get_option( CURLY_EVENTS_OPTION_CALENDAR_OPEN, 1 );
	$default_url   = home_url( '/contact/' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Curly Events Settings', 'curly-events' ); ?></h1>

		<form method="post" action="options.php">
			<?php settings_fields( CURLY_EVENTS_SETTINGS_GROUP ); ?>
			<table class="form-table" role="presentation">
				<tr valign="top">
					<th scope="row">
						<label for="<?php echo esc_attr( CURLY_EVENTS_OPTION_CONTACT_URL ); ?>"><?php esc_html_e( 'Contact link', 'curly-events' ); ?></label>
					</th>
					<td>
						<input type="url" class="regular-text" id="<?php echo esc_attr( CURLY_EVENTS_OPTION_CONTACT_URL ); ?>" name="<?php echo esc_attr( CURLY_EVENTS_OPTION_CONTACT_URL ); ?>" value="<?php echo esc_attr( $contact_url ); ?>" placeholder="<?php echo esc_attr( $default_url ); ?>" />
						<p class="description">
							<?php
							printf(
								/* translators: %s: default contact URL. */
								esc_html__( 'Shown in the "no upcoming events" notice. Leave blank to use %s.', 'curly-events' ),
								'<code>' . esc_html( $default_url ) . '</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							);
							?>
						</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="<?php echo esc_attr( CURLY_EVENTS_OPTION_CONTACT_TEXT ); ?>"><?php esc_html_e( 'Contact link text', 'curly-events' ); ?></label>
					</th>
					<td>
						<input type="text" class="regular-text" id="<?php echo esc_attr( CURLY_EVENTS_OPTION_CONTACT_TEXT ); ?>" name="<?php echo esc_attr( CURLY_EVENTS_OPTION_CONTACT_TEXT ); ?>" value="<?php echo esc_attr( $contact_text ); ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Calendar', 'curly-events' ); ?></th>
					<td>
						<input type="hidden" name="<?php echo esc_attr( CURLY_EVENTS_OPTION_CALENDAR_OPEN ); ?>" value="0" />
						<label for="<?php echo esc_attr( CURLY_EVENTS_OPTION_CALENDAR_OPEN ); ?>">
							<input type="checkbox" id="<?php echo esc_attr( CURLY_EVENTS_OPTION_CALENDAR_OPEN ); ?>" name="<?php echo esc_attr( CURLY_EVENTS_OPTION_CALENDAR_OPEN ); ?>" value="1" <?php checked( 1, $calendar_open ); ?> />
							<?php esc_html_e( 'Open the calendar on page load', 'curly-events' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When checked, the calendar opens automatically where the .calendar-toggle / .calendar-toggle-wrap elements are used. Uncheck to start closed (click to open).', 'curly-events' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
