<?php

define("BASE_PATH", dirname(__FILE__));

include_once(BASE_PATH . "/lib/class.playnice.php");
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
$statusFile = BASE_PATH . "/status.txt";
$logFile = BASE_PATH . "/log.txt";

$playnice = new playnice(null, $logFile, true);
$playnice->googleLogin(BASE_PATH . "/google-password.txt");

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
$playnice->updateLocation($latitude, $longitude, "5");

// All done.
echo "Done!\n";
