<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once 'Modules/Test/classes/class.ilTestPlayerAbstractGUI.php';

/**
 * Output class for assessment test execution
 *
 * The ilTestOutputGUI class creates the output for the ilObjTestGUI
 * class when learners execute a test. This saves some heap space because 
 * the ilObjTestGUI class will be much smaller then
 *
 * @extends ilTestPlayerAbstractGUI
 * 
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id: class.ilTestPlayerDynamicQuestionSetGUI.php 44345 2013-08-21 14:30:35Z bheyser $
 * 
 * @package		Modules/Test
 * 
 * @ilCtrl_Calls ilTestPlayerDynamicQuestionSetGUI: ilAssQuestionHintRequestGUI
 * @ilCtrl_Calls ilTestPlayerDynamicQuestionSetGUI: ilFilteredQuestionsTableGUI
 * @ilCtrl_Calls ilTestPlayerDynamicQuestionSetGUI: ilToolbarGUI
 */
class ilTestPlayerDynamicQuestionSetGUI extends ilTestPlayerAbstractGUI
{
	const CMD_SHOW_QUESTION_SELECTION = 'showQuestionSelection';
	const CMD_SHOW_QUESTION = 'showQuestion';
		
	/**
	 * @var ilObjTestDynamicQuestionSetConfig
	 */
	private $dynamicQuestionSetConfig = null;
	
	/**
	 * execute command
	 */
	function executeCommand()
	{
		global $ilDB, $lng, $ilPluginAdmin, $ilTabs, $tree;

		$ilTabs->clearTargets();
		
		$this->ctrl->saveParameter($this, "sequence");
		$this->ctrl->saveParameter($this, "active_id");

		require_once 'Modules/Test/classes/class.ilObjTestDynamicQuestionSetConfig.php';
		$this->dynamicQuestionSetConfig = new ilObjTestDynamicQuestionSetConfig($tree, $ilDB, $this->object);
		$this->dynamicQuestionSetConfig->loadFromDb();

		$testSessionFactory = new ilTestSessionFactory($this->object);
		$this->testSession = $testSessionFactory->getSession($_GET['active_id']);
		
		$testSequenceFactory = new ilTestSequenceFactory($ilDB, $lng, $ilPluginAdmin, $this->object);
		$this->testSequence = $testSequenceFactory->getSequence($this->testSession);
		$this->testSequence->loadFromDb();

		include_once 'Services/jQuery/classes/class.iljQueryUtil.php';
		iljQueryUtil::initjQuery();
		include_once "./Services/YUI/classes/class.ilYuiUtil.php";
		ilYuiUtil::initConnectionWithAnimation();
		if( $this->object->getKioskMode() )
		{
			include_once 'Services/UIComponent/Overlay/classes/class.ilOverlayGUI.php';
			ilOverlayGUI::initJavascript();
		}
		
		$cmd = $this->ctrl->getCmd();
		$nextClass = $this->ctrl->getNextClass($this);
		
		switch($nextClass)
		{
			case 'ilassquestionhintrequestgui':
				
				$questionGUI = $this->object->createQuestionGUI(
					"", $this->testSequenceFactory->getSequence()->getQuestionForSequence( $this->calculateSequence() )
				);

				require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionHintRequestGUI.php';
				$gui = new ilAssQuestionHintRequestGUI($this, $this->testSession, $questionGUI);
				
				$this->ctrl->forwardCommand($gui);
				
				break;
				
			case 'ilfilteredquestionstablegui':
				
				$this->ctrl->forwardCommand( $this->buildFilteredQuestionsTableGUI() );
				
				break;
			
			default:
				
				$cmd .= 'Cmd';
				$ret =& $this->$cmd();
				break;
		}
		
		return $ret;
	}

	/**
	 * Resume a test at the last position
	 */
	protected function resumePlayerCmd()
	{
		if ($this->object->checkMaximumAllowedUsers() == FALSE)
		{
			return $this->showMaximumAllowedUsersReachedMessage();
		}
		
		$this->handleUserSettings();
		
		if( $this->dynamicQuestionSetConfig->isTaxonomyFilterEnabled() )
		{
			$this->ctrl->redirect($this, self::CMD_SHOW_QUESTION_SELECTION);
		}
		
		$this->ctrl->redirect($this, self::CMD_SHOW_QUESTION);
	}
	
	protected function startTestCmd()
	{
		global $ilUser;
		
		// ensure existing test session
		$this->testSession->setRefId($this->object->getRefId());
		$this->testSession->setTestId($this->object->getTestId());
		$this->testSession->setUserId($ilUser->getId());
		$this->testSession->setAnonymousId($_SESSION['tst_access_code'][$this->object->getTestId()]);
		
		$this->testSession->setCurrentQuestionId(null); // no question "came up" yet
		
		$this->testSession->saveToDb();
		
		$this->ctrl->setParameter($this, 'active_id', $this->testSession->getActiveId());

		assQuestion::_updateTestPassResults($this->testSession->getActiveId(), $this->testSession->getPass());

		$_SESSION['active_time_id'] = $this->object->startWorkingTime(
				$this->testSession->getActiveId(), $this->testSession->getPass()
		);
		
		$this->ctrl->saveParameter($this, 'tst_javascript');
		
		if( $this->dynamicQuestionSetConfig->isTaxonomyFilterEnabled() )
		{
			$this->ctrl->redirect($this, self::CMD_SHOW_QUESTION_SELECTION);
		}
		
		$this->ctrl->redirect($this, self::CMD_SHOW_QUESTION);
	}
	
	protected function showQuestionSelectionCmd()
	{
		$this->prepareSummaryPage();
		
		$this->testSequence->loadQuestions(
				$this->dynamicQuestionSetConfig, $this->testSession->getTaxonomyFilterSelection()
		);
		
		$this->testSequence->cleanupQuestions($this->testSession);
		
		require_once 'Services/UIComponent/Toolbar/classes/class.ilToolbarGUI.php';
		$toolbarGUI = new ilToolbarGUI();
		$toolbarGUI->addButton($this->getEnterTestButtonLangVar(), $this->ctrl->getLinkTarget(
				$this, self::CMD_SHOW_QUESTION
		));
		
		$data = $this->buildQuestionsTableDataArray(
			$this->testSequence->getFilteredQuestionList(), $this->getMarkedQuestions()
		);
		
		$tableGUI = $this->buildFilteredQuestionsTableGUI();
		$tableGUI->setData($data);
		
		$content = $this->ctrl->getHTML($toolbarGUI);
		$content .= $this->ctrl->getHTML($tableGUI);

		$this->tpl->setVariable('TABLE_LIST_OF_QUESTIONS', $content);	

		if( $this->object->getEnableProcessingTime() )
		{
			$this->outProcessingTime($this->testSession->getActiveId());
		}
	}
	
	protected function filterQuestionSelectionCmd()
	{
		$tableGUI = $this->buildFilteredQuestionsTableGUI();
		$tableGUI->writeFilterToSession();
		
		$filterSelection = array();
		
		foreach( $tableGUI->getFilterItems() as $item )
		{
			$taxId = substr( $item->getPostVar(), strlen('tax_') );
			
			$filterSelection[$taxId] = $item->getValue();
		}
		
		$this->testSession->setTaxonomyFilterSelection( $filterSelection );
		$this->testSession->saveToDb();
		
		$this->ctrl->redirect($this, 'showQuestionSelection');
	}
	
	protected function resetQuestionSelectionCmd()
	{
		$tableGUI = $this->buildFilteredQuestionsTableGUI();
		$tableGUI->resetFilter();
		
		$this->testSession->setTaxonomyFilterSelection( array() );
		$this->testSession->saveToDb();
		
		$this->ctrl->redirect($this, 'showQuestionSelection');
	}

	protected function showTrackedQuestionListCmd()
	{
		$this->prepareSummaryPage();
		
		$this->saveQuestionSolution();
		
		$this->testSequence->loadQuestions(
				$this->dynamicQuestionSetConfig, $this->testSession->getTaxonomyFilterSelection()
		);
		
		$this->testSequence->cleanupQuestions($this->testSession);

		$data = $this->buildQuestionsTableDataArray(
			$this->testSequence->getTrackedQuestionList( $this->testSession->getCurrentQuestionId() ),
			$this->getMarkedQuestions()
		);
		
		include_once "./Modules/Test/classes/tables/class.ilTrackedQuestionsTableGUI.php";
		$table_gui = new ilTrackedQuestionsTableGUI(
				$this, 'showTrackedQuestionList', $this->object->getShowMarker()
		);
		
		$table_gui->setData($data);

		$this->tpl->setVariable('TABLE_LIST_OF_QUESTIONS', $table_gui->getHTML());	

		if( $this->object->getEnableProcessingTime() )
		{
			$this->outProcessingTime($this->testSession->getActiveId());
		}
	}

	protected function previousQuestionCmd()
	{
		
	}
	
	protected function nextQuestionCmd()
	{
		$this->updateWorkingTime();

		$this->saveQuestionSolution();
		
		$questionId = $this->testSession->getCurrentQuestionId();
		$activeId = $this->testSession->getActiveId();
		
		if( $this->isQuestionAnsweredCorrect($questionId, $activeId) )
		{
			$this->testSequence->setQuestionAnsweredCorrect($questionId);
		}
		else
		{
			$this->testSequence->setQuestionAnsweredWrong($questionId);
		}
		
		$this->testSession->setCurrentQuestionId(null);
		
		$this->testSequence->saveToDb();
		$this->testSession->saveToDb();
		
		$this->ctrl->setParameter(
				$this, 'sequence', $this->testSession->getCurrentQuestionId()
		);
		
		$this->ctrl->redirect($this, 'showQuestion');
	}
	
	protected function postponeQuestionCmd()
	{
		$this->updateWorkingTime();

		$this->saveQuestionSolution();
		
		$this->testSequence->setQuestionPostponed(
				$this->testSession->getCurrentQuestionId()
		);
		
		$this->testSession->setCurrentQuestionId(null);
		
		$this->testSequence->saveToDb();
		$this->testSession->saveToDb();
		
		$this->ctrl->redirect($this, 'showQuestion');
	}
	
	protected function markQuestionCmd()
	{
		$this->saveQuestionSolution();
		
		global $ilUser;
		$this->object->setQuestionSetSolved(1, $this->testSession->getCurrentQuestionId(), $ilUser->getId());
		
		$this->ctrl->redirect($this, 'showQuestion');
	}

	protected function unmarkQuestionCmd()
	{
		$this->saveQuestionSolution();
		
		global $ilUser;
		$this->object->setQuestionSetSolved(0, $this->testSession->getCurrentQuestionId(), $ilUser->getId());
		
		$this->ctrl->redirect($this, 'showQuestion');
	}
	
	protected function gotoQuestionCmd()
	{
		$this->testSequence->loadQuestions(
				$this->dynamicQuestionSetConfig, $this->testSession->getTaxonomyFilterSelection()
		);
		
		$this->testSequence->cleanupQuestions($this->testSession);
		
		if( isset($_GET['sequence']) && (int)$_GET['sequence'] )
		{
			$this->testSession->setCurrentQuestionId( (int)$_GET['sequence'] );
			$this->testSession->saveToDb();
			
			$this->ctrl->setParameter(
					$this, 'sequence', $this->testSession->getCurrentQuestionId()
			);
		}
		
		$this->ctrl->redirect($this, 'showQuestion');
	}
	
	protected function showQuestionCmd()
	{
		$this->handleJavascriptActivationStatus();

		$this->testSequence->loadQuestions(
				$this->dynamicQuestionSetConfig, $this->testSession->getTaxonomyFilterSelection()
		);
		
		$this->testSequence->cleanupQuestions($this->testSession);
		
		if( !$this->testSession->getCurrentQuestionId() )
		{
			$this->testSession->setCurrentQuestionId(
					$this->testSequence->getUpcomingQuestionId()
			);
		}
		
		if( $this->testSession->getCurrentQuestionId() )
		{
			$this->ctrl->setParameter(
					$this, 'sequence', $this->testSession->getCurrentQuestionId()
			);

			$this->outTestPage(false);
		}
		else
		{
			$this->outCurrentlyFinishedPage();
		}
		
		$this->testSequence->saveToDb();
		$this->testSession->saveToDb();
	}
	
	protected function showInstantResponseCmd()
	{
		$this->saveQuestionSolution();

		$this->handleJavascriptActivationStatus();

		$this->testSequence->loadQuestions(
				$this->dynamicQuestionSetConfig, $this->testSession->getTaxonomyFilterSelection()
		);
		
		$this->testSequence->cleanupQuestions($this->testSession);
		
		$this->ctrl->setParameter(
				$this, 'sequence', $this->testSession->getCurrentQuestionId()
		);

		$this->outTestPage(true);
		
		$this->testSequence->saveToDb();
		$this->testSession->saveToDb();
	}
	
	protected function handleQuestionActionCmd()
	{
		$this->updateWorkingTime();

		$this->saveQuestionSolution();
		
		$this->ctrl->setParameter(
				$this, 'sequence', $this->testSession->getCurrentQuestionId()
		);
		
		$this->ctrl->redirect($this, 'showQuestion');
	}
	
	/**
	 * Creates the learners output of a question
	 */
	protected function outWorkingForm($sequence = "", $test_id, $postpone_allowed, $directfeedback = false)
	{
		global $ilUser;
		
		$_SESSION["active_time_id"] = $this->object->startWorkingTime(
				$this->testSession->getActiveId(), $this->testSession->getPass()
		);

		$this->populateContentStyleBlock();
		$this->populateSyntaxStyleBlock();

		$question_gui = $this->object->createQuestionGUI(
				"", $this->testSession->getCurrentQuestionId()
		);
		
		$question_gui->setTargetGui($this);
		
		$question_gui->setQuestionCount(
				$this->testSequence->getLastPositionIndex()
		);
		$question_gui->setSequenceNumber( $this->testSequence->getCurrentPositionIndex(
				$this->testSession->getCurrentQuestionId()
		));
		
		$this->ctrl->setParameter($this, 'sequence', $this->testSession->getCurrentQuestionId());		
		
		if ($this->object->getJavaScriptOutput())
		{
			$question_gui->object->setOutputType(OUTPUT_JAVASCRIPT);
		}

		$is_postponed = $this->testSequence->isPostponedQuestion($question_gui->object->getId());
		$formaction = $this->ctrl->getFormAction($this);

		// output question
		$user_post_solution = FALSE;
		if( isset($_SESSION['previouspost']) )
		{
			$user_post_solution = $_SESSION['previouspost'];
			unset($_SESSION['previouspost']);
		}

		global $ilNavigationHistory;
		$ilNavigationHistory->addItem($_GET["ref_id"], $this->ctrl->getLinkTarget($this, "resumePlayer"), "tst");

		// Determine $answer_feedback: It should hold a boolean stating if answer-specific-feedback is to be given.
		// It gets the parameter "Scoring and Results" -> "Instant Feedback" -> "Show Answer-Specific Feedback"
		// $directfeedback holds a boolean stating if the instant feedback was requested using the "Check" button.
		$answer_feedback = FALSE;
		if (($directfeedback) && ($this->object->getSpecificAnswerFeedback()))
		{
			$answer_feedback = TRUE;
		}
		
		// Answer specific feedback is rendered into the display of the test question with in the concrete question types outQuestionForTest-method.
		// Notation of the params prior to getting rid of this crap in favor of a class
		$question_gui->outQuestionForTest(
				$formaction, 										#form_action
				$this->testSession->getActiveId(), 	#active_id
				NULL, 												#pass
				$is_postponed, 										#is_postponed
				$user_post_solution, 								#user_post_solution
				$answer_feedback									#answer_feedback == inline_specific_feedback
			);
		// The display of specific inline feedback and specific feedback in an own block is to honor questions, which
		// have the possibility to embed the specific feedback into their output while maintaining compatibility to
		// questions, which do not have such facilities. E.g. there can be no "specific inline feedback" for essay
		// questions, while the multiple-choice questions do well.
				
		$this->fillQuestionRelatedNavigation($question_gui);

		if ($directfeedback)
		{
			// This controls if the solution should be shown.
			// It gets the parameter "Scoring and Results" -> "Instant Feedback" -> "Show Solutions"			
			if ($this->object->getInstantFeedbackSolution())
			{
				$show_question_inline_score = $this->determineInlineScoreDisplay();
				
				// Notation of the params prior to getting rid of this crap in favor of a class
				$solutionoutput = $question_gui->getSolutionOutput(
					$this->testSession->getActiveId(), 	#active_id
					NULL, 												#pass
					TRUE, 												#graphical_output
					$show_question_inline_score,						#result_output
					FALSE, 												#show_question_only
					FALSE,												#show_feedback
					TRUE, 												#show_correct_solution
					FALSE, 												#show_manual_scoring
					FALSE												#show_question_text
				);
				$this->populateSolutionBlock( $solutionoutput );
			}
			
			// This controls if the score should be shown.
			// It gets the parameter "Scoring and Results" -> "Instant Feedback" -> "Show Results (Only Points)"				
			if ($this->object->getAnswerFeedbackPoints())
			{
				$reachedPoints = $question_gui->object->getAdjustedReachedPoints($this->testSession->getActiveId(), NULL);
				$maxPoints = $question_gui->object->getMaximumPoints();

				$this->populateScoreBlock( $reachedPoints, $maxPoints );
			}
			
			// This controls if the generic feedback should be shown.
			// It gets the parameter "Scoring and Results" -> "Instant Feedback" -> "Show Solutions"				
			if ($this->object->getGenericAnswerFeedback())
			{
				$this->populateGenericFeedbackBlock( $question_gui );
			}
			
			// This controls if the specific feedback should be shown.
			// It gets the parameter "Scoring and Results" -> "Instant Feedback" -> "Show Answer-Specific Feedback"
			if ($this->object->getSpecificAnswerFeedback())
			{
				$this->populateSpecificFeedbackBlock( $question_gui );				
			}
		}

		$this->populatePreviousButtons( $this->testSession->getCurrentQuestionId() );

		if( $postpone_allowed )
		{
			$this->populatePostponeButtons();
		}

		if ($this->object->getShowCancel()) 
		{
			$this->populateCancelButtonBlock();
		}		

		if ($this->isLastQuestionInSequence( $question_gui ))
		{
			if ($this->object->getListOfQuestionsEnd()) 
			{
				$this->populateNextButtonsLeadingToSummary();				
			} 
			else 
			{
				$this->populateNextButtonsLeadingToEndOfTest();
			}
		}
		else
		{
			$this->populateNextButtonsLeadingToQuestion();
		}
		
		if( $this->dynamicQuestionSetConfig->isTaxonomyFilterEnabled() )
		{
			$this->populateQuestionSelectionButtons();
		}
		
		if ($this->object->getShowMarker())
		{
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			$solved_array = ilObjTest::_getSolvedQuestions($this->testSession->getActiveId(), $question_gui->object->getId());
			$solved = 0;
			
			if (count ($solved_array) > 0) 
			{
				$solved = array_pop($solved_array);
				$solved = $solved["solved"];
			}
			
			if ($solved==1) 
			{
				$this->populateQuestionMarkingBlockAsMarked();
			} 
			else 
			{
				$this->populateQuestionMarkingBlockAsUnmarked();
			}
		}

		if ($this->object->getJavaScriptOutput())
		{
			$this->tpl->setVariable("JAVASCRIPT_IMAGE", ilUtil::getImagePath("javascript_disable.png"));
			$this->tpl->setVariable("JAVASCRIPT_IMAGE_ALT", $this->lng->txt("disable_javascript"));
			$this->tpl->setVariable("JAVASCRIPT_IMAGE_TITLE", $this->lng->txt("disable_javascript"));
			$this->ctrl->setParameter($this, "tst_javascript", "0");
			$this->tpl->setVariable("JAVASCRIPT_URL", $this->ctrl->getLinkTarget($this, "gotoQuestion"));
		}
		else
		{
			$this->tpl->setVariable("JAVASCRIPT_IMAGE", ilUtil::getImagePath("javascript.png"));
			$this->tpl->setVariable("JAVASCRIPT_IMAGE_ALT", $this->lng->txt("enable_javascript"));
			$this->tpl->setVariable("JAVASCRIPT_IMAGE_TITLE", $this->lng->txt("enable_javascript"));
			$this->ctrl->setParameter($this, "tst_javascript", "1");
			$this->tpl->setVariable("JAVASCRIPT_URL", $this->ctrl->getLinkTarget($this, "gotoQuestion"));
		}

		if ($question_gui->object->supportsJavascriptOutput())
		{
			$this->tpl->touchBlock("jsswitch");
		}

		$this->tpl->addJavaScript(ilUtil::getJSLocation("autosave.js", "Modules/Test"));
		
		$this->tpl->setVariable("AUTOSAVE_URL", $this->ctrl->getFormAction($this, "autosave", "", true));

		if ($question_gui->isAutosaveable()&& $this->object->getAutosave())
		{
			$this->tpl->touchBlock('autosave');
			//$this->tpl->setVariable("BTN_SAVE", "Zwischenspeichern");
			//$this->tpl->setVariable("CMD_SAVE", "gotoquestion_{$sequence}");
			//$this->tpl->setVariable("AUTOSAVEFORMACTION", str_replace("&amp;", "&", $this->ctrl->getFormAction($this)));
			$this->tpl->setVariable("AUTOSAVEFORMACTION", str_replace("&amp;", "&", $this->ctrl->getLinkTarget($this, "autosave")));
			$this->tpl->setVariable("AUTOSAVEINTERVAL", $this->object->getAutosaveIval());
		}
		
		if( $this->object->areObligationsEnabled() && ilObjTest::isQuestionObligatory($question_gui->object->getId()) )
		{
		    $this->tpl->touchBlock('question_obligatory');
		    $this->tpl->setVariable('QUESTION_OBLIGATORY', $this->lng->txt('required_field'));
		}
	}

	private function outCurrentlyFinishedPage()
	{
		$this->prepareTestPageOutput();
		
		$this->populatePreviousButtons( $this->testSession->getCurrentQuestionId() );
			
		if ($this->object->getKioskMode())
		{
			$this->populateKioskHead();
		}

		if ($this->object->getEnableProcessingTime())
		{
			$this->outProcessingTime($this->testSession->getActiveId());
		}

		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("FORM_TIMESTAMP", time());
		
		$this->tpl->setVariable("PAGETITLE", "- " . $this->object->getTitle());
		
		if ($this->object->getShowExamid() && !$this->object->getKioskMode())
		{
			$this->tpl->setCurrentBlock('exam_id');
			$this->tpl->setVariable('EXAM_ID', $this->object->getExamId(
					$this->testSession->getActiveId(), $this->testSession->getPass()
			));
			$this->tpl->setVariable('EXAM_ID_TXT', $this->lng->txt('exam_id'));
			$this->tpl->parseCurrentBlock();
		}
		
		if ($this->object->getShowCancel()) 
		{
			$this->populateCancelButtonBlock();
		}
		
		if( $this->dynamicQuestionSetConfig->isTaxonomyFilterEnabled() )
		{
			$this->populateQuestionSelectionButtons();
		}
		
		if( $this->testSequence->openQuestionExists() )
		{
			$msgLangVar = 'tst_dyn_test_msg_currently_finished_selection';
		}
		else
		{
			$msgLangVar = 'tst_dyn_test_msg_currently_finished_completely';
		}
		
		$msgHtml = $this->tpl->getMessageHTML($this->lng->txt($msgLangVar));
		
		$this->tpl->addBlockFile(
				'QUESTION_OUTPUT', 'test_currently_finished_msg_block',
				'tpl.test_currently_finished_msg.html', 'Modules/Test'
		);
		
		$this->tpl->setCurrentBlock('test_currently_finished_msg_block');
		$this->tpl->setVariable('TEST_CURRENTLY_FINISHED_MSG', $msgHtml);
		$this->tpl->parseCurrentBlock();

	}
	
	protected function isFirstPageInSequence($sequence)
	{
		return !$this->testSequence->trackedQuestionExists();
	}

	protected function isLastQuestionInSequence(assQuestionGUI $questionGUI)
	{
		return false; // always
	}
	
	protected function handleJavascriptActivationStatus()
	{
		global $ilUser;
		
		if( isset($_GET['tst_javascript']) )
		{
			$ilUser->writePref('tst_javascript', $_GET['tst_javascript']);
		}
	}
	
	/**
	 * Returns TRUE if the answers of the current user could be saved
	 *
	 * @return boolean TRUE if the answers could be saved, FALSE otherwise
	 */
	 protected function canSaveResult() 
	 {
		 return !$this->object->endingTimeReached();
	 }
	 
	/**
	 * saves the user input of a question
	 */
	public function saveQuestionSolution($force = FALSE)
	{
		// what is this formtimestamp ??
		if (!$force)
		{
			$formtimestamp = $_POST["formtimestamp"];
			if (strlen($formtimestamp) == 0) $formtimestamp = $_GET["formtimestamp"];
			if ($formtimestamp != $_SESSION["formtimestamp"])
			{
				$_SESSION["formtimestamp"] = $formtimestamp;
			}
			else
			{
				return FALSE;
			}
		}
		
		// determine current question
		
		$qId = $this->testSession->getCurrentQuestionId();
		
		if( !$qId || $qId != $_GET["sequence"])
		{
			return false;
		}
		
		// save question solution
		
		$this->saveResult = FALSE;

		if ($this->canSaveResult() || $force)
		{
				global $ilUser;
				
				$questionGUI = $this->object->createQuestionGUI("", $qId);
				
				if( $this->object->getJavaScriptOutput() )
				{
					$questionGUI->object->setOutputType(OUTPUT_JAVASCRIPT);
				}
				
				$activeId = $this->testSession->getActiveId();
				
				$this->saveResult = $questionGUI->object->persistWorkingState(
						$activeId, $pass = null, $this->object->areObligationsEnabled()
				);
		}
		
		if ($this->saveResult == FALSE)
		{
			$this->ctrl->setParameter($this, "save_error", "1");
			$_SESSION["previouspost"] = $_POST;
		}
		
		return $this->saveResult;
	}
	
	private function isQuestionAnsweredCorrect($questionId, $activeId)
	{
		$questionGUI = $this->object->createQuestionGUI("", $questionId);

		$reachedPoints = assQuestion::_getReachedPoints($activeId, $questionId, 0);
		$maxPoints = $questionGUI->object->getMaximumPoints();
		
		if($reachedPoints < $maxPoints)
		{
			return false;
		}
		
		return true;
	}


	protected function populatePreviousButtons($sequence)
	{
		if( !$this->isFirstPageInSequence($sequence) )
		{
			$this->populateUpperPreviousButtonBlock(
					'showTrackedQuestionList', "&lt;&lt; " . $this->lng->txt( "save_previous" )
			);
			$this->populateLowerPreviousButtonBlock(
					'showTrackedQuestionList', "&lt;&lt; " . $this->lng->txt( "save_previous" )
			);
		}
	}
	
	protected function buildQuestionsTableDataArray($questions, $marked_questions)
	{
		$data = array();
		
		foreach($questions as $key => $value )
		{
			$this->ctrl->setParameter($this, 'sequence', $value['question_id']);
			$href = $this->ctrl->getLinkTarget($this, 'gotoQuestion');
			$this->ctrl->setParameter($this, 'sequence', '');
			
			$description = "";
			if( $this->object->getListOfQuestionsDescription() )
			{
				$description = $value["description"];
			}
			
			$marked = false;
			if( count($marked_questions) )
			{
				if( isset($marked_questions[$value["question_id"]]) )
				{
					if( $marked_questions[$value["question_id"]]["solved"] == 1 )
					{
						$marked = true;
					}
				} 
			}
			
			array_push($data, array(
				'href' => $href,
				'title' => $this->object->getQuestionTitle($value["title"]),
				'description' => $description,
				'worked_through' => $this->testSequence->isAnsweredQuestion($value["question_id"]),
				'postponed' => $this->testSequence->isPostponedQuestion($value["question_id"]),
				'marked' => $marked
			));
		}
		
		return $data;
	}
	
	private function buildFilteredQuestionsTableGUI()
	{
		require_once 'Services/Taxonomy/classes/class.ilObjTaxonomy.php';
		$taxIds = ilObjTaxonomy::getUsageOfObject(
				$this->dynamicQuestionSetConfig->getSourceQuestionPoolId()
		);

		include_once "./Modules/Test/classes/tables/class.ilFilteredQuestionsTableGUI.php";
		$gui = new ilFilteredQuestionsTableGUI(
				$this, 'showQuestionSelection', $this->object->getShowMarker(), $taxIds
		);
		
		$gui->setFilterCommand('filterQuestionSelection');
		$gui->setResetCommand('resetQuestionSelection');
		
		return $gui;
	}
	
	private function getEnterTestButtonLangVar()
	{
		if( $this->testSequence->trackedQuestionExists() )
		{
			return $this->lng->txt('tst_resume_dyn_test_with_cur_quest_sel');
		}
		
		return $this->lng->txt('tst_start_dyn_test_with_cur_quest_sel');
	}
}
