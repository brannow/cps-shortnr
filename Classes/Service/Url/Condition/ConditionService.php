<?php declare(strict_types=1);

namespace CPSIT\ShortNr\Service\Url\Condition;

use CPSIT\ShortNr\Config\ConfigInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\DTO\OperatorHistoryInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\OperatorInterface;
use CPSIT\ShortNr\Service\Url\Condition\Operators\WrappingOperatorInterface;
use Generator;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class ConditionService
{
    private array $cache = [];

    public function __construct(
        private readonly iterable $operators
    )
    {}

    /**
     * @param array $conditionConfig
     * @param QueryBuilder $queryBuilder
     * @return array
     */
    public function buildQueryCondition(array $conditionConfig, QueryBuilder $queryBuilder): array
    {
        $dbConditions = [];
        foreach ($conditionConfig as $fieldName => $fieldConfig) {
            $dbConditions[] = $this->processFieldConfig($fieldName, $fieldConfig, $queryBuilder, null);
        }

        return $dbConditions;
    }

    private function processFieldConfig(string $fieldName, mixed $fieldConfig, QueryBuilder $queryBuilder, ?OperatorHistoryInterface $parent): mixed
    {
        $operator = $this->findOperator($fieldConfig);
        if ($operator instanceof WrappingOperatorInterface) {
            // do magic to unwrap
            return $operator->wrap($fieldName, $fieldConfig, $queryBuilder, $parent, fn(string $fieldName, mixed $fieldConfig, QueryBuilder $queryBuilder, ?OperatorHistoryInterface $parent): mixed => $this->processFieldConfig($fieldName, $fieldConfig, $queryBuilder, $parent));
        }

        return $operator->process($fieldName, $fieldConfig, $queryBuilder, $parent);
    }

    private function findOperator(mixed $fieldConfig): ?OperatorInterface
    {
        foreach ($this->operators as $operator) {
            if ($operator->support($fieldConfig)) {
                return $operator;
            }
        }

        return null;
    }

    /**
     * Fast check if any given regex matches the uri
     *
     * @param string $uri
     * @param ConfigInterface $config
     * @return bool
     */
    public function matchAny(string $uri, ConfigInterface $config): bool
    {
        foreach ($this->matchGenerator($uri, $config) as $match) {
            return true; // First match found
        }
        return false;
    }

    /**
     * Return all config names and matches that successfully matched a regex
     *
     * @param string $uri
     * @param ConfigInterface $config
     * @return Generator
     */
    public function findAllMatchConfigCandidates(string $uri, ConfigInterface $config): Generator
    {
        return $this->matchGenerator($uri, $config);
    }

    /**
     * Generator that yields matches for each successful regex
     *
     * @param string $uri
     * @param ConfigInterface $config
     * @return Generator<array>
     */
    private function matchGenerator(string $uri, ConfigInterface $config): Generator
    {
        foreach ($config->getUniqueRegexConfigNameGroup() as $regex => $names) {
            $regexMatches = $this->matchRegex($uri, $regex);
            if ($regexMatches !== null) {
                yield [
                    'matches' => $regexMatches,
                    'names' => $names,
                ];
            }
        }
    }

    /**
     * gives the matches of the regex check
     *
     * @param string $uri
     * @param string $regex
     * @return array|null
     */
    private function matchRegex(string $uri, string $regex): ?array
    {
        $cacheKey = $uri.'::'.$regex;
        if (isset($this->cache['match'][$cacheKey])) {
            return $this->cache['match'][$cacheKey];
        }

        $matches = [];
        if (preg_match($regex, $uri, $matches, PREG_OFFSET_CAPTURE)) {
            return $this->cache['match'][$cacheKey] = $matches;
        }

        return $this->cache['match'][$cacheKey] = null;
    }
}
