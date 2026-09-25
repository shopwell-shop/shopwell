---
title:              expose business events
issue:              NEXT-10701
author:             Oliver Skroblin
author_email:       o.skroblin@shopwell.com
author_github:      @OliverSkroblin
---
# Core
* Added remaining events to `\Shopwell\Core\Framework\Event\BusinessEvents`
* Added `\Shopwell\Core\Checkout\Cart\RuleLoader` to load all routes
* Added validation in `\Shopwell\Core\Checkout\Cart\Order\OrderConverter::assembleSalesChannelContext` to make sure that all data is available 
* Added `\Shopwell\Core\Framework\Event\BusinessEventCollector`, which returns a collection of all business events 
* Added `\Shopwell\Core\Framework\Event\BusinessEventCollectorEvent`, which allows to mutate business events
* Added `\Shopwell\Core\Framework\Event\BusinessEventCollectorResponse`, which returned by the collector
* Added `\Shopwell\Core\Framework\Event\BusinessEventDefinition`, which contains all information about a business event                                        
* Deprecated `\Shopwell\Core\Framework\Event\BusinessEventRegistry::getEvents` use `\Shopwell\Core\Framework\Event\BusinessEventCollector::collect` instead 
* Deprecated `\Shopwell\Core\Framework\Event\BusinessEventRegistry::getEventNames` use `\Shopwell\Core\Framework\Event\BusinessEventCollector::collect` instead
* Deprecated `\Shopwell\Core\Framework\Event\BusinessEventRegistry::getAvailableDataByEvent` use `\Shopwell\Core\Framework\Event\BusinessEventCollector::collect` instead 
* Deprecated `\Shopwell\Core\Framework\Event\BusinessEventRegistry::add` use `\Shopwell\Core\Framework\Event\BusinessEventRegistry::addClasses` instead
* Deprecated `\Shopwell\Core\Framework\Event\BusinessEventRegistry::addMultiple` use `\Shopwell\Core\Framework\Event\BusinessEventRegistry::addClasses` instead
* Added `\Shopwell\Core\Checkout\Order\Event\OrderStateChangeCriteriaEvent`, which allows to load additional data for order mails
___
# API
* Added `api.info.business-events` route
* Deprecated `api.info.events` use `api.info.business-events` instead
