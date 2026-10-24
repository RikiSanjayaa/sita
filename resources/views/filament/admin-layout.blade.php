{{--
    Admin panel layout rules that Filament cannot express through its PHP API:
    the dashboard "Perlu tindakan" list and the detail tables on the thesis project page.
    Colors come from Filament's own palette variables, which AdminPanelProvider pairs with DESIGN.md.
--}}
<style>
    /* Dashboard: Perlu tindakan */
    .sita-action-queue {
        display: grid;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .sita-action-queue-row {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        gap: 0.5rem 1rem;
        padding-block: 0.75rem;
        border-top: 1px solid var(--gray-200);
    }

    .dark .sita-action-queue-row {
        border-top-color: rgb(255 255 255 / 0.1);
    }

    .sita-action-queue-row:first-child {
        border-top: 0;
        padding-top: 0;
    }

    .sita-action-queue-row:last-child {
        padding-bottom: 0;
    }

    .sita-action-queue-icon {
        width: 1.25rem;
        height: 1.25rem;
        color: var(--gray-500);
    }

    .sita-action-queue-text {
        display: grid;
        gap: 0.125rem;
        min-width: 0;
    }

    .sita-action-queue-label {
        margin: 0;
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1.25rem;
        color: var(--gray-950);
    }

    .dark .sita-action-queue-label {
        color: #fff;
    }

    .sita-action-queue-description,
    .sita-action-queue-samples {
        margin: 0;
        font-size: 0.8125rem;
        line-height: 1.25rem;
        color: var(--gray-600);
    }

    .dark .sita-action-queue-description,
    .dark .sita-action-queue-samples {
        color: var(--gray-300);
    }

    .sita-action-queue-samples {
        color: var(--gray-500);
    }

    .dark .sita-action-queue-samples {
        color: var(--gray-400);
    }

    .sita-action-queue-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    @media (max-width: 40rem) {
        .sita-action-queue-row {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .sita-action-queue-actions {
            grid-column: 1 / -1;
            justify-content: space-between;
        }
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
</style>
