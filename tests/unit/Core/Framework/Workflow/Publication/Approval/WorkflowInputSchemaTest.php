<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Publication\Approval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Publication\Approval\WorkflowFormOptionsEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowInputSchema::class)]
class WorkflowInputSchemaTest extends TestCase
{
    public function testValidatesOptionSourcesWithoutDependingOnTheFieldName(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkflowFormOptionsEvent::class, static function (WorkflowFormOptionsEvent $event): void {
            if ($event->source === 'core:currency') {
                $event->options = [['value' => 'CNY', 'label' => '人民币 · CNY']];
            }
        });
        $forms = new WorkflowInputSchema($dispatcher);
        $schema = [['key' => 'settlement', 'label' => 'Settlement currency', 'type' => 'text', 'required' => true, 'dataSource' => 'core:currency']];
        static::assertSame(['settlement' => 'CNY'], $forms->normalizeData($schema, ['settlement' => 'CNY']));
        $this->expectException(WorkflowExecutionException::class);
        $forms->normalizeData($schema, ['settlement' => 'cny']);
    }

    public function testOptionsUseTheRequestContext(): void
    {
        $context = Context::createDefaultContext();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WorkflowFormOptionsEvent::class, static function (WorkflowFormOptionsEvent $event) use ($context): void {
            static::assertSame($context, $event->context);
            $event->options = [['value' => 'warehouse', 'label' => 'Warehouse']];
        });
        $forms = new WorkflowInputSchema($dispatcher);
        static::assertSame(['location' => 'warehouse'], $forms->normalizeData([['key' => 'location', 'label' => 'Location', 'type' => 'text', 'dataSource' => 'inventory:warehouse']], ['location' => 'warehouse'], $context));
    }

    public function testMissingOptionSourceFailsClosed(): void
    {
        $this->expectException(WorkflowExecutionException::class);
        (new WorkflowInputSchema())->normalizeData([['key' => 'a', 'label' => 'A', 'type' => 'text', 'dataSource' => 'missing:source']], ['a' => 'value']);
    }

    public function testNormalizesTypedValuesWithoutLosingDecimalPrecision(): void
    {
        $forms = new WorkflowInputSchema();
        static::assertSame(['amount' => '999999999999.99', 'number' => '-0.000001', 'date' => '2024-02-29', 'select' => 'travel', 'text' => '理由'], $forms->normalizeData(self::schema(), [
            'amount' => '999999999999.99', 'number' => '-0.000001', 'date' => '2024-02-29', 'select' => 'travel', 'text' => '理由',
        ]));
        static::assertSame(['amount' => '5000.00', 'number' => '12.5'], $forms->normalizeData(self::schema(), ['amount' => 5000, 'number' => 12.5]));
        static::assertSame(['number' => '999999999999.125'], $forms->normalizeData(self::schema(), ['number' => 999999999999.125]));
        static::assertSame(['number' => '123456789012.125'], $forms->normalizeData(self::schema(), ['number' => 123456789012.125]));
        static::assertSame(['number' => '0.000001'], $forms->normalizeData(self::schema(), ['number' => 0.000001]));
        static::assertSame(['date' => null, 'text' => null], $forms->normalizeData(self::schema(), ['text' => '   ', 'date' => '']));
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidValues')]
    public function testRejectsInvalidAndUnknownValues(array $data): void
    {
        $this->expectException(WorkflowExecutionException::class);
        (new WorkflowInputSchema())->normalizeData(self::schema(), $data);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidValues(): iterable
    {
        yield 'unknown submitted field' => [['hidden' => 'value']];
        yield 'negative money' => [['amount' => '-1.00']];
        yield 'money precision over two decimals' => [['amount' => '1.001']];
        yield 'numeric money is not rounded into validity' => [['amount' => 999999999999.125]];
        yield 'money above maximum' => [['amount' => '1000000000000']];
        yield 'numeric exponent is not a plain decimal' => [['number' => '1e3']];
        yield 'number precision over six decimals' => [['number' => '0.0000001']];
        yield 'non finite number' => [['number' => \INF]];
        yield 'invalid leap day' => [['date' => '2025-02-29']];
        yield 'date format must be unambiguous' => [['date' => '2025-2-01']];
        yield 'unknown select value' => [['select' => 'other']];
        yield 'select does not coerce numeric value' => [['select' => 1]];
        yield 'text does not coerce boolean' => [['text' => false]];
        yield 'text bounded in length' => [['text' => str_repeat('a', 4001)]];
    }

    public function testRequiredTextRejectsWhitespace(): void
    {
        $this->expectException(WorkflowExecutionException::class);
        (new WorkflowInputSchema())->normalizeData([['key' => 'reason', 'label' => 'Reason', 'type' => 'text', 'required' => true]], ['reason' => '   ']);
    }

    public function testRequiredFieldsRejectOmission(): void
    {
        $this->expectException(WorkflowExecutionException::class);
        (new WorkflowInputSchema())->normalizeData([['key' => 'amount', 'label' => 'Amount', 'type' => 'amount', 'required' => true]], []);
    }

    #[DataProvider('invalidSchemas')]
    public function testRejectsInvalidSchema(mixed $schema): void
    {
        $this->expectException(WorkflowExecutionException::class);
        (new WorkflowInputSchema())->normalize($schema);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidSchemas(): iterable
    {
        yield 'duplicate keys' => [[...self::schema(), self::schema()[0]]];
        yield 'too many fields' => [array_fill(0, 51, self::schema()[0])];
        yield 'nested field path' => [[['key' => 'a.b', 'label' => 'Field', 'type' => 'text']]];
        yield 'required must be boolean' => [[['key' => 'a', 'label' => 'Field', 'type' => 'text', 'required' => 'true']]];
        yield 'select options required' => [[['key' => 'a', 'label' => 'Field', 'type' => 'select']]];
        yield 'duplicate option values' => [[['key' => 'a', 'label' => 'Field', 'type' => 'select', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'a', 'label' => 'B']]]]];
        yield 'empty label' => [[['key' => 'a', 'label' => ' ', 'type' => 'text']]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function schema(): array
    {
        return [
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'amount'],
            ['key' => 'number', 'label' => 'Number', 'type' => 'number'],
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'select', 'label' => 'Category', 'type' => 'select', 'options' => [['value' => 'travel', 'label' => 'Travel']]],
            ['key' => 'text', 'label' => 'Reason', 'type' => 'text'],
        ];
    }
}
