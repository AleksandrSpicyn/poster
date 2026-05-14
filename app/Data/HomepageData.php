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
