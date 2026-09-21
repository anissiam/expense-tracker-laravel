# Expense Tracker API

A Laravel REST API backend for a personal/household expenses tracker. It handles
budgets, category allocations, expenses, savings, templates, monthly reviews,
budget sharing with partners (invites) and voice-driven expense parsing.

## Tech Stack

| Layer      | Choice                                  |
| ---------- | --------------------------------------- |
| Framework  | Laravel 13 (`^13.8`)                    |
| PHP        | `^8.3` (Dockerfile uses PHP 8.4 CLI)    |
| Database   | SQLite by default (PostgreSQL supported) |
| Auth       | Laravel Sanctum (API tokens)            |
| Tests      | PHPUnit                                 |
| Frontend assets | Vite + Tailwind (minimal, API is the focus) |

## Requirements

- PHP `^8.3` with extensions: `mbstring`, `pdo_sqlite`, `xml`, `curl`, `zip`, `bcmath`
- Composer 2+
- Node.js + npm (only for asset build / `composer run dev` script)
- SQLite (default) or a PostgreSQL server

## Project Overview

The API is organized around a `User` that owns one or more `Budget`s. Each budget
contains `BudgetAllocation`s (how income is split across `Category`s/subcategories),
`Expense`s, a `BudgetClosing` and a `MonthlyReview`. Users can share budgets with
partners (`BudgetMember`, roles: `owner` / `editor` / `viewer`) via email invites.
`Template`s provide ready-made allocation schemes (e.g. 50/30/20, Ramadan, Travel,
Back to School). `Saving`s and `IncomingIncome` track savings goals and incoming
money separately.

### Main Domain Entities

- **User** – `name`, `email`, `password`, `currency_code`, `monthly_salary_day`, `timezone`; has `settings` (UserSetting)
- **Budget** – `name`, `type`, `total_amount`, `currency_code`, `start_date`, `end_date`, `status`, `template_id`
- **BudgetAllocation** – one budget → many category allocations with percentages
- **Category** – hierarchical (a category can have a `parent_id` subcategory), `type` = expense
- **Expense** – `amount`, `currency_code`, `date`, `description`, `payment_method`, `source`, `is_recurring`, `receipt_path`, optional `subcategory_id`
- **Template** – system-templated allocation configs (`is_system`, `is_default`)
- **Saving / SavingTransaction** – savings goals + transactions
- **IncomingIncome** – expected/actual incoming money, `mark-received`
- **BudgetMember** – partnership on a budget with `role` and `status` (pending/accepted/declined)
- **PaymentMethod**, **BudgetClosing**, **MonthlyReview**, **CurrencyRate**, **UserSetting** – supporting entities

## Getting Started (Development)

### 1. Install PHP/Composer dependencies

```bash
composer install
```

### 2. Environment configuration

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` if needed. The defaults use SQLite; the database file lives at
`database/database.sqlite` (create it if missing):

```bash
touch database/database.sqlite
```

To use PostgreSQL instead set the `DB_*` variables (see `.env.example`).

### 3. Migrate and seed

```bash
php artisan migrate
php artisan db:seed
```

The seeder installs default categories/subcategories and the system budget
templates (`Standard Household 50/30/20`, `Ramadan Special Budget`,
`Vacation & Travel Budget`, `Back to School Season`).

### 4. Start the dev servers (single command)

The `dev` script runs the API server, queue worker, log tail and Vite dev server
concurrently:

```bash
composer run dev
```

Or run them individually:

```bash
php artisan serve          # API on http://localhost:8000
php artisan queue:listen --tries=1 --timeout=0
npm run dev                # Vite (if you touch frontend assets)
```

### Alternative: one-shot setup

`composer run setup` installs everything from scratch (deps, `.env`, key, migrate, build).

## Testing

```bash
composer test
```

or directly:

```bash
php artisan test
```

Tests use a separate in-memory SQLite database (`DB_DATABASE=:memory:`), so no
local database is touched. Key feature suites:

- `tests/Feature/AuthApiTest.php` – register/login/logout/profile
- `tests/Feature/BudgetApiTest.php` – budget CRUD and allocations
- `tests/Feature/BudgetPartnerTest.php` – partner invites, roles & access control
- `tests/Feature/CategoryApiTest.php` – category management
- `tests/Feature/ExpenseIntegrityTest.php` – expense rules and budget integrity
- `tests/Feature/VoiceParserTest.php` – natural-language expense parsing

Run a single suite:

```bash
php artisan test --filter=AuthApiTest
```

## Project Structure

```
.
├── app/
│   ├── Http/Controllers/   # API controllers (Auth, Budget, Category, Expense,
│   │                       #   Saving, Template, BudgetPartner, Voice, Report, ...)
│   ├── Mail/               # PartnerInviteMail (budget invitation emails)
│   ├── Models/             # Eloquent models (see "Main Domain Entities")
│   ├── Policies/           # BudgetPolicy (authorization)
│   ├── Providers/          # Laravel service providers
│   └── Services/           # Business logic (BudgetService, ExpenseService,
│                           #   VoiceParserService, BudgetAccessService, ...)
├── bootstrap/              # App bootstrap & cache
├── config/                 # Laravel configuration (incl. cors, sanctum)
├── database/
│   ├── migrations/         # Schema for users, budgets, categories, expenses, ...
│   ├── seeders/            # Default categories + system templates
│   ├── factories/          # UserFactory
│   └── database.sqlite     # Local SQLite database (not committed)
├── public/                 # Web root (index.php, assets)
├── resources/              # Views & frontend assets (Vite entry)
├── routes/
│   ├── api.php             # All REST API routes (auth + protected groups)
│   ├── web.php             # Web routes
│   └── console.php         # Artisan console routes
├── storage/                # Logs, cache, uploads (not committed)
├── tests/                  # Feature + Unit tests (see "Testing")
├── Dockerfile              # PHP 8.4 CLI + server (EXPOSE 8000)
├── artisan
├── composer.json           # Deps & scripts (setup, dev, test)
└── phpunit.xml             # Test config (in-memory SQLite)
```

## API Overview

All routes are defined in `routes/api.php`. Public routes:

```
POST /api/register
POST /api/login
GET  /api/invites/{token}            # public invite preview
```

Everything else requires `auth:sanctum` (Bearer token). Highlights:

```
GET    /api/user/profile              # current user
POST   /api/logout

GET    /api/budgets/active/summary
/api/budgets  (apiResource CRUD)
GET/POST /api/budgets/{budget}/allocations

GET    /api/dashboard/summary
GET    /api/reports/monthly

POST   /api/budgets/{budget}/close
GET    /api/budgets/{budget}/closing
GET    /api/closings

/api/savings, /api/expenses, /api/categories,
/api/payment-methods, /api/incomings, /api/templates  (apiResource)

POST   /api/voice/parse               # parse a natural-language expense sentence
POST   /api/budgets/{budget}/partners  (throttled: 20/min)
GET/PATCH/DELETE /api/budgets/{budget}/partners/{member}

GET    /api/invites/pending
POST   /api/invites/{token}/accept | /decline
POST   /api/incomings/{incoming}/mark-received

GET    /api/budgets/active/summary    # active budget for current user
```

### Budget Sharing / Partner Roles

A user can invite a partner by email. The partner receives
`PartnerInviteMail` with a token. Invites can be accepted, declined, or previewed
via the public route. Once accepted, the partner is a `BudgetMember` with a role:

- `owner` – full control (the budget creator)
- `editor` – can view and edit
- `viewer` – read-only

Authorization is centralized in `app/Services/BudgetAccessService.php` and
`app/Policies/BudgetPolicy.php`.

### Voice Parsing

`POST /api/voice/parse` takes free-text like "spent 45 on groceries at Carrefour"
and converts it into a structured expense via `app/Services/VoiceParserService.php`.
Tests for it live in `tests/Feature/VoiceParserTest.php`.

## Necessary Files for New Developers

| File                | Purpose                                             |
| ------------------- | --------------------------------------------------- |
| `.env.example`      | Copy to `.env`. Never commit the real `.env`.       |
| `composer.json`     | Dependencies and the `setup` / `dev` / `test` scripts. |
| `phpunit.xml`       | Test environment (in-memory SQLite).                |
| `routes/api.php`    | The full API surface.                               |
| `database/seeders/DatabaseSeeder.php` | Default categories + system templates.   |
| `Dockerfile`        | Containerized dev/prod image (php:8.4-cli, port 8000). |

## Docker

```bash
docker build -t expense-tracker-api .
docker run -p 8000:8000 -e APP_KEY=$(php artisan key:generate --show) expense-tracker-api
```

The container runs migrations then serves on port 8000.

## Code Style

Laravel Pint is available as a dev dependency:

```bash
./vendor/bin/pint --test   # check
./vendor/bin/pint          # fix
```