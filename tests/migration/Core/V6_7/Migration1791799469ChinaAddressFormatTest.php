<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Migration\V6_7\Migration1791799469ChinaAddressFormat;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(Migration1791799469ChinaAddressFormat::class)]
class Migration1791799469ChinaAddressFormatTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const CHINA_ADDRESS_FORMAT = [
        ['address/company', 'symbol/dash', 'address/department'],
        ['address/name'],
        ['address/country_state', 'address/city', 'address/district'],
        ['address/street'],
        ['address/zipcode'],
    ];

    private const DEFAULT_ADDRESS_FORMAT = [
        ['address/company', 'symbol/dash', 'address/department'],
        ['address/name'],
        ['address/street'],
        ['address/zipcode', 'address/city'],
        ['address/country'],
    ];

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1791799469, (new Migration1791799469ChinaAddressFormat())->getCreationTimestamp());
    }

    public function testUpdateMakesChinesePostalCodeOptionalAndAlignsAddressFormat(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $countryId = $this->getCountryId($connection, 'CN');
        $this->seedCountry($connection, $countryId, 1, self::DEFAULT_ADDRESS_FORMAT);

        $migration = new Migration1791799469ChinaAddressFormat();
        $migration->update($connection);
        $migration->update($connection);

        static::assertSame(0, (int) $connection->fetchOne(
            'SELECT postal_code_required FROM country WHERE id = :countryId',
            ['countryId' => $countryId]
        ));

        $formats = $connection->fetchFirstColumn(
            'SELECT address_format FROM country_translation WHERE country_id = :countryId',
            ['countryId' => $countryId]
        );

        static::assertNotEmpty($formats);

        foreach ($formats as $format) {
            static::assertIsString($format);
            static::assertSame(
                self::CHINA_ADDRESS_FORMAT,
                json_decode($format, true, 512, \JSON_THROW_ON_ERROR)
            );
        }
    }

    public function testUpdateKeepsOtherCountriesUntouched(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $countryId = $this->getCountryId($connection, 'DE');
        $this->seedCountry($connection, $countryId, 1, self::DEFAULT_ADDRESS_FORMAT);

        (new Migration1791799469ChinaAddressFormat())->update($connection);

        static::assertSame(1, (int) $connection->fetchOne(
            'SELECT postal_code_required FROM country WHERE id = :countryId',
            ['countryId' => $countryId]
        ));

        $formats = $connection->fetchFirstColumn(
            'SELECT address_format FROM country_translation WHERE country_id = :countryId',
            ['countryId' => $countryId]
        );

        static::assertNotEmpty($formats);

        foreach ($formats as $format) {
            static::assertIsString($format);
            static::assertSame(
                self::DEFAULT_ADDRESS_FORMAT,
                json_decode($format, true, 512, \JSON_THROW_ON_ERROR)
            );
        }
    }

    private function getCountryId(Connection $connection, string $iso): string
    {
        $countryId = $connection->fetchOne(
            'SELECT id FROM country WHERE iso = :iso',
            ['iso' => $iso]
        );

        static::assertIsString($countryId);

        return $countryId;
    }

    /**
     * @param array<array<string>> $addressFormat
     */
    private function seedCountry(Connection $connection, string $countryId, int $postalCodeRequired, array $addressFormat): void
    {
        $connection->executeStatement(
            'UPDATE country SET postal_code_required = :postalCodeRequired WHERE id = :countryId',
            ['postalCodeRequired' => $postalCodeRequired, 'countryId' => $countryId]
        );

        $connection->executeStatement(
            'UPDATE country_translation SET address_format = :addressFormat WHERE country_id = :countryId',
            [
                'addressFormat' => json_encode($addressFormat, \JSON_THROW_ON_ERROR),
                'countryId' => $countryId,
            ]
        );
    }
}
