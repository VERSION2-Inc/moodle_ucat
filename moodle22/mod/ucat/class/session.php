<?php
defined('MOODLE_INTERNAL') || die();

class ucat_session {
    const STATUS_NONE = 0;
    const STATUS_ASKED = 1;
    const STATUS_ANSWERED = 2;
    const STATUS_FINISHED = 3;
    const STATUS_REESTIMATED = 4;

    public $session;
    private $id;
    private $questionsusage;
    /**
     * @var \stdClass
     */
    private $ucat;
    private $cm;
    private $questions;
    public $currentquestion;

    /**
     *
     * @param int $sessionid
     */
    public function __construct($sessionid) {
        global $DB;

        $row = $DB->get_record('ucat_sessions', array('id' => $sessionid));
        $this->session = $row;

        $ucat = $DB->get_record('ucat', array('id' => $row->ucat));
        $cm = get_coursemodule_from_instance('ucat', $ucat->id);

        $this->id = $row->id;
        $this->questionsusage = $row->questionsusage;

        $this->ucat = $ucat;
        $this->cm = $cm;

        $this->questions = null;
        $this->currentquestion = null;
        if ($this->session->questions) {
            $this->questions = unserialize($this->session->questions);
            $this->currentquestion = end($this->questions);
        }
    }

    protected function update() {
        global $DB;

        $DB->update_record('ucat_sessions', $this->session);
    }

    /**
     *
     * @param int $page
     * @return object
     */
    public function get_next_question() {
        global $DB;

        $where = '';
        if ($this->questions) {
            $where = ' AND q.id NOT IN ('.implode(',', $this->questions).')';
        }

        $qcats = implode(',', question_categorylist($this->ucat->questioncategory));

        $qdiffs = $DB->get_records_sql('
                SELECT q.id, uq.difficulty
                    FROM {question} q
                        LEFT JOIN {ucat_questions} uq ON q.id = uq.questionid
                    WHERE q.category in ('.$qcats.')'.$where
        );
        $qdiffs = array_merge($qdiffs);
        $qcount = count($qdiffs);

        $accuracy = 0.7;

        $qselect = 0;

        if ($qcount == 0) {
            return;
        }

        $bias = 0;
        if (!empty($this->ucat->logitbias)) {
            $bias = $this->ucat->logitbias;
        }

        $n = mt_rand(0, $qcount - 1);
        $abilhalf = ($this->session->abilright + $this->session->ability) * 0.5;
        $qselect = 0;
        for ($qq = $n + 1; $qq <= $qcount + $n; $qq++) {
            if ($qq >= $qcount) {
                $q = $qq - $qcount;
            } else {
                $q = $qq;
            }

            if (is_null($qdiffs[$q]->difficulty)) {
                $i = 5;
            } else {
                $i = $qdiffs[$q]->difficulty;
            }

            if ($i >= $this->session->ability + $bias && $i <= $this->session->abilright + $bias) {
                $qselect = $q;
                break;// $qdiffs[$qselect];
            }
            if ($qselect == 0 || abs($i - $abilhalf) < $qhold) {
                $qselect = $q;
                $qhold = abs($i - $abilhalf);
            }
        }

        $this->questions[] = $qdiffs[$qselect]->id;

        $this->session->questions = serialize($this->questions);
        $this->update();
        $this->currentquestion = $qdiffs[$qselect]->id;

        $quba = question_engine::load_questions_usage_by_activity($this->questionsusage);
        $questions = question_load_questions(array($this->currentquestion));
        $qobj = question_bank::make_question(reset($questions));
        $slot = $quba->add_question($qobj);
        $quba->start_question($slot);
        question_engine::save_questions_usage_by_activity($quba);

        $this->session->slot = $slot;
        $this->session->status = self::STATUS_ASKED;
        $this->update();

        return $qdiffs[$qselect];
    }

    /**
     *
     * @param int $questionid
     * @return string
     */
    public function render_question($questionid) {
        global $PAGE;

        $quba = question_engine::load_questions_usage_by_activity($this->questionsusage);

        $PAGE->requires->js_init_call('M.core_question_engine.init_form',
                array('#responseform'), false
        );

        $output = '';
        $output .= html_writer::tag('h4', get_string('question').' '.$this->session->slot);

        $output .= html_writer::start_tag('form',
                array('action' => 'attempt.php', 'method' => 'post',
                'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                'id' => 'responseform'
                        ));
        $output.='<input type=hidden name=cmid value='.$this->cm->id.'>';
        $output.='<input type=hidden name=session value='.$this->id.'>';
        $options = new question_display_options();
        $options->marks = question_display_options::MAX_ONLY;
        $options->markdp = 2; // Display marks to 2 decimal places.
        $options->feedback = question_display_options::VISIBLE;
        $options->generalfeedback = question_display_options::HIDDEN;

        $output.= $quba->render_question($this->session->slot, $options);
        $output .= html_writer::start_tag('div', array('class' => 'submitbtns'));
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'next',
                'value' => get_string('next')));
        $output .= html_writer::end_tag('div');
        $output.=html_writer::end_tag('form');

        if ($this->ucat->supervisor) {
            $output .= $this->dump_state();
        }

        return $output;
    }

    public function process_session() {
        global $DB;

        $now = time();
        $quba = question_engine::load_questions_usage_by_activity($this->questionsusage);
        $slot=$this->session->slot;
        $submitteddata=$quba->extract_responses($slot);

        $quba->process_action($slot,$submitteddata);
        $quba->finish_question($slot); // 問題を閉じる

        question_engine::save_questions_usage_by_activity($quba);

        $sum = $quba->get_total_mark();
//         echo ' 得点='.$sum;

        $pexp = 0;
        $pvar = 0;
        $presult = 0;
        for ($p = 1; $p <= $slot; $p++) {
            $attempt = $quba->get_question_attempt($p);
            $question = new ucat_question($attempt->get_question()->id);

            $success = 1 / (1 + exp($question->get_difficulty() - $this->session->ability));
            $pexp += $success;
            $pvar += $success * (1 - $success);

            $presult += $attempt->get_mark();
        }
        if ($pvar != 0) {
            $this->session->se = sqrt(1 / $pvar);
        }
        if ($pvar < 1) {
            $pvar = 1;
        }

        $this->session->ability += ($presult - $pexp) / $pvar;
        $this->session->abilright = $this->session->ability + (1 / $pvar);

        //outofexpect check

        if ($this->session->status == self::STATUS_ASKED) {
            $this->session->status = self::STATUS_ANSWERED;
            $this->update();

            $this->add_record($attempt);
        }

        echo $this->format_status();
    }

    private function add_record(question_attempt $attempt) {
        global $DB;

        $record = new stdClass();
        $record->session = $this->session->id;
        $record->seq = $this->session->slot;
        $record->timemodified = time();
        $record->states = $this->session->status;
        $record->ability = $this->session->ability;
        $record->abilright = $this->session->abilright;
        $record->se = $this->session->se;
        $record->answer = $attempt->get_response_summary();
        $record->questiontext = '';
        $record->answer = '';

        $DB->insert_record('ucat_records', $record);
    }

    /**
     * @return bool
     */
    public function check_ending_condition() {
        global $DB;

        switch ($this->ucat->endcondition) {
            case ucat::ENDCOND_NUMQUEST:
                if ($this->session->slot >= $this->ucat->questions) {
                    return true;
                }
                return false;

            case ucat::ENDCOND_SE:
                if ($this->session->se < $this->ucat->se) {
                    return true;
                }
                return false;

            case ucat::ENDCOND_NUMQUESTANDSE:
                if ($this->session->slot > $this->ucat->numquestions && $this->session->se < $this->ucat->se) {
                    return true;
                }
                return false;

            case ucat::ENDCOND_ALL:
                return false;
        }
    }

    public function get_ability() {
        $abil = new stdClass();
        $abil->ability = $this->session->ability;
        $abil->abilright = $this->session->abilright;
        $abil->se = $this->session->se;
    }

    /**
     *
     * @return string
     */
    public function format_status() {
        if (!$this->ucat->showstate) {
            return '';
        }

        $floatformat = '%.2f';
        $table = new html_table();
        $table->data = array(
                array(ucat::str('ability'), sprintf($floatformat, $this->session->ability)),
                array(ucat::str('abilityright'), sprintf($floatformat, $this->session->abilright)),
                array(ucat::str('se'), sprintf($floatformat, $this->session->se))
        );
        return html_writer::table($table);
    }

    public function finish() {
        global $DB;

        if ($this->ucat->saveability) {
            $user = new ucat_user($this->ucat->userset, $this->session->userid);
            $user->set_ability($this->session->ability);
        }

        $this->session->timefinished = time();
        $this->session->status = self::STATUS_FINISHED;
        $this->update();
    }

    /**
     *
     * @return string
     */
    public function dump_state() {
        global $DB, $OUTPUT;

        $user = $DB->get_record('user', array('id' => $this->session->userid));

        $question = new ucat_question($this->currentquestion);

        $table = new html_table();
        $table->data = array(
                array(ucat::str('difficulty'), ucat::logit2unit($question->get_difficulty())),
                array(ucat::str('testtakersname'), fullname($user)),
                array(ucat::str('estimatedability'), ucat::logit2unit($this->session->ability)),
                array(ucat::str('probableabilityrange'), ucat::format_ability_range($this->session->ability, $this->session->se))
        );
        return html_writer::table($table);
    }

    /**
     *
     * @return string
     */
    public function render_report() {
        global $DB, $OUTPUT;

        $user = $DB->get_record('user', array('id' => $this->session->userid));

        $table = new html_table();
        $table->data = array(
                array(ucat::str('testtakersname'), fullname($user)),
                array(ucat::str('estimatedability'), ucat::logit2unit($this->session->ability)),
                array(ucat::str('probableabilityrange'), ucat::format_ability_range($this->session->ability, $this->session->se))
        );
        return html_writer::table($table);
    }
}
