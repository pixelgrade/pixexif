<?php
/*
* @package   PixEXIF
* @author    pixelgrade <contact@pixelgrade.com>
* @license   GPL-2.0+
* @link      https://pixelgrade.com
* @copyright 2015 Pixel Grade Media
*
* @wordpress-plugin
Plugin Name: PixEXIF
Plugin URI:  https://pixelgrade.com
Description:  Allows you to edit image EXIF metadata.
Version: 1.0.0
Author: pixelgrade
Author URI: https://pixelgrade.com
Author Email: contact@pixelgrade.com
Text Domain: pixexif
License:     GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Domain Path: /lang
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// ensure EXT is defined
if ( ! defined('EXT')) {
    define('EXT', '.php');
}

require_once( plugin_dir_path( __FILE__ ) . 'class-pixexif.php' );

global $pixexif_plugin;
$pixexif_plugin = PixExifPlugin::get_instance();