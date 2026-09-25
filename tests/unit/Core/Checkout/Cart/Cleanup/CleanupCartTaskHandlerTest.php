<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Cart\Cleanup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Checkout\Cart\AbstractCartPersister;
use Shopwell\Core\Checkout\Cart\Cleanup\CleanupCartTaskHandler;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CleanupCartTaskHandler::class)]
class CleanupCartTaskHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $cartPersister = $this->createMock(AbstractCartPersister::class);
        $cartPersister->expects($this->once())
            ->method('prune')
            ->with(30);

        $handler = new CleanupCartTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $cartPersister,
            30
        );

        $handler->run();
    }
}
