<?php
namespace block_ucat_manager;

require_once '../../config.php';
require_once $CFG->dirroot . '/mod/ucat/locallib.php';

class page_ajax {
    public function __construct() {

    }

    public function run() {
        try {
            switch (required_param('act', PARAM_ALPHA)) {
                case 'abilityupdate': $this->ability_update(); break;
                default: throw new http_exception(400);
            }

        } catch (http_exception $e) {
            header('HTTP', true, $e->status);
            echo 'an error occured';
            echo $e->getMessage();
        } catch (\Exception $e) {
            header('HTTP', true, 403);
            echo 'generic error ocured';
        }
    }

    private function ability_update() {
        global $DB;

        $courseid = required_param('course', PARAM_INT);
        $context = \context_course::instance($courseid);
        require_capability('block/ucat_manager:manage', $context);

        $uset = required_param('uset', PARAM_INT);
        $userid = required_param('user', PARAM_INT);
        $ability = \ucat::unit2logit(required_param('ability', PARAM_INT));

        $user = new \ucat_user($uset, $userid);
        $user->set_ability($ability);

        echo json_encode([
            'ability' => \ucat::logit2unit($ability)
        ]);
    }
}

class http_exception extends \Exception {
    public $status;

    public function __construct($status) {
        $this->status = $status;
    }
}

class forbidden_exception extends http_exception {
    public function __construct() {
        parent::__construct(403);
    }
}

$page = new page_ajax();
$page->run();
