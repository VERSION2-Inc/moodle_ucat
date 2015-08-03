<?php
namespace block_ucat_manager;

abstract class page {
    protected $url;
    protected $output;
    protected $course;
    protected $context;

    public function __construct($url) {
        global $CFG, $PAGE, $OUTPUT, $COURSE;

        if (strpos($url, $CFG->dirroot) === 0) {
            $url = substr($url, strlen($CFG->dirroot));

            if (DIRECTORY_SEPARATOR != '/')
                $url = str_replace(DIRECTORY_SEPARATOR, '/', $url);
        }

        $courseid = required_param('courseid', PARAM_INT);
        require_login($courseid);
        $this->course = $COURSE;

        $this->url = new \moodle_url($url, ['courseid' => $this->course->id]);

        $this->context = \context_course::instance($this->course->id);

        $PAGE->set_url($this->url);

        $this->output = $OUTPUT;
    }

    abstract function run();

    public static function run_new($url) {
        $page = new static($url);
        $page->run();
    }

    protected function course_url() {
        return new \moodle_url('/course/view.php', ['id' => $this->course->id]);
    }

    protected function require_capability($capability) {
        require_capability($capability, $this->context);
    }
}
