<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Gateway\Context\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Gateway\Context\Command\ChangeLanguageCommand;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ChangeLanguageCommand::class)]
class ChangeLanguageCommandTest extends TestCase
{
    public function testCommand(): void
    {
        $command = ChangeLanguageCommand::createFromPayload(['iso' => 'zh-CN']);

        static::assertSame('context_change-language', $command::getDefaultKeyName());
        static::assertSame('zh-CN', $command->iso);
    }
}
