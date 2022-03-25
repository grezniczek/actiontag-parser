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
     * Backslash (\) can be used as escape character ONLY in front of ", ', and ) (inside tag constructs) 
     * or @ (outside tags). To code \" (literal), use \\".
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
        /** @var int Start position of a segment */
        $seg_start = 0;
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
                if ($c == self::esc) {
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
                else if ($c == self::at) {
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
                            !($prev == "" || mb_strpos(self::at_valid_pre, $prev) !== false)
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
                else {
                    $seg_text .= $c;
                    $prev = $c;
                    continue;
                }
            }
            else {
                if ($in_tag_name) {
                    // Is the character a valid after-tag-name character (or are we at the end of the string)?
                    if ($c == "" || mb_strpos(self::at_valid_post, $c) !== false) {
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
                        if ($c == "") {
                            // We are done. Add the tag as a part.
                            $parts[] = $tag;
                            $n_parts += 1;
                            break;
                        }
                        else {
                            // TODO - Check for param
                            // For now, add the tag
                            $parts[] = $tag;
                            $n_parts += 1;
                            $tag = null;
                            $at_name = "";
                            $at_name_start = -1;
                            $at_name_end = -1;
                            $in_tag_name = false;
                            $outside_tag = true; // For now
                            $prev = $c;
                            continue;
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
            }
        }



        return array(
            "orig" => $orig,
            "parts" => $parts,
        );

    }

}