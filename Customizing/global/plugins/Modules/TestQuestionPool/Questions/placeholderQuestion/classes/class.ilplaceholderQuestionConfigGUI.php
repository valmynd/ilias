<?php

include_once 'Services/Component/classes/class.ilPluginConfigGUI.php';

/**
 * Question Plugin Configuration
 */
class ilplaceholderQuestionConfigGUI extends ilPluginConfigGUI
{
	function performCommand($cmd)
	{
		ilUtil::sendInfo("performCommand($cmd) called");
	}
}
