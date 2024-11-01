<?php
/**
 * Plugin Name:       TelePost
 * Plugin URI:        
 * Description:       Crawl Telegram channels posts and convert your channel to Wordpress website.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Sadegh Ghanbari Shohani
 * Author URI:        https://github.com/sadeghtkd
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html        
 */

include( plugin_dir_path( __FILE__ ) . 'admin_menu.php');
include( plugin_dir_path( __FILE__ ) . 'tg_fetch.php');


/**
 * Activate the plugin.
 */
/*function tpost_sgs_activate() { 
	error_log("activate my-plugin");
}*/
add_action( 'tpost_sgs_scrapeTelegram_cron_event', 'tpost_sgs_scrapeTelegram' );
function tpost_sgs_deactivation(){
	wp_clear_scheduled_hook( 'tpost_sgs_scrapeTelegram_cron_event' );
}

//register_activation_hook( __FILE__, 'tpost_sgs_activate' );

register_deactivation_hook( __FILE__, 'tpost_sgs_deactivation' );

?>