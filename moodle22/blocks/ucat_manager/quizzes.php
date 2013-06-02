<?php
/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 * @version $Id: quizzes.php 23 2012-09-04 00:24:02Z yama $
 */

require_once '../../config.php';
require_once 'lib.php';
require_once $CFG->libdir.'/tablelib.php';

class cat_quizzes {
    public function render() {
        global $DB, $PAGE, $OUTPUT, $course, $context;

        $PAGE->set_course($course);
        $PAGE->set_url('/blocks/ucat_manager/quizzes.php');
        $PAGE->set_title(get_string('quizzes', 'block_ucat_manager'));
        $PAGE->set_heading($course->fullname);
        $PAGE->navbar->add(get_string('catadmin', 'block_ucat_manager'));
        $PAGE->navbar->add(get_string('quizzes', 'block_ucat_manager'));

        echo $OUTPUT->header();

        $columns = array(
                'name',
                'userset',
                'endcondition',
                'questions',
                'se',
                'record',
                'logitbias',
                'showstate'
        );
        $headers = array(
                get_string('quizname', 'block_ucat_manager'),
                get_string('userset', 'ucat'),
                get_string('endcondition', 'ucat'),
                get_string('numquestions', 'ucat'),
                get_string('se', 'ucat'),
                get_string('recordstatus', 'ucat'),
                get_string('logitbias', 'ucat'),
                get_string('showstatus', 'ucat')
        );
        $table = new flexible_table('mod-quiz');
        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->define_baseurl(new moodle_url('/blocks/cat_manager/quizzes.php'));
        $table->set_attribute('class', 'generaltable generalbox');

        $table->setup();

        $usersets = $DB->get_records('ucat_user_sets');
        $opts_userset = array();
        foreach ($usersets as $userset) {
            $opts_userset[$userset->id] = $userset->name;
        }
        $opts_endcondition = array(
            CAT_ENDCOND_ALL => get_string('allquestions', 'ucat'),
            CAT_ENDCOND_NUMQUEST => get_string('bynumquestions', 'ucat'),
            CAT_ENDCOND_SE => get_string('byse', 'ucat'),
            CAT_ENDCOND_NUMQUESTANDSE => get_string('bynumquestionsandse', 'ucat'));

        $opts_questioncategory = array();

        echo html_writer::start_tag('form', array('action' => 'quizzes.php', 'method' => 'post'))
            .html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'course', 'value' => $course->id))
            .html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'data', 'value' => 1));

        $quizzes = $DB->get_records('ucat', array('course' => $course->id), 'name');
        foreach ($quizzes as $quiz) {
            $cquiz=$quiz;
            $prefix = 'quizzes['.$cquiz->id.']';
            $table->add_data(array(
                    $quiz->name,
                    html_writer::select($opts_userset, $prefix.'[userset]', $cquiz->userset),
                    html_writer::select($opts_endcondition, $prefix.'[endcondition]', $cquiz->endcondition),
                    html_writer::empty_tag('input',
                            array('name' => $prefix.'[questions]', 'value' => $cquiz->questions, 'size' => 4)),
                    html_writer::empty_tag('input',
                            array('name' => $prefix.'[se]', 'value' => $cquiz->se, 'size' => 4)),
                    html_writer::select_yes_no($prefix.'[record]', $cquiz->record),
                    html_writer::empty_tag('input',
                            array('name' => $prefix.'[logitbias]', 'value' => $cquiz->logitbias, 'size' => 4)),
                    html_writer::select_yes_no($prefix.'[showstate]', $cquiz->showstate)
            ));
        }

        echo $table->finish_output();

        echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('savechanges')))
            .html_writer::end_tag('form');

        echo $OUTPUT->footer();
    }

    public function update() {
        global $DB;

        $quizzes = required_param('quizzes', PARAM_TEXT);
        if ($quizzes) {
            foreach ($quizzes as $id => $quiz) {
                $quiz = (object)$quiz;
                $quiz->id = $id;
                $DB->update_record('ucat', $quiz);
            }
        }
    }
}

$course = required_param('course', PARAM_INT);
$course = $DB->get_record('course', array('id' => $course));
$context = context_course::instance($course->id);

require_capability('mod/quiz:manage', $context);

if (optional_param('data', 0, PARAM_BOOL)) {
    cat_quizzes::update();
}

cat_quizzes::render();
