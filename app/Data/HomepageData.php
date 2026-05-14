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
     * @param  array{
     *     title: string,
     *     items: list<array{name: string, href: string, period: string, summary: string}>
     * }  $selected_clients
     * @param  array{title: string, items: list<string>}  $official_status
     * @param  array{
     *     title: string,
     *     email: string,
     *     cta_label: string,
     *     cta_href: string,
     *     note: string,
     *     links: list<array{label: string, href: string}>
     * }  $contact
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
        public array $selected_clients,
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
                ['label' => 'Clients', 'href' => '#selected-clients'],
                ['label' => 'Work Process', 'href' => '#work-process'],
                ['label' => 'Official Status', 'href' => '#official-status'],
                ['label' => 'Contact', 'href' => '#contact'],
            ],
            hero: [
                'kicker' => 'Independent backend software developer',
                'title' => 'Scalable backend systems, API integrations, and reliable software architecture for long-term business operations.',
                'summary' => 'I provide remote backend development for long-term client engagements, focusing on web applications, REST APIs, scalable backend systems, system architecture, API integrations, and business automation solutions.',
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
                    'Business automation',
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
                    'ERP and third-party service integrations',
                    'High-load web application development',
                    'System architecture and technical consulting',
                    'Maintenance and optimization of existing projects',
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
                    'Development of scalable backend systems for business platforms',
                    'Experience working with high-load and multi-region applications',
                    'Long-term cooperation with clients under service contracts',
                    'Development and support of production systems with continuous monitoring and optimization',
                ],
            ],
            selected_clients: [
                'title' => 'Selected Clients and Contract History',
                'items' => [
                    [
                        'name' => 'Elite Trade (ETFP)',
                        'href' => 'https://etfp.ru',
                        'period' => '2025 - Present',
                        'summary' => 'Development of internal business systems, ERP (1C) integrations, automation tools, APIs, and high-load backend infrastructure for foodtech and logistics operations.',
                    ],
                    [
                        'name' => 'Flor2u.ru',
                        'href' => 'https://flor2u.ru/',
                        'period' => '2023 - 2025',
                        'summary' => 'Led development of new platform modules, participated in CRM development, created APIs for mobile applications, and optimized database performance for a large flower delivery e-commerce platform.',
                    ],
                    [
                        'name' => 'Hicaliber',
                        'href' => 'https://www.hicaliber.com.au/',
                        'period' => '2021 - 2023',
                        'summary' => 'Developed B2B CRM systems, real estate integrations, lead processing systems, internal messengers, logistics solutions, and business automation platforms for Australian companies. Managed a development team and participated in large-scale enterprise projects.',
                    ],
                    [
                        'name' => 'Irkutsk Segodnya',
                        'href' => 'https://irk.today/',
                        'period' => '2017 - 2021',
                        'summary' => 'Worked on media platforms, CRM systems, ticket aggregation services, mobile applications, and microservices architecture for regional news and event platforms.',
                    ],
                    [
                        'name' => 'Irk Life',
                        'href' => 'https://irk.life/',
                        'period' => '2017 - 2019',
                        'summary' => 'Developed internal corporate systems for warehouse accounting, logistics, QR tracking, equipment monitoring, and transport control using GPS/GLONASS data.',
                    ],
                ],
            ],
            official_status: [
                'title' => 'Official Status',
                'items' => [
                    'I am officially registered in the Russian Federation as an Individual Entrepreneur (sole proprietor / ИП).',
                    'My business operates under the Russian Patent Taxation System, which is a simplified legal taxation regime for independent professionals and small businesses.',
                    'I work independently without employees, which is common in software development and IT consulting.',
                    'All development, communication, deployment, and technical support tasks are performed personally by me.',
                ],
            ],
            contact: [
                'title' => 'Contact',
                'email' => 'fenixxxar85@gmail.com',
                'cta_label' => 'Email for project discussion',
                'cta_href' => 'mailto:fenixxxar85@gmail.com',
                'note' => 'Most engagements are long-term cooperation agreements focused on continuous backend development, maintenance, integrations, and remote technical collaboration.',
                'links' => [
                    [
                        'label' => 'LinkedIn',
                        'href' => 'https://www.linkedin.com/in/alexandr-spitsyn-79716020a/',
                    ],
                    [
                        'label' => 'GitHub',
                        'href' => 'https://github.com/AleksandrSpicyn',
                    ],
                ],
            ],
            footer: [
                'legal_name' => 'Aleksandr Olegovich Spitsyn',
                'label' => 'Independent backend software developer',
                'note' => 'Public business profile, client history, and official review information.',
            ],
        );
    }
}
