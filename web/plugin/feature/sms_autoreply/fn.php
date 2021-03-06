<?php
defined('_SECURE_') or die('Forbidden');

/*
 * Implementations of hook checkavailablekeyword()
 *
 * @param $keyword
 *   checkavailablekeyword() will insert keyword for checking to the hook here
 * @return
 *   TRUE if keyword is available
 */
function sms_autoreply_hook_checkavailablekeyword($keyword) {
	$ok = true;
	$db_query = "SELECT autoreply_id FROM "._DB_PREF_."_featureAutoreply WHERE autoreply_keyword='$keyword'";
	if ($db_result = dba_num_rows($db_query)) {
		$ok = false;
	}
	return $ok;
}

/*
 * Implementations of hook setsmsincomingaction()
 *
 * @param $sms_datetime
 *   date and time when incoming sms inserted to playsms
 * @param $sms_sender
 *   sender on incoming sms
 * @param $autoreply_keyword
 *   check if keyword is for sms_autoreply
 * @param $autoreply_param
 *   get parameters from incoming sms
 * @param $sms_receiver
 *   receiver number that is receiving incoming sms
 * @return $ret
 *   array of keyword owner uid and status, TRUE if incoming sms handled
 */
function sms_autoreply_hook_setsmsincomingaction($sms_datetime,$sms_sender,$autoreply_keyword,$autoreply_param='',$sms_receiver='',$raw_message='') {
	$ok = false;
	$db_query = "SELECT uid,autoreply_id FROM "._DB_PREF_."_featureAutoreply WHERE autoreply_keyword='$autoreply_keyword'";
	$db_result = dba_query($db_query);
	if ($db_row = dba_fetch_array($db_result)) {
		$c_uid = $db_row['uid'];
		$autoreply_id = $db_row['autoreply_id'];
		if (sms_autoreply_handle($c_uid,$sms_datetime,$sms_sender,$sms_receiver,$autoreply_id,$autoreply_keyword,$autoreply_param,$raw_message)) {
			$ok = true;
		}
	}
	$ret['uid'] = $c_uid;
	$ret['status'] = $ok;
	return $ret;
}

function sms_autoreply_handle($c_uid,$sms_datetime,$sms_sender,$sms_receiver,$autoreply_id,$autoreply_keyword,$autoreply_param='',$raw_message) {
	global $datetime_now;
	$ok = false;
	$autoreply_keyword = strtoupper(trim($autoreply_keyword));
	$autoreply_param = strtoupper(trim($autoreply_param));
	$autoreply_request = $autoreply_keyword." ".$autoreply_param;
	$array_autoreply_request = preg_split('/[\s]+/',$autoreply_request);
	for ($i=0;$i<count($array_autoreply_request);$i++) {
		$autoreply_part[$i] = trim($array_autoreply_request[$i]);
		$tmp_autoreply_request .= trim($array_autoreply_request[$i])." ";
	}
	$autoreply_request = trim($tmp_autoreply_request);
	for ($i=1;$i<7;$i++) {
		$autoreply_scenario_param_list .= "autoreply_scenario_param$i='".$autoreply_part[$i]."' AND ";
	}
	$db_query = "
		SELECT autoreply_scenario_result FROM "._DB_PREF_."_featureAutoreply_scenario 
		WHERE autoreply_id='$autoreply_id' AND $autoreply_scenario_param_list 1=1";
	$db_result = dba_query($db_query);
	$db_row = dba_fetch_array($db_result);
	if ($autoreply_scenario_result = $db_row['autoreply_scenario_result']) {
		$ok = false;
		$c_username = uid2username($c_uid);
		$unicode = core_detect_unicode($message);
		list($ok, $to, $smslog_id, $queue) = sendsms($c_username, $sms_sender, $autoreply_scenario_result, 'text', $unicode);
		$ok = $ok[0];
	}
	return $ok;
}

?>