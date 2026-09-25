---
title: Add Domain Exception for customer
issue: NEXT-26919
---
# Core
* Added new static methods for domain exception class `Shopwell\Core\Checkout\Customer\CustomerException`.
* Added new static method `addressNotFound` for domain exception class `Shopwell\Core\Checkout\Cart\CartException`.
* Added new static method `customerAuthThrottledException` for domain exception class `Shopwell\Core\Checkout\Order\OrderException`.
* Added new static method `customerNotFoundByIdException` for domain exception class `Shopwell\Core\System\SalesChannel\SalesChannelException`.
* Deprecated the following exceptions in replacement for Domain Exceptions:
    * `Shopwell\Core\Checkout\Customer\Exception\CannotDeleteActiveAddressException`
    * `Shopwell\Core\Checkout\Customer\Exception\CustomerGroupRegistrationConfigurationNotFound`
    * `Shopwell\Core\Checkout\Customer\Exception\CustomerWishlistNotActivatedException`
    * `Shopwell\Core\Checkout\Customer\Exception\InactiveCustomerException`
    * `Shopwell\Core\Checkout\Customer\Exception\LegacyPasswordEncoderNotFoundException`
    * `Shopwell\Core\Checkout\Customer\Exception\WishlistProductNotFoundException`
    * `Shopwell\Core\Checkout\Customer\Exception\NoHashProvidedException`
