---
title: Unify setup scripts
issue: NEXT-17218
---
# Core
* Added `\Shopwell\Core\Maintenance\Maintenance` bundle
  * Added `\Shopwell\Core\Maintenance\System\Command\SystemGenerateAppSecretCommand`
  * Deprecated `\Shopwell\Core\DevOps\System\Command\SystemGenerateAppSecretCommand`, use `\Shopwell\Core\Maintenance\System\Command\SystemGenerateAppSecretCommand` instead
  * Added `\Shopwell\Core\Maintenance\System\Command\SystemGenerateJwtSecretCommand`
  * Deprecated `\Shopwell\Core\DevOps\System\Command\SystemGenerateJwtSecretCommand`, use `\Shopwell\Core\Maintenance\System\Command\SystemGenerateJwtSecretCommand` instead
  * Added `\Shopwell\Core\Maintenance\System\Command\SystemInstallCommand`
  * Deprecated `\Shopwell\Core\DevOps\System\Command\SystemInstallCommand`, use `\Shopwell\Core\Maintenance\System\Command\SystemInstallCommand` instead
  * Added `\Shopwell\Core\Maintenance\System\Command\SystemSetupCommand`
  * Deprecated `\Shopwell\Core\DevOps\System\Command\SystemSetupCommand`, use `\Shopwell\Core\Maintenance\System\Command\SystemSetupCommand` instead
  * Added `\Shopwell\Core\Maintenance\System\Command\SystemUpdateFinishCommand`
  * Deprecated `\Shopwell\Core\DevOps\System\Command\SystemUpdateFinishCommand`, use `\Shopwell\Core\Maintenance\System\Command\SystemUpdateFinishCommand` instead
  * Added `\Shopwell\Core\Maintenance\System\Command\SystemUpdatePrepareCommand`
  * Deprecated `\Shopwell\Core\DevOps\System\Command\SystemUpdatePrepareCommand`, use `\Shopwell\Core\Maintenance\System\Command\SystemUpdatePrepareCommand` instead
  * Added `\Shopwell\Core\Maintenance\SalesChannel\Command\SalesChannelCreateCommand`
  * Deprecated `\Shopwell\Core\System\SalesChannel\Command\SalesChannelCreateCommand`, use `\Shopwell\Core\Maintenance\SalesChannel\Command\SalesChannelCreateCommand` instead
  * Added `\Shopwell\Core\Maintenance\SalesChannel\Command\SalesChannelListCommand`
  * Deprecated `\Shopwell\Core\System\SalesChannel\Command\SalesChannelListCommand`, use `\Shopwell\Core\Maintenance\SalesChannel\Command\SalesChannelListCommand` instead
  * Added `\Shopwell\Core\Maintenance\SalesChannel\Command\SalesChannelMaintenanceDisableCommand`
  * Deprecated `\Shopwell\Core\System\SalesChannel\Command\SalesChannelMaintenanceDisableCommand`, use `\Shopwell\Core\Maintenance\SalesChannel\Command\SalesChannelMaintenanceDisableCommand` instead
  * Added `\Shopwell\Core\Maintenance\SalesChannel\Command\SalesChannelMaintenanceEnableCommand`
  * Deprecated `\Shopwell\Core\System\SalesChannel\Command\SalesChannelMaintenanceEnableCommand`, use `\Shopwell\Core\Maintenance\SalesChannel\Command\SalesChannelMaintenanceEnableCommand` instead
  * Added `\Shopwell\Core\Maintenance\SalesChannel\Service\SalesChannelCreator`
  * Added `\Shopwell\Core\Maintenance\System\Command\SystemConfigureShopCommand`
  * Added `\Shopwell\Core\Maintenance\System\Service\DatabaseConnectionFactory`
  * Added `\Shopwell\Core\Maintenance\System\Service\DatabaseInitializer`
  * Added `\Shopwell\Core\Maintenance\System\Service\JwtCertificateGenerator`
  * Added `\Shopwell\Core\Maintenance\System\Service\ShopConfigurator`
  * Added `\Shopwell\Core\Maintenance\User\Command\UserChangePasswordCommand`
  * Deprecated `\Shopwell\Core\System\User\Command\UserChangePasswordCommand`, use `\Shopwell\Core\Maintenance\User\Command\UserChangePasswordCommand` instead
  * Added `\Shopwell\Core\Maintenance\User\Command\UserCreateCommand`
  * Deprecated `\Shopwell\Core\System\User\Command\UserCreateCommand`, use `\Shopwell\Core\Maintenance\User\Command\UserCreateCommand` instead
  * Added `\Shopwell\Core\Maintenance\User\Service\UserProvisioner`
  * Deprecated `\Shopwell\Core\System\User\Service\UserProvisioner`, use `\Shopwell\Core\Maintenance\User\Service\UserProvisioner` instead
* Changed `\Shopwell\Core\Framework\Adapter\Asset\AssetInstallCommand` to additionally install assets from the Recovery bundle if it is present
* Added `\Shopwell\Core\Framework\Plugin\Util\AssetService::copyRecoveryAssets()` to copy assets of the recovery bundle to the public folder
___
# Storefront
* Changed `\Shopwell\Storefront\Framework\Command\SalesChannelCreateStorefrontCommand` to add `snippetSetId`-parameter and to no longer ignore the `navigationCategoryId`-parameter
___
# Upgrade Information

## Added Maintenance-Bundle

A maintenance bundle was added to have one place where CLI-commands und Utils are located, that help with the ongoing maintenance of the shop.

To load enable that bundle, you should add the following line to your `/config/bundles.php` file, because from 6.5.0 onward the bundle will not be loaded automatically anymore:
```php
return [
   ...
   Shopwell\Core\Maintenance\Maintenance::class => ['all' => true],
];
```
In that refactoring we moved some CLI commands into that new bundle and deprecated the old command classes. The new commands are marked as internal, as you should not rely on the PHP interface of those commands, only on the CLI API.

Additionally we've moved the `UserProvisioner` service from the `Core/System/User` namespace, to the `Core/Maintenance/User` namespace, make sure you use the service from the new location.
Before:
```php
use Shopwell\Core\System\User\Service\UserProvisioner;
```
After:
```php
use Shopwell\Core\Maintenance\User\Service\UserProvisioner;
```
