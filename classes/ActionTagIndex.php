<?php

namespace ActionTagParser;

/**
 * Builds portable field, tag, and instrument indexes from caller-supplied
 * annotations. Metadata retrieval stays with the future core/EM facade.
 */
final class ActionTagIndex
{
    /**
     * @param array<string,string|array{annotation:string,instrument?:string}> $fieldAnnotations
     * @return array{by_field:array,by_tag:array,by_instrument:array,conditions:array}
     */
    public static function build(array $fieldAnnotations, array $parseOptions = []): array
    {
        $index = ['by_field' => [], 'by_tag' => [], 'by_instrument' => [], 'conditions' => []];
        foreach ($fieldAnnotations as $field => $source) {
            $annotation = is_array($source) ? ($source['annotation'] ?? '') : $source;
            $instrument = is_array($source) ? ($source['instrument'] ?? null) : null;
            if (!is_string($annotation) || strpos($annotation, '@') === false) continue;

            $parsed = ActionTagParser::parse($annotation, $parseOptions);
            if ($parsed['tags'] === []) continue;
            $index['by_field'][$field] = $parsed['tags'];
            $index['conditions'][$field] = $parsed['conditions'];
            if ($instrument !== null) $index['by_instrument'][$instrument][$field] = $parsed['tags'];
            foreach ($parsed['tags'] as $tag) {
                $index['by_tag'][$tag['name']][] = ['field' => $field, 'instrument' => $instrument, 'tag' => $tag];
            }
        }
        return $index;
    }
}
