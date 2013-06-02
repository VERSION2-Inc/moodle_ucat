<?php
/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 * @version $Id: quizzes.php 18 2012-05-17 12:19:29Z yama $
 */

require_once '../../config.php';
require_once 'lib.php';
require_once $CFG->libdir.'/tablelib.php';

class cat_quizzes {
    public function render() {
        global $DB, $PAGE, $OUTPUT, $course, $context;

        $PAGE->set_course($course);
        $PAGE->set_url('/blocks/cat_manager/quizzes.php');
        $PAGE->set_title(get_string('quizzes', 'block_cat_manager'));
        $PAGE->set_heading($course->fullname);
        $PAGE->navbar->add(get_string('catadmin', 'block_cat_manager'));
        $PAGE->navbar->add(get_string('quizzes', 'block_cat_manager'));

        echo $OUTPUT->header();

        $columns = array(
                'name',
                'usecat',
                'userset',
                'endcondition',
                'questions',
                'se',
                'record',
                'logitbias',
                'showstate'
        );
        $headers = array(
                get_string('quizname', 'block_cat_manager'),
                get_string('usecatfeature', 'block_cat_manager'),
                get_string('studentlist', 'block_cat_manager'),
                get_string('endcondition', 'block_cat_manager'),
                get_string('numquestions', 'block_cat_manager'),
                get_string('se', 'block_cat_manager'),
                get_string('recordstate', 'block_cat_manager'),
                get_string('logitbias', 'block_cat_manager'),
                get_string('showstate', 'block_cat_manager')
        );
        $table = new flexible_table('mod-quiz');
        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->define_baseurl(new moodle_url('/blocks/cat_manager/quizzes.php'));
        $table->set_attribute('class', 'generaltable generalbox');

        $table->setup();

        $usersets = $DB->get_records('cat_user_sets');
        $opts_userset = array();
        foreach ($usersets as $userset) {
            $opts_userset[$userset->id] = $userset->name;
        }
        $opts_endcondition = array(
            CAT_ENDCOND_ALL => get_string('allquestions', 'block_cat_manager'),
            CAT_ENDCOND_NUMQUEST => get_string('bynumquestions', 'block_cat_manager'),
            CAT_ENDCOND_SE => get_string('byse', 'block_cat_manager'),
            CAT_ENDCOND_NUMQUESTANDSE => get_string('bynumquestionsandse', 'block_cat_manager'));

        $opts_questioncategory = array();

        echo html_writer::start_tag('form', array('action' => 'quizzes.php', 'method' => 'post'))
            .html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'course', 'value' => $course->id))
            .html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'data', 'value' => 1));

        $quizzes = $DB->get_records('quiz', array('course' => $course->id), 'name');
        foreach ($quizzes as $quiz) {
            if (!$DB->record_exists('cat_quizzes', array('quiz' => $quiz->id))) {
                $cquiz = new stdClass();
                $cquiz->quiz = $quiz->id;
                $DB->insert_record('cat_quizzes', $cquiz);
            }
            $cquiz = $DB->get_record('cat_quizzes', array('quiz' => $quiz->id));
            $prefix = 'quizzes['.$cquiz->id.']';
            $table->add_data(array(
                    $quiz->name,
                    html_writer::select_yes_no($prefix.'[usecat]', $cquiz->usecat),
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
                $quiz = (object) $quiz;
                $quiz->id = $id;
                $DB->update_record('cat_quizzes', $quiz);
            }
        }
    }
}

$course = required_param('course', PARAM_INT);
$course = $DB->get_record('course', array('id' => $course));
$context = get_context_instance(CONTEXT_COURSE, $course->id);

require_capability('mod/quiz:manage', $context);

if (optional_param('data', 0, PARAM_BOOL)) {
    cat_quizzes::update();
}

cat_quizzes::render();
