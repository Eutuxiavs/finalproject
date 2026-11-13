# Yonzon: Ownership Registry and Marketplace

Symfony 8.1 / PHP 8.4 / Doctrine / MySQL. Register what you own, list it for sale or trade, report lost items, and
transfer ownership only after **both** sides confirm the handover.

## Run it

```bash
docker compose down --remove-orphans     # clears the old unused Postgres container
docker compose up -d                     # MySQL on :3307, phpMyAdmin on :8080
composer install
composer require symfony/rate-limiter    # needed for login throttling (once)

# fresh database, using one clean migration
symfony console doctrine:database:drop --force --if-exists
symfony console doctrine:database:create
# delete the old files in migrations/ first, then:
symfony console make:migration
symfony console doctrine:migrations:migrate -n

symfony console app:seed-demo            # creates the admin and client accounts below
symfony serve
```

Open the URL Symfony prints (usually https://127.0.0.1:8000).

## Demo accounts

The username is the email address.

| Role   | Username (email)            | Password       |
|--------|-----------------------------|----------------|
| Admin  | admin@yonzon.test           | Admin@12345    |
| Client | maria.santos@yonzon.test    | Client@12345   |
| Client | john.reyes@yonzon.test      | Client@12345   |
| Client | ana.cruz@yonzon.test        | Client@12345   |
| Client | carlo.mendoza@yonzon.test   | Client@12345   |
| Client | liza.tan@yonzon.test        | Client@12345   |

Forgot the admin login? Run `symfony console app:seed-demo --admin-only` to recreate or reset it.
To promote any other account: `symfony console app:make-admin someone@example.com`.
These are demo credentials. Change or delete them before any real deployment.

## Features

- Register, log in, log out; forgot / reset password (link shown on screen because no mail server is set up)
- Login throttling (5 attempts per 10 minutes) and suspended-account blocking
- Item registry with claim IDs, private serial numbers, photo upload (validated and resized)
- Marketplace with search, category filter, pagination, featured listings, public listing pages
- Offers: cash, trade, or trade + cash; 7-day expiry; accept, decline, withdraw, cancel; meetup note
- Two-sided handover confirmation, ownership history, dispute flag, 5% fee on completed cash deals
- Reviews, star ratings and a "verified trader" badge; public profiles
- Messaging per listing with live updates; block users; report listings
- Admin: stats, users (suspend / promote), reports moderation, revenue log
- Responsive layout (mobile menu, stacked grids, scrollable tables)
- Terms and Privacy pages

## Project rules

See `AGENTS.md`. In short: thin controllers, business rules in `src/Service`, schema changes through migrations,
CSRF token on every POST.
