# Project Guidelines

## Stack

- Craft CMS 5.x with Twig
- Tailwind CSS 4 with the Typography plugin
- Alpine.js 3 loaded from the CDN in the page layouts
- DDEV for local PHP, Craft, Composer, Node, and database commands
- MariaDB

## Craft Plugins

- CKEditor (`craftcms/ckeditor`) for rich-text fields such as `body` and `bodyText`
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

The `indexPage` entry type is shared by the Advertisers, Venues, Markets, and Blog index Singles. `bodyText` is optional. `simpleGrid` is optional and conditionally shown only for the Advertisers, Venues, and Markets indexes.

The Home Single uses its own `homePage` entry type. Its `homeContent` and `homeLogos` Matrix fields are limited by their configured layouts and should remain editable through Craft.

## Templates and Frontend

- Shared page templates extend `templates/_layouts/_page.twig`.
- The homepage at `templates/index.twig` is currently a standalone document and must receive shared head-level changes separately.
- Keep reusable site fragments in `templates/_partials/` when they are used by multiple templates.
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
- Sync the `web/photos/` volume separately.
- A local database import is acceptable for the initial launch after taking a backup and enabling maintenance mode.
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
