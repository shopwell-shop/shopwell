<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\CustomEntity\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\CustomEntity\CustomEntityException;
use Shopwell\Core\System\CustomEntity\Schema\DynamicFieldFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DynamicFieldFactory::class)]
class DynamicFieldFactoryTest extends TestCase
{
    public function testCreateThrowsAnExceptionWhenTheServiceIsNotFound(): void
    {
        $this->expectExceptionObject(new ServiceNotFoundException('Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry'));

        $factory = new DynamicFieldFactory();

        $factory->create(static::createStub(ContainerInterface::class), 'test', [
            ['name' => 'test', 'type' => '', 'reference' => '', 'onDelete' => ''],
        ]);
    }

    public function testGetDeletedFlagThrowsAnExceptionWhenTheFieldIsUnmatched(): void
    {
        $this->expectExceptionObject(CustomEntityException::unsupportedOnDeletePropertyOnField('INVALID', 'test'));

        $factory = new DynamicFieldFactory();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->willReturn(static::createStub(DefinitionInstanceRegistry::class));

        $factory->create($container, 'test', [
            ['name' => 'test', 'type' => 'many-to-one', 'reference' => 'unit', 'onDelete' => 'INVALID'],
        ]);
    }
}
