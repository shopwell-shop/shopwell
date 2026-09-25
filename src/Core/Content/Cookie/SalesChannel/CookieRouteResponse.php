<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cookie\SalesChannel;

use Shopwell\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{elements: CookieGroupCollection, hash: string, languageId: string}>>
 */
#[Package('discovery')]
class CookieRouteResponse extends StoreApiResponse
{
    public function __construct(
        CookieGroupCollection $cookieGroups,
        string $hash,
        string $languageId = '',
    ) {
        parent::__construct(new ArrayStruct([
            'elements' => $cookieGroups,
            'hash' => $hash,
            'languageId' => $languageId,
        ], 'cookie_groups_hash'));
    }

    public function getCookieGroups(): CookieGroupCollection
    {
        return $this->object->get('elements');
    }

    public function getHash(): string
    {
        return $this->object->get('hash');
    }

    public function getLanguageId(): string
    {
        return $this->object->get('languageId');
    }
}
