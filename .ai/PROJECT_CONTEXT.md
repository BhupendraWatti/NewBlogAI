# PROJECT CONTEXT

Project Name:
NewsBlogify AI

Purpose:
NewsBlogify is an AI-powered SaaS platform that automates website content generation through a Laravel backend and a WordPress plugin.

The Laravel application acts as the central control panel.

The WordPress plugin acts as the execution engine installed on customer websites.

Customers purchase subscriptions.

Each subscription controls

- number of websites
- number of AI topics
- publishing frequency
- AI model
- API limits
- scheduling

The backend communicates with plugins through secure APIs.

Every feature added must align with this architecture.

The plugin must never contain business logic that belongs to Laravel.

Laravel is the single source of truth.

WordPress is only responsible for execution.

## Media Manager & Image Generation Module
* **Location:** `app/Modules/MediaManager/`
* **Drivers:** Pollinations AI (free testing), Unsplash API, and OpenAI DALL-E.
* **Flow:** The pipeline converts Markdown to HTML first, then replaces any comment placeholders (e.g. `<!-- image-placeholder: ... -->`) with standard block `<figure><img></figure>` elements, downloading and storing assets locally under `storage/app/public/media` to prevent hotlinking and CORS blocks.
* **Safeguards:** Features strict scheme validation (preventing javascript: XSS), finfo binary signature mime checking (preventing malicious extension spoofing), size checking, and idempotency tracking.