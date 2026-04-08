# Translations (GNU gettext style)

- **Domain:** `harness` (used when the PHP gettext extension loads a `.mo` file).
- **Catalogs:** `locale/<LOCALE>/LC_MESSAGES/harness.po` (source for translators). Optional: `harness.mo` (binary for the gettext extension).

## Locales

| Session / URL `lang=` | Catalog |
|----------------------|---------|
| `en` (default) | English is always the **`tr('...')` source string** in PHP/templates. |
| `cz` | `locale/cs_CZ/LC_MESSAGES/harness.po` |

## How Czech is loaded (WAMP / no `.mo`)

For `lang=cz`, `tr()` loads Czech strings from **`harness.po` at runtime** (parsed in PHP). You do **not** need `harness.mo` or the gettext extension for translations to appear.

Optional speed-up: generate a PHP cache file (same content as parsing `.po`):

```bash
php tools/generate-harness-catalog.php
```

This writes `locale/cs_CZ/harness_catalog.php`. If that file exists, it is used instead of parsing `.po` on each request.

## gettext extension (optional)

If `extension=gettext` is enabled **and** `harness.mo` is present, `tr()` prefers gettext’s output when it differs from the English msgid (so you can use compiled catalogs in production).

Compile:

```bash
msgfmt -o locale/cs_CZ/LC_MESSAGES/harness.mo locale/cs_CZ/LC_MESSAGES/harness.po
```

On Linux, ensure locale data exists for `cs_CZ.UTF-8` when relying on gettext.

## Updating translations

1. Edit `locale/cs_CZ/LC_MESSAGES/harness.po` (add `msgid` / `msgstr` pairs; match template `tr('...')` strings exactly).
2. Reload the site with `?lang=cz` — PO is read automatically.
3. Optionally run `php tools/generate-harness-catalog.php` or `msgfmt` as above.

## Automating string extraction

English strings in code are wrapped in `tr('...')`. To refresh a POT template:

```bash
find . \( -name '*.php' -o -name '*.phtml' \) -not -path './vendor/*' | xargs xgettext --from-code=UTF-8 -o locale/harness.pot -L PHP --keyword=tr --keyword=sprintf:1
```

Then merge into `harness.po` with **Poedit**, **msgmerge**, or CI.

## Dynamic strings

Use `sprintf(tr('Page not found: %s'), $path)` or `sprintf(tr("%s's Messages"), $name)` so placeholders stay in the msgid for translators.
