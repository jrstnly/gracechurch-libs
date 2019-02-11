<?php

namespace GraceChurch;

use GraceChurch\Zebra\ZebraPrinter;
use GraceChurch\Zebra\ZebraCommunicationException;

use Weez\Zpl\Constant\ZebraFont;
use Weez\Zpl\Constant\ZebraPPP;
use Weez\Zpl\Constant\ZebraPrintMode;
use Weez\Zpl\Constant\ZebraRotation;
use Weez\Zpl\Model\Element\ZebraGraficBox;
use Weez\Zpl\Model\Element\ZebraNativeZpl;
use Weez\Zpl\Model\Element\ZebraQrCode;
use Weez\Zpl\Model\Element\ZebraText;
use Weez\Zpl\Model\PrinterOptions;
use Weez\Zpl\Model\ZebraLabel;


class Tag {
	private $tags, $label, $client;
	public function __construct() {
		$this->tags = array();
		$this->label = new ZebraLabel(609, 406);
		//$this->label->setPrinterOptions(new PrinterOptions(new ZebraPPP(ZebraPPP::DPI_203)));
		$this->label->setDefaultZebraFont(new ZebraFont(ZebraFont::ZEBRA_ZERO));
		$this->label->setZebraPrintMode(new ZebraPrintMode(ZebraPrintMode::CUTTER));
	}
	public function printer($address, $backup = false) {
		$return = array("status"=>"");
    try {
      $this->client = new ZebraPrinter($address);
			$status = $this->checkStatus();
			if ($status['paused'] == '1' && $status['paper_out'] == '0' && $status['head_open'] == '0') {
				if ($backup) {
					$this->client = new ZebraPrinter($backup);
					$return["status"] = "success";
					$return["message"] = "Primary printer paused. Job sent to backup printer.";
				} else {
					$return["status"] = "success";
					$return["message"] = "Primary printer paused. Job queued in printer.";
				}
			} else if ($status['paper_out'] == '1') {
				if ($backup) {
					$this->client = new ZebraPrinter($backup);
					$return["status"] = "success";
					$return["message"] = "Primary printer out of paper. Job sent to backup printer.";
				} else {
					$return["status"] = "success";
					$return["message"] = "Primary printer out of paper. Job queued in printer.";
				}
			} else if ($status['head_open'] == '1') {
				if ($backup) {
					$this->client = new ZebraPrinter($backup);
					$return["status"] = "success";
					$return["message"] = "Primary printer head open. Job sent to backup printer.";
				} else {
					$return["status"] = "success";
					$return["message"] = "Primary printer head open. Job queued in printer.";
				}
			} else {
				$return["status"] = "success";
			}
    } catch (ZebraCommunicationException $e) {
      try {
        if ($backup) {
    			$this->client = new ZebraPrinter($backup);
					$return["status"] = "success";
          $return["message"] = "Unable to contact primary printer. Job sent to backup printer.";
    		} else {
					$return["status"] = "success";
					$return["message"] = "Unable to contact primary printer and no backup defined. Please contact the system administrator for assistance.";
        }
      } catch (ZebraCommunicationException $e) {
				$return["status"] = "success";
				$return["message"] = "Unable to contact primary and backup printers. Please contact the system administrator for assistance.";
      }
    }
		return $return;
	}
	public function send() {
		try {
			$zpl = $this->generate();
			$this->client->send($zpl);
			return true;
		} catch (ZebraCommunicationException $e) {
			return $e->getMessage();
		}
	}
	public function addName($first_name, $last_name) {
		array_push($this->tags, array("type"=>"name","first_name"=>$first_name,"last_name"=>$last_name));
	}
	public function addEvent($first_name, $last_name, $event) {
		array_push($this->tags, array("type"=>"event","first_name"=>$first_name,"last_name"=>$last_name,"event"=>$event));
	}
	public function addSecurity($first_name, $last_name, $code, $event, $group, $allergies, $young) {
		array_push($this->tags, array("type"=>"security","first_name"=>$first_name,"last_name"=>$last_name,"code"=>$code,"event"=>$event,"group"=>$group,"allergies"=>$allergies));
		if ($young) array_push($this->tags, array("type"=>"security","first_name"=>$first_name,"last_name"=>$last_name,"code"=>$code,"event"=>$event,"group"=>$group,"allergies"=>$allergies));
	}
	public function addPickup($code) {
		array_push($this->tags, array("type"=>"pickup","code"=>$code));
	}
	public function checkStatus() {
		try {
			$req = new ZebraLabel(609, 406);
			$req->setDefaultZebraFont(new ZebraFont(ZebraFont::ZEBRA_ZERO));
			$req->setZebraPrintMode(new ZebraPrintMode(ZebraPrintMode::CUTTER));
			$req->addElement(new ZebraNativeZpl("~HS\n"));

			$data = $this->client->send($req->getZplCode());

			if ($data != "") {
				$strings = explode(PHP_EOL, $data);
				$string1 = explode(",", $strings[0]);
				$string2 = explode(",", $strings[1]);
				$string3 = explode(",", $strings[2]);
				$return = [
					"paper_out"=>preg_replace('/[[:cntrl:]]/', '', $string1[1]),
					"paused"=>preg_replace('/[[:cntrl:]]/', '', $string1[2]),
					"under_temperature"=>preg_replace('/[[:cntrl:]]/', '', $string1[10]),
					"over_temperature"=>preg_replace('/[[:cntrl:]]/', '', $string1[11]),
					"head_open"=>preg_replace('/[[:cntrl:]]/', '', $string2[2]),
					"ribbon_out"=>preg_replace('/[[:cntrl:]]/', '', $string2[3]),
					"labels_remaining"=>preg_replace('/[[:cntrl:]]/', '', $string2[2])
				];
			} else {
				$return = false;
			}
		} catch (ZebraCommunicationException $e) {
			$return = $e->getMessage();
		}
		return $return;
	}


	private function generate() {
		$names = 0;
		$all_names = false;
		$tags = array_reverse($this->tags);
		foreach ($tags as $key => $value) { if ($value['type'] == "name") { $names++; }}
		if ($names == count($tags)) { $all_names = true; }

		foreach ($tags as $key => $tag) {
			if ($key == (count($tags)-1)) { $last = true; }
			elseif ($all_names == true) { $last = true; }
			else { $last = false; }

			if ($tag["type"] == "name")     { $this->generate_name($tag['first_name'], $tag['last_name'], $last); }
			if ($tag["type"] == "event")    { $this->generate_event($tag['first_name'], $tag['last_name'], $tag['event'], $last); }
			if ($tag["type"] == "security") { $this->generate_security($tag['first_name'], $tag['last_name'], $tag['code'], $tag['event'], $tag['group'], $tag['allergies'], $last); }
			if ($tag["type"] == "pickup")   { $this->generate_pickup($tag['code'], $last); }
		}
		return $this->label->getZplCode();
	}
	private function generate_name($first_name, $last_name, $last = true) {
		$this->label->addElement(new ZebraNativeZpl("^XA\n"));
		$this->label->addElement(new ZebraNativeZpl("^FB603,1,0,C,0\n"));
		$this->label->addElement(new ZebraText(1, 180, $first_name, 26));
		$this->label->addElement(new ZebraNativeZpl("^FB603,1,0,C,0\n"));
		$this->label->addElement(new ZebraText(1, 280, $last_name, 22));
		if (!$last) $this->label->addElement(new ZebraNativeZpl("^XB\n"));
		$this->label->addElement(new ZebraNativeZpl("^XZ\n"));
	}
	private function generate_event($first_name, $last_name, $event, $last = true) {
		$this->label->addElement(new ZebraNativeZpl("^XA\n"));
		$this->label->addElement(new ZebraNativeZpl("^FB603,1,0,C,0\n"));
		$this->label->addElement(new ZebraText(1, 140, $first_name, 26));
		$this->label->addElement(new ZebraNativeZpl("^FB603,1,0,C,0\n"));
		$this->label->addElement(new ZebraText(1, 240, $last_name, 22));
		$this->label->addElement(new ZebraNativeZpl("^FB603,1,0,C,0\n"));
		$this->label->addElement(new ZebraText(20, 360, $event, 9));
		if (!$last) $this->label->addElement(new ZebraNativeZpl("^XB\n"));
		$this->label->addElement(new ZebraNativeZpl("^XZ\n"));
	}
	private function generate_security($first_name, $last_name, $code, $event, $group, $allergies, $last = false) {
		$this->label->addElement(new ZebraNativeZpl("^XA\n"));
		$this->label->addElement(new ZebraText(20, 80, $first_name, 18));
		$this->label->addElement(new ZebraText(20, 130, $last_name, 12));
		$this->label->addElement(new ZebraText(20, 190, $event, 9));
		$this->label->addElement(new ZebraText(20, 220, $group, 6));
		if ($allergies && $allergies != "") $this->label->addElement(new ZebraText(20, 280, "Allergies: ".$allergies, 6));
		$this->label->addElement(new ZebraText(20, 370, $code, 18));
		$this->label->addElement(new ZebraText(20, 400, date("l, M j, Y"), 6));
		$this->label->addElement(new ZebraQrCode(490, 420, 'pickup:'.$code, 5));
		if (!$last) $this->label->addElement(new ZebraNativeZpl("^XB\n"));
		$this->label->addElement(new ZebraNativeZpl("^XZ\n"));
	}
	private function generate_pickup($code, $last = false) {
		$this->label->addElement(new ZebraNativeZpl("^XA\n"));
		$this->label->addElement(new ZebraNativeZpl("^FB301,1,0,C,0\n"));
		$this->label->addElement(new ZebraText(1, 200, $code, 28));
		$this->label->addElement(new ZebraNativeZpl("^FB301,1,0,C,0\n"));
		$this->label->addElement(new ZebraText(1, 230, date("l, M j, Y"), 6));

		$this->label->addElement(new ZebraText(294, 1, "- - - - - - - - - - - - -", 6, null, new ZebraRotation("R")));

		$this->label->addElement(new ZebraNativeZpl("^FB301,1,0,C,0\n"));
		$this->label->addElement(new ZebraText(302, 200, $code, 28));
		$this->label->addElement(new ZebraNativeZpl("^FB301,1,0,C,0\n"));
		$this->label->addElement(new ZebraText(302, 230, date("l, M j, Y"), 6));

		$this->label->addElement(new ZebraNativeZpl("^FB609,1,0,C,0\n"));
		$this->label->addElement(new ZebraText(1, 400, "**Keep this ticket to pick up your child(ren)**", 6));

		if (!$last) $this->label->addElement(new ZebraNativeZpl("^XB\n"));
		$this->label->addElement(new ZebraNativeZpl("^XZ\n"));
	}
}

?>
