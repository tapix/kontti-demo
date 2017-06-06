<?php
/**
	Tämä tiedosto sisältää funktioita.
	

*/

require_once(ROOT_DIR . '/Pages/mod/connectors/mysql_connect.php');
$instance = new databaseConnect();
$instance->connect();
		global $dbh;
		
function pdoExecute($query){
		global $dbh;
		$list=$dbh->prepare($query);
		$list->execute();
	return $list;
}

function regexnums($value){ // Poistaa kaiken muun paitsi numerot
	return preg_replace("/[^0-9]/","",$value);
}
function timeForDatabase($date,$time){
	$time=regexLengthIsTwo($time);
	$time=regexTimeIsReal($time);
	$time=convertTimeTo($date,$time);
	return $time;
}
function timeFromDatabase($date,$time){
	$time=regexLengthIsTwo($time);
	$time=regexTimeIsReal($time);
	$time=convertTimeToFinland($date,$time);
	return $time;
}
function convertTimeToFinland($date,$time){	//converts the time to database time using booked's Date class
	require_once(ROOT_DIR.'lib/Common/Date.php');
	require_once(ROOT_DIR.'lib/Common/Time.php');
	
	$finlandtime = new Date();
	$finlandtime->__construct($date." ".$time,"UTC");
	return $finlandtime->ToTimezone('Europe/Helsinki');
}
function convertTimeTo($date,$time){	//converts the time to database time using booked's Date class
	require_once(ROOT_DIR.'lib/Common/Date.php');
	require_once(ROOT_DIR.'lib/Common/Time.php');
	
	$databasetime = new Date();
	$databasetime->__construct($date." ".$time, 'Europe/Helsinki');
	return $databasetime->ToDatabase();
	/**
	// Bad way of doing this
	
	$time=explode(":", $time);
	$time[0]=$time[0]-3;
	if($time[0]<0){
		$time[0]=24+$time[0];
	}
	if(strlen($time[0])!=2){
		$time[0]="0".$time[0];
	}
	return implode(":", $time);
	*/
}
function regexSingleNumber($numb){
	$status=FALSE;
	if(strlen($numb)==1){
		return preg_replace("/[^1]/","",$numb);
	}   
}
function regexUserInfoText($textstring){
	//allows numbers, letters, . and -
	//to add more allowed characters, just add them before ]
    return preg_replace("/[^a-zA-Z0-9.-äåöüÄÅÖÜ ]/","",$textstring);
}

function DateIsReal($date){
	$date=regexDateIsReal($date);
	list($y, $m, $d) = explode("-", $date);
	return checkdate($m, $d, $y);
}
function regexDateIsReal($time){
    return preg_replace("/[^0-9-]/","",$time); //poistaa annetusta $time muut kuin numerot ja - merkit ja palauttaa tuloksen
}
function regexTimeIsReal($time){
    return preg_replace("/[^0-9:]/","",$time); //poistaa annetusta $time muut kuin numerot ja : merkit ja palauttaa tuloksen
}
function regexCheckTimeIsReal($time){
	return preg_match("/(2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9])/", $time);
}
function regexLengthIsTwo($time){	//poistaa formaatista 00:00:00 ylimääräiset : eroteltuna, [2]:[2]:[2]
	$time=explode(":", $time);
	for($i=0;$i<count($time);$i++){
		$time[$i]=substr($time[$i],0,2);
	}
	return implode(":", $time);
}
function regexRemoveSecs($time){	//leaves 00:00 from any input with :
	$time=explode(":", $time);
	$output=$time[0].".".$time[1];
	return $output;
}
function mailToCatering($status,$foodInfo,$count,$id,$dayCounter,$restime,$seriesid){
	$daylist="";
	$statustext="";
	$userinfo=getAllUserAddonInfo($id);
	$compname=$userinfo['compname'];
	$personid=$userinfo['personid'];
	$billingaddress=$userinfo['billingaddress'];
	$reference=$userinfo['reference'];
	if($status==1){
		$statustext="Uusi varaus";
	}elseif($status==2){
		$statustext="Varausta muokattu";
	}
	foreach($dayCounter as $days){
		$tempdays=$days;
		$days=explode(" ",$days);
		$days=timeFromDatabase($days[0],$days[1]);
		$days=date('d.m.Y', strtotime($days));
		$daylist=$daylist.$days;
		if ($tempdays === end($dayCounter)){
		}else{
			$daylist=$daylist.", ";
		}
	}
	$to      = "matti.luhtala@vantaa.fi";
	$subject = "Muuntamo - ".$seriesid." - Ateriatilaus";
	if($status!=3){
		$message = $statustext."\n
					<h3>Tilauksen tiedot</h3>\n
					Varauksen numero: ".$seriesid."<br/><br/>\n
					Menun nimi: ".$foodInfo['name']."<br/><br/>\n
					Hinta: ".$foodInfo['price']." €/kpl<br/><br/>\n
					Määrä: ".$count." kpl<br/><br/>\n
					Päivät: ".$daylist."<br/><br/>\n
					Kellonaika: ".$restime."<br/><br/>\n
					<hr>
					<h3>Laskutustiedot</h3>
					Yrityksen nimi /Yksityishenkilön nimi: ".$compname."<br/><br/>\n
					Y-tunnus / henkilötunnus: ".$personid."<br/><br/>\n
					Laskutusosoite: ".$billingaddress."<br/><br/>\n
					Viitteenne tietoon kustannuspaikkanumero: ".$reference."<br/><br/>\n";
	}
	$headers = "From: muuntamo@smartlabvantaa.fi" . "\r\n" .
		"Reply-To: muuntamo@smartlabvantaa.fi" . "\r\n" .
		"X-Mailer: PHP/" . phpversion() . "\r\n" .
		"Content-Type: text/html; charset=UTF-8";

	mail($to, $subject, $message, $headers);
	$to      = "tapio.torronen@metropolia.fi";
	mail($to, $subject, $message, $headers);
}
function mailToCateringDeleted($seriesid,$id){
	//mail sent when reservation has it's menu selection removed
	$daylist="";
	$statustext="";
	$userinfo=getAllUserAddonInfo($id);
	$compname=$userinfo['compname'];
	$personid=$userinfo['personid'];
	$billingaddress=$userinfo['billingaddress'];
	$reference=$userinfo['reference'];
	$to      = "matti.luhtala@vantaa.fi";
	$subject = "Muuntamo - ID".$seriesid." - Poistettu ateriatilaus";
	$message = "Varauksesta poistettu ateriavalinta\n
				<h3>Tilauksen tiedot</h3>\n
				Varauksen numero: ".$seriesid."<br/><br/>\n
				<hr>
				<h3>Laskutustiedot</h3>
				Yrityksen nimi /Yksityishenkilön nimi: ".$compname."<br/><br/>\n
				Y-tunnus / henkilötunnus: ".$personid."<br/><br/>\n
				Laskutusosoite: ".$billingaddress."<br/><br/>\n
				Viitteenne tietoon kustannuspaikkanumero: ".$reference."<br/><br/>\n";
	$headers = "From: muuntamo@smartlabvantaa.fi" . "\r\n" .
		"Reply-To: muuntamo@smartlabvantaa.fi" . "\r\n" .
		"X-Mailer: PHP/" . phpversion() . "\r\n" .
		"Content-Type: text/html; charset=UTF-8";

	mail($to, $subject, $message, $headers);
	$to      = "tapio.torronen@metropolia.fi";
	mail($to, $subject, $message, $headers);
}
/**
function createThumb($address, $conf_id){	//thumbnail creator
		$tempfilename="temp_".$conf_id.".jpg";
		$copyname=ROOT_DIR."/uploads/arrangmenets/thumbnail/".$tempfilename;
		copy($address,$copyname);
		$name=ROOT_DIR."uploads/arrangements/thumbnail/".$tempfilename;
		$thumbname=ROOT_DIR."uploads/arrangements/thumbnail/thumb_".$conf_id.".png";
		
		$thumb = new Imagick();
		$thumb->readImage($name);   
		$aspects=$thumb->getImageGeometry();
		$x=70;
		$y=70;
		if($aspects['height']>$aspects['width']){
			$ratio=$aspects['height']/$aspects['width'];
			$x=70/$ratio;
		}else{
			$ratio=$aspects['width']/$aspects['height'];
			$y=70/$ratio;
		}
		$thumb->resizeImage($x,$y,Imagick::FILTER_LANCZOS,1);
		$thumb->writeImage($thumbname);
		$thumb->clear();
		$thumb->destroy();
		unlink($copyname);
}
*/
?>