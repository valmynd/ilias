<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once('./Services/Object/classes/class.ilObject2GUI.php');
include_once('./Modules/Portfolio/classes/class.ilObjPortfolio.php');
include_once('./Modules/Portfolio/classes/class.ilPortfolioPage.php');

/**
 * Portfolio view gui base class
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.com>
 * @version $Id$
 *
 * @ingroup ModulesPortfolio
 */
abstract class ilObjPortfolioBaseGUI extends ilObject2GUI
{				
	protected $user_id; // [int]
	protected $additional = array();
	protected $perma_link; // [string]		
	protected $page_id; // [int]
	protected $page_mode; // [string] preview|edit
	
	public function __construct($a_id = 0, $a_id_type = self::REPOSITORY_NODE_ID, $a_parent_node_id = 0)
	{
		global $ilUser;
		
		parent::__construct($a_id, $a_id_type, $a_parent_node_id);

		$this->user_id = $ilUser->getId();		
		
		$this->lng->loadLanguageModule("prtf");
		$this->lng->loadLanguageModule("user");		
	}
	
	protected function addLocatorItems()
	{
		global $ilLocator;
		
		if($this->object)
		{									
			$ilLocator->addItem($this->object->getTitle(),
				$this->ctrl->getLinkTarget($this, "view"));
		}		
				
		if($this->page_id)
		{								
			$page = $this->getPageInstance($this->page_id);
			$title = $page->getTitle();
			if($page->getType() == ilPortfolioPage::TYPE_BLOG)
			{
				$title = ilObject::_lookupTitle($title);
			}
			$this->ctrl->setParameterByClass($this->getPageGUIClassName(), "ppage", $this->page_id);	
			$ilLocator->addItem($title,
				$this->ctrl->getLinkTargetByClass($this->getPageGUIClassName(), "edit"));		
		}
	}	
	
	protected function determinePageCall()
	{
		// edit
		if(isset($_REQUEST["ppage"]))
		{			
			if(!$this->checkPermissionBool("write"))
			{
				$this->ctrl->redirect($this, "view");
			}
			
			$this->page_id = $_REQUEST["ppage"];
			$this->page_mode = "edit";
			$this->ctrl->setParameter($this, "ppage", $this->page_id);	
			return true;
		}
		// preview
		else
		{
			$this->page_id = $_REQUEST["user_page"];
			$this->page_mode = "preview";
			$this->ctrl->setParameter($this, "user_page", $this->page_id);			
			return false;
		}				
	}
	
	protected function handlePageCall($a_cmd)
	{		
		$this->tabs_gui->clearTargets();
		$this->tabs_gui->setBackTarget($this->lng->txt("back"),
			$this->ctrl->getLinkTarget($this, "view"));
		
		if(!$this->page_id)
		{
			$this->ctrl->redirect($this, "view");
		}

		$page_gui = $this->getPageGUIInstance($this->page_id);
		$ret = $this->ctrl->forwardCommand($page_gui);

		if ($ret != "" && $ret !== true)
		{									
			// preview (fullscreen)
			if($this->page_mode == "preview")
			{						
				// embedded call which did not generate any output (e.g. calendar month navigation)
				if($ret != ilPortfolioPageGUI::EMBEDDED_NO_OUTPUT)
				{
					// suppress (portfolio) notes for blog postings 
					$this->preview(false, $ret, ($a_cmd != "previewEmbedded"));
				}
				else
				{
					$this->preview(false);
				}
			}
			// edit
			else
			{
				$this->tpl->setContent($ret);
			}
		}			
	}
	
	/**
	* Set Additonal Information (used in public profile?)
	*
	* @param	array	$a_additional	Additonal Information
	*/
	public function setAdditional($a_additional)
	{
		$this->additional = $a_additional;
	}

	/**
	* Get Additonal Information.
	*
	* @return	array	Additonal Information
	*/
	public function getAdditional()
	{
		return $this->additional;
	}				
		
	/**
	 * Set custom perma link (used in public profile?)
	 * 
	 * @param string $a_link
	 */
	public function setPermaLink($a_link)
	{
		$this->perma_link = $a_link;
	}	
		
	
	//
	// CREATE/EDIT
	//
		
	protected function initEditCustomForm(ilPropertyFormGUI $a_form)
	{							
		// comments
		$comments = new ilCheckboxInputGUI($this->lng->txt("prtf_public_comments"), "comments");
		$a_form->addItem($comments);

		// profile picture
		$ppic = new ilCheckboxInputGUI($this->lng->txt("prtf_profile_picture"), "ppic");
		$a_form->addItem($ppic);

		$prfa_set = new ilSetting("prfa");
		if($prfa_set->get("banner"))
		{			
			include_once "Services/Form/classes/class.ilFileInputGUI.php";
			ilFileInputGUI::setPersonalWorkspaceQuotaCheck(true);	

			$dimensions = " (".$prfa_set->get("banner_width")."x".
				$prfa_set->get("banner_height").")";

			$img = new ilImageFileInputGUI($this->lng->txt("prtf_banner").$dimensions, "banner");
			$a_form->addItem($img);

			// show existing file
			$file = $this->object->getImageFullPath(true);
			if($file)
			{
				$img->setImage($file);
			}		
		}

		$bg_color = new ilColorPickerInputGUI($this->lng->txt("prtf_background_color"), "bg_color");
		$a_form->addItem($bg_color);

		$font_color = new ilColorPickerInputGUI($this->lng->txt("prtf_font_color"), "font_color");
		$a_form->addItem($font_color);								
	}
	
	protected function getEditFormCustomValues(array &$a_values)
	{
		$a_values["comments"] = $this->object->hasPublicComments();
		$a_values["ppic"] = $this->object->hasProfilePicture();
		$a_values["bg_color"] = $this->object->getBackgroundColor();
		$a_values["font_color"] = $this->object->getFontColor();
	}	
	
	public function updateCustom(ilPropertyFormGUI $a_form)
	{				
		$this->object->setPublicComments($a_form->getInput("comments"));
		$this->object->setProfilePicture($a_form->getInput("ppic"));
		$this->object->setBackgroundColor($a_form->getInput("bg_color"));
		$this->object->setFontcolor($a_form->getInput("font_color"));

		$prfa_set = new ilSetting("prfa");

		if($_FILES["banner"]["tmp_name"])
		{
			$this->object->uploadImage($_FILES["banner"]);
		}
		else if($prfa_set->get('banner') and $a_form->getItemByPostVar("banner")->getDeletionFlag())
		{
			$this->object->deleteImage();
		}			
	}
	
	
	//
	// PAGES
	//
	
	abstract protected function getPageInstance($a_page_id = null);
	
	abstract protected function getPageGUIInstance($a_page_id);
	
	abstract public function getPageGUIClassName();
		
	/**
	 * Show list of portfolio pages
	 */
	public function view()
	{
		global $ilToolbar, $ilSetting, $tree;
		
		if(!$this->checkPermissionBool("write"))
		{
			return;
		}
		
		$this->tabs_gui->activateTab("pages");

		$ilToolbar->addButton($this->lng->txt("prtf_add_page"),
			$this->ctrl->getLinkTarget($this, "addPage"));

		if(!$ilSetting->get('disable_wsp_blogs'))
		{
			$ilToolbar->addButton($this->lng->txt("prtf_add_blog"),
				$this->ctrl->getLinkTarget($this, "addBlog"));
		}

		$ilToolbar->addSeparator();

		$ilToolbar->addButton($this->lng->txt("export_html"),
			$this->ctrl->getLinkTarget($this, "export"));				
				
		include_once "Modules/Portfolio/classes/class.ilPortfolioPageTableGUI.php";
		$table = new ilPortfolioPageTableGUI($this, "view");
		
		// exercise portfolio?			
		include_once "Modules/Exercise/classes/class.ilObjExercise.php";			
		$exercises = ilObjExercise::findUserFiles($this->user_id, $this->object->getId());
		if($exercises)
		{
			$info = array();
			foreach($exercises as $exercise)
			{
				// #9988
				$active_ref = false;
				foreach(ilObject::_getAllReferences($exercise["obj_id"]) as $ref_id)
				{
					if(!$tree->isSaved($ref_id))
					{
						$active_ref = true;
						break;
					}
				}
				if($active_ref)
				{				
					$part = $this->getExerciseInfo($exercise["ass_id"], $table->dataExists());
					if($part)
					{
						$info[] = $part;
					}
				}
			}
			if(sizeof($info))
			{
				ilUtil::sendInfo(implode("<br />", $info));									
			}
		}
		
		$this->tpl->setContent($table->getHTML());
	}
	
	/**
	 * Show portfolio page creation form
	 */
	protected function addPage()
	{		
		$this->tabs_gui->clearTargets();
		$this->tabs_gui->setBackTarget($this->lng->txt("back"),
			$this->ctrl->getLinkTarget($this, "view"));

		$form = $this->initPageForm("create");
		$this->tpl->setContent($form->getHTML());
	}

	/**
	 * Init portfolio page form
	 *
	 * @param string $a_mode
	 * @return ilPropertyFormGUI
	 */
	public function initPageForm($a_mode = "create")
	{		
		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));

		// title
		$ti = new ilTextInputGUI($this->lng->txt("title"), "title");
		$ti->setMaxLength(200);
		$ti->setRequired(true);
		$form->addItem($ti);

		// save and cancel commands
		if ($a_mode == "create")
		{
			include_once "Services/Style/classes/class.ilPageLayout.php";
			$templates = ilPageLayout::activeLayouts(false, ilPageLayout::MODULE_PORTFOLIO);
			if($templates)
			{			
				$use_template = new ilRadioGroupInputGUI($this->lng->txt("prtf_use_page_layout"), "tmpl");
				$use_template->setRequired(true);
				$form->addItem($use_template);

				$opt = new ilRadioOption($this->lng->txt("none"), 0);
				$use_template->addOption($opt);

				foreach ($templates as $templ)
				{
					$templ->readObject();

					$opt = new ilRadioOption($templ->getTitle().$templ->getPreview(), $templ->getId());
					$use_template->addOption($opt);			
				}
			}
			
			$form->setTitle($this->lng->txt("prtf_add_page").": ".
				$this->object->getTitle());
			$form->addCommandButton("savePage", $this->lng->txt("save"));
			$form->addCommandButton("view", $this->lng->txt("cancel"));			
		}
		else
		{
			/* edit is done directly in table gui
			$form->setTitle($this->lng->txt("prtf_edit_page"));
			$form->addCommandButton("updatePage", $this->lng->txt("save"));
			$form->addCommandButton("view", $this->lng->txt("cancel"));
			*/			
		}
		
		return $form;
	}
		
	/**
	 * Create new portfolio page
	 */
	public function savePage()
	{	
		$form = $this->initPageForm("create");
		if ($form->checkInput() && $this->checkPermissionBool("write"))
		{
			include_once("Modules/Portfolio/classes/class.ilPortfolioPage.php");
			$page = $this->getPageInstance();
			$page->setType(ilPortfolioPage::TYPE_PAGE);		
			$page->setTitle($form->getInput("title"));		
			
			// use template as basis
			$layout_id = $form->getInput("tmpl");
			if($layout_id)
			{
				include_once("./Services/Style/classes/class.ilPageLayout.php");
				$layout_obj = new ilPageLayout($layout_id);
				$page->setXMLContent($layout_obj->getXMLContent());
			}
			
			$page->create();

			ilUtil::sendSuccess($this->lng->txt("prtf_page_created"), true);
			$this->ctrl->redirect($this, "view");
		}

		$this->tabs_gui->clearTargets();
		$this->tabs_gui->setBackTarget($this->lng->txt("back"),
			$this->ctrl->getLinkTarget($this, "view"));

		$form->setValuesByPost();
		$this->tpl->setContent($form->getHtml());
	}
	
	/**
	 * Show portfolio blog page creation form
	 */
	protected function addBlog()
	{		
		$this->tabs_gui->clearTargets();
		$this->tabs_gui->setBackTarget($this->lng->txt("back"),
			$this->ctrl->getLinkTarget($this, "view"));

		$form = $this->initBlogForm();
		$this->tpl->setContent($form->getHTML());
	}
	
	abstract protected function initBlogForm();
	
	abstract protected function saveBlog();	
	
	/**
	 * Save ordering of portfolio pages
	 */
	function savePortfolioPagesOrdering()
	{		
		if(!$this->checkPermissionBool("write"))
		{
			return;
		}

		if (is_array($_POST["order"]))
		{
			foreach ($_POST["order"] as $k => $v)
			{				
				$page = $this->getPageInstance(ilUtil::stripSlashes($k));				
				if($_POST["title"][$k])
				{
					$page->setTitle(ilUtil::stripSlashes($_POST["title"][$k]));
				}
				$page->setOrderNr(ilUtil::stripSlashes($v));
				$page->update();
			}
			ilPortfolioPage::fixOrdering($this->object->getId());
		}
		
		ilUtil::sendSuccess($this->lng->txt("msg_obj_modified"), true);
		$this->ctrl->redirect($this, "view");
	}

	/**
	 * Confirm portfolio deletion
	 */
	function confirmPortfolioPageDeletion()
	{		
		if (!is_array($_POST["prtf_pages"]) || count($_POST["prtf_pages"]) == 0)
		{
			ilUtil::sendInfo($this->lng->txt("no_checkbox"), true);
			$this->ctrl->redirect($this, "view");
		}
		else
		{
			include_once("./Services/Utilities/classes/class.ilConfirmationGUI.php");
			$cgui = new ilConfirmationGUI();
			$cgui->setFormAction($this->ctrl->getFormAction($this));
			$cgui->setHeaderText($this->lng->txt("prtf_sure_delete_portfolio_pages"));
			$cgui->setCancel($this->lng->txt("cancel"), "view");
			$cgui->setConfirm($this->lng->txt("delete"), "deletePortfolioPages");

			foreach ($_POST["prtf_pages"] as $id)
			{
				$page = $this->getPageInstance($id);
				$title = $page->getTitle();
				if($page->getType() == ilPortfolioPage::TYPE_BLOG)
				{
					$title = $this->lng->txt("obj_blog").": ".ilObject::_lookupTitle((int)$title);		
				}				
				$cgui->addItem("prtf_pages[]", $id, $title);
			}

			$this->tpl->setContent($cgui->getHTML());
		}
	}

	/**
	 * Delete portfolio pages
	 */
	function deletePortfolioPages()
	{				
		if(!$this->checkPermissionBool("write"))
		{
			return;
		}

		if (is_array($_POST["prtf_pages"]))
		{
			foreach ($_POST["prtf_pages"] as $id)
			{
				$page = $this->getPageInstance($id);
				$page->delete();
			}
		}
		ilUtil::sendSuccess($this->lng->txt("prtf_portfolio_page_deleted"), true);
		$this->ctrl->redirect($this, "view");
	}
	
	/**
	 * Show user page
	 */
	function preview($a_return = false, $a_content = false, $a_show_notes = true)
	{				
		// public profile
		if($_REQUEST["back_url"])
		{
			$back = $_REQUEST["back_url"];						
		}		
		// shared
		else if($_GET["baseClass"] != "ilPublicUserProfileGUI" && 
			$this->user_id && $this->user_id != ANONYMOUS_USER_ID)
		{
			if(!$this->checkPermissionBool("write"))
			{
				$this->ctrl->setParameterByClass("ilportfoliorepositorygui", "shr_id", $this->object->getOwner());
				$back = $this->ctrl->getLinkTargetByClass(array("ilpersonaldesktopgui", "ilportfoliorepositorygui"), "showOther");
				$this->ctrl->setParameterByClass("ilportfoliorepositorygui", "shr_id", "");
			}
			// owner
			else
			{
				$back = $this->ctrl->getLinkTarget($this, "view");
			}
		}
		$this->tpl->setTopBar($back);
		
		$portfolio_id = $this->object->getId();
		$user_id = $this->object->getOwner();
		
		$this->tabs_gui->clearTargets();
			
		$pages = ilPortfolioPage::getAllPages($portfolio_id);		
		$current_page = (int)$_GET["user_page"];
		
		// validate current page
		if($pages && $current_page)
		{
			$found = false;
			foreach($pages as $page)
			{
				if($page["id"] == $current_page)
				{
					$found = true;
					break;
				}
			}
			if(!$found)
			{
				$current_page = null;
			}
		}

		// display first page of portfolio if none given
		if(!$current_page && $pages)
		{
			$current_page = $pages;
			$current_page = array_shift($current_page);
			$current_page = $current_page["id"];
		}				
		
		// render tabs
		$current_blog = null;
		if(count($pages) > 1)
		{
			foreach ($pages as $p)
			{	
				if($p["type"] == ilPortfolioPage::TYPE_BLOG)
				{							
					// needed for blog comments (see below)
					if($p["id"] == $current_page)
					{
						$current_blog = (int)$p["title"];
					}									
					include_once "Modules/Blog/classes/class.ilObjBlog.php";
					$p["title"] = ilObjBlog::_lookupTitle($p["title"]);										
				}
				
				$this->ctrl->setParameter($this, "user_page", $p["id"]);
				$this->tabs_gui->addTab("user_page_".$p["id"],
					$p["title"],
					$this->ctrl->getLinkTarget($this, "preview"));				
			}
			
			$this->tabs_gui->activateTab("user_page_".$current_page);			
		}
		
		$this->ctrl->setParameter($this, "user_page", $current_page);
		
		if(!$a_content)
		{
			// get current page content
			$page_gui = $this->getPageGUIInstance($current_page);
			$page_gui->setEmbedded(true);

			$content = $this->ctrl->getHTML($page_gui);
		}
		else
		{
			$content = $a_content;
		}
		
		if($a_return && $this->checkPermissionBool("write"))
		{
			return $content;
		}
		
		// blog posting comments are handled within the blog
		$notes = "";
		if($a_show_notes && $this->object->hasPublicComments() && !$current_blog)
		{			
			include_once("./Services/Notes/classes/class.ilNoteGUI.php");			
			$note_gui = new ilNoteGUI($portfolio_id, $current_page, "pfpg");
			$note_gui->setRepositoryMode(false);			
			$note_gui->enablePublicNotes(true);
			$note_gui->enablePrivateNotes(false);
			$note_gui->enablePublicNotesDeletion($this->user_id == $user_id);
						
			$next_class = $this->ctrl->getNextClass($this);
			if ($next_class == "ilnotegui")
			{
				$notes = $this->ctrl->forwardCommand($note_gui);
			}
			else
			{
				$notes = $note_gui->getNotesHTML();
			}
		}
			
		if($this->perma_link === null)
		{			
			include_once('Services/PermanentLink/classes/class.ilPermanentLinkGUI.php');
			if($this->getType() == "prtf")
			{
				$plink = new ilPermanentLinkGUI($this->getType(), $this->object->getId(), "_".$current_page);
			}
			else
			{
				$plink = new ilPermanentLinkGUI($this->getType(), $this->object->getRefId());
			}
			$plink = $plink->getHTML();		
		}
		else
		{
			$plink = $this->perma_link;
		}
		
		self::renderFullscreenHeader($this->object, $this->tpl, $user_id);
		
		// wiki/forum will set locator items
		$this->tpl->setVariable("LOCATOR", "");
		
		// #10717
		$this->tpl->setContent($content.
			'<div class="ilClearFloat">'.$notes.$plink.'</div>');			
		$this->tpl->setFrameFixedWidth(true);
		
		echo $this->tpl->show("DEFAULT", true, true);
		exit();
	}
	
	/**
	 * Render banner, user name
	 * 
	 * @param object  $a_tpl
	 * @param int $a_user_id 
	 * @param bool $a_export_path
	 */
	public static function renderFullscreenHeader($a_portfolio, $a_tpl, $a_user_id, $a_export = false)
	{		
		$name = ilObjUser::_lookupName($a_user_id);
		$name = $name["lastname"].", ".($t = $name["title"] ? $t . " " : "").$name["firstname"];
		
		// show banner?
		$banner = $banner_width = $banner_height = false;
		$prfa_set = new ilSetting("prfa");
		if($prfa_set->get("banner"))
		{		
			$banner = $a_portfolio->getImageFullPath();
			$banner_width = $prfa_set->get("banner_width");
			$banner_height = $prfa_set->get("banner_height");
			if($a_export)
			{
				$banner = basename($banner);
			}
		}
		
		// profile picture
		$ppic = null;
		if($a_portfolio->hasProfilePicture())
		{
			$ppic = ilObjUser::_getPersonalPicturePath($a_user_id, "big");
			if($a_export)
			{
				$ppic = basename($ppic);
			}
		}
		
		include_once("./Services/User/classes/class.ilUserUtil.php");
		$a_tpl->setFullscreenHeader($a_portfolio->getTitle(), 
			$name, 	
			$ppic,
			$banner,
			$a_portfolio->getBackgroundColor(),
			$a_portfolio->getFontColor(),
			$banner_width,
			$banner_height,
			$a_export);
		$a_tpl->setBodyClass("std ilExternal ilPortfolio");
	}
			
	function export()
	{
		include_once "Modules/Portfolio/classes/class.ilPortfolioHTMLExport.php";
		$export = new ilPortfolioHTMLExport($this, $this->object);
		$zip = $export->buildExportFile();
		
	    ilUtil::deliverFile($zip, $this->object->getTitle().".zip", '', false, true);
	}
	
	
	/**
	 * Select target portfolio for page(s) copy
	 */
	function copyPageForm($a_form = null)
	{		
		if (!is_array($_POST["prtf_pages"]) || count($_POST["prtf_pages"]) == 0)
		{
			ilUtil::sendInfo($this->lng->txt("no_checkbox"), true);
			$this->ctrl->redirect($this, "view");
		}
		else
		{			
			if(!$a_form)
			{
				$a_form = $this->initCopyPageForm();
			}
		
			foreach($_POST["prtf_pages"] as $page_id)
			{
				$item = new ilHiddenInputGUI("prtf_pages[]");
				$item->setValue($page_id);
				$a_form->addItem($item);
			}
			
			$this->tpl->setContent($a_form->getHTML());
		}		
	}
	
	function copyPage()
	{				
		$form = $this->initCopyPageForm();
		if($form->checkInput())
		{
			// existing
			if($form->getInput("target") == "old")
			{
				$portfolio_id = $form->getInput("prtf");
				$portfolio = new ilObjPortfolio($portfolio_id, false);				
			}
			// new
			else
			{
				$portfolio = new ilObjPortfolio();
				$portfolio->setTitle($form->getInput("title"));
				$portfolio->create();		
				$portfolio_id = $portfolio->getId();
			}
			
			// copy page(s)
			foreach($_POST["prtf_pages"] as $page_id)
			{				
				$source = $this->getPageInstance($page_id);
				$target = $this->getPageInstance();
				$target->setXMLContent($source->copyXmlContent());
				$target->setType($source->getType());
				$target->setTitle($source->getTitle());
				$target->create();							
			}
				
			ilUtil::sendSuccess($this->lng->txt("prtf_pages_copied"), true);
			$this->ctrl->redirect($this, "view");
		}
		
		$form->setValuesByPost();
		$this->copyPageForm($form);
	}
	
	function initCopyPageForm()
	{		
		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();
		$form->setFormAction($this->ctrl->getFormAction($this));		
		$form->setTitle($this->lng->txt("prtf_copy_page"));			

		$tgt = new ilRadioGroupInputGUI($this->lng->txt("target"), "target");
		$tgt->setRequired(true);
		$form->addItem($tgt);

		$all = ilObjPortfolio::getPortfoliosOfUser($this->user_id);			
		if(sizeof($all) > 1)
		{			
			$old = new ilRadioOption($this->lng->txt("prtf_existing_portfolio"), "old");
			$tgt->addOption($old);

			$options = array();
			foreach($all as $item)
			{
				if($item["id"] != $this->object->getId())
				{
					$options[$item["id"]] = $item["title"]; 
				}
			}				
			$prtf = new ilSelectInputGUI($this->lng->txt("portfolio"), "prtf");
			$prtf->setRequired(true);
			$prtf->setOptions($options);
			$old->addSubItem($prtf);
		}

		$new = new ilRadioOption($this->lng->txt("prtf_new_portfolio"), "new");
		$tgt->addOption($new);

		// 1st page
		$tf = new ilTextInputGUI($this->lng->txt("title"), "title");
		$tf->setMaxLength(128);
		$tf->setSize(40);
		$tf->setRequired(true);
		$new->addSubItem($tf);		

		$form->addCommandButton("copyPage", $this->lng->txt("save"));
		$form->addCommandButton("view", $this->lng->txt("cancel"));
		
		return $form;
	}
}

?>