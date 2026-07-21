# Gemini Rules & Guidelines

> [!IMPORTANT]
> **Start Here: Project Entry Point**
> When you begin a new session, you MUST read these files first in this exact order to understand the codebase and requirements:
> 1. [.ai/PROJECT_CONTEXT.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/PROJECT_CONTEXT.md) - The central system architecture, modules, database and active components.
> 2. [.ai/TASK_STATE.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/TASK_STATE.md) - Current sprint progress, list of completed and remaining tasks.
> 3. [.ai/BUSINESS_RULES.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/BUSINESS_RULES.md) - Mandatory product constraints, tenancy logic, and database preservation rules.
> 4. [.ai/CODING_STANDARDS.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/CODING_STANDARDS.md) - Code style, module layouts, and testing expectations.
> 5. [.ai/guide.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/guide.md) - Detailed guide on prompt variables and image generator placeholders.
> 6. [.ai/refactoring_report.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/refactoring_report.md) - Refactoring, API token optimizations, and error classifier details. 
> 7. [.ai/current_issues.md] (file://D:\Company Work\Company projects\NewBlogAI\.ai\current_issues.md) - this file current the current issues of the projects before making new feature or fixing new feature make sure these all issues won't arise again. 

## 1. Preserve Original Test Data
* **DO NOT** run `migrate:fresh` or clear database tables unless explicitly requested by the user.
* Existing database records (such as saved websites, customers, API keys, content histories, and settings) are original test data and must be preserved.
* Use targeted migrations or database queries/seeds if you need to add or update records, rather than wiping out existing data.

## 2. API & Plugin Integrity
* Ensure that plugin communication tokens and connection keys are not overwritten or invalidated.
* Verify that frontend settings remain persistent across updates.

## 3. Do Not Modify Working Code
* The current files and core features are fully working and verified. Do not make modifications or refactorings to existing functional code structures unless specifically implementing new feature specifications or addressing newly identified bugs.

## 4. Keep Documentation Synchronized
* **MANDATORY RULES FOR SESSIONS:**
  * Whenever you implement a new feature, fix a bug, or refactor code, you MUST update the corresponding documentation files in the `.ai/` folder.
  * Update [.ai/TASK_STATE.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/TASK_STATE.md) to record the task completion and progress details.
  * Update [.ai/ARCHITECTURE.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/ARCHITECTURE.md) if the change introduces new stages, modules, endpoints, flows, or structural components.
  * Update [.ai/BUSINESS_RULES.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/BUSINESS_RULES.md) if the change introduces new business constraints or policy decisions.
  * Update [.ai/MODULE_OWNERSHIP.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/MODULE_OWNERSHIP.md) if capabilities or interactions between modules change.
  * Update [.ai/guide.md](file:///D:/Company%20Work/Company%20projects/NewBlogAI/.ai/guide.md) if template variables, placeholders, or prompt formats are added/modified.
  * Any new AI session relies entirely on these `.md` files to understand the system state without scanning the whole codebase. Make sure all documentation is 100% accurate, up-to-date, and contains explicit file paths/links.

## 5. Use the skills if it mentioned in the Prompt. 
 If any of the skills mentions like Systematic debugging skills, Frontend Design skills or more (take the skills  from this folder C:\Users\bhupe\.gemini\antigravity-cli\skills ) open the skills folder and use these skills to solve the current issues that user is defining as well as skills like these Stochastic Multi-Agent Consensus and Video-to-Action via Gemini Passthrough present at the .ai/ folder. Use these skills as well. 

