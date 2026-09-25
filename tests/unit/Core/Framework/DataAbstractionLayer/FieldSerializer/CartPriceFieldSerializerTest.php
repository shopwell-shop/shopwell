<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\CartPriceFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\WriteConstraintViolationException;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Mapping\Factory\BlackHoleMetadataFactory;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CartPriceFieldSerializer::class)]
class CartPriceFieldSerializerTest extends TestCase
{
    private CartPriceFieldSerializer $serializer;

    private CartPriceField $field;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $validator = new RecursiveValidator(
            new ExecutionContextFactory(static::createStub(TranslatorInterface::class)),
            new BlackHoleMetadataFactory(),
            new ConstraintValidatorFactory()
        );

        $this->serializer = new CartPriceFieldSerializer($validator, new StaticDefinitionInstanceRegistry(
            [new ProductDefinition()],
            $validator,
            static::createStub(EntityWriteGateway::class)
        ));

        $this->field = new CartPriceField('some_field', 'someField');
        $this->existence = new EntityExistence('product', ['someId' => true], true, false, false, []);
        $this->parameters = new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/0',
            new WriteCommandQueue()
        );
    }

    public function testEncodeStripsExtensionsFromCartPrice(): void
    {
        $cartPrice = new CartPrice(
            10.0,
            11.9,
            10.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_NET
        );
        $cartPrice->addArrayExtension('test', ['test' => 'test']);

        $encoded = iterator_to_array($this->serializer->encode(
            $this->field,
            $this->existence,
            new KeyValuePair('someField', $cartPrice, true),
            $this->parameters
        ), true);

        static::assertSame([
            'netPrice' => 10.0,
            'totalPrice' => 11.9,
            'calculatedTaxes' => [],
            'taxRules' => [],
            'positionPrice' => 10.0,
            'rawTotal' => 11.9,
            'taxStatus' => CartPrice::TAX_STATE_NET,
        ], json_decode((string) $encoded['some_field'], true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * The serializer pre-processes the payload before `JsonFieldSerializer::encode()` validates it. A scalar
     * used to reach that pre-processing and abort the request with a PHP `Error` instead of a violation.
     */
    #[DataProvider('nonArrayValueProvider')]
    public function testEncodeRejectsNonArrayValue(mixed $value): void
    {
        $this->expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation('This value should be of type array.', 'This value should be of type {{ type }}.', [], null, '/someField', $value),
            ])
        ));

        iterator_to_array($this->serializer->encode(
            $this->field,
            $this->existence,
            new KeyValuePair('someField', $value, false),
            $this->parameters
        ), true);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonArrayValueProvider(): iterable
    {
        yield 'number, where PHP reads the offset as an array index' => [12.5];
        yield 'string, where PHP reads the offset as a string offset' => ['2025-10-09'];
    }
}
