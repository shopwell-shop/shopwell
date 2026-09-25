---
title: Hot Reload - dont follow redirects, DDEV config
issue: NEXT-39321
author: Björn Meyer
author_email: b.meyer@shopwell.com
author_github: @BrocksiNet
---
# Storefront
* Changed the `start-hot-reload.js` script to not follow redirects when proxying requests.
  * When you activate the follow redirects option the cookies get wiped out and the session is lost.
* Changed the `start-hot-reload.js` script to support a DDEV setup without setting certificate files.
* Changed the `start-hot-reload.js` to make cart off-canvas work with hot reload.
