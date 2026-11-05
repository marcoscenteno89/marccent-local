# Marccent Local

A WordPress plugin for a small local business on a Marccent site: its details once (name, phone, location, services; Settings → Business), a page for each city or area it serves with their directory, and the search and answer-engine layer: titles, descriptions, Open Graph, JSON-LD, robots.txt, llms.txt, sitemaps, and a map in the theme's colors.

Requires [Marccent Blocks](https://github.com/marcoscenteno89/marccent-blocks) (WordPress won't activate it without): the Marccent theme's styles and scripts come from there, one shared copy at one version, so this plugin bundles none of it. No build step.

## Opening hours and locations

Settings → Business → Opening hours: each day's hours (two spans if it closes for lunch) and the days that differ
(holidays, a short day). Search gets them as `openingHoursSpecification` and its special days; `llms.txt` lists them.
On a page: `[business_hours]` (the week, today in bold, the coming days that differ) and `[open_now]` ("Open now ·
until 5 PM"). A business with several offices or shops lists them under Locations (JSON): each is a LocalBusiness of
its own in the schema, part of the business, and `[business_locations]` shows them.

## Redirects and the 404 log

Tools → Redirects sends old addresses to new ones (301, 302, 307, or 410 gone): an exact path, or a path and everything
under it (`/old/*` → `/new/*`). Addresses visitors asked for that don't exist are logged with how often and from where,
each one click from a redirect. Exported with the site's settings.

## Cookie consent

Settings → Business → Cookie consent turns on Google Consent Mode v2 and a small banner: Google's tags start denied
and follow the visitor's answer. "Hold the tracking codes" keeps tags that don't read Consent Mode (Meta's pixel,
heatmaps) from running until the visitor accepts. A link to `#cookie-settings` opens the banner again.

## IndexNow

A page published, changed or taken down is sent to Bing, Yandex and the other IndexNow engines a minute later (one
request for a burst of changes), with the site's key at `/<key>.txt`. Off for `.localhost` / `.test` addresses and
sites that ask not to be indexed; a checkbox under Settings → Business → Search engines.

## Tracking codes

Settings → Business → Tracking codes takes the HTML that Google Tag Manager, Analytics, Meta and the like give you, in
the three places they ask for: in `<head>`, right after `<body>` opens (GTM's `<noscript>`), and before `</body>`. They're
their own option (`mcl_tracking`), so a content import that rewrites the business details leaves them alone. Only an
administrator who can post unfiltered HTML can change them.

## Export

Tools → Marccent export (Marccent Blocks) exports and imports the business details, the tracking codes, the consent banner and the redirects.

## Uninstalling

Deleting the plugin removes its settings (Settings → Business). The city pages and the SEO fields on posts stay: they're
the site's content, and they're back as they were if the plugin is installed again.
