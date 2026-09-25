<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Language\Stubs;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Language\SalesChannelLanguageLoader;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
class StaticSalesChannelLanguageLoader extends SalesChannelLanguageLoader
{
    /**
     * @param array<string, list<string>> $languages
     */
    public function __construct(private readonly array $languages = [])
    {
    }

    /**
     * {@inheritDoc}
     */
    public function loadLanguages(): array
    {
        return $this->languages;
    }
}
