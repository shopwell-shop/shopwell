<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\DocumentV2;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\DocumentV2\DocumentDefinition;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentDefinition::class)]
class DocumentDefinitionTest extends TestCase
{
    public function testOrderIsTheParentDefinition(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [DocumentDefinition::class, OrderDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(DocumentDefinition::ENTITY_NAME);

        static::assertInstanceOf(OrderDefinition::class, $definition->getParentDefinition());
    }
}
