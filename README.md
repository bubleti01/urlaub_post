# urlaub_post

WordPress-Plugin zur Verwaltung und Anzeige von Urlaubszeiträumen inkl. Shortcodes, Dynamic Block und optionalem Export nach WP-Opening-Hours.

## Installation

1. Plugin-Dateien als ZIP-Paket bereitstellen (z. B. aus einem Release-Artifact).
2. In WordPress unter **Plugins → Installieren → Plugin hochladen** hochladen.
3. Aktivieren.

## Shortcodes

- `[urlaub]`
- `[vacation_notice]`

Attribute:

- `show_image="1|0"`
- `show_dates="1|0"`
- `limit="0|n"`
- `id="123"`

## Einstellungen

Unter **Urlaub → Einstellungen**:

- Vorankündigungstage (`urlaub_post_pre_days`)
- Optionaler Export in WP-Opening-Hours
