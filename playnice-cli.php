<?php

define("BASE_PATH", dirname(__FILE__));

include_once(BASE_PATH . "/lib/distance.php");
include_once(BASE_PATH . "/lib/class.google.php");

// Parse the arguments
switch (count($argv))
{
	case 2:
		$address = $argv[1];
		break;
	case 3:
		$latitude = $argv[1];
		$longitude = $argv[2];
		break;
	default:
		echo "Usage: php playnice-cli.php (<address> | <latitude> <longitude>)\n";
		exit(1);
}

// Generate paths to store information
$googlePasswordFile = BASE_PATH . "/google-password.txt";

// Login to Google
$google = new googleLatitude();
@include($googlePasswordFile);
while ((file_exists($googlePasswordFile) == false) || ($google->login($googleUsername, $googlePassword) == false))
{
	promptForLogin("Google", $googlePasswordFile, "google");
	@include($googlePasswordFile);
}

// Geocode the address
if (isset($address))
{
	echo "Geocoding address: '$address'\n";
	
	$json = file_get_contents("http://maps.google.com/maps/api/geocode/json?address=" . urlencode($address) . "&sensor=false");

	if ($json === false)
	{
		die("Error geocoding location\n");
	}

	$location = json_decode($json);

	if ($location->status !== "OK")
	{
		die("Error geocoding location\n");
	}

	$latitude = (string)$location->results[0]->geometry->location->lat;
	$longitude = (string)$location->results[0]->geometry->location->lng;
	
	echo "Result: latitude '$latitude' longitude '$longitude'\n";
}

// Now update Google Latitude
echo "Updating Google Latitude...";
$google->updateLatitude($latitude, $longitude, "5");

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