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

## Newsroom Discovery & Temporal Guardrails
* **Newsroom Discovery Mode:** When a pipeline runs in `discovery` mode, it researches the target category and generates exactly 9 unique news candidates (using Groq for speed and cost efficiency). These are persisted to the database. An employee then selects exactly one candidate.
* **Full Generation Run:** Selecting a candidate triggers the standard full generation pipeline, passing the candidate context and anchoring generation to its title and source references.
* **Temporal Framing Guardrail:** To prevent historical events in source articles from being framed as breaking news, the chronological context parser extracts dates from scraped snippets. If the event lag exceeds 48 hours, the story is classified as `followup`, and the AI writer is instructed to frame the article around what is new today, referencing the historical incident as background context.