<?php

class FacePlayer {

	var $curl;

	function __construct() {
		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_HEADER, false);
		curl_setopt($this->curl, CURLOPT_VERBOSE, false);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($this->curl, CURLOPT_HEADER, false);
		curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 10);
	}

	function __destruct() {
		curl_close($this->curl);
	}

	function getMusics($posts) {
		$musics = array();
		foreach ($posts as $post) {
			if (isset($post['attachment']['media'][0]['video']['display_url'])) {
				if (strpos($post['attachment']['media'][0]['video']['display_url'], 'youtube.com') !== false) {
					list(, $ytid) = explode('v=', $post['attachment']['media'][0]['video']['display_url']);
					list($ytid) = explode('&', $ytid);
					//die(print_r($post['attachment']['media'][0]));
					$title = $this->get_youtube_title_by_id($ytid);
					if ($ytid)
					$musics[] = array(
							'source' => 'youtube',
							'id' => $ytid,
							'time' => $post['created_time'],
							'title' => $title //$post['attachment']['media'][0]['alt']
					);
				}
			} else {
				if (strpos($post['message'], '//youtu.be/') !== false) {
					$words = explode(' ', $post['message']);
					foreach ($words as $word) {
						if (strpos($word, '//youtu.be/') !== false) {
							$word = substr($word, strlen('//youtu.be/') + strpos($word, '//youtu.be/'));
							$word = explode('/', $word);
							$ytid = $word[0];
							list($ytid) = explode('&', $ytid);
							$title = $this->get_youtube_title_by_id($ytid);
							if ($title != '') {
								$musics[] = array(
										'source' => 'youtube',
										'id' => $ytid,
										'time' => $post['created_time'],
										'title' => $title
								);
							}
						}
					}
				} else if (strpos($post['message'], 'youtube.com/') !== false) { //FIXME
					$words = explode(' ', $post['message']);
					foreach ($words as $word) {
						if (strpos($word, 'youtube.com/') !== false && strpos($word, 'v=') !== false) {
							$word = substr($word, strpos($word, 'v=') + 2);
							if (strpos($word, '&') !== false) {
								$word = substr($word, 0, strpos($word, '&'));
							}
							if (strpos($word, '/') !== false) {
								$word = substr($word, 0, strpos($word, '/'));
							}
							list($word) = explode('&', $word);
							$title = $this->get_youtube_title_by_id($word);
							if ($title != '') {
								$musics[] = array(
										'source' => 'youtube',
										'id' => $word,
										'time' => $post['created_time'],
										'title' => $title
								);
							}
						}
					}
				}
			}
		}
		return $musics;
	}

	function get_youtube_title_by_id($ytid) {
		//curl_setopt($this->curl, CURLOPT_URL, 'http://gdata.youtube.com/feeds/api/videos/'.$ytid.'?v=2');
		curl_setopt($this->curl, CURLOPT_URL, 'https://www.googleapis.com/youtube/v3/videos?id='.$ytid.'&key=AIzaSyDmVZChzoo9FypMKHBXZz8e8ksmP1WKkYU%20&part=snippet');
		$page = curl_exec($this->curl);
		$error = curl_errno($this->curl);
		if ($error != CURLE_OK || empty($page)) {
			return false;
		}
		// get title
		/*$dom = new DOMDocument();
		$dom->loadHTML($page);
		$titles = $dom->getElementsByTagName('title');
	
		$title = '';
		for ($i = 0; $i < $titles->length; $i++) {
			$title = trim($titles->item($i)->nodeValue);
			if ($title != '') {
				break;
			}
		}
		return $title != '' ? $title : false;*/
		$page = json_decode($page, true);//die(print_r($page));
		if (isset($page['items'][0]['snippet']['title'])) {
			return $page['items'][0]['snippet']['title'];
		}
		return false;
	}

	function getTrackData($title) {
		global $lastfm_key;

		if (strpos($title, ' - ') !== false) {
			list($artist, $track) = explode(' - ', $title, 2);
		} else if (strpos($title, ' : ') !== false) {
			list($artist, $track) = explode(' : ', $title, 2);
		} else {
			return false;
		}
		$artist = trim($artist);
		$track = trim($track);
		$image = '';
		$inspected = 0;

		curl_setopt($this->curl, CURLOPT_URL, "http://ws.audioscrobbler.com/2.0/?method=track.getInfo&api_key={$lastfm_key}&format=json&autocorrect=1&artist=".urlencode($artist)."&track=".urlencode($track));
		$data = curl_exec($this->curl);
		$error = curl_errno($this->curl);
		if ($error != CURLE_OK || empty($data)) {
			return false;
		}
		$data = json_decode($data, true);
		if (isset($data['track'])) {
			$data = $data['track'];
			if (isset($data['name'])) {
				$track = $data['name'];
			}
			if (isset($data['artist']['name'])) {
				$artist = $data['artist']['name'];
			}
			if (isset($data['album']['image'])) {
				$image = $data['album']['image'][count($data['album']['image']) - 1]['#text'];
				$inspected = 1;
			}
		}
		if ($image == '') {
			curl_setopt($this->curl, CURLOPT_URL, "http://ws.audioscrobbler.com/2.0/?method=artist.getInfo&api_key={$lastfm_key}&format=json&autocorrect=1&artist=".urlencode($artist));
			$data = curl_exec($this->curl);
			$error = curl_errno($this->curl);
			if ($error != CURLE_OK || empty($data)) {
				return false;
			}
			$data = json_decode($data, true);
			if (isset($data['artist'])) {
				$data = $data['artist'];
				if (isset($data['name'])) {
					$artist = $data['name'];
				}
				if (isset($data['image'])) {
					$image = $data['image'][count($data['image']) - 1]['#text'];
				}
			}
		}

		return array('artist' => $artist, 'track' => $track, 'image' => $image, 'inspected' => $inspected);
	}

	function post_to_facebook($sid) {
		global $fbconfig;
		$url = 'https://graph.facebook.com/me/faceplayer-org:listen'.
				'?access_token='.$_SESSION['fb_'.$fbconfig['appid'].'_access_token'].
				'&song=http://faceplayer.org/'.$sid.'.mp3&method=post';
		curl_setopt($this->curl, CURLOPT_URL, $url);
		$page = curl_exec($this->curl);
		die($page);
		$error = curl_errno($this->curl);
		if ($error != CURLE_OK || empty($page)) {
			return false;
		} else {
			return true;
		}
	}

}
