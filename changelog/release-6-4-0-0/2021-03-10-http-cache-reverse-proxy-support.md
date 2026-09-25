---
title: Http Cache Reverse Proxy support
issue: NEXT-12958
---

# Storefront

* Added following new classes:
    * `Shopwell\Storefront\DependencyInjection\ReverseProxyCompilerPass`
    * `Shopwell\Storefront\Framework\Cache\ReverseProxy\AbstractReverseProxyGateway`
    * `Shopwell\Storefront\Framework\Cache\ReverseProxy\RedisReverseProxyGateway`
    * `Shopwell\Storefront\Framework\Cache\ReverseProxy\ReverseProxyCache`
* Added new configuration in the `storefront.yaml` for reverse http cache
