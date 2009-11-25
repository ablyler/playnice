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

define("BASE_PATH", dirname(__FILE__));

include_once(BASE_PATH . "/lib/class.google.php");
include_once(BASE_PATH . "/lib/sosumi/class.sosumi.php");

$googlePasswordFile = BASE_PATH . "/google-password.txt";
$mobileMePasswordFile = BASE_PATH . "/mobile-me-password.txt";

$google = new googleLatitude();

// Login to Google
@include($googlePasswordFile);
while ((file_exists($googlePasswordFile) == false) || ($google->login($googleUsername, $googlePassword) == false))
{	
    promptForLogin("Google", $googlePasswordFile, "google");
    @include($googlePasswordFile);
}

// Login to MobileMe
do
{
	if (file_exists($mobileMePasswordFile) == false)
	{
		promptForLogin("MobileMe", $mobileMePasswordFile, "mobileMe");
	}

    @include($mobileMePasswordFile);

	$mobileMe = new Sosumi($mobileMeUsername, $mobileMePassword);
	
	if ($mobileMe->authenticated == false)
	{
		unlink($mobileMePasswordFile);
	}
} while ($mobileMe->authenticated == false);

// Get the iPhone location from MobileMe
echo "Fetching iPhone location...";

if (count ($mobileMe->devices) == 0) {
    echo "No iPhones found in your MobileMe account.\n";
    exit;
}

$iphoneLocation = $mobileMe->locate();
echo "got it.\n";

echo "iPhone location: $iphoneLocation->latitude, $iphoneLocation->longitude\n";


// Now update Google Latitude
echo "Updating Google Latitude...";
$google->updateLatitude($iphoneLocation->latitude, $iphoneLocation->longitude, $iphoneLocation->accuracy);

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