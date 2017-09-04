<?php
/**
 * Experiment to provide an alternative control to group admins on their public groups.
 *
 *
 * @author    imath
 * @license   GPL-2.0+
 * @link      http://imathi.eu
 *
 * @buddypress-plugin
 * Plugin Name:       Alternative Public Group Control
 * Plugin URI:        http://imathi.eu/2014/06/15/altctrl-public-group/
 * Description:       Experimental BuddyPress plugin to provide an alternative control to group admins on their public groups.
 * Version:           1.1.0-beta
 * Author:            imath
 * Author URI:        http://imathi.eu
 * Text Domain:       altctrl-public-group
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages/
 * GitHub Plugin URI: https://github.com/imath/altctrl-public-group
 */

if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * The Alternative Public Group control Loader class
 *
 * @since 1.0.0
 */
class Alt_Public_Group_Ctrl_Loader {

	/**
	 * Let's start
	 */
	public static function start() {
		$bp = buddypress();

		if ( empty( $bp->altctrl ) ) {
			$bp->altctrl = new self;
		}

		return $bp->altctrl;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setup_globals();
		$this->includes();
		$this->setup_actions();
	}

	/**
	 * Set the includes and templates dirs
	 */
	private function setup_globals() {
		$this->version       = '1.1.0-beta';
		$this->domain        = 'altctrl-public-group';
		$this->includes_dir  = trailingslashit( plugin_dir_path( __FILE__ ) . 'includes'  );
		$this->templates_dir = trailingslashit( plugin_dir_path( __FILE__ ) . 'templates' );
		$this->lang_dir      = trailingslashit( plugin_dir_path( __FILE__ ) . 'languages' );
		$this->css_url       = trailingslashit( plugin_dir_url ( __FILE__ ) . 'css'       );
	}

	/**
	 * Checks BuddyPress version
	 * 
	 * @since 1.1.0
	 */
	public function bp_version_check() {
		// taking no risk
		if ( ! defined( 'BP_VERSION' ) ) {
			return false;
		}

		return version_compare( BP_VERSION, '2.6.0-alpha', '>=' );
	}

	/**
	 * Include the needed file
	 *
	 * @since 1.0.0
	 */
	private function includes() {
		if ( ! bp_is_active( 'groups' ) || ! $this->bp_version_check() ) {
			return;
		}

		require( $this->includes_dir . 'alt-public-group-ctrl.php' );
	}

	/**
	 * Hook to bp_init to load translation
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {
		add_action( 'bp_init', array( $this, 'load_textdomain' ), 5 );

		if ( bp_is_active( 'groups' ) && $this->bp_version_check() ) {
			add_filter( 'bp_get_template_stack', array( $this, 'add_to_template_stack' ), 10, 1 );
		}
	}

	/**
	 * Add the plugin templates folder to the BuddyPress templates stack
	 */
	public function add_to_template_stack( $templates = array() ) {
		if ( Alt_Public_Group_Ctrl::show_front_page() ) {
			$templates = array_merge( $templates, array( buddypress()->altctrl->templates_dir ) );
		}

		return $templates;
	}

	/**
	 * Load the translation files
	 * 
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/altctrl-public-group/' . $mofile;

		// Look in global /wp-content/languages/altctrl-public-group folder
		load_textdomain( $this->domain, $mofile_global );

		// Look in local /wp-content/plugins/altctrl-public-group/languages/ folder
		load_textdomain( $this->domain, $mofile_local );

		// Look in global /wp-content/languages/plugins/
		load_plugin_textdomain( $this->domain );
	}
}
add_action( 'bp_include', array( 'Alt_Public_Group_Ctrl_Loader', 'start' ) );
