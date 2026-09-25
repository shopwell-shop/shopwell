---
title: Unify constraint option structure and checks
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique` to only require the `salesChannelContext` field in options
* Changed `Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerPasswordMatches` to validate the options parameter before passing it to the parent construct & renamed the `context` field to `salesChannelContext` in options
* Changed `Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification` to validate the optional `shouldCheck` parameter
* Changed `Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode` to validate the optional `countryId` & `caseSensitiveCheck` parameters
