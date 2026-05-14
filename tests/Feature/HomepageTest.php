<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_open_the_homepage_with_the_approved_content(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('homepage.navigation', [
                ['label' => 'Services', 'href' => '#services'],
                ['label' => 'Work Process', 'href' => '#work-process'],
                ['label' => 'Official Status', 'href' => '#official-status'],
                ['label' => 'Contact', 'href' => '#contact'],
            ])
            ->where('homepage.hero.kicker', 'Independent backend software developer')
            ->where('homepage.hero.title', 'Scalable backend systems, API integrations, and reliable software architecture for long-term business operations.')
            ->where('homepage.hero.summary', 'I design and deliver maintainable backend systems for production-critical workflows, with a focus on API integrations, infrastructure clarity, long-term support, and stable operations.')
            ->where('homepage.hero.primary_cta', [
                'label' => 'Discuss a project',
                'href' => 'mailto:fenixxxar85@gmail.com',
            ])
            ->where('homepage.hero.secondary_cta', [
                'label' => 'View official registration extract',
                'href' => url('/storage/req.pdf'),
            ])
            ->where('homepage.hero.highlights', [
                'Backend systems',
                'REST APIs',
                'Docker infrastructure',
            ])
            ->where('homepage.identity', [
                'legal_name' => 'Aleksandr Olegovich Spitsyn',
                'status' => 'Sole proprietor registered in the Russian Federation',
                'ogrnip' => '324385000070485',
                'inn' => '382007599065',
                'registered_at' => 'June 27, 2024',
                'primary_activity' => 'Software development (OKVED 62.01)',
                'tax_regime' => 'Russian patent taxation system',
                'work_model' => 'Independent contractor with no employees',
                'registration_authority' => 'Interdistrict Inspectorate of the Federal Tax Service No. 17 for the Irkutsk Region',
            ])
            ->where('homepage.services', [
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
            ])
            ->where('homepage.process', [
                'title' => 'Work Process',
                'items' => [
                    'Requirements and technical discussion',
                    'Architecture and implementation planning',
                    'Backend development and API delivery',
                    'Deployment and release support',
                    'Monitoring, maintenance, and iterative improvement',
                ],
            ])
            ->where('homepage.stack', [
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
            ])
            ->where('homepage.achievements', [
                'title' => 'Professional History and Achievements',
                'items' => [
                    'Several years of professional software development experience',
                    'Remote contract work with direct client communication',
                    'Full lifecycle participation from planning to production monitoring',
                    'Experience with high-load and multi-region applications',
                    'Successful implementation of third-party integrations',
                    'Long-term cooperation with clients under service contracts',
                ],
            ])
            ->where('homepage.official_status', [
                'title' => 'Official Status',
                'items' => [
                    'I work independently as a registered sole proprietor in Russia.',
                    'My software development work is performed under contract agreements.',
                    'All communication, implementation, deployment, and support are handled personally.',
                    'This business structure is standard for independent software development and consulting services.',
                ],
            ])
            ->where('homepage.contact', [
                'title' => 'Contact',
                'email' => 'fenixxxar85@gmail.com',
                'cta_label' => 'Email for project discussion',
                'cta_href' => 'mailto:fenixxxar85@gmail.com',
                'note' => 'Available for remote contract work, backend delivery, integrations, and long-term technical support.',
            ])
            ->where('homepage.footer', [
                'legal_name' => 'Aleksandr Olegovich Spitsyn',
                'label' => 'Independent backend software developer',
                'note' => 'Public business profile for client and official review.',
            ])
        );
    }

    public function test_authenticated_users_can_open_the_homepage_and_receive_their_auth_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('auth.user.email', $user->email)
        );
    }
}
