<?php

namespace GraceChurch;

class Geo {
	private $zip_base_url = "https://www.zipcodeapi.com/rest/";
	public function __construct() { }


	/**
	 * Given a $center (latitude, longitude) co-ordinates and a
	 * distance $radius (miles), returns a random point (latitude,longtitude)
	 * which is within $radius miles of $center.
	 *
	 * @param  array $center Numeric array of floats. First element is
	 *                       latitude, second is longitude.
	 * @param  float $radius The radius (in miles).
	 * @return array         Numeric array of floats (lat/lng). First
	 *                       element is latitude, second is longitude.
	 */
	public function generate_random_point($center, $radius) {
		$radius_earth = 3959; //miles

		//Pick random distance within $radius and 25% of $radius;
		$min = 0.25 * $radius;
		$distance = $min+lcg_value()*($radius-$min);

		//Convert degrees to radians.
		$center_rads = array_map('deg2rad', $center);

		//First suppose our point is the north pole.
		//Find a random point $distance miles away
		$lat_rads = (pi()/2) -  $distance/$radius_earth;
		$lng_rads = lcg_value()*2*pi();

		//($lat_rads,$lng_rads) is a point on the circle which is
		//$distance miles from the north pole. Convert to Cartesian
		$x1 = cos($lat_rads) * sin($lng_rads);
		$y1 = cos($lat_rads) * cos($lng_rads);
		$z1 = sin($lat_rads);

		//Rotate that sphere so that the north pole is now at $center.
		//Rotate in x axis by $rot = (pi()/2) - $center_rads[0];
		$rot = (pi()/2) - $center_rads[0];
		$x2 = $x1;
		$y2 = $y1 * cos($rot) + $z1 * sin($rot);
		$z2 = -$y1 * sin($rot) + $z1 * cos($rot);

		//Rotate in z axis by $rot = $center_rads[1]
		$rot = $center_rads[1];
		$x3 = $x2 * cos($rot) + $y2 * sin($rot);
		$y3 = -$x2 * sin($rot) + $y2 * cos($rot);
		$z3 = $z2;

		//Finally convert this point to polar co-ords
		$lng_rads = atan2($x3, $y3);
		$lat_rads = asin($z3);

	  return array_map('rad2deg', array($lat_rads, $lng_rads));
	 }


	 public function get_location_from_zip($zip, $type = "degrees") {
		 $curl = curl_init();
		 curl_setopt_array($curl, array(
			 CURLOPT_RETURNTRANSFER => 1,
			 CURLOPT_URL => $this->zip_base_url.ZIP_API_KEY."/"."info.json/".$zip."/".$type
		 ));
		 return json_decode(curl_exec($curl));
	 }
}

?>
