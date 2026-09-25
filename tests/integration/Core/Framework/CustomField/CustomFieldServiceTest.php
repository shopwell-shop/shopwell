<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\CustomField;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\CustomField\CustomFieldCollection;
use Shopwell\Core\System\CustomField\CustomFieldService;
use Shopwell\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
#[Package('framework')]
class CustomFieldServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<CustomFieldCollection>
     */
    private EntityRepository $attributeRepository;

    private CustomFieldService $attributeService;

    protected function setUp(): void
    {
        $this->attributeRepository = static::getContainer()->get('custom_field.repository');
        $this->attributeService = static::getContainer()->get(CustomFieldService::class);
    }

    /**
     * @return iterable<string, array{CustomFieldTypes::*, class-string<Field>}>
     */
    public static function attributeFieldTestProvider(): iterable
    {
        yield 'attribute field test custom field types bool bool field' => [CustomFieldTypes::BOOL, BoolField::class];
        yield 'attribute field test custom field types datetime date time field' => [CustomFieldTypes::DATETIME, DateTimeField::class];
        yield 'attribute field test custom field types float float field' => [CustomFieldTypes::FLOAT, FloatField::class];
        yield 'attribute field test custom field types html long text field' => [CustomFieldTypes::HTML, LongTextField::class];
        yield 'attribute field test custom field types int int field' => [CustomFieldTypes::INT, IntField::class];
        yield 'attribute field test custom field types json json field' => [CustomFieldTypes::JSON, JsonField::class];
        yield 'attribute field test custom field types text long text field' => [CustomFieldTypes::TEXT, LongTextField::class];
    }

    /**
     * @param CustomFieldTypes::* $attributeType
     * @param class-string<Field> $expectedFieldClass
     */
    #[DataProvider('attributeFieldTestProvider')]
    public function testGetCustomFieldField(string $attributeType, string $expectedFieldClass): void
    {
        $attribute = [
            'name' => 'test_attr',
            'type' => $attributeType,
        ];
        $this->attributeRepository->create([$attribute], Context::createDefaultContext());

        static::assertInstanceOf($expectedFieldClass, $this->attributeService->getCustomField('test_attr'));
    }

    public function testOnlyGetActive(): void
    {
        $id = Uuid::randomHex();
        $this->attributeRepository->upsert([[
            'id' => $id,
            'name' => 'test_attr',
            'active' => false,
            'type' => CustomFieldTypes::TEXT,
        ]], Context::createDefaultContext());

        $actual = $this->attributeService->getCustomField('test_attr');
        static::assertInstanceOf(JsonField::class, $actual);

        $this->attributeRepository->upsert([[
            'id' => $id,
            'active' => true,
        ]], Context::createDefaultContext());
        $this->attributeService->reset();

        $actual = $this->attributeService->getCustomField('test_attr');
        static::assertInstanceOf(LongTextField::class, $actual);
    }
}
