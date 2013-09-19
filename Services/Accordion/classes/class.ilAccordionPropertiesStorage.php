<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* Saves (mostly asynchronously) user properties of accordions
* 
* @author Alex Killing <alex.killing@gmx.de>
* @version $Id$
* @ingroup ServicesAccordion
* @ilCtrl_Calls ilAccordionPropertiesStorage:
*/
class ilAccordionPropertiesStorage
{
	var $properties = array (
		"opened" => array("storage" => "session")
		);
	
	/**
	* execute command
	*/
	function &executeCommand()
	{
		global $ilUser, $ilCtrl;
		
		$cmd = $ilCtrl->getCmd();
//		$next_class = $this->ctrl->getNextClass($this);

		$this->$cmd();
	}
	
	/**
	 * Show Filter
	 */
	function setOpenedTab()
	{
		global $ilUser;
		
		if ($_GET["user_id"] == $ilUser->getId())
		{
			$this->storeProperty($_GET["accordion_id"], $_GET["user_id"],
				"opened", $_GET["tab_nr"]);
		}
	}
	
	/**
	* Store property in session or db
	*/
	function storeProperty($a_table_id, $a_user_id, $a_property,
		$a_value)
	{
		global $ilDB;
		
		switch ($this->properties[$a_property]["storage"])
		{
			case "session":
				$_SESSION["accordion"][$a_table_id][$a_user_id][$a_property]
					= $a_value;
				break;
				
			case "db":
/*
				$ilDB->replace("table_properties", array(
					"table_id" => array("text", $a_table_id),
					"user_id" => array("integer", $a_user_id),
					"property" => array("text", $a_property)),
					array(
					"value" => array("text", $a_value)
					));
*/
		}
	}
	
	/**
	* Get property in session or db
	*/
	function getProperty($a_table_id, $a_user_id, $a_property)
	{
		global $ilDB;

		switch ($this->properties[$a_property]["storage"])
		{
			case "session":
				return $_SESSION["accordion"][$a_table_id][$a_user_id][$a_property];
				break;
				
			case "db":
/*
				$set = $ilDB->query("SELECT value FROM table_properties ".
					" WHERE table_id = ".$ilDB->quote($a_table_id, "text").
					" AND user_id = ".$ilDB->quote($a_user_id, "integer").
					" AND property = ".$ilDB->quote($a_property, "text")
					);
				$rec  = $ilDB->fetchAssoc($set);
				return $rec["value"];
				break;
*/
		}
	}
	

}
?>
