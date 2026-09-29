<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopwell\Core\Framework\Gateway\Context\Command\ChangeLanguageCommand;
use Shopwell\Core\Framework\Gateway\Context\Command\Handler\ChangeLanguageCommandHandler;
use Shopwell\Core\Framework\Gateway\GatewayException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChangeLanguageCommandHandler::class)]
class ChangeLanguageCommandHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $command = ChangeLanguageCommand::createFromPayload(['iso' => 'zh-CN']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $expectedCriteria = new Criteria();
        $expectedCriteria->addFilter(new EqualsFilter('locale.code', 'zh-CN'));

        $languageResult = new IdSearchResult(
            1,
            ['languageId' => ['primaryKey' => 'languageId', 'data' => []]],
            $expectedCriteria,
            $context->getContext()
        );

        $languageRepo = $this->createMock(EntityRepository::class);
        $languageRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedCriteria), $context->getContext())
            ->willReturn($languageResult);

        $handler = new ChangeLanguageCommandHandler($languageRepo);
        $handler->handle($command, $context, $parameters);

        static::assertSame(['languageId' => 'languageId'], $parameters);
    }

    public function testHandleWithLanguageNotFound(): void
    {
        $command = ChangeLanguageCommand::createFromPayload(['iso' => 'zh-CN']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $expectedCriteria = new Criteria();
        $expectedCriteria->addFilter(new EqualsFilter('locale.code', 'zh-CN'));

        $languageResult = new IdSearchResult(
            0,
            [],
            $expectedCriteria,
            $context->getContext()
        );

        $languageRepo = $this->createMock(EntityRepository::class);
        $languageRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedCriteria), $context->getContext())
            ->willReturn($languageResult);

        $this->expectExceptionObject(GatewayException::handlerException('Language with iso code {{ isoCode }} not found', ['isoCode' => 'zh-CN']));

        $handler = new ChangeLanguageCommandHandler($languageRepo);

        try {
            $handler->handle($command, $context, $parameters);
        } finally {
            static::assertSame([], $parameters);
        }
    }

    public function testSupportedCommands(): void
    {
        static::assertSame([ChangeLanguageCommand::class], ChangeLanguageCommandHandler::supportedCommands());
    }
}
