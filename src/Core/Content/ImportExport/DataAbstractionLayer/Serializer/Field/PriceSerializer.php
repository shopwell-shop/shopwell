<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use Shopwell\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopwell\Core\Content\ImportExport\Struct\Config;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Currency\CurrencyCollection;

#[Package('fundamentals@after-sales')]
class PriceSerializer extends FieldSerializer
{
    /**
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     *
     * @internal
     */
    public function __construct(private readonly EntityRepository $currencyRepository)
    {
    }

    public function serialize(Config $config, Field $entity, $prices): iterable
    {
        if (!$prices) {
            return;
        }

        $context = Context::createDefaultContext();

        $isoPrices = [];
        foreach ($prices as $price) {
            $price = $price instanceof Struct ? $price->jsonSerialize() : $price;
            $currencyId = $price['currencyId'];
            $currency = $this->mapToCurrencyIso($currencyId, $context);

            if (isset($price['listPrice']) && $price['listPrice'] instanceof Struct) {
                $price['listPrice'] = $price['listPrice']->jsonSerialize();
            }

            $isoPrices[$currency] = $price;
            if ($currencyId === Defaults::CURRENCY) {
                $isoPrices['DEFAULT'] = $isoPrices[$currency];
            }
        }

        yield $entity->getPropertyName() => $isoPrices;
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    public function deserialize(Config $config, Field $field, $record): ?array
    {
        $prices = [];

        if (!\is_array($record)) {
            return null;
        }

        $context = Context::createDefaultContext();

        foreach ($record as $currencyIso => $price) {
            $currency = $this->getCurrencyIdFromIso($currencyIso, $context);

            if ($currency === null || !$this->isValidPrice($price)) {
                continue;
            }

            $listPrice = null;
            if (isset($price['listPrice']) && $this->isValidPrice($price['listPrice'])) {
                $listPrice = new Price($currency, (float) $price['listPrice']['net'], (float) $price['listPrice']['gross'], (bool) ($price['listPrice']['linked'] ?? false));
            }

            $priceStruct = new Price($currency, (float) $price['net'], (float) $price['gross'], (bool) ($price['linked'] ?? false), $listPrice);
            $prices[$currency] = $priceStruct->jsonSerialize();

            if (isset($prices[$currency]['listPrice']) && $prices[$currency]['listPrice'] instanceof Price) {
                $prices[$currency]['listPrice'] = $prices[$currency]['listPrice']->jsonSerialize();
            }
        }

        if ($prices === []) {
            return null;
        }

        return $prices;
    }

    public function supports(Field $field): bool
    {
        return $field instanceof PriceField
            || ($field instanceof JsonField && $field->getPropertyName() === 'price');
    }

    public function setRegistry(SerializerRegistry $serializerRegistry): void
    {
    }

    /**
     * @param array<string, mixed> $price
     */
    private function isValidPrice(array $price): bool
    {
        return filter_var($price['net'] ?? null, \FILTER_VALIDATE_FLOAT) !== false
            && filter_var($price['gross'] ?? null, \FILTER_VALIDATE_FLOAT) !== false;
    }

    private function mapToCurrencyIso(string $currencyId, Context $context): string
    {
        $currency = $this->currencyRepository
            ->search(new Criteria([$currencyId]), $context)
            ->getEntities()
            ->first();

        return $currency ? $currency->getIsoCode() : $currencyId;
    }

    private function getCurrencyIdFromIso(string $iso, Context $context): ?string
    {
        if ($iso === 'DEFAULT') {
            return Defaults::CURRENCY;
        }

        if (Uuid::isValid($iso)) {
            $criteria = new Criteria([$iso]);
        } else {
            $criteria = (new Criteria())->addFilter(new EqualsFilter('isoCode', $iso));
        }

        return $this->currencyRepository->searchIds($criteria, $context)->firstId();
    }
}
