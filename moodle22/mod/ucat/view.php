<?php
require_once '../../config.php';
require_once $CFG->dirroot . '/mod/ucat/locallib.php';

$cmid = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('ucat', $cmid);
$ucat = new ucat($cm);

require_login($cm->course, true, $cm);

$PAGE->set_url('/mod/ucat/view.php');
$PAGE->set_title($cm->name);
$PAGE->set_heading($cm->name);
$url = new moodle_url('/mod/ucat/view.php', array('id' => $cmid));

$ismanager = $ucat->is_manager();

echo $OUTPUT->header();

echo $OUTPUT->heading($cm->name);

if ($ismanager)
    echo '<div><a href="report.php?cmid='.$cmid.'">'.get_string('results', 'ucat').'</a></div>';

echo $OUTPUT->box_start()
    .format_text($ucat->intro, $ucat->introformat)
    .$OUTPUT->box_end();

echo $OUTPUT->single_button(
        new moodle_url('/mod/ucat/attempt.php', array('cmid' => $cm->id)),
        get_string('startattempt', 'ucat')
);

if ($ismanager) {
    echo $OUTPUT->single_button(
        new moodle_url('/mod/ucat/reestimate.php', array('cmid' => $cm->id)),
        get_string('reestimatedifficulties', 'ucat')
    );
}

echo $OUTPUT->footer();
