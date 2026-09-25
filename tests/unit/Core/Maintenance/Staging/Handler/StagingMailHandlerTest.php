<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Maintenance\Staging\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Mail\Service\MailSender;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopwell\Core\Maintenance\Staging\Handler\StagingMailHandler;
use Shopwell\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StagingMailHandler::class)]
class StagingMailHandlerTest extends TestCase
{
    public function testDisabled(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingMailHandler($config);

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            static::createStub(SymfonyStyle::class),
            false,
            []
        ));

        static::assertNull($config->get(MailSender::DISABLE_MAIL_DELIVERY));
    }

    public function testEnabled(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingMailHandler($config);

        $handler(new SetupStagingEvent(
            Context::createDefaultContext(),
            static::createStub(SymfonyStyle::class),
            true,
            []
        ));

        static::assertTrue($config->get(MailSender::DISABLE_MAIL_DELIVERY));
    }
}
