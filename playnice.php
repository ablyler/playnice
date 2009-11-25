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

if (! file_exists ($googlePasswordFile)) {
    echo "You will need to type your Google Latitude username/password. They will be\n";
    echo "saved in $googlePasswordFile so you don't have to type them again.\n";
    echo "If you're not cool with this, you probably want to delete that file\n";
    echo "at some point (they are stored in plaintext).\n\n";
    echo "You do need a working Google Latitude account for playnice to work.\n\n";

    list($googleUsername, $googlePassword) = promptForLogin("Google");

	// save the credentials
	if (!file_put_contents($googlePasswordFile, "<?php\n\$googleUsername=\"$googleUsername\";\n\$googlePassword=\"$googlePassword\";\n?>\n")) {
		echo "Unable to save Google credentials to $googlePasswordFile, please check permissions.\n";
		exit;
	}

	// change the permissions of the password file
    chmod($googlePasswordFile, 0600);

    echo "\n";

} else {
    @include($googlePasswordFile);
}


if (! file_exists ($mobileMePasswordFile)) {
    echo "You will need to type your MobileMe username/password. They will be\n";
    echo "saved in $mobileMePasswordFile so you don't have to type them again.\n";
    echo "If you're not cool with this, you probably want to delete that file\n";
    echo "at some point (they are stored in plaintext).\n\n";
    echo "You do need a working MobileMe account for playnice to work, and you\n";
    echo "need to have enabled the Find My iPhone feature on your phone.\n\n";

    list($mobileMeUsername, $mobileMePassword) = promptForLogin("MobileMe");

	// save the credentials
	if (!file_put_contents($mobileMePasswordFile, "<?php\n\$mobileMeUsername=\"$mobileMeUsername\";\n\$mobileMePassword=\"$mobileMePassword\";\n?>\n")) {
		echo "Unable to save MobileMe credentials to $mobileMePasswordFile, please check permissions.\n";
		exit;
	}

	// change the permissions of the password file
    chmod($mobileMePasswordFile, 0600);

    echo "\n";

} else {
    @include($mobileMePasswordFile);
}

// Get the iPhone location from MobileMe
echo "Fetching iPhone location...";

$mobileMe = new Sosumi ($mobileMeUsername, $mobileMePassword);
if (! $mobileMe->authenticated) {
    echo "Unable to authenticate to MobileMe. Is your password correct?\n";
    exit;
}

if (count ($mobileMe->devices) == 0) {
    echo "No iPhones found in your MobileMe account.\n";
    exit;
}

$iphoneLocation = $mobileMe->locate();
echo "got it.\n";

echo "iPhone location: $iphoneLocation->latitude, $iphoneLocation->longitude\n";


// Now update Google Latitude
if (! $google->login($googleUsername, $googlePassword)) {
	echo "Unable to authenticate to Google. Is your password correct?\n";
    exit;
}

echo "Updating Google Latitude...";
$google->updateLatitude($iphoneLocation->latitude, $iphoneLocation->longitude, $iphoneLocation->accuracy);

// All done.
echo "Done!\n";



function promptForLogin($serviceName)
{
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

    return array ($username, $password);
}