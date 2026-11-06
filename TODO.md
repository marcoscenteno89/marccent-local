# Notes

**Retired (2026-11-06)**: no new work here. Left: the call bar as a footer pattern (marccent-theme T-003), then remove the plugin from the repo once no site has it active (crm-sample has it on, unused).


- Speed pass, not committed (peer review first): the schema's site-wide part built once (`mcl_schema_site()`, the
  `mcl_schema` transient, cleared by `mcl_schema_forget()`); service pages in one query; the flush flag and
  options read without a query. Also a fix: draft, private or trashed service pages no longer reach the schema or
  `llms.txt`; check it against a client site, since it changes their output.
- **Two hero images with `fetchpriority="high"`**: `inc/performance.php`'s first-image rule can mark one image while
  WordPress marks another. Drop ours for core's, or skip it when core has marked one.
- **The schema cache's limits**: it doesn't see posts changed by direct SQL, or a logo replaced in place under the
  same ID; it rebuilds on every request if `home_url` changes per request (`WP_HOME` from the request's host).
- [x] **Full words in Settings → Business's keys** (RULES.md A5): done in 0.4.0, where the business became Site content's field groups (`latitude`, `monday`, `postal_code`, `questions_heading`, `call_to_action_heading`…), every list a group's rows. (found 2026-11-05)
- [ ] **Front end built in PHP** (RULES.md A6, A9): the phone call bar (`inc/callbar.php`, printed in `wp_footer`; needs marccent-theme T-003 and a Call bar block in Marccent Blocks, then the Call bar group goes). The Service Areas archive is done: a page of nested Loops, the `city` type the site's own (0.5.0). (found 2026-11-05)
- [ ] **Its own CSS** (RULES.md A1): `assets/site.css` (breadcrumbs, facts, tables, link chips) until marccent-theme has them (T-005, T-007). (found 2026-11-05)
- [ ] **The hours as shortcodes** (`[business_hours]`, `[open_now]`, `[business_locations]`): markup made in PHP that the block editor can't change (RULES.md A9). Request: an Opening hours block (Marccent Blocks) reading the Business group and Closed days, and Locations as a Loop over its rows. (found 2026-11-05)
