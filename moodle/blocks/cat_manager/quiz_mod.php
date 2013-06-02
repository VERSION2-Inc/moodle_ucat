<?php
/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 * @version $Id: quiz_mod.php 18 2012-05-17 12:19:29Z yama $
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot.'/blocks/cat_manager/lib.php';

class cat_quiz_mod {
    public static function attempt_1() {
        global $DB, $USER, $attemptid, $catquiz, $page;

        $attempt = $DB->get_record('quiz_attempts', array('id' => $attemptid));
        $quiz = $DB->get_record('quiz', array('id' => $attempt->quiz));
        $catquiz = $DB->get_record('cat_quizzes', array('quiz' => $quiz->id));
        if (!empty($catquiz->usecat)) {
            if (cat_check_ending_condition($attemptid, $page)) {
                // CAT受験の終了条件に到達

                $attemptobj = quiz_attempt::create($attemptid);

                // processattempt.phpより受験の終了処理
                $questionids = $attemptobj->get_question_ids();
                $attemptobj->load_questions($questionids);

                /// Now load the state of every question, reloading the ones we messed around
                /// with above.
                $attemptobj->preload_question_states();
                $attemptobj->load_question_states();

                // /// Move each question to the closed state.
                // $success = true;
                $attempt = $attemptobj->get_attempt();
                foreach ($attemptobj->get_questions() as $id => $question) {

                    $state = $attemptobj->get_question_state($id);

                    $action = new stdClass();
                    $action->event = QUESTION_EVENTCLOSE;
                    $action->responses = $state->responses;
                    $action->responses['_flagged'] = $state->flagged;
                    $action->timestamp = $state->timestamp;
                    if (question_process_responses($attemptobj->get_question($id),
                                                   $state, $action, $attemptobj->get_quiz(), $attempt)) {
                        save_question_session($attemptobj->get_question($id), $state);
                    } else {
                        $success = false;
                    }
                }

                $timenow = time();
                $attempt->timemodified = $timenow;
                $attempt->timefinish = $timenow;

                $DB->update_record('quiz_attempts', $attempt);

                $eventdata = new stdClass();
                $eventdata->component  = 'mod_quiz';
                $eventdata->course     = $attemptobj->get_courseid();
                $eventdata->quiz       = $attemptobj->get_quizid();
                $eventdata->cm         = $attemptobj->get_cmid();
                $eventdata->user       = $USER;
                $eventdata->attempt    = $attemptobj->get_attemptid();
                events_trigger('quiz_attempt_processed', $eventdata);

                $sess = $DB->get_record('cat_sessions', array('attempt' => $attempt->id));
//            cat_add_record($sess, 'closed by program');

                redirect($attemptobj->review_url(0, $page));
            }

            // $state = cat_get_question_state($attemptid, $page);
            // すでにセーブされているか=解答されているかチェック
            // if ($state->event != QUESTION_EVENTSAVE) {
//        if (cat_is_question_saved($attemptid, $page)) {
//            echo 'editing eapmet';die;
            cat_modify_attempt_layout($attemptid, $page, cat_get_next_question($attemptid, $page));
//        }
        }
    }

    public static function attempt_2() {
        global $catquiz, $attemptid, $id, $page, $attemptobj;

        if (!empty($catquiz->usecat)) {
            if ($catquiz->showstate) {
                cat_print_state($attemptid, $id);
            }
            $state = cat_get_question_state($attemptid, $page);
//        if ($state->event == QUESTION_EVENTSAVE) {
            if (cat_is_question_saved($attemptid, $page)) {
                echo get_string('questionclosed', 'block_cat_manager');
            } else {
                $attemptobj->print_question($id, false, $attemptobj->attempt_url($id, $page));
            }
        } else {
            $attemptobj->print_question($id, false, $attemptobj->attempt_url($id, $page));
        }
    }

    public static function processattempt_1() {
        global $DB, $attemptid, $nextpage;

        $attempt = $DB->get_record('quiz_attempts', array('id' => $attemptid));
        $quiz = $DB->get_record('quiz', array('id' => $attempt->quiz));
        $catquiz = $DB->get_record('cat_quizzes', array('quiz' => $quiz->id));
        if (!empty($catquiz->usecat)) {

            if ($nextpage == -1) {
                $catpage = -1;
            } else {
                $catpage = $nextpage - 1;
            }
            // 再解答で評価が変わらないようにする
//        if (!cat_is_question_saved($attemptid, $catpage)) {
            cat_update_session($attempt, $catpage, 'next question');
//        }
        }
    }

    public static function processattempt_2() {
        global $finishattempt, $attempt, $catquiz;

        if (!empty($catquiz->usecat) && $finishattempt) {
            cat_save_ability($attempt);
        }
    }

    public static function review_1() {
    	global $DB, $OUTPUT, $attemptobj, $accessmanager;

        echo $OUTPUT->heading(get_string('catresult', 'block_cat_manager'));

        $catquiz = $DB->get_record('cat_quizzes', array('quiz' => $attemptobj->get_quizid()));
        if (!empty($catquiz->showstate)) {
            cat_print_state($attemptobj->get_attemptid());
        }

        $accessmanager->print_finish_review_link($attemptobj->is_preview_user());
    }

    public static function startattempt_1() {
        global $DB, $quiz, $attempt;

        $catquiz = $DB->get_record('cat_quizzes', array('quiz' => $quiz->id));
        if (!empty($catquiz->usecat)) {
            cat_create_session($attempt);
        }
    }

    public static function summary_1() {
        global $DB, $quiz, $attemptobj, $attemptid, $flag, $number, $question, $attempt, $quiz, $catquiz;

        if (!isset($attempt)) {
            $attempt = $DB->get_record('quiz_attempts', array('id' => $attemptid));
        }
        if (!isset($quiz)) {
            $quiz = $DB->get_record('quiz', array('id' => $attempt->quiz));
        }

        $catquiz = $DB->get_record('cat_quizzes', array('quiz' => $quiz->id));
        if (!empty($catquiz->usecat)) {
            $row = array($number . $flag,
                         get_string($attemptobj->get_question_status($question->id), 'quiz'));
        } else {
            $row = array('<a href="' . s($attemptobj->attempt_url($question->id)) . '">' . $number . $flag . '</a>',
                         get_string($attemptobj->get_question_status($question->id), 'quiz'));
        }
    }

    public static function summary_2() {
        global $catquiz, $attemptid;

        if (!empty($catquiz->usecat) && $catquiz->showstate) {
            cat_print_state($attemptid);
        }
    }
}
