# Gemini Rules & Guidelines

> [!IMPORTANT]
> **Start Here: Project Entry Point**
> When you begin a new session, you MUST read these files first in this exact order to understand the codebase and requirements:
> 1. [.ai/PROJECT_CONTEXT.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/PROJECT_CONTEXT.md) - The central system architecture, modules, database and active components.
> 2. [.ai/TASK_STATE.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/TASK_STATE.md) - Current sprint progress, list of completed and remaining tasks.
> 3. [.ai/BUSINESS_RULES.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/BUSINESS_RULES.md) - Mandatory product constraints, tenancy logic, and database preservation rules.
> 4. [.ai/CODING_STANDARDS.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/CODING_STANDARDS.md) - Code style, module layouts, and testing expectations.
> 5. [.ai/guide.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/guide.md) - Detailed guide on prompt variables and image generator placeholders.

## 1. Preserve Original Test Data
* **DO NOT** run `migrate:fresh` or clear database tables unless explicitly requested by the user.
* Existing database records (such as saved websites, customers, API keys, content histories, and settings) are original test data and must be preserved.
* Use targeted migrations or database queries/seeds if you need to add or update records, rather than wiping out existing data.

## 2. API & Plugin Integrity
* Ensure that plugin communication tokens and connection keys are not overwritten or invalidated.
* Verify that frontend settings remain persistent across updates.

## 3. Do Not Modify Working Code
* The current files and core features are fully working and verified. Do not make modifications or refactorings to existing functional code structures unless specifically implementing new feature specifications or addressing newly identified bugs.

