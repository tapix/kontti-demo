<?php
/**
 * Copyright 2011-2016 Nick Korbel
 *
 * This file is part of Booked Scheduler.
 * This file has been modified for Muuntamo.
 *
 * Booked Scheduler is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Booked Scheduler is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Booked Scheduler.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once(ROOT_DIR . 'Pages/mod/namespace.php');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Pages/Ajax/IReservationSaveResultsView.php');
require_once(ROOT_DIR . 'Presenters/Reservation/ReservationPresenterFactory.php');

interface IReservationSavePage extends IReservationSaveResultsView, IRepeatOptionsComposite
{
	/**
	 * @return int
	 */
	public function GetUserId();

	/**
	 * @return int
	 */
	public function GetResourceId();

	/**
	 * @return string
	 */
	public function GetTitle();

	/**
	 * @return string
	 */
	public function GetDescription();

	/**
	 * @return string
	 */
	public function GetStartDate();

	/**
	 * @return string
	 */
	public function GetEndDate();

	/**
	 * @return string
	 */
	public function GetStartTime();

	/**
	 * @return string
	 */
	public function GetEndTime();

	/**
	 * @return int[]
	 */
	public function GetResources();

	/**
	 * @return int[]
	 */
	public function GetParticipants();

	/**
	 * @return int[]
	 */
	public function GetInvitees();

	/**
	 * @param string $referenceNumber
	 */
	public function SetReferenceNumber($referenceNumber);

	/**
	 * @param bool $requiresApproval
	 */
	public function SetRequiresApproval($requiresApproval);

	/**
	 * @return AccessoryFormElement[]|array
	 */
	public function GetAccessories();

	/**
	 * @return AttributeFormElement[]|array
	 */
	public function GetAttributes();

	/**
	 * @return UploadedFile[]
	 */
	public function GetAttachments();

	/**
	 * @return bool
	 */
	public function HasStartReminder();

	/**
	 * @return string
	 */
	public function GetStartReminderValue();

	/**
	 * @return string
	 */
	public function GetStartReminderInterval();

	/**
	 * @return bool
	 */
	public function HasEndReminder();

	/**
	 * @return string
	 */
	public function GetEndReminderValue();

	/**
	 * @return string
	 */
	public function GetEndReminderInterval();

	/**
	 * @return bool
	 */
	public function GetAllowParticipation();

	/**
	 * @return string[]
	 */
	public function GetParticipatingGuests();

	/**
	 * @return string[]
	 */
	public function GetInvitedGuests();
}

class ReservationSavePage extends SecurePage implements IReservationSavePage
{
	/**
	 * @var ReservationSavePresenter
	 */
	private $_presenter;

	/**
	 * @var bool
	 */
	private $_reservationSavedSuccessfully = false;

	public function __construct()
	{
		parent::__construct();

		$factory = new ReservationPresenterFactory();
		$this->_presenter = $factory->Create($this, ServiceLocator::GetServer()->GetUserSession());
	}

	public function PageLoad()
	{
		try
		{		
			$ResourceArrangementAR=$_POST['additionalResources'];
			if(isset($_POST['ResourceFoodArrangementCountSelect'])){
				$tempCount=regexnums($_POST['ResourceFoodArrangementCountSelect']);
				foreach($ResourceArrangementAR as $resource){
					if($tempCount[$resource]>35){
						$tempCount[$resource]=35;
					}elseif($tempCount[$resource]<0){
						$tempCount[$resource]=0;
					}
				}
				
				$_POST['ResourceFoodArrangementCountSelect']=$tempCount;
			}
			$compname=regexUserInfoText($_POST['compname']);
			$personid=regexUserInfoText($_POST['personid']);
			$billingaddress=regexUserInfoText($_POST['billingaddress']);
			$reference=regexUserInfoText($_POST['reference']);
			$additionalInfo=regexUserInfoText($_POST['additionalinfo']);
			$ResourceArrangement=$_POST['ResourceArrangement'];
			$ResourceFoodArrangement=$_POST['ResourceFoodArrangement'];
			$ResourceFoodArrangementCountSelect=$_POST['ResourceFoodArrangementCountSelect'];
			$FoodHalfFirst=0;
			$FoodHalfSecond=0;
			if(isset($ResourceArrangementAR)){
				foreach($ResourceArrangementAR as $resource){						
					if(isset($_POST['foodhalffirst'.regexnums($ResourceFoodArrangement[$resource]).''])||isset($_POST['foodhalfsecond'.regexnums($ResourceFoodArrangement[$resource]).''])){
						$FoodHalfFirst=regexnums($_POST['foodhalffirst'.regexnums($ResourceFoodArrangement[$resource]).'']);
						$FoodHalfSecond=regexnums($_POST['foodhalfsecond'.regexnums($ResourceFoodArrangement[$resource]).'']);
						if($FoodHalfSecond==NULL){$FoodHalfSecond=0;}
					}
					setAllTemp($resource,timeForDatabase(regexDateIsReal($_POST['beginDate']),$_POST['beginPeriod']),regexnums($ResourceArrangement[$resource]),regexnums($ResourceFoodArrangement[$resource]),regexnums($ResourceFoodArrangementCountSelect[$resource]),$FoodHalfFirst,$FoodHalfSecond,$compname,$personid,$billingaddress,$reference,$additionalInfo);
				}
			}
			
			$this->EnforceCSRFCheck();
			$reservation = $this->_presenter->BuildReservation();
			$this->_presenter->HandleReservation($reservation);
			$databaseTimeConv=timeForDatabase(regexDateIsReal($_POST['beginDate']),$_POST['beginPeriod']);
			foreach($ResourceArrangementAR as $resource){
				delAllTemp($resource,$databaseTimeConv);
			}
			if ($this->_reservationSavedSuccessfully)		//Only when creating a new Reservation
			{
				$this->Set('Resources', $reservation->AllResources());
				$this->Set('Instances', $reservation->Instances());
				$this->Set('Timezone', ServiceLocator::GetServer()->GetUserSession()->Timezone);
				$food=0;
				// MODIFIED CODE STARTS HERE
				$userSession2 = ServiceLocator::GetServer()->GetUserSession();	
				
				if(isset($_POST['additionalResources'])){	//if multiple resources have been defined, this variable will be defined'
					if(isset($_POST['ResourceArrangement'])&&isset($_POST['beginDate'])&&isset($_POST['beginPeriod'])){
						$ResourceArrangement=$_POST['ResourceArrangement'];
						$ResourceArrangementAR=$_POST['additionalResources'];
						foreach($ResourceArrangementAR as $resource){
							setArrangement($ResourceArrangement[$resource],$resource,timeForDatabase(regexDateIsReal($_POST['beginDate']),$_POST['beginPeriod']));
						}
					}else{
						echo "Tilaratkaisuja ei tallennettu! Virhe: Puuttuva muuttuja.";
					}
					if(isset($_POST['ResourceFoodArrangementCountSelect'])&&isset($_POST['ResourceFoodArrangement'])&&isset($_POST['beginDate'])&&isset($_POST['beginPeriod'])){

						$ResourceFoodArrangement=$_POST['ResourceFoodArrangement'];
						$ResourceFoodArrangementCountSelect=$_POST['ResourceFoodArrangementCountSelect'];
						$ResourceArrangementAR=$_POST['additionalResources'];
						foreach($ResourceArrangementAR as $resource){
							if($ResourceFoodArrangement[$resource]!=0&&$ResourceFoodArrangementCountSelect[$resource]>0&&$ResourceFoodArrangementCountSelect[$resource]<36){
								$food=1;
								insertFoodConfToReservationWithDate($ResourceFoodArrangement[$resource],$ResourceFoodArrangementCountSelect[$resource],$FoodHalfFirst,$FoodHalfSecond,$resource,timeForDatabase(regexDateIsReal($_POST['beginDate']),$_POST['beginPeriod']));
								$ResourceFoodArrangementtemp=$ResourceFoodArrangement[$resource];
								$ResourceFoodArrangementCountSelectTemp=$ResourceFoodArrangementCountSelect[$resource];
								
							}
						}
					}
					
					$userSession2 = ServiceLocator::GetServer()->GetUserSession();
					if (isset($userSession2->UserId)){
						if(isset($_POST['compname'])&&isset($_POST['personid'])&&isset($_POST['billingaddress'])&&isset($_POST['reference'])){
							$compname=regexUserInfoText($_POST['compname']);
							$personid=regexUserInfoText($_POST['personid']);
							$billingaddress=regexUserInfoText($_POST['billingaddress']);
							$reference=regexUserInfoText($_POST['reference']);
							$additionalInfo=regexUserInfoText($_POST['additionalinfo']);
							addUserAddonInfo($userSession2->UserId,$compname,$personid,$billingaddress,$reference,$additionalInfo);
							$daycountlist="";
							if($food==1){
								$daycountlist="";
								foreach($reservation->Instances() as $tempInstance){
									$dayCountlist[]=$tempInstance->StartDate(); //make an array of the dates
								}
								$restime=explode(" ",$dayCountlist[0]); //get the reservation start time
								$restime=timeFromDatabase($restime[0],$restime[1]);
								$restime=date('H.i', strtotime($restime));
								foreach($ResourceArrangementAR as $resource){
								$foodInfo=getFoodArrangementInfo($resource);
									$seriesid=MatchDateAndResource($resource,timeForDatabase(regexDateIsReal($_POST['beginDate']),$_POST['beginPeriod']));
								}
								mailToCatering(1,$foodInfo,$ResourceFoodArrangementCountSelectTemp,$FoodHalfFirst,$FoodHalfSecond,$userSession2->UserId,$dayCountlist,$restime,$seriesid);
							}
						}else{
						}
						
					}
				}
				if(isset($_POST['SelectPublicTime'])&&isset($_POST['SelectPublicEndTime'])){
					$PublicTime=$_POST['SelectPublicTime'];
					$PublicEndTime=$_POST['SelectPublicEndTime'];
				}else{
					$PublicTime=NULL;
					$PublicEndTime=NULL;
				}
				if(isset($_POST['IsPublicEvent'])){
					$publicStatus=1;
				}else{
					$publicStatus=0;
				}
				if(isset($_POST['RoomForOtherPresenter'])){
					$RoomForOtherPresenter=1;
				}else{
					$RoomForOtherPresenter=0;
				}
				
				if(isset($PublicTime)&&isset($PublicEndTime)&&isset($publicStatus)&&isset($RoomForOtherPresenter)){
					insertEventPublicWithDate($publicStatus,$PublicTime,$PublicEndTime,$RoomForOtherPresenter,$resource,timeForDatabase(regexDateIsReal($_POST['beginDate']),$_POST['beginPeriod']));
				}
				
				// MODIFIED CODE STOPS HERE
				
				$this->Display('Ajax/reservation/save_successful.tpl');
			}
			else
			{
				$this->Display('Ajax/reservation/save_failed.tpl');
			}
		} catch (Exception $ex)
		{
			Log::Error('ReservationSavePage - Critical error saving reservation: %s', $ex);
			$this->Display('Ajax/reservation/reservation_error.tpl');
		}
	}

	public function SetSaveSuccessfulMessage($succeeded)
	{
		$this->_reservationSavedSuccessfully = $succeeded;
	}

	public function SetReferenceNumber($referenceNumber)
	{
		$this->Set('ReferenceNumber', $referenceNumber);
	}

	public function SetRequiresApproval($requiresApproval)
	{
		$this->Set('RequiresApproval', $requiresApproval);
	}

	public function SetErrors($errors)
	{
		$this->Set('Errors', $errors);
	}

	public function SetWarnings($warnings)
	{
		// set warnings variable
	}

	public function GetReservationAction()
	{
		return $this->GetForm(FormKeys::RESERVATION_ACTION);
	}

	public function GetReferenceNumber()
	{
		return $this->GetForm(FormKeys::REFERENCE_NUMBER);
	}

	public function GetUserId()
	{
		return $this->GetForm(FormKeys::USER_ID);
	}

	public function GetResourceId()
	{
		return $this->GetForm(FormKeys::RESOURCE_ID);
	}

	public function GetTitle()
	{
		return $this->GetForm(FormKeys::RESERVATION_TITLE);
	}

	public function GetDescription()
	{
		return $this->GetForm(FormKeys::DESCRIPTION);
	}

	public function GetStartDate()
	{
		return $this->GetForm(FormKeys::BEGIN_DATE);
	}

	public function GetEndDate()
	{
		return $this->GetForm(FormKeys::END_DATE);
	}

	public function GetStartTime()
	{
		return $this->GetForm(FormKeys::BEGIN_PERIOD);
	}

	public function GetEndTime()
	{
		return $this->GetForm(FormKeys::END_PERIOD);
	}

	public function GetResources()
	{
		$resources = $this->GetForm(FormKeys::ADDITIONAL_RESOURCES);
		if (is_null($resources))
		{
			return array();
		}

		if (!is_array($resources))
		{
			return array($resources);
		}

		return $resources;
	}

	public function GetRepeatOptions()
	{
		return $this->_presenter->GetRepeatOptions();
	}

	public function GetRepeatType()
	{
		return $this->GetForm(FormKeys::REPEAT_OPTIONS);
	}

	public function GetRepeatInterval()
	{
		return $this->GetForm(FormKeys::REPEAT_EVERY);
	}

	public function GetRepeatWeekdays()
	{
		$days = array();

		$sun = $this->GetForm(FormKeys::REPEAT_SUNDAY);
		if (!empty($sun))
		{
			$days[] = 0;
		}

		$mon = $this->GetForm(FormKeys::REPEAT_MONDAY);
		if (!empty($mon))
		{
			$days[] = 1;
		}

		$tue = $this->GetForm(FormKeys::REPEAT_TUESDAY);
		if (!empty($tue))
		{
			$days[] = 2;
		}

		$wed = $this->GetForm(FormKeys::REPEAT_WEDNESDAY);
		if (!empty($wed))
		{
			$days[] = 3;
		}

		$thu = $this->GetForm(FormKeys::REPEAT_THURSDAY);
		if (!empty($thu))
		{
			$days[] = 4;
		}

		$fri = $this->GetForm(FormKeys::REPEAT_FRIDAY);
		if (!empty($fri))
		{
			$days[] = 5;
		}

		$sat = $this->GetForm(FormKeys::REPEAT_SATURDAY);
		if (!empty($sat))
		{
			$days[] = 6;
		}

		return $days;
	}

	public function GetRepeatMonthlyType()
	{
		return $this->GetForm(FormKeys::REPEAT_MONTHLY_TYPE);
	}

	public function GetRepeatTerminationDate()
	{
		return $this->GetForm(FormKeys::END_REPEAT_DATE);
	}

	public function GetSeriesUpdateScope()
	{
		return $this->GetForm(FormKeys::SERIES_UPDATE_SCOPE);
	}

	/**
	 * @return int[]
	 */
	public function GetParticipants()
	{
		$participants = $this->GetForm(FormKeys::PARTICIPANT_LIST);
		if (is_array($participants))
		{
			return $participants;
		}

		return array();
	}

	/**
	 * @return int[]
	 */
	public function GetInvitees()
	{
		$invitees = $this->GetForm(FormKeys::INVITATION_LIST);
		if (is_array($invitees))
		{
			return $invitees;
		}

		return array();
	}

	public function GetInvitedGuests()
	{
		$invitees = $this->GetForm(FormKeys::GUEST_INVITATION_LIST);
		if (is_array($invitees))
		{
			return $invitees;
		}

		return array();
	}

	public function GetParticipatingGuests()
	{
		$participants = $this->GetForm(FormKeys::GUEST_PARTICIPATION_LIST);
		if (is_array($participants))
		{
			return $participants;
		}

		return array();
	}

	/**
	 * @return AccessoryFormElement[]
	 */
	public function GetAccessories()
	{
		$accessories = $this->GetForm(FormKeys::ACCESSORY_LIST);
		if (is_array($accessories))
		{
			$af = array();

			foreach ($accessories as $a)
			{
				$af[] = new AccessoryFormElement($a);
			}
			return $af;
		}

		return array();
	}

	/**
	 * @return AttributeFormElement[]|array
	 */
	public function GetAttributes()
	{
		return AttributeFormParser::GetAttributes($this->GetForm(FormKeys::ATTRIBUTE_PREFIX));
	}

	/**
	 * @return UploadedFile[]
	 */
	public function GetAttachments()
	{
		if ($this->AttachmentsEnabled())
		{
			return $this->server->GetFiles(FormKeys::RESERVATION_FILE);
		}
		return array();
	}

	private function AttachmentsEnabled()
	{
		return Configuration::Instance()->GetSectionKey(ConfigSection::UPLOADS,
														ConfigKeys::UPLOAD_ENABLE_RESERVATION_ATTACHMENTS,
														new BooleanConverter());
	}
    
	public function HasStartReminder()
	{
		$val = $this->server->GetForm(FormKeys::START_REMINDER_ENABLED);
		return !empty($val);
	}

	public function GetStartReminderValue()
	{
		return $this->server->GetForm(FormKeys::START_REMINDER_TIME);
	}

	public function GetStartReminderInterval()
	{
		return $this->server->GetForm(FormKeys::START_REMINDER_INTERVAL);
	}

	public function HasEndReminder()
	{
		$val = $this->server->GetForm(FormKeys::END_REMINDER_ENABLED);
		return !empty($val);
	}

	public function GetEndReminderValue()
	{
		return $this->server->GetForm(FormKeys::END_REMINDER_TIME);
	}

	public function GetEndReminderInterval()
	{
		return $this->server->GetForm(FormKeys::END_REMINDER_INTERVAL);
	}

	public function GetAllowParticipation()
	{
		$val = $this->server->GetForm(FormKeys::ALLOW_PARTICIPATION);
		return !empty($val);
	}

	public function SetCanBeRetried($canBeRetried)
	{
		$this->Set('CanBeRetried', $canBeRetried);
	}

	public function SetRetryParameters($retryParameters)
	{
		$this->Set('RetryParameters', $retryParameters);
	}

	public function GetRetryParameters()
	{
		return ReservationRetryParameter::GetParamsFromForm($this->GetForm(FormKeys::RESERVATION_RETRY_PREFIX));
	}

	public function SetRetryMessages($messages)
	{
		$this->Set('RetryMessages', $messages);
	}

    public function SetCanJoinWaitList($canJoinWaitlist)
    {
        $this->Set('CanJoinWaitList', $canJoinWaitlist);
    }
}

class AccessoryFormElement
{
	public $Id;
	public $Quantity;

	public function __construct($formValue)
	{
		$idAndQuantity = $formValue;
		$y = explode('-', $idAndQuantity);
		$params = explode(',', $y[1]);
		$id = explode('=', $params[0]);
		$quantity = explode('=', $params[1]);
		$name = explode('=', $params[2]);

		$this->Id = $id[1];
		$this->Quantity = $quantity[1];
		$this->Name = urldecode($name[1]);
	}

	public static function Create($id, $quantity)
	{
		$element = new AccessoryFormElement("accessory-id=$id,quantity=$quantity,name=");
		return $element;
	}
}