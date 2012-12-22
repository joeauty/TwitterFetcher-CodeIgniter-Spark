<?php
/**
 * CodeIgniter TwitterFetcher Class
 *
 * TwitterFetcher fetches Twitter data via the provided Twitter username
 *
 * By Joe Auty @ http://www.netmusician.org
 * 
 * http://getsparks.org/packages/TwitterFetcher/show
 * 
 */

class twitterfetcher {
	
	function __construct() {
		$this->CI =& get_instance();
	}
	
	function getTweets($configObj = array()) {	
	// set some defaults
		if (!isset($configObj['count'])) {
			$configObj['count'] = 1;
		}
		if (!isset($configObj['usecache'])) {
			$configObj['usecache'] = true;
		}
		if (!isset($configObj['cachefile'])) {
			$configObj['cachefile'] = "";
		}
		if (!isset($configObj['format'])) {
			$configObj['format'] = "json";
		}
		if (!isset($configObj['cacheduration'])) {
			$configObj['cacheduration'] = 5;
		}
		if (!isset($configObj['createlinks'])) {
			$configObj['createlinks'] = true;
		}
		$cachefile = ($configObj['cachefile']) ? $configObj['cachefile'] . "." . $configObj['format'] : 'twitterstatus.' . $configObj['format'];

		// throw up some errors
		if (!$configObj['twitterID']) {
			show_error('ERROR: a Twitter ID has not been provided');
		}		
		else if ($configObj['usecache'] && !is_writable(APPPATH . "cache")) {
			show_error('ERROR: Twitter cache file cannot be written to ' . APPPATH . "cache/" . $cachefile);
		}

		if ($configObj['usecache']) {
		// download new twitter status if older than five minutes

		// timestamp five minutes ago
			$cache = mktime(date('H'), date('i') - $configObj['cacheduration'], date('s'), date('m'), date('d'), date('Y'));	

			if (!file_exists(APPPATH . "cache/" . $cachefile) || !file_get_contents(APPPATH . "cache/" . $cachefile) || filemtime(APPPATH . "cache/" . $cachefile) < $cache) {
			// refresh cache
				$this->downloadTwitterStatus($configObj);
			}		

			if (file_get_contents(APPPATH . "cache/" . $cachefile)) {
				$twitterstatus = $this->formatTweets($configObj, json_decode(file_get_contents(APPPATH . "/cache/" . $cachefile)));	
				
				if ($configObj['count'] == 1) {
					if (isset($twitterstatus[0])) {
						return $twitterstatus[0];
					}
					else {
						return false;
					}
				}
				else {
					return $twitterstatus;
				}
			}	
		}
		else if (!$this->downloadTwitterStatus($configObj)) {
			return false;
		}
		else {
			$twitterstatus = $this->formatTweets($configObj, $this->downloadTwitterStatus($configObj));
						
			if ($configObj['count'] == 1) {
				if (isset($twitterstatus[0])) {
					return $twitterstatus[0];
				}
				else {
					return false;
				}
			}
			else {
				return $twitterstatus;
			}
		}	

	}
	
	function formatTweets($configObj, $twitterstatus) {
		$totaltweets = count($twitterstatus);
		for ($x=0; $x < $totaltweets; $x++) {
			$thiselapsedtime = $this->elapsedTime(strtotime($twitterstatus[$x]->created_at));
			if (isset($configObj['numdays']) && $thiselapsedtime['days'] > $configObj['numdays']) {
				unset($twitterstatus[$x]);
				continue;
			}	
			if ($configObj['createlinks']) {
				$twitterstatus[$x]->text = $this->convertToLinks($twitterstatus[$x]->text);
			}
			$twitterstatus[$x]->elapsedtime = $this->elapsedTimeString($thiselapsedtime);
		}
		return $twitterstatus;
	}

	function downloadTwitterStatus($configObj) {
	// Load the rest client spark
		$this->CI->load->spark('restclient/2.0.0');

	// Run some setup
		$this->CI->rest->initialize(array('server' => 'http://api.twitter.com/1/'));

	// Pull in an array of tweets
		if ($configObj['count']) {
			$tweeturl = 'statuses/user_timeline.' . $configObj['format'] . '?screen_name=' . $configObj['twitterID'] . '&count=' . $configObj['count'];
		}
		else {
			$tweeturl = 'statuses/user_timeline.' . $configObj['format'] . '?screen_name=' . $configObj['twitterID'];
		}
		$tweets = $this->CI->rest->get($tweeturl);

		if ($configObj['usecache'] && !isset($tweets->error)) {
			$twittercheck = json_encode($tweets);
			$cachefile = ($configObj['cachefile']) ? $configObj['cachefile'] . "." . $configObj['format'] : 'twitterstatus.' . $configObj['format'];
			if (is_array($tweets) && isset($tweets[0]) && $tweets[0]->text) {
				$fh = fopen(APPPATH . "cache/" . $cachefile, "w");
				fwrite($fh, $twittercheck);
				fclose($fh);
			}	
		}
		else if (isset($tweets->error)) {
			return false;
		}
		else {
			return $tweets;
		}
	}
	
	function convertToLinks($string) {
	// added space to beginning and end of string to capture links anchored to either end
		$string = " " . $string . " ";
	// string contained in the middle
		$string = trim(preg_replace('/\s(http|https)\:\/\/(.+?)\s/m', ' <a href = "$1://$2" target="_blank">$1://$2</a> ', $string));
		return $string;
	}
	
	function elapsedTime ( $start, $end = false) {
		$returntime = array();
		
		// set defaults
		if ($end == false) {
			$end = time();
		}

		$diff = $end - $start;
		$days = floor($diff/86400); 
		$diff = $diff - ($days*86400); 

		$hours = floor ($diff/3600); 
		$diff = $diff - ($hours*3600); 

		$mins = floor ($diff/60); 
		$diff = $diff - ($mins*60); 

		$secs = $diff;

		if ($secs > 0) {
			$returntime['secs'] = $secs;
		}
		else {
			$returntime['secs'] = 0;
		}

		if ($mins > 0) {
			$returntime['mins'] = $mins;
		}
		else {
			$returntime['mins'] = 0;
		}

		if ($hours > 0) {
			$returntime['hours'] = $hours;
		}
		else {
			$returntime['hours'] = 0;
		}

		if ($days > 0) {
			$returntime['days'] = $days;
		}
		else {
			$returntime['days'] = 0;
		}

		return $returntime;
	}
	
	function elapsedTimeString($elapsedtime) {
		if ($elapsedtime['days'] == 0) {
			if ($elapsedtime['hours'] == 0) {
					// show minutes
				return $elapsedtime['mins'] . " minute" . (($elapsedtime['mins']>1) ? "s":"") . " ago";
			}
			else {
					// show hours
				return $elapsedtime['hours'] . " hour" . (($elapsedtime['hours']>1) ? "s":"") . " ago";
			}
		}
		else {
				// show days
			return $elapsedtime['days'] . " day" . (($elapsedtime['days']>1) ? "s":"") . " ago";
		}
	}
	

}

?>