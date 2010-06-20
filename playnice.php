<?php

//
// A little script to scrape your iPhone's location from MobileMe
// and update Google Latitude with your iPhone's current position.
//
// Uses sosumi from http://github.com/tylerhall/sosumi/tree/master and
// some google scraping code from Jack Catchpoole <jack@catchpoole.com>.
//
// Nat Friedman <nat@nat.org>
//
// August 22nd, 2009
//
// MIT license.
//

define("MIN_INTERVAL", 10); // minimal interval in minutes
define("MAX_INTERVAL", 90); // maximum interval in minutes
define("POLLS_BEFORE_MAX", 5); // take this many polls to reach the max polling interval

define("BASE_PATH", dirname(__FILE__));

include_once(BASE_PATH . "/lib/distance.php");
include_once(BASE_PATH . "/lib/class.google.php");
include_once(BASE_PATH . "/lib/sosumi/class.sosumi.php");

// Generate paths to store information
$statusFile = BASE_PATH . "/status.txt";
$googlePasswordFile = BASE_PATH . "/google-password.txt";
$mobileMePasswordFile = BASE_PATH . "/mobile-me-password.txt";
$logFile = BASE_PATH . "/log.txt";

// Check the status to see if we should wait to run again
if (file_exists($statusFile))
{
	$data = file_get_contents($statusFile);
	if ($data == false) die("Error obtaining status from '$statusFile'");

	$status = unserialize($data);

	// calculate the delay multiplier
	$delay_multiplier = ($status["count"] > POLLS_BEFORE_MAX ? POLLS_BEFORE_MAX : $status["count"]);

	// wait the minimal ammount of time
	$wait_until = $status["last_updated"] + (MIN_INTERVAL * 60);

	// add additional wait time for each time the device did not move
	$wait_until += ((MAX_INTERVAL * 60) - (MIN_INTERVAL * 60)) * ($delay_multiplier / POLLS_BEFORE_MAX);

	// check to see if we should wait to poll the device
	if ($wait_until > time())
	{
		echo "Waiting for " . ($wait_until - time()) . " more seconds\n";
		exit();
	}
}
else
{
	// create status array
	$status = array("count" => 0);
}

// Login to Google
$google = new googleLatitude();
@include($googlePasswordFile);
while ((file_exists($googlePasswordFile) == false) || ($google->login($googleUsername, $googlePassword) == false))
{
	promptForLogin("Google", $googlePasswordFile, "google");
	@include($googlePasswordFile);
}

// Login to MobileMe
$prompt = false;
do
{
	if ($prompt || file_exists($mobileMePasswordFile) == false)
	{
		promptForLogin("MobileMe", $mobileMePasswordFile, "mobileMe");
	}

	@include($mobileMePasswordFile);

	try
	{
		$mobileMe = new Sosumi($mobileMeUsername, $mobileMePassword);
	}
	catch (Exception $exception)
	{
		$prompt = true;
	}
} while ($prompt === true);

// Get the iPhone location from MobileMe
echo "Fetching iPhone location...";

if (count ($mobileMe->devices) == 0) {
    echo "No iPhones found in your MobileMe account.\n";
    exit;
}


$time = time();
$try = 0;
do
{
    $try++;

	// Backoff if this is not our first attempt
    if ($try > 1) sleep($try * 10);

	// Locate the device
    $iphoneLocation = $mobileMe->locate();

	// Verify we got a location back
    if ((empty($iphoneLocation["latitude"])) || (empty($iphoneLocation["longitude"])))
    {
        echo "Error obtaining location\n";
        exit();
    }

	// Strip off microtime from unix timestamp
    $timestamp = substr($iphoneLocation["timestamp"], 0, 11);

    if ($timestamp == false)
    {
        echo "Error parsing last update time from MobileMe\n";
        exit();
    }

} while (($timestamp < ($time - (60 * 2))) && ($try <= 6));

echo "got it.\n";
echo "iPhone location: " . $iphoneLocation["latitude"] . ", " . $iphoneLocation["longitude"] . " as of: " . date("Y-m-d G:i:s T") . "\n";

// Log the location
file_put_contents($logFile, date("Y-m-d G:i:s T", $timestamp) . ": $iphoneLocation{'latitude'}, $iphoneLocation{'longitude'}, $iphoneLocation{'accuracy'}\n", FILE_APPEND);

// Calculate how far the device has moved (if we know the pervious location)
if ((isset($status["lat"])) && (isset($status["lon"])) && (isset($status["accuracy"])))
{
	$distance = distance($status["lat"], $status["lon"], $status["accuracy"], $iphoneLocation["latitude"], $iphoneLocation["longitude"], $iphoneLocation["accuracy"]);
	echo "Device has moved: $distance km\n";

	// Update the count by either increasing it if the device has not moved
	// or resetting it to zero if the device has moved
	if ($distance == 0)
		$status["count"]++;
	else
		$status["count"] = 0;
}

// Now update Google Latitude
echo "Updating Google Latitude...";
$google->updateLatitude($iphoneLocation["latitude"], $iphoneLocation["longitude"], $iphoneLocation["accuracy"]);

// Update status
$status["last_updated"] = time();
$status["lat"] = $iphoneLocation["latitude"];
$status["lon"] = $iphoneLocation["longitude"];
$status["accuracy"] = $iphoneLocation["accuracy"];

file_put_contents($statusFile, serialize($status));

// All done.
echo "Done!\n";



function promptForLogin($serviceName, $passwordFile, $variablePrefix)
{
	echo "\n";
    echo "You will need to type your $serviceName username/password. Because this\n";
    echo "is the first time you are running this script, or because authentication\n";
    echo "has failed.\n\n";
    echo "NOTE: They will be saved in $passwordFile so you don't have to type them again.\n";
    echo "If you're not cool with this, you probably want to delete that file\n";
    echo "at some point (they are stored in plaintext).\n\n";

    echo "$serviceName username: ";
    $username = trim(fgets(STDIN));

    if (empty($username)) {
		die("Error: No username specified.\n");
    }

    echo "$serviceName password: ";
    system ('stty -echo');
    $password = trim(fgets(STDIN));
    system ('stty echo');
    // add a new line since the users CR didn't echo
    echo "\n";

    if (empty ($password)) {
		die ("Error: No password specified.\n");
    }

	if (!file_put_contents($passwordFile, "<?php\n\$" . $variablePrefix . "Username=\"$username\";\n\$" . $variablePrefix . "Password=\"$password\";\n?>\n")) {
		echo "Unable to save $serviceName credentials to $passwordFile, please check permissions.\n";
		exit;
	}

    // change the permissions of the password file
	chmod($passwordFile, 0600);
}