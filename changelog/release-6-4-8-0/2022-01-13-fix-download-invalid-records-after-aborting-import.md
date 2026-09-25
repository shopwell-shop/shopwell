---
title: Fix download of invalid records after aborting import
issue: NEXT-19152
author: Krispin Lütjann
author_email: k.luetjann@shopwell.com
author_github: King-of-Babylon
---
# Core
* Added merging of invalid records files to the aborting api method to fix the download of the invalid records file
    * Added a new method `abort` to the `Content/ImportExport/ImportExport.php` service
