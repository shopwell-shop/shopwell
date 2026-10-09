<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopwell\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopwell\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopwell\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Country\CountryCollection;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentConfigLoaderFactory
{
    /**
     * @param array<string, string> $companyInfo
     */
    public static function create(DocumentTypeRegistry $registry, SystemConfigService $systemConfig, array $companyInfo = [], string $pageSize = 'A4'): DocumentConfigLoader
    {
        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());

        $config = new DocumentBaseConfigEntity();
        $config->setId(Uuid::randomHex());
        $config->setGlobal(true);
        $config->setPageSize($pageSize);
        $config->setPageOrientation('portrait');
        $config->setItemsPerPage(10);
        $config->setConfig([
            'companyName' => 'Example',
            'companyStreet' => 'Example Street 1',
            'companyZipcode' => '12345',
            'companyCity' => 'Example City',
            'companyCountryId' => $country->getId(),
            ...$companyInfo,
        ]);

        return new DocumentConfigLoader(
            new StaticEntityRepository([new DocumentBaseConfigCollection([$config])]),
            new StaticEntityRepository([new CountryCollection([$country])]),
            new StaticEntityRepository([new MediaCollection()]),
            $systemConfig,
            $registry,
        );
    }
}
