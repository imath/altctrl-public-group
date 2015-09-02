<?php

if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'BP_Group_Extension' ) ) :
/**
 * The Alternative Public Group control class
 *
 * @package Alternative Public Group Control
 * @since 1.0.0
 */
class Alt_Public_Group_Ctrl extends BP_Group_Extension {

	public static $needs_group_request;

	/**
	 * construct method to add some settings and hooks
	 */
	public function __construct() {

		$args = array(
			'slug'              => 'control',
			'name'              => __( 'Control', 'altctrl-public-group' ),
			'visibility'        => 'private',
			'nav_item_position' => 91,
			'enable_nav_item'   => false,
			'screens'           => array(
				'admin' => array(
					'enabled' => false,
				),
				'create' => array(
					'enabled' => false,
				),
				'edit' => array(
					'enabled' => $this->is_public_group(),
				),
			)
		);

        parent::init( $args );

        $this->setup_hooks();
        $this->register_post_type();
	}

	/**
	 * Set some hooks (Actions & filters)
	 */
	private function setup_hooks() {
		// Actions
		add_action( 'bp_actions',                                array( $this, 'control'              )        );
		add_action( 'groups_screen_group_request_membership',    array( $this, 'maybe_restore_status' )        );
		add_action( 'groups_screen_group_admin_requests',        array( $this, 'maybe_restore_status' )        );
		add_action( 'groups_admin_tabs',                         array( $this, 'maybe_admin_requests' ), 10, 2 );
		add_action( 'bp_after_group_request_membership_content', array( $this, 'maybe_request_info'   )        );
		add_action( 'bp_enqueue_scripts',                        array( $this, 'enqueue_css'          )        );

		// Filters
		add_filter( 'bp_get_template_stack',    array( $this, 'add_to_template_stack' ), 10, 1 );
		add_filter( 'bp_has_groups',            array( $this, 'append_need_request'   ), 10, 3 );
		add_filter( 'bp_get_group_join_button', array( $this, 'join_button'           ), 10, 1 );
		add_filter( 'bp_activity_can_comment',  array( $this, 'maybe_disable_can_do'  ), 10, 1 );
		add_filter( 'bp_activity_can_favorite', array( $this, 'maybe_disable_can_do'  ), 10, 1 );
	}

	/**
	 * Register an hidden post type to save custom group front pages
	 */
	private function register_post_type() {
		if ( ! bp_is_root_blog() ) {
			return;
		}

		$labels = array(
			'name'               => _x( 'Groups front', 'post type general name', 'altctrl-public-group' ),
			'singular_name'      => _x( 'Group front', 'post type singular name', 'altctrl-public-group' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'query_var'          => false,
			'rewrite'            => false,
			'has_archive'        => false,
			'hierarchical'       => true,
			'supports'           => array( 'editor' )
		);

		register_post_type( 'group_front', $args );
	}

	/** Actions *******************************************************************/

	/**
	 * Check public group's control options and do the needed job
	 */
	public function control() {
		$bp = buddypress();

		if ( ! $this->is_public_group() ) {
			return;
		}

		// Append to current group the control settings
		$hidden_tabs = (array) groups_get_groupmeta( $this->group->id, '_altctrl_tabs', true );
		$this->group->need_request = groups_get_groupmeta( $this->group->id, '_altctrl_request', true );

		// Managing requests
		if ( bp_is_group_admin_screen( 'membership-requests' ) && bp_is_item_admin() ) {
			$bp->groups->current_group->status = 'private';
		}

		// Admin or members always have access
		if ( bp_is_item_admin() || groups_is_user_member( bp_loggedin_user_id(), $this->group->id ) ) {
			return;
		}

		/** Group members only tabs ***************************************************/

		// Hide tabs
		if ( ! empty( $bp->bp_options_nav[ $this->group->slug ] ) && ! empty( $hidden_tabs ) ) {
			foreach ( array_keys( $bp->bp_options_nav[ $this->group->slug ] ) as $item_tab ) {
				if ( ! in_array( $item_tab, $hidden_tabs ) ) {
					continue;
				}

				$bp->bp_options_nav[ $this->group->slug ][ $item_tab ]['user_has_access'] = false;
			}
		}

		// Redirect if trying to access the page
		if ( ! empty( $hidden_tabs ) && in_array( bp_current_action(), $hidden_tabs ) ) {
			bp_core_add_message( __( 'This area is restricted to group members', 'altctrl-public-group' ), 'error' );
			bp_core_redirect( bp_get_group_permalink( $this->group ) );
		}

		/** Joining group *************************************************************/

		if ( ! empty( $this->group->need_request ) && is_user_logged_in() ) {
			// first add the request subnav
			bp_core_new_subnav_item( array(
				'name'               => __( 'Request Membership', 'altctrl-public-group' ),
				'slug'               => 'request-membership',
				'parent_url'         => bp_get_group_permalink( $this->group ),
				'parent_slug'        => $this->group->slug,
				'screen_function'    => 'groups_screen_group_request_membership',
				'position'           => 30
			) );

			// Then temporarly make the group private.
			if ( bp_is_group_membership_request() ) {
				$bp->groups->current_group->status = 'private';
			}
		}
	}

	/**
	 * Public > fake Private > Public..
	 */
	public function maybe_restore_status() {
		if ( ! empty( $this->group->need_request ) ) {
			buddypress()->groups->current_group->status = 'public';
		}
	}

	/**
	 * Add an admin tab to manage the requests (for public group) if needed
	 */
	public function maybe_admin_requests( $current_tab = '', $group_slug = '' ) {
		if ( empty( $this->group->need_request ) || $group_slug != $this->group->slug ) {
			return;
		}
		?>
		<li<?php if ( 'membership-requests' == $current_tab ) : ?> class="current"<?php endif; ?>><a href="<?php echo trailingslashit( bp_get_group_permalink( $this->group ) . 'admin/membership-requests' ) ?>"><?php _e( 'Requests', 'altctrl-public-group' ); ?></a></li>
		<?php
	}

	/**
	 * Inform the user his request has been taken in account
	 */
	public function maybe_request_info() {
		global $groups_template;

		if( ! $groups_template->group->is_pending ) {
			return;
		}
		?>
		<p><?php esc_html_e( 'Your membership request was sent to the group administrator successfully. You will be notified when the group administrator responds to your request.', 'altctrl-public-group' ); ?></p>
		<?php
	}

	/**
	 * Enqueue minimal css
	 */
	public function enqueue_css() {
		$bp = buddypress();

		if ( ! $this->is_public_group() ) {
			return;
		}

		$css_args = apply_filters( 'altctrl_public_group_css', array(
			'handle'  => 'altctrl-public-group-css',
			'src'     => $bp->altctrl->css_url . 'altctrl-public-group.css',
			'deps'    => array(), 
			'version' => $bp->altctrl->version,
		) );

		// in case admin wants to neutralize plugin's style
		if ( empty( $css_args ) ) {
			return;
		}
		
		wp_enqueue_style( $css_args['handle'], $css_args['src'], $css_args['deps'], $css_args['version'] );
	}

	/** Filters *******************************************************************/

	/**
	 * Add the plugin templates folder to the BuddyPress templates stack
	 */
	public function add_to_template_stack( $templates = array() ) {
		if ( $this->show_front_page() ) {
			$templates = array_merge( $templates, array( buddypress()->altctrl->templates_dir ) );
		}
		return $templates;
	}

	/**
	 * Append a flag to indicate if the public group needs the user
	 * to request a membership into the groups loop
	 */
	public function append_need_request( $has_groups, $groups, $args ) {
		global $groups_template;

		if ( bp_is_group() ) {
			return $has_groups;
		}

		$group_ids = wp_list_pluck( $groups->groups, 'id' );
		$altctrl_metas = $this->get_request_control_meta( $group_ids );

		if ( ! empty( $altctrl_metas ) ) {
			foreach( $groups_template->groups as $key => $group ) {

				if ( ! empty( $altctrl_metas[ $group->id ]->need_request ) ) {
					$groups_template->groups[ $key ]->need_request = true;
				}
			}
		}

		return $has_groups;
	}

	/**
	 * Eventually change the group action buttons
	 */
	public function join_button( $button ) {
		global $groups_template;

		if ( empty( $groups_template->group->need_request ) || 'leave_group' == $button['id'] ) {
			return $button;
		}

		// Difficult to filter BuddyPress ajax functions, so disabling them.

		if ( $groups_template->group->is_invited ) {
			$button = array(
				'id'                => 'accept_invite',
				'component'         => 'groups',
				'must_be_logged_in' => true,
				'block_self'        => false,
				'wrapper_class'     => 'no-ajax ' . $groups_template->group->status,
				'wrapper_id'        => 'groupbutton-' . $groups_template->group->id,
				'link_href'         => add_query_arg( 'redirect_to', bp_get_group_permalink( $groups_template->group ), bp_get_group_accept_invite_link( $groups_template->group ) ),
				'link_text'         => __( 'Accept Invitation', 'altctrl-public-group' ),
				'link_title'        => __( 'Accept Invitation', 'altctrl-public-group' ),
				'link_class'        => 'group-button accept-invite',
			);
		} elseif ( $groups_template->group->is_pending ) {
			$button = array(
				'id'                => 'membership_requested',
				'component'         => 'groups',
				'must_be_logged_in' => true,
				'block_self'        => false,
				'wrapper_class'     => 'no-ajax pending ' . $groups_template->group->status,
				'wrapper_id'        => 'groupbutton-' . $groups_template->group->id,
				'link_href'         => bp_get_group_permalink( $groups_template->group ),
				'link_text'         => __( 'Request Sent', 'altctrl-public-group' ),
				'link_title'        => __( 'Request Sent', 'altctrl-public-group' ),
				'link_class'        => 'group-button pending membership-requested',
			);
		} elseif ( ! is_super_admin() ) {
			$button = array(
				'id'                => 'request_membership',
				'component'         => 'groups',
				'must_be_logged_in' => true,
				'block_self'        => false,
				'wrapper_class'     => 'no-ajax ' . $groups_template->group->status,
				'wrapper_id'        => 'groupbutton-' . $groups_template->group->id,
				'link_href'         => wp_nonce_url( bp_get_group_permalink( $groups_template->group ) . 'request-membership', 'groups_request_membership' ),
				'link_text'         => __( 'Request Membership', 'altctrl-public-group' ),
				'link_title'        => __( 'Request Membership', 'altctrl-public-group' ),
				'link_class'        => 'group-button request-membership',
			);
		}

		return $button;
	}

	/**
	 * If a public group needs the user to register to be a group member, commenting or
	 * favoriting an activity is disabled
	 */
	public function maybe_disable_can_do( $can_do = true ) {
		global $activities_template;

		if ( is_null( $activities_template ) ) {
			return $can_do;
		}

		if ( 'groups' !=  $activities_template->activity->component ) {
			return $can_do;
		}

		if ( empty( $activities_template->activity->item_id ) ) {
			return $can_do;
		}

		if ( groups_is_user_member( bp_loggedin_user_id(), $activities_template->activity->item_id ) || is_super_admin() ) {
			return $can_do;
		}

		if ( empty( self::$needs_group_request ) ) {
			self::$needs_group_request = array();
		}

		if ( ! isset( self::$needs_group_request[ $activities_template->activity->item_id ] ) ) {
			self::$needs_group_request[ $activities_template->activity->item_id ] = ! groups_get_groupmeta( $activities_template->activity->item_id, '_altctrl_request', true );
		}

		$can_do = (bool) self::$needs_group_request[ $activities_template->activity->item_id ];

		return $can_do;
	}

	/** Helpers *******************************************************************/

	/**
	 * Should we show the group's custom front page ?
	 */
	private function show_front_page() {
		$retval = false;

		if ( bp_is_group_create() ) {
			return $retval;
		}

		if ( ! bp_is_group() ) {
			return $retval;
		}

		$group_id = bp_get_current_group_id();

		if ( empty( $group_id ) ) {
			return $retval;
		}

		if ( bp_is_item_admin() ) {
			return $retval;
		}

		if ( ! groups_is_user_member( bp_loggedin_user_id(), $group_id ) && $this->has_front_page( $group_id ) ) {
			$retval = true;
		}

		return $retval;
	}

	/**
	 * Does the group has a custom front page ?
	 */
	private function has_front_page( $group_id = 0 ) {
		if ( empty( $group_id ) ) {
			return false;
		}

		return (int) groups_get_groupmeta( $group_id, '_altctrl_page_id', true );
	}

	/**
	 * Is current group, a public group ?
	 */
	private function is_public_group() {
		if ( ! bp_is_group() ) {
			return false;
		}

		$this->group = groups_get_current_group();

		return ( 'public' == $this->group->status ) ? true : false;
	}

	/**
	 * Fetch all public groups that needs a membership request
	 */
	private function get_request_control_meta( $groups = array() ) {
		global $wpdb;
		$groupmeta_table = buddypress()->groups->table_name_groupmeta;

		$altctrl_metas = array();

		if ( empty( $groups ) ) {
			return $altctrl_metas;
		} else {
			$groups = wp_parse_id_list( $groups );
		}

		$is_meta_key = '_altctrl_request';
		$group_in = "'" . implode( "','", $groups ) . "'";

		$altctrl_metas = $wpdb->get_results( $wpdb->prepare( "SELECT group_id, meta_value as need_request FROM {$groupmeta_table} WHERE group_id IN ( $group_in ) AND meta_key LIKE %s", $is_meta_key ), OBJECT_K );

		return $altctrl_metas;
	}

	/** Groups component API functions ********************************************/

	/**
	 * Unused
	 */
	public function create_screen( $group_id = null ) {}
	public function create_screen_save( $group_id = null ) {}
	public function admin_screen( $group_id = null ) {}
	public function admin_screen_save( $group_id = null ) {}
	public function display() {}
	public function widget_display() {}

	/**
	 * Displays edit screen
	 */
	public function edit_screen( $group_id = null ) {
		$bp = buddypress();
		$group_id = empty( $group_id ) ? bp_get_current_group_id() : $group_id;

		$request  = apply_filters( 'alt_public_group_ctrl_users_request', groups_get_groupmeta( $group_id, '_altctrl_request', true ) );
		$page_id  = absint( $this->has_front_page( $group_id ) );

		$tabs = groups_get_groupmeta( $group_id, '_altctrl_tabs', true );
		if ( empty( $tabs ) ) {
			$tabs = array();
		}
		?>
		<h4><?php esc_html_e( 'Joining group', 'altctrl-public-group' );?></h4>

		<div class="checkbox">
			<label><input type="checkbox" name="_altctrl[request]" value="1" <?php checked( $request )?>> <?php esc_html_e( 'Users need to submit a request to join group', 'altctrl-public-group' );?></label>
		</div>

		<?php if ( ! empty( $bp->bp_options_nav[ $this->group->slug ] ) ) : ?>

			<hr />

			<h4><?php esc_html_e( 'Group members only tabs', 'altctrl-public-group' );?></h4>

			<?php foreach( $bp->bp_options_nav[ $this->group->slug ] as $nav_item ) {
				if ( in_array( $nav_item['slug'], array( 'home', 'send-invites', 'admin' ) ) ) {
					continue;
				}
				$item_name = preg_replace( '/([.0-9]+)/', '', $nav_item['name'] );
				$item_name = trim( strip_tags( $item_name ) );
				$item_slug = $nav_item['slug'];
				?>
				<div class="checkbox">
					<label><input type="checkbox" name="_altctrl[tabs][]" value="<?php echo esc_attr( $item_slug );?>" <?php checked( in_array( $item_slug, $tabs ) )?>> <?php echo esc_html( $item_name );?></label>
				</div>
				<?php
			}
			?>
			<p class="description"><?php esc_html_e( 'Use the checkboxes to choose the tabs to hide to non members', 'altctrl-public-group' );?></p>

		<?php endif;?>

		<hr />

		<h4><?php esc_html_e( 'Group&#39;s home page', 'altctrl-public-group' );?></h4>

		<div class="checkbox">
			<label><input type="checkbox" name="_altctrl[page_cb]" value="1" <?php checked( ! empty( $page_id ) )?>> <?php esc_html_e( 'Use a custom front page for non group members', 'altctrl-public-group' );?></label>
		</div>

		<div class="wp-editor">
		<?php
			$content = '';
			if ( ! empty( $page_id ) ) {
				$page = get_post( $page_id, OBJECT, 'edit' );
				$content = $page->post_content;
			}

			wp_editor( $content, 'altctrl-public-group', array(
				'textarea_name'     => '_altctrl[page_content]',
				'media_buttons'     => bp_current_user_can( 'upload_files' ),
				'textarea_rows'     => 12,
				'tinymce'           => apply_filters( 'altctrl_public_group_edit_front_page', false ),
				'teeny'             => true,
				'quicktags'         => true,
				'dfw'               => false,
			) );
		?>
		</div>

		<div class="submit">
			<input type="submit" name="_altctrl[save]" value="<?php _e( 'Save', 'altctrl-public-group' );?>" />
			<input type="hidden" name="_altctrl[page_id]" value="<?php echo $page_id; ?>" />

			<?php wp_nonce_field( 'groups_edit_save_' . $this->slug, 'altctrl' ); ?>
		</div>
		<?php
	}


	/**
	 * Save the settings of the group
	 */
	public function edit_screen_save( $group_id = null ) {

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
			return false;

		check_admin_referer( 'groups_edit_save_' . $this->slug, 'altctrl' );

		$group_id = ! empty( $group_id ) ? $group_id : bp_get_current_group_id();

		$altctrl = array();

		if ( empty( $_POST['_altctrl'] ) ) {
			return;
		} else {
			$altctrl = $_POST['_altctrl'];
		}

		if ( ! empty( $altctrl['request'] ) ) {
			groups_update_groupmeta( $group_id, '_altctrl_request', absint( $altctrl['request'] ) );
		} else {
			groups_delete_groupmeta( $group_id, '_altctrl_request' );
		}

		if ( ! empty( $altctrl['tabs'] ) ) {
			groups_update_groupmeta( $group_id, '_altctrl_tabs', array_map( 'sanitize_title', $altctrl['tabs'] ) );
		} else {
			groups_delete_groupmeta( $group_id, '_altctrl_tabs' );
		}

		$page_id = $altctrl['page_id'];

		if ( ! empty( $altctrl['page_cb'] ) && ! empty( $altctrl['page_content'] ) ) {
			$page_id = wp_insert_post( array(
				'ID'           => $page_id,
				'post_status'  => 'publish',
				'post_type'    => 'group_front',
				'post_content' => $altctrl['page_content'],
			) );

			update_post_meta( $page_id, '_altctrl_group_id', $group_id );
			groups_update_groupmeta( $group_id, '_altctrl_page_id', $page_id );
		}

		if ( ! empty( $altctrl['page_id'] ) && empty( $altctrl['page_cb'] ) ) {
			if ( wp_delete_post( $page_id ) ) {
				groups_delete_groupmeta( $group_id, '_altctrl_page_id', $page_id );
			}
		}

		bp_core_add_message( __( 'Settings saved successfully', 'altctrl-public-group' ) );
		bp_core_redirect( bp_get_group_permalink( buddypress()->groups->current_group ) . 'admin/' . $this->slug );
	}

	/** Template tag **************************************************************/

	/**
	 * Display the group's custom front page
	 */
	public static function the_content() {
		$group_id = bp_get_current_group_id();

		if ( empty( $group_id ) ) {
			return;
		}

		$page_id = groups_get_groupmeta( $group_id, '_altctrl_page_id', true );

		if ( empty( $page_id ) ) {
			return;
		}

		$page = get_post( $page_id );
		$output = apply_filters( 'the_content', $page->post_content );

		echo apply_filters( 'altctrl_public_group_display_front_page', $output );
	}
}

function altctrl_public_group() {
	bp_register_group_extension( 'Alt_Public_Group_Ctrl' );
}
add_action( 'bp_init', 'altctrl_public_group' );

endif;
