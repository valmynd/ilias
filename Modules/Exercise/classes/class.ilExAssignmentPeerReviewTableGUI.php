<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './Services/Table/classes/class.ilTable2GUI.php';
include_once './Modules/Exercise/classes/class.ilExAssignment.php';
include_once './Services/Rating/classes/class.ilRatingGUI.php';

/**
 * List all peers to be reviewed for user
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @ingroup ModulesExercise
 */
class ilExAssignmentPeerReviewTableGUI extends ilTable2GUI
{
	protected $ass; // [ilExAssignment]
	protected $user_id; // [int]
	protected $peer_data; // [array]
	protected $read_only; // [array]

	/**
	 * Constructor
	 *
	 * @param ilObject $a_parent_obj
	 * @param string $a_parent_cmd
	 * @param ilExAssignment $a_ass
	 * @param int $a_user_id
	 * @param array $a_peer_data
	 * @param string $a_title
	 * @param string $a_cancel_cmd
	 * @param bool $a_read_only
	 */
	public function  __construct($a_parent_obj, $a_parent_cmd, ilExAssignment $a_ass, $a_user_id, array $a_peer_data, $a_title, $a_cancel_cmd, $a_read_only = false)
	{
		global $ilCtrl;
				
		$this->ass = $a_ass;
		$this->user_id = $a_user_id;
		$this->peer_data = $a_peer_data;
		$this->read_only = $a_read_only;
		
		parent::__construct($a_parent_obj, $a_parent_cmd);
		
		$this->setLimit(9999);
	
		$this->addColumn($this->lng->txt("id"), "seq");
		if(!$this->read_only)
		{
			$this->addColumn($this->lng->txt("exc_submission"), "");
		}
		$this->addColumn($this->lng->txt("exc_peer_review_rating"), "mark");
		$this->addColumn($this->lng->txt("exc_peer_review_comment"), "");
		$this->addColumn($this->lng->txt("last_update"), "tstamp");
		
		$this->setDefaultOrderField("tstamp");
						
		$this->setRowTemplate("tpl.exc_peer_review_row.html", "Modules/Exercise");
		$this->setFormAction($ilCtrl->getFormAction($a_parent_obj, $a_parent_cmd));

		$this->setTitle($a_ass->getTitle().": ".$this->lng->txt("exc_peer_review")." - ".$this->lng->txt($a_title));
		
		if(!$this->read_only)
		{
			$this->addCommandButton("updatePeerReview", $this->lng->txt("save"));
		}
		$this->addCommandButton($a_cancel_cmd, $this->lng->txt("cancel"));
		
		$this->disable("numinfo");
		
		$this->getItems();
	}
	
	protected function getItems()
	{
		$data = array();
				
		foreach($this->peer_data as $idx => $item)
		{
			$row = array();
			
			$row["seq"] = $idx+1;
			$row["giver_id"] = $item["giver_id"];
			$row["peer_id"] = $item["peer_id"];
			$row["submission"] = "";
			$row["mark"] = (int)round(ilRating::getRatingForUserAndObject($this->ass->getId(), 
				"ass", $item["peer_id"], "peer", $item["giver_id"]));
			$row["comment"] = $item["pcomment"];
			$row["tstamp"] = $item["tstamp"];					
			
			$data[] = $row;
		}
		
		$this->setData($data);
	}
	
	public function numericOrdering($a_field) 
	{
		if(in_array($a_field, array("mark", "tstamp")))
		{
			return true;
		}
		return false;
	}

	protected function fillRow($a_set)
	{		
		global $ilCtrl;
					
		$this->tpl->setVariable("VAL_SEQ", $a_set["seq"]);		
			
		if($a_set["tstamp"])
		{
			$a_set["tstamp"] = ilDatePresentation::formatDate(new ilDateTime($a_set["tstamp"], IL_CAL_DATETIME));
		}
		$this->tpl->setVariable("VAL_TSTAMP", $a_set["tstamp"]);	
		
		
		// rating
		$ilCtrl->setParameter($this->parent_obj, "peer_id", $a_set["peer_id"]);		
		$rating = new ilRatingGUI();
		$rating->setObject($this->ass->getId(), "ass", $a_set["peer_id"], "peer");
		$rating->setUserId($a_set["giver_id"]);
		$this->tpl->setVariable("ID_RATING", "rtr_".$a_set["peer_id"]);
		if(!$this->read_only)
		{
			$this->tpl->setVariable("VAL_RATING", $rating->getHTML(false, true, 
				"il.ExcPeerReview.saveComments(".$a_set["peer_id"].", %rating%)"));	
		}
		else
		{
			$this->tpl->setVariable("VAL_RATING", $rating->getHTML(false, false));	
		}
		$ilCtrl->setParameter($this->parent_obj, "peer_id", "");		
				
		
		// submission
		
		if(!$this->read_only)
		{
			$ilCtrl->setParameter($this->parent_obj, "seq", $a_set["seq"]);

			$file_info = ilExAssignment::getDownloadedFilesInfoForTableGUIS($this->parent_obj, $this->ass->getExerciseId(), $this->ass->getType(), $this->ass->getId(), $a_set["peer_id"]);

			$ilCtrl->setParameter($this->parent_obj, "seq", "");

			$this->tpl->setVariable("VAL_LAST_SUBMISSION", $file_info["last_submission"]["value"]);
			$this->tpl->setVariable("TXT_LAST_SUBMISSION", $file_info["last_submission"]["txt"]);

			$this->tpl->setVariable("TXT_SUBMITTED_FILES", $file_info["files"]["txt"]);
			$this->tpl->setVariable("VAL_SUBMITTED_FILES", $file_info["files"]["count"]);

			if($file_info["files"]["download_url"])
			{
				$this->tpl->setCurrentBlock("download_link");
				$this->tpl->setVariable("LINK_DOWNLOAD", $file_info["files"]["download_url"]);
				$this->tpl->setVariable("TXT_DOWNLOAD", $file_info["files"]["download_txt"]);		
				$this->tpl->parseCurrentBlock();
			}

			if($file_info["files"]["download_new_url"])
			{
				$this->tpl->setCurrentBlock("download_link");
				$this->tpl->setVariable("LINK_NEW_DOWNLOAD", $file_info["files"]["download_new_url"]);
				$this->tpl->setVariable("TXT_NEW_DOWNLOAD", $file_info["files"]["download_new_txt"]);		
				$this->tpl->parseCurrentBlock();
			}
			
			
			$this->tpl->setCurrentBlock("pcomment_edit_bl");				
			$idx = $a_set["giver_id"]."__".$a_set["peer_id"];
			$this->tpl->setVariable("VAL_ID", $idx);		
			$this->tpl->setVariable("VAL_PCOMMENT_EDIT", $a_set["comment"]);	
			$this->tpl->parseCurrentBlock();	
		}				
		else
		{
			$this->tpl->setCurrentBlock("pcomment_static_bl");
			$this->tpl->setVariable("VAL_PCOMMENT_STATIC", $a_set["comment"]);		
			$this->tpl->parseCurrentBlock();	
		}				
	}
}

?>