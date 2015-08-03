<?php
/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 * @version $Id: version.php 19 2012-07-27 09:50:38Z yama $
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2015072900;
$plugin->requires = 2011112900;
$plugin->component = 'block_ucat_manager';
$plugin->dependencies = ['mod_ucat' => 2015072300];
