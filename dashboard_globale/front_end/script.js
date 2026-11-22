$(function () {
    initTabs();
    initFilters();
    bindContextActions();
    loadDashboard();
});

let dateDebutFilter, dateFinFilter, yearFilter, monthFilter;
let currentFilters = {};
let originalData = null;
let filterCollapsed = false;
let currentView = 'global';
let drilldown = { secteur: null, statut: null, conformite: null, rapport_livraison: null, alerte_gravite: null };

const MONTHS = [
    { id: 1, name: "Janvier" }, { id: 2, name: "Février" }, { id: 3, name: "Mars" }, { id: 4, name: "Avril" },
    { id: 5, name: "Mai" }, { id: 6, name: "Juin" }, { id: 7, name: "Juillet" }, { id: 8, name: "Août" },
    { id: 9, name: "Septembre" }, { id: 10, name: "Octobre" }, { id: 11, name: "Novembre" }, { id: 12, name: "Décembre" }
];

const DASHBOARD_COLORS = {
    green: "#10b981",
    red: "#ef4444",
    blue: "#2563eb",
    orange: "#f59e0b",
    teal: "#06b6d4",
    gray: "#64748b",
    purple: "#8b5cf6"
};


function initFilters() {
    const now = new Date();
    const currentYear = now.getFullYear();
    dateDebutFilter = $("#dashboardDateDebut").dxDateBox({
        type: "date",
        displayFormat: "yyyy-MM-dd",
        showClearButton: true,
        useMaskBehavior: true,
        width: "100%",
        stylingMode: "outlined"
    }).dxDateBox("instance");
    dateFinFilter = $("#dashboardDateFin").dxDateBox({
        type: "date",
        displayFormat: "yyyy-MM-dd",
        showClearButton: true,
        useMaskBehavior: true,
        width: "100%",
        stylingMode: "outlined"
    }).dxDateBox("instance");
    yearFilter = $("#dashboardYearFilter").dxSelectBox({
        dataSource: [currentYear - 3, currentYear - 2, currentYear - 1, currentYear, currentYear + 1],
        showClearButton: true,
        width: "100%",
        stylingMode: "outlined",
        placeholder: "Sélectionner une année"
    }).dxSelectBox("instance");
    monthFilter = $("#dashboardMonthFilter").dxSelectBox({
        dataSource: MONTHS,
        valueExpr: "id",
        displayExpr: "name",
        showClearButton: true,
        width: "100%",
        stylingMode: "outlined",
        placeholder: "Sélectionner un mois"
    }).dxSelectBox("instance");
    $("#btnDashboardToday").dxButton({
        text: "Aujourd'hui",
        icon: "event",
        onClick: function () {
            const d = new Date();
            setDateRange(d, d);
            yearFilter.option('value', null);
            monthFilter.option('value', null);
            applyFilters();
        }
    });
    $("#btnDashboardThisWeek").dxButton({
        text: "Cette semaine",
        icon: "calendar",
        onClick: function () {
            const today = new Date();
            const day = today.getDay() || 7;
            const start = new Date(today);
            start.setDate(today.getDate() - day + 1);
            const end = new Date(start);
            end.setDate(start.getDate() + 6);
            setDateRange(start, end);
            yearFilter.option('value', null);
            monthFilter.option('value', null);
            applyFilters();
        }
    });
    $("#btnDashboardThisMonth").dxButton({
        text: "Ce mois",
        icon: "calendar",
        onClick: function () {
            const today = new Date();
            setDateRange(
                new Date(today.getFullYear(), today.getMonth(), 1),
                new Date(today.getFullYear(), today.getMonth() + 1, 0)
            );
            yearFilter.option('value', null);
            monthFilter.option('value', null);
            applyFilters();
        }
    });
    $("#btnDashboardThisYear").dxButton({
        text: "Cette année",
        icon: "clock",
        onClick: function () {
            const today = new Date();
            setDateRange(
                new Date(today.getFullYear(), 0, 1),
                new Date(today.getFullYear(), 11, 31)
            );
            yearFilter.option('value', null);
            monthFilter.option('value', null);
            applyFilters();
        }
    });
    $("#btnDashboardFiltrer").dxButton({
        text: "Appliquer",
        icon: "find",
        type: "default",
        onClick: applyFilters
    });
    $("#btnDashboardReset").dxButton({
        text: "Réinitialiser",
        icon: "refresh",
        onClick: resetFilters
    });
    $("#btnToggleFilterBar").dxButton({
        text: "−",
        onClick: function (e) {
            filterCollapsed = !filterCollapsed;
            updateFilterBarState(e.component);
        }
    });
    renderActiveFilterBadges();
    updateFilterBarState();
}

function updateFilterBarState(buttonInstance) {
    $('#filterShell').toggleClass('filter-bar-hidden', filterCollapsed);
    const btn = buttonInstance || DevExpress.ui.dxButton.getInstance($('#btnToggleFilterBar'));
    if (btn) btn.option('text', filterCollapsed ? '+' : '−');
}

function setDateRange(startDate, endDate) {
    dateDebutFilter.option("value", startDate);
    dateFinFilter.option("value", endDate);
}

function parseDateSafe(value) {
    if (!value) return null;
    const d = new Date(value);
    return isNaN(d.getTime()) ? null : d;
}
function formatBadgeDate(value) {
    return value ? DevExpress.localization.formatDate(value, "dd/MM/yyyy") : "";
}
function getMonthName(monthId) {
    const found = MONTHS.find(m => m.id === Number(monthId));
    return found ? found.name : "";
}
function csvEscape(value) {
    if (value === null || value === undefined) return '""';
    return '"' + String(value).replace(/"/g, '""') + '"';
}
function removeSingleFilter(filterKey) {
    if (filterKey === 'date_debut') dateDebutFilter.option('value', null);
    if (filterKey === 'date_fin') dateFinFilter.option('value', null);
    if (filterKey === 'annee') yearFilter.option('value', null);
    if (filterKey === 'mois') monthFilter.option('value', null);
    applyFilters();
}
window.removeSingleFilter = removeSingleFilter;
function renderActiveFilterBadges() {
    const $badges = $('#activeFilterBadges');
    const badges = [];
    if (currentFilters.date_debut) badges.push({ text: 'Début : ' + formatBadgeDate(currentFilters.date_debut), key: 'date_debut', color: 'blue' });
    if (currentFilters.date_fin) badges.push({ text: 'Fin : ' + formatBadgeDate(currentFilters.date_fin), key: 'date_fin', color: 'green' });
    if (currentFilters.annee) badges.push({ text: 'Année : ' + currentFilters.annee, key: 'annee', color: 'gray' });
    if (currentFilters.mois) badges.push({ text: 'Mois : ' + getMonthName(currentFilters.mois), key: 'mois', color: 'orange' });
    $badges.html(
        badges.map(b => `
            <div class="filter-badge ${b.color}">
                <span>${b.text}</span>
                <button type="button" class="badge-reset" onclick="removeSingleFilter('${b.key}')">×</button>
            </div>
        `).join('')
    );
}
function isDateInFilterRange(dateValue) {
    const itemDate = parseDateSafe(dateValue);
    if (!itemDate) return false;
    if (currentFilters.date_debut) {
        const debut = new Date(currentFilters.date_debut);
        debut.setHours(0, 0, 0, 0);
        if (itemDate < debut) return false;
    }
    if (currentFilters.date_fin) {
        const fin = new Date(currentFilters.date_fin);
        fin.setHours(23, 59, 59, 999);
        if (itemDate > fin) return false;
    }

    return true;
}
function applyFilters() {
    currentFilters = {
        date_debut: dateDebutFilter.option('value'),
        date_fin: dateFinFilter.option('value'),
        annee: yearFilter.option('value'),
        mois: monthFilter.option('value')
    };
    renderActiveFilterBadges();
    if (originalData) filterDashboardData(originalData);
    else loadDashboard();
}
function hasAnyFilter() {
    return !!(currentFilters.date_debut || currentFilters.date_fin || currentFilters.annee || currentFilters.mois);
}
function hasAnyDrilldown() {
    return !!(drilldown.secteur || drilldown.statut || drilldown.conformite || drilldown.rapport_livraison || drilldown.alerte_gravite);
}
function calculateDelaiMinutes(startValue, endValue) {
    const debut = parseDateSafe(startValue);
    const fin = parseDateSafe(endValue);
    if (!debut || !fin || fin < debut) return null;
    return Math.round((fin.getTime() - debut.getTime()) / 60000);
}
function numericOrNull(value) {
    if (value === null || value === undefined || value === '') return null;
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
}

function calculateDelaiDemandeMinutes(demande) {
    if (!demande) return null;

    const hasDwDelayColumns = Object.prototype.hasOwnProperty.call(demande, 'delai_total_minutes')
        || Object.prototype.hasOwnProperty.call(demande, 'delai_total_jours');

    const minutesFromDw = numericOrNull(demande.delai_total_minutes);
    if (minutesFromDw !== null && minutesFromDw >= 0) {
        return Math.round(minutesFromDw);
    }

    const joursFromDw = numericOrNull(demande.delai_total_jours);
    if (joursFromDw !== null && joursFromDw >= 0) {
        return Math.round(joursFromDw * 1440);
    }

    // If the API provides DW delay columns but the value is NULL, the demande must be ignored
    // in delay averages. Do not silently convert NULL to 0 and do not recalculate from raw dates.
    if (hasDwDelayColumns) return null;

    // Legacy fallback only, for older API payloads without DW delay columns.
    return calculateDelaiMinutes(demande.date_demande, demande.date_cloture);
}

function calculateDelaiFicheMinutes(dateAffectation, dateFin) {
    return calculateDelaiMinutes(dateAffectation, dateFin);
}
function minutesToDecimalHours(minutes) {
    return minutes === null || minutes === undefined || isNaN(minutes) ? 0 : Number((minutes / 60).toFixed(2));
}
function formatMinutesToDayHourMinute(minutes) {
    if (minutes === null || minutes === undefined || isNaN(minutes)) return '-';
    const total = Math.round(minutes);
    const jours = Math.floor(total / 1440);
    const reste = total % 1440;
    const heures = Math.floor(reste / 60);
    const mins = reste % 60;
    return jours + 'j ' + heures + 'h ' + mins + 'min';
}
function formatMinutesToKpi(minutes) {
    return formatMinutesToDayHourMinute(minutes).replace('-', '0j 0h 0min');
}
function normalizeStatut(value) {
    if (value === null || value === undefined || String(value).trim() === '') return 'pas evalué';
    const raw = String(value).trim().toLowerCase().replace(',', '.');
    if (['pas evalué', 'non evalué', 'non évalué', 'pas évalué'].includes(raw)) return 'pas evalué';
    if (['terminé', 'termine', 'cloturé', 'cloture', 'clôturé', 'clôture'].includes(raw)) return 'terminé';
    if (raw === 'en cours') return 'en cours';
    const numericValue = parseFloat(raw.replace('%', ''));
    if (!isNaN(numericValue)) return numericValue >= 100 ? 'terminé' : 'en cours';

    return raw;
}
function recalculateStats(data) {
    const preserveApiDelaiMoyen = !hasAnyFilter()
        && !hasAnyDrilldown()
        && data.delai_moyen
        && data.delai_moyen.moyenne_delai_minutes !== undefined
        && data.delai_moyen.moyenne_delai_minutes !== null
        && data.delai_moyen.moyenne_delai_minutes !== '';

    const apiDelaiMoyen = preserveApiDelaiMoyen
        ? { moyenne_delai_minutes: Number(data.delai_moyen.moyenne_delai_minutes) || 0 }
        : null;

    const demandesList = Array.isArray(data.demandes_list) ? data.demandes_list : [];
    const fichesList = Array.isArray(data.fiches_list) ? data.fiches_list : [];
    const echantillonsList = Array.isArray(data.echantillons_list) ? data.echantillons_list : [];
    const rapportsList = Array.isArray(data.rapports_list) ? data.rapports_list : [];
    let conformes = 0, nonConformes = 0;
    echantillonsList.forEach(e => {
        const etat = normalizeConformite(e.est_conforme);
        if (etat === 'conforme') conformes++;
        else nonConformes++;
    });
    const laborantinStats = {};
    const secteurStats = {};
    const demandesSecteurStats = {};
    const rapportsSecteurStats = {};
    let totalDelaiDemandesMinutes = 0;
    let countDelaiDemandes = 0;
    demandesList.forEach(demande => {
        const secteur = demande.nom_secteur && demande.nom_secteur.trim() !== '' ? demande.nom_secteur : 'Secteur inconnu';
        if (!demandesSecteurStats[secteur]) {
            demandesSecteurStats[secteur] = { total: 0, totalDelaiMinutes: 0, countDelai: 0 };
        }
        demandesSecteurStats[secteur].total++;
        const delaiMinutes = calculateDelaiDemandeMinutes(demande);
        if (delaiMinutes !== null) {
            totalDelaiDemandesMinutes += delaiMinutes;
            countDelaiDemandes++;
            demandesSecteurStats[secteur].totalDelaiMinutes += delaiMinutes;
            demandesSecteurStats[secteur].countDelai++;
        }
    });
    fichesList.forEach(fiche => {
        const laborantin = fiche.nom_laborantin && fiche.nom_laborantin.trim() !== '' ? fiche.nom_laborantin : 'laborantin anonyme';
        const secteur = fiche.nom_secteur && fiche.nom_secteur.trim() !== '' ? fiche.nom_secteur : 'Secteur inconnu';
        const delaiFicheMinutes = calculateDelaiFicheMinutes(fiche.date_affectation, fiche.date_fin);
        if (!laborantinStats[laborantin]) {
            laborantinStats[laborantin] = { total: 0, totalDelaiMinutes: 0, countDelai: 0 };
        }
        laborantinStats[laborantin].total++;
        if (delaiFicheMinutes !== null) {
            laborantinStats[laborantin].totalDelaiMinutes += delaiFicheMinutes;
            laborantinStats[laborantin].countDelai++;
        }
        if (!secteurStats[secteur]) {
            secteurStats[secteur] = { total: 0, totalDelaiMinutes: 0, countDelai: 0 };
        }
        secteurStats[secteur].total++;
        if (delaiFicheMinutes !== null) {
            secteurStats[secteur].totalDelaiMinutes += delaiFicheMinutes;
            secteurStats[secteur].countDelai++;
        }
    });
    rapportsList.forEach(r => {
        const secteur = r.nom_secteur && r.nom_secteur.trim() !== '' ? r.nom_secteur : 'Secteur inconnu';
        rapportsSecteurStats[secteur] = (rapportsSecteurStats[secteur] || 0) + 1;
    });
    data.echantillons = { conformes, non_conformes: nonConformes };
    data.total_demandes = demandesList.length;
    data.affectations = { total_affectations: fichesList.filter(f => f.date_affectation).length };
    data.laborantin_delai = Object.keys(laborantinStats).map(nom => {
        const avg = laborantinStats[nom].countDelai > 0
            ? laborantinStats[nom].totalDelaiMinutes / laborantinStats[nom].countDelai
            : 0;
        return {
            laborantin: nom,
            fiches: laborantinStats[nom].total,
            delai_moyen_minutes: Number(avg.toFixed(0)),
            delai_moyen_heures: minutesToDecimalHours(avg),
            delai_moyen_affichage: formatMinutesToDayHourMinute(avg)
        };
    });
    data.secteur_delai = Object.keys(secteurStats).map(nom => {
        const avg = secteurStats[nom].countDelai > 0
            ? secteurStats[nom].totalDelaiMinutes / secteurStats[nom].countDelai
            : 0;

        return {
            secteur: nom,
            fiches: secteurStats[nom].total,
            delai_moyen_minutes: Number(avg.toFixed(0)),
            delai_moyen_heures: minutesToDecimalHours(avg),
            delai_moyen_affichage: formatMinutesToDayHourMinute(avg)
        };
    });
    data.demandes_secteur = Object.keys(demandesSecteurStats).map(nom => {
        const avg = demandesSecteurStats[nom].countDelai > 0
            ? demandesSecteurStats[nom].totalDelaiMinutes / demandesSecteurStats[nom].countDelai
            : 0;
        return {
            nom_secteur: nom,
            total: demandesSecteurStats[nom].total,
            delai_moyen_minutes: Number(avg.toFixed(0)),
            delai_moyen_heures: minutesToDecimalHours(avg),
            delai_moyen_affichage: formatMinutesToDayHourMinute(avg)
        };
    });
    data.rapports_secteur = Object.keys(rapportsSecteurStats).map(nom => ({
        nom_secteur: nom,
        total: rapportsSecteurStats[nom]
    }));
    data.delai_moyen = apiDelaiMoyen || {
        moyenne_delai_minutes: countDelaiDemandes > 0
            ? Number((totalDelaiDemandesMinutes / countDelaiDemandes).toFixed(0))
            : 0
    };
}
function getConformitePercentages(conformes, nonConformes) {
    const total = (Number(conformes) || 0) + (Number(nonConformes) || 0);
    return {
        total,
        conformesPct: total ? Number(((conformes * 100) / total).toFixed(1)) : 0,
        nonConformesPct: total ? Number(((nonConformes * 100) / total).toFixed(1)) : 0
    };
}
function applySvgAreaGradients(selector) {
    const svg = $(selector).find('svg').get(0);
    if (!svg) return;
    let defs = svg.querySelector('defs');
    if (!defs) {
        defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        svg.insertBefore(defs, svg.firstChild);
    }
    const gradients = [
        { id: 'grad-open', start: 'rgba(239,68,68,0.28)', end: 'rgba(239,68,68,0.06)' },
        { id: 'grad-progress', start: 'rgba(245,158,11,0.30)', end: 'rgba(245,158,11,0.07)' },
        { id: 'grad-closed', start: 'rgba(16,185,129,0.30)', end: 'rgba(16,185,129,0.07)' }
    ];
    gradients.forEach(g => {
        if (!svg.querySelector('#' + g.id)) {
            const grad = document.createElementNS('http://www.w3.org/2000/svg', 'linearGradient');
            grad.setAttribute('id', g.id);
            grad.setAttribute('x1', '0');
            grad.setAttribute('y1', '0');
            grad.setAttribute('x2', '0');
            grad.setAttribute('y2', '1');
            const s1 = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
            s1.setAttribute('offset', '0%');
            s1.setAttribute('stop-color', g.start);
            const s2 = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
            s2.setAttribute('offset', '100%');
            s2.setAttribute('stop-color', g.end);
            grad.appendChild(s1);
            grad.appendChild(s2);
            defs.appendChild(grad);
        }
    });
    const filledPaths = Array.from(svg.querySelectorAll('path[fill]')).filter(el => {
        const fill = (el.getAttribute('fill') || '').trim();
        return fill && fill !== 'none' && !fill.startsWith('url(') && !fill.includes('rgb(255, 255, 255)');
    });
    const gradientIds = ['url(#grad-open)', 'url(#grad-progress)', 'url(#grad-closed)'];
    filledPaths.slice(0, 3).forEach((el, idx) => {
        el.setAttribute('fill', gradientIds[idx]);
        el.setAttribute('fill-opacity', '1');
    });
    Array.from(svg.querySelectorAll('path[stroke]')).forEach(el => {
        const width = parseFloat(el.getAttribute('stroke-width') || '0');
        if (width > 1) el.setAttribute('stroke-linejoin', 'round');
    });
}
function roundBarChartShapes(selector) {
    const svg = $(selector).find('svg').get(0);
    if (!svg) return;
    svg.querySelectorAll('rect').forEach(rect => {
        const w = parseFloat(rect.getAttribute('width') || '0');
        const h = parseFloat(rect.getAttribute('height') || '0');
        if (w > 10 && h > 10) {
            rect.setAttribute('rx', '12');
            rect.setAttribute('ry', '12');
        }
    });
}
function renderStatusCell(container, value) {
    const statut = normalizeStatut(value === 'clôturée' ? 'terminé' : value);
    const label = statut === 'terminé'
        ? 'Clôturée'
        : (statut === 'en cours' ? 'En cours' : (statut === 'pas evalué' ? 'Ouverte' : String(value || '-')));
    const css = statut === 'terminé' ? 'closed' : (statut === 'en cours' ? 'progress' : 'open');
    $('<span>').addClass('badge-status ' + css).text(label).appendTo(container);
}
function renderStatusAreaChart(selector, legendSelector, demandesList) {
    resetChartContainer(selector);
    const trend = buildStatusTrend(demandesList);
    if (!trend.length) {
        $(selector).html('<div class="chart-empty">Aucune donnée disponible</div>');
        return;
    }
    $(selector).dxChart({
        dataSource: trend,
        palette: [DASHBOARD_COLORS.red, DASHBOARD_COLORS.orange, DASHBOARD_COLORS.green],
        commonSeriesSettings: {
            argumentField: 'date',
            type: 'stackedArea',
            hoverMode: 'allArgumentPoints',
            selectionMode: 'allArgumentPoints',
            point: { visible: false },
            border: { visible: true, width: 2 }
        },
        series: [
            { valueField: 'ouverte', name: 'ouverte', color: DASHBOARD_COLORS.red, opacity: 0.92 },
            { valueField: 'en cours', name: 'en cours', color: DASHBOARD_COLORS.orange, opacity: 0.92 },
            { valueField: 'clôturée', name: 'clôturée', color: DASHBOARD_COLORS.green, opacity: 0.92 }
        ],
        legend: { visible: false },
        tooltip: {
            enabled: true,
            customizeTooltip: function (arg) {
                const d = DevExpress.localization.formatDate(new Date(arg.argument), 'dd/MM/yyyy');
                return { text: d + '\n' + arg.seriesName + ' : ' + arg.valueText + ' demande(s)' };
            }
        },
        argumentAxis: {
            argumentType: 'datetime',
            discreteAxisDivisionMode: 'crossLabels',
            tickInterval: 'day',
            valueMarginsEnabled: true,
            endOnTick: false,
            label: {
                overlappingBehavior: 'stagger',
                staggeringSpacing: 8,
                format: 'dd/MM'
            },
            grid: { visible: false },
            tick: { visible: false }
        },
        valueAxis: {
            visualRange: { startValue: 0 },
            wholeRange: { startValue: 0, endValue: getTrendMax(trend) },
            grid: { color: '#dfe8f5', opacity: 0.75 },
            tick: { visible: false },
            label: { format: { type: 'fixedPoint', precision: 0 } }
        },
        commonPaneSettings: { border: { visible: false } },
        margin: { top: 20, bottom: 24, left: 18, right: 18 },
        onPointClick: function (e) {
            drilldown.statut = e.target.seriesName === drilldown.statut ? null : e.target.seriesName;
            updateContextBar();
            if (originalData) filterDashboardData(originalData);
        },
        onDone: function () {
            applySvgAreaGradients(selector);
        }
    });
    setTimeout(function () {
        applySvgAreaGradients(selector);
    }, 60);
    $(legendSelector).html([
        legendBtn('ouverte', DASHBOARD_COLORS.red),
        legendBtn('en cours', DASHBOARD_COLORS.orange),
        legendBtn('clôturée', DASHBOARD_COLORS.green)
    ].join(''));
    $(legendSelector)
        .off('click')
        .on('click', '.legend-chip', function () {
            const status = $(this).data('status');
            drilldown.statut = drilldown.statut === status ? null : status;
            updateContextBar();
            if (originalData) filterDashboardData(originalData);
        });
}
function legendBtn(status, color) {
    const active = drilldown.statut === status ? 'active' : '';
    return `<button class="legend-chip ${active}" data-status="${status}" style="${drilldown.statut === status ? `background:${color};color:#fff;border-color:transparent;` : ''}">${status}</button>`;
}
function buildStatusTrend(demandesList) {
    const grouped = {};
    (demandesList || []).forEach(d => {
        const dateObj = parseDateSafe(d.date_demande || d.date_creation || d.date);
        if (!dateObj) return;
        const key = dateObj.toISOString().slice(0, 10);
        if (!grouped[key]) {
            grouped[key] = { date: key, ouverte: 0, 'en cours': 0, 'clôturée': 0 };
        }
        const s = normalizeDemandeStatut(d);
        grouped[key][s] = (grouped[key][s] || 0) + 1;
    });
    let trend = Object.values(grouped).sort((a, b) => new Date(a.date) - new Date(b.date));
    if (trend.length <= 12) return trend;
    const bucketSize = Math.ceil(trend.length / 12);
    const compact = [];
    for (let i = 0; i < trend.length; i += bucketSize) {
        const bucket = trend.slice(i, i + bucketSize);
        compact.push({
            date: bucket[0].date,
            ouverte: bucket.reduce((s, r) => s + (Number(r.ouverte) || 0), 0),
            'en cours': bucket.reduce((s, r) => s + (Number(r['en cours']) || 0), 0),
            'clôturée': bucket.reduce((s, r) => s + (Number(r['clôturée']) || 0), 0)
        });
    }
    return compact;
}
function getTrendMax(trend) {
    const max = Math.max.apply(null, trend.map(item =>
        (Number(item.ouverte) || 0) +
        (Number(item['en cours']) || 0) +
        (Number(item['clôturée']) || 0)
    ));
    return Math.max(4, max + 1);
}
function renderDemandesSecteurChart(selector, dataSource, clickable) {
    resetChartContainer(selector);
    if (!Array.isArray(dataSource) || !dataSource.length) {
        $(selector).html('<div class="chart-empty">Aucune donnée disponible</div>');
        return;
    }
    $(selector).dxChart({
        dataSource: dataSource,
        commonSeriesSettings: { argumentField: 'nom_secteur', ignoreEmptyPoints: true },
        series: [
            { type: 'bar', valueField: 'total', name: 'Demandes', axis: 'demandesAxis', color: '#7aa1f2' },
            { type: 'line', valueField: 'delai_moyen_heures', name: 'Délai moyen demande', axis: 'delaiAxis', color: '#1f4c93', point: { visible: true, size: 7, border: { visible: false }, color: '#1f4c93' }, width: 3 }
        ],
        valueAxis: [
            { name: 'demandesAxis', position: 'left', title: 'Nombre de demandes', grid: { color: '#e3ebf7' }, tick: { visible: false } },
            { name: 'delaiAxis', position: 'right', title: 'Heures', grid: { visible: false }, tick: { visible: false } }
        ],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        argumentAxis: {
            label: { overlappingBehavior: 'stagger' },
            tick: { visible: false },
            grid: { visible: false }
        },
        tooltip: {
            enabled: true,
            customizeTooltip: function (arg) {
                if (arg.seriesName === 'Délai moyen demande') {
                    return { text: arg.argumentText + ' : ' + (arg.point.data.delai_moyen_affichage || '-') };
                }
                return { text: arg.argumentText + ' : ' + arg.valueText + ' demande(s)' };
            }
        },
        margin: { top: 12, right: 10, left: 8, bottom: 8 },
        onPointClick: clickable ? function (e) {
            const secteur = e.target.originalArgument;
            drilldown.secteur = drilldown.secteur === secteur ? null : secteur;
            updateContextBar();
            if (originalData) filterDashboardData(originalData);
        } : null,
        onDone: function () {
            roundBarChartShapes(selector);
        }
    });
    setTimeout(function () {
        roundBarChartShapes(selector);
    }, 60);
}
function renderSectorTrendChart(selector, demandesList) {
    resetChartContainer(selector);
    const trendData = buildDemandesTrendBySecteurCompact(demandesList);
    if (!trendData.length) {
        $(selector).html('<div class="chart-empty">Aucune donnée disponible</div>');
        return;
    }
    if (drilldown.secteur) {
        $(selector).dxChart({
            dataSource: trendData,
            commonSeriesSettings: {
                argumentField: 'date',
                type: 'spline',
                point: { visible: false },
                width: 3
            },
            series: [{
                valueField: drilldown.secteur,
                name: drilldown.secteur,
                color: DASHBOARD_COLORS.blue
            }],
            legend: {
                visible: true,
                verticalAlignment: 'bottom',
                horizontalAlignment: 'center'
            },
            argumentAxis: {
                argumentType: 'datetime',
                tickInterval: 'month',
                label: {
                    overlappingBehavior: 'stagger',
                    format: 'MM/yyyy'
                },
                grid: { visible: false },
                tick: { visible: false }
            },
            valueAxis: {
                visualRange: { startValue: 0 },
                wholeRange: { startValue: 0 },
                grid: { color: '#dfe8f5', opacity: 0.8 },
                tick: { visible: false }
            },
            margin: { top: 16, right: 20, bottom: 18, left: 20 },
            tooltip: {
                enabled: true,
                customizeTooltip: function (arg) {
                    return {
                        text:
                            arg.seriesName + '\n' +
                            DevExpress.localization.formatDate(new Date(arg.argument), 'MM/yyyy') +
                            ' : ' + arg.valueText + ' demande(s)'
                    };
                }
            }
        });
        return;
    }
    const secteurs = getTopSecteursFromTrend(trendData, 4);
    $(selector).dxChart({
        dataSource: trendData,
        commonSeriesSettings: {
            argumentField: 'date',
            type: 'spline',
            point: { visible: false },
            width: 3
        },
        series: secteurs.map(secteur => ({
            valueField: secteur,
            name: secteur
        })),
        palette: [
            DASHBOARD_COLORS.blue,
            DASHBOARD_COLORS.green,
            DASHBOARD_COLORS.purple,
            DASHBOARD_COLORS.orange
        ],
        legend: {
            verticalAlignment: 'bottom',
            horizontalAlignment: 'center',
            itemTextPosition: 'right'
        },
        argumentAxis: {
            argumentType: 'datetime',
            tickInterval: 'month',
            label: {
                overlappingBehavior: 'stagger',
                format: 'MM/yyyy'
            },
            grid: { visible: false },
            tick: { visible: false }
        },
        valueAxis: {
            visualRange: { startValue: 0 },
            wholeRange: { startValue: 0 },
            grid: { color: '#dfe8f5', opacity: 0.8 },
            tick: { visible: false }
        },
        margin: { top: 16, right: 20, bottom: 18, left: 20 },
        tooltip: {
            enabled: true,
            customizeTooltip: function (arg) {
                return {
                    text:
                        arg.seriesName + '\n' +
                        DevExpress.localization.formatDate(new Date(arg.argument), 'MM/yyyy') +
                        ' : ' + arg.valueText + ' demande(s)'
                };
            }
        }
    });
}
function buildDemandesBySecteurAndStatus(demandesList) {
    const grouped = {};

    (demandesList || []).forEach(d => {
        const secteur = d.nom_secteur && d.nom_secteur.trim() !== '' ? d.nom_secteur : 'Secteur inconnu';
        const statut = normalizeDemandeStatut(d);

        if (!grouped[secteur]) {
            grouped[secteur] = {
                secteur: secteur,
                ouverte: 0,
                'en cours': 0,
                'clôturée': 0,
                total: 0
            };
        }

        grouped[secteur][statut] += 1;
        grouped[secteur].total += 1;
    });

    return Object.values(grouped).sort((a, b) => b.total - a.total);
}

function renderSectorStatusStackedChart(selector, demandesList) {
    resetChartContainer(selector);

    const dataSource = buildDemandesBySecteurAndStatus(demandesList);

    if (!dataSource.length) {
        $(selector).html('<div class="chart-empty">Aucune donnée disponible</div>');
        return;
    }

    $(selector).dxChart({
        dataSource: dataSource,
        commonSeriesSettings: {
            argumentField: 'secteur',
            type: 'stackedBar',
            ignoreEmptyPoints: true
        },
        series: [
            { valueField: 'ouverte', name: 'ouverte', color: DASHBOARD_COLORS.red },
            { valueField: 'en cours', name: 'en cours', color: DASHBOARD_COLORS.orange },
            { valueField: 'clôturée', name: 'clôturée', color: DASHBOARD_COLORS.green }
        ],
        legend: {
            verticalAlignment: 'bottom',
            horizontalAlignment: 'center'
        },
        argumentAxis: {
            label: { overlappingBehavior: 'stagger' },
            tick: { visible: false },
            grid: { visible: false }
        },
        valueAxis: {
            title: 'Nombre de demandes',
            grid: { color: '#e3ebf7' },
            tick: { visible: false }
        },
        tooltip: {
            enabled: true,
            shared: true,
            customizeTooltip: function (arg) {
                return {
                    text: arg.argumentText + '\n' + arg.points.map(p => `${p.seriesName}: ${p.valueText}`).join('\n')
                };
            }
        },
        margin: {
            top: 12,
            right: 10,
            bottom: 8,
            left: 8
        },
        onPointClick: function (e) {
            drilldown.secteur = e.target.originalArgument;
            drilldown.statut = e.target.seriesName;
            updateContextBar();
            if (originalData) filterDashboardData(originalData);
        },
        onDone: function () {
            roundBarChartShapes(selector);
        }
    });

    setTimeout(function () {
        roundBarChartShapes(selector);
    }, 60);
}

function buildDelayBySecteur(demandesList) {
    const grouped = {};

    (demandesList || []).forEach(d => {
        const secteur = d.nom_secteur && d.nom_secteur.trim() !== '' ? d.nom_secteur : 'Secteur inconnu';
        const delaiMinutes = calculateDelaiDemandeMinutes(d);
        if (delaiMinutes === null) return;

        if (!grouped[secteur]) {
            grouped[secteur] = {
                secteur: secteur,
                totalMinutes: 0,
                count: 0
            };
        }

        grouped[secteur].totalMinutes += delaiMinutes;
        grouped[secteur].count += 1;
    });

    return Object.values(grouped).map(item => {
        const avgMinutes = item.count ? item.totalMinutes / item.count : 0;
        return {
            secteur: item.secteur,
            delai_moyen_minutes: Number(avgMinutes.toFixed(0)),
            delai_moyen_heures: Number((avgMinutes / 60).toFixed(2)),
            delai_moyen_affichage: formatMinutesToDayHourMinute(avgMinutes)
        };
    }).sort((a, b) => b.delai_moyen_minutes - a.delai_moyen_minutes);
}

function renderDelayBySectorChart(selector, demandesList) {
    resetChartContainer(selector);

    const dataSource = buildDelayBySecteur(demandesList);

    if (!dataSource.length) {
        $(selector).html('<div class="chart-empty">Aucune donnée disponible</div>');
        return;
    }

    $(selector).dxChart({
        dataSource: dataSource,
        rotated: true,
        commonSeriesSettings: {
            argumentField: 'secteur',
            type: 'bar',
            ignoreEmptyPoints: true
        },
        series: [{
            valueField: 'delai_moyen_heures',
            name: 'Délai moyen',
            color: '#4f87ff',
            label: {
                visible: true,
                position: 'outside',
                customizeText: function (info) {
                    return info.point.data.delai_moyen_affichage;
                }
            }
        }],
        legend: { visible: false },
        argumentAxis: {
            tick: { visible: false },
            grid: { visible: false },
            label: {
                font: { size: 13, weight: 500, color: '#38557e' }
            }
        },
        valueAxis: {
            title: 'Heures',
            grid: { color: '#e3ebf7' },
            tick: { visible: false }
        },
        tooltip: {
            enabled: true,
            customizeTooltip: function (info) {
                return {
                    text: info.argument + ' : ' + info.point.data.delai_moyen_affichage
                };
            }
        },
        margin: {
            top: 10,
            right: 22,
            bottom: 6,
            left: 10
        },
        onPointClick: function (e) {
            const secteur = e.target.originalArgument;
            drilldown.secteur = drilldown.secteur === secteur ? null : secteur;
            updateContextBar();
            if (originalData) filterDashboardData(originalData);
        },
        onDone: function () {
            roundBarChartShapes(selector);
        }
    });
    setTimeout(function () {
        roundBarChartShapes(selector);
    }, 60);
}
function buildDemandesTrendBySecteurCompact(demandesList) {
    const grouped = {};

    (demandesList || []).forEach(d => {
        const dateObj = parseDateSafe(d.date_demande);
        if (!dateObj) return;
        const dateKey = `${dateObj.getFullYear()}-${String(dateObj.getMonth() + 1).padStart(2, '0')}-01`;
        const secteur = d.nom_secteur && d.nom_secteur.trim() !== ''
            ? d.nom_secteur
            : 'Secteur inconnu';
        if (!grouped[dateKey]) grouped[dateKey] = { date: dateKey };
        grouped[dateKey][secteur] = (grouped[dateKey][secteur] || 0) + 1;
    });
    return Object.values(grouped).sort((a, b) => new Date(a.date) - new Date(b.date));
}
function getTopSecteursFromTrend(trendData, limit = 4) {
    const totals = {};
    trendData.forEach(row => {
        Object.keys(row).forEach(key => {
            if (key === 'date') return;
            totals[key] = (totals[key] || 0) + (Number(row[key]) || 0);
        });
    });
    return Object.entries(totals)
        .sort((a, b) => b[1] - a[1])
        .slice(0, limit)
        .map(entry => entry[0]);
}
function buildDemandesTrendBySecteur(demandesList) {
    const grouped = {};
    (demandesList || []).forEach(d => {
        const dateObj = parseDateSafe(d.date_demande);
        if (!dateObj) return;
        const dateKey = dateObj.toISOString().slice(0, 10);
        const secteur = d.nom_secteur && d.nom_secteur.trim() !== '' ? d.nom_secteur : 'Secteur inconnu';
        if (!grouped[dateKey]) grouped[dateKey] = { date: dateKey };
        grouped[dateKey][secteur] = (grouped[dateKey][secteur] || 0) + 1;
    });
    return Object.values(grouped).sort((a, b) => new Date(a.date) - new Date(b.date));
}
function renderLaborantinChart(selector, dataSource) {
    resetChartContainer(selector);
    if (!Array.isArray(dataSource) || !dataSource.length) {
        $(selector).html('<div class="chart-empty">Aucune donnée disponible</div>');
        return;
    }
    $(selector).dxChart({
        dataSource: dataSource,
        commonSeriesSettings: { argumentField: 'laborantin', ignoreEmptyPoints: true },
        series: [
            { type: 'bar', valueField: 'fiches', name: 'Nombre de fiches', axis: 'fichesAxis', color: DASHBOARD_COLORS.orange },
            { type: 'line', valueField: 'delai_moyen_heures', name: 'Délai moyen fiche', axis: 'delaiAxis', color: DASHBOARD_COLORS.blue, point: { visible: true, size: 6 } }
        ],
        valueAxis: [
            { name: 'fichesAxis', position: 'left', title: 'Nombre de fiches' },
            { name: 'delaiAxis', position: 'right', title: 'Heures' }
        ],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        argumentAxis: { label: { overlappingBehavior: 'rotate', rotationAngle: 35 } },
        tooltip: {
            enabled: true,
            customizeTooltip: function (arg) {
                if (arg.seriesName === 'Délai moyen fiche') {
                    return { text: arg.argumentText + ' : ' + (arg.point.data.delai_moyen_affichage || '-') };
                }
                return { text: arg.argumentText + ' : ' + arg.valueText };
            }
        }
    });
}
function renderRapportsSecteurChart(selector, dataSource) {
    resetChartContainer(selector);
    if (!Array.isArray(dataSource) || !dataSource.length) {
        $(selector).html('<div class="chart-empty">Aucune donnée disponible</div>');
        return;
    }

    $(selector).dxChart({
        dataSource: dataSource,
        rotated: true,
        commonSeriesSettings: { ignoreEmptyPoints: true },
        series: [{
            type: 'bar',
            argumentField: 'nom_secteur',
            valueField: 'total',
            color: '#1fb987',
            label: {
                visible: true,
                position: 'outside',
                font: { weight: 700, size: 14 },
                customizeText: function (i) { return i.valueText; }
            }
        }],
        tooltip: {
            enabled: true,
            customizeTooltip: function (info) {
                return { text: info.argument + ': ' + info.value + ' rapports' };
            }
        },
        legend: { visible: false },
        argumentAxis: {
            tick: { visible: false },
            grid: { visible: false },
            label: { font: { size: 13, weight: 500, color: '#38557e' } }
        },
        valueAxis: {
            title: 'Nombre de rapports',
            grid: { visible: false },
            tick: { visible: false },
            label: { visible: false }
        },
        margin: { top: 10, right: 22, bottom: 6, left: 10 },
        onDone: function () {
            roundBarChartShapes(selector);
        }
    });

    setTimeout(function () {
        roundBarChartShapes(selector);
    }, 60);
}



function addGridExportButtons(e, fileBaseName) {
    const items = e.toolbarOptions.items;

    if (!items.some(item => item.name === 'searchPanel')) {
        items.push({ name: 'searchPanel', location: 'after' });
    }

    items.push(
        {
            location: 'after',
            widget: 'dxButton',
            options: {
                icon: 'xlsxfile',
                hint: 'Exporter Excel',
                stylingMode: 'text',
                onClick: function () { exportGridToExcel(e.component, fileBaseName); }
            }
        },
        {
            location: 'after',
            widget: 'dxButton',
            options: {
                icon: 'export',
                hint: 'Exporter CSV',
                stylingMode: 'text',
                onClick: function () { exportGridToCSV(e.component, fileBaseName); }
            }
        }
    );
}

function exportGridToCSV(grid, fileBaseName) {
    const visibleColumns = grid.getVisibleColumns().filter(col => col.type !== 'buttons' && col.command !== 'edit');
    const dataSource = grid.getDataSource().items();
    const headers = visibleColumns.map(col => csvEscape(col.caption || col.dataField));
    const rows = dataSource.map(row => visibleColumns.map(col => csvEscape(row[col.dataField])).join(';'));
    const csvContent = [headers.join(';'), ...rows].join('\n');

    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = fileBaseName + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function exportGridToExcel(grid, fileBaseName) {
    if (typeof ExcelJS === 'undefined' || typeof saveAs === 'undefined' || !DevExpress.excelExporter) {
        DevExpress.ui.notify('ExcelJS / FileSaver manquant.', 'warning', 2500);
        return;
    }

    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Données');

    DevExpress.excelExporter.exportDataGrid({
        component: grid,
        worksheet: worksheet,
        autoFilterEnabled: true
    }).then(function () {
        workbook.xlsx.writeBuffer().then(function (buffer) {
            saveAs(new Blob([buffer], { type: 'application/octet-stream' }), fileBaseName + '.xlsx');
        });
    });
}


function extractRapportFilename(rapportString) {
    if (!rapportString) return '';
    const value = String(rapportString).trim();
    if (value === '') return '';
    if (value.indexOf('|') !== -1) {
        const parts = value.split('|');
        return parts[parts.length - 1].trim();
    }
    return value;
}

function renderMaskedId(container, idValue, labelPrefix) {
    $('<span>')
        .text('••••')
        .attr('title', (labelPrefix || 'ID') + ': ' + (idValue ?? '-'))
        .css({
            display: 'inline-block',
            padding: '3px 10px',
            borderRadius: '999px',
            background: '#eef2f7',
            color: '#4b5563',
            fontSize: '12px',
            letterSpacing: '1px',
            cursor: 'help',
            userSelect: 'none'
        })
        .appendTo(container);
}

function resetChartContainer(selector) {
    const $el = $(selector);
    if (!$el.length) return;
    const element = $el.get(0);

    try {
        const pie = DevExpress.viz.dxPieChart.getInstance(element);
        if (pie) pie.dispose();
    } catch (e) {}

    try {
        const chart = DevExpress.viz.dxChart.getInstance(element);
        if (chart) chart.dispose();
    } catch (e) {}

    $el.empty();
}

function resetGridContainer(selector) {
    const $el = $(selector);
    if (!$el.length) return;
    const element = $el.get(0);

    try {
        const grid = DevExpress.ui.dxDataGrid.getInstance(element);
        if (grid) grid.dispose();
    } catch (e) {}

    $el.empty();
}


function revealActiveView() {
    const $els = $('#view-' + currentView + ' .reveal, .context-bar, .tabs, .filter-shell');
    $els.each(function (i, el) {
        $(el).removeClass('shown');
        setTimeout(() => $(el).addClass('shown'), 40 * i);
    });
}
/* ==========================================================
   FINAL CONSOLIDATED OPERATIONAL PATCH - 2026-05-08
   Applies the corrections requested after the frontend ZIP:
   - raw opened KPI with coherent totals
   - echantillon drill-down affects all stats
   - report status = Livré / En attente only
   - report type = Provisoire / Final only
   - report-ready KPI <= demandes terminées
   - existing report link/id in Générer rapport
   - no numeric analysis IDs in delay-by-analysis chart
   - fiches/ordres grid = one row per ordre, grouped by demande
   - alert type labels are business-readable
========================================================== */

window.selectedFormats = window.selectedFormats || {};

window.escapeHtml = window.escapeHtml || function (value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};


function getDemandIdentityKeys(row) {
    const keys = [
        row?.id_demande,
        row?.demande_id,
        row?.id_demande_analyse_bk,
        row?.id_demande_analyse_sk,
        row?.reference_demande,
        row?.ref_demande,
        row?.ref_demande_analyse
    ];
    return keys.map(normalizeKeyFinal).filter(Boolean).filter(v => v !== '-');
}

function sameDemandFinal(demande, item) {
    const dKeys = new Set(getDemandIdentityKeys(demande));
    return getDemandIdentityKeys(item).some(k => dKeys.has(k));
}

function getDemandPrimaryKey(row) {
    return getDemandIdentityKeys(row)[0] || '';
}


function getRefDemande(row) {
    return textOrDash(row?.reference_demande || row?.ref_demande || row?.ref_demande_analyse || row?.id_demande);
}

function getDemandId(row) {
    return textOrDash(row?.id_demande || row?.demande_id || row?.id_demande_analyse_bk || row?.id_demande_analyse_sk || row?.reference_demande || row?.ref_demande);
}

function getClientName(row) {
    return textOrDash(row?.client_nom || row?.client_name || row?.nom_client || row?.client || '-');
}

function getSecteurName(row) {
    return textOrDash(row?.secteur_nom || row?.nom_secteur || row?.secteur || 'Secteur inconnu');
}

function getRiskKey(value) {
    return String(value || 'none')
        .trim()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, '_');
}

function formatDurationFromDays(value) {
    if (value === null || value === undefined || value === '' || value === '-') return '-';
    const n = Number(String(value).replace(',', '.').replace(/[^0-9.-]/g, ''));
    if (!Number.isFinite(n)) return String(value);
    const totalMinutes = Math.max(0, Math.round(n * 1440));
    return formatMinutesToDayHourMinute(totalMinutes);
}

function formatDaysValue(value) {
    return formatDurationFromDays(value);
}

function getRawStatus(row) {
    return String(row?.statut_brut || row?.raw_statut_demande || row?.statut_demande || row?.statut_original || row?.statut || '').trim();
}

function getRawStatusCategory(row) {
    const raw = getRawStatus(row).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    if (!raw || ['pas evalue', 'non evalue', 'ouverte', 'ouvert', 'open', 'valide', 'validee'].includes(raw)) return 'ouverte';
    if (['en cours', 'encours', 'in progress'].includes(raw)) return 'en cours';
    if (['termine', 'terminee', 'cloture', 'cloturee', 'livre', 'livree', 'final'].includes(raw)) return 'terminée';
    return 'ouverte';
}

function normalizeDemandeStatut(demande) {
    if (!demande) return 'ouverte';
    const fromApi = String(demande.statut_operationnel || demande.statut_dashboard || '').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    if (fromApi) {
        if (['terminee', 'termine', 'cloturee', 'cloture', 'valide', 'final'].includes(fromApi)) return 'clôturée';
        if (['en cours', 'encours'].includes(fromApi)) return 'en cours';
        if (['ouverte', 'ouvert'].includes(fromApi)) return 'ouverte';
    }
    const raw = getRawStatus(demande).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const closedWords = ['cloture', 'cloturee', 'termine', 'terminee', 'valide', 'validee', 'final', 'livre', 'livree'];
    if (demande.date_cloture || closedWords.includes(raw) || Number(demande.has_rapport_final || 0) === 1) return 'clôturée';
    const completeResults = Number(demande.nb_ordres || 0) > 0 && Number(demande.nb_resultats || 0) >= Number(demande.nb_ordres || 0);
    if (completeResults) return 'clôturée';
    const hasSomethingGoing =
        Number(demande.nb_ordres || 0) > 0 ||
        Number(demande.nb_resultats || 0) > 0 ||
        Number(demande.nb_analyses || 0) > 0 ||
        Number(demande.nb_analyses_distinct || 0) > 0 ||
        Number(demande.nb_laborantins_distinct || 0) > 0 ||
        Number(demande.nb_echantillons || demande.nb_fiches_paillasse || 0) > 0 ||
        Boolean(demande.date_reception || demande.date_affectation || demande.date_debut_analyse || demande.date_fin_analyse) ||
        (demande.delai_affectation !== null && demande.delai_affectation !== undefined) ||
        (demande.delai_debut_analyse !== null && demande.delai_debut_analyse !== undefined);
    if (raw === 'en cours' || raw === 'encours' || hasSomethingGoing) return 'en cours';
    return 'ouverte';
}


function renderRawAndOperationalStatus(container, rowData) {
    const op = rowData.statut_operationnel || rowData.statut_dashboard || normalizeDemandeStatut(rowData);
    const rawDisplay = getRawStatusDisplay(rowData);
    const opLabel = String(op).toLowerCase() === 'clôturée' ? 'Terminée' : textOrDash(op);
    const opClass = String(opLabel).toLowerCase().includes('termin') ? 'closed' : (String(opLabel).toLowerCase().includes('cours') ? 'progress' : 'open');
    const $wrap = $('<div>').addClass('status-stack');
    $('<span>').addClass('badge-status ' + opClass).text(opLabel).appendTo($wrap);
    if (rawDisplay && rawDisplay !== '-' && rawDisplay.toLowerCase() !== String(opLabel).toLowerCase()) {
        $('<small>').addClass('raw-status-note').text('Brut : ' + rawDisplay).appendTo($wrap);
    }
    $(container).empty().append($wrap);
}


function getAllDetailRows(rowData, type) {
    if (!rowData) return [];
    if (type === 'echantillons') return rowData._echantillons || getDemandDetails(rowData, 'echantillons');
    if (type === 'ordres') {
        const rows = rowData._ordres || getDemandDetails(rowData, 'ordres').concat(getDemandDetails(rowData, 'fiches'));
        const seen = new Set();
        return rows.map(normalizeOrdreFicheRow).filter(row => {
            const key = String(row.row_key || row.id_fiche || row.reference_ordre || JSON.stringify(row));
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    }
    if (type === 'alertes') return rowData._alertes || getDemandDetails(rowData, 'alertes');
    return [];
}

function renderDetailTableSafe(rows, columns, emptyText) {
    if (!Array.isArray(rows) || rows.length === 0) return $('<div>').addClass('detail-placeholder').text(emptyText || 'Aucune donnée disponible.');
    const $table = $('<table>').addClass('detail-simple-table');
    const $thead = $('<thead><tr></tr></thead>');
    const $tbody = $('<tbody>');
    columns.forEach(col => $thead.find('tr').append($('<th>').text(col.caption || '')));
    rows.forEach(row => {
        const $tr = $('<tr>');
        columns.forEach(col => {
            const $td = $('<td>');
            const value = typeof col.value === 'function' ? col.value(row) : row[col.field];
            if (typeof col.render === 'function') col.render($td, value, row);
            else $td.text(textOrDash(value));
            $tr.append($td);
        });
        $tbody.append($tr);
    });
    $table.append($thead, $tbody);
    return $('<div>').addClass('detail-table-scroll').append($table);
}



function renderDemandesGrid(dataSource, selector = '#demandesTable', withDetails = true) {
    resetGridContainer(selector);
    $(selector).dxDataGrid({
        dataSource,
        keyExpr: 'row_key',
        showBorders: true,
        showColumnLines: true,
        showRowLines: true,
        rowAlternationEnabled: true,
        hoverStateEnabled: true,
        wordWrapEnabled: true,
        columnAutoWidth: false,
        columnHidingEnabled: false,
        width: '100%',
        scrolling: { mode: 'standard', useNative: false, showScrollbar: 'always', scrollByContent: true, scrollByThumb: true },
        paging: { pageSize: 12 },
        pager: { showInfo: true, showNavigationButtons: true, showPageSizeSelector: true, allowedPageSizes: [12, 24, 50] },
        filterRow: { visible: true, applyFilter: 'auto' },
        headerFilter: { visible: true },
        searchPanel: { visible: true, width: 260, placeholder: 'Rechercher une demande...' },
        loadPanel: { enabled: true, text: 'Chargement...' },
        onToolbarPreparing: function (e) { addGridExportButtons(e, 'demandes_analyses'); },
        masterDetail: withDetails ? { enabled: true, template: (container, options) => renderDemandeMasterDetail(container, options.data) } : { enabled: false },
        columns: [
            { dataField: 'reference_demande', caption: 'Réf demande', width: 160, cellTemplate: (container, options) => $('<strong>').addClass('ref-demande-cell').text(options.value || '-').appendTo(container) },
            { dataField: 'client_nom', caption: 'Client', width: 210 },
            { dataField: 'secteur_nom', caption: 'Secteur', width: 165 },
            { dataField: 'date_demande', caption: 'Date demande', dataType: 'date', width: 145 },
            { dataField: 'statut_operationnel', caption: 'Statut', width: 180, cellTemplate: (container, options) => renderRawAndOperationalStatus(container, options.data) },
            { dataField: 'delai_total_demande', caption: 'Délai', width: 155, alignment: 'center', cellTemplate: (container, options) => $('<span>').addClass('delay-pill').text(formatDaysValue(options.value)).appendTo(container) },
            { dataField: 'avancement', caption: 'Avancement', width: 135, alignment: 'center', cellTemplate: (container, options) => $('<span>').addClass('progress-pill').text(options.value || '-').appendTo(container) },
            { dataField: 'niveau_risque', caption: 'Risque', width: 130, alignment: 'center', cellTemplate: (container, options) => renderRiskBadge(container, options.value) },
            { dataField: 'report_status', caption: 'Rapport', width: 135, alignment: 'center', cellTemplate: function (container, options) { const value = String(options.value || 'BLOCKED'); const label = value === 'FINAL_EXISTS' ? 'Final' : (value === 'READY' ? 'Prêt' : 'Non prêt'); $('<span>').addClass('report-status-pill ' + value.toLowerCase()).text(label).appendTo(container); } },
            { caption: 'Générer rapport', width: 320, minWidth: 300, allowFiltering: false, allowSorting: false, alignment: 'center', cellTemplate: (container, options) => renderGenerateReportButtons(container, options.data) }
        ]
    });
}


function updateContextBar() {
    const labels = {
        global: ['Vue globale', 'Cockpit opérationnel : demandes, délais, fiches paillasse et alertes.'],
        demandes: ['Demandes d’analyse', 'Statut opérationnel dans la grille ; statut brut visible sous le badge quand il diffère.'],
        fiches: ['Fiches paillasse', 'Une ligne = une fiche paillasse ; le KPI Sans laborantin reste calculé par ordre.'],
        rapports: ['Rapports', 'Type rapport = Provisoire/Final ; statut livraison = Livré/En attente.'],
        alertes: ['Alertes', 'Une alerte signale un retard prédit, un risque critique/élevé, un résultat incomplet, un rapport manquant/en attente, une non-conformité ou une fiche sans laborantin.']
    };
    const selected = labels[currentView] || labels.global;
    $('#contextTitle').text(selected[0]);
    $('#contextSubtitle').text(selected[1]);
    const parts = [];
    if (drilldown.secteur) parts.push('Secteur: ' + drilldown.secteur);
    if (drilldown.statut) parts.push('Statut: ' + (drilldown.statut === 'clôturée' ? 'Terminée' : drilldown.statut));
    if (drilldown.conformite) parts.push('Conformité: ' + drilldown.conformite);
    if (drilldown.rapport_livraison) parts.push('Rapport: ' + drilldown.rapport_livraison);
    if (drilldown.alerte_gravite) parts.push('Gravité: ' + drilldown.alerte_gravite);
    $('#showCurrentFilterBtn').text(parts.length ? parts.join(' | ') : 'Aucun drill-down');
}

var REPORT_URL = typeof REPORT_URL !== 'undefined' ? REPORT_URL : '../back_end/new_report.php';
function textOrDash(value) {
    return value === null || value === undefined || String(value).trim() === '' ? '-' : String(value);
}


function getRawStatusDisplay(row) {
    const category = getRawStatusCategory(row);
    if (category === 'terminée') return 'Clôturée';
    if (category === 'en cours') return 'En cours';
    return 'Ouverte';
}

function getDemandeDelayDays(row) {
    const candidates = [
        row?.delai_total_demande,
        row?.delai_total_jours,
        row?.delai_total,
        row?.jours_retard_predit
    ];
    for (const value of candidates) {
        const n = Number(String(value ?? '').replace(',', '.').replace(/[^0-9.-]/g, ''));
        if (Number.isFinite(n) && n > 0) return n;
    }
    const minutes = Number(row?.delai_total_minutes);
    if (Number.isFinite(minutes) && minutes > 0) return minutes / 1440;
    return 0;
}

function isDelayLongCandidate(row) {
    if (!row) return false;
    const delayDays = getDemandeDelayDays(row);
    const predictedLate = Number(row?.jours_retard_predit || 0) > 0;
    return normalizeDemandeStatut(row) !== 'clôturée' && (delayDays >= 7 || predictedLate);
}

function computeDelayAlertCount(data) {
    const alertes = Array.isArray(data?.alertes_list) ? data.alertes_list : [];
    const alertCount = alertes.filter(a => String(a.type_alerte || '').toUpperCase() === 'DELAI_LONG').length;
    const demandes = Array.isArray(data?.demandes_list) ? data.demandes_list : [];
    const delayDemandCount = demandes.filter(isDelayLongCandidate).length;
    return Math.max(alertCount, delayDemandCount, Number(data?.kpis_clean?.alertes_delai || 0));
}

function buildAlertesTypeRows(data) {
    const counts = {};
    const rows = Array.isArray(data?.alertes_list) && data.alertes_list.length
        ? data.alertes_list.map(a => ({ type_alerte: a.type_alerte || a.label || '-', total: 1 }))
        : (data?.charts?.alertes_type || []);

    (rows || []).forEach(row => {
        const type = String(row.type_alerte || row.label || '-').trim().toUpperCase();
        if (!type || type === '-') return;
        counts[type] = (counts[type] || 0) + Number(row.total || row.count || 1);
    });

    const delaiLong = computeDelayAlertCount(data);
    if (delaiLong > 0) counts.DELAI_LONG = Math.max(counts.DELAI_LONG || 0, delaiLong);

    return Object.keys(counts).map(type => ({ type_alerte: type, total: counts[type] }));
}

function getReportStatusDisplay(rowData) {
    const status = String(rowData?.report_status || '').trim().toUpperCase();
    if (status === 'FINAL_EXISTS') return 'Rapport final existant';
    if (hasExistingReport(rowData)) return 'Rapport existant';
    if (status === 'READY' || Number(rowData?.can_generate_report || 0) === 1) return 'Prêt à générer';
    return 'Non prêt';
}

function getReportDisplayLine(rowData) {
    const existing = getExistingReportLabel(rowData);
    if (existing) return existing;
    const status = String(rowData?.report_status || '').trim().toUpperCase();
    if (status === 'READY' || Number(rowData?.can_generate_report || 0) === 1) return 'Aucun rapport existant — génération possible';
    return '-';
}

function hideReportQuickIndicators() {
    if (!hasDom('#reportStats')) return;
    const $stats = $('#reportStats');
    $stats.empty().hide();
    const $section = $stats.closest('.dashboard-card, .chart-card, .panel, .section-card, .card, section');
    const title = ($section.find('h1,h2,h3,h4,.card-title,.section-title').first().text() || '').toLowerCase();
    if (title.includes('indicateurs')) $section.hide();
}

function renderKpiCard(label, value, foot, type) {
    return `
      <div class="kpi-card ${type || 'primary'}">
        <div class="kpi-topline"><span class="kpi-dot"></span></div>
        <div class="kpi-label">${escapeHtml(label)}</div>
        <div class="kpi-value">${escapeHtml(value)}</div>
        <div class="kpi-foot">${escapeHtml(foot || '')}</div>
      </div>`;
}

function getConformityCountsFromData(data) {
    const echantillons = Array.isArray(data?.echantillons_list) ? data.echantillons_list : [];
    if (echantillons.length) {
        let conformes = 0;
        let nonConformes = 0;
        echantillons.forEach(row => {
            if (getRowConformity(row) === 'non conforme') nonConformes++;
            else conformes++;
        });
        return { conformes, nonConformes };
    }

    if (Array.isArray(data?.charts?.conformite_echantillons)) {
        let conformes = 0, nonConformes = 0;
        data.charts.conformite_echantillons.forEach(row => {
            const label = String(row.conformite || row.est_conforme || row.label || '').toLowerCase();
            const total = Number(row.total || row.count || row.value || 0);
            if (label.includes('non')) nonConformes += total;
            else conformes += total;
        });
        return { conformes, nonConformes };
    }

    return {
        conformes: parseInt(data?.echantillons?.conformes) || 0,
        nonConformes: parseInt(data?.echantillons?.non_conformes) || 0
    };
}

function updateLastDataDateFromData(data) {
    const dates = (data.demandes_list || []).map(d => parseDateSafe(d.date_demande)).filter(Boolean).sort((a, b) => b - a);
    $('#lastDataDate').text(dates.length ? DevExpress.localization.formatDate(dates[0], 'dd/MM/yyyy') : '-');
}

function renderUrgentStrip(data) {
    const $target = $('#urgentStrip');
    if (!$target.length) return;
    const alertes = data.alertes_list || [];
    const critical = alertes.filter(a => getRiskKey(a.gravite) === 'critique').length;
    const delaiLong = computeDelayAlertCount(data);
    const rapportManquant = alertes.filter(a => String(a.type_alerte || '').toUpperCase() === 'RAPPORT_MANQUANT').length;
    const nonConf = alertes.filter(a => String(a.type_alerte || '').toUpperCase() === 'NON_CONFORMITE').length;
    $target.html(`
      <div class="urgent-card critical"><span>Risque critique</span><strong>${critical}</strong><em>Demandes prioritaires</em></div>
      <div class="urgent-card warning"><span>Délai long</span><strong>${delaiLong}</strong><em>Dépassement opérationnel</em></div>
      <div class="urgent-card danger"><span>Rapport manquant</span><strong>${rapportManquant}</strong><em>Résultats sans rapport</em></div>
      <div class="urgent-card quality"><span>Non conformité</span><strong>${nonConf}</strong><em>Échantillons à vérifier</em></div>
    `);
}

function renderRiskBadge(container, value) {
    const label = textOrDash(value);
    $('<span>').addClass('risk-pill risk-' + getRiskKey(label)).text(label).appendTo(container);
}

function renderConformityBadge(container, value) {
    const bad = normalizeConformite(value) === 'non conforme';
    $('<span>').addClass('conformity-badge ' + (bad ? 'bad' : 'good')).text(bad ? 'Non conforme' : 'Conforme').appendTo(container);
}

function renderResultBadge(container, value) {
    const hasResult = value !== null && value !== undefined && String(value).trim() !== '' && String(value).trim() !== '-';
    $('<span>').addClass('result-badge ' + (hasResult ? 'ok' : 'missing')).text(hasResult ? 'Résultat saisi' : 'Résultat manquant').appendTo(container);
}

function renderOperationalStatusBadge(container, value) {
    const s = normalizeDemandeStatut({ statut_operationnel: value });
    const label = s === 'clôturée' ? 'Terminée' : (s === 'en cours' ? 'En cours' : 'Ouverte');
    const css = s === 'clôturée' ? 'closed' : (s === 'en cours' ? 'progress' : 'open');
    $('<span>').addClass('badge-status ' + css).text(label).appendTo(container);
}

function normalizeFormatCode(formatCode) {
    const value = String(formatCode || '').trim();
    if (value === '1' || value === 'format_1') return 'format_1';
    if (value === '2' || value === 'format_2') return 'format_2';
    if (value === '3' || value === 'format_3') return 'format_3';
    return 'format_1';
}

function normalizeLangCode(langCode) {
    const value = String(langCode || '').trim().toLowerCase();
    return ['fr', 'en', 'ar'].includes(value) ? value : 'fr';
}

function buildReportUrl(rowData, formatCode, langCode) {
    const params = new URLSearchParams({
        id_demande: getDemandId(rowData),
        format: normalizeFormatCode(formatCode),
        lang: normalizeLangCode(langCode)
    });
    return `${REPORT_URL}?${params.toString()}`;
}

function openReport(rowData, formatCode, langCode) {
    window.open(buildReportUrl(rowData, formatCode, langCode), '_blank');
}


function renderFichesPaillasseLaborantinChart(selector, rows) {
    resetChartContainer(selector);
    const source = (rows || []).map(r => ({
        laborantin: r.laborantin || r.nom_laborantin || 'Non affecté',
        affectees: Number(r.fiches_affectees ?? r.affectees ?? 0),
        en_cours: Number(r.fiches_en_cours ?? r.en_cours ?? 0),
        terminees: Number(r.fiches_terminees ?? r.terminees ?? 0),
        total: Number(r.total_fiches ?? r.fiches ?? r.total ?? 0)
    })).map(r => ({ ...r, affectees: r.affectees || Math.max(0, r.total - r.en_cours - r.terminees) }))
      .sort((a, b) => b.total - a.total).slice(0, 12);
    if (!source.length) { $(selector).html('<div class="chart-empty">Aucune donnée disponible</div>'); return; }
    $(selector).dxChart({
        dataSource: source,
        rotated: true,
        commonSeriesSettings: { argumentField: 'laborantin', type: 'stackedBar' },
        series: [
            { valueField: 'affectees', name: 'Affectées', color: DASHBOARD_COLORS.blue },
            { valueField: 'en_cours', name: 'En cours', color: DASHBOARD_COLORS.orange },
            { valueField: 'terminees', name: 'Terminées', color: DASHBOARD_COLORS.green }
        ],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        argumentAxis: { tick: { visible: false }, grid: { visible: false } },
        valueAxis: { tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: { enabled: true, customizeTooltip: a => ({ text: `${a.argumentText}\n${a.seriesName}: ${a.valueText}` }) },
        onDone: function () { roundBarChartShapes(selector); }
    });
}

function renderOrdresStatusChart(selector, rows) {
    resetChartContainer(selector);
    const source = (rows || []).map(r => ({ statut: r.statut_ordre || r.statut || r.label || 'Non défini', total: Number(r.total || r.count || 0) }));
    if (!source.length) { $(selector).html('<div class="chart-empty">Aucune donnée disponible</div>'); return; }
    $(selector).dxPieChart({
        type: 'doughnut', innerRadius: 0.62, dataSource: source,
        palette: [DASHBOARD_COLORS.gray, DASHBOARD_COLORS.blue, DASHBOARD_COLORS.orange, DASHBOARD_COLORS.green, DASHBOARD_COLORS.purple],
        series: [{ argumentField: 'statut', valueField: 'total', label: { visible: true, connector: { visible: false }, customizeText: a => `${a.argumentText} ${a.valueText}` } }],
        legend: { visible: false }, tooltip: { enabled: true }
    });
}

function renderDelayEvolutionChart(selector, rows) {
    resetChartContainer(selector);
    const source = (rows || []).map(r => ({ periode: r.mois || r.semaine || r.date || r.mois_annee || '-', delai: Number(r.delai_moyen_jours ?? r.delai_moyen ?? r.value ?? 0) })).filter(r => r.periode !== '-');
    if (!source.length) { $(selector).html('<div class="chart-empty">Aucune donnée disponible</div>'); return; }
    $(selector).dxChart({
        dataSource: source,
        commonSeriesSettings: { argumentField: 'periode', type: 'spline', point: { visible: true, size: 6 }, width: 3 },
        series: [{ valueField: 'delai', name: 'Délai moyen', color: DASHBOARD_COLORS.purple }],
        legend: { visible: false },
        argumentAxis: { label: { overlappingBehavior: 'stagger' }, tick: { visible: false }, grid: { visible: false } },
        valueAxis: { title: 'Jours', tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: { enabled: true, customizeTooltip: a => ({ text: `${a.argumentText}: ${a.valueText} j` }) }
    });
}

function renderReportValidationDelayChart(selector, rows) {
    resetChartContainer(selector);
    const source = (rows || []).map(r => ({ periode: r.mois || r.date || r.mois_annee || '-', delai: Number(r.delai_moyen_validation ?? r.delai_validation ?? r.value ?? 0) })).filter(r => r.periode !== '-');
    if (!source.length) { $(selector).html('<div class="chart-empty">Aucune donnée disponible</div>'); return; }
    $(selector).dxChart({
        dataSource: source,
        commonSeriesSettings: { argumentField: 'periode', type: 'spline', point: { visible: true, size: 6 }, width: 3 },
        series: [{ valueField: 'delai', name: 'Délai validation', color: DASHBOARD_COLORS.blue }],
        legend: { visible: false },
        argumentAxis: { label: { overlappingBehavior: 'stagger' }, tick: { visible: false }, grid: { visible: false } },
        valueAxis: { title: 'Jours', tick: { visible: false }, grid: { color: '#e3ebf7' } }, tooltip: { enabled: true }
    });
}

function renderAlertesGravityChart(selector, rows) {
    renderRiskAlertsChart(selector, rows);
}

function renderAlertesKpis(data) {
    const rows = data.alertes_list || [];
    const critical = rows.filter(r => getRiskKey(r.gravite) === 'critique').length;
    const high = rows.filter(r => ['eleve', 'elevé'].includes(getRiskKey(r.gravite))).length;
    $('#alertesKpiGrid').html([
        renderKpiCard('Total alertes', rows.length, 'Toutes gravités', 'primary'),
        renderKpiCard('Critiques', critical, 'Priorité maximale', critical ? 'danger' : 'success'),
        renderKpiCard('Élevées', high, 'À surveiller', high ? 'warning' : 'success'),
        renderKpiCard('Retards prédits', rows.filter(r => String(r.type_alerte || '').toUpperCase() === 'RETARD_PREDIT').length, 'ML', 'violet')
    ].join(''));
}


/* ==========================================================
   SURGICAL FINAL OVERRIDES - 2026-05-08
   Keeps existing charts/drilldowns, fixes only the latest issues:
   - echantillon drill-down affects all stats/grids
   - ouvertes from raw status in KPI distribution
   - reports: type vs delivery status separation
   - reports prêts <= terminées
   - existing report shown in Générer rapport
   - fiche/ordre grid = one row per ordre, grouped by demande
   - real result shown
   - clean alert labels and risk charts
========================================================== */

function normalizeKeyFinal(value) {
    return String(value ?? '').trim().toLowerCase();
}

function getDemandMatchKeysFinal(row) {
    const keys = [];
    const values = [
        row?.id_demande,
        row?.demande_id,
        row?.id_demande_analyse_bk,
        row?.id_demande_analyse_sk,
        row?.reference_demande,
        row?.ref_demande,
        row?.ref_demande_analyse
    ];
    values.forEach(v => {
        const s = normalizeKeyFinal(v);
        if (s && s !== '-') keys.push(s);
    });
    return keys;
}

function rowsMatchDemandFinal(demande, item) {
    const dKeys = new Set(getDemandMatchKeysFinal(demande));
    return getDemandMatchKeysFinal(item).some(k => dKeys.has(k));
}

function formatAlertTypeLabel(type) {
    const t = String(type || '').trim().toUpperCase();
    const labels = {
        RETARD_PREDIT: 'Retard prédit',
        RISQUE_CRITIQUE: 'Risque critique',
        RISQUE_ELEVE: 'Risque élevé',
        DELAI_LONG: 'Dépassement de délai',
        NON_CONFORMITE: 'Non-conformité',
        RAPPORT_MANQUANT: 'Rapport manquant',
        RAPPORT_EN_ATTENTE: 'Rapport en attente',
        RESULTAT_MANQUANT: 'Résultat manquant',
        SANS_LABORANTIN: 'Sans laborantin'
    };
    return labels[t] || textOrDash(type);
}

function explainAlertType(type) {
    const t = String(type || '').trim().toUpperCase();
    const map = {
        RETARD_PREDIT: 'Le modèle prédit un retard potentiel.',
        RISQUE_CRITIQUE: 'La demande est classée comme critique et doit être prioritaire.',
        RISQUE_ELEVE: 'La demande présente un risque élevé et nécessite un suivi rapproché.',
        DELAI_LONG: 'La demande dépasse le seuil opérationnel de délai.',
        NON_CONFORMITE: 'Un échantillon lié à la demande est non conforme.',
        RAPPORT_MANQUANT: 'Les résultats existent mais aucun rapport n’est généré.',
        RAPPORT_EN_ATTENTE: 'Un rapport existe mais il n’est pas encore livré ou finalisé.',
        RESULTAT_MANQUANT: 'Des ordres existent mais les résultats sont incomplets.',
        SANS_LABORANTIN: 'Une fiche ou un ordre n’a pas encore de laborantin affecté.'
    };
    return map[t] || 'Alerte opérationnelle à vérifier.';
}

function isNumericOnly(value) {
    return /^[0-9]+$/.test(String(value || '').trim());
}


function hasExistingReport(row) {
    return Boolean(
        row?.id_rapport ||
        row?.reference_rapport ||
        row?.rapport ||
        row?.rapport_url ||
        row?.url_rapport ||
        row?.lien_rapport ||
        Number(row?.nb_rapports || 0) > 0 ||
        Number(row?.has_rapport_final || 0) === 1 ||
        Number(row?.rapport_final_flag || 0) === 1
    );
}

function getExistingReportLabel(rowData) {
    if (rowData?.reference_rapport) return rowData.reference_rapport;
    if (rowData?.id_rapport) return 'Rapport #' + rowData.id_rapport;
    if (rowData?.rapport) return extractRapportFilename(rowData.rapport);
    if (Number(rowData?.nb_rapports || 0) > 0) return rowData.nb_rapports + ' rapport(s)';
    if (Number(rowData?.has_rapport_final || 0) === 1) return 'Rapport final existant';
    return '';
}

function getExistingReportUrl(rowData) {
    const directUrl = rowData?.rapport_url || rowData?.url_rapport || rowData?.lien_rapport || rowData?.rapport_path || rowData?.rapport_fr_url;
    if (directUrl && String(directUrl).trim() !== '') return String(directUrl).trim();
    if (hasExistingReport(rowData) && getDemandId(rowData)) {
        return buildReportUrl(rowData, normalizeFormatCode(rowData.report_layout || 'format_1'), 'fr');
    }
    return '';
}

function isRawStatusTerminee(row) {
    return getRawStatusCategory(row) === 'terminée';
}

function isReportReadyForKpi(row) {
    return isRawStatusTerminee(row) && (
        Number(row?.can_generate_report || 0) === 1 ||
        hasExistingReport(row)
    );
}


function canGenerateReport(rowData) {
    if (rowData?.can_generate_report !== undefined) return Number(rowData.can_generate_report) === 1;
    const completeResults = Number(rowData?.nb_ordres || 0) > 0 && Number(rowData?.nb_resultats || 0) >= Number(rowData?.nb_ordres || 0);
    return Boolean(getDemandId(rowData)) && (normalizeDemandeStatut(rowData) === 'clôturée' || completeResults || hasExistingReport(rowData));
}

function canTranslateReport(rowData) {
    if (rowData?.can_translate_report !== undefined) return Number(rowData.can_translate_report) === 1;
    return canGenerateReport(rowData);
}

function getReportBlockReason(rowData) {
    const status = String(rowData?.report_status || '').trim().toUpperCase();
    if (status === 'READY' || status === 'FINAL_EXISTS' || Number(rowData?.can_generate_report || 0) === 1 || hasExistingReport(rowData)) return '';
    if (rowData?.report_block_reason) return rowData.report_block_reason;
    if (!getDemandId(rowData)) return 'Demande invalide';
    if (Number(rowData?.nb_ordres || 0) === 0) return 'Aucun ordre d’analyse';
    if (Number(rowData?.nb_resultats || 0) < Number(rowData?.nb_ordres || 0)) return 'Résultats incomplets';
    return 'Rapport non prêt';
}

function renderGenerateReportButtons(container, rowData) {
    const rowKey = rowData.row_key || getDemandId(rowData) || getRefDemande(rowData);
    const initialFormat = normalizeFormatCode(selectedFormats[rowKey] || rowData.report_layout || rowData.default_layout || 'format_1');
    selectedFormats[rowKey] = initialFormat;

    const reportReady = canGenerateReport(rowData);
    const translationReady = canTranslateReport(rowData);
    const $wrap = $('<div>').addClass('generate-report-box visible-report-box');

    const existingReportLabel = getExistingReportLabel(rowData);
    const existingReportUrl = getExistingReportUrl(rowData);
    if (existingReportLabel) {
        const $existing = $('<div>').addClass('existing-report-line');
        if (existingReportUrl) {
            $('<a>', { href: existingReportUrl, target: '_blank', text: existingReportLabel, title: 'Ouvrir le rapport existant' }).appendTo($existing);
        } else {
            $('<span>').text(existingReportLabel).attr('title', 'Rapport existant').appendTo($existing);
        }
        $wrap.append($existing);
    }

    const $formatLabel = $('<div>').addClass('generate-report-label').text('Format');
    const $formatRow = $('<div>').addClass('generate-report-formats');

    [
        { code: 'format_1', label: '1' },
        { code: 'format_2', label: '2' },
        { code: 'format_3', label: '3' }
    ].forEach(format => {
        const $btn = $('<button>', {
            type: 'button',
            class: 'btn-format' + (initialFormat === format.code ? ' active' : ''),
            text: format.label,
            title: 'Choisir le format ' + format.label
        });
        $btn.on('click', function () {
            selectedFormats[rowKey] = format.code;
            $wrap.find('.btn-format').removeClass('active');
            $(this).addClass('active');
        });
        $formatRow.append($btn);
    });

    const $reportLabel = $('<div>').addClass('generate-report-label').text('Générer rapport');
    const $langRow = $('<div>').addClass('generate-report-langs');

    [
        { code: 'fr', label: 'FR', enabled: reportReady },
        { code: 'en', label: 'EN', enabled: translationReady },
        { code: 'ar', label: 'AR', enabled: translationReady }
    ].forEach(lang => {
        const $btn = $('<button>', {
            type: 'button',
            class: 'btn-generate-report btn-generate-' + lang.code,
            text: lang.label,
            title: lang.code === 'fr' ? 'Générer le rapport original' : 'Générer le rapport traduit',
            disabled: !lang.enabled
        });
        $btn.on('click', function () {
            if (!lang.enabled) {
                DevExpress.ui.notify(getReportBlockReason(rowData), 'warning', 2500);
                return;
            }
            openReport(rowData, selectedFormats[rowKey] || 'format_1', lang.code);
        });
        $langRow.append($btn);
    });

    const blockReason = getReportBlockReason(rowData);
    if (!reportReady && blockReason) $('<div>').addClass('generate-report-blocked').text(blockReason).appendTo($wrap);

    $wrap.append($formatLabel, $formatRow, $reportLabel, $langRow);
    $(container).empty().append($wrap);
}


function renderRiskAlertsChart(selector, rows) {
    resetChartContainer(selector);
    const source = (rows || [])
        .map(r => {
            const niveau = r.niveau_risque || r.gravite || r.label || 'Non défini';
            const key = getRiskKey(niveau).replace('é', 'e');
            return {
                niveau,
                key,
                total: Number(r.total || r.count || r.value || 0)
            };
        })
        .filter(r => r.total > 0 && !['non defini', 'non défini', 'none', '-', ''].includes(r.key))
        .map(r => ({
            ...r,
            rank: r.key === 'critique' ? 1 : r.key === 'eleve' ? 2 : r.key === 'moyen' ? 3 : r.key === 'faible' ? 4 : 5,
            color: r.key === 'critique' ? '#7f1d1d' : r.key === 'eleve' ? '#ef4444' : r.key === 'moyen' ? '#f59e0b' : r.key === 'faible' ? '#10b981' : '#64748b'
        }))
        .sort((a, b) => a.rank - b.rank);

    if (!source.length) {
        $(selector).html('<div class="chart-empty">Aucune demande à risque définie</div>');
        return;
    }

    $(selector).dxChart({
        dataSource: source,
        rotated: true,
        commonSeriesSettings: { argumentField: 'niveau', type: 'bar' },
        series: [{ valueField: 'total', name: 'Demandes' }],
        customizePoint: function () { return { color: this.data.color }; },
        legend: { visible: false },
        argumentAxis: { tick: { visible: false }, grid: { visible: false } },
        valueAxis: { tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: { enabled: true, customizeTooltip: arg => ({ text: `${arg.argumentText} : ${arg.valueText} demande(s)` }) },
        margin: { top: 10, right: 18, left: 10, bottom: 6 },
        onPointClick: function (e) {
            const value = e.target.originalArgument;
            drilldown.alerte_gravite = drilldown.alerte_gravite === value ? null : value;
            updateContextBar();
            if (originalData) filterDashboardData(originalData);
        },
        onDone: function () { roundBarChartShapes(selector); }
    });
}

function renderAlertesTypeChart(selector, rows) {
    resetChartContainer(selector);
    const source = (rows || [])
        .map(r => {
            const rawType = r.type_alerte || r.label || '-';
            return {
                type_raw: rawType,
                type: formatAlertTypeLabel(rawType),
                total: Number(r.total || r.count || 0),
                explanation: explainAlertType(rawType)
            };
        })
        .filter(r => r.total > 0)
        .sort((a, b) => b.total - a.total);

    if (!source.length) {
        $(selector).html('<div class="chart-empty">Aucune donnée disponible</div>');
        return;
    }

    $(selector).dxChart({
        dataSource: source,
        rotated: true,
        commonSeriesSettings: { argumentField: 'type', type: 'bar' },
        series: [{ valueField: 'total', name: 'Alertes', color: DASHBOARD_COLORS.orange }],
        legend: { visible: false },
        argumentAxis: { tick: { visible: false }, grid: { visible: false } },
        valueAxis: { tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: { enabled: true, customizeTooltip: arg => ({ text: `${arg.point.data.type} : ${arg.valueText} alerte(s)\n${arg.point.data.explanation}` }) },
        onDone: function () { roundBarChartShapes(selector); }
    });
}



function calculateAverageReportValidationDelay(rows) {
    let totalMinutes = 0;
    let count = 0;
    (rows || []).forEach(row => {
        const start = parseDateSafe(row.date_creation || row.date_edition);
        const end = parseDateSafe(row.date_validation);
        if (!start || !end || end < start) return;
        totalMinutes += Math.round((end.getTime() - start.getTime()) / 60000);
        count++;
    });
    return count ? formatMinutesToDayHourMinute(totalMinutes / count) : '-';
}


function getReportDateInfo(row) {
    const delivered = isReportDelivered(row);
    const display = row.date_livraison || row.date_livraison_display || row.date_livraison_estimee || row.date_validation || row.date_validation_demande || '';
    let color = row.date_livraison_color || (delivered ? 'green' : 'orange');
    if (!delivered && row.date_livraison_estimee) {
        const estimated = parseDateSafe(row.date_livraison_estimee);
        const ref = new Date();
        if (estimated && estimated < ref) color = 'red';
    }
    return { display, color };
}

function normalizeOrdreFicheRow(row) {
    const idFiche = row.id_fiche || row.id_fiche_paillasse || row.fiche_paillasse_id || row.fiche_id || null;
    const referenceFiche = row.reference_fiche || row.ref_fiche || row.reference_fiche_paillasse || row.ref_fiche_paillasse || (idFiche ? 'FICHE-' + idFiche : '-');
    const idOrdre = row.id_ordre || row.ordre_id || row.id_ordre_analyse || row.id_ordre_analyse_sk || row.reference_ordre || row.ordre_reference || null;

    return {
        ...row,
        row_key: row.row_key || `${row.id_demande || row.demande_id || row.reference_demande || 'D'}_${idFiche || 'F'}_${idOrdre || Math.random()}`,
        id_demande: row.id_demande || row.demande_id || row.id_demande_analyse_bk || row.id_demande_analyse_sk,
        reference_demande: row.reference_demande || row.ref_demande || row.ref_demande_analyse || row.id_demande || '-',
        id_fiche: idFiche,
        reference_fiche: referenceFiche,
        id_ordre: idOrdre,
        reference_ordre: row.reference_ordre || row.ordre_reference || row.ref_ordre || (idOrdre ? 'ORD-' + idOrdre : '-'),
        analyse_nom: row.analyse_nom || row.nom_analyse || row.libelle_analyse || row.designation_analyse || row.analyse || '-',
        laborantin_nom: row.laborantin_nom || row.nom_laborantin || row.laborantin || 'Non affecté',
        statut_ordre: row.statut_ordre || row.statut || row.status || '-',
        date_affectation: row.date_affectation || row.date_assignation || null,
        date_validation: row.date_validation || row.date_validation_ordre || null,
        duree_analyse_jours: row.duree_analyse_jours,
        delai_prise_en_charge_jours: row.delai_prise_en_charge_jours,
        resultat: row.resultat || row.resultat_analyse || row.valeur_resultat || '',
        unite: row.unite || row.unite_resultat || '',
        methode: row.methode || row.methode_analyse || '',
        result_note: row.result_note || row.note_resultat || ''
    };
}

function renderActualResultCell(container, rowData) {
    const resultat = String(rowData.resultat || '').trim();
    const unite = String(rowData.unite || '').trim();
    const methode = String(rowData.methode || '').trim();
    const note = String(rowData.result_note || '').trim();
    const $wrap = $('<div>').addClass('actual-result-cell');

    if (!resultat) {
        $('<span>').addClass('result-badge missing').text('Résultat manquant').appendTo($wrap);
        $(container).append($wrap);
        return;
    }

    $('<strong>').addClass('actual-result-value').text(unite ? `${resultat} ${unite}` : resultat).appendTo($wrap);
    if (methode) $('<small>').addClass('actual-result-meta').text('Méthode : ' + methode).appendTo($wrap);
    if (note) $('<small>').addClass('actual-result-note').text(note).appendTo($wrap);
    $(container).append($wrap);
}


function renderFichesGrid(dataSource) {
    resetGridContainer('#fichesTable');

    const ficheRows = buildFichePaillasseGridRows(dataSource);

    $('#fichesTable').dxDataGrid({
        dataSource: ficheRows,
        keyExpr: 'row_key',
        showBorders: true,
        showColumnLines: true,
        showRowLines: true,
        columnAutoWidth: false,
        columnHidingEnabled: false,
        wordWrapEnabled: true,
        rowAlternationEnabled: true,
        hoverStateEnabled: true,
        width: '100%',
        scrolling: { mode: 'standard', useNative: false, showScrollbar: 'always', scrollByContent: true, scrollByThumb: true },
        paging: { pageSize: 12 },
        pager: { showPageSizeSelector: true, allowedPageSizes: [12, 24, 50], showInfo: true, showNavigationButtons: true },
        filterRow: { visible: true, applyFilter: 'auto' },
        headerFilter: { visible: true },
        searchPanel: { visible: true, width: 260, placeholder: 'Rechercher fiche paillasse...' },
        grouping: { autoExpandAll: false },
        groupPanel: { visible: false },
        onToolbarPreparing: function (e) { addGridExportButtons(e, 'fiches_paillasse'); },
        columns: [
            { dataField: 'reference_fiche', caption: 'Fiche paillasse', width: 180, sortOrder: 'asc', cellTemplate: function (container, options) { $('<span>').addClass('fiche-pill').text(options.value || '-').appendTo(container); } },
            { dataField: 'laborantin_nom', caption: 'Laborantin', minWidth: 210, cellTemplate: function (container, options) { const value = options.value || 'Non affecté'; $('<span>').addClass('laborantin-pill ' + (value === 'Non affecté' ? 'missing' : 'assigned')).text(value).appendTo(container); } },
            { dataField: 'statut_fiche', caption: 'Statut fiche', width: 140, cellTemplate: function (container, options) { const value = String(options.value || '-'); const css = value.toLowerCase().includes('termin') ? 'closed' : (value.toLowerCase().includes('cours') ? 'progress' : 'open'); $('<span>').addClass('badge-status ' + css).text(value).appendTo(container); } },
            { dataField: 'date_affectation', caption: 'Affectation', dataType: 'date', width: 135 },
            { dataField: 'date_validation', caption: 'Validation', dataType: 'date', width: 135 },
            { dataField: 'nb_ordres', caption: 'Ordres', width: 105, alignment: 'center' },
            { dataField: 'ordres_sans_laborantin', caption: 'Sans laborantin', width: 150, alignment: 'center', cellTemplate: function (container, options) { $('<span>').addClass(Number(options.value || 0) > 0 ? 'delay-pill' : 'progress-pill').text(options.value || 0).appendTo(container); } },
            { dataField: 'resultat_resume', caption: 'Résultats', width: 120, alignment: 'center', cellTemplate: function (container, options) { $('<span>').addClass('progress-pill').text(options.value || '-').appendTo(container); } }
        ]
    });
}

function renderRapportsGrid(dataSource) {
    resetGridContainer('#rapportsTable');
    $('#rapportsTable').dxDataGrid({
        dataSource,
        keyExpr: 'id_rapport',
        showBorders: true,
        columnAutoWidth: true,
        rowAlternationEnabled: true,
        hoverStateEnabled: true,
        paging: { pageSize: 10 },
        pager: { showPageSizeSelector: true, allowedPageSizes: [10, 20, 50], showInfo: true },
        filterRow: { visible: true, applyFilter: 'auto' },
        headerFilter: { visible: true },
        searchPanel: { visible: true, width: 240, placeholder: 'Rechercher...' },
        onToolbarPreparing: function (e) { addGridExportButtons(e, 'rapports'); },
        columns: [
            { dataField: 'reference_rapport', caption: 'Réf rapport', minWidth: 140 },
            { dataField: 'reference_demande', caption: 'Réf demande', minWidth: 130 },
            { dataField: 'client_nom', caption: 'Client', minWidth: 170 },
            { dataField: 'secteur_nom', caption: 'Secteur', width: 140 },
            { dataField: 'type_rapport', caption: 'Type rapport', width: 135 },
            { dataField: 'statut_livraison', caption: 'Statut livraison', width: 155, cellTemplate: function (container, options) { const value = String(options.value || 'En attente'); const css = value === 'Livré' ? 'delivered' : 'pending'; $('<span>').addClass('report-status-pill ' + css).text(value).appendTo(container); } },
            { dataField: 'date_creation', caption: 'Date création', dataType: 'date', width: 130 },
            { dataField: 'date_validation', caption: 'Date validation', dataType: 'date', width: 130 },
            { caption: 'Date livraison', width: 150, cellTemplate: function (container, options) { const info = getReportDateInfo(options.data); $('<span>').addClass('delivery-date-pill ' + info.color).text(info.display || '-').appendTo(container); } },
            { dataField: 'delai_livraison_jours', caption: 'Délai livraison', width: 130, customizeText: function (options) { return formatDaysValue(options.value); } },
            { dataField: 'validateur', caption: 'Validateur', minWidth: 150 }
        ]
    });
}

function renderAlertesGrid(dataSource) {
    resetGridContainer('#alertesTable');
    $('#alertesTable').dxDataGrid({
        dataSource,
        keyExpr: 'id_alerte',
        showBorders: true,
        columnAutoWidth: false,
        wordWrapEnabled: true,
        rowAlternationEnabled: true,
        hoverStateEnabled: true,
        paging: { pageSize: 12 },
        pager: { showPageSizeSelector: true, allowedPageSizes: [12, 24, 50], showInfo: true },
        filterRow: { visible: true, applyFilter: 'auto' },
        headerFilter: { visible: true },
        searchPanel: { visible: true, width: 260, placeholder: 'Rechercher une alerte...' },
        onToolbarPreparing: function (e) { addGridExportButtons(e, 'alertes_delais'); },
        columns: [
            { dataField: 'gravite', caption: 'Gravité', width: 125, cellTemplate: function (c, o) { renderRiskBadge(c, o.value); } },
            { dataField: 'type_alerte_label', caption: 'Type alerte', width: 190, calculateCellValue: r => r.type_alerte_label || formatAlertTypeLabel(r.type_alerte) },
            { dataField: 'reference_demande', caption: 'Réf demande', width: 150 },
            { dataField: 'client_nom', caption: 'Client', width: 180 },
            { dataField: 'secteur_nom', caption: 'Secteur', width: 145 },
            { dataField: 'statut_operationnel', caption: 'Statut', width: 145, cellTemplate: (c, o) => renderOperationalStatusBadge(c, o.value) },
            { dataField: 'delai_total_demande', caption: 'Délai actuel', width: 145, customizeText: o => formatDaysValue(o.value) },
            { dataField: 'delai_predit_jours', caption: 'Délai prédit', width: 145, customizeText: o => formatDaysValue(o.value) },
            { dataField: 'jours_retard_predit', caption: 'Retard prédit', width: 145, customizeText: o => formatDaysValue(o.value) },
            { dataField: 'message_alerte', caption: 'Message', width: 300, calculateCellValue: r => r.message_alerte || explainAlertType(r.type_alerte) },
            { dataField: 'action_recommandee', caption: 'Action recommandée', width: 270 }
        ]
    });
}


function renderDemandeMasterDetail(container, rowData) {
    const avancement = `${Number(rowData.nb_resultats || 0)} / ${Number(rowData.nb_ordres || 0)}`;
    const $detail = $('<div>').addClass('demande-detail demande-detail-lite');
    const rawStatus = getRawStatus(rowData);
    const opStatus = rowData.statut_operationnel || rowData.statut_dashboard || normalizeDemandeStatut(rowData);
    const rawLine = rawStatus && rawStatus !== '-' && rawStatus.toLowerCase() !== String(opStatus).toLowerCase()
        ? `<small>Brut : ${escapeHtml(rawStatus)}</small>`
        : '';

    const $summary = $(
        `<div class="demande-detail-summary compact-summary">
            <div><span>Demande</span><strong>${escapeHtml(rowData.reference_demande || '-')}</strong></div>
            <div><span>Client</span><strong>${escapeHtml(rowData.client_nom || '-')}</strong></div>
            <div><span>Statut</span><strong>${escapeHtml(opStatus || '-')}</strong>${rawLine}</div>
            <div><span>Délai</span><strong>${escapeHtml(formatDaysValue(rowData.delai_total_demande))}</strong></div>
            <div><span>Avancement</span><strong>${escapeHtml(avancement)}</strong></div>
        </div>`
    );

    const $tabs = $('<div>').addClass('lite-tabs');
    const $content = $('<div>').addClass('lite-tab-content');
    const tabs = [
        { key: 'resume', title: 'Résumé' },
        { key: 'echantillons', title: 'Échantillons' },
        { key: 'ordres', title: 'Ordres + résultats' },
        { key: 'alertes', title: 'Alertes' }
    ];

    function setTab(key) {
        $tabs.find('button').removeClass('active');
        $tabs.find(`[data-key="${key}"]`).addClass('active');
        $content.empty();

        if (key === 'resume') {
            $content.append(`
                <div class="detail-card-grid compact-cards">
                    <div class="detail-card"><h5>Identité</h5><p><b>Réf demande:</b> ${escapeHtml(rowData.reference_demande || '-')}</p><p><b>Client:</b> ${escapeHtml(rowData.client_nom || '-')}</p><p><b>Secteur:</b> ${escapeHtml(rowData.secteur_nom || '-')}</p></div>
                    <div class="detail-card"><h5>Dates</h5><p><b>Date demande:</b> ${escapeHtml(rowData.date_demande || '-')}</p><p><b>Date réception:</b> ${escapeHtml(rowData.date_reception || rowData.date_reception_echantillon || '-')}</p></div>
                    <div class="detail-card"><h5>Délais</h5><p><b>Délai total:</b> ${escapeHtml(formatDaysValue(rowData.delai_total_demande))}</p><p><b>Délai prédit:</b> ${escapeHtml(formatDaysValue(rowData.delai_predit_jours))}</p></div>
                    <div class="detail-card"><h5>Rapport</h5><p><b>État:</b> ${escapeHtml(getReportStatusDisplay(rowData))}</p><p><b>Rapport:</b> ${escapeHtml(getReportDisplayLine(rowData))}</p><p><b>Blocage:</b> ${escapeHtml(getReportBlockReason(rowData) || '-')}</p></div>
                </div>
            `);
        }

        if (key === 'echantillons') {
            const rows = getAllDetailRows(rowData, 'echantillons');
            $content.append(renderDetailTableSafe(rows, [
                { caption: 'Réf labo', value: r => r.ref_labo },
                { caption: 'Code', value: r => r.code_echantillon },
                { caption: 'Type', value: r => r.type_echantillon },
                { caption: 'Produit', value: r => r.nom_produit },
                { caption: 'Lot', value: r => r.numero_lot },
                { caption: 'DLC', value: r => r.dlc },
                { caption: 'Temp.', value: r => r.temperature_reception },
                { caption: 'Conformité', value: r => r.est_conforme, render: function (td, value) { renderConformityBadge(td, value); } },
                { caption: 'Réception', value: r => r.date_reception }
            ], 'Aucun échantillon lié à cette demande.'));
        }

        if (key === 'ordres') {
            const rows = getAllDetailRows(rowData, 'ordres').map(normalizeOrdreFicheRow);
            $content.append(renderDetailTableSafe(rows, [
                { caption: 'Fiche', value: r => r.reference_fiche },
                { caption: 'Réf ordre', value: r => r.reference_ordre },
                { caption: 'Analyse', value: r => r.analyse_nom },
                { caption: 'Laborantin', value: r => r.laborantin_nom },
                { caption: 'Statut', value: r => r.statut_ordre, render: function (td, value) { renderOperationalStatusBadge(td, value || '-'); } },
                { caption: 'Affectation', value: r => r.date_affectation },
                { caption: 'Validation', value: r => r.date_validation },
                { caption: 'Durée', value: r => formatDaysValue(r.duree_analyse_jours) },
                { caption: 'Résultat', value: r => r.resultat, render: function (td, value, row) { renderActualResultCell(td, row); } }
            ], 'Aucun ordre / fiche lié à cette demande.'));
        }

        if (key === 'alertes') {
            const rows = getAllDetailRows(rowData, 'alertes');
            if (!rows.length) {
                $content.append($('<div>').addClass('detail-placeholder').text('Aucune alerte pour cette demande.'));
                return;
            }
            const $wrap = $('<div>').addClass('alert-card-list');
            rows.forEach(a => {
                const type = a.type_alerte || '-';
                $wrap.append(`
                    <div class="alert-card severity-${escapeHtml(getRiskKey(a.gravite || 'moyen'))}">
                        <div><span>${escapeHtml(a.gravite || '-')}</span><strong>${escapeHtml(formatAlertTypeLabel(type))}</strong></div>
                        <p>${escapeHtml(a.message_alerte || explainAlertType(type))}</p>
                        <small>${escapeHtml(explainAlertType(type))}</small>
                        <em>${escapeHtml(a.action_recommandee || '-')}</em>
                    </div>
                `);
            });
            $content.append($wrap);
        }
    }

    tabs.forEach((tab, index) => {
        const $btn = $('<button type="button">').attr('data-key', tab.key).text(tab.title);
        if (index === 0) $btn.addClass('active');
        $btn.on('click', function () { setTab(tab.key); });
        $tabs.append($btn);
    });

    $detail.append($tabs, $content);
    $(container).empty().append($detail);
    setTab('resume');
}
function normalizeConformite(v) {
    const s = String(v || '').trim().toLowerCase();
    if (!s) return 'conforme';
    if (s.includes('non') || s.includes('nc') || ['0', 'false', 'ko'].includes(s)) return 'non conforme';
    return 'conforme';
}

window.selectedFormats = window.selectedFormats || {};
function hasDom(selector) {
    return !!($(selector).length);
}

function safeDashboardCall(label, fn) {
    try {
        return fn();
    } catch (err) {
        console.error('[Dashboard] ' + label + ' failed:', err);
        return null;
    }
}

function normalizeSectorDelayRows(rows) {
    return (rows || []).map(r => {
        const minutesRaw = r.delai_moyen_minutes ?? r.moyenne_delai_minutes;
        const hoursRaw = r.delai_moyen_heures ?? r.moyenne_delai_heures;
        const daysRaw = r.delai_moyen_jours ?? r.delai_moyen ?? r.value;

        let minutes = Number(minutesRaw);
        if (!Number.isFinite(minutes) || minutes < 0) {
            const hours = Number(hoursRaw);
            if (Number.isFinite(hours) && hours >= 0) minutes = hours * 60;
        }
        if (!Number.isFinite(minutes) || minutes < 0) {
            const days = Number(daysRaw);
            minutes = Number.isFinite(days) && days >= 0 ? days * 1440 : 0;
        }

        return {
            nom_secteur: r.nom_secteur || r.secteur || r.secteur_nom || 'Secteur inconnu',
            total: Number(r.total ?? r.total_demandes ?? r.nb_demandes ?? r.count ?? 0),
            delai_moyen_minutes: Math.round(minutes),
            delai_moyen_heures: Number((minutes / 60).toFixed(2)),
            delai_moyen_affichage: r.delai_moyen_affichage || formatMinutesToDayHourMinute(minutes)
        };
    }).filter(r => r.total > 0 || r.delai_moyen_minutes > 0);
}

function getRowConformity(row) {
    return normalizeConformite(
        row?.est_conforme_decision ||
        row?.est_conforme ||
        row?.conformite ||
        row?.statut_conformite ||
        row?.echantillon_conforme ||
        ''
    );
}


function renderConformiteChart(selector, legendSelector, conformes, nonConformes) {
    if (!hasDom(selector)) return;
    resetChartContainer(selector);

    const pct = getConformitePercentages(conformes, nonConformes);
    const clickConformity = function (value) {
        drilldown.conformite = drilldown.conformite === value ? null : value;
        updateContextBar();
        if (originalData) filterDashboardData(originalData);
    };

    $(selector).dxPieChart({
        type: 'doughnut',
        innerRadius: 0.62,
        palette: [DASHBOARD_COLORS.green, DASHBOARD_COLORS.red],
        dataSource: [
            { type: 'Conforme', key: 'conforme', count: conformes, pct: pct.conformesPct },
            { type: 'Non conforme', key: 'non conforme', count: nonConformes, pct: pct.nonConformesPct }
        ],
        series: [{
            argumentField: 'type',
            valueField: 'count',
            border: { visible: false },
            label: {
                visible: true,
                connector: { visible: false },
                customizeText: arg => `${arg.argumentText} ${Number(arg.point.data.pct).toFixed(1)}%`
            }
        }],
        legend: { visible: false },
        tooltip: {
            enabled: true,
            customizeTooltip: arg => ({ text: `${arg.argumentText} : ${arg.valueText} (${Number(arg.point.data.pct).toFixed(1)}%)` })
        },
        centerTemplate: function (component, element) {
            element.append($('<div>').addClass('pie-center-label').html(`<strong>${pct.total}</strong><span>échantillons</span>`).get(0));
        },
        onPointClick: function (e) {
            clickConformity(e.target.data.key);
        }
    });

    if (hasDom(legendSelector)) {
        $(legendSelector).html(`
          <button type="button" class="stat-item conformity-card ok conformity-filter" data-conformity="conforme"><strong>Conformes</strong><em>${Number(pct.conformesPct).toFixed(1)}%</em><span>${conformes} / ${pct.total}</span></button>
          <button type="button" class="stat-item conformity-card ko conformity-filter" data-conformity="non conforme"><strong>Non conformes</strong><em>${Number(pct.nonConformesPct).toFixed(1)}%</em><span>${nonConformes} / ${pct.total}</span></button>
        `);
        $(legendSelector).off('click.conformity').on('click.conformity', '.conformity-filter', function () {
            clickConformity($(this).data('conformity'));
        });
    }
}

function isReportDelivered(row) {
    const statut = String(row?.statut_livraison || row?.statut_rapport || row?.statut || '').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return Boolean(row?.date_livraison || row?.date_livraison_reelle || statut.includes('livre'));
}

function normalizeReportType(row) {
    const value = String(
        row?.type_rapport ||
        row?.report_type ||
        row?.type ||
        row?.nature_rapport ||
        row?.statut_type_rapport ||
        row?.statut_rapport ||
        ''
    ).trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

    if (value.includes('provis')) return 'Provisoire';
    if (value.includes('final')) return 'Final';
    return row?.type_rapport || row?.report_type || '-';
}

function getReportType(row) {
    const type = normalizeReportType(row).toLowerCase();
    if (type.includes('provis')) return 'provisoire';
    if (type.includes('final')) return 'final';
    return 'autre';
}

function normalizeReportDeliveryStatus(row) {
    return isReportDelivered(row) ? 'Livré' : 'En attente';
}

function renderRapportKpis(data) {
    const rows = data.rapports_list || [];
    const provisoires = rows.filter(r => getReportType(r) === 'provisoire').length;
    const finaux = rows.filter(r => getReportType(r) === 'final').length;
    const livres = rows.filter(isReportDelivered).length;
    const enAttente = rows.length - livres;
    const delaiValidation = typeof calculateAverageReportValidationDelay === 'function' ? calculateAverageReportValidationDelay(rows) : '-';

    if (!hasDom('#rapportKpiGrid')) {
        renderReportStats(rows);
        return;
    }

    $('#rapportKpiGrid').html([
        renderKpiCard('Provisoires', provisoires, 'Type de rapport', 'blue'),
        renderKpiCard('Finaux', finaux, 'Type de rapport', 'success'),
        renderKpiCard('Livrés', livres, 'Statut livraison', 'success'),
        renderKpiCard('En attente', enAttente, 'Non livrés', enAttente ? 'warning' : 'success'),
        renderKpiCard('Délai validation', delaiValidation, 'Création → validation', 'violet')
    ].join(''));
}

function renderReportStats(rapportsList) {
    hideReportQuickIndicators();
}

function renderReportsStatusChart(selector, rows) {
    if (!hasDom(selector)) return;
    resetChartContainer(selector);
    const delivered = (rows || []).filter(isReportDelivered).length;
    const pending = (rows || []).length - delivered;
    const source = [
        { statut: 'Livré', total: delivered },
        { statut: 'En attente', total: pending }
    ].filter(r => r.total > 0);
    if (!source.length) { $(selector).html('<div class="chart-empty">Aucune donnée disponible</div>'); return; }
    $(selector).dxPieChart({
        type: 'doughnut',
        innerRadius: 0.62,
        dataSource: source,
        palette: [DASHBOARD_COLORS.green, DASHBOARD_COLORS.orange],
        series: [{ argumentField: 'statut', valueField: 'total', label: { visible: true, connector: { visible: false }, customizeText: arg => `${arg.argumentText} ${arg.valueText}` } }],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        tooltip: { enabled: true, customizeTooltip: arg => ({ text: `${arg.argumentText} : ${arg.valueText} rapport(s)` }) },
        onPointClick: function (e) {
            const value = e.target.originalArgument;
            drilldown.rapport_livraison = drilldown.rapport_livraison === value ? null : value;
            updateContextBar();
            if (originalData) filterDashboardData(originalData);
        }
    });
}

function getAnalyseDisplayName(row) {
    const candidates = [row?.nom_analyse, row?.libelle_analyse, row?.designation_analyse, row?.analyse_nom, row?.analyse_name, row?.analyse];
    for (const value of candidates) {
        const clean = String(value || '').trim();
        if (clean && !/^[0-9]+$/.test(clean)) return clean;
    }
    return 'Analyse non nommée';
}

function renderDelayByAnalyseChart(selector, rows) {
    if (!hasDom(selector)) return;
    resetChartContainer(selector);
    const grouped = {};
    (rows || []).forEach(r => {
        const name = getAnalyseDisplayName(r);
        const delai = Number(r.delai_moyen_jours ?? r.delai_moyen ?? r.duree_analyse_jours ?? 0);
        const total = Math.max(1, Number(r.total_ordres ?? r.total ?? 1));
        if (!grouped[name]) grouped[name] = { analyse: name, totalDelai: 0, count: 0 };
        grouped[name].totalDelai += delai * total;
        grouped[name].count += total;
    });

    let source = Object.values(grouped)
        .map(r => ({ analyse: r.analyse, delai: r.count ? Number((r.totalDelai / r.count).toFixed(2)) : 0, total: r.count }))
        .filter(r => r.delai > 0)
        .sort((a, b) => b.delai - a.delai);

    const named = source.filter(r => r.analyse !== 'Analyse non nommée');
    if (named.length) source = named;
    source = source.slice(0, 10);

    if (!source.length) { $(selector).html('<div class="chart-empty">Aucune analyse nommée disponible</div>'); return; }

    $(selector).dxChart({
        dataSource: source,
        rotated: true,
        commonSeriesSettings: { argumentField: 'analyse', type: 'bar' },
        series: [{
            valueField: 'delai',
            name: 'Délai moyen',
            color: DASHBOARD_COLORS.teal,
            label: { visible: true, position: 'outside', customizeText: info => formatDaysValue(info.value) }
        }],
        legend: { visible: false },
        argumentAxis: { tick: { visible: false }, grid: { visible: false }, label: { overlappingBehavior: 'stagger' } },
        valueAxis: { title: 'Délai moyen', tick: { visible: false }, grid: { color: '#e3ebf7' }, label: { customizeText: info => formatDaysValue(info.value) } },
        tooltip: { enabled: true, customizeTooltip: a => ({ text: `${a.argumentText}
Délai moyen : ${formatDaysValue(a.value)}
Ordres : ${a.point.data.total}` }) },
        onDone: function () { roundBarChartShapes(selector); }
    });
}

function buildFichesSectorRows(data) {
    const rows = data?.charts?.fiches_paillasse_secteur || data?.secteur_delai || [];
    if (Array.isArray(rows) && rows.length) return rows;
    const source = (Array.isArray(data?.ordres_list) && data.ordres_list.length ? data.ordres_list : (data?.fiches_list || []));
    const grouped = {};
    source.forEach(row => {
        const secteur = getSecteurName(row);
        if (!grouped[secteur]) grouped[secteur] = { secteur, total_fiches: 0, totalMinutes: 0, countDelay: 0 };
        grouped[secteur].total_fiches += 1;
        const days = Number(row.delai_prise_en_charge_jours ?? row.duree_analyse_jours ?? row.delai_moyen_jours ?? 0);
        if (Number.isFinite(days) && days > 0) {
            grouped[secteur].totalMinutes += days * 1440;
            grouped[secteur].countDelay += 1;
        }
    });
    return Object.values(grouped).map(r => ({
        secteur: r.secteur,
        total_fiches: r.total_fiches,
        delai_moyen_minutes: r.countDelay ? r.totalMinutes / r.countDelay : 0
    }));
}

function renderSecteurChart(selector, dataSource) {
    if (!hasDom(selector)) return;
    resetChartContainer(selector);
    const source = (dataSource || []).map(r => {
        const minutesRaw = r.delai_moyen_minutes ?? r.moyenne_delai_minutes;
        const hoursRaw = r.delai_moyen_heures ?? r.moyenne_delai_heures;
        const daysRaw = r.delai_moyen_jours ?? r.delai_moyen ?? 0;
        let hours = Number(hoursRaw);
        if (!Number.isFinite(hours) || hours < 0) {
            const minutes = Number(minutesRaw);
            if (Number.isFinite(minutes) && minutes >= 0) hours = minutes / 60;
        }
        if (!Number.isFinite(hours) || hours < 0) {
            const days = Number(daysRaw);
            hours = Number.isFinite(days) && days >= 0 ? days * 24 : 0;
        }
        return {
            secteur: r.secteur || r.nom_secteur || r.secteur_nom || 'Secteur inconnu',
            fiches: Number(r.total_fiches ?? r.nb_fiches ?? r.fiches ?? r.total ?? r.count ?? 0),
            delai_moyen_heures: Number(hours.toFixed(2)),
            delai_moyen_affichage: r.delai_moyen_affichage || formatMinutesToDayHourMinute(hours * 60)
        };
    }).filter(r => r.fiches > 0 || r.delai_moyen_heures > 0);

    if (!source.length) { $(selector).html('<div class="chart-empty">Aucune donnée disponible</div>'); return; }

    $(selector).dxChart({
        dataSource: source,
        commonSeriesSettings: { argumentField: 'secteur', ignoreEmptyPoints: true },
        series: [
            { type: 'bar', valueField: 'fiches', name: 'Nombre de fiches', axis: 'fichesAxis', color: DASHBOARD_COLORS.teal },
            { type: 'line', valueField: 'delai_moyen_heures', name: 'Délai moyen fiche', axis: 'delaiAxis', color: DASHBOARD_COLORS.purple, point: { visible: true, size: 6 } }
        ],
        valueAxis: [
            { name: 'fichesAxis', position: 'left', title: 'Nombre de fiches', tick: { visible: false }, grid: { color: '#e3ebf7' } },
            { name: 'delaiAxis', position: 'right', title: 'Heures', tick: { visible: false }, grid: { visible: false } }
        ],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        argumentAxis: { label: { overlappingBehavior: 'rotate', rotationAngle: 35 }, tick: { visible: false }, grid: { visible: false } },
        tooltip: { enabled: true, customizeTooltip: arg => ({ text: arg.seriesName.includes('Délai') ? `${arg.argumentText}: ${arg.point.data.delai_moyen_affichage}` : `${arg.argumentText}: ${arg.valueText} fiche(s)` }) },
        onDone: function () { roundBarChartShapes(selector); }
    });
}

function normalizeStringForMissing(value) {
    return String(value ?? '')
        .trim()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function isMissingLaborantinOrder(row) {
    const name = normalizeStringForMissing(
        row?.laborantin_nom ||
        row?.nom_laborantin ||
        row?.laborantin ||
        row?.validateur ||
        ''
    );

    const missingNames = new Set([
        '', '-', '0', 'null', 'undefined', 'nan',
        'non affecte', 'non assigne', 'non assignee',
        'a affecter', 'sans laborantin', 'laborantin anonyme',
        'inconnu', 'inconnue', 'n/a', 'na'
    ]);

    return missingNames.has(name);
}

function getFicheGroupKey(row) {
    const normalized = normalizeOrdreFicheRow(row || {});
    return String(normalized.id_fiche || normalized.reference_fiche || normalized.ref_labo || normalized.reference_ordre || normalized.row_key || '')
        .trim()
        .toLowerCase();
}

function buildFichePaillasseGridRows(rows) {
    const grouped = {};

    (rows || []).map(normalizeOrdreFicheRow).forEach(row => {
        const key = getFicheGroupKey(row);
        if (!key) return;

        if (!grouped[key]) {
            grouped[key] = {
                ...row,
                row_key: 'fiche_' + key,
                nb_ordres: 0,
                nb_resultats: 0,
                _all_orders: [],
                _laborantins: new Set(),
                _statuts: new Set()
            };
        }

        const target = grouped[key];
        target.nb_ordres += 1;
        target._all_orders.push(row);

        if (row.resultat && String(row.resultat).trim() !== '') target.nb_resultats += 1;
        if (!isMissingLaborantinOrder(row)) target._laborantins.add(row.laborantin_nom || row.nom_laborantin || row.laborantin);
        if (row.statut_ordre && String(row.statut_ordre).trim() !== '') target._statuts.add(row.statut_ordre);

        if (!target.date_affectation && row.date_affectation) target.date_affectation = row.date_affectation;
        if (!target.date_validation && row.date_validation) target.date_validation = row.date_validation;
    });

    return Object.values(grouped).map(row => {
        const orders = row._all_orders || [];
        const missingCount = orders.filter(isMissingLaborantinOrder).length;
        const laborantins = Array.from(row._laborantins || []).filter(Boolean);
        const allValidated = orders.length > 0 && orders.every(o => o.date_validation || String(o.statut_ordre || '').toLowerCase().includes('termin') || String(o.statut_ordre || '').toLowerCase().includes('valid'));
        const anyAffected = orders.some(o => o.date_affectation || !isMissingLaborantinOrder(o));

        let statutFiche = 'À affecter';
        if (allValidated) statutFiche = 'Terminée';
        else if (anyAffected) statutFiche = 'En cours';

        return {
            ...row,
            laborantin_nom: laborantins.length ? laborantins.join(' | ') : 'Non affecté',
            statut_fiche: statutFiche,
            ordres_sans_laborantin: missingCount,
            resultat_resume: String(row.nb_resultats) + ' / ' + String(row.nb_ordres)
        };
    }).sort((a, b) => String(a.reference_fiche || '').localeCompare(String(b.reference_fiche || '')));
}

function isMeaningfulRisk(value) {
    const key = getRiskKey(value || '');
    return !!key && !['none', 'non_defini', 'non_definie', 'faible', '-', ''].includes(key);
}

function getPreviewPriority(row) {
    const statut = normalizeDemandeStatut(row);
    const hasAlert = Array.isArray(row?._alertes) && row._alertes.length > 0;
    const risk = isMeaningfulRisk(row?.niveau_risque || row?.gravite);
    const delayLong = isDelayLongCandidate(row);
    const active = statut !== 'clôturée';

    return (
        (active ? 1000 : 0) +
        (hasAlert ? 500 : 0) +
        (risk ? 300 : 0) +
        (delayLong ? 200 : 0) +
        Math.min(99, Math.round(getDemandeDelayDays(row) || 0))
    );
}

function sortDemandesToFollow(rows) {
    return (rows || []).slice().sort((a, b) => {
        const pa = getPreviewPriority(a);
        const pb = getPreviewPriority(b);
        if (pb !== pa) return pb - pa;
        const da = parseDateSafe(a.date_demande || a.date_creation || a.date_reception);
        const db = parseDateSafe(b.date_demande || b.date_creation || b.date_reception);
        return (db ? db.getTime() : 0) - (da ? da.getTime() : 0);
    });
}

function renderDemandesPreviewGrid(dataSource) {
    if (!hasDom('#globalDemandesPreview')) return;
    resetGridContainer('#globalDemandesPreview');

    const rows = Array.isArray(dataSource) ? dataSource : [];
    const actionableRows = rows.filter(row =>
        normalizeDemandeStatut(row) !== 'clôturée' ||
        isMeaningfulRisk(row.niveau_risque || row.gravite) ||
        isDelayLongCandidate(row) ||
        (Array.isArray(row._alertes) && row._alertes.length > 0)
    );
    const previewRows = sortDemandesToFollow(actionableRows.length ? actionableRows : rows).slice(0, 12);

    $('#globalDemandesPreview').dxDataGrid({
        dataSource: previewRows,
        keyExpr: 'row_key',
        showBorders: true,
        columnAutoWidth: true,
        rowAlternationEnabled: true,
        paging: { pageSize: 6 },
        columns: [
            { dataField: 'reference_demande', caption: 'Réf demande', minWidth: 140 },
            { dataField: 'client_nom', caption: 'Client', minWidth: 160 },
            { dataField: 'secteur_nom', caption: 'Secteur', width: 140 },
            { dataField: 'statut_operationnel', caption: 'Statut', width: 120, cellTemplate: (c, o) => renderOperationalStatusBadge(c, o.value || o.data.statut_dashboard) },
            { dataField: 'delai_total_demande', caption: 'Délai', width: 100, cellTemplate: (c, o) => $('<span>').addClass('delay-pill').text(formatDaysValue(o.value)).appendTo(c) },
            { dataField: 'niveau_risque', caption: 'Risque', width: 110, cellTemplate: (c, o) => renderRiskBadge(c, o.value) }
        ]
    });
}

function isFilteredDashboardData(data) {
    return Boolean(data && data.__filtered_context === true);
}

function getDashboardDateValue(row) {
    return row?.date_demande || row?.date_creation || row?.date_reception || row?.date_affectation || row?.date_validation || row?.date_edition || row?.date_livraison || null;
}

function rowPassesDateFilters(row) {
    if (!hasAnyFilter()) return true;

    const dateValue = getDashboardDateValue(row);
    const dateObj = parseDateSafe(dateValue);

    if ((currentFilters.date_debut || currentFilters.date_fin) && !isDateInFilterRange(dateValue)) return false;
    if (currentFilters.annee && !dateObj) return false;
    if (currentFilters.mois && !dateObj) return false;
    if (currentFilters.annee && dateObj.getFullYear() !== Number(currentFilters.annee)) return false;
    if (currentFilters.mois && (dateObj.getMonth() + 1) !== Number(currentFilters.mois)) return false;

    return true;
}

function groupRowsByKey(rows, keyGetter, valueBuilder) {
    const grouped = {};
    (rows || []).forEach(row => {
        const key = keyGetter(row);
        if (!key) return;
        if (!grouped[key]) grouped[key] = valueBuilder ? valueBuilder(key, row) : { label: key, total: 0 };
        grouped[key].total = (Number(grouped[key].total) || 0) + 1;
    });
    return Object.values(grouped);
}

function buildFilteredDemandesSecteurRows(data) {
    const grouped = {};
    (data?.demandes_list || []).forEach(row => {
        const secteur = getSecteurName(row);
        if (!grouped[secteur]) grouped[secteur] = { nom_secteur: secteur, total: 0, totalMinutes: 0, countDelay: 0 };
        grouped[secteur].total += 1;
        const minutes = calculateDelaiDemandeMinutes(row);
        if (minutes !== null && Number.isFinite(Number(minutes)) && Number(minutes) >= 0) {
            grouped[secteur].totalMinutes += Number(minutes);
            grouped[secteur].countDelay += 1;
        }
    });
    return Object.values(grouped).map(row => {
        const avg = row.countDelay ? row.totalMinutes / row.countDelay : 0;
        return {
            nom_secteur: row.nom_secteur,
            total: row.total,
            delai_moyen_minutes: Math.round(avg),
            delai_moyen_heures: Number((avg / 60).toFixed(2)),
            delai_moyen_affichage: formatMinutesToDayHourMinute(avg)
        };
    });
}

function getOrdreRowsForFilteredCharts(data) {
    const source = Array.isArray(data?.ordres_list) && data.ordres_list.length ? data.ordres_list : (data?.fiches_list || []);
    return (source || []).map(normalizeOrdreFicheRow);
}

function buildFilteredFichesLaborantinRows(data) {
    const grouped = {};
    getOrdreRowsForFilteredCharts(data).forEach(row => {
        const laborantin = isMissingLaborantinOrder(row) ? 'Non affecte' : (row.laborantin_nom || row.nom_laborantin || row.laborantin || 'Non affecte');
        if (!grouped[laborantin]) grouped[laborantin] = { laborantin, affectees: 0, en_cours: 0, terminees: 0, total: 0 };
        grouped[laborantin].total += 1;
        const statut = String(row.statut_ordre || row.statut || '').toLowerCase();
        const done = statut.includes('termin') || statut.includes('valid') || Boolean(row.date_validation || row.date_fin);
        const affected = !isMissingLaborantinOrder(row) || Boolean(row.date_affectation);
        if (done) grouped[laborantin].terminees += 1;
        else if (affected) grouped[laborantin].en_cours += 1;
        else grouped[laborantin].affectees += 1;
    });
    return Object.values(grouped).sort((a, b) => b.total - a.total);
}

function buildFilteredRiskRows(data) {
    const fromAlerts = groupRowsByKey(
        data?.alertes_list || [],
        row => row.gravite || row.niveau_risque || '',
        key => ({ niveau_risque: key, gravite: key, total: 0 })
    ).filter(row => isMeaningfulRisk(row.niveau_risque || row.gravite));

    if (fromAlerts.length) return fromAlerts;

    return groupRowsByKey(
        data?.demandes_list || [],
        row => isMeaningfulRisk(row.niveau_risque || row.gravite) ? (row.niveau_risque || row.gravite) : '',
        key => ({ niveau_risque: key, gravite: key, total: 0 })
    );
}

function buildFilteredDelayEvolutionRows(data) {
    const grouped = {};
    (data?.demandes_list || []).forEach(row => {
        const d = parseDateSafe(row.date_demande || row.date_creation || row.date_reception);
        if (!d) return;
        const key = String(d.getFullYear()) + '-' + String(d.getMonth() + 1).padStart(2, '0');
        if (!grouped[key]) grouped[key] = { mois: key, totalDays: 0, count: 0 };
        const minutes = calculateDelaiDemandeMinutes(row);
        if (minutes !== null && Number.isFinite(Number(minutes)) && Number(minutes) >= 0) {
            grouped[key].totalDays += Number(minutes) / 1440;
            grouped[key].count += 1;
        }
    });
    return Object.values(grouped)
        .filter(row => row.count > 0)
        .sort((a, b) => String(a.mois).localeCompare(String(b.mois)))
        .map(row => ({ mois: row.mois, delai_moyen_jours: Number((row.totalDays / row.count).toFixed(2)) }));
}

function buildFilteredOrdresStatusRows(data) {
    return groupRowsByKey(
        getOrdreRowsForFilteredCharts(data),
        row => row.statut_ordre || row.statut || row.status || 'Non defini',
        key => ({ statut_ordre: key, statut: key, total: 0 })
    );
}

function buildFilteredDelayAnalyseRows(data) {
    return getOrdreRowsForFilteredCharts(data).filter(row => Number(row.duree_analyse_jours ?? row.delai_moyen_jours ?? row.delai_moyen ?? 0) > 0);
}

function buildFilteredReportValidationRows(data) {
    const grouped = {};
    (data?.rapports_list || []).forEach(row => {
        const start = parseDateSafe(row.date_creation || row.date_edition);
        const end = parseDateSafe(row.date_validation);
        if (!start || !end || end < start) return;
        const key = String(end.getFullYear()) + '-' + String(end.getMonth() + 1).padStart(2, '0');
        if (!grouped[key]) grouped[key] = { mois: key, totalDays: 0, count: 0 };
        grouped[key].totalDays += (end.getTime() - start.getTime()) / 86400000;
        grouped[key].count += 1;
    });
    return Object.values(grouped)
        .filter(row => row.count > 0)
        .sort((a, b) => String(a.mois).localeCompare(String(b.mois)))
        .map(row => ({ mois: row.mois, delai_moyen_validation: Number((row.totalDays / row.count).toFixed(2)) }));
}

function buildFilteredRapportsSecteurRows(data) {
    return groupRowsByKey(
        data?.rapports_list || [],
        row => getSecteurName(row),
        key => ({ nom_secteur: key, total: 0 })
    );
}

function buildFilteredAlertesGraviteRows(data) {
    return groupRowsByKey(
        data?.alertes_list || [],
        row => row.gravite || row.niveau_risque || 'Non defini',
        key => ({ gravite: key, niveau_risque: key, total: 0 })
    );
}

function getDynamicChartSources(data) {
    const filtered = isFilteredDashboardData(data);
    return {
        demandesSecteur: filtered ? buildFilteredDemandesSecteurRows(data) : normalizeSectorDelayRows(data?.charts?.volume_delai_secteur || data?.demandes_secteur || []),
        riskAlerts: filtered ? buildFilteredRiskRows(data) : (data?.charts?.alertes_risque || data?.charts?.alertes_gravite || []),
        fichesLaborantin: filtered ? buildFilteredFichesLaborantinRows(data) : (data?.charts?.fiches_paillasse_laborantin || data?.laborantin_delai || []),
        delayEvolution: filtered ? buildFilteredDelayEvolutionRows(data) : (data?.charts?.evolution_delai || []),
        ordresStatus: filtered ? buildFilteredOrdresStatusRows(data) : (data?.charts?.ordres_statut || []),
        delayAnalyse: filtered ? buildFilteredDelayAnalyseRows(data) : (data?.charts?.delai_analyse || []),
        reportValidation: filtered ? buildFilteredReportValidationRows(data) : (data?.charts?.delai_validation_rapport || []),
        rapportsSecteur: filtered ? buildFilteredRapportsSecteurRows(data) : (data?.charts?.rapports_secteur || data?.rapports_secteur || []),
        alertesGravite: filtered ? buildFilteredAlertesGraviteRows(data) : (data?.charts?.alertes_gravite || [])
    };
}


getCleanKpis = function (data) {
    const demandes = data?.demandes_list || [];
    const k = data?.kpis_clean || {};
    const filtered = isFilteredDashboardData(data);

    const total = filtered
        ? demandes.length
        : Number(k.total_demandes ?? data?.total_demandes ?? demandes.length ?? 0);

    const ouvertes = demandes.filter(row => getRawStatusCategory(row) === 'ouverte').length;
    const enCours = demandes.filter(row => getRawStatusCategory(row) === 'en cours').length;
    const terminees = demandes.filter(row => getRawStatusCategory(row) === 'terminée').length;

    const ordreRows = getOrdreRowsForFilteredCharts(data);
    const ficheRows = typeof buildFichePaillasseGridRows === 'function' ? buildFichePaillasseGridRows(ordreRows) : [];
    const totalAffectations = filtered
        ? ficheRows.length
        : Number(k.total_affectations ?? data?.affectations?.total_affectations ?? ficheRows.length ?? ordreRows.length ?? 0);

    const moyenneMinutes = Number(data?.delai_moyen?.moyenne_delai_minutes ?? 0);
    const delaiMoyenJours = filtered
        ? Number((moyenneMinutes / 1440).toFixed(2))
        : Number(k.delai_moyen_jours ?? (moyenneMinutes / 1440).toFixed(2));

    const conformity = getConformityCountsFromData(data);
    const nonConfFromDemandes = demandes.filter(row => Number(row.non_conforme_flag || 0) === 1 || getRowConformity(row) === 'non conforme').length;
    const nonConformes = filtered
        ? conformity.nonConformes
        : Number(k.non_conformes ?? conformity.nonConformes ?? nonConfFromDemandes);

    let rapportsPrets = demandes.filter(isReportReadyForKpi).length;
    if (!filtered && !rapportsPrets && Number(k.rapports_prets || 0) > 0) rapportsPrets = Number(k.rapports_prets || 0);
    rapportsPrets = Math.min(rapportsPrets, terminees);

    return {
        total_demandes: total,
        demandes_ouvertes: ouvertes,
        demandes_en_cours: enCours,
        demandes_terminees: terminees,
        total_affectations: totalAffectations,
        delai_moyen_jours: delaiMoyenJours,
        rapports_prets: rapportsPrets,
        alertes_delai: computeDelayAlertCount(data),
        non_conformes: nonConformes
    };
};

renderFichesKpis = function (data) {
    const ordreRows = getOrdreRowsForFilteredCharts(data);
    const ficheRows = typeof buildFichePaillasseGridRows === 'function' ? buildFichePaillasseGridRows(ordreRows) : [];
    const filtered = isFilteredDashboardData(data);

    const totalFiches = filtered
        ? ficheRows.length
        : Number(data?.kpis_clean?.total_affectations ?? data?.affectations?.total_affectations ?? ficheRows.length ?? 0);
    const ordresAffectes = ordreRows.filter(row => !isMissingLaborantinOrder(row)).length;
    const ordresTermines = ordreRows.filter(row => String(row.statut_ordre || '').toLowerCase().includes('termin') || String(row.statut_ordre || '').toLowerCase().includes('valid') || row.date_validation || row.date_fin).length;
    const sansLab = ordreRows.filter(isMissingLaborantinOrder).length;

    $('#fichesKpiGrid').html([
        renderKpiCard('Total fiches', totalFiches, 'Fiches paillasse', 'primary'),
        renderKpiCard('Ordres affectés', ordresAffectes, 'Avec laborantin', 'violet'),
        renderKpiCard('Ordres terminés', ordresTermines, 'Validés ou terminés', 'success'),
        renderKpiCard('Sans laborantin', sansLab, 'Ordres à affecter', sansLab ? 'danger' : 'success')
    ].join(''));
};


let dashboardCurrentData = null;
let dashboardDataVersion = 0;
let dashboardRenderedGridsVersion = { global: -1, demandes: -1, fiches: -1, rapports: -1, alertes: -1 };
let dashboardRenderedChartsVersion = { global: -1, demandes: -1, fiches: -1, rapports: -1, alertes: -1 };

function cloneDashboardShell(data) {
    return Object.assign({}, data || {}, {
        demandes_list: Array.isArray(data?.demandes_list) ? data.demandes_list : [],
        fiches_list: Array.isArray(data?.fiches_list) ? data.fiches_list : [],
        ordres_list: Array.isArray(data?.ordres_list) ? data.ordres_list : [],
        rapports_list: Array.isArray(data?.rapports_list) ? data.rapports_list : [],
        alertes_list: Array.isArray(data?.alertes_list) ? data.alertes_list : [],
        echantillons_list: Array.isArray(data?.echantillons_list) ? data.echantillons_list : []
    });
}

function dashboardBumpVersion(data) {
    dashboardCurrentData = data || null;
    dashboardDataVersion += 1;
    dashboardRenderedGridsVersion = { global: -1, demandes: -1, fiches: -1, rapports: -1, alertes: -1 };
    dashboardRenderedChartsVersion = { global: -1, demandes: -1, fiches: -1, rapports: -1, alertes: -1 };
}

function dashboardChartSources(data) {
    return typeof getDynamicChartSources === 'function'
        ? getDynamicChartSources(data)
        : {
            demandesSecteur: normalizeSectorDelayRows(data?.charts?.volume_delai_secteur || data?.demandes_secteur || []),
            riskAlerts: data?.charts?.alertes_risque || data?.charts?.alertes_gravite || [],
            fichesLaborantin: data?.charts?.fiches_paillasse_laborantin || data?.laborantin_delai || [],
            delayEvolution: data?.charts?.evolution_delai || [],
            ordresStatus: data?.charts?.ordres_statut || [],
            delayAnalyse: data?.charts?.delai_analyse || [],
            reportValidation: data?.charts?.delai_validation_rapport || [],
            rapportsSecteur: data?.charts?.rapports_secteur || data?.rapports_secteur || [],
            alertesGravite: data?.charts?.alertes_gravite || []
        };
}

function dashboardRenderGlobalCharts(data, force) {
    if (!data) return;
    if (!force && dashboardRenderedChartsVersion.global === dashboardDataVersion) return;
    dashboardRenderedChartsVersion.global = dashboardDataVersion;
    const conformity = getConformityCountsFromData(data);
    const sources = dashboardChartSources(data);
    safeDashboardCall('renderStatusAreaChart', () => renderStatusAreaChart('#statusAreaChart', '#statusLegend', data.demandes_list || []));
    safeDashboardCall('renderConformiteChart', () => renderConformiteChart('#sampleDonutChart', '#sampleLegend', conformity.conformes, conformity.nonConformes));
    safeDashboardCall('renderDemandesSecteurChart', () => renderDemandesSecteurChart('#sectorBarChart', sources.demandesSecteur, true));
    safeDashboardCall('renderRiskAlertsChart', () => renderRiskAlertsChart('#riskAlertsChart', sources.riskAlerts));
    safeDashboardCall('renderFichesPaillasseLaborantinChart global', () => renderFichesPaillasseLaborantinChart('#fichesPaillasseLaborantinGlobalChart', sources.fichesLaborantin));
    safeDashboardCall('renderDelayEvolutionChart', () => renderDelayEvolutionChart('#delayEvolutionChart', sources.delayEvolution));
}

function dashboardRenderFichesView(data, force) {
    if (!data) return;
    if (!force && dashboardRenderedChartsVersion.fiches === dashboardDataVersion) return;
    dashboardRenderedChartsVersion.fiches = dashboardDataVersion;
    const sources = dashboardChartSources(data);
    safeDashboardCall('renderFichesPaillasseLaborantinChart', () => renderFichesPaillasseLaborantinChart('#laborantinComboChart', sources.fichesLaborantin));
    safeDashboardCall('renderSecteurChart', () => renderSecteurChart('#fichesSectorComboChart', isFilteredDashboardData(data) ? buildFichesSectorRows(Object.assign({}, data, { charts: {} })) : buildFichesSectorRows(data)));
    safeDashboardCall('renderOrdresStatusChart', () => renderOrdresStatusChart('#ordresStatusChart', sources.ordresStatus));
    safeDashboardCall('renderDelayByAnalyseChart', () => renderDelayByAnalyseChart('#delayByAnalyseChart', sources.delayAnalyse));
}

function dashboardRenderRapportsView(data, force) {
    if (!data) return;
    if (!force && dashboardRenderedChartsVersion.rapports === dashboardDataVersion) return;
    dashboardRenderedChartsVersion.rapports = dashboardDataVersion;
    const sources = dashboardChartSources(data);
    safeDashboardCall('renderReportsStatusChart', () => renderReportsStatusChart('#reportsStatusChart', data.rapports_list || []));
    safeDashboardCall('renderReportValidationDelayChart', () => renderReportValidationDelayChart('#reportValidationDelayChart', sources.reportValidation));
    safeDashboardCall('renderRapportsSecteurChart', () => renderRapportsSecteurChart('#reportsBarChart', sources.rapportsSecteur));
    safeDashboardCall('renderReportStats', () => renderReportStats(data.rapports_list || []));
}

function dashboardRenderAlertesView(data, force) {
    if (!data) return;
    if (!force && dashboardRenderedChartsVersion.alertes === dashboardDataVersion) return;
    dashboardRenderedChartsVersion.alertes = dashboardDataVersion;
    const sources = dashboardChartSources(data);
    safeDashboardCall('renderAlertesGravityChart', () => renderAlertesGravityChart('#alertsGravityChart', sources.alertesGravite));
    safeDashboardCall('renderAlertesTypeChart', () => renderAlertesTypeChart('#alertsTypeChart', buildAlertesTypeRows(data)));
}

function dashboardInitGridForView(data, view, force) {
    if (!data) return;
    const targetView = view || currentView || 'global';
    if (!force && dashboardRenderedGridsVersion[targetView] === dashboardDataVersion) return;
    dashboardRenderedGridsVersion[targetView] = dashboardDataVersion;

    const demandesList = Array.isArray(data.demandes_list) ? data.demandes_list : [];
    const echantillonsList = Array.isArray(data.echantillons_list) ? data.echantillons_list : [];
    const ordresList = Array.isArray(data.ordres_list) ? data.ordres_list : [];
    const fichesRaw = Array.isArray(data.fiches_list) ? data.fiches_list : [];
    const ordresFichesSource = ordresList.length ? ordresList : fichesRaw;
    const rapportsList = Array.isArray(data.rapports_list) ? data.rapports_list : [];
    const alertesList = Array.isArray(data.alertes_list) ? data.alertes_list : [];

    if (targetView === 'global' || targetView === 'demandes') {
        const demandesData = demandesList.map((d, index) => {
            const row = {
                ...d,
                row_key: d.row_key || d.id_demande_analyse_sk || d.id_demande || d.demande_id || d.reference_demande || d.ref_demande || `demande_${index}`,
                id_demande: getDemandId(d),
                reference_demande: getRefDemande(d),
                client_nom: getClientName(d),
                secteur_nom: getSecteurName(d),
                statut_dashboard: normalizeDemandeStatut(d),
                statut_brut: getRawStatus(d),
                delai_total_demande: d.delai_total_demande ?? d.delai_total_jours ?? (Number(d.delai_total_minutes || 0) / 1440),
                avancement: `${Number(d.nb_resultats || 0)} / ${Number(d.nb_ordres || 0)}`,
                niveau_risque: d.niveau_risque || d.gravite || '-',
                report_status: d.report_status || (canGenerateReport(d) ? 'READY' : 'BLOCKED')
            };
            row._echantillons = echantillonsList.filter(e => rowsMatchDemandFinal(row, e));
            row._ordres = ordresFichesSource.filter(o => rowsMatchDemandFinal(row, o));
            row._rapports = rapportsList.filter(r => rowsMatchDemandFinal(row, r));
            row._alertes = alertesList.filter(a => rowsMatchDemandFinal(row, a));
            return row;
        });
        if (targetView === 'global') {
            if (typeof renderDemandesPreviewGrid === 'function') safeDashboardCall('renderDemandesPreviewGrid', () => renderDemandesPreviewGrid(demandesData));
        } else {
            safeDashboardCall('renderDemandesGrid', () => renderDemandesGrid(demandesData, '#demandesTable', true));
        }
    }

    if (targetView === 'fiches') {
        const fichesData = ordresFichesSource
            .map(normalizeOrdreFicheRow)
            .sort((a, b) => {
                const demandeCompare = String(a.reference_demande || '').localeCompare(String(b.reference_demande || ''));
                if (demandeCompare !== 0) return demandeCompare;
                const ficheCompare = String(a.reference_fiche || '').localeCompare(String(b.reference_fiche || ''));
                if (ficheCompare !== 0) return ficheCompare;
                return String(a.reference_ordre || '').localeCompare(String(b.reference_ordre || ''));
            });
        safeDashboardCall('renderFichesGrid', () => renderFichesGrid(fichesData));
    }

    if (targetView === 'rapports') {
        const rapportsData = rapportsList.map(r => ({
            ...r,
            id_rapport: r.id_rapport || r.reference_rapport || r.rapport,
            reference_rapport: r.reference_rapport || r.id_rapport || extractRapportFilename(r.rapport) || '-',
            reference_demande: r.reference_demande || r.ref_demande || r.id_demande || '-',
            client_nom: getClientName(r),
            secteur_nom: getSecteurName(r),
            type_rapport: normalizeReportType(r),
            statut_livraison: normalizeReportDeliveryStatus(r),
            validateur: r.validateur || r.laborantin_validateur || 'Non assigné'
        }));
        safeDashboardCall('renderRapportsGrid', () => renderRapportsGrid(rapportsData));
    }

    if (targetView === 'alertes') {
        safeDashboardCall('renderAlertesGrid', () => renderAlertesGrid(alertesList.map((a, idx) => ({
            ...a,
            id_alerte: a.id_alerte || `${a.type_alerte || 'ALERTE'}_${getDemandId(a)}_${idx}`,
            type_alerte_label: formatAlertTypeLabel(a.type_alerte),
            reference_demande: a.reference_demande || a.ref_demande || getRefDemande(a),
            client_nom: getClientName(a),
            secteur_nom: getSecteurName(a)
        }))));
    }
}

function dashboardRenderActiveView(data, force) {
    if (!data) return;
    if (currentView === 'global') dashboardRenderGlobalCharts(data, force);
    if (currentView === 'fiches') dashboardRenderFichesView(data, force);
    if (currentView === 'rapports') dashboardRenderRapportsView(data, force);
    if (currentView === 'alertes') dashboardRenderAlertesView(data, force);
    dashboardInitGridForView(data, currentView, force);
}

function dashboardRefreshFromData(data) {
    if (!data) return;
    dashboardBumpVersion(data);
    if (isFilteredDashboardData(data)) {
        safeDashboardCall('recalculateStats', () => recalculateStats(data));
    }
    safeDashboardCall('updateDashboard', () => updateDashboard(data));
    safeDashboardCall('initGrids', () => initGrids(data));
}

initTabs = function () {
    $(document).off('click.dashboardTabs', '.tab-btn').on('click.dashboardTabs', '.tab-btn', function () {
        const view = $(this).data('view');
        if (!view) return;
        currentView = view;
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.view').removeClass('active');
        $('#view-' + view).addClass('active');
        updateContextBar();
        revealActiveView();
        setTimeout(function () {
            dashboardRenderActiveView(dashboardCurrentData || originalData, true);
        }, 30);
    });
};

bindContextActions = function () {
    $('#clearDrilldownBtn').off('click').on('click', function () {
        drilldown = { secteur: null, statut: null, conformite: null, rapport_livraison: null, alerte_gravite: null };
        updateContextBar();
        if (!originalData) return;
        if (hasAnyFilter()) filterDashboardData(originalData);
        else dashboardRefreshFromData(originalData);
    });
};

resetFilters = function () {
    dateDebutFilter.option('value', null);
    dateFinFilter.option('value', null);
    yearFilter.option('value', null);
    monthFilter.option('value', null);
    currentFilters = {};
    renderActiveFilterBadges();
    drilldown = { secteur: null, statut: null, conformite: null, rapport_livraison: null, alerte_gravite: null };
    updateContextBar();
    if (originalData) dashboardRefreshFromData(originalData);
    else loadDashboard();
};

loadDashboard = function () {
    $.ajax({
        url: '../back_end/api.php',
        dataType: 'json',
        cache: false,
        success: function (data) {
            if (!data || data.status === 'error') {
                $('#kpiGrid').html(`
                    <div class="kpi-card danger">
                        <div class="kpi-label">Erreur API</div>
                        <div class="kpi-value">JSON</div>
                        <div class="kpi-foot">${data?.message || 'Réponse invalide'}</div>
                    </div>
                `);
                return;
            }
            originalData = data;
            if (hasAnyFilter() || hasAnyDrilldown()) filterDashboardData(originalData);
            else dashboardRefreshFromData(originalData);
        },
        error: function () {
            $('#kpiGrid').html(`
                <div class="kpi-card danger">
                    <div class="kpi-label">Erreur</div>
                    <div class="kpi-value">API</div>
                    <div class="kpi-foot">../back_end/api.php indisponible</div>
                </div>
            `);
        }
    });
};

filterDashboardData = function (data) {
    if (!data) return;

    const source = cloneDashboardShell(data);
    const filteredData = cloneDashboardShell(data);
    filteredData.__filtered_context = true;

    const demandesList = source.demandes_list;
    const fichesList = source.fiches_list;
    const ordresList = source.ordres_list;
    const rapportsList = source.rapports_list;
    const alertesList = source.alertes_list;
    const echantillonsList = source.echantillons_list;

    let conformityKeys = null;
    let conformityKeysHaveData = false;

    if (drilldown.conformite) {
        conformityKeys = new Set();
        echantillonsList.forEach(e => {
            if (getRowConformity(e) === drilldown.conformite) {
                const keys = getDemandIdentityKeys(e);
                if (keys.length) conformityKeysHaveData = true;
                keys.forEach(k => conformityKeys.add(k));
            }
        });
    }

    const validKeys = new Set();

    filteredData.demandes_list = demandesList.filter(demande => {
        let ok = rowPassesDateFilters(demande);
        if (ok && drilldown.secteur) ok = getSecteurName(demande) === drilldown.secteur;
        if (ok && drilldown.statut) ok = normalizeDemandeStatut(demande) === drilldown.statut;

        if (ok && drilldown.conformite) {
            if (conformityKeysHaveData) ok = getDemandIdentityKeys(demande).some(k => conformityKeys.has(k));
            else ok = getRowConformity(demande) === drilldown.conformite;
        }

        if (ok) getDemandIdentityKeys(demande).forEach(k => validKeys.add(k));
        return ok;
    });

    const byDemand = item => getDemandIdentityKeys(item).some(k => validKeys.has(k));

    filteredData.fiches_list = fichesList.filter(byDemand);
    filteredData.ordres_list = ordresList.filter(byDemand);
    filteredData.rapports_list = rapportsList
        .filter(byDemand)
        .filter(row => !drilldown.rapport_livraison || normalizeReportDeliveryStatus(row) === drilldown.rapport_livraison);
    filteredData.alertes_list = alertesList
        .filter(byDemand)
        .filter(row => !drilldown.alerte_gravite || getRiskKey(row.gravite || row.niveau_risque || '') === getRiskKey(drilldown.alerte_gravite));
    filteredData.echantillons_list = echantillonsList
        .filter(byDemand)
        .filter(row => !drilldown.conformite || getRowConformity(row) === drilldown.conformite);

    dashboardRefreshFromData(filteredData);
};

updateDashboard = function (data) {
    const k = getCleanKpis(data);
    const conformity = getConformityCountsFromData(data);

    $('#kpiGrid').html([
        renderKpiCard('Demandes', k.total_demandes, 'Volume total', 'primary'),
        renderKpiCard('Ouvertes', k.demandes_ouvertes, 'Statut brut', 'neutral'),
        renderKpiCard('En cours', k.demandes_en_cours, 'Activité détectée', 'warning'),
        renderKpiCard('Terminées', k.demandes_terminees, 'Finalisées', 'success'),
        renderKpiCard('Affectations', k.total_affectations, 'Total fiches paillasse', 'violet'),
        renderKpiCard('Délai moyen', formatDaysValue(k.delai_moyen_jours), 'Traitement global', 'blue'),
        renderKpiCard('Rapports prêts', k.rapports_prets, 'Terminées avec rapport', 'success'),
        renderKpiCard('Alertes délai', k.alertes_delai, 'À surveiller', 'danger'),
        renderKpiCard('Non conformes', k.non_conformes, 'Échantillons', 'danger')
    ].join(''));

    $('#heroDelayAlerts').text(k.alertes_delai);
    safeDashboardCall('updateLastDataDateFromData', () => updateLastDataDateFromData(data));
    safeDashboardCall('renderUrgentStrip', () => renderUrgentStrip(data));
    safeDashboardCall('renderFichesKpis', () => renderFichesKpis(data));
    safeDashboardCall('renderRapportKpis', () => renderRapportKpis(data));
    safeDashboardCall('renderAlertesKpis', () => renderAlertesKpis(data));

    dashboardRenderActiveView(data, false);
    updateContextBar();
    revealActiveView();
};

initGrids = function (data) {
    dashboardInitGridForView(data, currentView || 'global', false);
};

(function installRisingChartAnimation() {
    const styleId = 'dashboard-rising-chart-animation';
    if (!document.getElementById(styleId)) {
        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = `
            .dashboard-chart-rise {
                animation: dashboardChartRiseIn 520ms cubic-bezier(.2,.8,.2,1) both;
                will-change: transform, opacity;
            }
            @keyframes dashboardChartRiseIn {
                from { opacity: 0; transform: translateY(18px) scale(.985); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }
        `;
        document.head.appendChild(style);
    }

    function rise($items) {
        $items.each(function () {
            const el = this;
            el.classList.remove('dashboard-chart-rise');
            void el.offsetWidth;
            el.classList.add('dashboard-chart-rise');
        });
    }

    if (window.jQuery && $.fn.dxChart && !$.fn.dxChart.__dashboardRisePatched) {
        const originalDxChart = $.fn.dxChart;
        $.fn.dxChart = function () {
            const result = originalDxChart.apply(this, arguments);
            if (arguments.length && typeof arguments[0] === 'object') rise(this);
            return result;
        };
        $.fn.dxChart.__dashboardRisePatched = true;
    }

    if (window.jQuery && $.fn.dxPieChart && !$.fn.dxPieChart.__dashboardRisePatched) {
        const originalDxPieChart = $.fn.dxPieChart;
        $.fn.dxPieChart = function () {
            const result = originalDxPieChart.apply(this, arguments);
            if (arguments.length && typeof arguments[0] === 'object') rise(this);
            return result;
        };
        $.fn.dxPieChart.__dashboardRisePatched = true;
    }
})();
var dashboardLazyLists = { demandes: false, ordres: false, echantillons: false, rapports: false, alertes: false };
var dashboardLazyRequests = {};
var dashboardFullRequest = null;

function dashboardHasAllCoreLists() {
    return !!(dashboardLazyLists.demandes && dashboardLazyLists.ordres && dashboardLazyLists.echantillons && dashboardLazyLists.rapports && dashboardLazyLists.alertes);
}

function dashboardMarkInitialPayload(data) {
    const full = data && data.__light_payload === false;
    dashboardLazyLists = {
        demandes: !!full,
        ordres: !!full,
        echantillons: !!full,
        rapports: !!full,
        alertes: !!full
    };
    dashboardLazyRequests = {};
    dashboardFullRequest = null;
}

function dashboardRefreshLightFlag() {
    if (!originalData) return;
    originalData.__light_payload = !dashboardHasAllCoreLists();
    if (dashboardCurrentData && dashboardCurrentData === originalData) {
        dashboardCurrentData.__light_payload = originalData.__light_payload;
    }
}

function dashboardMergeListPayload(listKey, payload) {
    if (!originalData) originalData = {};
    const rows = Array.isArray(payload?.data) ? payload.data : [];

    if (listKey === 'demandes') originalData.demandes_list = rows;
    if (listKey === 'ordres') {
        originalData.ordres_list = rows;
        originalData.fiches_list = rows;
    }
    if (listKey === 'echantillons') originalData.echantillons_list = rows;
    if (listKey === 'rapports') originalData.rapports_list = rows;
    if (listKey === 'alertes') {
        originalData.alertes_list = rows;
        if (payload?.summary) originalData.alertes_summary = payload.summary;
    }

    dashboardLazyLists[listKey] = true;
    dashboardRefreshLightFlag();
}

function dashboardFetchList(listKey) {
    if (dashboardLazyLists[listKey]) return $.Deferred().resolve().promise();
    if (dashboardLazyRequests[listKey]) return dashboardLazyRequests[listKey];

    const actions = {
        demandes: 'list_demandes',
        ordres: 'list_ordres',
        echantillons: 'list_echantillons',
        rapports: 'list_rapports',
        alertes: 'list_alertes'
    };

    dashboardShowLoading('Chargement...');
    dashboardLazyRequests[listKey] = $.ajax({
        url: '../back_end/api.php',
        dataType: 'json',
        cache: false,
        data: { action: actions[listKey] }
    }).done(function (payload) {
        if (payload && payload.status !== 'error') dashboardMergeListPayload(listKey, payload);
    }).always(function () {
        dashboardHideLoading();
        dashboardLazyRequests[listKey] = null;
    });

    return dashboardLazyRequests[listKey];
}

function dashboardEnsureLists(listKeys) {
    const requests = (listKeys || []).map(key => dashboardFetchList(key));
    return requests.length ? $.when.apply($, requests) : $.Deferred().resolve().promise();
}

function dashboardKeysForView(view) {
    if (view === 'demandes') return ['demandes', 'echantillons', 'ordres', 'rapports', 'alertes'];
    if (view === 'fiches') return ['ordres'];
    if (view === 'rapports') return ['rapports'];
    if (view === 'alertes') return ['alertes'];
    return [];
}

function dashboardEnsureViewData(view) {
    return dashboardEnsureLists(dashboardKeysForView(view));
}

function dashboardEnsureFullData() {
    if (dashboardHasAllCoreLists() && originalData && originalData.__light_payload !== true) {
        return $.Deferred().resolve().promise();
    }
    if (dashboardFullRequest) return dashboardFullRequest;

    dashboardShowLoading('Chargement...');
    dashboardFullRequest = $.ajax({
        url: '../back_end/api.php',
        dataType: 'json',
        cache: false,
        data: { action: 'operationnel_full' }
    }).done(function (payload) {
        if (!payload || payload.status === 'error') return;
        originalData = payload;
        dashboardCurrentData = payload;
        dashboardMarkInitialPayload(payload);
        dashboardLazyLists = { demandes: true, ordres: true, echantillons: true, rapports: true, alertes: true };
        originalData.__light_payload = false;
    }).always(function () {
        dashboardHideLoading();
        dashboardFullRequest = null;
    });

    return dashboardFullRequest;
}

var dashboardBaseChartSources = dashboardChartSources;
dashboardChartSources = function (data) {
    const sources = dashboardBaseChartSources ? (dashboardBaseChartSources(data) || {}) : {};
    const hasRows = rows => Array.isArray(rows) && rows.length > 0;

    if (!hasRows(sources.demandesSecteur)) sources.demandesSecteur = buildFilteredDemandesSecteurRows(data);
    if (!hasRows(sources.riskAlerts)) sources.riskAlerts = buildFilteredRiskRows(data);
    if (!hasRows(sources.fichesLaborantin)) sources.fichesLaborantin = buildFilteredFichesLaborantinRows(data);
    if (!hasRows(sources.delayEvolution)) sources.delayEvolution = buildFilteredDelayEvolutionRows(data);
    if (!hasRows(sources.ordresStatus)) sources.ordresStatus = buildFilteredOrdresStatusRows(data);
    if (!hasRows(sources.delayAnalyse)) sources.delayAnalyse = buildFilteredDelayAnalyseRows(data);
    if (!hasRows(sources.reportValidation)) sources.reportValidation = buildFilteredReportValidationRows(data);
    if (!hasRows(sources.rapportsSecteur)) sources.rapportsSecteur = buildFilteredRapportsSecteurRows(data);
    if (!hasRows(sources.alertesGravite)) sources.alertesGravite = buildFilteredAlertesGraviteRows(data);

    return sources;
};

dashboardRenderGlobalCharts = function (data, force) {
    if (!data) return;
    if (!force && dashboardRenderedChartsVersion.global === dashboardDataVersion) return;
    dashboardRenderedChartsVersion.global = dashboardDataVersion;
    const conformity = getConformityCountsFromData(data);
    const sources = dashboardChartSources(data);
    const statusRows = Array.isArray(data.demandes_status_trend) && data.demandes_status_trend.length
        ? data.demandes_status_trend
        : (data.demandes_list || []);
    safeDashboardCall('renderStatusAreaChart', () => renderStatusAreaChart('#statusAreaChart', '#statusLegend', statusRows));
    safeDashboardCall('renderConformiteChart', () => renderConformiteChart('#sampleDonutChart', '#sampleLegend', conformity.conformes, conformity.nonConformes));
    safeDashboardCall('renderDemandesSecteurChart', () => renderDemandesSecteurChart('#sectorBarChart', sources.demandesSecteur, true));
    safeDashboardCall('renderRiskAlertsChart', () => renderRiskAlertsChart('#riskAlertsChart', sources.riskAlerts));
    safeDashboardCall('renderFichesPaillasseLaborantinChart global', () => renderFichesPaillasseLaborantinChart('#fichesPaillasseLaborantinGlobalChart', sources.fichesLaborantin));
    safeDashboardCall('renderDelayEvolutionChart', () => renderDelayEvolutionChart('#delayEvolutionChart', sources.delayEvolution));
};

getCleanKpis = function (data) {
    const demandes = data?.demandes_list || [];
    const k = data?.kpis_clean || {};
    const filtered = isFilteredDashboardData(data);

    const total = filtered
        ? demandes.length
        : Number(k.total_demandes ?? data?.total_demandes ?? demandes.length ?? 0);

    const ouvertes = filtered
        ? demandes.filter(row => getRawStatusCategory(row) === 'ouverte').length
        : Number(k.demandes_ouvertes ?? demandes.filter(row => getRawStatusCategory(row) === 'ouverte').length);
    const enCours = filtered
        ? demandes.filter(row => getRawStatusCategory(row) === 'en cours').length
        : Number(k.demandes_en_cours ?? demandes.filter(row => getRawStatusCategory(row) === 'en cours').length);
    const terminees = filtered
        ? demandes.filter(row => getRawStatusCategory(row) === 'terminée').length
        : Number(k.demandes_terminees ?? demandes.filter(row => getRawStatusCategory(row) === 'terminée').length);

    const ordreRows = getOrdreRowsForFilteredCharts(data);
    const ficheRows = typeof buildFichePaillasseGridRows === 'function' ? buildFichePaillasseGridRows(ordreRows) : [];
    const totalAffectations = filtered
        ? ficheRows.length
        : Number(k.total_affectations ?? data?.affectations?.total_affectations ?? ficheRows.length ?? ordreRows.length ?? 0);

    const moyenneMinutes = Number(data?.delai_moyen?.moyenne_delai_minutes ?? 0);
    const delaiMoyenJours = filtered
        ? Number((moyenneMinutes / 1440).toFixed(2))
        : Number(k.delai_moyen_jours ?? (moyenneMinutes / 1440).toFixed(2));

    const conformity = getConformityCountsFromData(data);
    const nonConfFromDemandes = demandes.filter(row => Number(row.non_conforme_flag || 0) === 1 || getRowConformity(row) === 'non conforme').length;
    const nonConformes = filtered
        ? conformity.nonConformes
        : Number(k.non_conformes ?? conformity.nonConformes ?? nonConfFromDemandes);

    let rapportsPrets = filtered
        ? demandes.filter(isReportReadyForKpi).length
        : Number(k.rapports_prets ?? demandes.filter(isReportReadyForKpi).length);
    rapportsPrets = Math.min(rapportsPrets, terminees);

    return {
        total_demandes: total,
        demandes_ouvertes: ouvertes,
        demandes_en_cours: enCours,
        demandes_terminees: terminees,
        total_affectations: totalAffectations,
        delai_moyen_jours: delaiMoyenJours,
        rapports_prets: rapportsPrets,
        alertes_delai: computeDelayAlertCount(data),
        non_conformes: nonConformes
    };
};

var dashboardOriginalRenderUrgentStrip = renderUrgentStrip;
renderUrgentStrip = function (data) {
    const rows = data?.alertes_list || [];
    if (rows.length || !data?.alertes_summary) {
        return dashboardOriginalRenderUrgentStrip(data);
    }

    const $target = $('#urgentStrip');
    if (!$target.length) return;
    const summary = data.alertes_summary || {};
    const critical = Number(summary.critique || 0);
    const delaiLong = Number(summary.delai_long || data?.kpis_clean?.alertes_delai || 0);
    const rapportManquant = Number(summary.rapport_manquant || 0);
    const nonConf = Number(summary.non_conformite || data?.kpis_clean?.non_conformes || 0);

    $target.html(`
      <div class="urgent-card critical"><span>Risque critique</span><strong>${critical}</strong><em>Demandes prioritaires</em></div>
      <div class="urgent-card warning"><span>Délai long</span><strong>${delaiLong}</strong><em>Dépassement opérationnel</em></div>
      <div class="urgent-card danger"><span>Rapport manquant</span><strong>${rapportManquant}</strong><em>Résultats sans rapport</em></div>
      <div class="urgent-card quality"><span>Non conformité</span><strong>${nonConf}</strong><em>Échantillons à vérifier</em></div>
    `);
};


/* Lightweight loading message for initial launch and lazy tab requests */
var dashboardLoadingCounter = 0;

function dashboardEnsureLoadingLayer() {
    if (document.getElementById('dashboardLoadingLayer')) return;

    const style = document.createElement('style');
    style.id = 'dashboard-loading-layer-style';
    style.textContent = `
        #dashboardLoadingLayer {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(248, 250, 252, 0.72);
            backdrop-filter: blur(2px);
        }
        #dashboardLoadingLayer.dashboard-loading-visible { display: flex; }
        #dashboardLoadingLayer .dashboard-loading-box {
            min-width: 220px;
            padding: 18px 24px;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.16);
            color: #1e3a5f;
            font-weight: 700;
            text-align: center;
            letter-spacing: .2px;
        }
        #dashboardLoadingLayer .dashboard-loading-spinner {
            width: 34px;
            height: 34px;
            margin: 0 auto 10px auto;
            border-radius: 50%;
            border: 4px solid #dbeafe;
            border-top-color: #2563eb;
            animation: dashboardLoadingSpin .8s linear infinite;
        }
        @keyframes dashboardLoadingSpin { to { transform: rotate(360deg); } }
    `;
    document.head.appendChild(style);

    const layer = document.createElement('div');
    layer.id = 'dashboardLoadingLayer';
    layer.innerHTML = '<div class="dashboard-loading-box"><div class="dashboard-loading-spinner"></div><span id="dashboardLoadingText">Chargement...</span></div>';
    document.body.appendChild(layer);
}

function dashboardShowLoading(text) {
    dashboardLoadingCounter += 1;
    dashboardEnsureLoadingLayer();
    $('#dashboardLoadingText').text(text || 'Chargement...');
    $('#dashboardLoadingLayer').addClass('dashboard-loading-visible');
}

function dashboardHideLoading() {
    dashboardLoadingCounter = Math.max(0, dashboardLoadingCounter - 1);
    if (dashboardLoadingCounter === 0) {
        $('#dashboardLoadingLayer').removeClass('dashboard-loading-visible');
    }
}

var dashboardCoreFilterDashboardData = filterDashboardData;
filterDashboardData = function (data) {
    if (!data) return;
    if (!dashboardHasAllCoreLists()) {
        dashboardEnsureFullData().done(function () {
            dashboardCoreFilterDashboardData(originalData);
        });
        return;
    }
    dashboardCoreFilterDashboardData(data);
};

initTabs = function () {
    $(document).off('click.dashboardTabs', '.tab-btn').on('click.dashboardTabs', '.tab-btn', function () {
        const view = $(this).data('view');
        if (!view) return;
        currentView = view;
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.view').removeClass('active');
        $('#view-' + view).addClass('active');
        updateContextBar();
        revealActiveView();
        dashboardEnsureViewData(view).always(function () {
            if (hasAnyFilter() || hasAnyDrilldown()) filterDashboardData(originalData);
            else dashboardRefreshFromData(originalData || dashboardCurrentData);
        });
    });
};

loadDashboard = function () {
    dashboardShowLoading('Chargement...');
    $.ajax({
        url: '../back_end/api.php',
        dataType: 'json',
        cache: false,
        success: function (data) {
            if (!data || data.status === 'error') {
                $('#kpiGrid').html(`
                    <div class="kpi-card danger">
                        <div class="kpi-label">Erreur API</div>
                        <div class="kpi-value">JSON</div>
                        <div class="kpi-foot">${data?.message || 'Réponse invalide'}</div>
                    </div>
                `);
                return;
            }
            dashboardMarkInitialPayload(data);
            originalData = data;
            if (hasAnyFilter() || hasAnyDrilldown()) filterDashboardData(originalData);
            else dashboardRefreshFromData(originalData);
        },
        error: function () {
            $('#kpiGrid').html(`
                <div class="kpi-card danger">
                    <div class="kpi-label">Erreur</div>
                    <div class="kpi-value">API</div>
                    <div class="kpi-foot">../back_end/api.php indisponible</div>
                </div>
            `);
        },
        complete: function () {
            dashboardHideLoading();
        }
    });
};






























