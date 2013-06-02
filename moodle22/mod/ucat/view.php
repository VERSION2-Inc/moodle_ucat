<?php
require_once '../../config.php';

$cmid = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('ucat', $cmid);
$ucat = $DB->get_record('ucat', array('id' => $cm->instance));

require_login($cm->course, true, $cm);

$PAGE->set_url('/mod/ucat/view.php');
$PAGE->set_title($cm->name);
$url = new moodle_url('/mod/ucat/view.php', array('id' => $cmid));

echo $OUTPUT->header();

echo $OUTPUT->heading($cm->name);

echo '<div><a href="report.php?cmid='.$cmid.'">'.get_string('results', 'ucat').'</a></div>';

echo $OUTPUT->box_start()
    .format_text($ucat->intro, $ucat->introformat)
    .$OUTPUT->box_end();

echo $OUTPUT->single_button(
        new moodle_url('/mod/ucat/attempt.php', array('cmid' => $cm->id)),
        get_string('startattempt', 'ucat')
);

if (has_capability('mod/ucat:manage', context_module::instance($cmid))) {
    echo $OUTPUT->single_button(
            new moodle_url('/mod/ucat/reestimate.php', array('cmid' => $cm->id)),
            get_string('reestimatedifficulties', 'ucat')
    );
}

echo $OUTPUT->footer();
