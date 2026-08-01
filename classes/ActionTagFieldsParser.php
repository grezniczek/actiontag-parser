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
    public static function parse(array $fieldNames, array $context, array $parseOptions = [], bool $tryDraftMode = false): array
    {
        $Proj = self::project($context);
        return self::parseWithProject($Proj, $fieldNames, $parseOptions, $tryDraftMode);
    }

    /** @return array<string,array> Field name => pure parser result. */
    public static function parseInstrument(string $instrument, array $context, array $parseOptions = [], bool $tryDraftMode = false): array
    {
        $Proj = self::project($context);
        $forms = self::forms($Proj, $tryDraftMode);
        if (!isset($forms[$instrument])) {
            throw new \InvalidArgumentException("Unknown project instrument: $instrument");
        }
        return self::parseWithProject($Proj, array_keys($forms[$instrument]['fields'] ?? []), $parseOptions, $tryDraftMode);
    }

    /** @return array<string,array> Field name => pure parser result. */
    public static function parseProject(array $context, array $parseOptions = [], bool $tryDraftMode = false): array
    {
        $Proj = self::project($context);
        return self::parseWithProject($Proj, array_keys(self::metadata($Proj, $tryDraftMode)), $parseOptions, $tryDraftMode);
    }

    /** @return array<string,array> Field name => pure parser result. */
    private static function parseWithProject(\Project $Proj, array $fieldNames, array $parseOptions, bool $tryDraftMode): array
    {
        $metadata = self::metadata($Proj, $tryDraftMode);
        $parsed = [];
        foreach (array_values(array_unique($fieldNames)) as $fieldName) {
            if (!isset($metadata[$fieldName])) {
                throw new \InvalidArgumentException("Unknown project field: $fieldName");
            }
            $parsed[$fieldName] = ActionTagParser::parse($metadata[$fieldName]['misc'] ?? '', $parseOptions);
        }
        return $parsed;
    }

    /** @return array<string,array<string,mixed>> */
    private static function metadata(\Project $Proj, bool $tryDraftMode): array
    {
        if ($tryDraftMode && $Proj->isDraftMode()) {
            if ($Proj->metadata_temp === null) $Proj->loadMetadataTemp();
            return $Proj->metadata_temp ?? [];
        }
        // Project owns the current-project Draft Preview decision.
        return $Proj->getMetadata() ?? [];
    }

    /** @return array<string,array<string,mixed>> */
    private static function forms(\Project $Proj, bool $tryDraftMode): array
    {
        if ($tryDraftMode && $Proj->isDraftMode()) {
            if ($Proj->forms_temp === null) $Proj->loadMetadataTemp();
            return $Proj->forms_temp ?? [];
        }
        // Project owns the current-project Draft Preview decision.
        return $Proj->getForms() ?? [];
    }

    private static function project(array $context): \Project
    {
        if (!isset($context['project_id']) || !is_numeric($context['project_id'])) {
            throw new \InvalidArgumentException('Missing runtime context key: project_id');
        }
        return new \Project($context['project_id']);
    }
}
