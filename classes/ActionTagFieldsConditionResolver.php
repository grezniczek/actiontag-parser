<?php

namespace ActionTagParser;

/**
 * Field-scoped REDCap runtime resolver.
 *
 * Scope is chosen by the caller: an arbitrary field list, one instrument, or
 * every field in a project. Parsing, data preloading, and condition evaluation
 * are then batched only for that scope.
 */
final class ActionTagFieldsConditionResolver
{
    /**
     * @param list<string> $fieldNames Field names in the desired resolution scope.
     * @param null|callable(string,array,int):mixed $evaluator
     * @return array<string,array{conditions:array<int,array>,tags:list<array>}>
     */
    public static function resolve(array $fieldNames, array $context, ?callable $evaluator = null): array
    {
        return ActionTagProjectConditionResolver::resolveMany(
            ActionTagFieldsParser::parse($fieldNames, $context),
            $context,
            $evaluator
        );
    }

    /**
     * @param null|callable(string,array,int):mixed $evaluator
     * @return array<string,array{conditions:array<int,array>,tags:list<array>}>
     */
    public static function resolveInstrument(string $instrument, array $context, ?callable $evaluator = null): array
    {
        $context['instrument'] ??= $instrument;
        return ActionTagProjectConditionResolver::resolveMany(
            ActionTagFieldsParser::parseInstrument($instrument, $context),
            $context,
            $evaluator
        );
    }

    /**
     * @param null|callable(string,array,int):mixed $evaluator
     * @return array<string,array{conditions:array<int,array>,tags:list<array>}>
     */
    public static function resolveProject(array $context, ?callable $evaluator = null): array
    {
        return ActionTagProjectConditionResolver::resolveMany(
            ActionTagFieldsParser::parseProject($context),
            $context,
            $evaluator
        );
    }
}
