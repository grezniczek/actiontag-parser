<?php

namespace ActionTagParser;

/**
 * Runtime helper for the pure parser's condition references.
 *
 * It does not parse syntax, inspect tag semantics, or alter the parser
 * result. The default evaluator delegates to REDCap; tests and alternative
 * consumers can provide a callable instead.
 */
final class ActionTagConditionResolver
{
    /**
     * @param array $parseResult Result from ActionTagParser::parse().
     * @param array $context REDCap runtime context when no evaluator is supplied.
     * @param null|callable(string,array,int):mixed $evaluator Receives condition text, context, and condition ID.
     * @return array{conditions:array<int,array>,tags:list<array>}
     */
    public static function resolve(array $parseResult, array $context = [], ?callable $evaluator = null): array
    {
        $evaluate = $evaluator ?? static fn (string $condition, array $runtimeContext, int $id): mixed => self::evaluateWithRedcap($condition, $runtimeContext);
        $conditions = [];
        foreach ($parseResult['conditions'] ?? [] as $id => $definition) {
            $value = $evaluate($definition['raw'], $context, (int) $id);
            $conditions[(int) $id] = $definition + ['value' => self::isTrue($value)];
        }

        $tags = [];
        foreach ($parseResult['tags'] ?? [] as $tag) {
            $matches = (bool) ($tag['enabled'] ?? true);
            foreach ($tag['conditional'] ?? [] as $reference) {
                $value = $conditions[$reference['id']]['value'] ?? false;
                if ($value === $reference['negated']) {
                    $matches = false;
                    break;
                }
            }
            $tags[] = $tag + ['conditions_match' => $matches, 'active' => $matches];
        }

        return ['conditions' => $conditions, 'tags' => $tags];
    }

    /**
     * Resolve several parsed annotations in one shared runtime context.
     *
     * Identical opaque condition text is evaluated once and its value is
     * reused across all supplied parse results. A project-aware caller may
     * provide preloaded record data in $context['record_data'].
     *
     * @param array<string,array> $parseResults Field/key => parser result.
     * @param null|callable(string,array,int):mixed $evaluator
     * @return array<string,array{conditions:array<int,array>,tags:list<array>}>
     */
    public static function resolveMany(array $parseResults, array $context = [], ?callable $evaluator = null): array
    {
        $evaluate = $evaluator ?? static fn (string $condition, array $runtimeContext, int $id): mixed => self::evaluateWithRedcap($condition, $runtimeContext);
        $values = [];
        $resolved = [];
        foreach ($parseResults as $key => $parseResult) {
            $conditionValues = [];
            foreach ($parseResult['conditions'] ?? [] as $id => $definition) {
                $condition = $definition['raw'];
                if (!array_key_exists($condition, $values)) {
                    $values[$condition] = self::isTrue($evaluate($condition, $context, (int) $id));
                }
                $conditionValues[(int) $id] = $values[$condition];
            }

            $conditions = [];
            foreach ($parseResult['conditions'] ?? [] as $id => $definition) {
                $conditions[(int) $id] = $definition + ['value' => $conditionValues[(int) $id]];
            }
            $tags = [];
            foreach ($parseResult['tags'] ?? [] as $tag) {
                $matches = (bool) ($tag['enabled'] ?? true);
                foreach ($tag['conditional'] ?? [] as $reference) {
                    $value = $conditionValues[$reference['id']] ?? false;
                    if ($value === $reference['negated']) {
                        $matches = false;
                        break;
                    }
                }
                $tags[] = $tag + ['conditions_match' => $matches, 'active' => $matches];
            }
            $resolved[$key] = ['conditions' => $conditions, 'tags' => $tags];
        }
        return $resolved;
    }

    private static function evaluateWithRedcap(string $condition, array $context): mixed
    {
        foreach (['project_id', 'record', 'event_id', 'instrument'] as $key) {
            if (!array_key_exists($key, $context)) {
                throw new \InvalidArgumentException("Missing runtime context key: $key");
            }
        }
        return \REDCap::evaluateLogic(
            $condition,
            $context['project_id'],
            $context['record'],
            $context['event_id'],
            $context['instance'] ?? 1,
            $context['repeat_instrument'] ?? '',
            $context['instrument'],
            $context['record_data'] ?? null,
        );
    }

    private static function isTrue(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }
}
