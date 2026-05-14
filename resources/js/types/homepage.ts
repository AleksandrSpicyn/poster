export interface HomepageLink {
    label: string;
    href: string;
}

export interface HomepageHero {
    kicker: string;
    title: string;
    summary: string;
    primary_cta: HomepageLink;
    secondary_cta: HomepageLink;
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

export interface HomepageSection {
    title: string;
    items: string[];
}

export interface HomepageClient {
    name: string;
    href: string;
    period: string;
    summary: string;
}

export interface HomepageClientSection {
    title: string;
    items: HomepageClient[];
}

export interface HomepageContact {
    title: string;
    email: string;
    cta_label: string;
    cta_href: string;
    note: string;
    links: HomepageLink[];
}

export interface HomepageFooter {
    legal_name: string;
    label: string;
    note: string;
}

export interface HomepageProps {
    navigation: HomepageLink[];
    hero: HomepageHero;
    identity: HomepageIdentity;
    services: HomepageSection;
    process: HomepageSection;
    stack: HomepageSection;
    achievements: HomepageSection;
    selected_clients: HomepageClientSection;
    official_status: HomepageSection;
    contact: HomepageContact;
    footer: HomepageFooter;
}
