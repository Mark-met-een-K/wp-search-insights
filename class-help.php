<?php
defined('ABSPATH') or die("you do not have access to this page!");
if ( ! class_exists( 'wpsi_help' ) ) {
    class wpsi_help {
        private static $_this;

        function __construct() {
            if ( isset( self::$_this ) )
                wp_die( sprintf( __( '%s is a singleton class and you cannot create a second instance.','wp-search-insights' ), get_class( $this ) ) );

            self::$_this = $this;
        }

        static function this() {
            return self::$_this;
        }

	    /**
	     * @param   string   $str
	     *
	     * @return false|string
	     */

        public function get_help_tip($str){
	        ob_start();
            ?>
            <span class="wpsi-tooltip-right" data-wpsi-tooltip="<?php echo $str?>">
                <span class="dashicons dashicons-editor-help"></span>
            </span>
            <?php
	        return ob_get_clean();
        }

	    public function get_title_help_tip($str){
		    ob_start();
		    ?>
		    <span class="wpsi-tooltip-bottom" data-wpsi-tooltip="<?php echo $str?>">
                <span class="dashicons dashicons-editor-help"></span>
            </span>
		    <?php
		    return ob_get_clean();
	    }
    }//class closure
} //if class exists closure
