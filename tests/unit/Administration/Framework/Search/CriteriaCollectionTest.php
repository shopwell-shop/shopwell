<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Administration\Framework\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Administration\Framework\Search\CriteriaCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\FrameworkException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Notification\NotificationEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CriteriaCollection::class)]
class CriteriaCollectionTest extends TestCase
{
    public function testGetExpectedClass(): void
    {
        $collection = new CriteriaCollection();

        $collection->add(new Criteria());

        $this->expectExceptionObject(FrameworkException::collectionElementInvalidType(Criteria::class, NotificationEntity::class));

        try {
            /** @phpstan-ignore argument.type (for test purpose) */
            $collection->add(new NotificationEntity());
        } finally {
            static::assertCount(1, $collection);
        }
    }
}
