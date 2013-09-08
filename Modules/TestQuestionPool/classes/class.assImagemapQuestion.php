<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once './Modules/TestQuestionPool/classes/class.assQuestion.php';
require_once './Modules/Test/classes/inc.AssessmentConstants.php';
require_once './Modules/TestQuestionPool/interfaces/ilObjQuestionScoringAdjustable.php';
require_once './Modules/TestQuestionPool/interfaces/ilObjAnswerScoringAdjustable.php';

/**
 * Class for image map questions
 *
 * assImagemapQuestion is a class for imagemap question.
 * 
 * @author		Helmut Schottmüller <helmut.schottmueller@mac.com> 
 * @author		Björn Heyser <bheyser@databay.de>
 * @author		Maximilian Becker <mbecker@databay.de>
 * 
 * @version		$Id: class.assImagemapQuestion.php 44082 2013-08-12 15:06:54Z bheyser $
 * 
 * @ingroup		ModulesTestQuestionPool
 */
class assImagemapQuestion extends assQuestion implements ilObjQuestionScoringAdjustable, ilObjAnswerScoringAdjustable
{
	const MODE_SINGLE_CHOICE   = 0;
	const MODE_MULTIPLE_CHOICE = 1;

	/** @var $answers array The possible answers of the imagemap question. */
	var $answers;

	/** @var $image_filename string The image file containing the name of image file. */
	var $image_filename;

	/** @var $imagemap_contents string The variable containing contents of an imagemap file. */
	var $imagemap_contents;
	
	/** @var $coords array */
	var $coords;

	/** @var $is_multiple_choice bool Defines weather the Question is a Single or a Multiplechoice question. */
	protected $is_multiple_choice = false;

	/**
	 * assImagemapQuestion constructor
	 *
	 * The constructor takes possible arguments an creates an instance of the assImagemapQuestion object.
	 *
	 * @param string  $title    		A title string to describe the question.
	 * @param string  $comment  		A comment string to describe the question.
	 * @param string  $author   		A string containing the name of the questions author.
	 * @param integer $owner    		A numerical ID to identify the owner/creator.
	 * @param string  $question 		The question string of the imagemap question.
	 * @param string  $image_filename
	 * 
	 * @return \assImagemapQuestion
	 */
	public function __construct(
		$title = "",
		$comment = "",
		$author = "",
		$owner = -1,
		$question = "",
		$image_filename = ""
	)
	{
		parent::__construct($title, $comment, $author, $owner, $question);
		$this->image_filename = $image_filename;
		$this->answers = array();
		$this->coords = array();
	}

	/**
	 * Set true if the Imagemapquestion is a multiplechoice Question
	 *
	 * @param bool $is_multiple_choice
	 */
	public function setIsMultipleChoice($is_multiple_choice)
	{
		$this->is_multiple_choice = $is_multiple_choice;
	}

	/**
	 * Returns true, if the imagemap question is a multiplechoice question
	 *
	 * @return bool
	 */
	public function getIsMultipleChoice()
	{
		return $this->is_multiple_choice;
	}

/**
* Returns true, if a imagemap question is complete for use
*
* @return boolean True, if the imagemap question is complete for use, otherwise false
* @access public
*/
	function isComplete()
	{
		if (strlen($this->title) 
			&& ($this->author) 
			&& ($this->question) 
			&& ($this->image_filename) 
			&& (count($this->answers)) 
			&& ($this->getMaximumPoints() > 0)
		)
		{
			return true;
		}
		return false;
	}

	/**
	 * Saves an assImagemapQuestion object to a database
	 *
	 * Saves an assImagemapQuestion object to a database
	 *
	 * @param string $original_id
	 *
	 * @return mixed|void
	 */
	public function saveToDb($original_id = "")
	{
		$this->saveQuestionDataToDb($original_id);
		$this->saveAdditionalQuestionDataToDb();
		$this->saveAnswerSpecificDataToDb();
		parent::saveToDb($original_id);
	}

	public function saveAnswerSpecificDataToDb()
	{
		global $ilDB;
		$ilDB->manipulateF( "DELETE FROM qpl_a_imagemap WHERE question_fi = %s",
							array( "integer" ),
							array( $this->getId() )
		);

		// Anworten wegschreiben
		foreach ($this->answers as $key => $value)
		{
			$answer_obj   = $this->answers[$key];
			$next_id      = $ilDB->nextId( 'qpl_a_imagemap' );
			$ilDB->manipulateF( "INSERT INTO qpl_a_imagemap (answer_id, question_fi, answertext, points, aorder, coords, area, points_unchecked) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)",
									array( "integer", "integer", "text", "float", "integer", "text", "text", "float" ),
									array( $next_id, $this->id, $answer_obj->getAnswertext(
									), $answer_obj->getPoints(), $answer_obj->getOrder(
									), $answer_obj->getCoords(), $answer_obj->getArea(
									), $answer_obj->getPointsUnchecked() )
			);
		}
	}

	public function saveAdditionalQuestionDataToDb()
	{
		global $ilDB;
		
		$ilDB->manipulateF( "DELETE FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s",
							array( "integer" ),
							array( $this->getId() )
		);
		
		$ilDB->manipulateF( "INSERT INTO " . $this->getAdditionalTableName(
																		) . " (question_fi, image_file, is_multiple_choice) VALUES (%s, %s, %s)",
							array( "integer", "text", 'integer' ),
							array(
								$this->getId(),
								$this->image_filename,
								(int)$this->is_multiple_choice
							)
		);
	}

	/**
* Duplicates an assImagemapQuestion
*
* @access public
*/
	function duplicate($for_test = true, $title = "", $author = "", $owner = "", $testObjId = null)
	{
		if ($this->id <= 0)
		{
			// The question has not been saved. It cannot be duplicated
			return;
		}
		// duplicate the question in database
		$this_id = $this->getId();
		
		if( (int)$testObjId > 0 )
		{
			$thisObjId = $this->getObjId();
		}
		
		$clone = $this;
		include_once ("./Modules/TestQuestionPool/classes/class.assQuestion.php");
		$original_id = assQuestion::_getOriginalId($this->id);
		$clone->id = -1;
		
		if( (int)$testObjId > 0 )
		{
			$clone->setObjId($testObjId);
		}
		
		if ($title)
		{
			$clone->setTitle($title);
		}
		if ($author)
		{
			$clone->setAuthor($author);
		}
		if ($owner)
		{
			$clone->setOwner($owner);
		}
		if ($for_test)
		{
			$clone->saveToDb($original_id);
		}
		else
		{
			$clone->saveToDb();
		}

		// copy question page content
		$clone->copyPageOfQuestion($this_id);
		// copy XHTML media objects
		$clone->copyXHTMLMediaObjectsOfQuestion($this_id);
		// duplicate the image
		$clone->duplicateImage($this_id, $thisObjId);
		
		$clone->onDuplicate($this_id, $clone->getId());
		
		return $clone->id;
	}

	/**
	* Copies an assImagemapQuestion object
	*
	* Copies an assImagemapQuestion object
	*
	* @access public
	*/
	function copyObject($target_questionpool_id, $title = "")
	{
		if ($this->id <= 0)
		{
			// The question has not been saved. It cannot be duplicated
			return;
		}
		// duplicate the question in database
		$clone = $this;
		include_once ("./Modules/TestQuestionPool/classes/class.assQuestion.php");
		$original_id = assQuestion::_getOriginalId($this->id);
		$clone->id = -1;
		$source_questionpool_id = $this->getObjId();
		$clone->setObjId($target_questionpool_id);
		if ($title)
		{
			$clone->setTitle($title);
		}
		$clone->saveToDb();

		// copy question page content
		$clone->copyPageOfQuestion($original_id);
		// copy XHTML media objects
		$clone->copyXHTMLMediaObjectsOfQuestion($original_id);
		// duplicate the image
		$clone->copyImage($original_id, $source_questionpool_id);
		
		$clone->onCopy($source_questionpool_id, $original_id, $clone->getObjId(), $clone->getId());
		
		return $clone->id;
	}
	
	function duplicateImage($question_id, $objectId = null)
	{
		$imagepath = $this->getImagePath();
		$imagepath_original = str_replace("/$this->id/images", "/$question_id/images", $imagepath);
		
		if( (int)$objectId > 0 )
		{
			$imagepath_original = str_replace("/$this->obj_id/", "/$objectId/", $imagepath_original);
		}
		
		if (!file_exists($imagepath)) {
			ilUtil::makeDirParents($imagepath);
		}
		$filename = $this->getImageFilename();
		if (!copy($imagepath_original . $filename, $imagepath . $filename)) {
			print "image could not be duplicated!!!! ";
		}
	}

	function copyImage($question_id, $source_questionpool)
	{
		$imagepath = $this->getImagePath();
		$imagepath_original = str_replace("/$this->id/images", "/$question_id/images", $imagepath);
		$imagepath_original = str_replace("/$this->obj_id/", "/$source_questionpool/", $imagepath_original);
		if (!file_exists($imagepath)) 
		{
			ilUtil::makeDirParents($imagepath);
		}
		$filename = $this->getImageFilename();
		if (!copy($imagepath_original . $filename, $imagepath . $filename)) 
		{
			print "image could not be copied!!!! ";
		}
	}

/**
* Loads a assImagemapQuestion object from a database
*
* Loads a assImagemapQuestion object from a database (experimental)
*
* @param object $db A pear DB object
* @param integer $question_id A unique key which defines the multiple choice test in the database
* @access public
*/
	function loadFromDb($question_id)
	{
		global $ilDB;

		$result = $ilDB->queryF("SELECT qpl_questions.*, " . $this->getAdditionalTableName() . ".* FROM qpl_questions LEFT JOIN " . $this->getAdditionalTableName() . " ON " . $this->getAdditionalTableName() . ".question_fi = qpl_questions.question_id WHERE qpl_questions.question_id = %s",
			array("integer"),
			array($question_id)
		);
		if ($result->numRows() == 1)
		{
			$data = $ilDB->fetchAssoc($result);
			$this->setId($question_id);
			$this->setObjId($data["obj_fi"]);
			$this->setTitle($data["title"]);
			$this->setComment($data["description"]);
			$this->setOriginalId($data["original_id"]);
			$this->setNrOfTries($data['nr_of_tries']);
			$this->setAuthor($data["author"]);
			$this->setPoints($data["points"]);
			$this->setOwner($data["owner"]);
			$this->setIsMultipleChoice($data["is_multiple_choice"] == self::MODE_MULTIPLE_CHOICE);
			include_once("./Services/RTE/classes/class.ilRTE.php");
			$this->setQuestion(ilRTE::_replaceMediaObjectImageSrc($data["question_text"], 1));
			$this->setImageFilename($data["image_file"]);
			$this->setEstimatedWorkingTime(substr($data["working_time"], 0, 2), substr($data["working_time"], 3, 2), substr($data["working_time"], 6, 2));
			
			try
			{
				$this->setAdditionalContentEditingMode($data['add_cont_edit_mode']);
			}
			catch(ilTestQuestionPoolException $e)
			{
			}

			$result = $ilDB->queryF("SELECT * FROM qpl_a_imagemap WHERE question_fi = %s ORDER BY aorder ASC",
				array("integer"),
				array($question_id)
			);
			include_once "./Modules/TestQuestionPool/classes/class.assAnswerImagemap.php";
			if ($result->numRows() > 0)
			{
				while ($data = $ilDB->fetchAssoc($result)) 
				{
					array_push($this->answers, new ASS_AnswerImagemap($data["answertext"], $data["points"], $data["aorder"], $data["coords"], $data["area"], $data['question_fi'], $data['points_unchecked']));
				}
			}
		}
		parent::loadFromDb($question_id);
	}

	/**
	* Uploads an image map and takes over the areas
	*
	* @param string $imagemap_filename Imagemap filename
	* @return integer number of areas added
	*/
	function uploadImagemap($imagemap_filename = "") 
	{
		$added = 0;
		if (!empty($imagemap_filename)) 
		{
			$fp = fopen($imagemap_filename, "r");
			$contents = fread($fp, filesize($imagemap_filename));
			fclose($fp);
			if (preg_match_all("/<area(.+)>/siU", $contents, $matches)) 
			{
				for ($i=0; $i< count($matches[1]); $i++) 
				{
					preg_match("/alt\s*=\s*\"(.+)\"\s*/siU", $matches[1][$i], $alt);
					preg_match("/coords\s*=\s*\"(.+)\"\s*/siU", $matches[1][$i], $coords);
					preg_match("/shape\s*=\s*\"(.+)\"\s*/siU", $matches[1][$i], $shape);
					$this->addAnswer($alt[1], 0.0, count($this->answers), $coords[1], $shape[1]);
					$added++;
				}
			}
		}
		return $added;
	}

	function getImageFilename()
	{
		return $this->image_filename;
	}

/**
* Sets the image file name
*
* @param string $image_file name.
* @access public
* @see $image_filename
*/
	function setImageFilename($image_filename, $image_tempfilename = "") 
	{
		if (!empty($image_filename)) 
		{
			$image_filename = str_replace(" ", "_", $image_filename);
			$this->image_filename = $image_filename;
		}
		if (!empty($image_tempfilename)) 
		{
			$imagepath = $this->getImagePath();
			if (!file_exists($imagepath)) 
			{
				ilUtil::makeDirParents($imagepath);
			}
			if (!ilUtil::moveUploadedFile($image_tempfilename, $image_filename, $imagepath.$image_filename))
			{
				$this->ilias->raiseError("The image could not be uploaded!", $this->ilias->error_obj->MESSAGE);
			}
			global $ilLog; $ilLog->write("gespeichert: " . $imagepath.$image_filename);
		}
  }

/**
* Gets the imagemap file contents
*
* Gets the imagemap file contents
*
* @return string The imagemap file contents of the assImagemapQuestion object
* @access public
* @see $imagemap_contents
*/
	function get_imagemap_contents($href = "#") {
		$imagemap_contents = "<map name=\"".$this->title."\"> ";
		for ($i = 0; $i < count($this->answers); $i++) {
			$imagemap_contents .= "<area alt=\"".$this->answers[$i]->getAnswertext()."\" ";
			$imagemap_contents .= "shape=\"".$this->answers[$i]->getArea()."\" ";
			$imagemap_contents .= "coords=\"".$this->answers[$i]->getCoords()."\" ";
			$imagemap_contents .= "href=\"$href&selimage=" . $this->answers[$i]->getOrder() . "\" /> ";
		}
		$imagemap_contents .= "</map>";
		return $imagemap_contents;
	}

/**
* Adds a possible answer for a imagemap question
*
* Adds a possible answer for a imagemap question. A ASS_AnswerImagemap object will be
* created and assigned to the array $this->answers.
*
* @param string $answertext The answer text
* @param double $points The points for selecting the answer (even negative points can be used)
* @param integer $status The state of the answer (set = 1 or unset = 0)
* @param integer $order A possible display order of the answer
* @access public
* @see $answers
* @see ASS_AnswerImagemap
*/
	function addAnswer(
		$answertext = "",
		$points = 0.0,
		$order = 0,
		$coords="",
		$area="",
		$points_unchecked = 0.0
	)
	{
		include_once "./Modules/TestQuestionPool/classes/class.assAnswerImagemap.php";
		if (array_key_exists($order, $this->answers)) 
		{
			// Insert answer
			$answer = new ASS_AnswerImagemap($answertext, $points, $order, $coords, $area, -1, $points_unchecked);
			for ($i = count($this->answers) - 1; $i >= $order; $i--) 
			{
				$this->answers[$i+1] = $this->answers[$i];
				$this->answers[$i+1]->setOrder($i+1);
			}
			$this->answers[$order] = $answer;
		}
		else 
		{
			// Append answer
			$answer = new ASS_AnswerImagemap($answertext, $points, count($this->answers), $coords, $area, -1, $points_unchecked);
			array_push($this->answers, $answer);
		}
	}

/**
* Returns the number of answers
*
* Returns the number of answers
*
* @return integer The number of answers of the multiple choice question
* @access public
* @see $answers
*/
	function getAnswerCount() {
		return count($this->answers);
	}

/**
* Returns an answer
*
* Returns an answer with a given index. The index of the first
* answer is 0, the index of the second answer is 1 and so on.
*
* @param integer $index A nonnegative index of the n-th answer
* @return object ASS_AnswerImagemap-Object containing the answer
* @access public
* @see $answers
*/
	function getAnswer($index = 0) {
		if ($index < 0) return NULL;
		if (count($this->answers) < 1) return NULL;
		if ($index >= count($this->answers)) return NULL;
		return $this->answers[$index];
	}

	/**
	* Returns the answer array
	*
	* Returns the answer array
	*
	* @return array The answer array
	* @access public
	* @see $answers
	*/
	function &getAnswers() 
	{
		return $this->answers;
	}

/**
* Deletes an answer
*
* Deletes an area with a given index. The index of the first
* area is 0, the index of the second area is 1 and so on.
*
* @param integer $index A nonnegative index of the n-th answer
* @access public
* @see $answers
*/
	function deleteArea($index = 0) 
	{
		if ($index < 0) return;
		if (count($this->answers) < 1) return;
		if ($index >= count($this->answers)) return;
		unset($this->answers[$index]);
		$this->answers = array_values($this->answers);
		for ($i = 0; $i < count($this->answers); $i++) {
			if ($this->answers[$i]->getOrder() > $index) {
				$this->answers[$i]->setOrder($i);
			}
		}
	}

/**
* Deletes all answers
*
* Deletes all answers
*
* @access public
* @see $answers
*/
	function flushAnswers() {
		$this->answers = array();
	}

/**
* Returns the maximum points, a learner can reach answering the question
*
* Returns the maximum points, a learner can reach answering the question
*
* @access public
* @see $points
*/
  function getMaximumPoints() {
		$points = 0;
		foreach ($this->answers as $key => $value) {
			if($this->is_multiple_choice)
			{
				if($value->getPoints() > $value->getPointsUnchecked())
				{
					$points += $value->getPoints();
				}
				else
				{
					$points += $value->getPointsUnchecked();
				}
			}
			else
			{
				if($value->getPoints() > $points)
				{
					$points = $value->getPoints();
				}
			}
		}
		return $points;
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
		if( $returndetails )
		{
			throw new ilTestException('return details not implemented for '.__METHOD__);
		}
		
		global $ilDB;
		
		$found_values = array();
		if (is_null($pass))
		{
			$pass = $this->getSolutionMaxPass($active_id);
		}
		$result = $ilDB->queryF("SELECT * FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s",
			array("integer","integer","integer"),
			array($active_id, $this->getId(), $pass)
		);
		while ($data = $ilDB->fetchAssoc($result))
		{
			if (strcmp($data["value1"], "") != 0)
			{
				array_push($found_values, $data["value1"]);
			}
		}
		$points = 0;
		if (count($found_values) > 0)
		{
			foreach ($this->answers as $key => $answer)
			{
				if (in_array($key, $found_values))
				{
					$points += $answer->getPoints();
				}
				elseif( $this->getIsMultipleChoice() )
				{
					$points += $answer->getPointsUnchecked();
				}
			}
		}

		return $points;
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
		global $ilDB;
		global $ilUser;

		if (is_null($pass))
		{
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			$pass = ilObjTest::_getPass($active_id);
		}
		
		if($this->is_multiple_choice && strlen($_GET['remImage']))
		{
			$affectedRows = $ilDB->manipulateF("DELETE FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s AND value1 = %s",
				array("integer", "integer", "integer", "integer"),
				array($active_id, $this->getId(), $pass, $_GET['remImage'])
			);
		}
		elseif(!$this->is_multiple_choice)
		{
			$affectedRows = $ilDB->manipulateF("DELETE FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s",
				array("integer", "integer", "integer"),
				array($active_id, $this->getId(), $pass)
			);
		}

		if (strlen($_GET["selImage"]))
		{
			$next_id = $ilDB->nextId('tst_solutions');
			$affectedRows = $ilDB->insert("tst_solutions", array(
				"solution_id" => array("integer", $next_id),
				"active_fi" => array("integer", $active_id),
				"question_fi" => array("integer", $this->getId()),
				"value1" => array("clob", $_GET['selImage']),
				"value2" => array("clob", null),
				"pass" => array("integer", $pass),
				"tstamp" => array("integer", time())
			));

			include_once ("./Modules/Test/classes/class.ilObjAssessmentFolder.php");
			if (ilObjAssessmentFolder::_enabledAssessmentLogging())
			{
				$this->logAction($this->lng->txtlng("assessment", "log_user_entered_values", ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
			}
		}
		else
		{
			include_once ("./Modules/Test/classes/class.ilObjAssessmentFolder.php");
			if (ilObjAssessmentFolder::_enabledAssessmentLogging())
			{
				$this->logAction($this->lng->txtlng("assessment", "log_user_not_entered_values", ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
			}
		}

		return true;
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
		// nothing to rework!
	}

	function syncWithOriginal()
	{
		if ($this->getOriginalId())
		{
			parent::syncWithOriginal();
		}
	}

	/**
	* Returns the question type of the question
	*
	* Returns the question type of the question
	*
	* @return integer The question type of the question
	* @access public
	*/
	function getQuestionType()
	{
		return "assImagemapQuestion";
	}

	/**
	* Returns the name of the additional question data table in the database
	*
	* Returns the name of the additional question data table in the database
	*
	* @return string The additional table name
	* @access public
	*/
	function getAdditionalTableName()
	{
		return "qpl_qst_imagemap";
	}

	/**
	* Returns the name of the answer table in the database
	*
	* Returns the name of the answer table in the database
	*
	* @return string The answer table name
	* @access public
	*/
	function getAnswerTableName()
	{
		return "qpl_a_imagemap";
	}

	/**
	* Collects all text in the question which could contain media objects
	* which were created with the Rich Text Editor
	*/
	function getRTETextWithMediaObjects()
	{
		$text = parent::getRTETextWithMediaObjects();
		foreach ($this->answers as $index => $answer)
		{
			$text .= $this->feedbackOBJ->getSpecificAnswerFeedbackContent($this->getId(), $index);
		}
		return $text;
	}

	/**
	* Creates an Excel worksheet for the detailed cumulated results of this question
	*
	* @param object $worksheet Reference to the parent excel worksheet
	* @param object $startrow Startrow of the output in the excel worksheet
	* @param object $active_id Active id of the participant
	* @param object $pass Test pass
	* @param object $format_title Excel title format
	* @param object $format_bold Excel bold format
	* @param array $eval_data Cumulated evaluation data
	* @access public
	*/
	public function setExportDetailsXLS(&$worksheet, $startrow, $active_id, $pass, &$format_title, &$format_bold)
	{
		include_once ("./Services/Excel/classes/class.ilExcelUtils.php");
		$solution = $this->getSolutionValues($active_id, $pass);
		$worksheet->writeString($startrow, 0, ilExcelUtils::_convert_text($this->lng->txt($this->getQuestionType())), $format_title);
		$worksheet->writeString($startrow, 1, ilExcelUtils::_convert_text($this->getTitle()), $format_title);
		$i = 1;
		foreach ($this->getAnswers() as $id => $answer)
		{
			$worksheet->writeString($startrow + $i, 0, ilExcelUtils::_convert_text($answer->getArea() . ": " . $answer->getCoords()), $format_bold);
			if ($id == $solution[0]["value1"])
			{
				$worksheet->write($startrow + $i, 1, 1);
			}
			else
			{
				$worksheet->write($startrow + $i, 1, 0);
			}
			$i++;
		}
		return $startrow + $i + 1;
	}

	/**
	* Deletes the image file
	*/
	public function deleteImage()
	{
		$file = $this->getImagePath() . $this->getImageFilename();
		@unlink($file);
		$this->flushAnswers();
		$this->image_filename = "";
	}

	/**
	* Returns a JSON representation of the question
	*/
	public function toJSON()
	{
		include_once("./Services/RTE/classes/class.ilRTE.php");
		$result = array();
		$result['id'] = (int) $this->getId();
		$result['type'] = (string) $this->getQuestionType();
		$result['title'] = (string) $this->getTitle();
		$result['question'] =  $this->formatSAQuestion($this->getQuestion());
		$result['nr_of_tries'] = (int) $this->getNrOfTries();
		$result['shuffle'] = (bool) $this->getShuffle();
		$result['feedback'] = array(
			"onenotcorrect" => ilRTE::_replaceMediaObjectImageSrc(
					$this->feedbackOBJ->getGenericFeedbackExportPresentation($this->getId(), false), 0
			),
			"allcorrect" => ilRTE::_replaceMediaObjectImageSrc(
					$this->feedbackOBJ->getGenericFeedbackExportPresentation($this->getId(), true), 0
			)
		);
		$result['image'] = (string) $this->getImagePathWeb() . $this->getImageFilename();
		
		$answers = array();
		foreach ($this->getAnswers() as $key => $answer_obj)
		{
			array_push($answers, array(
				"answertext"       => (string)$answer_obj->getAnswertext(),
				"points"           => (float)$answer_obj->getPoints(),
				"points_unchecked" => (float)$answer_obj->getPointsUnchecked(),
				"order"            => (int)$answer_obj->getOrder(),
				"coords"           => $answer_obj->getCoords(),
				"state"            => $answer_obj->getState(),
				"area"             => $answer_obj->getArea(),
				"feedback"         => ilRTE::_replaceMediaObjectImageSrc(
					$this->feedbackOBJ->getSpecificAnswerFeedbackExportPresentation($this->getId(), $key), 0
				)
			));
		}
		$result['answers'] = $answers;

		$mobs = ilObjMediaObject::_getMobsOfObject("qpl:html", $this->getId());
		$result['mobs'] = $mobs;
		
		return json_encode($result);
	}
}
