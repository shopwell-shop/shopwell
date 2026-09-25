<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolation;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RestrictDeleteViolationException::class)]
class RestrictDeleteViolationExceptionTest extends TestCase
{
    #[TestDox('the message lists each restricting entity with its usage count')]
    public function testMessageListsUsages(): void
    {
        $violation = new RestrictDeleteViolation([
            'order_line_item' => [['id' => 'a'], ['id' => 'b']],
            'product_review' => [['id' => 'c']],
        ]);

        $exception = new RestrictDeleteViolationException(new ProductDefinition(), [$violation]);

        static::assertSame(
            'The delete request for product was denied due to a conflict. The entity is currently in use by: order_line_item (2), product_review (1)',
            $exception->getMessage()
        );
        static::assertSame([$violation], $exception->getRestrictions());
        static::assertSame(
            [
                ['entityName' => 'order_line_item', 'count' => 2],
                ['entityName' => 'product_review', 'count' => 1],
            ],
            $exception->getParameter('usages')
        );
    }
}
