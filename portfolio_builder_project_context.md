# Portfolio Builder — Project Context & Development Roadmap

## 1. Project Overview

This project is a **portfolio builder for non-coder users**.

The goal is to allow a user to:

1. Create an account.
2. Enter their professional/personal portfolio information.
3. Add skills, projects, experience, education, certifications, and social links.
4. Choose a portfolio template.
5. Preview their portfolio.
6. Publish the portfolio.
7. Share a public portfolio URL.

The most important product goal is the **template system**.

New portfolio templates should be able to be added continuously without changing the user's portfolio data structure or rewriting the core application.

### Core principle

> **Content and design must remain separate.**

The user owns the portfolio content.

The template controls how that content is presented.

---

# 2. Technology Stack

## Backend

- Laravel
- PHP
- SQL database
- Eloquent ORM

## Frontend

- Vue.js
- Inertia.js

## Architecture

The application is intended to be a Laravel monolith with Inertia/Vue for the application UI.

Conceptually:

```text
Browser
   ↓
Vue.js
   ↓
Inertia.js
   ↓
Laravel Routes
   ↓
Middleware
   ↓
Form Requests / Validation
   ↓
Controllers
   ↓
Services / Business Logic
   ↓
Eloquent Models
   ↓
SQL Database
```

---

# 3. Product Architecture

The portfolio system should work like this:

```text
User
  ↓
Portfolio
  ↓
Portfolio Data
  │
  ├── Profile
  ├── Skills
  ├── Projects
  ├── Experience
  ├── Education
  ├── Certifications
  └── Social Links
  │
  ↓
Selected Template
  ↓
Rendered Portfolio
  ↓
Public Portfolio URL
```

A portfolio should be able to switch between templates without changing its actual content.

```text
                 Portfolio Data
                       │
          ┌────────────┼────────────┐
          ↓            ↓            ↓
      Template A   Template B   Template C
          ↓            ↓            ↓
       Design A     Design B     Design C
```

---

# 4. Important Database Principle

Do not store the entire portfolio as one large JSON object.

Avoid:

```text
portfolios
    id
    user_id
    data JSON
```

The portfolio contains meaningful relational data, so SQL is appropriate.

Use relational tables for:

- Projects
- Skills
- Experiences
- Education
- Certifications
- Social links
- Templates

JSON can still be used where flexibility is genuinely useful, especially for **template-specific settings/configuration**.

---

# 5. Template Architecture

This is the most important architectural decision in the project.

Templates should consume a **standard portfolio data structure**.

A template should not care how the database is internally structured.

Conceptually:

```text
Database
   ↓
Eloquent Models
   ↓
Portfolio Service
   ↓
Standard Portfolio Data / DTO
   ↓
Template
```

A template should receive data such as:

```php
$portfolio->profile
$portfolio->skills
$portfolio->projects
$portfolio->experiences
$portfolio->educations
$portfolio->certifications
$portfolio->socialLinks
```

The template should focus on presentation.

---

# 6. Template Storage Strategy

A possible Laravel structure:

```text
resources/
    js/
        pages/
            templates/
                minimal/
                    home.vue
                    components/

                developer/
                    home.vue
                    components/

                creative/
                    home.vue
                    components/
```

However, the exact rendering implementation can evolve.

The key requirement is:

```text
Template A
Template B
Template C
...
Template N
```

must all consume the same portfolio content model.

Adding Template N should not require rewriting:

```text
User
Portfolio
Project
Skill
Experience
Education
Certification
```

---

# 8. Build the Portfolio CRUD

Do not build every feature at once.

Start with one complete vertical slice.

Recommended first feature:

```text
Portfolio
```

Build:

```text
Route
 ↓
Middleware
 ↓
Authorization
 ↓
Form Request
 ↓
Controller
 ↓
Service
 ↓
Model
 ↓
Database
 ↓
Inertia Response
 ↓
Vue Page
```

Then repeat the same pattern for:

```text
Profile
Projects
Skills
Experience
Education
Certifications
Social Links
```

This makes the application grow feature-by-feature instead of layer-by-layer.

---

# 9. Recommended Inertia/Vue Structure

A possible structure:

```text
resources/js/

    Pages/
        Dashboard/
        Portfolio/
            Index.vue
            Create.vue
            Edit.vue
            Show.vue

        Profile/
        Projects/
        Skills/
        Experience/
        Education/
        Certifications/
        SocialLinks/
        Templates/

    Components/
        Portfolio/
        Projects/
        Skills/
        Forms/
        UI/

    Layouts/
        AppLayout.vue
        DashboardLayout.vue

    composables/
```

The exact organization can change as the frontend grows.

---

# 10. Template Marketplace / Catalog — Future

Once the core portfolio system works, build the template catalog.

Users should be able to:

```text
Browse Templates
       ↓
Preview Template
       ↓
Select Template
       ↓
Apply Template
       ↓
Preview Portfolio
       ↓
Publish
```

Potential future template metadata:

```text
templates
    id
    name
    slug
    description
    preview_image
    thumbnail_image
    version
    author
    category
    is_active
    is_featured
    sort_order
```

Do not add fields just because they might be useful later. Add them when the feature actually requires them.

---

# 11. Template Versioning — Future

Because templates may be updated frequently, template versioning may eventually become important.

Potential concept:

```text
Template
    ↓
Template Version
    ├── v1.0
    ├── v1.1
    └── v2.0
```

This should be considered before allowing users to heavily customize template-specific settings.

For the first version, a simple `version` field may be enough.

---

# 12. Public Portfolio System — Future

A published portfolio should have a stable public URL.

Example concept:

```text
yourapp.com/p/username
```

or:

```text
yourapp.com/portfolio/slug
```

The public route should:

1. Find the published portfolio.
2. Load its selected template.
3. Load required portfolio data.
4. Render the template.
5. Return the public page.

Only published portfolios should be publicly accessible.

---

# 13. Security / Authorization

A user must only be able to modify their own portfolio.

Every private portfolio action should eventually follow:

```text
Authenticated User
       ↓
Portfolio Ownership Check
       ↓
Allowed?
   ↙       ↘
 Yes        No
 ↓           ↓
Continue    403
```

Laravel Policies should be preferred for resource authorization.

Do not rely only on frontend restrictions.

---

# 14. Performance Considerations

As templates and users increase, avoid N+1 queries.

Use appropriate eager loading, for example conceptually:

```php
Portfolio::with([
    'profile',
    'skills',
    'projects.skills',
    'experiences',
    'educations',
    'certifications',
    'socialLinks',
    'template',
]);
```

The exact query should be optimized based on actual usage.

Do not prematurely optimize everything.

---

# 15. Testing Strategy

Start testing the business-critical parts.

Important tests:

```text
User can create portfolio
User can only edit own portfolio
Portfolio belongs to correct template
Portfolio can load all sections
Project belongs to portfolio
Skills attach correctly
Project skills attach correctly
Draft portfolio is not publicly accessible
Published portfolio is publicly accessible
Template renders correctly
Changing template does not change portfolio content
```

The last two tests are especially important because the template system is a core product feature.

---

# 17. Long-Term Roadmap

## Phase 1 — Foundation

```text
[x] Product concept
[x] Technology stack
[x] Database design
[x] Eloquent models
[x] Relationships
[ ] Factories
[ ] Seeders
```

## Phase 2 — Authentication

```text
[x] Registration
[x] Login
[x] Logout
[x] Email verification
[x] Password reset
[ ] User dashboard
```

## Phase 3 — Portfolio Builder

```text
[x] Create portfolio
[x] Edit portfolio
[x] Profile
[x] Skills
[x] Projects
[x] Experience
[x] Education
[x] Certifications
[x] Social links
```

## Phase 4 — Template System

```text
[ ] Template model
[ ] Template catalog
[ ] Template preview
[ ] Template selection
[ ] Template renderer
[ ] First template
[ ] Second template
[ ] Template switching
```

## Phase 5 — Publishing

```text
[ ] Portfolio preview
[ ] Draft/published states
[ ] Public portfolio URL
[ ] SEO metadata
[ ] Social sharing metadata
```

## Phase 6 — Customization

```text
[ ] Colors
[ ] Typography
[ ] Section visibility
[ ] Layout options
[ ] Template settings
```

## Phase 7 — Growth Features

Potential future features:

```text
[ ] More templates
[ ] Featured templates
[ ] Template categories
[ ] Template search/filter
[ ] Analytics
[ ] Custom domains
[ ] Resume download
[ ] SEO tools
[ ] Premium templates
[ ] Template versioning
[ ] Admin template management
```

---

# 18. Development Rule

For every feature, follow this loop:

```text
Requirement
    ↓
Database
    ↓
Model
    ↓
Relationship
    ↓
Validation
    ↓
Authorization
    ↓
Service / Business Logic
    ↓
Controller
    ↓
Inertia
    ↓
Vue
    ↓
Test
```

Do not automatically create every layer for every tiny operation.

For simple CRUD, a controller + Form Request + model may be enough.

Introduce services when the business logic becomes meaningful or reusable.

---

# 23. Most Important Architectural Rules

### Rule 1 — Content ≠ Design

Never couple portfolio content to a specific template.

### Rule 2 — Templates consume standardized data

Every template should receive the same portfolio information.

### Rule 3 — Adding a template should not require a database redesign

A new design should primarily mean adding a new template implementation and registering it.

### Rule 4 — Authorization belongs on the backend

Never trust Vue to protect user data.

### Rule 5 — Build vertical slices

Complete one feature from database → Laravel → Inertia → Vue → test before moving to the next.

### Rule 6 — Keep the first version simple

Do not build template marketplace, payments, analytics, custom domains, advanced customization, and versioning before the basic portfolio builder works.

---
