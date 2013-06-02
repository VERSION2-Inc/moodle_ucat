<?php
/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 * @version $Id$
 */

defined('MOODLE_INTERNAL') || die();

class block_cat_manager extends block_base {
    function init() {
        $this->title = get_string('pluginname', 'block_cat_manager');
    }

    function get_content() {
        global $CFG, $COURSE, $OUTPUT;

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (has_capability('mod/quiz:manage', get_context_instance(CONTEXT_COURSE, $COURSE->id))) {
            $this->content->text = '<strong>' . get_string('admin', 'block_cat_manager') . '</strong><br/>'
                .$OUTPUT->action_link(new moodle_url('/blocks/cat_manager/users.php', array('courseid' => $COURSE->id)),
                        get_string('users', 'block_cat_manager')).'<br/>'
                .$OUTPUT->action_link(new moodle_url('/blocks/cat_manager/questions.php', array('courseid' => $COURSE->id)),
                        get_string('questions', 'block_cat_manager')).'<br/>'
                .$OUTPUT->action_link(new moodle_url('/blocks/cat_manager/quizzes.php', array('course' => $COURSE->id)),
                        get_string('quizzes', 'block_cat_manager'));
        }

        return $this->content;
    }
}
