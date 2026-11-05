# Marccent Local

A WordPress plugin for a small local business on a Marccent site: its details once (name, phone, location, services; Settings → Business), a page for each city or area it serves with their directory, its part of the JSON-LD (the business, its locations, the places it serves, a Service on a service or city page, the areas archive; Marccent Content builds the rest from the fields), the phone call bar, and a map in the theme's colors.

Titles, descriptions, Open Graph, sitemaps, robots.txt, llms.txt, redirects with the 404 log and IndexNow are [Rank Math](https://wordpress.org/plugins/seo-by-rank-math/)'s, with its Schema and Local SEO modules off: the structured data is Marccent Content's (from the pages and their fields), with this plugin's business in it (the JSON-LD's titles and descriptions are the ones Rank Math prints).

Requires [Marccent Blocks](https://github.com/marcoscenteno89/marccent-blocks) (WordPress won't activate it without): the Marccent theme's styles and scripts come from there, one shared copy at one version, so this plugin bundles none of it. No build step.

## Opening hours and locations

Settings → Business → Opening hours: each day's hours (two spans if it closes for lunch) and the days that differ
(holidays, a short day). Search gets them as `openingHoursSpecification` and its special days.
On a page: `[business_hours]` (the week, today in bold, the coming days that differ) and `[open_now]` ("Open now ·
until 5 PM"). A business with several offices or shops lists them under Locations (JSON): each is a LocalBusiness of
its own in the schema, part of the business, and `[business_locations]` shows them.

## Tracking codes and cookie consent

They're the Marccent WP theme's now (Appearance → Marccent theme → Tracking and cookies), so a site without this plugin
has them too. A site that had them here gets them moved there once (its saved codes and banner).

## Export

Tools → Marccent export (Marccent Blocks) exports and imports the business details and the call bar (the tracking codes and the consent banner are the theme's part of it). Rank Math's settings and redirects export from Rank Math → Status & Tools → Import & Export.

## Uninstalling

Deleting the plugin removes its settings (Settings → Business). The city pages stay: they're
the site's content, and they're back as they were if the plugin is installed again.
