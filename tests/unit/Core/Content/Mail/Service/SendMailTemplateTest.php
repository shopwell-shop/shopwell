<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Mail\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Content\Mail\Service\AbstractMailService;
use Shopwell\Core\Content\Mail\Service\SendMailTemplate;
use Shopwell\Core\Content\Mail\Service\SendMailTemplateParams;
use Shopwell\Core\Content\MailTemplate\MailTemplateCollection;
use Shopwell\Core\Content\MailTemplate\MailTemplateEntity;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\Mime\Address;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(SendMailTemplate::class)]
class SendMailTemplateTest extends TestCase
{
    public function testSendFailureDoesNotLogBodyOrExceptionContent(): void
    {
        $context = Context::createDefaultContext();
        $template = new MailTemplateEntity();
        $template->setId(Uuid::randomHex());
        $template->setTranslated([
            'subject' => 'Recovery',
            'contentHtml' => 'BODY-SECRET',
            'contentPlain' => 'BODY-SECRET',
        ]);
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            'mail_template',
            1,
            new MailTemplateCollection([$template]),
            null,
            new Criteria(),
            $context,
        ));
        $mailService = static::createStub(AbstractMailService::class);
        $mailService->method('send')->willThrowException(new \RuntimeException('BODY-SECRET'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            'Could not send mail.',
            static::callback(static function (array $data): bool {
                static::assertSame('Recovery', $data['subject']);
                static::assertSame(\RuntimeException::class, $data['exceptionClass']);
                static::assertStringNotContainsString('BODY-SECRET', json_encode($data, \JSON_THROW_ON_ERROR));

                return true;
            }),
        );
        $sender = new SendMailTemplate($mailService, $repository, $logger, static::createStub(Connection::class));

        $sender->send(new SendMailTemplateParams(
            $template->getId(),
            Defaults::LANGUAGE_SYSTEM,
            [new Address('customer@example.com')],
            ['resetUrl' => 'BODY-SECRET', 'sensitive' => true],
        ), $context);
    }
}
