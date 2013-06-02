<?php
defined('MOODLE_INTERNAL') || die();

class ucat_question {
    private $question;
    private $difficulty;

    /**
     *
     * @param int $questionid
     */
    public function __construct($questionid) {
        global $DB;

        $this->id = $questionid;

        $q = $DB->get_record('question', array('id' => $questionid));
        $this->question = $q;
        if ($cq = $DB->get_record('ucat_questions', array('questionid' => $questionid))) {
            $this->difficulty = $cq->difficulty;
        } else {
            $this->difficulty = ucat::DEFAULT_DIFFICULTY;
        }
    }

    /**
     *
     * @return float
     */
    public function get_difficulty() {
        return $this->difficulty;
    }

    /**
     *
     * @param float $difficulty
     */
    public function set_difficulty($difficulty) {
        global $DB;

        if (!$DB->record_exists('ucat_questions', array('questionid' => $this->id))) {

            $DB->insert_record('ucat_questions', array('questionid' => $this->id));
        }

        $cquestion = $DB->get_record('ucat_questions', array('questionid' => $this->id));
        $cquestion->difficulty = $difficulty;
        $DB->update_record('ucat_questions', $cquestion);
    }

    /**
     *
     * @param float $addend
     */
    public function adjust_difficulty($addend) {
        $this->set_difficulty($this->get_difficulty() + $addend);
    }
    //     public function set_difficulty($difficulty) {
//         global $DB;
//         $this->difficulty = $difficulty;
//     }
}
