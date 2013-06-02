<?php
defined('MOODLE_INTERNAL') || die();

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
