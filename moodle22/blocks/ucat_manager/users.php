<?php
/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 * @version $Id: users.php 23 2012-09-04 00:24:02Z yama $
 */

require_once '../../config.php';
require_once(dirname(__FILE__) . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = get_context_instance(CONTEXT_COURSE, $course->id);

require_capability('mod/quiz:manage', $context);

$uset = optional_param('uset', 0, PARAM_INT);

$PAGE->set_course($course);
$PAGE->set_url('/blocks/ucat_manager/questions.php');
$PAGE->set_title(get_string('users', 'block_ucat_manager'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('catadmin', 'block_ucat_manager'));
$PAGE->navbar->add(get_string('users', 'block_ucat_manager'));

$abilityrange = range(200, 0);

if (optional_param('adduset', 0, PARAM_BOOL)) {
    $name = trim(optional_param('name', '', PARAM_TEXT));

    if ($name != '') {
        $check = true;
        if ($DB->get_record('ucat_user_sets', array('name' => $name))) {
            $check = false;
            $error = 'Name already used';
        }
    }

    if ($name != '' && $check) {
        $uset = new stdClass();
        $uset->name = $name;
        $id = $DB->insert_record('ucat_user_sets', $uset);

        redirect("$CFG->wwwroot/blocks/ucat_manager/users.php?courseid=$courseid&uset=$id");
    } else {
        echo $OUTPUT->header();

        echo '
  <form action="users.php" method="post">
    <input type="hidden" name="courseid" value="' . $courseid . '"/>
    <input type="text" name="name" size="64"/>
    <input type="submit" name="adduset" value="' . get_string('ok') . '"/>
  </form>';
    }
} else if (optional_param('deluset', 0, PARAM_BOOL)) {
    $DB->delete_records('ucat_user_sets', array('id' => $uset));

    redirect("$CFG->wwwroot/blocks/ucat_manager/users.php?courseid=$courseid");

} else if (optional_param('export', 0, PARAM_BOOL)) {
    $users = $DB->get_records('user');
    $catusers = $DB->get_records('ucat_users', array('userset' => $uset));

    echo implode(',',array(
            'ID',
            get_string('name'),
            get_string('ability', 'ucat')))."\n";
    foreach ($users as $user) {
        // ゲスト除外
        if ($user->username == 'guest') {
            continue;
        }
        $def = 100;
        foreach ($catusers as $catuser) {
            if ($catuser->userid == $user->id) {
                $def = cat_logit2unit($catuser->ability);
                break;
            }
        }


        echo implode(',',
                array(
                        $user->id,
                        fullname($user),
                        $def
                ))."\n";
    }
    exit();

} else if (optional_param('import', 0, PARAM_BOOL)) {
    $fp = fopen($_FILES['file']['tmp_name'], 'r');
    fgets($fp);
    while ($row = fgetcsv($fp)) {
        list($id, $name, $ability) = $row;

        if ($cuser = $DB->get_record('ucat_users', array('userid' => $id))) {
            $cuser->ability = $ability;
            $DB->update_record('ucat_users', $cuser);
        } else {
            $cuser = new stdClass();
            $cuser->userset = $uset;
            $cuser->ability = $ability;
            $DB->insert_record('ucat_users', $cuser);
        }
    }
    fclose($fp);
    redirect("$CFG->wwwroot/blocks/ucat_manager/users.php?courseid=$courseid");

} else {
    echo $OUTPUT->header();

    $usets = $DB->get_records('ucat_user_sets');

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
' . get_string('userset', 'ucat') . '
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
        $catusers = $DB->get_records('ucat_users', array('userset' => $uset));

        if (optional_param('save', 0, PARAM_BOOL)) {
            foreach ($users as $user) {
                if (($ability = optional_param('abil_' . $user->id, -1, PARAM_INT)) >= 0) {
                    $ability = cat_unit2logit($ability);

                    if ($catuser = $DB->get_record('ucat_users',
                                                   array('userset' => $uset,
                                                         'userid' => $user->id))) {
                        $catuser->ability = $ability;
                        $DB->update_record('ucat_users', $catuser);
                    } else {
                        $catuser = new stdClass();
                        $catuser->id = $user->id;
                        $catuser->userset = $uset;
                        $catuser->userid = $user->id;
                        $catuser->ability = $ability;
                        $DB->insert_record('ucat_users', $catuser);
                    }
                }
            }
        }

        // とりあえずカテゴリー考えない
        $users = $DB->get_records('user');
        $catusers = $DB->get_records('ucat_users', array('userset' => $uset));

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

        echo $OUTPUT->box_start()
            .$OUTPUT->single_button(new moodle_url('users.php', array('export' => '1',
                    'courseid' => $courseid,
                    'uset' => $uset)), 'Export')
                   .'<div class="center">
                   <form action="users.php" method="post" enctype="multipart/form-data">
                   <input type="hidden" name="import" value="1"/>
                   <input type="hidden" name="courseid" value="'.$courseid.'"/>
                   <input type="hidden" name="uset" value="'.$uset.'"/>
                   <input type="file" name="file"/>
                   <input type="submit" value="Import"/>
                   </form>
                   </div>'
            .$OUTPUT->box_end();
    }
}

echo '
  <p><a href="' . $CFG->wwwroot . '/course/view.php?id=' . $courseid . '">' . get_string('returntocourse', 'block_ucat_manager') . '</a></p>';
echo $OUTPUT->footer();
