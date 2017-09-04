<?php
/**
 * Plugin settings.
 *
 * @package AltPublicGroupCtrl\includes
 *
 * @since 2.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

function apgc_settings() {
	add_settings_field(
		'_apgc_restrict_group_visibility',
		__( 'Available Group visibilities', 'buddypress' ),
		'apgc_settings_group_visibility_callback',
		'buddypress',
		'bp_groups'
	);

	register_setting( 'buddypress', '_apgc_restrict_group_visibility', 'apgc_settings_sanitize_visibility_restrictions' );
}
add_action( 'bp_register_admin_settings', 'apgc_settings', 11 );

function apgc_settings_group_visibility_callback() {
	$visibilities = array(
		'public-open'      => __( 'Public', 'altctrl-public-group' ),
		'public-subscribe' => __( 'Public with subsription', 'altctrl-public-group' ),
		'public-invite'    => __( 'Public invited only', 'altctrl-public-group' ),
		'private-hidden'   => __( 'Private or Hidden', 'altctrl-public-group' ),
	);

	$setting  = (array) bp_get_option( '_apgc_restrict_group_visibility', array( 'public-open', 'public-subscribe' ) );
	$disabled  = '';
	if ( bp_restrict_group_creation() ) {
		$disabled  = ' disabled="disabled"';
	}
	?>

	<fieldset style="border: solid 1px #ccc; margin-bottom: 1em">
		<legend style="padding: 0 1em"><?php esc_html_e( 'Visibility levels', 'altctrl-public-group' ); ?></legend>
			<ul style="margin: 1em 2em 1em;">

			<?php foreach ( $visibilities as $key => $visibility ) :

				$readonly = '';
				if ( in_array( $key, array( 'public-open', 'public-subscribe' ), true ) ) {
					$readonly = ' readonly="readonly"';
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

function apgc_settings_sanitize_visibility_restrictions( $option = '' ) {
	$option = array_merge( (array) $option, array( 'public-open', 'public-subscribe' ) );

	return array_map( 'sanitize_key', $option );
}
