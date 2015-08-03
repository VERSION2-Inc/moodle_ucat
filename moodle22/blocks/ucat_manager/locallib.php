<?php
defined('MOODLE_INTERNAL') || die();

if ($CFG->version < 2013111800) {
    function block_ucat_manager_autoload($classname) {
        global $CFG;

        if (strpos($classname, 'block_ucat_manager') === 0) {
            $classname = preg_replace('/^block_ucat_manager\\\\/', '', $classname);

            $classdir = $CFG->dirroot . '/blocks/ucat_manager/classes/';
            $path = $classdir . str_replace('\\', DIRECTORY_SEPARATOR, $classname) . '.php';
            if (is_readable($path)) {
                require $path;
            }
        }
    }

    spl_autoload_register('block_ucat_manager_autoload');
}
