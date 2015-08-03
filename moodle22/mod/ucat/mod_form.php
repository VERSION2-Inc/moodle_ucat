<?php
defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/course/moodleform_mod.php';
require_once $CFG->dirroot . '/mod/ucat/locallib.php';

class mod_ucat_mod_form extends moodleform_mod {
    private $tp;

    public function definition() {
        global $DB, $COURSE, $PAGE;

        $PAGE->requires->js_init_call('M.mod_ucat.mod_form_init', null, false, array(
                'name' => 'mod_ucat',
                'fullpath' => '/mod/ucat/module.js',
                'requires' => array('panel')
        ));
        $PAGE->requires->strings_for_js(array(
                'ok',
                'cancel'
        ), 'moodle');
        $PAGE->requires->strings_for_js(array(
                'targetprobability',
                'tpoutofrange'
        ), 'mod_ucat');

        $f = $this->_form;

        $f->addElement('header', 'general', get_string('general', 'form'));

        $f->addElement('text', 'name', get_string('name'), array('size' => 64));
        if (!empty($CFG->formatstringstriptags)) {
            $f->setType('name', PARAM_TEXT);
        } else {
            $f->setType('name', PARAM_CLEANHTML);
        }
        $f->addRule('name', null, 'required', null, 'client');

        $this->add_intro_editor();

        $usersets = $DB->get_records('ucat_user_sets', null, 'name');
        $opts = array();
        foreach ($usersets as $userset) {
            $opts[$userset->id] = $userset->name;
        }
        $f->addElement('select', 'userset', get_string('userset', 'ucat'), $opts);

        $opts_endcondition = array(
                ucat::ENDCOND_ALL => get_string('allquestions', 'ucat'),
                ucat::ENDCOND_NUMQUEST => get_string('bynumquestions', 'ucat'),
                ucat::ENDCOND_SE => get_string('byse', 'ucat'),
                ucat::ENDCOND_NUMQUESTANDSE => get_string('bynumquestionsandse', 'ucat')
        );
        $f->addElement('select', 'endcondition', get_string('endcondition', 'ucat'), $opts_endcondition);

        $f->addElement('text', 'questions', get_string('numquestions', 'ucat'), array('size' => 4));
        $f->setType('questions', PARAM_INT);

        $f->addElement('text', 'se', get_string('se', 'ucat'), array('size' => 8));
        $f->setType('se', PARAM_FLOAT);

        $f->addElement('selectyesno', 'record', get_string('recordstatus', 'ucat'));
        $f->addElement('text', 'logitbias', get_string('logitbias', 'ucat'), ['size' => 8]);
        $f->setType('logitbias', PARAM_TEXT);

        $contexts = new question_edit_contexts(context_course::instance($COURSE->id));
        $f->addElement('selectgroups', 'questioncategory', get_string('questioncategory', 'ucat'),
                question_category_options($contexts->having_cap('moodle/question:add')));

        $f->addElement('selectyesno', 'saveability', get_string('saveability', 'ucat'));
        $f->addElement('selectyesno', 'showstate', get_string('showstatus', 'ucat'));
        $f->addElement('selectyesno', 'supervisor', get_string('supervisormode', 'ucat'));

        $f->addElement('header', 'targetprobabilityhdr', ucat::str('targetprobability'));

//         $this->add_target_probability_group(ucat::TP_FIRST);
//         $this->add_target_probability_group(ucat::TP_LAST);
//         $this->add_target_probability_group(ucat::TP_REST);

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

//     public function data_preprocessing(&$toform) {
//         foreach (ucat::$tptypes as $type) {
//             if (!empty($this->tp[$type])) {
//                 $toform['tp'][$type]['probability'] = sprintf('%g', $this->tp[$type]->probability);
//                 if ($type != ucat::TP_REST)
//                     $toform['tp'][$type]['numquestions'] = $this->tp[$type]->numquestions;
//             }
//         }
//     }

    private function add_target_probability_group($type) {
        global $DB;

        $f = $this->_form;

        $groupname = "tp[$type]";

        $group = array();
        if ($type != ucat::TP_REST) {
            $group[] = $f->createElement('static', '', '', ucat::str('numquestions'.$type).':');
            $group[] = $f->createElement('text', 'numquestions', '', array('size' => 3));
            $f->setType($groupname.'[numquestions]', PARAM_INT);
        }
        $group[] = $f->createElement('static', '', '', ucat::str('tp').':');
        $group[] = $f->createElement('text', 'probability', '', array('size' => 10));
        $f->setType($groupname.'[probability]', PARAM_FLOAT);

        $f->addGroup($group, $groupname, ucat::str('questionstp'.$type));

        if ($this->_instance) {
            if ($tp = $DB->get_record(ucat::TBL_TP, array(
                'ucat' => $this->_instance,
                'targettype' => $type
            ))) {
                $this->tp[$type] = $tp;
            }
        }
    }
}
