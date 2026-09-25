---
title: Handling tax-free dependent values
issue: NEXT-14599
---
# Core
* Deprecated `taxFree` from `Shopwell/Core/System/Country/CountryEntity`, use `$customerTax->getEnabled()` instead.
* Deprecated `companyTaxFree` from `Shopwell/Core/System/Country/CountryEntity`, use `$companyTax->getEnabled()` instead.
* Added two new properties `customerTax`, `companyTax` to `Shopwell/Core/System/Country/CountryEntity`.
* Added two new fields `customerTax`, `companyTax` to `Shopwell/Core/System/Country/CountryDefinition`.
* Added two new columns `customer_tax` and `company_tax` to `country` table.
* Added `TaxFreeConfigField` to `Shopwell/Core/Framework/DataAbstractionLayer/Field` which stores `customer_tax` and `company_tax` in `country`.
* Added `TaxFreeConfig` to `Shopwell/Core/Framework/DataAbstractionLayer` which will be decoded by the corresponding `TaxFreeConfigField`.
* Added `TaxFreeConfigFieldSerializer` to `Shopwell/Core/Framework/DataAbstractionLayer/FieldSerializer`.
___
# Upgrade Information

## Change tax-free get and set in CountryEntity
Deprecated `taxFree` and `companyTaxFree` in `Shopwell/Core/System/Country/CountryEntity`, use `customerTax` and `companyTax` instead.

## If you are writing the fields directly, the tax-free of the country will be used:
### Before
```php
$countryRepository->create([
        [
            'id' => Uuid::randomHex(),
            'taxFree' => true,
            'companyTaxFree' => true,
            ...
        ]
    ],
    $context
);
```
### After 
```php
$countryRepository->create([
        [
            'id' => Uuid::randomHex(),
            'customerTax' => [
                'enabled' => true, // enabled is taxFree value in old version
                'currencyId' => $currencyId,
                'amount' => 0,
            ],
            'companyTax' => [
                'enabled' => true, // enabled is companyTaxFree value in old version
                'currencyId' => $currencyId,
                'amount' => 0,
            ],
            ...
        ]
    ],
    $context
);
```
## How to use the new getter and setter of tax-free in country:
### Before
* To get tax-free
```php
$country->getTaxFree();
$country->getCompanyTaxFree();
```
* To set tax-free
```php
$country->setTaxFree($isTaxFree);
$country->setCompanyTaxFree($isTaxFree);
```
### After
* To get tax-free
```php
$country->getCustomerTax()->getEnabled(); // enabled is taxFree value in old version
$country->getCompanyTax()->getEnabled(); // enabled is companyTaxFree value in old version
```
* To set tax-free
```php
// TaxFreeConfig::__construct(bool $enabled, string $currencyId, float $amount);
$country->setCusotmerTax(new TaxFreeConfig($isTaxFree, $currencyId, $amount));
$country->setCompanyTax(new TaxFreeConfig($isTaxFree, $currencyId, $amount));
```
