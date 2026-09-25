<?php declare(strict_types=1);

namespace Shopwell\Elasticsearch\Framework\DataAbstractionLayer;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityAggregator;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\RangeAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\DateHistogramResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\AvgResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MinResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\RangeResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopwell\Elasticsearch\ElasticsearchException;

#[Package('framework')]
class ElasticsearchEntityAggregatorHydrator extends AbstractElasticsearchAggregationHydrator
{
    /**
     * @internal
     */
    public function __construct(private readonly DefinitionInstanceRegistry $registry)
    {
    }

    public function getDecorated(): AbstractElasticsearchAggregationHydrator
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @param array<string, mixed> $result
     */
    public function hydrate(EntityDefinition $definition, Criteria $criteria, Context $context, array $result): AggregationResultCollection
    {
        if (!isset($result['aggregations'])) {
            return new AggregationResultCollection();
        }

        $aggregations = new AggregationResultCollection();

        foreach ($result['aggregations'] as $name => $aggResult) {
            $aggregation = $criteria->getAggregation($name);

            if (!$aggregation) {
                continue;
            }

            $hydration = $this->hydrateAggregation($aggregation, $aggResult, $context);
            if ($hydration) {
                $aggregations->add(
                    $hydration
                );
            }
        }

        return $aggregations;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrateAggregation(Aggregation $aggregation, array $result, Context $context): ?AggregationResult
    {
        switch (true) {
            case $aggregation instanceof StatsAggregation:
                return new StatsResult($aggregation->getName(), $result['min'], $result['max'], $result['avg'], $result['sum']);

            case $aggregation instanceof AvgAggregation:
                return new AvgResult($aggregation->getName(), $result['value']);

            case $aggregation instanceof CountAggregation:
                return new CountResult($aggregation->getName(), $result['value']);

            case $aggregation instanceof EntityAggregation:
                return $this->hydrateEntityAggregation($aggregation, $result, $context);

            case $aggregation instanceof MaxAggregation:
                return new MaxResult($aggregation->getName(), $result['value']);

            case $aggregation instanceof MinAggregation:
                return new MinResult($aggregation->getName(), $result['value']);

            case $aggregation instanceof SumAggregation:
                return new SumResult($aggregation->getName(), $result['value']);

            case $aggregation instanceof FilterAggregation:
                $nested = $aggregation->getAggregation();

                if (!$nested) {
                    throw ElasticsearchException::nestedAggregationMissingInFilterAggregation($aggregation->getName());
                }
                $nestedResult = $result;

                while (isset($nestedResult[$aggregation->getName()])) {
                    $nestedResult = $nestedResult[$aggregation->getName()];
                }

                if (isset($nestedResult[$nested->getName()])) {
                    $nestedResult = $nestedResult[$nested->getName()];
                }

                return $this->hydrateAggregation($nested, $nestedResult, $context);

            case $aggregation instanceof DateHistogramAggregation:
                return $this->hydrateDateHistogram($aggregation, $result, $context);

            case $aggregation instanceof TermsAggregation:
                return $this->hydrateTermsAggregation($aggregation, $result, $context);

            case $aggregation instanceof RangeAggregation:
                return $this->hydrateRangeAggregation($aggregation, $result);

            default:
                throw ElasticsearchException::unsupportedAggregation($aggregation::class);
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrateEntityAggregation(EntityAggregation $aggregation, array $result, Context $context): EntityResult
    {
        if (\array_key_exists($aggregation->getName(), $result)) {
            $result = $result[$aggregation->getName()];
        }

        $ids = array_column($result['buckets'], 'key');

        if ($ids === []) {
            $definition = $this->registry->getByEntityName($aggregation->getEntity());
            /** @var class-string<EntityCollection<Entity>> $class */
            $class = $definition->getCollectionClass();

            return new EntityResult($aggregation->getName(), new $class());
        }
        $repository = $this->registry->getRepository($aggregation->getEntity());

        $entities = $repository->search(new Criteria($ids), $context);

        return new EntityResult($aggregation->getName(), $entities->getEntities());
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrateDateHistogram(DateHistogramAggregation $aggregation, array $result, Context $context): ?DateHistogramResult
    {
        if (isset($result[$aggregation->getName()])) {
            $result = $result[$aggregation->getName()];
        }

        if (!isset($result['buckets'])) {
            return null;
        }

        $buckets = [];

        $nestedAggregation = $aggregation->getAggregation();

        foreach ($result['buckets'] as $bucket) {
            $nested = null;

            if ($nestedAggregation) {
                $nested = $this->hydrateAggregation($nestedAggregation, $bucket[$nestedAggregation->getName()], $context);
            }

            $key = $bucket['key'][$aggregation->getName() . '.key'];

            $date = new \DateTime($key);

            if ($dateFormat = $aggregation->getFormat()) {
                $value = $date->format($dateFormat);
            } else {
                $value = EntityAggregator::formatDate($aggregation->getInterval(), $date);
            }

            $buckets[] = new Bucket($value, $bucket['doc_count'], $nested);
        }

        return new DateHistogramResult($aggregation->getName(), $buckets);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrateTermsAggregation(TermsAggregation $aggregation, array $result, Context $context): ?TermsResult
    {
        if ($aggregation->getSorting()) {
            return $this->hydrateSortedTermsAggregation($aggregation, $result, $context);
        }

        if (isset($result[$aggregation->getName()])) {
            $result = $result[$aggregation->getName()];
        }

        $key = $aggregation->getName() . '.key';
        if (isset($result[$key])) {
            $result = $result[$key];
        }

        if (!isset($result['buckets'])) {
            return null;
        }

        $buckets = [];
        $nestedAggregation = $aggregation->getAggregation();

        foreach ($result['buckets'] as $bucket) {
            $nested = null;

            if ($nestedAggregation) {
                $nested = $this->hydrateAggregation(
                    $nestedAggregation,
                    $bucket[$nestedAggregation->getName()],
                    $context
                );
            }

            $buckets[] = new Bucket((string) $bucket['key'], $bucket['doc_count'], $nested);
        }

        return new TermsResult($aggregation->getName(), $buckets);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrateRangeAggregation(RangeAggregation $aggregation, array $result): ?RangeResult
    {
        if (isset($result[$aggregation->getName()])) {
            $result = $result[$aggregation->getName()];
        }

        $key = $aggregation->getName() . '.key';
        if (isset($result[$key])) {
            $result = $result[$key];
        }

        if (!isset($result['buckets'])) {
            return null;
        }

        $ranges = [];
        foreach ($result['buckets'] as $bucket) {
            $ranges[(string) $bucket['key']] = (int) $bucket['doc_count'];
        }

        return new RangeResult($aggregation->getName(), $ranges);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrateSortedTermsAggregation(TermsAggregation $aggregation, array $result, Context $context): ?TermsResult
    {
        if (isset($result[$aggregation->getName()])) {
            $result = $result[$aggregation->getName()];
        }

        if (!isset($result['buckets'])) {
            return null;
        }

        $buckets = [];
        $nestedAggregation = $aggregation->getAggregation();

        foreach ($result['buckets'] as $bucket) {
            $nested = null;

            if ($nestedAggregation) {
                $nested = $this->hydrateAggregation(
                    $nestedAggregation,
                    $bucket[$nestedAggregation->getName()],
                    $context
                );
            }

            $key = $bucket['key'][$aggregation->getName() . '.key'];

            $buckets[] = new Bucket((string) $key, $bucket['doc_count'], $nested);
        }

        return new TermsResult($aggregation->getName(), $buckets);
    }
}
