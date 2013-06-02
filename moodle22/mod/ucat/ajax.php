<?php
require_once '../../config.php';
require_once $CFG->dirroot.'/mod/ucat/lib.php';

class ucat_ajax {
    public function execute() {
        switch (optional_param('action', '', PARAM_ALPHAEXT)) {
            case 'load_question':
                $this->load_question();
                break;
        }
    }

    private function load_question() {
        $cmid = optional_param('cmid', 0, PARAM_INT);
        $cm = get_coursemodule_from_id('ucat', $cmid);
        var_dump($cm);

        $ucat = new ucat($cm);
        $question = $ucat->get_next_question(0);
        echo $ucat->render_question($question->id);
    }
}

require_login(1);
$ajax = new ucat_ajax();
$ajax->execute();
