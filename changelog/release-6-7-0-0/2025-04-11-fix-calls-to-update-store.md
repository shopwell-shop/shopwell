---
title: Fixed calls to update extensions
issue: #8333
---
# Administration
* Changed `Shopwell\Core\Framework\Store\Services\StoreClient` to cache the request for updates in the store to limit the amount of requests. The result will now be cached for one hour, significantly reducing the load on the API.

### Breaking Changes
* Deprecated the `Shopwell\Core\Framework\Plugin\Exception\PluginNotAZipFileException` class. Please use `Shopwell\Core\Framework\Store\StoreException::pluginNotAZipFile` instead.
