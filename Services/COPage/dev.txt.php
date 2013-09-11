<?php exit; ?>

=================================
handleCopiedContent
=================================

handleCopiedContent
- called by copyXmlContent
- called by pasteContents
-- called by ilPageEditorGUI->paste

=================================
Multi lang
=================================

- new table
- copg_page_properties
  - multi_lang_def_lang, default "-" -> no support
  - if activated, default lang is set, e.g. to "de"
  -> page_object record with "-" in lang mean "de", but value is not
     set in page_object (no dependent tables need to be updated)
     OR
  -> page object record with "-" is set to "de" and parts of delelete/update
     are invoked to update dependencies
- page_object
  - new pk lang with "-" as default
- page_history
  - new pk lang with "-" as default
- ilPageObject
  - first reads copg_page_properties
  - always reads the default "-" record of page_object/page_history
  - ? if another language is set, a second ilPageObject is read into the master
  - change language (e.g. "-" -> "de") by parts of delete procedure and
    calling update() (-> usages and things are updated)
  - ilPageObject changes due to new pk "lang" in page_object (bwc backwards compatible for components not using multilinguality)
    - __construct  (bwc)
    - read  (bwc)
    - _exists (bwc)
    - _lookupActive (bwc)
    - _isScheduledActivation (bwc)
    - _writeActive (bwc)
    - _lookupActivationData (bwc)
    - createFromXML (bwc)
    - updateFromXML (bwc)
    - update (bwc)
    - _lookupContainsDeactivatedElements (bwc)
    - increaseViewCnt (bwc)
    - getRecentChanges (bwc)
    - getAllPages (bwc)
    - getNewPages (bwc)
    - getParentObjectContributors (! major change !, should be bwc)
    - getPageContributors (! major change !, should be bwc)
    - writeRenderedContent (bwc)
    - getPagesWithLinks (bwc)
  - ilPageUtil changes
    - _existsAndNotEmpty (bwc)
  - change due to new pk "lang" in page_history
    - read (bwc)
    - update (bwc)
    - getHistoryEntries (bwc)
    - getHistoryEntry (bwc)
    - getHistoryInfo (bwc)
  - open issues in page_object/ilPageObject
    - lookupParentId/_writeParentId: parent_id into copg_page_properties?
      - page_object.parent_id is accessed directly in Modules/Glossary/classes/class.ilGlossaryTerm.php
      - page_object.parent_id is accessed directly by Services/LinkChecker/classes/class.ilLinkChecker.php
    - what happens in current callUpdateListeners()?
    - import/export
    - search (page_object is accessed in Lucene.xml files; multilinguality?)
      - page_object accessed in Services/Search/classes/class.ilLMContentSearch.php
      - page_object accessed in Services/Search/classes/class.ilWikiContentSearch.php
    

Dependencies
  - int_link: new field source_lang, Services/COPage/classes/class.ilInternalLink.php
  - mob_usage: new field usage_lang, Services/MediaObject/classes/class.ilObjMediaObject.php
  - page_anchor: new field page_lang, Services/COPage/classes/class.ilPageObject.php
  - page_pc_usage: new field usage_lang, Services/COPage/classes/class.ilPageContentUsage.php
  - page_style_usage: new field page_lang, Services/COPage/classes/class.ilPageObject.php
  - file_usage: new field usage_lang, Modules/File/classes/class.ilObjFile.php 
  - meta keywords? (currently just added)
  

=================================
extends ilPageObject (18)
=================================

Modules/Blog (config intro)

Modules/DataCollection (config intro)

Modules/MediaPool (config intro, except ilmediapoolpageusagetable)

Modules/Scorm2004 (config intro)

Modules/Wiki (config intro)

Services/Imprint (config intro)

Services/Portfolio (config intro)

More:
Services/Style (config intro)

Modules/Glossary (config intro, except iltermusagetable)

Modules/Test (TestQuestionContent, Feedback)
Modules/TestQuestionPool

Modules/LearningModule (Unirep Branch)

Services/Container (config intro)
Modules/Category
Modules/Course
Modules/Folder
Modules/Group
Modules/ItemGroup
Modules/RootFolder

Services/Authentication (config intro)
Services/Init

Services/Help

Services/MediaObjects

Services/Payment (config intro)




=================================
extends ilPageContent ocurrences (24)
=================================

/htdocs/ilias2/Services/COPage/classes/class.ilPCBlog.php
36: class ilPCBlog extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCContentInclude.php
17: class ilPCContentInclude extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCFileItem.php
class ilPCFileItem extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCFileList.php
36: class ilPCFileList extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCilPCInteractiveImage.php
15: class ilPCInteractiveImage extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCList.php
16: class ilPCList extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCListItem.php
36: class ilPCListItem extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCLoginPageElements.php
16: class ilPCLoginPageElements extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCMap.php
class ilPCMap extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCMediaObject.php
16: class ilPCMediaObject extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCParagraph.php
17: class ilPCParagraph extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCPlaceHolder.php
37: class ilPCPlaceHolder extends ilPageContent {
/htdocs/ilias2/Services/COPage/classes/class.ilPCPlugged.php
35: class ilPCPlugged extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCProfile.php
36: class ilPCProfile extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCQuestion.php
36: class ilPCQuestion extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCQuestionOverview.php
15: class ilPCQuestionOverview extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCResources.php
17: class ilPCResources extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCSection.php
17: class ilPCSection extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCSkills.php
36: class ilPCSkills extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCTab.php
36: class ilPCTab extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCTable.php
17: class ilPCTable extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCTableData.php
36: class ilPCTableData extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCTabs.php
36: class ilPCTabs extends ilPageContent
/htdocs/ilias2/Services/COPage/classes/class.ilPCVerification.php
36: class ilPCVerification extends ilPageContent




