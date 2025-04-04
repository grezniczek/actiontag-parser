<?php namespace ActionTagParser;

use Exception;
use REDCap;

/**
 * Helper class defining segment types
 * @package ActionTagParser
 */
class SEGTYPE {
    /** @var string Outside tag segment */
    const OTS = "OTS"; 
    /** @var string Action tag segment */
    const TAG = "TAG";
}

/**
 * Helper class defining parameter types
 * @package ActionTagParser
 */
class PARAMTYPE {
    /** @var string Integer parameter */
    const INTEGER = "INT";
    /** @var string Unquoted string parameter */
    const UNQUOTED_STRING = "STRING";
    /** @var string Quoted (double or single) string parameter */
    const QUOTED_STRING = "QUOTED-STRING";
    /** @var string JSON parameter */
    const JSON = "JSON";
    /** @var string General arguments */
    const ARGS = "ARGS";
    /** @var string No arguments (i.e., the action tag does not support arguments */
    const NONE = "NONE";
}

/**
 * Action tag parser
 * @package ActionTagParser
 */
class ActionTagParser {

    /** @var string Escape character */
    const esc = "\\";
    /** @var string Action tag start character */
    const at = "@";
    /** @var string Valid characters at the start and end of action tags */
    const at_valid_first_last = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    const at_valid_first_last_array = ["A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z"];
    /** @var string Valid characters inside action tag names */
    const at_valid_mid = "ABCDEFGHIJKLMNOPQRSTUVWXYZ_-";
    const at_valid_mid_array = ["A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z","_","-"];
    /** @var string Valid character before an action tag start character (if not at start of string) */
    const at_valid_pre = " \t\n\r";
    const at_valid_pre_array = [" ","\t","\n","\r"];
    /** @var string Valid character after an action tag name (if not end of string) */
    const at_valid_post = " \t=({\n\r";
    const at_valid_post_array = [" ","\t","=","(","{","\n","\r"];
    /** @var string Number characters 0-9 */
    const at_numbers = "0123456789";
    const at_numbers_array = ["0","1","2","3","4","5","6","7","8","9"];
    /** @var string Valid whitespace characters */
    const at_whitespace = " \t\n\r";
    const at_whitespace_array = [" ","\t","\n","\r"];
    /** @var string Possible escaped characters in JSON strings */
    const at_json_allowed_escaped = "/bfnrtu";
    const at_json_allowed_escaped_array = ["/", "b","f","n","r","t","u"];

    /** @var string Regular expression to split a string into segments at @ACTION-TAGS */
    const splitter = '/(\s+@(?:[A-Z]+(?:[-_][A-Z]+)*))|([\]})])/u';


    private static $cachebuster = false;

    public static function setCacheEnabled() {
        self::$cachebuster = false;
    }

    public static function setCacheDisabled() {
        self::$cachebuster = true;
    }

    #region Action Tag Info

    const at_info = array(
        "@APPUSERNAME-APP" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app"],
            "field-types" => ["text","textarea"],
        ),
        "@BARCODE-APP" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app"],
            "field-types" => ["text","textarea"],
        ),
        "@CALCDATE" => array(
            "param" => [PARAMTYPE::ARGS],
            "scope" => ["mobile-app","survey","data-entry","calc","import"],
            "warn-when-inside" => ["@IF"],
            "field-types" => ["text","textarea"],
        ),
        "@CALCTEXT" => array(
            "param" => [PARAMTYPE::ARGS],
            "scope" => ["mobile-app","survey","data-entry","calc","import"],
            "warn-when-inside" => ["@IF"],
            "field-types" => ["text","textarea"],
        ),
        "@CHARLIMIT" => array(
            "param" => [PARAMTYPE::INTEGER, PARAMTYPE::QUOTED_STRING],
            "supports-piping" => false,
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text","textarea"],
        ),
        "@DEFAULT" => array(
            "param" => [PARAMTYPE::QUOTED_STRING],
            "supports-piping" => true,
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text","textarea"],
        ),
        "@DOWNLOAD-COUNT" => array(
            "param" => [PARAMTYPE::ARGS],
            "scope" => ["survey","data-entry"],
            "args-limit" => "same-scope-field",
            "field-types" => ["text","textarea"],
        ),
        "@FORCE-MINMAX" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["survey","data-entry","import"],
            "field-types" => ["text"],
        ),
        "@HIDDEN" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@HIDDEN-APP" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@HIDDEN-FORM" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["data-entry"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@HIDDEN-PDF" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["pdf"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@HIDDEN-SURVEY" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["survey"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@HIDEBUTTON" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@HIDECHOICE" => array(
            "param" => [PARAMTYPE::QUOTED_STRING],
            "scope" => ["survey","data-entry"],
            "field-types" => ["checkbox","radio","select","truefalse","yesno"],
        ),
        "@HIDDEN" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@IF" => array(
            "param" => [PARAMTYPE::ARGS],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@INLINE" => array(
            "param" => [PARAMTYPE::NONE,PARAMTYPE::ARGS],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["file"],
        ),
        "@LANGUAGE-CURRENT-FORM" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["data-entry"],
            "field-types" => ["radio","select","text"],
        ),
        "@LANGUAGE-CURRENT-SURVEY" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["survey"],
            "field-types" => ["radio","select","text"],
        ),
        "@LANGUAGE-FORCE" => array(
            "param" => [PARAMTYPE::QUOTED_STRING],
            "scope" => ["survey","data-entry"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
            "max-per-form" => 1,
        ),
        "@LANGUAGE-FORCE-FORM" => array(
            "param" => [PARAMTYPE::QUOTED_STRING],
            "scope" => ["data-entry"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
            "max-per-form" => 1,
        ),
        "@LANGUAGE-FORCE-SURVEY" => array(
            "param" => [PARAMTYPE::QUOTED_STRING],
            "scope" => ["survey"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
            "max-per-form" => 1,
        ),
        "@LANGUAGE-SET" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["survey","data-entry"],
            "field-types" => ["radio","select"],
        ),
        "@LATITUDE" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@LONGITUDE" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@MAXCHECKED" => array(
            "param" => [PARAMTYPE::INTEGER, PARAMTYPE::QUOTED_STRING],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox"],
        ),
        "@MAXCHOICE" => array(
            "param" => [PARAMTYPE::ARGS],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox","radio","select"],
        ),
        "@MAXCHOICE-SURVEY-COMPLETE" => array(
            "param" => [PARAMTYPE::ARGS],
            "scope" => ["survey"],
            "field-types" => ["checkbox","radio","select"],
        ),
        "@NOMISSING" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@NONEOFTHEABOVE" => array(
            "param" => [PARAMTYPE::INTEGER, PARAMTYPE::QUOTED_STRING, PARAMTYPE::UNQUOTED_STRING],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox"],
        ),
        "@NOW" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@NOW-SERVER" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@NOW-UTC" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@PASSWORDMASK" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@PLACEHOLDER" => array(
            "param" => [PARAMTYPE::QUOTED_STRING],
            "supports-piping" => true,
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text","textarea"],
        ),
        "@PREFILL" => array(
            "param" => [PARAMTYPE::QUOTED_STRING],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox","radio","select","slider","sql","text","textarea","truefalse","yesno"],
            "deprecated" => true,
            "equivalent-to" => "@SETVALUE",
        ),
        "@RANDOMORDER" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox","radio","select","truefalse","yesno"],
        ),
        "@READONLY" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@READONLY-APP" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app"],
            "field-types" => ["checkbox","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@READONLY-FORM" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["data-entry"],
            "field-types" => ["checkbox","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@READONLY-SURVEY" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["survey"],
            "field-types" => ["checkbox","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@RICHTEXT" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["survey","data-entry"],
            "field-types" => ["textarea"],
        ),
        "@SETVALUE" => array(
            "param" => [PARAMTYPE::QUOTED_STRING],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@SYNC-APP" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app"],
            "field-types" => ["file"],
        ),
        "@TODAY" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@TODAY-SERVER" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@TODAY-UTC" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@USERNAME" => array(
            "param" => [PARAMTYPE::NONE],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["radio","select","sql","text","textarea"],
        ),
        "@WORDLIMIT" => array(
            "param" => [PARAMTYPE::INTEGER, PARAMTYPE::QUOTED_STRING],
            "supports-piping" => false,
            "scope" => ["mobile-app","survey","data-entry"],
            "not-together-with" => ["@CHARLIMIT"],
            "field-types" => ["text","textarea"],
        ),
    );

    #endregion


    #region Parsing

    public static function parse_optimized_more($orig, $tags_only = false) {

        // Split input using an action tag regex ... something like /(\@[A-Z][-_A-Z0-9])*/
        // To qualify as an action tag, there must be string start or whitespace before, and whitespace or [=(] after
        // Parameters must start with = or ( and may be quoted (singe/double) or not
        // Add to array (including splitters)
        // Process, check against known tags to be smarter about params
        // @IF needs special handling ... use character parser and feed it additional chunks until its param is done


        $re_for_splitting_action_tags = '/(@(?:[A-Z]+(?:[-_][A-Z]+)*))/';


    }


    /**
     * Parses a string for action tags and returns all action tag candidates with their parameters.
     * Backslash (\) can be used as escape character ONLY inside tag parameters and only in front of quotes [",'] and 
     * closing parenthesis [)] or @ (outside tags). To code \" (literal), use \\".
     * @param string $s The string to be parsed
     * @param int $nested_start The start position of the current nesting level
     * @return array
     */
    public static function parse($orig, $nested_start = 0) {

        #region State

        /** @var string[] The (multibyte) characters of the original string */
        $chars = mb_str_split($orig);

        /** @var int Length of the original string */
        $len = count($chars);

        /** @var bool Whether outside a tag (name/params) */
        $outside_tag = true;
        /** @var bool Whether inside a tag name candidate */
        $in_tag_name = false;
        /** @var bool|"=" Whether looking for a param candidate */
        $searching_param = false;
        /** @var bool|string Whether inside a param candidate */
        $in_param = false;
        /** @var bool|string Whether inside a string literal */
        $in_string_literal = false;
        /** @var int Start position of a segment */
        $seg_start = 0;
        /** @var int End position of a segment */
        $seg_end = 0;
        /** @var string[] The current segment */
        $seg_text = [];
        /** @var string The next character */
        $next = "";
        /** @var string The previous character */
        $prev = "";
        /** @var bool Tracks whether escape mode is on or off */
        $escaped = false;
        /** @var string[] Action tag name candidate */
        $at_name = [];
        /** @var int Start position of an action tag name */
        $at_name_start = -1;
        /** @var int End position of an action tag name */
        $at_name_end = -1;
        /** @var string The quote type a param is enclosed in */
        $param_quotetype = "";
        /** @var string[] Action tag parameter candidate */
        $param_text = [];
        /** @var int Start position of an action tag parameter */
        $param_start = -1;
        /** @var int Number of open brackets (parenthesis or curly braces) */
        $param_nop = 0;
        /** @var string The current line in an ARGS-type parameter */
        $param_line = [];
        /** @var bool Whether inside a comment */
        $param_comment = false;
        /** @var string The JSON start character, [ or { */
        $param_json_start = "";
        /** @var string The expected JSON end character, ] or }, depending on start character */
        $param_json_expected_end = "";
        /** @var array Parts */
        $parts = array();
        /** @var array|null The currently worked-on tag */
        $tag = null;


        #endregion

        #region Main Loop

        // Walk through each char
        $pos = -1;
        while ($pos <= $len) {
            $pos++;
            // Get chars at current and next pos
            $c = $pos == 0 ? $chars[0] : $next;
            $next = $pos < $len - 1 ? $chars[$pos + 1] : "";

            #region Outside tag or in tag name ...
            // Check if outside tag
            if ($outside_tag) {
                // We are currently OUTSIDE of a tag segment
                // Is c the escape character?
                if ($c === self::esc) {
                    // Are we already in escape mode?
                    if ($escaped) {
                        // Yes. Thus, we add esc to the seg and simply continue after exiting escape mode
                        $seg_text[] = $c;
                        $escaped = false;
                        $prev = $c;
                        continue;
                    }
                    else {
                        // No. Let's turn on escape mode and continue
                        $escaped = true;
                        $prev = $c;
                        continue;
                    }
                }
                // Is c a tag start?
                else if ($c === self::at) {
                    // Are we in escape mode? 
                    if ($escaped) {
                        // We ignore this tag start, but we will add both esc and at to the segment, end escape, and continue
                        $seg_text[] = self::esc;
                        $seg_text[] = $c;
                        $escaped = false;
                        $prev = $c;
                        continue;
                    }
                    else {
                        // A proper tag name must start with a valid character and be at the start of the string or 
                        // there must be a whitespace/line break char in front of it 
                        if (
                            strpos(self::at_valid_first_last, $next) === false
                            || 
                            !($prev === "" || strpos(self::at_valid_pre, $prev) !== false)
                           ) {
                            // Cannot be an action tag. Add the previous segment, this non-starter as an annotated segment, and start a new segment
                            if (count($seg_text)) {
                                $parts[] = array(
                                    "type" => SEGTYPE::OTS,
                                    "start" => $seg_start + $nested_start,
                                    "end" => $pos + $nested_start - 1,
                                    "text" => join("", $seg_text),
                                    "warnings" => [],
                                );
                            }
                            $parts[] = array(
                                "type" => SEGTYPE::OTS,
                                "start" => $pos + $nested_start,
                                "end" => $pos + $nested_start,
                                "text" => $c,
                                "annotation" => "Did not qualify as Action Tag starter.",
                                "warnings" => [],
                            );
                            $seg_text = [];
                            $seg_start = $pos + 1;
                            $prev = $c;
                            continue;
                        }
                        else {
                            // This is an action tag name candidate
                            $in_tag_name = true;
                            $outside_tag = false;
                            $tag = null;
                            $at_name = [self::at];
                            $at_name_start = $pos;
                            // Let's add the previous segment to the parts
                            if (count($seg_text)) {
                                $parts[] = array(
                                    "type" => SEGTYPE::OTS,
                                    "start" => $seg_start + $nested_start,
                                    "end" => $pos + $nested_start - 1,
                                    "text" => join("", $seg_text),
                                    "annotation" => null,
                                    "warnings" => [],
                                );
                                $seg_text = [];
                            }
                            $prev = $c;
                            continue;
                        }
                    }
                }
                // Some other char
                else if ($c != "") {
                    if (count($seg_text) == 0) $seg_start = $pos;
                    $seg_text[] = $c;
                    $prev = $c;
                    continue;
                }
                // Empty char
                else {
                    // Anything in a last segment?
                    if (count($seg_text)) {
                        $parts[] = array(
                            "type" => SEGTYPE::OTS,
                            "start" => $seg_start + $nested_start,
                            "end" => $pos + $nested_start - 1,
                            "text" => join("", $seg_text),
                            "annotation" => null,
                            "warnings" => [],
                        );
                    }
                    // We are done. We are overly specific here. This could be handled by the previous else block (with condition removed)
                    break;
                }
            }
            else if ($in_tag_name) {
                // Is the character a valid after-tag-name character (or are we at the end of the string)?
                if ($c === "" || strpos(self::at_valid_post, $c) !== false || ($nested_start > 0 && $c === ",")) {
                    $at_name_end = $pos - 1;
                    $in_tag_name = false;
                    // Does the tag name end with a valid character?
                    if (strpos(self::at_valid_first_last, $prev) !== false) {
                        // Valid name, prepare tag
                        $tag = array(
                            "type" => SEGTYPE::TAG,
                            "param" => "",
                            "start" => $at_name_start + $nested_start,
                            "end" => $at_name_end + $nested_start,
                            "text" => join("", $at_name),
                        );
                    }
                    else {
                        // Not a valid name, add as OTS part
                        $parts[] = array(
                            "type" => SEGTYPE::OTS,
                            "start" => $at_name_start + $nested_start,
                            "end" => $at_name_end + $nested_start,
                            "text" => join("", $at_name),
                            "annotation" => "Did not qualify as a valid Action Tag name.",
                            "warnings" => []
                        );
                    }
                    if ($c === "") {
                        // We are done. Add the tag as a part.
                        $parts[] = $tag;
                        break;
                    }
                    else {
                        // A valid tag name has been found. A parameter could follow.
                        // Switch to parameter mode
                        $in_tag_name = false;
                        $searching_param = true;
                        // Reset name vars
                        $at_name = [];
                        $at_name_start = -1;
                        $at_name_end = -1;
                        // No continue here - we drop down to if ($in_param), as we still need to handle the current char
                    }
                }
                // Is the character a valid tag name character? (first char already vetted)
                else if ($pos == $at_name_start + 1 || strpos(self::at_valid_mid, $c) !== false) {
                    $at_name[] = $c;
                    $prev = $c;
                    continue;
                }
                // Not a valid tag name, convert to OTS and continue
                else {
                    $in_tag_name = false;
                    $outside_tag = true;
                    $parts[] = array(
                        "type" => SEGTYPE::OTS,
                        "start" => $at_name_start + $nested_start,
                        "end" => $pos + $nested_start - 1,
                        "text" => join("", $at_name),
                        "annotation" => "Did not qualify as a valid Action Tag name.",
                        "warnings" => [],
                    );
                    $at_name = [];
                    $at_name_start = -1;
                    $at_name_end = -1;
                    $prev = $c;
                    continue;
                }
            }
            #endregion

            #region Search parameter ...
            // Searching for a parameter that is separated from the tag name by an equal sign
            // (implying that only whitespace can occur before a quote char MUST follow)
            if ($searching_param === "=") {
                // Is char a quote (single or double)?
                if ($c === "'" || $c === '"') {
                    // This is the start of a string parameter
                    // End segment and mode
                    $searching_param = false;
                    $seg_end = $pos - 1;
                    // Start param mode
                    $in_param = PARAMTYPE::QUOTED_STRING;
                    $param_quotetype = $c;
                    $param_text[] = $c;
                    $param_start = $pos;
                    // Set previous and continue
                    $prev = $c;
                    continue;
                }
                // Is char a whitespace?
                else if (strpos(self::at_whitespace, $c) !== false) {
                    // Nothing special yet, add to segment and continue
                    $seg_text[] = $c;
                    $prev = $c;
                    continue;
                }
                // Is the char an opening curly brace or square bracket (potential JSON paramater)?
                else if ($c === "{" || $c === "[") {
                    // This is the start of a JSON parameter
                    // End segment and mode
                    $searching_param = false;
                    $seg_end = $pos - 1;
                    // Start param mode
                    $in_param = PARAMTYPE::JSON;
                    $param_json_start = $c;
                    $param_json_expected_end = $c === "{" ? "}" : "]";
                    $param_text[] = $c;
                    $param_start = $pos;
                    $param_nop = 1;
                    $in_string_literal = false;
                    // Set previous and continue
                    $prev = $c;
                    continue;
                }
                // Is the char a number? Number parameters can occur outside quotes
                else if (strpos(self::at_numbers, $c) !== false) {
                    // This is the start of a integer parameter
                    // End segment and mode
                    $searching_param = false;
                    $seg_end = $pos - 1;
                    // Start param mode
                    $in_param = PARAMTYPE::INTEGER;
                    $param_text[] = $c;
                    $param_start = $pos;
                    $param_nop = 0;
                    $in_string_literal = false;
                    // Set previous and continue
                    $prev = $c;
                    continue;
                }
                // Is it something else?
                else {
                    // This is the start of an unquoted string parameter
                    // End segment and mode
                    $searching_param = false;
                    $seg_end = $pos - 1;
                    // Start param mode
                    $in_param = PARAMTYPE::UNQUOTED_STRING;
                    $param_text[] = $c;
                    $param_start = $pos;
                    $param_nop = 0;
                    $in_string_literal = false;
                    // Set previous and continue
                    $prev = $c;
                    continue;
                }
            }
            // Searching for any parameter
            else if ($searching_param) {
                // Is char a whitespace/linebreak character?
                if (strpos(self::at_whitespace, $c) !== false) {
                    // Nothing special yet, add to segment (set start if first char) and continue
                    if (count($seg_text) == 0) $seg_start = $pos;
                    $seg_text[] = $c;
                    $prev = $c;
                    continue;
                }
                // Is char the equal sign?
                else if ($c === "=") {
                    // Change to equal-sign-mode, add to segment  (set start if first char) and continue
                    $searching_param = "=";
                    if (count($seg_text) == 0) $seg_start = $pos;
                    $seg_text[] = $c;
                    $prev = $c;
                    continue;
                }
                // Is the char an opening parenthesis?
                else if ($c === "(") {
                    // This is the start of a args parameter
                    // End segment and mode
                    $searching_param = false;
                    $seg_end = $pos - 1;
                    // Start param mode
                    $in_param = PARAMTYPE::ARGS;
                    $param_text[] = $c;
                    $param_start = $pos;
                    $param_nop = 1;
                    $param_line = [];
                    $in_string_literal = false;
                    // Set previous and continue
                    $prev = $c;
                    continue;
                }
                // Anything else?
                else {
                    // This means that this cannot be a parameter.
                    // Thus, add the tag to parts
                    $parts[] = $tag;
                    $tag = null;
                    // Switch mode to outside-tag-mode
                    $searching_param = false;
                    $outside_tag = true;
                    // To get the current char into the appropriate logic, we need to set the loop back one position
                    $pos -= 1;
                    $next = $c;
                    // We do not need to set the previous char
                    continue;
                }
            }
            #endregion

            #region Parameter parsing ...
            // Integer parameter
            if ($in_param == PARAMTYPE::INTEGER) {
                // End of string reached or a whitespace character
                if ($c === "" || strpos(self::at_valid_pre, $c) !== false) {
                    $tag["param"] = array(
                        "type" => PARAMTYPE::INTEGER,
                        "start" => $param_start + $nested_start,
                        "end" => $pos + $nested_start - 1,
                        "text" => $param_text,
                    );
                    $param_start = -1;
                    $param_text = [];
                    $param_quotetype = "";
                    $in_param = false;
                    $parts[] = $tag;
                    $prev = $c;
                    $outside_tag = true;
                    // Reset segment stuff
                    $seg_start = -1;
                    $seg_end = -1;
                    $seg_text = [];
                    if ($c === "") {
                        break;
                    }
                    else {
                        $pos -= 1;
                        $next = $c;
                        continue;
                    }
                }
                // Is char a number?
                if (strpos(self::at_numbers, $c) !== false) {
                    $param_text[] = $c;
                    $prev = $c;
                    continue;
                }
                // Any other character is illegal here - we switch over to the unquoted string parameter type
                else {
                    $in_param = PARAMTYPE::UNQUOTED_STRING;
                    $param_text[] = $c;
                    $prev = $c;
                    continue;
                }
            }
            // Integer parameter
            else if ($in_param == PARAMTYPE::UNQUOTED_STRING) {
                // End of string reached or a whitespace character
                if ($c === "" || strpos(self::at_valid_pre, $c) !== false) {
                    $tag["param"] = array(
                        "type" => PARAMTYPE::UNQUOTED_STRING,
                        "start" => $param_start + $nested_start,
                        "end" => $pos + $nested_start - 1,
                        "text" => join("", $param_text),
                    );
                    $param_start = -1;
                    $param_text = [];
                    $param_quotetype = "";
                    $in_param = false;
                    $parts[] = $tag;
                    $prev = $c;
                    $outside_tag = true;
                    // Reset segment stuff
                    $seg_start = -1;
                    $seg_end = -1;
                    $seg_text = [];
                    if ($c === "") {
                        break;
                    }
                    else {
                        $pos -= 1;
                        $next = $c;
                        continue;
                    }
                }
                // Any other char is allowed
                $param_text[] = $c;
                $prev = $c;
                continue;
            }
            // String parameter
            else if ($in_param == PARAMTYPE::QUOTED_STRING) {
                // End of string reached
                if ($c === "") {
                    // This is premature. We have a "broken" parameter.
                    // Add the tag
                    $parts[] = $tag;
                    // Add partial param to the segment
                    $seg_text = array_merge($seg_text, $param_text);
                    $seg_end = $pos - 1;
                    $parts[] = array(
                        "type" => SEGTYPE::OTS,
                        "start" => $seg_start + $nested_start,
                        "end" => $seg_end + $nested_start,
                        "text" => join("", $seg_text),
                        "annotation" => "Incomplete potential parameter. Missing end quote [{$param_quotetype}].",
                        "warnings" => [],
                    );
                    break;
                }
                // Char is escape character
                else if ($c === self::esc) {
                    if ($escaped) {
                        $param_text[] = $c;
                        $escaped = false;
                        $prev = $c;
                        continue;
                    }
                    else {
                        $escaped = true;
                        continue;
                    }
                }
                // Char is an end quote candidate
                else if ($c === $param_quotetype) {
                    if ($escaped) {
                        // Quote is escaped - simply add it (the escape char doesn't get added)
                        $param_text[] = $c;
                        $escaped = false;
                        $prev = $c;
                    }
                    else {
                        // End of parameter reached
                        $param_text[] = $c;
                        $tag["param"] = array(
                            "type" => PARAMTYPE::QUOTED_STRING,
                            "start" => $param_start + $nested_start,
                            "end" => $pos + $nested_start,
                            "text" => join("", $param_text),
                        );
                        $param_start = -1;
                        $param_text = [];
                        $param_quotetype = "";
                        $in_param = false;
                        $parts[] = $tag;
                        $prev = $c;
                        $outside_tag = true;
                        // Reset segment stuff
                        $seg_start = -1;
                        $seg_end = -1;
                        $seg_text = [];
                        continue;
                    }
                }
                // Any other char is part of the parameter
                else {
                    if ($escaped) {
                        // Exit of escape. The escape has no effect (but the escape char is not part of the parameter value!)
                        $escaped = false;
                    }
                    $param_text[] = $c;
                    $prev = $c;
                    continue;
                }
            }
            // JSON parameter. The idea here is to count the "open" curly braces or square brackets (outside of string literals).
            // Entering, the counter is at 1. When 0 is reached, the JSON parameter ends.
            else if ($in_param == PARAMTYPE::JSON) {
                // Is char the escape character?
                if ($c === self::esc) {
                    // Escaping in a JSON candidate is ONLY possible in a string literal! See https://www.json.org/
                    if (!$in_string_literal) {
                        // Add a warning to the tag, for the user's benefit
                        $tag["warnings"][] = array(
                            "start" => $pos + $nested_start,
                            "end" => $pos + $nested_start,
                            "text" => "Invalid JSON syntax: Escape character '\\' may only occur inside string literals.",
                        );
                        // Simply add and continue. The JSON check will catch this, too
                        $param_text[] = $c;
                        $prev = $c;
                        continue;
                    }
                    if ($escaped) {
                        $param_text[] = $c;
                        $escaped = false;
                        $prev = $c;
                        continue;
                    }
                    else {
                        // We still add the escape character, to be parsed by the JSON decoder
                        $param_text[] = $c;
                        $escaped = true;
                        $prev = $c;
                        continue;
                    }
                }
                // Is char a double quote? Note: Only double quotes are valid quotes in JSON
                else if ($c === '"') {
                    // When not in a string literal, start string literal
                    if (!$in_string_literal) {
                        $param_text[] = $c;
                        $in_string_literal = true;
                        $prev = $c;
                        continue;
                    }
                    // Is the quote escaped?
                    if ($escaped) {
                        // Add it, and the esc char and end escaped state
                        $param_text[] = self::esc;
                        $param_text[] = $c;
                        $prev = $c;
                        $escaped = false;
                        continue;
                    }
                    else {
                        // This ends the string literal
                        $in_string_literal = false;
                        $param_text[] = $c;
                        $prev = $c;
                        continue;
                    }
                }
                // From here on, there must not be an escaped state
                if ($escaped) {
                    // Check if the character following the esc char is legal. If not, we add a warning for the benefit of the user
                    // Allowed escaped characters are "/bfnrtu"; we exit out of the escape in any case
                    $escaped = false;
                    if (strpos(self::at_json_allowed_escaped, $c) === false) {
                        // Illegal character - add a warning
                        $tag["warnings"][] = array(
                            "start" => $pos + $nested_start - 1,
                            "end" => $pos + $nested_start,
                            "text" => "Invalid escape sequence. See https://json.org for a list of allowed escape sequences inside JSON strings.",
                        );
                    }
                }
                // Is char a single quote? Note: Only double quotes are valid quotes in JSON outside of a string literal
                if ($c === "'") {
                    if (!$in_string_literal) {
                        // Single quote outside of a string literal is not valid JSON. We kindly inform about this, as it might be a common mistake
                        $tag["warnings"][] = array(
                            "start" => $pos + $nested_start,
                            "end" => $pos + $nested_start,
                            "text" => "Invalid JSON syntax. Single quotes are only allowed inside strings. Did you mean to use a double quote?",
                        );
                    }
                    // In any case, we add it. The JSON check will catch this later.
                    $param_text[] = $c;
                    $prev = $c;
                    continue;
                }
                // Is char an opening [json start character]?
                else if ($c === $param_json_start) {
                    $param_text[] = $c;
                    // Increase open count, but only when not inside a string literal
                    $param_nop += ($in_string_literal ? 0 : 1);
                    $prev = $c;
                    continue;
                }
                // Is char a closing [json start character]?
                else if ($c === $param_json_expected_end) {
                    $param_text[] = $c;
                    // Decrease open bracket count, but only when not inside a string literal
                    $param_nop -= ($in_string_literal ? 0 : 1);
                    $prev = $c;
                    // Are we at the closing character?
                    if ($param_nop == 0) {
                        // The JSON parameter is complete
                        // Test for valid JSON
                        $valid_json = true;
                        $json_error = null;
                        try {
                            $_ = json_decode(join("", $param_text), true, 512, JSON_THROW_ON_ERROR);
                        }
                        catch (\Throwable $ex) {
                            $valid_json = false;
                            $json_error = $ex->getMessage();
                        }
                        $tag["param"] = array(
                            "type" => PARAMTYPE::JSON,
                            "start" => $param_start + $nested_start,
                            "end" => $pos + $nested_start,
                            "text" => join("", $param_text),
                            "valid" => $valid_json,
                            "annotation" => $json_error,
                        );
                        $param_start = -1;
                        $param_text = [];
                        $in_param = false;
                        $parts[] = $tag;
                        $outside_tag = true;
                        // Reset segment stuff
                        $seg_start = -1;
                        $seg_end = -1;
                        $seg_text = [];
                    }
                    continue;
                }
                // End of string
                else if ($c === "") {
                    // This is premature. We have a "broken" parameter.
                    // Move any warnings from tag to OTS
                    $warnings = [];
                    if (isset($tag["warnings"])) {
                        $warnings = $tag["warnings"];
                        unset($tag["warnings"]);
                    }
                    // Add the tag
                    $parts[] = $tag;
                    // Add partial param to the segment
                    $seg_text = array_merge($seg_text, $param_text);
                    $seg_end = $pos - 1;
                    $parts[] = array(
                        "type" => SEGTYPE::OTS,
                        "start" => $seg_start + $nested_start,
                        "end" => $seg_end + $nested_start,
                        "text" => join("", $seg_text),
                        "annotation" => "Incomplete or broken potential JSON parameter.",
                        "warnings" => $warnings,
                    );
                    break;
                }
                // Any other character
                else {
                    $param_text[] = $c;
                    $prev = $c;
                    continue;
                }
            }
            // Argument-style parameter. The idea here is to count the "open" parentheses (outside of string literals).
            // Entering, the counter is at 1. When 0 is reached, the ARGS parameter ends.
            // We need to consider comments that can start with # or // (and may only have whitespace before them on any line;
            // which means we must track newlines).
            else if ($in_param == PARAMTYPE::ARGS) {
                // Comment handling
                if ($c === "\n") {
                    $param_line = [];
                    $param_comment = false;
                }
                else {
                    $param_line[] = $c;
                }
                if (!$in_string_literal && !$param_comment && ($c === "#" || ($c === "/" && $prev === "/"))) {
                    // Are we inside a comment?
                    if (trim(join("", $param_line)) === ($c == "#" ? "#" : "//")) {
                        $param_comment = true;
                    }
                }
                // Is char the escape character?
                if ($c === self::esc) {
                    // Escaping in an ARGS candidate is ONLY possible in a string literal, and only for the (current) quote character
                    if (!$in_string_literal) {
                        // We only warn about this, but do not take any further action
                        $tag["warnings"][] = array(
                            "start" => $pos + $nested_start,
                            "end" => $pos + $nested_start,
                            "text" => "Invalid parameter syntax: Escape character '\\' may only occur inside string literals."
                        );
                        // Add it and continue, but do not switch into escaped mode
                        $param_text[] = $c;
                        $prev = $c;
                        continue;
                    }
                    if ($escaped) {
                        $param_text[] = $c;
                        $escaped = false;
                        $prev = $c;
                        continue;
                    }
                    else {
                        $escaped = true;
                        continue;
                    }
                }
                // Is char a single or double quote?
                else if ($c === '"' || $c == "'") {
                    // When not in a string literal, start string literal
                    if (!$in_string_literal) {
                        $param_text[] = $c;
                        $in_string_literal = $c;
                        $prev = $c;
                        continue;
                    }
                    // Is the quote the same that started the string literal?
                    if ($c === $in_string_literal) {
                        // Is the quote escaped?
                        if ($escaped) {
                            // Add it (and the esc char) and terminate escaped state
                            $param_text[] = self::esc;
                            $param_text[] = $c;
                            $prev = $c;
                            $escaped = false;
                            continue;
                        }
                        else {
                            // This ends the string literal
                            $in_string_literal = false;
                            $param_text[] = $c;
                            $prev = $c;
                            continue;
                        }
                    }
                    else {
                        // Add it
                        $param_text[] = $c;
                        $prev = $c;
                        continue;
                    }
                }
                // Is char an opening parenthesis?
                if ($c === "(") {
                    $param_text[] = $c;
                    // Increase open bracket count, but only when not inside a string literal or a comment
                    $param_nop += (($in_string_literal || $param_comment) ? 0 : 1);
                    $prev = $c;
                    continue;
                }
                // Is char a closing parenthesis?
                else if ($c === ")") {
                    $param_text[] = $c;
                    // Decrease open bracket count, but only when not inside a string literal or a comment
                    $param_nop -= (($in_string_literal || $param_comment) ? 0 : 1);
                    $prev = $c;
                    // Are we at the closing brace?
                    if ($param_nop == 0) {
                        // The ARGS parameter is complete
                        $tag["param"] = array(
                            "type" => PARAMTYPE::ARGS,
                            "start" => $param_start + $nested_start + 1,
                            "end" => $pos + $nested_start - 1,
                            "text" => substr(join("", $param_text), 1, count($param_text) - 2),
                            "valid" => null, // TODO - any sensible checks? AT-provided callback?
                            "annotation" => "",
                        );
                        $param_start = -1;
                        $param_text = [];
                        $in_param = false;
                        $parts[] = $tag;
                        $outside_tag = true;
                        // Reset segment stuff
                        $seg_start = -1;
                        $seg_end = -1;
                        $seg_text = [];
                    }
                    continue;
                }
                // End of string
                else if ($c === "") {
                    // This is premature. We have a "broken" parameter.
                    // Move any warnings from tag to OTS
                    $warnings = [];
                    if (isset($tag["warnings"])) {
                        $warnings = $tag["warnings"];
                        unset($tag["warnings"]);
                    }
                    // Add the tag
                    $parts[] = $tag;
                    // Add partial param to the segment
                    $seg_text = array_merge($seg_text, $param_text);
                    $seg_end = $pos - 1;
                    $parts[] = array(
                        "type" => SEGTYPE::OTS,
                        "start" => $seg_start + $nested_start,
                        "end" => $seg_end + $nested_start,
                        "text" => join("", $seg_text),
                        "annotation" => "Incomplete potential argument-style parameter (inside parentheses).",
                        "warnings" => $warnings,
                    );
                    break;
                }
                // Any other character
                else {
                    $param_text[] = $c;
                    $prev = $c;
                    continue;
                }
            }
            #endregion
        }

        #endregion

        #region Finishing touches and nested @IF parsing

        // Add "length", "full" string to all tags and process @IF
        foreach ($parts as &$part) {
            $start = $part["start"] - $nested_start;
            $end = (is_array($part["param"]) 
            ? ($part["param"]["end"] + ($part["param"]["type"] == PARAMTYPE::ARGS ? 1 : 0))
            : ($part["end"])
            ) - $nested_start;
            $part["full"] = join("", array_slice($chars, $start, $end - $start + 1));
            $part["length"] =  $end - $start + 1;
            if ($part["type"] == SEGTYPE::TAG) {
                if ($part["text"] == "@IF" && $part["param"]["type"] == PARAMTYPE::ARGS) {
                    // Parse @IF parts
                    $ifParts = self::splitIfContent($part["param"]["text"]);
                    if (count($ifParts) == 3) {
                        $if_then_start = $part["param"]["start"] + strpos($part["param"]["text"], $ifParts[1]);
                        $if_else_start = $part["param"]["start"] + strpos($part["param"]["text"], $ifParts[2]);
                        $part["if_condition"] = $ifParts[0];
                        $part["if_then_text"] = $ifParts[1];
                        $part["if_then"] = self::parse($part["if_then_text"], $if_then_start);
                        $part["if_else_text"] = $ifParts[2];
                        $part["if_else"] = self::parse($part["if_else_text"], $if_else_start);
                    }
                    else {
                        $part["warnings"][] = "Invalid @IF syntax.";
                    }
                }
            }
        }
        #endregion

        return $parts;
    }

    /**
     * Splits an @IF expression into its parts
     * @param string $ifBody 
     * @return string[] 0 = condition, 1 = then, 2 = else
     */
    private static function splitIfContent($ifBody) {
        $parts = [];
        $stack = [];
        $current = [];
        $insideString = false;
        $quoteChar = '';
        $chars = mb_str_split($ifBody);
        $len = count($chars);
        for ($i = 0; $i < $len; $i++) {
            $char = $chars[$i];
            // Handle quoted strings
            if (($char === '"' || $char === "'") && (!$insideString || $quoteChar === $char)) {
                $insideString = !$insideString;
                $quoteChar = $insideString ? $char : '';
            }
            if (!$insideString) {
                if ($char === '(') {
                    $stack[] = $char;
                } elseif ($char === ')') {
                    array_pop($stack);
                }
    
                if (empty($stack) && $char === ',') {
                    $parts[] = join("", $current);
                    $current = [];
                    continue;
                }
            }
            $current[] = $char;
        }
        if (count($current)) {
            $parts[] = join("", $current);
        }
        return $parts;
    }

    #endregion


    private static $cache = [];

    /**
     * Gets all action tags, optionally filtered by tags, fields, instruments, and processed for the given context (i.e., with @IF resolved)
     * @param array $context The current context (project_id, record, event_id, instrument, instance). A project_id must be provided. When record, event_id, instrument, and instance are provided, any @IF action tags will be resolved.
     * @param array|null $filter An array of filters to apply. Valid keys are:
     *  - tags: string|string[]|null The tag(s) to filter by
     *  - fields: string|string[]|null The field(s) to filter by
     *  - instruments: string|string[]|null The form(s) to filter by
     * @param bool $use_internal_if_resolver Whether to use the internal @IF resolver (Note: This may give different results than the REDCap @IF resolver for nested @IF expressions)
     * @return array An array of action tags (keyed by action tag name)
     * @throws Exception 
     */
    public static function getActionTags($context, $filter = null, $use_internal_if_resolver = false) {

        // Check to see if this search has been cached
        $arg_key = md5(json_encode(func_get_args()));
        if (!self::$cachebuster && isset(self::$cache[$arg_key])) {
            return self::$cache[$arg_key];
        }

        // Check minimal context
        if (!is_array($context) || !isset($context["project_id"]) || intval($context["project_id"]) < 1) {
            throw new Exception("Invalid context provided. Context must at least provide a project_id.");
        }
        $project_id = intval($context["project_id"]);

        // Set context (should we validate this? Probably not)
        $record = $context["record"] ?? null;
        $event_id = $context["event_id"] ?? null;
        $instrument = $context["instrument"] ?? null;
        $instance = intval($context["instance"]) ?? 1;
        $full_context = "$record" != "" && intval($event_id) != 0 && "$instrument" != "";

        // Get metadata
        $Proj = new \Project($project_id);
        $metadata = $Proj->getMetadata();

        // Set list of fields to check
        $fields = [];
        if (isset($filter["fields"])) {
            $fields = is_array($filter["fields"]) ? $filter["fields"] : [$filter["fields"]];
        }
        if (isset($filter["instruments"])) {
            $instruments = is_array($filter["instruments"]) ? $filter["instruments"] : [$filter["instruments"]];
            foreach ($instruments as $instrument) {
                $fields = array_merge($fields, array_keys(($Proj->forms[$instrument] ?? [])['fields'] ?? []));
            }
        }
        if (empty($fields)) {
            // When no filters are provided, use all fields
            $fields = array_keys($metadata);
        }
        else {
            $n_fields_provided = count($fields);
            $fields = array_intersect($fields, array_keys($metadata));
            if (count($fields) != $n_fields_provided) {
                throw new Exception("Not all fields provided could be found in the project's metadata.");
            }
        }

        // Set list of tags to check
        $tags = [];
        if (isset($filter["tags"])) {
            $tags = is_array($filter["tags"]) ? $filter["tags"] : [$filter["tags"]];
        }

        // Check hidden edit global - if it is "0", we need to change it for Form::replaceIfActionTag() to work
        $hidden_edit = isset($GLOBALS['hidden_edit']) ? $GLOBALS['hidden_edit'] : null;
        if ($hidden_edit == "0") {
            $GLOBALS["hidden_edit"] = "dummy";
        }

        // Assemble action tags
        $action_tags = array();
        /** @var int $i Action tag id (a simple counter) */
        $i = 0;
        foreach ($fields as $field) {
            $misc = $metadata[$field]["misc"] ?? "";
            if (strpos($misc, "@") === false) continue;
            $resolve_if = $full_context && strpos($misc, "@IF") !== false;
            // External (REDCap) @IF resolving
            if ($resolve_if && !$use_internal_if_resolver) {
                $misc = \Form::replaceIfActionTag($misc, $project_id, $record, $event_id, $instrument, $instance);
            }
            // Parse
            $parts = self::parse($misc);
            // Internal @IF resolving
            if ($resolve_if && $use_internal_if_resolver) {
                // Get conditions of all @IF
                $conditions = [];
                $gather = function($parts) use (&$gather, &$conditions) {
                    foreach ($parts as $part) {
                        if ($part["type"] != SEGTYPE::TAG) continue;
                        if (isset($part["if_condition"])) {
                            $conditions[] = $part["if_condition"];
                            $gather($part["if_then"]);
                            $gather($part["if_else"]);
                        }
                    }
                };
                $gather($parts);
                $conditions = join(" ", $conditions);
                $conditions = \Piping::pipeSpecialTags($conditions, $context["project_id"], $context["record"], $context["event_id"], $context["instance"], null, true, null, $context["instrument"], false, false, false, true, false, false, true); 
                $condition_fields = array_unique(array_keys(getBracketedFields($conditions, true, true, false)));
                $record_data = null;
                if (count($condition_fields) > 0) {
                    $getDataParams = [
                        'project_id' => $Proj->project_id,
                        'records' => [$context["record"]],
                        'fields' => $condition_fields,
                        'events' => [$context["event_id"]],
                        'returnEmptyEvents' => true,
                        'decimalCharacter' => '.'
                    ];
                    $record_data = \Records::getData($getDataParams);
                }
                // Resolve @IF recursively
                $resolved_parts = [];
                $resolve = function($parts) use (&$resolve, &$resolved_parts, $context, $Proj, $record_data) {
                    foreach ($parts as $part) {
                        if ($part["type"] != SEGTYPE::TAG) continue;
                        if (isset($part["if_condition"])) {
                            $result = \REDCap::evaluateLogic($part["if_condition"],  $context["project_id"], $context["record"], $context["event_id"], $context["instance"], ($Proj->isRepeatingForm($context["event_id"], $context["instrument"]) ? $context["instrument"] : ""), $context["instrument"], $record_data);
                            $resolve($result == "1" ? $part["if_then"] : $part["if_else"]);
                        }
                        else {
                            $resolved_parts[] = $part;
                        }
                    }
                };
                $resolve($parts);
                $parts = $resolved_parts;
            }
            // Helpers to add action tags
            /**
             * Recursive function to add action tags to the action_tags array
             * @param string[] $parts The action tag parts
             * @param bool $nested True if the action tag is nested inside an @IF
             */
            $add_tag_no_context = function($parts, $nested = null) use (&$add_tag_no_context, $field, &$action_tags, $tags, &$i) {
                foreach ($parts as $tag) {
                    if ($tag["type"] != SEGTYPE::TAG) continue;
                    $action_tag = $tag['text'];
                    // Tag filtering
                    if ($tags && !in_array($action_tag, $tags)) continue;
                    // Initialize the action_tag node
                    if (!array_key_exists($action_tag, $action_tags)) $action_tags[$action_tag] = [];
                    // Merge action_tag into action_tags
                    $action_tags[$action_tag][] = [
                        "id" => $i,
                        "field" => $field,
                        "params" => is_array($tag['param']) ? $tag['param']['text'] : "",
                        "nested" => $nested,
                    ];
                    $current = $i;
                    $i++;
                    if (is_array($tag["if_then"])) {
                        $add_tag_no_context($tag["if_then"], $current);
                    }
                    if (is_array($tag["if_else"])) {
                        $add_tag_no_context($tag["if_else"], $current);
                    }
                }
            };
            /**
             * Recursive function (optimized for full context) to add action tags to the action_tags array
             * @param string[] $parts The action tag parts
             */
            $add_tag_full_context = function($parts) use (&$add_tag_full_context, $field, &$action_tags, $tags, &$i) {
                foreach ($parts as $tag) {
                    if ($tag["type"] != SEGTYPE::TAG) continue;
                    $action_tag = $tag['text'];
                    // Tag filtering
                    if ($tags && !in_array($action_tag, $tags)) continue;
                    // Initialize the action_tag node
                    if (!array_key_exists($action_tag, $action_tags)) $action_tags[$action_tag] = [];
                    // Merge action_tag into action_tags
                    $action_tags[$action_tag][] = [
                        "id" => $i,
                        "field" => $field,
                        "params" => is_array($tag['param']) ? $tag['param']['text'] : "",
                        "nested" => null,
                    ];
                    $i++;
                    if (is_array($tag["if_then"])) {
                        $add_tag_full_context($tag["if_then"]);
                    }
                    if (is_array($tag["if_else"])) {
                        $add_tag_full_context($tag["if_else"]);
                    }
                }
            };
            if ($full_context && $use_internal_if_resolver) {
                $add_tag_full_context($parts);
            }
            else {
                $add_tag_no_context($parts);
            }
        }

        // Reset hidden edit
        if ($hidden_edit !== null) {
            $GLOBALS["hidden_edit"] = $hidden_edit;
        }

        // Cache this search
        self::$cache[$arg_key] = $action_tags;

        return $action_tags;
    }


    /**
     * Gets all action tags, optionally filtered by tags, fields, instruments, and processed for the given context (i.e., with @IF resolved)
     * @param array $context The current context (project_id, record, event_id, instrument, instance). A project_id must be provided. When record, event_id, instrument, and instance are provided, any @IF action tags will be resolved.
     * @param array|null $filter An array of filters to apply. Valid keys are:
     *  - tags: string|string[]|null The tag(s) to filter by
     *  - fields: string|string[]|null The field(s) to filter by
     *  - instruments: string|string[]|null The form(s) to filter by
     * @param bool $use_internal_if_resolver Whether to use the internal @IF resolver (Note: This may give different results than the REDCap @IF resolver for nested @IF expressions)
     * 
     * @return array An array of action tags (keyed by field, then action tag name)
     * @throws Exception 
     */
    public static function getActionTagsByField($context, $filter = null, $use_internal_if_resolver = false) {
        $by_actiontag = self::getActionTags($context, $filter, $use_internal_if_resolver);
        $by_field = [];
        foreach ($by_actiontag as $action_tag => $tags) {
            foreach ($tags as $tag) {
                $by_field[$tag["field"]][$action_tag][] = $tag;
            }
        }
        return $by_field;
    }

}