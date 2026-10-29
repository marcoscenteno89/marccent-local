# Marccent Local

A WordPress plugin for a small local business on a Marccent site: its details once (name, phone, location, services; Settings → Business), a page for each city or area it serves with their directory, and the search and answer-engine layer: titles, descriptions, Open Graph, JSON-LD, robots.txt, llms.txt, sitemaps, and a map in the theme's colors.

Requires [Marccent Blocks](https://github.com/marcoscenteno89/marccent-blocks) (WordPress won't activate it without): the Marccent theme's styles and scripts come from there, one shared copy at one version, so this plugin bundles none of it. No build step.

## Uninstalling

Deleting the plugin removes its settings (Settings → Business). The city pages and the SEO fields on posts stay: they're
the site's content, and they're back as they were if the plugin is installed again.
