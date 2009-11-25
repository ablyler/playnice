<?php

function distance($lat1, $lon1, $lat2, $lon2, $unit = "m")
{ 
	$distance = rad2deg(acos(sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1 - $lon2)))); 

	switch ($unit)
	{
		case "m":
			return 69.0900 * $distance;
		case "n":
			return 59.9977 * $distance;
		case "k":
			return 111.189576 * $distance;
		default:
			return false;
	}
}

echo distance(32.9697, -96.80322, 29.46786, -98.53506, "m") . " miles\n";
echo distance(32.9697, -96.80322, 29.46786, -98.53506, "k") . " kilometers\n";
echo distance(32.9697, -96.80322, 29.46786, -98.53506, "n") . " nautical miles\n";

?>
