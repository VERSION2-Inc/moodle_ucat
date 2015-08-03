<?php
function xmldb_ucat_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2015011500) {

        // Define table ucat_target_probabilities to be created
        $table = new xmldb_table('ucat_target_probabilities');

        // Adding fields to table ucat_target_probabilities
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ucat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('targettype', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('probability', XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('numquestions', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table ucat_target_probabilities
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('ucat', XMLDB_KEY_FOREIGN, array('ucat'), 'ucat', array('id'));

        // Conditionally launch create table for ucat_target_probabilities
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // ucat savepoint reached
        upgrade_mod_savepoint(true, 2015011500, 'ucat');
    }

    if ($oldversion < 2015072801) {

        // Define field saveability to be added to ucat
        $table = new xmldb_table('ucat');
        $field = new xmldb_field('saveability', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'supervisor');

        // Conditionally launch add field saveability
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // ucat savepoint reached
        upgrade_mod_savepoint(true, 2015072801, 'ucat');
    }

    return true;
}
