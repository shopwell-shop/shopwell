<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\RevocationRequest\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Content\Cms\Service\CmsFormSlotConfigResolver;
use Shopwell\Core\Content\RevocationRequest\Extension\RevocationRequestRouteExtension;
use Shopwell\Core\Content\RevocationRequest\SalesChannel\RevocationRequestRoute;
use Shopwell\Core\Content\RevocationRequest\SalesChannel\RevocationRequestRouteResponse;
use Shopwell\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;
use Shopwell\Core\Test\Generator;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(RevocationRequestRoute::class)]
class RevocationRequestRouteTest extends TestCase
{
    /**
     * @param array<string, string> $data
     */
    #[DataProvider('validationDataProvider')]
    public function testRequestValidatesFormData(array $data): void
    {
        $requestData = new RequestDataBag($data);
        $definition = new DataValidationDefinition('revocation_request_form.create');

        $validationFactory = static::createStub(DataValidationFactoryInterface::class);
        $validationFactory->method('create')->willReturn($definition);

        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())
            ->method('getViolations')
            ->willReturnCallback(static function (array $validatedData, DataValidationDefinition $validatedDefinition) use ($data, $definition): ConstraintViolationList {
                foreach ($data as $property => $value) {
                    static::assertSame($value, $validatedData[$property] ?? null);
                }
                static::assertSame($definition, $validatedDefinition);

                return new ConstraintViolationList();
            });

        $route = $this->createRevocationRequestRoute(
            validatorFactory: $validationFactory,
            validator: $validator,
        );

        $route->request($requestData, $this->createSalesChannelContext());
    }

    public static function validationDataProvider(): \Generator
    {
        yield 'valid form data' => [[
            'name' => 'Max Mustermann',
            'email' => 'max@muster.com',
            'contractNumber' => 'SW123456789',
            'comment' => 'This is a simple comment',
        ]];

        yield 'form data with optional context fields' => [[
            'name' => 'Max Mustermann',
            'email' => 'max@muster.com',
            'contractNumber' => 'SW123456789',
            'comment' => 'This is a simple comment',
            'slotId' => Uuid::randomHex(),
            'navigationId' => Uuid::randomHex(),
            'entityName' => 'landing_page',
        ]];
    }

    public function testPublishesExtension(): void
    {
        $dataBag = new RequestDataBag();
        $context = Generator::generateSalesChannelContext();
        $response = static::createStub(RevocationRequestRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('revocation-request-route.request.pre', static function (RevocationRequestRouteExtension $extension) use ($dataBag, $context, $response): void {
            static::assertSame(['dataBag' => $dataBag, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new RevocationRequestRoute(
            static::createStub(DataValidationFactoryInterface::class),
            static::createStub(DataValidator::class),
            static::createStub(RequestStack::class),
            static::createStub(RateLimiter::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(ClockInterface::class),
            static::createStub(CmsFormSlotConfigResolver::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->request($dataBag, $context));
    }

    private function createRequestStackMock(): RequestStack
    {
        $requestStackMock = static::createStub(RequestStack::class);
        $requestStackMock->method('getMainRequest')->willReturn(new Request());

        return $requestStackMock;
    }

    private function createRevocationRequestRoute(
        ?EventDispatcherInterface $eventDispatcher = null,
        ?DataValidationFactoryInterface $validatorFactory = null,
        ?DataValidator $validator = null,
    ): RevocationRequestRoute {
        $validatorFactory ??= static::createStub(DataValidationFactoryInterface::class);
        $validator ??= static::createStub(DataValidator::class);

        $slotConfigResolver = static::createStub(CmsFormSlotConfigResolver::class);
        $slotConfigResolver->method('resolve')->willReturn([
            'receivers' => ['foo' => 'bar'],
            'message' => 'baz',
        ]);

        return new RevocationRequestRoute(
            $validatorFactory,
            $validator,
            $this->createRequestStackMock(),
            static::createStub(RateLimiter::class),
            $eventDispatcher ?? static::createStub(EventDispatcherInterface::class),
            new NativeClock(),
            $slotConfigResolver,
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        return Generator::generateSalesChannelContext(
            baseContext: new Context(new SalesChannelApiSource(Uuid::randomHex())),
            salesChannel: $salesChannel
        );
    }
}
