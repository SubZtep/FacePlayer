<?php
require_once(__DIR__."/init.php");

if (isset($_GET['logout'])) {
	setcookie("PHPSESSID", "", time() - 3600, '/');
	setcookie("uh", "", time() - 3600, '/');
	$_SESSION = array();
	header('Location: index.php');
	session_destroy();
	die();
}

$facebook = new Facebook(array(
	'appId'  => $fbconfig['appid'],
	'secret' => $fbconfig['secret']
));
$user = $facebook->getUser(); //facebook user uid
$faceplayer = new FacePlayer();

$track = false;


if (isset($_GET['tid'])) {
	$tid = mysql_real_escape_string($_GET['tid']);
	$res = mysql_query("SELECT id, source, source_id, title, image FROM tracks WHERE id = '$tid'") or die(mysql_error());
	if (mysql_num_rows($res) > 0) {
		$track = mysql_fetch_assoc($res);
	} else {
		header('Location: index.php');
		die();
	}

} else if (! isset($_GET['tid']) && $user) {

	// User auth
	if (isset($_GET['state']) && isset($_GET['code'])) {
		// back from facebook, reg or update
		$code = mysql_real_escape_string($_SESSION['fb_'.$fbconfig['appid'].'_code']);
		$access_token = mysql_real_escape_string($_SESSION['fb_'.$fbconfig['appid'].'_access_token']);
		$user_id = mysql_real_escape_string($_SESSION['fb_'.$fbconfig['appid'].'_user_id']);
		$res = mysql_query("SELECT id, code, access_token FROM users WHERE user_id = '$user_id'");

		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_assoc($res);
			$users_id = $row['id'];
			//TODO: sessionbol kivenni az access_token-t talan, protected elkerni
			if ($row['code'] != $code || $row['access_token'] != $access_token) {
				mysql_query("UPDATE users SET code = '$code', access_token = '$access_token' WHERE id = $users_id");
			}
		} else {
			mysql_query("INSERT INTO users (code, access_token, user_id, created) VALUES ('$code', '$access_token', '$user_id', NOW())");
			$users_id = mysql_insert_id();
		}
		$hash = md5($users_id . 'tropax' . $user_id);
		$_SESSION['uid'] = $users_id;
		setcookie('uh', $hash, time() + (86400 * 365));
		header('Location: index.php');
		die();
	}

	try {
		$groups = $facebook->api("/$user/groups");
	} catch(Exception $o){
		// facebook error, probably access denied
		if (isset($users_id) && ! empty($users_id)) {
			$users_id = mysql_real_escape_string($users_id);
			mysql_query("UPDATE users SET code = '', access_token = '' WHERE id = '$users_id'");
			header('index.php?logout');
		}
		die();
	}

	if (! isset($groups['data']) || empty($groups['data'])) {
		die('Join to a group XP');
	}


	// Get Group ID
	$res_groups = mysql_query("SELECT id, name FROM groups WHERE inspected = 1 ORDER BY name LIMIT 1000") or die(mysql_error());	
	if (isset($_GET['gid'])) {
		$gid = $_GET['gid'];
	} else if (isset($_SESSION['gid']) && ! empty($_SESSION['gid'])) {
		$gid = $_SESSION['gid'];
	} else if (mysql_num_rows($res_groups) > 0) {
		$row = mysql_fetch_assoc($res_groups);
		$gid = $row['id'];
		mysql_data_seek($res_groups, 0);
	} else {
		$gid = 0;
	}
	$_SESSION['gid'] = $gid;
	$gid = mysql_real_escape_string($gid);

	// Get User's Groups from Facebook
	try {
		$fql = "select name, description, pic from group where gid = $gid";
		$param  =   array(
			'method'   => 'fql.multiquery',
			'queries'  => '{"group":  "SELECT name, description, pic, privacy FROM group WHERE gid = '.$gid.'",
						    "stream": "SELECT created_time, attachment, message, action_links, app_data, description, type, filter_key, attribution, impressions, comments, permalink FROM stream WHERE source_id = '.$gid.'"}',
			'callback' => ''
		);
		$fqlResult = $facebook->api($param);
	} catch(Exception $o){
		var_dump($o);
		die('Get group error');
	}
	$group = $fqlResult[0]['fql_result_set'][0];
	$posts = $fqlResult[1]['fql_result_set'];
	$res = mysql_query("SELECT name FROM groups WHERE id = '$gid'") or die(mysql_error());
	if (mysql_num_rows($res) == 0) {
		if ($group['privacy'] == 'OPEN') {
			$name = mysql_real_escape_string($group['name']);
			mysql_query("INSERT INTO groups (id, name) VALUES ('$gid', '$name')") or die(mysql_error());
		}
	} else {
		if ($group['privacy'] != 'OPEN') {
			mysql_query("DELETE FROM groups WHERE id = '$gid'") or die(mysql_error());
		}
	}
	unset($fqlResult);
}

?>
<!DOCTYPE html>
<html lang="en">
  <head<?php if ($track) { echo ' prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# faceplayer-org: http://ogp.me/ns/fb/faceplayer-org#"'; } ?>>
 	<?php if ($track) { ?>
	  <meta property="fb:app_id" content="159316557523352" />
	  <meta property="og:type"   content="faceplayer-org:song" />
	  <meta property="og:url"    content="http://faceplayer.org/<?php echo $track['id']; ?>.mp3" />
	  <meta property="og:title"  content="<?php echo $track['title']; ?>" />
	  <meta property="og:image"  content="<?php echo $track['image'] != '' ? $track['image'] : 'https://fbcdn-photos-a.akamaihd.net/photos-ak-snc7/v85005/152/159316557523352/app_1_159316557523352_1230554656.gif'; ?>" />
 	<?php } ?>
    <meta charset="utf-8">
    <title><?php if (isset($group)) { echo htmlspecialchars($group['name']).' - '; } ?>facePlayer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="@SubZtep">

    <!-- Le styles -->
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
      body {
        padding-top: 60px; /* 60px to make the container go all the way to the bottom of the topbar */
      }
      a { color: #d4d2dd; }
      .brand { font-family: 'Russo One', cursive; color: #eee !important; }
      .hero-unit, .hero-unit p { color: #333; }
      :-moz-any-link:focus { outline: none; }
      body { background: #2F2B3A url('images/gradient_transparent.png') repeat-x; color: #d4d2dd; }
      .well { background-color: #4c4b58; }
      .nav-list > li > a, .nav-list .nav-header { text-shadow: 0 1px 0 #000; }
      .nav-list > li > a:hover { color: #353440; text-shadow: 0 1px 0 #fff; }
      .nav > li > a:hover { color: #353440; background-color: #e6e6e6; border-radius: 3px; }
      .nav-list .active > a, .nav-list .active > a:hover { background-color: #68676f; border-radius: 3px; }
      .nav-pills .active > a, .nav-pills .active > a:hover { background-color: #4c4b58; }
      /*
      a { color: #f5f5f5; }
      .brand { font-family: 'Russo One', cursive; color: #eee !important; }
      .hero-unit, .hero-unit p { color: #333; }
      :-moz-any-link:focus { outline: none; }
      body { background-color: #d0ebca; color: #d4d2dd; }
      .well { background-color: #5575ac; }
      .nav-list > li > a, .nav-list .nav-header { text-shadow: 0 1px 0 #000; }
      .nav-list > li > a:hover { color: #f5f5f5; text-shadow: 0 1px 0 #000; }
      .nav > li > a:hover { color: #f5f5f5; background-color: #96afd8; border-radius: 3px; }
      .nav-list .active > a, .nav-list .active > a:hover { background-color: #58974a; border-radius: 3px; }
      .nav-pills .active > a, .nav-pills .active > a:hover { background-color: #5575ac; }
      .nav-pills a { color: #58974a; }
	*/
    </style>
    <!--link href="bootstrap/css/bootstrap-responsive.css" rel="stylesheet"-->

    <!-- Le HTML5 shim, for IE6-8 support of HTML5 elements -->
    <!--[if lt IE 9]>
      <script src="//html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->

    <!-- Le fav and touch icons -->
    <link rel="shortcut icon" href="images/logo.gif">

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
	<link href='http://fonts.googleapis.com/css?family=Russo+One' rel='stylesheet' type='text/css'>

	<script type="text/javascript">
//ha ezt hallgatod, atjar a plussenergia!//
	  var _gaq = _gaq || [];
	  _gaq.push(['_setAccount', 'UA-6394241-14']);
	  _gaq.push(['_trackPageview']);
	  (function() {
		var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
		ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
		var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
	  })();
	</script>
  </head>

  <body>

    <div class="navbar navbar-fixed-top">
      <div class="navbar-inner">
        <div class="container">
        
          <img src="images/faceplayer_top.png" style="height: 24px;" />
        
          <a class="brand" href="index.php"><?php echo isset($group) ? htmlspecialchars($group['name']) : 'Facebook Group Player' ?></a>
          <?php if ($user) { ?>
          <p class="navbar-text pull-right">Logged in. <a href="index.php?logout">Logout.</a></p>
          <?php } ?>
        </div>
      </div>
    </div>

    <div class="container">

<?php
if ($track) {

	  //
	 // Track Page
	//
	$tid = mysql_real_escape_string($_GET['tid']);
	$res = mysql_query("SELECT source, source_id, title, image FROM tracks WHERE id = '$tid'") or die(mysql_error());
	if (mysql_num_rows($res) > 0) {
		$row = mysql_fetch_assoc($res);
		?>
		<script type="text/javascript" src="swfobject/swfobject.js"></script>
		<div class="hero-unit" style="text-align: center;">
		  <h1><?php echo $row['title']; ?></h1>
		  	<?php if ($row['source'] == 'youtube') { ?>
		  	<div style="margin: 15px 0; height: 310px;">
				<div id="youtube_player">You need Flash player 8+ and JavaScript enabled to view this video.</div>
				<script type="text/javascript">
				$(function() {
					swfobject.embedSWF("http://www.youtube.com/v/<?php echo $row['source_id']; ?>?enablejsapi=1&playerapiid=ytplayer&version=3&showinfo=0", "youtube_player", "370", "310", "8", null, null, { allowScriptAccess: "always", wmode: "opaque" }, { id: "myytplayer" });
					var ytplayer = document.getElementById("myytplayer");
					if (ytplayer) {
						ytplayer.playVideo();
					}
				});
				</script>
			</div>
		  	<?php } ?>
			<p align="center">
				<a href="/" class="btn btn-large btn-primary">Click here for facePlayer</a>
			</p>
			<?php if (! empty($row['image'])) { ?>
				<br /><img src="<?php echo $row['image']; ?>" />
			<?php } ?>
		</div>
		<?php
	} else {
		?>
		<div class="hero-unit">
		  <h1>404 error</h1>
		 	<p>Track not found</p>
			<p align="center">
				<a href="/" class="btn btn-large btn-primary">Click here for facePlayer</a>
			</p>
		</div>
		<?php
	}

} else if (! $user) {

	  //
	 // Login Page
	//
	$loginUrl = $facebook->getLoginUrl(
		array(
			'scope'        => 'user_groups,read_stream,publish_actions',
			'redirect_uri' => $fbconfig['baseurl']
		)
	);

	if (isset($_GET['gid'])) {
		$_SESSION['gid'] = $_GET['gid'];
	}
	?>
	<div class="hero-unit">
	  <h1>Welcome</h1>
	 	<p>1. Do you take part of <strong>FB groups</strong> that have some fresh, stunning <strong>music posts</strong> (youtube<!--, soundcloud etc.-->..) on their walls?</p>
		<p>2. Are you tired / busy / too cool to search for them in the jungle of FB every time you need to reward your ears?</p>
		<p>3. Solution is right here: <strong>FACEPLAYER converts your FB groups into dynamic playlists.</strong> They are visible, they are organized. Way more relaxing like this, isn´t it? You can use it as a playlist, but this is actually an interactive playlist, selected by groupmembers. </p>
		<p>4. Enter the new dimension of real time music sharing. Sign in and listen to your special FB sound galaxy.</p>
	</div>
	<p align="center">
		<a href="<?=$loginUrl?>"><img src="facebook_login_button.png" alt="Log in using your Facebook account" /></a>
	</p>
	<?php

} else {

	  //
	 // Player Page
	//
	?>

	<script type="text/javascript" src="swfobject/swfobject.js"></script>

	<div class="row">
		<div class="span4">
			<div style="margin-bottom: 15px; height: 310px;">
				<div id="youtube_player">You need Flash player 8+ and JavaScript enabled to view this video.</div>
			</div>
			<p style="text-align: justify;"><?=htmlspecialchars($group['description'])?></p>
			<?php /*if ($group['pic'] != '') { ?>
			<img src="<?=$group['pic']?>" alt="" style="padding: 0 1px 1px 0; border-width: 0 1px 1px 0; border-style: solid; border-color: #4C4B58;" />
			<?php }*/ ?>
		</div>
		<div class="span4">
	

			<div class="well">
				<ul class="nav nav-list playlist">
					<?php
					$musics = $faceplayer->getMusics($posts);

					foreach ($musics as $music) {
						echo "<li><a href=\"javascript:void(0);\" rel=\"{$music['source']}.{$music['id']}.{$music['time']}\">{$music['title']}</a></li>\n";
					}
					?>
				</ul>

				<?php if (count($musics) == 0) { ?>
					YouTube?
				<?php } else { ?>
					<button class="btn btn-mini" id="load_more" style="margin: 4px 0 0 8px;">Load more</button>
					<div id="load_more_spinner" style="display: none; margin: 6px 0 0 18px;"><img src="ajax-loader.gif" alt="Loading" /></div>
				<?php } ?>
			</div>
			

			<script type="text/javascript">
				var youtube_player = false;
				var has_more = true;
				var ytplayer = false;
				var listen_interval = false;

				function load_youtube(id) {
					if (! ytplayer) {
						swfobject.embedSWF("http://www.youtube.com/v/"+id+"?enablejsapi=1&playerapiid=ytplayer&version=3&showinfo=0", "youtube_player", "370", "310", "8", null, null, { allowScriptAccess: "always", wmode: "opaque" }, { id: "myytplayer" });						
					} else {
						ytplayer.loadVideoById(id);
					}
				}

				function onYouTubePlayerReady(playerId) {
					ytplayer = document.getElementById("myytplayer");
					if (ytplayer) {
						ytplayer.addEventListener("onStateChange", "onytplayerStateChange");
						<?php if (isset($_GET['gid'])) { ?>
							//ytplayer.playVideo();
						<?php } ?>
					}
				}

				function onytplayerStateChange(newState) {
					if (newState == 0) {
						var curr = $('.playlist li.active');
						curr.removeClass('active');
						var yid = curr.next().children('a').attr('rel');
						if (yid != 'undefined') {
							load_music(yid);
							if (curr.next().nextAll('li').length < 5) {
								load_more();
							}
						}
					}
				}

				function load_more() {
					if (has_more) {
						$('#load_more').hide();
						$('#load_more_spinner').show();
						$.get('load_more.php', { gid: '<?=$gid?>', time: $('.playlist li:last a').attr('rel').split('.')[2] }, function(data) {
							var html = '';
							$.each(data, function(idx, item) {
								html += '<li><a href="javascript:void(0);" rel="'+item.source+'.'+item.id+'.'+item.time+'">'+item.title+'</a></li>';
							});
							$('.playlist').append(html);
							$('#load_more_spinner').hide();
							if (data.length > 0) {
								$('#load_more').show();
							} else {
								has_more = false;
							}
						}, 'json');
					}
				}

				function load_music(rel) {
					var data = rel.split('.');
					$('.playlist li.active').removeClass('active');
					if (data[0] == 'youtube') {
						load_youtube(data[1]);
					}
					$('a[rel="'+rel+'"]').parent('li').addClass('active');
					if (listen_interval) {
						clearInterval(listen_interval);
					}
					listen_interval = setInterval("set_listen('"+data[0]+"', '"+data[1]+"', '"+$('a[rel="'+rel+'"]').text().replace(/\'/g, "\\'")+"')", 1000);
				}

				function set_listen(source, source_id, title) {
					if (source == 'youtube') {
						if (ytplayer.getPlayerState() == 1 && ytplayer.getCurrentTime() > 10) {
							clearInterval(listen_interval);
							listen_interval = false;
							console.log(source+', '+source_id+', '+title);
							$.post('listen.php', { source: source, source_id: source_id, title: title }, function(data) {
								if ($.trim(data) != '') {
									console.log(data);
								}
							});
						}
					}
				}

				$(function() {
					$('.playlist').on('click', 'li a', function() {
						load_music($(this).attr('rel'));
						if ($(this).parent('li').nextAll('li').length < 5) {
							load_more();
						}
					});

					$('#load_more').click(function() {
						load_more();
					});

					if ($('.playlist li').children().length > 0) {
						load_music($('.playlist li:first a').attr('rel'));
					}
				});

			</script>

		</div>
		<div class="span4">
			<?php
			$is_my_group = false;
			foreach ($groups['data'] as $g) {
				if ($g['id'] == $gid) {
					$is_my_group = true;
					break;
				}
			}
			?>
			<ul class="nav nav-tabs">
				<li<?php if ($is_my_group) { echo ' class="active"'; } ?>><a href="#tab1" data-toggle="tab">My Groups</a></li>
				<li<?php if (! $is_my_group) { echo ' class="active"'; } ?>><a href="#tab2" data-toggle="tab">Open Groups</a></li>
			</ul>
			<div class="tab-content" style="padding-bottom: 9px; border-bottom: 1px solid #ddd;">
				<div class="tab-pane<?php if ($is_my_group) { echo ' active'; } ?>" id="tab1">
					<ul class="nav nav-pills nav-stacked">
					<?php
					foreach ($groups['data'] as $g) {
						echo "<li".($g['id'] == $gid ? ' class="active"' : '')."><a href=\"?gid={$g['id']}\">".htmlspecialchars($g['name'])."</a></li>\n";
					}
					?>
					</ul>
				</div>
				<div class="tab-pane<?php if (! $is_my_group) { echo ' active'; } ?>" id="tab2">
					<ul class="nav nav-pills nav-stacked">
					<?php
					while ($row = mysql_fetch_assoc($res_groups)) {
						echo "<li".($row['id'] == $gid ? ' class="active"' : '')."><a href=\"?gid={$row['id']}\">".htmlspecialchars($row['name'])."</a></li>\n";
					}
					?>
					</ul>
				</div>
			</div>
		</div>
	</div>
 

	<?php
}
?>

      <footer>
        <p>&copy; facePlayer 2012</p>
      </footer>
    </div>
    <script src="bootstrap/js/bootstrap.min.js"></script>
  </body>
</html>

