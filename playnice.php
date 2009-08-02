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

include 'class.google.php';
include 'class.sosumi.php';

$mobileMePasswordFile = "./mobile-me-password.txt";

$google = new iGoogle();

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

if (! file_exists ($mobileMePasswordFile)) {
    echo "No MobileMe username/password data found. You will need to type your\n";
    echo "MobileMe username/password. We will save them in $mobileMePasswordFile\n";
    echo "so you don't have to type it again. If you're not cool with this, you\n";
    echo "you probably want to delete that file later.\n\n";

    list($mobileMeUsername, $mobileMePassword) = promptForLogin("MobileMe");

    $f = fopen ($mobileMePasswordFile, "w");
    fwrite ($f, "<?php\n\$mobileMeUsername=\"$mobileMeUsername\";\n\$mobileMePassword=\"$mobileMePassword\";\n?>\n");
    fclose ($f);

    echo "\n";

} else {
    @include($mobileMePasswordFile);
}

if (! $google->haveCookie()) {
    echo "No Google cookie found. You will need to authenticate with your\n";
    echo "Google username/password. You should only need to do this once;\n";
    echo "we will save the session cookie for the future.\n\n";
    echo "Please note that you need to have the Latitude widget on your main\n";
    echo "iGoogle page for this to work.\n\n";

    list($username, $password) = promptForLogin("Google");

    echo "Acquiring Google session cookie...";
    $google->login($username, $password);
    echo "got it.\n";
}

// Get the iPhone location from MobileMe
echo "Fetching iPhone location...";
$mobileMe = new Sosumi ($mobileMeUsername, $mobileMePassword);
$iphoneLocation = $mobileMe->locate();
echo "got it.\n";

echo "iPhone location: $iphoneLocation->latitude, $iphoneLocation->longitude\n";

// Now update Google Latitude
echo "Updating Google Latitude...";
$google->updateLatitude($iphoneLocation->latitude, $iphoneLocation->longitude,
			$iphoneLocation->accuracy);

// All done.
echo "Done!\n";
