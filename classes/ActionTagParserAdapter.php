<?php

namespace DE\RUB\ActionTagParserExternalModule;

use ActionTagParser\ActionTagParser;

/**
 * Compatibility-shaped view over the pure parser for incremental EM caller
 * migration. It deliberately does not evaluate conditional references.
 */
final class ActionTagParserAdapter
{
    /**
     * @return list<array{actiontag:string,params:string,match:string,start:int,end:int,conditional:list<array{id:int,negated:bool}>}>
     */
    public static function parseActionTags(string $annotation): array
    {
        $result = ActionTagParser::parse($annotation);
        $tags = [];
        foreach ($result['tags'] as $tag) {
            $tags[] = [
                'actiontag' => $tag['name'],
                'params' => $tag['parameter']['raw'] ?? '',
                'match' => $tag['raw'],
                'start' => $tag['start'],
                'end' => $tag['end'],
                'conditional' => $tag['conditional'],
            ];
        }
        return $tags;
    }
}
