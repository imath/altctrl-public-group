<?php
/**
 * Plugin functions.
 *
 * @package AltPublicGroupCtrl\includes
 *
 * @since 2.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Get the plugin Instance.
 *
 * @since 2.0.0
 *
 * @return Alt_Public_Group_Ctrl_Loader The plugin Instance.
 */
function apgc_instance() {
	return buddypress()->altctrl;
}

/**
 * Get the "any user" allowed Group visibilities.
 *
 * @since 2.0.0
 *
 * @param  array $default The default "any user" allowed Group visibilities.
 * @return array          The "any user" allowed Group visibilities.
 */
function apgc_get_allowed_visibility_levels( $default = array( 'public-open', 'public-request' ) ) {
	return (array) bp_get_option( '_apgc_restrict_group_visibility', $default );
}

/**
 * Get the supported Group visibilities.
 *
 * @since 2.0.0
 *
 * @return array The supported Group visibilities.
 */
function apgc_get_visibility_levels() {
	return array(
		'public-open'      => array(
			'id'          => 'public-open',
			'title'       => __( 'Public', 'altctrl-public-group' ),
			'description' => __( 'Any site member can join this group.', 'altctrl-public-group' ),
			'public'      => true,
		),
		'public-request' => array(
			'id'          => 'public-request',
			'title'       => __( 'Public with membership requests', 'altctrl-public-group' ),
			'description' => __( 'The site member needs to request a group membership.', 'altctrl-public-group' ),
			'public'      => true,
		),
		'public-invite'    =>  array(
			'id'          => 'public-invite',
			'title'       => __( 'Public for invited members only', 'altctrl-public-group' ),
			'description' => __( 'The site member needs to be invited to join the group.', 'altctrl-public-group' ),
			'public'      => true,
		),
		'private-hidden'   => array(
			'id'          => 'private-hidden',
			'title'       => __( 'Private or Hidden', 'altctrl-public-group' ),
			'description' => '',
			'public'      => false,
		),
	);
}

/**
 * Load translations.
 *
 * @since 2.0.0
 */
function apgc_load_textdomain() {
	// Get plugin's intance.
	$apgc = apgc_instance();

	// Traditional WordPress plugin locale filter
	$locale = apply_filters( 'plugin_locale', get_locale(), $apgc->domain );
	$mofile = sprintf( '%1$s-%2$s.mo', $apgc->domain, $locale );

	// Setup paths to current locale file
	$mofile_local  = $apgc->lang_dir . $mofile;
	$mofile_global = WP_LANG_DIR . '/altctrl-public-group/' . $mofile;

	// Look in global /wp-content/languages/altctrl-public-group folder
	load_textdomain( $apgc->domain, $mofile_global );

	// Look in local /wp-content/plugins/altctrl-public-group/languages/ folder
	load_textdomain( $apgc->domain, $mofile_local );

	// Look in global /wp-content/languages/plugins/
	load_plugin_textdomain( $apgc->domain );
}
add_action( 'bp_init', 'apgc_load_textdomain', 5 );

/**
 * Should the plugin override BuddyPress build-in templates ?
 *
 * @since 2.0.0
 *
 * @return boolean True if it should. False otherwise.
 */
function apgc_override_template_stack() {
	$return = Alt_Public_Group_Ctrl::show_front_page();

	if ( ! $return && bp_is_group_create() && bp_is_group_creation_step( 'group-settings' ) ) {
		$return = ! in_array( 'private-hidden', apgc_get_visibility_levels(), true ) && ! bp_current_user_can( 'bp_moderate' );
	}

	return $return;
}

/**
 * Inject Plugin's templates dir into the BuddyPress Templates dir stack.
 *
 * @since 2.0.0
 *
 * @param  array $template_stack The list of available locations to get BuddyPress templates
 * @return array                 The list of available locations to get BuddyPress templates
 */
function apgc_template_stack( $template_stack = array() ) {
	if ( apgc_override_template_stack() ) {
		$priority = 0;

		foreach ( $template_stack as $itpl => $template ) {
			if ( false !== strrpos( $template, bp_get_theme_compat_dir() ) ) {
				$priority = $itpl;
				break;
			}
		}

		// Before BuddyPress active's template pack or first if no template packs are in use.
		$bp_legacy = array_splice( $template_stack, $itpl );

		$template_stack = array_merge(
			$template_stack,
			array( buddypress()->altctrl->templates_dir ),
			$bp_legacy
		);
	}

	return $template_stack;
}
add_filter( 'bp_get_template_stack', 'apgc_template_stack', 10, 1 );

/**
 * Outputs the public visibiity options.
 *
 * @since 2.0.0
 */
function apgc_group_visibility_options() {
	$group_id = bp_get_current_group_id();

	if ( ! $group_id ) {
		$group_id = bp_get_new_group_id();
	}

	$visibilities = wp_filter_object_list( apgc_get_visibility_levels(), array( 'public' => true ) );
	$allowed      = apgc_get_allowed_visibility_levels();
	$current      = apgc_group_get_visibility_level( $group_id );

	$output = array();
	foreach ( $visibilities as $visibility ) {
		if ( ! in_array( $visibility['id'], $allowed, true ) ) {
			continue;
		}
		$checked = ' ' . checked( $current, $visibility['id'], false );
		$output[] = sprintf(
			'<label for="apgc-group-visibility-%1$s">
				<input type="radio" name="_altctrl_visibility_level" id="apgc-group-visibility-%1$s" value="%1$s"%2$s aria-describedby="public-group-description" />
				%3$s
			</label>
			<p class="description">%4$s</p>',
			esc_attr( $visibility['id'] ),
			$checked,
			esc_html( $visibility['title'] ),
			esc_html( $visibility['description'] )
		);
	}

	echo '<li>' . join( '</li><li>', $output ) . '</li>';
}

/**
 * Get the public visibility level of a Public Group.
 *
 * @since 2.0.0
 *
 * @param  integer $group_id The group ID.
 * @return string            The Public group's visibility.
 */
function apgc_group_get_visibility_level( $group_id = 0 ) {
	if ( ! bp_is_active( 'groups' ) ) {
		return false;
	}

	$visibility = groups_get_groupmeta( $group_id, '_altctrl_visibility_level', true );
	if ( ! $visibility ) {
		$visibility = 'public-open';
	}

	return $visibility;
}

/**
 * Saves the Public group's visibility.
 *
 * @since 2.0.0
 *
 * @param  integer $group_id   The group ID.
 * @param  string  $visibility The Public group's visibility.
 * @return boolean             True on success. False otherwise.
 */
function apgc_group_update_visibility_level( $group_id = 0, $visibility = 'public-open' ) {
	if ( ! bp_is_active( 'groups' ) || ! $group_id ) {
		return false;
	}

	if ( ! empty( $visibility ) ) {
		$visibilities = wp_filter_object_list( apgc_get_visibility_levels(), array( 'public' => true ), 'and', 'id' );
		$allowed      = apgc_get_allowed_visibility_levels();

		if ( ! isset( $visibilities[ $visibility ] ) || ! in_array( $visibility, $allowed, true ) ) {
			return false;
		}

		groups_update_groupmeta( $group_id, '_altctrl_visibility_level', sanitize_key( $visibility ) );
	} else {
		groups_delete_groupmeta( $group_id, '_altctrl_visibility_level' );
	}

	return true;
}

/**
 * Creates the Public group's visibility setting (during the group's creation process).
 *
 * @since 2.0.0
 */
function apgc_group_create_group_visibility_setting() {
	if ( ! isset( $_POST['_altctrl_visibility_level'] ) ) {
		return;
	}

	$group_id = bp_get_current_group_id();

	if ( ! $group_id ) {
		$group_id = bp_get_new_group_id();
	}

	if ( ! apgc_group_update_visibility_level( $group_id, $_POST['_altctrl_visibility_level'] ) ) {
		bp_core_add_message( __( 'There was an error saving the group privacy option. Please try again.', 'altctrl-public-group' ), 'error' );
		bp_core_redirect( trailingslashit( bp_get_groups_directory_permalink() . 'create/step/' . bp_get_groups_current_create_step() ) );
	}
}
add_action( 'groups_create_group_step_save_group-settings', 'apgc_group_create_group_visibility_setting' );
