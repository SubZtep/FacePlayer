<?php
require_once(__DIR__."/init.php");

$source = mysql_real_escape_string(trim(@$_POST['source']));
$source_id = mysql_real_escape_string(trim(@$_POST['source_id']));
$title = trim(@$_POST['title']);

if (empty($source) || empty($source_id) || empty($title)) {
	die("<tt>&nbsp;&nbsp;.--.<br>&nbsp;&nbsp;'&nbsp;&nbsp;&nbsp;'&nbsp;:<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;/<br>&nbsp;&nbsp;&nbsp;&nbsp;/</tt>");	
}

$track_id = false;
$need_update = false;

$fp = new FacePlayer();


// Get track
$res = mysql_query("SELECT id, title, inspected, artist, track FROM tracks WHERE source = '$source' AND source_id = '$source_id' LIMIT 1") or die(mysql_error());
if (mysql_num_rows($res) > 0) {
	$row = mysql_fetch_assoc($res);
	$track_id = $row['id'];
	if ($row['inspected'] == 0 && ($title != $row['title'])) {
		$need_update = true;
	} else if ($row['inspected'] == 1) {
		$artist = $row['artist'];
		$track = $row['track'];
	}
}

// Get track data From Last.fm
if ($track_id === false || $need_update) {
	$data = $fp->getTrackData($title);
	if ($data !== false ) {
		$artist = mysql_real_escape_string($data['artist']);
		$track = mysql_real_escape_string($data['track']);
		$image = mysql_real_escape_string($data['image']);
		$inspected = mysql_real_escape_string($data['inspected']);
		$need_update = true;
	} else {
		$artist = '';
		$track = '';
		$image = '';
		$inspected = '0';
	}
	if ($track_id === false || $neet_update) {
		if ($track_id !== false) {
			mysql_query("UPDATE tracks SET artist = '$artist', track = '$track', image = '$image', inspected = '$inspected' WHERE track_id = $track_id") or die(mysql_error());
		} else {
			$title = mysql_real_escape_string($title);
			mysql_query("INSERT INTO tracks (source, source_id, title, artist, track, image, inspected) VALUES ('$source', '$source_id', '$title', '$artist', '$track', '$image', '$inspected')") or die(mysql_error());
			$track_id = mysql_insert_id();
		}
	}
}

// Insert listen
if (isset($_SESSION['gid']) && ! empty($_SESSION['gid']) && isset($_SESSION['uid']) && ! empty($_SESSION['uid'])) {
	$gid = mysql_real_escape_string($_SESSION['gid']);
	$uid = mysql_real_escape_string($_SESSION['uid']);
	$res = mysql_query("INSERT INTO listens (user_id, group_id, track_id, created) VALUES ('$uid', '$gid', $track_id, NOW())") or die(mysql_error());
}

// Send to facebook
//if ($artist != '' && $track != '') {
if (@$track_id) {
	$fp->post_to_facebook($track_id);
	// post to facebook
	
}





