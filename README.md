# MUK MHOB — Cooking Recipes System

A vanilla PHP recipe sharing platform with MySQL, featuring role-based access, recipe CRUD with cascading filters, auto-calorie calculation from ingredients, Cloudinary image uploads, and a full admin portal.

## Tech Stack

- **Backend:** Vanilla PHP (no framework)
- **Database:** MySQL (XAMPP)
- **Frontend:** Vanilla CSS, Material Icons, Gruvbox dark/light theme
- **Images:** Cloudinary (free tier — 25 GB storage)
- **No build tools, no package manager, no JavaScript framework**

## Directory Structure

```
Web/
├── index.php              ← Redirects to public/
├── .env                   ← DB + Cloudinary credentials
├── AGENTS.md              ← OpenCode agent instructions
├── includes/              ← Shared infrastructure
│   ├── db.php             ← Single PDO connection
│   ├── env.php            ← .env loader
│   ├── cloudinary.php     ← Upload/delete helpers
│   ├── navbar.php
│   └── footer.php
├── public/                ← Web-accessible pages
│   ├── index.php          ← Landing page
│   ├── auth/              ← Login, register, profile
│   ├── crs_app/           ← Browse, view, create, edit, delete recipes
│   ├── admin/             ← Dashboard, CRUD tables, messages
│   ├── user/              ← My recipes, favorites, reviews
│   ├── terms.php, privacy.php, contact.php
│   └── img/logo.svg
└── database/
    ├── schema.sql
    └── seed_nutrition.sql
```

## Setup

### 1. Requirements
- XAMPP (Apache + MySQL + PHP 8.0+)
- PHP `curl` extension enabled (for Cloudinary uploads)

### 2. Database
```bash
# Create database and tables
/opt/lampp/bin/mysql -u root < database/schema.sql

# (Optional) Seed nutrition data for auto-calorie calculation
/opt/lampp/bin/mysql -u root crs_app < database/seed_nutrition.sql
```

### 3. Configuration
Copy `.env` and fill in your credentials:

```env
DB_HOST=localhost
DB_NAME=your_app
DB_USER=root
DB_PASS=root_pw

CLOUDINARY_CLOUD_NAME=your-cloud
CLOUDINARY_API_KEY=123456789
CLOUDINARY_API_SECRET=abc123
```

Defaults work for local XAMPP (root/no password). Cloudinary is optional — avatar upload just won't work without it; the app still runs.

### 4. Serve
Place in XAMPP docroot (`/opt/lampp/htdocs/Web`) and visit:

```
http://localhost/Web/
```

## Roles

| Role | Access |
|------|--------|
| **Admin** | Full access — admin portal, user management, recipe CRUD, all settings |
| **Content Collector** | Recipe CRUD + all user features (no admin portal) |
| **User** | Browse recipes, submit reviews, save favorites |

## Features

- **Recipe browsing** with cascading filters (food type → region → country)
- **Recipe detail** with ingredients, reviews, YouTube embed, auto calorie calculation
- **Recipe CRUD** with ingredient autocomplete, category tags, transaction safety
- **User profiles** with avatar upload (Cloudinary), activity stats, recent recipes/reviews
- **Favorites** with card grid, search, food type filter, sort, order toggle
- **Admin portal** with dashboard, charts, full CRUD for all entities, search/filter/sort on all pages
- **Contact form** with admin message inbox
- **Dark/light theme** persisted in localStorage
- **Auto-calories** calculated from ingredient nutrition data

## Development

No build step — edit PHP files directly. All styles are inline `<style>` blocks per page. The app runs on XAMPP Apache + MySQL.

### Key conventions
- One PDO connection (`includes/db.php`) — all pages require this
- Prepared statements everywhere — no raw SQL interpolation
- `env()` helper reads from `.env` file with default fallbacks
- Material Icons via Google Fonts CDN
- Gruvbox-inspired color scheme with CSS variables

## License

MIT
