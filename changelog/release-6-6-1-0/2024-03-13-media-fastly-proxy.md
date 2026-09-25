---
title: Media fastly proxy
issue: NEXT-34343
author: oskroblin Skroblin
author_email: o.skroblin@shopwell.com
---

# Core
* Added `MediaReverseProxy` which allows to proxy media requests to Fastly or any other CDN
* Added `FastlyMediaReverseProxy`, which allows native support for Fastly
* Added `showpare.cdn.fastly` config to enable Fastly as a media proxy
* Added `MediaPathChangedEvent`, which is dispatched when the media path is changed on file system level
___
# Upgrade Information
## Configure Fastly as media proxy
When you are using Fastly as a media proxy, you should configure this inside shopwell, to make sure that the media urls are purged correctly.
Enabling Fastly as a media proxy can be done by setting the `shopwell.cdn.fastly` configuration (for example with an env variable):

```yaml
shopwell:
    fastly:
        api_key: '%env(FASTLY_API_KEY)%'
```
