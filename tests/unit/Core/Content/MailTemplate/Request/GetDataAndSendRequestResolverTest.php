<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\MailTemplate\Request;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Mail\Payload\MailPayload;
use Shopwell\Core\Content\Mail\Payload\MailPayloadFactory;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopwell\Core\Content\MailTemplate\MailTemplateEntity;
use Shopwell\Core\Content\MailTemplate\MailTemplateException;
use Shopwell\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Shopwell\Core\Content\MailTemplate\Request\Resolver\GetDataAndSendRequestResolver;
use Shopwell\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(GetDataAndSendRequestResolver::class)]
class GetDataAndSendRequestResolverTest extends TestCase
{
    private MailTemplateService&MockObject $mailTemplateService;

    private MailPayloadFactory&MockObject $mailPayloadFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailTemplateService = $this->createMock(MailTemplateService::class);
        $this->mailPayloadFactory = $this->createMock(MailPayloadFactory::class);
    }

    public function testResolveBuildsRequestAndFiltersUnknownEntities(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $mailPayload = new MailPayload(subject: 'payload subject');

        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'entities' => [
                'order' => 'order-id',
                'customer' => 'customer-id',
            ],
            'templateData' => [
                'foo' => 'bar',
            ],
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->mailPayloadFactory->expects($this->once())
            ->method('make')
            ->with(
                static::callback(static fn (RequestDataBag $requestDataBag): bool => $requestDataBag->all() === [
                    'mailTemplateId' => 'template-id',
                    'entities' => [
                        'order' => 'order-id',
                        'customer' => 'customer-id',
                    ],
                    'templateData' => [
                        'foo' => 'bar',
                    ],
                ]),
                [
                    'contentHtml' => '<p>html</p>',
                    'contentPlain' => 'plain',
                    'subject' => 'template subject',
                    'senderName' => 'template sender',
                ]
            )
            ->willReturn($mailPayload);

        $result = $this->resolveRequest($request);

        static::assertSame($mailTemplate, $result->mailTemplate);
        static::assertSame(['order' => 'order-id'], $result->entityMapping);
        static::assertSame(['foo' => 'bar'], $result->templateData);
        static::assertSame($mailPayload, $result->mailPayload);
    }

    public function testResolveThrowsForInvalidEntities(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'entities' => 'invalid',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->mailPayloadFactory->expects($this->never())
            ->method('make');

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('entities', 'array|object', 'string')
        );

        $this->resolveRequest($request);
    }

    public function testResolveThrowsForInvalidTemplateData(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'templateData' => 'invalid',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->mailPayloadFactory->expects($this->never())
            ->method('make');

        $this->expectExceptionObject(
            MailTemplateException::invalidRequestParameterType('templateData', 'array|object', 'string')
        );

        $this->resolveRequest($request);
    }

    public function testResolveKeepsEntitiesWhenMailTemplateTypeIsMissing(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $mailPayload = new MailPayload();

        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'entities' => [
                'order' => 'order-id',
                'customer' => 'customer-id',
            ],
        ]);

        $this->mailTemplateService->expects($this->once())->method('loadTemplate')->willReturn($mailTemplate);
        $this->mailPayloadFactory->expects($this->once())->method('make')->willReturn($mailPayload);

        $result = $this->resolveRequest($request);

        static::assertSame(
            [
                'order' => 'order-id',
                'customer' => 'customer-id',
            ],
            $result->entityMapping
        );
    }

    public function testResolveAcceptsPlainArrayValuesFromRequest(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $mailPayload = new MailPayload(subject: 'payload subject');
        $request = $this->createRequest($context, [
            'mailTemplateId' => 'template-id',
            'entities' => ['order' => 'order-id', 'customer' => 'customer-id'],
            'templateData' => ['foo' => 'bar'],
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->mailPayloadFactory->expects($this->once())
            ->method('make')
            ->with(
                static::callback(static fn (RequestDataBag $requestDataBag): bool => $requestDataBag->all() === [
                    'mailTemplateId' => 'template-id',
                    'entities' => ['order' => 'order-id', 'customer' => 'customer-id'],
                    'templateData' => ['foo' => 'bar'],
                ]),
                [
                    'contentHtml' => '<p>html</p>',
                    'contentPlain' => 'plain',
                    'subject' => 'template subject',
                    'senderName' => 'template sender',
                ]
            )
            ->willReturn($mailPayload);

        $result = $this->resolveRequest($request);

        static::assertSame(['order' => 'order-id'], $result->entityMapping);
        static::assertSame(['foo' => 'bar'], $result->templateData);
        static::assertSame($mailPayload, $result->mailPayload);
    }

    private function createMailTemplate(): MailTemplateEntity
    {
        $mailTemplateType = new MailTemplateTypeEntity();
        $mailTemplateType->setAvailableEntities([
            'order' => 'order',
        ]);

        $mailTemplate = new MailTemplateEntity();
        $mailTemplate->setMailTemplateType($mailTemplateType);
        $mailTemplate->setContentHtml('<p>html</p>');
        $mailTemplate->setContentPlain('plain');
        $mailTemplate->setSubject('template subject');
        $mailTemplate->setSenderName('template sender');

        return $mailTemplate;
    }

    private function resolveRequest(Request $request): GetDataAndSendRequest
    {
        $resolver = new GetDataAndSendRequestResolver($this->mailTemplateService, $this->mailPayloadFactory);

        return iterator_to_array(
            $resolver->resolve($request, new ArgumentMetadata('request', GetDataAndSendRequest::class, false, false, null))
        )[0];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createRequest(Context $context, array $payload): Request
    {
        $request = new Request([], $payload);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        return $request;
    }
}
