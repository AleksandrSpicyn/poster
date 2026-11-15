<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\HomepageData;
use Inertia\Response;

class HomeController
{
    /**
     * Публичный профиль
     *
     * HTML при обычной навигации; JSON ниже при корректной версии Inertia.
     *
     * @group Публичные страницы
     * @unauthenticated
     * @header Accept application/json
     * @header X-Inertia true
     * @header X-Inertia-Version {ASSET_VERSION}
     * @responseFile 200 docs/responses/home.json
     * @responseField component string component.
     * @responseField props object props.
     * @responseField props.errors object props.errors.
     * @responseField props.name string props.name.
     * @responseField props.quote object props.quote.
     * @responseField props.quote.message string props.quote.message.
     * @responseField props.quote.author string props.quote.author.
     * @responseField props.auth object props.auth.
     * @responseField props.auth.user string props.auth.user. Значение может быть null.
     * @responseField props.ziggy object props.ziggy.
     * @responseField props.ziggy.url string props.ziggy.url.
     * @responseField props.ziggy.port integer props.ziggy.port.
     * @responseField props.ziggy.defaults string[] props.ziggy.defaults.
     * @responseField props.ziggy.routes object props.ziggy.routes.
     * @responseField props.ziggy.location string props.ziggy.location.
     * @responseField props.appearanceValues object Значения оформления из enum.
     * @responseField props.appearanceValues.LIGHT string Светлое оформление.
     * @responseField props.appearanceValues.DARK string Тёмное оформление.
     * @responseField props.appearanceValues.SYSTEM string Системное оформление.
     * @responseField props.sidebarOpen boolean props.sidebarOpen.
     * @responseField props.homepage object props.homepage.
     * @responseField props.homepage.navigation object[] props.homepage.navigation.
     * @responseField props.homepage.navigation[].label string props.homepage.navigation[].label.
     * @responseField props.homepage.navigation[].href string props.homepage.navigation[].href.
     * @responseField props.homepage.hero object props.homepage.hero.
     * @responseField props.homepage.hero.kicker string props.homepage.hero.kicker.
     * @responseField props.homepage.hero.title string props.homepage.hero.title.
     * @responseField props.homepage.hero.summary string props.homepage.hero.summary.
     * @responseField props.homepage.hero.primary_cta object props.homepage.hero.primary_cta.
     * @responseField props.homepage.hero.primary_cta.label string props.homepage.hero.primary_cta.label.
     * @responseField props.homepage.hero.primary_cta.href string props.homepage.hero.primary_cta.href.
     * @responseField props.homepage.hero.secondary_cta object props.homepage.hero.secondary_cta.
     * @responseField props.homepage.hero.secondary_cta.label string props.homepage.hero.secondary_cta.label.
     * @responseField props.homepage.hero.secondary_cta.href string props.homepage.hero.secondary_cta.href.
     * @responseField props.homepage.hero.highlights string[] props.homepage.hero.highlights.
     * @responseField props.homepage.identity object props.homepage.identity.
     * @responseField props.homepage.identity.legal_name string props.homepage.identity.legal_name.
     * @responseField props.homepage.identity.status string props.homepage.identity.status.
     * @responseField props.homepage.identity.ogrnip string props.homepage.identity.ogrnip.
     * @responseField props.homepage.identity.inn string props.homepage.identity.inn.
     * @responseField props.homepage.identity.registered_at string props.homepage.identity.registered_at.
     * @responseField props.homepage.identity.primary_activity string props.homepage.identity.primary_activity.
     * @responseField props.homepage.identity.tax_regime string props.homepage.identity.tax_regime.
     * @responseField props.homepage.identity.work_model string props.homepage.identity.work_model.
     * @responseField props.homepage.identity.registration_authority string props.homepage.identity.registration_authority.
     * @responseField props.homepage.services object props.homepage.services.
     * @responseField props.homepage.services.title string props.homepage.services.title.
     * @responseField props.homepage.services.items string[] props.homepage.services.items.
     * @responseField props.homepage.process object props.homepage.process.
     * @responseField props.homepage.process.title string props.homepage.process.title.
     * @responseField props.homepage.process.items string[] props.homepage.process.items.
     * @responseField props.homepage.stack object props.homepage.stack.
     * @responseField props.homepage.stack.title string props.homepage.stack.title.
     * @responseField props.homepage.stack.items string[] props.homepage.stack.items.
     * @responseField props.homepage.achievements object props.homepage.achievements.
     * @responseField props.homepage.achievements.title string props.homepage.achievements.title.
     * @responseField props.homepage.achievements.items string[] props.homepage.achievements.items.
     * @responseField props.homepage.selected_clients object props.homepage.selected_clients.
     * @responseField props.homepage.selected_clients.title string props.homepage.selected_clients.title.
     * @responseField props.homepage.selected_clients.items object[] props.homepage.selected_clients.items.
     * @responseField props.homepage.selected_clients.items[].name string props.homepage.selected_clients.items[].name.
     * @responseField props.homepage.selected_clients.items[].href string props.homepage.selected_clients.items[].href.
     * @responseField props.homepage.selected_clients.items[].period string props.homepage.selected_clients.items[].period.
     * @responseField props.homepage.selected_clients.items[].summary string props.homepage.selected_clients.items[].summary.
     * @responseField props.homepage.official_status object props.homepage.official_status.
     * @responseField props.homepage.official_status.title string props.homepage.official_status.title.
     * @responseField props.homepage.official_status.items string[] props.homepage.official_status.items.
     * @responseField props.homepage.contact object props.homepage.contact.
     * @responseField props.homepage.contact.title string props.homepage.contact.title.
     * @responseField props.homepage.contact.email string props.homepage.contact.email.
     * @responseField props.homepage.contact.cta_label string props.homepage.contact.cta_label.
     * @responseField props.homepage.contact.cta_href string props.homepage.contact.cta_href.
     * @responseField props.homepage.contact.note string props.homepage.contact.note.
     * @responseField props.homepage.contact.links object[] props.homepage.contact.links.
     * @responseField props.homepage.contact.links[].label string props.homepage.contact.links[].label.
     * @responseField props.homepage.contact.links[].href string props.homepage.contact.links[].href.
     * @responseField props.homepage.footer object props.homepage.footer.
     * @responseField props.homepage.footer.legal_name string props.homepage.footer.legal_name.
     * @responseField props.homepage.footer.label string props.homepage.footer.label.
     * @responseField props.homepage.footer.note string props.homepage.footer.note.
     * @responseField url string url.
     * @responseField version string version.
     * @responseField sharedProps string[] sharedProps.
     */
    public function __invoke(): Response
    {
        return inertia('Welcome', [
            'homepage' => HomepageData::fromApprovedContent()->toArray(),
        ]);
    }
}
