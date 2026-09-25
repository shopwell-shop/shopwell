<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Mail\Payload\MailPayload;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopwell\Core\Content\MailTemplate\MailTemplateCollection;
use Shopwell\Core\Content\MailTemplate\MailTemplateEntity;
use Shopwell\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Shopwell\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[Package('after-sales')]
class MailTemplateSendServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MailTemplateSendService $mailTemplateSendService;

    private Context $context;

    /**
     * @var EntityRepository<MailTemplateCollection>
     */
    private EntityRepository $mailTemplateRepository;

    protected function setUp(): void
    {
        $this->mailTemplateRepository = static::getContainer()->get('mail_template.repository');
        $this->mailTemplateSendService = static::getContainer()->get(MailTemplateSendService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testGetTemplateDataAndSend(): void
    {
        $mailTemplate = $this->createSimpleMailTemplate();

        $email = $this->mailTemplateSendService->getTemplateDataAndSend(
            new GetDataAndSendRequest(
                $mailTemplate,
                [],
                ['customName' => 'Shopwell'],
                new MailPayload(
                    recipients: ['test@example.com' => 'Test'],
                    contentHtml: $mailTemplate->getContentHtml(),
                    contentPlain: $mailTemplate->getContentPlain(),
                    subject: $mailTemplate->getSubject(),
                    senderName: $mailTemplate->getSenderName(),
                )
            ),
            $this->context
        );

        static::assertInstanceOf(Email::class, $email);
        static::assertSame('Hello Shopwell', $email->getSubject());
        static::assertSame('Shopwell', $email->getFrom()[0]->getName());
        static::assertSame('Test', $email->getTo()[0]->getName());
        static::assertSame('test@example.com', $email->getTo()[0]->getAddress());
        static::assertSame('Hello Shopwell', $email->getTextBody());
        static::assertSame('<p>Hello Shopwell</p>', $email->getHtmlBody());
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
