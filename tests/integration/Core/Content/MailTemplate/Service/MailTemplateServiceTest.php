<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopwell\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopwell\Core\Content\MailTemplate\MailTemplateCollection;
use Shopwell\Core\Content\MailTemplate\MailTemplateEntity;
use Shopwell\Core\Content\MailTemplate\Request\PreviewRequest;
use Shopwell\Core\Content\MailTemplate\Request\SimulateRequest;
use Shopwell\Core\Content\MailTemplate\Service\MailDataSimulator;
use Shopwell\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopwell\Core\Content\MailTemplate\Validation\MailTemplateRenderResult;
use Shopwell\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopwell\Core\Content\RevocationRequest\Event\RevocationRequestEvent;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('after-sales')]
class MailTemplateServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MailTemplateService $mailTemplateService;

    private Context $context;

    /**
     * @var EntityRepository<MailTemplateCollection>
     */
    private EntityRepository $mailTemplateRepository;

    protected function setUp(): void
    {
        $this->mailTemplateRepository = static::getContainer()->get('mail_template.repository');
        $this->mailTemplateService = static::getContainer()->get(MailTemplateService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testLoadTemplate(): void
    {
        $mailTemplate = $this->createSimpleMailTemplate();

        $loadedTemplate = $this->mailTemplateService->loadTemplate($mailTemplate->getId(), $this->context);

        static::assertSame($mailTemplate->getId(), $loadedTemplate->getId());
        static::assertSame('Hello {{ customName }}', $loadedTemplate->getSubject());
        static::assertSame('<p>Hello {{ customName }}</p>', $loadedTemplate->getContentHtml());
        static::assertSame('Hello {{ customName }}', $loadedTemplate->getContentPlain());
        static::assertSame('Shopwell', $loadedTemplate->getSenderName());
    }

    public function testPreviewRendersTemplateData(): void
    {
        $mailTemplate = $this->createSimpleMailTemplate();

        $rendered = $this->mailTemplateService->preview(
            new PreviewRequest(
                mailTemplate: $mailTemplate,
                entityMapping: [],
                templateData: ['customName' => 'Shopwell'],
            ),
            $this->context
        );

        static::assertEquals(MailTemplateRenderResult::success('Hello Shopwell'), $rendered['subject']);
        static::assertEquals(MailTemplateRenderResult::success('Shopwell'), $rendered['senderName']);
        static::assertEquals(MailTemplateRenderResult::success('<p>Hello Shopwell</p>'), $rendered['contentHtml']);
        static::assertEquals(MailTemplateRenderResult::success('Hello Shopwell'), $rendered['contentPlain']);
    }

    public function testSimulate(): void
    {
        $rendered = $this->mailTemplateService->simulate(
            new SimulateRequest(
                templateParts: ['contentHtml' => '<p>{{ order.id }}</p>'],
                eventName: 'checkout.order.placed',
            ),
            $this->context
        );

        static::assertInstanceOf(MailTemplateRenderResult::class, $rendered['contentHtml']);
        static::assertSame(MailTemplateRenderResult::TYPE_SUCCESS, $rendered['contentHtml']->getType());
        static::assertNotSame('', $rendered['contentHtml']->getContent());
    }

    public function testSimulateRevocationRequestTemplate(): void
    {
        $formDataVariable = FlowMailVariables::REVOCATION_REQUEST_FORM_DATA;
        $contentHtml = \sprintf(
            '<p>{{ %1$s.name }} {{ %1$s.email }} {{ %1$s.contractNumber }} {{ %1$s.submitTime|format_datetime("medium", "short", locale="en-GB") }}</p>',
            $formDataVariable
        );

        $rendered = $this->mailTemplateService->simulate(
            new SimulateRequest(
                templateParts: [
                    'contentHtml' => $contentHtml,
                ],
                eventName: RevocationRequestEvent::EVENT_NAME,
            ),
            $this->context
        );

        static::assertSame(MailTemplateRenderResult::TYPE_SUCCESS, $rendered['contentHtml']->getType());
        static::assertStringContainsString('Max Mustermann', $rendered['contentHtml']->getContent());
        static::assertStringContainsString('max.mustermann@example.com', $rendered['contentHtml']->getContent());
        static::assertStringContainsString('10000', $rendered['contentHtml']->getContent());
    }

    #[DataProvider('formMailTemplateProvider')]
    public function testDefaultFormMailTemplatesRenderWithSimulatedData(string $eventName, string $fixtureDirectory): void
    {
        $fixturePath = __DIR__ . '/../../../../../../src/Core/Migration/Fixtures/mails/' . $fixtureDirectory;
        static::assertDirectoryExists($fixturePath);

        $files = iterator_to_array((new Finder())->files()->in($fixturePath)->name('*.twig'));
        static::assertNotEmpty($files, \sprintf('No template fixtures found in "%s".', $fixtureDirectory));

        foreach ($files as $file) {
            $rendered = $this->mailTemplateService->simulate(
                new SimulateRequest(
                    templateParts: ['contentHtml' => $file->getContents()],
                    eventName: $eventName,
                ),
                $this->context
            );

            static::assertSame(
                MailTemplateRenderResult::TYPE_SUCCESS,
                $rendered['contentHtml']->getType(),
                \sprintf(
                    'Simulating "%s/%s" failed. Does %s provide all referenced form data? Error: %s',
                    $fixtureDirectory,
                    $file->getFilename(),
                    MailDataSimulator::class,
                    $rendered['contentHtml']->getContent()
                )
            );
        }
    }

    public static function formMailTemplateProvider(): \Generator
    {
        yield 'contact form' => [ContactFormEvent::EVENT_NAME, 'contact_form'];
        yield 'review form' => [ReviewFormEvent::EVENT_NAME, 'review_form'];
        yield 'revocation request (customer)' => [RevocationRequestEvent::EVENT_NAME, 'revocation_request.customer'];
        yield 'revocation request (merchant)' => [RevocationRequestEvent::EVENT_NAME, 'revocation_request.merchant'];
    }

    public function testGetAvailableVariables(): void
    {
        $variables = $this->mailTemplateService->getAvailableVariables('checkout.order.placed', $this->context, 'order');

        static::assertIsArray($variables);
        static::assertNotSame([], $variables);
        static::assertContains('lineItems', array_column($variables, 'fieldName'));
    }

    private function createSimpleMailTemplate(): MailTemplateEntity
    {
        $typeCriteria = new Criteria();
        $typeCriteria->setLimit(1);

        /** @var EntityRepository<MailTemplateTypeCollection> $mailTemplateTypeRepository */
        $mailTemplateTypeRepository = static::getContainer()->get('mail_template_type.repository');
        $mailTemplateType = $mailTemplateTypeRepository->search($typeCriteria, $this->context)->getEntities()->first();

        static::assertInstanceOf(MailTemplateTypeEntity::class, $mailTemplateType);

        $mailTemplateId = Uuid::randomHex();

        $this->mailTemplateRepository->create([[
            'id' => $mailTemplateId,
            'mailTemplateTypeId' => $mailTemplateType->getId(),
            'subject' => 'Hello {{ customName }}',
            'senderName' => 'Shopwell',
            'contentHtml' => '<p>Hello {{ customName }}</p>',
            'contentPlain' => 'Hello {{ customName }}',
        ]], $this->context);

        $mailTemplate = $this->mailTemplateRepository->search(
            new Criteria([$mailTemplateId]),
            $this->context
        )->getEntities()->first();

        static::assertInstanceOf(MailTemplateEntity::class, $mailTemplate);

        return $mailTemplate;
    }
}
