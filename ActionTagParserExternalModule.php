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
            $result = ActionTagParser::parse_optimized($meta["misc"]);
            print_r($result["orig"]);
            print "<hr>";
            print_r($result["parts"]);
            print "</pre>";
        }
    }

    function benchmark() {

        $n = max(intval($_GET["n"]), 1);

        print "<h5>Timing ($n iterations)</h5>";
        print "<p>Set the number of iterations as GET parameter '<i>n</i>'.</p>";

        $start = microtime(true);

        for ($i = 0; $i < $n; $i++) {
            $parser_tags = ActionTagParser::getActionTags(null, null, null, null, false, $i);
        }

        $end = microtime(true);
        print "<p>Parser: ".round(($end-$start) * 1000, 2)." µs</p>";
        
        $start = microtime(true);

        for ($i = 0; $i < $n; $i++) {
            $parser_tags = ActionTagParser::getActionTags(null, null, null, null, true, $i);
        }

        $end = microtime(true);
        print "<p>Parser (Optimized): ".round(($end-$start) * 1000, 2)." µs</p>";
        
        $start = microtime(true);
        
        for ($i = 0; $i < $n; $i++) {
            $helper_tags = ActionTagHelper::getActionTags(null, null, null, null, $i);
        }
        
        $end = microtime(true);
        print "<p>Helper: ".round(($end-$start) * 1000, 2)." µs</p>";
        

        $print = function($tags) {
            foreach ($tags as $tag => $fields) {
                print "<p><b>$tag</b></p>";
                foreach ($fields as $field => $params) {
                    print "<p class=\"ml-2\"><i>$field</i>";
                    if ($params['params'] != "") print " - <code>".htmlentities(str_replace("\n", " ", $params['params']))."</code>";
                    print "</p>";
                }
            }
        };

        print "<hr>";
        print "<h5>Parser:</h5>";
        $print($parser_tags);
        print "<h5>Helper:</h5>";
        $print($helper_tags);
    }
}