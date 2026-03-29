# Event Reservation App

Symfony API + Twig frontend for event management and reservations.

This README focuses on:
- Running the project quickly (local and Docker)
- Understanding the implemented security model
- Verifying that security controls are active

## Stack

- PHP 8.3
- Symfony 7
- Doctrine ORM + Migrations
- PostgreSQL
- LexikJWTAuthenticationBundle (JWT auth)
- Twig frontend
- Symfony Mailer (Mailpit in Docker)

## Project Structure (important parts)

- `src/Controller` API and frontend controllers
- `src/EventSubscriber` security subscribers (CSRF enforcement, headers)
- `src/Service/ReservationConfirmationMailer.php` reservation email confirmation
- `config/packages/security.yaml` authentication and access rules
- `compose.yaml` and `compose.override.yaml` Docker setup

## Run Locally (without Docker)

### 1) Install dependencies

```bash
composer install
```

### 2) Configure environment

Update `.env.local` (recommended) with your local values:

```bash
APP_ENV=dev
APP_SECRET=change-me
DATABASE_URL="postgresql://postgres:1234@127.0.0.1:5432/event_db?serverVersion=15&charset=utf8"
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your-passphrase
MAILER_DSN=smtp://127.0.0.1:1025
MAILER_FROM=no-reply@event-studio.local
```

### 3) Generate JWT keys (first run only)

```bash
mkdir -p config/jwt
php bin/console lexik:jwt:generate-keypair
```

### 4) Run migrations

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

### 5) Create test users

```bash
php bin/console app:create-user user1 password123 --email=user1@example.com
php bin/console app:create-user admin1 password123 --admin --email=admin1@example.com
```

### 6) Start the app

```bash
php -S 127.0.0.1:8000 -t public
```

Open frontend pages:
- `http://127.0.0.1:8000/`
- `http://127.0.0.1:8000/app/login`

## Run With Docker (recommended)

### 1) Start all services

```bash
docker compose up --build
```

Services:
- App: `http://127.0.0.1:8000`
- PostgreSQL: `127.0.0.1:5432`
- Mailpit SMTP: `127.0.0.1:1025`
- Mailpit UI: `http://127.0.0.1:8025`

### 2) Optional: run commands inside app container

```bash
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console app:create-user user1 password123 --email=user1@example.com
docker compose exec app php bin/console app:create-user admin1 password123 --admin --email=admin1@example.com
```

### 3) Stop stack

```bash
docker compose down
```

To remove DB volume too:

```bash
docker compose down -v
```

## Security Model

### Authentication and authorization

- Login endpoint: `POST /api/login_check`
- JWT required for protected API routes (`Authorization: Bearer <token>`)
- Access rules (`config/packages/security.yaml`):
  - `^/api/login` => public
  - `^/api/admin` => `ROLE_ADMIN`
  - `^/api` => `ROLE_USER`

### CSRF protection for API writes

- Global CSRF enabled: `config/packages/framework.yaml`
- API write methods (`POST`, `PUT`, `PATCH`, `DELETE`) require `X-CSRF-Token`
- Implemented in `src/EventSubscriber/ApiCsrfSubscriber.php`
- Login route (`/api/login_check`) is excluded from CSRF check

Frontend sends token automatically from:
- `<meta name="api-csrf-token" ...>` in `templates/base.html.twig`
- Header injection logic in `public/scripts/app-ui.js`

### Security headers

`src/EventSubscriber/SecurityHeadersSubscriber.php` sets:
- `Content-Security-Policy`
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy`
- `Permissions-Policy`

### XSS mitigation

Event input sanitization is applied server-side in event creation/update flow (HTML sanitizer).

### Password security

- Password hasher: Symfony auto hasher (`password_hashers`)
- Plain passwords are hashed via `UserPasswordHasherInterface`

## Email Confirmation (Reservation)

On reservation creation (`POST /api/events/{id}/reservations`):
- Reservation is stored
- Confirmation email is sent by `ReservationConfirmationMailer`
- Template: `templates/emails/reservation_confirmation.html.twig`

If no user email is set, email sending is skipped safely.

## Quick Security Checks

### Get JWT

```bash
curl -X POST http://127.0.0.1:8000/api/login_check \
  -H "Content-Type: application/json" \
  -d '{"username":"user1","password":"password123"}'
```

### Check protected route without token (should fail)

```bash
curl -i http://127.0.0.1:8000/api/me
```

### Check protected route with token (should pass)

```bash
TOKEN="<paste-jwt>"
curl -i http://127.0.0.1:8000/api/me -H "Authorization: Bearer $TOKEN"
```

### Check CSRF enforcement on write endpoint (should fail if missing)

```bash
TOKEN="<paste-jwt>"
curl -i -X POST http://127.0.0.1:8000/api/events/1/reservations \
  -H "Authorization: Bearer $TOKEN"
```

## Troubleshooting

### Port 8000 already in use

If `php -S 127.0.0.1:8000 -t public` fails with "Address already in use":
- stop the process using port 8000, or
- use another port, for example:

```bash
php -S 127.0.0.1:8080 -t public
```

### `curl` exit code 7 (connection failed)

Usually means server is not running on the target port.
- start server first (local or Docker)
- retry request with the correct host/port

### JWT encoding errors

Check:
- key files exist in `config/jwt/private.pem` and `config/jwt/public.pem`
- passphrase matches `JWT_PASSPHRASE`

## Useful Commands

```bash
php bin/console lint:container
php bin/console lint:twig templates
php bin/console doctrine:migrations:status
```

## Branching Strategy Used

- Stable branch: `main`
- Integration branch: `dev`
- Feature branches: `feature/*`
- Merge flow: `feature/* -> dev -> main`

This keeps releases reviewable and traceable.
