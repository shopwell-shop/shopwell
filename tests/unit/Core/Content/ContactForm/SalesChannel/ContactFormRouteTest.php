<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\ContactForm\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Cms\Service\CmsFormSlotConfigResolver;
use Shopwell\Core\Content\ContactForm\Extension\ContactFormRouteExtension;
use Shopwell\Core\Content\ContactForm\SalesChannel\ContactFormRoute;
use Shopwell\Core\Content\ContactForm\SalesChannel\ContactFormRouteResponse;
use Shopwell\Core\Content\ContactForm\Validation\ContactFormValidationFactory;
use Shopwell\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ContactFormRoute::class)]
class ContactFormRouteTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = static::createStub(SalesChannelContext::class);
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $properties
     * @param array<int, mixed> $constraints
     */
    #[DataProvider('validatorDataProvider')]
    public function testSubscribeWithValidation(array $data, array $properties, array $constraints): void
    {
        $requestData = new RequestDataBag();
        $requestData->add($data);

        $newsletterRecipientEntity = new NewsletterRecipientEntity();
        $newsletterRecipientEntity->setId(Uuid::randomHex());
        $newsletterRecipientEntity->setConfirmedAt(new \DateTime());

        $salutationEntitySearchResult = new EntitySearchResult(
            'salutation',
            1,
            new EntityCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->expects($this->once())->method('search')->willReturn($salutationEntitySearchResult);

        $mock = static::createStub(DataValidator::class);
        $mock->method('validate')->willReturnCallback(static function (array $data, DataValidationDefinition $definition) use ($properties, $constraints): void {
            foreach ($properties as $propertyName => $value) {
                static::assertSame($value, $data[$propertyName] ?? null);
                static::assertSame($definition->getProperties()[$propertyName] ?? null, $constraints);
            }
        });

        $slotConfigResolverMock = static::createStub(CmsFormSlotConfigResolver::class);
        $slotConfigResolverMock->method('resolve')->willReturn([
            'receivers' => ['foo' => 'bar'],
            'message' => 'baz',
        ]);

        $contactFormRoute = new ContactFormRoute(
            static::createStub(DataValidationFactoryInterface::class),
            $mock,
            static::createStub(EventDispatcherInterface::class),
            $entityRepository,
            static::createStub(RequestStack::class),
            static::createStub(RateLimiter::class),
            $slotConfigResolverMock,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $contactFormRoute->load($requestData, $this->salesChannelContext);
    }

    public static function validatorDataProvider(): \Generator
    {
        yield 'subscribe with no correct validation' => [
            [
                'email' => 'test@example.com',
                'option' => 'direct',
                'firstName' => 'Y http://localhost',
                'lastName' => 'Tran http://localhost',
                'salutationId' => Uuid::randomHex(),
            ],
            ['firstName' => 'Y http://localhost', 'lastName' => 'Tran http://localhost'],
            [
                new NotBlank(),
                new Regex(pattern: ContactFormValidationFactory::DOMAIN_NAME_REGEX, match: false),
            ],
        ];

        yield 'subscribe correct is validation' => [
            [
                'email' => 'test@example.com',
                'option' => 'direct',
                'firstName' => 'Y',
                'lastName' => 'Tran',
                'salutationId' => Uuid::randomHex(),
            ],
            ['firstName' => 'Y', 'lastName' => 'Tran'],
            [
                new NotBlank(),
                new Regex(pattern: ContactFormValidationFactory::DOMAIN_NAME_REGEX, match: false),
            ],
        ];
    }

    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag();
        $response = static::createStub(ContactFormRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('contact-form-route.load.pre', function (ContactFormRouteExtension $extension) use ($data, $response): void {
            static::assertSame(['data' => $data, 'context' => $this->salesChannelContext], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ContactFormRoute(
            static::createStub(DataValidationFactoryInterface::class),
            static::createStub(DataValidator::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(EntityRepository::class),
            static::createStub(RequestStack::class),
            static::createStub(RateLimiter::class),
            static::createStub(CmsFormSlotConfigResolver::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($data, $this->salesChannelContext));
    }
}
