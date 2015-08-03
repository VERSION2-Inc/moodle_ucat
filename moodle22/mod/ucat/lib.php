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
function ucat_add_instance($ucat, $form) {
    global $DB;

    $ucat->id = $DB->insert_record('ucat', $ucat);

//     ucat::save_target_probability($ucat);

    return $ucat->id;
}

/**
 *
 * @param stdClass $ucat
 * @return bool
 */
function ucat_update_instance($ucat, $form) {
    global $DB;

    $ucat->id = $ucat->instance;
    $DB->update_record('ucat', $ucat);

//     ucat::save_target_probability($ucat);

    return true;
}

/**
 *
 * @param int $id
 * @return bool
 */
function ucat_delete_instance($id) {
    global $DB;

    $DB->delete_records('ucat_target_probabilities', array('ucat' => $id));

    $sessions = $DB->get_records('ucat_sessions', array('ucat' => $id), '', 'id');
    foreach ($sessions as $sess)
        $DB->delete_records('ucat_records', array('ucatsession' => $sess->id));

    $DB->delete_records('ucat_sessions', array('ucat' => $id));

    $DB->delete_records('ucat', array('id' => $id));

    return true;
}
