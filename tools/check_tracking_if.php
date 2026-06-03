<?php
$path = 'c:/xampp/htdocs/SIMAset/tracking_aset.php';
$s = file_get_contents($path);
$tokens = token_get_all($s);
$stack = [];
$line = 1;
$open = 0; $close = 0;
foreach ($tokens as $tok) {
    if (is_array($tok)) {
        $line = $tok[2];
        $text = $tok[1];
        if ($tok[0] === T_OPEN_TAG || $tok[0] === T_OPEN_TAG_WITH_ECHO) $open++;
        if (preg_match('/\bif\s*\([^)]*\)\s*:\s*$/m', $text)) {
            $stack[] = ['line' => $line, 'code' => trim($text)];
        }
        if (strpos($text, 'endif;') !== false) {
            if (count($stack) > 0) array_pop($stack);
            else echo "Found endif; without matching if at line $line\n";
        }
    } else {
        // simple token
        if ($tok === '?>') $close++;
    }
}
if (count($stack) > 0) {
    echo "Unclosed if(: blocks:\n";
    foreach ($stack as $s) echo " - line {$s['line']}: {$s['code']}\n";
} else echo "No unclosed alternative-if blocks detected\n";
echo "PHP open tags: $open, close tags: $close\n";
// print last 10 tokens for inspection
$last = array_slice($tokens, -10);
echo "Last tokens:\n";
foreach($last as $t){
    if(is_array($t)) echo token_name($t[0])." => " . substr($t[1],0,80) . " (line {$t[2]})\n";
    else echo "STR => $t\n";
}
