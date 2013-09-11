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
	 */
	function getSpecificFeedbackOutput($active_id, $pass)
	{
		return "This is a placeholder for the help text";
	}

	/**
	 * The getSolutionOutput() method is used to print either the
	 * user's pass' solution or the best possible solution for the
	 * current errorText question object.
	 *
	 * @param    integer $active_id             The active test id
	 * @param    integer $pass                  The test pass counter
	 * @param    boolean $graphicalOutput       Show visual feedback for right/wrong answers
	 * @param    boolean $result_output         Show the reached points for parts of the question
	 * @param    boolean $show_question_only    Show the question without the ILIAS content around
	 * @param    boolean $show_feedback         Show the question feedback
	 * @param    boolean $show_correct_solution Show the correct solution instead of the user solution
	 * @param    boolean $show_manual_scoring   Show specific information for the manual scoring outp
	 * @param    boolean $show_question_text    Show the question text
	 * @return   string HTML solution output
	 **/
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
		return "This is a Solution Output Placeholder";
	}
}
