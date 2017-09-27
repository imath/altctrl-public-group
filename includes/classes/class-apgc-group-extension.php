<?php
/**
 * Group Extension.
 *
 * @package AltPublicGroupCtrl\includes\classes
 *
 * @since 2.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'BP_Group_Extension' ) ) :
/**
 * The Alternative Public Group control class
 *
 * @since  1.0.0
 * @since  2.0.0 Class has been renamed.
 */
class APGC_Group_Extension extends BP_Group_Extension {

	public static $needs_group_request;
	public $group = null;

	/**
	 * construct method to add some settings and hooks
	 *
	 * @since  1.0.0
	 */
	public function __construct() {
		$is_public_group = $this->is_public_group();
		$admin_screen    = array();

		if ( $is_public_group ) {
			$admin_screen = array(
				'metabox_context'  => 'side',
				'metabox_priority' => 'high',
			);
		}

		parent::init(  array(
			'slug'              => 'control',
			'name'              => __( 'Control', 'altctrl-public-group' ),
			'visibility'        => 'private',
			'nav_item_position' => 91,
			'enable_nav_item'   => false,
			'screens'           => array(
				'admin' => array_merge( array(
					'enabled' => $is_public_group,
				), $admin_screen ),
				'create' => array(
					'enabled' => false,
				),
				'edit' => array(
					'enabled' => $is_public_group && ( ! apgc_disable_group_control_screen() || bp_current_user_can( 'bp_moderate' ) ),
				),
			),
		) );

		$this->setup_hooks();
		$this->register_post_type();
	}

	/**
	 * Set some hooks (Actions & filters)
	 *
	 * @since  1.0.0
	 */
	private function setup_hooks() {
		// Actions
		add_action( 'bp_actions',                                array( $this, 'control'              ) );
		add_action( 'groups_screen_group_request_membership',    array( $this, 'maybe_restore_status' ) );
		add_action( 'groups_screen_group_admin_requests',        array( $this, 'maybe_restore_status' ) );
		add_action( 'bp_after_group_request_membership_content', array( $this, 'maybe_request_info'   ) );
		add_action( 'bp_enqueue_scripts',                        array( $this, 'enqueue_css'          ) );
		add_action( 'bp_admin_enqueue_scripts',                  array( $this, 'inline_cssjs'         ) );
		add_action( 'bp_group_admin_edit_after',                 array( $this, 'admin_screen_save'    ) );

		// Filters
		add_filter( 'bp_has_groups',            array( $this, 'append_need_request'   ), 10, 3 );
		add_filter( 'bp_get_group_join_button', array( $this, 'join_button'           ), 10, 1 );
		add_filter( 'bp_activity_can_comment',  array( $this, 'maybe_disable_can_do'  ), 10, 1 );
		add_filter( 'bp_activity_can_favorite', array( $this, 'maybe_disable_can_do'  ), 10, 1 );
	}

	/**
	 * Register an hidden post type to save custom group front pages
	 *
	 * @since  1.0.0
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
	 *
	 * @since  1.0.0
	 */
	public function control() {
		$bp = buddypress();

		if ( ! $this->is_public_group() ) {
			return;
		}

		// Append to current group the control settings
		$hidden_tabs  = (array) groups_get_groupmeta( $this->group->id, '_altctrl_tabs', true );
		$need_request = apgc_group_get_visibility_level( $this->group->id );

		$this->group->need_request = 'public-request' === $need_request;
		$this->group->need_invite  = 'public-invite'  === $need_request;

		if ( ! empty( $this->group->need_request ) && bp_is_group_admin_page() ) {
			bp_core_new_subnav_item( array(
				'name'               => __( 'Requests', 'altctrl-public-group' ),
				'slug'               => 'membership-requests',
				'parent_url'         => bp_get_groups_action_link( 'admin' ),
				'parent_slug'        => $this->group->slug . '_manage',
				'screen_function'    => 'groups_screen_group_admin',
				'user_has_access'    => bp_is_item_admin(),
				'position'           => 40
			), 'groups' );
		}

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
		if ( ! empty( $hidden_tabs ) ) {
			foreach ( $hidden_tabs as $item_tab ) {
				$bp->groups->nav->edit_nav( array( 'user_has_access' => false ), $item_tab, $this->group->slug );
			}
		}

		// Redirect if trying to access the page
		if ( ! empty( $hidden_tabs ) && in_array( bp_current_action(), $hidden_tabs, true ) ) {
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
			), 'groups' );

			// Then temporarly make the group private.
			if ( bp_is_group_membership_request() ) {
				$bp->groups->current_group->status = 'private';
			}
		}
	}

	/**
	 * Public > fake Private > Public..
	 *
	 * @since  1.0.0
	 */
	public function maybe_restore_status() {
		if ( ! empty( $this->group->need_request ) ) {
			buddypress()->groups->current_group->status = 'public';
		}
	}

	/**
	 * Inform the user his request has been taken in account
	 *
	 * @since  1.0.0
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
	 *
	 * @since  1.0.0
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
	 * Append a flag to indicate if the public group needs the user
	 * to request a membership into the groups loop
	 *
	 * @since  1.0.0
	 *
	 * @param  boolean $has_groups Whether the Groups loop contains items or not.
	 * @param  array   $groups     The list of found groups.
	 * @param  array   $args       The Groups loop arguments.
	 * @return boolean             Whether the Groups loop contains items or not.
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

				if ( ! isset( $altctrl_metas[ $group->id ]->need_request ) ) {
					continue;
				}

				if ( 'public-request' === $altctrl_metas[ $group->id ]->need_request ) {
					$groups_template->groups[ $key ]->need_request = true;
				} elseif ( 'public-invite' === $altctrl_metas[ $group->id ]->need_request ) {
					$groups_template->groups[ $key ]->need_invite = true;
				}
			}
		}

		return $has_groups;
	}

	/**
	 * Eventually change the group action buttons
	 *
	 * @since  1.0.0
	 *
	 * @param  BP_Button $button The BuddyPress Group main action button.
	 * @return BP_Button         The BuddyPress Group main action button.
	 */
	public function join_button( $button ) {
		global $groups_template;

		$bail = empty( $groups_template->group->need_request ) && empty( $groups_template->group->need_invite );

		if ( $bail || 'leave_group' === $button['id'] ) {
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
			// When a request is needed: display a button to let the user request a membership
			if ( ! empty( $groups_template->group->need_request ) ) {
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

			// When an invite is required: do not display the button at all.
			} elseif ( ! empty( $groups_template->group->need_invite ) ) {
				$button = false;
			}
		}

		return $button;
	}

	/**
	 * If a public group needs the user to register to be a group member, commenting or
	 * favoriting an activity is disabled
	 *
	 * @since  1.0.0
	 *
	 * @param  boolean $can_do Whether the current user can perform the action.
	 * @return boolean         Whether the current user can perform the action.
	 */
	public function maybe_disable_can_do( $can_do = true ) {
		global $activities_template;

		if ( empty( $activities_template ) ) {
			return $can_do;
		}

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
			$visibility = apgc_group_get_visibility_level( $activities_template->activity->item_id );

			self::$needs_group_request[ $activities_template->activity->item_id ] = ! in_array( $visibility, array( 'public-request', 'public-invite' ), true );
		}

		$can_do = (bool) self::$needs_group_request[ $activities_template->activity->item_id ];

		return $can_do;
	}

	/** Helpers *******************************************************************/

	/**
	 * Should we show the group's custom front page ?
	 *
	 * @since  1.0.0
	 */
	public static function show_front_page() {
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

		if ( ! groups_is_user_member( bp_loggedin_user_id(), $group_id ) && self::has_front_page( $group_id ) ) {
			$retval = true;
		}

		return $retval;
	}

	/**
	 * Does the group has a custom front page ?
	 *
	 * @since  1.0.0
	 *
	 * @param  integer $group_id The group ID.
	 * @return integer           The Post ID of tge custom front page.
	 */
	public static function has_front_page( $group_id = 0 ) {
		if ( empty( $group_id ) ) {
			return false;
		}

		return (int) groups_get_groupmeta( $group_id, '_altctrl_page_id', true );
	}

	/**
	 * Is current group, a public group ?
	 *
	 * @since  1.0.0
	 */
	private function is_public_group() {
		if ( bp_is_group() ) {
			$this->group = groups_get_current_group();

		} elseif ( is_admin() && ! wp_doing_ajax() ) {
			$admin_url_query = wp_parse_args( wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY ), array(
				'page'   => '',
				'action' => '',
				'gid'    => 0,
			) );

			if ( 'bp-groups' === $admin_url_query['page'] && 'edit' === $admin_url_query['action'] ) {
				$this->group = groups_get_group( (int) $admin_url_query['gid'] );
			}
		}

		if ( ! empty( $this->group->status ) ) {
			return 'public' === $this->group->status;
		}

		return false;
	}

	/**
	 * Fetch all public groups that needs a membership/invites request
	 *
	 * @todo cache this.
	 *
	 * @since  1.0.0
	 *
	 * @param  array  $groups A list of group IDs.
	 * @return object         The Public groups that needs a membership/invites request.
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

		$is_meta_key = '_altctrl_visibility_level';
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
	public function display( $group_id = null ) {}
	public function widget_display() {}

	/**
	 * Displays edit screen.
	 *
	 * @since  1.0.0
	 *
	 * @param integer $group_id The group ID.
	 */
	public function edit_screen( $group_id = null ) {
		$bp = buddypress();
		$group_id = empty( $group_id ) ? bp_get_current_group_id() : $group_id;
		$page_id  = absint( self::has_front_page( $group_id ) );

		$tabs = groups_get_groupmeta( $group_id, '_altctrl_tabs', true );
		if ( empty( $tabs ) ) {
			$tabs = array();
		}

		$group_nav_items = $bp->groups->nav->get_secondary( array( 'parent_slug' => $this->group->slug ), false );
		?>

		<?php if ( apgc_can_current_user_do_private_groups() ) : ?>
			<h4><?php esc_html_e( 'Joining group', 'altctrl-public-group' );?></h4>

			<?php apgc_group_visibility_options() ;?>

		<?php endif ;?>

		<?php if ( ! empty( $group_nav_items ) ) : ?>

			<h4><?php esc_html_e( 'Group members only tabs', 'altctrl-public-group' );?></h4>

			<?php foreach( $group_nav_items as $nav_item ) {
				if ( in_array( $nav_item->slug, array( 'home', 'send-invites', 'admin' ) ) ) {
					continue;
				}

				$item_name = preg_replace( '/([.0-9]+)/', '', $nav_item->name );
				$item_name = trim( strip_tags( $item_name ) );
				$item_slug = $nav_item->slug;
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
	 * Save the settings of the group.
	 *
	 * @since  1.0.0
	 *
	 * @param integer $group_id The group ID.
	 */
	public function edit_screen_save( $group_id = null ) {
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return false;
		}

		check_admin_referer( 'groups_edit_save_' . $this->slug, 'altctrl' );

		$group_id = ! empty( $group_id ) ? $group_id : bp_get_current_group_id();

		// Update the visibility level.
		if ( isset( $_POST['_altctrl_visibility_level'] ) ) {
			apgc_group_update_visibility_level( $group_id, $_POST['_altctrl_visibility_level'] );
		}

		$altctrl = array();

		if ( empty( $_POST['_altctrl'] ) ) {
			return;
		} else {
			$altctrl = $_POST['_altctrl'];
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

	/**
	 * Add admin inline style and script for the Public group.
	 *
	 * @since 2.0.0
	 */
	public function inline_cssjs() {
		if ( is_null( $this->group ) ) {
			return;
		}

		wp_add_inline_style( 'bp_groups_admin_css', '
			#apgc-admin ul {
				list-style: none;
			}
		' );

		wp_add_inline_script( 'bp_groups_admin_js', '
			( function($) {
				$( \'input[name="group-status"]\' ).on( \'click\', function( e ) {
					if ( \'public\' ===  $( e.currentTarget ).val() ) {
						$( \'#apgc-feedback\' ).addClass( \'hide-if-js hide-if-no-js\' );
						$( \'#apgc-admin\' ).removeClass( \'hide-if-js hide-if-no-js\' );
					} else {
						$( \'#apgc-feedback\' ).removeClass( \'hide-if-js hide-if-no-js\' );
						$( \'#apgc-admin\' ).addClass( \'hide-if-js hide-if-no-js\' );
					}
				} );
			} )( jQuery );
		' );
	}

	/**
	 * Displays the Public group's control metabox
	 *
	 * @since  2.0.0
	 *
	 * @param  integer $group_id The Group ID
	 */
	public function admin_screen( $group_id = null ) {
		?>
		<div id="apgc-feedback" class="hide-if-js hide-if-no-js">
			<p class="description"><?php esc_html_e( 'The control features are only available for public groups', 'altctrl-public-group' ); ?></p>
		</div>
		<div id="apgc-admin">
			<h4><?php esc_html_e( 'Joining group', 'altctrl-public-group' );?></h4>

			<ul><?php apgc_group_visibility_options( $group_id ) ;?></ul>

			<?php wp_nonce_field( 'groups_edit_save_' . $this->slug, 'altctrl' ); ?>
		</div>
		<?php
	}

	/**
	 * Saves the Public group's control options.
	 *
	 * @since  2.0.0
	 *
	 * @param  integer $group_id The Group ID
	 */
	public function admin_screen_save( $group_id = null ) {
		if ( ! isset( $_POST['_altctrl_visibility_level'] ) ) {
			return;
		}

		$this->edit_screen_save( $group_id );
	}

	/** Template tag **************************************************************/

	/**
	 * Display the group's custom front page
	 *
	 * @since  1.0.0
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

endif;
