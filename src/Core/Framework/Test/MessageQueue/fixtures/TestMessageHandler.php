<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Test\MessageQueue\fixtures;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final class TestMessageHandler
{
    public function __invoke(FooMessage|BarMessage $msg): void
    {
    }
}
