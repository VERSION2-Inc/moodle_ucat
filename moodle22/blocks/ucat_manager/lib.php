<?php
/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 * @version $Id: lib.php 19 2012-07-27 09:50:38Z yama $
 */

defined('MOODLE_INTERNAL') || die();

define('CAT_ENDCOND_ALL', 0);
define('CAT_ENDCOND_NUMQUEST', 1);
define('CAT_ENDCOND_SE', 2);
define('CAT_ENDCOND_NUMQUESTANDSE', 3);
define('CAT_DUMMY_SE', 100);

function cat_logit2unit($val) {
    return (int) ($val * 10 + 100);
}

function cat_unit2logit($val) {
    return ($val - 100) * 0.1;
}

function cat_diff_logit2unit($val) {
    return (int) ($val * 10);
}

function cat_get_ability($uset, $userid) {
    global $DB;

    if ($uinfo = $DB->get_record('cat_users', array('userset' => $uset, 'userid' => $userid))) {
        return $uinfo->ability;
    } else {
        return 5;
    }
}

function cat_get_difficulty($questionid) {
    global $DB;

    if ($qinfo = $DB->get_record('cat_questions', array('questionid' => $questionid))) {
        return $qinfo->difficulty;
    } else {
        return 5;
    }
}

function cat_create_session($attempt) {
    global $DB;

    $qmean = cat_get_mean_difficulty($attempt->quiz);

    $quiz = $DB->get_record('quiz', array('id' => $attempt->quiz));
    $cquiz = $DB->get_record('cat_quizzes', array('quiz' => $quiz->id));

    $sess = new stdClass();
    $sess->attempt = $attempt->id;
    $sess->questions = '';
    $sess->states = '';
    $sess->ability = cat_get_ability($cquiz->userset, $attempt->userid);
    $sess->abilright = $sess->ability + 1;
    $sess->se = CAT_DUMMY_SE;

    $DB->insert_record('cat_sessions', $sess);

    $questions = array_filter(explode(',', $attempt->layout));
    $newquestions = array();
    foreach ($questions as $q) {
        $newquestions[] = $q;
        $newquestions[] = 0;
    }

    $newattempt = new stdClass();
    $newattempt->id = $attempt->id;
    $newattempt->layout = implode(',', $newquestions);

    $DB->update_record('quiz_attempts', $newattempt);
}

function cat_get_next_question($attemptid, $page) {
    global $DB;

    $attempt = $DB->get_record('quiz_attempts', array('id' => $attemptid));

    $layout = cat_get_question_ids($attempt->layout);

    $questions = array_slice($layout, $page);

    $qcount = count($questions);

    $sess = $DB->get_record('cat_sessions', array('attempt' => $attempt->id));
    if (!$sess) {
        cat_create_session($attempt);
        $sess = $DB->get_record('cat_sessions', array('attempt' => $attempt->id));
    }

    $accuracy = 0.7;

    $qselect = 0;
    if ($qcount == 0) {
        return;
    }

    $quiz = $DB->get_record('quiz', array('id' => $attempt->quiz));
    $cquiz = $DB->get_record('cat_quizzes', array('quiz' => $quiz->id));
    $bias = 0;
    if (!empty($cquiz->logitbias)) {
        $bias = $cquiz->logitbias;
    }

    $n = mt_rand(0, $qcount - 1);
    $abilhalf = ($sess->abilright + $sess->ability) * 0.5;
    $qselect = 0;
    for ($qq = $n + 1; $qq <= $qcount + $n; $qq++) {
        if ($qq >= $qcount) {
            $q = $qq - $qcount;
        } else {
            $q = $qq;
        }

        $i = cat_get_difficulty($questions[$q]);
        if ($i >= $sess->ability + $bias && $i <= $sess->abilright + $bias) {
            $qselect = $q;
            return $questions[$qselect];
        }
        if ($qselect == 0 || abs($i - $abilhalf) < $qhold) {
            $qselect = $q;
            $qhold = abs($i - $abilhalf);
        }
    }

    return $questions[$qselect];
}

function cat_get_question_state($attemptid, $page) {
    global $DB;

    if ($page < 0) {
        return false;
    }

    $attempt = $DB->get_record('quiz_attempts', array('id' => $attemptid));
    $layout = cat_get_question_ids($attempt->layout);
    $questionid = $layout[$page];

    $state = $DB->get_record_sql(
        'SELECT * from {question_states}'
        . ' WHERE attempt = ? AND question = ?'
        . ' ORDER BY seq_number DESC LIMIT 1',
        array($attemptid, $questionid));

    return $state;
}

function cat_is_question_saved($attemptid, $page) {
    global $DB;

    if ($page < 0) {
        return false;
    }

    $attempt = $DB->get_record('quiz_attempts', array('id' => $attemptid));
    $layout = cat_get_question_ids($attempt->layout);
    $questionid = $layout[$page];

    $state = $DB->get_records_sql(
        'SELECT * FROM {question_states}'
        . ' WHERE attempt = ? AND question = ? AND event = ?',
        array($attemptid, $questionid, QUESTION_EVENTSAVE));

    return $state;
}

function cat_check_ending_condition($attemptid, $page) {
    global $DB;

    if ($page < 0) {
        return false;
    }

    $attempt = $DB->get_record('quiz_attempts', array('id' => $attemptid));
    $sess = $DB->get_record('cat_sessions', array('attempt' => $attempt->id));
    $quiz = $DB->get_record('quiz', array('id' => $attempt->quiz));
    $cquiz = $DB->get_record('cat_quizzes', array('quiz' => $quiz->id));
    $layout = cat_get_question_ids($attempt->layout);
    $questionid = $layout[$page];

    switch ($cquiz->endcondition) {
    case CAT_ENDCOND_NUMQUEST:
        if ($page >= $cquiz->questions) {
            return true;
        }
        return false;

    case CAT_ENDCOND_SE:
        if ($sess->se < $cquiz->se) {
            return true;
        }
        return false;

    case CAT_ENDCOND_NUMQUESTANDSE:
        if ($page > $cquiz->numquestions && $sess->se < $cquiz->se) {
            return true;
        }
        return false;

    case CAT_ENDCOND_ALL:
        return false;
    }
}

function cat_modify_attempt_layout($attemptid, $page, $nextquestion) {
    global $DB;

    $attempt = $DB->get_record('quiz_attempts', array('id' => $attemptid));

    $layout = array_filter(explode(',', $attempt->layout));

    $donequestions = array_slice($layout, 0, $page);
    $restquestions = array_slice($layout, $page);

    $newlayout = array();

    foreach ($donequestions as $q) {
        $newlayout[] = $q;
        $newlayout[] = 0;
    }

    if (!empty($nextquestion)) {
        $newlayout[] = $nextquestion;
        $newlayout[] = 0;
    }

    foreach ($restquestions as $q) {
        if ($q != $nextquestion) {
            $newlayout[] = $q;
            $newlayout[] = 0;
        }
    }

    $newattempt = new stdClass();
    $newattempt->id = $attempt->id;
    $newattempt->layout = implode(',', $newlayout);

    $DB->update_record('quiz_attempts', $newattempt);
}

function cat_update_session($attempt, $page, $data) {
    global $DB;

    if ($page < 0) {
//        return false;
    }

    // 解答済みの問題は更新しない
    // $state = cat_get_question_state($attempt->id, $page);
    // // $aobj->get_que
    // if ($state->event == QUESTION_EVENTSAVE) {
    //     return false;
    // }

    $sess = $DB->get_record('cat_sessions', array('attempt' => $attempt->id));
    if (!$sess) {
        cat_create_session($attempt);
        $sess = $DB->get_record('cat_sessions', array('attempt' => $attempt->id));
    }

    $questions = cat_get_question_ids($attempt->layout);

    if ($page < 0) {
        $page = count($questions) - 1;
    }

    $pexp = 0;
    $pvar = 0;
    $presult = 0;
    for ($p = 0; $p <= $page; $p++) {
        $question = $DB->get_record('question', array('id' => $questions[$p]));

        // 零除算エラー対策
        if ($question->defaultgrade == 0) {
            continue;
        }

        $success = 1 / (1 + exp(cat_get_difficulty($questions[$p]) - $sess->ability));
        $pexp += $success;
        $pvar += $success * (1 - $success);

        $state = $DB->get_record_sql(
            'SELECT * FROM {question_states}'
            . ' WHERE attempt = ? AND question = ?'
            . ' ORDER BY seq_number DESC LIMIT 1',
            array($attempt->id, $questions[$p]));
        $presult += $state->raw_grade / $question->defaultgrade;
    }
    if ($pvar != 0) {
        $sess->se = sqrt(1 / $pvar);
    }
    if ($pvar < 1) {
        $pvar = 1;
    }

    $sess->ability += ($presult - $pexp) / $pvar;
    $sess->abilright = $sess->ability + (1 / $pvar);

    $DB->update_record('cat_sessions', $sess);

    cat_add_record($sess, $data);
}

function cat_save_ability($attempt) {
    global $DB;

    $quiz = $DB->get_record('quiz', array('id' => $attempt->quiz));
    $sess = $DB->get_record('cat_sessions', array('attempt' => $attempt->id));

    if (!$sess) {
        return;
    }

    $userid = $attempt->userid;

    if ($uinfo = $DB->get_record(
            'cat_users',
            array('userset' => $quiz->userset, 'userid' => $userid))) {
        $uinfo->ability = $sess->ability;
        $DB->update_record('cat_users', $uinfo);
    } else {
        $uinfo = new stdClass();
        $uinfo->userset = $quiz->userset;
        $uinfo->userid = $userid;
        $uinfo->ability = $sess->ability;
        $DB->insert_record('cat_users', $uinfo);
    }
}

function cat_get_mean_difficulty($quizid) {
    global $DB;

    $quiz = $DB->get_record('quiz', array('id' => $quizid));

    $questions = cat_get_question_ids($quiz->questions);

    $sum = 0;
    $n = 0;
    foreach ($questions as $q) {
        $sum += cat_get_difficulty($q);
        $n++;
    }

    return $sum / $n;
}

function cat_get_question_ids($layout) {
    return array_merge(array_filter(explode(',', $layout)));
}

function cat_print_state($attemptid, $questionid = null) {
    global $DB;

    $sess = $DB->get_record('cat_sessions', array('attempt' => $attemptid));

    $ability = cat_logit2unit($sess->ability);
    echo '<div style="text-align: center">' . get_string('abilityestimate', 'block_cat_manager') . ': ' . $ability . '<br/>';
    if ($questionid) {
        echo get_string('itemdifficulty', 'block_cat_manager') . ': ' . cat_logit2unit(cat_get_difficulty($questionid))
            . '<br/>';
    }
    if ($sess->se < CAT_DUMMY_SE) {
        $se = cat_diff_logit2unit($sess->se);
        echo get_string('se', 'block_cat_manager') . ': ' . $se . '<br/>';
        echo get_string('probableabilityrange', 'block_cat_manager') . ': ' . ($ability - $se) . '–' . ($ability + $se);
    }
    echo '</div>';
}

function cat_add_record($sess, $data) {
    global $DB;

    $attempt = $DB->get_record('quiz_attempts', array('id' => $sess->attempt));
    $quiz = $DB->get_record('quiz', array('id' => $attempt->quiz));
    $catquiz = $DB->get_record('cat_quizzes', array('quiz' => $quiz->id));

    if (!empty($catquiz->record)) {
        $seq = 1;
        if ($lastrec = $DB->get_record_sql('SELECT * FROM {cat_records} WHERE session = ? ORDER BY seq DESC LIMIT 1', array($sess->id))) {
            $seq = $lastrec->seq + 1;
        }

        $rec = new stdClass();
        $rec->session = $sess->id;
        $rec->seq = $seq;
        $rec->timemodified = time();
        $rec->states = $sess->states;
        $rec->ability = $sess->ability;
        $rec->abilright = $sess->abilright;
        $rec->se = $sess->se;
        $rec->states = $data;

        $DB->insert_record('cat_records', $rec);
    }
}

function cat_download_record($attemptid) {
    global $DB;

    $attempt = $DB->get_record('quiz_attempts', array('id' => $attemptid));
    $sess = $DB->get_record('cat_sessions', array('attempt' => $attempt->id));
    $records = $DB->get_records('cat_records', array('session' => $sess->id), 'seq');
    $layout = array_merge(array_filter(explode(',', $attempt->layout)));

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=CAT_' . $attempt->id . '.csv');

    echo "Q,Question,Difficulty,Grade,Ability,SE,Type\n";
    $i = 0;
    foreach ($records as $rec) {
        $quest = $DB->get_record('question', array('id' => $layout[$i]));
        $state = $DB->get_record_sql(
            'SELECT * FROM {question_states} WHERE attempt = ? AND question = ? ORDER BY seq_number DESC LIMIT 1',
            array($attempt->id, $quest->id));

        echo implode(',',
                array(
                        $i + 1,
                        '"' . $quest->name . '"',
                        cat_logit2unit(cat_get_difficulty($quest->id)),
                        $state->grade,
                        cat_logit2unit($rec->ability),
                        $rec->se,
                        $rec->states
                )
        ) . "\n";
        $i++;
    }
}
