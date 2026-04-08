# Harness (PHP)

https://harness.heliohost.us/

**Version:** 0.1.0 (override at runtime with `HARNESS_VERSION`).

PHP + MySQL version of Harness: register, log in, record messages you hear, and see other users who attached the same text (linked victims).

## Runtime requirements

- **PHP 7.4+** — matches common “PHP 7.x / 8.x” offers on Czech free hosting (no PHP 8-only syntax in this project). Extensions: **`pdo_mysql`**, **`gettext`**
- **MySQL** 5.7+ or **MariaDB** 10.3+

## 1. Database

Create a database and load the schema:

```bash
mysql -u YOUR_USER -p YOUR_DATABASE < sql/schema.sql
```

## 2. Configuration

Environment variables (defaults suit local MySQL with empty password):

| Variable | Default |
|----------|---------|
| `HARNESS_VERSION` | `0.1.0` |
| `HARNESS_DB_DSN` | `mysql:host=127.0.0.1;dbname=harness;charset=utf8mb4` |
| `HARNESS_DB_USER` | `root` |
| `HARNESS_DB_PASS` | *(empty)* |

Or edit `config/config.php`.

## 3. Run

Document root must be **`public/`** (so every request hits `public/index.php`, which loads `bootstrap.php`).

```bash
cd php
php -S localhost:8080 -t public
```

Open **http://localhost:8080** — you should be redirected to login.

**Apache:** set `DocumentRoot` to `php/public` and enable `.htaccess` in that folder so URLs rewrite to `index.php`.

**nginx:** route to `public/index.php` via `try_files` and FastCGI.

## 4. Demo user (after empty DB)

If both `message` and `victim` are empty on first request, sample rows are inserted:

- **Username:** `user`
- **Password:** `123456`

## Code layout (MVC)

| Layer | Folder | Purpose |
|-------|--------|---------|
| **Model** | `model/` | Plain objects: `Victim`, `Message` |
| **Model** | `repository/` | SQL only (`VictimRepository`, `MessageRepository`) |
| **Service** | `service/` | Business logic: auth, registration, profiles, seed |
| **Controller** | `controller/` | Read request, call services, render `templates/*.phtml` |
| **Entry** | `Application.php`, `Router.php` | Wire everything and match URLs |

No PHP namespaces — one global class name per file (`ClassName.php`). `helpers.php` defines `h()` for escaping HTML and **`tr()`** for translations (English = source string; Czech = GNU gettext).

## Language / translations

UI strings use **`tr('English source string')`** in PHP and templates. Catalogs live under **`locale/<LOCALE>/LC_MESSAGES/`** (`harness.po` / `harness.mo`). See [locale/README.md](locale/README.md) for **msgfmt**, **xgettext** (use `--keyword=tr`), and Poedit workflows.

**Switch language:** `?lang=en` or `?lang=cz` (stored in session). Czech uses locale `cs_CZ`; English uses `en_US` (fallback: untranslated msgid when no `.mo`).

**Compile after editing a `.po` file:**

```bash
msgfmt -o locale/cs_CZ/LC_MESSAGES/harness.mo locale/cs_CZ/LC_MESSAGES/harness.po
```

## Questions?
Ask at https://github.com/michaloblastni/harness/discussions/1
