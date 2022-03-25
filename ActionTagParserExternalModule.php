<?php namespace DE\RUB\ActionTagParserExternalModule;

use ExternalModules\AbstractExternalModule;

require_once "ActionTagParser.php";

use ActionTagParser\ActionTagParser;

class ActionTagParserExternalModule extends AbstractExternalModule {

    /**
     * EM Framework (tooling support)
     * @var \ExternalModules\Framework
     */
    private $fw;

    function __construct() {
        parent::__construct();
        $this->fw = $this->framework;
    }

    function redcap_every_page_top($project_id = null) {
        if ($project_id == null) return;
    }


    function explain() {
        $project_id = $this->fw->getProjectId();

        // Get all fields that have an action tag

        $Proj = new \Project($project_id);
        foreach ($Proj->metadata as $field => $meta) {
            $misc = $meta["misc"] ?? "";
            if (strpos($misc, "@") !== false) {
                print "<hr><p class=\"ml-2\">Field: <b>$field</b></p><pre class=\"mr-2\">";
                $result = ActionTagParser::parse($misc);
                print_r($result["orig"]);
                print_r($result["parts"]);
                print "</pre>";
            }
        }


    }

}