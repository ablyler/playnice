<?php

function distance($lat1, $lon1, $accuracy1, $lat2, $lon2, $accuracy2)
{ 
	// Obtain the distance in km
	$distance = 111.189576 * rad2deg(acos(sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1 - $lon2))));
	
	// Decrease the distance by the accuracy in meters
	$distance -= ($accuracy1 + $accuracy2) * 0.001;

	return ($distance > 0 ? $distance : 0);
}

?>