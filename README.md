# cloudfront_invalidator

This is a module that I wrote that creates AWS CloudFront invalidations.

## Intro
A client had a need to implement CloudFront on their site and were encountering issues where invalidations were not happening fast enough.

## Problem
Clients needed specificity when invalidating caches
* Invalidate entities when they are edited
*  When certain entities and content types are updated, we needed to invalidate other pages
## Solution
* Connect module to AWS API
* Create admin form to allow for key/secret input
* Create admin form that allows for ad-hoc path invalidation
* Create admin form that allows users to to set Content Type specific path invalidations.
   *  This allows admins to give a list of paths that should be invalidated any time a node of the selected content type is created, updated, or deleted.
   *  For example, if we have a news page (/news) and a new news node is created, it will invalidate the /news path so that new node is shown as the newest news item in a view.
