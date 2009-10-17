<?php

// A class to login to Google Latitude Mobile, save a session cookie
// and update Latitude with a given location.
//
// Nat Friedman <nat@nat.org>
// Jack Catchpoole <jack@catchpoole.com>
// Andy Blyler <ajb@blyler.cc>
//
// MIT license.
//

class googleLatitude
{
	private $cookieFile; // Where we store the Google session cookie
	private $lastURL;    // The previous URL as visited by curl

	public function __construct()
	{
		$this->cookieFile = dirname(__FILE__) . "/google-cookie.txt";
	}

	// Update the location on google latitude
	public function updateLatitude($lat, $lng, $accuracy)
	{
		/* build the post data */
		$post_data  = "t=ul&mwmct=iphone&mwmcv=5.8&mwmdt=iphone&mwmdv=30102&auto=true&nr=180000&";
		$post_data .= "cts=" . time() . "000&lat=$lat&lng=$lng&accuracy=$accuracy";

		/* set the needed header */
		$header = array("X-ManualHeader: true");

		/* execute the location update */
		$this->curlPost("http://maps.google.com/glm/mmap/mwmfr?hl=en", $post_data, $this->lastURL, $header);
	}

	/* obtain listing of friends and their location */
	public function friendList()
	{
		/* create the friend output array */
		$friends = array();

		/* build the post data */
		$post_data  = "t=fs&mwmct=iphone&mwmcv=5.8&mwmdt=iphone&mwmdv=30102&gpsc=false";

		/* set the needed header */
		$header = array("X-ManualHeader: true");

		/* execute the http request */
		$response = $this->curlPost("http://maps.google.com/glm/mmap/mwmfr?hl=en", $post_data, $this->lastURL, $header);

		/* parse out the friends from the response */
		if (preg_match_all('/,\[,\[,"-?\d+",3,1,1,,0\]\n,"(?<email>[^"]+)","(?<name>[^"]+)",(?<phone>[^,]*),(?<lat>-?\d+),(?<lon>-?\d+),"(?<timestamp>\d{10})\d{3}",(?<accuracy>\d*),\["(?<address>[^"]*)","(?<city_state>[^"]*)"]/', $response, $matches, PREG_SET_ORDER))
		{
			/* create friendly output array */
			foreach ($matches as $match)
			{
				$friends[] = array(
						"name"			=> $match["name"],
						"email"			=> $match["email"],
						"phone"			=> $match["phone"],
						"lat"			=> $match["lat"],
						"lon"			=> $match["lon"],
						"accuracy"		=> $match["accuracy"],
						"timestamp"		=> $match["timestamp"],
						"address"		=> $match["address"],
						"city_state"	=> $match["city_state"],
					);
			}
		}
		
		return $friends;
	}

	// Login to google and save the cookie in $cookieFile
	public function login($username, $password)
	{
		/* obtain needed cookies from the mobile latitude site */
		$html = $this->curlGet("http://maps.google.com/maps/m?mode=latitude");

		/* obtain login form and cookies */
		$html = $this->curlGet("https://www.google.com/accounts/ServiceLogin?service=friendview&hl=en&nui=1&continue=http://maps.google.com/maps/m%3Fmode%3Dlatitude", $this->lastURL);

		/* parse out the hidden fields */
		preg_match_all('!hidden.*?name=["\'](.*?)["\'].*?value=["\'](.*?)["\']!ms', $html, $hidden);

		/* build post data */
		$post_data = '';
		for($i = 0; $i < count($hidden[1]); $i++)
		{
			$post_data .= $hidden[1][$i] . '=' . urlencode($hidden[2][$i]) . '&';
		}

		$post_data .= "signIn=Sign+in&PersistentCookie=yes";
		$post_data .= "&Email=$username";
		$post_data .= "&Passwd=$password";

		/* execute the login */
		$html = $this->curlPost("https://www.google.com/accounts/ServiceLoginAuth?service=friendview", $post_data, $this->lastURL);

		/* verify the login was successful */
		if (strpos ($html, "Sign in") != FALSE)
		{
			unlink($this->cookieFile);
			die ("\nGoogle login failed. Did you mistype something?\n");
		}

		/* reset the permissions of the cookie file */
		chmod($this->cookieFile, 0600);
	}

	public function haveCookie()
	{
		return file_exists($this->cookieFile);
	}

	private function curlGet($url, $referer = null, $headers = null)
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (iPhone; U; CPU iPhone OS 3_1_2 like Mac OS X; en-us) AppleWebKit/528.18 (KHTML, like Gecko) Version/4.0 Mobile/7D11 Safari/528.16");
		if(!is_null($referer)) curl_setopt($ch, CURLOPT_REFERER, $referer);
		if(!is_null($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($ch, CURLOPT_HEADER, true);
		// curl_setopt($ch, CURLOPT_VERBOSE, true);

		$html = curl_exec($ch);

		if (curl_errno($ch) != 0)
		{
			die("\nError during GET of '$url': " . curl_error($ch) . "\n");
		}

		$this->lastURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

		return $html;
	}

	private function curlPost($url, $post_vars = null, $referer = null, $headers = null)
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (iPhone; U; CPU iPhone OS 3_1_2 like Mac OS X; en-us) AppleWebKit/528.18 (KHTML, like Gecko) Version/4.0 Mobile/7D11 Safari/528.16");
		if(!is_null($referer)) curl_setopt($ch, CURLOPT_REFERER, $referer);
		curl_setopt($ch, CURLOPT_POST, true);
		if(!is_null($post_vars)) curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vars);
		if(!is_null($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($ch, CURLOPT_HEADER, true);
		// curl_setopt($ch, CURLOPT_VERBOSE, true);

		$html = curl_exec($ch);

		if (curl_errno($ch) != 0)
		{
			die("\nError during POST of '$url': " . curl_error($ch) . "\n");
		}

		$this->lastURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

		return $html;
	}
}
