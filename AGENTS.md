# Project Guidelines

## Stack

- Craft CMS 5.x with Twig
- Tailwind CSS 4 with the Typography plugin
- Alpine.js 3 loaded from the CDN in the page layouts
- DDEV for local PHP, Craft, Composer, Node, and database commands
- MariaDB

## Craft Plugins

- CKEditor (`craftcms/ckeditor`) for rich-text fields such as `body` and `bodyText`; `bodyText` supports inline images through the `pageImages` field
- Contact Form (`craftcms/contact-form`) for the site contact form
- Google Maps (`doublesecretagency/craft-googlemaps`) for market addresses and maps
- Expanded Singles (`verbb/expanded-singles`) for easier Single-section editing

This project does not use Formie. Google Maps browser and server keys come from environment variables; never hardcode credentials in templates or migrations.

## Development Workflow

- Run project commands through DDEV.
- Use `ddev composer install` to install the lock-file dependencies.
- Use `ddev composer update ...` only when intentionally changing dependencies.
- Use `ddev craft ...` for Craft CLI commands.
- Use `ddev npm ...` for frontend tooling.
- After Craft schema or project-config changes, run `ddev craft up --interactive=0`.
- After adding or changing Tailwind classes, run `ddev npm run build`.
- Use `ddev npm run dev` only when an ongoing Tailwind watch process is useful.

## Craft Content and Project Config

- Commit changes under `config/project/`.
- Use content migrations for seeded content, ownership changes, or data transformations that project config cannot represent.
- Make migrations idempotent and preserve existing content.
- `craft up` applies project config before content migrations. Write migrations against the final field handles and layouts, while allowing legacy handles when an older migration may run on a fresh environment.
- Do not edit Craft content tables directly except for read-only diagnostics. Use Craft element APIs in migrations.
- Check the actual field handle and entry-type layout before changing templates.

The shared `page` entry type is used by basic pages, blog posts, Contact, and the Advertisers, Venues, Markets, and Blog index Singles. It has `indexImage` for the hero and an ordered `pageContent` Matrix field. Do not restore the retired `pageBuilder`, `contactPage`, `indexPage`, or Blog entry types.

`pageContent` supports these blocks:

- Body Text (`bodyTextBlock`) containing the `bodyText` CKEditor field
- Body Image (`bodyImage`) containing the single-asset `pageImages` field
- Image + Text (`imageTextBlock`) containing `pageImages` and `bodyText`, rendered as a responsive image/copy grid
- Simple Grid (`simpleGridBlock`) containing the nested `simpleGrid` Matrix field

Editors can arrange Page Content blocks in any order. `simpleGrid` contains `featureCard` entries. CKEditor can also place Body Image entries inline within `bodyText`; use Image + Text when copy must remain paired with an image without floats.

The `market` entry type remains separate and continues to use `pageImage`, `bodyText`, `address`, and the existing Market templates. Do not move Market detail content into `pageContent`.

The Home Single uses its own `homePage` entry type. Its `homeContent` and `homeLogos` Matrix fields are limited by their configured layouts and should remain editable through Craft.

The Page consolidation and content transformation are handled by the `m261013_120000`, `m261013_121000`, and `m261013_130000` content migrations. Keep them idempotent and compatible with project config being applied before content migrations.

## Templates and Frontend

- Shared page templates extend `templates/_layouts/_page.twig`.
- The homepage at `templates/index.twig` is currently a standalone document and must receive shared head-level changes separately.
- Keep reusable site fragments in `templates/_partials/` when they are used by multiple templates.
- Render ordered Page blocks through `templates/_partials/_page-content.twig`. `templates/_partials/_entry-content.twig` handles both Page entries and the unchanged Market fields.
- The footer shows a small `Edit Page` link only when `currentUser` is logged in and the current `entry` has a control-panel edit URL. The site uses Craft Solo and assumes one editor.
- Prefer native Twig, Craft queries, Alpine.js, and inline Tailwind utilities over new abstractions or dependencies.
- Alpine is loaded with `defer`; use accessible state, keyboard behavior, and ARIA attributes for interactive controls.
- Heroicons are rendered as inline SVGs through `_partials/_heroicon.twig`; no Heroicons JavaScript package is installed.
- Source styles live in `src/css/app.css`.
- `web/css/app.css` is generated, tracked, and deployed. Always rebuild it before committing Tailwind changes because `deploy.sh` does not run npm.

## Assets

- The `photos` volume is stored in `web/photos/` and served from `$PRIMARY_SITE_URL/photos`.
- Uploaded assets are not committed. Sync `web/photos/` separately during deployment without deleting production-only uploads.
- Track migration source assets under `src/assets/` when a content migration needs to create Craft assets.
- Do not deploy generated control-panel resources or runtime caches.

## Verification

- PHP syntax: `ddev php -l path/to/file.php`
- Apply Craft changes: `ddev craft up --interactive=0`
- Frontend build: `ddev npm run build`
- Render checks: request each affected route through DDEV and confirm an HTTP 200 response.
- For content migrations, verify both the rendered result and the resulting element ownership/counts.

## Deployment

- Commit and push templates, project config, migrations, Composer lock changes, source assets, and the compiled `web/css/app.css` before deploying.
- `deploy.sh` pulls `main`, installs production Composer dependencies, runs `php craft up --interactive=0`, and clears Craft caches.
- Both the deployment user and PHP process must be able to write to `storage/` and `web/cpresources/`; otherwise `craft up` can fail while compiling nested-entry title templates.
- Sync the `web/photos/` volume separately.
- A local database import is acceptable for the initial launch when the local content should replace production. Back up production, enable maintenance mode, import the database, deploy the matching code and project config, sync `web/photos/`, run `php craft up --interactive=0`, clear caches, verify the site, and then disable maintenance mode.
- An imported local database already contains the applied content-migration records and migrated Page content; `craft up` should not rerun those migrations.
- Do not routinely import a local database after production content editing begins; deploy code, project config, and migrations instead.
- Keep production `.env` values on the server and confirm `PRIMARY_SITE_URL`, database credentials, security key, and Google Maps keys are correct.

## Approach

- Keep solutions simple and direct.
- Prefer inline native Tailwind utilities.
- Avoid unnecessary variables, helpers, components, dependencies, and configuration.
- Preserve unrelated work in a dirty worktree.
- Keep comments useful and concise.

## Communication

- Use a casual, concise tone.
