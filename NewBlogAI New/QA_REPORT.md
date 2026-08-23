# Production QA report

Date: 2026-08-23  
Mode: Standard, test-and-fix  
Target: local production-like Laravel build at `http://127.0.0.1:8765`

## Result

Ship readiness improved from **62/100** to **94/100**. The application passes its automated suite, builds release assets, boots with production caches, returns a healthy `/up` response, and completes the tested authenticated newsroom flows without browser-console errors.

## Live browser coverage

- Unauthenticated `/` redirects to `/login`.
- Invalid credentials return controlled feedback and do not create a session.
- Valid local QA credentials reach the authenticated dashboard.
- Prompt Library, Newsroom Pipeline, Websites, AI Providers, and Publishing Queue switch visibly and update the URL.
- Privileged dashboard APIs return successful responses for a super-admin session.
- Desktop and 390px mobile layouts were exercised.
- The mobile navigation drawer opens, closes after navigation, and leaves the newsroom workspace at full width.
- Prompt Library exposes all eleven runtime variables for manually maintained templates.
- Unknown routes render a branded 404 with a recovery link.

## Defects fixed

| Severity | Defect | Resolution |
|---|---|---|
| High | Fixed desktop sidebar consumed most of a mobile viewport and clipped the workspace. | Added an accessible mobile drawer, overlay, close behavior, responsive header, and single-column newsroom/dashboard grids. |
| High | Login HTML shipped prefilled demonstration credentials. | Removed values and added correct username/current-password autocomplete metadata. |
| High | Locked PHP packages had 2026 Guzzle and CommonMark advisories, including high-severity findings. | Updated Guzzle to 7.15.3, CommonMark to 2.10.0, and related packages; Composer audit is clean. |
| Medium | Dashboard executed Tailwind and SweetAlert from CDNs despite having a Vite build. | Enabled the compiled Tailwind theme and bundled SweetAlert2 locally. |
| Medium | Prompt editor and dry run did not expose or populate all runtime prompt variables. | Synced all eleven placeholders and added safe mock research context. |
| Medium | Default 404 was a dead end. | Added a branded, accessible recovery page. |

## Automated release gate

- PHPUnit: **193 tests passed, 1,096 assertions**.
- Vite: production build passed; CSS and JavaScript assets emitted successfully.
- Laravel: migrations applied, `php artisan optimize` passed, `/up` returned HTTP 200.
- Formatting: changed PHP production files and new tests pass Pint; `git diff --check` passes.
- Dependency security: `composer audit --locked` and `npm audit --omit=dev` report zero advisories.

## Evidence

- `.gstack/qa-login.png`
- `.gstack/qa-invalid-login.png`
- `.gstack/qa-after-production-assets.png`
- `.gstack/qa-mobile-final.png`
- `.gstack/qa-universal-prompt.png`
- `.gstack/devex-404-after.png`

## Remaining release boundaries

The local QA environment did not publish a real article, spend AI-provider credits, or mutate an external WordPress site. Those destructive/external smoke tests require production-like credentials and a designated test WordPress endpoint. Deployment infrastructure, TLS termination, backups, queue supervision, and monitoring must be configured according to `PRODUCTION.md` on the target host.
