<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Bulk user registration functions
 *
 * @package    core
 * @subpackage admin
 * @copyright  2004 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

define('UU_USER_ADDNEW', 0);
define('UU_USER_ADDINC', 1);
define('UU_USER_ADD_UPDATE', 2);
define('UU_USER_UPDATE', 3);

define('UU_UPDATE_NOCHANGES', 0);
define('UU_UPDATE_FILEOVERRIDE', 1);
define('UU_UPDATE_ALLOVERRIDE', 2);
define('UU_UPDATE_MISSING', 3);

define('UU_BULK_NONE', 0);
define('UU_BULK_NEW', 1);
define('UU_BULK_UPDATED', 2);
define('UU_BULK_ALL', 3);

define('UU_PWRESET_NONE', 0);
define('UU_PWRESET_WEAK', 1);
define('UU_PWRESET_ALL', 2);

/**
 * Tracking of processed users.
 *
 * This class prints user information into a html table.
 *
 * @package    core
 * @subpackage admin
 * @copyright  2007 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class uu_progress_tracker {
    private $_row;
    public $columns = array('status', 'line', 'id', 'username', 'firstname', 'lastname', 'email', 'password', 'auth', 'enrolments', 'deleted');

    /**
     * Print table header.
     * @return void
     */
    public function start() {
        $ci = 0;
        echo '<table id="uuresults" class="generaltable boxaligncenter flexible-wrap" summary="'.get_string('uploadusersresult', 'admin').'">';
        echo '<tr class="heading r0">';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('status').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('uucsvline', 'admin').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">ID</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('username').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('firstname').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('lastname').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('email').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('password').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('authentication').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('enrolments', 'enrol').'</th>';
        echo '<th class="header c'.$ci++.'" scope="col">'.get_string('delete').'</th>';
        echo '</tr>';
        $this->_row = null;
    }

    /**
     * Flush previous line and start a new one.
     * @return void
     */
    public function flush() {
        if (empty($this->_row) or empty($this->_row['line']['normal'])) {
            // Nothing to print - each line has to have at least number
            $this->_row = array();
            foreach ($this->columns as $col) {
                $this->_row[$col] = array('normal'=>'', 'info'=>'', 'warning'=>'', 'error'=>'');
            }
            return;
        }
        $ci = 0;
        $ri = 1;
        echo '<tr class="r'.$ri.'">';
        foreach ($this->_row as $key=>$field) {
            foreach ($field as $type=>$content) {
                if ($field[$type] !== '') {
                    $field[$type] = '<span class="uu'.$type.'">'.$field[$type].'</span>';
                } else {
                    unset($field[$type]);
                }
            }
            echo '<td class="cell c'.$ci++.'">';
            if (!empty($field)) {
                echo implode('<br />', $field);
            } else {
                echo '&nbsp;';
            }
            echo '</td>';
        }
        echo '</tr>';
        foreach ($this->columns as $col) {
            $this->_row[$col] = array('normal'=>'', 'info'=>'', 'warning'=>'', 'error'=>'');
        }
    }

    /**
     * Add tracking info
     * @param string $col name of column
     * @param string $msg message
     * @param string $level 'normal', 'warning' or 'error'
     * @param bool $merge true means add as new line, false means override all previous text of the same type
     * @return void
     */
    public function track($col, $msg, $level = 'normal', $merge = true) {
        if (empty($this->_row)) {
            $this->flush(); //init arrays
        }
        if (!in_array($col, $this->columns)) {
            debugging('Incorrect column:'.$col);
            return;
        }
        if ($merge) {
            if ($this->_row[$col][$level] != '') {
                $this->_row[$col][$level] .='<br />';
            }
            $this->_row[$col][$level] .= $msg;
        } else {
            $this->_row[$col][$level] = $msg;
        }
    }

    /**
     * Print the table end
     * @return void
     */
    public function close() {
        $this->flush();
        echo '</table>';
    }
}

/**
 * Validation callback function - verified the column line of csv file.
 * Converts column names to lowercase too.
 * @param csv_import_reader $cir
 * @param array standard user fields
 * @param array custom profile fields
 * @param moodle_url $returnurl return url in case of any error
 * @return array list of fields
 */
function uu_validate_user_upload_columns(csv_import_reader $cir, $stdfields, $frofilefields, moodle_url $returnurl) {
    $columns = $cir->get_columns();

    if (empty($columns)) {
        $cir->close();
        $cir->cleanup();
        print_error('cannotreadtmpfile', 'error', $returnurl);
    }
    if (count($columns) < 2) {
        $cir->close();
        $cir->cleanup();
        print_error('csvfewcolumns', 'error', $returnurl);
    }

    // test columns
    $processed = array();
    foreach ($columns as $key=>$unused) {
        $field = strtolower($columns[$key]); // no unicode expected here, ignore case
        if (!in_array($field, $stdfields) && !in_array($field, $frofilefields) &&// if not a standard field and not an enrolment field, then we have an error
            !preg_match('/^course\d+$/', $field) && !preg_match('/^group\d+$/', $field) &&
            !preg_match('/^type\d+$/', $field) && !preg_match('/^role\d+$/', $field) &&
            !preg_match('/^enrolperiod\d+$/', $field)) {
            print_error('invalidfieldname', 'error', $returnurl, $field);
        }
        if (in_array($field, $processed)) {
            $cir->close();
            $cir->cleanup();
            print_error('duplicatefieldname', 'error', $returnurl, $field);
        }
        $processed[$key] = $field;
    }

    return $processed;
}

/**
 * Increments username - increments trailing number or adds it if not present.
 * Varifies that the new username does not exist yet
 * @param string $username
 * @return incremented username which does not exist yet
 */
function uu_increment_username($username) {
    global $DB, $CFG;

    if (!preg_match_all('/(.*?)([0-9]+)$/', $username, $matches)) {
        $username = $username.'2';
    } else {
        $username = $matches[1][0].($matches[2][0]+1);
    }

    if ($DB->record_exists('user', array('username'=>$username, 'mnethostid'=>$CFG->mnet_localhost_id))) {
        return uu_increment_username($username);
    } else {
        return $username;
    }
}

/**
 * Check if default field contains templates and apply them.
 * @param string template - potential tempalte string
 * @param object user object- we need username, firstname and lastname
 * @return string field value
 */
function uu_process_template($template, $user) {
    if (is_array($template)) {
        // hack for for support of text editors with format
        $t = $template['text'];
    } else {
        $t = $template;
    }
    if (strpos($t, '%') === false) {
        return $template;
    }

    $username  = isset($user->username)  ? $user->username  : '';
    $firstname = isset($user->firstname) ? $user->firstname : '';
    $lastname  = isset($user->lastname)  ? $user->lastname  : '';

    $callback = partial('uu_process_template_callback', $username, $firstname, $lastname);

    $result = preg_replace_callback('/(?<!%)%([+-~])?(\d)*([flu])/', $callback, $t);

    if (is_null($result)) {
        return $template; //error during regex processing??
    } else {
        if (array($template)) {
            $template['text'] = $t;
            return $t;
        } else {
            return $t;
        }
    }
}

/**
 * Internal callback function.
 */
function uu_process_template_callback($block, $username, $firstname, $lastname) {
    $textlib = textlib_get_instance();
    $repl = $block[0];

    switch ($block[3]) {
        case 'u': $repl = $username; break;
        case 'f': $repl = $firstname; break;
        case 'l': $repl = $lastname; break;
    }
    switch ($block[1]) {
        case '+': $repl = $textlib->strtoupper($repl); break;
        case '-': $repl = $textlib->strtolower($repl); break;
        case '~': $repl = $textlib->strtotitle($repl); break;
    }
    if (!empty($block[2])) {
        $repl = $textlib->substr($repl, 0 , $block[2]);
    }

    return $repl;
}

/**
 * Returns list of auth plugins that are enabled and known to work.
 *
 * If ppl want to use some other auth type they have to include it
 * in the CSV file next on each line.
 *
 * @return array type=>name
 */
function uu_supported_auths() {
    // only following plugins are guaranteed to work properly
    $whitelist = array('manual', 'nologin', 'none', 'email');
    $plugins = get_enabled_auth_plugins();
    $choices = array();
    foreach ($plugins as $plugin) {
        if (!in_array($plugin, $whitelist)) {
            continue;
        }
        $choices[$plugin] = get_string('pluginname', "auth_{$plugin}");
    }

    return $choices;
}

/**
 * Returns list of roles that are assignable in courses
 * @return array
 */
function uu_allowed_roles() {
    // let's cheat a bit, frontpage is guaranteed to exist and has the same list of roles ;-)
    $roles = get_assignable_roles(get_context_instance(CONTEXT_COURSE, SITEID), ROLENAME_ORIGINALANDSHORT);
    return array_reverse($roles, true);
}

/**
 * Returns mapping of all roles using short role name as index.
 * @return array
 */
function uu_allowed_roles_cache() {
    $allowedroles = get_assignable_roles(get_context_instance(CONTEXT_COURSE, SITEID), ROLENAME_SHORT);
    foreach ($allowedroles as $rid=>$rname) {
        $rolecache[$rid] = new stdClass();
        $rolecache[$rid]->id   = $rid;
        $rolecache[$rid]->name = $rname;
        if (!is_numeric($rname)) { // only non-numeric shortnames are supported!!!
            $rolecache[$rname] = new stdClass();
            $rolecache[$rname]->id   = $rid;
            $rolecache[$rname]->name = $rname;
        }
    }
    return $rolecache;
}
