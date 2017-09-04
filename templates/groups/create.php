<?php
/**
 * Group Create form - settings step.
 *
 * @package AltPublicGroupCtrl\templates\groups
 */

/**
 * Fires at the top of the groups creation template file.
 *
 * @since BuddyPress 1.7.0
 */
do_action( 'bp_before_create_group_page' ); ?>

<div id="buddypress">

	<?php

	/**
	 * Fires before the display of group creation content.
	 *
	 * @since BuddyPress 1.6.0
	 */
	do_action( 'bp_before_create_group_content_template' ); ?>

	<form action="<?php bp_group_creation_form_action(); ?>" method="post" id="create-group-form" class="standard-form" enctype="multipart/form-data">

		<?php

		/**
		 * Fires before the display of group creation.
		 *
		 * @since BuddyPress 1.2.0
		 */
		do_action( 'bp_before_create_group' ); ?>

		<div class="item-list-tabs no-ajax" id="group-create-tabs">
			<ul>

				<?php bp_group_creation_tabs(); ?>

			</ul>
		</div>

		<div id="template-notices" role="alert" aria-atomic="true">
			<?php

			/** This action is documented in bp-templates/bp-legacy/buddypress/activity/index.php */
			do_action( 'template_notices' ); ?>

		</div>

		<div class="item-body" id="group-create-body">
			<h2 class="bp-screen-reader-text"><?php
				/* translators: accessibility text */
				esc_html_e( 'Group Settings', 'altctrl-public-group' );
			?></h2>

			<?php

			/**
			 * Fires before the display of the group settings creation step.
			 *
			 * @since BuddyPress 1.1.0
			 */
			do_action( 'bp_before_group_settings_creation_step' ); ?>

			<fieldset class="group-create-privacy">

				<legend><?php _e( 'Privacy Options', 'altctrl-public-group' ); ?></legend>

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
					<legend><?php _e( 'Group Types', 'altctrl-public-group' ); ?></legend>

					<p><?php _e( 'Select the types this group should be a part of.', 'altctrl-public-group' ); ?></p>

					<?php foreach ( $group_types as $type ) : ?>
						<div class="checkbox">
							<label for="<?php printf( 'group-type-%s', $type->name ); ?>"><input type="checkbox" name="group-types[]" id="<?php printf( 'group-type-%s', $type->name ); ?>" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( true, ! empty( $type->create_screen_checked ) ); ?> /> <?php echo esc_html( $type->labels['name'] ); ?>
								<?php
									if ( ! empty( $type->description ) ) {
										/* translators: Group type description shown when creating a group. */
										printf( __( '&ndash; %s', 'altctrl-public-group' ), '<span class="bp-group-type-desc">' . esc_html( $type->description ) . '</span>' );
									}
								?>
							</label>
						</div>

					<?php endforeach; ?>

				</fieldset>

			<?php endif; ?>

			<fieldset class="group-create-invitations">

				<legend><?php _e( 'Group Invitations', 'altctrl-public-group' ); ?></legend>

				<p><?php _e( 'Which members of this group are allowed to invite others?', 'altctrl-public-group' ); ?></p>

				<div class="radio">

					<label for="group-invite-status-members"><input type="radio" name="group-invite-status" id="group-invite-status-members" value="members"<?php bp_group_show_invite_status_setting( 'members' ); ?> /> <?php _e( 'All group members', 'altctrl-public-group' ); ?></label>

					<label for="group-invite-status-mods"><input type="radio" name="group-invite-status" id="group-invite-status-mods" value="mods"<?php bp_group_show_invite_status_setting( 'mods' ); ?> /> <?php _e( 'Group admins and mods only', 'altctrl-public-group' ); ?></label>

					<label for="group-invite-status-admins"><input type="radio" name="group-invite-status" id="group-invite-status-admins" value="admins"<?php bp_group_show_invite_status_setting( 'admins' ); ?> /> <?php _e( 'Group admins only', 'altctrl-public-group' ); ?></label>

				</div>

			</fieldset>

			<?php if ( bp_is_active( 'forums' ) && bp_current_user_can( 'manage_network_options' ) ) : ?>

				<h4><?php _e( 'Group Forums', 'altctrl-public-group' ); ?></h4>

					<p class="attention"><?php esc_html_e( 'Attention Site Admin: the legacy Group forums are retired and not supported by the Alternative Public Group Control plugin.', 'altctrl-public-group' ); ?></p>

			<?php endif; ?>

			<?php

			/**
			 * Fires after the display of the group settings creation step.
			 *
			 * @since BuddyPress 1.1.0
			 */
			do_action( 'bp_after_group_settings_creation_step' ); ?>

			<?php wp_nonce_field( 'groups_create_save_group-settings' ); ?>

			<?php

			/**
			 * Fires before the display of the group creation step buttons.
			 *
			 * @since BuddyPress 1.1.0
			 */
			do_action( 'bp_before_group_creation_step_buttons' ); ?>

			<div class="submit" id="previous-next">

				<input type="button" value="<?php esc_attr_e( 'Back to Previous Step', 'altctrl-public-group' ); ?>" id="group-creation-previous" name="previous" onclick="location.href='<?php bp_group_creation_previous_link(); ?>'" />
				<input type="submit" value="<?php esc_attr_e( 'Next Step', 'altctrl-public-group' ); ?>" id="group-creation-next" name="save" />

			</div>

			<?php

			/**
			 * Fires after the display of the group creation step buttons.
			 *
			 * @since BuddyPress 1.1.0
			 */
			do_action( 'bp_after_group_creation_step_buttons' ); ?>

			<?php /* Don't leave out this hidden field */ ?>
			<input type="hidden" name="group_id" id="group_id" value="<?php bp_new_group_id(); ?>" />

			<?php

			/**
			 * Fires and displays the groups directory content.
			 *
			 * @since BuddyPress 1.1.0
			 */
			do_action( 'bp_directory_groups_content' ); ?>

		</div><!-- .item-body -->

		<?php

		/**
		 * Fires after the display of group creation.
		 *
		 * @since BuddyPress 1.2.0
		 */
		do_action( 'bp_after_create_group' ); ?>

	</form>

	<?php

	/**
	 * Fires after the display of group creation content.
	 *
	 * @since BuddyPress 1.6.0
	 */
	do_action( 'bp_after_create_group_content_template' ); ?>

</div>

<?php

/**
 * Fires at the bottom of the groups creation template file.
 *
 * @since BuddyPress 1.7.0
 */
do_action( 'bp_after_create_group_page' );
