<?php

require_once __DIR__ . '/../classes/ActionTagParser.php';

use ActionTagParser\ActionTagParser;

$scenarios = [
    'ordinary_text' => static fn (int $n): string => str_repeat('ordinary annotation text ', $n),
    'independent_tags' => static fn (int $n): string => str_repeat('@HIDDEN @DEFAULT=alpha ', $n),
    'json_parameters' => static fn (int $n): string => str_repeat('@TAG={"choices":[1,2,{"x":"comma, quote \\""}]} ', $n),
    'nested_if' => static function (int $n): string {
        $annotation = '@HIDDEN';
        for ($i = 0; $i < $n; $i++) $annotation = "@IF([field_$i] = '1', $annotation, @READONLY)";
        return $annotation;
    },
];

foreach ($scenarios as $name => $makeInput) {
    foreach ([10, 100, 500] as $size) {
        $input = $makeInput($size);
        $start = hrtime(true);
        $result = ActionTagParser::parse($input);
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        printf("%-18s size=%-4d bytes=%-7d tags=%-4d time=%8.3f ms\n", $name, $size, strlen($input), count($result['tags']), $elapsedMs);
    }
}
