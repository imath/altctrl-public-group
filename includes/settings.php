<?php
/**
 * Plugin settings.
 *
 * @package AltPublicGroupCtrl\includes
 *
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Plugin's settings into the BuddyPress Groups settings section.
 *
 * @since 2.0.0
 */
function apgc_settings() {
	add_settings_field(
		'_apgc_restrict_group_visibility',
		__( 'Available Group visibilities', 'altctrl-public-group' ),
		'apgc_settings_group_visibility_callback',
		'buddypress',
		'bp_groups'
	);

	register_setting( 'buddypress', '_apgc_restrict_group_visibility', 'apgc_settings_sanitize_visibility_restrictions' );

	add_settings_field(
		'_apgc_disable_group_control',
		__( 'Public Groups Manage Control Screen', 'altctrl-public-group' ),
		'apgc_settings_group_control_screen',
		'buddypress',
		'bp_groups'
	);

	register_setting( 'buddypress', '_apgc_disable_group_control', 'intval' );
}
add_action( 'bp_register_admin_settings', 'apgc_settings', 11 );

/**
 * Displays the Visibility restriction setting.
 *
 * @since 2.0.0
 */
function apgc_settings_group_visibility_callback() {
	$visibilities = wp_list_pluck( apgc_get_visibility_levels(), 'title', 'id' );
	$setting      = apgc_get_allowed_visibility_levels();
	$is_restriced = bp_restrict_group_creation();
	?>

	<fieldset style="border: solid 1px #ccc; margin-bottom: 1em">
		<legend style="padding: 0 1em"><?php esc_html_e( 'Visibility levels', 'altctrl-public-group' ); ?></legend>
			<ul style="margin: 1em 2em 1em;">

			<?php foreach ( $visibilities as $key => $visibility ) :

				$readonly = '';
				if ( in_array( $key, array( 'public-open', 'public-request' ), true ) ) {
					$readonly = ' readonly="readonly"';
				}

				$disabled = '';
				if ( $is_restriced || ( $key === 'public-invite' && ! bp_is_active( 'friends' ) ) ) {
					$disabled  = ' disabled="disabled"';
				}
			?>
				<li>
					<label for="apgc-visibility-level-<?php echo esc_attr( $key ); ?>">
						<input id="apgc-visibility-level-<?php echo esc_attr( $key ); ?>" type="checkbox" name="_apgc_restrict_group_visibility[]" value="<?php echo esc_attr( $key );?>" <?php checked( true, in_array( $key, $setting, true ) ); echo $disabled . ' ' . $readonly; ?>> <?php echo esc_html( $visibility ) ;?>
					</label>
				</li>
			<?php endforeach ; ?>
		</ul>
	</fieldset>

	<p class="description"><?php _e( 'Activate the checkboxes regular members can use.', 'altctrl-public-group' ); ?></p>

	<?php
}

/**
 * Sanitizes the Visibility restriction setting.
 *
 * @since 2.0.0
 *
 * @param  mixed $option The option value.
 * @return array         The option value.
 */
function apgc_settings_sanitize_visibility_restrictions( $option = '' ) {
	$option = array_merge( (array) $option, array( 'public-open', 'public-request' ) );

	return array_map( 'sanitize_key', array_unique( $option ) );
}

/**
 * Should the Control screen be disabled?
 *
 * @since 2.0.0
 */
function apgc_settings_group_control_screen() {
	$disabled = apgc_disable_group_control_screen();
	?>
	<input id="_apgc_disable_group_control" name="_apgc_disable_group_control" type="checkbox" value="1" <?php checked( $disabled ); ?> />
	<label for="_apgc_disable_group_control"><?php esc_html_e( 'Disable', 'altctrl-public-group' ); ?></label>
	<?php
}
