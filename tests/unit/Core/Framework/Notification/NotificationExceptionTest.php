<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\ApiException;
use Shopwell\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Notification\NotificationException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NotificationException::class)]
class NotificationExceptionTest extends TestCase
{
    public function testAdminApiSourceExpected(): void
    {
        $exception = NotificationException::invalidAdminSource(SystemSource::class);

        static::assertSame(InvalidContextSourceException::class, $exception::class);
        static::assertSame(ApiException::API_INVALID_CONTEXT_SOURCE, $exception->getErrorCode());
    }
}
