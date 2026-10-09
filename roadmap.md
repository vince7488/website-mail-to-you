
Below is the master implementation document. Once the backend repository exists, this should become its `BUILD_PLAN.md`; the consumer-facing portions will become `README.md`. Nothing should be added to `logias-static` until the backend is independently deployed and tested.

# Static Form Mailer: Build and Integration Plan

## 1. Project purpose

Create a small, self-hosted PHP application that allows any static or frontend-only website to send form submissions through an authenticated SMTP account.

It will support:

- Plain HTML forms
- Vanilla JavaScript
- React
- Vue
- Angular
- Static-exported Next.js
- ASP.NET/Razor and Blazor WebAssembly
- Command-line testing with cURL
- cPanel SMTP accounts
- Gmail or Google Workspace SMTP
- Microsoft 365 SMTP when SMTP AUTH is enabled
- Other standards-compliant SMTP providers

The application will use PHPMailer for SMTP, TLS, authentication, MIME formatting, and header safety. Composer is PHPMailer’s recommended installation method; the current maintained package is PHPMailer 7.x. [PHPMailer on Packagist](https://packagist.org/packages/phpmailer/phpmailer)

## 2. Explicit non-goals

The first version will not include:

- A database
- Laravel, Symfony, Slim, or another application framework
- User accounts
- An administrative dashboard
- Form-building UI
- File uploads or attachments
- Mailing lists
- Autoresponders sent to visitors
- Arbitrary recipients supplied by the browser
- Arbitrary `From` addresses supplied by the browser
- Message history or stored submissions
- Analytics or visitor fingerprinting
- GeoIP lookup
- Cookies or sessions
- CAPTCHA unless real production spam makes it necessary

This prevents the application from becoming an open mail relay or a collection point for unnecessary personal information.

## 3. Runtime requirements

Use:

- PHP 8.3 or newer
- PHP 8.4 or 8.5 where the host supports it
- Composer 2
- PHP extensions:
  - `ctype`
  - `filter`
  - `hash`
  - `json`
  - `mbstring`
  - `openssl`
- HTTPS for the public endpoint
- A writable, non-public storage directory

PHP 8.2 reaches end of security support at the end of 2026, so 8.3 should be the minimum even if older versions could technically run the code. [PHP supported versions](https://www.php.net/supported-versions.php)

## 4. Repository structure

```text
static-form-mailer/
├── bin/
│   ├── check-config
│   └── clear-rate-limits
├── config/
│   ├── config.example.php
│   └── config.php
├── public/
│   ├── .htaccess
│   └── index.php
├── src/
│   ├── Application.php
│   ├── Config/
│   │   ├── ConfigLoader.php
│   │   └── ConfigValidator.php
│   ├── Http/
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Router.php
│   │   ├── BodyParser.php
│   │   └── CorsPolicy.php
│   ├── Form/
│   │   ├── Submission.php
│   │   ├── SubmissionValidator.php
│   │   └── SpamGuard.php
│   ├── Mail/
│   │   ├── MailComposer.php
│   │   ├── Mailer.php
│   │   └── PhpMailerAdapter.php
│   ├── RateLimit/
│   │   └── FileRateLimiter.php
│   └── Support/
│       ├── EventLogger.php
│       ├── IpAddress.php
│       └── RequestId.php
├── storage/
│   ├── logs/
│   └── rate-limits/
├── tests/
│   ├── Unit/
│   └── Integration/
├── .gitignore
├── composer.json
├── composer.lock
├── LICENSE
├── BUILD_PLAN.md
├── README.md
├── SECURITY.md
└── phpunit.xml
```

Only `public/` will be exposed through the web server.

The SMTP configuration, Composer files, source code, logs, and rate-limit records must remain outside the document root.

## 5. Repository initialization

Run these commands after creating the new repository:

```bash
git init
composer init
composer require phpmailer/phpmailer:^7.0
composer require --dev phpunit/phpunit phpstan/phpstan
```

Configure Composer PSR-4 autoloading:

```json
{
  "require": {
    "php": ">=8.3",
    "ext-ctype": "*",
    "ext-filter": "*",
    "ext-hash": "*",
    "ext-json": "*",
    "ext-mbstring": "*",
    "ext-openssl": "*",
    "phpmailer/phpmailer": "^7.0"
  },
  "autoload": {
    "psr-4": {
      "StaticFormMailer\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "StaticFormMailer\\Tests\\": "tests/"
    }
  },
  "scripts": {
    "test": "phpunit",
    "analyse": "phpstan analyse src tests",
    "check": [
      "@test",
      "@analyse"
    ]
  }
}
```

After changing autoload settings:

```bash
composer dump-autoload
```

## 6. Configuration design

The developer copies:

```text
config/config.example.php
```

to:

```text
config/config.php
```

`config/config.php` must be ignored by Git:

```gitignore
/config/config.php
/storage/logs/*
/storage/rate-limits/*
!/storage/logs/.gitkeep
!/storage/rate-limits/.gitkeep
/vendor/
.phpunit.cache/
```

The live configuration file should look approximately like this:

```php
<?php

declare(strict_types=1);

return [
    'application' => [
        'environment' => 'production',
        'debug' => false,
        'public_url' => 'https://forms.example.com',
        'request_max_bytes' => 32768,
        'storage_path' => dirname(__DIR__) . '/storage',
    ],

    'smtp' => [
        'host' => 'mail.example.com',
        'port' => 465,

        // Valid values: "smtps", "starttls", or "none".
        'encryption' => 'smtps',

        'authenticate' => true,
        'username' => 'website@example.com',
        'password' => 'replace-with-real-password',

        // null lets PHPMailer negotiate.
        // Other supported values: "LOGIN", "PLAIN", "CRAM-MD5".
        'auth_type' => null,

        'timeout_seconds' => 15,

        'tls' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,

            // Optional custom certificate-authority bundle.
            'ca_file' => null,

            // Optional expected certificate hostname.
            'peer_name' => null,

            // Optional mutual-TLS client credentials.
            'client_certificate' => null,
            'client_private_key' => null,
            'client_private_key_passphrase' => null,
        ],
    ],

    'mail' => [
        'from' => [
            'address' => 'website@example.com',
            'name' => 'Example Website',
        ],

        // Normally the same address as "from".
        'return_path' => 'website@example.com',
    ],

    'forms' => [
        'contact' => [
            'recipients' => [
                [
                    'address' => 'team@example.com',
                    'name' => 'Example Team',
                ],
            ],

            'cc' => [],
            'bcc' => [],

            // Never accept the email subject directly from the request.
            'email_subject' => 'New website inquiry',

            // The validated visitor address becomes Reply-To.
            'reply_to_field' => 'email',

            'allowed_origins' => [
                'https://example.com',
                'https://www.example.com',
                'http://localhost:3000',
            ],

            // Used by non-JavaScript HTML forms.
            'success_redirect_url' =>
                'https://example.com/contact/success/',
            'error_redirect_url' =>
                'https://example.com/contact/error/',

            'honeypot_field' => 'company_website',

            'timing' => [
                'field' => '_started_at',
                'required' => false,
                'minimum_seconds' => 3,
                'maximum_seconds' => 7200,
            ],

            'fields' => [
                'name' => [
                    'label' => 'Name',
                    'type' => 'string',
                    'required' => true,
                    'minimum_length' => 2,
                    'maximum_length' => 120,
                ],

                'email' => [
                    'label' => 'Email',
                    'type' => 'email',
                    'required' => true,
                    'maximum_length' => 254,
                ],

                'topic' => [
                    'label' => 'Topic',
                    'type' => 'string',
                    'required' => false,
                    'maximum_length' => 100,
                ],

                'message' => [
                    'label' => 'Message',
                    'type' => 'string',
                    'required' => true,
                    'minimum_length' => 10,
                    'maximum_length' => 5000,
                ],
            ],
        ],
    ],

    'rate_limit' => [
        'enabled' => true,
        'maximum_attempts' => 5,
        'window_seconds' => 900,

        // Generate a long random value unique to this installation.
        'ip_hash_key' => 'replace-with-at-least-32-random-bytes',

        // Do not trust X-Forwarded-For unless the actual proxy is listed.
        'trusted_proxies' => [],
    ],

    'logging' => [
        'enabled' => true,
        'retain_days' => 30,

        // Never log form contents, email addresses, or SMTP credentials.
        'log_submission_contents' => false,
    ],
];
```

### Configuration rules

The configuration validator must reject startup when:

- The SMTP hostname is empty.
- The SMTP port is outside `1–65535`.
- The encryption mode is unsupported.
- Production certificate verification is disabled.
- A recipient is invalid.
- The `From` address is invalid.
- An allowed origin contains a path, query, or fragment.
- A redirect URL is not HTTPS in production.
- The storage directory is missing or unwritable.
- A field name is duplicated or unsafe.
- The rate-limit hashing key is missing or too short.
- Debugging is enabled in production.

Recommended SMTP combinations:

| Port | Encryption | Usage |
|---:|---|---|
| 465 | `smtps` | Implicit TLS |
| 587 | `starttls` | SMTP upgraded to TLS |
| 25 | `starttls` or `none` | Only when explicitly required by the provider |

Certificate verification must remain enabled. PHPMailer supports custom TLS stream settings, but its own documentation warns that disabling certificate validation is not the correct production fix for a misconfigured mail server. [PHPMailer TLS options](https://github.com/PHPMailer/PHPMailer/blob/master/examples/ssl_options.phps)

### OAuth limitation

The baseline version supports normal authenticated SMTP.

PHPMailer supports XOAUTH2, but Gmail and Microsoft providers require additional OAuth libraries, registration, tokens, and token refresh logic. That should be implemented as a separate transport adapter rather than pretending a few configuration values are sufficient.

Google Workspace can use SMTP relay or an app password where permitted. Microsoft 365 SMTP AUTH may be disabled at either the organization or mailbox level and increasingly favors OAuth. [Google SMTP guidance](https://support.google.com/a/answer/176600), [Microsoft SMTP AUTH guidance](https://learn.microsoft.com/en-us/Exchange/clients-and-mobile-in-exchange-online/authenticated-client-smtp-submission)

## 7. HTTP API contract

### Endpoints

```text
POST    /v1/forms/{form-id}
OPTIONS /v1/forms/{form-id}
GET     /health
```

Examples:

```text
POST /v1/forms/contact
GET  /health
```

`/health` verifies that the PHP application can load its configuration and access its storage directory. It must not send a test email or reveal SMTP details.

### Accepted content types

Support only:

```text
application/json
application/x-www-form-urlencoded
```

Reject unsupported types with `415 Unsupported Media Type`.

Do not support file uploads or `multipart/form-data` in version one.

### JSON submission

```json
{
  "name": "Jane Example",
  "email": "jane@example.net",
  "topic": "Project inquiry",
  "message": "I would like to discuss a new project.",
  "company_website": "",
  "_started_at": 1791510123
}
```

### Successful JSON response

```json
{
  "ok": true,
  "requestId": "01JABCDEF123456789",
  "message": "Your message was sent."
}
```

### Validation response

Status:

```text
422 Unprocessable Content
```

Body:

```json
{
  "ok": false,
  "requestId": "01JABCDEF123456789",
  "code": "validation_failed",
  "message": "Please correct the highlighted fields.",
  "fields": {
    "email": "Enter a valid email address."
  }
}
```

### Other response statuses

| Status | Meaning |
|---:|---|
| `200` | Submission accepted and handed to SMTP |
| `204` | Successful CORS preflight |
| `400` | Malformed request |
| `403` | Origin not allowed |
| `404` | Unknown form |
| `405` | Unsupported HTTP method |
| `413` | Request body too large |
| `415` | Unsupported content type |
| `422` | Invalid form values |
| `429` | Rate limit exceeded |
| `503` | SMTP temporarily unavailable |

SMTP exception messages must never be returned to the browser.

## 8. CORS behavior

For an allowed browser origin, return:

```http
Access-Control-Allow-Origin: https://www.example.com
Access-Control-Allow-Methods: POST, OPTIONS
Access-Control-Allow-Headers: Content-Type
Access-Control-Max-Age: 600
Vary: Origin
```

Rules:

- Reflect only an exact configured origin.
- Never return `Access-Control-Allow-Origin: *`.
- Do not allow cookies.
- Do not return `Access-Control-Allow-Credentials`.
- Handle `OPTIONS` without performing validation or sending mail.
- Treat `Origin: null` as disallowed.
- Keep scheme, hostname, and port significant.

JSON cross-origin requests normally trigger an `OPTIONS` preflight because `application/json` is not a CORS-safelisted content type. [MDN CORS guide](https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/CORS)

CORS is browser policy, not bot protection. A non-browser client can fabricate an `Origin` header. Rate limiting, fixed recipients, validation, and spam controls remain necessary.

## 9. Validation and mail-safety rules

For every submission:

1. Enforce the maximum raw request size before decoding.
2. Parse only the permitted content types.
3. Reject malformed JSON.
4. Reject arrays or objects where scalar strings are expected.
5. Reject fields not listed in configuration, except internal anti-spam fields.
6. Normalize line endings.
7. Verify valid UTF-8.
8. Trim outer whitespace.
9. Enforce minimum and maximum lengths.
10. Validate email through PHP’s email filter.
11. Reject CR or LF characters in values that could reach headers.
12. Escape all submitted values before producing HTML email.
13. Produce a plain-text alternative body.
14. Use a fixed configured `From` address.
15. Use fixed configured recipients.
16. Use a fixed configured email subject.
17. Set the validated visitor email only as `Reply-To`.

The browser must never be able to supply:

- `From`
- `To`
- `CC`
- `BCC`
- Return path
- SMTP hostname
- SMTP credentials
- Raw email headers
- An arbitrary subject header
- A redirect destination

PHPMailer validates addresses and protects against header-injection problems, but the application must still validate its own public input. [PHPMailer features and installation](https://packagist.org/packages/phpmailer/phpmailer)

## 10. Spam and abuse controls

Implement these layers:

### Honeypot

A field such as `company_website` should be empty for humans.

If it is filled:

- Do not send mail.
- Return the same successful response as a real submission.
- Log only that a honeypot submission was discarded.

Do not tell the bot it was detected.

### Timing heuristic

JavaScript integrations send `_started_at` as a Unix timestamp.

The backend can reject submissions that arrive implausibly quickly or after an excessive interval. This is only a heuristic; a competent bot can forge the timestamp.

Plain HTML submissions must be allowed to omit it.

### Rate limiting

Implement a file-backed fixed-window limiter suitable for one shared-hosting server.

Requirements:

- Determine the address from `REMOTE_ADDR`.
- Ignore forwarding headers unless the direct proxy is explicitly trusted.
- Hash addresses with an installation-specific HMAC key.
- Never store raw IP addresses.
- Use `flock()` for concurrent updates.
- Expire old records.
- Return `Retry-After` with `429`.
- Include an occasional probabilistic cleanup of expired records.
- Provide a CLI cleanup command.

Default:

```text
5 attempts per 15 minutes per address
```

Document that file-backed limits are not suitable for a multi-server deployment. A future adapter could use Redis without changing the application layer.

### No visitor autoresponder

Version one sends only to the configured site owner. It does not send “Thanks for contacting us” to the visitor.

An attacker could otherwise use the service to send unwanted messages to arbitrary victim addresses.

## 11. Email composition

The generated email should contain:

```text
Subject: New website inquiry
From: Example Website <website@example.com>
To: Example Team <team@example.com>
Reply-To: Jane Example <jane@example.net>
```

Body:

```text
A new form submission was received.

Name: Jane Example
Email: jane@example.net
Topic: Project inquiry

Message:
I would like to discuss a new project.

Request ID: 01JABCDEF123456789
Received: 2026-10-09T19:25:00Z
Form: contact
```

Generate both:

- A simple escaped HTML body
- A complete plain-text alternative

Do not embed tracking pixels, external assets, JavaScript, or raw visitor HTML.

## 12. Logging

Log operational events as JSON lines:

```json
{
  "timestamp": "2026-10-09T19:25:00Z",
  "level": "info",
  "requestId": "01JABCDEF123456789",
  "form": "contact",
  "event": "mail_sent",
  "ipHash": "..."
}
```

Allowed log data:

- Timestamp
- Request ID
- Form identifier
- Success or failure category
- Hashed IP
- SMTP response category
- Execution duration

Never log:

- SMTP password
- Full SMTP debug transcript in production
- Visitor message
- Visitor email
- Visitor name
- Authorization headers
- Client-certificate passphrases

## 13. Implementation sequence

Build in this order:

1. Composer project and autoloading.
2. Configuration loader.
3. Configuration validation.
4. Request and response value objects.
5. Router and `/health`.
6. CORS preflight handling.
7. Raw body-size enforcement.
8. JSON and form-urlencoded parsers.
9. Config-driven field validation.
10. Honeypot and timing checks.
11. Hashed file-backed rate limiter.
12. Mail composer.
13. PHPMailer adapter.
14. JSON response mode.
15. HTML redirect mode.
16. Minimal operational logger.
17. Unit tests.
18. SMTP integration test using a fake local SMTP server.
19. cPanel deployment instructions.
20. Framework integration examples.
21. Security review.
22. Production test using the actual SMTP account.

Each step should be independently reviewable.

## 14. Frontend integration documentation

All examples below use:

```text
https://forms.example.com/v1/forms/contact
```

### Plain HTML—no JavaScript required

```html
<form
  method="post"
  action="https://forms.example.com/v1/forms/contact"
  accept-charset="UTF-8"
>
  <div>
    <label for="contact-name">Name</label>
    <input
      id="contact-name"
      name="name"
      type="text"
      minlength="2"
      maxlength="120"
      autocomplete="name"
      required
    >
  </div>

  <div>
    <label for="contact-email">Email</label>
    <input
      id="contact-email"
      name="email"
      type="email"
      maxlength="254"
      autocomplete="email"
      required
    >
  </div>

  <div>
    <label for="contact-topic">Topic</label>
    <input
      id="contact-topic"
      name="topic"
      type="text"
      maxlength="100"
    >
  </div>

  <div>
    <label for="contact-message">Message</label>
    <textarea
      id="contact-message"
      name="message"
      minlength="10"
      maxlength="5000"
      required
    ></textarea>
  </div>

  <div class="form-honeypot" aria-hidden="true">
    <label for="company-website">Company website</label>
    <input
      id="company-website"
      name="company_website"
      type="text"
      tabindex="-1"
      autocomplete="off"
    >
  </div>

  <button type="submit">Send message</button>
</form>
```

```css
.form-honeypot {
  position: absolute;
  left: -10000px;
  width: 1px;
  height: 1px;
  overflow: hidden;
}
```

The backend recognizes `application/x-www-form-urlencoded` and returns a `303 See Other` redirect to the configured success or error page.

Limitations:

- Backend validation errors cannot be displayed inline without JavaScript.
- The timing field is unavailable.
- Browser-native validation still provides a reasonable accessible baseline.

### Vanilla JavaScript

```html
<form id="contact-form">
  <!-- Use the same labeled fields as the plain HTML example. -->
</form>

<p id="contact-status" role="status" aria-live="polite"></p>

<script type="module">
  const endpoint =
    "https://forms.example.com/v1/forms/contact";

  const form = document.querySelector("#contact-form");
  const status = document.querySelector("#contact-status");
  const startedAt = Math.floor(Date.now() / 1000);

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const submitButton = form.querySelector('[type="submit"]');
    const formData = new FormData(form);

    const payload = {
      name: String(formData.get("name") ?? ""),
      email: String(formData.get("email") ?? ""),
      topic: String(formData.get("topic") ?? ""),
      message: String(formData.get("message") ?? ""),
      company_website: String(
        formData.get("company_website") ?? ""
      ),
      _started_at: startedAt,
    };

    submitButton.disabled = true;
    status.textContent = "Sending…";

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        mode: "cors",
        credentials: "omit",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
        },
        body: JSON.stringify(payload),
      });

      const result = await response.json();

      if (!response.ok) {
        throw new Error(
          result.message ?? "The message could not be sent."
        );
      }

      form.reset();
      status.textContent = "Your message was sent.";
    } catch (error) {
      status.textContent =
        error instanceof Error
          ? error.message
          : "The message could not be sent.";
    } finally {
      submitButton.disabled = false;
    }
  });
</script>
```

### React

```tsx
import { FormEvent, useRef, useState } from "react";

const endpoint =
  "https://forms.example.com/v1/forms/contact";

export function ContactForm() {
  const startedAt = useRef(Math.floor(Date.now() / 1000));
  const [status, setStatus] =
    useState<"idle" | "sending" | "sent" | "error">("idle");
  const [message, setMessage] = useState("");

  async function handleSubmit(
    event: FormEvent<HTMLFormElement>,
  ) {
    event.preventDefault();

    const form = event.currentTarget;
    const data = new FormData(form);

    setStatus("sending");
    setMessage("Sending…");

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        mode: "cors",
        credentials: "omit",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
        },
        body: JSON.stringify({
          name: data.get("name"),
          email: data.get("email"),
          topic: data.get("topic"),
          message: data.get("message"),
          company_website: data.get("company_website"),
          _started_at: startedAt.current,
        }),
      });

      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.message);
      }

      form.reset();
      setStatus("sent");
      setMessage("Your message was sent.");
    } catch (error) {
      setStatus("error");
      setMessage(
        error instanceof Error
          ? error.message
          : "The message could not be sent.",
      );
    }
  }

  return (
    <form onSubmit={handleSubmit}>
      {/* Add properly labeled name, email, topic,
          message, and honeypot fields here. */}

      <button type="submit" disabled={status === "sending"}>
        Send message
      </button>

      <p role="status" aria-live="polite">
        {message}
      </p>
    </form>
  );
}
```

### Vue

```vue
<script setup lang="ts">
import { reactive, ref } from "vue";

const endpoint =
  "https://forms.example.com/v1/forms/contact";

const startedAt = Math.floor(Date.now() / 1000);
const sending = ref(false);
const status = ref("");

const form = reactive({
  name: "",
  email: "",
  topic: "",
  message: "",
  company_website: "",
});

async function submit() {
  sending.value = true;
  status.value = "Sending…";

  try {
    const response = await fetch(endpoint, {
      method: "POST",
      mode: "cors",
      credentials: "omit",
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
      },
      body: JSON.stringify({
        ...form,
        _started_at: startedAt,
      }),
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(result.message);
    }

    form.name = "";
    form.email = "";
    form.topic = "";
    form.message = "";
    status.value = "Your message was sent.";
  } catch (error) {
    status.value =
      error instanceof Error
        ? error.message
        : "The message could not be sent.";
  } finally {
    sending.value = false;
  }
}
</script>

<template>
  <form @submit.prevent="submit">
    <!-- Add labeled fields using v-model. -->

    <button type="submit" :disabled="sending">
      Send message
    </button>

    <p role="status" aria-live="polite">
      {{ status }}
    </p>
  </form>
</template>
```

### Angular

Create a small injectable form service rather than placing transport logic throughout components. Angular’s `HttpClient.post()` serializes plain objects as JSON and sends only after its returned Observable is subscribed. [Angular HTTP guide](https://angular.dev/guide/http/making-requests)

```ts
import { Injectable, inject } from "@angular/core";
import { HttpClient } from "@angular/common/http";

export interface ContactSubmission {
  name: string;
  email: string;
  topic: string;
  message: string;
  company_website: string;
  _started_at: number;
}

export interface FormResponse {
  ok: boolean;
  requestId: string;
  message: string;
}

@Injectable({ providedIn: "root" })
export class ContactFormService {
  private readonly http = inject(HttpClient);

  submit(submission: ContactSubmission) {
    return this.http.post<FormResponse>(
      "https://forms.example.com/v1/forms/contact",
      submission,
    );
  }
}
```

Component:

```ts
import { Component, inject } from "@angular/core";
import {
  FormBuilder,
  ReactiveFormsModule,
  Validators,
} from "@angular/forms";
import { ContactFormService } from "./contact-form.service";

@Component({
  selector: "app-contact-form",
  standalone: true,
  imports: [ReactiveFormsModule],
  templateUrl: "./contact-form.component.html",
})
export class ContactFormComponent {
  private readonly builder = inject(FormBuilder);
  private readonly forms = inject(ContactFormService);
  private readonly startedAt = Math.floor(Date.now() / 1000);

  sending = false;
  status = "";

  form = this.builder.nonNullable.group({
    name: ["", [Validators.required, Validators.maxLength(120)]],
    email: ["", [Validators.required, Validators.email]],
    topic: ["", [Validators.maxLength(100)]],
    message: ["", [Validators.required, Validators.maxLength(5000)]],
    company_website: [""],
  });

  submit() {
    if (this.form.invalid || this.sending) {
      this.form.markAllAsTouched();
      return;
    }

    this.sending = true;
    this.status = "Sending…";

    this.forms
      .submit({
        ...this.form.getRawValue(),
        _started_at: this.startedAt,
      })
      .subscribe({
        next: () => {
          this.form.reset();
          this.status = "Your message was sent.";
          this.sending = false;
        },
        error: () => {
          this.status = "The message could not be sent.";
          this.sending = false;
        },
      });
  }
}
```

Configure `HttpClient` according to the Angular version, using `provideHttpClient()` where required.

### Static Next.js

Keep the page as a Server Component and isolate the interactive form in a small Client Component.

Public build-time configuration:

```dotenv
NEXT_PUBLIC_FORM_ENDPOINT=https://forms.example.com/v1/forms/contact
```

This endpoint is intentionally public. Never place SMTP credentials in a `NEXT_PUBLIC_` value.

```tsx
"use client";

import { FormEvent, useRef, useState } from "react";

const endpoint = process.env.NEXT_PUBLIC_FORM_ENDPOINT;

export function ContactForm() {
  const startedAt = useRef(Math.floor(Date.now() / 1000));
  const [status, setStatus] = useState("");

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!endpoint) {
      setStatus("The contact form is unavailable.");
      return;
    }

    const form = event.currentTarget;
    const data = new FormData(form);

    setStatus("Sending…");

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        credentials: "omit",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
        },
        body: JSON.stringify({
          name: data.get("name"),
          email: data.get("email"),
          topic: data.get("topic"),
          message: data.get("message"),
          company_website: data.get("company_website"),
          _started_at: startedAt.current,
        }),
      });

      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.message);
      }

      form.reset();
      setStatus("Your message was sent.");
    } catch (error) {
      setStatus(
        error instanceof Error
          ? error.message
          : "The message could not be sent.",
      );
    }
  }

  return (
    <form onSubmit={submit}>
      {/* Accessible fields go here. */}

      <button type="submit">Send message</button>

      <p role="status" aria-live="polite">
        {status}
      </p>
    </form>
  );
}
```

No Next.js API route, Route Handler, Server Action, or Node.js runtime is required. This remains compatible with `output: "export"`.

### ASP.NET

For Razor Pages or MVC views, the native HTML example can be used directly. The browser posts to the PHP endpoint and follows its configured redirect.

For Blazor WebAssembly, use `HttpClient`:

```razor
@using System.ComponentModel.DataAnnotations
@using System.Net.Http.Json
@inject HttpClient Http

<EditForm Model="_form" OnValidSubmit="SubmitAsync">
    <DataAnnotationsValidator />

    <label>
        Name
        <InputText @bind-Value="_form.Name" />
    </label>

    <label>
        Email
        <InputText @bind-Value="_form.Email" />
    </label>

    <label>
        Message
        <InputTextArea @bind-Value="_form.Message" />
    </label>

    <button type="submit" disabled="@_sending">
        Send message
    </button>

    <p role="status" aria-live="polite">@_status</p>
</EditForm>

@code {
    private readonly ContactModel _form = new();
    private readonly long _startedAt =
        DateTimeOffset.UtcNow.ToUnixTimeSeconds();

    private bool _sending;
    private string _status = "";

    private async Task SubmitAsync()
    {
        _sending = true;
        _status = "Sending…";

        var response = await Http.PostAsJsonAsync(
            "https://forms.example.com/v1/forms/contact",
            new
            {
                name = _form.Name,
                email = _form.Email,
                topic = "",
                message = _form.Message,
                company_website = "",
                _started_at = _startedAt
            }
        );

        _status = response.IsSuccessStatusCode
            ? "Your message was sent."
            : "The message could not be sent.";

        _sending = false;
    }

    private sealed class ContactModel
    {
        [Required, StringLength(120)]
        public string Name { get; set; } = "";

        [Required, EmailAddress]
        public string Email { get; set; } = "";

        [Required, StringLength(5000, MinimumLength = 10)]
        public string Message { get; set; } = "";
    }
}
```

`PostAsJsonAsync()` is the standard .NET helper for serializing and sending a JSON `POST`. [Microsoft HttpClient documentation](https://learn.microsoft.com/en-us/dotnet/framework/network-programming/requesting-data)

This example is specifically browser-side Blazor WebAssembly. A server-side ASP.NET service should eventually use an authenticated server-to-server client rather than depending on browser-origin checks.

### cURL

Health check:

```bash
curl --fail-with-body \
  https://forms.example.com/health
```

CORS preflight:

```bash
curl -i \
  -X OPTIONS \
  -H 'Origin: https://www.example.com' \
  -H 'Access-Control-Request-Method: POST' \
  -H 'Access-Control-Request-Headers: content-type' \
  https://forms.example.com/v1/forms/contact
```

JSON submission:

```bash
curl --fail-with-body \
  -X POST \
  -H 'Origin: https://www.example.com' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  --data '{
    "name": "Jane Example",
    "email": "jane@example.net",
    "topic": "Test submission",
    "message": "This is a test message from cURL.",
    "company_website": "",
    "_started_at": 1791510123
  }' \
  https://forms.example.com/v1/forms/contact
```

Form-encoded submission:

```bash
curl -i \
  -X POST \
  -H 'Origin: https://www.example.com' \
  --data-urlencode 'name=Jane Example' \
  --data-urlencode 'email=jane@example.net' \
  --data-urlencode 'topic=Test submission' \
  --data-urlencode 'message=This is a form-encoded test.' \
  --data-urlencode 'company_website=' \
  https://forms.example.com/v1/forms/contact
```

The manually supplied `Origin` is for testing—it is not authentication.

## 15. Automated testing plan

### Unit tests

Test:

- Configuration acceptance and rejection
- Origin normalization and exact matching
- Request body-size enforcement
- JSON parsing
- Form-urlencoded parsing
- Unknown-field rejection
- Required-field validation
- String-length validation
- Email validation
- CR/LF rejection
- Honeypot behavior
- Timing behavior
- IP hashing
- Rate-limit expiration
- Rate-limit concurrency
- HTML escaping
- Plain-text email rendering
- Fixed sender and recipient behavior
- Safe exception responses

### Integration tests

Test:

- `GET /health`
- Allowed preflight
- Rejected preflight
- Successful JSON submission
- Successful form submission and redirect
- Invalid JSON
- Unsupported content type
- Oversized body
- Unknown form
- SMTP success
- SMTP failure
- No SMTP error leakage
- Rate-limit response and `Retry-After`

Use a fake SMTP server locally so tests never send real email. PHPMailer itself recommends fake SMTP services for testing message generation and error handling. [PHPMailer example guidance](https://github.com/PHPMailer/PHPMailer/blob/master/examples/README.md)

## 16. Deployment to cPanel

1. Create a subdomain such as:

   ```text
   forms.example.com
   ```

2. Enable HTTPS for that subdomain.

3. Upload or clone the backend outside the public web directory where possible.

4. Point the subdomain’s document root specifically to:

   ```text
   /path/to/static-form-mailer/public
   ```

5. Run:

   ```bash
   composer install \
     --no-dev \
     --classmap-authoritative
   ```

6. Verify the server’s PHP extensions:

   ```bash
   composer check-platform-reqs
   ```

7. Copy the configuration:

   ```bash
   cp config/config.example.php config/config.php
   ```

8. Enter the real SMTP settings.

9. Restrict configuration permissions, normally:

   ```text
   config/config.php: 600 or 640
   ```

10. Ensure PHP can write only to:

    ```text
    storage/logs/
    storage/rate-limits/
    ```

11. Confirm directory listing is disabled.

12. Confirm these URLs are inaccessible:

    ```text
    /config/config.php
    /composer.json
    /vendor/
    /storage/
    ```

13. Verify `/health`.

14. Perform the CORS preflight cURL test.

15. Perform a real submission.

16. Inspect the resulting headers and delivery route.

17. Run:

    ```bash
    composer audit
    ```

## 17. Logias production configuration

For Logias:

```php
'smtp' => [
    'host' => 'the exact outgoing host shown by cPanel',
    'port' => 465, // Or 587 if cPanel specifies STARTTLS.
    'encryption' => 'smtps',
    'authenticate' => true,
    'username' => 'website@logias.org',
    'password' => 'the mailbox password',
],

'mail' => [
    'from' => [
        'address' => 'website@logias.org',
        'name' => 'Logias Website',
    ],
    'return_path' => 'website@logias.org',
],

'forms' => [
    'contact' => [
        'recipients' => [
            [
                'address' => 'team@logias.org',
                'name' => 'Logias Team',
            ],
        ],
        'email_subject' => 'New Logias website inquiry',
        'allowed_origins' => [
            'https://logias.org',
            'https://www.logias.org',
            'http://localhost:3000',
        ],
        // Remaining validation configuration...
    ],
],
```

Do not guess the cPanel SMTP host or port. Use cPanel’s **Email Accounts → Connect Devices** values. [cPanel mail-client settings](https://docs.cpanel.net/cpanel/email/set-up-mail-client/)

For `website@logias.org`, use:

- Receiving Incoming Mail: **Suspend**
- Sending Outgoing Email: **Allow**
- Logging In: **Allow**

Logging in must remain allowed because authenticated SMTP requires the mailbox to authenticate. cPanel documents these as separate restrictions. [cPanel email-account restrictions](https://docs.cpanel.net/cpanel/email/manage-email-accounts/108/)

Leave `team@logias.org` and its Microsoft 365 configuration untouched.

### Critical split-mail-routing check

Because cPanel sends as `website@logias.org` while Microsoft 365 receives mail for `logias.org`, verify cPanel’s routing for `logias.org`.

The cPanel server must not mistakenly deliver `team@logias.org` to a local cPanel mailbox. Generally, when Microsoft 365 owns the domain’s MX records, cPanel should detect or use **Remote Mail Exchanger**. That setting causes mail for the domain to be sent to the external MX rather than a local mailbox. [cPanel Email Routing](https://docs.cpanel.net/cpanel/email/email-routing/)

After the first test:

1. Open cPanel’s **Track Delivery**.
2. Find the test message.
3. Confirm its route is shown as remote.
4. Confirm Microsoft 365 received it at `team@logias.org`.
5. Reply to the message.
6. Confirm the reply goes to the visitor address, not `website@logias.org`.

## 18. Production acceptance checklist

The first release is complete only when:

- [ ] The repository exposes only `public/`.
- [ ] Configuration and credentials are not tracked.
- [ ] SMTP TLS certificate verification is enabled.
- [ ] The sender is always configured server-side.
- [ ] All recipients are configured server-side.
- [ ] Visitor email is used only as `Reply-To`.
- [ ] Unknown fields are rejected.
- [ ] Oversized requests are rejected.
- [ ] JSON and plain HTML submissions work.
- [ ] Exact origin checks work.
- [ ] CORS preflights work.
- [ ] Honeypot submissions are silently discarded.
- [ ] Rate limiting works without storing raw IP addresses.
- [ ] Logs contain no form contents or credentials.
- [ ] SMTP errors are not exposed to visitors.
- [ ] HTML and plain-text email bodies are generated.
- [ ] Accessibility instructions appear in every integration example.
- [ ] Unit and integration tests pass.
- [ ] Composer’s security audit passes.
- [ ] cPanel routes Logias recipient mail remotely to Microsoft 365.
- [ ] A real Logias submission reaches `team@logias.org`.
- [ ] Replying targets the visitor.
- [ ] No Node.js or backend runtime has been added to `logias-static`.

This is the architecture I would now follow step by step. The immediate next action is to create the separate empty repository, choose its final name, and implement only phases 1–5 first: Composer setup, configuration, request handling, `/health`, and CORS.
