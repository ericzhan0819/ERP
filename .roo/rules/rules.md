# Roo Code Global Rules

Model target: Use GPT5.3-codex for implementation, bug fixing, refactoring, and code generation. Use GPT5.5 only for architecture review, security review, permission-model review, or high-level technical planning.

## Core Output Rules

1. All generated source code must include detailed technical comments in Traditional Chinese.
2. Keep responses concise and implementation-focused.
3. Do not provide long explanations unless explicitly requested.
4. Prefer the smallest safe change.
5. Do not perform large rewrites unless the user explicitly requests it.
6. Preserve existing behavior unless the task explicitly asks to change it.
7. Avoid speculative architecture changes.
8. Before editing, identify the exact files and the minimum affected scope.
9. After editing, summarize only changed files, changed logic, and required verification commands.
10. Never invent files, routes, database columns, permissions, or services without checking the existing project structure first.

## UI Design Rules

1. Design a custom Used Car ERP Dashboard using Swiss / International design principles.
2. The interface must communicate absolute transparency, systemic order, professional reliability, calm control, and objective clarity.
3. Use a strict grid-based hierarchy for complex automotive data, including inventory procurement costs, maintenance expenses, sales commissions, vehicle gross profit, receivables, and risk alerts.
4. Use minimalist functional colors. High-contrast accents must be used sparingly for critical financial metrics, overdue items, abnormal costs, and risk alerts.
5. Typography must feel authoritative, neutral, legible, and data-oriented. Prefer professional sans-serif typography and avoid decorative styling.
6. Interactions and transitions must be snappy, precise, logical, and mechanically consistent.
7. The user journey must flow from macro business health overview to detailed vehicle records, accounting entries, and operational logs.
8. Visual inspiration should come from Modernist architecture, public signage systems, premium watchmaking workshops, and high-precision laboratories.
9. Follow “less is more”. Sophistication must come from proportion, spacing, alignment, and restraint, not ornament.
10. Do not sacrifice usability for decoration.

## Architecture Rules

1. All functional modules must be modularized.
2. The same feature must not be scattered across unrelated files.
3. Centralize permission logic in one permission layer, such as PermissionService, Policy, Middleware, or a dedicated authorization module.
4. Centralize module metadata in the Module Registry. Do not duplicate module labels, route names, icons, permission names, or active states across frontend and backend.
5. Frontend navigation must consume backend-provided permission/module data. Do not hard-code permission decisions in React components.
6. Backend is the only source of truth for authentication, authorization, and data access.
7. Frontend visibility is only UX. It must never be treated as security.
8. Any route, controller action, API endpoint, mutation, export, upload, delete, or financial operation must have backend authorization.
9. Avoid circular dependencies and hidden side effects.
10. Prefer services, policies, request classes, DTO-like arrays, and small utility functions over duplicated inline logic.

## Laravel / Inertia / React Rules

1. Laravel controllers must stay thin. Put business logic in services.
2. Use FormRequest classes for validation when request validation is non-trivial.
3. Never use `$request->all()` for create or update operations.
4. Use explicit allowlists such as `$request->validated()` or carefully selected fields.
5. Use Policies or Gates for model-level authorization.
6. Use Middleware for route/module-level authorization.
7. Use database constraints where appropriate, including foreign keys, unique indexes, nullable rules, and enum-like validation.
8. Keep Inertia shared props minimal, explicit, and safe.
9. Do not expose secrets, internal IDs that are not needed, permission internals, debug data, stack traces, or sensitive financial details to the frontend.
10. React components should be presentational when possible. Move permission checks, formatting, and data shaping into dedicated utilities or backend props.
11. Do not introduce global state unless clearly necessary.
12. Do not use `dangerouslySetInnerHTML` unless explicitly approved and sanitized.
13. Keep route names stable. Do not rename existing routes without updating all references.
14. When changing auth, route, or permission behavior, include cache-clearing and verification commands.

## RBAC / Permission Rules

1. Role means primary identity or job level.
2. Direct permission means explicit exception or override.
3. Do not mix role labels, permission names, and module keys.
4. Permission names must be consistent and predictable, for example `module.staff.view`, `module.staff.create`, `module.staff.update`, `module.staff.delete`.
5. Every sidebar item must map to a backend module record and a backend permission.
6. Every protected route must verify permission on the backend.
7. Every sensitive controller method must verify permission again when appropriate.
8. Do not rely on frontend role checks such as `user.role === "admin"` for security.
9. When using Spatie Laravel Permission, use official role/permission APIs instead of raw database writes.
10. After changing roles, permissions, seeders, or module access, reset permission cache using the proper Laravel/Spatie mechanism.
11. Permission cache issues must be treated as deployment/runtime consistency issues, not UI bugs.
12. New users must receive role and permission assignment through one centralized flow.
13. Do not assign permissions in random controllers, seeders, components, or migration side effects.
14. Add authorization tests for admin, normal employee, denied user, and direct-permission override cases.

## Security Rules

1. Apply OWASP Top 10 awareness as the minimum security baseline.
2. Prioritize prevention of broken access control, injection, authentication failure, insecure design, security misconfiguration, vulnerable dependencies, and insufficient logging.
3. Every authenticated feature must answer: who can access it, what records they can access, what actions they can perform, and how this is enforced on the backend.
4. Prevent IDOR by checking ownership, company scope, branch scope, or assigned permission before reading, updating, deleting, exporting, or displaying records.
5. Do not trust URL parameters, request IDs, frontend state, hidden inputs, localStorage, or client-side filters.
6. Validate all input on the backend.
7. Sanitize or escape all user-controlled output.
8. Do not pass untrusted input into raw SQL, `DB::raw`, dynamic order-by clauses, dynamic table names, shell commands, file paths, or HTML rendering.
9. Use parameter binding or Eloquent query builder for database queries.
10. Use explicit sortable/filterable allowlists for table sorting, filtering, search, and export.
11. Protect all POST, PUT, PATCH, and DELETE web routes with CSRF protection.
12. Use rate limiting for login, password reset, invitation, upload, export, webhook, and sensitive mutation endpoints.
13. Regenerate sessions after login and privilege changes.
14. Never store plaintext passwords, tokens, API keys, or secrets.
15. Never commit `.env`, API keys, database passwords, private certificates, webhook secrets, or production credentials.
16. Secrets must only be read from environment variables or a secure secret manager.
17. Do not expose debug mode, stack traces, SQL errors, environment values, or internal exception details in production.
18. Use least privilege for database users and service accounts.
19. File uploads must validate MIME type, extension, size, storage path, and authorization.
20. Uploaded files must not be executable.
21. Export features must verify permission and data scope before generating files.
22. Financial records, commissions, procurement costs, and maintenance expenses must have stricter authorization than normal display data.
23. Destructive actions must use explicit authorization, confirmation UX, audit logging, and preferably soft delete when appropriate.
24. Critical accounting or vehicle status changes must be auditable.
25. Add audit logs for login-sensitive actions, permission changes, role changes, vehicle cost changes, commission changes, exports, deletes, and admin operations.
26. Do not implement SSO unless explicitly requested. If requested, prefer a standard provider and protocol such as Google Workspace / Microsoft Entra ID with OAuth2 or OIDC.
27. Do not implement RLS unless the database supports it and the project intentionally uses PostgreSQL. For MySQL/Laravel projects, use application-level ownership checks, Policies, global scopes, and query constraints instead.
28. For multi-company or SaaS behavior, every business table must include a tenant boundary such as `company_id` when applicable.
29. Every query that reads tenant-owned data must be scoped by tenant boundary unless the user is a verified super-admin.
30. Never allow normal employees to infer other companies, branches, users, vehicles, or financial records by changing IDs.
31. Composer and npm dependencies must be checked before adding new packages.
32. Do not add abandoned, unmaintained, or unnecessary packages.
33. Use `composer audit` and `npm audit` when security-sensitive dependencies are changed.
34. Prefer Laravel-native features before adding external security/auth packages.
35. Production must use HTTPS, secure cookies, proper session settings, disabled debug mode, and restricted CORS.
36. Webhooks must verify signatures or shared secrets.
37. Background jobs and scheduled commands must enforce the same authorization/data-scope assumptions as normal requests where applicable.
38. Security fixes must be minimal, explicit, and testable.

## Database / Data Integrity Rules

1. Use migrations for schema changes.
2. Do not manually alter database schema without a migration.
3. Use transactions for multi-step financial, inventory, commission, or accounting writes.
4. Avoid partial writes for operations that must stay consistent.
5. Use database indexes for frequently queried foreign keys, permission keys, module keys, vehicle status, stock number, company scope, and date filters.
6. Use unique constraints for stable identifiers such as module keys, permission names, role names, vehicle stock numbers, and user emails when appropriate.
7. Do not delete financial history unless explicitly required. Prefer soft delete or status transitions.
8. Keep accounting-relevant changes traceable.
9. Avoid silently overwriting costs, commissions, or payment statuses.
10. Use explicit status transition rules for vehicle lifecycle states.

## Testing / Verification Rules

1. Every bug fix must include the smallest practical verification step.
2. For permission changes, verify at least admin, normal employee, unauthorized user, and direct permission override.
3. For sidebar changes, verify backend shared props, visible module list, route access, and direct URL access.
4. For auth changes, verify login, logout, redirect, session regeneration, and protected route access.
5. For financial changes, verify validation, transaction behavior, rollback behavior, and audit log creation.
6. For security-sensitive changes, include negative tests.
7. Do not claim a fix is complete unless there is a concrete verification command or manual test path.
8. Prefer existing project test structure. Do not introduce a new test framework without approval.

## Git / Change Safety Rules

1. Keep changes small and reviewable.
2. Do not combine unrelated UI, backend, database, and security rewrites in one change.
3. Do not rename files or restructure folders unless necessary.
4. Do not remove existing features unless explicitly requested.
5. Before risky changes, identify rollback path.
6. After changes, provide exact commands to run, such as `php artisan test`, `npm run build`, `php artisan route:list`, `php artisan permission:cache-reset`, or relevant Sail commands.
7. Do not write changelogs unless explicitly requested.
8. Do not fabricate successful command output.
9. If a command was not run, say it was not run.

## Comment Style Rules

1. All code comments must be in Traditional Chinese.
2. Comments must explain why the code exists, not merely repeat what the code does.
3. Security-related comments must identify the protected risk, such as IDOR, mass assignment, privilege escalation, CSRF, injection, or tenant data leakage.
4. Do not over-comment obvious UI markup.
5. Comment complex permission, query-scope, transaction, and audit-log logic clearly.

## Prohibited Patterns

1. Do not implement frontend-only permission protection.
2. Do not use `$request->all()` for persistence.
3. Do not create permissions directly in random feature code.
4. Do not duplicate module definitions between backend and frontend.
5. Do not hard-code secrets.
6. Do not expose `.env` values.
7. Do not use raw SQL with user input.
8. Do not bypass middleware to make a page work.
9. Do not disable CSRF, auth, validation, or permission checks to fix errors.
10. Do not solve cache problems by removing authorization.
11. Do not add broad admin bypasses unless explicitly required and documented.
12. Do not hide backend bugs with frontend conditions.
13. Do not introduce large dependencies for small problems.
14. Do not create new architecture when a small patch is enough.
15. Do not scatter the same feature logic across controllers, React components, middleware, services, and config files.

## Final Response Format

1. Keep the response short.
2. List changed files.
3. List key logic changes.
4. List security impact.
5. List verification commands.
6. Mention any commands not run.
7. Do not include long theory unless explicitly requested.