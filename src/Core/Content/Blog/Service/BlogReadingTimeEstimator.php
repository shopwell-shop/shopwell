<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Blog\Service;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\HtmlSanitizer;

/**
 * The one place that answers "how long does this take to read".
 *
 * The estimate travels into three templates - the listing card, the article header and the related
 * entries - and a reader compares the numbers. Computing it three times would mean three chances to
 * disagree, so the arithmetic lives here and every caller asks for it.
 *
 * Two scripts are counted separately and added: a chinese reader covers characters and a latin reader
 * words, and treating a chinese text as one long word would report one minute for an article that
 * takes ten. The result is never zero - "0 min read" reads like a broken page.
 *
 * @final
 */
#[Package('discovery')]
class BlogReadingTimeEstimator
{
    /**
     * Words per minute for a latin text.
     */
    private const WORDS_PER_MINUTE = 200;

    /**
     * Characters per minute for a chinese text. Far lower than the word rate, because a character
     * carries more meaning than a letter does.
     */
    private const CHARS_PER_MINUTE = 350;

    /**
     * @internal
     */
    public function __construct(private readonly HtmlSanitizer $htmlSanitizer)
    {
    }

    /**
     * Reading time in minutes, at least one.
     */
    public function estimate(string $content): int
    {
        $text = preg_replace('/\s+/u', ' ', $this->htmlSanitizer->stripTags($content));

        if ($text === null) {
            return 1;
        }

        $text = trim($text);

        if ($text === '') {
            return 1;
        }

        $chineseCharacters = preg_replace('/[^\x{4e00}-\x{9fff}]/u', '', $text) ?? '';
        $latinText = preg_replace('/[\x{4e00}-\x{9fff}]/u', ' ', $text) ?? '';

        $chineseCount = mb_strlen($chineseCharacters, 'UTF-8');
        $latinWords = \count(preg_split('/\s+/u', $latinText, -1, \PREG_SPLIT_NO_EMPTY) ?: []);

        $minutes = $latinWords / self::WORDS_PER_MINUTE + $chineseCount / self::CHARS_PER_MINUTE;

        return max(1, (int) ceil($minutes));
    }
}
