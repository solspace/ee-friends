<?php if ( ! defined('EXT')) exit('No direct script access allowed');


/**
 * Friends - Config
 *
 * @package		Solspace:Friends
 * @author		Solspace, Inc.
 * @copyright	Copyright (c) 2010-2016, Solspace, Inc.
 * @link		https://solspace.com/expressionengine/friends
 * @license		https://solspace.com/software/license-agreement
 * @version		1.6.5
 * @filesource	friends/config.php
 */

require_once 'constants.friends.php';

$config['name']									= 'Friends';
$config['version']								= FRIENDS_VERSION;
$config['nsm_addon_updater']['versions_xml'] 	= 'http://solspace.com/software/nsm_addon_updater/friends';
