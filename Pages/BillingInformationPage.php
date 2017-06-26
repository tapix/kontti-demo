<?php
/**
Copyright 2011-2016 Nick Korbel

This file is part of Booked Scheduler.

Booked Scheduler is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

Booked Scheduler is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Booked Scheduler.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/BillingInformationPresenter.php');


class BillingInformationPage extends Page
{
	public function __construct()
	{
		parent::__construct('BillingInfo');
	}

	public function PageLoad()
	{
		$userSession = ServiceLocator::GetServer()->GetUserSession();
		$billinginfo=getAllUserAddonInfo($userSession->UserId);
		$this->Set('compname',$billinginfo['compname']);
		$this->Set('personid',$billinginfo['personid']);
		$this->Set('billingaddress',$billinginfo['billingaddress']);
		$this->Set('reference',$billinginfo['reference']);
		$this->Set('additionalInfo',$billinginfo['additionalinfo']);
		$this->Display('MyAccount/BillingInformation.tpl');
	}

}