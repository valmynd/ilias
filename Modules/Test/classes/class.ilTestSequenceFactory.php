<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Factory for test sequence
 *
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id: class.ilTestSequenceFactory.php 44256 2013-08-19 08:43:34Z bheyser $
 * 
 * @package		Modules/Test
 */
class ilTestSequenceFactory
{
	/**
	 * singleton instance of test sequence
	 *
	 * @var ilTestSequence|ilTestSequenceDynamicQuestionSet 
	 */
	private static $testSequence = null;
	
	/**
	 * global ilDB object instance
	 *
	 * @var ilDB
	 */
	private $db = null;
	
	/**
	 * global ilLanguage object instance
	 *
	 * @var ilLanguage
	 */
	private $lng = null;
	
	/**
	 * global ilPluginAdmin object instance
	 *
	 * @var ilPluginAdmin
	 */
	private $pluginAdmin = null;
	
	/**
	 * object instance of current test
	 *
	 * @var ilObjTest
	 */
	private $testOBJ = null;
	
	/**
	 * constructor
	 * 
	 * @param ilObjTest $testOBJ
	 */
	public function __construct(ilDB $db, ilLanguage $lng, ilPluginAdmin $pluginAdmin, ilObjTest $testOBJ)
	{
		$this->db = $db;
		$this->lng = $lng;
		$this->pluginAdmin = $pluginAdmin;
		$this->testOBJ = $testOBJ;
	}
	
	/**
	 * creates and returns an instance of a test sequence
	 * that corresponds to the current test mode and the pass stored in test session
	 * 
	 * @param ilTestSession|ilTestSessionDynamicQuestionSet $testSession
	 * @return ilTestSequence|ilTestSequenceDynamicQuestionSet
	 */
	public function getSequence($testSession)
	{
		return $this->getSequenceByPass($testSession, $testSession->getPass());
	}
	
	/**
	 * creates and returns an instance of a test sequence
	 * that corresponds to the current test mode and given pass
	 * 
	 * @param ilTestSession|ilTestSessionDynamicQuestionSet $testSession
	 * @param integer $pass
	 * @return ilTestSequence|ilTestSequenceDynamicQuestionSet
	 */
	public function getSequenceByPass($testSession, $pass)
	{
		if(self::$testSequence === null)
		{
			switch( $this->testOBJ->getQuestionSetType() )
			{
				case ilObjTest::QUESTION_SET_TYPE_FIXED:
				case ilObjTest::QUESTION_SET_TYPE_RANDOM:

					require_once 'Modules/Test/classes/class.ilTestSequence.php';
					self::$testSequence = new ilTestSequence(
							$testSession->getActiveId(), $pass, $this->testOBJ->isRandomTest()
					);
					break;

				case ilObjTest::QUESTION_SET_TYPE_DYNAMIC:

					require_once 'Modules/Test/classes/class.ilTestSequenceDynamicQuestionSet.php';
					require_once 'Modules/Test/classes/class.ilTestDynamicQuestionSet.php';
					$questionSet = new ilTestDynamicQuestionSet(
							$this->db, $this->lng, $this->pluginAdmin, $this->testOBJ
					);
					self::$testSequence = new ilTestSequenceDynamicQuestionSet(
							$this->db, $questionSet, $testSession->getActiveId()
					);
					break;
			}
		}

		return self::$testSequence;
	}
}
