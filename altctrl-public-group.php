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
 * Version:           2.0.0-alpha
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
	}

	/**
	 * Set the includes and templates dirs
	 */
	private function setup_globals() {
		$this->version       = '2.0.0-alpha';
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

		return version_compare( BP_VERSION, '2.6.0', '>=' );
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

		require $this->includes_dir . 'functions.php';
		require $this->includes_dir . 'alt-public-group-ctrl.php';

		if ( is_admin() ) {
			require $this->includes_dir . 'settings.php';
		}
	}
}
add_action( 'bp_include', array( 'Alt_Public_Group_Ctrl_Loader', 'start' ) );
