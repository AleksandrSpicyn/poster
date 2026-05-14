# Homepage Landing Design

Date: 2026-05-14
Status: Approved in chat, pending file review

## Goal

Replace the current public homepage with a new English landing page that serves two audiences at once:

1. potential clients evaluating backend engineering services
2. embassy or official reviewers verifying business legitimacy and work profile

The page must feel commercially credible and visually strong without hiding formal business facts below the fold.

## Audience Strategy

The homepage uses a balanced authority model.

- Client-facing reading: the page should clearly communicate backend engineering value, services, technical maturity, and a reliable delivery model.
- Embassy-facing reading: the page should immediately expose legal identity, registration details, and plain-English explanations of business status and work model.

Neither audience should feel like a secondary afterthought.

## Core Direction

The approved direction is:

- Structure: `Balanced authority`
- Visual style: `Technical boutique`
- Language: English only

This means the page should read like a premium engineering profile with visible official documentation, not like a generic startup homepage and not like a dry legal registry extract.

## Visual Direction

### Look and feel

- Light, mineral palette with cool gray, slate, muted teal, and restrained steel-blue accents
- Clean, premium, engineering-oriented atmosphere
- Strong hierarchy with deliberate whitespace
- Calm, documentary tone rather than flashy marketing
- Modern but serious visual language suitable for official review

### Typography

- Primary typography should feel sharp, calm, and technical
- Headlines should carry authority without becoming corporate boilerplate
- Avoid decorative startup aesthetics and avoid legal-document monotony

### Motion

- Minimal motion only
- Subtle reveal or staggered entrance is acceptable
- No playful effects, parallax, or noisy micro-interactions

## Page Outcome

The page should answer these questions within one scroll:

- Who is this developer?
- What services does he provide?
- Why is he credible technically?
- What is the legal and business status?
- How can a client or reviewer verify the identity and reach out?

## Information Sources

### Professional profile

Use `storage/app/public/profile.md` as the source for service narrative, work history, mission, vision, process, and achievements.

### Official business details

Use `storage/app/public/req.pdf` as the source for public legal identity and registration details.

Approved official facts for the page:

- Full legal name: Aleksandr Olegovich Spitsyn
- Sole proprietor registered in the Russian Federation
- OGRNIP: 324385000070485
- INN: 382007599065
- Registration date: June 27, 2024
- Primary activity: Software development (OKVED 62.01)
- Registration authority: Interdistrict Inspectorate of the Federal Tax Service No. 17 for the Irkutsk Region
- Tax context: patent taxation system, as described in `profile.md`
- Work model: independent contractor with no employees

## Page Structure

### 1. Header

Simple, clean public header with anchor navigation.

Suggested items:

- Services
- Work Process
- Official Status
- Contact

Header should be minimal and should not look like an app dashboard.

### 2. Hero Section

The first screen is split into two complementary zones.

#### Left side

Client-facing commercial promise:

- clear one-line positioning as an independent backend software developer
- strong headline focused on scalable backend systems, API integrations, and reliable architecture
- short supporting paragraph emphasizing long-term operational reliability and business-focused engineering
- primary CTA for contacting or discussing a project
- secondary CTA for viewing or downloading official profile materials

#### Right side

Official identity card:

- legal name
- sole proprietor status
- OGRNIP
- INN
- registration date
- primary activity
- independent contractor / no employees note
- patent taxation note

This card must feel integrated into the design, not bolted on as a compliance appendix.

### 3. Services Section

A structured services block that translates the profile into client-readable offers.

Include:

- backend development using PHP and Laravel
- REST API development and integrations
- database architecture and optimization
- Docker-based infrastructure setup
- queue systems and asynchronous processing
- ERP and third-party integrations
- high-load web application development
- technical consulting and architecture
- maintenance and optimization of existing systems

This section should feel precise and useful, not inflated.

### 4. Work Process Section

Explain how engagement typically works in concise operational terms.

Suggested flow:

- requirements and technical discussion
- architecture and planning
- implementation
- deployment and release support
- monitoring, maintenance, and iteration

This should support both client trust and embassy readability.

### 5. Technology Stack Section

Present the stack as a concise technical capability block.

Include:

- PHP 8.x
- Laravel
- PostgreSQL / MySQL
- Redis
- Docker
- Git
- REST APIs
- queue systems and Horizon
- Linux server environments
- modular architecture / microservice-oriented thinking

This section should visually read as engineering evidence rather than buzzword decoration.

### 6. Professional History and Achievements

Summarize experience in plain, serious language.

Include:

- several years of professional software development experience
- remote contract work
- full lifecycle participation from planning to production monitoring
- high-load and multi-region application experience
- successful third-party integrations
- long-term service relationships with clients

Avoid unverifiable numbers, vague claims, or startup-style bragging.

### 7. Official Status / Embassy-Friendly Explanation

Add a dedicated plain-English section explaining the business model in a direct, review-friendly format.

This section should state:

- the developer works independently as a registered sole proprietor in Russia
- work is performed under contract agreements
- all technical work, communication, deployment, and support are handled personally
- the legal structure is normal for independent software development and consulting

This block should reduce ambiguity for non-technical and official readers.

### 8. Contact Section

The contact area should be simple and concrete.

Current approved public contact:

- email: `fenixxxar85@gmail.com`

If real public profile links are added later, they can be included, but no placeholder social links should be invented now.

### 9. Footer

Footer should include:

- full legal/public name
- concise professional label
- policy link
- terms link

It may also repeat a short official note if that improves credibility without clutter.

## Copy Direction

### Tone

Copy must be:

- precise
- calm
- senior
- credible
- non-hype
- easy to verify

### Avoid

- exaggerated marketing claims
- startup clichés such as "cutting-edge", "world-class", or "disruptive"
- vague value statements with no concrete service meaning
- overlong legal paragraphs in the hero

### Target impression

The reader should feel:

- this person builds serious backend systems
- this person is legitimate and easy to verify
- this page is composed, modern, and trustworthy

## UX Principles

- The page must be coherent in a single long-scroll layout
- Official identity must be visible in the first impression, not buried
- Each section should be short enough to scan and substantial enough to support trust
- Mobile layout must preserve the same authority hierarchy
- The page should work well without requiring any interaction beyond scrolling

## Implementation Constraints

- The new landing page replaces the current homepage rendered by `resources/js/pages/Welcome.vue`
- It remains a public page on the existing `/` route
- The page should fit the existing Laravel + Inertia + Vue stack
- No CMS or admin editing flow is required
- No fabricated metrics, testimonials, or client logos should be introduced

## Non-Goals

- No multilingual homepage in this iteration
- No separate embassy-only page
- No full portfolio or case-study system
- No new backend data model for homepage content
- No social/contact placeholders that cannot be backed by real public information

## Acceptance Criteria

The design is successful when:

1. the homepage clearly communicates backend engineering services in English
2. the official legal identity is visible and readable in the first screen area
3. the page works for both commercial and official review use cases
4. the visual tone feels premium, technical, and trustworthy
5. the page becomes the main public homepage instead of the current placeholder welcome screen
6. the content is grounded in `profile.md`, `req.pdf`, and already-approved public contact details
