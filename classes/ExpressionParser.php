<?php namespace ActionTagParser;

use DateTime;
use Exception;
use Piping;
use Project;
use RCView;
use Records;
use REDCap;
use RepeatInstance;
use Survey;
use User;
use UserRights;

/** 
 * A class that performs piping operations (for piping, logic, calculations) 
 */
class ExpressionParser {

    #region Constants

    // Defaults
    const PREFIX = "=";
    const POSTFIX = "=";

    // Stages
    const STAGE_PARSE = "STAGE_PARSE";
    const STAGE_PARTS = "STAGE_PARTS";
    const STAGE_DATA = "STAGE_DATA";

    // Tasks
    /** Task: Determine, whether the field or smart variable is allowed in the given context. If not, resolve it by setting the value to null or empty and issue a warning. The utility method disallow($state, $part, [$warnings]) can be used for this. */
    const TASK_ALLOWED = "TASK_ALLOWED";
    /** Task: Parse and validate commands. */
    const TASK_COMMANDS = "TASK_COMMANDS";
    /** Task: Assign value (in STAGE_DATA, the value MUST be resolved). */
    const TASK_RESOLVE = "TASK_RESOLVE";

    // Options
    /** Template placeholder prefix override */
    const opt_prefix = "prefix";
    /** Template placeholder postfix override */
    const opt_postfix = "postfix";
    /** Debug mode (set to TRUE to enable). */
    const opt_debug = "debug";

    #endregion

    #region Key Constants

    // State
    const ps_debug = "debug";
    const ps_source = "source";
    const ps_prefix = "prefix";
    const ps_postfix = "postfix";
    const ps_template = "template";
    const ps_parts = "parts";
    const ps_context = "context";
    const ps_warnings = "warnings";
    const ps_enums = "ps_enums";
    const ps_fields = "ps_fields";
    const ps_events = "ps_events";
    const ps_stage = "stage";
    const ps_done = "done";

    // Context
    const pctx_Proj = "Proj";
    const pctx_pid = "pid";
    const pctx_instance = "instance";
    const pctx_event_id = "event_id";
    const pctx_form = "pctx_form";
    const pctx_user_id = "pctx_user_id";
    const pctx_repeating = "pctx_repeating";
    const pctx_record = "record";
    const pctx_participant_id = "participant_id";

    // Parts
    const p_name = "name";
    const p_type = "type";
    const p_identifier = "identifier";
    const p_construct = "construct";
    const p_kind = "kind";
    const p_event = "event";
    const p_event_id = "event_id";
    const p_instance = "instance";
    const p_instance_num = "instance_num";
    const p_instance_exists = "instance_exists";
    const p_code = "code";
    const p_commands_raw = "commands_raw";
    const p_commands = "commands";
    const p_form = "form";
    const p_repeating = "repeating";
    const p_warnings = "warnings";
    const p_value = "value";
    const p_processor = "processor";
    const p_sql = "sql";
    const p_sql_resolver = "sql_resolver";
    const p_sql_enum = "sql_enum";
    const p_data = "data";

    // Part Kind
    const kind_smart = "smart";
    const kind_field = "field";

    // Repeating Type
    /** The event is repeating */
    const repeating_event = "event";
    /** The form is repeating */
    const repeating_form = "form";

    /** Indicates that the instance number needs to be resolved at a later stage */
    const defer_instance_eval = "defer";

    #endregion

    #region Regular Expressions

    /**
     * A regular expression matching (potential) fields (with checkbox codes and commands/qualifiers).
     * https://regex101.com/r/BxfFna/2
     * @var string
     */
    private static $reField = '/\[(?\'field\'[a-z](?:[a-z0-9_]*[a-z0-9])?)(?:\((?\'code\'[0-9A-Za-z._\-+]+)\))?(?\'commands\'(?::[a-z]+){0,}|(?::.*))\]/m';

    /**
     * A regular expression matching event names (they are always AT THE END of a string!)
     * Event names will always contain "_arm_".
     * https://regex101.com/r/f5FDOr/1/
     * @var string
     */
    private static $reEvent = '/\[(?\'event\'[a-z][a-z0-9_]*_arm_\d+[a-z]*)\]$/m';

    /**
     * A regular experession matching smart variables (a-z and hypen; with commands/qualifiers).
     * A smart variable will always contain at least one hyphen.
     * https://regex101.com/r/sIlGHx/2/
     * @var string
     */
    private static $reSmart = '/\[(?\'smart\'[a-z]+(?:-[a-z]+)+)(?\'commands\'(?::[a-z]+){0,}|(?::.*))\]/mU';

    /**
     * A regular experession matching instance numbers (digits) at the START OF STRINGS only!
     * @var string
     */
    private static $reInstance = '/^\[(?\'instance\'\d+)\]/m';

    #endregion

    #region Behavioral Tables - TODO - these should be refactored into TASKs!

    private static $instanceSmartVariables = array (
        "previous-instance",
        "current-instance",
        "next-instance",
        "first-instance",
        "last-instance",
    );

    private static $outsideContextResolvableInstanceSmartVariables = array (
        "first-instance",
        "last-instance",
    );

    private static $eventNameSmartVariables = array (
        "event-name",
        "previous-event-name",
        "next-event-name",
        "first-event-name",
        "last-event-name",
    );

    private static $smartVariables = array (
        "user-name",
        "user-fullname",
        "user-email",
        "user-dag-name",
        "user-dag-id",
        "user-dag-label",
        "record-name",
        "record-dag-name",
        "record-dag-id",
        "record-dag-label",
        "is-form",
        "form-url",
        "form-link",
        "instrument-name",
        "instrument-label",
        "is-survey",
        "survey-url",
        "survey-link",
        "survey-queue-url",
        "survey-queue-link",
        "survey-time-completed",
        "survey-date-completed",
        "survey-title",
        "event-name",
        "event-label",
        "previous-event-name",
        "previous-event-label",
        "next-event-name",
        "next-event-label",
        "first-event-name",
        "first-event-label",
        "last-event-name",
        "last-event-label",
        "arm-number",
        "arm-label",
        "previous-instance",
        "current-instance",
        "next-instance",
        "first-instance",
        "last-instance",
        "project-id",
        "redcap-base-url",
        "redcap-version",
        "redcap-version-url",
        "survey-base-url",
    );

    private static $smartVariablesWithEvent = array (
        "arm-number",
        "arm-label",
        "event-label",
        "first-instance",
        "last-instance",
        "form-url",
        "form-link",
        "survey-url",
        "survey-link",
        "survey-time-completed",
        "survey-date-completed",
    );

    private static $smartVariablesWithInstance = array (
        "form-url",
        "form-link",
        "survey-url",
        "survey-link",
        "survey-time-completed",
        "survey-date-completed",
    );

    private static $smartVariablesRequiringSurveysEnabled = array (
        "survey-url",
        "survey-link",
        "survey-queue-url",
        "survey-queue-link",
        "survey-time-completed",
        "survey-date-completed",
        "survey-title",
    );

    #endregion

    #region Special Helper Constructs

    /**
     * These are applied via search/replace before any processing. 
     * They are useful in certain cases to work around ambitguities or
     * simply to have square brackets that are not interfering with piping
     */
    private static $specialConstructs = array (
        // Construct Sepearator: [-]
        array (
            "name" => "construct-separator",
            "construct" => "[-]",
            "search" => "][-][",
            "replace_prefix" => "]",
            "replace_postfix" => "[",
            "value" => "",
        ),
        // Left Bracket: [[]
        array (
            "name" => "left-bracket",
            "construct" => "[[]",
            "search" => "[[]",
            "value" => "[",
        ),
        // Right Bracket: []]
        array (
            "name" => "right-bracket",
            "construct" => "[]]",
            "search" => "[]]",
            "value" => "]",
        ),
    );

    #endregion

    /** Parses an input string with piping constructs.
     * 
     * Returns the following data structure (state) - treat as READ ONLY!
     * [
     *   "source" => "Original input",
     *   "prefix" => "The placeholder prefix",
     *   "postfix" => "The placehodler postfix",
     *   "template" => "The template",
     *   "parts" => [
     *      [
     *         "name" => "Field name or standalone Smart Variable",
     *         "kind" => "field"|"smart",
     *         "form" => "Name of the form the field is on" | null,
     *         "code" => "Code of a checkbox" | null,
     *         "commands_raw" => "List", "of", "unprocessed", "parameters", ],
     *         "commands" => [ "List", "of", "validated", "commands", ],
     *         "event" => "The unique event name, a event-name smart variable" | null,
     *         "instance" => "Instance number or an instance smart variable" | "" | null,
     *         "construct" => "The original piping construct for this part",
     *         "warnings" => [ "List", "of", "warnings" ],
     *         "processor" => Processor function,
     *         "done" => "STAGE" | false
     *      ]
     *   ],
     *   "context" => [
     *      "pid" => Project ID
     *   ],
     *   "warnings" => [ "List", "of", "warnings" ],
     *   "stage" => "The current processing stage",
     *   "done" => "STAGE" | false indicating whether all parts have a value and at what stage this was achieved
     * ]
     * @param int|string|null $project_id
     * @param string $input 
     * @param array{
     *   prefix: string,  // Override for the placeholder prefix
     *   postfix: string, // Override for the placeholder postfix
     *   debug: boolean   // Set to TRUE to enable debug mode
     * } $options
     * @return array An associative array with processing information (state) - consider this READ ONLY
     */
    public static function parse($project_id = null, $input = "", $options = array()) {
        // 
        #region Preparations
        //
        $project_id = self::requireProjectId($project_id);
        $Proj = new Project($project_id); // ALWAYS use our own copy! Nobody should be able to mess with it!
        $input = "$input"; // Make input a string
        $prefix = self::generatePrefix($input, $options[self::opt_prefix] ?: self::PREFIX);
        $postfix = $options[self::opt_postfix] ?: self::POSTFIX;
        $state = array(
            self::ps_debug => $options[self::opt_debug] == true,
            self::ps_source => $input,
            self::ps_prefix => $prefix,
            self::ps_postfix => $postfix,
            self::ps_template => $input,
            self::ps_parts => array(),
            self::ps_context => array(
                self::pctx_Proj => $Proj,
                self::pctx_pid => $project_id,
            ),
            self::ps_warnings => array(),
            self::ps_stage => self::STAGE_PARSE,
            self::ps_done => false,
        );

        // Check if parsing is required
        if (strpos($input, "[") === false || strpos($input, "]") === false) {
            // Nothing to to
            $state[self::ps_done] = self::STAGE_PARSE;
            return $state;
        }

        // Initialize some start values
        $parts_idx = 0;
        $template = "";
        $reField_start = str_replace("/m", "/", str_replace("\[", "^\[", self::$reField)); // No multiline
        $reSmart_start = str_replace("/m", "/", str_replace("\[", "^\[", self::$reSmart)); // No multiline
        $reSmart_end = str_replace("\]", "\]$", self::$reSmart);
        /** @var array [ "field" => "form" ] */
        $fieldForms = self::getFieldForms($Proj);
        $before = "";
        $after = $state[self::ps_source];
        //
        #endregion
        //
        #region Special Constructs
        //
        foreach (self::$specialConstructs as $construct) {
            $this_replace  = isset($construct["replace_prefix"]) ? $construct["replace_prefix"] : "";
            $this_replace .= $prefix . $parts_idx . $postfix;
            $this_replace .= isset($construct["replace_postfix"]) ? $construct["replace_postfix"] : "";
            $after = str_replace($construct["search"], $this_replace, $after, $count);
            if ($count > 0) {
                $state[self::ps_parts][$parts_idx] = array (
                    self::p_name => $construct["name"],
                    self::p_construct => $construct["construct"],
                    self::p_kind => self::kind_smart,
                    self::p_event => "event-name",
                    self::p_instance => "",
                    self::p_commands_raw => array(),
                    self::p_commands => array(),
                    self::p_form => null,
                    self::p_warnings => array(),
                    self::p_value => $construct["value"],
                );
                $parts_idx++;
            }
        }
        #endregion
        //
        #region Identify Fields
        //
        preg_match_all(self::$reField, $input, $field_matches, PREG_SET_ORDER, 0);
        for ($match_idx = 0; $match_idx < count($field_matches); $match_idx++) {
            $field_match = $field_matches[$match_idx];
            if (!array_key_exists($field_match["field"], $Proj->metadata)) continue;
            list($before, $after) = explode($field_match[0], $after, 2);
            // This is a known field - but it could be an event, in which case there would not be any codes or commands.
            if (empty($field_match["code"]) && empty($field_match["commands"]) && $Proj->uniqueEventNameExists($field_match["field"])) {
                // As it could be an event name, we will treat it as an event if:
                // there is a field immediately after it,
                // unless there is an event before it
                // Thus, first check for an event before (at end of $before)
                preg_match_all(self::$reEvent, $before, $event_check_matches, PREG_SET_ORDER, 0);
                if (!(count($event_check_matches) && $Proj->uniqueEventNameExists($event_check_matches[0]["event"]))) {
                    // As there is no event before, check if there is a field after
                    preg_match_all($reField_start, $after, $field_check_matches, PREG_SET_ORDER, 0);
                    if (count($field_check_matches) && array_key_exists($field_check_matches[0]["field"], $Proj->metadata)) {
                        // So there is an adjacent, valid field. Thus, the supposed field will be treated as an event (by skipping over it)
                        // To do this, the split has to be adjusted and the match index advanced
                        $before .= $field_match[0];
                        $after = substr($after, strlen($field_check_matches[0][0]));
                        $field_match = $field_check_matches[0];
                        $match_idx++;
                    }
                }
            }
            $part = array (
                self::p_name => $field_match["field"],
                self::p_kind => self::kind_field,
                self::p_form => $fieldForms[$field_match["field"]],
                self::p_code => $field_match["code"],
                self::p_instance => "",
                self::p_event => "event-name",
                self::p_commands_raw => empty($field_match["commands"]) ? array() : explode(":", trim($field_match["commands"], ":")),
                self::p_commands => array(),
                self::p_warnings => array(),
            );
            $this_construct = $field_match[0];
            // Event specified before field?
            preg_match_all(self::$reEvent, $before, $event_matches, PREG_SET_ORDER, 0);
            if (count($event_matches)) {
                $part[self::p_event] = $event_matches[0]["event"];
                $before = substr($before, 0, strlen($before) - strlen($event_matches[0][0]));
                $this_construct = $event_matches[0][0].$this_construct;
            } 
            else {
                // Event-name smart variable specified before field?
                preg_match_all($reSmart_end, $before, $smart_matches, PREG_SET_ORDER, 0);
                if (count($smart_matches) && in_array($smart_matches[0]["smart"], self::$eventNameSmartVariables)) {
                    // Valid event smart variable
                    $part[self::p_event] = $smart_matches[0]["smart"];
                    $before = substr($before, 0, strlen($before) - strlen($smart_matches[0][0]));
                    $this_construct = $smart_matches[0][0].$this_construct;
                }
            }
            // Can add before-part to the template
            $template .= $before.$prefix.$parts_idx.$postfix;
            // Instance specified after field?
            preg_match_all(self::$reInstance, $after, $instance_matches, PREG_SET_ORDER, 0);
            if (count($instance_matches)) {
                $this_instance = $instance_matches[0]["instance"] * 1;
                if ($this_instance > 0) {
                    // Valid instance
                    $part[self::p_instance] = $this_instance;
                    $after = substr($after, strlen($instance_matches[0][0]));
                    $this_construct .= $instance_matches[0][0];
                }
            }
            else {
                // Instance smart variable specified after field?
                preg_match_all($reSmart_start, $after, $smart_matches, PREG_SET_ORDER, 0);
                if (count($smart_matches) && in_array($smart_matches[0]["smart"], self::$instanceSmartVariables)) {
                    // Valid instance smart variable
                    $part["instance"] = $smart_matches[0]["smart"];
                    $after = substr($after, strlen($smart_matches[0][0]));
                    $this_construct .= $smart_matches[0][0];
                }
            }
            $part[self::p_construct] = $this_construct;
            $state[self::ps_parts][$parts_idx] = $part;
            $parts_idx++;
        }
        $template .= $after;
        //
        #endregion
        //
        // Reset template for further processing
        $after = $template;
        $template = "";
        //
        #region Identify Standalone Smart Variables
        //
        preg_match_all(self::$reSmart, $after, $smart_matches, PREG_SET_ORDER, 0);
        for ($match_idx = 0; $match_idx < count($smart_matches); $match_idx++) {
            $smart_match = $smart_matches[$match_idx];
            $smart = $smart_match["smart"];
            if (!in_array($smart, self::$smartVariables)) continue;
            $this_construct = $smart_match[0];
            list($before, $after) = explode($smart_match[0], $after, 2);
            $part_event = "";
            $part_instance = "";
            $part_warnings = array();
            // Check for preceding event-name qualifiers
            preg_match_all(self::$reEvent, $before, $before_matches, PREG_SET_ORDER, 0);
            if (count($before_matches)) {
                // There is an explicit event qualifier - checks will happen in the next step
                $part_event = $before_matches[0]["event"];
                $before = substr($before, 0, strlen($before) - strlen($before_matches[0][0]));
                $this_construct = $before_matches[0][0].$this_construct;
            }
            else {
                preg_match_all($reSmart_end, $before, $before_matches, PREG_SET_ORDER, 0);
                if (count($before_matches) && in_array($before_matches[0]["smart"], self::$eventNameSmartVariables)) {
                    $this_construct = $before_matches[0][0].$this_construct;
                    $part_event = $before_matches[0]["smart"];
                    $before = substr($before, 0, strlen($before) - strlen($before_matches[0][0]));
                }
            }
            // Check for an instance qualifer
            preg_match_all(self::$reInstance, $after, $after_matches, PREG_SET_ORDER, 0);
            // Explicit instance?
            if (count($after_matches)) {
                $part_instance = $after_matches[0]["instance"];
                $after = substr($after, strlen($after_matches[0][0]));
                $this_construct .= $after_matches[0][0];
            }
            else {
                // Instance smart variable?
                preg_match_all($reSmart_start, $after, $after_matches, PREG_SET_ORDER, 0);
                if (count($after_matches) && in_array($after_matches[0]["smart"], self::$instanceSmartVariables)) {
                    $part_instance = $after_matches[0]["smart"];
                    $after = substr($after, strlen($after_matches[0][0]));
                    $this_construct .= $after_matches[0][0];
                    // Need to advance loop counter
                    $match_idx++;
                }
            }
            // If not preceeded by an event qualifier, check if this is an event-name smart variable that might serve as 
            // the event qualifier for a subsequent smart variable
            if (empty($part_event) && in_array($smart, self::$eventNameSmartVariables)) {
                preg_match_all($reSmart_start, $after, $after_matches, PREG_SET_ORDER, 0);
                if (count($after_matches) && in_array($after_matches[0]["smart"], self::$smartVariablesWithEvent)) {
                    // So there is an adjacent, valid smart variable. Thus, the supposed standalone smart variable will be treated 
                    // as an event (by skipping over it). To do this, glue before/match/after together and continue the loop
                    $after = $before.$smart_match[0].$after;
                    continue;
                }
            }
            // Now the part can be assembled
            $part = array (
                self::p_name => $smart,
                self::p_kind => self::kind_smart,
                self::p_event => empty($part_event) ? "event-name" : $part_event,
                self::p_instance => $part_instance,
                self::p_form => null,
                self::p_commands_raw => empty($smart_match["commands"]) ? array() : explode(":", trim($smart_match["commands"], ":")),
                self::p_commands => array(),
                self::p_construct => $this_construct,
                self::p_warnings => $part_warnings,
            );
            $template .= $before.$prefix.$parts_idx.$postfix;
            $state[self::ps_parts][$parts_idx] = $part;
            $parts_idx++;
        }
        $template .= $after;
        #endregion
        //
        $state[self::ps_template] = $template;
        //
        #region Cleanup, Additional Warnings - TODO
        //
        // Anything left in the template that looks like a field or smart variable is a potential user error
        // Thus, issue warnings for those
        // TODO: These should probably be added as parts with an empty value ("") and type of "invalid"
        preg_match_all(self::$reField, $template, $matches, PREG_SET_ORDER, 0);
        foreach ($matches as $match) {
            $state["warnings"][] = "Potentially erroneous piping construct: {$match[0]}."; // tt-fy
        }
        preg_match_all(self::$reSmart, $template, $matches, PREG_SET_ORDER, 0);
        foreach ($matches as $match) {
            $state["warnings"][] = "Potentially erroneous piping construct: {$match[0]}."; // tt-fy
        }
        if (strpos($template, "[-]") !== false) {
            $state["warnings"][] = "Potentially unneeded piping expression separator ([-])."; // tt-fy
        }
        #endregion
        //
        #region Add Part Processors, Execute TASK_ALLOWED
        foreach ($state[self::ps_parts] as &$part) {
            $this_processor = false;
            if (self::isField($part)) {
                $this_processor = [ __CLASS__, "processFields" ];
            }
            else if (self::isSSV($part)) {
                $this_processor = self::getSSVProcessor($part[self::p_name]);
            }
            $part[self::p_processor] = $this_processor;
            if ($this_processor) {
                $this_processor($state, self::TASK_ALLOWED, $part);
            }
        }
        #endregion
        //
        $state[self::ps_done] = self::isDone($state) ? self::STAGE_PARSE : false;
        return $state;
    }

    /** Preprocess expression parts (resolves smart variables and event ids).
     * Augments $state with
     *  - "fields" => [ "List", "of", "field", "names" ]
     *  - "events" => [ List of event ids (integers) ]
     *  - "context" => [
     *       "event_id" => Event id (integer),
     *       "instance" => Instance number (integer),
     *       "form" => "Unique instrument name",
     *       "user_id" => "User id",
     *       "repeating" => "event"|"form"|false
     *    ]
     *  - "enums" => [
     *       "field_name" => [ "code" => "label", ... ],
     *    ]
     * Augments parts with
     *  - "event_id" => Resolved event id (integer) | null if this cannot resolve (e.g., because the field does not exist on the event)
     *  - "instance_num" => Resolved instance number (integer), "defer" if not yet evaluated, or false if resolution is impossible
     *  - "value" => "Value of fields/smart variables" | null if the value cannot be resolved
     *  - "repeating" => "form or event" | false
     *  In case of sql fields:
     *  - "sql" => "SQL for generating enum" | false (when data is needed for resolution; sql_resolver is then set)
     *  - "sql_enum" => [ Expanded enum ]
     *  - "sql_resolver" => [ Data structure for further resolution ] (only when needed)
     * @param array $state 
     * @param int|string|null $ctx_event_id 
     * @param int $ctx_instance 
     * @param string|null $ctx_form
     * @param int|string|null $ctx_participant_id
     * @param string|null $ctx_user_id
     * @return array
     */
    public static function preprocessParts($state, $ctx_event_id = null, $ctx_instance = 1, $ctx_form = null, $ctx_participant_id, $ctx_user_id = null) {
        //
        #region Preparations
        //
        /** @var Project $Proj */
        $Proj = $state[self::ps_context][self::pctx_Proj];
        $ctx_pid = $Proj->project_id;
        $ctx_event_id = self::validateEventId($ctx_event_id, $ctx_pid);
        $ctx_instance = is_numeric($ctx_instance) ? $ctx_instance : null;
        $ctx_participant_id = is_numeric($ctx_participant_id) ? $ctx_participant_id : null;
        $ctx_form = self::validateForm($ctx_form, $ctx_participant_id);
        $ctx_event_name = $Proj->getUniqueEventNames($ctx_event_id);
        if (empty($ctx_user_id) && defined("USERID")) $ctx_user_id = USERID;
        $fields = array();
        $events = $Proj->longitudinal ? array($ctx_event_id) : array();
        $ctx_repeating = $Proj->isRepeatingEvent($ctx_event_id) ? self::repeating_event :
            ($Proj->isRepeatingForm($ctx_event_id, $ctx_form) ? self::repeating_form : false);
        // Add context
        $state[self::ps_context] = array_merge($state[self::ps_context], array(
            self::pctx_event_id => $ctx_event_id,
            self::pctx_instance => $ctx_instance,
            self::pctx_form => $ctx_form,
            self::pctx_user_id => $ctx_user_id,
            self::pctx_repeating => $ctx_repeating,
        ));
        // Set stage
        $state[self::ps_stage] = self::STAGE_PARTS;
        $enums = array();
        #endregion
        //
        #region Parts Processing
        //
        foreach ($state[self::ps_parts] as &$part) {

            $processor = $part[self::p_processor];
            //
            #region Resolve Events
            //
            if ($Proj->longitudinal) {
                if (strpos($part[self::p_event], "event-name") !== false) {
                    // Evaluate event-name smart variable
                    switch ($part[self::p_event]) {
                        case "event-name":
                            $part[self::p_event_id] = $ctx_event_id;
                        break;
                        case "next-event-name":
                            $part[self::p_event_id] = self::getNextEventIdWithinArm($Proj, $ctx_event_id, $part[self::p_form]);
                        break;
                        case "previous-event-name":
                            $part[self::p_event_id] = self::getPreviousEventIdWithinArm($Proj, $ctx_event_id, $part[self::p_form]);
                        break;
                        case "first-event-name":
                            $part[self::p_event_id] = self::getFirstEventIdWithinArm($Proj, $ctx_event_id, $part[self::p_form]);
                        break;
                        case "last-event-name":
                            $part[self::p_event_id] = self::getLastEventIdWithinArm($Proj, $ctx_event_id, $part[self::p_form]);
                        break;
                    }
                    if ($part[self::p_event_id]) $events[] = $part[self::p_event_id];
                }
                else {
                    // Explicit event name
                    $this_event = $Proj->getEventIdUsingUniqueEventName($part[self::p_event]);
                    if ($this_event === false) {
                        $part[self::p_event_id] = null;
                        self::resolve($state, $part, null, "The event '{$part["event"]}' does not exist in this project. Thus, this will never have a value."); // tt-fy
                    }
                    // In case of a field part, check whether the field's form exists on that event
                    else if (self::isField($part) && !$Proj->validateFormEvent($part[self::p_form], $this_event)) {
                        $part[self::p_event_id] = null;
                        self::resolve($state, $part, null, "The field '{$part["name"]}' does not exist on the event '{$Proj->getUniqueEventNames($part["event_id"])}'. Thus, this will never have a value."); // tt-fy
                    }
                    else {
                        $part[self::p_event_id] = $this_event;
                    }
                }
            }
            else {
                // There is only one event in non-longitudinal projects
                $part[self::p_event_id] = $ctx_event_id;
                // Warn in case an event other than [event-name] has been specified:
                if ($part[self::p_event] != "event-name") {
                    self::warn($part, "In non-longitudinal projects, events should not be specified."); // tt-fy
                }
            }
            #endregion
            //
            #region Add Repeating Info
            //
            if ($Proj->isRepeatingEvent($part[self::p_event_id])) {
                $part[self::p_repeating] = self::repeating_event;
            }
            else if (!empty($part[self::p_form]) && $Proj->isRepeatingForm($part[self::p_event_id], $part[self::p_form])) {
                $part[self::p_repeating] = self::repeating_form;
            }
            else {
                $part[self::p_repeating] = false;
            }
            #endregion
            //
            #region Resolve Instance
            if (self::isField($part) || in_array($part[self::p_name], self::$smartVariablesWithInstance)) {
                // Is the instance number set explicitly?
                if (is_numeric($part[self::p_instance])) {
                    $part_instance = $part[self::p_instance] * 1;
                    // Check if these make sense and issue a warning if not
                    if (!$part[self::p_repeating]) {
                        if ($part_instance == 1) {
                            $part[self::p_instance_num] = 1;
                            self::warn($part, "The instance qualifer [1] has no effect in the given context of the event '{$Proj->getUniqueEventNames($part[self::p_event_id])}'. It will be ignored."); // tt-fy
                        }
                        else {
                            $part[self::p_instance_num] = false;
                            self::resolve($state, $part, null, "The instance qualifier is invalid in the context of the event '{$Proj->getUniqueEventNames($part[self::p_event_id])}'. This will result in an empty value."); // tt-fy
                        }
                    }
                    else {
                        $part[self::p_instance_num] = $part_instance;
                    }
                }
                // No instance specified
                else if (empty($part[self::p_instance])) {
                    // Is the field on the context event?
                    if ($part[self::p_event_id] == $ctx_event_id) {
                        if ($part[self::p_repeating] == self::repeating_event) {
                            // The context instance ([current-instance]) applies
                            $part[self::p_instance] = "current-instance";
                            $part[self::p_instance_num] = $ctx_instance;
                        }
                        else if ($part[self::p_repeating] == self::repeating_form) {
                            // The context instance only applies if the field is on the context form
                            if ($part[self::p_form] == $ctx_form) {
                                $part[self::p_instance] = "current-instance";
                                $part[self::p_instance_num] = $ctx_instance;
                            }
                            else {
                                // Check whether the field's form is repeating or not
                                if ($Proj->isRepeatingForm($ctx_event_id, $part[self::p_form])) {
                                    // There really should be an instance specified. REDCap assumed the first instance here, 
                                    // but this should really not resolve to anything, forcing the user to be explicit.
                                    // TODO - revisit this after discussion, for now, assume [first-instance], but it really should 
                                    // be [first-existing-instance].
                                    $part[self::p_instance] = "first-instance";
                                    $part[self::p_instance_num] = self::defer_instance_eval;
                                    self::warn($part, "The field '{$part[self::p_name]}' is on a repeating form or event outside of the context of form '{$ctx_form}' on event '{$ctx_event_name}'. By default, the first instance is assumed, but the instance should be explicitly specified."); // tt-fy
                                }
                                else {
                                    // In the non-repeating situation, [1] is used
                                    $part[self::p_instance_num] = 1;
                                }
                            }
                        }
                        else {
                            $part[self::p_instance_num] = 1;
                        }
                    }
                    // The field/smart variable is not on the context event
                    else {
                        if ($part[self::p_repeating]) {
                            // There really should be an instance specified. REDCap assumed the first instance here, 
                            // but this should really not resolve to anything, forcing the user to be explicit.
                            // TODO - revisit this after discussion, for now, assume [first-instance], but it really should be
                            // [first-existing-instance].
                            $part[self::p_instance] = "first-instance";
                            $part[self::p_instance_num] = self::defer_instance_eval;
                            self::warn($part, "This refers to a repeating form or event context other than the current context of the form '{$ctx_form}' on the event '{$ctx_event_name}'. By default, the first instance in this other context is assumed, but the instance should be explicitly specified."); // tt-fy
                        }
                        else {
                            // In the non-repeating situation, [1] is used
                            $part[self::p_instance_num] = 1;
                        }
                    }
                }
                // An instance smart variable is used
                else {
                    // Is the field/smart variable scoped to the context event?
                    if ($part[self::p_event_id] == $ctx_event_id) {
                        if ($part[self::p_repeating] == self::repeating_event) {
                            // As the entire event is repeating, the instance can be resolved once data is available
                            $part[self::p_instance_num] = self::defer_instance_eval;
                        }
                        else if (self::isField($part) && $part[self::p_repeating] == self::repeating_form && $part[self::p_form] == $ctx_form) {
                            // As the field is on the context repeating form, the instance can be resolved once data is available
                            $part[self::p_instance_num] = self::defer_instance_eval;
                        }
                        else if ($part[self::p_repeating]) {
                            // When outside of the context, only [first/last-instance] have meaning
                            if (in_array($part[self::p_instance], self::$outsideContextResolvableInstanceSmartVariables)) {
                                $part[self::p_instance_num] = self::defer_instance_eval;
                            }
                            else {
                                // Instance can never be resolved
                                $part[self::p_instance_num] = false;
                                self::resolve($state, $part, null, "This refers to a repeating form or event context other than the current context of the form '{$ctx_form}' on the event '{$ctx_event_name}'. Thus, the instance specifier cannot be resolved and this piping expression cannot result in a value."); // tt-fy
                            }
                        }
                    } 
                    // The field is not on the context event 
                    // Is the field's event repeating or is the field's form repeating on this event?
                    else if ($Proj->isRepeatingEvent($part[self::p_event_id]) || $Proj->isRepeatingForm($part[self::p_event_id], $part[self::p_form])) {
                        // Only [first/last-instance] can be resolved
                            if (in_array($part[self::p_instance], self::$outsideContextResolvableInstanceSmartVariables)) {
                            $part[self::p_instance_num] = self::defer_instance_eval;
                        }
                        else {
                            // Instance can never be resolved
                            $part[self::p_instance_num] = false;
                            self::resolve($state, $part, null, "This refers to a repeating form or event context other than the current context of the form '{$ctx_form}' on the event '{$ctx_event_name}'. Thus, the instance specifier cannot be resolved and the corresponding piping expression cannot result in a value."); // tt-fy
                        }
                    }
                    else {
                        // Not repeating - a warning is issued, but the instance is resolved to [1]
                        $part[self::p_instance_num] = 1;
                        self::warn($part, "This refers to a non-repeating form or event context. Thus, the instance specifier will be ignored."); // tt-fy
                    }
                }
            }
            else if(!empty($part[self::p_instance])) {
                // There should not be an instance specifier
                $part[self::p_instance] = "";
                self::warn($part, "Instance qualifiers are not supported and will be ignored."); // tt-fy
            }
            #endregion
            //
            #region Process Fields
            //
            if (self::isField($part)) {
                $field = $part[self::p_name];
                $fields[] = $field;
                // Add field type info and field enums
                $part[self::p_type] = self::getFieldType($Proj, $field);
                if (($part[self::p_type] == "radio" || $part[self::p_type] == "checkbox") && empty($enums[$field])) {
                    $enums[$field] = self::getFieldEnum($Proj, $field);
                }
                // Is this a PHI field?
                $part[self::p_identifier] = $Proj->metadata[$field]["field_phi"] == 1;
                // SQL fields need special treatment, as they may contain piping constructs themselves
                // Therefore, their enum will be stored internally, and may only be resolvable with record data.
                if ($part[self::p_type] == "sql") {
                    $this_rawsql = $Proj->metadata[$field]["element_enum"];
                    $this_sql_resolver = self::parse($ctx_pid, $this_rawsql);
                    $this_sql_resolver = self::preprocessParts($this_sql_resolver, $ctx_event_id, $ctx_instance, $ctx_form, $ctx_participant_id, $ctx_user_id);
                    $this_sql = null;
                    $this_sql_enum = null;
                    if ($this_sql_resolver[self::ps_done]) {
                        // When the sql is fully resolved within the first two steps, then the resulting enum can be cached
                        if (isset(self::$sqlEnumCache[$ctx_pid][$field])) {
                            $this_sql_enum = self::$sqlEnumCache[$ctx_pid][$field];
                            $this_sql = self::$sqlCache[$ctx_pid][$field];
                        }
                        else {
                            $this_sql = self::render($this_sql_resolver, [__CLASS__, "sqlPartRenderer"]);
                            $this_sql_enum = self::getSqlFieldEnum($this_sql);
                            self::$sqlEnumCache[$ctx_pid][$field] = $this_sql_enum;
                            self::$sqlCache[$ctx_pid][$field] = $this_sql;
                        }
                    }
                    else {
                        $part[self::p_sql_resolver] = $this_sql_resolver;
                        $this_sql = false; // Indicates that stage-3-resolution (with data) is necessary
                    }
                    $part[self::p_sql] = $this_sql;
                    $part[self::p_sql_enum] = $this_sql_enum; 
                }
                // Validate commands (doing this AFTER setting field type etc.)
                if ($processor) {
                    $processor($state, self::TASK_COMMANDS, $part);
                }
            }
            #endregion
            //
            #region Process Standalone Smart Variables
            //
            else if (self::isSSV($part)) {
                $smart = $part[self::p_name];
                // Validate commands (doing this BEFORE attempting to resolve)
                if ($processor) {
                    $processor($state, self::TASK_COMMANDS, $part);
                }

                switch($smart) {
                    //
                    #region User (fully resolved)
                    //
                    case "user-name":
                        $part["value"] = self::getUserName($ctx_user_id);
                    break;
                    case "user-fullname":
                        $part["value"] = self::getUserFullname($ctx_user_id);
                    break;
                    case "user-email":
                        $part["value"] = self::getUserEmail($ctx_user_id);
                    break;
                    case "user-dag-id":
                        $part["value"] = self::getUserDagId($ctx_user_id, $ctx_pid);
                    break;
                    case "user-dag-name":
                        $dag_id = self::getUserDagId($ctx_user_id, $ctx_pid);
                        $part["value"] = empty($dag_id) ? "" : $Proj->getUniqueGroupNames($dag_id);
                    break;
                    case "user-dag-label":
                        $dag_id = self::getUserDagId($ctx_user_id, $ctx_pid);
                        $part["value"] = empty($dag_id) ? "" : $Proj->getGroups($dag_id);
                    break;
                    #endregion
                    //
                    #region Form and Survey (partially resolved)
                    //
                    case "is-form":
                        $part["value"] = (PAGE == "DataEntry/index.php" && isset($_GET["id"]) && isset($_GET["page"])) ? "1" : "0";
                    break;
                    case "is-survey":
                        $part["value"] = (PAGE == 'surveys/index.php') ? '1' : '0';
                    break;
                    case "form-url": // [form-url:instrument]
                    case "survey-url": // [survey-url:instrument]
                        // Check commands - there must be no more than 1
                        if (count($part["commands"]) > 1) {
                            $part["warnings"][] = "Must not specify more than one parameter."; // tt-fy
                        }
                        // Validate commands
                        $this_instrument = trim($part["commands"][0]);
                        if (empty($this_instrument)) $this_instrument = $ctx_form;
                        if ($Proj->validateFormEvent($this_instrument, $part["event_id"])) {
                            $part["commands"] = array($this_instrument);
                            // Check if enabled as survey
                            if ($smart == "survey-url" && !is_numeric($Proj->forms[$this_instrument]["survey_id"])) {
                                $part["value"] = null;
                                $part["warnings"][] = "The instrument '{$this_instrument}' is not enabled as a survey."; // tt-fy
                            }
                        }
                        else {
                            // Invalid form - can never resolve
                            $part["value"] = null;
                            $part["warnings"][] = "The instrument ('{$this_instrument}') does not exist on the event '{$Proj->getUniqueEventNames($part["event_id"])}'."; // tt-fy
                        }
                    break;
                    case "form-link": // [form-link:instrument:Custom Text]
                    case "survey-link": // [survey-link:instrument:Custom Text]
                        $this_form = trim($part["commands"][0]);
                        // Does this instrument exist in the project? If not, assume that no instrument has been specified
                        if (empty($this_form) || !isset($Proj->forms[$this_form])) {
                            $this_form = $ctx_form;
                            $this_custom = join(":", $part["commands"]);
                        }
                        else {
                            $this_custom = join(":", array_slice($part["commands"], 1));
                        }
                        if (empty($this_custom)) {
                            if ($smart == "form-link") {
                                $this_custom = $Proj->forms[$this_form]["menu"];
                            }
                            else {
                                $this_custom = $Proj->surveys[$Proj->forms[$this_form]["survey_id"]]["title"];
                            }
                        }
                        $part["commands"] = array($this_form, $this_custom);
                        // Validate event/form combination
                        if (!$Proj->validateFormEvent($this_form, $part["event_id"])) {
                            // Invalid - can never resolve
                            $part["value"] = null;
                            $part["warnings"][] = "The form ('{$this_form}') does not exist on the event '{$Proj->getUniqueEventNames($part["event_id"])}'."; // tt-fy
                        }
                        else if ($smart == "survey-link" && !is_numeric($Proj->forms[$this_form]["survey_id"])) {
                            // Instrument is not enabled as survey
                            $part["value"] = null;
                            $part["warnings"][] = "The instrument '{$this_form}' is not enabled as a survey."; // tt-fy
                        }
                    break;
                    case "instrument-name":
                        $part["value"] = $ctx_form;
                    break;
                    case "instrument-label":
                        $part["value"] = isset($Proj->forms[$ctx_form]) ? $Proj->forms[$ctx_form]["menu"] : "";
                    break;
                    case "survey-queue-link": // [survey-queue-link:Custom Text]
                        $this_custom = trim(join(":", $part["commands"]));
                        if (empty($this_custom)) $this_custom = $GLOBALS["lang"]["survey_553"]; // Survey Queue Link
                        $part["commands"] = array($this_custom);
                        // Fall through
                    case "survey-queue-url":
                        // Check if the survey queue is enabled
                        if (!Survey::surveyQueueEnabled($ctx_pid)) {
                            $part["value"] = null;
                            $part["warnings"][] = "The survey queue is not enabled in this project."; // tt-fy
                        }
                    break;
                    case "survey-time-completed": // [survey-time-completed:instrument(:value)]
                    case "survey-date-completed": // [survey-date-completed:instrument(:value)]
                        // Nothing to do in this step
                    break;
                    case "survey-title": // [survey-title:instrument]
                        $this_form = $part["commands"][0];
                        $this_form = empty($this_form) ? $ctx_form : $this_form;
                        if (isset($Proj->forms[$this_form])) {
                            if (isset($Proj->forms[$this_form]["survey_id"])) {
                                $part["value"] = $Proj->surveys[$Proj->forms[$this_form]["survey_id"]]["title"];
                            }
                            else {
                                $part["value"] = null;
                                // Warn that the instrument not being enabled as a survey
                                $part["warnings"][] = "The instrument '{$this_form}' is not enabled as a survey."; // tt-fy
                            }
                        }
                        else {
                            $part["value"] = null;
                            // Warn that the instrument does not exist
                            $part["warnings"][] = "The instrument '{$this_form}' does not exist in this project."; // tt-fy
                        }
                    break;
                    #endregion
                    //
                    #region Event & Arm (fully resolved)
                    //
                    case "event-name":
                        $part["value"] = is_numeric($part["event_id"]) ? $Proj->getUniqueEventNames($part["event_id"]) : null;
                    break;
                    case "event-label":
                        $part["value"] = is_numeric($part["event_id"]) ? $Proj->eventInfo[$part["event_id"]]["name"] : null;
                    break;
                    case "first-event-name":
                    case "first-event-label":
                    case "previous-event-name":
                    case "previous-event-label":
                    case "next-event-name":
                    case "next-event-label":
                    case "last-event-name":
                    case "last-event-label":
                        // Note: Stand-alone event smart variable does not observe designated forms
                        $setEventId = false;
                        switch ($smart[0]) {
                            case "f": $setEventId = $Proj->getFirstEventIdInArmByEventId($part["event_id"]); break;
                            case "p": $setEventId = $Proj->getPrevEventId($part["event_id"]); break;
                            case "n": $setEventId = $Proj->getNextEventId($part["event_id"]); break;
                            case "l": $setEventId = $Proj->getLastEventIdInArmByEventId($part["event_id"]);
                        }
                        if (is_numeric($setEventId)) {
                            if (substr($smart, -4) == "name") {
                                $part["value"] = $Proj->getUniqueEventNames($setEventId);
                            }
                            else {
                                $part["value"] = $Proj->eventInfo[$setEventId]["name"];
                            }
                        }
                        else {
                            $part["value"] = null;
                        } 
                    break;
                    case "arm-number":
                        $part["value"] = is_numeric($part["event_id"]) ? $Proj->eventInfo[$part["event_id"]]["arm_num"] : "";
                    break;
                    case "arm-label":
                        $part["value"] = is_numeric($part["event_id"]) ? $Proj->eventInfo[$part["event_id"]]["arm_num"]["name"] : "";
                    break;
                    #endregion
                    //
                    #region Repeating Instruments and Events (partially resolved)
                    //
                    case "current-instance":
                    case "previous-instance":
                    case "next-instance":
                        // Resolvable when in the same event and context is repeating
                        if ($ctx_event_id == $part["event_id"] && $ctx_repeating) {
                            $prev = $ctx_instance - 1;
                            $next = $ctx_instance + 1;
                            if ($smart[0] == "p") {
                                $part["value"] = $prev > 0 ? $prev : null; // No instance < 1
                            }
                            else if ($smart[0] == "n") {
                                $part["value"] = $next;
                            }
                            else {
                                $part["value"] = $ctx_instance;
                            }
                        }
                        else {
                            $part["value"] = null;
                            $part["warnings"][] = "Cannot resolve previous, current, or next instance outside of a repeating context.";
                        }
                    break;
                    case "first-instance": // [first-instance:insrument]
                    case "last-instance": // [last-instance:instrument]
                        // Check commands
                        if (count($part["commands"]) > 1) {
                            $part["warnings"][] = "Must not specify more than one parameter."; // tt-fy
                        }
                        else if (count($part["commands"])) {
                            // Validate commands - Form must exist and be a repeating form
                            $this_form = trim($part["commands"][0]);
                            if (!isset($Proj->forms[$this_form])) {
                                $parts["value"] = null;
                                $parts["warnings"][] = "The instrument '{$this_form}' does not exist in this project."; // tt-fy
                            }
                            else if (!$Proj->isRepeatingForm($part["event_id"], $this_form)) {
                                $parts["value"] = null;
                                $parts["warnings"][] = "The instrument '{$this_form}' is not repeating on the event '{$Proj->getUniqueEventNames($part["event_id"])}'."; // tt-fy
                            }
                            else {
                                // Store validated command
                                $part["commands"] = array($this_form);
                            }
                        }
                        // Check if current context is repeating
                        else if (!$ctx_repeating) {
                            $part["value"] = null;
                            $part["warnings"][] = "Cannot resolve first or last instance outside of a repeating context.";
                        }
                        else if ($ctx_repeating == "form") {
                            if ($ctx_form) {
                                // Store context form as command
                                $part["commands"] = array($ctx_form);
                            }
                            else {
                                // If there is no form context, then this is not resolvable - no warning, as this is not a user's fault
                                $part["value"] = null;
                            }
                        }
                    break;
                    #endregion
                    //
                    #region Miscellaneous (fully resolved)
                    //
                    case "project-id":
                        $part["value"] = $ctx_pid;
                    break;
                    case "redcap-base-url":
                        $part["value"] = APP_PATH_WEBROOT_FULL;
                    break;
                    case "redcap-version":
                        $part["value"] = REDCAP_VERSION;
                    break;
                    case "redcap-version-url":
                        $part["value"] = APP_PATH_WEBROOT_FULL."redcap_v".REDCAP_VERSION."/";
                    break;
                    case "survey-base-url":
                        $part["value"] = APP_PATH_SURVEY_FULL;
                    break;
                    #endregion
                }
                // Resolve survey smart variables when surveys are not enabled in the project
                if (!$part["done"] && !$Proj->project["surveys_enabled"] && in_array($smart, self::$smartVariablesRequiringSurveysEnabled)) {
                    // This block will go away eventually
                    $part["value"] = null;
                    $part["warnings"][] = "Surveys are not enabled in this project."; // tt-fy
                }
            }
            #endregion
        }
        #endregion
        //
        #region Finalize
        //
        $state[self::ps_enums] = $enums;
        $state[self::ps_fields] = array_values(array_unique($fields, SORT_STRING));
        $state[self::ps_events] = array_values(array_unique($events, SORT_NUMERIC));
        $state[self::ps_done] = self::isDone($state);
        #endregion
        //
        return $state;
    }

    /** Applies data and resolves all instances and values.
     * 
     * Record name and optionally record data must be provided.
     * Augments $state with
     *  - "context" => [
     *       "record" => "Record ID"
     *       "participant_id" => Survey participant Id,
     *    ]
     * Augments parts with
     *  - "instance_num" => Resolved instance number (integer) for any defered
     *  - "instance_exists" => true|false When set, this indicates if the instance exists 
     *  - "value" => "Value of fields/smart variables"
     * @param array $state
     * @param string $record The record name (required)
     * @param array $record_data The record data (optional)
     * @return array
     */
    public static function applyData($state, $record = null, $ctx_participant_id, $data = array()) {
        //
        #region Preparations
        //
        /** @var Project $Proj */
        $Proj = $state[self::ps_context][self::pctx_Proj];
        $ctx_pid = $Proj->project_id;
        $ctx_event = $state[self::ps_context][self::pctx_event_id];
        $ctx_instance = $state[self::ps_context][self::pctx_instance];
        $ctx_form = $state[self::ps_context][self::pctx_form];
        $ctx_user = $state[self::ps_context][self::pctx_user_id];
        $ctx_repeating = $state[self::ps_context][self::pctx_repeating];
        $ctx_participant_id = is_numeric($ctx_participant_id) ? $ctx_participant_id : null;
        $state[self::ps_context][self::pctx_record] = $record;
        // Update stage
        $state[self::ps_stage] = self::STAGE_DATA;
        #endregion
        //
        #region Get Record and Data
        //
        if (!$state[self::ps_done]) {
            // Only obtain data in case it is needed.
            // If $record_data is not provided, obtain it via $record.
            if (empty($record) && is_numeric($ctx_participant_id)) {
                // Get record from participant id
                $record = Survey::getRecordFromParticipantId($ctx_participant_id);
            }
            if (!empty($record) && (empty($data) || !isset($data[$record]))) {
                if (count($state[self::ps_fields])) {
                    // Only get data when there are any fields
                    $data = Records::getData($ctx_pid, 'array', $record, $state[self::ps_fields], $state[self::ps_events]);
                }
                else {
                    // When there are no fields, prepare an empty data structure
                    $data = array($record => array());
                }
            }
        }
        #endregion
        //
        #region Update Context
        //
        $state[self::ps_context] = array_merge($state[self::ps_context], array(
            self::pctx_record => $record,
            self::pctx_participant_id => $ctx_participant_id,
        ));
        #endregion
        //
        #region Parts Processing
        //
        if (!isset($data[$record])) {
            // No data - set all remaining unset values to null
            foreach ($state[self::ps_parts] as &$part) {
                if (!self::isResolved($part)) {
                    self::resolve($state, $part, null, "No record data available."); // tt-fy
                }
            }
        }
        else {
            // Make data more "accessible"
            $record_data = $data[$record]; 
            // Resolve unresolved parts based on the record data
            foreach ($state[self::ps_parts] as &$part) {
                // In case the value is already set, the part can be skipped
                if (self::isResolved($part)) continue;
                $processor = $part[self::p_processor];
                //
                #region Resolve Instance
                //
                if ($part[self::p_instance_num] == self::defer_instance_eval) {
                    // Note: In context/out of context checks have already been done, so there 
                    // is no need for any further such checks
                    // Get instances list
                    $this_instances = array_keys(RepeatInstance::getRepeatFormInstanceList($record, $part[self::p_event_id], $part[self::p_form], $Proj));
                    if (count($this_instances)) {
                        switch ($part[self::p_instance]) {
                            case "first-instance":
                                $part[self::p_instance_num] = min($this_instances);
                                $part[self::p_instance_exists] = true;
                            break;
                            case "last-instance":
                                $part[self::p_instance_num] = max($this_instances);
                                $part[self::p_instance_exists] = true;
                            break;
                            case "previous-instance":
                                // To replicate legacy behavior, there will be no check whether 
                                // the repeat form or event exists. But we will not allow an instance < 1.
                                $this_instance = $ctx_instance - 1;
                                $part[self::p_instance_num] = $this_instance > 0 ? $this_instance : false;
                                $part[self::p_instance_exists] = in_array($this_instance, $this_instances);
                            break;
                            case "next-instance":
                                // To replicate legacy behavior, there will be no check whether
                                // the repeat form or event exists.
                                $this_instance = $ctx_instance + 1;
                                $part[self::p_instance_num] = $this_instance;
                                $part[self::p_instance_exists] = in_array($this_instance, $this_instances);
                            break;
                        }
                    }
                    else {
                        // There are no instances
                        $part[self::p_instance_num] = false;
                    }
                }
                #endregion
                //
                #region Validate Commands
                //
                if ($processor) {
                    $processor($state, self::TASK_COMMANDS, $part);
                }
                #endregion
                //
                #region Fields
                //
                if (self::isField($part)) {
                    // Get field data (instances)
                    if ($part[self::p_repeating] == self::repeating_form) {
                        $data_instances = $record_data["repeat_instances"][$part[self::p_event_id]][$part[self::p_form]];
                    }
                    else if ($part[self::p_repeating] == self::repeating_event) {
                        $data_instances = $record_data["repeat_instances"][$part[self::p_event_id]][null];
                    }
                    else {
                        $data_instances = array(1 => $record_data[$part[self::p_event_id]]);
                    }
                    $part[self::p_data] = $data_instances;
                    //
                    #region Resolve Instance
                    if ($part[self::p_instance_num] == self::defer_instance_eval) {
                        // Note: In context/out of context checks have already been done, so there 
                        // is no need for any further such checks
                        if (count($data_instances)) {
                            switch ($part[self::p_instance]) {
                                case "first-instance":
                                    $part[self::p_instance_num] = array_key_first($data_instances);
                                break;
                                case "last-instance":
                                    $part[self::p_instance_num] = array_key_last($data_instances);
                                break;
                                case "previous-instance":
                                    // To replicate legacy behavior, there will be no check whether 
                                    // the repeat form or event exists. But we will not allow an instance < 1.
                                    $this_instance = $ctx_instance - 1;
                                    $part[self::p_instance_num] = $this_instance > 0 ? $this_instance : false;
                                break;
                                case "next-instance":
                                    // To replicate legacy behavior, there will be no check whether
                                    // the repeat form or event exists.
                                    $this_instance = $ctx_instance + 1;
                                    $part[self::p_instance_num] = $this_instance;
                                break;
                            }
                        }
                        else {
                            // There are no instances
                            $part[self::p_instance_num] = false;
                        }
                    }
                    #endregion
                    //
                    #region Resolve SQL
                    if ($part[self::p_type] == "sql" && $part[self::p_sql] === false) {
                        $part[self::p_sql_resolver] = self::applyData($part[self::p_sql_resolver], $record, $data);
                        $part[self::p_sql] = self::render($part[self::p_sql_resolver], [__CLASS__, "sqlPartRenderer"]);
                        $part[self::p_sql_enum] = self::getSqlFieldEnum($part[self::p_sql]);
                    }
                    #endregion
                    //
                    // Let the field processor assign a value
                    $processor($state, self::TASK_RESOLVE, $part);
                    //
                }
                #endregion
                //
                #region Smart Variables
                //
                else if (self::isSSV($part)) {
                    $smart = $part[self::p_name];
                    switch($smart) {
                        //
                        #region Record, DAG
                        //
                        case "record-name":
                            $part["value"] = $record;
                        break;
                        case "record-dag-id" :
                        case "record-dag-name" :
                        case "record-dag-label" :
                            $dag_id = Records::getRecordGroupId($ctx_pid, $record);
                            if (!is_numeric($dag_id)) {
                                $part["value"] = null;
                            } 
                            elseif ($smart == "record-dag-id") {
                                $part["value"] = $dag_id;
                            } 
                            elseif ($smart == "record-dag-label") {
                                $dag_name = $Proj->getGroups($dag_id);
                                $part["value"] = $dag_name ? $dag_name : null;
                            } 
                            else {
                                $dag_name = $Proj->getUniqueGroupNames($dag_id);
                                $part["value"] = $dag_name ? $dag_name : null;
                            }
                        break;
                        #endregion
                        //
                        #region Repeating Instruments and Events
                        //
                        case "first-instance":
                        case "last-instance":
                            $data_instances = count($part["commands"]) ? 
                                $record_data["repeat_instances"][$part["event_id"]][$part["commands"][0]] :
                                $record_data["repeat_instances"][$part["event_id"]][null];
                            if (count($data_instances)) {
                                $part["value"] = $smart[0] == "f" ? array_key_first($data_instances) : array_key_last($data_instances);
                            }
                            else {
                                $part["value"] = null;
                            }
                        break;
                        #endregion
                        //
                        #region Form and Survey
                        //
                        case "form-url": // [form-url:instrument]
                        case "form-link": // [form-link:instrument:Custom Text]
                            $this_form = $part["commands"][0];
                            $this_title = RCView::escape($part["commands"][1]); // Escape since this is embedded in an <a> tag
                            $this_event_id = $part["event_id"];
                            $this_instance = $part["instance_num"];
                            $this_url = APP_PATH_WEBROOT_FULL . "redcap_v" . REDCAP_VERSION . "/DataEntry/index.php?" .
                                "pid={$ctx_pid}&page={$this_form}&id={$record}&event_id={$this_event_id}&instance={$this_instance}";
                            $part["value"] = $smart == "form-link" ?
                                "<a href=\"{$this_url}\" target=\"_blank\">{$this_title}</a>" : $this_url;
                        break;
                        case "survey-url": // [survey-url:instrument]
                        case "survey-link": // [survey-link:instrument:Custom]
                            $this_form = $part["commands"][0];
                            $this_title = RCView::escape($part["commands"][1]); // Escape since this is embedded in an <a> tag
                            $this_event_id = $part["event_id"];
                            $this_instance = $part["instance_num"];
                            if (is_numeric($ctx_participant_id) && $ctx_form == $this_form && $this_event_id == $ctx_event) {
                                // Get link using only participant_id from back-end (only if target form/event is same as context form/event)
                                $this_url = Survey::getSurveyLinkFromParticipantId($ctx_participant_id);
                            }
                            else if (!empty($record)) {
                                // Get link using record
                                $this_url = REDCap::getSurveyLink($record, $this_form, $this_event_id, $this_instance, $ctx_pid);
                            }
                            else if (empty($record) && $this_form == $Proj->firstForm && $this_event_id == $Proj->firstEventId) {
                                // Get public survey link
                                $this_url = APP_PATH_SURVEY_FULL . "?s=" . Survey::getSurveyHash($Proj->forms[$this_form]['survey_id'], $this_event_id);
                            }
                            $part["value"] = $smart == "survey-link" ?
                                "<a href=\"{$this_url}\" target=\"_blank\">{$this_title}</a>" : $this_url;
                        break;
                        case "survey-queue-url":
                        case "survey-queue-link": // [survey-queue-link:Custom Text]
                            $this_url = REDCap::getSurveyQueueLink($record, $ctx_pid);
                            $this_title = RCView::escape($part["commands"][0]); // Escape since this is embedded in an <a> tag
                            $part["value"] = $smart == "survey-queue-link" ?
                                "<a href=\"{$this_url}\" target=\"_blank\">{$this_title}</a>" : $this_url;
                        break;
                        case "survey-time-completed": // [survey-time-completed:instrument]
                        case "survey-date-completed": // [survey-date-completed:instrument]
                            $this_event_id = $part["event_id"];
                            $this_instance = $part["instance_num"];
                            $this_form = $part["form"];
                            $this_timestamp = Piping::getSurveyTimestamp($Proj, $record, $this_form, $this_event_id, $this_instance);
                            $part["value"] = $smart == "survey-date-completed" ?
                                substr($this_timestamp, 0, 10) : $this_timestamp;
                        break;
                        #endregion
                        //
                    }
                }
                #endregion
                //
            }
        }
        #endregion
        //
        $state[self::ps_done] = self::STAGE_DATA;
        return $state;
    }


    #region Part Processor (Fields)

    /** Process 'field' parts. Act upon task (depending on state/stage). Return after a task is handled.
     * 
     * @param array $state (read only)
     * @param string $task 
     * @param array $part Passed by reference!
     * @return void 
     */
    private static function processFields($state, $task, &$part) {
        $stage = $state[self::ps_stage];
        $field = $part[self::p_name];
        $type = $part[self::p_type];
        $validated = array();
        $defer = array();
        $invalid = array();
        //
        #region TASK_COMMANDS
        //
        if ($task == self::TASK_COMMANDS) {
            #region Defaults
            if ($stage == self::STAGE_PARTS) { // Only set in STAGE_PARTS (as before, type is not set)
                switch ($type) {
                    #region Date and Datetime
                    // Question: Should this all default to what is set in the user's profile? 
                    case "date_ymd":
                        $validated["format"] = "Y-m-d";
                    break;
                    case "date_dmy":
                        $validated["format"] = "d-m-Y";
                    break;
                    case "date_mdy": 
                        $validated["format"] = "m-d-Y";
                    break;
                    case "datetime_ymd":
                        $validated["format"] = "Y-m-d H:i";

                    case "datetime_dmy":
                        $validated["format"] = "d-m-Y H:i";
                    break;
                    case "datetime_mdy":
                        $validated["format"] = "m-d-Y H:i";
                    break;
                    case "datetime_seconds_ymd":
                        $validated["format"] = "Y-m-d H:i:s";
                    break;
                    case "datetime_seconds_dmy":
                        $validated["format"] = "d-m-Y H:i:s";
                    break;
                    case "datetime_seconds_mdy": 
                        $validated["format"] = "m-d-Y H:i:s";
                    break;
                    #endregion
                }
            }
            #endregion
            foreach ($part[self::p_commands_raw] as $cmd) {
                switch ($type) {
                    #region Checkbox
                    case "checkbox":
                        switch ($cmd) {
                            case "value": 
                                $validated["value"] = true;
                                $validated["label"] = false;
                            break;
                            case "label":
                                $validated["label"] = true;
                                $validated["value"] = false;
                            break;
                            case "checked":
                                if (empty($part[self::p_code])) {
                                    $validated["checked"] = true;
                                    $validated["unchecked"] = false;
                                }
                                else {
                                    self::resolve($state, $part, null, "Cannot use 'checked' together with code."); // tt-fy
                                }
                            break;
                            case "unchecked":
                                if (empty($part[self::p_code])) {
                                    $validated["unchecked"] = true;
                                    $validated["checked"] = false;
                                }
                                else {
                                    self::resolve($state, $part, null, "Cannot use 'unchecked' together with code."); // tt-fy
                                }
                            break;
                            default:
                            $invalid[] = $cmd;
                        }
                    break;
                    #endregion
                    #region Date and Datetime
                    case "date_ymd":
                    case "date_dmy":
                    case "date_mdy": 
                        switch ($cmd) {
                            case "value":
                            case "ymd":
                                $validated["format"] = "Y-m-d";
                                $validated["month-name"] = false;
                                $validated["day-name"] = false;
                            break;
                            case "dmy":
                                $validated["format"] = "d-m-Y";
                                $validated["month-name"] = false;
                                $validated["day-name"] = false;
                            break;
                            case "mdy":
                                $validated["format"] = "m-d-Y";
                                $validated["month-name"] = false;
                                $validated["day-name"] = false;
                            break;
                            case "year": 
                                $validated["format"] = "Y";
                                $validated["month-name"] = false;
                                $validated["day-name"] = false;
                            break;
                            case "month":
                                $validated["format"] = "m";
                                $validated["month-name"] = false;
                                $validated["day-name"] = false;
                            break;
                            case "day":
                                $validated["day"] = "d";
                                $validated["day-name"] = false;
                                $validated["month-name"] = false;
                            break;
                            case "month-name":
                                $validated["format"] = "m";
                                $validated["month-name"] = true;
                                $validated["day-name"] = false;
                            break;
                            case "day-name":
                                $validated["day"] = "d";
                                $validated["day-name"] = true;
                                $validated["month-name"] = false;
                            break;
                        }
                    break;
                    case "datetime_ymd":
                    case "datetime_dmy":
                    case "datetime_mdy":
                        switch ($cmd) {
                            case "value":
                            case "ymd":
                                $validated["format"] = "Y-m-d H:i";
                                $validated["day-name"] = false;
                                $validated["month-name"] = false;
                            break;
                            case "dmy":
                                $validated["format"] = "d-m-Y H:i";
                                $validated["day-name"] = false;
                                $validated["month-name"] = false;
                            break;
                            case "mdy":
                                $validated["format"] = "m-d-Y H:i";
                                $validated["day-name"] = false;
                                $validatad["month-name"] = false;
                            break;
                            case "year": 
                                $validated["format"] = "Y";
                                $validated["day-name"] = false;
                                $validated["month-name"] = false;
                            break;
                            case "month":
                                $validated["format"] = "m";
                                $validated["day-name"] = false;
                                $validated["month-name"] = false;
                            break;
                            case "day":
                                $validated["day"] = "d";
                                $validated["day-name"] = false;
                                $validated["month-name"] = false;
                            break;
                            case "month-name":
                                $validated["format"] = "m";
                                $validated["month-name"] = true;
                                $validated["day-name"] = false;
                            break;
                            case "day-name":
                                $validated["day"] = "d";
                                $validated["day-name"] = true;
                                $validated["month-name"] = false;
                            break;
                        }
                    break;
                    case "datetime_seconds_ymd":
                    case "datetime_seconds_dmy":
                    case "datetime_seconds_mdy": 
                        switch ($cmd) {
                            case "value": 
                            case "ymd":
                                $validated["format"] = "Y-m-d H:i:s";
                                $validated["day-name"] = false;
                                $validated["month-name"] = false;
                            break;
                            case "dmy":
                                $validated["format"] = "d-m-Y H:i:s";
                                $validated["day-name"] = false;
                                $validated["month-name"] = false;
                            break;
                            case "mdy":
                                $validated["format"] = "m-d-Y H:i:s";
                                $validated["day-name"] = false;
                                $validated["month-name"] = false;
                            break;
                            case "year": 
                                $validated["format"] = "Y";
                                $validated["day-name"] = false;
                                $validated["month-name"] = false;
                            break;
                            case "month":
                                $validated["format"] = "m";
                                $validated["month-name"] = false;
                                $validated["day-name"] = false;
                            break;
                            case "day":
                                $validated["day"] = "d";
                                $validated["day-name"] = false;
                                $validated["month-name"] = false;
                            break;
                            case "month-name":
                                $validated["format"] = "m";
                                $validated["month-name"] = true;
                                $validated["day-name"] = false;
                            break;
                            case "day-name":
                                $validated["day"] = "d";
                                $validated["day-name"] = true;
                                $validated["month-name"] = false;
                            break;
                            default:
                            $invalid[] = $cmd;
                        }
                    break;
                    #endregion
                    case "radio":
                    case "sql":
                        switch ($cmd) {
                            case "value": 
                                $validated["value"] = true;
                                $validated["label"] = false;
                            break;
                            case "label":
                                $validated["label"] = true;
                                $validated["value"] = false;
                            break;
                            default:
                            $invalid[] = $cmd;
                        }
                    break;
                    default:
                    $invalid[] = $cmd;
                }
            }
            foreach ($validated as $cmd => $val) {
                $part[self::p_commands][$cmd] = $val;
            }
            $part[self::p_commands_raw] = $defer;
            foreach ($invalid as $cmd) {
                self::warn($part, "Invalid command parameter '{$cmd}' is ignored."); // tt-fy
            }
            // Check if all required are present
            // There are no required command parameters for field piping
            if ($stage == self::STAGE_DATA) {
                $commands = $part[self::p_commands];
                switch ($type) {
                    case "checkbox":
                        // Must have either a code and or checked/unchecked
                        if (!($commands["checked"] || $commands["unchecked"]) && empty($part[self::p_code])) {
                            $part[self::p_commands]["checked"] = true;
                            // self::warn($part, "Neither a code nor 'checked' or 'unchecked' are specified. Assuming 'checked'."); // tt-fy
                        }
                    break;
                }
            }
            return;
        }
        #endregion
        //
        #region TASK_RESOLVE
        //
        if ($task == self::TASK_RESOLVE && $stage == self::STAGE_DATA) {
            // Assign value (depending on field type)
            if ($part[self::p_instance_num]) {
                $data = $part[self::p_data][$part[self::p_instance_num]][$field];
                $cmd = $part[self::p_commands];
                if ($data === null) {
                    self::resolve($state, $part, null);
                }
                else {
                    switch ($type) {
                        case "checkbox":
                            self::resolveCheckbox($state, $part, $data);
                        break;
                        case "radio":
                            $this_value = $cmd["value"] ? $data : $state[self::ps_enums][$field][$data];
                            self::resolve($state, $part, $this_value);
                        break;
                        case "date_ymd":
                        case "date_dmy":
                        case "date_mdy":
                            self::resolveDate($state, $part, "Y-m-d", $data);
                        break;
                        case "datetime_ymd":
                        case "datetime_dmy":
                        case "datetime_mdy": 
                            self::resolveDate($state, $part, "Y-m-d H:i", $data);
                        break;
                        case "datetime_seconds_ymd":
                        case "datetime_seconds_dmy":
                        case "datetime_seconds_mdy": 
                            self::resolveDate($state, $part, "Y-m-d H:i:s", $data);
                        break;
                        default:
                        self::resolve($state, $part, $data);
                    }
                }
            }
            else {
                // Value cannot be resolved
                self::resolve($state, $part, null, "No field value available."); // tt-fy
            }
            return;
        }
        #endregion
    }

    /** Resolves a date/datetime value.
     *
     * @param array $state 
     * @param array $part (passed by reference)
     * @param string $parseFormat
     * @param string $data
     * @return void 
     */
    private static function resolveDate($state, &$part, $parseFormat, $data) {
        $datetime = DateTime::createFromFormat($parseFormat, $data);
        if ($datetime === false) {
            self::resolve($state, $part, null, "Failed to parse stored date value '{$data}' as '{$parseFormat}'.");
            $dateInfo = date_parse($data);
            $datetime = DateTime::createFromFormat("Y-m-d H:i:s", "{$dateInfo["year"]}-{$dateInfo["month"]}-{$dateInfo["day"]} 00:00:00");
            if ($datetime === false) return;
        }
        $timestamp = $datetime->getTimestamp();
        $cmd = $part[self::p_commands];
        $value = date($cmd["format"], $timestamp);
        if ($cmd["month-name"]) {
            $monthnames = array ("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"); // tt-fy
            $value = $monthnames[$value * 1 - 1];
        }
        else if ($cmd["day-name"]) {
            $value = date("N", $timestamp); // 1 = Monday [global_100] ... 7 = Sunday [gobal_99]
            $value = $value % 7 + 99;
            $value = $GLOBALS["lang"]["global_$value"];
        }
        self::resolve($state, $part, $value);
    }

    /** Resolves checkbox values.
     * 
     * @param array $state 
     * @param array $part (passed by reference)
     * @param mixed $data 
     * @return void 
     */
    private static function resolveCheckbox($state, &$part, $data) {
        $enum = $state[self::ps_enums][$part[self::p_name]];
        $cmd = $part[self::p_commands];
        $code = $part[self::p_code];
        $value = empty($code) ? $data : $data[$code];
        if ($cmd["checked"] || $cmd["unchecked"]) {
            $values = array();
            foreach ($data as $this_code => $this_value) {
                if ($cmd["checked"] && $this_value == 1) {
                    $values[] = $cmd["value"] ? $this_code : $enum[$this_code];
                }
                else if ($cmd["unchecked"] && $this_value == 0) {
                    $values[] = $cmd["value"] ? $this_code : $enum[$this_code];
                }
            }
            self::resolve($state, $part, join(", ", $values));
        }
        else if ($cmd["value"]) {
            self::resolve($state, $part, $value);
        }
        else {
            $value = $enum[$value];
            self::resolve($state, $part, $value);
        }
    }

    #endregion

    #region Part Processors (Standalone Smart Variables)

    /**
     * A template for creating SSV processors. Copy, paste, fill in
     * @param array $state (read only)
     * @param string $task The task to be handled. Return after the task is handled.
     * @param array $part (passed by reference)
     * @return void 
     */
    private static function processSSV_template($state, $task, &$part) {
        $Proj = self::getProj($state);
        $stage = $state[self::ps_stage];
        //
        #region TASK_ALLOWED
        //
        if ($task == self::TASK_ALLOWED) {

            return;
        }
        #endregion
        //
        #region TASK_COMMANDS
        //
        if ($task == self::TASK_COMMANDS) {

            return;
        }
        #endregion
        //
        #region TASK_RESOLVE
        //
        if ($task == self::TASK_RESOLVE) {

            return;
        }
        #endregion
    }


    /**
     * Processor for [survey-time-completed:instrument(:value)]
     * @param array $state (read only)
     * @param string $task The task to be handled. Return after the task is handled.
     * @param array $part (passed by reference)
     * @return void 
     */
    private static function processSSV_survey_time_completed($state, $task, &$part) {
        $Proj = self::getProj($state);
        $stage = $state[self::ps_stage];
        //
        #region TASK_ALLOWED
        //
        if ($task == self::TASK_ALLOWED) {
            if (!$Proj->project["surveys_enabled"]) {
                self::disallow($state, $part, "Surveys are not enabled in this project.");
            }
            return;
        }
        #endregion
        //
        #region TASK_COMMANDS
        //
        if ($task == self::TASK_COMMANDS) {
            // Validate commands - there must be no more than 2
            if (count($part[self::p_commands_raw]) > 2) {
                self::warn($part, "Must not specify more than two parameter (instrument name and optionally 'value')."); // tt-fy
            }
            $this_form = trim($part["commands"][0]);
            if (empty($this_form)) {
                self::resolve($state, $part, null, "Missing required parameter 'instrument'."); // tt-fy
            } 
            else if (!isset($Proj->forms[$this_form])) {
                self::resolve($state, $part, null, "The instrument '{$this_form}' does not exist in this project."); // tt-fy
            }
            else if (!$Proj->validateFormEvent($this_form, $part["event_id"])) {
                self::resolve($state, $part, null, "The instrument ('{$this_form}') does not exist on the event '{$Proj->getUniqueEventNames($part["event_id"])}'."); // tt-fy
            }
            else if($Proj->project["surveys_enabled"] && !is_numeric($Proj->forms[$this_form]["survey_id"])) {
                self::resolve($state, $part, null, "The instrument '{$this_form}' is not enabled as a survey."); // tt-fy
            }
            $this_valueparam = trim($part["commands"][1]);
            if (!empty($this_valueparam && $this_valueparam != "value")) {
                self::warn($part, "Ignoring invalid second parameter. Only 'value' is allowed.");
            }
            $part["form"] = $this_form;
            $part["commands"] = array(
                "value" => $this_valueparam == "value",
            );
            return;
        }
        #endregion
        //
    }

    /**
     * Processor for [survey-date-completed:instrument(:value)]
     * 
     * This mirrors survey-time-completed and only augements on it occasionally.
     * @param array $state (read only)
     * @param string $task The task to be handled. Return after the task is handled.
     * @param array $part (passed by reference)
     * @return void 
     */
    private static function processSSV_survey_date_completed($state, $task, &$part) {
        self::processSSV_survey_time_completed($state, $task, $part);
    }

    #endregion


    /** Formats the values.
     * 
     * Values are formatted according to the commands / options
     * Depending on context (data entry/survey piping, export), user access rights and handling of identifiers needs to be observed.
     * @param $state Note - this is passed by reference!
     * @return void
     */
    public static function formatValues(&$state) {

        // TODO

    }





    #region Template and Part Renderers

    /** Renders a template.
     * 
     * Returns null in case of incompletely resolved piping data (unless force is set to true).
     * @param array $state 
     * @param callable|null $callback A part renderer: function partRenderer($state, $part, $options)
     * @param array $options Options to be passed to a part renderer
     * @param bool $force Set to true to force rendering of incompletely resolved piping data
     * @return string
     */
    public static function render($state, $callback = null, $options = array(), $force = false) {
        // Anything to render?
        if (!count($state["parts"])) return $state["template"];
        // Ready to render?
        $result = null;
        if ($state["done"] || $force) {
            // Let the callback render the parts
            if ($callback == null) $callback = [__CLASS__, "plainPartRenderer"];
            $rendered_parts = array();
            foreach ($state["parts"] as $key => $part) {
                $rendered_parts[$key] = $callback($state, $part, $options);
            }
            // Replace placeholders in the template
            $rest = $state["template"];
            $result = "";
            $prefix = $state["prefix"];
            $prefix_len = strlen($prefix);
            $postfix = $state["postfix"];
            $postfix_len = strlen($postfix);
            while (($pos = strpos($rest, $prefix)) !== false) {
                $result .= substr($rest, 0, $pos);
                $idx_start = $pos + $prefix_len;
                $idx_end = strpos($rest, $postfix, $pos + 1);
                $idx = substr($rest, $idx_start, $idx_end - $idx_start);
                $result .= $rendered_parts[$idx];
                $rest = substr($rest, $idx_end + $postfix_len);
            }
            $result .= $rest;
        }
        return $result;
    }

    /** Renders a part for use in SQL
     * 
     * @param array $state
     * @param array $part
     * @param array $options
     * @return string
     */
    public static function sqlPartRenderer($state, $part, $options) {
        // WARNING - TODO - For now, this simply puts out plain text and does not try to wrap anything in quotes or to escape stuff.
        return self::plainPartRenderer($state, $part, $options);
    }

    /** Renders a part as plain text. 
     * 
     * Unresolved values are treated according to the options:
     * [
     *   "null_value" => "______" (default is six underscores),
     *   "unresolved" => "as-null" (default) | "empty" | "construct" | "custom",
     *   "custom_unresolved" => "a custom unresolved value" (default: null_value),
     * ]
     * @param array $state
     * @param array $part
     * @param string $options
     * @return string
     */
    public static function plainPartRenderer($state, $part, $options) {
        $null_val = $options["null_value"] ?: "______";
        $unresolved = in_array($options["unresolved"], ["as-null", "empty", "construct", "custom"]) ? $options["unresolved"] : "as-null";
        $custom_unresolved = $options["custom_unresolved"] ?: $null_val;
        if (array_key_exists("value", $part)) {
            $value = $part["value"] === null ? $null_val : $part["value"];
        }
        else {
            $value = $null_val;
            switch ($unresolved) {
                case "construct":
                    $value = $part["construct"];
                break;
                case "empty":
                    $value = "";
                break;
                case "custom":
                    $value = $custom_unresolved;
                break;
            }
        }
        return $value;
    }

    #endregion




    #region Private Helpers


    /**
     * Adds one or more warnings to a part.
     * @param array $part Passed by reference!
     * @param string|array $warnings
     * @return void
     */
    private static function warn(&$part, $warnings) {
        array_push($part[self::p_warnings], $warnings);
    }

    /**
     * Checks whether a process for the specified standalone smart variable exists in this class
     * and if so, returns it.
     * @param string $smart Name of the smart variable
     * @return callable
     */
    private static function getSSVProcessor($smart) {
        $method = "processSSV_" . str_replace("-", "_", $smart);
        return method_exists(__CLASS__, $method) ? [ __CLASS__, $method ] : false;
    }

    /**
     * Sets p_value to the given value, p_done to STAGE, and optionally adds a warning.
     * @param array $state
     * @param array $part Passed by reference!
     * @param mixed $value
     * @param array|string|null $warnings
     * @return void
     */
    private static function resolve($state, &$part, $value = null, $warnings = null) {
        $part[self::p_value] = $value;
        $part[self::ps_done] = $state[self::ps_stage];
        if (!empty($warnings)) {
            array_push($part[self::p_warnings], $warnings);
        }
        // Unset data
        if (!$state[self::ps_debug]) unset($part[self::p_data]);
    }

    /**
     * Sets p_value to null, p_done to STAGE, and optionally overrides the default warning.
     * @param array $state
     * @param array $part Passed by reference!
     * @param array|string|null $warnings
     * @return void
     */
    private static function disallow($state, &$part, $warnings = null) {
        $part[self::p_value] = null;
        $part[self::ps_done] = $state[self::ps_stage];
        $warnings = !empty($warnings) ? $warnings : "This is not allowed or supported in the current context."; // tt-fy
        array_push($part[self::p_warnings], $warnings);
    }

    /**
     * Gets the Project object from the state.
     * @param array $state
     * @return Project
     */
    private static function getProj($state) {
        return $state[self::ps_context][self::pctx_Proj];
    }

    /** Returns true if the part is a field.
     * @param array $part
     * @return boolean
     */
    private static function isField($part) {
        return $part[self::p_kind] == self::kind_field;
    }

    /** Returns true if the part is a standalone smart variable.
     * @param array $part
     * @return boolean
     */
    private static function isSSV($part) {
        return $part[self::p_kind] == self::kind_smart;
    }

    /** Returns true if the part is resolved (i.e. it has a value).
     * @param array $part
     * @return boolean
     */
    private static function isResolved($part) {
        return array_key_exists(self::p_value, $part);
    }


    /**
     * Gets the enum for a radio/dropdown/yesno/truefalse/checkbox field
     * @param Project $Proj
     * @param string $field_name
     * @return array [ "code" => "label" ]
     */
    private static function getFieldEnum($Proj, $field_name) {
        return parseEnum($Proj->metadata[$field_name]["element_enum"]);
    }

    /**
     * Reads project metadata and returns the essential field type
     * @param Project $Proj
     * @param string $field_name
     * @return string
     */
    private static function getFieldType($Proj, $field_name) {
        $metadata = $Proj->metadata[$field_name];
        switch ($metadata["element_type"]) {
            case "text":
                switch ($metadata["element_validation_type"]) {
                    case null: 
                        return "text";
                    case "date_ymd":
                    case "date_dmy":
                    case "date_mdy": 
                    case "datetime_ymd":
                    case "datetime_dmy":
                    case "datetime_mdy": 
                    case "datetime_seconds_ymd":
                    case "datetime_seconds_dmy":
                    case "datetime_seconds_mdy": 
                    case "email":
                        return $metadata["element_validation_type"];
                    case "int":
                        return "integer";
                    case "float":
                    case "number_1dp":
                    case "number_2dp":
                    case "number_3dp":
                    case "number_4dp":
                        return "number_dot_decimal";
                    case "number_comma_decimal":
                    case "number_1dp_comma_decimal":
                    case "number_2dp_comma_decimal":
                    case "number_3dp_comma_decimal":
                    case "number_4dp_comma_decimal":
                        return "number_comma_decimal";
                    case "time":
                        return "time_hm";
                    case "time_mm_ss":
                        return "time_ms";
                }
                break;
            case "select":
            case "radio":
            case "yesno":
            case "truefalse":
                return "radio";
            case "file":
                return $metadata["element_validation_type"] == "signature" ? "signature" : "file";
            case "textarea":
            case "checkbox":
            case "calc":
            case "slider":
            case "descriptive":
            case "sql":
                return $metadata["element_type"];
        }
        return "custom";
    }

    private static function getUserName($user) {
        if ($user == USERID && UserRights::isImpersonatingUser()) {
            $user = UserRights::getUsernameImpersonating();
        }
        return $user;
    }

    private static function getUserFullname($user) {
        $user = self::getUserName($user);
        $user_info = empty($user) ? false : User::getUserInfo($user);
        return is_array($user_info) ? trim($user_info['user_firstname'])." ".trim($user_info['user_lastname']) : "";
    }

    private static function getUserEmail($user) {
        $user = self::getUserName($user);
        $user_info = empty($user) ? false : User::getUserInfo($user);
        return is_array($user_info) ? $user_info['user_email'] : "";
    }

    private static function getUserDagId($user, $project_id) {
        $user = self::getUserName($user);
        $userRights = UserRights::getPrivileges($project_id, $user);
        $dag_id = isset($userRights[$project_id][$user]) ? $userRights[$project_id][$user]['group_id'] : "";
        return is_numeric($dag_id) ? "$dag_id" : "";
    }

    private static function validateForm($form, $participant_id) {
        // If we have participant_id, use it to determine $form
        if (is_numeric($participant_id)) {
            $sql = "SELECT s.form_name 
                    FROM redcap_surveys s, redcap_surveys_participants p 
                    WHERE p.survey_id = s.survey_id AND p.participant_id = $participant_id";
            $q = db_query($sql);
            if (db_num_rows($q)) {
                $form = db_result($q, 0);
            }
        }
        return $form;
    }

    /**
     * Expands the expressions in template.
     * @param array $state 
     * @return string 
     */
    private static function expand($state) {
        $search = array();
        $replace = array();
        $idx = 0;
        foreach ($state["parts"] as $part) {
            $search[] = $state["prefix"].$idx.$state["postfix"];
            if ($part["kind"] = "field") {
                // Field
                $field = $part["name"];
                if (!empty($part["code"])) {
                    $field .= "({$part["code"]})";
                }
                if (!empty($part["commands"])) {
                    $field .= ":" . join(":", $part["commands"]);
                }
                $replace[] = "[{$part["event"]}][{$field}][{$part["instance"]}]";
            }
            else if ($part["kind"] == "smart") {
                // Smart Variable
                $smart = $part["name"] . (empty($part["commands"]) ? "" : (":" . join(":", $part["commands"]) ));
                $replace[] = "[{$smart}]";
            }
            $idx++;
        }
        return str_replace($search, $replace, $state["template"]);
    }

    /**
     * Generate a placeholder value that does not exist in the input.
     * @param string $input 
     * @param string $fixed A fixed part at the start of the prefix
     * @return string
     */
    private static function generatePrefix($input, $fixed) {
        $placeholder = $fixed;
        while (strpos($input, $placeholder) !== false) {
            $placeholder .= substr(self::$placeholderCharacterPool, rand(0, strlen(self::$placeholderCharacterPool) - 1), 1);
        }
        return $placeholder;
    }
    private static $placeholderCharacterPool = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";

    /**
     * Ensures that project id is valid.
     * @param mixed $project_id 
     * @return int 
     * @throws Exception 
     */
    private static function requireProjectId($project_id) {
        if (empty($project_id) && defined("PROJECT_ID")) $project_id = PROJECT_ID;
        if (empty($project_id) || !isinteger($project_id * 1) || ($project_id * 1) < 1) {
            throw new Exception("Must supply valid project id.");
        }
        return (int)($project_id * 1);
    }

    /**
     * Validates event id.
     * @param mixed $event_id 
     * @param int|string|null $project_id 
     * @return int|null
     */
    private static function validateEventId($event_id, $project_id = null) {
        $project_id = self::requireProjectId($project_id);
        $Proj = new Project($project_id);
        if (empty($event_id)) return $Proj->longitudinal ? null : $Proj->firstEventId;
        if (!is_numeric($event_id) || !isset($Proj->eventInfo[$event_id])) {
            return null;
        }
        return $event_id * 1;
    }


    /**
     * Checks whether all parts have a value key
     * @param array $state
     * @return boolean
     */
    private static function isDone($state) {
        $done = true;
        foreach($state[self::ps_parts] as $part) {
            $done = $done && array_key_exists(self::p_value, $part);
        }
        return $done;
    }


    /**
     * Gets a [ "field" => "form" ] array
     * @param Project $Proj
     * @return array
     */
    private static function getFieldForms($Proj) {
        $project_id = $Proj->project_id;
        if (isset(self::$fieldFormsCache[$project_id])) {
            return self::$fieldFormsCache[$project_id];
        }
        // Build
        $fieldForms = array();
        foreach ($Proj->forms as $form => $formInfo) {
            foreach ($formInfo["fields"] as $field => $_) {
                $fieldForms[$field] = $form;
            }
        }
        // Add to cache
        self::$fieldFormsCache[$project_id] = $fieldForms;
        return $fieldForms;
    }
    private static $fieldFormsCache = array();



    //If one field in query, then show field as both coded value and displayed text.
    //If two fields in query, then show first as coded value and second as displayed text.
    private static function getSqlFieldEnum($sql) {

        if (strtolower(substr(trim($sql), 0, 7)) == "select ") {
            $sql = html_entity_decode($sql, ENT_QUOTES);
            // Execute query
            $result = db_query($sql);
            if ($result) {
                $enum = "";
                while ($row = db_fetch_array($result, MYSQLI_NUM)) {
                    $enum .= str_replace(",", "&#44;", $row[0]);
                    if (!isset($row[1])) {
                        $enum .= " \\n ";
                    } 
                    else {
                        $enum .= ", " . str_replace(",", "&#44;", $row[1]) . " \\n ";
                    }
                }
                return parseEnum(substr($enum, 0, -4));
            } 
        }
        return array();
    }

    private static $sqlCache = array();
    private static $sqlEnumCache = array();


    #endregion


    #region Project Class Helpers

       /**
     * Gets the next event id within the same arm after the given event id.
     * When a form is specified, the next event id for which the form is designated is 
     * returned. In case there is no matching event, false is returned.
     * @param Project $Proj
     * @param string|int $event_id 
     * @param string|null $form 
     * @return int|false  
     */
    public static function getNextEventIdWithinArm($Proj, $event_id, $form = null) {
        if (!isset($Proj->eventInfo[$event_id])) return false;
        $arm_num = $Proj->eventInfo[$event_id]["arm_num"];
        if ($form != null) {
            // If form is provided, then find the next *designated* event for that form
            $eventsForms = array_intersect_key($Proj->eventsForms, $Proj->events[$arm_num]["events"]);
            $foundEventId = false;
            foreach ($eventsForms as $this_event_id => $forms) {
                if (!$foundEventId) {
                    $foundEventId = ($event_id == $this_event_id);
                    continue;
                }
                if (in_array($form, $forms)) {
                    return $this_event_id;
                }
            }
            return false;
        }
        $events = array_keys(array_intersect_key($Proj->eventInfo, $Proj->events[$arm_num]["events"]));
        $nextEventIndex = array_search($event_id, $events) + 1;
        return (isset($events[$nextEventIndex])) ? $events[$nextEventIndex] : false;
    }

    /**
     * Gets the previous event id within the same arm after the given event id.
     * When a form is specified, the previous event id for which the form is designated is 
     * returned. In case there is no matching event, false is returned.
     * @param Project $Proj
     * @param string|int $event_id 
     * @param string|null $form 
     * @return int|false  
     */
    public static function getPreviousEventIdWithinArm($Proj, $event_id, $form = null) {
        if (!isset($Proj->eventInfo[$event_id])) return false;
        $arm_num = $Proj->eventInfo[$event_id]["arm_num"];
        if ($form != null) {
            // If form is provided, then find the previous *designated* event for that form
            $eventsForms = array_intersect_key($Proj->eventsForms, $Proj->events[$arm_num]["events"]);
            $events = array_reverse(array_keys($eventsForms));
            $foundEventId = false;
            foreach ($events as $this_event_id) {
                if (!$foundEventId) {
                    $foundEventId = ($event_id == $this_event_id);
                    continue;
                }
                if (in_array($form, $eventsForms[$this_event_id])) {
                    return $this_event_id;
                }
            }
            return false;
        }
        $events = array_reverse(array_keys(array_intersect_key($Proj->eventInfo, $Proj->events[$arm_num]["events"])));
        $nextEventIndex = array_search($event_id, $events) + 1;
        return (isset($events[$nextEventIndex])) ? $events[$nextEventIndex] : false;
    }

    /**
     * Gets the first event id within the same arm as the given event id.
     * When a form is specified, the first event id for which the form is designated is 
     * returned. In case there is no matching event, false is returned.
     * @param Project $Proj
     * @param string|int $event_id 
     * @param string|null $form 
     * @return int|false  
     */
    public static function getFirstEventIdWithinArm($Proj, $event_id, $form = null) {
        if (!isset($Proj->eventInfo[$event_id])) return false;
        $arm_num = $Proj->eventInfo[$event_id]["arm_num"];
        if ($form != null) {
            $eventsForms = array_intersect_key($Proj->eventsForms, $Proj->events[$arm_num]["events"]);
            // If form is provided, then find the first *designated* event for that form
            foreach ($eventsForms as $this_event_id => $forms) {
                if (in_array($form, $forms)) {
                    return $this_event_id;
                }
            }
            return false;
        }
        $events = array_intersect_key($Proj->eventInfo, $Proj->events[$arm_num]["events"]);
        return array_key_first($events);
    }

    /**
     * Gets the last event id within the same arm as the given event id.
     * When a form is specified, the last event id for which the form is designated is 
     * returned. In case there is no matching event, false is returned.
     * @param Project $Proj
     * @param string|int $event_id 
     * @param string|null $form 
     * @return int|false  
     */
    public static function getLastEventIdWithinArm($Proj, $event_id, $form = null) {
        if (!isset($Proj->eventInfo[$event_id])) return false;
        $arm_num = $Proj->eventInfo[$event_id]["arm_num"];
        if ($form != null) {
            $eventsForms = array_intersect_key($Proj->eventsForms, $Proj->events[$arm_num]["events"]);
            $events = array_reverse(array_keys($eventsForms));
            // If form is provided, then find the first *designated* event for that form
            foreach ($events as $this_event_id) {
                if (in_array($form, $eventsForms[$this_event_id])) {
                    return $this_event_id;
                }
            }
            return false;
        }
        $events = array_intersect_key($Proj->eventInfo, $Proj->events[$arm_num]["events"]);
        return array_key_last($events);
    }

    #endregion

}