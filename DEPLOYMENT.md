# Production deployment

This application requires a PHP 8+ web host with MySQL/MariaDB and the PHP cURL extension. Cloudflare Pages cannot run its `.php` files or host its MySQL database; use Cloudflare's free DNS/CDN plan in front of a PHP host instead.

1. Create a MySQL database and import `database/educonnect.sql`.
2. Upload this project to the PHP host and set its document root to this folder.
3. In the host's environment-variable panel, create every value in `.env.example`. Use a Flutterwave test secret key first; never commit either secret key.
4. Add the domain to Cloudflare and change the registrar nameservers to those Cloudflare provides. Create an `A` record for the PHP host, enable the orange-cloud proxy, and use **Full (strict)** SSL once the origin has a valid certificate.
5. Set `APP_URL` to the exact public HTTPS URL. Allow that domain as Flutterwave's checkout redirect domain.
6. Test a paid resource in Flutterwave test mode. Access is unlocked only after server-side verification confirms the reference, amount, currency, resource, and user.

Do not cache `*.php`, `checkout.php`, or `download.php` in Cloudflare. Static files may be cached normally. User uploads remain on the PHP origin.
