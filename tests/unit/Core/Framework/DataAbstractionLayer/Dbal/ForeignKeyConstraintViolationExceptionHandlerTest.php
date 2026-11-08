<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Driver\PDO\Exception as PdoDriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Category\CategoryDefinition;
use Shopwell\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\ForeignKeyConstraintViolationExceptionHandler;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\InvalidForeignKeyReferenceException;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ForeignKeyConstraintViolationExceptionHandler::class)]
class ForeignKeyConstraintViolationExceptionHandlerTest extends TestCase
{
    public function testForeignKeyViolationBecomesAFieldSpecificShopwellError(): void
    {
        $driverException = PdoDriverException::new(new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1452 foreign key constraint fails (`shopwell`.`product_category`, CONSTRAINT `fk.product_category.category_id` FOREIGN KEY (`category_id`, `category_version_id`) REFERENCES `category` (`id`, `version_id`))'));

        $registry = new StaticDefinitionInstanceRegistry(
            [ProductCategoryDefinition::class, ProductDefinition::class, CategoryDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
        $handler = new ForeignKeyConstraintViolationExceptionHandler($registry);

        $exception = $handler->matchException(new ForeignKeyConstraintViolationException($driverException, null));

        static::assertInstanceOf(InvalidForeignKeyReferenceException::class, $exception);
        $error = $exception->getErrors()->current();
        static::assertSame(InvalidForeignKeyReferenceException::ERROR_CODE, $error['code']);
        static::assertSame('/categoryId', $error['source']['pointer']);
        static::assertStringContainsString('product_category', (string) $error['detail']);
        static::assertStringContainsString('category', (string) $error['detail']);
        static::assertStringContainsString('Integrity constraint violation: 1452', (string) $error['detail']);
    }

    public function testUnrelatedExceptionIsNotHandled(): void
    {
        $handler = new ForeignKeyConstraintViolationExceptionHandler(new StaticDefinitionInstanceRegistry(
            [],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        ));

        static::assertSame(ExceptionHandlerInterface::PRIORITY_LATE, $handler->getPriority());
        static::assertNull($handler->matchException(new \RuntimeException('Unrelated failure')));
    }

    public function testDeleteRestrictionIsNotTreatedAsAMissingReference(): void
    {
        $driverException = PdoDriverException::new(new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`shopwell`.`product_category`, CONSTRAINT `fk.product_category.category_id` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`))'));
        $handler = new ForeignKeyConstraintViolationExceptionHandler(new StaticDefinitionInstanceRegistry(
            [],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        ));

        static::assertNull($handler->matchException(new ForeignKeyConstraintViolationException($driverException, null)));
    }
}
