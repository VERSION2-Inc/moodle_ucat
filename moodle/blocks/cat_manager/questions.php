<?php
/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 * @version $Id$
 */

require_once '../../config.php';
require_once(dirname(__FILE__) . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$qcatid = optional_param('qcat', 0, PARAM_INT);
$context = get_context_instance(CONTEXT_COURSE, $course->id);

require_capability('mod/quiz:manage', $context);

$PAGE->set_course($course);
$PAGE->set_url('/blocks/cat_manager/questions.php');
$PAGE->set_title(get_string('questions', 'block_cat_manager'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('catadmin', 'block_cat_manager'));
$PAGE->navbar->add(get_string('questions', 'block_cat_manager'));
$PAGE->requires->js('/blocks/cat_manager/module.js');

// とりあえずカテゴリー考えない
$questions = $DB->get_records('question', array('category' => $qcatid));
$catquestions = $DB->get_records('cat_questions');

if (optional_param('save', 0, PARAM_BOOL)) {
    foreach ($questions as $question) {
        if (($difficulty = optional_param('diff_' . $question->id, -1, PARAM_INT)) >= 0) {
            $difficulty = cat_unit2logit($difficulty);

            if ($catquestion = $DB->get_record('cat_questions',
                                           array('questionid' => $question->id))) {
                $catquestion->difficulty = $difficulty;
                $DB->update_record('cat_questions', $catquestion);
            } else {
                $catquestion = new stdClass();
                $catquestion->id = $question->id;
                $catquestion->questionid = $question->id;
                $catquestion->difficulty = $difficulty;
                $DB->insert_record('cat_questions', $catquestion);
            }
        }
    }
}

echo $OUTPUT->header();

$qcats = $DB->get_records('question_categories');

echo '
  <form action="questions.php">
    <input type="hidden" name="courseid" value="' . $courseid . '"/>
    <select name="qcat" onchange="this.form.submit()">
      <option value="0">' . get_string('select') . '</option>';
foreach ($qcats as $qcat) {
    if ($qcat->id == $qcatid) {
        $selected = ' selected="selected"';
    } else {
        $selected = '';
    }
    echo '<option value="' . $qcat->id . '"' . $selected . '>' . $qcat->name . '</option>';
}
echo '
    </select>
  </form>';

if ($qcatid) {
    $questions = $DB->get_records('question', array('category' => $qcatid));
    $catquestions = $DB->get_records('cat_questions');

    $opts = '';
    $def = 0;
    foreach (range(200, 0) as $d) {
        if ($d == $def) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $opts .= '<option value="' . $d . '"' . $selected . '>' . $d . '</option>';
    }
    echo '
  <form action="questions.php" method="post">
    <input type="hidden" name="courseid" value="' . $courseid . '"/>
    <input type="hidden" name="qcat" value="' . $qcatid . '"/>
    <table class="generaltable">
      <tr>
        <td class="cell">
          <input type="checkbox" onclick="cat_checkall(this)"/>
        </td>
        <td class="cell">
          <input type="button" value="' . get_string('copy') . '"
            onclick="cat_copy_data(\'diff_\')"/>
        </td>
        <td class="cell">
          <select id="diff_batch">
          ' . $opts . '
          </select>
        </td>
      </tr>';

    foreach ($questions as $question) {
        $opts = '';
        $def = 50;
        foreach ($catquestions as $catquestion) {
            if ($catquestion->questionid == $question->id) {
                $def = cat_logit2unit($catquestion->difficulty);
                break;
            }
        }
        foreach (range(200, 0) as $d) {
            if ($d == $def) {
                $selected = ' selected="selected"';
            } else {
                $selected = '';
            }
            $opts .= '<option value="' . $d . '"' . $selected . '>' . $d . '</option>';
        }

        echo '
      <tr>
        <td class="cell">
          <input type="checkbox" id="chk_' . $question->id . '"/>
        </td>
        <td class="cell">' . $question->name . '</td>
        <td class="cell">
          <select name="diff_' . $question->id . '"
            id="diff_' . $question->id . '">' . $opts . '</select>
        </td>
      </tr>';
    }
    echo '
    </table>
    <input type="submit" name="save" value="' . get_string('savechanges') . '"/>
  </form>';
}

echo '
  <p><a href="' . $CFG->wwwroot . '/course/view.php?id=' . $courseid . '">' . get_string('returntocourse', 'block_cat_manager') . '</a></p>';
echo $OUTPUT->footer();
