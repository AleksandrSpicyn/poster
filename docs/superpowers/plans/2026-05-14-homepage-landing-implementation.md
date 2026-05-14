# Homepage Landing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the current placeholder homepage with an English public landing page that sells backend engineering services while exposing embassy-friendly business identity details on the first screen.

**Architecture:** Move `/` from an inline route closure to a dedicated `HomeController` that returns a typed `HomepageData` payload to the existing Inertia `Welcome` page. Keep homepage content static and code-owned, with approved facts copied from `storage/app/public/profile.md` and `storage/app/public/req.pdf`, then render the page through a prop-driven Vue template plus page-scoped CSS utilities.

**Tech Stack:** Laravel 12, Inertia.js, Vue 3, TypeScript, Tailwind CSS v4, PHPUnit, Spatie Laravel Data

**Preflight note:** `phpunit.xml` uses in-memory SQLite. Before running the test steps below, ensure CLI PHP has `pdo_sqlite` enabled or execute the test commands in an environment where the default PHPUnit database configuration works.

---

## File Map

- Create: `app/Data/HomepageData.php`
  - Static, typed source of approved homepage content and public document/contact URLs.
- Create: `app/Http/Controllers/HomeController.php`
  - Public controller that renders the homepage and passes `homepage` props to Inertia.
- Create: `tests/Feature/HomepageTest.php`
  - Feature-level contract test for the new public homepage response and auth compatibility.
- Create: `resources/js/types/homepage.ts`
  - Frontend TypeScript contract for `homepage` page props.
- Modify: `routes/web.php`
  - Replace `/` route closure with `HomeController`.
- Modify: `resources/js/pages/Welcome.vue`
  - Render the new landing page using the approved structure and style direction.
- Modify: `resources/css/app.css`
  - Add page-scoped marketing classes used by `Welcome.vue`.

## Task 1: Lock the Homepage Contract in a Feature Test and Route It Through a Controller

**Files:**
- Create: `tests/Feature/HomepageTest.php`
- Create: `app/Data/HomepageData.php`
- Create: `app/Http/Controllers/HomeController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the failing homepage feature test**

Create `tests/Feature/HomepageTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_homepage_exposes_the_approved_public_profile_contract(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('homepage.hero.kicker', 'Independent backend software developer')
                ->where('homepage.identity.legal_name', 'Aleksandr Olegovich Spitsyn')
                ->where('homepage.identity.ogrnip', '324385000070485')
                ->where('homepage.identity.inn', '382007599065')
                ->where('homepage.contact.email', 'fenixxxar85@gmail.com')
                ->where('homepage.hero.secondary_cta.href', url('/storage/req.pdf'))
                ->has('homepage.navigation', 4)
                ->has('homepage.services.items', 9)
                ->has('homepage.process.items', 5)
                ->has('homepage.stack.items', 10)
                ->has('homepage.achievements.items', 6)
                ->has('homepage.official_status.items', 4)
            );
    }

    public function test_authenticated_users_can_still_open_the_public_homepage(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('auth.user.email', $user->email)
            );
    }
}
```

- [ ] **Step 2: Run the focused test to verify it fails**

Run:

```bash
php artisan test tests/Feature/HomepageTest.php
```

Expected: FAIL because the current `/` route returns `Welcome` without a `homepage` prop, typically with an Inertia assertion like `Property [homepage] does not exist`.

- [ ] **Step 3: Write the minimal backend implementation to satisfy the contract**

Create `app/Data/HomepageData.php`:

```php
<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class HomepageData extends Data
{
    /**
     * @param  list<array{label: string, href: string}>  $navigation
     * @param  array{
     *     kicker: string,
     *     title: string,
     *     summary: string,
     *     primary_cta: array{label: string, href: string},
     *     secondary_cta: array{label: string, href: string},
     *     highlights: list<string>
     * }  $hero
     * @param  array{
     *     legal_name: string,
     *     status: string,
     *     ogrnip: string,
     *     inn: string,
     *     registered_at: string,
     *     primary_activity: string,
     *     tax_regime: string,
     *     work_model: string,
     *     registration_authority: string
     * }  $identity
     * @param  array{title: string, items: list<string>}  $services
     * @param  array{title: string, items: list<string>}  $process
     * @param  array{title: string, items: list<string>}  $stack
     * @param  array{title: string, items: list<string>}  $achievements
     * @param  array{title: string, items: list<string>}  $official_status
     * @param  array{title: string, email: string, cta_label: string, cta_href: string, note: string}  $contact
     * @param  array{legal_name: string, label: string, note: string}  $footer
     */
    public function __construct(
        public array $navigation,
        public array $hero,
        public array $identity,
        public array $services,
        public array $process,
        public array $stack,
        public array $achievements,
        public array $official_status,
        public array $contact,
        public array $footer,
    ) {
    }

    public static function fromApprovedContent(): self
    {
        return new self(
            navigation: [
                ['label' => 'Services', 'href' => '#services'],
                ['label' => 'Work Process', 'href' => '#work-process'],
                ['label' => 'Official Status', 'href' => '#official-status'],
                ['label' => 'Contact', 'href' => '#contact'],
            ],
            hero: [
                'kicker' => 'Independent backend software developer',
                'title' => 'Scalable backend systems, API integrations, and reliable software architecture for long-term business operations.',
                'summary' => 'I design and deliver maintainable backend systems for production-critical workflows, with a focus on API integrations, infrastructure clarity, long-term support, and stable operations.',
                'primary_cta' => [
                    'label' => 'Discuss a project',
                    'href' => 'mailto:fenixxxar85@gmail.com',
                ],
                'secondary_cta' => [
                    'label' => 'View official registration extract',
                    'href' => url('/storage/req.pdf'),
                ],
                'highlights' => [
                    'Backend systems',
                    'REST APIs',
                    'Docker infrastructure',
                ],
            ],
            identity: [
                'legal_name' => 'Aleksandr Olegovich Spitsyn',
                'status' => 'Sole proprietor registered in the Russian Federation',
                'ogrnip' => '324385000070485',
                'inn' => '382007599065',
                'registered_at' => 'June 27, 2024',
                'primary_activity' => 'Software development (OKVED 62.01)',
                'tax_regime' => 'Russian patent taxation system',
                'work_model' => 'Independent contractor with no employees',
                'registration_authority' => 'Interdistrict Inspectorate of the Federal Tax Service No. 17 for the Irkutsk Region',
            ],
            services: [
                'title' => 'Services',
                'items' => [
                    'Backend development using PHP and Laravel',
                    'REST API development and integrations',
                    'Database architecture and optimization',
                    'Docker-based infrastructure setup',
                    'Queue systems and asynchronous processing',
                    'ERP and third-party integrations',
                    'High-load web application development',
                    'System architecture and technical consulting',
                    'Maintenance and optimization of existing systems',
                ],
            ],
            process: [
                'title' => 'Work Process',
                'items' => [
                    'Requirements and technical discussion',
                    'Architecture and implementation planning',
                    'Backend development and API delivery',
                    'Deployment and release support',
                    'Monitoring, maintenance, and iterative improvement',
                ],
            ],
            stack: [
                'title' => 'Technology Stack',
                'items' => [
                    'PHP 8.x',
                    'Laravel',
                    'PostgreSQL',
                    'MySQL',
                    'Redis',
                    'Docker',
                    'Git',
                    'REST APIs',
                    'Queue systems and Horizon',
                    'Linux server environments',
                ],
            ],
            achievements: [
                'title' => 'Professional History and Achievements',
                'items' => [
                    'Several years of professional software development experience',
                    'Remote contract work with direct client communication',
                    'Full lifecycle participation from planning to production monitoring',
                    'Experience with high-load and multi-region applications',
                    'Successful implementation of third-party integrations',
                    'Long-term cooperation with clients under service contracts',
                ],
            ],
            official_status: [
                'title' => 'Official Status',
                'items' => [
                    'I work independently as a registered sole proprietor in Russia.',
                    'My software development work is performed under contract agreements.',
                    'All communication, implementation, deployment, and support are handled personally.',
                    'This business structure is standard for independent software development and consulting services.',
                ],
            ],
            contact: [
                'title' => 'Contact',
                'email' => 'fenixxxar85@gmail.com',
                'cta_label' => 'Email for project discussion',
                'cta_href' => 'mailto:fenixxxar85@gmail.com',
                'note' => 'Available for remote contract work, backend delivery, integrations, and long-term technical support.',
            ],
            footer: [
                'legal_name' => 'Aleksandr Olegovich Spitsyn',
                'label' => 'Independent backend software developer',
                'note' => 'Public business profile for client and official review.',
            ],
        );
    }
}
```

Create `app/Http/Controllers/HomeController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Data\HomepageData;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Welcome', [
            'homepage' => HomepageData::fromApprovedContent()->toArray(),
        ]);
    }
}
```

Modify `routes/web.php`:

```php
<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Posts\ListController as PostsListController;
use App\Http\Controllers\Prompts\ListController as PromptsListController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');

Route::get('policy', static function () {
    return Inertia::render('Policy');
})->name('policy');

Route::get('terms', static function () {
    return Inertia::render('Terms');
})->name('terms');

Route::get('dashboard', static function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', static function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('posts', PostsListController::class)->name('posts');
    Route::get('prompts', PromptsListController::class)->name('prompts');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
```

- [ ] **Step 4: Run the focused test to verify it passes**

Run:

```bash
php artisan test tests/Feature/HomepageTest.php
```

Expected: PASS with 2 tests passing and no Inertia contract failures.

- [ ] **Step 5: Commit**

```bash
git add app/Data/HomepageData.php app/Http/Controllers/HomeController.php routes/web.php tests/Feature/HomepageTest.php
git commit -m "feat: add homepage content contract"
```

## Task 2: Implement the Prop-Driven Landing Page in Vue

**Files:**
- Create: `resources/js/types/homepage.ts`
- Modify: `resources/js/pages/Welcome.vue`

**Implementation note:** This repo does not currently include a dedicated frontend unit test runner. The safety rail for this task is the prop contract locked in Task 1, plus TypeScript/build verification and browser smoke checks.

- [ ] **Step 1: Add the frontend type contract for the homepage props**

Create `resources/js/types/homepage.ts`:

```ts
export interface HomepageNavItem {
    label: string;
    href: string;
}

export interface HomepageAction {
    label: string;
    href: string;
}

export interface HomepageHero {
    kicker: string;
    title: string;
    summary: string;
    primary_cta: HomepageAction;
    secondary_cta: HomepageAction;
    highlights: string[];
}

export interface HomepageIdentity {
    legal_name: string;
    status: string;
    ogrnip: string;
    inn: string;
    registered_at: string;
    primary_activity: string;
    tax_regime: string;
    work_model: string;
    registration_authority: string;
}

export interface HomepageListSection {
    title: string;
    items: string[];
}

export interface HomepageContact {
    title: string;
    email: string;
    cta_label: string;
    cta_href: string;
    note: string;
}

export interface HomepageFooter {
    legal_name: string;
    label: string;
    note: string;
}

export interface HomepageProps {
    navigation: HomepageNavItem[];
    hero: HomepageHero;
    identity: HomepageIdentity;
    services: HomepageListSection;
    process: HomepageListSection;
    stack: HomepageListSection;
    achievements: HomepageListSection;
    official_status: HomepageListSection;
    contact: HomepageContact;
    footer: HomepageFooter;
}
```

- [ ] **Step 2: Rewrite `Welcome.vue` around the new landing content**

Replace `resources/js/pages/Welcome.vue` with:

```vue
<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';

import type { SharedData } from '@/types';
import type { HomepageProps } from '@/types/homepage';

defineProps<{
    homepage: HomepageProps;
}>();

const page = usePage<SharedData>();
</script>

<template>
    <Head title="Aleksandr Spitsyn" />

    <div class="public-home relative min-h-screen overflow-hidden bg-[linear-gradient(180deg,#eef4f7_0%,#f8fbfc_38%,#fdfefe_100%)] text-slate-950">
        <div class="public-home-glow public-home-glow--teal"></div>
        <div class="public-home-glow public-home-glow--slate"></div>

        <header class="sticky top-0 z-30 border-b border-white/70 bg-white/80 backdrop-blur-xl">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-6 py-4">
                <div class="min-w-0">
                    <p class="text-[0.68rem] font-semibold uppercase tracking-[0.28em] text-teal-700">
                        {{ homepage.hero.kicker }}
                    </p>
                    <p class="truncate text-sm text-slate-600">Aleksandr Olegovich Spitsyn</p>
                </div>

                <nav class="hidden items-center gap-6 text-sm text-slate-600 lg:flex">
                    <a v-for="item in homepage.navigation" :key="item.href" :href="item.href" class="transition hover:text-slate-950">
                        {{ item.label }}
                    </a>
                </nav>

                <div class="flex items-center gap-3">
                    <Link
                        v-if="page.props.auth.user"
                        :href="route('dashboard')"
                        class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
                    >
                        Dashboard
                    </Link>

                    <Link
                        v-else
                        :href="route('login')"
                        class="rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
                    >
                        Login
                    </Link>

                    <a
                        :href="homepage.hero.primary_cta.href"
                        class="rounded-full bg-slate-950 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                    >
                        {{ homepage.hero.primary_cta.label }}
                    </a>
                </div>
            </div>
        </header>

        <main class="relative">
            <section class="mx-auto max-w-7xl px-6 pb-18 pt-16 lg:pb-24 lg:pt-24">
                <div class="grid gap-10 xl:grid-cols-[1.2fr_0.8fr] xl:items-start">
                    <div class="space-y-8">
                        <div class="space-y-5">
                            <p class="public-home-eyebrow">{{ homepage.hero.kicker }}</p>
                            <h1 class="max-w-4xl text-4xl font-semibold tracking-tight text-slate-950 sm:text-5xl lg:text-6xl">
                                {{ homepage.hero.title }}
                            </h1>
                            <p class="max-w-2xl text-lg leading-8 text-slate-600">
                                {{ homepage.hero.summary }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-4">
                            <a
                                :href="homepage.hero.primary_cta.href"
                                class="rounded-full bg-slate-950 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                            >
                                {{ homepage.hero.primary_cta.label }}
                            </a>
                            <a
                                :href="homepage.hero.secondary_cta.href"
                                target="_blank"
                                rel="noreferrer"
                                class="rounded-full border border-slate-300 bg-white/75 px-6 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950"
                            >
                                {{ homepage.hero.secondary_cta.label }}
                            </a>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <span
                                v-for="highlight in homepage.hero.highlights"
                                :key="highlight"
                                class="public-home-chip"
                            >
                                {{ highlight }}
                            </span>
                        </div>
                    </div>

                    <aside class="public-home-card">
                        <p class="public-home-eyebrow">Official business identity</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ homepage.identity.legal_name }}</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ homepage.identity.status }}</p>

                        <dl class="mt-6 space-y-4 text-sm text-slate-700">
                            <div class="public-home-identity-row">
                                <dt>OGRNIP</dt>
                                <dd>{{ homepage.identity.ogrnip }}</dd>
                            </div>
                            <div class="public-home-identity-row">
                                <dt>INN</dt>
                                <dd>{{ homepage.identity.inn }}</dd>
                            </div>
                            <div class="public-home-identity-row">
                                <dt>Registered</dt>
                                <dd>{{ homepage.identity.registered_at }}</dd>
                            </div>
                            <div class="public-home-identity-row">
                                <dt>Primary activity</dt>
                                <dd>{{ homepage.identity.primary_activity }}</dd>
                            </div>
                            <div class="public-home-identity-row">
                                <dt>Tax regime</dt>
                                <dd>{{ homepage.identity.tax_regime }}</dd>
                            </div>
                            <div class="public-home-identity-row">
                                <dt>Work model</dt>
                                <dd>{{ homepage.identity.work_model }}</dd>
                            </div>
                        </dl>

                        <p class="mt-6 text-sm leading-6 text-slate-500">
                            {{ homepage.identity.registration_authority }}
                        </p>
                    </aside>
                </div>
            </section>

            <section id="services" class="mx-auto max-w-7xl px-6 py-8 lg:py-12">
                <div class="grid gap-6 lg:grid-cols-2">
                    <article class="public-home-panel">
                        <p class="public-home-eyebrow">Services</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ homepage.services.title }}</h2>
                        <ul class="mt-6 grid gap-3 text-sm leading-6 text-slate-600">
                            <li v-for="item in homepage.services.items" :key="item" class="public-home-list-item">
                                {{ item }}
                            </li>
                        </ul>
                    </article>

                    <article id="work-process" class="public-home-panel">
                        <p class="public-home-eyebrow">Delivery model</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ homepage.process.title }}</h2>
                        <ol class="mt-6 grid gap-3 text-sm leading-6 text-slate-600">
                            <li
                                v-for="(item, index) in homepage.process.items"
                                :key="item"
                                class="flex items-start gap-3"
                            >
                                <span class="public-home-step">{{ index + 1 }}</span>
                                <span>{{ item }}</span>
                            </li>
                        </ol>
                    </article>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-6 py-8 lg:py-12">
                <div class="grid gap-6 lg:grid-cols-2">
                    <article class="public-home-panel">
                        <p class="public-home-eyebrow">Engineering evidence</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ homepage.stack.title }}</h2>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <span v-for="item in homepage.stack.items" :key="item" class="public-home-chip">
                                {{ item }}
                            </span>
                        </div>
                    </article>

                    <article class="public-home-panel">
                        <p class="public-home-eyebrow">Track record</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ homepage.achievements.title }}</h2>
                        <ul class="mt-6 grid gap-3 text-sm leading-6 text-slate-600">
                            <li v-for="item in homepage.achievements.items" :key="item" class="public-home-list-item">
                                {{ item }}
                            </li>
                        </ul>
                    </article>
                </div>
            </section>

            <section id="official-status" class="mx-auto max-w-7xl px-6 py-8 lg:py-12">
                <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                    <article class="public-home-panel">
                        <p class="public-home-eyebrow">Official status</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ homepage.official_status.title }}</h2>
                        <ul class="mt-6 grid gap-3 text-sm leading-6 text-slate-600">
                            <li v-for="item in homepage.official_status.items" :key="item" class="public-home-list-item">
                                {{ item }}
                            </li>
                        </ul>
                    </article>

                    <article id="contact" class="public-home-panel">
                        <p class="public-home-eyebrow">Contact</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ homepage.contact.title }}</h2>
                        <p class="mt-4 text-sm leading-6 text-slate-600">
                            {{ homepage.contact.note }}
                        </p>
                        <a
                            :href="homepage.contact.cta_href"
                            class="mt-6 inline-flex rounded-full bg-teal-700 px-6 py-3 text-sm font-semibold text-white transition hover:bg-teal-800"
                        >
                            {{ homepage.contact.cta_label }}
                        </a>
                        <p class="mt-5 text-sm text-slate-500">
                            {{ homepage.contact.email }}
                        </p>
                    </article>
                </div>
            </section>
        </main>

        <footer class="border-t border-white/70 bg-white/70">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-6 text-sm text-slate-500 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="font-medium text-slate-700">{{ homepage.footer.legal_name }}</p>
                    <p>{{ homepage.footer.label }} · {{ homepage.footer.note }}</p>
                </div>

                <div class="flex items-center gap-4">
                    <Link :href="route('policy')" class="transition hover:text-slate-950">Privacy Policy</Link>
                    <Link :href="route('terms')" class="transition hover:text-slate-950">Terms of Service</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
```

- [ ] **Step 3: Run the frontend type-check and build to verify the new page compiles**

Run:

```bash
./node_modules/.bin/vue-tsc --noEmit
npm run build
```

Expected: PASS on both commands, with no TypeScript prop/import errors and a successful Vite build that generates `public/build/*` assets.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/Welcome.vue resources/js/types/homepage.ts
git commit -m "feat: implement public homepage landing page"
```

## Task 3: Add Page-Scoped CSS for the Approved Technical Boutique Style

**Files:**
- Modify: `resources/css/app.css`

- [ ] **Step 1: Add page-scoped component classes for the landing page**

Append this block to `resources/css/app.css`:

```css
@layer components {
    .public-home-glow {
        @apply pointer-events-none absolute blur-3xl;
    }

    .public-home-glow--teal {
        @apply -left-20 top-20 h-72 w-72 rounded-full bg-teal-200/45;
    }

    .public-home-glow--slate {
        @apply right-0 top-0 h-80 w-80 rounded-full bg-sky-100/60;
    }

    .public-home-card {
        @apply rounded-[2rem] border border-white/80 bg-white/88 p-7 shadow-[0_30px_100px_-48px_rgba(15,23,42,0.55)] backdrop-blur;
    }

    .public-home-panel {
        @apply rounded-[1.75rem] border border-white/80 bg-white/78 p-7 shadow-[0_24px_80px_-56px_rgba(15,23,42,0.5)] backdrop-blur;
    }

    .public-home-chip {
        @apply rounded-full border border-teal-100 bg-teal-50 px-4 py-2 text-sm font-medium text-teal-900;
    }

    .public-home-eyebrow {
        @apply text-[0.72rem] font-semibold uppercase tracking-[0.28em] text-teal-700;
    }

    .public-home-list-item {
        @apply rounded-2xl border border-slate-200/70 bg-slate-50/75 px-4 py-3;
    }

    .public-home-step {
        @apply inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-950 text-xs font-semibold text-white;
    }

    .public-home-identity-row {
        @apply grid gap-1 border-b border-slate-200/80 pb-4 last:border-b-0 last:pb-0;
        grid-template-columns: minmax(0, 8rem) minmax(0, 1fr);
    }

    .public-home-identity-row dt {
        @apply text-slate-500;
    }

    .public-home-identity-row dd {
        @apply font-medium text-slate-800;
    }
}
```

- [ ] **Step 2: Re-run the frontend build to verify the CSS layer compiles**

Run:

```bash
npm run build
```

Expected: PASS with no Tailwind layer or unknown utility errors.

- [ ] **Step 3: Commit**

```bash
git add resources/css/app.css
git commit -m "style: add homepage landing page styling"
```

## Task 4: Verify the Experience End-to-End

**Files:**
- Test: `tests/Feature/HomepageTest.php`
- Verify: `resources/js/pages/Welcome.vue`
- Verify: `resources/css/app.css`

- [ ] **Step 1: Run the focused backend verification**

Run:

```bash
php artisan test tests/Feature/HomepageTest.php tests/Feature/Auth/AuthenticationTest.php
```

Expected: PASS. This confirms the public homepage contract works and existing login behavior is still intact.

- [ ] **Step 2: Run the production build verification**

Run:

```bash
./node_modules/.bin/vue-tsc --noEmit
npm run build
```

Expected: PASS on both commands, with generated manifest and asset bundles.

- [ ] **Step 3: Run a browser smoke check for guest and authenticated states**

Run:

```bash
php artisan serve --host=127.0.0.1 --port=8008
```

Then verify manually in the browser:

- Guest `/` shows the new landing page hero, official identity card, services, official status, and contact CTA.
- Guest `/login` still renders normally.
- After logging in, `/` still opens and shows the dashboard button in the header.
- `/dashboard` still renders.
- On a narrow viewport, hero content stacks above the identity card and CTA buttons remain accessible.

- [ ] **Step 4: Review the worktree and confirm no unexpected changes remain**

Run:

```bash
git status --short
```

Expected: clean worktree, or only intentional homepage files if you have not committed the earlier task checkpoints yet. Do not create a no-op commit at this stage.
