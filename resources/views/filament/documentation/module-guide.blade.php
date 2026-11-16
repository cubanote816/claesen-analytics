{{--
    "Documentation" → module user guide (App\Filament\Documentation\ModuleDocumentationPage, CLA-693).

    The guide body is Markdown rendered by App\Support\Documentation\ModuleGuide; UI names arrive
    as .doc-ui chips resolved from the same translation keys the screens use.

    Plain scoped CSS, never Tailwind utilities: the body is generated HTML, so Tailwind could
    never see its classes (CLAUDE.md rule 11). Colours come from the panel's CSS variables, so
    the guide follows the Claesen palette and the light/dark theme (DESIGN.md).
--}}
<x-filament-panels::page>
    <style>
        .module-guide { display: grid; gap: 2rem; grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 1024px) { .module-guide { grid-template-columns: 15rem minmax(0, 1fr); } }

        .module-guide__toc { font-size: .875rem; }
        @media (min-width: 1024px) { .module-guide__toc { position: sticky; top: 5rem; align-self: start; max-height: calc(100vh - 6rem); overflow-y: auto; } }
        .module-guide__toc-title { font-weight: 600; margin-bottom: .5rem; color: var(--gray-950); }
        .dark .module-guide__toc-title { color: #fff; }
        .module-guide__toc ol { list-style: none; margin: 0; padding: 0; }
        .module-guide__toc li { margin: .15rem 0; }
        .module-guide__toc li.is-sub { padding-left: .9rem; }
        .module-guide__toc a { color: var(--gray-600); text-decoration: none; }
        .dark .module-guide__toc a { color: var(--gray-400); }
        .module-guide__toc a:hover { color: var(--primary-600); }
        .dark .module-guide__toc a:hover { color: var(--primary-400); }

        .module-guide__body { color: var(--gray-700); line-height: 1.65; max-width: 52rem; }
        .dark .module-guide__body { color: var(--gray-300); }
        .module-guide__body :is(h2, h3, h4) { color: var(--gray-950); font-weight: 600; line-height: 1.3; scroll-margin-top: 5rem; }
        .dark .module-guide__body :is(h2, h3, h4) { color: #fff; }
        .module-guide__body h2 { font-size: 1.35rem; margin: 2.25rem 0 .75rem; padding-top: 1.25rem; border-top: 1px solid var(--gray-200); }
        .dark .module-guide__body h2 { border-top-color: var(--gray-800); }
        .module-guide__body h2:first-child { margin-top: 0; padding-top: 0; border-top: 0; }
        .module-guide__body h3 { font-size: 1.1rem; margin: 1.75rem 0 .5rem; }
        .module-guide__body h4 { font-size: 1rem; margin: 1.25rem 0 .4rem; }
        .module-guide__body p, .module-guide__body ul, .module-guide__body ol, .module-guide__body table, .module-guide__body blockquote { margin: 0 0 .9rem; }
        .module-guide__body ul { list-style: disc; padding-left: 1.4rem; }
        .module-guide__body ol { list-style: decimal; padding-left: 1.4rem; }
        .module-guide__body li { margin: .25rem 0; }
        .module-guide__body a { color: var(--primary-600); text-decoration: underline; text-underline-offset: 2px; }
        .dark .module-guide__body a { color: var(--primary-400); }
        .module-guide__body code { font-size: .85em; padding: .1rem .3rem; border-radius: .25rem; background: var(--gray-100); }
        .dark .module-guide__body code { background: var(--gray-800); }
        .module-guide__body blockquote { border-left: 3px solid var(--primary-500); padding: .5rem .9rem; background: var(--gray-50); border-radius: 0 .375rem .375rem 0; }
        .dark .module-guide__body blockquote { background: var(--gray-900); }
        .module-guide__body blockquote p:last-child { margin-bottom: 0; }
        .module-guide__body table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .module-guide__body :is(th, td) { text-align: left; vertical-align: top; padding: .45rem .6rem; border-bottom: 1px solid var(--gray-200); }
        .dark .module-guide__body :is(th, td) { border-bottom-color: var(--gray-800); }
        .module-guide__body th { font-weight: 600; color: var(--gray-950); }
        .dark .module-guide__body th { color: #fff; }

        .doc-ui { white-space: nowrap; font-weight: 600; }
        .doc-ui--action { display: inline-block; padding: 0 .45rem; border-radius: .375rem; font-size: .85em; line-height: 1.6; color: var(--primary-700); background: var(--primary-50); border: 1px solid var(--primary-200); }
        .dark .doc-ui--action { color: var(--primary-300); background: color-mix(in oklab, var(--primary-500) 15%, transparent); border-color: color-mix(in oklab, var(--primary-500) 35%, transparent); }
        .doc-ui--label { color: var(--gray-950); }
        .dark .doc-ui--label { color: #fff; }
        .doc-ui--nav { color: var(--gray-950); }
        .dark .doc-ui--nav { color: #fff; }
        .doc-ui--missing { color: var(--danger-600); text-decoration: wavy underline; }
    </style>

    <div class="module-guide" data-guide-locale="{{ $guide->locale }}">
        @if (count($guide->toc) > 0)
            <nav class="module-guide__toc" aria-label="{{ __('documentation.toc') }}">
                <p class="module-guide__toc-title">{{ __('documentation.toc') }}</p>
                <ol>
                    @foreach ($guide->toc as $entry)
                        <li @class(['is-sub' => $entry['level'] === 3])>
                            <a href="#{{ $entry['id'] }}">{{ $entry['text'] }}</a>
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        <article class="module-guide__body">
            {!! $guide->html !!}
        </article>
    </div>
</x-filament-panels::page>
