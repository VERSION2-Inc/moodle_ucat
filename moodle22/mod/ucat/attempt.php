<?php
require_once '../../config.php';
require_once $CFG->dirroot.'/mod/ucat/locallib.php';

$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id('ucat', $cmid);
$ucat = new ucat($cm);

require_login($cm->course, true, $cm);

if ($sid = optional_param('session', null, PARAM_INT)) {
    $session = new ucat_session($sid);
} else {
    $sessionrecord = $ucat->create_session($cm->instance);
    $session = new ucat_session($sessionrecord->id);
}

$PAGE->set_url('/mod/ucat/view.php');
$PAGE->set_title($cm->name);
$PAGE->set_heading($cm->name);
$url = new moodle_url('/mod/ucat/view.php', array('id' => $cmid));

echo $OUTPUT->header();

echo $OUTPUT->heading($cm->name);

if ($session->check_ending_condition()) {
    $session->finish();
}

if ($session->session->status >= ucat_session::STATUS_FINISHED) {
    echo $session->render_report();
} else {
    if ($session->session->status == ucat_session::STATUS_ASKED) {
        if (optional_param('next', null, PARAM_BOOL)) {
            $session->process_session();
        }
    }
    if ($session->session->status != ucat_session::STATUS_ASKED) {
        $question = $session->get_next_question();
        echo $session->render_question($question->id);
    } else if ($session->currentquestion) {
        echo $session->render_question($session->currentquestion);
    }
}
$session->get_ability();

echo $OUTPUT->footer();
