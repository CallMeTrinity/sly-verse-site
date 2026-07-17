# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Craft CMS 5 website using Tailwind CSS 4 for styling and Vite for asset compilation. The site uses DDEV for local development and deploys via Ploi to UpCloud servers.

## Development Commands

### DDEV Environment
All commands should be prefixed with `ddev` when running in the DDEV environment:

```bash
# Start/stop DDEV
ddev start
ddev stop

# Install dependencies
ddev npm install
ddev composer install

# Build & watch assets
ddev npm run dev      # Start Vite dev server with HMR on port 3000
ddev npm run build    # Build production assets to web/dist/

# Craft CLI
ddev craft                           # Run any Craft console command
ddev craft setup                     # Initial Craft setup
ddev craft setup/app-id              # Generate APP_ID for .env
ddev craft setup/security-key        # Generate CRAFT_SECURITY_KEY
ddev craft project-config/rebuild    # Rebuild Project Config
ddev craft project-config/apply      # Apply Project Config changes
ddev craft clear-caches/all          # Clear all caches

# Database operations
ddev import-db                       # Import .sql file from project root
ddev export-db > backup.sql          # Export database

# Composer scripts (from composer.json)
ddev composer craft-update           # Run migrations and clear caches
ddev composer nuke                   # Nuclear option: remove vendor/, composer.lock, reinstall
```

### Running Tests
This boilerplate doesn't include a test suite by default.

## Architecture & Code Organization

### Frontend Architecture

**Vite Build System** (vite.config.js):
- Entry point: `src/js/app.js` (imports `src/css/app.css`)
- Output: `web/dist/` with manifest at `web/dist/.vite/manifest.json`
- Dev server: localhost:3000 with HMR enabled
- Static assets in `src/public/` are copied to `web/dist/` during build
- ViteRestart plugin watches `templates/**/*` for changes

**Tailwind CSS 4**:
- Using new `@tailwindcss/vite` plugin (Tailwind CSS 4 approach)
- Tailwind classes auto-sorted via `prettier-plugin-tailwindcss`
- Forms plugin: `@tailwindcss/forms`

**JavaScript**:
- Alpine.js for interactive components with `@alpinejs/focus` plugin
- ES modules enabled (`"type": "module"` in package.json)
- HMR support configured

### Backend Architecture

**Craft CMS Structure**:
- Bootstrap: `bootstrap.php` defines paths and loads Composer autoloader + dotenv
- Config: `config/` directory contains:
  - `general.php`: General Craft settings (dev determined by `CRAFT_ENVIRONMENT=dev`)
  - `vite.php`: Vite plugin config (dev server detection, manifest paths)
  - `app.php`: Yii application config, custom modules
  - `routes.php`: URL routing rules
- Custom modules: `modules/Module.php` (PSR-4 autoloaded as `modules\`)
  - Module ID is `my-module`, can be accessed via `Craft::$app->getModule('my-module')`
  - Uncomment `'bootstrap' => ['my-module']` in `config/app.php` to load on every request

**Template Structure**:
- `templates/_layout.twig`: Base layout with Vite script loading, Admin Bar, skip link, header/footer includes
- `templates/_includes/`: Reusable components (header, footer, alertBar, fonts, macros, contentMatrix)
- `templates/_includes/blocks/`: Content builder blocks (text, image, textImage, video, form)
- `templates/_pages/`: Page templates (_entry.twig, pageContent.twig)
- `templates/homepage.twig`: Homepage template
- `templates/404.twig`: 404 error page

**Vite Integration**:
- Craft Vite plugin (nystudio107/craft-vite) loads assets via `{{ craft.vite.script('src/js/app.js', false) }}`
- In dev: Loads from Vite dev server at PRIMARY_SITE_URL:3000
- In production: Loads from `web/dist/` using manifest.json

### Project Config Workflow

Craft uses [Project Config](https://craftcms.com/docs/5.x/system/project-config.html) to store database schema changes as YAML in `config/project/`:

1. Make database structure changes locally in the Craft control panel
2. Changes are automatically written to `config/project/*.yaml`
3. Commit YAML files to git
4. Other developers run `composer install` (triggers `craft-update` script) to apply changes
5. Deployment runs migrations automatically via composer scripts

**Important**: Never overwrite staging/production databases with local copy. All structure changes flow from local → staging/production via Project Config.

### Deployment Process

**Composer Scripts**:
- `craft-update`: Runs migrations (`craft up`) and clears caches (used by post-install/post-update hooks)
- `deploy-staging`: Runs `composer install` (Ploi runs this on staging when `main` branch updates)
- `deploy-production`: Runs `craft-update` only (Ploi rsyncs files from staging, then runs migrations)

**Deployment Flow**:
1. Push to `main` → Ploi auto-deploys to staging
2. Manual deploy from Ploi dashboard → Deploys staging to production

### Code Formatting

**Prettier Configuration** (.prettierrc.json):
- Tabs (width 3) for indentation
- Single quotes
- No semicolons
- 120 character line width
- Plugins: PHP, Twig, Tailwind class sorting
- Custom Twig multi-tags: nav/endnav, switch/case, cache/endcache, js/endjs, etc.

**Setup**: Configure editor to format on save. See README for VS Code setup articles.

## Key Configuration Details

### Environment Variables (.env)
Critical variables to set for local development:
- `CRAFT_ENVIRONMENT="dev"` (enables dev mode, Vite dev server)
- `SYSTEM_STATUS="on"`
- `CRAFT_SECURITY_KEY` (generate with `ddev craft setup/security-key`)
- `APP_ID` (generate with `ddev craft setup/app-id`)
- `PRIMARY_SITE_URL` (DDEV domain)
- Plugin license keys (copy from live site)

### DDEV Hooks
Post-start hooks in `.ddev/config.yaml`:
- `npm install` → Install/update npm packages
- `npm run build` → Build assets to web/dist/
- `composer install` → Install Craft/plugins + run migrations

These run automatically on `ddev start`.

### PHP Requirements
- PHP 8.2+ (DDEV config uses 8.3)
- Node 20+ (enforced by package.json engines + .npmrc)
- Composer 2

## Plugins Installed

Key Craft plugins (from composer.json):
- **nystudio107/craft-vite**: Vite asset integration
- **nystudio107/craft-seomatic**: SEO management (title/meta/OG rendered via `{% hook 'seomaticRender' %}` in `_layout.twig`; per-page overrides via `{% do seomatic.meta.… %}` at the top of templates)
- **craftcms/ckeditor**: Rich text editor
- **craftcms/feed-me**: Content imports
- **craftcms/guest-entries**: Public character proposal form
- **wbrowar/craft-admin-bar**: Frontend admin bar
- **voku/stringy**: Not a plugin — explicit dependency required by SEOmatic (do not remove)

Note: SEOmatic forces `robots: none` and omits the canonical tag in dev mode (`🚧` title prefix); both render normally in production.

## Important Notes

### Asset Syncing
Use rsync to sync uploads from staging/production:
```bash
# Staging to local
rsync -rtP --delete ploi@SERVER.IP:/home/ploi/staging.domain.com/web/uploads/ web/uploads/

# Production to local
rsync -rtP --delete ploi@SERVER.IP:/home/ploi/domain.com/web/uploads/ web/uploads/
```

### Craft License Key
`config/license.key` is not in git - must be copied from server for local dev.

### General Config Customizations
Notable settings in `config/general.php`:
- Week starts Monday (`defaultWeekStartDay(1)`)
- CP trigger: `/admin`
- Email as username (`useEmailAsUsername()`)
- 100MB upload limit
- Template caching only on production
- Preview tokens last 1 month
- Custom file kind: SVG-only option for asset fields
- GraphQL disabled
