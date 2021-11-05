<?php

$fbconfig = array();
$fbconfig['appid' ]     = "0000";
$fbconfig['secret']     = "0000";
$fbconfig['baseurl']    = "http://faceplayer.org/index.php";

$lastfm_key = '0000';


//header('P3P: CP="CAO PSA OUR"');
session_start();

$conn = mysql_pconnect('localhost', 'u', '0000');
mysql_select_db('faceplayer', $conn);
mysql_set_charset('utf8', $conn);

// autologin
if (isset($_COOKIE['uh']) && ! isset($_SESSION['fb_'.$fbconfig['appid'].'_user_id'])) {
	$uh = mysql_real_escape_string($_COOKIE['uh']);
	$res = mysql_query("SELECT id, code, access_token, user_id FROM users WHERE md5(CONCAT_WS('tropax', id, user_id)) = '$uh' AND code != ''");
	if (mysql_num_rows($res) == 1) {
		$row = mysql_fetch_assoc($res);
		//echo 'JUHUU'; print_r($row);
		//echo 'autologged';
		$users_id = $row['id'];
		$_SESSION['fb_'.$fbconfig['appid'].'_code'] = $row['code'];
		$_SESSION['fb_'.$fbconfig['appid'].'_access_token'] = $row['access_token'];
		$_SESSION['fb_'.$fbconfig['appid'].'_user_id'] = $row['user_id'];
	}
}


require_once(__DIR__."/facebook/facebook.php");
require_once(__DIR__."/faceplayer.class.php");
