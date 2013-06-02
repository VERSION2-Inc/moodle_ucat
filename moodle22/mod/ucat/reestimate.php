<?php
require_once '../../config.php';
require_once $CFG->dirroot . '/mod/ucat/locallib.php';

$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id('ucat', $cmid);
$ucat = $DB->get_record('ucat', array('id' => $cm->instance));

require_login($cm->course, true, $cm);

$PAGE->set_url('/mod/ucat/view.php');
$PAGE->set_title($cm->name);
$PAGE->set_heading($cm->name);
$url = new moodle_url('/mod/ucat/view.php', array('id' => $cmid));

echo $OUTPUT->header();

echo $OUTPUT->heading($cm->name);

$ucatobj = new ucat($cm);
$ucatobj->reestimate();

echo $OUTPUT->footer();
