# Notes

- Speed pass, not committed (peer review first): the schema's site-wide part built once (`mcl_schema_site()`, the
  `mcl_schema` transient, cleared by `mcl_schema_forget()`); service pages in one query; the flush flag and
  options read without a query. Also a fix: draft, private or trashed service pages no longer reach the schema or
  `llms.txt`; check it against a client site, since it changes their output.
- **Two hero images with `fetchpriority="high"`**: `inc/performance.php`'s first-image rule can mark one image while
  WordPress marks another. Drop ours for core's, or skip it when core has marked one.
- **The schema cache's limits**: it doesn't see posts changed by direct SQL, or a logo replaced in place under the
  same ID; it rebuilds on every request if `home_url` changes per request (`WP_HOME` from the request's host).
- [ ] **Full words in Settings → Business's keys** (RULES.md A5): `h1`, `faq`, `faq_heading`, `cta_heading`, `cta_text`, `quote_href` (archive), `lat`, `lng` (business, locations), `mo`…`su` (hours), `postal`; rename with the old ones still read. City fields and questions done (0.3.x, mcl_full_word_fields). (found 2026-11-05)
- [ ] **Front end built in PHP** (RULES.md A6), until Marccent Local is retired into Marccent Content and the theme: the phone call bar (`inc/callbar.php`, printed in `wp_footer`; needs marccent-theme T-003 and a block), the Service Areas archive (`mcl_render_directory`; becomes a page of nested Loops). (found 2026-11-05)
