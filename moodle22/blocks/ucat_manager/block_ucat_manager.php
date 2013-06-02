<?php
/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 * @version $Id: block_ucat_manager.php 23 2012-09-04 00:24:02Z yama $
 */

defined('MOODLE_INTERNAL') || die();

class block_ucat_manager extends block_base {
    function init() {
        $this->title = get_string('pluginname', 'block_ucat_manager');
    }

    /**
     * @return stdClass
     */
    function get_content() {
        global $CFG, $COURSE, $OUTPUT;

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (has_capability('mod/quiz:manage', get_context_instance(CONTEXT_COURSE, $COURSE->id))) {
            $this->content->text = '<strong>' . get_string('admin', 'block_ucat_manager') . '</strong><br/>'
                .$OUTPUT->action_link(new moodle_url('/blocks/ucat_manager/users.php', array('courseid' => $COURSE->id)),
                        get_string('users', 'block_ucat_manager')).'<br/>'
                .$OUTPUT->action_link(new moodle_url('/blocks/ucat_manager/questions.php', array('courseid' => $COURSE->id)),
                        get_string('questions', 'block_ucat_manager')).'<br/>'
                .$OUTPUT->action_link(new moodle_url('/blocks/ucat_manager/quizzes.php', array('course' => $COURSE->id)),
                        get_string('quizzes', 'block_ucat_manager'));
        }

        return $this->content;
    }
}
