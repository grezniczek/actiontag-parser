<?php namespace DE\RUB\ActionTagParserExternalModule;

use ExternalModules\AbstractExternalModule;

require_once "classes/ActionTagParser.php";
require_once "classes/ActionTagHelper.php";

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

    function benchmark() {
        $project_id = $this->fw->getProjectId();

        // Get all fields that have an action tag

        $n = 1000;

        $start = microtime(true);

        $q = \REDCap::getDataDictionary('json', false);
        $metadata = json_decode($q,true);

        for ($i = 0; $i < $n; $i++) {
            $parser_tags = [];
            foreach ($metadata as $meta) {
                $result = ActionTagParser::parse($meta["field_annotation"], true);
                // foreach ($result["parts"] as $part) {
                //     if ($part["type"] == "TAG") {
                //         $parser_tags[] = $part;
                //     }
                // }
            }
        }
        $end = microtime(true);
        print "<p>Parser: Time: ".($end-$start)."</p>";
        
        $start = microtime(true);

        for ($i = 0; $i < $n; $i++) {
            $helper_tags = ActionTagHelper::getActionTags();
        }

        $end = microtime(true);
        print "<p>Helper: Time: ".($end-$start)."</p>";
    }
}