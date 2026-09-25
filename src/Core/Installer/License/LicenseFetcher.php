<?php declare(strict_types=1);

namespace Shopwell\Core\Installer\License;

use GuzzleHttp\Client;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Installer\Subscriber\InstallerLocaleListener;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
class LicenseFetcher
{
    /**
     * @param string[] $tosUrls
     */
    public function __construct(
        private readonly Client $guzzle,
        private readonly array $tosUrls
    ) {
    }

    public function fetch(Request $request): string
    {
        $locale = $request->attributes->get('_locale');
        $uri = $this->tosUrls[$locale] ?? $this->tosUrls[InstallerLocaleListener::FALLBACK_LOCALE];

        $response = $this->guzzle->get($uri);

        return $response->getBody()->getContents();
    }
}
