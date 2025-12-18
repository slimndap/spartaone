# Spartaone setup

## Configuration

1. Copy `.env.example` to `.env` and fill in your secrets:
   ```
   STRAVA_CLIENT_ID=xxx
   STRAVA_CLIENT_SECRET=xxx
   STRAVA_REDIRECT_URI=https://your-domain.tld/spartaone
   OPENAI_API_KEY=sk-...
   ```
2. The app auto-loads `.env` on boot, so you do not need to expose these via Apache/nginx. You can still set real environment variables in your service manager (systemd) to override values.

## Ubuntu deployment notes

- Install PHP extensions: `curl`, `json`, `mbstring`, and `openssl` (e.g., `sudo apt install php-curl php-json php-mbstring php-xml`).
- Serve the project root (`/spartaone`) via your web server with PHP-FPM or mod_php.
- Ensure the `data/` directory is writable by the web server user if you persist athlete/settings data.
- Restart PHP-FPM/web server after updating the `.env` file so new env overrides (if set at the service level) are picked up.
