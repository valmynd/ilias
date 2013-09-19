<?php
include_once "./Modules/TestQuestionPool/classes/class.assQuestionGUI.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";

/**
 * GUI representation
 */
class placeholderQuestionGUI extends assQuestionGUI
{

	/**
	 * Returns the answer specific feedback for the question
	 *
	 * This method should be overwritten by the actual question.
	 *
	 * @param integer $active_id Active ID of the user
	 * @param integer $pass Active pass
	 * @return string HTML Code with the answer specific feedback
	 * @access public
	 */
	function getSpecificFeedbackOutput($active_id, $pass)
	{
		// TODO: Implement getSpecificFeedbackOutput() method.
	}

	public function getSolutionOutput(
		$active_id,
		$pass = NULL,
		$graphicalOutput = FALSE,
		$result_output = FALSE,
		$show_question_only = TRUE,
		$show_feedback = FALSE,
		$show_correct_solution = FALSE,
		$show_manual_scoring = FALSE,
		$show_question_text = TRUE
	)
	{
		// TODO: Implement getSolutionOutput() method.
	}
}
