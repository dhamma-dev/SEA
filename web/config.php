<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'moodleseadba.db.5740542.hostedresource.com';
$CFG->dbname    = 'moodleseadba';
$CFG->dbuser    = 'moodleseadba';
$CFG->dbpass    = 'M00dle53adba';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbsocket' => 0,
);

$CFG->wwwroot   = 'http://localhost/moodle';
$CFG->dataroot  = '/var/moodle/data';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;

$CFG->passwordsaltmain = '1{n9{T@qF[W>H`c=!ySpsm)8@H5ij#,F';

require_once(dirname(__FILE__) . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
