<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\Integration\Core\Framework\Api\ApiDefinition\EntityDefinition\SimpleDefinition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WriteTypeIntendException::class)]
class WriteTypeIntendExceptionTest extends TestCase
{
    public function testErrorSignalsBadRequest(): void
    {
        $exception = new WriteTypeIntendException(
            new SimpleDefinition(),
            'expected',
            'actual'
        );

        static::assertSame(400, $exception->getStatusCode());
    }

    public function testDoesHintAtCorrectApiUsage(): void
    {
        $exception = new WriteTypeIntendException(
            new SimpleDefinition(),
            UpdateCommand::class,
            InsertCommand::class
        );

        static::assertStringContainsString('Use POST method', $exception->getMessage());
    }
}
