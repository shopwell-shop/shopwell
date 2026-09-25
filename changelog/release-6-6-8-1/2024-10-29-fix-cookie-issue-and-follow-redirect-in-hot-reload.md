---
title: Fix cookie issue and follow redirect in HOT reload
issue: NEXT-39321
author: Björn Meyer
author_email: b.meyer@shopwell.com
author_github: @BrocksiNet
---
# Storefront
* Changed from `http-proxy` to `http-proxy-middleware` for HOT reloading
* Changed `start-hot-reload` script to follow redirects and set cookies
