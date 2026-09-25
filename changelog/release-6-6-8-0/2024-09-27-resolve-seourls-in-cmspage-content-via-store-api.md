---
title: Resolve seoUrls in cmsPage content via store API
issue: NEXT-33839
author: Björn Meyer
author_email: b.meyer@shopwell.com
author_github: @BrocksiNet
---
# API
* Added `CmsLinksForStoreApiSubscriber` to replace the links with UUIDs with seoUrls
  * This only changes the output for the `store-api`
  * This only changes links in content type `text` or `html`
  * The subscriber is using one concatenated string to replace all links to reduce db calls
  * This only changes links in translated content and config content fields but not in data (there you have the original content from admin) of that slot
