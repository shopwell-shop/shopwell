<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroupRegistrationSalesChannel\CustomerGroupRegistrationSalesChannelDefinition;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationDefinition;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryDefinition;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerTag\CustomerTagDefinition;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistDefinition;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerWishlistProduct\CustomerWishlistProductDefinition;
use Shopwell\Core\Checkout\Customer\Api\ConvertGuestController;
use Shopwell\Core\Checkout\Customer\Api\CustomerGroupRegistrationActionController;
use Shopwell\Core\Checkout\Customer\CleanupCustomerRecoveryTask;
use Shopwell\Core\Checkout\Customer\CleanupCustomerRecoveryTaskHandler;
use Shopwell\Core\Checkout\Customer\Command\DeleteUnusedGuestCustomersCommand;
use Shopwell\Core\Checkout\Customer\Cookie\WishlistCookieCollectListener;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\CustomerValueResolver;
use Shopwell\Core\Checkout\Customer\DataAbstractionLayer\CustomerIndexer;
use Shopwell\Core\Checkout\Customer\DataAbstractionLayer\CustomerWishlistProductExceptionHandler;
use Shopwell\Core\Checkout\Customer\DeleteUnusedGuestCustomerHandler;
use Shopwell\Core\Checkout\Customer\DeleteUnusedGuestCustomerService;
use Shopwell\Core\Checkout\Customer\DeleteUnusedGuestCustomerTask;
use Shopwell\Core\Checkout\Customer\ImitateCustomerTokenGenerator;
use Shopwell\Core\Checkout\Customer\Password\LegacyEncoder\Md5;
use Shopwell\Core\Checkout\Customer\Password\LegacyEncoder\Sha256;
use Shopwell\Core\Checkout\Customer\Password\LegacyPasswordVerifier;
use Shopwell\Core\Checkout\Customer\SalesChannel\AccountNewsletterRecipientRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopwell\Core\Checkout\Customer\SalesChannel\AddWishlistProductRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\ChangeEmailRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\ChangeLanguageRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\ChangePasswordRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\ConvertGuestRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerGroupRegistrationSettingsRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\CustomerRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\DeleteAddressRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\DeleteCustomerRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\DownloadRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\ImitateCustomerRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\ListAddressRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\LoadWishlistRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\MergeWishlistProductRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\RegisterConfirmRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\RemoveWishlistProductRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\ResetPasswordRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\SalesChannelCustomerAddressDefinition;
use Shopwell\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\SwitchDefaultAddressRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\UpsertAddressRoute;
use Shopwell\Core\Checkout\Customer\Service\DoubleOptInService;
use Shopwell\Core\Checkout\Customer\Service\GuestAuthenticator;
use Shopwell\Core\Checkout\Customer\Service\ProductReviewCountService;
use Shopwell\Core\Checkout\Customer\Subscriber\AddressHashSubscriber;
use Shopwell\Core\Checkout\Customer\Subscriber\CustomerAddressSubscriber;
use Shopwell\Core\Checkout\Customer\Subscriber\CustomerBeforeDeleteSubscriber;
use Shopwell\Core\Checkout\Customer\Subscriber\CustomerChangePasswordSubscriber;
use Shopwell\Core\Checkout\Customer\Subscriber\CustomerEmailUniqueSubscriber;
use Shopwell\Core\Checkout\Customer\Subscriber\CustomerFlowEventsSubscriber;
use Shopwell\Core\Checkout\Customer\Subscriber\CustomerLanguageSalesChannelSubscriber;
use Shopwell\Core\Checkout\Customer\Subscriber\CustomerLogoutSubscriber;
use Shopwell\Core\Checkout\Customer\Subscriber\CustomerMetaFieldSubscriber;
use Shopwell\Core\Checkout\Customer\Subscriber\CustomerRemoteAddressSubscriber;
use Shopwell\Core\Checkout\Customer\Subscriber\CustomerSalutationSubscriber;
use Shopwell\Core\Checkout\Customer\Subscriber\CustomerTokenSubscriber;
use Shopwell\Core\Checkout\Customer\Subscriber\ProductReviewSubscriber;
use Shopwell\Core\Checkout\Customer\Validation\AddressValidationFactory;
use Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUniqueValidator;
use Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerPasswordMatchesValidator;
use Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentificationValidator;
use Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerZipCodeValidator;
use Shopwell\Core\Checkout\Customer\Validation\CustomerEmailUniqueChecker;
use Shopwell\Core\Checkout\Customer\Validation\CustomerProfileValidationFactory;
use Shopwell\Core\Checkout\Customer\Validation\CustomerValidationFactory;
use Shopwell\Core\Checkout\Customer\Validation\PasswordValidationFactory;
use Shopwell\Core\Content\Media\File\DownloadResponseGenerator;
use Shopwell\Core\Content\Newsletter\DataAbstractionLayer\Indexing\CustomerNewsletterSalesChannelsUpdater;
use Shopwell\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Shopwell\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopwell\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Routing\SessionContextTokenAccessor;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopwell\Core\System\SalesChannel\Context\CartRestorer;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('customer.account_types', [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            CustomerEntity::ACCOUNT_TYPE_PRIVATE,
        ]);

    $services = $containerConfigurator->services();

    $services->set(CustomerDefinition::class)
        ->tag('shopwell.entity.definition')
        ->tag('shopwell.entity.hookable');

    $services->set(CustomerGroupTranslationDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CustomerAddressDefinition::class)
        ->tag('shopwell.entity.definition')
        ->tag('shopwell.entity.hookable');

    $services->set(CustomerRecoveryDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CustomerGroupDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CustomerGroupRegistrationSalesChannelDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CustomerTagDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(AccountService::class)
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(LegacyPasswordVerifier::class),
            service(SwitchDefaultAddressRoute::class),
            service(CartRestorer::class),
            service(DoubleOptInService::class),
            service(ClockInterface::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(DoubleOptInService::class)
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service('sales_channel_domain.repository'),
            service(ClockInterface::class),
        ]);

    $services->set(AddressValidationFactory::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(CustomerProfileValidationFactory::class)
        ->args([
            service(SystemConfigService::class),
            param('customer.account_types'),
        ]);

    $services->set(PasswordValidationFactory::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(CustomerValidationFactory::class)
        ->args([
            service(CustomerProfileValidationFactory::class),
        ]);

    $services->set(CustomerEmailUniqueChecker::class)
        ->args([
            service(Connection::class),
            service(SystemConfigService::class),
        ]);

    $services->set(CustomerEmailUniqueValidator::class)
        ->args([
            service(CustomerEmailUniqueChecker::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(CustomerPasswordMatchesValidator::class)
        ->args([
            service(AccountService::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(CustomerVatIdentificationValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(CustomerZipCodeValidator::class)
        ->args([
            service('country.repository'),
        ])
        ->tag('validator.constraint_validator');

    $services->set(Md5::class)
        ->tag('shopwell.legacy_encoder');

    $services->set(Sha256::class)
        ->tag('shopwell.legacy_encoder');

    $services->set(LegacyPasswordVerifier::class)
        ->args([
            tagged_iterator('shopwell.legacy_encoder'),
        ]);

    $services->set(AddressHashSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(CustomerMetaFieldSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ProductReviewCountService::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(GuestAuthenticator::class);

    $services->set(ProductReviewSubscriber::class)
        ->args([
            service(ProductReviewCountService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerRemoteAddressSubscriber::class)
        ->args([
            service(Connection::class),
            service('request_stack'),
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerTokenSubscriber::class)
        ->args([
            service(SalesChannelContextPersister::class),
            service('request_stack'),
            service(SessionContextTokenAccessor::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerChangePasswordSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerFlowEventsSubscriber::class)
        ->args([
            service(EventDispatcherInterface::class),
            service(SalesChannelContextRestorer::class),
            service(CustomerIndexer::class),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerLogoutSubscriber::class)
        ->args([
            service(RequestStack::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(LoginRoute::class)
        ->public()
        ->args([
            service(AccountService::class),
            service(RequestStack::class),
            service(RateLimiter::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(LogoutRoute::class)
        ->public()
        ->args([
            service(SalesChannelContextPersister::class),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service(CartService::class),
            service(SalesChannelContextService::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(SendPasswordRecoveryMailRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('customer_recovery.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(SystemConfigService::class),
            service(RequestStack::class),
            service('shopwell.rate_limiter'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ResetPasswordRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('customer_recovery.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(RequestStack::class),
            service('shopwell.rate_limiter'),
            service(PasswordValidationFactory::class),
            service(ClockInterface::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CustomerRecoveryIsExpiredRoute::class)
        ->public()
        ->args([
            service('customer_recovery.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(ClockInterface::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ChangeCustomerProfileRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(CustomerProfileValidationFactory::class),
            service(StoreApiCustomFieldMapper::class),
            service('salutation.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ChangePasswordRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service(DataValidator::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ChangeEmailRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service('customer_recovery.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ChangeLanguageRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ConvertGuestRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(PasswordValidationFactory::class),
            service(RequestStack::class),
            service('shopwell.rate_limiter'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CustomerRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(DeleteCustomerRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(RegisterRoute::class)
        ->public()
        ->args([
            service('event_dispatcher'),
            service(NumberRangeValueGeneratorInterface::class),
            service(DataValidator::class),
            service(CustomerValidationFactory::class),
            service(AddressValidationFactory::class),
            service(SystemConfigService::class),
            service('customer.repository'),
            service(SalesChannelContextPersister::class),
            service('sales_channel.country.repository'),
            service(Connection::class),
            service(SalesChannelContextService::class),
            service(StoreApiCustomFieldMapper::class),
            service('salutation.repository'),
            service(PasswordValidationFactory::class),
            service(DoubleOptInService::class),
            service(CustomerNewsletterSalesChannelsUpdater::class),
            service(ClockInterface::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(RegisterConfirmRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(SalesChannelContextPersister::class),
            service(SalesChannelContextService::class),
            service(ClockInterface::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ListAddressRoute::class)
        ->public()
        ->args([
            service('sales_channel.customer_address.repository'),
            service('event_dispatcher'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(UpsertAddressRoute::class)
        ->public()
        ->args([
            service('customer_address.repository'),
            service('sales_channel.customer_address.repository'),
            service(DataValidator::class),
            service('event_dispatcher'),
            service(AddressValidationFactory::class),
            service(SystemConfigService::class),
            service(StoreApiCustomFieldMapper::class),
            service('salutation.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(DeleteAddressRoute::class)
        ->public()
        ->args([
            service('customer_address.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(SwitchDefaultAddressRoute::class)
        ->public()
        ->args([
            service('customer_address.repository'),
            service('customer.repository'),
            service(EventDispatcherInterface::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CustomerGroupRegistrationSettingsRoute::class)
        ->public()
        ->args([
            service('customer_group.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(SalesChannelCustomerAddressDefinition::class)
        ->tag('shopwell.sales_channel.entity.definition');

    $services->set(CustomerIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('customer.repository'),
            service(ManyToManyIdFieldUpdater::class),
            service(CustomerNewsletterSalesChannelsUpdater::class),
            service('event_dispatcher'),
        ])
        ->tag('shopwell.entity_indexer', ['priority' => 100]);

    $services->set(ConvertGuestController::class)
        ->public()
        ->args([
            service('customer.repository'),
            service(SalesChannelContextService::class),
            service(ConvertGuestRoute::class),
            service(SendPasswordRecoveryMailRoute::class),
            service(Connection::class),
        ]);

    $services->set(CustomerGroupRegistrationActionController::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('customer_group.repository'),
            service('event_dispatcher'),
            service(SalesChannelContextRestorer::class),
        ]);

    $services->set(CustomerWishlistDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(CustomerWishlistProductDefinition::class)
        ->tag('shopwell.entity.definition');

    $services->set(LoadWishlistRoute::class)
        ->public()
        ->args([
            service('customer_wishlist.repository'),
            service('sales_channel.product.repository'),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service(ProductCloseoutFilterFactory::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(AddWishlistProductRoute::class)
        ->public()
        ->args([
            service('customer_wishlist.repository'),
            service('sales_channel.product.repository'),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(RemoveWishlistProductRoute::class)
        ->public()
        ->args([
            service('customer_wishlist.repository'),
            service('customer_wishlist_product.repository'),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CustomerWishlistProductExceptionHandler::class)
        ->tag('shopwell.dal.exception_handler');

    $services->set(MergeWishlistProductRoute::class)
        ->public()
        ->args([
            service('customer_wishlist.repository'),
            service('sales_channel.product.repository'),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(Connection::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(WishlistCookieCollectListener::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(CustomerValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1002]);

    $services->set(AccountNewsletterRecipientRoute::class)
        ->public()
        ->args([
            service('sales_channel.newsletter_recipient.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ImitateCustomerRoute::class)
        ->public()
        ->args([
            service(AccountService::class),
            service(ImitateCustomerTokenGenerator::class),
            service(LogoutRoute::class),
            service(SalesChannelContextFactory::class),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(DeleteUnusedGuestCustomerService::class)
        ->args([
            service('customer.repository'),
            service(SystemConfigService::class),
        ]);

    $services->set(ImitateCustomerTokenGenerator::class)
        ->args([
            env('APP_SECRET'),
            service('shopwell.jwt_config'),
            service(DataValidator::class),
            service(ClockInterface::class),
        ]);

    $services->set(DeleteUnusedGuestCustomersCommand::class)
        ->args([
            service(DeleteUnusedGuestCustomerService::class),
        ])
        ->tag('console.command');

    $services->set(DeleteUnusedGuestCustomerTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(DeleteUnusedGuestCustomerHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(DeleteUnusedGuestCustomerService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(CleanupCustomerRecoveryTask::class)
        ->tag('shopwell.scheduled.task');

    $services->set(CleanupCustomerRecoveryTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(CustomerBeforeDeleteSubscriber::class)
        ->args([
            service('customer.repository'),
            service('sales_channel.repository'),
            service(SalesChannelContextService::class),
            service('event_dispatcher'),
            service(JsonEntityEncoder::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerLanguageSalesChannelSubscriber::class)
        ->args([
            service('sales_channel.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerEmailUniqueSubscriber::class)
        ->args([
            service(Connection::class),
            service(CustomerEmailUniqueChecker::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DownloadRoute::class)
        ->public()
        ->args([
            service('order_line_item_download.repository'),
            service(DownloadResponseGenerator::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CustomerSalutationSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerAddressSubscriber::class)
        ->tag('kernel.event_subscriber');
};
