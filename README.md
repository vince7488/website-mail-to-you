# website-mail-to-you

A small, self-hosted PHP form backend that lets any static website send contact submissions through your own SMTP provider.

Connect HTML, React, Vue, Angular, Next.js, ASP.NET, or any other frontend to your own cPanel, Gmail, Microsoft 365, or standard SMTP mailbox—without a database or full-stack framework.

## Frontend Integration
Please read the [Frontend integration documentation](https://github.com/vince7488/website-mail-to-you/wiki/Frontend-integration-documentation) for instructions on how to connect your frontend to this service.

## Setting Up Locally

### Prerequisites

- **PHP 8.3 or newer**: Required to run the application. Check your version with `php -v`.
- **Composer 2**: Required to install dependencies (like PHPMailer). If you don't have it, follow the [Composer installation guide](https://getcomposer.org/download/).
- **PHP Extensions**: Ensure `ctype`, `filter`, `hash`, `json`, `mbstring`, and `openssl` are enabled in your `php.ini`.

### Setup Instructions

1. **Clone the repository**:

   ```bash
   git clone https://github.com/vince7488/website-mail-to-you.git
   cd website-mail-to-you
   ```

2. **Install dependencies**:

   ```bash
   composer install
   ```

3. **Configure the application**:
   Copy the example configuration file and edit it to add your local SMTP details:

   ```bash
   cp config/config.example.php config/config.local.php
   ```

   for your production site, create a separate configuration file.

   ```bash
   cp config/config.example.php config/config.php
   ```

4. **Run a local development server**:
   Start PHP's built-in web server, pointing it to the `public/` directory:

   ```bash
   php -S localhost:8000 -t public
   ```

   The backend will now be available at `http://localhost:8000`.

## Setting up in Production

When deploying to a production server (like a cPanel, Plesk, or Apache/Nginx VPS), it's crucial that only the `public/` directory is accessible from the web.

### Deploying to cPanel / Shared Hosting

1. **Upload the files**: Upload the entire repository to a directory **above** your web root (e.g., `/home/username/website-mail-to-you`). Do not put the whole project inside `public_html`.
2. **Set the Document Root**: In cPanel, create a subdomain (e.g., `forms.example.com`) or modify an existing domain's document root to point to the `public/` directory inside the project folder (`/home/username/website-mail-to-you/public`).
3. **Install dependencies**: If you have SSH access, run `composer install --no-dev --optimize-autoloader`. If not, run `composer install` locally and upload the `vendor/` directory along with your project.
4. **Configure the application**: Copy `config/config.example.php` to `config/config.php` and configure your production SMTP credentials, allowed frontend origins (CORS), and recipients.
5. **Permissions**: Ensure the `storage/` directory is writable by the web server process.
