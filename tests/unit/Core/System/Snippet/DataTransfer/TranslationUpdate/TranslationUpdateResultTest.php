<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Snippet\DataTransfer\TranslationUpdate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Snippet\DataTransfer\TranslationUpdate\TranslationUpdateResult;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationUpdateResult::class)]
class TranslationUpdateResultTest extends TestCase
{
    public function testExposesUpdatedAndSkippedLocales(): void
    {
        $result = new TranslationUpdateResult(['zh-CN', 'es-ES'], ['en-GB']);

        static::assertSame(['zh-CN', 'es-ES'], $result->updated);
        static::assertSame(['en-GB'], $result->skipped);
    }

    public function testDefaultsToEmptyLists(): void
    {
        $result = new TranslationUpdateResult();

        static::assertSame([], $result->updated);
        static::assertSame([], $result->skipped);
    }
}
