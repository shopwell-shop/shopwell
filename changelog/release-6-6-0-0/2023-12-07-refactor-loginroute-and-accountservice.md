---
title: Refactor LoginRoute and AccountService
issue: NEXT-32258
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Added method `Shopwell\Core\Checkout\Customer\SalesChannel\AccountService::loginByCredentials`
* Changed `Shopwell\Core\Checkout\Customer\SalesChannel\LoginRoute` and moved login logic into the `AccountService`
* Deprecated method `Shopwell\Core\Checkout\Customer\SalesChannel\AccountService::login` use `AccountService::loginByCredentials` or `AccountService::loginById` instead
* Deprecated unused constant `Shopwell\Core\Checkout\Customer\CustomerException::CUSTOMER_IS_INACTIVE` and unused method `Shopwell\Core\Checkout\Customer\CustomerException::inactiveCustomer`
___
# Upgrade Information
## Shopwell\Core\Checkout\Customer\SalesChannel\AccountService::login is removed

The `Shopwell\Core\Checkout\Customer\SalesChannel\AccountService::login` method will be removed in the next major version. Use `AccountService::loginByCredentials` or `AccountService::loginById` instead.
___
# Next Major Version Changes
## AccountService refactoring

The `Shopwell\Core\Checkout\Customer\SalesChannel\AccountService::login` method is removed. Use `AccountService::loginByCredentials` or `AccountService::loginById` instead.

Unused constant `Shopwell\Core\Checkout\Customer\CustomerException::CUSTOMER_IS_INACTIVE` and unused method `Shopwell\Core\Checkout\Customer\CustomerException::inactiveCustomer` are removed.
