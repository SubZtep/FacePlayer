<?php
require_once(__DIR__."/init.php");

$user =  null; //facebook user uid

$facebook = new Facebook(array(
	'appId'  => $fbconfig['appid'],	
	'secret' => $fbconfig['secret']
));

$user = $facebook->getUser();
$faceplayer = new FacePlayer();
header('Content-type: text/json');

$gid = isset($_GET['gid']) ? $_GET['gid'] : false;
$time = isset($_GET['time']) ? $_GET['time'] : false;

if (! $user || ! $gid) {
	die(json_encode(array('error' => 'Parameter error')));
}

try {
	$fql = "SELECT created_time, attachment FROM stream WHERE source_id = $gid AND created_time < $time ";
	$param  =   array(
		'method'    => 'fql.query',
		'query'     => $fql,
		'callback'  => ''
	);
	$fqlResult   =   $facebook->api($param);
}
catch(Exception $o){
	var_dump($o);
	die(json_encode(array('error' => 'Get feed error')));
}

$musics = $faceplayer->getMusics($fqlResult);

//TODO: ha nincs zene menni tovabb ameddig van, vagy pedig vege.

echo json_encode($musics);
die();
