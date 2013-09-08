<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once './Modules/Test/classes/class.ilTestPlayerAbstractGUI.php';

/**
 * Output class for assessment test execution
 *
 * The ilTestOutputGUI class creates the output for the ilObjTestGUI class when learners execute a test. This saves
 * some heap space because the ilObjTestGUI class will be much smaller then
 * 
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com>
 * @author		Björn Heyser <bheyser@databay.de>
 * @author		Maximilian Becker <mbecker@databay.de>
 *          
 * @version		$Id: class.ilTestOutputGUI.php 44168 2013-08-15 08:46:56Z mbecker $
 * 
 * @inGroup		ModulesTest
 * 
 * @ilCtrl_Calls ilTestOutputGUI: ilAssQuestionHintRequestGUI, ilAssSpecFeedbackPageGUI, ilAssGenFeedbackPageGUI
 * @ilCtrl_Calls ilTestOutputGUI: ilTestSignatureGUI
 */
class ilTestOutputGUI extends ilTestPlayerAbstractGUI
{
	/**
	 * Execute Command
	 */
	public function executeCommand()
	{
		global $ilUser, $ilDB, $ilPluginAdmin, $lng, $ilTabs;

		$ilTabs->clearTargets();
		
		$cmd = $this->ctrl->getCmd();
		$next_class = $this->ctrl->getNextClass($this);
		
		$this->ctrl->saveParameter($this, "sequence");
		$this->ctrl->saveParameter($this, "active_id");
		
		if (preg_match("/^gotoquestion_(\\d+)$/", $cmd, $matches))
		{
			$cmd = "gotoquestion";
			if (strlen($matches[1]))
			{
				$this->ctrl->setParameter($this, 'gotosequence', $matches[1]);
			}
		}
		
		$testSessionFactory = new ilTestSessionFactory($this->object);
		$this->testSession = $testSessionFactory->getSession($_GET['active_id']);
		
		if( !$this->testSession->getActiveId() )
		{
			$this->testSession->setUserId($ilUser->getId());
			$this->testSession->setAnonymousId($_SESSION["tst_access_code"][$this->object->getTestId()]);
			$this->testSession->saveToDb();
		}
		
		$testSequenceFactory = new ilTestSequenceFactory($ilDB, $lng, $ilPluginAdmin, $this->object);
		$this->testSequence = $testSequenceFactory->getSequence($this->testSession);
		$this->testSequence->loadFromDb();
		$this->testSequence->loadQuestions();

		include_once 'Services/jQuery/classes/class.iljQueryUtil.php';
		iljQueryUtil::initjQuery();
		include_once "./Services/YUI/classes/class.ilYuiUtil.php";
		ilYuiUtil::initConnectionWithAnimation();
		
		$cmd = $this->getCommand($cmd);
		
		switch($next_class)
		{
			case 'iltestsubmissionreviewgui':
				require_once './Modules/Test/classes/class.ilTestSubmissionReviewGUI.php';
				$gui = new ilTestSubmissionReviewGUI($this, $this->object);
				$ret = $this->ctrl->forwardCommand($gui);
				break;
			
			case 'ilassquestionhintrequestgui':
				
				$questionGUI = $this->object->createQuestionGUI(
					"", $this->testSequence->getQuestionForSequence( $this->calculateSequence() )
				);
				
				require_once 'Modules/TestQuestionPool/classes/class.ilAssQuestionHintRequestGUI.php';
				$gui = new ilAssQuestionHintRequestGUI($this, $this->testSession, $questionGUI);
				
				$ret = $this->ctrl->forwardCommand($gui);
				
				break;
			
			case 'iltestsignaturegui':
				require_once './Modules/Test/classes/class.ilTestSignatureGUI.php';
				$gui = new ilTestSignatureGUI($this);
				$ret = $this->ctrl->forwardCommand($gui);
				break;

			default:
				
				$cmd .= 'Cmd';
				$ret =& $this->$cmd();
				break;
		}
		return $ret;
	}

	protected function startTestCmd()
	{
		$_GET['activecommand'] = 'start';
		$this->redirectQuestionCmd();
	}

	/**
	 * Go to the next question
	 */
	protected function nextQuestionCmd()
	{
		$this->saveQuestionSolution();
		$this->ctrl->setParameter($this, "activecommand", "next");
		$this->ctrl->redirect($this, "redirectQuestion");
	}

	/**
	 * Go to the previous question
	 */
	protected function previousQuestionCmd()
	{
		$this->saveQuestionSolution();
		$this->ctrl->setParameter($this, "activecommand", "previous");
		$this->ctrl->redirect($this, "redirectQuestion");
	}

	/**
	 * Called when a user answered a question to perform a redirect after POST.
	 * This is called for security reasons to prevent users sending a form twice.
	 * --> description up to date ??
	 *
	 * @access public
	 */
	protected function redirectQuestionCmd()
	{
		global $ilUser;

		// check the test restrictions to access the test in case one
		// of the test navigation commands was called by an external script
		// e.g. $ilNavigationHistory
		$executable = $this->object->isExecutable($this->testSession, $ilUser->getId());
		if (!$executable["executable"])
		{
			ilUtil::sendInfo($executable["errormessage"], TRUE);
			$this->ctrl->redirectByClass("ilobjtestgui", "infoScreen");
		}
		switch ($_GET["activecommand"])
		{
			case "next":
				$this->sequence = $this->calculateSequence();
				if ($this->sequence === FALSE)
				{
					if ($this->object->getListOfQuestionsEnd())
					{
						
						$allObligationsAnswered = ilObjTest::allObligationsAnswered(
								$this->testSession->getTestId(),
								$this->testSession->getActiveId(),
								$this->testSession->getPass()
						);

						if( $this->object->areObligationsEnabled() && !$allObligationsAnswered )
						{
							$this->ctrl->redirect($this, "outQuestionSummaryWithObligationsInfo");
						}
						
						$this->outQuestionSummaryCmd();
					}
					else
					{
						$this->ctrl->redirect($this, "finishTest");
					}
				}
				else
				{
					$this->testSession->setLastSequence($this->sequence);
					$this->testSession->saveToDb();
					$this->outTestPage(false);
				}
				break;
			case "previous":
				$this->sequence = $this->calculateSequence();
				$this->testSession->setLastSequence($this->sequence);
				$this->testSession->saveToDb();
				if ($this->sequence === FALSE)
				{
					$this->ctrl->redirect($this, "outIntroductionPage");
				}
				else
				{
					$this->outTestPage(false);
				}
				break;
			case "postpone":
				$this->sequence = $this->calculateSequence();
				$nextSequence = $this->testSequence->getNextSequence($this->sequence);
				$this->testSequence->postponeSequence($this->sequence);
				$this->testSequence->saveToDb();
				$this->testSession->setLastSequence($nextSequence);
				$this->testSession->saveToDb();
				$this->sequence = $nextSequence;
				$this->outTestPage(false);
				break;
			case "setmarked":
				$this->sequence = $this->calculateSequence();	
				$this->testSession->setLastSequence($this->sequence);
				$this->testSession->saveToDb();
				$q_id  = $this->testSequence->getQuestionForSequence($_GET["sequence"]);
				$this->object->setQuestionSetSolved(1, $q_id, $ilUser->getId());
				$this->outTestPage(false);
				break;
			case "resetmarked":
				$this->sequence = $this->calculateSequence();	
				$this->testSession->setLastSequence($this->sequence);
				$this->testSession->saveToDb();
				$q_id  = $this->testSequence->getQuestionForSequence($_GET["sequence"]);
				$this->object->setQuestionSetSolved(0, $q_id, $ilUser->getId());
				$this->outTestPage(false);
				break;
			case "directfeedback":
				$this->sequence = $this->calculateSequence();	
				$this->testSession->setLastSequence($this->sequence);
				$this->testSession->saveToDb();
				$this->outTestPage(true);
				break;
			case "handleQuestionAction":
				$this->sequence = $this->calculateSequence();	
				$this->testSession->setLastSequence($this->sequence);
				$this->testSession->saveToDb();
				$this->outTestPage(false);
				break;
			case "summary":
				$this->ctrl->redirect($this, "outQuestionSummary");
				break;
			case "summary_obligations":
				$this->ctrl->redirect($this, "outQuestionSummaryWithObligationsInfo");
				break;
			case "summary_obligations_only":
				$this->ctrl->redirect($this, "outObligationsOnlySummary");
				break;
			case "start":
				$_SESSION['tst_pass_finish'] = 0;
				
				// ensure existing test session
				$this->testSession->setUserId($ilUser->getId());
				$this->testSession->setAnonymousId($_SESSION["tst_access_code"][$this->object->getTestId()]);
				$this->testSession->saveToDb();
				
				$active_id = $this->testSession->getActiveId();
				
				assQuestion::_updateTestPassResults(
						$active_id, $this->testSession->getPass(), $this->object->areObligationsEnabled()
				);
				
				$this->ctrl->setParameter($this, "active_id", $active_id);
				$shuffle = $this->object->getShuffleQuestions();
				if ($this->object->isRandomTest())
				{
					$this->object->generateRandomQuestions($this->testSession->getActiveId());
					$this->object->loadQuestions();
					$shuffle = FALSE; // shuffle is already done during the creation of the random questions
				}
				
				// ensure existing test sequence
				if( !$this->testSequence->hasSequence() )
				{
					$this->testSequence->createNewSequence($this->object->getQuestionCount(), $shuffle);
					$this->testSequence->saveToDb();
				}
				
				$active_time_id = $this->object->startWorkingTime($this->testSession->getActiveId(), $this->testSession->getPass());
				$_SESSION["active_time_id"] = $active_time_id;
				if ($this->object->getListOfQuestionsStart())
				{
					$this->ctrl->setParameter($this, "activecommand", "summary");
					$this->ctrl->redirect($this, "redirectQuestion");
				}
				else
				{
					$this->ctrl->setParameter($this, "sequence", $this->sequence);
					$this->ctrl->setParameter($this, "activecommand", "gotoquestion");
					$this->ctrl->saveParameter($this, "tst_javascript");
					$this->ctrl->redirect($this, "redirectQuestion");
				}
				break;
			case "resume":
				$_SESSION['tst_pass_finish'] = 0;
				$active_id = $this->testSession->getActiveId();
				$this->ctrl->setParameter($this, "active_id", $active_id);

				if ($this->object->isRandomTest())
				{
					if (!$this->object->hasRandomQuestionsForPass($active_id, $this->testSession->getPass()))
					{
						// create a new set of random questions
						$this->object->generateRandomQuestions($active_id, $this->testSession->getPass());
					}
				}
				$shuffle = $this->object->getShuffleQuestions();
				if ($this->object->isRandomTest())
				{
					$shuffle = FALSE;
				}

				// ensure existing test sequence
				if( !$this->testSequence->hasSequence() )
				{
					$this->testSequence->createNewSequence($this->object->getQuestionCount(), $shuffle);
					$this->testSequence->saveToDb();
				}

				$this->sequence = $this->testSession->getLastSequence();
				$active_time_id = $this->object->startWorkingTime($active_id, $this->testSession->getPass());
				$_SESSION["active_time_id"] = $active_time_id;
				if ($this->object->getListOfQuestionsStart())
				{
					$this->ctrl->setParameter($this, "activecommand", "summary");
					$this->ctrl->redirect($this, "redirectQuestion");
				}
				else
				{
					$this->ctrl->setParameter($this, "sequence", $this->sequence);
					$this->ctrl->setParameter($this, "activecommand", "gotoquestion");
					$this->ctrl->saveParameter($this, "tst_javascript");
					$this->ctrl->redirect($this, "redirectQuestion");
				}
				break;
				
			case 'test_submission_overview':
				require_once './Modules/Test/classes/class.ilTestSubmissionReviewGUI.php';
				$this->ctrl->redirect(new ilTestSubmissionReviewGUI($this, $this->object), "show");
				break;
			
			case "back":
			case "gotoquestion":
			default:
				$_SESSION['tst_pass_finish'] = 0;
				if (array_key_exists("tst_javascript", $_GET))
				{
					$ilUser->writePref("tst_javascript", $_GET["tst_javascript"]);
				}
				$this->sequence = $this->calculateSequence();	
				if (strlen($_GET['gotosequence'])) $this->sequence = $_GET['gotosequence'];
				$this->testSession->setLastSequence($this->sequence);
				$this->testSession->saveToDb();
				$this->outTestPage(false);
				break;
		}
	}

	/**
	 * Creates the learners output of a question
	 */
	protected function outWorkingForm($sequence = "", $test_id, $postpone_allowed, $directfeedback = false)
	{
		global $ilUser;
		
		if ($sequence < 1) $sequence = $this->testSequence->getFirstSequence();
		
		$_SESSION["active_time_id"]= $this->object->startWorkingTime($this->testSession->getActiveId(), 
																	 $this->testSession->getPass()
		);

		$this->populateContentStyleBlock();
		$this->populateSyntaxStyleBlock();

		if ($this->object->getListOfQuestions())
		{
			$this->showSideList();
		}
		
		$question_gui = $this->object->createQuestionGUI("", $this->testSequence->getQuestionForSequence($sequence));
		$question_gui->setTargetGui($this);
		
		if ($this->object->getJavaScriptOutput())
		{
			$question_gui->object->setOutputType(OUTPUT_JAVASCRIPT);
		}

		$is_postponed = $this->testSequence->isPostponedQuestion($question_gui->object->getId());
		$this->ctrl->setParameter($this, "sequence", "$sequence");
		$formaction = $this->ctrl->getFormAction($this, "gotoQuestion");

		$question_gui->setSequenceNumber($this->testSequence->getPositionOfSequence($sequence));
		$question_gui->setQuestionCount($this->testSequence->getUserQuestionCount());
		
		
		// output question
		$user_post_solution = FALSE;
		if (array_key_exists("previouspost", $_SESSION))
		{
			$user_post_solution = $_SESSION["previouspost"];
			unset($_SESSION["previouspost"]);
		}

		global $ilNavigationHistory;
		$ilNavigationHistory->addItem($_GET["ref_id"], $this->ctrl->getLinkTarget($this, "resume"), "tst");

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
					FALSE, 												#graphical_output
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

		$this->populatePreviousButtons( $sequence );

		if ($postpone_allowed && !$is_postponed)
		{
			$this->populatePostponeButtons();
		}
		
		if ($this->object->getListOfQuestions()) 
		{
			$this->populateSummaryButtons();
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

	protected function isFirstPageInSequence($sequence)
	{
		return $sequence == $this->testSequence->getFirstSequence();
	}

	protected function isLastQuestionInSequence(assQuestionGUI $question_gui)
	{
		return $this->testSequence->getQuestionForSequence($this->testSequence->getLastSequence()) == $question_gui->object->getId();
	}

	/**
	 * Returns TRUE if the answers of the current user could be saved
	 *
	 * @return boolean TRUE if the answers could be saved, FALSE otherwise
	 */
	 protected function canSaveResult() 
	 {
		 return !$this->object->endingTimeReached() && !$this->isMaxProcessingTimeReached() && !$this->isNrOfTriesReached();
	 }
	
	/**
	 * saves the user input of a question
	 */
	public function saveQuestionSolution($force = FALSE)
	{
		$this->updateWorkingTime();
		$this->saveResult = FALSE;
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
		// save question solution
		if ($this->canSaveResult() || $force)
		{
			// but only if the ending time is not reached
			$q_id = $this->testSequence->getQuestionForSequence($_GET["sequence"]);
			if (is_numeric($q_id) && (int)$q_id) 
			{
				global $ilUser;
				
				$question_gui = $this->object->createQuestionGUI("", $q_id);
				if ($this->object->getJavaScriptOutput())
				{
					$question_gui->object->setOutputType(OUTPUT_JAVASCRIPT);
				}
				$pass = NULL;
				$active_id = $this->testSession->getActiveId();
				if ($this->object->isRandomTest())
				{
					$pass = $this->object->_getPass($active_id);
				}
				$this->saveResult = $question_gui->object->persistWorkingState(
						$active_id, $pass, $this->object->areObligationsEnabled()
				);

				// update learning progress (is done in ilTestSession)
				//include_once("./Services/Tracking/classes/class.ilLPStatusWrapper.php");
				//ilLPStatusWrapper::_updateStatus($this->object->getId(), $ilUser->getId());
			}
		}
		if ($this->saveResult == FALSE)
		{
			$this->ctrl->setParameter($this, "save_error", "1");
			$_SESSION["previouspost"] = $_POST;
		}
		return $this->saveResult;
	}

	protected function showInstantResponseCmd()
	{
		$this->saveQuestionSolution();
		$this->ctrl->setParameter($this, "activecommand", "directfeedback");
		$this->ctrl->redirect($this, "redirectQuestion");
	}

	protected function showQuestionListCmd()
	{
		$this->saveQuestionSolution();
		if ($this->saveResult == FALSE)
		{
			$this->ctrl->setParameter($this, "activecommand", "");
			$this->ctrl->redirect($this, "redirectQuestion");
		}
		else
		{
			$this->ctrl->setParameter($this, "activecommand", "summary");
			$this->ctrl->redirect($this, "redirectQuestion");
		}
	}

	protected function showQuestionListWithoutSavingCmd()
	{
		$this->ctrl->setParameter($this, "activecommand", "summary");
		$this->ctrl->redirect($this, "redirectQuestion");
	}

	/**
	 * Postpone a question to the end of the test
	 *
	 * @access public
	 */
	protected function postponeQuestionCmd()
	{
		$this->saveQuestionSolution();
		$this->ctrl->setParameter($this, "activecommand", "postpone");
		$this->ctrl->redirect($this, "redirectQuestion");
	}

	protected function handleQuestionActionCmd()
	{
		$this->updateWorkingTime();

		$this->saveQuestionSolution();
		
		$this->ctrl->setParameter($this, 'activecommand', 'handleQuestionAction');
		$this->ctrl->redirect($this, 'redirectQuestion');
	}
}