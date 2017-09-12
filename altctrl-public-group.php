<?php
/**
 * @author    imath
 * @license   GPL-2.0+
 * @link      https://imathi.eu
 *
 * @buddypress-plugin
 * Plugin Name:       Alternative Public Group Control
 * Plugin URI:        https://imathi.eu/2014/06/15/altctrl-public-group/
 * Description:       Adds visibility levels to BuddyPress public groups.
 * Version:           2.0.0-beta
 * Author:            imath
 * Author URI:        https://imathi.eu
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
		$this->version       = '2.0.0-beta';
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

		spl_autoload_register( array( $this, 'autoload' ) );

		require $this->includes_dir . 'functions.php';

		if ( is_admin() ) {
			require $this->includes_dir . 'settings.php';
		}
	}

	/**
	 * Class Autoload function
	 *
	 * @since  2.0.0
	 *
	 * @param  string $class The class name.
	 */
	public function autoload( $class ) {
		$name = str_replace( '_', '-', strtolower( $class ) );

		if ( 0 !== strpos( $name, 'apgc' ) ) {
			return;
		}

		$path = $this->includes_dir . "classes/class-{$name}.php";

		// Sanity check.
		if ( ! file_exists( $path ) ) {
			return;
		}

		require $path;
	}
}
add_action( 'bp_include', array( 'Alt_Public_Group_Ctrl_Loader', 'start' ) );
