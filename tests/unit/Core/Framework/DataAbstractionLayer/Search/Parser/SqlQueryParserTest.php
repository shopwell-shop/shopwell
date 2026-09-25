<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Parser;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ListDefinition;
use Shopwell\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;
use Shopwell\Core\System\Unit\UnitDefinition;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SqlQueryParser::class)]
class SqlQueryParserTest extends TestCase
{
    public function testParseUnsupportedQueryFilter(): void
    {
        $this->expectException(DataAbstractionLayerException::class);

        $parser = new SqlQueryParser(new EntityDefinitionQueryHelper(), static::createStub(Connection::class));

        $parser->parse(
            new ScoreQuery(new ContainsFilter('description', 'test'), 250),
            new UnitDefinition(),
            Context::createDefaultContext(),
        );
    }

    public function testParseNegatedEqualsAnyFilterKeepsNullableRows(): void
    {
        $parser = new SqlQueryParser(new EntityDefinitionQueryHelper(), static::createStub(Connection::class));

        $result = $parser->parse(
            new NotFilter(NotFilter::CONNECTION_AND, [
                new EqualsAnyFilter('shortCode', ['foo']),
            ]),
            $this->getRegistry()->getByEntityName(UnitDefinition::ENTITY_NAME),
            Context::createDefaultContext(),
        );

        static::assertCount(1, $result->getWheres());
        static::assertStringStartsWith('NOT ((', $result->getWheres()[0]);
        static::assertStringContainsString(' IN (:param_', $result->getWheres()[0]);
        static::assertStringContainsString('IS NOT NULL', $result->getWheres()[0]);

        $parameters = array_values($result->getParameters());
        static::assertCount(1, $parameters);
        static::assertSame(['foo'], $parameters[0]);
    }

    public function testParseEmptyEqualsAnyFilterOnListFieldMatchesNothing(): void
    {
        $parser = new SqlQueryParser(new EntityDefinitionQueryHelper(), static::createStub(Connection::class));

        $result = $parser->parse(
            new EqualsAnyFilter('data', []),
            $this->getRegistry([ListDefinition::class])->getByEntityName(ListDefinition::ENTITY_NAME),
            Context::createDefaultContext(),
        );

        static::assertSame(['1 = 0'], $result->getWheres());
        static::assertSame([], $result->getParameters());
    }

    /**
     * @param list<class-string<EntityDefinition>> $definitions
     */
    private function getRegistry(array $definitions = [UnitDefinition::class, UnitTranslationDefinition::class]): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            $definitions,
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }
}
