<?php declare(strict_types=1);

return [
    'filePatterns' => [
        '**/Test/**', // Testing
        '**/src/Core/Framework/Update/**', // Updater
        '**/src/Core/TestBootstrapper.php', // Testing
        '**/src/Core/Framework/Demodata/Faker/Commerce.php', // dev dependency
        '**/src/Core/DevOps/StaticAnalyze/**', // dev dependency
        '**/src/Core/Profiling/Doctrine/BacktraceDebugDataHolder.php', // dev dependency
        '**/src/Core/Migration/Traits/MigrationUntouchedDbTestTrait.php', // Test code in prod
        '**src/Core/Framework/Script/ServiceStubs.php', // never intended to be extended
        '**/tests/unit/Core/DevOps/Docs/Script/_fixtures/**', // Testing
        '**/src/Core/Framework/App/AppException.php', // intended to be internal
    ],
    'errors' => [
        // Don't complain about doctrine library changes
        'Doctrine\\\\DBAL',

        // Will be typed in Symfony 8 (maybe)
        preg_quote('Symfony\Component\Console\Command\Command#configure() changed from no type to void', '/'),

        // False positive, when an object extends Symfony Command and has its own constructor
        '.* was added to Method __construct\(\) of class Symfony\\\\Component\\\\Console\\\\Command\\\\Command',
        preg_quote('Symfony\Component\Console\Command\Command#__construct()', '/'),

        // Cannot be inspected through reflection https://github.com/Roave/BetterReflection/issues/1376
        'An enum expression .* is not supported in .*',

        // Expected to be appended when a new event is added
        preg_quote('Value of constant Shopwell\Core\Framework\Webhook\Hookable', '/'),

        // Expected to be appended when a new default admin user privilege is added; existing entries are never removed
        preg_quote('Value of constant Shopwell\Core\Framework\Api\Context\AdminApiSource::DEFAULT_USER_PRIVILEGES', '/'),

        // Expected to be appended when a new internal route default is added; existing entries are never removed
        preg_quote('Value of constant Shopwell\Core\PlatformRequest::ATTRIBUTE_INTERNAL_ROUTE_PARAMS', '/'),

        // swagger-php 6.4 is required for OpenAPI 3.2 generation. The reported
        // BC changes are in the third-party OpenApi\Analysis API. Extensions
        // that only define OpenAPI annotations/attributes continue to work; code
        // using swagger-php programmatically has a documented migration path in
        // RELEASE_INFO-6.7.md / UPGRADE-6.7.md.
        preg_quote('REMOVED: Method OpenApi\Analysis#getSchemaForSource() was removed', '/'),
        preg_quote('REMOVED: Method OpenApi\Analysis#getContext() was removed', '/'),
        preg_quote('REMOVED: Method OpenApi\Analysis#process() was removed', '/'),
        preg_quote('CHANGED: Type of property OpenApi\Analysis#$annotations changed from having no type to SplObjectStorage', '/'),
        preg_quote('CHANGED: Type of property OpenApi\Analysis#$classes changed from having no type to array', '/'),
        preg_quote('CHANGED: Type of property OpenApi\Analysis#$interfaces changed from having no type to array', '/'),
        preg_quote('CHANGED: Type of property OpenApi\Analysis#$traits changed from having no type to array', '/'),
        preg_quote('CHANGED: Type of property OpenApi\Analysis#$enums changed from having no type to array', '/'),
        preg_quote('CHANGED: Type of property OpenApi\Analysis#$openapi changed from having no type to OpenApi\Annotations\OpenApi|null', '/'),
        preg_quote('CHANGED: Type of property OpenApi\Analysis#$context changed from having no type to OpenApi\Context|null', '/'),
        preg_quote('CHANGED: The parameter $annotation of OpenApi\Analysis#addAnnotation() changed from object', '/'),
        preg_quote('CHANGED: The parameter $class of OpenApi\Analysis#getSuperClasses() changed from string to string|null', '/'),
        preg_quote('CHANGED: The parameter $class of OpenApi\Analysis#getInterfacesOfClass() changed from string to string|null', '/'),
        preg_quote('CHANGED: The parameter $source of OpenApi\Analysis#getTraitsOfClass() changed from string to string|null', '/'),
        preg_quote('ADDED: Parameter sourceClass was added to Method getAnnotationForSource() of class OpenApi\Analysis', '/'),
        preg_quote('CHANGED: Parameter 1 of OpenApi\Analysis#getAnnotationForSource() changed name from class to sourceClass', '/'),
        preg_quote('CHANGED: The return type of OpenApi\Analysis#split() changed from no type to stdClass', '/'),

        // MailDataSimulatorFieldEvent no longer exposes Faker in the runtime simulate feature
        preg_quote('REMOVED: Property Shopwell\Core\Content\MailTemplate\Service\Event\MailDataSimulatorFieldEvent#$faker was removed', '/'),
        preg_quote('REMOVED: Parameter faker was removed from Method Shopwell\Core\Content\MailTemplate\Service\Event\MailDataSimulatorFieldEvent::__construct()', '/'),

        // Optional parameter added with default null; existing callers are unaffected
        preg_quote('ADDED: Parameter introducedIn was added to Method triggerDeprecationOrThrow() of class Shopwell\Core\Framework\Feature', '/'),
        preg_quote('ADDED: Parameter silentUntil was added to Method triggerDeprecationOrThrow() of class Shopwell\Core\Framework\Feature', '/'),

        // Optional parameter added with default null; callers are unaffected and decorations go through
        // AbstractCartLoadRoute::load(), which keeps its signature until the change announced for 6.8.
        // CartLoadRoute is not an extension point, its getDecorated() throws a DecorationPatternException.
        preg_quote('ADDED: Parameter cart was added to Method load() of class Shopwell\Core\Checkout\Cart\SalesChannel\CartLoadRoute', '/'),

        // Promoted properties expose their default on the constructor parameter rather than
        // ReflectionProperty. CustomerZipCode still initializes this property to true.
        preg_quote('CHANGED: Property Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode#$caseSensitiveCheck changed default value from true to NULL', '/'),

        // Rule classes are tagged @final
        preg_quote('CHANGED: Type of property Shopwell\Core\Checkout\Customer\Rule\CustomerBirthdayRule#$birthday changed from string|null to string|array|null', '/'),
        preg_quote('CHANGED: Type of property Shopwell\Core\Checkout\Cart\Rule\LineItemReleaseDateRule#$lineItemReleaseDate changed from string|null to string|array|null', '/'),
        preg_quote('CHANGED: Type of property Shopwell\Core\Checkout\Cart\Rule\LineItemCreationDateRule#$lineItemCreationDate changed from string|null to string|array|null', '/'),
        preg_quote('REMOVED: Property Shopwell\Core\Checkout\Cart\Rule\LineItemPurchasePriceRule#$isNet was removed', '/'),
        preg_quote('CHANGED: The return type of Shopwell\Core\Framework\Rule\Rule#getConfig() changed from Shopwell\Core\Framework\Rule\RuleConfig|null to Shopwell\Core\Framework\Rule\RuleConfig', '/'),

        // Rule::getConfig() already permits null. These deprecated implementations keep returning
        // RuleConfig in 6.7 and only return null when their removal flag v6.8.0.0 is active.
        preg_quote('CHANGED: The return type of Shopwell\Core\Checkout\Cart\Rule\LineItemStockRule#getConfig() changed from Shopwell\Core\Framework\Rule\RuleConfig to ', '/') . '(?:the non-covariant )?' . preg_quote('Shopwell\Core\Framework\Rule\RuleConfig|null', '/'),
        preg_quote('CHANGED: The return type of Shopwell\Core\Checkout\Cart\Rule\LineItemProductStatesRule#getConfig() changed from Shopwell\Core\Framework\Rule\RuleConfig to ', '/') . '(?:the non-covariant )?' . preg_quote('Shopwell\Core\Framework\Rule\RuleConfig|null', '/'),

        // DefinitionValidator is @final; optional parameter added with default [], existing callers are unaffected
        preg_quote('ADDED: Parameter toleratedNonStandardForeignKeys was added to Method validate() of class Shopwell\Core\Framework\DataAbstractionLayer\DefinitionValidator', '/'),

        // DocumentType translations were incorrectly typed as product translations
        preg_quote('CHANGED: Type of property Shopwell\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity#$translations changed from Shopwell\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection|null', '/'),
        preg_quote('CHANGED: The return type of Shopwell\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity#getTranslations() changed from Shopwell\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection|null', '/'),
        preg_quote('CHANGED: The parameter $translations of Shopwell\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeEntity#setTranslations() changed from Shopwell\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection', '/'),

        // Contravariant widening so the filter also accepts PartialEntity media from partial listing loading
        preg_quote('The parameter $media of Shopwell\Storefront\Framework\Twig\Extension\UrlEncodingTwigFilter#encodeMediaUrl() changed from', '/'),

        // Experimental MCP feature (gated behind the MCP_SERVER flag, all MCP classes are
        // @experimental stableVersion:v6.8.0). The MCP rate-limit route was split per API
        // scope, replacing the single RateLimiter::MCP constant with MCP_ADMIN_API /
        // MCP_STORE_API. The constant lived on the non-experimental RateLimiter class so it
        // was not auto-skipped, but it is part of the still-experimental MCP surface.
        preg_quote('REMOVED: Constant Shopwell\Core\Framework\RateLimiter\RateLimiter::MCP was removed', '/'),

        // Experimental app MCP DAL aggregates were replaced by the generic app feature storage.
        // Their accessors lived on stable parent entities, so the BC checker does not infer the
        // experimental status from the removed aggregate types.
        preg_quote('REMOVED: Property Shopwell\Core\Framework\App\AppEntity#$mcp', '/'),
        preg_quote('REMOVED: Method Shopwell\Core\Framework\App\AppEntity#getMcp', '/'),
        preg_quote('REMOVED: Method Shopwell\Core\Framework\App\AppEntity#setMcp', '/'),
        preg_quote('REMOVED: Property Shopwell\Core\System\Language\LanguageEntity#$appMcp', '/'),
        preg_quote('REMOVED: Method Shopwell\Core\System\Language\LanguageEntity#getAppMcp', '/'),
        preg_quote('REMOVED: Method Shopwell\Core\System\Language\LanguageEntity#setAppMcp', '/'),

        // EntitySearchResult::merge() takes EntityCollection (not self) so it accepts any collection, not just other search results.
        'CHANGED: The parameter \$collection of Shopwell\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\EntityCollection#merge\(\) changed from self to (?:a non-contravariant )?Shopwell\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\EntityCollection',

        // Translated CustomerGroupEntity properties are now nullable like the translation entity (fixes #16461).
        preg_quote('CHANGED: Type of property Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#$registrationTitle changed from string to string|null', '/'),
        preg_quote('CHANGED: Type of property Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#$registrationIntroduction changed from string to string|null', '/'),
        preg_quote('CHANGED: Type of property Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#$registrationOnlyCompanyRegistration changed from bool to bool|null', '/'),
        preg_quote('CHANGED: Type of property Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#$registrationSeoMetaDescription changed from string to string|null', '/'),
        preg_quote('CHANGED: The return type of Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#getRegistrationTitle() changed from string', '/'),
        preg_quote('CHANGED: The return type of Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#getRegistrationIntroduction() changed from string', '/'),
        preg_quote('CHANGED: The return type of Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#getRegistrationOnlyCompanyRegistration() changed from bool', '/'),
        preg_quote('CHANGED: The return type of Shopwell\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity#getRegistrationSeoMetaDescription() changed from string', '/'),

        // ProductEntity::$guaranteeConfirmed is null for a variant that inherits it, like every other inherited product flag.
        preg_quote('CHANGED: Type of property Shopwell\Core\Content\Product\ProductEntity#$guaranteeConfirmed changed from bool to bool|null', '/'),
        preg_quote('CHANGED: Property Shopwell\Core\Content\Product\ProductEntity#$guaranteeConfirmed changed default value from false to NULL', '/'),

        // parent method has no type. not really a break
        preg_quote('CHANGED: The return type of Shopwell\Core\Framework\Migration\Command\RefreshMigrationCommand#configure() changed from void to ', '/'),

        // intended to be internal on release
        preg_quote('CHANGED: Shopwell\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface was marked "@internal"', '/'),
        preg_quote('CHANGED: Shopwell\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver was marked "@internal"', '/'),

        // Only thrown during the legacy commercial license sync; all services
        // that used it have published versions using the new webhooks instead
        preg_quote('REMOVED: Constant Shopwell\Core\Service\ServiceException::SERVICE_MISSING_APP_SECRET_INFO was removed', '/'),
        preg_quote('REMOVED: Method Shopwell\Core\Service\ServiceException::missingAppSecretInfo() was removed', '/'),

        // Not sure why an external library is complained about
        preg_quote('CHANGED: The return type of Twig\Extension\AbstractExtension#getNodeVisitors() changed from no type to array', '/'),

        // Not released yet, so safe to be removed again
        preg_quote('REMOVED: Class Shopwell\Core\System\SystemConfig\Service\SystemConfigDefinitionService has been deleted', '/'),
        preg_quote('REMOVED: Class Shopwell\Core\Checkout\Document\Extension\DocumentRouteExtension has been deleted', '/'),
        preg_quote('REMOVED: Method Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome#rows() was removed', '/'),
        preg_quote('REMOVED: Method Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow#isInPayload() was removed', '/'),

        // The first and last name of customers, customer addresses, order customers, order
        // addresses, users and newsletter recipients are merged into a single `name` attribute.
        // The removed properties, accessors, constants, the LastNameRule and the split contact
        // form settings are intentional. Migration for extensions is documented in
        // UPGRADE-6.7.md and RELEASE_INFO-6.7.md.
        preg_quote('REMOVED: Class Shopwell\Core\Checkout\Customer\Rule\LastNameRule has been deleted', '/'),
        'REMOVED: Constant Shopwell\\\\Core\\\\.*::(MAX_LENGTH_FIRST_NAME|MAX_LENGTH_LAST_NAME|FIRST_NAME_FIELD|LAST_NAME_FIELD) was removed',
        'REMOVED: Property Shopwell\\\\Core\\\\.*(CustomerEntity|CustomerAddressEntity|OrderCustomerEntity|OrderAddressEntity|UserEntity|NewsletterRecipientEntity|ShopContactFormSettings)#\$(firstName|lastName|firstNameFieldRequired|lastNameFieldRequired) was removed',
        'REMOVED: Method Shopwell\\\\Core\\\\.*(CustomerEntity|CustomerAddressEntity|OrderCustomerEntity|OrderAddressEntity|UserEntity|NewsletterRecipientEntity)#(get|set)(FirstName|LastName)\(\) was removed',
        'CHANGED: Value of constant Shopwell\\\\Core\\\\System\\\\Country\\\\CountryDefinition::DEFAULT_ADDRESS_FORMAT changed',

        // A customer no longer needs an address to be created (registration without name and address,
        // see RELEASE_INFO-6.7.md), so a customer that has none has no default address either. The
        // columns are nullable, the accessors only widen their type and every caller that read the id
        // of a customer with an address sees the same value as before.
        preg_quote('CHANGED: Type of property Shopwell\Core\Checkout\Customer\CustomerEntity#$defaultBillingAddressId changed from string to string|null', '/'),
        preg_quote('CHANGED: Type of property Shopwell\Core\Checkout\Customer\CustomerEntity#$defaultShippingAddressId changed from string to string|null', '/'),
        preg_quote('CHANGED: The return type of Shopwell\Core\Checkout\Customer\CustomerEntity#getDefaultBillingAddressId() changed from string to the non-covariant string|null', '/'),
        preg_quote('CHANGED: The return type of Shopwell\Core\Checkout\Customer\CustomerEntity#getDefaultBillingAddressId() changed from string to string|null', '/'),
        preg_quote('CHANGED: The parameter $defaultBillingAddressId of Shopwell\Core\Checkout\Customer\CustomerEntity#setDefaultBillingAddressId() changed from string to string|null', '/'),
        preg_quote('CHANGED: The return type of Shopwell\Core\Checkout\Customer\CustomerEntity#getDefaultShippingAddressId() changed from string to the non-covariant string|null', '/'),
        preg_quote('CHANGED: The return type of Shopwell\Core\Checkout\Customer\CustomerEntity#getDefaultShippingAddressId() changed from string to string|null', '/'),
        preg_quote('CHANGED: The parameter $defaultShippingAddressId of Shopwell\Core\Checkout\Customer\CustomerEntity#setDefaultShippingAddressId() changed from string to string|null', '/'),

        // The wallet, loyalty membership, sms and payout gateway features are still being shaped
        // before their release (see RELEASE_INFO-6.7.md). Their configuration moved out of constants
        // on the services into WalletSettings and the loyalty settings, the top-up simulate route was
        // replaced by the create/status pair, and the strategy/mode/audit markers of the sms and
        // payment gateway were replaced by the state machines, enums and provider registry of those
        // domains. The removed symbols were plumbing of an unreleased feature, no published extension
        // can depend on them, and nothing was shipped that would need a migration path.
        'REMOVED: Constant Shopwell\\\\Core\\\\(Checkout\\\\Wallet|Checkout\\\\Loyalty)\\\\.*::CONFIG_KEY_[A-Z_]+ was removed',
        'REMOVED: Constant Shopwell\\\\Core\\\\System\\\\(Sms\\\\Gateway\\\\SmsGatewayEntity|Payment\\\\App\\\\Aggregate\\\\PaymentAppProvider\\\\PaymentAppProviderEntity)::[A-Z_]+ was removed',
        'REMOVED: Constant Shopwell\\\\Core\\\\System\\\\Sms\\\\Template\\\\Aggregate\\\\SmsTemplateMapping\\\\SmsTemplateMappingEntity::AUDIT_[A-Z_]+ was removed',
        'REMOVED: Property Shopwell\\\\Core\\\\(Checkout\\\\Payment\\\\Aggregate\\\\PaymentMethodGatewayChannel\\\\PaymentMethodGatewayChannelEntity|System\\\\Sms\\\\Gateway\\\\SmsGatewayEntity|System\\\\Payment\\\\App\\\\Aggregate\\\\PaymentAppProvider\\\\PaymentAppProviderEntity)#\$(providerTechnicalName|strategy|mode) was removed',
        preg_quote('REMOVED: Method Shopwell\Core\Checkout\Wallet\Service\WalletTopUpService#simulate() was removed', '/'),
        preg_quote('REMOVED: Class Shopwell\Core\Checkout\Wallet\SalesChannel\WalletTopUpSimulateRoute has been deleted', '/'),
        preg_quote('REMOVED: Class Shopwell\Core\Checkout\Wallet\Extension\WalletTopUpSimulateRouteExtension has been deleted', '/'),

        // Optional parameters with a null default; existing callers are unaffected
        preg_quote('ADDED: Parameter salesChannelId was added to Method options() of class Shopwell\Core\Checkout\Wallet\Service\WalletTopUpService', '/'),
        preg_quote('ADDED: Parameter salesChannelId was added to Method create() of class Shopwell\Core\Checkout\Wallet\Service\WalletTopUpService', '/'),

        // AbstractSmsSender is only extended by the senders of this repository; the parameter is
        // optional, so a sender that does not implement it keeps working, it only does not see the
        // country a template was chosen for.
        preg_quote('ADDED: Parameter countryIso was added to Method sendTemplate() of class Shopwell\Core\System\Sms\Service\AbstractSmsSender', '/'),

        // A membership plan may now be created before its name is typed in (the merchant fills it
        // afterwards), so the name of its translation is nullable. The loyalty domain is still
        // unreleased (see RELEASE_INFO-6.7.md), the accessors only widen their type, and every
        // caller that reads a plan with a name sees the same value as before.
        preg_quote('CHANGED: Type of property Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\Aggregate\MembershipPlanTranslation\MembershipPlanTranslationEntity#$name changed from string to string|null', '/'),
        preg_quote('CHANGED: The return type of Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\Aggregate\MembershipPlanTranslation\MembershipPlanTranslationEntity#getName() changed from string to the non-covariant string|null', '/'),
        preg_quote('CHANGED: The return type of Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\Aggregate\MembershipPlanTranslation\MembershipPlanTranslationEntity#getName() changed from string to string|null', '/'),
        preg_quote('CHANGED: The parameter $name of Shopwell\Core\Checkout\Loyalty\Entity\MembershipPlan\Aggregate\MembershipPlanTranslation\MembershipPlanTranslationEntity#setName() changed from string to string|null', '/'),

        // The salutation entity and the title field are replaced by the generic `gender` dictionary:
        // customer, address, order and newsletter recipient rows now store the technical name of a
        // dictionary entry (male/female/not_specified) in their `gender` column, which the V6_3
        // baseline creates and seeds. The removed entity, route, serializer, cart error classes,
        // rule, storefront page bindings and accessors are intentional; migration for extensions is
        // documented in UPGRADE-6.7.md.
        'REMOVED: Class Shopwell\\\\.*\\\\\w*Salutation\w* has been deleted',
        'REMOVED: Constant Shopwell\\\\Core\\\\\w+\\\\.*::MAX_LENGTH_TITLE was removed',
        'REMOVED: Property Shopwell\\\\Core\\\\.*(CustomerEntity|CustomerAddressEntity|OrderAddressEntity|OrderCustomerEntity|NewsletterRecipientEntity)#\$(title|salutation|salutationId) was removed',
        'REMOVED: Method Shopwell\\\\Core\\\\.*(CustomerEntity|CustomerAddressEntity|OrderAddressEntity|OrderCustomerEntity|NewsletterRecipientEntity)#(get|set)(Title|Salutation|SalutationId)\(\) was removed',
        'REMOVED: Property Shopwell\\\\Core\\\\Checkout\\\\Customer\\\\Rule\\\\CustomerSalutationRule#\$salutationIds was removed',
        'REMOVED: Method Shopwell\\\\Core\\\\Framework\\\\Adapter\\\\Cache\\\\CacheInvalidationSubscriber#invalidateSalutationRoute\(\) was removed',
        'REMOVED: Property Shopwell\\\\Core\\\\System\\\\Language\\\\LanguageEntity#\$salutationTranslations was removed',
        'REMOVED: Method Shopwell\\\\Core\\\\System\\\\Language\\\\LanguageEntity#(get|set)SalutationTranslations\(\) was removed',
        'REMOVED: Property Shopwell\\\\Core\\\\System\\\\SystemConfig\\\\SalesChannel\\\\ShopLoginRegistrationSettings#\$showTitleField was removed',
        'REMOVED: Property Shopwell\\\\Storefront\\\\Page\\\\\w+\\\\\w+\\\\\w+Page#\$salutations was removed',
        'REMOVED: Method Shopwell\\\\Storefront\\\\Page\\\\\w+\\\\\w+\\\\\w+Page#(get|set)Salutations\(\) was removed',
        // Reviewed development contracts: merchant accounts and attempts replace provider-bound
        // execution; membership purchases and subscriptions replace the combined loyalty model.
        '^' . preg_quote('CHANGED: Type of property Shopwell\\Core\\System\\Language\\LanguageEntity#$membershipPlanTranslations changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\Aggregate\\MembershipPlanTranslation\\MembershipPlanTranslationCollection|null to Shopwell\\Core\\Checkout\\Membership\\Entity\\MembershipPlan\\Aggregate\\MembershipPlanTranslation\\MembershipPlanTranslationCollection|null', '/') . '$',
        '^' . preg_quote('CHANGED: The return type of Shopwell\\Core\\System\\Language\\LanguageEntity#getMembershipPlanTranslations() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\Aggregate\\MembershipPlanTranslation\\MembershipPlanTranslationCollection|null to the non-covariant Shopwell\\Core\\Checkout\\Membership\\Entity\\MembershipPlan\\Aggregate\\MembershipPlanTranslation\\MembershipPlanTranslationCollection|null', '/') . '$',
        '^' . preg_quote('CHANGED: The return type of Shopwell\\Core\\System\\Language\\LanguageEntity#getMembershipPlanTranslations() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\Aggregate\\MembershipPlanTranslation\\MembershipPlanTranslationCollection|null to Shopwell\\Core\\Checkout\\Membership\\Entity\\MembershipPlan\\Aggregate\\MembershipPlanTranslation\\MembershipPlanTranslationCollection|null', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $membershipPlanTranslations of Shopwell\\Core\\System\\Language\\LanguageEntity#setMembershipPlanTranslations() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\Aggregate\\MembershipPlanTranslation\\MembershipPlanTranslationCollection to a non-contravariant Shopwell\\Core\\Checkout\\Membership\\Entity\\MembershipPlan\\Aggregate\\MembershipPlanTranslation\\MembershipPlanTranslationCollection', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $membershipPlanTranslations of Shopwell\\Core\\System\\Language\\LanguageEntity#setMembershipPlanTranslations() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\Aggregate\\MembershipPlanTranslation\\MembershipPlanTranslationCollection to Shopwell\\Core\\Checkout\\Membership\\Entity\\MembershipPlan\\Aggregate\\MembershipPlanTranslation\\MembershipPlanTranslationCollection', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\System\\Payment\\Security\\PaymentGatewayTokenCollection has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\System\\Payment\\Security\\PaymentGatewayTokenEntity has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\System\\Payment\\Order\\Refund\\PaymentRefundEntity has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\System\\Payment\\Order\\Refund\\PaymentRefundStates has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\System\\Payment\\Order\\Refund\\PaymentRefundCollection has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\System\\Payment\\Order\\PaymentOrderEntity#$providerTechnicalName was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\System\\Payment\\Order\\PaymentOrderEntity#$channelTechnicalName was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\System\\Payment\\Order\\PaymentOrderEntity#$channelParams was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\System\\Payment\\Order\\PaymentOrderEntity#$device was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\System\\Payment\\Order\\PaymentOrderEntity#$gatewayTokens was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\System\\Payment\\App\\Aggregate\\PaymentAppProvider\\PaymentAppProviderEntity has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\System\\Payment\\App\\Aggregate\\PaymentAppProvider\\PaymentAppProviderCollection has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\System\\Payment\\App\\Aggregate\\PaymentAppChannel\\PaymentAppChannelEntity#$params was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\System\\Payment\\App\\PaymentAppEntity#$providers was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Constant Shopwell\\Core\\System\\Payment\\Subscription\\Schedule\\PaymentCycleCalendar::ALIPAY_MAX_ANCHOR_DAY_OF_MONTH was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Constant Shopwell\\Core\\System\\Payment\\Subscription\\Schedule\\PaymentCycleCalendar::WINDOW_START_HOUR was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Constant Shopwell\\Core\\System\\Payment\\Subscription\\Schedule\\PaymentCycleCalendar::WINDOW_END_HOUR was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Constant Shopwell\\Core\\System\\Payment\\Subscription\\Schedule\\PaymentCycleCalendar::ALIPAY_LEAD_TIME_HOURS was removed', '/') . '$',
        '^' . preg_quote('CHANGED: The number of required arguments for Shopwell\\Core\\System\\Payment\\Subscription\\Schedule\\PaymentCycleCalendar#deductionWindowStart() increased from 1 to 2', '/') . '$',
        '^' . preg_quote('CHANGED: The number of required arguments for Shopwell\\Core\\System\\Payment\\Subscription\\Schedule\\PaymentCycleCalendar#clampIntoWindow() increased from 1 to 2', '/') . '$',
        '^' . preg_quote('CHANGED: The number of required arguments for Shopwell\\Core\\System\\Payment\\Subscription\\Schedule\\PaymentCycleCalendar#isWithinWindow() increased from 1 to 2', '/') . '$',
        '^' . preg_quote('CHANGED: The number of required arguments for Shopwell\\Core\\System\\Payment\\Subscription\\Schedule\\PaymentCycleCalendar#earliestExecutableAt() increased from 1 to 2', '/') . '$',
        '^' . preg_quote('CHANGED: The number of required arguments for Shopwell\\Core\\System\\Payment\\Subscription\\Schedule\\PaymentCycleCalendar#assertExpressibleBy() increased from 4 to 5', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $unit of Shopwell\\Core\\System\\Payment\\Subscription\\Schedule\\PaymentCycleCalendar#assertExpressibleBy() changed from string to a non-contravariant Shopwell\\Core\\System\\Payment\\Provider\\Cycle\\PaymentCycleRules', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $count of Shopwell\\Core\\System\\Payment\\Subscription\\Schedule\\PaymentCycleCalendar#assertExpressibleBy() changed from int to a non-contravariant string', '/') . '$',
        '^' . preg_quote('REMOVED: Constant Shopwell\\Core\\System\\Payment\\Subscription\\Schedule\\PaymentRetryPolicy::MAX_RETRIES was removed', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $provider of Shopwell\\Core\\System\\Payment\\Subscription\\Schedule\\PaymentRetryPolicy#classify() changed from string to a non-contravariant Shopwell\\Core\\System\\Payment\\Provider\\Cycle\\PaymentCycleRules|null', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $provider of Shopwell\\Core\\System\\Payment\\Subscription\\Schedule\\PaymentRetryPolicy#nextAttemptAt() changed from string to a non-contravariant Shopwell\\Core\\System\\Payment\\Provider\\Cycle\\PaymentCycleRules|null', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\System\\Payment\\Provider\\PaymentChannelConfigCollection has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\System\\Payment\\Provider\\AbstractPaymentProvider#getCaptureAction() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\System\\Payment\\Provider\\AbstractPaymentProvider#getChannels() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\System\\Payment\\Provider\\Channel\\PaymentFlow has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\System\\Payment\\Provider\\PaymentProviderConfigEntity has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\System\\Payment\\Provider\\PaymentProviderConfigCollection has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\System\\Payment\\Provider\\PaymentChannelConfigEntity has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\System\\Payment\\Provider\\Cycle\\PaymentCycleRules#$unknownFailureCodes was removed', '/') . '$',
        '^' . preg_quote('CHANGED: Default parameter value for parameter $unknownFailureCodes of Shopwell\\Core\\System\\Payment\\Provider\\Cycle\\PaymentCycleRules#__construct() changed from array (', '/') . '$',
        '^' . preg_quote('CHANGED: Default parameter value for parameter $maxRetries of Shopwell\\Core\\System\\Payment\\Provider\\Cycle\\PaymentCycleRules#__construct() changed from 0 to array (', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $unknownFailureCodes of Shopwell\\Core\\System\\Payment\\Provider\\Cycle\\PaymentCycleRules#__construct() changed from array to a non-contravariant int', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $maxRetries of Shopwell\\Core\\System\\Payment\\Provider\\Cycle\\PaymentCycleRules#__construct() changed from int to a non-contravariant array', '/') . '$',
        '^' . preg_quote('CHANGED: Type of property Shopwell\\Core\\System\\Currency\\CurrencyEntity#$membershipPlans changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\MembershipPlanCollection|null to Shopwell\\Core\\Checkout\\Membership\\Entity\\MembershipPlan\\MembershipPlanCollection|null', '/') . '$',
        '^' . preg_quote('CHANGED: The return type of Shopwell\\Core\\System\\Currency\\CurrencyEntity#getMembershipPlans() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\MembershipPlanCollection|null to the non-covariant Shopwell\\Core\\Checkout\\Membership\\Entity\\MembershipPlan\\MembershipPlanCollection|null', '/') . '$',
        '^' . preg_quote('CHANGED: The return type of Shopwell\\Core\\System\\Currency\\CurrencyEntity#getMembershipPlans() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\MembershipPlanCollection|null to Shopwell\\Core\\Checkout\\Membership\\Entity\\MembershipPlan\\MembershipPlanCollection|null', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $membershipPlans of Shopwell\\Core\\System\\Currency\\CurrencyEntity#setMembershipPlans() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\MembershipPlanCollection to a non-contravariant Shopwell\\Core\\Checkout\\Membership\\Entity\\MembershipPlan\\MembershipPlanCollection', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $membershipPlans of Shopwell\\Core\\System\\Currency\\CurrencyEntity#setMembershipPlans() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\MembershipPlanCollection to Shopwell\\Core\\Checkout\\Membership\\Entity\\MembershipPlan\\MembershipPlanCollection', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\System\\SalesChannel\\SalesChannelEntity#$customerPointTransactions was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\System\\SalesChannel\\SalesChannelEntity#getCustomerPointTransactions() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\System\\SalesChannel\\SalesChannelEntity#setCustomerPointTransactions() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: These ancestors of Shopwell\\Core\\System\\User\\Aggregate\\UserOrganizationUnit\\UserOrganizationUnitDefinition have been removed: ["Shopwell\\\\Core\\\\Framework\\\\DataAbstractionLayer\\\\MappingEntityDefinition"]', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Extension\\MembershipPurchaseRouteExtension has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#$minGrowthValue was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#$pointsMultiplier was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#$discountPercent was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#$freeShipping was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#$membershipOrders was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#getMinGrowthValue() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#setMinGrowthValue() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#getPointsMultiplier() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#setPointsMultiplier() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#getDiscountPercent() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#setDiscountPercent() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#getFreeShipping() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#setFreeShipping() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#getMembershipOrders() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#setMembershipOrders() was removed', '/') . '$',
        '^' . preg_quote('CHANGED: Type of property Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#$customers changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\CustomerLoyalty\\CustomerLoyaltyCollection|null to Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyAccount\\LoyaltyAccountCollection|null', '/') . '$',
        '^' . preg_quote('CHANGED: Type of property Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#$membershipPlans changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\MembershipPlanCollection|null to Shopwell\\Core\\Checkout\\Membership\\Entity\\MembershipPlan\\MembershipPlanCollection|null', '/') . '$',
        '^' . preg_quote('CHANGED: The return type of Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#getCustomers() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\CustomerLoyalty\\CustomerLoyaltyCollection|null to the non-covariant Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyAccount\\LoyaltyAccountCollection|null', '/') . '$',
        '^' . preg_quote('CHANGED: The return type of Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#getCustomers() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\CustomerLoyalty\\CustomerLoyaltyCollection|null to Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyAccount\\LoyaltyAccountCollection|null', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $customers of Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#setCustomers() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\CustomerLoyalty\\CustomerLoyaltyCollection to a non-contravariant Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyAccount\\LoyaltyAccountCollection', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $customers of Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#setCustomers() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\CustomerLoyalty\\CustomerLoyaltyCollection to Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyAccount\\LoyaltyAccountCollection', '/') . '$',
        '^' . preg_quote('CHANGED: The return type of Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#getMembershipPlans() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\MembershipPlanCollection|null to the non-covariant Shopwell\\Core\\Checkout\\Membership\\Entity\\MembershipPlan\\MembershipPlanCollection|null', '/') . '$',
        '^' . preg_quote('CHANGED: The return type of Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#getMembershipPlans() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\MembershipPlanCollection|null to Shopwell\\Core\\Checkout\\Membership\\Entity\\MembershipPlan\\MembershipPlanCollection|null', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $membershipPlans of Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#setMembershipPlans() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\MembershipPlanCollection to a non-contravariant Shopwell\\Core\\Checkout\\Membership\\Entity\\MembershipPlan\\MembershipPlanCollection', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $membershipPlans of Shopwell\\Core\\Checkout\\Loyalty\\Entity\\LoyaltyTier\\LoyaltyTierEntity#setMembershipPlans() changed from Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\MembershipPlanCollection to Shopwell\\Core\\Checkout\\Membership\\Entity\\MembershipPlan\\MembershipPlanCollection', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipOrder\\MembershipOrderCollection has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipOrder\\MembershipOrderDefinition has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipOrder\\MembershipOrderEntity has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Entity\\CustomerLoyalty\\CustomerLoyaltyCollection has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Entity\\CustomerLoyalty\\CustomerLoyaltyDefinition has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Entity\\CustomerLoyalty\\CustomerLoyaltyEntity has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\Aggregate\\MembershipPlanTranslation\\MembershipPlanTranslationDefinition has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\Aggregate\\MembershipPlanTranslation\\MembershipPlanTranslationEntity has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\Aggregate\\MembershipPlanTranslation\\MembershipPlanTranslationCollection has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\MembershipPlanEntity has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\MembershipPlanCollection has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Entity\\MembershipPlan\\MembershipPlanDefinition has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Entity\\CustomerPointTransaction\\CustomerPointTransactionDefinition has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Entity\\CustomerPointTransaction\\CustomerPointTransactionEntity has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Entity\\CustomerPointTransaction\\CustomerPointTransactionCollection has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Constant Shopwell\\Core\\Checkout\\Loyalty\\Service\\LoyaltySettings::DEFAULT_GROWTH_PER_CURRENCY_UNIT was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Service\\LoyaltySettings#growthPerCurrencyUnit() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\Service\\MembershipService has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Service\\TierResolver#findTierIdForGrowthValue() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Service\\TierResolver#getPointsMultiplier() was removed', '/') . '$',
        '^' . preg_quote('ADDED: Parameter tierPoints was added to Method resolve() of class Shopwell\\Core\\Checkout\\Loyalty\\Service\\TierResolver', '/') . '$',
        '^' . preg_quote('CHANGED: Parameter 1 of Shopwell\\Core\\Checkout\\Loyalty\\Service\\TierResolver#resolve() changed name from growthValue to tierPoints', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Service\\LoyaltyLedger#addGrowthValue() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Service\\LoyaltyLedger#activateMembership() was removed', '/') . '$',
        '^' . preg_quote('CHANGED: Shopwell\\Core\\Checkout\\Loyalty\\Service\\LoyaltyLedger was marked "@internal"', '/') . '$',
        '^' . preg_quote('REMOVED: Constant Shopwell\\Core\\Checkout\\Loyalty\\Service\\LoyaltyPointService::TRANSACTION_TYPE_AWARD was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Constant Shopwell\\Core\\Checkout\\Loyalty\\Service\\LoyaltyPointService::TRANSACTION_TYPE_REDEEM was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Constant Shopwell\\Core\\Checkout\\Loyalty\\Service\\LoyaltyPointService::TRANSACTION_TYPE_ADJUST was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Constant Shopwell\\Core\\Checkout\\Loyalty\\Service\\LoyaltyPointService::TRANSACTION_TYPE_EXPIRE was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Service\\LoyaltyPointService#getBalance() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Service\\LoyaltyPointService#provisionMembership() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Service\\LoyaltyPointService#expire() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\Service\\LoyaltyPointService#addGrowthValue() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Constant Shopwell\\Core\\Checkout\\Loyalty\\LoyaltyException::LOYALTY_PLAN_NOT_FOUND_CODE was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Constant Shopwell\\Core\\Checkout\\Loyalty\\LoyaltyException::LOYALTY_PLAN_NOT_PURCHASABLE_CODE was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\LoyaltyException::planNotFound() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Loyalty\\LoyaltyException::planNotPurchasable() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\SalesChannel\\MembershipPurchaseRoute has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Loyalty\\SalesChannel\\MembershipPurchaseRouteResponse has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\Checkout\\Customer\\CustomerEntity#$customerLoyalties was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\Checkout\\Customer\\CustomerEntity#$customerPointTransactions was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\Checkout\\Customer\\CustomerEntity#$membershipOrders was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Customer\\CustomerEntity#getCustomerLoyalties() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Customer\\CustomerEntity#setCustomerLoyalties() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Customer\\CustomerEntity#getCustomerPointTransactions() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Customer\\CustomerEntity#setCustomerPointTransactions() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Customer\\CustomerEntity#getMembershipOrders() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Customer\\CustomerEntity#setMembershipOrders() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\Checkout\\Order\\OrderEntity#$customerPointTransactions was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Order\\OrderEntity#getCustomerPointTransactions() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Order\\OrderEntity#setCustomerPointTransactions() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\Checkout\\Payment\\Aggregate\\PaymentMethodGatewayChannel\\PaymentMethodGatewayChannelEntity#$channelTechnicalName was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Constant Shopwell\\Core\\Checkout\\Wallet\\Entity\\CustomerWalletTopUp\\CustomerWalletTopUpEntity::STATE_PAID was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Property Shopwell\\Core\\Checkout\\Wallet\\Entity\\CustomerWalletTopUp\\CustomerWalletTopUpEntity#$providerTechnicalName was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Wallet\\Entity\\CustomerWalletTopUp\\CustomerWalletTopUpEntity#getProviderTechnicalName() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Wallet\\Entity\\CustomerWalletTopUp\\CustomerWalletTopUpEntity#setProviderTechnicalName() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Constant Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService::ATTACH_PREFIX was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService#recent() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService#status() was removed', '/') . '$',
        '^' . preg_quote('REMOVED: Method Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService#settle() was removed', '/') . '$',
        '^' . preg_quote('ADDED: Parameter paymentContext was added to Method options() of class Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $currency of Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService#options() changed from string to a non-contravariant Shopwell\\Core\\System\\Payment\\Routing\\PaymentContext', '/') . '$',
        '^' . preg_quote('CHANGED: Parameter 0 of Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService#options() changed name from currency to paymentContext', '/') . '$',
        '^' . preg_quote('CHANGED: Parameter 1 of Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService#options() changed name from device to salesChannelId', '/') . '$',
        '^' . preg_quote('ADDED: Parameter paymentContext was added to Method create() of class Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService', '/') . '$',
        '^' . preg_quote('ADDED: Parameter method was added to Method create() of class Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $currency of Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService#create() changed from string to a non-contravariant Shopwell\\Core\\System\\Payment\\Routing\\PaymentContext', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $device of Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService#create() changed from string|null to a non-contravariant Shopwell\\Core\\Framework\\Context', '/') . '$',
        '^' . preg_quote('CHANGED: The parameter $context of Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService#create() changed from Shopwell\\Core\\Framework\\Context to a non-contravariant string|null', '/') . '$',
        '^' . preg_quote('CHANGED: Parameter 2 of Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService#create() changed name from currency to paymentContext', '/') . '$',
        '^' . preg_quote('CHANGED: Parameter 4 of Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService#create() changed name from channel to method', '/') . '$',
        '^' . preg_quote('CHANGED: Parameter 7 of Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService#create() changed name from device to context', '/') . '$',
        '^' . preg_quote('CHANGED: Parameter 8 of Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletTopUpService#create() changed name from context to salesChannelId', '/') . '$',
        '^' . preg_quote('CHANGED: Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletLedger was marked "@internal"', '/') . '$',
        '^' . preg_quote('ADDED: Parameter oncePerReference was added to Method book() of class Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletLedger', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Core\\Checkout\\Wallet\\Service\\WalletService has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Storefront\\Page\\Account\\Loyalty\\AccountLoyaltyPage has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Storefront\\Page\\Account\\Loyalty\\AccountLoyaltyPageLoader has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Storefront\\Page\\Account\\Loyalty\\AccountLoyaltyPageLoadedHook has been deleted', '/') . '$',
        '^' . preg_quote('REMOVED: Class Shopwell\\Storefront\\Page\\Account\\Loyalty\\AccountLoyaltyPageLoadedEvent has been deleted', '/') . '$',
    ],
];
