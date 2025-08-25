<?php
/**
 * BRAG book Plugin
 *
 * This file is used to generate all plugin information. Including all the
 * dependencies used by the plugin, registers the activation and deactivation
 * functions, and defines a function that starts the plugin.
 *
 * @author     Candace Crowe Design <bragbook@candacecrowe.com>
 * @copyright  Copyright (c) 2025, Candace Crowe Design LLC
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @link       https://www.bragbookgallery.com/
 * @package    BRAGBookGallery
 * @since      3.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       BRAG book Gallery
 * Plugin URI:        https://www.bragbookgallery.com/
 * Description:       BRAG book before and after gallery.
 * Version:           3.0.5
 * Requires at Least: 6.8
 * Requires PHP:      8.2
 * Author:            Candace Crowe Design <bragbook@candacecrowe.com>
 * Author URI:        https://www.bragbookgallery.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       brag-book-gallery
 */

namespace BRAGBookGallery;

use BRAGBookGallery\Includes\Core\Setup;
use BRAGBookGallery\Includes\Core\Updater;

if ( ! defined( constant_name: 'WPINC' ) ) {
	die( 'Restricted Access' );
}

require_once 'includes/autoload.php';

// Initialize plugin setup and define constants
Setup::init_plugin( __FILE__ );

register_deactivation_hook(
	__FILE__,
	function () {
		flush_rewrite_rules();
	}
);

add_action( 'admin_init', function () {
	if ( is_admin() ) {
		new Updater(
			__FILE__,
			'bragbook2',
			'brag-book-gallery',
		);
	}
} );
