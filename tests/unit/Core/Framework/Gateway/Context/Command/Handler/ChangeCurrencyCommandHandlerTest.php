<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Gateway\Context\Command\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopwell\Core\Framework\Gateway\Context\Command\ChangeCurrencyCommand;
use Shopwell\Core\Framework\Gateway\Context\Command\Handler\ChangeCurrencyCommandHandler;
use Shopwell\Core\Framework\Gateway\GatewayException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChangeCurrencyCommandHandler::class)]
class ChangeCurrencyCommandHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $command = ChangeCurrencyCommand::createFromPayload(['iso' => 'EUR']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $expectedCriteria = new Criteria();
        $expectedCriteria->addFilter(new EqualsFilter('isoCode', 'EUR'));

        $currencyResult = new IdSearchResult(
            1,
            ['currencyId' => ['primaryKey' => 'currencyId', 'data' => []]],
            $expectedCriteria,
            $context->getContext()
        );

        $currencyRepo = $this->createMock(EntityRepository::class);
        $currencyRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedCriteria), $context->getContext())
            ->willReturn($currencyResult);

        $handler = new ChangeCurrencyCommandHandler($currencyRepo);

        $handler->handle($command, $context, $parameters);

        static::assertSame(['currencyId' => 'currencyId'], $parameters);
    }

    public function testHandleWithNotCurrencyNotFound(): void
    {
        $command = ChangeCurrencyCommand::createFromPayload(['iso' => 'EUR']);
        $context = Generator::generateSalesChannelContext();
        $parameters = [];

        $expectedCriteria = new Criteria();
        $expectedCriteria->addFilter(new EqualsFilter('isoCode', 'EUR'));

        $currencyResult = new IdSearchResult(
            0,
            [],
            $expectedCriteria,
            $context->getContext()
        );

        $currencyRepo = $this->createMock(EntityRepository::class);
        $currencyRepo
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::equalTo($expectedCriteria), $context->getContext())
            ->willReturn($currencyResult);

        $this->expectExceptionObject(GatewayException::handlerException('Currency with iso code {{ isoCode }} not found', ['isoCode' => 'EUR']));

        $handler = new ChangeCurrencyCommandHandler($currencyRepo);

        try {
            $handler->handle($command, $context, $parameters);
        } finally {
            static::assertSame([], $parameters);
        }
    }

    public function testSupportedCommands(): void
    {
        static::assertSame([ChangeCurrencyCommand::class], ChangeCurrencyCommandHandler::supportedCommands());
    }
}
