<?php
/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 * @version $Id: questions.php 23 2012-09-04 00:24:02Z yama $
 */

require_once '../../config.php';
require_once(dirname(__FILE__) . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$qcatid = optional_param('qcat', 0, PARAM_INT);
$context = get_context_instance(CONTEXT_COURSE, $course->id);

require_capability('mod/quiz:manage', $context);

$PAGE->set_course($course);
$PAGE->set_url('/blocks/ucat_manager/questions.php');
$PAGE->set_title(get_string('questions', 'block_ucat_manager'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('catadmin', 'block_ucat_manager'));
$PAGE->navbar->add(get_string('questions', 'block_ucat_manager'));
$PAGE->requires->js('/blocks/ucat_manager/module.js');

// とりあえずカテゴリー考えない
$questions = $DB->get_records('question', array('category' => $qcatid));
$catquestions = $DB->get_records('ucat_questions');

if (optional_param('save', 0, PARAM_BOOL)) {
    foreach ($questions as $question) {
        if (($difficulty = optional_param('diff_' . $question->id, -1, PARAM_INT)) >= 0) {
            $difficulty = cat_unit2logit($difficulty);

            if ($catquestion = $DB->get_record('ucat_questions',
                                           array('questionid' => $question->id))) {
                $catquestion->difficulty = $difficulty;
                $DB->update_record('ucat_questions', $catquestion);
            } else {
                $catquestion = new stdClass();
                $catquestion->id = $question->id;
                $catquestion->questionid = $question->id;
                $catquestion->difficulty = $difficulty;
                $DB->insert_record('ucat_questions', $catquestion);
            }
        }
    }
} else if (optional_param('export', 0, PARAM_BOOL)) {
    $questions = $DB->get_records('question', array('category' => $qcatid));
    $catquestions = $DB->get_records('ucat_questions');

    echo implode(',',array(
            'ID',
            get_string('name'),
            get_string('ability', 'ucat')))."\n";
    foreach ($questions as $questions) {
            $def = 100;
                foreach ($catquestions as $catquestion) {
                if ($catquestion->questionid == $question->id) {
                    $def = cat_logit2unit($catquestion->difficulty);
                    break;
                }
            }


            echo implode(',',
                    array(
                            $question->id,
                            $catquestion->difficulty,
                            $def
                    ))."\n";
    }
    exit();

} else if (optional_param('import', 0, PARAM_BOOL)) {
    $fp = fopen($_FILES['file']['tmp_name'], 'r');
    fgets($fp);
    while ($row = fgetcsv($fp)) {
        list($id, $name, $difficulty) = $row;

        if ($cquestion = $DB->get_record('ucat_questions', array('questionid' => $id))) {
            $cquestion->difficulty = $difficulty;
            $DB->update_record('ucat_questions', $cquestion);
        } else {
            $cquestion = new stdClass();
            $cquestion->questionid = $id;
            $cquestion->difficulty = $difficulty;
            $DB->insert_record('ucat_questions', $cquestion);
        }
    }
    fclose($fp);
    redirect("$CFG->wwwroot/blocks/ucat_manager/questions.php?courseid=$courseid");
}

echo $OUTPUT->header();

$qcats = $DB->get_records('question_categories');


        $contexts = new question_edit_contexts(context_course::instance($COURSE->id));
//         $mform->addElement('selectgroups', 'questioncategory', get_string('questioncategory', 'ucat'),
//                 question_category_options($contexts->having_cap('moodle/question:add')));
$opts = question_category_options($contexts->having_cap('moodle/question:add'));
echo '
  <form action="questions.php">
    <input type="hidden" name="courseid" value="' . $courseid . '"/>
    <select name="qcat" onchange="this.form.submit()">
      <option value="0">' . get_string('select') . '</option>';
foreach ($opts as $optgroup) {
    foreach ($optgroup as $id => $qcat) {
        if ($id == $qcatid) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        echo '<option value="' . $id . '"' . $selected . '>' . $qcat . '</option>';
    }
}
echo '
    </select>
  </form>';

if ($qcatid) {
    $questions = $DB->get_records('question', array('category' => $qcatid));
    $catquestions = $DB->get_records('ucat_questions');

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

        echo $OUTPUT->box_start()
            .$OUTPUT->single_button(new moodle_url('questions.php', array('export' => '1',
                    'courseid' => $courseid
                  )), 'Export')
                   .'<div class="center">
                   <form action="questions.php" method="post" enctype="multipart/form-data">
                   <input type="hidden" name="import" value="1"/>
                   <input type="hidden" name="courseid" value="'.$courseid.'"/>
                   <input type="file" name="file"/>
                   <input type="submit" value="Import"/>
                   </form>
                   </div>'
            .$OUTPUT->box_end();
}

echo '
  <p><a href="' . $CFG->wwwroot . '/course/view.php?id=' . $courseid . '">' . get_string('returntocourse', 'block_ucat_manager') . '</a></p>';
echo $OUTPUT->footer();
