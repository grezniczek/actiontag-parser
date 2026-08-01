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
            $context['record_data'] = self::preloadRecordData($parseResults, $context, $tryDraftMode)['record_data'];
        }
        return ActionTagConditionResolver::resolveMany($parseResults, $context, $evaluator);
    }

    /**
     * Resolve a scope and report REDCap data-preload and resolver phase costs.
     * Timing values are in microseconds and are intended for tooling/benchmarks.
     *
     * @param array<string,array> $parseResults Field/key => parser result.
     * @param null|callable(string,array,int):mixed $evaluator
     * @return array{results:array<string,array>,metrics:array<string,int|float|bool>}
     */
    public static function resolveManyWithMetrics(array $parseResults, array $context, ?callable $evaluator = null, bool $tryDraftMode = false): array
    {
        $started = hrtime(true);
        $preloadMetrics = [
            'record_data_preload_us' => 0.0,
            'referenced_fields' => 0,
            'preloaded_fields' => 0,
            'record_data_queries' => 0,
            'record_data_supplied' => array_key_exists('record_data', $context),
        ];
        if ($evaluator === null && !array_key_exists('record_data', $context)) {
            $preloadStarted = hrtime(true);
            $preload = self::preloadRecordData($parseResults, $context, $tryDraftMode);
            $context['record_data'] = $preload['record_data'];
            $preloadMetrics = array_replace($preloadMetrics, $preload['metrics']);
            $preloadMetrics['record_data_preload_us'] = (hrtime(true) - $preloadStarted) / 1000;
        }
        $resolved = ActionTagConditionResolver::resolveManyWithMetrics($parseResults, $context, $evaluator);
        return [
            'results' => $resolved['results'],
            'metrics' => array_replace($preloadMetrics, $resolved['metrics'], [
                'project_resolver_total_us' => (hrtime(true) - $started) / 1000,
            ]),
        ];
    }

    /** @return array{record_data:?array,metrics:array{referenced_fields:int,preloaded_fields:int,record_data_queries:int}} */
    private static function preloadRecordData(array $parseResults, array $context, bool $tryDraftMode): array
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
        if ($conditions === []) return ['record_data' => null, 'metrics' => ['referenced_fields' => 0, 'preloaded_fields' => 0, 'record_data_queries' => 0]];

        $Proj = new \Project($context['project_id']);
        $metadata = self::metadata($Proj, $tryDraftMode);
        // REDCap's helper also removes logic comments and normalizes checkbox
        // references, so this follows the evaluator's own field discovery.
        $fields = array_keys(\getBracketedFields(implode("\n", $conditions), true, true, true));
        $fields = array_values(array_filter($fields, static fn (string $field): bool => isset($metadata[$field])));
        if ($fields === []) return ['record_data' => null, 'metrics' => ['referenced_fields' => 0, 'preloaded_fields' => 0, 'record_data_queries' => 0]];

        $extraFields = [];
        foreach ($fields as $field) {
            $form = $metadata[$field]['form_name'];
            if ($Proj->isRepeatingFormAnyEvent($form)) $extraFields[] = $form . '_complete';
        }
        $preloadedFields = array_values(array_unique(array_merge($fields, $extraFields)));
        return ['record_data' => \Records::getData([
            'project_id' => $Proj->project_id,
            'records' => [$context['record']],
            'fields' => $preloadedFields,
            // Loading all project events keeps explicit [event][field]
            // references correct while remaining one record-data request.
            'events' => array_keys($Proj->eventInfo),
            'returnEmptyEvents' => true,
            'decimalCharacter' => '.',
            'returnBlankForGrayFormStatus' => true,
        ]), 'metrics' => [
            'referenced_fields' => count($fields),
            'preloaded_fields' => count($preloadedFields),
            'record_data_queries' => 1,
        ]];
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
