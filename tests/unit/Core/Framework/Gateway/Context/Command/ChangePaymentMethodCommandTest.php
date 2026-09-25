<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Gateway\Context\Command\ChangePaymentMethodCommand;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChangePaymentMethodCommand::class)]
class ChangePaymentMethodCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = ChangePaymentMethodCommand::createFromPayload(['technicalName' => 'app_test_payment']);

        static::assertSame('context_change-payment-method', $command::getDefaultKeyName());
        static::assertSame('app_test_payment', $command->technicalName);
    }
}
