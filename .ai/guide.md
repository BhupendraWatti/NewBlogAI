# NewBlogAI Prompt Template & Image Placement Guide

This guide defines how to write effective AI prompts in the NewBlogAI system, detailing the template variables and rules for inserting inline image placeholders.

---

## 1. Supported Template Variables

When you create a Prompt Template in the system, you can use curly brace variables. The backend automatically compiles them into specific context values when executing the content pipeline:

| Variable | Description | Example Value |
| :--- | :--- | :--- |
| `{{topic}}` | The target keyword or subject name | `Laravel Testing` |
| `{{category}}` | The category group of the topic | `Technology` |
| `{{language}}` | The target writing language | `en` |
| `{{website}}` | The destination blog domain url | `https://example-blog.com` |

> [!NOTE]
> To escape the variable mapping and print literal curly braces (for example, in code blocks), prefix it with `@` like: `@{{topic}}`.

---

## 2. Inline Image Placeholder Rules

To instruct the AI to insert visual assets at key breakpoints in the article, insert specific HTML comment tags. The system post-processor scans the output, generates the visual assets using the active driver, caches them locally, and inserts WordPress-compatible block elements.

### Supported Formats

1. **Prompt Format (Default):**
   The post-processor uses the comment text for the generation prompt, alt text, and caption:
   ```html
   <!-- image-placeholder: A realistic photo of a developer desk with a laptop displaying code -->
   ```

2. **Attribute Format:**
   Define specific prompt, alt, and caption settings:
   ```html
   <!-- image-placeholder: prompt="A close up of hands typing on a mechanical keyboard" alt="Hands on keyboard" caption="Coding in progress" -->
   ```

---

## 3. Best Practices for Prompt Writing

To ensure the AI places image placeholders cleanly, add structural constraints to your prompt instructions:
* **Standalone Lines:** Always instruct the AI to put the `<!-- image-placeholder -->` on a separate line with a double newline (`\n\n`) before and after it.
* **Frequency Constraint:** Instruct the AI to limit the number of placeholders (e.g. "insert exactly one image placeholder every 2-3 paragraphs").
* **No Nesting:** Expressly forbid the AI from nesting placeholders inside list items (`<li>`), blockquotes, or markdown code blocks.

---

## 4. Demo Prompt Template

Copy and paste this template into the Prompt Template field inside the SaaS app:

```markdown
You are a senior technical writer blogging for the website {{website}}.
Write an engaging, SEO-optimized blog post in the {{language}} language.

Topic: {{topic}}
Category: {{category}}

### Writing Instructions:
1. Write a compelling title.
2. Structure the content with clear headings (##, ###).
3. Include an introductory hook, followed by 3 main sections, and a concise summary.
4. Throughout the post, place exactly two inline image placeholders to break up text sections. Place them on a standalone line.
5. The format of the image placeholder must be:
   <!-- image-placeholder: prompt="[detailed prompt representing the section]" alt="[short alt description]" caption="[readable caption]" -->

### Output Structure Example:
# [Compelling Title]

[First paragraph of introduction...]

<!-- image-placeholder: prompt="A clean modern office workspace with computers" alt="Office workspace" caption="The modern developer environment" -->

## [Section Header]
[Section content paragraphs...]

<!-- image-placeholder: prompt="A glowing network map representing cloud connectivity" alt="Cloud Network" caption="Mapping data across services" -->

## Conclusion
[Concluding summary...]
```
