# Marccent Local (retired)

**Retired 2026-11-06.** Everything it did is Marccent Content's or Marccent Blocks' now, so a local business site needs only those two and its kit (field groups, types and patterns as data): the business is the site's Business group with a schema mapping (`"id": "organization"`); opening hours are Content's Opening hours field type (`{{ business.hours:open_now }}`, the Field block's table, `openingHoursSpecification`); the map takes tokens (`{{ business.latitude }}`); locations are a Loop over the Locations rows; the call bar becomes a pattern in the footer once marccent-theme has T-003's classes. The code stays for older sites; nothing new uses it.

A WordPress plugin for a small local business on a Marccent site. It makes nothing of its own (RULES.md A9): the business is the site's field groups under **Site content** (Business: name, phone, address, hours…; a group's rows for every list: Services, Locations, Closed days, Profiles…), shown anywhere with `{{ business.phone }}` and Loops, and the cities are the site's `city` posts (Fields → Post types), listed on a Service Areas page of Loops. This plugin reads them for its part of the JSON-LD (the business, its locations, the places it serves, a Service on a service or city page; Marccent Content builds the rest), the hours shortcodes, the phone call bar and the map's default place.

Titles, descriptions, Open Graph, sitemaps, robots.txt, llms.txt, redirects with the 404 log and IndexNow are [Rank Math](https://wordpress.org/plugins/seo-by-rank-math/)'s, with its Schema and Local SEO modules off: the structured data is Marccent Content's (from the pages and their fields), with this plugin's business in it (the JSON-LD's titles and descriptions are the ones Rank Math prints).

Requires [Marccent Blocks](https://github.com/marcoscenteno89/marccent-blocks) and Marccent Content (WordPress won't activate it without): the Marccent theme's styles and scripts come from the first, one shared copy at one version, so this plugin bundles none of it. No build step.

## Opening hours and locations

Site content → Business, Monday to Sunday: each day's hours (two spans if it closes for lunch); Closed days: the days
that differ (holidays, a short day). Search gets them as `openingHoursSpecification` and its special days.
On a page: `[business_hours]` (the week, today in bold, the coming days that differ) and `[open_now]` ("Open now ·
until 5 PM"). A business with several offices or shops lists them as Locations rows: each is a LocalBusiness of
its own in the schema, part of the business, and `[business_locations]` shows them.

## Tracking codes and cookie consent

They're the Marccent WP theme's now (Appearance → Marccent theme → Tracking and cookies), so a site without this plugin
has them too. A site that had them here gets them moved there once (its saved codes and banner).

## Export

The business and the call bar are Site content, so Tools → Marccent export (Marccent Blocks) exports and imports them with Marccent Content's part. A site that had Settings → Business (before 0.4.0) gets its details moved into the groups once. Rank Math's settings and redirects export from Rank Math → Status & Tools → Import & Export.

## Uninstalling

Deleting the plugin removes what older versions kept (Settings → Business, the call bar). The business's groups'
values (Site content) and the city pages stay: they're the site's content, and they're back as they were if the plugin
is installed again.
