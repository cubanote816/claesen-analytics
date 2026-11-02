{{--
    "Guides & Procedures" → ETS6 Project Archive (App\Filament\Guides\Ets6ProjectArchiveGuide).

    One markup, two skins, chosen by the page from the current panel:
    - bertels: the approved Electro Bertels mockup (dark, orange accent). Its light variant is
      derived from the same palette, because the bertels panel follows the system theme.
    - claesen: the same content in the Claesen identity (DESIGN.md): Industrial Night surfaces
      (#0b0b0f / #14141b / #252533), Claesen Cyan as the only interactive accent, Lime for the
      "in sync / enabled" states, Amber for warnings, and the logo's four colour dots on the
      section numbers. The admin panel defaults to dark; the light variant uses the panel grays.

    Plain CSS, never Tailwind utilities: the bertels panel has no ->viteTheme(), so utilities used
    only here would never be generated there (CLAUDE.md rule 11). Every rule is scoped to
    .ets6-guide, and only the current panel's skin is emitted, so neither panel ships the other
    company's palette.

    Light-mode accent text is a darker shade of the brand colour on purpose: #EE7203 and #00aeef
    on white are 3.0:1 and 2.6:1, under WCAG AA for body text. The brand colours stay on the
    non-text marks (dots, bars).

    The footer names no company (user decision): the guide is shared by both.
--}}
<x-filament-panels::page>
    <style id="ets6-guide-theme">
        @if ($skin === 'bertels')
        .ets6-guide--bertels {
            --g-surface: #ffffff;
            --g-border: #e4e4e7;
            --g-divider: #ececef;
            --g-text: #18181b;
            --g-body: #3f3f46;
            --g-muted: #5b5c63;
            --g-faint: #6e6f76;
            --g-accent: #ee7203;
            --g-accent-text: #a84f00;
            --g-accent-bg: #fdecdc;
            --g-ok: #2f9e5e;
            --g-ok-text: #1f7a45;
            --g-warn-bg: #fff6ec;
            --g-warn-border: #f6d2ad;
            --g-warn-text: #a84f00;
            --g-code-bg: #f4f4f5;
            --g-code-border: #e4e4e7;
            --g-code-text: #3f3f46;
        }

        /* The mockup puts #1a1b1e cards on a #111214 page. This panel's dark page is #282828 (its gray
           palette is generated from #121212), so the mockup's surfaces would sit DARKER than the page
           and invert the hierarchy. Same hues, lifted one step above the panel background instead. */
        .dark .ets6-guide--bertels {
            --g-surface: #313236;
            --g-border: #3d3e43;
            --g-divider: #393a3f;
            --g-text: #ececee;
            --g-body: #d0d1d6;
            --g-muted: #a6a7af;
            --g-faint: #9c9da5;
            --g-accent: #ee8a3c;
            --g-accent-text: #f5a764;
            --g-accent-bg: #3a2414;
            --g-ok: #5fbf8a;
            --g-ok-text: #7fd3a0;
            --g-warn-bg: #362a20;
            --g-warn-border: #5a3e26;
            --g-warn-text: #f5a764;
            --g-code-bg: #26272b;
            --g-code-border: #45464c;
            --g-code-text: #c9cad0;
        }

        @else
        .ets6-guide--claesen {
            --g-surface: #ffffff;
            --g-border: #e9ecef;
            --g-divider: #f1f3f5;
            --g-text: #14141b;
            --g-body: #343a40;
            --g-muted: #495057;
            --g-faint: #5f676f;
            --g-accent: #00aeef;
            --g-accent-text: #00759f;
            --g-accent-bg: #e3f6fd;
            --g-ok: #a5d610;
            --g-ok-text: #4f6a00;
            --g-warn-bg: #fffaeb;
            --g-warn-border: #fde68a;
            --g-warn-text: #7a5600;
            --g-code-bg: #f8f9fa;
            --g-code-border: #e9ecef;
            --g-code-text: #343a40;
        }

        .dark .ets6-guide--claesen {
            --g-surface: #14141b;
            --g-border: #252533;
            --g-divider: #1c1c28;
            --g-text: #f8f9fa;
            --g-body: #dee2e6;
            --g-muted: #adb5bd;
            --g-faint: #8d96a0;
            --g-accent: #00aeef;
            --g-accent-text: #33c3f5;
            --g-accent-bg: rgba(0, 174, 239, 0.12);
            --g-ok: #a5d610;
            --g-ok-text: #b8e43a;
            --g-warn-bg: rgba(252, 211, 77, 0.07);
            --g-warn-border: rgba(252, 211, 77, 0.32);
            --g-warn-text: #fcd34d;
            --g-code-bg: #0b0b0f;
            --g-code-border: #252533;
            --g-code-text: #dee2e6;
        }

        @endif

        .ets6-guide {
            --g-mono: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            --g-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 40px;
            color: var(--g-text);
            font-size: 14px;
            line-height: 1.55;
        }

        .ets6-guide b,
        .ets6-guide strong {
            font-weight: 600;
            color: var(--g-text);
        }

        .ets6-guide a {
            color: var(--g-accent-text);
            text-decoration: none;
        }

        .ets6-guide a:hover {
            text-decoration: underline;
        }

        .ets6-guide code {
            font-family: var(--g-mono);
            font-size: 12px;
            background: var(--g-code-bg);
            border: 1px solid var(--g-code-border);
            border-radius: 5px;
            padding: 1px 6px;
            color: var(--g-code-text);
        }

        .ets6-guide__card {
            background: var(--g-surface);
            border: 1px solid var(--g-border);
            border-radius: var(--g-radius);
        }

        .ets6-guide__card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 16px 20px;
            border-bottom: 1px solid var(--g-border);
        }

        .ets6-guide__card-title {
            font-weight: 600;
        }

        .ets6-guide__card-note {
            color: var(--g-muted);
            font-size: 13px;
            margin-top: 2px;
        }

        .ets6-guide__badge {
            font-family: var(--g-mono);
            font-size: 12px;
            color: var(--g-accent-text);
            background: var(--g-accent-bg);
            border-radius: 100px;
            padding: 3px 10px;
            white-space: nowrap;
        }

        .ets6-guide__settings {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 20px;
            padding: 20px;
            margin: 0;
        }

        .ets6-guide__settings div {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .ets6-guide__settings dt {
            font-size: 12px;
            color: var(--g-faint);
        }

        .ets6-guide__settings dd {
            margin: 0;
            font-weight: 500;
        }

        .ets6-guide__on {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ets6-guide__on::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--g-ok);
            flex-shrink: 0;
        }

        .ets6-guide__folders {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .ets6-guide__folders li {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 14px 20px;
        }

        .ets6-guide__folders li + li {
            border-top: 1px solid var(--g-divider);
        }

        .ets6-guide__folder-meta {
            display: flex;
            align-items: baseline;
            gap: 10px;
            flex-wrap: wrap;
        }

        .ets6-guide__folder-where {
            font-size: 12px;
            color: var(--g-faint);
        }

        .ets6-guide__folder-path {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .ets6-guide__folder-path code {
            user-select: all;
            overflow-wrap: anywhere;
            padding: 4px 8px;
        }

        .ets6-guide__copy {
            font-size: 12px;
            font-weight: 500;
            color: var(--g-accent-text);
            background: var(--g-accent-bg);
            border: 1px solid transparent;
            border-radius: 6px;
            padding: 4px 12px;
            cursor: pointer;
        }

        .ets6-guide__copy:hover {
            border-color: var(--g-accent);
        }

        .ets6-guide [x-cloak] {
            display: none !important;
        }

        .ets6-guide__section {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .ets6-guide__section-head {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ets6-guide__section-head h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--g-text);
        }

        .ets6-guide__num {
            font-family: var(--g-mono);
            font-size: 12px;
            color: var(--g-accent-text);
            background: var(--g-accent-bg);
            border-radius: 6px;
            padding: 2px 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        @if ($skin === 'claesen')
        /* Claesen: the logo's colour dots ("points of light") mark the sections, in the logo's order. */
        .ets6-guide--claesen .ets6-guide__num {
            color: var(--g-text);
            background: var(--g-surface);
            border: 1px solid var(--g-border);
        }

        .ets6-guide--claesen .ets6-guide__num::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--g-dot, #00aeef);
        }

        .ets6-guide--claesen .ets6-guide__section:nth-of-type(4n + 1) { --g-dot: #00aeef; }
        .ets6-guide--claesen .ets6-guide__section:nth-of-type(4n + 2) { --g-dot: #e6007e; }
        .ets6-guide--claesen .ets6-guide__section:nth-of-type(4n + 3) { --g-dot: #a5d610; }
        .ets6-guide--claesen .ets6-guide__section:nth-of-type(4n + 4) { --g-dot: #fcd34d; }
        @endif

        .ets6-guide__note {
            margin: 8px 0 0;
            color: var(--g-muted);
            max-width: 620px;
            text-wrap: pretty;
        }

        .ets6-guide__steps {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .ets6-guide__steps li {
            display: grid;
            grid-template-columns: 28px minmax(0, 1fr);
            gap: 14px;
            padding: 14px 20px;
            color: var(--g-body);
        }

        .ets6-guide__steps li + li {
            border-top: 1px solid var(--g-divider);
        }

        .ets6-guide__steps li > span:first-child {
            font-family: var(--g-mono);
            font-size: 12px;
            color: var(--g-faint);
            padding-top: 2px;
        }

        .ets6-guide__flow {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .ets6-guide__flow > div {
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .ets6-guide__flow-step {
            font-family: var(--g-mono);
            font-size: 12px;
            color: var(--g-accent-text);
        }

        .ets6-guide__flow-title {
            font-weight: 600;
        }

        .ets6-guide__flow-text {
            font-size: 13px;
            color: var(--g-muted);
        }

        .ets6-guide__tag {
            font-family: var(--g-mono);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--g-accent-text);
            background: var(--g-accent-bg);
            border-radius: 100px;
            padding: 2px 8px;
        }

        .ets6-guide__case-head {
            justify-content: flex-start;
        }

        .ets6-guide__list {
            list-style: none;
            margin: 0;
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            color: var(--g-body);
        }

        .ets6-guide__list li {
            display: flex;
            gap: 10px;
        }

        .ets6-guide__list li::before {
            content: "—";
            color: var(--g-accent);
            flex-shrink: 0;
        }

        .ets6-guide__callouts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 12px;
        }

        .ets6-guide__callout {
            padding: 16px;
        }

        .ets6-guide__callout p {
            margin: 6px 0 0;
            color: var(--g-body);
            font-size: 13px;
        }

        .ets6-guide__label {
            font-family: var(--g-mono);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--g-ok-text);
        }

        .ets6-guide__callout--warn {
            background: var(--g-warn-bg);
            border-color: var(--g-warn-border);
        }

        .ets6-guide__callout--warn .ets6-guide__label {
            color: var(--g-warn-text);
        }

        .ets6-guide__footer {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            padding-top: 20px;
            border-top: 1px solid var(--g-border);
            font-size: 12px;
            color: var(--g-faint);
        }
    </style>

    <div class="ets6-guide ets6-guide--{{ $skin }}" data-guide="ets6-project-archive">

        <div class="ets6-guide__card">
            <div class="ets6-guide__card-head">
                <div>
                    <div class="ets6-guide__card-title">Recommended settings</div>
                    <div class="ets6-guide__card-note">Apply the same values on every PC. Version 6.4 or later is needed for the automatic backup on close.</div>
                </div>
                <span class="ets6-guide__badge">ETS6 · 6.4 or later</span>
            </div>
            <dl class="ets6-guide__settings">
                <div><dt>ETS6 version</dt><dd>The same on every PC, 6.4 or later</dd></div>
                <div><dt>Archive folder</dt><dd>The Project Archive folder below</dd></div>
                <div><dt>Collaboration mode</dt><dd class="ets6-guide__on">Enabled</dd></div>
                <div><dt>Automatic backup on close</dt><dd class="ets6-guide__on">Enabled</dd></div>
                <div><dt>Automatically retained versions</dt><dd>10 per project (the maximum)</dd></div>
            </dl>
        </div>

        <div class="ets6-guide__card">
            <div class="ets6-guide__card-head">
                <div>
                    <div class="ets6-guide__card-title">Network folders</div>
                    <div class="ets6-guide__card-note">Copy these paths into ETS6. Use the network path, not a drive letter: drive letters differ per PC.</div>
                </div>
            </div>
            <ul class="ets6-guide__folders">
                @foreach ($folders as $folder)
                    <li x-data="{ copied: false, flash() { this.copied = true; setTimeout(() => this.copied = false, 1500) } }">
                        <div class="ets6-guide__folder-meta">
                            <span class="ets6-guide__card-title">{{ $folder['label'] }}</span>
                            <span class="ets6-guide__folder-where">{{ $folder['location'] }}</span>
                        </div>
                        <div class="ets6-guide__folder-path">
                            <code x-ref="path">{{ $folder['path'] }}</code>
                            <button
                                type="button"
                                class="ets6-guide__copy"
                                data-copy-path
                                x-on:click="
                                    if (window.isSecureContext && navigator.clipboard) {
                                        navigator.clipboard.writeText($refs.path.textContent.trim()).then(() => flash())
                                    } else {
                                        const range = document.createRange()
                                        range.selectNodeContents($refs.path)
                                        const selection = window.getSelection()
                                        selection.removeAllRanges()
                                        selection.addRange(range)
                                        if (document.execCommand('copy')) flash()
                                    }
                                "
                            >
                                <span x-show="! copied">Copy</span>
                                <span x-show="copied" x-cloak>Copied</span>
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        <section class="ets6-guide__section">
            <div>
                <div class="ets6-guide__section-head"><span class="ets6-guide__num">01</span><h2>One-time setup on every PC</h2></div>
                <p class="ets6-guide__note">Everyone must point to the same network folder. That is what turns the archive into a shared one instead of a personal backup.</p>
            </div>
            <ol class="ets6-guide__card ets6-guide__steps">
                <li><span>1</span><span>Open <b>Settings → Data Storage</b> in ETS6.</span></li>
                <li><span>2</span><span>Under <b>Project Archive</b>, set the directory to the Project Archive folder listed under <b>Network folders</b>. Every user needs read and write access to it.</span></li>
                <li><span>3</span><span>Enable <b>Collaboration mode</b>.</span></li>
                <li><span>4</span><span>Enable <b>Automatically backup archived projects on project close</b>. This option only appears once Collaboration mode is on.</span></li>
                <li><span>5</span><span>Set <b>Number of automatically retained project versions</b> to 10 on every PC, so everyone knows how many versions are kept.</span></li>
                @if (count($folders) > 1)
                    <li><span>6</span><span>Under <b>Linked Folders</b>, check that the Linked folder listed under <b>Network folders</b> is there. If it is missing, add it once with <b>Add Link</b>: ETS stores the link in the shared archive folder, so everyone using that archive sees it.</span></li>
                @endif
            </ol>
        </section>

        <section class="ets6-guide__section">
            <div>
                <div class="ets6-guide__section-head"><span class="ets6-guide__num">02</span><h2>Adding a project to the archive</h2></div>
                <p class="ets6-guide__note">Done once per project, by the person who has the most recent copy.</p>
            </div>
            <ol class="ets6-guide__card ets6-guide__steps">
                <li><span>1</span><span>Check that every device in the project is marked <b>operational</b>. ETS refuses to archive a project that has devices marked "broken".</span></li>
                <li><span>2</span><span>In the local project list, open the project's quick-actions menu (or right-click) and choose <b>Backup to Archive</b>.</span></li>
                <li><span>3</span><span>Pick the destination folder in the archive. You can create a new subfolder from the same dialog.</span></li>
                <li><span>4</span><span>Add a short comment (for example <code>initial version</code>) Tick <b>Keep local project</b> if you will continue working on it, and then <b>Lock the archive copy for others</b>: without it, the backup releases the lock.</span></li>
                <li><span>5</span><span>The project now appears under <b>Archived Projects</b> with a green checkmark: local copy and archive are in sync.</span></li>
            </ol>
        </section>

        <section class="ets6-guide__section">
            <div>
                <div class="ets6-guide__section-head"><span class="ets6-guide__num">03</span><h2>Daily workflow</h2></div>
                <p class="ets6-guide__note">Collaboration mode is one person at a time: ETS never merges edits made in parallel. With automatic backup enabled, ETS saves a new version when you close the project. The step you must not skip is the lock: ETS does not stop someone else from opening the same project.</p>
            </div>
            <div class="ets6-guide__flow">
                <div class="ets6-guide__card"><span class="ets6-guide__flow-step">STEP 1</span><div class="ets6-guide__flow-title">Restore + Lock</div><div class="ets6-guide__flow-text">Restore the latest version from the archive and accept the lock when ETS asks. <b>Restore overwrites your local copy</b>: local changes not in the archive are lost. If you already have the latest version locally, use <b>Lock</b> from the project's context menu.</div></div>
                <div class="ets6-guide__card"><span class="ets6-guide__flow-step">STEP 2</span><div class="ets6-guide__flow-title">Work</div><div class="ets6-guide__flow-text">Edit as usual. A blue spinner on the project tile means your local copy has changes that are not in the archive yet.</div></div>
                <div class="ets6-guide__card"><span class="ets6-guide__flow-step">STEP 3</span><div class="ets6-guide__flow-title">Close</div><div class="ets6-guide__flow-text">On close, ETS creates a new archive version, but only if your copy is newer and there is no conflict. Otherwise it asks you to resolve it.</div></div>
                <div class="ets6-guide__card"><span class="ets6-guide__flow-step">STEP 4</span><div class="ets6-guide__flow-title">Release</div><div class="ets6-guide__flow-text">Use <b>Release</b> on the tile when you finish, even if you continue tomorrow, so nobody is left waiting.</div></div>
            </div>
        </section>

        <section class="ets6-guide__section">
            <div>
                <div class="ets6-guide__section-head"><span class="ets6-guide__num">04</span><h2>When two copies have diverged</h2></div>
                <p class="ets6-guide__note"><b>ETS does not merge projects.</b> When two copies differ you choose one whole version; <b>Compare Versions</b> shows what is different so you can decide.</p>
            </div>
            <div class="ets6-guide__card">
                <div class="ets6-guide__card-head ets6-guide__case-head"><span class="ets6-guide__tag">Same project</span><span class="ets6-guide__card-title">Both copies come from the same archived project</span></div>
                <ul class="ets6-guide__list">
                    <li>Click the blue spinner on the project tile: the status dialog compares the dates and users of the last changes and offers <b>Backup to Archive</b>, <b>Restore</b>, <b>Compare Versions</b> and <b>Close</b>.</li>
                    <li>If only one side changed, back up or restore that side. Nothing is lost.</li>
                    <li>If both sides changed, open both and copy the missing parts by hand before the final backup. Whichever version you keep replaces the other completely.</li>
                </ul>
            </div>
            <div class="ets6-guide__card">
                <div class="ets6-guide__card-head ets6-guide__case-head"><span class="ets6-guide__tag">Separate project</span><span class="ets6-guide__card-title">The copy was imported as a copy or rebuilt</span></div>
                <ul class="ets6-guide__list">
                    <li>A project imported with <b>Import Copy</b> gets a new project ID, so for ETS it is a different project. Backing it up creates a new entry in the archive, not a new version.</li>
                    <li>Merge by hand: restore the main copy, open the other one next to it and copy over what is missing.</li>
                    <li>Back up the main copy and remove the duplicate from the archive so nobody restores it by mistake.</li>
                </ul>
            </div>
            <p class="ets6-guide__note">Before touching either copy, export a separate <code>.knxproj</code> of each one. Once a project is in the archive, everyone's first step is always <b>Restore</b>, never continuing on an old local copy.</p>
        </section>

        <section class="ets6-guide__section">
            <div class="ets6-guide__section-head"><span class="ets6-guide__num">05</span><h2>Good practices</h2></div>
            <div class="ets6-guide__callouts">
                <div class="ets6-guide__card ets6-guide__callout ets6-guide__callout--warn"><span class="ets6-guide__label">Watch out</span><p><b>The lock is a signal, not a block.</b> Another user can still copy a locked project or even force the lock to be released. Hover a locked project to see who locked it, when, and on which computer.</p></div>
                <div class="ets6-guide__card ets6-guide__callout ets6-guide__callout--warn"><span class="ets6-guide__label">Requirement</span><p><b>No "broken" devices.</b> Resolve or mark them operational, otherwise the project cannot be archived.</p></div>
                <div class="ets6-guide__card ets6-guide__callout ets6-guide__callout--warn"><span class="ets6-guide__label">Local projects stay local</span><p><b>The local project store is single-user.</b> Never move it to a network or cloud folder: share projects only through the archive, or by export and import.</p></div>
                <div class="ets6-guide__card ets6-guide__callout"><span class="ets6-guide__label">Status icons</span><p>A <b>green checkmark</b> means local and archive are in sync. A <b>blue spinner</b> means your local copy has changes to back up. A <b>black lock</b> means the archive copy is locked. The status dialog shows who changed it and when; <b>Compare Versions</b> shows what changed.</p></div>
                <div class="ets6-guide__card ets6-guide__callout"><span class="ets6-guide__label">Same ETS version</span><p><b>Keep every PC on the same ETS6 version.</b> A project saved with a newer version may not open in an older one.</p></div>
                <div class="ets6-guide__card ets6-guide__callout"><span class="ets6-guide__label">Important versions</span><p><b>Pin</b> a version before handover or commissioning. Pinned versions are not deleted when the retained-version limit is reached.</p></div>
                <div class="ets6-guide__card ets6-guide__callout"><span class="ets6-guide__label">Several archive locations</span><p><b>Linked Folders</b> (Settings → Data Storage → Add Link) add other archive folders, such as another network share, to the same Archived Projects view.</p></div>
                <div class="ets6-guide__card ets6-guide__callout"><span class="ets6-guide__label">After a restore</span><p>Some device plug-ins change project data right after a restore. If the status is no longer green, back up again to get back in sync.</p></div>
            </div>
        </section>

        <footer class="ets6-guide__footer">
            <span>Based on ETS6 6.4</span>
            <span>Source: <a href="https://support.knx.org/hc/en-us/articles/360019850580-Project-archive" target="_blank" rel="noopener noreferrer">KNX Support: Project archive</a> · <a href="https://support.knx.org/hc/en-us/articles/360020712719" target="_blank" rel="noopener noreferrer">Data Storage settings</a> · <a href="https://support.knx.org/hc/en-us/articles/9649204695058-Linked-folders" target="_blank" rel="noopener noreferrer">Linked folders</a></span>
        </footer>
    </div>
</x-filament-panels::page>
