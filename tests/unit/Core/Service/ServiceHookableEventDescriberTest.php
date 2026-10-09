<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEventDescription;
use Shopwell\Core\Service\Event\CommercialLicenseProvidedEvent;
use Shopwell\Core\Service\ServiceHookableEventDescriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceHookableEventDescriber::class)]
class ServiceHookableEventDescriberTest extends TestCase
{
    public function testDescribeExposesServiceOnlyEventsSoTheyCanBeToldApartFromUnknownEvents(): void
    {
        $describer = new ServiceHookableEventDescriber();

        static::assertEquals($this->expectedDescriptions(), $describer->describe());
    }

    public function testDescribeForValidationExposesServiceOnlyEventsToEveryManifest(): void
    {
        $describer = new ServiceHookableEventDescriber();

        static::assertEquals($this->expectedDescriptions(), $describer->describeForValidation($this->createManifest()));
    }

    /**
     * @return list<HookableEventDescription>
     */
    private function expectedDescriptions(): array
    {
        return [
            new HookableEventDescription(
                CommercialLicenseProvidedEvent::NAME,
                'Fires when the current commercial license data is provided to services.',
                []
            ),
        ];
    }

    private function createManifest(): Manifest
    {
        return Manifest::createFromXml(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopwell-shop/shopwell/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
    <meta>
        <name>test-app</name>
        <label>Test app</label>
        <description>Test app</description>
        <author>Shopwell</author>
        <copyright>(c) by Shopwell</copyright>
        <version>1.0.0</version>
        <license>MIT</license>
    </meta>
</manifest>
XML);
    }
}
