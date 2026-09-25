<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Elasticsearch\Framework\Indexing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Elasticsearch\Framework\Indexing\CreateAliasTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CreateAliasTask::class)]
class CreateAliasTaskTest extends TestCase
{
    public function testShouldRun(): void
    {
        static::assertTrue(CreateAliasTask::shouldRun(new ParameterBag(['elasticsearch.enabled' => true])));
        static::assertFalse(CreateAliasTask::shouldRun(new ParameterBag(['elasticsearch.enabled' => false])));
    }

    public function testGetDefaultInterval(): void
    {
        static::assertSame(300, CreateAliasTask::getDefaultInterval());
    }

    public function testGetTaskName(): void
    {
        static::assertSame('shopwell.elasticsearch.create.alias', CreateAliasTask::getTaskName());
    }
}
