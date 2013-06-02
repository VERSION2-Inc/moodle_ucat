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
$context = get_context_instance(CONTEXT_COURSE, $course->id);

require_capability('mod/quiz:manage', $context);

$uset = optional_param('uset', 0, PARAM_INT);

$PAGE->set_course($course);
$PAGE->set_url('/blocks/cat_manager/questions.php');
$PAGE->set_title(get_string('users', 'block_cat_manager'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('catadmin', 'block_cat_manager'));
$PAGE->navbar->add(get_string('users', 'block_cat_manager'));

$abilityrange = range(200, 0);

if (optional_param('adduset', 0, PARAM_BOOL)) {
    $name = trim(optional_param('name', '', PARAM_TEXT));

    if ($name != '') {
        $check = true;
        if ($DB->get_record('cat_user_sets', array('name' => $name))) {
            $check = false;
            $error = 'Name used already';
        }
    }

    if ($name != '' && $check) {
        $uset = new stdClass();
        $uset->name = $name;
        $id = $DB->insert_record('cat_user_sets', $uset);

        redirect("$CFG->wwwroot/blocks/cat_manager/users.php?courseid=$courseid&uset=$id");
    } else {
        echo $OUTPUT->header();

        echo '
  <form action="users.php" method="post">
    <input type="hidden" name="courseid" value="' . $courseid . '"/>
    <input type="text" name="name"/>
    <input type="submit" name="adduset" value="' . get_string('ok') . '"/>
  </form>';
    }
} else if (optional_param('deluset', 0, PARAM_BOOL)) {
    $DB->delete_records('cat_user_sets', array('id' => $uset));

    redirect("$CFG->wwwroot/blocks/cat_manager/users.php?courseid=$courseid");

} else {
    echo $OUTPUT->header();

    $usets = $DB->get_records('cat_user_sets');

    $usetopts = '';
    $usetopts = '<option value="0">' . get_string('select') . '</option>';
    foreach ($usets as $s) {
        if ($s->id == $uset) {
            $selected = ' selected="selected"';
        } else {
            $selected = '';
        }
        $usetopts .= '<option value="' . $s->id . '"' . $selected . '>' . $s->name . '</option>';
    }

    echo '
' . get_string('studentlist', 'block_cat_manager') . '
  <form action="users.php" method="get">
    <input type="hidden" name="courseid" value="' . $courseid . '"/>
    <select name="uset" onchange="this.form.submit()">
      ' . $usetopts . '
    </select>
    <input type="submit" name="adduset" value="' . get_string('new') . '"/>
    <!--<input type="submit" name="renuset" value="' . get_string('rename') . '"/>-->
    <input type="submit" name="deluset" value="' . get_string('delete') . '"/>
  </form>';

    // ユーザー編集
    if ($uset) {
        $users = $DB->get_records('user');
        // $users = get_users_by_capability(
        //     get_context_instance(CONTEXT_COURSE, $courseid),
        //     array('mod/quiz:attempt'));
        $catusers = $DB->get_records('cat_users', array('userset' => $uset));

        if (optional_param('save', 0, PARAM_BOOL)) {
            foreach ($users as $user) {
                if (($ability = optional_param('abil_' . $user->id, -1, PARAM_INT)) >= 0) {
                    $ability = cat_unit2logit($ability);

                    if ($catuser = $DB->get_record('cat_users',
                                                   array('userset' => $uset,
                                                         'userid' => $user->id))) {
                        $catuser->ability = $ability;
                        $DB->update_record('cat_users', $catuser);
                    } else {
                        $catuser = new stdClass();
                        $catuser->id = $user->id;
                        $catuser->userset = $uset;
                        $catuser->userid = $user->id;
                        $catuser->ability = $ability;
                        $DB->insert_record('cat_users', $catuser);
                    }
                }
            }
        }

        // とりあえずカテゴリー考えない
        $users = $DB->get_records('user');
        $catusers = $DB->get_records('cat_users', array('userset' => $uset));

        echo '
  <p><a href="users.php?courseid=' . $courseid . '&amp;uset=' . $uset . '">' . get_string('reload') . '</a></p>
  <form action="users.php" method="post">
    <input type="hidden" name="courseid" value="' . $courseid . '"/>
    <input type="hidden" name="uset" value="' . $uset . '"/>
    <table class="generaltable">';
        foreach ($users as $user) {
            // ゲスト除外
            if ($user->username == 'guest') {
                continue;
            }

            $opts = '';
            $def = 100;
            foreach ($catusers as $catuser) {
                if ($catuser->userid == $user->id) {
                    $def = cat_logit2unit($catuser->ability);
                    break;
                }
            }
            foreach ($abilityrange as $d) {
                if ($d == $def) {
                    $selected = ' selected="selected"';
                } else {
                    $selected = '';
                }
                $opts .= '<option value="' . $d . '"' . $selected . '>' . $d . '</option>';
            }
            echo '
      <tr>
        <td class="cell">' . fullname($user) . '</td>
        <td class="cell">
          <select name="abil_' . $user->id . '">' . $opts . '</select>
        </td>
      </tr>';
        }
        echo '
    </table>
    <input type="submit" name="save" value="' . get_string('savechanges') . '"/>
  </form>
';
    }
}

echo '
  <p><a href="' . $CFG->wwwroot . '/course/view.php?id=' . $courseid . '">' . get_string('returntocourse', 'block_cat_manager') . '</a></p>';
echo $OUTPUT->footer();
