# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel 10 REST API backend (PHP 8.1+) providing user authentication and management endpoints. Uses Laravel Sanctum for token-based API auth (configured but not fully implemented). The project is in early stages (v1.0). Comments and messages are in Spanish.

## Common Commands

```bash
# Install dependencies
composer install
npm install

# Run dev server (http://localhost:8000)
php artisan serve

# Run all tests
php vendor/bin/phpunit

# Run a single test file
php vendor/bin/phpunit tests/Feature/ExampleTest.php

# Run a specific test suite (Unit or Feature)
php vendor/bin/phpunit --testsuite=Unit

# Lint/format PHP code
php vendor/bin/pint

# Database migrations
php artisan migrate
php artisan migrate:fresh --seed

# Generate app key (first-time setup)
php artisan key:generate
```

## Architecture

**API routes** are defined in `routes/api.php` (all prefixed with `/api`):
- `GET /api/users` — List users (via `App\Http\Controllers\Api\UserController@index`)
- `POST /api/login` — Credential validation (inline closure, no token generation)
- `GET /api/user` — Authenticated user info (requires `auth:sanctum`)

**API controllers** live in `app/Http/Controllers/Api/`. The login endpoint is currently an inline closure in the routes file, not extracted to a controller.

**CORS** is configured to allow requests only from `http://localhost:8080` (the expected frontend origin), defined in `config/cors.php`.

**Database**: MySQL by default. PHPUnit has SQLite in-memory config commented out in `phpunit.xml` — uncomment `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` to enable faster test runs without a real database.

**Authentication**: Sanctum is installed and the User model uses `HasApiTokens`, but the `/api/login` endpoint only validates credentials and returns user data without issuing a token.
