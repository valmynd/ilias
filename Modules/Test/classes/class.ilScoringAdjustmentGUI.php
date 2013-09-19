<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilScoringAdjustmentGUI
 *
 * @author		Maximilian Becker <mbecker@databay.de>
 *
 * @version		$Id$
 *
 * @ingroup		ModulesTest
 *
 * @ilCtrl_IsCalledBy ilScoringAdjustmentGUI: ilObjTestGUI
 * 
 * @ilCtrl_Calls ilScoringAdjustmentGUI: ilQuestionBrowserTableGUI
 */
class ilScoringAdjustmentGUI 
{
	/** @var \ilLanguage $lng */
	protected $lng;
	
	/** @var \ilTemplate $tpl */
	protected $tpl;
	
	/** @var ilCtrl $ctrl */
	protected $ctrl;
	
	/** @var ILIAS $ilias */
	protected $ilias;
	
	/** @var \ilObjTest $object */
	public $object; // Public due to law of demeter violation in ilTestQuestionsTableGUI.
	
	/** @var \ilTree $tree */
	protected $tree;
	
	/** @var int $ref_id */
	protected $ref_id;
	
	/** @var \ilTestService $service */
	protected $service;

	/**
	 * Default constructor
	 * 
	 * @param ilObjTest $a_object
	 */
	public function __construct(ilObjTest $a_object)
	{
		global $lng, $tpl, $ilCtrl, $ilias, $tree;

		$this->lng 		= $lng;
		$this->tpl 		= $tpl;
		$this->ctrl 	= $ilCtrl;
		$this->ilias 	= $ilias;
		$this->object 	= $a_object;
		$this->tree 	= $tree;
		$this->ref_id 	= $a_object->ref_id;

		require_once './Modules/Test/classes/class.ilTestService.php';
		$this->service 	= new ilTestService($a_object);
	}

	/**
	 * execute command
	 */
	public function executeCommand()
	{
		$cmd = $this->ctrl->getCmd();
		$next_class = $this->ctrl->getNextClass($this);

		switch($next_class)
		{
			default:
				return $this->dispatchCommand($cmd);
		}
	}

	protected function dispatchCommand($cmd)
	{
		switch (strtolower($cmd))
		{
			case 'save':
				$this->saveQuestion();
				break;
				
			case 'adjustscoringfortest':
				$this->editQuestion();
				break;
			
			case 'showquestionlist':
			default: 
				$this->questionsObject();
		}
	}

	protected function questionsObject()
	{
		/** @var $ilAccess ilAccessHandler */
		global $ilAccess;

		if (!$ilAccess->checkAccess("write", "", $this->ref_id))
		{
			// allow only write access
			ilUtil::sendInfo($this->lng->txt("cannot_edit_test"), true);
			$this->ctrl->redirect($this, "infoScreen");
		}

		if ($_GET['browse'])
		{
			exit('Browse??');
			return $this->object->questionbrowser();
		}

		if ($_GET["eqid"] && $_GET["eqpl"])
		{
			$this->ctrl->setParameter($this, 'q_id', $_GET["eqid"]);
			$this->ctrl->setParameter($this, 'qpl_id', $_GET["eqpl"]);
			$this->ctrl->redirect($this, 'adjustscoringfortest');
		}


		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_questions.html", "Modules/Test");

		$this->tpl->setCurrentBlock("adm_content");

		include_once "./Modules/Test/classes/tables/class.ilTestQuestionsTableGUI.php";
		$checked_move = is_array($_SESSION['tst_qst_move_' . $this->object->getTestId()]) 
			&& (count($_SESSION['tst_qst_move_' . $this->object->getTestId()]));

		$table_gui = new ilTestQuestionsTableGUI(
			$this, 
			'questions', 
			(($ilAccess->checkAccess("write", "", $this->ref_id) ? true : false)), 
			$checked_move, 0);

		$data = $this->object->getTestQuestions();
		// @TODO Ask object for random test.
		if (!$data)
		{
			$this->object->getPotentialRandomTestQuestions();
		}

		$filtered_data = array();
		foreach($data as $question)
		{
			$question_object = assQuestion::instantiateQuestionGUI($question['question_id']);

			if ( $this->supportsAdjustment( $question_object ) )
			{
				$filtered_data[] = $question;
			}
		}
		$table_gui->setData($filtered_data);

		$table_gui->clearActionButtons();
		$table_gui->clearCommandButtons();
		$table_gui->multi = array();
		$table_gui->setRowTemplate('tpl.il_as_tst_adjust_questions_row.html', 'Modules/Test');
		$table_gui->header_commands = array();
		$table_gui->setSelectAllCheckbox(null);

		$this->tpl->setVariable('QUESTIONBROWSER', $table_gui->getHTML());
		$this->tpl->setVariable("ACTION_QUESTION_FORM", $this->ctrl->getFormAction($this));
		$this->tpl->parseCurrentBlock();
	}

	/**
	 * Returns if the given question object support scoring adjustment.
	 * 
	 * @param $question_object assQuestionGUI
	 *
	 * @return bool True, if relevant interfaces are implemented to support scoring adjustment.
	 */
	protected function supportsAdjustment(\assQuestionGUI $question_object)
	{
		return ($question_object instanceof ilGuiQuestionScoringAdjustable
			|| $question_object instanceof ilGuiAnswerScoringAdjustable)
			&& ($question_object->object instanceof ilObjQuestionScoringAdjustable
			|| $question_object->object instanceof ilObjAnswerScoringAdjustable);
	}

	protected function editQuestion()
	{
		$question_id = $_GET['q_id'];
		$question_pool_id = $_GET['qpl_id'];
		$form = $this->buildAdjustQuestionForm( $question_id, $question_pool_id );
		// @TODO: Add statistical data to the output.

		$this->outputAdjustQuestionForm( $form );
	}

	/**
	 * @param $form
	 */
	protected function outputAdjustQuestionForm($form)
	{
		$this->tpl->addBlockFile( "ADM_CONTENT", "adm_content", "tpl.il_as_tst_questions.html", "Modules/Test" );
		$this->tpl->setCurrentBlock( "adm_content" );
		$this->tpl->setVariable( 'QUESTIONBROWSER', $form->getHTML() );
		$this->tpl->parseCurrentBlock();
	}

	/**
	 * @param $question_id
	 * @param $question_pool_id
	 *
	 * @return ilPropertyFormGUI
	 */
	protected function buildAdjustQuestionForm($question_id, $question_pool_id)
	{
		require_once './Services/Form/classes/class.ilPropertyFormGUI.php';
		require_once './Modules/TestQuestionPool/classes/class.assQuestion.php';

		$form = new ilPropertyFormGUI();
		$form->setFormAction( $this->ctrl->getFormAction( $this ) );
		$form->setMultipart( FALSE );
		$form->setTableWidth( "100%" );
		$form->setId( "adjustment" );

		/** @var $question assQuestionGUI|ilGuiQuestionScoringAdjustable|ilGuiAnswerScoringAdjustable */
		$question = assQuestion::instantiateQuestionGUI( $question_id );
		$form->setTitle( $question->outQuestionType() );

		$hidden_q_id = new ilHiddenInputGUI('q_id');
		$hidden_q_id->setValue( $question_id );
		$form->addItem( $hidden_q_id );

		$hidden_qpl_id = new ilHiddenInputGUI('qpl_id');
		$hidden_qpl_id->setValue( $question_pool_id );
		$form->addItem( $hidden_qpl_id );

		if ($question instanceof ilGuiQuestionScoringAdjustable)
		{
			$question->populateQuestionSpecificFormPart( $form );
			$this->suppressPostParticipationFormElements( $form, $question->getAfterParticipationSuppressionQuestionPostVars() );
		}

		if ($question instanceof ilGuiAnswerScoringAdjustable)
		{
			$question->populateAnswerSpecificFormPart( $form );
			$this->suppressPostParticipationFormElements( $form, $question->getAfterParticipationSuppressionAnswerPostVars());
		}

		$form->addCommandButton("save", $this->lng->txt("save"));
		return $form;
	}

	protected function suppressPostParticipationFormElements(\ilPropertyFormGUI $form, $postvars_to_suppress)
	{
		foreach ($postvars_to_suppress as $postvar)
		{
			/** @var $item ilFormPropertyGUI */
			$item = $form->getItemByPostVar($postvar);
			$item->setDisabled(true);
		}
		return $form;
	}

	protected function saveQuestion()
	{
		$question_id = $_POST['q_id'];
		$question_pool_id = $_POST['qpl_id'];
		$form = $this->buildAdjustQuestionForm($question_id, $question_pool_id);

		$form->setValuesByPost($_POST);

		if (!$form->checkInput())
		{
			ilUtil::sendFailure($this->lng->txt('adjust_question_form_error'));
			$this->outputAdjustQuestionForm($form);
			return;
		}

		require_once './Modules/TestQuestionPool/classes/class.assQuestion.php';
		/** @var $question assQuestionGUI|ilGuiQuestionScoringAdjustable */
		$question = assQuestion::instantiateQuestionGUI( $question_id );

		if ($question instanceof ilGuiQuestionScoringAdjustable)
		{
			$question->writeQuestionSpecificPostData(true);

		}
		
		if ($question->object instanceof ilObjQuestionScoringAdjustable)
		{
			$question->object->saveAdditionalQuestionDataToDb();
		}
		
		if ($question instanceof ilGuiAnswerScoringAdjustable)
		{
			$question->writeAnswerSpecificPostData(true);
		}
		
		if($question->object instanceof ilObjAnswerScoringAdjustable)
		{
			$question->object->saveAnswerSpecificDataToDb();
		}

		$question->object->setPoints($question->object->getMaximumPoints());
		$question->object->saveQuestionDataToDb();

		require_once './Modules/Test/classes/class.ilTestScoring.php';
		$scoring = new ilTestScoring($this->object);
		$scoring->recalculateSolutions();

		ilUtil::sendSuccess($this->lng->txt('saved_adjustment'));
		$this->questionsObject();
		
	}
}