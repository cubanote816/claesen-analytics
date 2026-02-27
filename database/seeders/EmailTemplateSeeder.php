<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => '1. Eerste Kennismaking (Audit)',
                'subject' => 'Vrijblijvende Lichtaudit voor {{ name }}',
                'body' => '
                    <p>Beste sportvrienden van <strong>{{ name }}</strong>,</p>
                    <p>Wij zijn Claesen Verlichting, specialisten in professionele en duurzame LED-sportverlichting.</p>
                    <p>Als gepassioneerde voetbalclub in de regio <strong>{{ regio }}</strong>, begrijpen we dat kwalitatieve veldverlichting cruciaal is voor zowel het spelcomfort van jullie spelers als de energierekening van de club.</p>
                    <p>Wisten jullie dat de overstap naar onze moderne LED-systemen de energiekosten tot wel 70% kan verlagen en de lichtsterkte (lux-waarde) op het veld spectaculair kan verhogen?</p>
                    <p><strong>Zouden jullie openstaan voor een gratis en vrijblijvende lichtaudit van jullie huidige installatie?</strong></p>
                    <p>Wij komen graag langs om de velden te bekijken en een berekening te maken van wat Claesen Verlichting voor jullie kan betekenen.</p>
                    <p><br></p>
                    <p>Met sportieve groeten,</p>
                    <p><strong>Het Claesen Team</strong><br><a href="https://www.claesen.be" target="_blank">www.claesen.be</a></p>
                ',
            ],
            [
                'name' => '2. Subsidies & Premies (Informatief)',
                'subject' => 'Belangrijke info: Subsidies voor LED-verlichting bij {{ name }}',
                'body' => '
                    <p>Beste bestuurslid van <strong>{{ name }}</strong>,</p>
                    <p>De energieprijzen vormen een zware last voor veel sportclubs in de regio <strong>{{ regio }}</strong>. Bij Claesen Verlichting helpen we clubs die omslag te maken.</p>
                    <p>Graag willen we jullie informeren dat er momenteel <strong>belangrijke subsidies en premies</strong> beschikbaar zijn via de overheid ter ondersteuning van investeringen in energiebesparende LED-sportverlichting.</p>
                    <p>Dit betekent dat de investering in een modern, energiezuinig lichtsysteem voor {{ name }} aanzienlijk goedkoper kan uitvallen dan jullie wellicht denken. Ons team begeleidt clubs niet alleen bij de technische installatie, maar treedt ook op als partner bij het aanvragen van deze subsidies.</p>
                    <p>Mogen we dit in een kort (telefonisch) gesprek aan jullie voorleggen?</p>
                    <p><br></p>
                    <p>Met sportieve groeten, en succes met de rest van het seizoen,</p>
                    <p><strong>Het Energie-team van Claesen Verlichting</strong><br><a href="https://www.claesen.be" target="_blank">www.claesen.be</a></p>
                ',
            ],
            [
                'name' => '3. Late Seizoenscheck (Korte reminder)',
                'subject' => 'Is de veldverlichting van {{ name }} klaar voor de donkere wintermaanden?',
                'body' => '
                    <p>Hallo team van <strong>{{ name }}</strong>,</p>
                    <p>De avonden vallen inmiddels vroeg en een optimale veldverlichting is belangrijker dan ooit om veilig te kunnen trainen in <strong>{{ regio }}</strong>.</p>
                    <p>Claesen Verlichting ontwerpt en installeert systemen die voldoen aan de strengste KBBB (Voetbal Vlaanderen) normeringen. Mochten jullie dit seizoen nog tegen problemen aanlopen met verouderde gasontladingslampen of hoge energiefacturen, aarzel dan niet om ons te contacteren.</p>
                    <p>Wij staan klaar met snel advies en een transparante offerte.</p>
                    <p><br></p>
                    <p>Sportieve groeten,</p>
                    <p><strong>Claesen Verlichting</strong><br><a href="https://www.claesen.be" target="_blank">www.claesen.be</a></p>
                ',
            ]
        ];

        foreach ($templates as $template) {
            EmailTemplate::firstOrCreate(
                ['name' => $template['name']],
                $template
            );
        }
    }
}
