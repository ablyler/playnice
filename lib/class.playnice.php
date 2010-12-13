<?php

class playnice
{
	// number of seconds to wait before running
	public $waitSeconds = 0;

	private $mobileMe = null;
	private $google = null;
	public $device = null;

	private $logFile;
	private $statusFile;
	private $status;

	// device location
	public $location = null;

	public function __construct($statusFile, $logFile, $forceRun = false)
	{
		// Set properties
		$this->logFile = $logFile;
		$this->statusFile = $statusFile;

		// Check the status to see if we should wait to run again
		if (file_exists($this->statusFile))
		{
			// obtain the raw data from the status file
			if ( ($data = file_get_contents($this->statusFile)) === false)
				die("Error obtaining status from '$this->statusFile'");

			// convert the serialized data into an array
			$this->status = unserialize($data);

			// calculate the delay multiplier
			$delay_multiplier = ($this->status["count"] > POLLS_BEFORE_MAX ? POLLS_BEFORE_MAX : $this->status["count"]);

			// wait at least the minimal ammount of time
			$this->waitSeconds = $this->status["last_updated"] + (MIN_INTERVAL * 60);

			// add additional wait time for each time the device did not move
			$this->waitSeconds += ((MAX_INTERVAL * 60) - (MIN_INTERVAL * 60)) * ($delay_multiplier / POLLS_BEFORE_MAX);
		}
		else
		{
			// create status array
			$this->status = array("count" => 0);
		}
	}

	public function googleLogin($googlePasswordFile)
	{
		// Login to Google
		$this->google = new googleLatitude();
		@include($googlePasswordFile);
		while ((file_exists($googlePasswordFile) == false) || ($this->google->login($googleUsername, $googlePassword) == false))
		{
			$this->promptForLogin("Google", $googlePasswordFile, "google");
			@include($googlePasswordFile);
		}
	}

	public function mobilemeLogin($mobileMePasswordFile)
	{
		// Login to MobileMe
		$prompt = false;
		do
		{
			if ($prompt || file_exists($mobileMePasswordFile) == false)
			{
				$this->promptForLogin("MobileMe", $mobileMePasswordFile, "mobileMe");
			}

			@include($mobileMePasswordFile);

			try
			{
				$this->mobileMe = new Sosumi($mobileMeUsername, $mobileMePassword);
			}
			catch (Exception $exception)
			{
				$prompt = true;
			}
		} while ($prompt === true);
	}

	public function locateDevice($deviceNum = 0)
	{
		// Get the iPhone location from MobileMe
		echo "Fetching iPhone location...";

		if ($this->mobileMe->devices[$deviceNum] === null)
		{
		    echo "Could not find device number $deviceNum within your account.\n";
		    exit();
		}

		// Locate the device
		try
		{
			$this->mobileMe->locate($deviceNum);
		}
	    catch (Exception $exception)
		{
			echo "Error obtaining location: " . $exception->getMessage() . "\n";
			exit();
		}

		// Update device reference
		$this->device = &$this->mobileMe->devices[$deviceNum];

		if ($this->device->locationOld)
		{
			echo "Device's location is stale, NOT updating Google Latitude\n";
			exit();
		}

		echo "got it.\n";
		echo "iPhone location: " . $this->device->latitude . ", " . $this->device->longitude . " as of: " . $this->device->locationTimestamp . "\n";

		// Log the location
		file_put_contents($this->logFile, $this->device->locationTimestamp . ": " . $this->device->latitude . ", " . $this->device->longitude . ", " .  $this->device->horizontalAccuracy . "\n", FILE_APPEND);

		// Calculate how far the device has moved (if we know the pervious location)
		if ((isset($this->status["lat"])) && (isset($this->status["lon"])) && (isset($this->status["accuracy"])))
		{
			$distance = $this->distance($this->status["lat"], $this->status["lon"], $this->status["accuracy"], $this->device->latitude, $this->device->longitude, $this->device->horizontalAccuracy);
			echo "Device has moved: $distance km\n";

			// Update the count by either increasing it if the device has not moved
			// or resetting it to zero if the device has moved
			$this->status["count"] = ($distance == 0 ? $this->status["count"] + 1 : 0);
		}

		// Now update Google Latitude
		echo "Updating Google Latitude...";
		$this->updateLocation($this->device->latitude, $this->device->longitude, $this->device->horizontalAccuracy);

		// Update status
		$this->status["last_updated"] = time();
		$this->status["lat"] = $this->device->latitude;
		$this->status["lon"] = $this->device->longitude;
		$this->status["accuracy"] = $this->device->horizontalAccuracy;

		file_put_contents($this->statusFile, serialize($this->status));
	}

	public function updateLocation($latitude, $longitude, $accuracy)
	{
		$this->google->updateLatitude($latitude, $longitude, $accuracy);
	}

	public function distance($lat1, $lon1, $accuracy1, $lat2, $lon2, $accuracy2)
	{ 
		// Obtain the distance in km
		$distance = 111.189576 * rad2deg(acos(sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1 - $lon2))));

		// Decrease the distance by the accuracy in meters
		$distance -= ($accuracy1 + $accuracy2) * 0.001;

		return ($distance > 0 ? $distance : 0);
	}
	
	public function promptForLogin($serviceName, $passwordFile, $variablePrefix)
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

		if (empty($username))
			die("Error: No username specified.\n");

		echo "$serviceName password: ";
		system ('stty -echo');
		$password = trim(fgets(STDIN));
		system ('stty echo');
		// add a new line since the users CR didn't echo
		echo "\n";

		if (empty ($password))
			die ("Error: No password specified.\n");

		if (!file_put_contents($passwordFile, "<?php\n\$" . $variablePrefix . "Username=\"$username\";\n\$" . $variablePrefix . "Password=\"$password\";\n?>\n"))
		{
			echo "Unable to save $serviceName credentials to $passwordFile, please check permissions.\n";
			exit;
		}

		// change the permissions of the password file
		chmod($passwordFile, 0600);
	}
} 

