<?php namespace ActionTagParser;

class ActionTagParser {

    /** @var string Escape character */
    const esc = "\\";
    /** @var string Action tag start character */
    const at = "@";
    /** @var string Valid characters at the start and end of action tags */
    const at_valid_first_last = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    /** @var string Valid characters inside action tag names */
    const at_valid_mid = "ABCDEFGHIJKLMNOPQRSTUVWXYZ_-";
    /** @var string Valid character before an action tag start character (if not at start of string) */
    const at_valid_pre = " \t\n\r";
    /** @var string Valid character after an action tag name (if not end of string) */
    const at_valid_post = " \t=({\n\r";
    /** @var array Number characters 0-9 */
    const at_numbers = ["0","1","2","3","4","5","6","7","8","9"];

    const at_info = array(
        "@APPUSERNAME-APP" => array(
            "param" => ["none"],
            "scope" => ["mobile-app"],
            "field-types" => ["text","textarea"],
        ),
        "@BARCODE-APP" => array(
            "param" => ["none"],
            "scope" => ["mobile-app"],
            "field-types" => ["text","textarea"],
        ),
        "@CALCDATE" => array(
            "param" => ["args"],
            "scope" => ["mobile-app","survey","data-entry","calc","import"],
            "warn-when-inside" => ["@IF"],
            "field-types" => ["text","textarea"],
        ),
        "@CALCTEXT" => array(
            "param" => ["args"],
            "scope" => ["mobile-app","survey","data-entry","calc","import"],
            "warn-when-inside" => ["@IF"],
            "field-types" => ["text","textarea"],
        ),
        "@CHARLIMIT" => array(
            "param" => ["integer","quoted-string"],
            "supports-piping" => true,
            "scope" => ["mobile-app","survey","data-entry"],
            "not-together-with" => ["@WORDLIMIT"],
            "field-types" => ["text","textarea"],
        ),
        "@DEFAULT" => array(
            "param" => ["quoted-string"],
            "supports-piping" => true,
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text","textarea"],
        ),
        "@DOWNLOAD-COUNT" => array(
            "param" => ["args"],
            "scope" => ["survey","data-entry"],
            "args-limit" => "same-scope-field",
            "field-types" => ["text","textarea"],
        ),
        "@FORCE-MINMAX" => array(
            "param" => ["none"],
            "scope" => ["survey","data-entry","import"],
            "field-types" => ["text"],
        ),
        "@HIDDEN" => array(
            "param" => ["none"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@HIDDEN-APP" => array(
            "param" => ["none"],
            "scope" => ["mobile-app"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@HIDDEN-FORM" => array(
            "param" => ["none"],
            "scope" => ["data-entry"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@HIDDEN-PDF" => array(
            "param" => ["none"],
            "scope" => ["pdf"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@HIDDEN-SURVEY" => array(
            "param" => ["none"],
            "scope" => ["survey"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@HIDEBUTTON" => array(
            "param" => ["none"],
            "scope" => ["survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@HIDECHOICE" => array(
            "param" => ["quoted-string"],
            "scope" => ["survey","data-entry"],
            "field-types" => ["checkbox","radio","select","truefalse","yesno"],
        ),
        "@HIDDEN" => array(
            "param" => ["none"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@IF" => array(
            "param" => ["args"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@INLINE" => array(
            "param" => ["none","args"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["file"],
        ),
        "@LANGUAGE-CURRENT-FORM" => array(
            "param" => ["none"],
            "scope" => ["data-entry"],
            "field-types" => ["radio","select","text"],
        ),
        "@LANGUAGE-CURRENT-SURVEY" => array(
            "param" => ["none"],
            "scope" => ["survey"],
            "field-types" => ["radio","select","text"],
        ),
        "@LANGUAGE-FORCE" => array(
            "param" => ["quoted-string"],
            "scope" => ["survey","data-entry"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
            "max-per-form" => 1,
        ),
        "@LANGUAGE-FORCE-FORM" => array(
            "param" => ["quoted-string"],
            "scope" => ["data-entry"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
            "max-per-form" => 1,
        ),
        "@LANGUAGE-FORCE-SURVEY" => array(
            "param" => ["quoted-string"],
            "scope" => ["survey"],
            "field-types" => ["calc","checkbox","descriptive","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
            "max-per-form" => 1,
        ),
        "@LANGUAGE-SET" => array(
            "param" => ["none"],
            "scope" => ["survey","data-entry"],
            "field-types" => ["radio","select"],
        ),
        "@LATITUDE" => array(
            "param" => ["none"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@LONGITUDE" => array(
            "param" => ["none"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@MAXCHECKED" => array(
            "param" => ["integer","quoted-string"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox"],
        ),
        "@MAXCHOICE" => array(
            "param" => ["args"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox","radio","select"],
        ),
        "@MAXCHOICE-SURVEY-COMPLETE" => array(
            "param" => ["args"],
            "scope" => ["survey"],
            "field-types" => ["checkbox","radio","select"],
        ),
        "@NOMISSING" => array(
            "param" => ["none"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@NONEOFTHEABOVE" => array(
            "param" => ["integer","unquoted-string","quoted-string"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox"],
        ),
        "@NOW" => array(
            "param" => ["none"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@NOW-SERVER" => array(
            "param" => ["none"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@NOW-UTC" => array(
            "param" => ["none"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@PASSWORDMASK" => array(
            "param" => ["none"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@PLACEHOLDER" => array(
            "param" => ["quoted-string"],
            "supports-piping" => true,
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text","textarea"],
        ),
        "@PREFILL" => array(
            "param" => ["quoted-string"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox","radio","select","slider","sql","text","textarea","truefalse","yesno"],
            "deprecated" => true,
            "equivalent-to" => "@SETVALUE",
        ),
        "@RANDOMORDER" => array(
            "param" => ["none"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox","radio","select","truefalse","yesno"],
        ),
        "@READONLY" => array(
            "param" => ["none"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@READONLY-APP" => array(
            "param" => ["none"],
            "scope" => ["mobile-app"],
            "field-types" => ["checkbox","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@READONLY-FORM" => array(
            "param" => ["none"],
            "scope" => ["data-entry"],
            "field-types" => ["checkbox","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@READONLY-SURVEY" => array(
            "param" => ["none"],
            "scope" => ["survey"],
            "field-types" => ["checkbox","file","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@RICHTEXT" => array(
            "param" => ["none"],
            "scope" => ["survey","data-entry"],
            "field-types" => ["textarea"],
        ),
        "@SETVALUE" => array(
            "param" => ["quoted-string"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["checkbox","radio","select","slider","sql","text","textarea","truefalse","yesno"],
        ),
        "@SYNC-APP" => array(
            "param" => ["none"],
            "scope" => ["mobile-app"],
            "field-types" => ["file"],
        ),
        "@TODAY" => array(
            "param" => ["none"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@TODAY-SERVER" => array(
            "param" => ["none"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@TODAY-UTC" => array(
            "param" => ["none"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["text"],
        ),
        "@USERNAME" => array(
            "param" => ["none"],
            "scope" => ["mobile-app","survey","data-entry"],
            "field-types" => ["radio","select","sql","text","textarea"],
        ),
        "@WORDLIMIT" => array(
            "param" => ["integer","quoted-string"],
            "supports-piping" => true,
            "scope" => ["mobile-app","survey","data-entry"],
            "not-together-with" => ["@CHARLIMIT"],
            "field-types" => ["text","textarea"],
        ),
    );

    /**
     * Parses a string for action tags and returns all action tag candidates with their parameters.
     * Backslash (\) can be used as escape character ONLY inside tag parameters and only in front of quotes [",'] and 
     * closing parenthesis [)] or @ (outside tags). To code \" (literal), use \\".
     * @param string $s The string to be parsed
     * @return array
     */
    public static function parse($orig) {

        #region State

        /** @var int Length of the original string */
        $len = mb_strlen($orig);

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
        /** @var string The current segment */
        $seg_text = "";
        /** @var string The next character */
        $next = "";
        /** @var string The previous character */
        $prev = "";
        /** @var bool Tracks whether escape mode is on or off */
        $escaped = false;
        /** @var string Action tag name candidate */
        $at_name = "";
        /** @var int Start position of an action tag name */
        $at_name_start = -1;
        /** @var int End position of an action tag name */
        $at_name_end = -1;
        /** @var string The quote type a param is enclosed in */
        $param_quotetype = "";
        /** @var string Action tag parameter candidate */
        $param_text = "";
        /** @var int Start position of an action tag parameter */
        $param_start = -1;
        /** @var int Number of open brackets (parenthesis or curly braces) */
        $param_nop = 0;
        /** @var array Parts */
        $parts = array();
        /** @var array|null The currently worked-on tag */
        $tag = null;

        #endregion

        #region Main Loop
        // Walk through each char
        for ($pos = 0; $pos <= $len; $pos++) {
            // Get chars at current and next pos
            $c = $pos == $len ? "" : mb_substr($orig, $pos, 1);
            $next = $pos < $len - 1 ? mb_substr($orig, $pos + 1, 1) : "";

            #region Outside tag or in tag name ...
            // Check if outside tag
            if ($outside_tag) {
                // We are currently OUTSIDE of a tag segment
                // Is c the escape character?
                if ($c === self::esc) {
                    // Are we already in escape mode?
                    if ($escaped) {
                        // Yes. Thus, we add esc to the seg and simply continue after exiting escape mode
                        $seg_text .= $c;
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
                        $seg_text .= self::esc . $c;
                        $escaped = false;
                        $prev = $c;
                        continue;
                    }
                    else {
                        // A proper tag name must start with a valid character and be at the start of the string or 
                        // there must be a whitespace/line break char in front of it 
                        if (
                            (mb_strpos(self::at_valid_first_last, $next) === false) 
                            || 
                            !($prev === "" || mb_strpos(self::at_valid_pre, $prev) !== false)
                           ) {
                            // Cannot be an action tag. Add the previous segment, this non-starter as an annotated segment, and start a new segment
                            if ($seg_text != "") {
                                $parts[] = array(
                                    "type" => "ots", // outside tag segment
                                    "start" => $seg_start,
                                    "end" => $pos - 1,
                                    "text" => $seg_text,
                                    "warnings" => [],
                                );
                            }
                            $parts[] = array(
                                "type" => "ots", // outside tag segment
                                "start" => $pos,
                                "end" => $pos,
                                "text" => $c,
                                "annotation" => "Did not qualify as Action Tag starter.",
                                "warnings" => [],
                            );
                            $seg_text = "";
                            $seg_start = $pos + 1;
                            $prev = $c;
                            continue;
                        }
                        else {
                            // This is an action tag name candidate
                            $in_tag_name = true;
                            $outside_tag = false;
                            $tag = null;
                            $at_name = self::at;
                            $at_name_start = $pos;
                            // Let's add the previous segment to the parts
                            if (mb_strlen($seg_text)) {
                                $parts[] = array(
                                    "type" => "ots", // outside tag segment
                                    "start" => $seg_start,
                                    "end" => $pos - 1,
                                    "text" => $seg_text,
                                    "annotation" => null,
                                    "warnings" => [],
                                );
                                $seg_text = "";
                            }
                            $prev = $c;
                            continue;
                        }
                    }
                }
                // Some other char
                else if ($c != "") {
                    if ($seg_text == "") $seg_start = $pos;
                    $seg_text .= $c;
                    $prev = $c;
                    continue;
                }
                // Empty char
                else {
                    // Anything in a last segment?
                    if (strlen($seg_text)) {
                        $parts[] = array(
                            "type" => "ots",
                            "start" => $seg_start,
                            "end" => $pos - 1,
                            "text" => $seg_text,
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
                if ($c === "" || mb_strpos(self::at_valid_post, $c) !== false) {
                    $at_name_end = $pos - 1;
                    $in_tag_name = false;
                    // Does the tag name end with a valid character?
                    if (mb_strpos(self::at_valid_first_last, $prev) !== false) {
                        // Valid name, prepare tag
                        $tag = array(
                            "type" => "tag",
                            "param" => "",
                            "start" => $at_name_start,
                            "end" => $at_name_end,
                            "text" => $at_name,
                        );
                    }
                    else {
                        // Not a valid name, add as ots part
                        $parts[] = array(
                            "type" => "ots",
                            "start" => $at_name_start,
                            "end" => $at_name_end,
                            "text" => $at_name,
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
                        $at_name = "";
                        $at_name_start = -1;
                        $at_name_end = -1;
                        // No continue here - we drop down to if ($in_param), as we still need to handle the current char
                    }
                }
                // Is the character a valid tag name character? (first char already vetted)
                else if ($pos == $at_name_start + 1 || mb_strpos(self::at_valid_mid, $c) !== false) {
                    $at_name .= $c;
                    $prev = $c;
                    continue;
                }
                // Not a valid tag name, convert to ots and continue
                else {
                    $in_tag_name = false;
                    $outside_tag = true;
                    $parts[] = array(
                        "type" => "ots",
                        "start" => $at_name_start,
                        "end" => $pos - 1,
                        "text" => $at_name,
                        "annotation" => "Did not qualify as a valid Action Tag name.",
                        "warnings" => [],
                    );
                    $at_name = "";
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
                    $in_param = "quoted-string";
                    $param_quotetype = $c;
                    $param_text = $c;
                    $param_start = $pos;
                    // Set previous and continue
                    $prev = $c;
                    continue;
                }
                // Is char a whitespace?
                else if (mb_strpos(" \t\n\r", $c) !== false) {
                    // Nothing special yet, add to segment and continue
                    $seg_text .= $c;
                    $prev = $c;
                    continue;
                }
                // Is the char an opening curly brace (potential JSON paramater)?
                else if ($c === "{") {
                    // This is the start of a JSON parameter
                    // End segment and mode
                    $searching_param = false;
                    $seg_end = $pos - 1;
                    // Start param mode
                    $in_param = "json";
                    $param_text = $c;
                    $param_start = $pos;
                    $param_nop = 1;
                    $in_string_literal = false;
                    // Set previous and continue
                    $prev = $c;
                    continue;
                }
                // Is the char a number? Number parameters can occur outside quotes
                else if (in_array($c, self::at_numbers, true)) {
                    // This is the start of a integer parameter
                    // End segment and mode
                    $searching_param = false;
                    $seg_end = $pos - 1;
                    // Start param mode
                    $in_param = "integer";
                    $param_text = $c;
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
                    $in_param = "unquoted-string";
                    $param_text = $c;
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
                if (mb_strpos(" \t\r\n", $c) !== false) {
                    // Nothing special yet, add to segment (set start if first char) and continue
                    if ($seg_text == "") $seg_start = $pos;
                    $seg_text .= $c;
                    $prev = $c;
                    continue;
                }
                // Is char the equal sign?
                else if ($c === "=") {
                    // Change to equal-sign-mode, add to segment  (set start if first char) and continue
                    $searching_param = "=";
                    if ($seg_text == "") $seg_start = $pos;
                    $seg_text .= $c;
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
                    $in_param = "args";
                    $param_text = $c;
                    $param_start = $pos;
                    $param_nop = 1;
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
                    // We do not need to set the previous char
                    continue;
                }
            }
            #endregion
            
            #region Parameter parsing ...
            // Integer parameter
            if ($in_param == "integer") {
                // End of string reached or a whitespace character
                if ($c === "" || mb_strpos(self::at_valid_pre, $c) !== false) {
                    $tag["param"] = array(
                        "type" => "integer",
                        "start" => $param_start,
                        "end" => $pos - 1,
                        "text" => $param_text,
                    );
                    $param_start = -1;
                    $param_text = "";
                    $param_quotetype = "";
                    $in_param = false;
                    $parts[] = $tag;
                    $prev = $c;
                    $outside_tag = true;
                    // Reset segment stuff
                    $seg_start = -1;
                    $seg_end = -1;
                    $seg_text = "";
                    if ($c === "") {
                        break;
                    }
                    else {
                        $pos -= 1;
                        continue;
                    }
                }
                // Is char a number?
                if (in_array($c, self::at_numbers, true)) {
                    $param_text .= $c;
                    $prev = $c;
                    continue;
                }
                // Any other character is illegal here - we switch over to the unquoted string parameter type
                else {
                    $in_param = "unquoted-string";
                    $param_text .= $c;
                    $prev = $c;
                    continue;
                }
            }
            // Integer parameter
            else if ($in_param == "unquoted-string") {
                // End of string reached or a whitespace character
                if ($c === "" || mb_strpos(self::at_valid_pre, $c) !== false) {
                    $tag["param"] = array(
                        "type" => "unquoted-string",
                        "start" => $param_start,
                        "end" => $pos - 1,
                        "text" => $param_text,
                    );
                    $param_start = -1;
                    $param_text = "";
                    $param_quotetype = "";
                    $in_param = false;
                    $parts[] = $tag;
                    $prev = $c;
                    $outside_tag = true;
                    // Reset segment stuff
                    $seg_start = -1;
                    $seg_end = -1;
                    $seg_text = "";
                    if ($c === "") {
                        break;
                    }
                    else {
                        $pos -= 1;
                        continue;
                    }
                }
                // Any other char is allowed
                $param_text .= $c;
                $prev = $c;
                continue;
            }
            // String parameter
            else if ($in_param == "quoted-string") {
                // End of string reached
                if ($c === "") {
                    // This is premature. We have a "broken" parameter.
                    // Add the tag
                    $parts[] = $tag;
                    // Add partial param to the segment
                    $seg_text .= $param_text;
                    $seg_end = $pos - 1;
                    $parts[] = array(
                        "type" => "ots",
                        "start" => $seg_start,
                        "end" => $seg_end,
                        "text" => $seg_text,
                        "annotation" => "Incomplete potential parameter. Missing end quote [{$param_quotetype}].",
                        "warnings" => [],
                    );
                    break;
                }
                // Char is escape character
                else if ($c === self::esc) {
                    if ($escaped) {
                        $param_text .= $c;
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
                        $param_text .= $c;
                        $escaped = false;
                        $prev = $c;
                    }
                    else {
                        // End of parameter reached
                        $param_text .= $c;
                        $tag["param"] = array(
                            "type" => "quoted-string",
                            "start" => $param_start,
                            "end" => $pos,
                            "text" => $param_text,
                        );
                        $param_start = -1;
                        $param_text = "";
                        $param_quotetype = "";
                        $in_param = false;
                        $parts[] = $tag;
                        $prev = $c;
                        $outside_tag = true;
                        // Reset segment stuff
                        $seg_start = -1;
                        $seg_end = -1;
                        $seg_text = "";
                        continue;
                    }
                }
                // Any other char is part of the parameter
                else {
                    if ($escaped) {
                        // Exit of escape. The escape has no effect (but the escape char is not part of the parameter value!)
                        $escaped = false;
                    }
                    $param_text .= $c;
                    $prev = $c;
                    continue;
                }
            }
            // JSON parameter. The idea here is to count the "open" curly braces (outside of string literals).
            // Entering, the counter is at 1. When 0 is reached, the JSON parameter ends.
            else if ($in_param == "json") {
                // Is char the escape character?
                if ($c === self::esc) {
                    // Escaping in a JSON candidate is ONLY possible in a string literal! See https://www.json.org/
                    if (!$in_string_literal) {
                        // Add a warning to the tag, for the user's benefit
                        $tag["warnings"][] = array(
                            "start" => $pos,
                            "end" => $pos,
                            "text" => "Invalid JSON syntax: Escape character '\\' may only occur inside string literals.",
                        );
                        // Simply add and continue. The JSON check will catch this, too
                        $param_text .= $c;
                        $prev = $c;
                        continue;
                    }
                    if ($escaped) {
                        $param_text .= $c;
                        $escaped = false;
                        $prev = $c;
                        continue;
                    }
                    else {
                        // We still add the escape character, to be parsed by the JSON decoder
                        $param_text .= $c;
                        $escaped = true;
                        $prev = $c;
                        continue;
                    }
                }
                // Is char a double quote? Note: Only double quotes are valid quotes in JSON
                else if ($c === '"') {
                    // When not in a string literal, start string literal
                    if (!$in_string_literal) {
                        $param_text .= $c;
                        $in_string_literal = true;
                        $prev = $c;
                        continue;
                    }
                    // Is the quote escaped?
                    if ($escaped) {
                        // Add it, and the esc char and end escaped state
                        $param_text .= (self::esc . $c);
                        $prev = $c;
                        $escaped = false;
                        continue;
                    }
                    else {
                        // This ends the string literal
                        $in_string_literal = false;
                        $param_text .= $c;
                        $prev = $c;
                        continue;
                    }
                }
                // From here on, there must not be an escaped state
                if ($escaped) {
                    // Check if the character following the esc char is legal. If not, we add a warning for the benefit of the user
                    // Allowed escaped characters are "/bfnrtu"; we exit out of the escape in any case
                    $escaped = false;
                    if (mb_strpos("/bfnrtu", $c) === false) {
                        // Illegal character - add a warning
                        $tag["warnings"][] = array(
                            "start" => $pos - 1,
                            "end" => $pos,
                            "text" => "Invalid escape sequence. See https://json.org for a list of allowed escape sequences inside JSON strings.",
                        );
                    }
                }
                // Is char a single quote? Note: Only double quotes are valid quotes in JSON outside of a string literal
                if ($c === "'") {
                    if (!$in_string_literal) {
                        // Single quote outside of a string literal is not valid JSON. We kindly inform about this, as it might be a common mistake
                        $tag["warnings"][] = array(
                            "start" => $pos,
                            "end" => $pos,
                            "text" => "Invalid JSON syntax. Single quotes are only allowed inside strings. Did you mean to use a double quote?",
                        );
                    }
                    // In any case, we add it. The JSON check will catch this later.
                    $param_text .= $c;
                    $prev = $c;
                    continue;
                }
                // Is char an opening curly brace?
                else if ($c === "{") {
                    $param_text .= $c;
                    // Increase open bracket count, but only when not inside a string literal
                    $param_nop += ($in_string_literal ? 0 : 1);
                    $prev = $c;
                    continue;
                }
                // Is char a closing curly brace?
                else if ($c === "}") {
                    $param_text .= $c;
                    // Decrease open bracket count, but only when not inside a string literal
                    $param_nop -= ($in_string_literal ? 0 : 1);
                    $prev = $c;
                    // Are we at the closing brace?
                    if ($param_nop == 0) {
                        // The JSON parameter is complete
                        // Test for valid JSON
                        $valid_json = true;
                        $json_error = null;
                        try {
                            $_ = json_decode($param_text, true, 512, JSON_THROW_ON_ERROR);
                        }
                        catch (\Throwable $ex) {
                            $valid_json = false;
                            $json_error = $ex->getMessage();
                        }
                        $tag["param"] = array(
                            "type" => "json",
                            "start" => $param_start,
                            "end" => $pos,
                            "text" => $param_text,
                            "valid" => $valid_json,
                            "annotation" => $json_error,
                        );
                        $param_start = -1;
                        $param_text = "";
                        $in_param = false;
                        $parts[] = $tag;
                        $outside_tag = true;
                        // Reset segment stuff
                        $seg_start = -1;
                        $seg_end = -1;
                        $seg_text = "";
                    }
                    continue;
                }
                // End of string
                else if ($c === "") {
                    // This is premature. We have a "broken" parameter.
                    // Move any warnings from tag to ots
                    $warnings = [];
                    if (isset($tag["warnings"])) {
                        $warnings = $tag["warnings"];
                        unset($tag["warnings"]);
                    }
                    // Add the tag
                    $parts[] = $tag;
                    // Add partial param to the segment
                    $seg_text .= $param_text;
                    $seg_end = $pos - 1;
                    $parts[] = array(
                        "type" => "ots",
                        "start" => $seg_start,
                        "end" => $seg_end,
                        "text" => $seg_text,
                        "annotation" => "Incomplete potential JSON parameter.",
                        "warnings" => $warnings,
                    );
                    break;
                }
                // Any other character
                else {
                    $param_text .= $c;
                    $prev = $c;
                    continue;
                }
            }
            // Argument-style parameter. The idea here is to count the "open" parentheses (outside of string literals).
            // Entering, the counter is at 1. When 0 is reached, the args parameter ends.
            else if ($in_param == "args") {
                // Is char the escape character?
                if ($c === self::esc) {
                    // Escaping in an args candidate is ONLY possible in a string literal, and only for the (current) quote character
                    if (!$in_string_literal) {
                        // We only warn about this, but do not take any further action
                        $tag["warnings"][] = array(
                            "start" => $pos,
                            "end" => $pos,
                            "text" => "Invalid parameter syntax: Escape character '\\' may only occur inside string literals."
                        );
                        // Add it and continue, but do not switch into escaped mode
                        $param_text .= $c;
                        $prev = $c;
                        continue;
                    }
                    if ($escaped) {
                        $param_text .= $c;
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
                        $param_text .= $c;
                        $in_string_literal = $c;
                        $prev = $c;
                        continue;
                    }
                    // Is the quote the same that started the string literal?
                    if ($c === $in_string_literal) {
                        // Is the quote escaped?
                        if ($escaped) {
                            // Add it (and the esc char) and terminate escaped state
                            $param_text .= (self::esc . $c);
                            $prev = $c;
                            $escaped = false;
                            continue;
                        }
                        else {
                            // This ends the string literal
                            $in_string_literal = false;
                            $param_text .= $c;
                            $prev = $c;
                            continue;
                        }
                    }
                    else {
                        // Add it
                        $param_text .= $c;
                        $prev = $c;
                        continue;
                    }
                }
                // Is char an opening parenthesis?
                if ($c === "(") {
                    $param_text .= $c;
                    // Increase open bracket count, but only when not inside a string literal
                    $param_nop += ($in_string_literal ? 0 : 1);
                    $prev = $c;
                    continue;
                }
                // Is char a closing parenthesis?
                else if ($c === ")") {
                    $param_text .= $c;
                    // Decrease open bracket count, but only when not inside a string literal
                    $param_nop -= ($in_string_literal ? 0 : 1);
                    $prev = $c;
                    // Are we at the closing brace?
                    if ($param_nop == 0) {
                        // The args parameter is complete
                        $tag["param"] = array(
                            "type" => "args",
                            "start" => $param_start,
                            "end" => $pos,
                            "text" => $param_text,
                            "valid" => null, // TODO - any sensible checks? AT-provided callback?
                            "annotation" => "",
                        );
                        $param_start = -1;
                        $param_text = "";
                        $in_param = false;
                        $parts[] = $tag;
                        $outside_tag = true;
                        // Reset segment stuff
                        $seg_start = -1;
                        $seg_end = -1;
                        $seg_text = "";
                    }
                    continue;
                }
                // End of string
                else if ($c === "") {
                    // This is premature. We have a "broken" parameter.
                    // Move any warnings from tag to ots
                    $warnings = [];
                    if (isset($tag["warnings"])) {
                        $warnings = $tag["warnings"];
                        unset($tag["warnings"]);
                    }
                    // Add the tag
                    $parts[] = $tag;
                    // Add partial param to the segment
                    $seg_text .= $param_text;
                    $seg_end = $pos - 1;
                    $parts[] = array(
                        "type" => "ots",
                        "start" => $seg_start,
                        "end" => $seg_end,
                        "text" => $seg_text,
                        "annotation" => "Incomplete potential argument-style parameter (inside parentheses).",
                        "warnings" => $warnings,
                    );
                    break;
                }
                // Any other character
                else {
                    $param_text .= $c;
                    $prev = $c;
                    continue;
                }
            }
            #endregion
        }

        #endregion

        return array(
            "orig" => $orig,
            "parts" => $parts,
        );

    }

}