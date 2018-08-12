<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="main.css">
    <link rel="stylesheet" href="reset.css">
    <link href="http://fonts.googleapis.com/css?family=Roboto|Roboto+Mono|Ubuntu|Fira+Mono&amp;subset=latin-ext" rel="stylesheet" type="text/css">
    <title>Document</title>
</head>

<?php

    class BlockEnum {
        const AtxHeading = 1;
        const SetextHeading = 2;
        const Blockquote = 3;
        const Codeblock = 4;
        const FencedCodeblock = 5;
        const UnorderedList = 6;
        const OrderedList = 7;
        const ThematicBreak = 8;

        const SetextHeadingStart = 9;
        const BlockquoteStart = 10;
        const CodeblockStart = 11;
        const FencedCodeblockStart = 12;
        const UnorderedListStart = 13;
        const OrderedListStart = 14;
    } 

    class Markdown {

        public static $block = array(
            "/^[ ]{0,3}\#{1,6}\s+/" => array(BlockEnum::AtxHeading, "AtxHeading", "#"),
            "/^[ ]{0,3}(?:\-\s*){3,}$/m" => array(BlockEnum::SetextHeading, "SetextHeading", "-"), #Todo
            "/^[ ]{0,3}(?:\=\s*){3,}$/m" => array(BlockEnum::SetextHeading, "SetextHeading", "="), #Todo Maybe just remove the stuff before it finds this, as this will be checked b4 ThematicBreak
            "/^[ ]{0,3}\>\s?/" => array(BlockEnum::Blockquote, "Blockquote", ">"),
            "/^(?:[ ]{4}|\t)(?!\s).+/" => array(BlockEnum::Codeblock, "Codeblock", "    "),
            "/^[ ]{0,3}\`{3}(?:\w+)?/" => array(BlockEnum::FencedCodeblock, "FencedCodeblock", "```"),
            "/^[ ]{0,3}\-\s+/" => array(BlockEnum::UnorderedList, "UnorderedList", "-"),
            "/^[ ]{0,3}\+\s+/" => array(BlockEnum::UnorderedList, "UnorderedList", "+"),
            "/^[ ]{0,3}\*\s+/" => array(BlockEnum::UnorderedList, "UnorderedList", "*"),
            "/^[ ]{0,3}[0-9]{1,9}(?:\.|\))\s+/" => array(BlockEnum::OrderedList, "Ordered_List", "/\d. |\d) /"),
            "/^[ ]{0,3}(?:\-\s*){3,}$/" => array(BlockEnum::ThematicBreak, "ThematicBreak", "-"),
            "/^[ ]{0,3}(?:\*\s*){3,}$/" => array(BlockEnum::ThematicBreak, "ThematicBreak", "*"),
            "/^[ ]{0,3}(?:\_\s*){3,}$/" => array(BlockEnum::ThematicBreak, "ThematicBreak", "_"),
        );

        private $escapeable = array(
            "\\" => array("backslash", "&#92;"),
            "`" => array("backtick", "&#96;"),
            "*" => array("asterisk", "&#42;"),
            "_" => array("underscore", "&#95;"),
            "{" => array("curly brace open", "&#123;"),
            "}" => array("curly brace close", "&#125;"),
            "[" => array("square bracket open", "&#91;"),
            "]" => array("square bracket close", "&#93;"),
            "(" => array("parentheses open", "&#40;"),
            ")" => array("parentheses close", "&#41;"),
            "#" => array("hash mark", "&#35;"),
            "+" => array("plus sign", "&#43;"),
            "-" => array("minus sign", "&#45;"),
            "." => array("dot", "&#46;"),
            "!" => array("exclamation mark", "&#33;"),
            "<" => array("less than", "&lt;"),
            ">" => array("greater than", "&gt;"),
            "|" => array("pipe", "&#124;")
        );



        private function lex(string $input) : array {
            
        }

        private function prepare(string $input ) : array {
            $input = str_replace(array("\r\n", "\r"), "\n", $input);
            if(strlen(chop($input))) {
                return explode("\n", $input);
            }
            return array(null);
        }    

        private function lexDebug(array $lines, $startLine, $startLineOffset, $endLine, $endLineOffset, int $level = 0) : array {

            $tokens = array();

            if($level === 128) {
                return $tokens; 
                # This is a safety so we can stop someone from nesting elements into each other.
                # We can have up to 256 function calls, however that is far too much for a document.
                # As Nesting more than 5 Blockquotes would already be kinda bonkers
            }

            for($lnum = $startLine; $lnum < $endLine; $lnum++) {

                $line = $lines[$lnum];

                if($lnum === $startLine) {
                    $line = substr($line, $startLineOffset);
                }
                if ($lnum === $endLine - 1) {
                    $line = substr($line, 0, $endLineOffset - $startLineOffset);
                }

                foreach (array_keys(Markdown::$block) as $i=>$key) {
                    if(preg_match($key, $line, $matches, PREG_OFFSET_CAPTURE)) {
                        $type = Markdown::$block[$key][0];

                        $function = "resolve" . Markdown::$block[$key][1];
                        if($type == BlockEnum::SetextHeading) {
                            $tokens = array_merge($tokens, $this->$function($lines, $lnum, $startLineOffset, $tokens, $level, $matches, $i, $key));                            
                            break;
                        } 
                        else if(is_callable (array($this, $function))) {
                            $tokens = array_merge($tokens, $this->$function($lines, $lnum, $startLineOffset, $level, $matches, $i, $key));
                            $lnum = $tokens[count($tokens) - 1]["Line Number"] - $startLine; #Todo Lists, Tables
                            if ($level && $this->doesInterrupt(-1, $type)) {
                                return $tokens;
                            }
                            break;
                        }
                        else {
                            echo "Couldn't resolve " . Markdown::$block[$key][1];
                        }
                    }
                }
            }
            return $tokens;
        }

        private function resolveAtxHeading($lines, $startLine, $startLineOffset, $level, $matches, $i, $key) : array {
            $tokens = array();
            $type = BlockEnum::AtxHeading;
            array_push($tokens, array("Name" => Markdown::$block[$key][1], "Type" => $type, "Line Number" => $startLine, "Match" => $matches[0][1], "Block Number" => $i, $matches[0], "Offset" => $level));
            return $tokens;
        }

        private function resolveSetextHeading($lines, $startLine, $startLineOffset, $token, $level, $matches, $i, $key) : array {
            $tokens = array();
            $type = BlockEnum::SetextHeading;
            if(count($token)) {
                $counter = $token[count($token) - 1]["Line Number"] + 1;
            }
            else {
                $counter = 0;
            }

            # Jump to the Line AFTER the last token and iterate to find any empty lines which would be a break for this kind of heading
            for($tempi = $counter; $tempi < $startLine; $tempi++) {
                if($lines[$tempi] === "") {
                    $counter = $tempi + 1;
                }
            }

            if($startLine - $counter) {
                array_push($tokens, array("Name" =>"Setext Heading Start", "Type" => BlockEnum::SetextHeadingStart, "Line Number" => $counter, "Block Number" => $i, $matches[0], "Offset" => $level));
                array_push($tokens, array("Name" => Markdown::$block[$key][1], "Type" => $type, "Line Number" => $startLine, "Match" => $matches[0][1], "Block Number" => $i, $matches[0], "Offset" => $level));
            } else if($counter === 0) {
                # Todo Check for Thematic Break (--- will produce a thematic break, but not ===)
            }

            return $tokens;
        }

        private function resolveBlockquote($lines, $startLine, $startLineOffset, $level, $matches, $i, $key) : array {
            $tokens = array();

            $counter = 0;
            $type = BlockEnum::Blockquote;

            while(($startLine + $counter) < count($lines) && $lines[$startLine + $counter] !== "") {
                $counter++;
            }
            array_push($tokens, array("Name" => "Blockquote Start", "Type" => BlockEnum::BlockquoteStart, "Line Number" => $startLine, "Match" => $matches[0][1], "Block Number" => $i, $matches[0], "Offset" => $level));
            $endLine = $startLine + $counter; # We pass "endLine + 1", so that the forLoop in lex will atleast loop once (Same Line), because 1 is not lower than 1.
            $endLineOffset = strlen($lines[$endLine - 1]);

            $tokens = array_merge($tokens, $this->lexDebug($lines, $startLine, $matches[0][1] + strlen($matches[0][0]) + $startLineOffset, $endLine, $endLineOffset, $level + 1));
            if($this->doesInterrupt($type, $tokens[count($tokens) - 1]["Type"])) { 
                $startLine = $tokens[count($tokens) - 1]["Line Number"] + 1;
                array_push($tokens, array("Name" => Markdown::$block[$key][1], "Type" => $type, "Line Number" => $tokens[count($tokens) - 1]["Line Number"], "Block Number" => $i, "Offset" => $level));
            }
            else {
                $startLine += $counter;
                array_push($tokens, array("Name" => Markdown::$block[$key][1], "Type" => $type, "Line Number" => $endLine, "Match" => $matches[0][1], "Block Number" => $i, $matches[0], "Offset" => $level));
            }

            return $tokens;
        }

        # This doesn't work right now, see todo
        private function resolveCodeblock($lines, $startLine, $startLineOffset, $level, $matches, $i, $key) : array {
            $tokens = array();

            $type = BlockEnum::Codeblock;
            
            # Todo Check for Correct Indentation
            while(($startLine + $counter) < count($lines ) && preg_match(array_keys(Markdown::block)[$i], $lines[$startLine + $counter])) {
                $counter++;
            }
            array_push($tokens, array("Name" => "Code Block Start", "Type" => BlockEnum::CodeblockStart, "Line Number" => $startLine, "Match" => $matches[0][1], "Block Number" => $i, $matches[0], "Offset" => $level));
            array_push($tokens, array("Name" => Markdown::$block[$key][1], "Type" => $type, "Line Number" => $startLine+$counter, "Match" => $matches[0][1], "Block Number" => $i, $matches[0], "Offset" => $level));
        
            return $tokens;
        }

        private function resolveFencedCodeblock($lines, $startLine, $startLineOffset, $level, $matches, $i, $key) : array {
            $tokens = array();

            $counter = 1;
            $type = BlockEnum::FencedCodeblock;
            
            while(($startLine + $counter) < count($lines ) && !preg_match(array_keys(Markdown::$block)[$i], $lines[$startLine + $counter])) {
                $counter++;
            }
            array_push($tokens, array("Name" => "Fenced Code Block Start", "Type" => BlockEnum::FencedCodeblockStart, "Line Number" => $startLine, "Block Number" => $i, $matches[0], "Offset" => $level));
            array_push($tokens, array("Name" => Markdown::$block[$key][1], "Type" => $type, "Line Number" => $startLine+$counter, "Match" => $matches[0][1], "Block Number" => $i, $matches[0], "Offset" => $level));
            $startLine += $counter;
            
            return $tokens;
        }

        # Both Lists are not working correctly right now.
        private function resolveUnorderedList($lines, $startLine, $startLineOffset, $level, $matches, $i, $key) : array {
            $tokens = array();

            $type = BlockEnum::UnorderedList;
            
            $counter = 0;
            while(($startLine + $counter) < count($lines)) {
                if(preg_match(array_keys(Markdown::$block)[$i], $lines[$startLine + $counter], $endMatch) ||  $lines[$startLine + $counter] !== "") {
                    $counter++;
                }
            }

            $endLine = count($lines);
            $endLineOffset = strlen($lines[$endLine - 1]);

            if(!empty($endMatch)) {
                $endLine = $startLine + $counter;
                $endLineOffset = $endMatch[0][0];
            }

            array_push($tokens, array("Name" => "Unordered List Start", "Type" => BlockEnum::UnorderedListStart, "Line Number" => $startLine, "Match" => $matches[0][1], "Block Number" => $i, $matches[0], "Offset" => $level));                                    
            $tokens = array_merge($tokens, $this->lexDebug($lines, $startLine, $matches[0][1] + strlen($matches[0][0]), $endLine, $endLineOffset,  $level + 1));
            array_push($tokens, array("Name" => Markdown::$block[$key][1], "Type" => $type, "Line Number" => $startLine+$counter, "Match" => $matches[0][1], "Block Number" => $i, $matches[0], "Offset" => $level));
            
            return $tokens;
        }

        private function resolveOrderedList($lines, $startLine, $startLineOffset, $level, $matches, $i, $key) : array {
            $tokens = array();

            $counter = 0;
            $type = BlockEnum::OrderedList;
            
            while(($startLine + $counter) < count($lines)) {
                if(preg_match(array_keys(Markdown::$block)[$i], $lines[$startLine + $counter], $endMatch) || $lines[$startLine + $counter] !== "") {
                    $counter++;
                }
            }

            $endLine = count($lines);
            $endLineOffset = strlen($lines[$endLine - 1]);

            if(!empty($endMatch)) {
                $endLine = $startLine + $counter;
                $endLineOffset = $endMatch[0][0];
            }

            array_push($tokens, array("Name" => "Unordered List Start", "Type" => BlockEnum::OrderedListStart, "Line Number" => $startLine, "Match" => $matches[0][1], "Block Number" => $i, $matches[0], "Offset" => $level));                                    
            $tokens = array_merge($tokens, $this->lexDebug($lines, $startLine, $matches[0][1] + strlen($matches[0][0]), $endLine, $endLineOffset, $level + 1));
            array_push($tokens, array("Name" => Markdown::$block[$key][1], "Type" => $type, "Line Number" => $startLine+$counter, "Match" => $matches[0][1], "Block Number" => $i, $matches[0], "Offset" => $level));
            
            return $tokens;
        }

        private function resolveThematicBreak($lines, $startLine, $startLineOffset, $level, $matches, $i, $key) : array {
            $tokens = array();

            $type = BlockEnum::ThematicBreak;
            
            array_push($tokens, array("Name" => Markdown::$block[$key][1], "Type" => $type, "Line Number" => $startLine, "Match" => $matches[0][1], "Block Number" => $i, $matches[0], "Offset" => $level));

            return $tokens;
        }

        # This function checks if the $followingType interrupts the $currentType, e.g.
        # > # Hello\nWorld
        # => <blockquote><h1>Hello</h1></blockquote><p> World</p> 
        # -----------------------
        # > Hello\nWorld
        # => <blockquote>Hello World</blockquote>
        private function doesInterrupt(int $currentType, int $followingType = -1) : bool {
            # Fix Lists and SetextHeadings, Right now They Count until either they hit a line only filled with a "\n" or until they hit the end of the file.
            # It should more likely work with some kind of function "checkForBreak($line)" which returns a bool, it breaks
            # on AtxHeadings, Thematic Breaks, Other Lists, New Lines, Fenced Code Blocks, Block Quotes (for now, check further blocks that stop lists)
            if($currentType == -2) { # This Signals Root Level for now. (Can prolly be removed)
                return false;
            }
            else if($followingType == BlockEnum::AtxHeading || $followingType == BlockEnum::ThematicBreak || $followingType == BlockEnum::FencedCodeblock || $followingType == BlockEnum::Blockquote)
                return true;
            if(($currentType == BlockEnum::OrderedList && $followingType == BlockEnum::UnorderedList) || $currentType == BlockEnum::UnorderedList && $followingType == BlockEnum::OrderedList)
                return true;
            return false;
        }

        private function isSingleLine(int $type) : bool {
            if ($type == BlockEnum::AtxHeading || $type == BlockEnum::ThematicBreak)
                return true;
            return false;
        }

        private function escape(string $input) : string {
            foreach ($escapeable.keys($lines, $lnum + $startLine, $startLineOffset, $level, $matches, $tokens, $i, $key) as $key) {
                $input = $input.replace($key, $escapeable[$key][1]);
            }
            return $input;
        }


        # Test Behavior of
        # > Hello
        # World
        # > > How are you?

        # =>

        # Expected:
        # <blockquote>Hello\nWorld\n<blockquote><blockquote>How are you?</blockquote></blockquote></blockquote>

        public function parse(string $input) {
            $input = "# Heading\n```\n\nTestCode();\n\nBNlaaaa\n```\n\nHallo\nWelt\n===\n\n> # Hello World Does\n This Work?\n> > > Heeelloo\n"; 
            # Fix Lists and SetextHeadings, Right now They Count until either they hit a line only filled with a "\n" or until they hit the end of the file.
            # It should more likely work with some kind of function "checkForBreak($line)" which returns a bool, it breaks
            # on AtxHeadings, Thematic Breaks, Other Lists, New Lines, Fenced Code Blocks, Block Quotes (for now, check further blocks that stop lists)
            $inputArray = $this->prepare($input);
            $length = count($inputArray) - 1;

            $tokens = $this->lexDebug($inputArray, 0, 0, $length + 1, strlen($inputArray[$length]));

            echo "<pre><code>";
            foreach ($tokens as $token) {
                $ws = "";
                for ($i = 0; $i < $token["Offset"]; $i++)
                    $ws .= "\t";
                echo $ws . "- " . $token["Name"] . "\n";
            }
            echo "</code></pre>";
            echo "</hr>";
            var_dump($tokens);
        }

        private function inline_search(string $input) {
            $input = $this->replace($input, "<strong>", "</strong>", "/(\*|\_){2}(.+)(\*|\_){2}/", 2);
            $input = $this->replace($input, "<em>", "</em>", "/(\*|\_){1}(.+)(\*|\_){1}/", 2);
            $input = $this->replace($input, "<code>", "</code>", "/\`{1}(.+)\`{1}/", 1);
            $input = $this->replace($input, "<del>", "</del>", "/\~{2}(.+)\~{2}/", 1);
            $input = $this->replace($input, "<sub>", "</sub>", "/\~{1}(.+)\~{1}/", 1);
            $input = $this->replace($input, "<sup>", "</sup>", "/\^{1}(.+)\^{1}/", 1);
            if (preg_match("/\[(.+)\]\(([^ ]+)(\s\"(.+)\")?\)/", $input, $matches) > 0) {
                $anchor_content = "a href='$matches[2]'";
                if(isset($matches[4])){
                    $anchor_content .= " title='$matches[4]'";
                }
                $input = preg_replace("/\[(.+)\]\(([^ ]+)(\s\"(.+)\")?\)/", "<" . $anchor_content . ">" . $matches[1] . "</a>", $input);
            }

            return $input;
        }

    }

    if(isset($_POST["input"])) {
        $in = $_POST["input"];

        $md = new Markdown();
        echo $md->parse($in);
    }
?>

<body>
    <form method="POST">
        <textarea name="input"></textarea>
        <input type="submit" value="submite">
    </form>
</body>
</html>
