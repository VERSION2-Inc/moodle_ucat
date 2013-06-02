<?php
defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/course/moodleform_mod.php';
require_once $CFG->dirroot . '/mod/ucat/locallib.php';

class mod_ucat_mod_form extends moodleform_mod {
    public function definition() {
        global $DB, $COURSE;

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $this->add_intro_editor();

        // Number of attempts.
//         $attemptoptions = array('0' => get_string('unlimited'));
//         for ($i = 1; $i <= QUIZ_MAX_ATTEMPT_OPTION; $i++) {
//             $attemptoptions[$i] = $i;
//         }
//         $mform->addElement('select', 'attempts', get_string('attemptsallowed', 'quiz'),
//                 $attemptoptions);
//         $mform->setAdvanced('attempts', $quizconfig->attempts_adv);
//         $mform->setDefault('attempts', $quizconfig->attempts);

        // userset
        $usersets = $DB->get_records('ucat_user_sets', null, 'name');
        $opts = array();
        foreach ($usersets as $userset) {
            $opts[$userset->id] = $userset->name;
        }
        $mform->addElement('select', 'userset', get_string('userset', 'ucat'), $opts);

        // endcondition
        $opts_endcondition = array(
                ucat::ENDCOND_ALL => get_string('allquestions', 'ucat'),
                ucat::ENDCOND_NUMQUEST => get_string('bynumquestions', 'ucat'),
                ucat::ENDCOND_SE => get_string('byse', 'ucat'),
                ucat::ENDCOND_NUMQUESTANDSE => get_string('bynumquestionsandse', 'ucat')
        );
        $mform->addElement('select', 'endcondition', get_string('endcondition', 'ucat'), $opts_endcondition);
        // questions
        $mform->addElement('text', 'questions', get_string('numquestions', 'ucat'));
        // se
        $mform->addElement('text', 'se', get_string('se', 'ucat'));
        // record
        $mform->addElement('selectyesno', 'record', get_string('recordstatus', 'ucat'));
        // logitbias
        $mform->addElement('text', 'logitbias', get_string('logitbias', 'ucat'));
        // questioncategory
        $contexts = new question_edit_contexts(context_course::instance($COURSE->id));
        $mform->addElement('selectgroups', 'questioncategory', get_string('questioncategory', 'ucat'),
                question_category_options($contexts->having_cap('moodle/question:add')));
        // showstate
        $mform->addElement('selectyesno', 'showstate', get_string('showstatus', 'ucat'));
        $mform->addElement('selectyesno', 'supervisor', get_string('supervisormode', 'ucat'));
        $mform->addElement('selectyesno', 'debug', get_string('debugmode', 'ucat'));

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }
}
