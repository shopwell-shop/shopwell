<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Criteria::class)]
class CriteriaExcludeFieldsTest extends TestCase
{
    public function testExcludeFieldsStoresAndMerges(): void
    {
        $criteria = new Criteria();
        $criteria->excludeFields(['description'])->excludeFields(['keywords']);

        static::assertSame(['description', 'keywords'], $criteria->getExcludedFields());
        static::assertSame([], $criteria->getFields());
    }

    public function testCloneForReadKeepsExcludedFields(): void
    {
        $criteria = (new Criteria())->excludeFields(['description']);

        static::assertSame(['description'], $criteria->cloneForRead(['test-id'])->getExcludedFields());
    }

    public function testExcludeFieldsThrowsWhenAllowlistFieldsAlreadySet(): void
    {
        $criteria = (new Criteria())->addFields(['name']);

        $this->expectException(DataAbstractionLayerException::class);
        $criteria->excludeFields(['description']);
    }

    public function testAddFieldsThrowsWhenExcludedFieldsAlreadySet(): void
    {
        $criteria = (new Criteria())->excludeFields(['description']);

        $this->expectException(DataAbstractionLayerException::class);
        $criteria->addFields(['name']);
    }
}
