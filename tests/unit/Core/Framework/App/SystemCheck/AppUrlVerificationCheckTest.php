<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\SystemCheck;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\SystemCheck\AppUrlVerificationCheck;
use Shopwell\Core\Framework\App\Url\AppUrlVerifier;
use Shopwell\Core\Framework\App\Url\VerificationState;
use Shopwell\Core\Framework\App\Url\VerificationStatus;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\SystemCheck\Check\Category;
use Shopwell\Core\Framework\SystemCheck\Check\Status;
use Shopwell\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppUrlVerificationCheck::class)]
class AppUrlVerificationCheckTest extends TestCase
{
    /**
     * @return iterable<string, array{VerificationStatus, Status, bool}>
     */
    public static function verificationResults(): iterable
    {
        yield 'pass' => [VerificationStatus::PASS, Status::OK, true];
        yield 'soft fail' => [VerificationStatus::SOFT_FAIL, Status::WARNING, false];
        yield 'hard fail' => [VerificationStatus::HARD_FAIL, Status::ERROR, false];
    }

    #[DataProvider('verificationResults')]
    public function testItReportsAFreshVerification(VerificationStatus $verificationStatus, Status $expectedStatus, bool $expectedHealthy): void
    {
        $verifier = $this->createMock(AppUrlVerifier::class);
        $verifier->expects($this->once())
            ->method('forceVerify')
            ->willReturn(new VerificationState(
                'https://example.com',
                $verificationStatus,
                1,
                new \DateTimeImmutable('2025-01-01 12:00:00'),
                'Unexpected response from APP_URL verification endpoint',
                404,
            ));

        $result = (new AppUrlVerificationCheck($verifier))->run();

        static::assertSame('AppUrlVerification', $result->name);
        static::assertSame($expectedStatus, $result->status);
        static::assertSame($expectedHealthy, $result->healthy);
        static::assertSame([
            'appUrl' => 'https://example.com',
            'info' => 'Unexpected response from APP_URL verification endpoint',
            'statusCode' => 404,
        ], $result->extra);
    }

    public function testItOnlyRunsInLongRunningChecksAfterTheRollout(): void
    {
        $check = new AppUrlVerificationCheck(static::createStub(AppUrlVerifier::class));

        static::assertSame(Category::EXTERNAL, $check->category());
        static::assertTrue($check->allowedToRunIn(SystemCheckExecutionContext::CLI));
        static::assertTrue($check->allowedToRunIn(SystemCheckExecutionContext::RECURRENT));
        static::assertFalse($check->allowedToRunIn(SystemCheckExecutionContext::WEB));
        static::assertFalse($check->allowedToRunIn(SystemCheckExecutionContext::PRE_ROLLOUT));
    }
}
