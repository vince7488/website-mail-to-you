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

3. **(Recommended) Start Mailpit for local email testing**:
   For local development, we recommend using [Mailpit](https://github.com/axllent/mailpit) as a mock SMTP server. It traps outgoing emails so you can view them in a local web interface without sending real emails.

   - **Installation**:
     - macOS (Homebrew): `brew install mailpit`
     - Linux / Windows: Download pre-built binaries from GitHub releases or run via Docker:

       ```bash
       docker run -d -p 1025:1025 -p 8025:8025 axllent/mailpit
       ```

   - **Launch in terminal**:

     ```bash
     mailpit
     ```

     Once started:
     - **SMTP server**: listening at `127.0.0.1:1025`
     - **Web UI**: open `http://localhost:8025` in your browser to view incoming emails.

4. **Configure the application**:
   Copy the example configuration file to `config/config.local.php`:

   ```bash
   cp config/config.example.php config/config.local.php
   ```

   Configure `config/config.local.php` to route mail to Mailpit:

   ```php
   'application' => [
       'environment' => 'local',
       'debug' => true,
       'public_url' => 'http://localhost:8000',
   ],

   'smtp' => [
       'host' => '127.0.0.1',
       'port' => 1025,
       'encryption' => 'none',
       'authenticate' => false,
       'username' => '',
       'password' => '',
   ],

   'forms' => [
       'contact' => [
           'allowed_origins' => [
               'http://localhost:3000',
               'http://localhost:5173',
               'http://localhost:8000',
           ],
           // ...
       ],
   ],
   ```

   *(Note: `config.local.php` is ignored by Git, ensuring local overrides never affect production).*

   For production deployments, you will configure `config/config.php` with your live SMTP credentials:

   ```bash
   cp config/config.example.php config/config.php
   ```

5. **Run a local development server**:
   Start PHP's built-in web server, pointing it to the `public/` directory:

   ```bash
   php -S localhost:8000 -t public
   ```

   The backend will now be available at `http://localhost:8000`. You can check server health at `http://localhost:8000/health`.

## Setting up in Production

When deploying to a production server (like a cPanel, Plesk, or Apache/Nginx VPS), it's crucial that only the `public/` directory is accessible from the web.

### Deploying to cPanel / Shared Hosting

1. **Upload the files**: Upload the entire repository to a directory **above** your web root (e.g., `/home/username/website-mail-to-you`). Do not put the whole project inside `public_html`.
2. **Set the Document Root**: In cPanel, create a subdomain (e.g., `forms.example.com`) or modify an existing domain's document root to point to the `public/` directory inside the project folder (`/home/username/website-mail-to-you/public`).
3. **Install dependencies**: If you have SSH access, run `composer install --no-dev --optimize-autoloader`. If not, run `composer install` locally and upload the `vendor/` directory along with your project.
4. **Configure the application**: Copy `config/config.example.php` to `config/config.php` and configure your production SMTP credentials, allowed frontend origins (CORS), and recipients.
5. **Permissions**: Ensure the `storage/` directory is writable by the web server process.
