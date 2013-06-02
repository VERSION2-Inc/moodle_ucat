<?php
/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 * @version $Id: lib-reestimate-buggy.php 23 2012-09-04 00:24:02Z yama $
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir.'/questionlib.php';

class ucat {
    const ENDCOND_ALL           = 0;
    const ENDCOND_NUMQUEST      = 1;
    const ENDCOND_SE            = 2;
    const ENDCOND_NUMQUESTANDSE = 3;

    const DUMMY_SE = 100;
    const DEFAULT_DIFFICULTY = 5;

    const PREFERRED_BEHAVIOR = 'deferredfeedback';

    /**
     *
     * @param float $val
     * @return int
     */
    public static function logit2unit($val) {
        return (int)($val * 10 + 100);
    }

    /**
     *
     * @param float $val
     * @return int
     */
    public static function diff_logit2unit($val) {
        return (int)($val * 10);
    }

    /**
     *
     * @param int $val
     * @return float
     */
    public static function unit2logit($val) {
        return ($val - 100) * 0.1;
    }

    /**
     *
     * @param float $ability
     * @param float $se
     * @param string $dashchar
     * @return string
     */
    public static function format_ability_range($ability, $se, $dashchar = '&ndash;') {
        return self::logit2unit($ability - $se).$dashchar.self::logit2unit($ability + $se);
    }

    /**
     *
     * @param object $cm
     */
    public function __construct($cm) {
        global $DB;

        $this->cm = $cm;
        $this->context = context_module::instance($this->cm->id);
        $this->options = $DB->get_record('ucat', array('id' => $cm->instance));
    }

    /**
     *
     * @param int $ucatid
     * @return object
     */
    public function create_session($ucatid) {
        global $DB, $USER;

        $user = new ucat_user();

        $session = new stdClass();
        $session->ucat = $ucatid;
        $session->questions = '';
        $session->states = '';

        $session->userid = $USER->id;
        $session->ability = $user->get_ability();
        $session->abilright = $session->ability + 1;
        $session->se = self::DUMMY_SE;

        $quba = question_engine::make_questions_usage_by_activity('mod_ucat', $this->context);
        $quba->set_preferred_behaviour(self::PREFERRED_BEHAVIOR);
        question_engine::save_questions_usage_by_activity($quba);
        $session->questionsusage = $quba->get_id();
        $session->timestarted = time();

        $session->id = $DB->insert_record('ucat_sessions', $session);

        return $session;
    }

    /**
     * @return array
     */
    public static function get_js_module() {
        return array(
                'name'     => 'mod_ucat',
                'fullpath' => '/mod/ucat/module.js',
                'requires' => array('io-base')
        );
    }

    public function reestimate() {
        global $DB;

        // データ読み込み
        $pability = array();
        $result = array();
        $sessions = $DB->get_records_select(
                'ucat_sessions',
                'ucat = :ucatid AND timefinished > 0',
                array('ucatid' => $this->options->id)
        );












        $pasked = count($sessions);//$paskedはセッション回しするからいらないかも
        $i = 0;
        foreach ($sessions as $session) {
            $userid = $session->userid;
            $pid = $session->id;
            $pability[$pid] = $session->ability;

            $result[$userid] = array();
            $quba = question_engine::load_questions_usage_by_activity($session->questionsusage);
            $questionids = unserialize($session->questions);
            $slot = 1;
            foreach ($questionids as $questionid) {
                //問題が使用されたかを記録いらないかも
                $qa = $quba->get_question_attempt($slot++);
                // 正誤
                $result[$pid][$questionid] = $qa->get_mark();
            }

            $i++;
        }
//         var_dump($result);
// die;
        // 集計
        $recount = 1;
        $totalcycle = 0;
        while ($recount) {
            $totalcycle++;
            if ($totalcycle > 10)
                die;

            echo 'Totaling scores... ';
            $qtotal = 0;
            $ptotal = 0;
            $recount = 0;
            $qasked = array();
            $qscore = array();
            // P loop
            foreach ($sessions as $session) {
                $p = $session->id;
                $presult = 0;
                $pscore[$session->id] = 0;
                $questionids = unserialize($session->questions);
                $slot = 1;
                foreach ($questionids as $questionid) {
                    if (isset($result[$session->id][$questionid])) {
                        $n = $result[$session->id][$questionid];
                        $presult++;
                        @$qasked[$questionid]++;
                        $pscore[$session->id] += $n;
                        @$qscore[$questionid] += $n;
                    }
                }

                if ($presult == 0) {
                    continue;
                }

                if ($pscore[$p] > 0 && $pscore[$p] < $presult) {
                    $ptotal++;
                    continue;
                }
                $recount = 1;
                echo 'recount on ';
                unset($result[$p]);
            }

            // Q loop
            foreach ($questionids as $q) {
                if ($qasked[$q] == 0) {
                    continue;
                }
                if ($qscore[$q] > 0 && $qscore[$q] < $qasked[$q]) {
                    $qtotal++;
                    continue;
                }
                $recount = 1;
                foreach ($sessions as $session) {
                    $result[$p][$q] = -1;
                }
            }

            if ($ptotal < 2 || $qtotal < 2) {
                echo 'Not enough data to reestimate';
            }

            //if recount
        }

        $bias = 1;
        foreach ($questionids as $q) {
            if ($qasked[$q] != 0) {
                $question = new ucat_question($q);
                $question->set_difficulty($question->get_difficulty() / $bias);
            }
        }
        $padj = 0;
        foreach ($sessions as $session) {
            if ($pscore[$p] > 0) {
                $pability[$p] /= $bias;
                $padj += $pability[$p];
            }
        }

        // 再推定
        $recount = 1;
        $cycle = 1;

        while ($recount > 0 || $maxresidual > 0.1) {
            var_dump($recount);
          @  var_dump($maxresidual);

            $recount = 0;
            $cycle++;
            $maxresidual = 0;

            echo 'Estimation cycle no. '.$cycle;
            if ($cycle>20)
                break;

            $psum = 0;

            $qexp = array();
            $qvar = array();
//             for ($q = 0; $q < $qcount; $q++) {
//                 $qexp[$q] = 0;
//                 $qvar[$q] = 0;
//             }

            // P loop
            foreach ($sessions as $session) {
                //TODO:grade=0ならcontinue
                $pexp = 0;
                $pvar = 0;

                $cuser = new ucat_user($session->userid);

                $quba = question_engine::load_questions_usage_by_activity($session->questionsusage);

                $questionids = unserialize($session->questions);

//                for ($q = 0; $q < $session->slot; $q++) {
                foreach ($questionids as $questionid) {
                    $q=$questionid;
//                     if ($qasked[$q] == 0 || $result[$q] == -1) {
//                         break;
//                     }

                    $question = new ucat_question($questionid);

                    $success = 1 / (1 + exp($question->get_difficulty() - $session->ability));

                    // Accumulate estimated scores
                    @$qexp[$q] += $success;
                    $pexp += $success;

                    // Sum variance
                    $n = $success * (1 - $success);
                    @$qvar[$q] += $n;
                    $pvar += $n;
                }

                // Difference between actual and estimated
//                 $residual = $pscore[$p] - $pexp;
                $residual = $quba->get_total_mark() - $pexp;
                if (abs($residual) > $maxresidual) {
                    $maxresidual = abs($residual);
                }

                if ($pvar > 1) {
                    // Amount to adjust by
                    $residual /= $pvar;
                }

                // New ability estimate
//                     $pability += $residual;
                $cuser->adjust_ability($residual);

                $se = 1 / sqrt($pvar);

                // Ability sum across test-takers
                $psum += $cuser->get_ability();
            }

            // What is change in mean ability?
            $psum = ($psum - $padj) / $ptotal;

            // P loop
            foreach ($sessions as $session) {
                // Keep mean ability of test-takers constant
//                 if ($pscore[$p] > 0) {
                $quba = question_engine::load_questions_usage_by_activity($session->questionsusage);
                if ($quba->get_total_mark() > 0) {
                    //    $pability[$p] -= $psum;
                    $user = new ucat_user($session->userid);
                    $user->adjust_ability(-$psum);
                }
            }

            // Q loop
            foreach ($questionids as $questionid) {
                $question = new ucat_question($questionid);

                // Reestimate questions
                if (!$qasked[$q]) {
                    continue;
                }

                // Difference between actual and estimated
                $residual = $qscore[$q] - $qexp[$q];
                var_dump($residual);

                if (abs($residual) > $maxresidual) {
                    $maxresidual = abs($residual);
                }

                if ($qvar[$q] > 1) {
                    // Amount to adjust by
                    $residual /= $qvar[$q];
                }

                // New question difficulty estimate
//                 $qdiff[$q] -= $residual;
                $question->adjust_difficulty(-$residual);
            }
        }

        echo 'Reestimation complete.';
    }
}

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

class ucat_user {
    private $user;
    private $ability;

    /**
     * @param int $userset
     * @param int $userid
     */
    public function __construct($userset, $userid = null) {
        global $DB, $USER;

        $this->userset = $userset;

        if (!$userid) {
            $userid = $USER->id;
        }
        $this->userid = $userid;

        $q = $DB->get_record('user', array('id' => $userid));
        if ($cq = $DB->get_record('ucat_users', array('userset' => $userset, 'userid' => $userid))) {
            $this->ability = $cq->ability;
        } else {
            $this->ability = ucat::DEFAULT_DIFFICULTY;
        }
    }

    /**
     *
     * @return float
     */
    public function get_ability() {
        return $this->ability;
    }

    /**
     *
     * @param float $ability
     */
    public function set_ability($ability) {
        global $DB;

        if (!$DB->record_exists('ucat_users', array('userset' => $this->userset, 'userid' => $this->userid))) {

            $DB->insert_record('ucat_users', array('userset' => $this->userset, 'userid' => $this->userid));
        }

        $cuser = $DB->get_record('ucat_users', array('userset' => $this->userset, 'userid' => $this->userid));
        $cuser->ability;
        $DB->update_record('ucat_users', $cuser);
    }

    /**
     *
     * @param float $addend
     */
    public function adjust_ability($addend) {
        $this->set_ability($this->get_ability() + $addend);
    }
}

class ucat_session {
    const STATUS_NONE = 0;
    const STATUS_ASKED = 1;
    const STATUS_ANSWERED = 2;
    const STATUS_FINISHED = 3;
    const STATUS_REESTIMATED = 4;

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

        $html = 'ability: '.$this->session->ability
            .' abilright: '.$this->session->abilright
            .' se:'.$this->session->se;

        return $html;
    }

    public function finish() {
        global $DB;

        $cuser = $DB->get_record('ucat_users', array(
                'userset' => $this->ucat->userset,
                'userid' => $this->session->userid
        ));
        $cuser->ability = $this->session->ability;
        $DB->update_record('ucat_users', $cuser);

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

        $lines = array(
                'Difficulty: '.ucat::logit2unit($question->get_difficulty()),
                get_string('testtakersname', 'ucat').': '.fullname($user),
                get_string('estimatedability', 'ucat').': '.ucat::logit2unit($this->session->ability),
                get_string('probableabilityrange', 'ucat').': '.ucat::format_ability_range($this->session->ability, $this->session->se),
                get_string('score', 'ucat').': '.-1
        );
        $html = $OUTPUT->box(
                html_writer::tag('div', implode(html_writer::empty_tag('br'), $lines))
        );

        return $html;
    }

    /**
     *
     * @return string
     */
    public function render_report() {
        global $DB, $OUTPUT;

        $user = $DB->get_record('user', array('id' => $this->session->userid));

        $lines = array(
                get_string('testtakersname', 'ucat').': '.fullname($user),
                get_string('estimatedability', 'ucat').': '.ucat::logit2unit($this->session->ability),
                get_string('probableabilityrange', 'ucat').': '.ucat::format_ability_range($this->session->ability, $this->session->se),
                get_string('score', 'ucat').': '.-1
        );
        $html = $OUTPUT->box(
                html_writer::tag('div', implode(html_writer::empty_tag('br'), $lines))
        );

        return $html;
    }
}

/**
 *
 * @param object $ucat
 * @return int
 */
function ucat_add_instance($ucat) {
    global $DB;

    $ucat->id = $DB->insert_record('ucat', $ucat);

    return $ucat->id;
}

/**
 *
 * @param object $ucat
 * @return bool
 */
function ucat_update_instance($ucat) {
    global $DB;

    $ucat->id = $ucat->instance;
    $DB->update_record('ucat', $ucat);

    return true;
}

/**
 *
 * @param object $ucat
 * @return int
 */
function ucat_delete_instance($id) {
    global $DB;
    $DB->delete_records('ucat', array('id' => $id));
    return true;
}
