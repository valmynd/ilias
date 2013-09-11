<?php
include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
include_once "./Modules/Test/classes/inc.AssessmentConstants.php";

class placeholderQuestion extends assQuestion
{
	function __construct($title = "", $comment = "", $author = "", $owner = -1, $question = "")
	{
		parent::__construct($title, $comment, $author, $owner, $question);
	}

	public function isComplete()
	{
		return true;
	}

	function getQuestionType()
	{
		return "placeholderQuestion";
	}

	/**
	 * Saves the learners input of the question to the database.
	 *
	 * @access public
	 * @param integer $active_id Active id of the user
	 * @param integer $pass Test pass
	 * @return boolean $status
	 */
	public function saveWorkingData($active_id, $pass = NULL)
	{
		return false;
	}

	/**
	 * Reworks the allready saved working data if neccessary
	 *
	 * @access protected
	 * @param integer $active_id
	 * @param integer $pass
	 * @param boolean $obligationsAnswered
	 */
	protected function reworkWorkingData($active_id, $pass, $obligationsAnswered)
	{
		return true;
	}

	/**
	 * Returns the points, a learner has reached answering the question.
	 * The points are calculated from the given answers.
	 *
	 * @access public
	 * @param integer $active_id
	 * @param integer $pass
	 * @param boolean $returndetails (deprecated !!)
	 * @return integer/array $points/$details (array $details is deprecated !!)
	 */
	public function calculateReachedPoints($active_id, $pass = NULL, $returndetails = FALSE)
	{
		return 0;
	}
}

?>
