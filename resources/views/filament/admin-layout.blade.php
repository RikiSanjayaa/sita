{{--
    Admin panel layout rules that Filament cannot express through its PHP API:
    the dashboard "Perlu tindakan" list and the detail tables on the thesis project page.
    Colors come from Filament's own palette variables, which AdminPanelProvider pairs with DESIGN.md.
--}}
<style>
    /* Dashboard widgets share the panel's neutral ramp and semantic token pairs. */
    .sita-admin-actions,
    .sita-admin-agenda,
    .sita-admin-overview {
        --sita-paper: #fff;
        --sita-ink: var(--gray-950);
        --sita-secondary: var(--gray-600);
        --sita-rule: var(--gray-200);
        min-width: 0;
        color: var(--sita-ink);
    }

    .dark .sita-admin-actions,
    .dark .sita-admin-agenda,
    .dark .sita-admin-overview {
        --sita-paper: var(--gray-900);
        --sita-ink: var(--gray-50);
        --sita-secondary: var(--gray-300);
        --sita-rule: var(--gray-800);
    }

    .sita-admin-actions [data-tone],
    .sita-admin-overview [data-tone] {
        --sita-tone: var(--sita-secondary);
        --sita-tone-muted: var(--sita-rule);
        --sita-tone-ink: var(--sita-ink);
    }

    @foreach (['primary', 'success', 'warning', 'danger', 'info'] as $tone)
        .sita-admin-actions [data-tone="{{ $tone }}"],
        .sita-admin-overview [data-tone="{{ $tone }}"] {
            --sita-tone: var(--sita-{{ $tone }});
            --sita-tone-muted: var(--sita-{{ $tone }}-muted);
            --sita-tone-ink: var(--sita-{{ $tone }}-muted-foreground);
        }
    @endforeach

    .sita-dashboard-heading {
        display: grid;
        gap: 0.25rem;
        margin-bottom: 1rem;
    }

    .sita-dashboard-heading h2 {
        font-size: 1.125rem;
        line-height: 1.5;
        font-weight: 600;
        letter-spacing: -0.025em;
    }

    .sita-dashboard-heading p,
    .sita-action-queue-description,
    .sita-action-queue-samples,
    .sita-agenda-empty p {
        font-size: 0.875rem;
        line-height: 1.5;
        color: var(--sita-secondary);
        text-wrap: pretty;
    }

    .sita-action-queue {
        display: grid;
        gap: 1rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .sita-action-queue-row {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
        min-width: 0;
        padding: 1rem;
        border: 1px solid var(--sita-rule);
        border-radius: 0.75rem;
        background-color: var(--sita-paper);
        overflow-wrap: anywhere;
    }

    .sita-action-card-header {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        gap: 0.75rem;
        width: 100%;
    }

    .sita-action-card-header h3 {
        font-size: 0.875rem;
        line-height: 1.5;
        font-weight: 600;
        text-wrap: balance;
    }

    .sita-action-card-icon {
        display: grid;
        place-items: center;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.5rem;
        background-color: var(--sita-tone-muted);
        color: var(--sita-tone-ink);
    }

    .sita-action-card-icon svg { width: 1.25rem; height: 1.25rem; }

    .sita-action-card-count {
        font-size: 1.5rem;
        line-height: 1.25;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: var(--sita-tone-ink);
    }

    .sita-action-card-featured {
        gap: 1rem;
        padding: 1.5rem;
        border-color: var(--sita-primary);
    }

    .sita-action-card-featured h3 { font-size: 1.125rem; }
    .sita-action-card-featured .sita-action-card-count { font-size: 2rem; }
    .sita-action-queue-samples { font-weight: 500; }
    .sita-action-queue-row > .fi-btn { margin-top: auto; min-height: 2.75rem; }

    .sita-dashboard-clear {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.5rem;
        border-radius: 0.75rem;
        background: var(--sita-primary-muted);
        color: var(--sita-primary-muted-foreground);
    }

    .sita-dashboard-clear > svg { width: 2rem; height: 2rem; flex-shrink: 0; }
    .sita-dashboard-clear h3 { font-size: 1rem; font-weight: 600; }
    .sita-dashboard-clear p { margin-top: 0.25rem; font-size: 0.875rem; }

    .sita-admin-overview {
        border: 1px solid var(--sita-rule);
        border-radius: 0.75rem;
        background: var(--sita-paper);
        overflow: hidden;
    }

    .sita-admin-overview .fi-sc { gap: 0; }
    .sita-admin-overview .fi-section-content { padding: 0; }
    .sita-admin-overview .fi-sc-has-gap { gap: 0; }
    .sita-admin-stat.fi-wi-stats-overview-stat {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        grid-template-areas: "icon value" "label label" "description description";
        align-content: start;
        gap: 0.5rem 0.75rem;
        height: 100%;
        padding: 1.25rem;
        border-radius: 0;
        box-shadow: none;
        background: transparent;
    }

    .sita-admin-stat .fi-wi-stats-overview-stat-content,
    .sita-admin-stat .fi-wi-stats-overview-stat-label-ctn { display: contents; }
    .sita-admin-stat .fi-wi-stats-overview-stat-label-ctn > svg {
        grid-area: icon;
        width: 2.5rem;
        height: 2.5rem;
        padding: 0.625rem;
        border-radius: 0.5rem;
        background: var(--sita-tone-muted);
        color: var(--sita-tone-ink);
    }
    .sita-admin-stat .fi-wi-stats-overview-stat-label {
        grid-area: label;
        color: var(--sita-ink);
        font-size: 0.875rem;
        line-height: 1.5;
        font-weight: 600;
    }
    .sita-admin-stat .fi-wi-stats-overview-stat-value {
        grid-area: value;
        align-self: center;
        text-align: right;
        color: var(--sita-tone-ink);
        font-size: 1.75rem;
        line-height: 1.25;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        overflow-wrap: anywhere;
    }
    .sita-admin-stat .fi-wi-stats-overview-stat-description {
        grid-area: description;
        color: var(--sita-secondary);
        font-size: 0.75rem;
        line-height: 1.5;
    }
    .sita-admin-overview .fi-grid-col:not(:last-child) > .fi-sc-component > .sita-admin-stat { border-inline-end: 1px solid var(--sita-rule); }

    .sita-agenda-day + .sita-agenda-day { margin-top: 1.5rem; }
    .sita-agenda-day h3 { margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; }
    .sita-agenda-day ol { list-style: none; margin: 0; padding: 0; }
    .sita-agenda-day li + li { border-top: 1px solid var(--sita-rule); }
    .sita-agenda-link {
        display: grid;
        grid-template-columns: 4rem minmax(0, 1fr) auto;
        align-items: start;
        gap: 0.75rem;
        padding-block: 1rem;
        min-height: 2.75rem;
        border-radius: 0.25rem;
        overflow-wrap: anywhere;
    }
    .sita-agenda-link time {
        display: grid;
        gap: 0.25rem;
        color: var(--sita-primary);
        font-weight: 600;
        font-size: 1rem;
        font-variant-numeric: tabular-nums;
    }
    .sita-agenda-link time span { font-size: 0.75rem; font-weight: 400; }
    .sita-agenda-person { display: grid; gap: 0.25rem; font-size: 0.875rem; line-height: 1.5; }
    .sita-agenda-person > strong { font-weight: 600; }
    .sita-agenda-person > span { color: var(--sita-secondary); font-size: 0.75rem; }
    .sita-agenda-link > svg { width: 1rem; height: 1rem; margin-top: 0.25rem; color: var(--sita-secondary); }
    .sita-agenda-link:hover { background: var(--sita-primary-muted); }
    .sita-agenda-link:focus-visible { outline: 2px solid var(--sita-primary); outline-offset: 3px; }
    .sita-agenda-empty { display: flex; gap: 0.75rem; padding-block: 1rem; }
    .sita-agenda-empty > svg { width: 1.5rem; height: 1.5rem; color: var(--sita-secondary); flex-shrink: 0; }
    .sita-agenda-empty h3 { font-size: 0.875rem; font-weight: 500; }

    @media (min-width: 48rem) {
        .sita-action-queue { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .sita-action-card-featured { grid-column: 1 / -1; }
    }

    @media (max-width: 47.999rem) {
        .sita-admin-stat.fi-wi-stats-overview-stat { padding: 1rem; }
        .sita-admin-stat .fi-wi-stats-overview-stat-value { font-size: 1.5rem; }
        .sita-admin-overview .fi-grid-col:nth-child(2) > .fi-sc-component > .sita-admin-stat { border-inline-end: 0; }
        .sita-admin-overview .fi-grid-col:nth-child(-n+2) > .fi-sc-component > .sita-admin-stat { border-bottom: 1px solid var(--sita-rule); }
    }

    /*
     * Thesis project detail tables. Filament stacks repeatable tables as cards below 36rem
     * of container width and as a squeezed, non-scrolling table above it. From 36rem we let
     * the table keep its natural column widths and scroll sideways instead.
     */
    .sita-detail-table {
        overflow-x: auto;
    }

    @container (min-width: 36rem) {
        .sita-detail-table > table {
            width: max-content;
            min-width: 100%;
        }

        .sita-detail-table > table > thead > tr > th {
            white-space: nowrap;
        }

        .sita-detail-table > table > tbody > tr > td {
            min-width: 7rem;
            max-width: 22rem;
            overflow-wrap: break-word;
        }

        .sita-detail-table .fi-badge {
            white-space: nowrap;
        }

        /* Keep the action column (Unduh) visible at the right edge while scrolling. */
        .sita-detail-table-sticky-end > table > thead > tr > th:last-child,
        .sita-detail-table-sticky-end > table > tbody > tr > td:last-child {
            position: sticky;
            inset-inline-end: 0;
            z-index: 1;
            box-shadow: -1px 0 0 var(--gray-200);
        }

        .sita-detail-table-sticky-end > table > thead > tr > th:last-child {
            background-color: var(--gray-50);
        }

        .sita-detail-table-sticky-end > table > tbody > tr > td:last-child {
            background-color: #fff;
        }

        .dark .sita-detail-table-sticky-end > table > thead > tr > th:last-child,
        .dark .sita-detail-table-sticky-end > table > tbody > tr > td:last-child {
            box-shadow: -1px 0 0 rgb(255 255 255 / 0.1);
        }

        .dark .sita-detail-table-sticky-end > table > thead > tr > th:last-child {
            background-color: color-mix(in oklab, var(--gray-900), #fff 5%);
        }

        .dark .sita-detail-table-sticky-end > table > tbody > tr > td:last-child {
            background-color: var(--gray-900);
        }
    }
    .sita-revision-logs,
    .sita-revision-log {
        display: grid;
        gap: 1rem;
        min-width: 0;
        overflow-wrap: anywhere;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .sita-revision-logs {
        gap: 1.5rem;
    }

    .sita-revision-log h3,
    .sita-revision-log h4,
    .sita-revision-log h5 {
        margin: 0;
        font-weight: 600;
    }

    .sita-revision-log h3 {
        font-size: 1rem;
    }

    .sita-revision-group,
    .sita-revision-item {
        display: grid;
        gap: 0.75rem;
        min-width: 0;
        border-top: 1px solid var(--gray-200);
        padding-top: 0.75rem;
    }

    .dark .sita-revision-group,
    .dark .sita-revision-item {
        border-color: var(--gray-800);
    }

    .sita-revision-state {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        min-width: 0;
    }

    .sita-revision-log ol {
        display: grid;
        gap: 0.75rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .sita-revision-meta {
        color: var(--gray-600);
        font-size: 0.75rem;
    }

    .dark .sita-revision-meta {
        color: var(--gray-300);
    }

    .sita-revision-copy {
        white-space: pre-line;
    }

    .sita-revision-log dt,
    .sita-revision-log dd {
        display: inline;
    }

    .sita-revision-log a {
        display: inline-block;
        min-height: 2.75rem;
        max-width: 100%;
        padding-block: 0.5rem;
        color: var(--primary-600);
        text-decoration: underline;
        text-underline-offset: 0.2em;
    }

    .dark .sita-revision-log a {
        color: var(--primary-400);
    }
</style>
