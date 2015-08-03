<?php
/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 */

namespace block_ucat_manager;

require_once '../../config.php';
require_once $CFG->dirroot . '/blocks/ucat_manager/locallib.php';
require_once(dirname(__FILE__) . '/lib.php');
require_once $CFG->dirroot . '/mod/ucat/locallib.php';

class page_users extends page {
    public function run() {
        global $PAGE;

        $this->require_capability(ucat_manager::CAP_MANAGE);

        $PAGE->set_pagelayout('course');
        $PAGE->set_title($this->course->shortname.': '.ucat_manager::str('users'));
        $PAGE->set_heading($this->course->fullname);
        $PAGE->navbar->add(ucat_manager::str('catadmin'));
        $PAGE->navbar->add(ucat_manager::str('users'));

        if (optional_param('adduset', 0, PARAM_BOOL))
            $this->add_user_set();
        elseif (optional_param('deluset', 0, PARAM_BOOL))
            $this->delete_user_set();
        elseif (optional_param('export', 0, PARAM_BOOL))
            $this->csv_export();
        elseif (optional_param('import', 0, PARAM_BOOL))
            $this->csv_import();
        else
            $this->view();
    }

    private function footer() {
        $o = $this->output->container(
            $this->output->action_link($this->course_url(),
                get_string('returntocourse', 'block_ucat_manager')));
        $o .= $this->output->footer();

        return $o;
    }

    private function view() {
        global $CFG, $PAGE, $DB;

        require_once $CFG->libdir . '/tablelib.php';

        $uset = optional_param('uset', 0, PARAM_INT);

        $jsparams = [
            'course' => $this->course->id,
            'uset' => $uset
        ];
        $PAGE->requires->js_init_call('M.block_ucat_manager.users_init', [$jsparams], false, [
            'name' => 'block_ucat_manager',
            'fullpath' => '/blocks/ucat_manager/module.js',
            'requires' => ['event-key']
        ]);
        $PAGE->requires->strings_for_js([
            'reallydeleteuserset'
        ], ucat_manager::COMPONENT);

        $perpage = 20;

        //XXX
        $courseid = $this->course->id;

        $abilityrange = range(200, 0);

        echo $this->output->header();

        $usetsel = \html_writer::select(
            $DB->get_records_menu('ucat_user_sets', null, 'name', 'id, name'),
            'uset', $uset);

        echo '
    ' . get_string('userset', 'ucat') . '
      <form action="users.php" method="get">
        <input type="hidden" name="courseid" value="' . $courseid . '"/>
            ' . $usetsel . '
        <input type="submit" name="adduset" value="' . get_string('new') . '"/>
        <!--<input type="submit" name="renuset" value="' . get_string('rename') . '"/>-->
        <input id="deluset" type="submit" name="deluset" value="' . get_string('delete') . '"/>
      </form>';

        // ユーザー編集
        if ($uset) {
            $page = optional_param('page', 0, PARAM_INT);

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

            $users = get_enrolled_users($this->context);
            $catusers = $DB->get_records('ucat_users', array('userset' => $uset));

            $baseurl = new \moodle_url($this->url, ['uset' => $uset]);

            $table = new \flexible_table('users');
            $table->define_baseurl($baseurl);
            $columns = [
                'userpic' => get_string('userpic'),
                'fullname' => get_string('fullnameuser'),
                'ability' => ucat_manager::str('ability')
            ];
            $table->define_columns(array_keys($columns));
            $table->define_headers(array_values($columns));
            $table->sortable(true, 'lastname');
            $table->no_sorting('userpic');
            $table->column_class('userpic', 'col-userpic');
            $table->column_class('ability', 'col-ability');
            $table->setup();

            list($esql, $params) = get_enrolled_sql($this->context);

            $order = '';
            if ($sort = $table->get_sql_sort())
                $order = 'ORDER BY '.$sort;
            $sql = '
                SELECT u.id, ' . ucat_manager::get_user_fields('u', true) . ',
                    cu.ability
                FROM {user} u
                    JOIN ('.$esql.') je ON je.id = u.id
                    LEFT JOIN {ucat_users} cu ON cu.userid = u.id AND cu.userset = :uset
                WHERE u.deleted = 0
                '.$order;
            $params['uset'] = $uset;

//             echo $sql;

            $numusers = count_enrolled_users($this->context);

            echo $this->output->paging_bar($numusers, $page, $perpage, $baseurl);

            $users = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
//             var_dump($users);

//             die;

//             echo '
//       <p><a href="users.php?courseid=' . $courseid . '&amp;uset=' . $uset . '">' . get_string('reload') . '</a></p>
//       <form action="users.php" method="post">
//         <input type="hidden" name="courseid" value="' . $courseid . '"/>
//         <input type="hidden" name="uset" value="' . $uset . '"/>
//         <table class="generaltable">';
            foreach ($users as $user) {
                if ($user->ability !== null) {
                    $ability = \ucat::logit2unit($user->ability);
                } else {
                    $ability = 100;
                }
                $table->add_data([
                    $this->output->user_picture($user),
                    fullname($user),
                    \html_writer::tag('div', $ability, [
                        'class' => 'ability',
                        'data-user-id' => $user->id,
                        'data-ability' => $ability
                    ])
                ]);

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
//                 echo '
//           <tr>
//             <td class="cell">' . fullname($user) . '</td>
//             <td class="cell">
//                 '.$def.'
//             </td>
//           </tr>';
                //<select name="abil_' . $user->id . '">' . $opts . '</select>
            }
            echo '
        </table>
        <input type="submit" name="save" value="' . get_string('savechanges') . '"/>
      </form>
    ';

            $table->finish_output();

            echo $this->output->box_start()
                .$this->output->single_button(new \moodle_url('users.php', array('export' => '1',
                        'courseid' => $courseid,
                        'uset' => $uset)), ucat_manager::str('export'))
                       .'<div class="center">
                       <form action="users.php" method="post" enctype="multipart/form-data">
                       <input type="hidden" name="import" value="1"/>
                       <input type="hidden" name="courseid" value="'.$courseid.'"/>
                       <input type="hidden" name="uset" value="'.$uset.'"/>
                       <input type="file" name="file"/>
                       <input type="submit" value="'.ucat_manager::str('import').'"/>
                       </form>
                       </div>'
                .$this->output->box_end();
        }

        echo $this->footer();
    }

    private function add_user_set() {
        global $DB;

        $name = trim(optional_param('name', '', PARAM_TEXT));

        if ($name !== '') {
            $check = true;
            if ($DB->get_record('ucat_user_sets', array('name' => $name))) {
                $check = false;
                $error = 'Name already used';
            }
        }

        if ($name !== '' && $check) {
            $id = $DB->insert_record('ucat_user_sets', ['name' => $name]);

            redirect(new \moodle_url('/blocks/ucat_manager/users.php', [
                'courseid' => $this->course->id,
                'uset' => $id
            ]));
        } else {
            echo $this->output->header();

            echo '
      <form action="users.php" method="post">
        <input type="hidden" name="courseid" value="' . $this->course->id . '"/>
        <input type="text" name="name" size="64"/>
        <input type="submit" name="adduset" value="' . get_string('ok') . '"/>
      </form>';
            echo $this->footer();
        }
    }

    private function delete_user_set() {
        global $DB;

        $uset = required_param('uset', PARAM_INT);

        $DB->delete_records('ucat_users', ['userset' => $uset]);
        $DB->delete_records('ucat_user_sets', ['id' => $uset]);

        redirect(new \moodle_url('/blocks/ucat_manager/users.php', [
            'courseid' => $this->course->id,
        ]));
    }

    private function csv_export() {
        global $DB;

        $uset = required_param('uset', PARAM_INT);

        $users = get_enrolled_users($this->context);
        $catusers = $DB->get_records('ucat_users', array('userset' => $uset));

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=ucatusers.csv');

        $f = new \SplFileObject('php://output', 'w');
        $f->fputcsv([
            'ID',
            get_string('name'),
            get_string('ability', 'ucat')
        ]);
        foreach ($users as $user) {
            $def = 100;
            foreach ($catusers as $catuser) {
                if ($catuser->userid == $user->id) {
                    $def = \ucat::logit2unit($catuser->ability);
                    break;
                }
            }

            $f->fputcsv([
                $user->id,
                fullname($user),
                $def
            ]);
        }
    }

    private function csv_import() {
        global $DB;

        $uset = required_param('uset', PARAM_INT);

        $f = new \SplFileObject($_FILES['file']['tmp_name']);
        $f->fgets();
        while ($row = $f->fgetcsv()) {
            if (count($row) < 3)
                continue;

            list($id, $name, $ability) = $row;
            $ability = \ucat::unit2logit($ability);

            if ($cuser = $DB->get_record('ucat_users', array(
                'userset' => $uset,
                'userid' => $id
            ))) {
                $cuser->ability = $ability;
                $DB->update_record('ucat_users', $cuser);
            } else {
                $DB->insert_record('ucat_users', (object)[
                    'userset' => $uset,
                    'userid' => $id,
                    'ability' => $ability
                ]);
            }
        }
        redirect(new \moodle_url('/blocks/ucat_manager/users.php', [
            'courseid' => $this->course->id,
            'uset' => $uset
        ]));
    }
}

page_users::run_new(__FILE__);
