<?php declare(strict_types=1);

namespace Shopwell\Storefront\DependencyInjection;

use Shopwell\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopwell\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartLoadRoute;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopwell\Core\Checkout\Customer\SalesChannel\AddWishlistProductRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\ChangeEmailRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\ChangePasswordRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\ConvertGuestRoute;
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
use Shopwell\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\UpsertAddressRoute;
use Shopwell\Core\Checkout\Document\SalesChannel\DocumentRoute;
use Shopwell\Core\Checkout\Order\SalesChannel\CancelOrderRoute;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderService;
use Shopwell\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute;
use Shopwell\Core\Checkout\Payment\PaymentProcessor;
use Shopwell\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRoute;
use Shopwell\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopwell\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopwell\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopwell\Core\Content\Cms\SalesChannel\CmsRoute;
use Shopwell\Core\Content\ContactForm\SalesChannel\ContactFormRoute;
use Shopwell\Core\Content\Cookie\SalesChannel\CookieRoute;
use Shopwell\Core\Content\Newsletter\SalesChannel\NewsletterConfirmRoute;
use Shopwell\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopwell\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute;
use Shopwell\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopwell\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopwell\Core\Content\Product\SalesChannel\ProductListRoute;
use Shopwell\Core\Content\Product\SalesChannel\PurchaseLimit\ProductPurchaseLimitRoute;
use Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Shopwell\Core\Content\Product\SalesChannel\Review\ProductReviewSaveRoute;
use Shopwell\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use Shopwell\Core\Content\RevocationRequest\SalesChannel\RevocationRequestRoute;
use Shopwell\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopwell\Core\Content\Sitemap\SalesChannel\SitemapFileRoute;
use Shopwell\Core\Framework\Adapter\Translation\ConstraintViolationTranslator;
use Shopwell\Core\Framework\App\Api\AppJWTGenerateRoute;
use Shopwell\Core\Framework\Gateway\Context\SalesChannel\ContextGatewayRoute;
use Shopwell\Core\Framework\Script\Api\ScriptResponseEncoder;
use Shopwell\Core\Framework\Util\HtmlSanitizer;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopwell\Storefront\Controller\AccountOrderController;
use Shopwell\Storefront\Controller\AccountProfileController;
use Shopwell\Storefront\Controller\AddressController;
use Shopwell\Storefront\Controller\Api\CaptchaController as ApiCaptchaController;
use Shopwell\Storefront\Controller\AppController;
use Shopwell\Storefront\Controller\AuthController;
use Shopwell\Storefront\Controller\CaptchaController;
use Shopwell\Storefront\Controller\CartLineItemController;
use Shopwell\Storefront\Controller\CheckoutController;
use Shopwell\Storefront\Controller\CmsController;
use Shopwell\Storefront\Controller\ContextController;
use Shopwell\Storefront\Controller\ContextGatewayController;
use Shopwell\Storefront\Controller\CookieController;
use Shopwell\Storefront\Controller\CountryStateController;
use Shopwell\Storefront\Controller\DocumentController;
use Shopwell\Storefront\Controller\DownloadController;
use Shopwell\Storefront\Controller\ErrorController;
use Shopwell\Storefront\Controller\FormController;
use Shopwell\Storefront\Controller\LandingPageController;
use Shopwell\Storefront\Controller\MaintenanceController;
use Shopwell\Storefront\Controller\NavigationController;
use Shopwell\Storefront\Controller\NewsletterController;
use Shopwell\Storefront\Controller\ProductController;
use Shopwell\Storefront\Controller\RegisterController;
use Shopwell\Storefront\Controller\RobotsController;
use Shopwell\Storefront\Controller\ScriptController;
use Shopwell\Storefront\Controller\SearchController;
use Shopwell\Storefront\Controller\SitemapController;
use Shopwell\Storefront\Controller\StorybookController;
use Shopwell\Storefront\Controller\VerificationHashController;
use Shopwell\Storefront\Controller\WellKnownController;
use Shopwell\Storefront\Controller\WishlistController;
use Shopwell\Storefront\Framework\Captcha\BasicCaptcha;
use Shopwell\Storefront\Framework\Guard\DoubleSubmitGuard;
use Shopwell\Storefront\Framework\Routing\MaintenanceModeResolver;
use Shopwell\Storefront\Framework\Twig\ErrorTemplateResolver;
use Shopwell\Storefront\Page\Account\CustomerGroupRegistration\CustomerGroupRegistrationPageLoader;
use Shopwell\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopwell\Storefront\Page\Account\Order\AccountEditOrderPageLoader;
use Shopwell\Storefront\Page\Account\Order\AccountOrderDetailPageLoader;
use Shopwell\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Shopwell\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Shopwell\Storefront\Page\Account\Profile\AccountProfilePageLoader;
use Shopwell\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Shopwell\Storefront\Page\Address\Detail\AddressDetailPageLoader;
use Shopwell\Storefront\Page\Address\Listing\AddressListingPageLoader;
use Shopwell\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader;
use Shopwell\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Shopwell\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoader;
use Shopwell\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoader;
use Shopwell\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Shopwell\Storefront\Page\GenericPageLoader;
use Shopwell\Storefront\Page\LandingPage\LandingPageLoader;
use Shopwell\Storefront\Page\Maintenance\MaintenancePageLoader;
use Shopwell\Storefront\Page\Navigation\Error\ErrorPageLoader;
use Shopwell\Storefront\Page\Navigation\NavigationPageLoader;
use Shopwell\Storefront\Page\Newsletter\Subscribe\NewsletterSubscribePageLoader;
use Shopwell\Storefront\Page\Product\ProductPageLoader;
use Shopwell\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Shopwell\Storefront\Page\Robots\RobotsPageLoader;
use Shopwell\Storefront\Page\Search\SearchPageLoader;
use Shopwell\Storefront\Page\Sitemap\SitemapPageLoader;
use Shopwell\Storefront\Page\Suggest\SuggestPageLoader;
use Shopwell\Storefront\Page\Wishlist\GuestWishlistPageLoader;
use Shopwell\Storefront\Page\Wishlist\WishlistPageLoader;
use Shopwell\Storefront\Pagelet\Captcha\BasicCaptchaPageletLoader;
use Shopwell\Storefront\Pagelet\Country\CountryStateDataPageletLoader;
use Shopwell\Storefront\Pagelet\Footer\FooterPageletLoader;
use Shopwell\Storefront\Pagelet\Header\HeaderPageletLoader;
use Shopwell\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoader;
use Shopwell\Storefront\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader;
use Shopwell\Storefront\Pagelet\Wishlist\GuestWishlistPageletLoader;
use Shopwell\Storefront\Storybook\StorybookService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->public();

    $services->set(ApiCaptchaController::class)
        ->public()
        ->args([
            tagged_iterator('shopwell.storefront.captcha'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AccountOrderController::class)
        ->args([
            service(AccountOrderPageLoader::class),
            service(AccountEditOrderPageLoader::class),
            service(ContextSwitchRoute::class),
            service(CancelOrderRoute::class),
            service(SetPaymentOrderRoute::class),
            service(HandlePaymentMethodRoute::class),
            service('event_dispatcher'),
            service(AccountOrderDetailPageLoader::class)->nullOnInvalid(),
            service(OrderRoute::class),
            service(SalesChannelContextService::class),
            service(SystemConfigService::class),
            service(OrderService::class),
            service(HeaderPageletLoader::class),
            service(FooterPageletLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AccountProfileController::class)
        ->args([
            service(AccountOverviewPageLoader::class),
            service(AccountProfilePageLoader::class),
            service(ChangeCustomerProfileRoute::class),
            service(ChangePasswordRoute::class),
            service(ChangeEmailRoute::class),
            service(DeleteCustomerRoute::class),
            service('logger'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AddressController::class)
        ->args([
            service(AddressListingPageLoader::class),
            service(AddressDetailPageLoader::class),
            service(AccountService::class),
            service(ListAddressRoute::class),
            service(UpsertAddressRoute::class),
            service(DeleteAddressRoute::class),
            service(ContextSwitchRoute::class),
            service(SalesChannelContextService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AuthController::class)
        ->args([
            service(AccountLoginPageLoader::class),
            service(SendPasswordRecoveryMailRoute::class),
            service(ResetPasswordRoute::class),
            service(LoginRoute::class),
            service(LogoutRoute::class),
            service(ImitateCustomerRoute::class),
            service(StorefrontCartFacade::class),
            service(AccountRecoverPasswordPageLoader::class),
            service(ConvertGuestRoute::class),
            service(SystemConfigService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AppController::class)
        ->public()
        ->args([
            service(AppJWTGenerateRoute::class),
        ]);

    $services->set(CartLineItemController::class)
        ->args([
            service(CartService::class),
            service(PromotionItemBuilder::class),
            service(ProductLineItemFactory::class),
            service(HtmlSanitizer::class),
            service(ProductListRoute::class),
            service(LineItemFactoryRegistry::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CheckoutController::class)
        ->args([
            service(CartService::class),
            service(CheckoutCartPageLoader::class),
            service(CheckoutConfirmPageLoader::class),
            service(CheckoutFinishPageLoader::class),
            service(OrderService::class),
            service(PaymentProcessor::class),
            service(OffcanvasCartPageLoader::class),
            service(LogoutRoute::class),
            service(CartLoadRoute::class),
            service(HeaderPageletLoader::class),
            service(FooterPageletLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ContextGatewayController::class)
        ->args([
            service(ContextGatewayRoute::class),
            service(CartService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CookieController::class)
        ->args([
            service(CookieRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CmsController::class)
        ->args([
            service(CmsRoute::class),
            service(CategoryRoute::class),
            service(ProductListingRoute::class),
            service(ProductDetailRoute::class),
            service(ProductReviewLoader::class),
            service(FindProductVariantRoute::class),
            service('event_dispatcher'),
            service(SystemConfigService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(FormController::class)
        ->args([
            service(ContactFormRoute::class),
            service(NewsletterSubscribeRoute::class),
            service(NewsletterUnsubscribeRoute::class),
            service(RevocationRequestRoute::class),
            service(ConstraintViolationTranslator::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ContextController::class)
        ->args([
            service(ContextSwitchRoute::class),
            service('request_stack'),
            service('router.default'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(MaintenanceController::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
            service(MaintenancePageLoader::class),
            service(MaintenanceModeResolver::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ErrorController::class)
        ->public()
        ->args([
            service(ErrorTemplateResolver::class),
            service(SystemConfigService::class),
            service(ErrorPageLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(NavigationController::class)
        ->args([
            service(NavigationPageLoader::class),
            service(MenuOffcanvasPageletLoader::class),
            service(HeaderPageletLoader::class),
            service(FooterPageletLoader::class),
            service(CategoryUrlGenerator::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(NewsletterController::class)
        ->args([
            service(NewsletterSubscribePageLoader::class),
            service(NewsletterConfirmRoute::class),
            service(NewsletterAccountPageletLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ProductController::class)
        ->args([
            service(ProductPageLoader::class),
            service(FindProductVariantRoute::class),
            service(MinimalQuickViewPageLoader::class),
            service(ProductReviewSaveRoute::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service(ProductReviewLoader::class),
            service(ProductPurchaseLimitRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(LandingPageController::class)
        ->args([
            service(LandingPageLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(RegisterController::class)
        ->args([
            service(AccountLoginPageLoader::class),
            service(RegisterRoute::class),
            service(RegisterConfirmRoute::class),
            service(CartService::class),
            service(CheckoutRegisterPageLoader::class),
            service(SystemConfigService::class),
            service('customer.repository'),
            service(CustomerGroupRegistrationPageLoader::class),
            service('sales_channel_domain.repository'),
            service(HeaderPageletLoader::class),
            service(FooterPageletLoader::class),
            service(DoubleSubmitGuard::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ScriptController::class)
        ->args([
            service(GenericPageLoader::class),
            service(ScriptResponseEncoder::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(SearchController::class)
        ->args([
            service(SearchPageLoader::class),
            service(SuggestPageLoader::class),
            service(ProductSearchRoute::class),
            param('shopwell.storefront.redirect_on_single_hit_fields'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(SitemapController::class)
        ->args([
            service(SitemapPageLoader::class),
            service(SitemapFileRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CountryStateController::class)
        ->public()
        ->args([
            service(CountryStateDataPageletLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(DocumentController::class)
        ->public()
        ->args([
            service(DocumentRoute::class),
            service(LogoutRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(DownloadController::class)
        ->public()
        ->args([
            service(DownloadRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(WellKnownController::class)
        ->public()
        ->call('setContainer', [service('service_container')]);

    $services->set(WishlistController::class)
        ->public()
        ->args([
            service(WishlistPageLoader::class),
            service(LoadWishlistRoute::class),
            service(AddWishlistProductRoute::class),
            service(RemoveWishlistProductRoute::class),
            service(MergeWishlistProductRoute::class),
            service(GuestWishlistPageLoader::class),
            service(GuestWishlistPageletLoader::class),
            service('event_dispatcher'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CaptchaController::class)
        ->public()
        ->args([
            service(BasicCaptchaPageletLoader::class),
            service(BasicCaptcha::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(VerificationHashController::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(RobotsController::class)
        ->public()
        ->args([
            service(RobotsPageLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(StorybookController::class)
        ->public()
        ->args([
            service('twig'),
            service(StorybookService::class),
        ])
        ->call('setContainer', [service('service_container')]);
};
