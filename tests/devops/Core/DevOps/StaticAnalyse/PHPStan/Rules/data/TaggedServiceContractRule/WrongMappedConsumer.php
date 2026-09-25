<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TaggedServiceContractRule;

class WrongMappedConsumer
{
    /**
     * @param iterable<WrongContract> $services
     */
    public function __construct(iterable $services)
    {
    }
}
