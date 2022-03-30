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

        $fields = [];
        $Proj = new \Project($project_id);
        foreach ($Proj->metadata as $field => $meta) {
            $misc = $meta["misc"] ?? "";
            if (strpos($misc, "@") !== false) {
                $fields[$meta["form_name"]."-".$meta["field_order"]] = $meta;
            }
        }
        ksort($fields);
        foreach ($fields as $_ => $meta) {
            print "<hr><p class=\"ml-2\">Field: <b>{$meta["field_name"]}</b></p><pre class=\"mr-2\">";
            $result = ActionTagParser::parse($meta["misc"]);
            print_r($result["orig"]);
            print "<hr>";
            print_r($result["parts"]);
            print "</pre>";
        }
    }
}