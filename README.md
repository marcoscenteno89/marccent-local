# Marccent Local

A WordPress plugin for a small local business on a Marccent site: its details once (name, phone, location, services; Settings → Business), a page for each city or area it serves with their directory, and the search and answer-engine layer: titles, descriptions, Open Graph, JSON-LD, robots.txt, llms.txt, sitemaps, and a map in the theme's colors.

Requires [Marccent Blocks](https://github.com/marcoscenteno89/marccent-blocks) (WordPress won't activate it without): the Marccent theme's styles and scripts come from there, one shared copy at one version, so this plugin bundles none of it. No build step.

## Tracking codes

Settings → Business → Tracking codes takes the HTML that Google Tag Manager, Analytics, Meta and the like give you, in
the three places they ask for: in `<head>`, right after `<body>` opens (GTM's `<noscript>`), and before `</body>`. They're
their own option (`mcl_tracking`), so a content import that rewrites the business details leaves them alone. Only an
administrator who can post unfiltered HTML can change them.

## Export

Tools → Marccent export (Marccent Blocks) exports and imports the business details (without the Maps key, a secret tied to the site's domain) and the tracking codes.

## Uninstalling

Deleting the plugin removes its settings (Settings → Business). The city pages and the SEO fields on posts stay: they're
the site's content, and they're back as they were if the plugin is installed again.
