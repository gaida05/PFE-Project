document.addEventListener('DOMContentLoaded', () => {
    const table = document.querySelector('[data-report-table]');
    if (!table) {
        return;
    }

    const endpoint = table.dataset.reportEndpoint || 'new_report_i18n.php';
    const filters = Array.from(document.querySelectorAll('.grid-filter'));

    const normalize = (value) => String(value || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

    const getCellValue = (row, column) => {
        const cell = row.querySelector(`[data-cell="${column}"]`);
        if (!cell) {
            return '';
        }
        return cell.dataset.raw || cell.textContent || '';
    };

    const buildUrl = (row, format, lang) => {
        const reportId = row.dataset.reportId;
        const sampleId = row.dataset.sampleId || '0';
        const url = new URL(endpoint, window.location.href);
        url.searchParams.set('id_rapport', reportId);
        if (sampleId !== '0') {
            url.searchParams.set('id_echantillon', sampleId);
        }
        url.searchParams.set('format', format || 'format_1');
        url.searchParams.set('lang', lang || 'fr');
        return url.toString();
    };

    const openReport = (row, format, lang) => {
        window.open(buildUrl(row, format, lang), '_blank', 'noopener');
    };

    const activateFormat = (row, format) => {
        row.dataset.selectedFormat = format;
        row.querySelectorAll('.js-format-btn').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.format === format);
        });

        const frLink = row.querySelector('.fr-link');
        if (frLink) {
            frLink.setAttribute('href', buildUrl(row, format, 'fr'));
        }
    };

    const applyFilters = () => {
        const bodyRows = Array.from(table.querySelectorAll('tbody tr'));

        bodyRows.forEach((row) => {
            let visible = true;

            filters.forEach((input) => {
                if (!visible) {
                    return;
                }

                const column = input.dataset.column;
                const filterValue = normalize(input.value);
                if (!filterValue) {
                    return;
                }

                const cellValue = normalize(getCellValue(row, column));
                if (!cellValue.includes(filterValue)) {
                    visible = false;
                }
            });

            row.style.display = visible ? '' : 'none';
        });
    };

    filters.forEach((input) => {
        input.addEventListener('input', applyFilters);
        input.addEventListener('change', applyFilters);
    });

    table.addEventListener('click', (event) => {
        const formatBtn = event.target.closest('.js-format-btn');
        if (formatBtn) {
            event.preventDefault();
            const row = formatBtn.closest('tr');
            const format = formatBtn.dataset.format || 'format_1';
            activateFormat(row, format);
            openReport(row, format, 'fr');
            return;
        }

        const langBtn = event.target.closest('.js-lang-btn');
        if (langBtn) {
            event.preventDefault();
            const row = langBtn.closest('tr');
            const format = row.dataset.selectedFormat || 'format_1';
            activateFormat(row, format);
            openReport(row, format, langBtn.dataset.lang || 'fr');
        }
    });

    table.querySelectorAll('tbody tr').forEach((row) => activateFormat(row, row.dataset.selectedFormat || 'format_1'));
});
