<?php
/**
 * Group's front page for non members
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'APGC_Group_Extension' ) ) exit;
?>

<div class="group-custom-front">
	<?php APGC_Group_Extension::the_content() ;?>
</div>
