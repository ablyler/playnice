<?php

//
// A little script to scrape your iPhone's location from MobileMe
// and update Google Latitude with your iPhone's current position.
//
// Uses sosumi from http://github.com/tylerhall/sosumi/tree/master and
// some google scraping code from Jack Catchpoole <jack@catchpoole.com>.
//
// Original Author: Nat Friedman <nat@nat.org>
// Maintainer: Andy Blyler <andy@blyler.cc>
//
// MIT license.
//

define("MIN_INTERVAL", 10); // minimal interval in minutes
define("MAX_INTERVAL", 90); // maximum interval in minutes
define("POLLS_BEFORE_MAX", 5); // take this many polls to reach the max polling interval

define("BASE_PATH", dirname(__FILE__));

include_once(BASE_PATH . "/lib/class.google.php");
include_once(BASE_PATH . "/lib/class.playnice.php");
include_once(BASE_PATH . "/lib/sosumi/class.sosumi.scraper.php");

// Generate paths to store information
$statusFile = BASE_PATH . "/status.txt";
$logFile = BASE_PATH . "/log.txt";

$playnice = new playnice($statusFile, $logFile);

// check to see if we should wait to poll the device
if ($playnice->waitSeconds > time())
{
	echo "Waiting for " . ($playnice->waitSeconds - time()) . " more seconds\n";
	exit();
}

// Login to Google and MobileMe
$playnice->googleLogin(BASE_PATH . "/google-password.txt");
$playnice->mobilemeLogin(BASE_PATH . "/mobile-me-password.txt");

// Locate the device
$playnice->locateDevice();

// All done.
echo "Done!\n";
