<?php

namespace Modules\Mailing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Mailing\Enums\TemplateCategory;
use Modules\Mailing\Models\EmailTemplate;

class Led2027HighConversionTemplatesSeeder extends Seeder
{
    private const VARIABLES = [
        ['key' => 'name', 'label' => 'Clubnaam', 'example' => 'KVC Westerlo'],
    ];

    public function run(): void
    {
        foreach ($this->templates() as $template) {
            EmailTemplate::updateOrCreate(
                ['name' => $template['name']],
                $template,
            );
        }
    }

    private function templates(): array
    {
        return [
            [
                'name'      => 'LED 2027 — Continuiteit sportverlichting',
                'subject'   => 'Voorkom problemen met uw sportverlichting richting 2027',
                'body'      => <<<'HTML'
<p>Beste sportpartner van {{ name }},</p>

<p>Voor veel sportclubs lijkt 2027 nog ver weg. Toch is dit precies het moment waarop een goede voorbereiding het verschil maakt.</p>

<p>Vanaf 24 februari 2027 verdwijnen bepaalde hogedruk-ontladingslampen en kwikhoudende lampen verder uit de markt. Voor clubs met oudere sportverlichting kan dat betekenen dat vervanglampen moeilijker verkrijgbaar worden en onderhoud steeds minder voorspelbaar wordt.</p>

<p><strong>De vraag is dus niet alleen: "moeten we ooit naar LED?" maar vooral: "wanneer plannen we dit best, zonder druk op budget, seizoen of werking?"</strong></p>

<p>Een tijdige overstap naar LED helpt uw club met:</p>

<ul>
    <li><strong>Meer zekerheid</strong> over beschikbaarheid en werking van de installatie.</li>
    <li><strong>Lagere energiekosten</strong>, vaak tot 60-70% minder verbruik, afhankelijk van de huidige installatie.</li>
    <li><strong>Minder onderhoud</strong> door de langere levensduur van LED-armaturen.</li>
    <li><strong>Beter veldlicht</strong> voor trainingen, wedstrijden en jeugdwerking.</li>
    <li><strong>Betere planning</strong> van budget, timing en uitvoering.</li>
</ul>

<p>Claesen Outdoor Lighting helpt sportclubs al meer dan 50 jaar met terreinverlichting, van studie en technische planning tot plaatsing, bekabeling, funderingen en oplevering.</p>

<p><strong>Wilt u weten wat de overstap voor {{ name }} concreet betekent?</strong></p>

<p>Antwoord eenvoudig op deze e-mail met <strong>LICHTSCAN</strong>. Dan bekijken wij vrijblijvend uw situatie en bezorgen we u een eerste praktische inschatting.</p>

<p>Met sportieve groeten,</p>

<p>
Team Claesen Outdoor Lighting<br>
<a href="https://www.claesen-verlichting.be">www.claesen-verlichting.be</a>
</p>
HTML,
                'category'            => TemplateCategory::COMMERCIAL,
                'preference_category' => 'offers',
                'variables'           => self::VARIABLES,
                'version'             => 1,
            ],

            [
                'name'      => 'LED 2027 — Besparing energiekosten',
                'subject'   => 'Hoeveel kan {{ name }} besparen met LED-sportverlichting?',
                'body'      => <<<'HTML'
<p>Beste sportpartner van {{ name }},</p>

<p>Veel sportclubs kijken vandaag kritisch naar hun vaste kosten. Terreinverlichting is daarbij vaak een stille grootverbruiker, zeker wanneer de installatie nog werkt met oudere lamptechnologie.</p>

<p>Een overstap naar LED kan voor een club financieel veel betekenen.</p>

<p><strong>Afhankelijk van de huidige installatie kan LED-sportverlichting tot 60-70% minder energie verbruiken.</strong> Daarnaast dalen meestal ook de onderhoudskosten, omdat LED-armaturen veel langer meegaan en minder interventies vragen.</p>

<p>Voor uw bestuur geeft dat meer grip op:</p>

<ul>
    <li>de energiefactuur;</li>
    <li>het onderhoudsbudget;</li>
    <li>de planning richting 2027;</li>
    <li>de kwaliteit van trainingen en wedstrijden;</li>
    <li>het gebruik van dimstanden voor jeugdwerking of trainingsmomenten.</li>
</ul>

<p>Wij merken dat clubs vooral geholpen zijn met duidelijke cijfers, geen vage beloftes. Daarom bekijken we graag samen:</p>

<ul>
    <li>welke installatie er vandaag staat;</li>
    <li>waar het grootste besparingspotentieel zit;</li>
    <li>welke LED-oplossing technisch past;</li>
    <li>welk budget realistisch is;</li>
    <li>hoe de uitvoering praktisch kan worden ingepland.</li>
</ul>

<p><strong>Wilt u een vrijblijvende besparingsinschatting voor {{ name }}?</strong></p>

<p>Antwoord op deze e-mail met <strong>BESPARING</strong> of bel ons via <strong>+32 (0)473 536 591</strong>. Wij nemen daarna kort contact op om de situatie van uw club te bekijken.</p>

<p>Met sportieve groeten,</p>

<p>
Team Claesen Outdoor Lighting<br>
<a href="https://www.claesen-verlichting.be">www.claesen-verlichting.be</a>
</p>
HTML,
                'category'            => TemplateCategory::COMMERCIAL,
                'preference_category' => 'offers',
                'variables'           => self::VARIABLES,
                'version'             => 1,
            ],

            [
                'name'      => 'LED 2027 — Beter veldlicht minder hinder',
                'subject'   => 'Beter veldlicht voor spelers, minder hinder voor de omgeving',
                'body'      => <<<'HTML'
<p>Beste sportpartner van {{ name }},</p>

<p>Goede sportverlichting gaat niet alleen over voldoende licht. Het gaat ook over comfort, veiligheid, gelijkmatige verlichting en respect voor de omgeving rond het sportterrein.</p>

<p>Bij oudere installaties zien we vaak dezelfde problemen:</p>

<ul>
    <li>donkere zones op het veld;</li>
    <li>ongelijke lichtverdeling;</li>
    <li>veel strooilicht richting buren;</li>
    <li>hoge energiekosten;</li>
    <li>onderhoud dat steeds moeilijker wordt richting 2027.</li>
</ul>

<p>Met moderne LED-sportverlichting kan uw club deze punten gericht aanpakken.</p>

<p><strong>Het resultaat: beter zicht voor spelers en scheidsrechters, meer controle voor de club en minder onnodige lichthinder voor de omgeving.</strong></p>

<p>Voor {{ name }} kan dat concreet betekenen:</p>

<ul>
    <li>uniformer veldlicht tijdens trainingen en wedstrijden;</li>
    <li>gerichte armaturen die strooilicht beperken;</li>
    <li>dimbare standen voor trainingen of jeugdwedstrijden;</li>
    <li>lagere energiekosten;</li>
    <li>een installatie die klaar is voor de toekomst.</li>
</ul>

<p>Claesen Outdoor Lighting voert elk project in eigen beheer uit: studie, technische dossiers, funderingen, bekabeling, montage, elektrische borden en oplevering. Zo behoudt uw club één aanspreekpunt van analyse tot uitvoering.</p>

<p><strong>Wilt u weten hoe uw huidige verlichting scoort?</strong></p>

<p>Antwoord eenvoudig met <strong>VELDLICHT</strong>. Wij bekijken vrijblijvend waar verbetering mogelijk is voor uw terrein, uw spelers en uw omgeving.</p>

<p>Met sportieve groeten,</p>

<p>
Team Claesen Outdoor Lighting<br>
<a href="https://www.claesen-verlichting.be">www.claesen-verlichting.be</a>
</p>
HTML,
                'category'            => TemplateCategory::COMMERCIAL,
                'preference_category' => 'offers',
                'variables'           => self::VARIABLES,
                'version'             => 1,
            ],
        ];
    }
}
