<?php
include_once "./Modules/TestQuestionPool/classes/export/qti12/class.assQuestionExport.php";

/**
 * Class for formula question question exports
 *
 * assSingleChoiceExport is a class for single choice question exports
 */
class placeholderQuestionExport extends assQuestionExport
{
	/**
	 * Returns a QTI xml representation of the question and sets the internal
	 * domxml variable with the DOM XML representation of the QTI xml representation
	 *
	 * @return string The QTI xml representation of the question
	 *
	 * @param bool $a_include_header
	 * @param bool $a_include_binary
	 * @param bool $a_shuffle
	 * @param bool $test_output
	 * @param bool $force_image_references
	 * @return string The QTI xml representation of the question
	 */
	function toXML($a_include_header = true, $a_include_binary = true, $a_shuffle = false, $test_output = false,
				   $force_image_references = false)
	{
		// left empty, it seems to be optional

		/*global $ilias;

		include_once("./classes/class.ilXmlWriter.php");
		$writer = new ilXmlWriter;
		// set xml header
		$writer->xmlHeader();
		$writer->xmlStartTag("questestinterop");
		$attrs = array(
			"ident" => "il_" . IL_INST_ID . "_qst_" . $this->object->getId(),
			"title" => $this->object->getTitle()
		);
		$writer->xmlEndTag("questestinterop");

		$xml = $writer->xmlDumpMem(FALSE);
		if (!$a_include_header) {
			$pos = strpos($xml, "?>");
			$xml = substr($xml, $pos + 2);
		}
		return $xml;*/
	}

}

?>
