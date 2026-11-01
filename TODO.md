# Notes

- Speed pass, not committed (peer review first): the schema's site-wide part built once (`mcl_schema_site()`, the
  `mcl_schema` transient, cleared by `mcl_schema_forget()`); service pages in one query; the flush flag and
  options read without a query. Also a fix: draft, private or trashed service pages no longer reach the schema or
  `llms.txt`; check it against a client site, since it changes their output.
- **Unsaved tracking codes cost a query a page**: a site that imported its business but never saved Settings →
  Business has no `mcl_tracking` option. Add it (autoloaded) on activation or import.
- **Two hero images with `fetchpriority="high"`**: `inc/performance.php`'s first-image rule can mark one image while
  WordPress marks another. Drop ours for core's, or skip it when core has marked one.
- **The schema cache's limits**: it doesn't see posts changed by direct SQL, or a logo replaced in place under the
  same ID; it rebuilds on every request if `home_url` changes per request (`WP_HOME` from the request's host).
