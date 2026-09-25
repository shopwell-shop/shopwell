---
title: Added delete unused guest customer command and task
issue: NEXT-16234
author: Lennart Tinkloh
author_email: l.tinkloh@shopwell.com 
author_github: @lernhart
---
# Core
* Added `customer:delete-unused-guests` command to manually delete unused guest customers.
* Added `customer.delete_unuesd_guests` scheduled task to remove unused guest customers periodically.
* Added `core.loginRegistration.unusedGuestCustomerLifetime` system config entry to control, when guest customers expire and be deleted.
* Added `Shopwell\Core\Checkout\Customer\DeleteUnusedGuestCustomerService` to delete guest customers with no orders.
* Added `Shopwell\Core\Checkout\Customer\DeleteUnusedGuestCustomerTask`.
* Added `Shopwell\Core\Checkout\Customer\DeleteUnusedGuestCustomerHandler`.
* Added `Shopwell\Core\Checkout\Customer\Command\DeleteUnusedGuestCustomersCommand`.
* Added `Shopwell\Core\Migration\V6_4\Migration1636018970UnusedGuestCustomerLifetime`.
