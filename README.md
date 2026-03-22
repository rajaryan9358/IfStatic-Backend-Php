# IfStatic Admin API (PHP)

This directory contains a Slim 4 based re-write of the existing Node.js admin API so you can serve the same `/api` surface from PHP. The available endpoints, validation rules, database schema expectations, and response envelopes mirror the original Express server.

## Getting started

1. **Install dependencies**
   ```bash
   cd server-php
   composer install
   ```
2. **Environment variables**
   Copy the sample file and adjust the values to match your MySQL instance and client origins.
   ```bash
   cp .env.example .env
   ```
   - Set `DB_HOST` to `127.0.0.1` (or your remote host) to force TCP connections. Using `localhost` can trigger socket-related `SQLSTATE[HY000] [2002] No such file or directory` errors on macOS.
   - If your MySQL instance is only reachable through a Unix socket, provide the absolute path via `DB_SOCKET=/path/to/mysql.sock`. When this variable is present the host/port values are ignored and PDO connects through the socket instead.
3. **Run the API locally**
   ```bash
   composer start
   ```
   The built-in PHP server listens on `http://localhost:5000` by default. Adjust the `PORT` and `APP_URL` values in `.env` if you need a different port or base URL.

## Key implementation notes

- **Framework**: Slim 4 with native PSR-7 request/response handling. CORS, JSON parsing, and structured error responses are wired up globally.
- **Database access**: Plain PDO using the same schema as `server/sql/schema.sql`. JSON columns are encoded/decoded exactly like the Node models.
- **Validation**: Request payloads are validated in the controllers to keep parity with the Joi schemas from the Node implementation.
- **Uploads**: `POST /api/uploads/images` accepts the `image` field and stores files under `server-php/public/uploads`. The returned URL mirrors Express (`${APP_URL}/uploads/{file}`).
- **Routes & auth**: Every previous Express route is registered under the same path. Use `/api/health` to verify the server is running. All mutating routes (POST/PUT/PATCH/DELETE plus list endpoints that expose private data) now require a Bearer token issued by `/api/admin/login`.

## Admin access & authentication

- Run the migration under `database/migrations/20251202_create_site_settings_table.sql` to provision the new `site_settings` table (and seed the initial row).
- Configure `ADMIN_AUTH_SECRET` (or reuse `APP_KEY`) in `.env` so issued tokens are signed with an environment-specific secret.
- Before the first login, populate `admin_username` and `admin_password_hash` (use `php -r "echo password_hash('your-password', PASSWORD_BCRYPT);";` to generate the hash) via SQL. Once signed in you can rotate the credentials and enable/disable admin access from the in-app settings page.
- Use the `/api/settings/admin-access` endpoints:
   - `GET /api/settings/admin-access` – public flag to let the frontend know if `/admin` should be exposed.
   - `GET /api/settings/admin-access/secure` – returns `enabled`, `username`, and whether a password exists (requires Bearer token).
   - `PUT /api/settings/admin-access` – update the flag, username, and password hash from the settings page (requires Bearer token).
- `POST /api/admin/login` verifies the username/password stored in `site_settings`, checks that admin access is enabled, and returns a signed token valid for 2 hours.
- Attach the returned token as `Authorization: Bearer <token>` when calling any protected route (all mutations, uploads, query listing endpoints for contact/quote data).

## Deploying

- Point your PHP-FPM/Apache/Nginx document root at `server-php/public`.
- Ensure the `public/uploads` directory is writable by the web server user.
- Keep your `.env` synced with the production credentials.

Refer to the source under `src/` for the complete controllers, models, and helpers if you need to extend or customize behaviour.
