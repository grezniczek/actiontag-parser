<?php

namespace ActionTagParser;

/**
 * REDCap metadata facade for parsing a caller-selected field scope.
 *
 * The underlying ActionTagParser remains pure. This helper owns only project
 * metadata lookup and scope selection for EM/core runtime consumers.
 */
final class ActionTagFieldsParser
{
    /**
     * @param list<string> $fieldNames Field names in the desired parse scope.
     * @return array<string,array> Field name => pure parser result.
     */
    public static function parse(array $fieldNames, array $context, array $parseOptions = []): array
    {
        $Proj = self::project($context);
        $parsed = [];
        foreach (array_values(array_unique($fieldNames)) as $fieldName) {
            if (!isset($Proj->metadata[$fieldName])) {
                throw new \InvalidArgumentException("Unknown project field: $fieldName");
            }
            $parsed[$fieldName] = ActionTagParser::parse($Proj->metadata[$fieldName]['misc'] ?? '', $parseOptions);
        }
        return $parsed;
    }

    /** @return array<string,array> Field name => pure parser result. */
    public static function parseInstrument(string $instrument, array $context, array $parseOptions = []): array
    {
        $Proj = self::project($context);
        if (!isset($Proj->forms[$instrument])) {
            throw new \InvalidArgumentException("Unknown project instrument: $instrument");
        }
        return self::parse(array_keys($Proj->forms[$instrument]['fields'] ?? []), $context, $parseOptions);
    }

    /** @return array<string,array> Field name => pure parser result. */
    public static function parseProject(array $context, array $parseOptions = []): array
    {
        $Proj = self::project($context);
        return self::parse(array_keys($Proj->metadata), $context, $parseOptions);
    }

    private static function project(array $context): \Project
    {
        if (!isset($context['project_id']) || !is_numeric($context['project_id'])) {
            throw new \InvalidArgumentException('Missing runtime context key: project_id');
        }
        return new \Project($context['project_id']);
    }
}
