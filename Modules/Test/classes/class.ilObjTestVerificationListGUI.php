<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* Class ilObjTestVerificationListGUI
*
* @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
* $Id: class.ilObjTestVerificationListGUI.php 44245 2013-08-17 11:15:45Z mbecker $
*
* @extends ilObjectListGUI
*/

include_once "Services/Object/classes/class.ilObjectListGUI.php";

class ilObjTestVerificationListGUI extends ilObjectListGUI
{
	/**
	* initialisation
	*/
	function init()
	{
		$this->delete_enabled = true;
		$this->cut_enabled = true;
		$this->copy_enabled = true;
		$this->subscribe_enabled = false;
		$this->link_enabled = false;
		$this->payment_enabled = false;
		$this->info_screen_enabled = false;
		$this->type = "tstv";
		$this->gui_class_name = "ilobjtestverificationgui";

		// general commands array
		include_once('./Modules/Test/classes/class.ilObjTestVerificationAccess.php');
		$this->commands = ilObjTestVerificationAccess::_getCommands();
	}
	
} // END class.ilObjTestVerificationListGUI
?>
