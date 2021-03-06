<?php
/**
 * Functionality to edit data from this interface.
 *
 * TODO:
 *  - Does not support checkboxes yet, since these
 *    return no value when not checked.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */
require('init.php');
requireAdmin();

$cmd = @$_POST['cmd'];
if ( $cmd != 'add' && $cmd != 'edit' && $cmd != 'batch_adding') error ("Unknown action.");

require(LIBDIR .  '/relations.php');

$t = @$_POST['table'];
if(!$t) error ("No table selected.");
if(!in_array($t, array_keys($KEYS))) error ("Unknown table.");

$keydata       = @$_POST['keydata'];
$skipwhenempty = @$_POST['skipwhenempty'];
$referrer      = @$_POST['referrer'];


// ensure referrer only contains a single filename, not complete URLs
if ( ! preg_match('/^[._a-zA-Z0-9?&=]*$/', $referrer ) ) error ("Invalid characters in referrer.");

require(LIBWWWDIR . '/checkers.jury.php');

if ( isset($_POST['cancel']) ) {
	// do nothing
} elseif ( $cmd == 'batch_adding' ) {
	$login_begin = $_POST['login_begin'];
	$login_end   = $_POST['login_end'];

	for ( $i = $login_begin; $i <= $login_end; $i++ ) {
		$team       = $i;
		$categories = array(
			'system'        => 1,
			'participants'  => 2,
			'observers'     => 3,
			'organisation'  => 4,
		);

		$itemdata = array(
			'login'      => $team,
			'name'       => $team,
			'categoryid' => $categories['system'],
			'authtoken'  => md5($team . '#' . $team),
		);

		$newid = $DB->q("RETURNID INSERT INTO $t SET %S", $itemdata);
		auditlog($t, $newid, 'added');
		auditlog('team', $team, 'set password');
	}
} else {
	$data          =  $_POST['data'];
	if ( empty($data) ) error ("No data.");

	foreach ($data as $i => $itemdata ) {
		if ( !empty($skipwhenempty) && empty($itemdata[$skipwhenempty]) ) {
			continue;
		}

		// set empty string to null
		foreach ( $itemdata  as $k => $v ) {
			if ( $v === "" ) {
				$itemdata[$k] = null;
			}
		}

		$fn = "check_$t";
		if ( function_exists($fn) ) {
			$CHECKER_ERRORS = array();
			$itemdata = $fn($itemdata, $keydata[$i]);
			if ( count($CHECKER_ERRORS) ) {
				error("Errors while processing $t " .
					@implode(', ', @$keydata[$i]) . ":\n" .
					implode(";\n", $CHECKER_ERRORS));
			}

		}
		check_sane_keys($itemdata);

		if ( !empty($_FILES['problem_file']['tmp_name']) ) {
			$itemdata['prob_file'] = file_get_contents($_FILES['problem_file']['tmp_name']);
		}

		if ( $cmd == 'add' ) {
			$newid = $DB->q("RETURNID INSERT INTO $t SET %S", $itemdata);
			auditlog($t, $newid, 'added');

			foreach($KEYS[$t] as $tablekey) {
				if ( isset($itemdata[$tablekey]) ) {
					$newid = $itemdata[$tablekey];
				}
			}
		} elseif ( $cmd == 'edit' ) {
			foreach($KEYS[$t] as $tablekey) {
					$prikey[$tablekey] = $keydata[$i][$tablekey];
			}
			check_sane_keys($prikey);

			$DB->q("UPDATE $t SET %S WHERE %S", $itemdata, $prikey);
			auditlog($t, implode(', ', $prikey), 'updated');
		}
	}
}

// Throw the user back to the page he came from, if not available
// to the overview for the edited data.
if ( !empty($referrer) ) {
	$returnto = $referrer;
} else {
	$returnto = ($t == 'team_category' ? 'team_categories' : $t.'s'). '.php';
}

header('Location: '.$returnto);

/**
 * Check an array with field->value data to make sure there's no
 * strange characters in the field name, so we can use that safely
 * in a SQL query.
 */
function check_sane_keys($itemdata) {
	foreach(array_keys($itemdata) as $key) {
		if ( ! preg_match ('/^' . IDENTIFIER_CHARS . '+$/', $key ) ) {
			error ("Invalid characters in field name \"$key\".");
		}
	}
}
