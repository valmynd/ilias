<?php

include_once "./Modules/TestQuestionPool/classes/class.ilQuestionsPlugin.php";

class ilplaceholderQuestionPlugin extends ilQuestionsPlugin
{
	final function getPluginName()
	{
		return "placeholderQuestion";
	}

	final function getQuestionType()
	{
		return "placeholderQuestion";
	}
}

?>
