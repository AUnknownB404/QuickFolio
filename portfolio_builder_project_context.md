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

# 4. Database Structure

The current database design discussed for the project is approximately:

```text
users
portfolios
portfolio_profiles

skills
portfolio_skills

projects
project_skills

experiences
educations
certifications
social_links

templates
```

## 4.1 users

Authentication/account information.

Typical fields:

```text
id
name
email
password
email_verified_at
created_at
updated_at
```

Relationship:

```text
User hasMany Portfolios
```

---

## 4.2 portfolios

The main portfolio entity.

Typical fields:

```text
id
user_id
template_id
title
slug
status
published_at
created_at
updated_at
```

Possible status values:

```text
draft
published
```

Relationships:

```text
Portfolio belongsTo User
Portfolio belongsTo Template
Portfolio hasOne Profile
Portfolio belongsToMany Skills
Portfolio hasMany Projects
Portfolio hasMany Experiences
Portfolio hasMany Educations
Portfolio hasMany Certifications
Portfolio hasMany SocialLinks
```

---

## 4.3 portfolio_profiles

Portfolio-specific personal information.

Typical fields:

```text
id
portfolio_id
first_name
last_name
headline
about
location
profile_image
resume
phone
created_at
updated_at
```

Relationship:

```text
Profile belongsTo Portfolio
```

---

## 4.4 skills

Reusable skills.

Typical fields:

```text
id
name
slug
created_at
updated_at
```

Examples:

```text
Laravel
PHP
Vue.js
JavaScript
MySQL
Docker
Git
```

---

## 4.5 portfolio_skills

Pivot table connecting portfolios and skills.

Typical fields:

```text
id
portfolio_id
skill_id
level
sort_order
```

Relationship:

```text
Portfolio belongsToMany Skills
Skill belongsToMany Portfolios
```

---

## 4.6 projects

Portfolio projects.

Typical fields:

```text
id
portfolio_id
title
slug
description
image
project_url
github_url
start_date
end_date
is_featured
sort_order
created_at
updated_at
```

Relationship:

```text
Project belongsTo Portfolio
Project belongsToMany Skills
```

---

## 4.7 project_skills

Pivot table connecting projects and skills.

Typical fields:

```text
id
project_id
skill_id
```

---

## 4.8 experiences

Professional/work experience.

Typical fields:

```text
id
portfolio_id
company
position
description
location
start_date
end_date
is_current
sort_order
created_at
updated_at
```

---

## 4.9 educations

Education history.

Typical fields:

```text
id
portfolio_id
institution
degree
field_of_study
description
start_date
end_date
sort_order
created_at
updated_at
```

---

## 4.10 certifications

Certificates and credentials.

Typical fields:

```text
id
portfolio_id
name
organization
credential_id
credential_url
issue_date
expiry_date
image
created_at
updated_at
```

---

## 4.11 social_links

Social/profile links.

Typical fields:

```text
id
portfolio_id
platform
url
sort_order
created_at
updated_at
```

Examples:

```text
GitHub
LinkedIn
Facebook
YouTube
X
```

---

## 4.12 templates

The template catalog.

Typical fields:

```text
id
name
slug
description
preview_image
thumbnail_image
version
is_active
is_featured
created_at
updated_at
```

This table is especially important because the product is intended to support many templates.

A portfolio references a template using:

```text
portfolios.template_id
        ↓
templates.id
```

Adding a new template should **not require changing the portfolio database schema**.

---

# 5. Important Database Principle

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

# 6. Template Architecture

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

# 7. Template Storage Strategy

A possible Laravel structure:

```text
resources/
    views/
        templates/
            minimal/
                home.blade.php
                components/

            developer/
                home.blade.php
                components/

            creative/
                home.blade.php
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

# 8. Current Development State

## Completed

- Product concept defined.
- Target users defined: non-coder users.
- Main portfolio workflow defined.
- SQL-first approach selected.
- Database structure designed.
- Template system identified as the primary product feature.
- Separation of content and design established.
- Laravel + Inertia + Vue.js selected as the application stack.

## Current Task

The next implementation phase is:

> **Build the Eloquent model layer from the existing database.**

The database should be treated as the current source of truth.

Do not redesign the database while creating the models unless an actual relationship or implementation problem is discovered.

---

# 9. Immediate Development Sequence

The recommended next sequence is:

```text
Database
   ↓
Eloquent Models
   ↓
Model Relationships
   ↓
Factories
   ↓
Seeders
   ↓
Authentication
   ↓
Authorization / Policies
   ↓
Form Requests
   ↓
Services
   ↓
Controllers
   ↓
Inertia Pages
   ↓
Vue Components
   ↓
Portfolio CRUD
   ↓
Template System
   ↓
Preview
   ↓
Publishing
   ↓
Public Portfolio
```

---

# 10. Model Implementation Order

Create the models in dependency order.

Recommended order:

```text
User
 ↓
Template
 ↓
Portfolio
 ↓
PortfolioProfile
 ↓
Skill
 ↓
Project
 ↓
Experience
 ↓
Education
 ↓
Certification
 ↓
SocialLink
```

Then configure the pivot relationships:

```text
Portfolio ↔ Skill
Project ↔ Skill
```

For each model:

1. Define `$fillable` or the chosen mass-assignment strategy.
2. Define casts where required.
3. Define relationships.
4. Define useful scopes.
5. Add factories.
6. Add tests for important relationships.

---

# 11. Example Model Relationship Map

```text
User
 └── hasMany(Portfolio)

Portfolio
 ├── belongsTo(User)
 ├── belongsTo(Template)
 ├── hasOne(PortfolioProfile)
 ├── belongsToMany(Skill)
 ├── hasMany(Project)
 ├── hasMany(Experience)
 ├── hasMany(Education)
 ├── hasMany(Certification)
 └── hasMany(SocialLink)

Project
 ├── belongsTo(Portfolio)
 └── belongsToMany(Skill)

Skill
 ├── belongsToMany(Portfolio)
 └── belongsToMany(Project)
```

---

# 12. After Models: Build the Portfolio CRUD

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

# 13. Recommended Inertia/Vue Structure

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

# 14. Template Marketplace / Catalog — Future

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

# 15. Template Versioning — Future

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

# 16. Customization System — Future

The user should eventually be able to customize things such as:

```text
Colors
Fonts
Layout options
Section visibility
Profile image style
Project layout
Social icon style
```

Keep customization separate from core content.

Conceptually:

```text
Portfolio Content
        +
Template
        +
Template Settings
        ↓
Final Portfolio
```

Do not put design settings into:

```text
projects
skills
experiences
```

---

# 17. Public Portfolio System — Future

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

# 18. Security / Authorization

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

# 19. Performance Considerations

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

# 20. Testing Strategy

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

# 21. Long-Term Roadmap

## Phase 1 — Foundation

```text
[x] Product concept
[x] Technology stack
[x] Database design
[ ] Eloquent models
[ ] Relationships
[ ] Factories
[ ] Seeders
```

## Phase 2 — Authentication

```text
[ ] Registration
[ ] Login
[ ] Logout
[ ] Email verification
[ ] Password reset
[ ] User dashboard
```

## Phase 3 — Portfolio Builder

```text
[ ] Create portfolio
[ ] Edit portfolio
[ ] Profile
[ ] Skills
[ ] Projects
[ ] Experience
[ ] Education
[ ] Certifications
[ ] Social links
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

# 22. Development Rule

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

# 24. Immediate Next Task

The project is now ready to move from:

```text
DATABASE DESIGN
```

to:

```text
ELOQUENT MODEL LAYER
```

The immediate implementation target is:

```text
1. User
2. Template
3. Portfolio
4. PortfolioProfile
5. Skill
6. Project
7. Experience
8. Education
9. Certification
10. SocialLink
11. Pivot relationships
12. Model factories
13. Relationship tests
```

After that, move into the first complete vertical slice:

```text
Create Portfolio
    ↓
Laravel validation
    ↓
Authorization
    ↓
Controller
    ↓
Inertia
    ↓
Vue form
    ↓
Database
    ↓
Test
```

This document should be treated as the current project handoff/context document. Update it as the architecture or feature scope changes.
