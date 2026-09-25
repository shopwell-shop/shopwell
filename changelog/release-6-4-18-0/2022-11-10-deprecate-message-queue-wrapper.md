---
title: Deprecate MessageQueue Wrapper
issue: NEXT-24016
---
# Core
* Deprecated several classes in the `MessageQueue` component, as we will remove our wrapper around the symfony messenger and enqueue as well in 6.5.0. This mostly affects the retry handling and encryption we build ourselves previously.
* Deprecated `Shopwell\Core\Framework\MessageQueue\Handler\AbstractMessageHandler`, use `\Symfony\Component\Messenger\Handler\MessageSubscriberInterface` directly instead.
* Deprecated all classes that extend the `Shopwell\Core\Framework\MessageQueue\Handler\AbstractMessageHandler`, as those will only implement the `MessageSubscriberInterface` and will be final and internal in 6.5.0.
* Deprecated `\Shopwell\Elasticsearch\Framework\Indexing\IndexerOffset::__construct` as the first parameter now expects an array of languageIds directly, passing a LanguageIdCollection is deprecated and will be removed in 6.5.0.
* Added `\Shopwell\Core\Framework\Adapter\Cache\InvalidateCacheTaskHandler` to handle the `InvalidateCacheTask` tasks.
* Deprecated `\Shopwell\Core\Framework\Adapter\Cache\CacheInvalidator` it will be final in v6.5.0.0 and won't extend `ScheduledTaskHandler` anymore, as that is now done in `InvalidateCacheTaskHandler`.
* Added `\Shopwell\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFoldersHandler` to handle the `CleanupOldCacheFoldersMessage` messages.
* Deprecated `\Shopwell\Core\Framework\Adapter\Cache\CacheClearer` it will be final in v6.5.0.0 and won't extend `AbstractMessageHandler` anymore, as that is now done in `CleanupOldCacheFoldersHandler`.
___
# Storefront
* Added `\Shopwell\Storefront\Framework\Cache\CacheWarmer\CacheWarmerTaskHandler` to handle the `Warmup` tasks.
* Deprecated `\Shopwell\Storefront\Framework\Cache\CacheWarmer\CacheWarmer` it won't extend `ScheduledTaskHandler` anymore, as that is now done in `CacheWarmerTaskHandler`.
___ 
# Upgrade Information
### MessageQueue Deprecations

For v6.5.0.0 we will remove our wrapper around the symfony messenger component and remove the enqueue integration as well. Therefore, we deprecated several classes for the retry and encryption handling, without replacement, as we  will use the symfony standards for that.

Additionally, we deprecated the `Shopwell\Core\Framework\MessageQueue\Handler\AbstractMessageHandler`, you should directly implement the `\Symfony\Component\Messenger\Handler\MessageSubscriberInterface` instead.

Before:
```php
class MyMessageHandler extends AbstractMessageHandler
{
    public static function getHandledMessages(): iterable
    {
        return [MyMessage::class];
    }

    public function handle(MyMessage $message): void
    {
        // do something
    }
}
```

After:
```php
class MyMessageHandler implements MessageSubscriberInterface
{
    public static function getHandledMessages(): iterable
    {
        return [MyMessage::class];
    }

    public function __invoke(MyMessage $message): void
    {
        // do something
    }
}
```
