<?php
defined('ABSPATH') or die("you do not have access to this page!");

if (class_exists( 'bbPress' )) {
	require_once( 'bbpress.php' );
}

if (defined('WPV_PATH') ){
	require_once('toolset.php');
}
