<?php
require_once "$CFG->libdir/formslib.php";
class mod_quiz_report_cat_settings extends moodleform {

    function definition() {
        global $COURSE;
        $mform    =& $this->_form;
//-------------------------------------------------------------------------------
        $mform->addElement('header', 'preferencespage', get_string('preferencespage', 'quiz_cat'));

        if (!$this->_customdata['currentgroup']){
            $studentsstring = get_string('participants');
        } else {
            $a = new stdClass();
            $a->coursestudent = get_string('participants');
            $a->groupname = groups_get_group_name($this->_customdata['currentgroup']);
            if (20 < strlen($a->groupname)){
              $studentsstring = get_string('studentingrouplong', 'quiz_cat', $a);
            } else {
              $studentsstring = get_string('studentingroup', 'quiz_cat', $a);
            }
        }
        $options = array();
        if (!$this->_customdata['currentgroup']){
            $options[QUIZ_REPORT_ATTEMPTS_ALL] = get_string('optallattempts','quiz_cat');
        }
        if ($this->_customdata['currentgroup'] || $COURSE->id != SITEID) {
            $options[QUIZ_REPORT_ATTEMPTS_ALL_STUDENTS] = get_string('optallstudents','quiz_cat', $studentsstring);
            $options[QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH] =
                     get_string('optattemptsonly','quiz_cat', $studentsstring);
            $options[QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH_NO] = get_string('optnoattemptsonly', 'quiz_cat', $studentsstring);
        }
        $mform->addElement('select', 'attemptsmode', get_string('show', 'quiz_cat'), $options);

        $showattemptsgrp = array();
        if ($this->_customdata['qmsubselect']){
            $gm = '<span class="highlight">'.quiz_get_grading_option_name($this->_customdata['quiz']->grademethod).'</span>';
            $showattemptsgrp[] =& $mform->createElement('advcheckbox', 'qmfilter', get_string('showattempts', 'quiz_cat'), get_string('optonlygradedattempts', 'quiz_cat', $gm), null, array(0,1));
        }
        if (has_capability('mod/quiz:regrade', $this->_customdata['context'])){
            $showattemptsgrp[] =& $mform->createElement('advcheckbox', 'regradefilter', get_string('showattempts', 'quiz_cat'), get_string('optonlyregradedattempts', 'quiz_cat'), null, array(0,1));
        }
        if ($showattemptsgrp){
            $mform->addGroup($showattemptsgrp, null, get_string('showattempts', 'quiz_cat'), '<br />', false);
        }
//-------------------------------------------------------------------------------
        $mform->addElement('header', 'preferencesuser', get_string('preferencesuser', 'quiz_cat'));

        $mform->addElement('text', 'pagesize', get_string('pagesize', 'quiz_cat'));
        $mform->setType('pagesize', PARAM_INT);

        $mform->addElement('selectyesno', 'detailedmarks', get_string('showdetailedmarks', 'quiz_cat'));

        $mform->addElement('submit', 'submitbutton', get_string('preferencessave', 'quiz_cat'));
    }
}

