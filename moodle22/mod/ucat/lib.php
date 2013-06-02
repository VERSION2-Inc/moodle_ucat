<?php
/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 * @version $Id: lib.php 23 2012-09-04 00:24:02Z yama $
 */

defined('MOODLE_INTERNAL') || die();

/**
 *
 * @param stdClass $ucat
 * @return int
 */
function ucat_add_instance(stdClass $ucat) {
    global $DB;

    $ucat->id = $DB->insert_record('ucat', $ucat);

    return $ucat->id;
}

/**
 *
 * @param stdClass $ucat
 * @return bool
 */
function ucat_update_instance(stdClass $ucat) {
    global $DB;

    $ucat->id = $ucat->instance;
    $DB->update_record('ucat', $ucat);

    return true;
}

/**
 *
 * @param int $id
 * @return bool
 */
function ucat_delete_instance($id) {
    global $DB;
    $DB->delete_records('ucat', array('id' => $id));
    return true;
}
