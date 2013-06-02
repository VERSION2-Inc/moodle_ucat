<?php
require_once '../../config.php';
require_once $CFG->dirroot.'/mod/ucat/locallib.php';
require_once $CFG->libdir.'/tablelib.php';

$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id('ucat', $cmid);
$ucat = new ucat($cm);

require_login($cm->course, true, $cm);

if (optional_param('export', 0, PARAM_BOOL)) {
    $sessions = $DB->get_records_sql('
            SELECT s.*, u.lastname, u.firstname
                FROM {ucat_sessions} s
                    left JOIN {user} u ON s.userid = u.id
                ORDER BY s.id DESC
            '
    );
    foreach ($sessions as $session) {
            $quba = question_engine::load_questions_usage_by_activity($session->questionsusage);
        $slots = $quba->get_slots();
        $questions = '';
        foreach ($slots as $slot) {
            $at = $quba->get_question_attempt($slot);
            $questions.=' '.$at->get_question()->name.'-'.($at->get_fraction()?'correct':'wrong');
        }
        $data = array(
                fullname($session),
                ucat::logit2unit($session->ability),
                ucat::logit2unit($session->se),
                ucat::format_ability_range($session->ability, $session->se),
                $questions
        );
        echo implode(',', $data)."\n";
    }

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
    $url = new moodle_url('/mod/ucat/view.php', array('id' => $cmid));
    $PAGE->navbar->add($cm->name, $url);
    $reporturl = new moodle_url('/mod/ucat/report.php');

    echo $OUTPUT->header();

    echo $OUTPUT->heading($cm->name);

    $table = new flexible_table('ucatattempts');
    $table->set_attribute('class', 'generaltable generalbox');
    $columns = array('userid', 'ability', 'se', 'abilityrange', 'questions');
    $headers = array(
            'User',
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
            SELECT s.*, u.lastname, u.firstname
                FROM {ucat_sessions} s
                    left JOIN {user} u ON s.userid = u.id
                ORDER BY s.id DESC
            '
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
                ucat::logit2unit($session->ability),
                ucat::logit2unit($session->se),
                ucat::format_ability_range($session->ability, $session->se),
                $questions
        );
        $table->add_data($data);
    }

    $table->finish_html();

    echo $OUTPUT->single_button(new moodle_url('/mod/ucat/report.php', array('cmid' => $cmid, 'export' => 1)), 'Export');

    echo $OUTPUT->footer();
}
