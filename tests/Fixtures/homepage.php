<?php

declare(strict_types=1);

return [
    'homepage.navigation' => [
        ['label' => 'Video workspace', 'href' => '#workspace'],
        ['label' => 'TikTok for creators', 'href' => '#tiktok'],
        ['label' => 'Contact', 'href' => '#contact'],
    ],
    'homepage.hero' => [
        'kicker' => 'Poster',
        'title' => 'A workspace for short video series',
        'summary' => 'Plan episodes, assemble scenes into a finished video and keep track of releases across connected channels. Use the creator workspace to preview an original video, choose its audience and share it to your own TikTok account.',
        'primary_cta' => ['label' => 'Explore the creator workspace', 'href' => '#tiktok'],
        'secondary_cta' => ['label' => 'See the video workflow', 'href' => '#workspace'],
        'highlights' => [],
    ],
    'homepage.identity' => [
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
    'homepage.services' => [
        'title' => 'From script to release',
        'items' => [
            'Plan an episode around recurring characters and write the action for each scene.',
            'Review takes, choose the shots and assemble the episode into one video.',
            'Track releases by channel and review the viewing and engagement metrics available from connected accounts.',
        ],
    ],
    'homepage.process' => [
        'title' => 'Share a video to TikTok',
        'items' => [
            'Connect your TikTok account and check the recipient shown in Poster.',
            'Choose and preview your own MP4. Edit the caption, select the audience and review interaction and disclosure settings.',
            'Give consent and send the video. Return to Poster for the publication result or open TikTok to view the clip.',
        ],
    ],
    'homepage.stack' => [
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
    'homepage.achievements' => [
        'title' => 'Professional History and Achievements',
        'items' => [
            'Development of scalable backend systems for business platforms',
            'Experience working with high-load and multi-region applications',
            'Long-term cooperation with clients under service contracts',
            'Development and support of production systems with continuous monitoring and optimization',
        ],
    ],
    'homepage.selected_clients' => [
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
    'homepage.official_status' => [
        'title' => 'Official Status',
        'items' => [
            'I am officially registered in the Russian Federation as an Individual Entrepreneur (sole proprietor / ИП).',
            'My business operates under the Russian Patent Taxation System, which is a simplified legal taxation regime for independent professionals and small businesses.',
            'I work independently without employees, which is common in software development and IT consulting.',
            'All development, communication, deployment, and technical support tasks are performed personally by me.',
        ],
    ],
    'homepage.contact' => [
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
    'homepage.footer' => [
        'legal_name' => 'Aleksandr Olegovich Spitsyn',
        'label' => 'Poster',
        'note' => 'Short video production and publishing.',
    ],
];
