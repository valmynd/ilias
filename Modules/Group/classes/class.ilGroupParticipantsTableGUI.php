<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once('./Services/Table/classes/class.ilTable2GUI.php');

/**
*
* @author Stefan Meyer <smeyer.ilias@gmx.de>
* @version $Id$
*
* @ingroup ModulesGroup
*/

class ilGroupParticipantsTableGUI extends ilTable2GUI
{
    protected $type = 'admin';
	protected $role = 0;
    protected $show_learning_progress = false;
    
    protected static $export_allowed = false;
    protected static $confirmation_required = true;
    protected static $accepted_ids = null;
	
	protected static $all_columns = null;

    /**
     * Constructor
     *
     * @access public
     * @param
     * @return
     */
    public function __construct(
			$a_parent_obj,$a_type = 'admin',
			$show_content = true,
			$show_learning_progress = false,
			$a_role_id = 0
	)
    {
        global $lng,$ilCtrl;
        
        $this->show_learning_progress = $show_learning_progress;
        
        $this->lng = $lng;
        $this->lng->loadLanguageModule('grp');
        $this->lng->loadLanguageModule('trac');
        $this->ctrl = $ilCtrl;
        
        $this->type = $a_type; 
		$this->role = $a_role_id;
        
        include_once('./Services/PrivacySecurity/classes/class.ilPrivacySettings.php');
        $this->privacy = ilPrivacySettings::_getInstance();
        
        $this->setId('grp_'.$a_type.'_'.$this->getRole().'_'.$a_parent_obj->object->getId());
        parent::__construct($a_parent_obj,'members');
		
		$this->initAcceptedAgreements();

        $this->setFormName('participants');

        $this->addColumn('','f',"1");
        $this->addColumn($this->lng->txt('name'),'lastname','20%');
        
		$all_cols = $this->getSelectableColumns();
        foreach($this->getSelectedColumns() as $col)
        {
			$this->addColumn($all_cols[$col]['txt'],$col);
        }
        
        if($this->show_learning_progress)
        {
            $this->addColumn($this->lng->txt('learning_progress'),'progress');
        }

        if($this->privacy->enabledGroupAccessTimes())
        {
            $this->addColumn($this->lng->txt('last_access'),'access_time_unix');
        }
        if($this->type == 'admin')
        {
            $this->setPrefix('admin');
            $this->setSelectAllCheckbox('admins');
            $this->addColumn($this->lng->txt('grp_notification'),'notification');
            $this->addCommandButton('updateStatus',$this->lng->txt('save'));
        }
        elseif($this->type == 'member')
        {
            $this->setPrefix('member');
            $this->setSelectAllCheckbox('members');
        }
		else
		{
            $this->setPrefix('role');
            $this->setSelectAllCheckbox('roles');
		}
        $this->addColumn($this->lng->txt(''),'optional');
        $this->setDefaultOrderField('lastname');
        
        $this->setRowTemplate("tpl.show_participants_row.html","Modules/Group");
		
		$this->setShowRowsSelector(true);
        
        if($show_content)
        {
            $this->enable('sort');
            $this->enable('header');
            $this->enable('numinfo');
            $this->enable('select_all');
        }
        else
        {
            $this->disable('content');
            $this->disable('header');
            $this->disable('footer');
            $this->disable('numinfo');
            $this->disable('select_all');
        }       
    }
	
	/**
	 * Get role
	 * @return type
	 */
	public function getRole()
	{
		return $this->role;
	}
    
    /**
     * Get selectable columns
     * @return 
     */
    public function getSelectableColumns()
    {
		global $ilSetting;
		
		if(self::$all_columns)
		{
			# return self::$all_columns;
		}
		
		include_once './Services/PrivacySecurity/classes/class.ilExportFieldsInfo.php';
		$ef = ilExportFieldsInfo::_getInstanceByType($this->getParentObject()->object->getType());
		self::$all_columns = $ef->getSelectableFieldsInfo($this->getParentObject()->object->getId());
		
		if ($this->type == 'member' &&
			$ilSetting->get('user_portfolios'))
		{
			self::$all_columns['prtf'] = array(
				'txt' => $this->lng->txt('obj_prtf'),
				'default' => false
			);			
		}
		
		return self::$all_columns;
    }
    
    
    /**
     * fill row 
     *
     * @access public
     * @param
     * @return
     */
    public function fillRow($a_set)
    {
        global $ilUser,$ilAccess;
        
        $this->tpl->setVariable('VAL_ID',$a_set['usr_id']);
        $this->tpl->setVariable('VAL_NAME',$a_set['lastname'].', '.$a_set['firstname']);
        if(!$ilAccess->checkAccessOfUser($a_set['usr_id'],'read','',$this->getParentObject()->object->getRefId()) and 
            is_array($info = $ilAccess->getInfo()))
        {
			$this->tpl->setCurrentBlock('access_warning');
			$this->tpl->setVariable('PARENT_ACCESS',$info[0]['text']);
			$this->tpl->parseCurrentBlock();
        }

		if(!ilObjUser::_lookupActive($a_set['usr_id']))
		{
			$this->tpl->setCurrentBlock('access_warning');
			$this->tpl->setVariable('PARENT_ACCESS',$this->lng->txt('usr_account_inactive'));
			$this->tpl->parseCurrentBlock();
		}

        
        foreach($this->getSelectedColumns() as $field)
        {
            switch($field)
            {
                case 'gender':
                    $a_set['gender'] = $a_set['gender'] ? $this->lng->txt('gender_'.$a_set['gender']) : '';                 
                    $this->tpl->setCurrentBlock('custom_fields');
                    $this->tpl->setVariable('VAL_CUST',$a_set[$field]);
                    $this->tpl->parseCurrentBlock();
                    break;
                    
                case 'birthday':
                    $a_set['birthday'] = $a_set['birthday'] ? ilDatePresentation::formatDate(new ilDate($a_set['birthday'],IL_CAL_DATE)) : $this->lng->txt('no_date');              
                    $this->tpl->setCurrentBlock('custom_fields');
                    $this->tpl->setVariable('VAL_CUST',$a_set[$field]);
                    $this->tpl->parseCurrentBlock();
                    break;

				case 'consultation_hour':
					$this->tpl->setCurrentBlock('custom_field');
					$dts = array();
					foreach((array) $a_set['consultation_hours'] as $ch)
					{
						$tmp = ilDatePresentation::formatPeriod(
								new ilDateTime($ch['dt'],IL_CAL_UNIX),
								new ilDateTime($ch['dtend'],IL_CAL_UNIX)
						);
						if($ch['explanation'])
						{
							$tmp .= ' ' . $ch['explanation'];
						}
						$dts[] = $tmp;
					}
					$dt_string = implode('<br />', $dts);
					$this->tpl->setVariable('VAL_CUST',$dt_string) ;
					$this->tpl->parseCurrentBlock();
					break;
					
				case 'prtf':			
					$tmp = array();
					if(is_array($a_set['prtf']))
					{						
						foreach($a_set['prtf'] as $prtf_url => $prtf_txt)
						{
							$tmp[] = '<a href="'.$prtf_url.'">'.$prtf_txt.'</a>';							
						}
					}
					$this->tpl->setVariable('VAL_CUST', implode('<br />', $tmp)) ;					
					break;
                                        
                default:
                    $this->tpl->setCurrentBlock('custom_fields');
                    $this->tpl->setVariable('VAL_CUST',isset($a_set[$field]) ? (string) $a_set[$field] : '');
                    $this->tpl->parseCurrentBlock();
                    break;
            }
        }
        
        if($this->privacy->enabledGroupAccessTimes())
        {
            $this->tpl->setVariable('VAL_ACCESS',$a_set['access_time']);
        }
        
        if($this->show_learning_progress)
        {
            $this->tpl->setCurrentBlock('lp');
            switch($a_set['progress'])
            {
                case ilLPStatus::LP_STATUS_COMPLETED:
                    $this->tpl->setVariable('LP_STATUS_ALT',$this->lng->txt($a_set['progress']));
                    $this->tpl->setVariable('LP_STATUS_PATH',ilUtil::getImagePath('scorm/complete.png'));
                    break;
                    
                case ilLPStatus::LP_STATUS_IN_PROGRESS:
                    $this->tpl->setVariable('LP_STATUS_ALT',$this->lng->txt($a_set['progress']));
                    $this->tpl->setVariable('LP_STATUS_PATH',ilUtil::getImagePath('scorm/incomplete.png'));
                    break;

                case ilLPStatus::LP_STATUS_NOT_ATTEMPTED:
                    $this->tpl->setVariable('LP_STATUS_ALT',$this->lng->txt($a_set['progress']));
                    $this->tpl->setVariable('LP_STATUS_PATH',ilUtil::getImagePath('scorm/not_attempted.png'));
                    break;  

                case ilLPStatus::LP_STATUS_FAILED:
                    $this->tpl->setVariable('LP_STATUS_ALT',$this->lng->txt($a_set['progress']));
                    $this->tpl->setVariable('LP_STATUS_PATH',ilUtil::getImagePath('scorm/failed.png'));
                    break;
                                
            }
            $this->tpl->parseCurrentBlock();
        }
        
        
        if($this->type == 'admin')
        {
            $this->tpl->setVariable('VAL_POSTNAME','admins');
            $this->tpl->setVariable('VAL_NOTIFICATION_ID',$a_set['usr_id']);
            $this->tpl->setVariable('VAL_NOTIFICATION_CHECKED',$a_set['notification'] ? 'checked="checked"' : '');
        }
        elseif($this->type == 'member')
        {
            $this->tpl->setVariable('VAL_POSTNAME','members');
        }
		else
		{
            $this->tpl->setVariable('VAL_POSTNAME','roles');
		}
        
        $this->ctrl->setParameter($this->parent_obj,'member_id',$a_set['usr_id']);
        $this->tpl->setVariable('LINK_NAME',$this->ctrl->getLinkTarget($this->parent_obj,'editMember'));
        $this->tpl->setVariable('LINK_TXT',$this->lng->txt('edit'));
        $this->ctrl->clearParameters($this->parent_obj);
        
        $this->tpl->setVariable('VAL_LOGIN',$a_set['login']);
    }
    
    /**
     * Parse user data
     * @param array $a_user_data
     * @return 
     */
    public function parse($a_user_data)
    {
        include_once './Services/User/classes/class.ilUserQuery.php';
		
        $additional_fields = $this->getSelectedColumns();
        unset($additional_fields["firstname"]);
        unset($additional_fields["lastname"]);
        unset($additional_fields["last_login"]);
        unset($additional_fields["access_until"]);
		unset($additional_fields['consultation_hour']);
		unset($additional_fields['prtf']);
				
        switch($this->type)
        {
            case 'admin':
                $part = ilGroupParticipants::_getInstanceByObjId($this->getParentObject()->object->getId())->getAdmins();
                break;              
            case 'member':
				$part = $GLOBALS['rbacreview']->assignedUsers($this->getRole());
                break;
			case 'role':
				$part = $GLOBALS['rbacreview']->assignedUsers($this->getRole());
				break;
        }
		
		$udf_ids = $usr_data_fields = $odf_ids = array();
		foreach($additional_fields as $field)
		{
			if(substr($field,0,3) == 'udf')
			{
				$udf_ids[] = substr($field,4);
				continue;
			}
			if(substr($field,0,3) == 'odf')
			{
				$odf_ids[] = substr($field,4);
				continue;
			}
			
			$usr_data_fields[] = $field;
		}

        $usr_data = ilUserQuery::getUserListData(
            'login',
            'ASC',
            0,
            9999,
            '',
            '',
            null,
            false,
            false,
            0,
            0,
            null,
            $usr_data_fields,
            $part
        );
		// Custom user data fields
		if($udf_ids)
		{
			include_once './Services/User/classes/class.ilUserDefinedData.php';
			$data = ilUserDefinedData::lookupData($part, $udf_ids);
			foreach($data as $usr_id => $fields)
			{
	            if(!$this->checkAcceptance($usr_id))
    	        {
					continue;
            	}
				
				foreach($fields as $field_id => $value)
				{
					$a_user_data[$usr_id]['udf_'.$field_id] = $value;
				}
			}
		}
		// Object specific user data fields
		if($odf_ids)
		{
			include_once './Modules/Course/classes/Export/class.ilCourseUserData.php';
			$data = ilCourseUserData::_getValuesByObjId($this->getParentObject()->object->getId());
			foreach($data as $usr_id => $fields)
			{
	            if(!$this->checkAcceptance($usr_id))
    	        {
					continue;
            	}
				
				foreach($fields as $field_id => $value)
				{
					if($a_user_data[$usr_id])
					{
						$a_user_data[$usr_id]['odf_'.$field_id] = $value;
					}
				}
			}
		}
		
        foreach($usr_data['set'] as $user)
        {
            // Check acceptance
            if(!$this->checkAcceptance($user['usr_id']))
            {
				continue;
            }
            // DONE: accepted
            foreach($usr_data_fields as $field)
            {
                $a_user_data[$user['usr_id']][$field] = $user[$field] ? $user[$field] : '';
            }
        }
		// consultation hours
		if($this->isColumnSelected('consultation_hour'))
		{
			include_once './Services/Booking/classes/class.ilBookingEntry.php';
			foreach(ilBookingEntry::lookupManagedBookingsForObject($this->getParentObject()->object->getId(), $GLOBALS['ilUser']->getId()) as $buser => $booking)
			{
				if(isset($a_user_data[$buser]))
				{
					$a_user_data[$buser]['consultation_hour'] = $booking[0]['dt'];
					$a_user_data[$buser]['consultation_hour_end'] = $booking[0]['dtend'];
					$a_user_data[$buser]['consultation_hours'] = $booking;
				}
			}
		}
		
        return $this->setData($a_user_data);
    }
    
	/**
	 * Check acceptance
	 * @param object $a_usr_id
	 * @return 
	 */
    public function checkAcceptance($a_usr_id)
    {
        if(!self::$confirmation_required)
        {
            return true;
        }
        if(!self::$export_allowed)
        {
            return false;
        }
        return in_array($a_usr_id,self::$accepted_ids);
    }
    
    
    /**
     * Init acceptance
     * @return 
     */
    public function initAcceptedAgreements()
    {
        if(self::$accepted_ids !== NULL)
        {
            return true;
        }
        
        self::$export_allowed = ilPrivacySettings::_getInstance()->checkExportAccess($this->getParentObject()->object->getRefId());
        self::$confirmation_required = ilPrivacySettings::_getInstance()->groupConfirmationRequired();
		
        include_once 'Services/Membership/classes/class.ilMemberAgreement.php';
        self::$accepted_ids = ilMemberAgreement::lookupAcceptedAgreements($this->getParentObject()->object->getId());
    }
    
}
?>
