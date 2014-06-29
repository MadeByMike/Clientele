<?php
/**
 * Plugin Name: Clientele
 * Description: Clientele is a modular client relationship management tool built into WordPress. With a highly configurable client database at it's core, add-on modules make it easy for you to leverage your client data for different tasks. Best of all, it's all stored in your database and controlled through the familiar WordPress interface.
 * Author: Mike Riethmuller
 * Version: 1.0
 * Licence: GPL2
 *
 * @package clientele
 *
 */
/*  
Copyright 2014 - Mike Riethmuller

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Define constants
define('clientele_VERSION', '0.1');
define('clientele_ROOT', dirname(__FILE__));
define('clientele_URL', plugins_url('/', __FILE__));
define('clientele_uninst_path', (__FILE__));
// Load classes
require_once(clientele_ROOT . '/core/class-clientele.php');
require_once(clientele_ROOT . '/core/class-module.php');
require_once(clientele_ROOT . '/core/class-event.php');
require_once(clientele_ROOT . '/core/class-clientele-table.php');

do_action('clientele_core_loaded');

// Create clientele object
global $clientele;
$clientele = new clienteleApp();
// Instantiate clientele_Modules object
global $clientele_modules;
$clientele_modules = new clienteleModule();
// Instantiate clientele_events object
global $clientele_events;
$clientele_events = new clienteleEvent();
error_reporting(E_ALL);
?>