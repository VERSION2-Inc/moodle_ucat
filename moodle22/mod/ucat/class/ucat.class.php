<?php
defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir . '/questionlib.php';

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
     * @var stdClass
     */
    private $cm;
    /**
     *
     * @var context_module
     */
    private $context;
    /**
     *
     * @var stdClass
     */
    private $options;

    /**
     *
     * @param stdClass $cm
     */
    public function __construct(stdClass $cm) {
        global $DB;

        $this->cm = $cm;
        $this->context = context_module::instance($this->cm->id);
        $this->options = $DB->get_record('ucat', array('id' => $cm->instance));
    }

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
     * @param int $ucatid
     * @return stdClass
     */
    public function create_session($ucatid) {
        global $DB, $USER;

        $user = new ucat_user($this->options->userset);

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
        $sids = array_keys($sessions);

        $qcats = implode(',', question_categorylist($this->options->questioncategory));

        $questions = $DB->get_records_sql('SELECT q.id, uq.difficulty
                    FROM {question} q
                        LEFT JOIN {ucat_questions} uq ON q.id = uq.questionid
                    WHERE q.category in ('.$qcats.')'
        );
        $qids = array_keys($questions);

        $qcount = count($questions);
        $pasked = count($sessions);

        for ($p = 0; $p < $pasked; $p++) {
            for ($q = 0; $q < $qcount; $q++) {
                $result[$p][$q] = -1;
            }
            $pability[$p] = 0;


            $session = $sessions[$sids[$p]];

            $user = new ucat_user($this->options->userset, $session->userid);
            $pability[$p] = $user->get_ability();

            $quba = question_engine::load_questions_usage_by_activity($session->questionsusage);
            $sqids = unserialize($session->questions);
            $slot = 1;
            foreach ($sqids as $qid) {
                $qa = $quba->get_question_attempt($slot++);
                $q = array_search($qid, $qids);

                $r = $qa->get_fraction();
                if ($r !== null) {
                    $result[$p][$q] = $r;
                }
            }
        }

        // 集計
        $qtotal = 0;
        $ptotal = 0;
        $recount = 1;
        $totalcycle = 0;
        while ($recount) {
            $totalcycle++;
            if ($totalcycle > 10)
                die;

            echo 'Totaling scores...<br/>';
            $qtotal = 0;
            $ptotal = 0;
            $recount = 0;
            for ($q = 0; $q < $qcount; $q++) {
                $qasked[$q] = 0;
                $qscore[$q] = 0;
            }

            for ($p = 0; $p < $pasked; $p++) {
                $presult = 0;
                $pscore[$p] = 0;
                for ($q = 0; $q < $qcount; $q++) {
                    $n = $result[$p][$q];
                    if ($n >= 0) {
                        $presult++;
                        $qasked[$q]++;
                        $pscore[$p] += $n;
                        $qscore[$q] += $n;
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
                for ($q = 0; $q < $qcount; $q++) {
                    $result[$p][$q] = -1;
                }
            }

            for ($q = 0; $q < $qcount; $q++) {
                if ($qasked[$q] == 0) {
                    continue;
                }
                if ($qscore[$q] > 0 && $qscore[$q] < $qasked[$q]) {
                    $qtotal++;
                    continue;
                }
                $recount = 1;
                for ($p = 0; $p < $pasked; $p++) {
                    $result[$p][$q] = -1;
                }
            }

            if ($ptotal < 2 || $qtotal < 2) {
                echo 'Not enough data to reestimate<br/>';
                return;
            }
        }

        $bias = 1;
        for ($q = 0; $q < $qcount; $q++) {
            if ($qasked[$q] != 0) {
                $question = new ucat_question($q);
                $qdiff[$q] = $question->get_difficulty() / $bias;
            }
        }
        $padj = 0;
        for ($p = 0; $p < $pasked; $p++) {
            if ($pscore[$p] > 0) {
                $pability[$p] /= $bias;
                $padj += $pability[$p];
            }
        }

        // 再推定
        $recount = 1;
        $cycle = 1;

        while ($recount > 0 || $maxresidual > 0.1) {
            $recount = 0;
            $cycle++;
            $maxresidual = 0;

            echo 'Estimation cycle no. '.$cycle.'<br/>';
            if ($cycle>20)
                break;

            $psum = 0;

            for ($q = 0; $q < $qcount; $q++) {
                $qexp[$q] = 0;
                $qvar[$q] = 0;
            }

            for ($p = 0; $p < $pasked; $p++) {
                $pexp = 0;
                $pvar = 0;

                for ($q = 0; $q < $qcount; $q++) {
                    if ($qasked[$q] == 0 || $result[$q] == -1) {
                        break;
                    }

                    // Probability of success
                    $success = 1 / (1 + exp($question->get_difficulty() - $session->ability));

                    // Accumulate estimated scores
                    $qexp[$q] += $success;
                    $pexp += $success;

                    // Sum variance
                    $n = $success * (1 - $success);
                    $qvar[$q] += $n;
                    $pvar += $n;
                }

                // Difference between actual and estimated
                $residual = $pscore[$p] - $pexp;
                if (abs($residual) > $maxresidual) {
                    $maxresidual = abs($residual);
                }

                if ($pvar > 1) {
                    // Amount to adjust by
                    $residual /= $pvar;
                }

                // New ability estimate
                $pability[$p] += $residual;

                $pse[$p] = 1 / sqrt($pvar);

                // Ability sum across test-takers
                $psum += $pability[$p];
            }

            // What is change in mean ability?
            $psum = ($psum - $padj) / $ptotal;

            for ($p = 0; $p < $pasked; $p++) {
                // Keep mean ability of test-takers constant
                if ($pscore[$p] > 0) {
                       $pability[$p] -= $psum;
                }
            }

            for ($q = 0; $q < $qcount; $q++) {
                // Reestimate questions
                if (!$qasked[$q]) {
                    continue;
                }

                // Difference between actual and estimated
                $residual = $qscore[$q] - $qexp[$q];

                if (abs($residual) > $maxresidual) {
                    $maxresidual = abs($residual);
                }

                if ($qvar[$q] > 1) {
                    // Amount to adjust by
                    $residual /= $qvar[$q];
                }

                // New question difficulty estimate
                $qdiff[$q] -= $residual;
            }
        }

        // データ書き込み
        for ($p = 0; $p < $pasked; $p++) {
            $cuser = new ucat_user($this->options->userset, $sessions[$sids[$p]]->userid);
            $cuser->set_ability($pability[$p]);
        }

        for ($q = 0; $q < $qcount; $q++) {
            $cquestion = new ucat_question($qids[$q]);
            $cquestion->set_difficulty($qdiff[$q]);
        }

        echo 'Reestimation complete.';
    }
}
