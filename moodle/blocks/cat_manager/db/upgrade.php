<?php
/**
 * Computer-Adaptive Testing
 *
 * @package ucat
 * @author  VERSION2 Inc.
 * @version $Id$
 */

function xmldb_block_cat_manager_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2012051000) {
        $table = new xmldb_table('cat_quizzes');
        $field = new xmldb_field('showstate', XMLDB_TYPE_INTEGER, 1, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);

        $dbman->add_field($table, $field);

        upgrade_block_savepoint(true, 2012051000, 'cat_manager');
    }

    return true;
}
