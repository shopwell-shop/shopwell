---
title: Fix user recovery request on different default language
issue: NEXT-29959
---
# Core
* Changed `\Shopwell\Core\System\User\Recovery\UserRecoveryService::generateUserRecovery` to get the language of an existing sales channel before creating sales channel context
* Added a new domain exception in `\Shopwell\Core\System\User\UserException`