# Changelog

## 4.2 — 2026-11-07

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 4.1 — 2026-08-14

- Exclude password protected entries from ping action
- Enforces some verification before registering module

## 4.0.2 — 2026-08-05

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 4.0.1 — 2026-08-05

- Cope with ids given by ArrayObject

## 4.0 — 2026-08-03

- Avoid using magic methods for user preferences and blog settings (2.39+)
- Use MetaRecord::strField(), MetaRecord::intField() and MetaRecord::boolField() whenever is possible

## 3.9 — 2026-06-27

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 3.8 — 2026-04-04

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 3.7 — 2026-03-30

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 3.6 — 2026-03-22

- Add an option to restrict automatic ping to one category only
- DA -&gt; DC

## 3.5 — 2025-12-30

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 3.4 — 2025-12-22

- Add an option to enable/disable ping on 1st publication for entries

## 3.3 — 2025-11-28

- PHP 8.5 compliance

## 3.2 — 2025-10-29

- Better this way
- FIX: PascalCase for accented characters

## 3.1 — 2025-09-22

- Use backend services rather than static calls

## 3.0 — 2025-09-07

- DC 2.36 compliance

## 2.2.2 — 2025-07-23

- Use category name rather than tag for category options (thanks Tomek)

## 2.2.1 — 2025-05-22

- Prefix is not required

## 2.2 — 2025-02-26

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 2.1 — 2025-01-11

- Be conservative (will see in future if something more is needed)

## 2.0 — 2025-01-09

- 2.33 compliance

## 1.8.1 — 2024-05-29

- Fix description

## 1.8 — 2024-05-25

- Sanitize tags and categories references

## 1.7 — 2024-05-24

- Add an option to include categories (post cat' and post cat's parents) as tags

## 1.6 — 2024-04-27

- Add a newline between prefix+title, tags and URI, thanks xakan (https://github.com/xakan) for suggestion

## 1.5 — 2024-04-25

- Cope with #tag facets

## 1.4 — 2024-04-15

- Review tag management

## 1.3.4 — 2024-04-12

- Fix card image embedding

## 1.3.3 — 2024-04-12

- Version de maintenance, sans changement fonctionnel notable relevé dans l’historique.

## 1.3.2 — 2024-04-12

- Fix post structure

## 1.3.1 — 2024-04-12

- Fix upload media API return type

## 1.3 — 2024-04-10

- Add several tag conversion modes (camelCase by default)
- Fix test on return value
- Don't check SSL if debug and dev modes on
- Add entry card if possible

## 1.2 — 2024-02-03

- Add lang information to post record

## 1.1 — 2024-01-29

- Add correct icons

## 1.0 — 2024-01-29

- Fix Record facets
- Don't add posts/pages action if plugin not activated
- Cope with URL in post title too
- Fix current version in dcstore.xml
- Add a notice about tags/hashtags
- Add URL facet
- Check for cURL capabilities (installed and enabled)
- 1st commit
