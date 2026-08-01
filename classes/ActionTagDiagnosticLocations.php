<?php

namespace ActionTagParser;

/** Adds Unicode-aware display locations to an already diagnostic parse result. */
final class ActionTagDiagnosticLocations
{
    public static function enrich(string $annotation, array $result): array
    {
        $lineStarts = self::lineStarts($annotation);
        foreach (['diagnostics', 'tags', 'nodes'] as $key) {
            if (isset($result[$key])) $result[$key] = self::enrichList($annotation, $result[$key], $lineStarts);
        }
        if (isset($result['conditions'])) {
            foreach ($result['conditions'] as $id => $condition) {
                $result['conditions'][$id] = self::enrichItem($annotation, $condition, $lineStarts);
            }
        }
        return $result;
    }

    /** @return list<int> */
    private static function lineStarts(string $annotation): array
    {
        $starts = [0];
        $length = strlen($annotation);
        for ($i = 0; $i < $length; $i++) {
            if ($annotation[$i] === "\r") {
                if ($i + 1 < $length && $annotation[$i + 1] === "\n") $i++;
                $starts[] = $i + 1;
            } elseif ($annotation[$i] === "\n") {
                $starts[] = $i + 1;
            }
        }
        return $starts;
    }

    /** @param list<array> $items @param list<int> $lineStarts */
    private static function enrichList(string $annotation, array $items, array $lineStarts): array
    {
        foreach ($items as $key => $item) $items[$key] = self::enrichItem($annotation, $item, $lineStarts);
        return $items;
    }

    /** @param list<int> $lineStarts */
    private static function enrichItem(string $annotation, array $item, array $lineStarts): array
    {
        if (isset($item['start'])) {
            ['line' => $item['start_line'], 'column' => $item['start_column'], 'byte_column' => $item['start_byte_column']] = self::location($annotation, $item['start'], $lineStarts);
        }
        if (isset($item['end'])) {
            ['line' => $item['end_line'], 'column' => $item['end_column'], 'byte_column' => $item['end_byte_column']] = self::location($annotation, $item['end'], $lineStarts);
        }
        foreach (['then', 'else'] as $branch) {
            if (isset($item[$branch])) $item[$branch] = self::enrichList($annotation, $item[$branch], $lineStarts);
        }
        return $item;
    }

    /** @param list<int> $lineStarts @return array{line:int,column:int,byte_column:int} */
    private static function location(string $annotation, int $offset, array $lineStarts): array
    {
        $low = 0;
        $high = count($lineStarts) - 1;
        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);
            if ($lineStarts[$middle] <= $offset) $low = $middle;
            else $high = $middle - 1;
        }
        $lineStart = $lineStarts[$low];
        $prefix = substr($annotation, $lineStart, $offset - $lineStart);
        $column = function_exists('mb_strlen') ? mb_strlen($prefix, 'UTF-8') + 1 : strlen($prefix) + 1;
        return ['line' => $low + 1, 'column' => $column, 'byte_column' => $offset - $lineStart + 1];
    }
}
