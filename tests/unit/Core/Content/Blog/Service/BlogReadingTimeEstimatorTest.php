<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Blog\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Blog\Service\BlogReadingTimeEstimator;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\HtmlSanitizer;

/**
 * The estimate is shown on the card and on the article itself, so the two numbers have to be computed
 * the same way. The cases pin down the edge that makes a chinese shop different from a latin one:
 * characters and words are counted separately, because counting a chinese paragraph as one word would
 * report "1 min" for an article that takes ten.
 *
 * @internal
 */
#[Package('discovery')]
#[CoversClass(BlogReadingTimeEstimator::class)]
class BlogReadingTimeEstimatorTest extends TestCase
{
    public function testAnEmptyTextIsStillOneMinute(): void
    {
        static::assertSame(1, $this->estimator('')->estimate('<p></p>'));
    }

    public function testATextWithoutWordsIsStillOneMinute(): void
    {
        static::assertSame(1, $this->estimator('   ')->estimate('<p> </p>'));
    }

    public function testALatinTextIsCountedInWords(): void
    {
        $text = implode(' ', array_fill(0, 400, 'word'));

        // 400 words at 200 words per minute
        static::assertSame(2, $this->estimator($text)->estimate('<p>ignored</p>'));
    }

    public function testAChineseTextIsCountedInCharacters(): void
    {
        $text = str_repeat('好', 700);

        // 700 characters at 350 characters per minute
        static::assertSame(2, $this->estimator($text)->estimate('<p>ignored</p>'));
    }

    public function testAMixedTextAddsBothScripts(): void
    {
        // 100 words are half a minute, 350 characters are a full one
        $text = implode(' ', array_fill(0, 100, 'word')) . ' ' . str_repeat('好', 350);

        static::assertSame(2, $this->estimator($text)->estimate('<p>ignored</p>'));
    }

    public function testAJustStartedMinuteIsRoundedUp(): void
    {
        // one word is not a full minute, but "0 min read" reads like a broken page
        static::assertSame(1, $this->estimator('word')->estimate('<p>ignored</p>'));
    }

    private function estimator(string $strippedText): BlogReadingTimeEstimator
    {
        $htmlSanitizer = static::createStub(HtmlSanitizer::class);
        $htmlSanitizer->method('stripTags')->willReturn($strippedText);

        return new BlogReadingTimeEstimator($htmlSanitizer);
    }
}
