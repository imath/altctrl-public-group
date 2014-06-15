<?php
/**
 * Group's front page for non members
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'Alt_Public_Group_Ctrl' ) ) exit;
?>

<div class="group-custom-front">
	<?php Alt_Public_Group_Ctrl::the_content() ;?>
</div>
