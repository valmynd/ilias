<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Render add new item selector
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @version $Id: class.ilContainerGUI.php 43751 2013-07-30 10:07:45Z jluetzen $
 * 
 * @ingroup ServicesObject
 */
class ilObjectAddNewItemGUI
{
	protected $mode; // [int]
	protected $parent_ref_id; // [int]	
	protected $disabled_object_types; // [array]
	protected $sub_objects; // [array]
	protected $url_creation_callback; // [int]
	protected $url_creation; // [string]
			
	/**
	 * Constructor
	 * 
	 * @param int $a_parent_ref_id
	 * @return ilObjectAddNewItemGUI
	 */
	public function __construct($a_parent_ref_id)
	{
		global $lng;
		
		$this->parent_ref_id = (int)$a_parent_ref_id;
		$this->mode = ilObjectDefinition::MODE_REPOSITORY;
				
		$lng->loadLanguageModule("rep");		
		$lng->loadLanguageModule("cntr");				
	}
	
	public function setMode($a_mode)
	{
		$this->mode = (int)$a_mode;
	}
	
	/**
	 * Set object types which may not be created
	 * 
	 * @param array $a_types
	 */
	public function setDisabledObjectTypes(array $a_types)
	{
		$this->disabled_object_types = $a_types;
	}
	
	/**
	 * Set after creation callback
	 * 
	 * @param int $a_ref_id
	 */
	public function setAfterCreationCallback($a_ref_id)
	{
		$this->url_creation_callback = $a_ref_id;
	}
	
	/**
	 * Set (custom) url for object creation
	 * 
	 * @param string $a_url
	 */
	public function setCreationUrl($a_url)
	{
		$this->url_creation = $a_url;
	}
	
	/**
	 * Parse creatable sub objects for personal workspace
	 * 
	 * Grouping is not supported here, order is alphabetical (!)
	 *
	 * @return bool
	 */
	protected function parsePersonalWorkspace()
	{
		global $objDefinition, $lng, $ilSetting;
		
		$this->sub_objects = array();
		
		$settings_map = array("blog" => "blogs",
				"file" => "files",
				"tstv" => "certificates",
				"excv" => "certificates",
				"webr" => "links");
	
		$subtypes = $objDefinition->getCreatableSubObjects("wfld", ilObjectDefinition::MODE_WORKSPACE);		
		if (count($subtypes) > 0)
		{					
			foreach (array_keys($subtypes) as $type)
			{												
				if (isset($settings_map[$type]) && 
					$ilSetting->get("disable_wsp_".$settings_map[$type]))
				{
					continue;
				}
				
				$this->sub_objects[] = array("type" => "object",
					"value" => $type,
					"title" => $lng->txt("wsp_type_".$type));
			}
		}						
		
		$this->sub_objects = ilUtil::sortArray($this->sub_objects, "title", 1);		
		
		return (bool)sizeof($this->sub_objects);
	}
	
	/**
	 * Parse creatable sub objects for repository incl. grouping
	 * 
	 * @return bool
	 */
	protected function parseRepository()
	{
		global $objDefinition, $lng, $ilAccess;
		
		$this->sub_objects = array();
		
		if(!is_array($this->disabled_object_types))
		{
			$this->disabled_object_types = array();	
		}
		$this->disabled_object_types[] = "rolf";						
		
		$parent_type = ilObject::_lookupType($this->parent_ref_id, true);
		$subtypes = $objDefinition->getCreatableSubObjects($parent_type, $this->mode);		
		if (count($subtypes) > 0)
		{						
			// grouping of object types

			$grp_map = $pos_group_map = array();

			include_once("Services/Repository/classes/class.ilObjRepositorySettings.php");
			foreach(ilObjRepositorySettings::getNewItemGroupSubItems() as $grp_id => $subitems)
			{
				foreach($subitems as $subitem)
				{
					$grp_map[$subitem] = $grp_id;
				}
			}

			$group_separators = array();
			$pos_group_map[0] = $lng->txt("rep_new_item_group_other");		
			$old_grp_id = 0;
			foreach(ilObjRepositorySettings::getNewItemGroups() as $item)
			{
				if($item["type"] == ilObjRepositorySettings::NEW_ITEM_GROUP_TYPE_GROUP)
				{
					$pos_group_map[$item["id"]] = $item["title"];
				}
				else if($old_grp_id)
				{
					$group_separators[] = $old_grp_id;
				}
				$old_grp_id = $item["id"];
			}				

			$current_grp = null;
			foreach ($subtypes as $type => $subitem)
			{								
				if (!in_array($type, $this->disabled_object_types))
				{				
					// #9950
					if ($ilAccess->checkAccess("create_".$type, "", $this->parent_ref_id, $parent_type))
					{
						// if only assigned - do not add groups
						if(sizeof($pos_group_map) > 1)
						{
							$obj_grp_id = (int)$grp_map[$type];
							if($obj_grp_id !== $current_grp)
							{
								// add seperator after last group?
								if(in_array($current_grp, $group_separators))
								{
									$this->sub_objects[] = array("type" => "column_separator");		
								}								 
								
								$title = $pos_group_map[$obj_grp_id];

								$this->sub_objects[] = array("type" => "group",
									"title" => $title);		

								$current_grp = $obj_grp_id;
							}
						}

						$title = $lng->txt("obj_".$type);
						if ($subitem["plugin"])
						{
							include_once("./Services/Component/classes/class.ilPlugin.php");
							$title = ilPlugin::lookupTxt("rep_robj", $type, "obj_".$type);
						}							
						$this->sub_objects[] = array("type" => "object",
							"value" => $type,
							"title" => $title);							
					}
				}				
			}
		}		
		
		return (bool)sizeof($this->sub_objects);
	}
	
	/**
	 * Get rendered html of sub object list
	 * 
	 * @return string
	 */
	protected function getHTML()
	{
		global $ilCtrl;
				
		if($this->mode != ilObjectDefinition::MODE_WORKSPACE && !isset($this->url_creation))
		{
			$base_url = "ilias.php?baseClass=ilRepositoryGUI&ref_id=".$this->parent_ref_id."&cmd=create";
		}
		else
		{
			$base_url = $this->url_creation;
		}
		$base_url = $ilCtrl->appendRequestTokenParameterString($base_url);	
		
		if($this->url_creation_callback)
		{
			$base_url .= "&crtcb=".$this->url_creation_callback;
		}
		
		include_once("./Services/UIComponent/GroupedList/classes/class.ilGroupedListGUI.php");
		$gl = new ilGroupedListGUI();

		foreach ($this->sub_objects as $item)
		{
			switch($item["type"])
			{
				case "column_separator":
					$gl->nextColumn();
					break;

				/*
				case "separator":
					$gl->addSeparator();
					break;
				*/

				case "group":				
					$gl->addGroupHeader($item["title"]);
					break;

				case "object":
					$type = $item["value"];

					$path = ilObject::_getIcon('', 'tiny', $type);
					$icon = ($path != "")
						? ilUtil::img($path)." "
						: "";
					
					$url = $base_url . "&new_type=".$type;

					$ttip = ilHelp::getObjCreationTooltipText($type);

					$gl->addEntry($icon.$item["title"], $url, "_top", "", "",
						$type, $ttip, "bottom center", "top center", false);						

					break;					
			}
		}
		
		return $gl->getHTML();
	}
	
	/**
	 * Add new item selection to current page incl. toolbar (trigger) and overlay
	 */
	public function render()
	{
		global $ilToolbar, $tpl, $lng;
						
		if($this->mode == ilObjectDefinition::MODE_WORKSPACE)
		{
			if (!$this->parsePersonalWorkspace())
			{
				return;
			}	
		}
		else if(!$this->parseRepository())
		{
			return;
		}
				
		$ov_id = "il_add_new_item_ov";
		$ov_trigger_id = $ov_id."_tr";		
		
		include_once "Services/UIComponent/Overlay/classes/class.ilOverlayGUI.php";
		$ov = new ilOverlayGUI($ov_id);
//		$ov->setAnchor($ov_trigger_id, "tl", "tr");
//		$ov->setTrigger($ov_trigger_id, "click", $ov_trigger_id);
		$ov->add();

		// trigger
		$ov->addTrigger($ov_trigger_id, "click", $ov_trigger_id, false, "tl", "tr");

		// toolbar
		$ilToolbar->addButton($lng->txt("cntr_add_new_item"), "#", "", "", 
			"", $ov_trigger_id, 'submit emphsubmit');
			
		// css?
		$tpl->setVariable("SELECT_OBJTYPE_REPOS",
			'<div id="'.$ov_id.'" style="display:none;" class="ilOverlay">'.
			$this->getHTML().'</div>');	
	}
}

?>