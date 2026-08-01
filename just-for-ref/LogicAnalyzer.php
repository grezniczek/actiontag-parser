<?php

namespace Just_For_Reference;

// Test logic:

// // Comment
// non existing([int1]>(2) or [int2]< 7) * (3 + 7)*([int1] - 4);
// datediff([date1], 'today', 'd');
// if([int1] <= 3, 2^4, 100);
// 5 * [event-name][int1][2] - max(7, [int2]*2) + 7.2e+3; // Comment
// [is-form];
// [checkbox1(1):value] * 7 + [aggregate-sum:int1, int2:record-name] + var(3)

class LogicAnaylzer {

    /** @var string Escape character */
    const esc = "\\";

    /** @var string Quotes */
    const quotes = "\"'";

    /** @var string Operators */
    const operators = "+-*/^<>=!";

    /** 
     * @var string Separator - Used to separate logic parts  
     * Only the last part will return a value.  
     * The other parts will be stored as values numbered sequentially (from 1) and 
     * can be accessed in later parts with the help of the var() function
    */
    const logic_separator = ";";

    /** @var string Arguments separator - required between function arguments */
    const args_separator = ",";

    /** @var string Numbers */
    const numbers = "0123456789";

    /** @var string Exponents */
    const e = "eE";

    /** @var string Whitespace (will be ignored) */
    const whitespace = " \t\n\r";
    

    public static function parse($orig) {

        #region State

        /** @var int Length of the original string */
        $len = mb_strlen($orig);
        /** @var string The next character */
        $next = "";
        /** @var int The current position */
        $pos = 0;


        /** @var string Whether inside a quoted string; holds the type of quote (" or ') */
        $in_quoted_string = "";
        /** @var bool Whether inside a square bracket */
        $in_square_brackets = false;

        /** @var string The current sequence */
        $seq = "";

        /** @var int The start of the current sequence */
        $seq_start = 0;

        /** @var Array The parts */
        $parts = [];
        /** @var Array The current part */
        $part = [];

        /** @var string The (putative) type of the current sequence */
        $type = "start";

        /** @var int[] Open parentheses (number) */
        $parens_open = [];

        /** @var int Number of parentheses opened */
        $parens_opened = 0;

        /** @var int Number of logic separators */
        $n_logic_separators = 0;

        $in_comment = false;

        #endregion

        #region Main Loop
        // Walk through each char
        for ($pos = 0; $pos <= $len; $pos++) {
            // Get chars at current and next pos
            $c = $pos == $len ? "" : mb_substr($orig, $pos, 1);
            $next = $pos < $len - 1 ? mb_substr($orig, $pos + 1, 1) : "";

            if ($in_quoted_string == "" && !$in_comment && $c == "/" && $next == "/") {
                $in_comment = true;
                if ($seq != "") {
                    $parts[] = [
                        "type" => $type,
                        "value" => $seq,
                        "start" => $seq_start,
                        "end" => $pos,
                    ];
                }
                $seq = "";
                $seq_start = $pos + 1;
                $pos++;
                continue;
            }
            if ($in_comment) {
                if ($c == "\n") {
                    $in_comment = false;
                    if ($seq != "") {
                        $parts[] = [
                            "type" => "comment",
                            "value" => trim($seq),
                            "start" => $seq_start,
                            "end" => $pos,
                        ];
                        $seq = "";
                        $seq_start = $pos + 1;
                        $type = "start";
                        continue;
                    }
                }
                else {
                    $seq .= $c;
                    continue;
                }
            } 

            #region Quoted Strings
            if ($in_quoted_string != "") {
                // Inside a quoted string everything is added to the string unless it's the non-escaped quote that started the string
                if ($c == $in_quoted_string || ($in_square_brackets && $c == "]")) {
                    // Terminate the current sequence, but only when it's an acutal quoted string
                    // (and not a string-like parameter from a square bracket)
                    if (strpos(self::quotes, $c) !== false) {
                        $parts[] = [
                            "type" => $type,
                            "value" => $seq,
                            "quote" => $in_quoted_string,
                            "start" => $seq_start,
                            "end" => $pos,
                        ];
                        $in_quoted_string = "";
                        $seq = "";
                        $seq_start = $pos + 1;
                        continue;
                    }
                } 
                else if ($c == self::esc && $next == $in_quoted_string) {
                    $seq .= $in_quoted_string;
                    $pos++;
                    continue;
                } 
                else if ($c == self::esc && $next == self::esc) {
                    $seq .= self::esc;
                    $pos++;
                    continue;
                }
                else {
                    $seq .= $c;
                    continue;
                }
            }
            else {
                if (strpos(self::quotes, $c) !== false) {
                    // Start a new string
                    $in_quoted_string = $c;
                    if ($seq != "") {
                        $parts[] = [
                            "type" => $type,
                            "value" => $seq,
                            "start" => $seq_start,
                            "end" => $pos,
                        ];
                        $seq = "";
                        $seq_start = $pos + 1;
                    }
                    $type = "string";
                    continue;
                }
            }
            #endregion

            #region Square bracket (event, variable, smart variable)
            if ($in_square_brackets) {
                if ($c == "(" && $type != "sq-param") { 
                    $parts[] = [
                        "type" => $type,
                        "value" => $seq,
                        "start" => $seq_start,
                        "end" => $pos,
                    ];
                    $seq = "";
                    $seq_start = $pos + 1;
                    $type = "sq-code";
                    continue;
                }
                else if ($c == ")" && $type == "sq-code") {
                    if (!(empty($seq) && $type == "sq-code")) {
                        $parts[] = [
                            "type" => $type,
                            "value" => $seq,
                            "start" => $seq_start,
                            "end" => $pos,
                        ];
                    }
                    $seq = "";
                    $seq_start = $pos + 1;
                    continue;
                }
                else if ($c == "]") {
                    $parts[] = [
                        "type" => $type." sq-end",
                        "value" => $seq,
                        "start" => $seq_start,
                        "end" => $pos,
                    ];
                    $seq = "";
                    $seq_start = $pos + 1;
                    $in_square_brackets = false;
                    if ($in_quoted_string == ":") { 
                        $in_quoted_string = "";
                    }
                    continue;
                }
                else if ($c == ":") {
                    $parts[] = [
                        "type" => $type,
                        "value" => $seq,
                        "start" => $seq_start,
                        "end" => $pos,
                    ];
                    $seq = "";
                    $seq_start = $pos + 1;
                    $type = "sq-param";
                    $in_quoted_string = ":"; // Treated as a string
                    continue;
                }
                else {
                    $seq .= $c;
                    continue;
                }
            }
            else {
                if ($c == "[") {
                    $in_square_brackets = true;
                    if ($seq != "") {
                        $parts[] = [
                            "type" => $type,
                            "value" => $seq,
                            "start" => $seq_start,
                            "end" => $pos,
                        ];
                        $seq = "";
                        $seq_start = $pos + 1;
                    }
                    $type = "sq-start";
                    continue;
                }
            }
            #endregion

            #region Parentheses (open, close)
            if ($c == "(") {
                if ($seq != "") {
                    $parts[] = [
                        "type" => $type,
                        "value" => $seq,
                        "start" => $seq_start,
                        "end" => $pos - 1,
                    ];
                }
                $parens_opened++;
                array_push($parens_open, $parens_opened);
                $parts[] = [
                    "type" => "parens-open",
                    "value" => "(",
                    "start" => $pos,
                    "end" => $pos,
                    "num" => $parens_opened,
                ];
                $seq = "";
                $seq_start = $pos + 1;
                $type = "start";
                continue;
            }
            if ($c == ")") {
                if ($seq != "") {
                    $parts[] = [
                        "type" => $type,
                        "value" => $seq,
                        "start" => $seq_start,
                        "end" => $pos - 1,
                    ];
                }
                $part = [
                    "type" => "parens-close",
                    "value" => ")",
                    "start" => $pos,
                    "end" => $pos,
                ];
                if (count($parens_open) > 0) {
                    $part["num"] = array_pop($parens_open);
                }
                else {
                    $part["type"] .= " error";
                    $part["num"] = 0;
                    $part["messages"] = ["No matching openening parenthesis!"];
                }
                $parts[] = $part;
                $seq = "";
                $seq_start = $pos + 1;
                $type = "start";
                continue;
            }
            #endregion

            #region Spaces, New Lines - these are ignored, but terminate the sequence
            if (strpos(self::whitespace, $c) !== false) {
                if ($seq != "") {
                    $parts[] = [
                        "type" => $type,
                        "value" => $seq,
                        "start" => $seq_start,
                        "end" => $pos - 1,
                    ];
                }
                if ($c == "\n") {
                    $parts[] = [
                        "type" => "newline",
                        "value" => "",
                        "start" => $pos,
                        "end" => $pos,
                    ];
                }
                $seq = "";
                $seq_start = $pos + 1;
                $type = "start";
                continue;
            }
            #endregion

            #region Operators (some are composite)
            if (strpos(self::operators, $c) !== false) {
                if ($seq != "") {
                    $parts[] = [
                        "type" => $type,
                        "value" => $seq,
                        "start" => $seq_start,
                        "end" => $pos - 1,
                    ];
                }
                if ($c == "<") {
                    if ($next == ">") {
                        $parts[] = [
                            "type" => "operator",
                            "value" => "<>",
                            "start" => $pos,
                            "end" => $pos + 1,
                            "op" => "neq",
                        ];
                        $pos++;
                    }
                    else if ($next == "=") {
                        $parts[] = [
                            "type" => "operator",
                            "value" => "<=",
                            "start" => $pos,
                            "end" => $pos + 1,
                            "op" => "lte",
                        ];
                        $pos++;
                    }
                    else {
                        $parts[] = [
                            "type" => "operator",
                            "value" => "<",
                            "start" => $pos,
                            "end" => $pos,
                            "op" => "lt",
                        ];
                    }
                }
                else if ($c == ">") {
                    if ($next == "=") {
                        $parts[] = [
                            "type" => "operator",
                            "value" => ">=",
                            "start" => $pos,
                            "end" => $pos + 1,
                            "op" => "gte",
                        ];
                        $pos++;
                    }
                    else {
                        $parts[] = [
                            "type" => "operator",
                            "value" => ">",
                            "start" => $pos,
                            "end" => $pos,
                            "op" => "gt",
                        ];
                    }
                }
                else if ($c == "!") {
                    if ($next == "=") {
                        $parts[] = [
                            "type" => "operator",
                            "value" => "!=",
                            "start" => $pos,
                            "end" => $pos + 1,
                            "op" => "eq",
                        ];
                        $pos++;
                    }
                    else {
                        $parts[] = [
                            "type" => "operator",
                            "value" => "!",
                            "start" => $pos,
                            "end" => $pos,
                            "op" => "not",
                        ];
                    }
                }
                else {
                    $parts[] = [
                        "type" => "operator",
                        "value" => $c,
                        "start" => $pos,
                        "end" => $pos,
                        "op" => $c,
                    ];
                }
                $seq = "";
                $seq_start = $pos + 1;
                $type = "start";
                continue;
            }
            #endregion

            #region Logic Separator
            if ($c == self::logic_separator) {
                if ($seq != "") {
                    $parts[] = [
                        "type" => $type,
                        "value" => $seq,
                        "start" => $seq_start,
                        "end" => $pos - 1,
                    ];
                }
                $parts[] = [
                    "type" => "logic-separator",
                    "value" => self::logic_separator,
                    "start" => $pos,
                    "end" => $pos,
                    "num" => ++$n_logic_separators,
                ];
                $seq = "";
                $seq_start = $pos + 1;
                $type = "start";
                continue;
            }
            #endregion

            #region Argument Separator
            if ($c == self::args_separator) {
                if ($seq != "") {
                    $parts[] = [
                        "type" => $type,
                        "value" => $seq,
                        "start" => $seq_start,
                        "end" => $pos - 1,
                    ];
                }
                $parts[] = [
                    "type" => "arg-separator",
                    "value" => self::args_separator,
                    "start" => $pos,
                    "end" => $pos,
                ];
                $seq = "";
                $seq_start = $pos + 1;
                $type = "start";
                continue;
            }
            #endregion

            #region Number
            if (strpos(self::numbers, $c) !== false) {
                if ($type == "start") {
                    if ($seq != "") {
                        $parts[] = [
                            "type" => $type,
                            "value" => $seq,
                            "start" => $seq_start,
                            "end" => $pos - 1,
                        ];
                        $seq = "";
                        $seq_start = $pos;
                    }
                    $type = "number";
                } 
                $seq .= $c;
                continue;
            }
            if ($type == "number" && strpos(self::e, $c) !== false) {
                if (strpos(self::numbers."+-", $next) !== false) {
                    $seq .= $c.$next;
                    $pos++;
                    continue;
                }
            }
            if ($c == ".") {
                if ($type == "number" && strpos(self::numbers, $next) !== false) {
                    $seq .= $c;
                    continue;
                }
                // There can never be an isolated point
                else {
                    if ($seq != "") {
                        $parts[] = [
                            "type" => "number",
                            "value" => $seq,
                            "start" => $seq_start,
                            "end" => $pos - 1,
                        ];
                    }
                    $parts[] = [
                        "type" => "error",
                        "value" => ".",
                        "start" => $pos,
                        "end" => $pos,
                    ];
                    $seq = "";
                    $seq_start = $pos + 1;
                    $type = "start";
                    continue;
                }
            }
            #endregion

            // If we get here, it must be a function
            if ($type == "start") {
                $type = "function";
            }
            $seq .= $c;

        } // for
        #endregion

        // There should not be any open strings, square brackets, or parentheses

        return array(
            "orig" => $orig,
            "parts" => $parts,
        );

    }


    public static function toHtml($parts) {
        $html = "<div class='logic-part'>";
        $add_after_next = "";

        for ($i = 0; $i < count($parts); $i++) {
            $part = $parts[$i];
            $next = $parts[$i+1] ?? null;
            $attr = [
                "class" => $part["type"],
            ];
            if (isset($part["messages"])) {
                $attr["title"] = js_escape2(join("\n", $part["messages"]));
            }
            $val = htmlentities($part["value"]);
            if ($part["type"] == "string") {
                $val = $part["quote"].$val.$part["quote"];
            }
            if ($part["type"] == "operator") {
                switch ($part["op"]) {
                    case "+":
                        $val = "<i class=\"fa-solid fa-plus\"></i>";
                        break;
                    case "-":
                        $val = "<i class=\"fa-solid fa-minus\"></i>";
                        break;
                    case "*":
                        $val = "<i class=\"fa-solid fa-times\"></i>";
                        break;
                    case "^":
                        $val = "<i class=\"fa-solid fa-chevron-up\"></i>";
                        break;
                    case "/":
                        $val = "<i class=\"fa-solid fa-divide\"></i>";
                        break;
                    case "lt": 
                        $val = "<i class=\"fa-solid fa-less-than\"></i>";
                        break;
                    case "lte": 
                        $val = "<i class=\"fa-solid fa-less-than-equal\"></i>";
                        break;
                    case "gt": 
                        $val = "<i class=\"fa-solid fa-greater-than\"></i>";
                        break;
                    case "gte":
                        $val = "<i class=\"fa-solid fa-greater-than-equal\"></i>";
                        break;
                    case "eq": 
                        $val = "<i class=\"fa-solid fa-equals\"></i>";
                        break;
                    case "ne": 
                        $val = "<i class=\"fa-solid fa-not-equal\"></i>";
                        break;
                    case "and": 
                        $val = "<i class=\"fa-solid fa-and\"></i>";
                        break;
                    case "or": 
                        $val = "<i class=\"fa-solid fa-or\"></i>";
                        break;
                    case "not": 
                        $val = "<i class=\"fa-solid fa-not\"></i>";
                        break;
                    default:
                        $val = $part["op"];
                        break;
                }
            }
            $html .= \RCView::span($attr, $val);
            if (($part["type"] == "comment" || $part["type"] == "newline") && $add_after_next == "") {
                $html .= "<br>";
            }
            $html .= $add_after_next;
            $add_after_next = "";
            if ($part["type"] == "logic-separator") {
                $sep = "<span class='logic-part-num'><i class=\"fa-solid fa-arrow-right-long\"></i> {$part["num"]}</span></div><div class='logic-part'>";
                if ($next && $next["type"] == "comment") {
                    $add_after_next = $sep;
                } else {
                    $html .= $sep;
                }
                if ($next && $next["type"] == "newline") {
                    $i++; // Skip
                }
            }
        }
        $html .= "</div>";
        return str_replace("<div class='logic-part'></div>", "", $html);
    }
}
