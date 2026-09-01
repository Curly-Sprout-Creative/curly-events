<?php
/**
 * Events admin: native meta box (date/time/location/recurrence) + save handler.
 *
 * @package CurlyEvents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes', 'custom_events_add_meta_boxes' );

/**
 * Register the event details meta box.
 */
function custom_events_add_meta_boxes() {
	add_meta_box(
		'event_details_meta_box',
		'Event Details & Recurrence Settings',
		'custom_events_render_meta_box',
		'events',
		'normal',
		'high'
	);
}

/**
 * Render the meta box.
 *
 * @param WP_Post $post Current post.
 */
function custom_events_render_meta_box( $post ) {
	wp_nonce_field( 'custom_events_save_meta', 'custom_events_nonce' );

	$start_date      = get_post_meta( $post->ID, '_event_start_date', true );
	$start_time      = get_post_meta( $post->ID, '_event_start_time', true );
	$end_time_text   = get_post_meta( $post->ID, '_event_end_time_text', true );
	$location        = get_post_meta( $post->ID, '_event_location', true );
	$rec_type        = get_post_meta( $post->ID, '_event_rec_type', true ) ?: 'none';
	$weekly_days     = get_post_meta( $post->ID, '_event_weekly_days', true ) ?: array();
	$monthly_type    = get_post_meta( $post->ID, '_event_monthly_type', true ) ?: 'day_num';
	$monthly_day_num = get_post_meta( $post->ID, '_event_monthly_day_num', true ) ?: '1';
	$monthly_nth_pos = get_post_meta( $post->ID, '_event_monthly_nth_pos', true ) ?: 'first';
	$monthly_nth_day = get_post_meta( $post->ID, '_event_monthly_nth_day', true ) ?: 'sun';
	$end_date        = get_post_meta( $post->ID, '_event_end_date', true );
	$exceptions      = get_post_meta( $post->ID, '_event_exceptions', true );

	$days_of_week = array(
		'sun' => 'Sun', 'mon' => 'Mon', 'tue' => 'Tue',
		'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat',
	);
	?>
	<style>
		.evt-row { margin-bottom: 18px; }
		.evt-row label { font-weight: bold; display: block; margin-bottom: 5px; }
		.evt-row input[type="text"], .evt-row input[type="date"], .evt-row select, .evt-row textarea { width: 100%; max-width: 400px; }
		.evt-inline-group { display: flex; gap: 15px; flex-wrap: wrap; }
		.evt-checkbox-group label { font-weight: normal; display: inline-block; margin-right: 12px; }
		.evt-sub-box { background: #f9f9f9; border: 1px solid #e5e5e5; padding: 12px; margin-top: 8px; max-width: 600px; border-radius: 4px; }
	</style>

	<div class="evt-inline-group">
		<div class="evt-row">
			<label for="event_start_date">Start Date</label>
			<input type="date" id="event_start_date" name="event_start_date" value="<?php echo esc_attr( $start_date ); ?>" required />
		</div>
		<div class="evt-row">
			<label for="event_start_time">Start Time</label>
			<select id="event_start_time" name="event_start_time">
				<option value="">-- Select Time --</option>
				<?php
				for ( $h = 0; $h < 24; $h++ ) {
					for ( $m = 0; $m < 60; $m += 15 ) {
						$time_val   = sprintf( '%02d:%02d', $h, $m );
						$time_label = date( 'g:i A', strtotime( "2000-01-01 $time_val" ) );
						echo '<option value="' . esc_attr( $time_val ) . '" ' . selected( $start_time, $time_val, false ) . '>' . esc_html( $time_label ) . '</option>';
					}
				}
				?>
			</select>
		</div>
		<div class="evt-row">
			<label for="event_end_time_text">End Time / Time Notes (Flexible text)</label>
			<input type="text" id="event_end_time_text" name="event_end_time_text" value="<?php echo esc_attr( $end_time_text ); ?>" placeholder="e.g. 9:00 PM or Until Late" />
		</div>
	</div>

	<div class="evt-row">
		<label for="event_location">Location Details</label>
		<?php
		wp_editor(
			$location,
			'event_location',
			array(
				'textarea_name' => 'event_location',
				'media_buttons' => false,
				'textarea_rows' => 4,
				'teeny'         => true,
				'quicktags'     => false,
			)
		);
		?>
	</div>

	<hr style="margin: 20px 0;">
	<h3>Recurrence Settings</h3>

	<div class="evt-row">
		<label for="event_rec_type">Recurrence Mode</label>
		<select id="event_rec_type" name="event_rec_type">
			<option value="none" <?php selected( $rec_type, 'none' ); ?>>Does Not Repeat</option>
			<option value="weekly" <?php selected( $rec_type, 'weekly' ); ?>>Weekly / Multi-Day Schedule</option>
			<option value="monthly" <?php selected( $rec_type, 'monthly' ); ?>>Monthly Schedule</option>
		</select>
	</div>

	<!-- WEEKLY CONTROLS -->
	<div id="evt-weekly-wrap" class="evt-sub-box" style="display:none;">
		<label>Weekly Repeat Days:</label>
		<div class="evt-checkbox-group">
			<?php foreach ( $days_of_week as $slug => $label ) : ?>
				<label>
					<input type="checkbox" name="event_weekly_days[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $weekly_days, true ) ); ?> />
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- MONTHLY CONTROLS -->
	<div id="evt-monthly-wrap" class="evt-sub-box" style="display:none;">
		<label>Monthly Recurrence Type:</label>
		<div class="evt-row" style="margin-top:8px;">
			<label style="font-weight:normal;">
				<input type="radio" name="event_monthly_type" value="day_num" <?php checked( $monthly_type, 'day_num' ); ?> /> Specific Day of Month (1-31)
			</label>
			<input type="number" name="event_monthly_day_num" min="1" max="31" value="<?php echo esc_attr( $monthly_day_num ); ?>" style="width:80px;" />
		</div>
		<div class="evt-row">
			<label style="font-weight:normal;">
				<input type="radio" name="event_monthly_type" value="nth_day" <?php checked( $monthly_type, 'nth_day' ); ?> /> Relative Weekday
			</label>
			<select name="event_monthly_nth_pos" style="width:110px;">
				<option value="first" <?php selected( $monthly_nth_pos, 'first' ); ?>>1st</option>
				<option value="second" <?php selected( $monthly_nth_pos, 'second' ); ?>>2nd</option>
				<option value="third" <?php selected( $monthly_nth_pos, 'third' ); ?>>3rd</option>
				<option value="fourth" <?php selected( $monthly_nth_pos, 'fourth' ); ?>>4th</option>
				<option value="last" <?php selected( $monthly_nth_pos, 'last' ); ?>>Last</option>
			</select>
			<select name="event_monthly_nth_day" style="width:140px;">
				<?php foreach ( $days_of_week as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $monthly_nth_day, $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<!-- COMMON RECURRENCE SETTINGS -->
	<div id="evt-common-rec-wrap" style="display:none;">
		<div class="evt-row" style="margin-top:15px;">
			<label for="event_end_date">Recurrence End Date (Optional)</label>
			<input type="date" id="event_end_date" name="event_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
		</div>

		<div class="evt-row">
			<label for="event_exceptions">Exception Dates to Skip</label>
			<textarea id="event_exceptions" name="event_exceptions" rows="3" placeholder="YYYY-MM-DD (One per line, e.g. 2026-12-25)"><?php echo esc_textarea( $exceptions ); ?></textarea>
			<span class="description">Enter dates in YYYY-MM-DD format, separated by line breaks.</span>
		</div>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var recSelect = document.getElementById('event_rec_type');
		var weeklyBox = document.getElementById('evt-weekly-wrap');
		var monthlyBox = document.getElementById('evt-monthly-wrap');
		var commonBox = document.getElementById('evt-common-rec-wrap');

		function updateRecurrenceUI() {
			var val = recSelect.value;
			if (val === 'none') {
				weeklyBox.style.display = 'none';
				monthlyBox.style.display = 'none';
				commonBox.style.display = 'none';
			} else if (val === 'weekly') {
				weeklyBox.style.display = 'block';
				monthlyBox.style.display = 'none';
				commonBox.style.display = 'block';
			} else if (val === 'monthly') {
				weeklyBox.style.display = 'none';
				monthlyBox.style.display = 'block';
				commonBox.style.display = 'block';
			}
		}

		if (recSelect) {
			recSelect.addEventListener('change', updateRecurrenceUI);
			updateRecurrenceUI();
		}
	});
	</script>
	<?php
}

add_action( 'save_post_events', 'custom_events_save_meta_data' );

/**
 * Save the event meta on post save.
 *
 * @param int $post_id Post ID.
 */
function custom_events_save_meta_data( $post_id ) {
	if ( ! isset( $_POST['custom_events_nonce'] ) || ! wp_verify_nonce( $_POST['custom_events_nonce'], 'custom_events_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = array(
		'_event_start_date'      => 'sanitize_text_field',
		'_event_start_time'      => 'sanitize_text_field',
		'_event_end_time_text'   => 'sanitize_text_field',
		'_event_location'        => 'wp_kses_post',
		'_event_rec_type'        => 'sanitize_text_field',
		'_event_monthly_type'    => 'sanitize_text_field',
		'_event_monthly_day_num' => 'absint',
		'_event_monthly_nth_pos' => 'sanitize_text_field',
		'_event_monthly_nth_day' => 'sanitize_text_field',
		'_event_end_date'        => 'sanitize_text_field',
		'_event_exceptions'      => 'sanitize_textarea_field',
	);

	foreach ( $fields as $key => $sanitizer ) {
		$field_name = ltrim( $key, '_' );
		if ( isset( $_POST[ $field_name ] ) ) {
			update_post_meta( $post_id, $key, call_user_func( $sanitizer, $_POST[ $field_name ] ) );
		}
	}

	$weekly_days = isset( $_POST['event_weekly_days'] ) ? array_map( 'sanitize_text_field', $_POST['event_weekly_days'] ) : array();
	update_post_meta( $post_id, '_event_weekly_days', $weekly_days );
}