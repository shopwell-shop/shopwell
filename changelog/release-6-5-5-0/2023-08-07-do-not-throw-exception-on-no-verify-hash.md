---
title: do not throw exception on no verification hash
issue: NEXT-29716
---
# Storefront
* Changed `\Shopwell\Storefront\Controller\VerificationHashController::load` to return a 400 response instead of throwing an exception if the verification hash is missing 
