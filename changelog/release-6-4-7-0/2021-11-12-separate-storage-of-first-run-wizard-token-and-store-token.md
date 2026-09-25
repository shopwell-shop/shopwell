---
title: Separate storage of First Run Wizard token and Store token
issue: NEXT-18549
author: Frederik Schmitt
author_email: f.schmitt@shopwell.com 
author_github: fschmtt
---
# Core
* Changed `Shopwell\Core\Framework\Store\Authentication\FrwRequestOptionsProvider` to add the First Run Wizard token as X-Shopwell-Token header
* Changed `Shopwell\Core\Framework\Store\Api\FirstRunWizardController::frwLogin()` to store the First Run Wizard token in the `user_config` database table instead of `user.store_token`
* Changed `Shopwell\Core\Framework\Store\Api\FirstRunWizardController::upgradeAccessToken()` to remove the First Run Wizard token from the `user_config` database table
* Added `Shopwell\Core\Framework\Store\Services\FirstRunWizardClient::USER_CONFIG_KEY_FRW_USER_TOKEN`
* Added `Shopwell\Core\Framework\Store\Services\FirstRunWizardClient::USER_CONFIG_VALUE_FRW_USER_TOKEN`
* Added `Shopwell\Core\Framework\Store\Services\FirstRunWizardClient::updateFrwUserToken()`
* Added `Shopwell\Core\Framework\Store\Services\FirstRunWizardClient::removeFrwUserToken()`
* Added `Shopwell\Core\Framework\Store\Services\FirstRunWizardClient::getFrwUserTokenConfigId()`
