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

    /**
     * Parses a string for action tags and returns all action tag candidates with their parameters.
     * Backslash (\) can be used as escape character ONLY inside tag parameters and only in front of quotes [",'] and 
     * closing parenthesis [)] or @ (outside tags). To code \" (literal), use \\".
     * @param string $s The string to be parsed
     * @return array
     */
    public static function parse($orig) {

        // State

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
        /** @var array Parts */
        $parts = array();
        /** @var int Number of parts */
        $n_parts = 0;
        /** @var array|null The currently worked-on tag */
        $tag = null;

        // Walk through each char
        for ($pos = 0; $pos <= $len; $pos++) {
            // Get chars at current and next pos
            $c = $pos == $len ? "" : mb_substr($orig, $pos, 1);
            $next = $pos < $len - 1 ? mb_substr($orig, $pos + 1, 1) : "";

            
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
                                );
                                $n_parts += 1;
                            }
                            $parts[] = array(
                                "type" => "ots", // outside tag segment
                                "start" => $pos,
                                "end" => $pos,
                                "text" => $c,
                                "annotation" => "Did not qualify as Action Tag starter.",
                            );
                            $n_parts += 1;
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
                            $parts[] = array(
                                "type" => "ots", // outside tag segment
                                "start" => $seg_start,
                                "end" => $pos - 1,
                                "text" => $seg_text,
                            );
                            $n_parts += 1;
                            $seg_text = "";
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
                        );
                        $n_parts += 1;
                    }
                    if ($c === "") {
                        // We are done. Add the tag as a part.
                        $parts[] = $tag;
                        $n_parts += 1;
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
                    );
                    $n_parts += 1;
                    $at_name = "";
                    $at_name_start = -1;
                    $at_name_end = -1;
                    $prev = $c;
                    continue;
                }
            }
            // Searching for a parameter that is separated from the tag name by an equal sign
            // (implying that only whitespace can occur before a quote char MUST follow)
            if ($searching_param === "=") {
                // Is char a quote (single or double)?
                if ($c === "'" || $c === '"') {
                    // This is the start of a quoted parameter
                    // End segment and mode
                    $searching_param = false;
                    $seg_end = $pos - 1;
                    // Start param mode
                    $in_param = "quoted";
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
                // Is it something else?
                else {
                    // This cannot be a parameter.
                    // Thus, add the tag to parts
                    $parts[] = $tag;
                    $n_parts += 1;
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
                    // This is the start of a bracketed parameter
                    // End segment and mode
                    $searching_param = false;
                    $seg_end = $pos - 1;
                    // Start param mode
                    $in_param = "bracketed";
                    $param_text = $c;
                    $param_start = $pos;
                    // Set previous and continue
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
                    // Set previous and continue
                    $prev = $c;
                    continue;
                }
                // Anything else?
                else {
                    // This means that this cannot be a parameter.
                    // Thus, add the tag to parts
                    $parts[] = $tag;
                    $n_parts += 1;
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
            // Parameter parsing
            if ($in_param == "quoted") {
                // End of string reached
                if ($c === "") {
                    // This is premature. We have a "broken" parameter.
                    // Add to the segment
                    $seg_text .= $param_text;
                    $seg_end = $pos - 1;
                    $parts[] = array(
                        "type" => "ots",
                        "start" => $seg_start,
                        "end" => $seg_end,
                        "text" => $seg_text,
                        "annotation" => "Incomplete potential parameter. Missing end quote [{$param_quotetype}].",
                    );
                    $n_parts += 1;
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
                            "start" => $param_start,
                            "end" => $pos,
                            "text" => $param_text,
                        );
                        $param_start = -1;
                        $param_text = "";
                        $param_quotetype = "";
                        $in_param = false;
                        $parts[] = $tag;
                        $n_parts += 1;
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
            else if ($in_param == "bracketed") {
                // TODO
            }
            else if ($in_param == "json") {
                // TODO
            }
        }



        return array(
            "orig" => $orig,
            "parts" => $parts,
        );

    }

}