# Agent Instructions

These instructions apply to the entire repository.

## 1. Source of truth

- Read `roadmap.md` before planning or implementing changes.
- Treat `roadmap.md` as the authoritative guide for scope, architecture, security requirements, API behavior, implementation order, and acceptance criteria.
- Inspect the current code, configuration examples, tests, and Composer metadata before changing them. Do not assume the roadmap has already been fully implemented.
- If the implementation and roadmap disagree, identify the mismatch. Follow the roadmap unless the user explicitly requests a different direction.
- Keep work limited to this repository. Do not modify a consuming frontend or another repository unless the user explicitly requests it.

## 2. Project boundaries

- Preserve the project's intentionally small design: PHP 8.3 or newer, Composer 2, PHPMailer, no framework, and no database.
- Do not add user accounts, an admin dashboard, file uploads, attachments, mailing lists, visitor autoresponders, analytics, tracking, cookies, sessions, or CAPTCHA unless the user changes the documented scope.
- Only `public/` may be web-accessible. Keep source code, Composer files, configuration, credentials, logs, rate-limit records, and other runtime data outside the document root.
- Prefer small, cohesive classes and explicit data flow over hidden global state, service locators, or unnecessary framework-like abstractions.
- Follow the namespaces and PSR-4 layout defined in `composer.json`.

## 3. Working method

Before editing:

1. read the relevant roadmap sections;
2. inspect the affected code and nearby implementations;
3. inspect `git status` and preserve unrelated user work;
4. identify security boundaries, failure modes, and tests affected by the change;
5. make the smallest coherent change that satisfies the request.

Do not overwrite, revert, reformat, or otherwise disturb unrelated changes already present in the working tree. If required work overlaps unclear user changes, stop and ask before replacing them.

## 4. PHP engineering standards

- Start every PHP source file with `declare(strict_types=1);`.
- Use PHP 8.3-compatible language features and explicit types wherever practical, including parameter, return, property, and `readonly` types.
- Follow PSR-12 formatting and the existing `StaticFormMailer\` namespace structure.
- Prefer constructor injection and narrowly scoped dependencies. Avoid mutable global state, hidden side effects, and static state that makes behavior difficult to test.
- Keep HTTP parsing, validation, spam controls, rate limiting, mail composition, SMTP transport, logging, and configuration concerns separated as described in the roadmap.
- Use clear domain names. Avoid vague names such as `data`, `thing`, `helper`, or `manager` when a more specific name is available.
- Use early returns and small methods when they improve readability, but do not fragment straightforward logic into unnecessary layers.
- Document non-obvious invariants, security reasoning, array shapes, and compatibility decisions. Do not add comments that merely repeat the code.
- Catch exceptions only where the code can add context, convert them into an intentional application response, or perform required cleanup. Do not silently swallow failures.
- Keep public error responses stable and generic. Do not expose stack traces, filesystem paths, configuration values, SMTP responses, or internal exception messages.

## 5. Composer and dependencies

- Use Composer for PHP dependencies and autoloading. Do not manually copy third-party libraries into the repository.
- Prefer the PHP standard library and existing dependencies when they are sufficient.
- Add a dependency only when it provides clear value that is proportionate to the size of this project. Explain the reason in the implementation report.
- When dependencies or autoload rules change, update both `composer.json` and `composer.lock` and regenerate autoload files as needed.
- Do not run broad dependency upgrades as part of an unrelated task.
- Never edit generated files under `vendor/`.
- Never delete `composer.lock`; application builds must remain reproducible.

## 6. Secure input and HTTP handling

- Treat all request data, headers, server variables, configuration files, and proxy information as untrusted until validated.
- Enforce the raw request-size limit before decoding or processing the body.
- Accept only the methods, routes, content types, and payload shapes documented in the roadmap. Reject unsupported input explicitly.
- Require expected scalar field values. Reject nested arrays or objects where strings are expected.
- Verify UTF-8, normalize line endings, trim outer whitespace, and enforce documented minimum and maximum lengths.
- Validate email addresses with PHP's email filter and reject carriage returns or line feeds in any value that could reach a header.
- Escape untrusted content for its output context. Never insert raw visitor HTML into an email or response.
- Use consistent status codes and response shapes. Never expose SMTP exception details to a client.
- Keep `/health` side-effect free: it may verify configuration and storage access, but it must not send email or reveal sensitive details.
- Do not rely on CORS as authentication or bot protection.

## 7. Mail safety

- Recipients, `From`, return path, subject, SMTP host, SMTP credentials, and redirect destinations must come from trusted configuration, never from a public request.
- A validated visitor email may be used only as `Reply-To` where configured.
- Produce both escaped HTML and plain-text message bodies.
- Do not create arbitrary headers from submitted field names or values.
- Use PHPMailer for SMTP, TLS, MIME formatting, and address/header handling; do not reimplement SMTP.
- Keep TLS certificate and hostname verification enabled in production. Never normalize an invalid setup by disabling verification.
- Do not send visitor autoresponders unless the roadmap and user explicitly add that feature.

## 8. Configuration and secrets

- Keep live configuration in `config/config.php` and keep it ignored by Git.
- Use `config/config.example.php` for safe, non-secret examples only. Never place real credentials, API keys, private keys, personal addresses, or production secrets in examples, tests, logs, fixtures, documentation, commits, or output.
- Fail fast on invalid configuration using actionable messages that do not reveal secret values.
- Preserve secure production defaults: debug disabled, HTTPS redirects, TLS verification enabled, fixed origins and recipients, and a non-public writable storage path.
- Validate paths and URLs before use. Do not allow request values to select local files, log destinations, redirect targets, or configuration entries.

## 9. CORS, proxy, and abuse controls

- Match browser origins exactly, including scheme, hostname, and port. Never reflect an unvalidated origin or use `Access-Control-Allow-Origin: *`.
- Do not enable credentialed CORS or cookies. Preflight requests must not validate a submission, consume a rate-limit attempt, or send mail.
- Treat `Origin: null` as disallowed unless the documented policy is explicitly changed.
- Use `REMOTE_ADDR` by default. Trust forwarding headers only when the direct proxy is explicitly configured as trusted.
- Never store raw IP addresses. Hash rate-limit identifiers with an installation-specific HMAC key.
- Use file locking and atomic writes for shared mutable state. Handle partial, missing, corrupt, expired, and concurrently accessed rate-limit files safely.
- Keep abuse responses useful without disclosing information that makes controls easy to bypass.

## 10. Logging and privacy

- Log only the operational metadata allowed by the roadmap.
- Never log form contents, visitor names or email addresses, raw IP addresses, authorization headers, SMTP credentials, private-key passphrases, or full SMTP debug transcripts in production.
- Use structured JSON Lines for operational logs and include a request ID where available.
- Sanitize values before logging so control characters cannot forge log entries.
- Treat log retention and cleanup as explicit behavior. Do not clear logs or rate-limit state without authorization.

## 11. Responses, accessibility, and frontend examples

- Keep API responses deterministic and machine-readable for JSON clients.
- Preserve progressive enhancement for plain HTML forms and documented redirect behavior.
- When editing integration examples, use semantic HTML, accessible labels and status messaging, and browser-native behavior before adding JavaScript.
- Do not add client-side JavaScript when server-rendered HTML, native form behavior, or CSS is sufficient.
- Never add tracking pixels, remote email assets, or executable content to generated messages.

## 12. Testing and validation

- Add or update tests for new behavior, regressions, edge cases, and security boundaries.
- Prefer focused unit tests for pure behavior and integration tests for HTTP, filesystem concurrency, configuration, and mail-transport boundaries.
- Tests must not use real SMTP credentials, contact real recipients, depend on production services, or mutate production data.
- Cover failure paths as well as success paths, especially malformed bodies, oversized requests, invalid UTF-8, header injection, origin rejection, proxy handling, rate-limit concurrency, output escaping, and unavailable SMTP.
- Run the narrowest useful checks while developing, then run the complete relevant validation before reporting completion.
- The default full project check is:

  ```bash
  composer check
  ```

- Also run `composer validate` when Composer metadata changes and `composer dump-autoload` when autoload configuration changes.
- Do not claim a check passed unless it was actually run and completed successfully. If a check cannot run, report the exact command, reason, and remaining risk.

## 13. Code quality and maintainability

- Reuse an existing implementation when it already expresses the needed behavior cleanly.
- Remove duplication when doing so clarifies the current change, but avoid unrelated cleanup.
- Keep configuration-driven behavior configurable instead of scattering form IDs, recipients, origins, limits, or URLs through source code.
- Design for the roadmap's predictable growth in forms and providers without inventing speculative plug-in systems or generic abstractions.
- Keep classes and methods focused. Split responsibilities when a component begins parsing, validating, persisting, composing, sending, and formatting responses at once.
- Preserve backwards compatibility for documented endpoints and response formats unless the user explicitly requests a breaking change.

## 14. Documentation

- Update documentation and safe configuration examples when behavior, setup, requirements, commands, or public contracts change.
- Keep examples runnable and consistent with the current implementation.
- Do not document incomplete behavior as available.
- Record security-sensitive deployment requirements clearly, especially the `public/` document root, HTTPS, storage permissions, secrets, and TLS verification.

## 15. Second-pass engineering review

After implementing a change and before validation, review the code you just created a second time.

Ask:

- Will this obviously require refactoring later?
- Does this create unnecessary coupling?
- Did I duplicate an existing implementation?
- Is there an obvious reusable component or pattern?
- Will this structure survive reasonable growth in pages or content?
- Is something hard-coded that should clearly be configurable?
- Is the implementation dependent on a temporary assumption?
- Did I introduce unnecessary client-side JavaScript?
- Could semantic HTML or CSS solve something more cleanly?
- Is the component becoming responsible for too many things?
- Will another developer understand this implementation later?
- If frontend UI is in scope, did I use an existing React Icon where one was available?
- If I created a custom SVG, did I confirm that no suitable default icon existed?

If an obvious improvement can be made within the requested scope, make it before considering the implementation complete.

Future-proof reasonable and predictable growth.

Do not introduce speculative abstractions for hypothetical future requirements.

The goal is:

```text
avoid foreseeable refactoring
≠
design every possible future feature today
```

## 16. Git and commits

Do not commit automatically.

Completing a task does **not** authorize a commit.

Only create a commit when the user explicitly instructs you to do so, for example:

> commit changes

Until that instruction is given, leave completed work uncommitted.

### When instructed to commit

Before committing:

1. inspect `git status`;
2. inspect all changed files;
3. inspect the diff;
4. identify the complete set of changes belonging to the requested work;
5. preserve unrelated existing user changes;
6. stage all changes belonging to the atomic unit of work;
7. inspect the staged diff;
8. commit locally.

Commits must be atomic.

One commit should represent one coherent change that can be understood and reverted independently.

Do not interpret "atomic" as "one file per commit."

When all working-tree changes belong to the requested work, stage them together.

When unrelated user changes exist, do not stage them.

### Commit message

Derive the commit message from the actual staged diff.

Every commit must contain:

```text
<concise title describing the overall change>

<concise description covering the meaningful implementation changes>
```

Example:

```text
Build responsive project showcase

Add reusable project cards, responsive portfolio layouts, optimized media,
accessible navigation states, and supporting project metadata.
```

The message must describe the complete staged change, not merely the last file edited.

Avoid vague messages such as:

```text
updates
changes
fixes
cleanup
misc
final
```

### Never push

Never execute:

```text
git push
```

Do not:

- push branches;
- push tags;
- force-push;
- create remote branches through push;
- publish commits automatically.

A request to commit means:

```text
review
-> stage
-> commit locally
-> verify
-> stop
```

It never means:

```text
commit
-> push
```

After committing, run:

```text
git status
```

and report the local commit result.

## 17. Destructive and external actions

Do not automatically:

- push;
- deploy;
- publish;
- submit forms;
- send messages or email;
- make live SMTP requests;
- modify remote services;
- delete branches;
- delete dependencies;
- delete lock files;
- clear logs, caches, or rate-limit data;
- reset Git state;
- discard user changes.

Any destructive or externally visible action requires explicit authorization. Resolve the exact target and scope before acting, and prefer a reversible operation when one is available.

## 18. Implementation reporting rules

At the end of an implementation task, report:

- a concise summary of the behavior changed;
- the files added or modified and the purpose of each meaningful change;
- the validation commands actually run and whether they passed;
- any checks not run, including why they could not be run;
- security, compatibility, configuration, or deployment implications the user needs to know;
- any remaining limitation or follow-up that is genuinely required.

Do not claim that work is complete, secure, tested, deployed, committed, or production-ready without evidence.

Keep the report proportional to the change. Summarize relevant results instead of pasting long command output. Mention assumptions or roadmap deviations explicitly.

Unless the user explicitly requested a commit, state that the changes remain uncommitted. If a commit was requested, report the local commit hash and subject plus the final `git status`; do not push it.
