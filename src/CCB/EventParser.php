<?php

namespace GraceChurch\CCB;

use GraceChurch\DatabaseHandler;
use GraceChurch\AssetManager;

class EventParser {
	private $db;
	private $am;

	public function __construct() {
		$this->db = new DatabaseHandler("grace");
		$this->am = new AssetManager();
	}

	public function getAllEventsInRange($StartTime, $EndTime, $Campus = '1', $sort = false, $Grouping = null, $Public = null) {
		$allEvents = array();
		$query = "SELECT * FROM `ccb_events` WHERE `StartTime` < '".$EndTime->format("Y-m-d H:i:s")."' AND `AbsoluteEnd` > '".$StartTime->format("Y-m-d H:i:s")."'";

		if ($Grouping != null && $Grouping != "") {
			$query .= " AND ";
			if (is_array($Grouping)) {
				foreach ($Grouping as $key => $value) {
					if ($key == (count($Grouping) - 1)) $query .= "`Grouping` = '$value'";
					else $query .= "`Grouping` = '$value' OR ";
				}
			} else {
				$query .= "`Grouping` = '$Grouping'";
			}
		}
		if ($Public) $query .= " AND `PublicCalendarListed` = '1'";

		$result = $this->db->getRecords($query);

		foreach ($result as $ev) {
			$group = $this->db->getOneRecord("SELECT
				`ccb_groups`.`ID`,
				`ccb_groups`.`Name`,
				`ccb_groups`.`Inactive`,
				`ccb_groups`.`Campus`,
				`ccb_groups`.`GroupType`,
				`ccb_groups`.`Department`,
				`ccb_campuses`.`Name` AS `CampusName`,
				`ccb_lookup_group_type`.`name` AS `TypeName`,
				`ccb_lookup_group_grouping`.`name` AS `DepartmentName`
				FROM `ccb_groups`
				JOIN `ccb_campuses` ON `ccb_groups`.`Campus` = `ccb_campuses`.`ID`
				JOIN `ccb_lookup_group_type` ON `ccb_groups`.`GroupType` = `ccb_lookup_group_type`.`id`
				JOIN `ccb_lookup_group_grouping` ON `ccb_groups`.`Department` = `ccb_lookup_group_grouping`.`id`
				WHERE `ccb_groups`.`ID` = '".$ev['GroupID']."'");
			if ($group['Inactive'] == '0' && ($Campus == null || $Campus == false || $group['Campus'] == $Campus)) {
				$newEvent = new CalendarEvent();
				$newEvent->ID = $ev['ID'];
				$newEvent->Image = ($ev['Image'] != "") ? $this->am->getAccessKey($ev['Image']) : "";
				$newEvent->StartTime = date_create_from_format("Y-m-d H:i:s",$ev['StartTime']);
				$newEvent->EndTime = date_create_from_format("Y-m-d H:i:s",$ev['EndTime']);
				//$newEvent->setupStart = date_create_from_format("Y-m-d H:i:s",$ev['SetupStart']);
				//$newEvent->setupEnd = date_create_from_format("Y-m-d H:i:s",$ev['SetupEnd']);
				$newEvent->Occurrence = date_create_from_format("Y-m-d H:i:s", $ev['StartTime']);
				$newEvent->Recurrence = $ev['Recurrence'];
				$newEvent->Resources = $ev['Resources'];
				$newEvent->Name = $ev['Name'];
				$newEvent->Campus = $group['Campus'];
				$newEvent->CampusName = $group['CampusName'];
				$newEvent->Group = $group['ID'];
				$newEvent->GroupName = $group['Name'];
				$newEvent->GroupType = $group['TypeName'];
				$newEvent->GroupTypeID = $group['GroupType'];
				$newEvent->Department = $group['DepartmentName'];
				$newEvent->DepartmentID = $group['Department'];
				$newEvent->Grouping = $ev['Grouping'];
				$newEvent->Exceptions = json_decode($ev['Exceptions'], true);
				$newEvent->Description = $ev['Description'];
				$newEvent->Organizer = $ev['Organizer'];
				$newEvent->Location = (object)["Name"=>$ev['LocationName'],"StreetAddress"=>$ev['StreetAddress'],"City"=>$ev['City'],"State"=>$ev['State'],"Zip"=>$ev['Zip']];
				$newEvent->Tags = $ev['Tags'];

				$array = $this->findAllOccurances($StartTime, $EndTime, $newEvent, $ev['Recurrence'], date_create_from_format("Y-m-d H:i:s", $ev['AbsoluteEnd']));

				$allEvents = array_merge($allEvents, $array);
			}
		}

		if ($sort || $sort == true || $sort == "Alphabetical") usort($allEvents, array($this, "sortAlphabetically"));
		if ($sort && $sort == "StartTime") usort($allEvents, array($this, "sortByStartTime"));
		return $allEvents;
	}


	public function getEventsByUser($uid, $StartTime, $EndTime, $sort = true) {
		$allEvents = array();
		$groups = $this->db->getRecords("SELECT GroupID FROM ccb_group_participants WHERE Individual = '$uid'");
		$query = "SELECT * FROM `ccb_events` WHERE StartTime < '".$EndTime->format("Y-m-d H:i:s")."' AND AbsoluteEnd > '".$StartTime->format("Y-m-d H:i:s")."'";

		if ($groups != null && count($groups) > 0) {
			$query .= " AND (";
			foreach ($groups as $key => $value) {
				if ($key == (count($groups) - 1)) $query .= "GroupID = '".$value['GroupID']."'";
				else $query .= "GroupID = '".$value['GroupID']."' OR ";
			}
			$query .= ")";
		}

		$result = $this->db->getRecords($query);
		foreach ($result as $ev) {
			$group = $this->db->getOneRecord("SELECT * FROM ccb_groups WHERE ID = '".$ev['GroupID']."'");
			if ($group['Inactive'] == '0' && $group['InteractionType'] != 'Administrative') {
				$newEvent = new CalendarEvent();
				$newEvent->ID = $ev['ID'];
				$newEvent->StartTime = date_create_from_format("Y-m-d H:i:s",$ev['StartTime']);
				$newEvent->EndTime = date_create_from_format("Y-m-d H:i:s",$ev['EndTime']);
				//$newEvent->setupStart = date_create_from_format("Y-m-d H:i:s",$ev['SetupStart']);
				//$newEvent->setupEnd = date_create_from_format("Y-m-d H:i:s",$ev['SetupEnd']);
				$newEvent->Occurrence = date_create_from_format("Y-m-d H:i:s", $ev['StartTime']);
				$newEvent->Resources = $ev['Resources'];
				$newEvent->Name = $ev['Name'];
				$newEvent->Group = $ev['GroupID'];
				$newEvent->Grouping = $ev['Grouping'];
				$newEvent->Exceptions = json_decode($ev['Exceptions'], true);
				$newEvent->Description = $ev['Description'];
				$newEvent->Organizer = $ev['Organizer'];

				$array = $this->findAllOccurances($StartTime, $EndTime, $newEvent, $ev['Recurrence'], date_create_from_format("Y-m-d H:i:s", $ev['AbsoluteEnd']));
				$allEvents = array_merge($allEvents, $array);
			}
		}
		if($sort) usort($allEvents, array($this, "sortByStartTime"));
		return $allEvents;
	}

	public function getTodaysEvents($Campus = '1', $sort = "Alphabetical") {
		$StartTime = date_create_from_format("Y-m-d H:i:s", date("Y-m-d")." 00:00:00");
		$EndTime = date_create_from_format("Y-m-d H:i:s", date("Y-m-d")." 23:59:59");
		$allEvents = $this->getAllEventsInRange($StartTime, $EndTime, $Campus, $sort);
		foreach ($allEvents as $key => $event) {
			if ($event->EndTime->format("U") < time()) {
				unset($allEvents[$key]);
			}
		}
		return array_values($allEvents);
	}

	public function getTodaysEventsByGrouping($Grouping, $Campus = '1', $sort = "Alphabetical") {
		$StartTime = date_create_from_format("Y-m-d H:i:s", date("Y-m-d")." 00:00:00");
		$EndTime = date_create_from_format("Y-m-d H:i:s", date("Y-m-d")." 23:59:59");
		$allEvents = $this->getAllEventsInRange($StartTime, $EndTime, $Campus, $sort, $Grouping);
		return array_values($allEvents);
	}

	public function getActiveEventsByGrouping($Grouping, $Campus = '1', $setup = false, $sort = "Alphabetical") {
		$StartTime = date_create_from_format("Y-m-d H:i:s", date("Y-m-d")." 00:00:00");
		$EndTime = date_create_from_format("Y-m-d H:i:s", date("Y-m-d")." 23:59:59");
		$allEvents = $this->getAllEventsInRange($StartTime, $EndTime, $Campus, $sort, $Grouping);
		foreach ($allEvents as $key => $event) {
			if ($event->EndTime->format("U") < time()) {
				unset($allEvents[$key]);
			}
		}
		return array_values($allEvents);
	}


	public function findAllOccurances($StartTime, $EndTime, $event, $recurrance, $absoluteEnd) {
		$eventList = array();
		if($event->StartTime <= $EndTime && $event->EndTime >= $StartTime && !$this->isException($event->StartTime, $event)){
			array_push($eventList, $event);
		}
		$eventList = array_merge($eventList, $this->CalculateDailyRecurrancesInRange($StartTime, $EndTime, $event, $recurrance, $absoluteEnd));
		$eventList = array_merge($eventList, $this->CalculateWeeklyRecurrancesInRange($StartTime, $EndTime, $event, $recurrance, $absoluteEnd));
		$eventList = array_merge($eventList, $this->CalculateMonthlyRecurrancesInRange($StartTime, $EndTime, $event, $recurrance, $absoluteEnd));
		$exceptionArray = $event->Exceptions;
		return $eventList;
	}

	private function isException($StartTime, $event) {
		if (is_array($event->Exceptions)) {
			return in_array($StartTime->format("Y-m-d"),$event->Exceptions);
		} else {
			return false;
		}
	}

	/*********************** Sort functions ************************/
	private static function sortAlphabetically($a,$b) {
		$string = strcasecmp($a->Name, $b->Name);
		if ($string == 0) $return = $a->StartTime > $b->StartTime;
		else $return = $string;
		return $return;
	}
	private static function sortByStartTime($a,$b) {
		return $a->StartTime > $b->StartTime;
	}

	/*********************** Calculate daily recurrances in range ************************/
	private function calculateDailyRecurrancesInRange($StartTime, $EndTime, $event, $recurrance, $absoluteEnd) {
		$ret = array();

		if(strpos($recurrance,"Every day") === false || $EndTime < $event->StartTime || $absoluteEnd < $StartTime)
			return $ret;

		$tmp = clone $StartTime;

		if($tmp < $event->StartTime)
			$tmp = clone $event->StartTime;

		//Don't create a new occurrence on the start date of the event
		if($tmp->format("Y-m-d") == $event->StartTime->format("Y-m-d"))
			$tmp->add(date_interval_create_from_date_string('1 day'));

		while ($tmp <= $EndTime){
			$newEvent = clone $event;
			$newEvent->StartTime = date_create_from_format("Y-m-d H:i:s", $tmp->format("Y-m-d")." ".$event->StartTime->format("H:i:s"));
			$newEvent->EndTime = date_create_from_format("Y-m-d H:i:s", $tmp->format("Y-m-d")." ".$event->EndTime->format("H:i:s"));
			if($newEvent->StartTime > $absoluteEnd && !$this->isException($newEvent->StartTime,$event))
				return $ret;

			array_push($ret, $newEvent);

			$tmp->add(date_interval_create_from_date_string('1 day'));
		}
		return $ret;
	}

	/*********************** Calculate weekly recurrances in range ************************/
	private function calculateWeeklyRecurrancesInRange($StartTime, $EndTime, $event, $recurrance, $absoluteEnd) {
		$ret = array();
		$matches = array();
		$days_in_week = 7;
		$calc_start_week = true;

		if (preg_match("/^Every ([2-4])?( )?week(s)?/", $recurrance, $matches) && $EndTime > $event->StartTime && $absoluteEnd > $StartTime) {
			$multiplier = (isset($matches[1])) ? (int)$matches[1] : 1;

			$searchDate1 = clone $StartTime;
			$searchDate2;

			for($i = 0; $i < 7; $i++) {
				if($searchDate1 > $EndTime)
					break;
				if(strpos($recurrance,$searchDate1->format("l")) !== false) {
					if ($searchDate1 < $event->StartTime) { // Check to see if range start is before start of event. If so, clone start of event instead of range start to prevent unnecessary cycles.
						$searchDate2 = clone $event->StartTime;
						$calc_start_week = false; // Skip calculating starting week since event start is the starting week.
					} else {
						$searchDate2 = clone $searchDate1;
					}
					if ($calc_start_week) { // Check to see if we need to calculate week offset.
						while (true) {
							// Check to see if current week is a multiple of week offset and if not add one week and check again.
							$weeks = ceil(abs($searchDate2->format('U') - $event->StartTime->format("U")) / 60 / 60 / 24 / 7);
							if ($weeks % $multiplier == 0) {
								break;
							}
							$searchDate2->add(date_interval_create_from_date_string('7 days'));
						}
						$calc_start_week = false;
					}
					while($searchDate2 <= $EndTime) {
						if($searchDate2 > $EndTime)
							break;
						$newEvent = clone $event;
						$newEvent->StartTime = date_create_from_format("Y-m-d H:i:s", $searchDate2->format("Y-m-d")." ".$event->StartTime->format("H:i:s"));
						$newEvent->EndTime = date_create_from_format("Y-m-d H:i:s", $searchDate2->format("Y-m-d")." ".$event->EndTime->format("H:i:s"));
						$newEvent->Occurrence = $newEvent->StartTime;
						//Checks: don't duplicate the original event, verify start & end time range
						if($newEvent->StartTime->format("Y-m-d") != $event->StartTime->format("Y-m-d") &&
								$newEvent->StartTime < $EndTime &&
								$newEvent->EndTime > $StartTime &&
								!$this->isException($newEvent->StartTime, $event) &&
								$newEvent->StartTime > $event->StartTime &&
								$newEvent->EndTime <= $absoluteEnd
							)
							array_push($ret, $newEvent);
						$searchDate2->add(date_interval_create_from_date_string(($days_in_week * $multiplier).' days'));
					}
				}
				$searchDate1->add(date_interval_create_from_date_string('1 day'));
			}
		}
		return $ret;
	}

	/*********************** Calculate monthly recurrances in range ************************/
	private function calculateMonthlyRecurrancesInRange($StartTime, $EndTime, $event, $recurrance, $absoluteEnd) {
		$ret = array();

		if(strpos($recurrance,"Every month") === false || $EndTime < $event->StartTime || $absoluteEnd < $StartTime) return $ret;

		$pattern = '/the (?<occurance>\\w+) (?<day>\\w+) of the month/';
		preg_match_all($pattern, $recurrance, $matches);

		$searchMonth = date_create_from_format("Y-m-d H:i:s",$StartTime->format("Y-m")."-01 00:00:00");

		if($searchMonth < date_create_from_format("Y-m-d H:i:s",$event->StartTime->format("Y-m")."-01 00:00:00")) {
			$searchMonth = date_create_from_format("Y-m-d H:i:s",$event->StartTime->format("Y-m")."-01 00:00:00");
		}

		while($searchMonth < $EndTime) {
			for($i=0;$i<count($matches['occurance']);$i++) {
				$occurance = 0;

				switch($matches['occurance'][$i]) {
					case "first": $occurance = 1; break;
					case "second": $occurance = 2; break;
					case "third": $occurance = 3; break;
					case "fourth": $occurance = 4; break;
					case "fifth": $occurance = 5; break;
				}

				for($x=1;$x<=cal_days_in_month(CAL_GREGORIAN, intval($searchMonth->format("m")), intval($searchMonth->format("Y")));$x++) {
					if(strtolower(date_create_from_format("Y-m-d H:i:s",$searchMonth->format("Y-m")."-".$x." 00:00:00")->format("l")) == strtolower($matches['day'][$i])) {
						$occurance--;
						if($occurance == 0) {
							$occurance=$x;
							break;
						} else {
							$x += 6;
						}
					}
				}

				$newStart = date_create_from_format("Y-m-d H:i:s",$searchMonth->format("Y-m-").$occurance." ".$event->StartTime->format("H:i:s"));
				$newEnd = date_create_from_format("Y-m-d H:i:s",$searchMonth->format("Y-m-").$occurance." ".$event->EndTime->format("H:i:s"));
				if($newStart <= $absoluteEnd && $newStart > $event->StartTime && $newStart < $EndTime && $newEnd > $StartTime && $newStart->format("Y-m-d") != $event->StartTime->format("Y-m-d") && !$this->isException($newStart,$event)) {
					$newEvent = clone $event;
					$newEvent->StartTime = $newStart;
					$newEvent->EndTime = $newEnd;
					$newEvent->Occurrence = $newStart;
					array_push($ret,$newEvent);
				}
			}

			$searchMonth->add(date_interval_create_from_date_string('1 month'));
			if($searchMonth > $absoluteEnd)
				break;
		}

		return $ret;
	}

}

?>
