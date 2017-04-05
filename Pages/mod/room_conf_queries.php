<?php
/**
	This file contains sql query functions for the feature "Room configurations".
	

*/
require_once('connectors/mysql_connect.php');
$instance = new databaseConnect();
$instance->connect();
		global $dbh;
function getTargetId($series_id){		//hakee ja palauttaa series_id:n perusteella target_id:n
		global $dbh;
		$list=$dbh->prepare("SELECT target_id FROM `reservation_series` WHERE series_id=".regexnums($series_id)." Limit 1");
		$list->execute();
		$row=$list->fetch(PDO::FETCH_ASSOC);
	return $row['target_id'];
}
function getArrangementDescription($conf_id){		//hakee ja palauttaa conf_id:n perusteella huonekonfiguraation kuvauksen
		global $dbh;
		$list=$dbh->prepare("SELECT description FROM `resource_conf` WHERE conf_id=".regexnums($conf_id)." Limit 1");
		$list->execute();
		$row=$list->fetch(PDO::FETCH_ASSOC);
	return $row['description'];
}
function getArrangementName($conf_id){		//hakee ja palauttaa conf_id:n perusteella huonekonfiguraation nimen
		global $dbh;
		$list=$dbh->prepare("SELECT name FROM `resource_conf` WHERE conf_id=".regexnums($conf_id)." Limit 1");
		$list->execute();
		$row=$list->fetch(PDO::FETCH_ASSOC);
	return $row['name'];
}

function getArrangements($resource_id){	//Retrieves and returns all of $resource_id's roomconfigurations.
	global $dbh;		
	$resource_id=regexnums($resource_id);
	$list=$dbh->prepare("SELECT * FROM `resource_conf` WHERE conf_id IN(SELECT conf_id FROM `resource_conf_target` WHERE resource_id='".$resource_id."')");
	$list->execute();
	while($row=$list->fetch(PDO::FETCH_ASSOC)){
		$contents[]=$row['conf_id'].":".$row['name'];
	}
	return $contents;
}
//$StartDate ja $resource_id vaihettava id:seen vaikka mahdoton olla samoja...?
//P�ivitt�� huoneeseen valitun target_id:n $roomconf_id:ll�.
function setArrangement($roomconf_id,$resource_id,$StartDate){ 
	$value=0;	//
	if($resource_id==NULL||$StartDate==NULL){	//pakko olla molemmat, muuten ei voida olla varma oikeasta varauksesta
		echo "Muuttuja puuttuu! \$resource_id:".$resource_id.", \$StartDate:".$StartDate."<br>";
	}else{
		//tarkista onko kyseinen conf_id ($roomconf_id) ja resource_id ($resource_id) pareina
		global $dbh;
		$list=$dbh->prepare("SELECT target_id FROM `resource_conf_target` WHERE resource_id=".regexnums($resource_id)." AND conf_id=".regexnums($roomconf_id)." LIMIT 1");
		$list->execute();
		if($row=$list->fetch(PDO::FETCH_ASSOC)){	//jos conf_id ja resource_id matchaa, jatka
			$series_id=matchDateAndResource($resource_id,$StartDate);
			if($series_id!=NULL){
				updateTargetId($roomconf_id,$series_id);
				$value=1;
			}else{
				echo "Jotain meni pieleen! Virhe: \$series_id:t� ei saatu haettua. \$StartDate: ".$StartDate;
			}
		}else{
			echo "Jotain meni pieleen! Virhe: Annettu huonekonfiguraatio ei ole kyseiselle tilalle.";
		}
	}
	return $value;
}

function setArrangementWResIID($roomconf_id,$resource_id,$reservation_instance_id){ 
	$value=0;	//
	if($resource_id==NULL||$reservation_instance_id==NULL){
		echo "Muuttuja puuttuu! \$resource_id:".$resource_id.", \$reservation_instance_id:".$reservation_instance_id."<br>";
	}else{
		//tarkista onko kyseinen conf_id ($roomconf_id) ja resource_id ($resource_id) pareina
		global $dbh;
		$list=$dbh->prepare("SELECT target_id FROM `resource_conf_target` WHERE resource_id=".regexnums($resource_id)." AND conf_id=".regexnums($roomconf_id)." LIMIT 1");
		$list->execute();
		if($row=$list->fetch(PDO::FETCH_ASSOC)){	//jos conf_id ja resource_id matchaa, jatka
			$series_id=getSeriesIdWResIID($reservation_instance_id);
			if($series_id!=NULL){
				updateTargetId($roomconf_id,$series_id);
				$value=1;
			}else{
				echo "Jotain meni pieleen! Virhe: \$series_id:t� ei saatu haettua.";
			}
		}else{
			echo "Jotain meni pieleen! Virhe: Annettu huonekonfiguraatio ei ole kyseiselle tilalle.";
		}
	}
	return $value;
}

function removeRoomConf($conf_id){	//removes a room configuration from the database based on conf_id
	global $dbh;
	$conf_id=regexnums($conf_id);
	$list=$dbh->prepare("DELETE FROM resource_conf WHERE `conf_id` = ".$conf_id." LIMIT 1");
	$list->execute();
}

function removeRoomConfResourceLink($target_id){	//removes a link between a room and a room configuration
	global $dbh;
	$target_id=regexnums($target_id);
	$list=$dbh->prepare("DELETE FROM resource_conf_target WHERE `target_id` = ".$target_id." LIMIT 1");
	$list->execute();
}

function createRoomConf($name,$description,$resource_id){	//Creates a room configuration
	global $dbh;
	$resource_id=regexnums($resource_id);
	$list=$dbh->prepare("INSERT INTO resource_conf(name,description) VALUES ('".$name."','".$description."')");
	$list->execute();
	//createRoomConfResourceLink($resource_id,$dbh->lastInsertId()); //temp
}

function createRoomConfResourceLink($resource_id,$conf_id){
		global $dbh;
		$list=$dbh->prepare("INSERT INTO resource_conf_target(resource_id,conf_id) VALUES (".$resource_id.",".$conf_id.")");
		$list->execute();
}

function updateTargetId($roomconf_id,$series_id){
	global $dbh;
	$list=$dbh->prepare("UPDATE `reservation_series` SET `target_id` = ".regexnums($roomconf_id)." WHERE series_id = ".regexnums($series_id)." ");
	$list->execute();
}
function getSeriesIdWResIID($reservation_instance_id){
	global $dbh;
	$list=$dbh->prepare("SELECT series_id FROM `reservation_instances` WHERE reservation_instance_id = ".regexnums($reservation_instance_id)." LIMIT 1");
	$list->execute();
	$row=$list->fetch(PDO::FETCH_ASSOC);
	return $row['series_id'];
}
function matchDateAndResource($resource_id,$StartDate){
	global $dbh;
	//selvitet��n series_id (eli varaustapahtuman id) etsim�ll� $StartDate:n perusteella 
	//ja tarkistamalla ett� kyseinen aika on halutun resource_id:n
	$list=$dbh->prepare("SELECT series_id FROM `reservation_instances` WHERE start_date='".$StartDate."' AND series_id IN(SELECT series_id FROM `reservation_resources` WHERE resource_id = ".regexnums($resource_id).")");
	$list->execute();
	$row=$list->fetch(PDO::FETCH_ASSOC);
	return $row['series_id'];
}
?>