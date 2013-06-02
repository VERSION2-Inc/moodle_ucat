<?php
/**
 * This page lists all the instances of quiz in a particular course
 *
 * @author Martin Dougiamas and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package quiz
 */
    require_once("../../config.php");
    require_once("locallib.php");

    $id = required_param('id', PARAM_INT);
    $PAGE->set_url('/mod/quiz/index.php', array('id'=>$id));
    if (!$course = $DB->get_record('course', array('id' => $id))) {
        print_error('invalidcourseid');
    }
    $coursecontext = get_context_instance(CONTEXT_COURSE, $id);
    require_login($course->id);
    $PAGE->set_pagelayout('incourse');

    add_to_log($course->id, "quiz", "view all", "index.php?id=$course->id", "");

// Print the header
    $strquizzes = get_string("modulenameplural", "quiz");
    $streditquestions = '';
    $editqcontexts = new question_edit_contexts($coursecontext);
    if ($editqcontexts->have_one_edit_tab_cap('questions')) {
        $streditquestions =
                "<form target=\"_parent\" method=\"get\" action=\"$CFG->wwwroot/question/edit.php\">
                   <div>
                   <input type=\"hidden\" name=\"courseid\" value=\"$course->id\" />
                   <input type=\"submit\" value=\"".get_string("editquestions", "quiz")."\" />
                   </div>
                 </form>";
    }
    $PAGE->navbar->add($strquizzes);
    $PAGE->set_title($strquizzes);
    $PAGE->set_button($streditquestions);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

// Get all the appropriate data
    if (!$quizzes = get_all_instances_in_course("quiz", $course)) {
        notice(get_string('thereareno', 'moodle', $strquizzes), "../../course/view.php?id=$course->id");
        die;
    }
    $sections = get_all_sections($course->id);

// Check if we need the closing date header
    $showclosingheader = false;
    $showfeedback = false;
    foreach ($quizzes as $quiz) {
        if ($quiz->timeclose!=0) {
            $showclosingheader=true;
        }
        if (quiz_has_feedback($quiz)) {
            $showfeedback=true;
        }
        if($showclosingheader && $showfeedback) {
            break;
        }
    }

// Configure table for displaying the list of instances.
    $headings = array(get_string('name'));
    $align = array('left');

    if ($showclosingheader) {
        array_push($headings, get_string('quizcloses', 'quiz'));
        array_push($align, 'left');
    }

    array_unshift($headings, get_string('sectionname', 'format_'.$course->format));
    array_unshift($align, 'center');

    $showing = '';  // default

    if (has_capability('mod/quiz:viewreports', $coursecontext)) {
        array_push($headings, get_string('attempts', 'quiz'));
        array_push($align, 'left');
        $showing = 'stats';
    } else if (has_any_capability(array('mod/quiz:reviewmyattempts', 'mod/quiz:attempt'), $coursecontext)) {
        array_push($headings, get_string('grade', 'quiz'));
        array_push($align, 'left');
        if ($showfeedback) {
            array_push($headings, get_string('feedback', 'quiz'));
            array_push($align, 'left');
        }
        $showing = 'scores';  // default

        $scores = $DB->get_records_sql_menu('
                SELECT qg.quiz, qg.grade
                FROM {quiz_grades} qg
                JOIN {quiz} q ON q.id = qg.quiz
                WHERE q.course = ? AND qg.userid = ?',
                array($course->id, $USER->id));
    }

    $table = new html_table();
    $table->head = $headings;
    $table->align = $align;

/// Populate the table with the list of instances.
    $currentsection = '';
    foreach ($quizzes as $quiz) {
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        $data = array();

        // Section number if necessary.
        $strsection = '';
        if ($quiz->section != $currentsection) {
            if ($quiz->section) {
                $strsection = $quiz->section;
                $strsection = get_section_name($course, $sections[$quiz->section]);
            }
            if ($currentsection) {
                $learningtable->data[] = 'hr';
            }
            $currentsection = $quiz->section;
        }
        $data[] = $strsection;

        // Link to the instance.
        $class = '';
        if (!$quiz->visible) {
            $class = ' class="dimmed"';
        }
        $data[] = "<a$class href=\"view.php?id=$quiz->coursemodule\">" . format_string($quiz->name, true) . '</a>';

        // Close date.
        if ($quiz->timeclose) {
            $data[] = userdate($quiz->timeclose);
        } else if ($showclosingheader) {
            $data[] = '';
        }

        if ($showing == 'stats') {
            // The $quiz objects returned by get_all_instances_in_course have the necessary $cm
            // fields set to make the following call work.
            $data[] = quiz_attempt_summary_link_to_reports($quiz, $cm, $context);

        } else if ($showing == 'scores') {
            // Grade and feedback.
            $attempts = quiz_get_user_attempts($quiz->id, $USER->id, 'all');
            list($someoptions, $alloptions) = quiz_get_combined_reviewoptions($quiz, $attempts, $context);

            $grade = '';
            $feedback = '';
            if ($quiz->grade && array_key_exists($quiz->id, $scores)) {
                if ($alloptions->scores) {
                    $a = new stdClass;
                    $a->grade = quiz_format_grade($quiz, $scores[$quiz->id]);
                    $a->maxgrade = quiz_format_grade($quiz, $quiz->grade);
                    $grade = get_string('outofshort', 'quiz', $a);
                }
                if ($alloptions->overallfeedback) {
                    $feedback = quiz_feedback_for_grade($scores[$quiz->id], $quiz, $context, $cm);
                }
            }
            $data[] = $grade;
            if ($showfeedback) {
                $data[] = $feedback;
            }
        }

        $table->data[] = $data;
    } // End of loop over quiz instances.

// Display the table.
    echo '<br />';
    echo html_writer::table($table);

// Finish the page
    echo $OUTPUT->footer();

