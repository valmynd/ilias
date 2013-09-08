<?php
/**
 * Created by JetBrains PhpStorm.
 * @author: Oskar Truffer <ot@studer-raimann.ch>
 * Date: 4/07/13
 * Time: 1:09 PM
 * To change this template use File | Settings | File Templates.
 * @ilCtrl_IsCalledBy ilObjOrgUnitGUI: ilAdministrationGUI
 * @ilCtrl_Calls ilObjOrgUnitGUI: ilPermissionGUI, ilPageObjectGUI, ilContainerLinkListGUI, ilObjUserGUI, ilObjUserFolderGUI
 * @ilCtrl_Calls ilObjOrgUnitGUI: ilInfoScreenGUI, ilObjStyleSheetGUI, ilCommonActionDispatcherGUI
 * @ilCtrl_Calls ilObjOrgUnitGUI: ilColumnGUI, ilObjectCopyGUI, ilUserTableGUI, ilDidacticTemplateGUI, ilExportGUI, illearningprogressgui, ilRepositorySearchGUI
 */

require_once("./Modules/Category/classes/class.ilObjCategoryGUI.php");
require_once("./Services/Container/classes/class.ilContainerGUI.php");
require_once("./Modules/OrgUnit/classes/class.ilObjOrgUnitTree.php");
require_once("./Modules/OrgUnit/classes/class.ilOrgUnitStaffTableGUI.php");
//require_once("./Modules/OrgUnit/classes/class.ilOrguUserPickerToolbarInputGUI.php");
require_once("./Modules/OrgUnit/classes/class.ilOrgUnitExporter.php");
require_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
require_once("./Services/Search/classes/class.ilRepositorySearchGUI.php");

class ilObjOrgUnitGUI extends ilObjCategoryGUI{

	/** @var  ilTabsGUI */
	protected $tabs_gui;

	protected $active_subtab;

	function __construct(){
		parent::ilContainerGUI(array(), $_GET["ref_id"], true, false);
		global $tpl, $ilCtrl, $ilDB;
		/**
		 * @var $tpl    ilTemplate
		 * @var $ilCtrl ilCtrl
		 * @var $ilDB ilDB
		 */
		$this->tpl = $tpl;
		$this->ctrl = $ilCtrl;
		$this->db = $ilDB;

		$this->lng->loadLanguageModule("orgu");
	}

    function isActiveAdministrationPanel()
    {
        return false;
    }

	public function executeCommand(){
		global $ilTabs, $lng;
		$ilTabs = new ilTabsGUI();
		$this->getTabs($ilTabs);
		$own_ex = array("illearningprogressgui", "illplistofprogressgui", "ilexportgui", "ilrepositorysearchgui");
		$cmdClass = $this->ctrl->getCmdClass();
		$cmd = $this->ctrl->getCmd();
		if(in_array($cmdClass, $own_ex)){
			$this->ownExecuteCommand();

		}else{
			parent::executeCommand();
		}
		//fighting the symptoms, TODO: find where this unnecessary empty target[2] comes from.
//		unset($ilTabs->target[2]);

		$this->showTreeObject();
		switch($cmd){
			case 'editTranslations':
				$ilTabs->setTabActive("settings");
				break;
			case 'infoScreen':
				$ilTabs->setTabActive("info_short");
				break;
		}
	}

	/**
	 * show possible sub objects selection list
	 */
	function showPossibleSubObjects()
	{
		include_once "Services/Object/classes/class.ilObjectAddNewItemGUI.php";
		$gui = new ilObjectAddNewItemGUI($this->object->getRefId());
		$gui->setMode(ilObjectDefinition::MODE_ADMINISTRATION);
		$gui->setCreationUrl($this->ctrl->getLinkTarget($this, "create"));
		$gui->render();
	}

	public function getAdminTabs(&$tabs_gui){
		return;
	}

	public function ownExecuteCommand(){
		global $lng;
		$cmdClass = $this->ctrl->getCmdClass();
		$cmd = $this->ctrl->getCmd();
		switch($cmdClass){
			case 'illearningprogressgui';
                $this->tabs_gui->setTabActive('orgu_staff');
			case 'illplistofprogressgui';
				if(!$this->checkPermForLP()){
					ilUtil::sendFailure($lng->txt("permission_denied"), true);
					$this->ctrl->redirectByClass("ilObjOrgUnitGUI", "render");
				}
				$this->prepareOutput();
				include_once './Services/Tracking/classes/class.ilLearningProgressGUI.php';
				if($user_id = $_GET["obj_id"]){
					$this->ctrl->saveParameterByClass("illearningprogressgui", "obj_id");
					$this->ctrl->saveParameterByClass("illearningprogressgui", "recursive");
					$did = new ilLearningProgressGUI(ilLearningProgressGUI::LP_CONTEXT_USER_FOLDER,USER_FOLDER_ID, $user_id);
					$this->ctrl->forwardCommand($did);
				}
				break;
			case 'ilexportgui':
				$this->prepareOutput();
				$this->extendExportGUI();
				$this->tabs_gui->setTabActive('export');
				include_once './Services/Export/classes/class.ilExportGUI.php';
				$exp = new ilExportGUI($this);
				$exp->addFormat('xml');
				$this->ctrl->forwardCommand($exp);
				break;
			case 'ilrepositorysearchgui':
				if($cmd == "addUserFromAutoComplete"){
					$this->prepareOutput();
					$this->addUserFromAutoCompleteObject();
					break;
				}
				$next = new ilRepositorySearchGUI();
				$this->ctrl->forwardCommand($next);
				break;
		}
		$this->addHeaderAction();
		return true;
	}

    /**
     * Init object creation form
     *
     * @param	string	$a_new_type
     * @return	ilPropertyFormGUI
     */
    protected function initCreateForm($a_new_type)
    {
        include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
        $form = new ilPropertyFormGUI();
        $form->setTarget("_top");
        $form->setFormAction($this->ctrl->getFormAction($this, "save"));
        $form->setTitle($this->lng->txt($a_new_type."_new"));

        // title
        $ti = new ilTextInputGUI($this->lng->txt("title"), "title");
        $ti->setMaxLength(128);
        $ti->setSize(40);
        $ti->setRequired(true);
        $form->addItem($ti);

        // description
        $ta = new ilTextAreaInputGUI($this->lng->txt("description"), "desc");
        $ta->setCols(40);
        $ta->setRows(2);
        $form->addItem($ta);

        $form->addCommandButton("save", $this->lng->txt($a_new_type."_add"));
        $form->addCommandButton("cancel", $this->lng->txt("cancel"));

        return $form;
    }

	public function editTranslationsObject(){
		parent::editTranslationsObject();
		global $ilTabs;
		$ilTabs->removeSubTab("settings_misc");
		$ilTabs->removeSubTab("settings_trans");
		$this->setContentSubTabs();
	}

	public function editExtIdObject(){
		global $tpl, $ilTabs;
		$ilTabs->setTabActive("settings");
		$form = $this->initEditExtIdForm();
		$tpl->setContent($form->getHTML());
	}

	public function updateExtIdObject(){
		global $tpl, $ilTabs;
		$ilTabs->setTabActive("settings");
		$form = $this->initEditExtIdForm();
		$form->setValuesByPost();
		if($form->checkInput()){
			$this->object->setImportId($form->getItemByPostVar("ext_id")->getValue());
			$this->object->update();
			ilUtil::sendSuccess($this->lng->txt("ext_id_updated"), true);
			$tpl->setContent($form->getHTML());
		}else{
			$tpl->setContent($form->getHTML());
		}
	}

	public function initEditExtIdForm(){
		$form = new ilPropertyFormGUI();
		$input = new ilTextInputGUI($this->lng->txt("ext_id"), "ext_id");
		$input->setValue($this->object->getImportId());
		$form->addItem($input);
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->addCommandButton("updateExtId", $this->lng->txt("save"));
		return $form;
	}


	private function extendExportGUI(){
		if($this->ctrl->getCmd() != "" || $this->object->getRefId() != ilObjOrgUnit::getRootOrgRefId())
			return;
		global $ilToolbar, $lng;
		/** @var ilToolbarGUI $toolbar */
		$toolbar = $ilToolbar;
		$toolbar->addButton($lng->txt("simple_xml"), $this->ctrl->getLinkTarget($this, "simpleExport"));
		$toolbar->addButton($lng->txt("simple_xls"), $this->ctrl->getLinkTarget($this, "simpleExportExcel"));
	}

	public function simpleExportObject(){
		$exporter = new ilOrgUnitExporter();
		$exporter->sendAndCreateSimpleExportFile();
	}

	public function simpleExportExcelObject(){
		$exporter = new ilOrgUnitExporter();
		$exporter->simpleExportExcel(ilObjOrgUnit::getRootOrgRefId());
	}

	protected function checkPermForLP(){
		$recursive = $_GET["recursive"];
		global $ilAccess, $ilUser;
		if(!$ilAccess->checkAccess("view_learning_progress".($recursive?"_rec":""), "", $_GET["ref_id"]))
			return false;
		//obj id / user_id is the id of the user which lp we want to inspect.
		if(!($user_id = $_GET["obj_id"]))
			return false;
		//the user has to be an employee in this or a subsequent org-unit.
		if(!in_array($user_id, ilObjOrgUnitTree::_getInstance()->getEmployees($_GET["ref_id"], $recursive)) && $ilUser->getId() != 6)
			return false;
		return true;
	}

	public function renderObject(){
		global $ilTabs, $ilToolbar;
		/** @var ilToolbarGUI $ilToolbar */
		$ilToolbar = $ilToolbar;
		parent::renderObject();
		$ilTabs->setTabActive("view_content");
		$this->tabs_gui->removeSubTab("page_editor");
		if($this->object->getRefId() == ilObjOrgUnit::getRootOrgRefId()){
			$ilToolbar->addButton($this->lng->txt("simple_import"), $this->ctrl->getLinkTarget($this, "importScreen"));
			$ilToolbar->addButton($this->lng->txt("simple_user_import"), $this->ctrl->getLinkTarget($this, "userImportScreen"));
		}
	}

	public function viewObject() {
		$this->renderObject();
	}


	public function showTreeObject(){
		require_once("./Services/Tree/classes/class.ilTree.php");
		require_once("./Modules/OrgUnit/classes/class.ilOrgUnitExplorerGUI.php");

		$tree = new ilOrgUnitExplorerGUI("orgu_explorer", "ilObjOrgUnitGUI", "showTree", new ilTree(1));
		$tree->setTypeWhiteList(array("orgu"));
		if(!$tree->handleCommand()){
			global $tpl;
			$tpl->setLeftNavContent($tree->getHTML());
		}
	}
	/**
	 * called by prepare output
	 */
	function setTitleAndDescription()
	{
		global $rbacreview;
		# all possible create permissions
		$possible_ops_ids = $rbacreview->getOperationsByTypeAndClass(
			'orgu',
			'create'
		);

		global $lng;
		parent::setTitleAndDescription();
		if($this->object->getTitle() == "__OrgUnitAdministration")
			$this->tpl->setTitle($lng->txt("objs_orgu"));
		$this->tpl->setDescription($lng->txt("objs_orgu"));
	}

	protected function addAdminLocatorItems(){
		global $ilLocator, $tree, $ilCtrl, $lng;
		/** @var ilLocatorGUI $ilLocator */
		$ilLocator = $ilLocator;
//		$ilLocator->addRepositoryItems($_GET["ref_id"]);
		$path = $tree->getPathFull($_GET["ref_id"], ilObjOrgUnit::getRootOrgRefId());

		// add item for each node on path
		foreach ((array) $path as $key => $row)
		{
			if ($row["title"] == "__OrgUnitAdministration")
			{
				$row["title"] = $lng->txt("objs_orgu");
			}

			$ilCtrl->setParameterByClass("ilobjorgunitgui", "ref_id", $row["child"]);
			$ilLocator->addItem($row["title"],
				$ilCtrl->getLinkTargetByClass("ilobjorgunitgui", "view"),
				ilFrameTargetInfo::_getFrame("MainContent"), $row["child"]);
			$ilCtrl->setParameterByClass("ilobjorgunitgui", "ref_id", $_GET["ref_id"]);
		}
	}

	protected function redirectToRefId($a_ref_id, $a_cmd = "")
	{
		$obj_type = ilObject::_lookupType($a_ref_id,true);
		if($obj_type != "orgu")
			parent::redirectToRefId($a_ref_id, $a_cmd);
		else{
			$this->ctrl->setParameterByClass("ilObjOrgUnitGUI", "ref_id", $a_ref_id);
			$this->ctrl->redirectByClass("ilObjOrgUnitGUI", $a_cmd);
		}
	}

	function getTabs(&$tabs_gui){
		/** @var ilTabsGUI $ilTabs */
		$ilTabs = $tabs_gui;
		parent::getTabs($tabs_gui);
		if($this->checkAccess("write")){
			$ilTabs->addTab("orgu_staff", $this->lng->txt("orgu_staff"), $this->ctrl->getLinkTarget($this, "showStaff"), "", 25);
			if($_GET["ref_id"] != ilObjOrgUnit::getRootOrgRefId())
				$ilTabs->replaceTab("settings", "settings", $this->lng->txt("settings"), $this->ctrl->getLinkTarget($this, "editTranslations"));
			else{
				$ilTabs->removeTab("settings");
			}
		}
		if($this->checkAccess("visible")){
			$ilTabs->replaceTab("info_short", "info_short", $this->lng->txt("info_short"), $this->ctrl->getLinkTarget($this, "infoScreen"));
		}
	}

	public function showStaffObject(){
		global $ilTabs;
		$ilTabs->setTabActive("orgu_staff");
		$this->setContentSubTabs();
		$this->addToolbar();
		$this->ctrl->setParameter($this, "recursive", false);
		if(!$this->checkAccess("write"))
			return;
		$this->tpl->setContent($this->getStaffTableHTML(false, "showStaff"));
	}

	public function showStaffRecObject(){
		global $ilTabs;
		$ilTabs->setTabActive("orgu_staff");
		$this->setContentSubTabs();
		$this->ctrl->setParameter($this, "recursive", true);
		if(!$this->checkAccess("write"))
			return;
		$this->tpl->setContent($this->getStaffTableHTML(true, "showStaffRec"));
	}

	protected function addToolbar(){
		global $lng;
		if(!$this->checkAccess("write"))
			return;

		global $ilToolbar;
		/** @var ilToolbarGUI $ilToolbar */
		$toolbar = $ilToolbar;
		//TODO place old input gui.
//		$item = new ilOrguUserPickerToolbarInputGUI("user_ids");
//		$item->setSubmitLink($this->ctrl->getLinkTarget($this, "addStaff"));
//		$ilToolbar->addInputItem($item);

//		include_once("./Services/Form/classes/class.ilTextInputGUI.php");
//		$ul = new ilTextInputGUI($this->lng->txt("user"), 'user_login');
//		$ul->setDataSource($this->ctrl->getLinkTarget($this, "searchUsersAjax", "", true));
//		$ul->setSize(15);
//		$toolbar->addInputItem($ul, true);

		$types = array(
			"employee" => $this->lng->txt("employee"), "superior" => $this->lng->txt("superior")
		);

		ilRepositorySearchGUI::fillAutoCompleteToolbar(
			$this,
			$ilToolbar,
			array(
				'auto_complete_name'	=> $lng->txt('user'),
				'user_type'				=> $types,
				'submit_name'			=> $lng->txt('add')
			)
		);
	}


	public function addUserFromAutoCompleteObject(){
		$users = explode(',', $_POST['user_login']);
		$user_ids = array();
		foreach($users as $user)
		{
			$user_id = ilObjUser::_lookupId($user);
			if($user_id)
			{
				$user_ids[] = $user_id;
			}
		}

		$user_type = isset($_POST['user_type']) ? $_POST['user_type'] : 0;

		if($user_type == "employee")
			$this->object->assignUsersToEmployeeRole($user_ids);
		elseif($user_type == "superior")
			$this->object->assignUsersToSuperiorRole($user_ids);
		else
			throw new Exception("The post request didn't specify wether the user_ids should be assigned to the employee or the superior role.");
		ilUtil::sendSuccess($this->lng->txt("users_successfuly_added"), true);
		$this->showStaffObject();
	}

	/**
	 * used if JF decides for new multi user input gui.
	 */
	public function addStaffObject(){
		if(!$this->checkAccess("write"))
			return;
		global $lng;
//		$item = new ilOrguUserPickerToolbarInputGUI("user_ids");
//		$item->setValueByArray($_POST);
//		if($item->getStaff() == "employee")
//			$this->object->assignUsersToEmployeeRole($item->getValue());
//		elseif($item->getStaff() == "superior")
//			$this->object->assignUsersToSuperiorRole($item->getValue());
//		else
//			throw new Exception("The post request didn't specify wether the user_ids should be assigned to the employee or the superior role.");

		ilUtil::sendSuccess($lng->txt("users_successfuly_added"), true);
		$this->showStaffObject();
	}

	/**
	 * @param bool $recursive
	 * @param string $table_cmd
	 * @return string the tables html.
	 */
	public function getStaffTableHTML($recursive = false, $table_cmd = "showStaff"){
		global $lng;
		$superior_table = new ilOrgUnitStaffTableGUI($this, $table_cmd, "superior");
		$superior_table->setRecursive($recursive);
		$superior_table->parseData();
		$superior_table->setTitle($lng->txt("Superior"));

		$employee_table = new ilOrgUnitStaffTableGUI($this, $table_cmd, "employee");
		$employee_table->setRecursive($recursive);
		$employee_table->parseData();
		$employee_table->setTitle($lng->txt("Employee"));

		return $superior_table->getHTML().$employee_table->getHTML();
	}

	protected function checkAccess($perm){
		global $ilAccess, $lng;
		if(!$ilAccess->checkAccess($perm, "", $_GET["ref_id"])){
			ilUtil::sendFailure($lng->txt("permission_denied"), true);
			$this->ctrl->redirect($this, "showStaff");
			return false;
		}
		return true;
	}

	public function _goto($ref_id){
		global $ilCtrl;
		$ilCtrl->initBaseClass("ilAdministrationGUI");
		$ilCtrl->setTargetScript("ilias.php");
		$ilCtrl->setParameterByClass("ilObjOrgUnitGUI", "ref_id", $ref_id);
		$ilCtrl->setParameterByClass("ilObjOrgUnitGUI", "admin_mode", "settings");
		$ilCtrl->redirectByClass(array("ilAdministrationGUI", "ilObjOrgUnitGUI"), "view");
	}

	/**
	 * @param ilTabsGUI $tabs_gui
	 * @param bool $force_activate
	 */
	protected function addInfoTab(&$tabs_gui, $force_activate){
		$tabs_gui->addTab("info_short", "Info",
			$this->ctrl->getLinkTarget(
				$this, "infoScreen")
			);
	}

	public function fromSuperiorToEmployeeObject(){
		if(!$this->checkAccess("write"))
			return;
		$this->object->deassignUserFromSuperiorRole($_GET["obj_id"]);
		$this->object->assignUsersToEmployeeRole(array($_GET["obj_id"]));
		ilUtil::sendSuccess($this->lng->txt("user_changed_successful"), true);
		$this->ctrl->redirect($this, "showStaff");
	}

	public function fromEmployeeToSuperiorObject(){
		if(!$this->checkAccess("write"))
			return;
		$this->object->deassignUserFromEmployeeRole($_GET["obj_id"]);
		$this->object->assignUsersToSuperiorRole(array($_GET["obj_id"]));
		ilUtil::sendSuccess($this->lng->txt("user_changed_successful"), true);
		$this->ctrl->redirect($this, "showStaff");
	}

	public function removeFromSuperiorsObject(){
		if(!$this->checkAccess("write"))
			return;
		$this->object->deassignUserFromSuperiorRole($_GET["obj_id"]);
		ilUtil::sendSuccess($this->lng->txt("deassign_user_successful"), true);
		$this->ctrl->redirect($this, "showStaff");
	}

	public function removeFromEmployeesObject(){
		if(!$this->checkAccess("write"))
			return;
		$this->object->deassignUserFromEmployeeRole($_GET["obj_id"]);
		ilUtil::sendSuccess($this->lng->txt("deassign_user_successful"), true);
		$this->ctrl->redirect($this, "showStaff");
	}

	public function importScreenObject(){
		global $tpl;
		$form = $this->initSimpleImportForm("startImport");
		$tpl->setContent($form->getHTML());
	}

	public function userImportScreenObject(){
		global $tpl;
		$form = $this->initSimpleImportForm("startUserImport");
		$tpl->setContent($form->getHTML());
	}

	protected  function initSimpleImportForm($submit_action){
		$form = new ilPropertyFormGUI();
		$input = new ilFileInputGUI($this->lng->txt("import_xml_file"), "import_file");
		$input->setRequired(true);
		$form->addItem($input);
		$form->setFormAction($this->ctrl->getFormAction($this));
		$form->addCommandButton($submit_action, $this->lng->txt("import"));
		return $form;
	}

	public function startImportObject(){
		global $tpl, $lng;
		$form = $this->initSimpleImportForm("startImport");
		if(!$form->checkInput()){
			$tpl->setContent($form->getHTML());
		}else{
			$file = $form->getInput("import_file");
			$importer = new ilOrgUnitImporter();
			try{
				$importer->simpleImport($file["tmp_name"]);
			}catch(Exception $e){
				global $ilLog;
				$ilLog->wirte($e->getMessage()."\\n".$e->getTraceAsString());
				ilUtil::sendFailure($lng->txt("import_failed"), true);
				$this->ctrl->redirect($this, "render");
			}
		$this->displayImportResults($importer);
		}
	}

	public function startUserImportObject(){
		global $tpl, $lng;
		$form = $this->initSimpleImportForm("startUserImport");
		if(!$form->checkInput()){
			$tpl->setContent($form->getHTML());
		}else{
			$file = $form->getInput("import_file");
			$importer = new ilOrgUnitImporter();
			try{
				$importer->simpleUserImport($file["tmp_name"]);
			}catch(Exception $e){
				global $ilLog;
				$ilLog->wirte($e->getMessage()."\\n".$e->getTraceAsString());
				ilUtil::sendFailure($lng->txt("import_failed"), true);
				$this->ctrl->redirect($this, "render");
			}
			$this->displayImportResults($importer);
		}
	}

	/**
	 * @param $importer ilOrgUnitImporter
	 */
	public function displayImportResults($importer){
		if(!$importer->hasErrors() && !$importer->hasWarnings()){
			$stats = $importer->getStats();
			ilUtil::sendSuccess(sprintf($this->lng->txt("import_successful"), $stats["created"], $stats["updated"], $stats["deleted"]), true);
		}
		if($importer->hasWarnings()){
			$msg = $this->lng->txt("import_terminated_with_warnings").":<br>";
			foreach($importer->getWarnings() as $warning)
				$msg.= "-".$this->lng->txt($warning["lang_var"])." (import id: ".$warning["import_id"].")<br>";
			ilUtil::sendInfo($msg, true);
		}
		if($importer->hasErrors()){
			$msg = $this->lng->txt("import_terminated_with_errors").":<br>";
			foreach($importer->getErrors() as $warning)
				$msg.= "-".$this->lng->txt($warning["lang_var"])." (import id: ".$warning["import_id"].")<br>";
			ilUtil::sendFailure($msg, true);
		}
	}

	public function setContentSubTabs($cmd = ""){
		global $ilTabs, $lng, $ilAccess;

		if(!$cmd)
			$cmd = $this->ctrl->getCmd();
		/** @var ilTabsGUI $ilTabs */
		$ilTabs = $ilTabs;
		switch($cmd){
			case 'render':
			case 'view':
			case '':
				parent::setContentSubTabs();
				$ilTabs->removeSubTab("page_editor");
				if($this->isActiveAdministrationPanel())
					$ilTabs->activateSubTab("manage");
				else
					$ilTabs->activateSubTab("view_content");
			break;
			case 'showStaff':
				$active_subtab = "show_staff";
			case 'showStaffRec':
				if(!$active_subtab)
					$active_subtab = "show_staff_rec";
			case 'addStaff':
				$ilTabs->addSubTab("show_staff",sprintf($lng->txt("local_staff"), $this->object->getTitle()), $this->ctrl->getLinkTarget($this, "showStaff"));
				if($ilAccess->checkAccess("view_learning_progress_rec", "", $_GET["ref_id"]))
					$ilTabs->addSubTab("show_staff_rec", sprintf($lng->txt("rec_staff"), $this->object->getTitle()), $this->ctrl->getLinkTarget($this, "showStaffRec"));
				$ilTabs->setSubTabActive($active_subtab);
				break;
			case 'editTranslations':
			case 'addTranslation':
				$active_subtab = "edit_translations";
			case 'editExtId':
			case 'updateExtId':
				if(!$active_subtab)
					$active_subtab = "edit_ext_id";
				$ilTabs->addSubTab("edit_translations", $this->lng->txt("edit_translations"), $this->ctrl->getLinkTarget($this, "editTranslations"));
				$ilTabs->addSubTab("edit_ext_id", $this->lng->txt("edit_ext_id"), $this->ctrl->getLinkTarget($this, "editExtId"));
				$ilTabs->setSubTabActive($active_subtab);
				break;
			default:
				break;
		}
	}

}