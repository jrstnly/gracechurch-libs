<?php

namespace GraceChurch\CCB;

use GraceChurch\DatabaseHandler;
use GraceChurch\AssetManager;

class EventParser {
	private $db;
	private $am;
	private $featured_groups;

	public function __construct() {
		$this->db = new DatabaseHandler("grace");
		$this->am = new AssetManager();
		$this->featured_groups = [
			1,     // Our Church
			26,    // All Eden Prairie Campus Attenders
			2375,  // All Chaska Campus Attenders
			2857   // All Online Campus Attenders
		];
	}

	public function getAllEventsInRange($StartTime, $EndTime, $Campus = '1', $sort = false, $Grouping = null, $Public = null) {
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

		$records = $this->db->getRecords($query);
		$events = $this->buildEventsList($records, $StartTime, $EndTime, $Campus);

		if ($sort || $sort == true || $sort == "Alphabetical") usort($events, array($this, "sortAlphabetically"));
		if ($sort && $sort == "StartTime") usort($events, array($this, "sortByStartTime"));
		return $events;
	}


	public function getEventsByUser($uid, $StartTime, $EndTime, $sort = true) {
		$allEvents = array();
		$groups = $this->db->getRecords("SELECT GroupID FROM ccb_group_participants WHERE Individual = '$uid'");
		$query = "SELECT * FROM `ccb_events` WHERE StartTime < '".$EndTime->format("Y-m-d H:i:s")."' AND AbsoluteEnd > '".$StartTime->format("Y-m-d H:i:s")."'";

		if ($groups != null && count($groups) > 0) {
			$query .= " AND (";
			foreach ($groups as $key => $value) {
				if (!in_array($value['GroupID'], $this->featured_groups)) {
					if ($key == (count($groups) - 1)) $query .= "GroupID = '".$value['GroupID']."'";
					else $query .= "GroupID = '".$value['GroupID']."' OR ";
				}
			}
			$query .= ")";
		}

		$records = $this->db->getRecords($query);
		$events = $this->buildEventsList($records, $StartTime, $EndTime, $Campus);

		usort($events, array($this, "sortByStartTime"));
		return $events;
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


	private function buildEventsList($Records, $StartTime, $EndTime, $Campus = null) {
		$all_events = [];
		foreach ($Records as $ev) {
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
				$event = new CalendarEvent();
				$event->ID = $ev['ID'];
				$event->Image = ($ev['Image'] != "") ? $this->am->getAccessKey($ev['Image']) : "";
				$event->StartTime = date_create_from_format("Y-m-d H:i:s",$ev['StartTime']);
				$event->EndTime = date_create_from_format("Y-m-d H:i:s",$ev['EndTime']);
				//$event->SetupStart = date_create_from_format("Y-m-d H:i:s",$ev['SetupStart']);
				//$event->SetupEnd = date_create_from_format("Y-m-d H:i:s",$ev['SetupEnd']);
				$event->Occurrence = date_create_from_format("Y-m-d H:i:s", $ev['StartTime']);
				$event->Recurrence = $ev['Recurrence'];
				$event->Resources = $ev['Resources'];
				$event->Name = $ev['Name'];
				$event->Campus = $group['Campus'];
				$event->CampusName = $group['CampusName'];
				$event->Group = $group['ID'];
				$event->GroupName = $group['Name'];
				$event->GroupType = $group['TypeName'];
				$event->GroupTypeID = $group['GroupType'];
				$event->Department = $group['DepartmentName'];
				$event->DepartmentID = $group['Department'];
				$event->Grouping = $ev['Grouping'];
				$event->Exceptions = json_decode($ev['Exceptions'], true);
				$event->Description = $ev['Description'];
				$event->Organizer = $ev['Organizer'];
				$event->Location = (object)["Name"=>$ev['LocationName'],"StreetAddress"=>$ev['StreetAddress'],"City"=>$ev['City'],"State"=>$ev['State'],"Zip"=>$ev['Zip']];
				$event->Tags = $ev['Tags'];

				$occurrences = $this->findAllOccurances($StartTime, $EndTime, $event, $ev['Recurrence'], date_create_from_format("Y-m-d H:i:s", $ev['AbsoluteEnd']));

				$all_events = array_merge($all_events, $occurrences);
			}
		}
		return $all_events;
	}

	private function findAllOccurances($StartTime, $EndTime, $SourceEvent, $Recurrence, $AbsoluteEnd) {
		$eventList = array();
		if($SourceEvent->StartTime <= $EndTime && $SourceEvent->EndTime >= $StartTime && !$this->isException($SourceEvent->StartTime, $SourceEvent)){
			array_push($eventList, $SourceEvent);
		}
		$eventList = array_merge($eventList, $this->CalculateDailyRecurrancesInRange($StartTime, $EndTime, $SourceEvent, $Recurrence, $AbsoluteEnd));
		$eventList = array_merge($eventList, $this->CalculateWeeklyRecurrancesInRange($StartTime, $EndTime, $SourceEvent, $Recurrence, $AbsoluteEnd));
		$eventList = array_merge($eventList, $this->CalculateMonthlyRecurrancesInRange($StartTime, $EndTime, $SourceEvent, $Recurrence, $AbsoluteEnd));
		$exceptionArray = $SourceEvent->Exceptions;
		return $eventList;
	}

	private function isException($StartTime, $SourceEvent) {
		if (is_array($SourceEvent->Exceptions)) {
			return in_array($StartTime->format("Y-m-d"),$SourceEvent->Exceptions);
		} else {
			return false;
		}
	}

	/*********************** Sort functions ************************/
	public static function sortAlphabetically($a,$b) {
		$string = strcasecmp($a->Name, $b->Name);
		if ($string == 0) $return = $a->StartTime > $b->StartTime;
		else $return = $string;
		return $return;
	}
	public static function sortByStartTime($a,$b) {
		return $a->StartTime > $b->StartTime;
	}

	/*********************** Calculate daily recurrances in range ************************/
	private function calculateDailyRecurrancesInRange($StartTime, $EndTime, $SourceEvent, $Recurrence, $AbsoluteEnd) {
		$occurrences = [];

		if(strpos($Recurrence,"Every day") === false || $EndTime < $SourceEvent->StartTime || $AbsoluteEnd < $StartTime)
			return $occurrences;

		$tmp = clone $StartTime;

		if($tmp < $SourceEvent->StartTime)
			$tmp = clone $SourceEvent->StartTime;

		//Don't create a new occurrence on the start date of the event
		if($tmp->format("Y-m-d") == $SourceEvent->StartTime->format("Y-m-d"))
			$tmp->add(date_interval_create_from_date_string('1 day'));

		while ($tmp <= $EndTime){
			$Event = clone $SourceEvent;
			$Event->StartTime = date_create_from_format("Y-m-d H:i:s", $tmp->format("Y-m-d")." ".$SourceEvent->StartTime->format("H:i:s"));
			$Event->EndTime = date_create_from_format("Y-m-d H:i:s", $tmp->format("Y-m-d")." ".$SourceEvent->EndTime->format("H:i:s"));
			if($Event->StartTime > $AbsoluteEnd && !$this->isException($Event->StartTime, $SourceEvent))
				return $occurrences;

			array_push($occurrences, $Event);

			$tmp->add(date_interval_create_from_date_string('1 day'));
		}
		return $occurrences;
	}

	/*********************** Calculate weekly recurrances in range ************************/
	private function calculateWeeklyRecurrancesInRange($StartTime, $EndTime, $SourceEvent, $Recurrence, $AbsoluteEnd) {
		$occurrences = [];
		$matches = [];
		$days_in_week = 7;
		$calc_start_week = true;

		if (preg_match("/^Every ([2-4])?( )?week(s)?/", $Recurrence, $matches) && $EndTime > $SourceEvent->StartTime && $AbsoluteEnd > $StartTime) {
			$multiplier = (isset($matches[1])) ? (int)$matches[1] : 1;

			$searchDate1 = clone $StartTime;
			$searchDate2;

			for($i = 0; $i < 7; $i++) {
				if($searchDate1 > $EndTime)
					break;
				if(strpos($Recurrence,$searchDate1->format("l")) !== false) {
					if ($searchDate1 < $SourceEvent->StartTime) { // Check to see if range start is before start of event. If so, clone start of event instead of range start to prevent unnecessary cycles.
						$searchDate2 = clone $SourceEvent->StartTime;
						$calc_start_week = false; // Skip calculating starting week since event start is the starting week.
					} else {
						$searchDate2 = clone $searchDate1;
					}
					if ($calc_start_week) { // Check to see if we need to calculate week offset.
						while (true) {
							// Check to see if current week is a multiple of week offset and if not add one week and check again.
							$weeks = ceil(abs($searchDate2->format('U') - $SourceEvent->StartTime->format("U")) / 60 / 60 / 24 / 7);
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
						$Event = clone $SourceEvent;
						$Event->StartTime = date_create_from_format("Y-m-d H:i:s", $searchDate2->format("Y-m-d")." ".$SourceEvent->StartTime->format("H:i:s"));
						$Event->EndTime = date_create_from_format("Y-m-d H:i:s", $searchDate2->format("Y-m-d")." ".$SourceEvent->EndTime->format("H:i:s"));
						$Event->Occurrence = $Event->StartTime;
						//Checks: don't duplicate the original event, verify start & end time range
						if($Event->StartTime->format("Y-m-d") != $SourceEvent->StartTime->format("Y-m-d") &&
								$Event->StartTime < $EndTime &&
								$Event->EndTime > $StartTime &&
								!$this->isException($Event->StartTime, $SourceEvent) &&
								$Event->StartTime > $SourceEvent->StartTime &&
								$Event->EndTime <= $AbsoluteEnd
							)
							array_push($occurrences, $Event);
						$searchDate2->add(date_interval_create_from_date_string(($days_in_week * $multiplier).' days'));
					}
				}
				$searchDate1->add(date_interval_create_from_date_string('1 day'));
			}
		}
		return $occurrences;
	}

	/*********************** Calculate monthly recurrances in range ************************/
	private function calculateMonthlyRecurrancesInRange($StartTime, $EndTime, $SourceEvent, $Recurrence, $AbsoluteEnd) {
		$occurrences = [];

		if (preg_match("/^Every ([1-12])?( )?month(s)?/", $Recurrence, $matches1) && $EndTime > $SourceEvent->StartTime && $AbsoluteEnd > $StartTime) {
			$multiplier = (isset($matches1[1]) && $matches1[1] != "") ? (int)$matches1[1] : 1;

			$searchMonth = date_create_from_format("Y-m-d H:i:s",$StartTime->format("Y-m")."-01 00:00:00");

			if ($searchMonth < date_create_from_format("Y-m-d H:i:s",$SourceEvent->StartTime->format("Y-m")."-01 00:00:00")) {
				$searchMonth = date_create_from_format("Y-m-d H:i:s",$SourceEvent->StartTime->format("Y-m")."-01 00:00:00");
			} else {
				while (true) {
					$originalMonth = date_create_from_format("Y-m-d H:i:s",$SourceEvent->StartTime->format("Y-m")."-01 00:00:00");
					$months = date_diff($originalMonth, $searchMonth)->m;
					if ($months % $multiplier == 0) {
						break;
					}
					$searchMonth->add(date_interval_create_from_date_string('1 month'));
				}
			}

			$pattern = '/the (?<occurence>\\w+) (?<day>\\w+) of the month/';
			preg_match_all($pattern, $Recurrence, $matches2);

			while ($searchMonth < $EndTime) {
				for ($i=0;$i<count($matches2['occurence']);$i++) {
					$occurence = 0;

					switch ($matches2['occurence'][$i]) {
						case "first": $occurence = 1; break;
						case "second": $occurence = 2; break;
						case "third": $occurence = 3; break;
						case "fourth": $occurence = 4; break;
						case "fifth": $occurence = 5; break;
					}

					for ($x=1;$x<=cal_days_in_month(CAL_GREGORIAN, intval($searchMonth->format("m")), intval($searchMonth->format("Y")));$x++) {
						if (strtolower(date_create_from_format("Y-m-d H:i:s",$searchMonth->format("Y-m")."-".$x." 00:00:00")->format("l")) == strtolower($matches2['day'][$i])) {
							$occurence--;
							if ($occurence == 0) {
								$occurence=$x;
								break;
							} else {
								$x += 6;
							}
						}
					}

					$newStart = date_create_from_format("Y-m-d H:i:s",$searchMonth->format("Y-m-").$occurence." ".$SourceEvent->StartTime->format("H:i:s"));
					$newEnd = date_create_from_format("Y-m-d H:i:s",$searchMonth->format("Y-m-").$occurence." ".$SourceEvent->EndTime->format("H:i:s"));
					if ($newStart <= $AbsoluteEnd && $newStart > $SourceEvent->StartTime && $newStart < $EndTime && $newEnd > $StartTime && $newStart->format("Y-m-d") != $SourceEvent->StartTime->format("Y-m-d") && !$this->isException($newStart,$SourceEvent)) {
						$Event = clone $SourceEvent;
						$Event->StartTime = $newStart;
						$Event->EndTime = $newEnd;
						$Event->Occurrence = $newStart;
						array_push($occurrences,$Event);
					}
				}

				$dateIntervalString = $multiplier.' month';
				$dateIntervalString .= ($multiplier > 1) ? 's' : '';
				$searchMonth->add(date_interval_create_from_date_string($dateIntervalString));
				if ($searchMonth > $AbsoluteEnd)
					break;
			}
		}
		return $occurrences;
	}

}

?>
