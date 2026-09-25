---
title: Fix passing non string identifier to client repository
issue: NEXT-29534
---
# Core
* Changed method `\Shopwell\Core\Framework\Api\OAuth\ClientRepository::validateClient` to return false if client identifier is not a string
* Changed method `\Shopwell\Core\Framework\Api\OAuth\ClientRepository::getClientEntity` to return null if client identifier is not a string
