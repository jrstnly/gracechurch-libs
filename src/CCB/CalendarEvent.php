<?php

namespace GraceChurch\CCB;

class CalendarEvent implements \JsonSerializable {
	public $ID;
	public $Name;
	public $StartTime;
	public $EndTime;
	public $Resources;
	public $Group;
	public $Description;
	public $Exceptions;

	public function jsonSerialize() {
		return [
			'ID' => $this->ID,
			'Name' => $this->Name,
			'Description' => $this->Description,
			'Image' => $this->Image,
			'StartTime' => $this->StartTime->format("Y-m-d H:i:s"),
			'EndTime' => $this->EndTime->format("Y-m-d H:i:s"),
			//'SetupStart' => $this->setupStart->format("Y-m-d H:i:s"),
			//'SetupEnd' => $this->setupEnd->format("Y-m-d H:i:s"),
			'Occurrence' => $this->Occurrence->format("Y-m-d H:i:s"),
			'Recurrence' => $this->Recurrence,
			'Resources' => $this->Resources,
			'AttendeeLimit' => $this->AttendeeLimit,
			'Campus' => $this->Campus,
			'CampusName' => $this->CampusName,
			'CheckedInCount' => $this->CheckedInCount,
			'Group' => $this->Group,
			'GroupName' => $this->GroupName,
			'GroupType' => $this->GroupType,
			'GroupTypeID' => $this->GroupTypeID,
			'Department' => $this->Department,
			'DepartmentID' => $this->DepartmentID,
			'Grouping' => $this->Grouping,
			'Exceptions' => $this->Exceptions,
			'Organizer' => $this->Organizer,
			'Location' => $this->Location,
			'PreCheckedInCount' => $this->PreCheckedInCount,
			'Tags' => $this->Tags
		];
	}
}

?>
