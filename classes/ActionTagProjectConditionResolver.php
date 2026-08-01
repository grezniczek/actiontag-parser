<?php

namespace ActionTagParser;

/**
 * REDCap runtime companion for ActionTagConditionResolver.
 *
 * It remains outside the pure parser: callers supply already parsed results
 * and an explicit record context. It fetches the union of referenced fields
 * once, then delegates condition application to the generic resolver.
 */
final class ActionTagProjectConditionResolver
{
    /**
     * @param array<string,array> $parseResults Field/key => parser result.
     * @param null|callable(string,array,int):mixed $evaluator
     * @return array<string,array{conditions:array<int,array>,tags:list<array>}>
     */
    public static function resolveMany(array $parseResults, array $context, ?callable $evaluator = null, bool $tryDraftMode = false): array
    {
        if ($evaluator === null && !array_key_exists('record_data', $context)) {
            $context['record_data'] = self::preloadRecordData($parseResults, $context, $tryDraftMode);
        }
        return ActionTagConditionResolver::resolveMany($parseResults, $context, $evaluator);
    }

    private static function preloadRecordData(array $parseResults, array $context, bool $tryDraftMode): ?array
    {
        foreach (['project_id', 'record', 'event_id'] as $key) {
            if (!array_key_exists($key, $context)) {
                throw new \InvalidArgumentException("Missing runtime context key: $key");
            }
        }
        $conditions = [];
        foreach ($parseResults as $parseResult) {
            foreach ($parseResult['conditions'] ?? [] as $definition) $conditions[] = $definition['raw'];
        }
        if ($conditions === []) return null;

        $Proj = new \Project($context['project_id']);
        $metadata = self::metadata($Proj, $tryDraftMode);
        // REDCap's helper also removes logic comments and normalizes checkbox
        // references, so this follows the evaluator's own field discovery.
        $fields = array_keys(\getBracketedFields(implode("\n", $conditions), true, true, true));
        $fields = array_values(array_filter($fields, static fn (string $field): bool => isset($metadata[$field])));
        if ($fields === []) return null;

        $extraFields = [];
        foreach ($fields as $field) {
            $form = $metadata[$field]['form_name'];
            if ($Proj->isRepeatingFormAnyEvent($form)) $extraFields[] = $form . '_complete';
        }
        return \Records::getData([
            'project_id' => $Proj->project_id,
            'records' => [$context['record']],
            'fields' => array_values(array_unique(array_merge($fields, $extraFields))),
            // Loading all project events keeps explicit [event][field]
            // references correct while remaining one record-data request.
            'events' => array_keys($Proj->eventInfo),
            'returnEmptyEvents' => true,
            'decimalCharacter' => '.',
            'returnBlankForGrayFormStatus' => true,
        ]);
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
}
