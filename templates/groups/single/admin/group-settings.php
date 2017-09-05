<?php
/**
 * Group Edit settings form.
 *
 * @package AltPublicGroupCtrl\templates\groups\single\admin
 */
?>

<h2 class="bp-screen-reader-text"><?php esc_html_e( 'Manage Group Settings', 'altctrl-public-group' ); ?></h2>

<?php

/**
 * Fires before the group settings admin display.
 *
 * @since BuddyPress 1.1.0
 */
do_action( 'bp_before_group_settings_admin' ); ?>

<fieldset class="group-create-privacy">

	<legend><?php esc_html_e( 'Privacy Options', 'altctrl-public-group' ); ?></legend>

	<div class="radio">

		<input type="hidden" name="group-status" value="public"/>

		<ul id="public-group-description">
			<?php apgc_group_visibility_options() ;?>
		</ul>

	</div>

</fieldset>

<?php // Group type selection ?>
<?php if ( $group_types = bp_groups_get_group_types( array( 'show_in_create_screen' => true ), 'objects' ) ): ?>

	<fieldset class="group-create-types">
		<legend><?php esc_html_e( 'Group Types', 'altctrl-public-group' ); ?></legend>

		<p><?php esc_html_e( 'Select the types this group should be a part of.', 'altctrl-public-group' ); ?></p>

		<?php foreach ( $group_types as $type ) : ?>
			<div class="checkbox">
				<label for="<?php printf( 'group-type-%s', $type->name ); ?>">
					<input type="checkbox" name="group-types[]" id="<?php printf( 'group-type-%s', $type->name ); ?>" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( bp_groups_has_group_type( bp_get_current_group_id(), $type->name ) ); ?>/> <?php echo esc_html( $type->labels['name'] ); ?>
					<?php
						if ( ! empty( $type->description ) ) {
							printf( __( '&ndash; %s', 'altctrl-public-group' ), '<span class="bp-group-type-desc">' . esc_html( $type->description ) . '</span>' );
						}
					?>
				</label>
			</div>

		<?php endforeach; ?>

	</fieldset>

<?php endif; ?>

<?php
/**
 * It's only making sense if the Friends component is active.
 */
if ( bp_is_active( 'friends' ) ) : ?>

	<fieldset class="group-create-invitations">

		<legend><?php esc_html_e( 'Group Invitations', 'altctrl-public-group' ); ?></legend>

		<p><?php esc_html_e( 'Which members of this group are allowed to invite others?', 'altctrl-public-group' ); ?></p>

		<div class="radio">

			<label for="group-invite-status-members"><input type="radio" name="group-invite-status" id="group-invite-status-members" value="members"<?php bp_group_show_invite_status_setting( 'members' ); ?> /> <?php esc_html_e( 'All group members', 'altctrl-public-group' ); ?></label>

			<label for="group-invite-status-mods"><input type="radio" name="group-invite-status" id="group-invite-status-mods" value="mods"<?php bp_group_show_invite_status_setting( 'mods' ); ?> /> <?php esc_html_e( 'Group admins and mods only', 'altctrl-public-group' ); ?></label>

			<label for="group-invite-status-admins"><input type="radio" name="group-invite-status" id="group-invite-status-admins" value="admins"<?php bp_group_show_invite_status_setting( 'admins' ); ?> /> <?php esc_html_e( 'Group admins only', 'altctrl-public-group' ); ?></label>

		</div>

	</fieldset>

<?php else : ?>

	<input type="hidden" name="group-invite-status" value="members"/>

<?php endif ; ?>

<?php

/**
 * Fires after the group settings admin display.
 *
 * @since BuddyPress 1.1.0
 */
do_action( 'bp_after_group_settings_admin' ); ?>

<p><input type="submit" value="<?php esc_attr_e( 'Save Changes', 'altctrl-public-group' ); ?>" id="save" name="save" /></p>
<?php wp_nonce_field( 'groups_edit_group_settings' );
