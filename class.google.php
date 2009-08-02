<?php

// A class to login to iGoogle, save a session cookie, scrape
// out the iGoogle security token, and update Latitude with a given
// location.
//
// Nat Friedman <nat@nat.org>
// Jack Catchpoole <jack@catchpoole.com>
//
// MIT license.
//

class iGoogle
{
    private $cookieFile = "./google-cookie.txt"; // Where we store the Google session cookie
    private $latitudeToken = null; // The Google latitude security token

    // Where to login ?
    private $loginUrl="https://www.google.com/accounts/ServiceLoginAuth";

    // What page do we scrape the latitude security token from?
    private $targetPage="https://www.google.com/ig";

    // What URL do we use to proxy the Latitude update request?
    private $latitudeProxyUrl = "http://lfkq9vbe9u4sg98ip8rfvf00l7atcn3d.ig.ig.gmodules.com/gadgets/makeRequest";

    // What URL do we use to update Latitude?
    private $latitudeUpdateUrlPrefix = "http://www.google.com/glm/mmap/ig?t=ul&";

    public function __construct()
    {
    }

    public function updateLatitude($lat, $lng, $accuracy)
    {
	$this->getLatitudeToken ();

	$ig = curl_init();

	$post_data  = "OAUTH_SERVICE_NAME=google&";
	$post_data .= "authz=oauth&";
	$post_data .= "httpMethod=GET&";
	$post_data .= "st=" . urlencode($this->latitudeToken) . "&";
	$post_data .= "url=" . urlencode($this->latitudeUpdateUrlPrefix . "lat=$lat&lng=$lng&accuracy=$accuracy");

	curl_setopt($ig, CURLOPT_URL, $this->latitudeProxyUrl);
	curl_setopt($ig, CURLOPT_COOKIEFILE, $this->cookieFile);   // Where to read cookie info from
	curl_setopt($ig, CURLOPT_COOKIEJAR, $this->cookieFile);    // Where to save next cookie info
	curl_setopt($ig, CURLOPT_RETURNTRANSFER, TRUE);      // Don't output results of transfer, instead send as return val

	//curl_setopt($ig, CURLOPT_VERBOSE, TRUE);           // Verbose output for debugging
	//curl_setopt($ig, CURLOPT_HEADER, TRUE);            // Include headers in output, for debugging

	curl_setopt($ig, CURLOPT_POST, TRUE);                 // We're going to be POSTing
	curl_setopt($ig, CURLOPT_POSTFIELDS, $post_data);     // Send our login data

	$junk = curl_exec ($ig);
    }

    public function getLatitudeToken ()
    {
	$ig = curl_init();

	// Now we're logged in, grab the /ig page
	curl_setopt($ig, CURLOPT_URL, $this->targetPage);
	curl_setopt($ig, CURLOPT_COOKIEFILE, $this->cookieFile);   // Where to read cookie info from
	curl_setopt($ig, CURLOPT_COOKIEJAR, $this->cookieFile);    // Where to save next cookie info
	curl_setopt($ig, CURLOPT_RETURNTRANSFER, TRUE);       // Don't output results of transfer, instead send as return val

	// Execute the curl call
	$output=curl_exec($ig);

	// Display retreived output
	// echo str_pad(" $this->targetPage Content Follows ",72,"-",STR_PAD_BOTH) . "\n\n";
	// echo $output . "\n\n";

	// If "Sign out" does not appear in output, login must have failed
	if (strpos($output,"Sign out")===FALSE) {
	    echo "It looks like log in to Google failed\n";
	}

	curl_close ($ig);

	// --------------------------------------------------------------------
	// Now analyse the output, and pull out the required Google Latitude data
	//
	// Latitude must be a gadget on the page;  Gadgets and tabs are defined
	// in a block of JS starting with "_IG_MD_Generate" which AFAICT is unique on
	// the page.

	// First grab the list of tabs and gadgets, bail out if nothing found
	if (! preg_match("/_IG_MD_Generate(.+?)<\/script>/",$output,$tabs_and_gadgets)) {
	    die ("No gadgets identified on iGoogle home page\n");
	}

	// Now all gadgets are in $tabs_and_gadgets[1], examine them.
	/*
	 * For me, the format here looks something like this :
	 *
	 * ... some stuff ...
	 * dt: [0, 1, 2, 3],
	 * m: [
	 * {... gadget 1 stuff ...}
	 * {... gadget 2 stuff ...}
	 * {... gadget 3 stuff ...
	 * view: {...max_u: "..."}
	 * }
	 * ...etc...
	 * ]
	 * });
	 *
	 */

	// First strip of the list of tabs, so we have just gadgets
	if (! preg_match("/dt:\[.+?\],m:\[(.+)]/",$tabs_and_gadgets[1],$gadgets)) {
	    die("Couldn't parse out individual gadget variables\n");
	}

	// Now seperate out each gadget block
	if (! preg_match_all("/{(.+?)}/",$gadgets[1],$gadget_blocks)) {
	    die("Couldn't separate gadget variable blocks\n");
	}

	// Now loop through each individual gadget and look for the Latitude gadget,
	// identified by ti:"Google Latitude"
	foreach ($gadget_blocks[1] as $var) {

	    if (strstr($var,"ti:\"Google Latitude\"")) {
		// This is the one we want.  Pull out the max_u var
		preg_match("/max_u:\"(.+?)\"/",$var,$url);
		$cleaned_url=str_replace("\\x26","&",$url[1]);
		parse_str($cleaned_url,$params);

		// echo "Parameters of the max_u: URL for the Google Latitude gadget are : \n";
		// print_r($params);

		$st=str_replace("core:core.io:core.iglegacy#","",$params["libs"]);
	    }
	}

	if (!empty($st)) {
	    $this->latitudeToken = str_replace("st=", "", $st);
	} else {
	    die ("Error: The Google Latitude security token could not be found.\nGoogle probably broke the screen scraper.\n");
	}

	echo "\n";
    }

    // Login to google and save the cookie in $cookieFile
    public function login($username, $password)
    {
	$ig = curl_init();
	$post_data  = "continue=http://www.google.com/ig";
	$post_data .= "&followup=http://www.google.com/ig";
	$post_data .= "&service=ig";
	$post_data .= "&Email=$username";
	$post_data .= "&Passwd=$password";
	$post_data .= "&submit=Sign in";
	curl_setopt($ig, CURLOPT_URL, $this->loginUrl);

	//curl_setopt($ig, CURLOPT_VERBOSE, TRUE);           // Verbose output for debugging
	//curl_setopt($ig, CURLOPT_HEADER, TRUE);            // Include headers in output, for debugging

	curl_setopt($ig, CURLOPT_FOLLOWLOCATION, TRUE);       // Follow any Location: headers
	curl_setopt($ig, CURLOPT_POST, TRUE);                 // We're going to be POSTing
	curl_setopt($ig, CURLOPT_POSTFIELDS, $post_data);     // Send our login data
	curl_setopt($ig, CURLOPT_COOKIEJAR, $this->cookieFile);    // Where to save cookie info for next time
	curl_setopt($ig, CURLOPT_RETURNTRANSFER, TRUE);       // Don't output results of transfer, instead send as return val

	// Execute the curl call
	$junk=curl_exec($ig);
    }

    public function haveCookie()
    {
	return file_exists($this->cookieFile);
    }
}



