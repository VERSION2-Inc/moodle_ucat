<?php
require_once '../../config.php';
require_once $CFG->dirroot.'/mod/ucat/locallib.php';
require_once $CFG->libdir.'/tablelib.php';

$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id('ucat', $cmid);
$ucat = new ucat($cm);

require_login($cm->course, true, $cm);

$ucat->require_manager();

if (optional_param('export', 0, PARAM_BOOL)) {
    $sessions = $DB->get_records_sql('
            SELECT s.*,
                '.ucat::get_user_fields('u').'
                FROM {ucat_sessions} s
                    left JOIN {user} u ON s.userid = u.id
                WHERE ucat = :ucat
                ORDER BY s.id DESC
            ',
            ['ucat' => $cm->instance]
    );
    $o = '';
    foreach ($sessions as $session) {
        $quba = question_engine::load_questions_usage_by_activity($session->questionsusage);
        $slots = $quba->get_slots();
        $questions = '';
        foreach ($slots as $slot) {
            $at = $quba->get_question_attempt($slot);
            $questions .= ' '.$at->get_question()->name.'-'.($at->get_fraction()?'correct':'wrong');
        }
        $data = array(
            fullname($session),
            date('Y-m-d H:i:s', $session->timestarted),
            ucat::logit2unit($session->ability),
            ucat::diff_logit2unit($session->se),
            ucat::format_ability_range($session->ability, $session->se, '-'),
            $questions
        );
        $o .= implode(',', $data)."\n";
    }

    $filename = $ucat->name . '-' . date('Ymd-Hi') . '.csv';
    send_file($o, $filename, null, 0, true);

} else if ($id = optional_param('session', 0, PARAM_INT)) {
    $PAGE->set_url('/mod/ucat/view.php');
    $PAGE->set_title($cm->name);
    $url = new moodle_url('/mod/ucat/view.php', array('id' => $cmid));
    $PAGE->navbar->add($cm->name, $url);
    $session = $DB->get_record('ucat_sessions', array('id' => $id));
        $quba = question_engine::load_questions_usage_by_activity($session->questionsusage);
        $slots = $quba->get_slots();
        $questions = '';
        foreach ($slots as $slot) {
            $at = $quba->get_question_attempt($slot);
            $questions.=' <br/>'.$at->get_question()->name.'-'.($at->get_fraction()?'correct':'wrong');
        }
    echo $OUTPUT->header();

    echo $OUTPUT->heading($cm->name);

    $sessionobj=new ucat_session($session->id);
    echo $sessionobj->render_report();
        echo $questions;
    echo $OUTPUT->footer();

} else {

    $PAGE->set_url('/mod/ucat/view.php');
    $PAGE->set_title($cm->name);
    $PAGE->set_heading($cm->name);
    $url = new moodle_url('/mod/ucat/view.php', array('id' => $cmid));
    $PAGE->navbar->add(ucat::str('results'));
    $reporturl = new moodle_url('/mod/ucat/report.php');

    echo $OUTPUT->header();

    echo $OUTPUT->heading($cm->name);

    $table = new flexible_table('ucatattempts');
    $table->set_attribute('class', 'generaltable generalbox');
    $columns = array('userid', 'timestart', 'ability', 'se', 'abilityrange', 'questions');
    $headers = array(
        get_string('user'),
        get_string('timestarted', 'ucat'),
        get_string('ability', 'ucat'),
        get_string('se', 'ucat'),
        get_string('probableabilityrange', 'ucat'),
        get_string('questionsadministered', 'ucat')
    );
    $table->baseurl = $url;
    $table->define_columns($columns);
    $table->define_headers($headers);
    $table->setup();

    $sessions = $DB->get_records_sql('
            SELECT s.*,
                '.ucat::get_user_fields('u').'
                FROM {ucat_sessions} s
                    JOIN {user} u ON s.userid = u.id
                WHERE ucat = :ucat
                ORDER BY s.timestarted DESC
            ',
            ['ucat' => $cm->instance]
    );

    foreach ($sessions as $session) {
        $quba = question_engine::load_questions_usage_by_activity($session->questionsusage);
        $slots = $quba->get_slots();
        $questions = '';
        foreach ($slots as $slot) {
            $at = $quba->get_question_attempt($slot);
            $questions.=' '.$at->get_question()->name.'-'.($at->get_fraction()?'correct':'wrong');
        }

        $user = $OUTPUT->action_link(new moodle_url('report.php', array('cmid' => $cmid,
                'session' => $session->id)), fullname($session));

        $data = array(
            $user,
            userdate($session->timestarted),
            ucat::logit2unit($session->ability),
            ucat::diff_logit2unit($session->se),
            ucat::format_ability_range($session->ability, $session->se),
            $questions
        );
        $table->add_data($data);
    }

    $table->finish_html();

    echo $OUTPUT->single_button(
        new moodle_url('/mod/ucat/report.php', array('cmid' => $cmid, 'export' => 1)),
        ucat::str('export'));

    echo $OUTPUT->footer();
}
