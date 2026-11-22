$(function () {
    initTabs();
    initFilters();
    bindContextActions();
    initStaticUi();
    loadDashboard();
});

const API_URL = '../back_end/api.php';

let dashboardData = null;
let currentView = 'global';
let currentDrilldown = null;
let filterCollapsed = false;

const MONTHS = [
    { id: 1, name: 'Janvier' },
    { id: 2, name: 'Février' },
    { id: 3, name: 'Mars' },
    { id: 4, name: 'Avril' },
    { id: 5, name: 'Mai' },
    { id: 6, name: 'Juin' },
    { id: 7, name: 'Juillet' },
    { id: 8, name: 'Août' },
    { id: 9, name: 'Septembre' },
    { id: 10, name: 'Octobre' },
    { id: 11, name: 'Novembre' },
    { id: 12, name: 'Décembre' }
];

const COLORS = {
    blue: '#2563eb',
    blueSoft: '#6ea8ff',
    blueDark: '#17325c',
    green: '#10b981',
    red: '#ef4444',
    orange: '#f59e0b',
    violet: '#8b5cf6',
    cyan: '#06b6d4',
    slate: '#64748b',
    gray: '#94a3b8'
};

function initStaticUi() {
    updateContextTexts();
    updateCurrentFilterBtn();
    renderActiveFilterBadges();
}

function initTabs() {
    $(document).off('click.tabSwitch').on('click.tabSwitch', '.tab-btn', function () {
        const nextView = $(this).data('view');
        if (!nextView || nextView === currentView) return;

        currentView = nextView;
        $('.tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.view').removeClass('active');
        $('#view-' + currentView).addClass('active');

        updateContextTexts();
        updateCurrentFilterBtn();
        revealActiveView();

        setTimeout(function () {
            repaintChartsInActiveView();
            repaintVisibleGrids();
        }, 100);
    });
}

function initFilters() {
    $('#dashboardDateDebut').dxDateBox({
        type: 'date',
        displayFormat: 'yyyy-MM-dd',
        stylingMode: 'outlined',
        showClearButton: true,
        useMaskBehavior: true,
        onValueChanged: renderActiveFilterBadges
    });

    $('#dashboardDateFin').dxDateBox({
        type: 'date',
        displayFormat: 'yyyy-MM-dd',
        stylingMode: 'outlined',
        showClearButton: true,
        useMaskBehavior: true,
        onValueChanged: renderActiveFilterBadges
    });

    $('#dashboardYearFilter').dxSelectBox({
        dataSource: [],
        displayExpr: 'annee',
        valueExpr: 'annee',
        stylingMode: 'outlined',
        placeholder: 'Sélectionner une année',
        showClearButton: true,
        onValueChanged: renderActiveFilterBadges
    });

    $('#dashboardMonthFilter').dxSelectBox({
        dataSource: MONTHS,
        displayExpr: 'name',
        valueExpr: 'id',
        stylingMode: 'outlined',
        placeholder: 'Sélectionner un mois',
        showClearButton: true,
        onValueChanged: renderActiveFilterBadges
    });

    $('#btnDashboardFiltrer').dxButton({
        text: 'Appliquer',
        icon: 'find',
        type: 'default',
        stylingMode: 'contained',
        onClick: function () {
            currentDrilldown = null;
            updateCurrentFilterBtn();
            loadDashboard();
        }
    });

    $('#btnDashboardReset').dxButton({
        text: 'Réinitialiser',
        icon: 'refresh',
        stylingMode: 'outlined',
        onClick: performFullReset
    });

    $('#btnDashboardToday').dxButton({
        text: 'Aujourd\'hui',
        icon: 'event',
        stylingMode: 'outlined',
        onClick: function () {
            const today = new Date();
            setFilterValues(today, today, null, null);
            currentDrilldown = null;
            updateCurrentFilterBtn();
            renderActiveFilterBadges();
            loadDashboard();
        }
    });

    $('#btnDashboardThisWeek').dxButton({
        text: 'Cette semaine',
        icon: 'calendar',
        stylingMode: 'outlined',
        onClick: function () {
            const now = new Date();
            const day = now.getDay() || 7;
            const monday = new Date(now);
            monday.setDate(now.getDate() - day + 1);
            const sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6);
            setFilterValues(monday, sunday, null, null);
            currentDrilldown = null;
            updateCurrentFilterBtn();
            renderActiveFilterBadges();
            loadDashboard();
        }
    });

    $('#btnDashboardThisMonth').dxButton({
        text: 'Ce mois',
        icon: 'calendar',
        stylingMode: 'outlined',
        onClick: function () {
            const now = new Date();
            setFilterValues(null, null, now.getFullYear(), now.getMonth() + 1);
            currentDrilldown = null;
            updateCurrentFilterBtn();
            renderActiveFilterBadges();
            loadDashboard();
        }
    });

    $('#btnDashboardThisYear').dxButton({
        text: 'Cette année',
        icon: 'clock',
        stylingMode: 'outlined',
        onClick: function () {
            const now = new Date();
            setFilterValues(null, null, now.getFullYear(), null);
            currentDrilldown = null;
            updateCurrentFilterBtn();
            renderActiveFilterBadges();
            loadDashboard();
        }
    });

    $('#btnToggleFilterBar').dxButton({
        text: '−',
        stylingMode: 'outlined',
        onClick: function (e) {
            filterCollapsed = !filterCollapsed;
            $('#filterShell').toggleClass('collapsed', filterCollapsed);
            e.component.option('text', filterCollapsed ? '+' : '−');
        }
    });
}

function bindContextActions() {
    $('#clearDrilldownBtn').off('click').on('click', function () {
        currentDrilldown = null;
        updateCurrentFilterBtn();
        if (!dashboardData) return;
        const resetData = cloneData(dashboardData);
        renderAll(resetData);
        revealActiveView();
        setTimeout(function () {
            repaintChartsInActiveView();
            repaintVisibleGrids();
        }, 100);
    });

    $('#showCurrentFilterBtn').off('click').on('click', function () {
        if (!currentDrilldown) {
            DevExpress.ui.notify('Aucun drill-down', 'info', 1500);
            return;
        }
        DevExpress.ui.notify(currentDrilldown.label, 'info', 1800);
    });

    $('#activeFilterBadges').off('click', '.badge-reset').on('click', '.badge-reset', function () {
        const key = $(this).data('key');
        clearOneFilter(key);
        currentDrilldown = null;
        updateCurrentFilterBtn();
        loadDashboard();
    });
}

function setFilterValues(dateStart, dateEnd, year, month) {
    $('#dashboardDateDebut').dxDateBox('instance').option('value', dateStart);
    $('#dashboardDateFin').dxDateBox('instance').option('value', dateEnd);
    $('#dashboardYearFilter').dxSelectBox('instance').option('value', year);
    $('#dashboardMonthFilter').dxSelectBox('instance').option('value', month);
}

function clearOneFilter(key) {
    if (key === 'date_start') $('#dashboardDateDebut').dxDateBox('instance').option('value', null);
    if (key === 'date_end') $('#dashboardDateFin').dxDateBox('instance').option('value', null);
    if (key === 'year') $('#dashboardYearFilter').dxSelectBox('instance').option('value', null);
    if (key === 'month') $('#dashboardMonthFilter').dxSelectBox('instance').option('value', null);
    renderActiveFilterBadges();
}

function performFullReset() {
    setFilterValues(null, null, null, null);
    currentDrilldown = null;
    updateCurrentFilterBtn();
    renderActiveFilterBadges();
    showLoadingState();
    loadDashboard();
}

function buildApiParams() {
    const params = {};
    const dateStart = $('#dashboardDateDebut').dxDateBox('instance').option('value');
    const dateEnd = $('#dashboardDateFin').dxDateBox('instance').option('value');
    const year = $('#dashboardYearFilter').dxSelectBox('instance').option('value');
    const month = $('#dashboardMonthFilter').dxSelectBox('instance').option('value');

    if (dateStart) params.date_start = formatDateForApi(dateStart);
    if (dateEnd) params.date_end = formatDateForApi(dateEnd);
    if (year) params.year = year;
    if (month) params.month = month;
    return params;
}

function loadDashboard() {
    showLoadingState();
    $.ajax({
        url: API_URL,
        method: 'GET',
        dataType: 'json',
        cache: false,
        data: buildApiParams(),
        success: function (data) {
            if (!data || data.status !== 'success') {
                showError(data && data.message ? data.message : 'Réponse API invalide');
                return;
            }
            dashboardData = cloneData(data);
            hydrateYearFilter(data);
            renderActiveFilterBadges();
            updateContextTexts();
            updateCurrentFilterBtn();

            const rendered = applyCurrentDrilldown(dashboardData);
            renderAll(rendered);
            revealActiveView();
            setTimeout(function () {
                repaintChartsInActiveView();
                repaintVisibleGrids();
            }, 120);
        },
        error: function (xhr) {
            console.error(xhr.responseText || xhr.statusText || xhr);
            showError('Erreur chargement API');
        }
    });
}

function hydrateYearFilter(data) {
    let years = Array.isArray(data?.filter_options?.years) ? data.filter_options.years : [];
    if (!years.length) {
        const dates = [].concat(data.demandes_clients_grid || [], data.devis_grid || []).map(r => r.date_demande || r.date_creation || r.date_facture).filter(Boolean);
        years = [...new Set(dates.map(d => new Date(d).getFullYear()).filter(Boolean))].sort((a, b) => b - a).map(y => ({ annee: y }));
    }
    $('#dashboardYearFilter').dxSelectBox('instance').option('dataSource', years);
}

function cloneData(data) {
    return JSON.parse(JSON.stringify(data || {}));
}

function applyDrilldown(type, value, label) {
    currentDrilldown = { type, value, label };
    updateCurrentFilterBtn();
    const rendered = applyCurrentDrilldown(dashboardData);
    renderAll(rendered);
    revealActiveView();
    setTimeout(function () {
        repaintChartsInActiveView();
        repaintVisibleGrids();
    }, 90);
}

function applyCurrentDrilldown(data) {
    if (!currentDrilldown) return cloneData(data);
    const cloned = cloneData(data);
    const type = currentDrilldown.type;
    const value = currentDrilldown.value;

    if (type === 'client') {
        cloned.demandes_clients_grid = (cloned.demandes_clients_grid || []).filter(r => (r.client_nom || '') === value);
        cloned.devis_grid = (cloned.devis_grid || []).filter(r => (r.client_nom || '') === value);
        return cloned;
    }
    if (type === 'analyse') {
        cloned.demandes_clients_grid = (cloned.demandes_clients_grid || []).filter(r => (r.analyse_nom || '') === value);
        cloned.analyses_grid = (cloned.analyses_grid || []).filter(r => (r.analyse_nom || '') === value);
        return cloned;
    }
    if (type === 'risk') {
        cloned.demandes_clients_grid = (cloned.demandes_clients_grid || []).filter(r => (r.niveau_risque_ml || '') === value);
        return cloned;
    }
    if (type === 'retard_predit') {
        const wantsRetard = normalizeText(value).includes('retard');
        cloned.demandes_clients_grid = (cloned.demandes_clients_grid || []).filter(r => {
            const hasRetard = isOui(r.retard_predit) || toNumber(r.retard_predit) === 1 || toNumber(r.jours_retard_predit) > 0;
            return wantsRetard ? hasRetard : !hasRetard;
        });
        return cloned;
    }
    if (type === 'commercial_client') {
        cloned.devis_grid = (cloned.devis_grid || []).filter(r => (r.client_nom || '') === value);
        return cloned;
    }
    if (type === 'devis_etat') {
        cloned.devis_grid = (cloned.devis_grid || []).filter(r => (r.etat_devis || '') === value);
        return cloned;
    }
    if (type === 'commercial_month') {
        cloned.devis_grid = (cloned.devis_grid || []).filter(r => getPeriodeFromDate(r.date_creation) === value);
        return cloned;
    }
    return cloned;
}

function renderAll(data) {
    renderGlobalSection(data);
    renderClientsSection(data);
    renderAnalysesSection(data);
    renderCommercialSection(data);
}


function isUnknownLabel(value) {
    const raw = normalizeText(value);
    if (!raw) return true;
    return raw === '-' || raw === 'na' || raw === 'n/a' || raw === 'null' || raw === 'undefined' || raw === 'non defini' || raw === 'non definie' || raw === 'inconnu' || raw === 'client inconnu' || raw === 'analyse inconnue' || raw.includes('inconnu');
}

function filterKnownRows(rows, field) {
    return (rows || []).filter(r => !isUnknownLabel(r[field]));
}

function firstKnownValue(rows, labelField, valueField) {
    const known = filterKnownRows(rows, labelField)
        .slice()
        .sort((a, b) => toNumber(b[valueField]) - toNumber(a[valueField]));
    return known.length ? known[0][labelField] : '-';
}

function cleanRiskRows(rows) {
    return (rows || []).filter(r => {
        const label = r.niveau_risque || r.label || r.risque || '';
        return !isUnknownLabel(label) && normalizeRisk(label) !== 'none' && toNumber(r.total || r.value) > 0;
    });
}

function buildRetardPredictedRows(data) {
    const rows = data?.demandes_clients_grid || [];
    let oui = 0;
    let non = 0;
    rows.forEach(r => {
        if (isOui(r.retard_predit) || toNumber(r.retard_predit) === 1 || toNumber(r.jours_retard_predit) > 0) oui += 1;
        else non += 1;
    });
    return [
        { label: 'Retard prédit', total: oui },
        { label: 'Sans retard prédit', total: non }
    ].filter(r => r.total > 0);
}

function shortLabel(value, maxLen = 34) {
    const text = textOrDash(value);
    return text.length > maxLen ? text.slice(0, maxLen - 1) + '…' : text;
}


function renderGlobalSection(data) {
    const k = data?.kpis?.global || data?.vue_globale?.kpis || {};
    const charts = data?.charts || data?.vue_globale?.charts || {};

    $('#kpiGrid').html([
        kpiCard('Total demandes clients', toNumber(k.total_demandes_clients), 'Volume global reçu', 'primary'),
        kpiCard('Taux faisabilité', pctLabel(k.taux_faisabilite), 'Demandes réalisables', rateClass(k.taux_faisabilite)),
        kpiCard('Taux validation', pctLabel(k.taux_validation), 'Demandes validées', rateClass(k.taux_validation)),
        kpiCard('Délai moyen global', formatDurationDays(k.delai_moyen_global_jours), 'Moyenne de traitement', 'warning'),
        kpiCard('Taux conformité', pctLabel(k.taux_conformite), 'Qualité échantillons', rateClass(k.taux_conformite)),
        kpiCard('Non-conformités', toNumber(k.non_conformites), 'Échantillons à problème', 'danger'),
        kpiCard('Taux devis → facture', pctLabel(k.taux_devis_facture), 'Transformation commerciale', 'violet'),
        kpiCard('Rapports finaux', toNumber(k.rapports_finaux), 'Rapports finalisés', 'success'),
        kpiCard('Demandes critiques ML', toNumber(k.demandes_critiques_ml), 'Priorités prédictives', 'danger'),
        kpiCard('Retards prédits', toNumber(k.retards_predits), 'Synthèse ML', 'violet')
    ].join(''));

    $('#heroPredictedDelays').text(toNumber(k.retards_predits));

    renderFunnel('#funnelChart', charts.funnel_performance || []);
    renderPredictionStats(data);
    renderLineChart('#evolutionDelayChart', normalizeMonthValueRows(charts.evolution_delai_moyen, 'mois', 'delai_moyen_jours'), 'mois', 'delai_moyen_jours', 'Délai moyen', COLORS.violet, true);
    renderGroupedYesNoChart('#faisabiliteValidationChart', charts.faisabilite_validation_globale || []);
    renderConformiteGlobal('#conformiteGlobalChart', '#conformiteGlobalCenter', charts.conformite_globale || []);
    renderPredictedDelaySummary('#riskDistributionChart', data);
    renderCommercialSummary('#commercialSummaryChart', charts.commercial_summary || charts.evolution_commerciale || charts.devis_vs_factures || []);
}

function renderPredictionStats(data) {
    const k = data?.kpis?.global || data?.vue_globale?.kpis || {};
    const charts = data?.charts || {};
    const stats = data?.vue_globale?.charts?.statistiques_predictives || charts.statistiques_predictives || {};
    const riskHighRate = toNumber(stats.taux_risque_eleve ?? k.taux_risque_eleve ?? 0);
    const predictedDelay = toNumber(stats.delai_moyen_predit_jours ?? k.delai_moyen_predit_jours ?? 0);
   
    $('#predictiveStats').html([
        statItem('Demandes critiques ML', toNumber(k.demandes_critiques_ml)),
        statItem('Retards prédits', toNumber(k.retards_predits)),
        statItem('Taux risque élevé', pctLabel(riskHighRate)),
        statItem('Délai moyen prédit', formatDurationDays(predictedDelay)),
    ].join(''));
}


function renderClientsSection(data) {
    const k = data?.kpis?.clients || data?.clients?.kpis || {};
    const charts = data?.charts || data?.clients?.charts || {};
    const rows = data.demandes_clients_grid || [];

    const topClients = filterKnownRows(charts.top_clients_volume || [], 'client_nom');
    const delayClients = filterKnownRows(charts.delai_moyen_par_client || [], 'client_nom');
    const fvClients = filterKnownRows(charts.faisabilite_validation_par_client || [], 'client_nom');
    const priorityClients = filterKnownRows(charts.clients_prioritaires_ml || [], 'client_nom').filter(r => toNumber(r.score_priorite_ml || r.total || r.value) > 0);
    const timeline = (charts.volume_demandes_client_temps || []).filter(r => !isUnknownLabel(r.client_nom));

    const clientLePlusActif = isUnknownLabel(k.client_le_plus_actif) ? firstKnownValue(topClients, 'client_nom', 'total_demandes') : k.client_le_plus_actif;
    const clientLePlusLent = isUnknownLabel(k.client_le_plus_lent) ? firstKnownValue(delayClients, 'client_nom', 'delai_moyen_jours') : k.client_le_plus_lent;

    $('#clientsKpiGrid').html([
        kpiCard('Clients actifs', toNumber(k.clients_actifs), 'Clients avec demandes', 'primary'),
        kpiCard('Client le plus actif', textOrDash(clientLePlusActif), 'Volume le plus élevé', 'violet'),
        kpiCard('Délai moyen client', formatDurationDays(k.delai_moyen_client_jours), 'Moyenne clients', 'warning'),
        kpiCard('Meilleur taux validation', textOrDash(k.meilleur_taux_validation_client), 'Client le plus stable', 'success'),
        kpiCard('Client le plus lent', textOrDash(clientLePlusLent), 'Délai moyen élevé', 'danger'),
        kpiCard('Clients prioritaires ML', toNumber(k.clients_prioritaires_ml), 'Score priorité > 0', 'violet'),
        kpiCard('Retards prédits par client', toNumber(k.retards_predits_par_client), 'Alertes ML client', 'danger')
    ].join(''));

    renderHorizontalBar('#topClientsChart', topClients, 'client_nom', 'total_demandes', 'Demandes', COLORS.blueSoft, 'client');
    renderHorizontalBar('#delayByClientChart', delayClients, 'client_nom', 'delai_moyen_jours', 'Délai moyen', COLORS.orange, 'client', true);
    renderClientFeasValidation('#faisabiliteValidationClientChart', fvClients);
    renderClientTimeline('#clientTimelineChart', timeline);
    renderHorizontalBar('#clientsPriorityMlChart', priorityClients, 'client_nom', 'score_priorite_ml', 'Score priorité ML', COLORS.violet, 'client');
    renderDemandesClientsGrid(rows.filter(r => !isUnknownLabel(r.client_nom)));
}


function renderAnalysesSection(data) {
    const k = data?.kpis?.analyses_conformite || data?.analyses_conformite?.kpis || {};
    const charts = data?.charts || data?.analyses_conformite?.charts || {};

    const frequentAnalyses = filterKnownRows(charts.analyses_frequentes || [], 'analyse_nom');
    const delayAnalyses = filterKnownRows(charts.delai_moyen_par_analyse || [], 'analyse_nom');
    const sensitiveAnalyses = filterKnownRows(charts.analyses_sensibles_ml || [], 'analyse_nom').filter(r => toNumber(r.score_sensibilite || r.total || r.value) > 0);
    const lateAnalyses = filterKnownRows(charts.retards_predits_par_analyse || [], 'analyse_nom').filter(r => toNumber(r.retards_predits || r.total || r.value) > 0);
    const volumeDelay = filterKnownRows(charts.volume_delai_par_analyse || [], 'analyse_nom');

    const analyseFrequente = isUnknownLabel(k.analyse_la_plus_frequente) ? firstKnownValue(frequentAnalyses, 'analyse_nom', 'total') : k.analyse_la_plus_frequente;
    const analyseLente = isUnknownLabel(k.analyse_la_plus_lente) ? firstKnownValue(delayAnalyses, 'analyse_nom', 'delai_moyen_jours') : k.analyse_la_plus_lente;
    const analyseSensible = isUnknownLabel(k.analyse_la_plus_sensible_ml) ? firstKnownValue(sensitiveAnalyses, 'analyse_nom', 'score_sensibilite') : k.analyse_la_plus_sensible_ml;

    $('#analysesKpiGrid').html([
        kpiCard('Analyses distinctes', toNumber(k.analyses_distinctes), 'Catalogue utilisé', 'primary'),
        kpiCard('Analyse la plus fréquente', textOrDash(analyseFrequente), 'Plus grand volume', 'violet'),
        kpiCard('Analyse la plus lente', textOrDash(analyseLente), 'Délai moyen élevé', 'danger'),
        kpiCard('Délai moyen analyse', formatDurationDays(k.delai_moyen_analyse_jours), 'Performance technique', 'warning'),
        kpiCard('Analyse sensible ML', textOrDash(analyseSensible), 'Score sensibilité', 'violet'),
        kpiCard('Retards prédits analyse', toNumber(k.retards_predits_par_analyse), 'Synthèse ML', 'danger')
    ].join(''));

    renderHorizontalBar('#frequentAnalysesChart', frequentAnalyses, 'analyse_nom', 'total', 'Demandes', COLORS.blue, 'analyse');
    renderHorizontalBar('#analyseSensitivityMlChart', sensitiveAnalyses, 'analyse_nom', 'score_sensibilite', 'Score sensibilité', COLORS.violet, 'analyse');
    renderHorizontalBar('#predictedLateAnalyseChart', lateAnalyses, 'analyse_nom', 'retards_predits', 'Retards prédits', COLORS.red, 'analyse');
    renderVolumeDelayAnalyse('#volumeDelayAnalyseChart', volumeDelay);
    renderAnalysesGrid((data.analyses_grid || []).filter(r => !isUnknownLabel(r.analyse_nom)));
}

function renderCommercialSection(data) {
    const k = data?.kpis?.commercial || data?.commercial?.kpis || {};
    const charts = data?.charts || data?.commercial?.charts || {};
    $('#commercialKpiGrid').html([
        kpiCard('Nombre devis', toNumber(k.nombre_devis), 'Opportunités commerciales', 'primary'),
        kpiCard('Nombre factures', toNumber(k.nombre_factures), 'Devis transformés', 'success'),
        kpiCard('Taux transformation', pctLabel(k.taux_transformation), 'Conversion devis → facture', 'violet'),
        kpiCard('Devis en attente', toNumber(k.devis_en_attente), 'À suivre', 'warning'),
        kpiCard('Devis transformés', toNumber(k.devis_transformes), 'Réussis', 'success'),
        kpiCard('Délai devis → facture', formatDurationDays(k.delai_moyen_devis_facture_jours), 'Réactivité commerciale', 'warning')
    ].join(''));

    renderDevisVsFactures('#devisFacturesChart', charts.devis_vs_factures || []);
    renderLineChart('#transformationRateChart', charts.taux_transformation_mensuel || [], 'mois', 'taux_transformation', 'Taux transformation', COLORS.violet, false, '%');
    renderCommercialTrend('#commercialTrendChart', charts.evolution_commerciale || []);
    renderDevisState('#devisStateChart', '#devisStateCenter', charts.devis_par_etat || []);
    renderHorizontalBar('#topClientsDevisChart', charts.top_clients_par_devis || [], 'client_nom', 'total_devis', 'Devis', COLORS.blueSoft, 'commercial_client');
    renderLineChart('#delayDevisFactureChart', charts.delai_devis_facture || [], 'mois', 'delai_moyen_devis_facture_jours', 'Délai devis → facture', COLORS.orange, true);
    renderDevisGrid(data.devis_grid || []);
}

function kpiCard(label, value, foot, type) {
    return `
        <div class="kpi-card ${type || 'primary'} reveal shown">
            <div class="kpi-topline"><span class="kpi-dot"></span></div>
            <div class="kpi-label">${escapeHtml(label)}</div>
            <div class="kpi-value">${escapeHtml(value)}</div>
            <div class="kpi-foot">${escapeHtml(foot || '')}</div>
        </div>
    `;
}

function statItem(label, value) {
    return `<div class="prediction-stat"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`;
}

function renderFunnel(selector, rows) {
    resetChartContainer(selector);
    const dataSource = (rows || []).map(r => ({ stage: r.etape || r.stage || '-', total: toNumber(r.total || r.value) }));
    if (!dataSource.length) return emptyChart(selector);
    $(selector).dxFunnel({
        dataSource,
        argumentField: 'stage',
        valueField: 'total',
        sortData: false,
        inverted: true,
        palette: ['#2563eb', '#3b82f6', '#10b981', '#8b5cf6', '#06b6d4', '#17325c'],
        label: { visible: true, position: 'inside', backgroundColor: 'none', customizeText: arg => `${arg.item.argument} : ${arg.value}` },
        tooltip: { enabled: true }
    });
}

function renderLineChart(selector, rows, argField, valueField, name, color, isDelay, suffix) {
    resetChartContainer(selector);
    const dataSource = (rows || []).map(r => ({ ...r, [valueField]: toNumber(r[valueField]) })).filter(r => r[argField] !== undefined && r[argField] !== null);
    if (!dataSource.length) return emptyChart(selector);
    $(selector).dxChart({
        dataSource,
        commonSeriesSettings: { argumentField: argField, type: 'spline', point: { visible: true, size: 6 }, width: 3 },
        series: [{ valueField, name, color }],
        legend: { visible: false },
        argumentAxis: { label: { overlappingBehavior: 'stagger' }, tick: { visible: false }, grid: { visible: false } },
        valueAxis: { tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: {
            enabled: true,
            customizeTooltip: function (arg) {
                const val = isDelay ? formatDurationDays(arg.value) : `${arg.valueText}${suffix || ''}`;
                return { text: `${arg.argumentText}<br>${name}: ${val}` };
            }
        },
        margin: { top: 12, right: 16, bottom: 8, left: 10 }
    });
}

function renderGroupedYesNoChart(selector, rows) {
    resetChartContainer(selector);
    const ds = (rows || []).map(r => ({ dimension: r.dimension || '-', oui: toNumber(r.oui), non: toNumber(r.non) }));
    if (!ds.length) return emptyChart(selector);
    $(selector).dxChart({
        dataSource: ds,
        commonSeriesSettings: { argumentField: 'dimension', type: 'bar' },
        series: [
            { valueField: 'oui', name: 'Oui', color: COLORS.green },
            { valueField: 'non', name: 'Non', color: COLORS.red }
        ],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        argumentAxis: { tick: { visible: false }, grid: { visible: false } },
        valueAxis: { tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: { enabled: true },
        onDone: function () { roundBarChartShapes(selector); }
    });
}

function renderConformiteGlobal(selector, centerSelector, rows) {
    resetChartContainer(selector);
    const ds = (rows || []).map(r => ({ label: r.label || r.conformite || '-', total: toNumber(r.total || r.value) }));
    if (!ds.length) return emptyChart(selector);
    $(selector).dxPieChart({
        type: 'doughnut',
        innerRadius: 0.62,
        dataSource: ds,
        palette: [COLORS.green, COLORS.red, COLORS.gray],
        series: [{ argumentField: 'label', valueField: 'total', label: { visible: true, connector: { visible: false }, customizeText: a => `${a.argumentText} ${a.valueText}` } }],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        tooltip: { enabled: true },
        onPointClick: e => applyDrilldown('conformite', e.target.argument, 'Conformité : ' + e.target.argument)
    });
    const total = ds.reduce((s, x) => s + x.total, 0);
    $(centerSelector).html(`<strong>${total}</strong><span>échantillons</span>`);
}


function renderPredictedDelaySummary(selector, data) {
    resetChartContainer(selector);
    const rows = buildRetardPredictedRows(data);
    if (!rows.length) return emptyChart(selector);
    $(selector).dxPieChart({
        type: 'doughnut',
        innerRadius: 0.62,
        dataSource: rows,
        palette: [COLORS.red, COLORS.green],
        series: [{
            argumentField: 'label',
            valueField: 'total',
            label: { visible: true, connector: { visible: false }, customizeText: a => `${a.argumentText} ${a.valueText}` }
        }],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        tooltip: { enabled: true },
        onPointClick: function (e) {
            applyDrilldown('retard_predit', e.target.argument, 'Retard prédit : ' + e.target.argument);
        }
    });
}



function renderRiskDistribution(selector, rows) {
    resetChartContainer(selector);
    const ds = cleanRiskRows(rows).map(r => ({ niveau: r.niveau_risque || r.label || '-', total: toNumber(r.total || r.value) }));
    if (!ds.length) return emptyChart(selector);
    $(selector).dxChart({
        dataSource: ds,
        rotated: true,
        commonSeriesSettings: { argumentField: 'niveau', type: 'bar' },
        series: [{ valueField: 'total', name: 'Demandes', color: COLORS.violet }],
        legend: { visible: false },
        argumentAxis: { tick: { visible: false }, grid: { visible: false } },
        valueAxis: { tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: { enabled: true },
        onPointClick: e => applyDrilldown('risk', e.target.argument, 'Risque : ' + e.target.argument),
        onDone: function () { roundBarChartShapes(selector); }
    });
}

function renderCommercialSummary(selector, rows) {
    resetChartContainer(selector);
    const ds = normalizeMonthValueRows(rows, 'mois', 'devis');
    if (!ds.length) return emptyChart(selector);
    $(selector).dxChart({
        dataSource: ds,
        commonSeriesSettings: { argumentField: 'mois' },
        series: [
            { valueField: 'devis', name: 'Devis', type: 'bar', color: COLORS.blueSoft },
            { valueField: 'factures', name: 'Factures', type: 'bar', color: COLORS.blue },
            { valueField: 'taux_transformation', name: 'Taux %', type: 'spline', axis: 'tauxAxis', color: COLORS.violet, point: { visible: true, size: 6 } }
        ],
        valueAxis: [
            { name: 'default', position: 'left', tick: { visible: false }, grid: { color: '#e3ebf7' } },
            { name: 'tauxAxis', position: 'right', max: 100, tick: { visible: false }, grid: { visible: false } }
        ],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        argumentAxis: { label: { overlappingBehavior: 'stagger' }, tick: { visible: false }, grid: { visible: false } },
        tooltip: { enabled: true },
        onPointClick: e => applyDrilldown('commercial_month', e.target.argument, 'Période : ' + e.target.argument),
        onDone: function () { roundBarChartShapes(selector); }
    });
}

function renderHorizontalBar(selector, rows, argField, valueField, name, color, drillType, isDelay, suffix) {
    resetChartContainer(selector);
    const ds = (rows || []).map(r => ({ ...r, [valueField]: toNumber(r[valueField]) })).filter(r => r[argField] !== undefined && r[argField] !== null).slice(0, 12);
    if (!ds.length) return emptyChart(selector);
    $(selector).dxChart({
        dataSource: ds,
        rotated: true,
        commonSeriesSettings: { argumentField: argField, type: 'bar' },
        series: [{ valueField, name, color }],
        legend: { visible: false },
        argumentAxis: { tick: { visible: false }, grid: { visible: false }, label: { overlappingBehavior: 'stagger' } },
        valueAxis: { tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: { enabled: true, customizeTooltip: arg => ({ text: `${arg.argumentText}<br>${name}: ${isDelay ? formatDurationDays(arg.value) : arg.valueText + (suffix || '')}` }) },
        onPointClick: function (e) {
            if (drillType) applyDrilldown(drillType, e.target.argument, `${name} : ${e.target.argument}`);
        },
        onDone: function () { roundBarChartShapes(selector); }
    });
}

function renderClientFeasValidation(selector, rows) {
    resetChartContainer(selector);
    const ds = (rows || []).slice(0, 10).map(r => ({
        client_nom: r.client_nom || '-',
        faisables: toNumber(r.faisables),
        valides: toNumber(r.valides),
        non_faisables: toNumber(r.non_faisables),
        non_valides: toNumber(r.non_valides)
    }));
    if (!ds.length) return emptyChart(selector);
    $(selector).dxChart({
        dataSource: ds,
        commonSeriesSettings: { argumentField: 'client_nom', type: 'bar' },
        series: [
            { valueField: 'faisables', name: 'Faisables', color: COLORS.green },
            { valueField: 'valides', name: 'Validées', color: COLORS.blue },
            { valueField: 'non_faisables', name: 'Non faisables', color: COLORS.red },
            { valueField: 'non_valides', name: 'Non validées', color: COLORS.orange }
        ],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        argumentAxis: { label: { overlappingBehavior: 'rotate', rotationAngle: 25 }, tick: { visible: false }, grid: { visible: false } },
        valueAxis: { tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: { enabled: true },
        onPointClick: e => applyDrilldown('client', e.target.argument, 'Client : ' + e.target.argument),
        onDone: function () { roundBarChartShapes(selector); }
    });
}


function renderClientTimeline(selector, rows) {
    resetChartContainer(selector);
    const clean = filterKnownRows(rows || [], 'client_nom');
    if (!clean.length) return emptyChart(selector);

    const clients = [...new Set(clean.map(r => r.client_nom).filter(Boolean))].slice(0, 5);
    const byMonth = {};
    clean.forEach(r => {
        const mois = r.mois || r.periode || '-';
        if (!byMonth[mois]) byMonth[mois] = { mois };
        if (clients.includes(r.client_nom)) byMonth[mois][r.client_nom] = toNumber(r.total_demandes || r.total || r.value);
    });
    const ds = Object.values(byMonth).sort((a, b) => String(a.mois).localeCompare(String(b.mois)));

    $(selector).dxChart({
        dataSource: ds,
        commonSeriesSettings: { argumentField: 'mois', type: 'spline', point: { visible: true, size: 5 } },
        series: clients.map((client, i) => ({ valueField: client, name: client, color: [COLORS.blue, COLORS.green, COLORS.orange, COLORS.violet, COLORS.cyan][i % 5] })),
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        argumentAxis: { label: { overlappingBehavior: 'stagger' }, tick: { visible: false }, grid: { visible: false } },
        valueAxis: { tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: { enabled: true }
    });
}

function renderVolumeDelayAnalyse(selector, rows) {
    resetChartContainer(selector);
    const ds = filterKnownRows(rows || [], 'analyse_nom')
        .slice()
        .sort((a, b) => toNumber(b.volume || b.total) - toNumber(a.volume || a.total))
        .slice(0, 8)
        .map(r => ({
            analyse_nom: r.analyse_nom,
            analyse_label: shortLabel(r.analyse_nom, 30),
            volume: toNumber(r.volume || r.total),
            delai_moyen_jours: toNumber(r.delai_moyen_jours)
        }));
    if (!ds.length) return emptyChart(selector);
    $(selector).dxChart({
        dataSource: ds,
        commonSeriesSettings: { argumentField: 'analyse_label' },
        series: [
            { type: 'bar', valueField: 'volume', name: 'Volume', axis: 'volumeAxis', color: COLORS.blueSoft },
            { type: 'spline', valueField: 'delai_moyen_jours', name: 'Délai moyen', axis: 'delayAxis', color: COLORS.orange, point: { visible: true, size: 7 }, width: 3 }
        ],
        valueAxis: [
            { name: 'volumeAxis', position: 'left', title: 'Volume', tick: { visible: false }, grid: { color: '#e3ebf7' } },
            { name: 'delayAxis', position: 'right', title: 'Délai', tick: { visible: false }, grid: { visible: false }, label: { customizeText: arg => formatDurationDays(arg.value) } }
        ],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        argumentAxis: {
            label: { overlappingBehavior: 'rotate', rotationAngle: 20 },
            tick: { visible: false },
            grid: { visible: false }
        },
        margin: { left: 18, right: 34, top: 14, bottom: 18 },
        tooltip: {
            enabled: true,
            customizeTooltip: function (arg) {
                const full = arg.point.data.analyse_nom;
                if (arg.seriesName === 'Délai moyen') return { text: `${full}<br>Délai : ${formatDurationDays(arg.value)}` };
                return { text: `${full}<br>Volume : ${arg.valueText}` };
            }
        },
        onPointClick: e => applyDrilldown('analyse', e.target.data.analyse_nom, 'Analyse : ' + e.target.data.analyse_nom),
        onDone: function () { roundBarChartShapes(selector); }
    });
}

function renderDevisVsFactures(selector, rows) {
    resetChartContainer(selector);
    const ds = rows || [];
    if (!ds.length) return emptyChart(selector);
    $(selector).dxChart({
        dataSource: ds,
        commonSeriesSettings: { argumentField: 'mois', type: 'bar' },
        series: [
            { valueField: 'devis', name: 'Devis', color: COLORS.blueSoft },
            { valueField: 'factures', name: 'Factures', color: COLORS.blue }
        ],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        argumentAxis: { label: { overlappingBehavior: 'stagger' }, tick: { visible: false }, grid: { visible: false } },
        valueAxis: { tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: { enabled: true },
        onPointClick: e => applyDrilldown('commercial_month', e.target.argument, 'Période : ' + e.target.argument),
        onDone: function () { roundBarChartShapes(selector); }
    });
}

function renderCommercialTrend(selector, rows) {
    resetChartContainer(selector);
    const ds = rows || [];
    if (!ds.length) return emptyChart(selector);
    $(selector).dxChart({
        dataSource: ds,
        commonSeriesSettings: { argumentField: 'mois' },
        series: [
            { valueField: 'devis', name: 'Devis', type: 'bar', color: COLORS.blueSoft },
            { valueField: 'factures', name: 'Factures', type: 'bar', color: COLORS.green },
            { valueField: 'taux_transformation', name: 'Taux %', type: 'spline', axis: 'tauxAxis', color: COLORS.violet, point: { visible: true, size: 6 } }
        ],
        valueAxis: [
            { name: 'default', position: 'left', tick: { visible: false }, grid: { color: '#e3ebf7' } },
            { name: 'tauxAxis', position: 'right', max: 100, tick: { visible: false }, grid: { visible: false } }
        ],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        argumentAxis: { label: { overlappingBehavior: 'stagger' }, tick: { visible: false }, grid: { visible: false } },
        tooltip: { enabled: true },
        onPointClick: e => applyDrilldown('commercial_month', e.target.argument, 'Période : ' + e.target.argument),
        onDone: function () { roundBarChartShapes(selector); }
    });
}

function renderDevisState(selector, centerSelector, rows) {
    resetChartContainer(selector);
    const ds = (rows || []).map(r => ({ etat: r.etat || '-', total: toNumber(r.total) }));
    if (!ds.length) return emptyChart(selector);
    $(selector).dxPieChart({
        type: 'doughnut',
        innerRadius: 0.62,
        dataSource: ds,
        palette: [COLORS.orange, COLORS.green, COLORS.red, COLORS.blue, COLORS.gray],
        series: [{ argumentField: 'etat', valueField: 'total', label: { visible: true, connector: { visible: false }, customizeText: a => `${a.argumentText} ${a.valueText}` } }],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        tooltip: { enabled: true },
        onPointClick: e => applyDrilldown('devis_etat', e.target.argument, 'État devis : ' + e.target.argument)
    });
    $(centerSelector).html(`<strong>${ds.reduce((s, x) => s + x.total, 0)}</strong><span>devis</span>`);
}


function renderDemandesClientsGrid(rows) {
    resetGridContainer('#gridDemandesClient');
    $('#gridDemandesClient').dxDataGrid({
        dataSource: rows || [],
        keyExpr: 'id_demande_client',
        showBorders: true,
        columnAutoWidth: false,
        allowColumnResizing: true,
        columnResizingMode: 'widget',
        rowAlternationEnabled: true,
        hoverStateEnabled: true,
        scrolling: { mode: 'standard', showScrollbar: 'always', useNative: false },
        paging: { pageSize: 12 },
        pager: { showInfo: true, showNavigationButtons: true, showPageSizeSelector: true, allowedPageSizes: [12, 24, 50] },
        filterRow: { visible: true, applyFilter: 'auto' },
        headerFilter: { visible: true },
        searchPanel: { visible: true, width: 260, placeholder: 'Rechercher...' },
        onToolbarPreparing: e => addGridExportButtons(e, 'demandes_clients'),
        columns: [
            { dataField: 'ref_demande_client', caption: 'Réf demande client', width: 170 },
            { dataField: 'client_nom', caption: 'Client', width: 190 },
            { dataField: 'date_demande', caption: 'Date demande', dataType: 'date', width: 140 },
            { dataField: 'analyse_nom', caption: 'Analyse demandée', width: 230 },
            { dataField: 'faisabilite', caption: 'Faisabilité', width: 130, cellTemplate: (c, o) => renderYesNoCell(c, o.value) },
            { dataField: 'validite', caption: 'Validité', width: 120, cellTemplate: (c, o) => renderYesNoCell(c, o.value) },
            { dataField: 'delai_transformation_jours', caption: 'Délai transformation', width: 175, cellTemplate: (c, o) => renderDurationCell(c, o.value) },
            { dataField: 'delai_labo_jours', caption: 'Délai labo', width: 145, cellTemplate: (c, o) => renderDurationCell(c, o.value) },
            { dataField: 'reference_demande', caption: 'Demande analyse liée', width: 180 },
            { dataField: 'retard_predit', caption: 'Retard prédit', width: 135, cellTemplate: (c, o) => renderYesNoCell(c, o.value) },
            { dataField: 'priorite_ml', caption: 'Priorité ML', width: 125, alignment: 'center' }
        ]
    });
}


function renderAnalysesGrid(rows) {
    resetGridContainer('#gridAnalyses');
    $('#gridAnalyses').dxDataGrid({
        dataSource: rows || [],
        keyExpr: 'id_analyse',
        showBorders: true,
        columnAutoWidth: false,
        allowColumnResizing: true,
        columnResizingMode: 'widget',
        rowAlternationEnabled: true,
        hoverStateEnabled: true,
        scrolling: { mode: 'standard', showScrollbar: 'always', useNative: false },
        paging: { pageSize: 12 },
        pager: { showInfo: true, showNavigationButtons: true, showPageSizeSelector: true, allowedPageSizes: [12, 24, 50] },
        filterRow: { visible: true, applyFilter: 'auto' },
        headerFilter: { visible: true },
        searchPanel: { visible: true, width: 260, placeholder: 'Rechercher...' },
        onToolbarPreparing: e => addGridExportButtons(e, 'analyses_conformite'),
        columns: [
            { dataField: 'analyse_nom', caption: 'Analyse', width: 260 },
            { dataField: 'code_analyse', caption: 'Code analyse', width: 130 },
            { dataField: 'secteur_nom', caption: 'Secteur', width: 150 },
            { dataField: 'nombre_demandes', caption: 'Nombre demandes', width: 150 },
            { dataField: 'nombre_clients', caption: 'Nombre clients', width: 140 },
            { dataField: 'delai_moyen_jours', caption: 'Délai moyen', width: 145, cellTemplate: (c, o) => renderDurationCell(c, o.value) },
            { dataField: 'delai_max_jours', caption: 'Délai max', width: 135, cellTemplate: (c, o) => renderDurationCell(c, o.value) },
            { dataField: 'taux_validation', caption: 'Taux validation', width: 145, cellTemplate: (c, o) => renderPctCell(c, o.value) },
            { dataField: 'taux_conformite', caption: 'Taux conformité', width: 150, cellTemplate: (c, o) => renderPctCell(c, o.value) },
            { dataField: 'retards_predits', caption: 'Retards prédits', width: 145 },
            { dataField: 'score_sensibilite', caption: 'Score sensibilité', width: 155 }
        ]
    });
}


function renderDevisGrid(rows) {
    resetGridContainer('#gridDevis');
    $('#gridDevis').dxDataGrid({
        dataSource: rows || [],
        keyExpr: 'id_devis',
        showBorders: true,
        columnAutoWidth: false,
        allowColumnResizing: true,
        columnResizingMode: 'widget',
        rowAlternationEnabled: true,
        hoverStateEnabled: true,
        scrolling: { mode: 'standard', showScrollbar: 'always', useNative: false },
        paging: { pageSize: 12 },
        pager: { showInfo: true, showNavigationButtons: true, showPageSizeSelector: true, allowedPageSizes: [12, 24, 50] },
        filterRow: { visible: true, applyFilter: 'auto' },
        headerFilter: { visible: true },
        searchPanel: { visible: true, width: 260, placeholder: 'Rechercher...' },
        onToolbarPreparing: e => addGridExportButtons(e, 'devis'),
        columns: [
            { dataField: 'ref_devis', caption: 'Réf devis', width: 150 },
            { dataField: 'client_nom', caption: 'Client', width: 190 },
            { dataField: 'date_creation', caption: 'Date création', dataType: 'date', width: 140 },
            { dataField: 'etat_devis', caption: 'État devis', width: 150, cellTemplate: (c, o) => renderStateBadge(c, o.value) },
            { dataField: 'facture', caption: 'Facturé', width: 115, cellTemplate: (c, o) => renderYesNoCell(c, o.value) },
            { dataField: 'date_facture', caption: 'Date facture', dataType: 'date', width: 140 },
            { dataField: 'ref_facture_erp', caption: 'Réf facture ERP', width: 165 },
            { dataField: 'delai_devis_facture_jours', caption: 'Délai devis → facture', width: 180, cellTemplate: (c, o) => renderDurationCell(c, o.value) }
        ]
    });
}

function addGridExportButtons(e, fileName) {
    e.toolbarOptions.items.unshift({
        location: 'after',
        widget: 'dxButton',
        options: {
            icon: 'xlsxfile',
            text: 'Exporter',
            stylingMode: 'outlined',
            onClick: function () {
                const grid = e.component;
                const workbook = new ExcelJS.Workbook();
                const worksheet = workbook.addWorksheet('Export');
                DevExpress.excelExporter.exportDataGrid({ component: grid, worksheet, autoFilterEnabled: true }).then(function () {
                    return workbook.xlsx.writeBuffer();
                }).then(function (buffer) {
                    saveAs(new Blob([buffer], { type: 'application/octet-stream' }), (fileName || 'export') + '.xlsx');
                });
            }
        }
    });
}

function showLoadingState() {
    $('.kpi-grid').html('<div class="loading-box">Chargement...</div>');
    ['#funnelChart','#evolutionDelayChart','#faisabiliteValidationChart','#conformiteGlobalChart','#riskDistributionChart','#commercialSummaryChart','#topClientsChart','#delayByClientChart','#faisabiliteValidationClientChart','#clientTimelineChart','#clientsPriorityMlChart','#frequentAnalysesChart','#analyseSensitivityMlChart','#predictedLateAnalyseChart','#volumeDelayAnalyseChart','#devisFacturesChart','#transformationRateChart','#commercialTrendChart','#devisStateChart','#topClientsDevisChart','#delayDevisFactureChart'].forEach(function(sel) {
        resetChartContainer(sel);
        if ($(sel).length) $(sel).html('<div class="chart-empty">Chargement...</div>');
    });
    $('#predictiveStats').html('<div class="loading-box">Chargement...</div>');
    ['#gridDemandesClient','#gridAnalyses','#gridDevis'].forEach(function(sel) {
        resetGridContainer(sel);
        $(sel).html('<div class="loading-box">Chargement...</div>');
    });
}

function showError(message) {
    $('#kpiGrid').html('<div class="loading-box error-box">Erreur API : ' + escapeHtml(message) + '</div>');
}

function updateContextTexts() {
    const map = {
        global: ['Vue globale', 'KPIs, funnel, délais, conformité, commercial et synthèse ML.'],
        clients: ['Clients', 'Top clients, délais, faisabilité/validation et clients prioritaires ML.'],
        analyses: ['Analyses & conformité', 'Analyses fréquentes, délais, conformité, sensibilité ML et DataGrid analyses.'],
        commercial: ['Commercial', 'Devis, factures, transformation, états et délai devis → facture.']
    };
    const selected = map[currentView] || map.global;
    $('#contextTitle').text(selected[0]);
    $('#contextSubtitle').text(selected[1]);
}

function updateCurrentFilterBtn() {
    $('#showCurrentFilterBtn').text(currentDrilldown ? currentDrilldown.label : 'Aucun drill-down');
}

function renderActiveFilterBadges() {
    const $wrap = $('#activeFilterBadges');
    const dateStart = $('#dashboardDateDebut').dxDateBox('instance')?.option('value');
    const dateEnd = $('#dashboardDateFin').dxDateBox('instance')?.option('value');
    const year = $('#dashboardYearFilter').dxSelectBox('instance')?.option('value');
    const monthObj = $('#dashboardMonthFilter').dxSelectBox('instance')?.option('selectedItem');
    const badges = [];
    if (dateStart) badges.push({ key: 'date_start', color: 'blue', text: 'Début : ' + formatBadgeDate(dateStart) });
    if (dateEnd) badges.push({ key: 'date_end', color: 'green', text: 'Fin : ' + formatBadgeDate(dateEnd) });
    if (year) badges.push({ key: 'year', color: 'gray', text: 'Année : ' + year });
    if (monthObj) badges.push({ key: 'month', color: 'orange', text: 'Mois : ' + monthObj.name });
    $wrap.html(badges.map(b => `<div class="filter-badge ${b.color}"><span>${escapeHtml(b.text)}</span><button type="button" class="badge-reset" data-key="${b.key}">×</button></div>`).join(''));
    updateHeroPeriodLabel(dateStart, dateEnd, year, monthObj);
}

function updateHeroPeriodLabel(dateStart, dateEnd, year, monthObj) {
    let label = 'Toutes';
    if (dateStart || dateEnd) label = `${dateStart ? formatBadgeDate(dateStart) : '...'} → ${dateEnd ? formatBadgeDate(dateEnd) : '...'}`;
    else if (year && monthObj) label = `${monthObj.name} ${year}`;
    else if (year) label = String(year);
    else if (monthObj) label = monthObj.name;
    $('#heroPeriodLabel').text(label);
}

function revealActiveView() {
    const $els = $('#view-' + currentView + ' .reveal, .context-bar, .tabs, .filter-shell');
    $els.each(function (i, el) {
        $(el).removeClass('shown');
        setTimeout(function () { $(el).addClass('shown'); }, 35 * i);
    });
}

function repaintChartsInActiveView() {
    const selectors = ['#funnelChart','#evolutionDelayChart','#faisabiliteValidationChart','#conformiteGlobalChart','#riskDistributionChart','#commercialSummaryChart','#topClientsChart','#delayByClientChart','#faisabiliteValidationClientChart','#clientTimelineChart','#clientsPriorityMlChart','#frequentAnalysesChart','#analyseSensitivityMlChart','#predictedLateAnalyseChart','#volumeDelayAnalyseChart','#devisFacturesChart','#transformationRateChart','#commercialTrendChart','#devisStateChart','#topClientsDevisChart','#delayDevisFactureChart'];
    selectors.forEach(function (sel) {
        const el = $(sel).get(0);
        if (!el) return;
        try { const chart = DevExpress.viz.dxChart.getInstance(el); if (chart) chart.render(); } catch (e) {}
        try { const pie = DevExpress.viz.dxPieChart.getInstance(el); if (pie) pie.render(); } catch (e) {}
        try { const funnel = DevExpress.viz.dxFunnel.getInstance(el); if (funnel) funnel.render(); } catch (e) {}
    });
}

function repaintVisibleGrids() {
    ['#gridDemandesClient', '#gridAnalyses', '#gridDevis'].forEach(function (sel) {
        const el = $(sel).get(0);
        if (!el) return;
        try { const grid = DevExpress.ui.dxDataGrid.getInstance(el); if (grid) grid.updateDimensions(); } catch (e) {}
    });
}

function resetChartContainer(selector) {
    const $el = $(selector);
    if (!$el.length) return;
    const element = $el.get(0);
    try { const pie = DevExpress.viz.dxPieChart.getInstance(element); if (pie) pie.dispose(); } catch (e) {}
    try { const chart = DevExpress.viz.dxChart.getInstance(element); if (chart) chart.dispose(); } catch (e) {}
    try { const funnel = DevExpress.viz.dxFunnel.getInstance(element); if (funnel) funnel.dispose(); } catch (e) {}
    $el.empty();
}

function resetGridContainer(selector) {
    const $el = $(selector);
    if (!$el.length) return;
    const element = $el.get(0);
    try { const grid = DevExpress.ui.dxDataGrid.getInstance(element); if (grid) grid.dispose(); } catch (e) {}
    $el.empty();
}

function emptyChart(selector) {
    $(selector).html('<div class="chart-empty">Aucune donnée disponible</div>');
}

function roundBarChartShapes(selector) {
    const svg = $(selector).find('svg').get(0);
    if (!svg) return;
    svg.querySelectorAll('rect').forEach(function (rect) {
        const w = parseFloat(rect.getAttribute('width') || '0');
        const h = parseFloat(rect.getAttribute('height') || '0');
        if (w > 10 && h > 10) {
            rect.setAttribute('rx', '10');
            rect.setAttribute('ry', '10');
        }
    });
}

function normalizeMonthValueRows(rows) {
    return (rows || []).map(r => ({ ...r, mois: r.mois || r.periode || r.date || '-' }));
}

function formatDurationDays(value) {
    if (value === null || value === undefined || value === '' || isNaN(Number(value))) return '-';
    const totalMinutes = Math.round(Math.max(0, Number(value)) * 1440);
    const days = Math.floor(totalMinutes / 1440);
    const hours = Math.floor((totalMinutes % 1440) / 60);
    const minutes = totalMinutes % 60;
    const parts = [];
    if (days > 0) parts.push(days + 'j');
    if (hours > 0) parts.push(hours + 'h');
    if (minutes > 0 || !parts.length) parts.push(minutes + 'min');
    return parts.join(' ');
}

function renderDurationCell(container, value) {
    $('<span>').addClass('duration-pill').text(formatDurationDays(value)).appendTo(container);
}

function renderPctCell(container, value) {
    const n = toNumber(value);
    $('<span>').addClass('pct-pill ' + rateClass(n)).text(pctLabel(n)).appendTo(container);
}

function renderYesNoCell(container, value) {
    const yes = isOui(value);
    const no = isNon(value);
    const cls = yes ? 'badge-yes' : (no ? 'badge-no' : 'badge-empty');
    const label = yes ? 'Oui' : (no ? 'Non' : textOrDash(value));
    $('<span>').addClass(cls).text(label).appendTo(container);
}

function renderRiskBadge(container, value) {
    const label = textOrDash(value);
    const cls = normalizeRisk(label);
    $('<span>').addClass('risk-pill risk-' + cls).text(label).appendTo(container);
}

function renderStateBadge(container, value) {
    const raw = normalizeText(value);
    let cls = 'badge-info';
    if (raw.includes('fact') || raw.includes('accept')) cls = 'badge-yes';
    else if (raw.includes('refus') || raw.includes('annul')) cls = 'badge-no';
    else if (raw.includes('attente')) cls = 'badge-warning';
    $('<span>').addClass(cls).text(textOrDash(value)).appendTo(container);
}

function pctLabel(value) {
    const n = toNumber(value);
    return n.toFixed(1) + '%';
}

function rateClass(value) {
    const n = toNumber(value);
    if (n >= 80) return 'success';
    if (n >= 50) return 'warning';
    return 'danger';
}

function toNumber(value) {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
}

function textOrDash(value) {
    const s = String(value ?? '').trim();
    return s ? s : '-';
}

function normalizeText(value) {
    return String(value ?? '').trim().toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function normalizeRisk(value) {
    const raw = normalizeText(value);
    if (raw.includes('critique')) return 'critique';
    if (raw.includes('eleve')) return 'eleve';
    if (raw.includes('moyen')) return 'moyen';
    if (raw.includes('faible')) return 'faible';
    return 'none';
}

function isOui(value) {
    const raw = normalizeText(value);
    return ['oui', 'yes', 'true', '1', 'ok', 'valide', 'validé', 'faisable', 'conforme'].includes(raw) || raw.includes('oui');
}

function isNon(value) {
    const raw = normalizeText(value);
    return ['non', 'no', 'false', '0', 'ko', 'non valide', 'invalide', 'non faisable', 'non conforme'].includes(raw) || raw.includes('non');
}

function getPeriodeFromDate(value) {
    if (!value) return null;
    const d = new Date(value);
    if (isNaN(d.getTime())) return null;
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
}

function formatDateForApi(date) {
    if (!date) return null;
    const d = new Date(date);
    if (isNaN(d.getTime())) return null;
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function formatBadgeDate(date) {
    const d = new Date(date);
    if (isNaN(d.getTime())) return '-';
    return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}










/* ==========================================================
   PERFORMANCE DASHBOARD HOTFIX - FILTERS + CHART STYLE
   Keeps the existing structural logic. Only overrides rendering,
   filter parameter mapping, and client-side date filtering fallback.
========================================================== */

const CHART_ANIMATION = { enabled: true, duration: 900, easing: 'easeOutCubic' };

function buildApiParams() {
    const params = {};
    const dateStart = $('#dashboardDateDebut').dxDateBox('instance').option('value');
    const dateEnd = $('#dashboardDateFin').dxDateBox('instance').option('value');
    const year = $('#dashboardYearFilter').dxSelectBox('instance').option('value');
    const month = $('#dashboardMonthFilter').dxSelectBox('instance').option('value');

    if (dateStart) {
        params.date_start = formatDateForApi(dateStart);
        params.date_debut = formatDateForApi(dateStart);
    }
    if (dateEnd) {
        params.date_end = formatDateForApi(dateEnd);
        params.date_fin = formatDateForApi(dateEnd);
    }
    if (year) {
        params.year = year;
        params.annee = year;
    }
    if (month) {
        params.month = month;
        params.mois = month;
    }
    return params;
}

function hasActiveTimeFilters() {
    return !!(
        $('#dashboardDateDebut').dxDateBox('instance')?.option('value') ||
        $('#dashboardDateFin').dxDateBox('instance')?.option('value') ||
        $('#dashboardYearFilter').dxSelectBox('instance')?.option('value') ||
        $('#dashboardMonthFilter').dxSelectBox('instance')?.option('value')
    );
}

function parseDateSafe(value) {
    if (!value) return null;
    const d = new Date(value);
    return isNaN(d.getTime()) ? null : d;
}

function getRowDate(row, fields) {
    for (const field of fields) {
        const d = parseDateSafe(row?.[field]);
        if (d) return d;
    }
    return null;
}

function dateInsideCurrentFilters(date) {
    if (!date) return false;
    const start = $('#dashboardDateDebut').dxDateBox('instance')?.option('value');
    const end = $('#dashboardDateFin').dxDateBox('instance')?.option('value');
    const year = $('#dashboardYearFilter').dxSelectBox('instance')?.option('value');
    const month = $('#dashboardMonthFilter').dxSelectBox('instance')?.option('value');

    const d = new Date(date);
    d.setHours(0, 0, 0, 0);

    if (start) {
        const s = new Date(start);
        s.setHours(0, 0, 0, 0);
        if (d < s) return false;
    }
    if (end) {
        const e = new Date(end);
        e.setHours(23, 59, 59, 999);
        if (d > e) return false;
    }
    if (year && d.getFullYear() !== Number(year)) return false;
    if (month && d.getMonth() + 1 !== Number(month)) return false;
    return true;
}

function rowInsideCurrentFilters(row, fields) {
    if (!hasActiveTimeFilters()) return true;
    return dateInsideCurrentFilters(getRowDate(row, fields));
}

function filterMonthlyRows(rows) {
    if (!hasActiveTimeFilters()) return rows || [];
    const year = $('#dashboardYearFilter').dxSelectBox('instance')?.option('value');
    const month = $('#dashboardMonthFilter').dxSelectBox('instance')?.option('value');
    return (rows || []).filter(r => {
        const raw = String(r.mois || r.periode || r.date || '').trim();
        if (!raw) return true;
        const m = raw.match(/(\d{4})[-/](\d{1,2})/);
        if (m) {
            if (year && Number(m[1]) !== Number(year)) return false;
            if (month && Number(m[2]) !== Number(month)) return false;
            return true;
        }
        return true;
    });
}

function applyTimeFiltersToDashboard(data) {
    const cloned = cloneData(data);
    if (!hasActiveTimeFilters()) return cloned;

    cloned.demandes_clients_grid = (cloned.demandes_clients_grid || [])
        .filter(r => rowInsideCurrentFilters(r, ['date_demande', 'date_reception', 'date_creation']));
    cloned.devis_grid = (cloned.devis_grid || [])
        .filter(r => rowInsideCurrentFilters(r, ['date_creation', 'date_facture']));

    const charts = cloned.charts || {};
    Object.keys(charts).forEach(key => {
        if (Array.isArray(charts[key])) charts[key] = filterMonthlyRows(charts[key]);
    });

    rebuildFilteredMetrics(cloned);
    return cloned;
}

function avgNumber(rows, field) {
    const values = (rows || []).map(r => toNumber(r[field])).filter(v => v > 0);
    if (!values.length) return 0;
    return values.reduce((a, b) => a + b, 0) / values.length;
}

function monthKeyFromRow(row, fields) {
    const d = getRowDate(row, fields);
    if (!d) return '-';
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
}

function pushAgg(map, key, defaults) {
    if (!map[key]) map[key] = Object.assign({}, defaults);
    return map[key];
}

function rebuildFilteredMetrics(data) {
    const rows = data.demandes_clients_grid || [];
    const devis = data.devis_grid || [];
    const charts = data.charts || (data.charts = {});
    const kGlobal = data.kpis?.global || data.vue_globale?.kpis || {};
    const kClients = data.kpis?.clients || data.clients?.kpis || {};
    const kAnalyses = data.kpis?.analyses_conformite || data.analyses_conformite?.kpis || {};
    const kCommercial = data.kpis?.commercial || data.commercial?.kpis || {};

    const knownRows = rows.filter(r => !isUnknownLabel(r.client_nom));
    const totalDemandes = knownRows.length;
    const faisables = knownRows.filter(r => isOui(r.faisabilite)).length;
    const valides = knownRows.filter(r => isOui(r.validite)).length;
    const retards = knownRows.filter(r => isOui(r.retard_predit) || toNumber(r.retard_predit) === 1 || toNumber(r.jours_retard_predit) > 0).length;

    if (totalDemandes > 0) {
        kGlobal.total_demandes_clients = totalDemandes;
        kGlobal.taux_faisabilite = faisables * 100 / totalDemandes;
        kGlobal.taux_validation = valides * 100 / totalDemandes;
        kGlobal.delai_moyen_global_jours = avgNumber(knownRows, 'delai_labo_jours');
        kGlobal.retards_predits = retards;
    }

    const clientMap = {};
    knownRows.forEach(r => {
        const c = r.client_nom || '-';
        const row = pushAgg(clientMap, c, {
            client_nom: c, total_demandes: 0, delais: [], faisables: 0, valides: 0,
            non_faisables: 0, non_valides: 0, score_priorite_ml: 0, retards_predits: 0
        });
        row.total_demandes += 1;
        if (toNumber(r.delai_labo_jours) > 0) row.delais.push(toNumber(r.delai_labo_jours));
        if (isOui(r.faisabilite)) row.faisables += 1; else if (isNon(r.faisabilite)) row.non_faisables += 1;
        if (isOui(r.validite)) row.valides += 1; else if (isNon(r.validite)) row.non_valides += 1;
        const priority = toNumber(r.priorite_ml || r.score_priorite_ml || 0);
        const hasRetard = isOui(r.retard_predit) || toNumber(r.retard_predit) === 1 || toNumber(r.jours_retard_predit) > 0;
        row.retards_predits += hasRetard ? 1 : 0;
        row.score_priorite_ml += priority || (hasRetard ? 1 : 0);
    });
    const clientRows = Object.values(clientMap).map(r => ({
        ...r,
        delai_moyen_jours: r.delais.length ? r.delais.reduce((a, b) => a + b, 0) / r.delais.length : 0
    }));
    charts.top_clients_volume = clientRows.slice().sort((a, b) => b.total_demandes - a.total_demandes).slice(0, 12);
    charts.delai_moyen_par_client = clientRows.slice().sort((a, b) => b.delai_moyen_jours - a.delai_moyen_jours).slice(0, 12);
    charts.faisabilite_validation_par_client = clientRows.slice().sort((a, b) => b.total_demandes - a.total_demandes).slice(0, 10);
    charts.clients_prioritaires_ml = clientRows.slice().sort((a, b) => b.score_priorite_ml - a.score_priorite_ml).slice(0, 10);

    const clientTimeline = {};
    knownRows.forEach(r => {
        const key = monthKeyFromRow(r, ['date_demande', 'date_reception', 'date_creation']) + '||' + r.client_nom;
        const row = pushAgg(clientTimeline, key, { mois: monthKeyFromRow(r, ['date_demande', 'date_reception', 'date_creation']), client_nom: r.client_nom, total_demandes: 0 });
        row.total_demandes += 1;
    });
    charts.volume_demandes_client_temps = Object.values(clientTimeline);

    kClients.clients_actifs = clientRows.length;
    kClients.client_le_plus_actif = clientRows.slice().sort((a, b) => b.total_demandes - a.total_demandes)[0]?.client_nom || '-';
    kClients.delai_moyen_client_jours = avgNumber(clientRows, 'delai_moyen_jours');
    kClients.client_le_plus_lent = clientRows.slice().sort((a, b) => b.delai_moyen_jours - a.delai_moyen_jours)[0]?.client_nom || '-';
    kClients.clients_prioritaires_ml = clientRows.filter(r => toNumber(r.score_priorite_ml) > 0).length;
    kClients.retards_predits_par_client = clientRows.reduce((s, r) => s + toNumber(r.retards_predits), 0);

    const analyseMap = {};
    knownRows.filter(r => !isUnknownLabel(r.analyse_nom)).forEach(r => {
        const a = r.analyse_nom;
        const row = pushAgg(analyseMap, a, {
            analyse_nom: a, total: 0, volume: 0, delais: [], retards_predits: 0, score_sensibilite: 0
        });
        row.total += 1;
        row.volume += 1;
        if (toNumber(r.delai_labo_jours) > 0) row.delais.push(toNumber(r.delai_labo_jours));
        const hasRetard = isOui(r.retard_predit) || toNumber(r.retard_predit) === 1 || toNumber(r.jours_retard_predit) > 0;
        row.retards_predits += hasRetard ? 1 : 0;
        row.score_sensibilite += toNumber(r.score_sensibilite || r.priorite_ml || 0) || (hasRetard ? 1 : 0);
    });
    const analyseRows = Object.values(analyseMap).map(r => ({
        ...r,
        delai_moyen_jours: r.delais.length ? r.delais.reduce((a, b) => a + b, 0) / r.delais.length : 0
    }));
    charts.analyses_frequentes = analyseRows.slice().sort((a, b) => b.total - a.total).slice(0, 12);
    charts.delai_moyen_par_analyse = analyseRows.slice().sort((a, b) => b.delai_moyen_jours - a.delai_moyen_jours).slice(0, 12);
    charts.analyses_sensibles_ml = analyseRows.slice().sort((a, b) => b.score_sensibilite - a.score_sensibilite).slice(0, 12);
    charts.retards_predits_par_analyse = analyseRows.slice().sort((a, b) => b.retards_predits - a.retards_predits).slice(0, 12);
    charts.volume_delai_par_analyse = analyseRows.slice().sort((a, b) => b.volume - a.volume).slice(0, 12);

    if (analyseRows.length > 0) {
        kAnalyses.analyses_distinctes = analyseRows.length;
        kAnalyses.analyse_la_plus_frequente = analyseRows.slice().sort((a, b) => b.total - a.total)[0]?.analyse_nom || '-';
        kAnalyses.analyse_la_plus_lente = analyseRows.slice().sort((a, b) => b.delai_moyen_jours - a.delai_moyen_jours)[0]?.analyse_nom || '-';
        kAnalyses.delai_moyen_analyse_jours = avgNumber(analyseRows, 'delai_moyen_jours');
        kAnalyses.analyse_la_plus_sensible_ml = analyseRows.slice().sort((a, b) => b.score_sensibilite - a.score_sensibilite)[0]?.analyse_nom || '-';
        kAnalyses.retards_predits_par_analyse = analyseRows.reduce((s, r) => s + toNumber(r.retards_predits), 0);
    }

    const devisByMonth = {};
    devis.forEach(d => {
        const m = monthKeyFromRow(d, ['date_creation', 'date_facture']);
        const row = pushAgg(devisByMonth, m, { mois: m, devis: 0, factures: 0, taux_transformation: 0, delai_moyen_devis_facture_jours: 0, delais: [] });
        row.devis += 1;
        if (isOui(d.facture) || d.date_facture) row.factures += 1;
        if (toNumber(d.delai_devis_facture_jours) > 0) row.delais.push(toNumber(d.delai_devis_facture_jours));
    });
    const commercialRows = Object.values(devisByMonth).map(r => ({
        ...r,
        taux_transformation: r.devis ? r.factures * 100 / r.devis : 0,
        delai_moyen_devis_facture_jours: r.delais.length ? r.delais.reduce((a, b) => a + b, 0) / r.delais.length : 0
    })).sort((a, b) => String(a.mois).localeCompare(String(b.mois)));
    charts.devis_vs_factures = commercialRows;
    charts.evolution_commerciale = commercialRows;
    charts.taux_transformation_mensuel = commercialRows;
    charts.delai_devis_facture = commercialRows;

    kCommercial.nombre_devis = devis.length;
    kCommercial.nombre_factures = devis.filter(d => isOui(d.facture) || d.date_facture).length;
    kCommercial.taux_transformation = kCommercial.nombre_devis ? kCommercial.nombre_factures * 100 / kCommercial.nombre_devis : 0;
    kCommercial.delai_moyen_devis_facture_jours = avgNumber(devis, 'delai_devis_facture_jours');
}

function loadDashboard() {
    showLoadingState();
    $.ajax({
        url: API_URL,
        method: 'GET',
        dataType: 'json',
        cache: false,
        data: buildApiParams(),
        success: function (data) {
            if (!data || data.status !== 'success') {
                showError(data && data.message ? data.message : 'Réponse API invalide');
                return;
            }
            dashboardData = cloneData(data);
            hydrateYearFilter(data);
            renderActiveFilterBadges();
            updateContextTexts();
            updateCurrentFilterBtn();

            const timeFiltered = applyTimeFiltersToDashboard(dashboardData);
            const rendered = applyCurrentDrilldown(timeFiltered);
            renderAll(rendered);
            revealActiveView();
            setTimeout(function () {
                repaintChartsInActiveView();
                repaintVisibleGrids();
            }, 120);
        },
        error: function (xhr) {
            console.error(xhr.responseText || xhr.statusText || xhr);
            showError('Erreur chargement API');
        }
    });
}

function applyDrilldown(type, value, label) {
    currentDrilldown = { type, value, label };
    updateCurrentFilterBtn();
    const rendered = applyCurrentDrilldown(applyTimeFiltersToDashboard(dashboardData));
    renderAll(rendered);
    revealActiveView();
    setTimeout(function () {
        repaintChartsInActiveView();
        repaintVisibleGrids();
    }, 90);
}

function bindContextActions() {
    $('#clearDrilldownBtn').off('click').on('click', function () {
        currentDrilldown = null;
        updateCurrentFilterBtn();
        if (!dashboardData) return;
        renderAll(applyTimeFiltersToDashboard(dashboardData));
        revealActiveView();
        setTimeout(function () {
            repaintChartsInActiveView();
            repaintVisibleGrids();
        }, 100);
    });

    $('#showCurrentFilterBtn').off('click').on('click', function () {
        if (!currentDrilldown) {
            DevExpress.ui.notify('Aucun drill-down', 'info', 1500);
            return;
        }
        DevExpress.ui.notify(currentDrilldown.label, 'info', 1800);
    });

    $('#activeFilterBadges').off('click', '.badge-reset').on('click', '.badge-reset', function () {
        const key = $(this).data('key');
        clearOneFilter(key);
        currentDrilldown = null;
        updateCurrentFilterBtn();
        loadDashboard();
    });
}

function tauxNonConformiteFromKpi(k) {
    const conformite = toNumber(k.taux_conformite);
    if (conformite > 0) return Math.max(0, 100 - conformite);
    return 0;
}

function renderGlobalSection(data) {
    const k = data?.kpis?.global || data?.vue_globale?.kpis || {};
    const charts = data?.charts || data?.vue_globale?.charts || {};
    const tauxNonConf = tauxNonConformiteFromKpi(k);

    $('#kpiGrid').html([
        kpiCard('Total demandes clients', toNumber(k.total_demandes_clients), 'Volume global reçu', 'primary'),
        kpiCard('Taux faisabilité', pctLabel(k.taux_faisabilite), 'Demandes réalisables', rateClass(k.taux_faisabilite)),
        kpiCard('Taux validation', pctLabel(k.taux_validation), 'Demandes validées', rateClass(k.taux_validation)),
        kpiCard('Délai moyen global', formatDurationDays(k.delai_moyen_global_jours), 'Moyenne de traitement', 'warning'),
        kpiCard('Taux conformité', pctLabel(k.taux_conformite), 'Échantillons conformes', rateClass(k.taux_conformite)),
        kpiCard('Taux non-conformité', pctLabel(tauxNonConf), `${toNumber(k.non_conformites)} cas non conformes`, tauxNonConf > 20 ? 'danger' : 'warning'),
        kpiCard('Taux devis → facture', pctLabel(k.taux_devis_facture), 'Transformation commerciale', 'violet'),
        kpiCard('Rapports finaux', toNumber(k.rapports_finaux), 'Rapports finalisés', 'success'),
        kpiCard('Demandes critiques ML', toNumber(k.demandes_critiques_ml), 'Priorités prédictives', 'danger'),
        kpiCard('Retards prédits', toNumber(k.retards_predits), 'Synthèse ML', 'violet')
    ].join(''));

    $('#heroPredictedDelays').text(toNumber(k.retards_predits));

    renderFunnel('#funnelChart', charts.funnel_performance || []);
    renderPredictionStats(data);
    renderLineChart('#evolutionDelayChart', normalizeMonthValueRows(charts.evolution_delai_moyen), 'mois', 'delai_moyen_jours', 'Délai moyen', COLORS.violet, true);
    renderGroupedYesNoChart('#faisabiliteValidationChart', charts.faisabilite_validation_globale || []);
    renderConformiteGlobal('#conformiteGlobalChart', '#conformiteGlobalCenter', charts.conformite_globale || []);
    renderPredictedDelaySummary('#riskDistributionChart', data);
    renderCommercialSummary('#commercialSummaryChart', charts.commercial_summary || charts.evolution_commerciale || charts.devis_vs_factures || []);
}

function renderClientsSection(data) {
    const k = data?.kpis?.clients || data?.clients?.kpis || {};
    const charts = data?.charts || data?.clients?.charts || {};
    const rows = data.demandes_clients_grid || [];

    const topClients = filterKnownRows(charts.top_clients_volume || [], 'client_nom');
    const delayClients = filterKnownRows(charts.delai_moyen_par_client || [], 'client_nom');
    const fvClients = filterKnownRows(charts.faisabilite_validation_par_client || [], 'client_nom');
    const priorityClients = filterKnownRows(charts.clients_prioritaires_ml || [], 'client_nom').filter(r => toNumber(r.score_priorite_ml || r.total || r.value) > 0);
    const timeline = (charts.volume_demandes_client_temps || []).filter(r => !isUnknownLabel(r.client_nom));

    const clientLePlusActif = isUnknownLabel(k.client_le_plus_actif) ? firstKnownValue(topClients, 'client_nom', 'total_demandes') : k.client_le_plus_actif;
    const clientLePlusLent = isUnknownLabel(k.client_le_plus_lent) ? firstKnownValue(delayClients, 'client_nom', 'delai_moyen_jours') : k.client_le_plus_lent;

    $('#clientsKpiGrid').html([
        kpiCard('Clients actifs', toNumber(k.clients_actifs), 'Clients avec demandes', 'primary'),
        kpiCard('Client le plus actif', textOrDash(clientLePlusActif), 'Volume le plus élevé', 'violet'),
        kpiCard('Délai moyen client', formatDurationDays(k.delai_moyen_client_jours), 'Moyenne clients', 'warning'),
        kpiCard('Meilleur taux validation', textOrDash(k.meilleur_taux_validation_client), 'Client le plus stable', 'success'),
        kpiCard('Client le plus lent', textOrDash(clientLePlusLent), 'Délai moyen élevé', 'danger'),
        kpiCard('Clients prioritaires ML', toNumber(k.clients_prioritaires_ml), 'Demandes à suivre en priorité', 'violet'),
        kpiCard('Retards prédits par client', toNumber(k.retards_predits_par_client), 'Alertes ML client', 'danger')
    ].join(''));

    renderHorizontalBarRising('#topClientsChart', topClients, 'client_nom', 'total_demandes', 'Demandes', COLORS.blueSoft, 'client');
    renderLollipopChart('#delayByClientChart', delayClients, 'client_nom', 'delai_moyen_jours', 'Délai moyen', COLORS.orange, 'client', true);
    renderClientFeasValidation('#faisabiliteValidationClientChart', fvClients);
    renderClientTimeline('#clientTimelineChart', timeline);
    renderHorizontalBarRising('#clientsPriorityMlChart', priorityClients, 'client_nom', 'score_priorite_ml', 'Score priorité ML', COLORS.violet, 'client');
    renderDemandesClientsGrid(rows.filter(r => !isUnknownLabel(r.client_nom)));
}

function renderAnalysesSection(data) {
    const k = data?.kpis?.analyses_conformite || data?.analyses_conformite?.kpis || {};
    const kGlobal = data?.kpis?.global || data?.vue_globale?.kpis || {};
    const charts = data?.charts || data?.analyses_conformite?.charts || {};

    const frequentAnalyses = filterKnownRows(charts.analyses_frequentes || [], 'analyse_nom');
    const delayAnalyses = filterKnownRows(charts.delai_moyen_par_analyse || [], 'analyse_nom');
    const sensitiveAnalyses = filterKnownRows(charts.analyses_sensibles_ml || [], 'analyse_nom').filter(r => toNumber(r.score_sensibilite || r.total || r.value) > 0);
    const lateAnalyses = filterKnownRows(charts.retards_predits_par_analyse || [], 'analyse_nom').filter(r => toNumber(r.retards_predits || r.total || r.value) > 0);
    const volumeDelay = filterKnownRows(charts.volume_delai_par_analyse || [], 'analyse_nom');

    const analyseFrequente = isUnknownLabel(k.analyse_la_plus_frequente) ? firstKnownValue(frequentAnalyses, 'analyse_nom', 'total') : k.analyse_la_plus_frequente;
    const analyseLente = isUnknownLabel(k.analyse_la_plus_lente) ? firstKnownValue(delayAnalyses, 'analyse_nom', 'delai_moyen_jours') : k.analyse_la_plus_lente;
    const analyseSensible = isUnknownLabel(k.analyse_la_plus_sensible_ml) ? firstKnownValue(sensitiveAnalyses, 'analyse_nom', 'score_sensibilite') : k.analyse_la_plus_sensible_ml;
    const tauxConformite = toNumber(k.taux_conformite || kGlobal.taux_conformite);
    const tauxNonConf = Math.max(0, 100 - tauxConformite);

    $('#analysesKpiGrid').html([
        kpiCard('Analyses distinctes', toNumber(k.analyses_distinctes), 'Catalogue utilisé', 'primary'),
        kpiCard('Analyse la plus fréquente', textOrDash(analyseFrequente), 'Plus grand volume', 'violet'),
        kpiCard('Analyse la plus lente', textOrDash(analyseLente), 'Délai moyen élevé', 'danger'),
        kpiCard('Délai moyen analyse', formatDurationDays(k.delai_moyen_analyse_jours), 'Performance technique', 'warning'),
        kpiCard('Taux non-conformité', pctLabel(tauxNonConf), `Conformité : ${pctLabel(tauxConformite)}`, tauxNonConf > 20 ? 'danger' : 'warning'),
        kpiCard('Analyse sensible ML', textOrDash(analyseSensible), 'Score de sensibilité ML', 'violet'),
        kpiCard('Retards prédits analyse', toNumber(k.retards_predits_par_analyse), 'Retards associés aux analyses', 'danger')
    ].join(''));

    renderHorizontalBarRising('#frequentAnalysesChart', frequentAnalyses, 'analyse_nom', 'total', 'Demandes', COLORS.blue, 'analyse');
    renderLollipopChart('#analyseSensitivityMlChart', sensitiveAnalyses, 'analyse_nom', 'score_sensibilite', 'Score sensibilité', COLORS.violet, 'analyse', false);
    renderDonutCategory('#predictedLateAnalyseChart', lateAnalyses, 'analyse_nom', 'retards_predits', 'Retards prédits', COLORS.red, 'analyse');
    renderVolumeDelayAnalyse('#volumeDelayAnalyseChart', volumeDelay);
    renderAnalysesGrid((data.analyses_grid || []).filter(r => !isUnknownLabel(r.analyse_nom)));
}

function renderHorizontalBarRising(selector, rows, argField, valueField, name, color, drillType, isDelay, suffix) {
    resetChartContainer(selector);
    const ds = (rows || [])
        .map(r => ({ ...r, [valueField]: toNumber(r[valueField] ?? r.total ?? r.value) }))
        .filter(r => r[argField] !== undefined && r[argField] !== null && !isUnknownLabel(r[argField]))
        .sort((a, b) => toNumber(b[valueField]) - toNumber(a[valueField]))
        .slice(0, 10)
        .reverse();
    if (!ds.length) return emptyChart(selector);
    $(selector).addClass('chart-rise');
    $(selector).dxChart({
        dataSource: ds,
        rotated: true,
        animation: CHART_ANIMATION,
        commonSeriesSettings: { argumentField: argField, type: 'bar', minBarSize: 6, barPadding: 0.35 },
        series: [{ valueField, name, color }],
        legend: { visible: false },
        argumentAxis: { tick: { visible: false }, grid: { visible: false }, label: { overlappingBehavior: 'stagger' } },
        valueAxis: { tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: { enabled: true, customizeTooltip: arg => ({ text: `${arg.argumentText}<br>${name}: ${isDelay ? formatDurationDays(arg.value) : arg.valueText + (suffix || '')}` }) },
        onPointClick: function (e) {
            if (drillType) applyDrilldown(drillType, e.target.argument, `${name} : ${e.target.argument}`);
        },
        onDone: function () { roundBarChartShapes(selector); }
    });
}

function renderLollipopChart(selector, rows, argField, valueField, name, color, drillType, isDelay) {
    resetChartContainer(selector);
    const ds = (rows || [])
        .map(r => ({ ...r, [valueField]: toNumber(r[valueField] ?? r.total ?? r.value) }))
        .filter(r => r[argField] !== undefined && r[argField] !== null && !isUnknownLabel(r[argField]))
        .sort((a, b) => toNumber(b[valueField]) - toNumber(a[valueField]))
        .slice(0, 10)
        .reverse();
    if (!ds.length) return emptyChart(selector);
    $(selector).addClass('chart-rise');
    $(selector).dxChart({
        dataSource: ds,
        rotated: true,
        animation: CHART_ANIMATION,
        commonSeriesSettings: { argumentField: argField },
        series: [
            { valueField, name, type: 'line', color, width: 3, point: { visible: true, size: 10, color } }
        ],
        legend: { visible: false },
        argumentAxis: { tick: { visible: false }, grid: { visible: false }, label: { overlappingBehavior: 'stagger' } },
        valueAxis: { tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: { enabled: true, customizeTooltip: arg => ({ text: `${arg.argumentText}<br>${name}: ${isDelay ? formatDurationDays(arg.value) : arg.valueText}` }) },
        onPointClick: function (e) {
            if (drillType) applyDrilldown(drillType, e.target.argument, `${name} : ${e.target.argument}`);
        }
    });
}

function renderClientFeasValidation(selector, rows) {
    resetChartContainer(selector);
    const ds = (rows || []).slice(0, 10).map(r => ({
        client_nom: r.client_nom || '-',
        faisables: toNumber(r.faisables),
        valides: toNumber(r.valides),
        non_faisables: toNumber(r.non_faisables),
        non_valides: toNumber(r.non_valides)
    })).filter(r => !isUnknownLabel(r.client_nom));
    if (!ds.length) return emptyChart(selector);
    $(selector).addClass('chart-rise');
    $(selector).dxChart({
        dataSource: ds,
        animation: CHART_ANIMATION,
        commonSeriesSettings: { argumentField: 'client_nom', type: 'fullStackedBar', barPadding: 0.28 },
        series: [
            { valueField: 'faisables', name: 'Faisables', color: COLORS.green },
            { valueField: 'valides', name: 'Validées', color: COLORS.blue },
            { valueField: 'non_faisables', name: 'Non faisables', color: COLORS.red },
            { valueField: 'non_valides', name: 'Non validées', color: COLORS.orange }
        ],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        argumentAxis: { label: { overlappingBehavior: 'rotate', rotationAngle: 20 }, tick: { visible: false }, grid: { visible: false } },
        valueAxis: { label: { customizeText: arg => Math.round(arg.value * 100) + '%' }, tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: { enabled: true },
        onPointClick: e => applyDrilldown('client', e.target.argument, 'Client : ' + e.target.argument),
        onDone: function () { roundBarChartShapes(selector); }
    });
}

function renderClientTimeline(selector, rows) {
    resetChartContainer(selector);
    const clean = filterKnownRows(rows || [], 'client_nom');
    if (!clean.length) return emptyChart(selector);
    const clients = [...new Set(clean.map(r => r.client_nom).filter(Boolean))].slice(0, 5);
    const byMonth = {};
    clean.forEach(r => {
        const mois = r.mois || r.periode || '-';
        if (!byMonth[mois]) byMonth[mois] = { mois };
        if (clients.includes(r.client_nom)) byMonth[mois][r.client_nom] = toNumber(r.total_demandes || r.total || r.value);
    });
    const ds = Object.values(byMonth).sort((a, b) => String(a.mois).localeCompare(String(b.mois)));
    $(selector).addClass('chart-rise');
    $(selector).dxChart({
        dataSource: ds,
        animation: CHART_ANIMATION,
        commonSeriesSettings: { argumentField: 'mois', type: 'stackedarea', point: { visible: true, size: 4 }, opacity: 0.72 },
        series: clients.map((client, i) => ({ valueField: client, name: client, color: [COLORS.blue, COLORS.green, COLORS.orange, COLORS.violet, COLORS.cyan][i % 5] })),
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        argumentAxis: { label: { overlappingBehavior: 'stagger' }, tick: { visible: false }, grid: { visible: false } },
        valueAxis: { tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: { enabled: true }
    });
}

function renderDonutCategory(selector, rows, argField, valueField, name, color, drillType) {
    resetChartContainer(selector);
    const ds = (rows || [])
        .map(r => ({ label: r[argField], total: toNumber(r[valueField] ?? r.total ?? r.value), original: r }))
        .filter(r => !isUnknownLabel(r.label) && r.total > 0)
        .sort((a, b) => b.total - a.total)
        .slice(0, 8);
    if (!ds.length) return emptyChart(selector);
    $(selector).addClass('chart-rise');
    $(selector).dxPieChart({
        type: 'doughnut',
        innerRadius: 0.58,
        dataSource: ds,
        animation: CHART_ANIMATION,
        palette: [COLORS.red, COLORS.orange, COLORS.violet, COLORS.blue, COLORS.cyan, COLORS.green, COLORS.gray],
        series: [{
            argumentField: 'label',
            valueField: 'total',
            label: { visible: true, connector: { visible: false }, customizeText: a => `${shortLabel(a.argumentText, 18)} ${a.valueText}` }
        }],
        legend: { visible: false },
        tooltip: { enabled: true, customizeTooltip: a => ({ text: `${a.argumentText}<br>${name}: ${a.valueText}` }) },
        onPointClick: e => {
            if (drillType) applyDrilldown(drillType, e.target.argument, `${name} : ${e.target.argument}`);
        }
    });
}

function renderVolumeDelayAnalyse(selector, rows) {
    resetChartContainer(selector);
    const ds = filterKnownRows(rows || [], 'analyse_nom')
        .slice()
        .sort((a, b) => toNumber(b.volume || b.total) - toNumber(a.volume || a.total))
        .slice(0, 8)
        .map(r => ({
            analyse_nom: r.analyse_nom,
            analyse_label: shortLabel(r.analyse_nom, 20),
            volume: toNumber(r.volume || r.total),
            delai_moyen_jours: toNumber(r.delai_moyen_jours)
        }));
    if (!ds.length) return emptyChart(selector);
    $(selector).addClass('chart-rise');
    $(selector).dxChart({
        dataSource: ds,
        animation: CHART_ANIMATION,
        commonSeriesSettings: { argumentField: 'analyse_label' },
        series: [
            { type: 'bar', valueField: 'volume', name: 'Volume', axis: 'volumeAxis', color: COLORS.blueSoft, barPadding: 0.35 },
            { type: 'splinearea', valueField: 'delai_moyen_jours', name: 'Délai moyen', axis: 'delayAxis', color: COLORS.orange, point: { visible: true, size: 7 }, width: 3, opacity: 0.28 }
        ],
        valueAxis: [
            { name: 'volumeAxis', position: 'left', title: 'Volume', tick: { visible: false }, grid: { color: '#e3ebf7' } },
            { name: 'delayAxis', position: 'right', title: 'Délai', tick: { visible: false }, grid: { visible: false }, label: { customizeText: arg => formatDurationDays(arg.value) } }
        ],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        argumentAxis: { label: { overlappingBehavior: 'rotate', rotationAngle: 18 }, tick: { visible: false }, grid: { visible: false } },
        margin: { left: 18, right: 42, top: 14, bottom: 24 },
        tooltip: {
            enabled: true,
            customizeTooltip: function (arg) {
                const full = arg.point.data.analyse_nom;
                if (arg.seriesName === 'Délai moyen') return { text: `${full}<br>Délai : ${formatDurationDays(arg.value)}` };
                return { text: `${full}<br>Volume : ${arg.valueText}` };
            }
        },
        onPointClick: e => applyDrilldown('analyse', e.target.data.analyse_nom, 'Analyse : ' + e.target.data.analyse_nom),
        onDone: function () { roundBarChartShapes(selector); }
    });
}

function renderLineChart(selector, rows, argField, valueField, name, color, isDelay, suffix) {
    resetChartContainer(selector);
    const dataSource = (rows || []).map(r => ({ ...r, [valueField]: toNumber(r[valueField]) })).filter(r => r[argField] !== undefined && r[argField] !== null);
    if (!dataSource.length) return emptyChart(selector);
    $(selector).addClass('chart-rise');
    $(selector).dxChart({
        dataSource,
        animation: CHART_ANIMATION,
        commonSeriesSettings: { argumentField: argField, type: 'splinearea', point: { visible: true, size: 7 }, width: 3, opacity: 0.25 },
        series: [{ valueField, name, color }],
        legend: { visible: false },
        argumentAxis: { label: { overlappingBehavior: 'stagger' }, tick: { visible: false }, grid: { visible: false } },
        valueAxis: { tick: { visible: false }, grid: { color: '#e3ebf7' } },
        tooltip: { enabled: true, customizeTooltip: arg => ({ text: `${arg.argumentText}<br>${name}: ${isDelay ? formatDurationDays(arg.value) : `${arg.valueText}${suffix || ''}`}` }) },
        margin: { top: 12, right: 16, bottom: 8, left: 10 }
    });
}

function renderFunnel(selector, rows) {
    resetChartContainer(selector);
    const dataSource = (rows || []).map(r => ({ stage: r.etape || r.stage || '-', total: toNumber(r.total || r.value) }));
    if (!dataSource.length) return emptyChart(selector);
    $(selector).addClass('chart-rise');
    $(selector).dxFunnel({
        dataSource,
        argumentField: 'stage',
        valueField: 'total',
        sortData: false,
        inverted: true,
        palette: ['#2563eb', '#3b82f6', '#10b981', '#8b5cf6', '#06b6d4', '#17325c'],
        animation: CHART_ANIMATION,
        label: { visible: true, position: 'inside', backgroundColor: 'none', customizeText: arg => `${arg.item.argument} : ${arg.value}` },
        tooltip: { enabled: true }
    });
}

function renderConformiteGlobal(selector, centerSelector, rows) {
    resetChartContainer(selector);
    const ds = (rows || []).map(r => ({ label: r.label || r.conformite || '-', total: toNumber(r.total || r.value) })).filter(r => r.total > 0);
    if (!ds.length) return emptyChart(selector);
    $(selector).addClass('chart-rise');
    $(selector).dxPieChart({
        type: 'doughnut',
        innerRadius: 0.62,
        dataSource: ds,
        palette: [COLORS.green, COLORS.red, COLORS.gray],
        animation: CHART_ANIMATION,
        series: [{ argumentField: 'label', valueField: 'total', label: { visible: true, connector: { visible: false }, customizeText: a => `${a.argumentText} ${a.valueText}` } }],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        tooltip: { enabled: true },
        onPointClick: e => applyDrilldown('conformite', e.target.argument, 'Conformité : ' + e.target.argument)
    });
    const total = ds.reduce((s, x) => s + x.total, 0);
    $(centerSelector).html(`<strong>${total}</strong><span>échantillons</span>`);
}

function renderPredictedDelaySummary(selector, data) {
    resetChartContainer(selector);
    const rows = buildRetardPredictedRows(data);
    if (!rows.length) return emptyChart(selector);
    $(selector).addClass('chart-rise');
    $(selector).dxPieChart({
        type: 'doughnut',
        innerRadius: 0.62,
        dataSource: rows,
        palette: [COLORS.red, COLORS.green],
        animation: CHART_ANIMATION,
        series: [{ argumentField: 'label', valueField: 'total', label: { visible: true, connector: { visible: false }, customizeText: a => `${a.argumentText} ${a.valueText}` } }],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        tooltip: { enabled: true },
        onPointClick: function (e) { applyDrilldown('retard_predit', e.target.argument, 'Retard prédit : ' + e.target.argument); }
    });
}

function renderCommercialSummary(selector, rows) {
    resetChartContainer(selector);
    const ds = normalizeMonthValueRows(rows);
    if (!ds.length) return emptyChart(selector);
    $(selector).addClass('chart-rise');
    $(selector).dxChart({
        dataSource: ds,
        animation: CHART_ANIMATION,
        commonSeriesSettings: { argumentField: 'mois' },
        series: [
            { valueField: 'devis', name: 'Devis', type: 'bar', color: COLORS.blueSoft },
            { valueField: 'factures', name: 'Factures', type: 'bar', color: COLORS.blue },
            { valueField: 'taux_transformation', name: 'Taux %', type: 'spline', axis: 'tauxAxis', color: COLORS.violet, point: { visible: true, size: 7 } }
        ],
        valueAxis: [
            { name: 'default', position: 'left', tick: { visible: false }, grid: { color: '#e3ebf7' } },
            { name: 'tauxAxis', position: 'right', max: 100, tick: { visible: false }, grid: { visible: false } }
        ],
        legend: { verticalAlignment: 'bottom', horizontalAlignment: 'center' },
        argumentAxis: { label: { overlappingBehavior: 'stagger' }, tick: { visible: false }, grid: { visible: false } },
        tooltip: { enabled: true },
        onPointClick: e => applyDrilldown('commercial_month', e.target.argument, 'Période : ' + e.target.argument),
        onDone: function () { roundBarChartShapes(selector); }
    });
}

function updateContextTexts() {
    const map = {
        global: ['Vue globale', 'Synthèse des délais, conformité, transformation commerciale et indicateurs prédictifs.'],
        clients: ['Clients', 'Volumes clients, délais, qualité des demandes et priorité ML. La priorité indique les clients à suivre en premier.'],
        analyses: ['Analyses & conformité', 'Analyses fréquentes, retards prédits et sensibilité ML. La sensibilité indique les analyses qui pèsent le plus sur le délai ou le risque.'],
        commercial: ['Commercial', 'Devis, factures, transformation et délai de conversion.']
    };
    const selected = map[currentView] || map.global;
    $('#contextTitle').text(selected[0]);
    $('#contextSubtitle').text(selected[1]);
}











/* ==========================================================
   TARGETED HOTFIX - CURRENT USER FILES
   Only overrides time-filtering, grid export buttons, chart rise
   and the requested client/analysis wording behavior.
========================================================== */

const EXPORT_BUTTON_CLASS = 'grid-export-icon-btn';

function buildApiParams() {
    const params = {};
    const dateStart = $('#dashboardDateDebut').dxDateBox('instance').option('value');
    const dateEnd = $('#dashboardDateFin').dxDateBox('instance').option('value');
    const year = $('#dashboardYearFilter').dxSelectBox('instance').option('value');
    const month = $('#dashboardMonthFilter').dxSelectBox('instance').option('value');

    if (dateStart) {
        const start = formatDateForApi(dateStart);
        params.date_start = start;
        params.date_debut = start;
        params.start_date = start;
        params.start = start;
    }
    if (dateEnd) {
        const end = formatDateForApi(dateEnd);
        params.date_end = end;
        params.date_fin = end;
        params.end_date = end;
        params.end = end;
    }
    if (year) {
        params.year = year;
        params.annee = year;
    }
    if (month) {
        params.month = month;
        params.mois = month;
    }
    return params;
}

function parseDateSafe(value) {
    if (!value) return null;
    if (value instanceof Date && !isNaN(value.getTime())) return value;

    const raw = String(value).trim();
    if (!raw || raw === '-' || raw === '0000-00-00' || raw.startsWith('1900-01-01')) return null;

    let m = raw.match(/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T].*)?$/);
    if (m) {
        const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
        return isNaN(d.getTime()) ? null : d;
    }

    m = raw.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})(?:[ T].*)?$/);
    if (m) {
        const d = new Date(Number(m[3]), Number(m[2]) - 1, Number(m[1]));
        return isNaN(d.getTime()) ? null : d;
    }

    m = raw.match(/^(\d{4})-(\d{1,2})$/);
    if (m) {
        const d = new Date(Number(m[1]), Number(m[2]) - 1, 1);
        return isNaN(d.getTime()) ? null : d;
    }

    const d = new Date(raw);
    return isNaN(d.getTime()) ? null : d;
}

function dateInsideCurrentFilters(date) {
    const d0 = parseDateSafe(date);
    if (!d0) return false;

    const start = parseDateSafe($('#dashboardDateDebut').dxDateBox('instance')?.option('value'));
    const end = parseDateSafe($('#dashboardDateFin').dxDateBox('instance')?.option('value'));
    const year = $('#dashboardYearFilter').dxSelectBox('instance')?.option('value');
    const month = $('#dashboardMonthFilter').dxSelectBox('instance')?.option('value');

    const d = new Date(d0);
    d.setHours(0, 0, 0, 0);

    if (start) {
        start.setHours(0, 0, 0, 0);
        if (d < start) return false;
    }
    if (end) {
        end.setHours(23, 59, 59, 999);
        if (d > end) return false;
    }
    if (year && d.getFullYear() !== Number(year)) return false;
    if (month && d.getMonth() + 1 !== Number(month)) return false;
    return true;
}

function filterMonthlyRows(rows) {
    if (!hasActiveTimeFilters()) return rows || [];
    const start = parseDateSafe($('#dashboardDateDebut').dxDateBox('instance')?.option('value'));
    const end = parseDateSafe($('#dashboardDateFin').dxDateBox('instance')?.option('value'));
    const year = $('#dashboardYearFilter').dxSelectBox('instance')?.option('value');
    const month = $('#dashboardMonthFilter').dxSelectBox('instance')?.option('value');

    return (rows || []).filter(r => {
        const raw = String(r.mois || r.periode || r.date || '').trim();
        if (!raw) return true;
        const m = raw.match(/(\d{4})[-/](\d{1,2})/);
        if (!m) return true;
        const d = new Date(Number(m[1]), Number(m[2]) - 1, 1);
        if (year && Number(m[1]) !== Number(year)) return false;
        if (month && Number(m[2]) !== Number(month)) return false;
        if (start) {
            const s = new Date(start.getFullYear(), start.getMonth(), 1);
            if (d < s) return false;
        }
        if (end) {
            const e = new Date(end.getFullYear(), end.getMonth(), 1);
            if (d > e) return false;
        }
        return true;
    });
}

function resetChartContainer(selector) {
    const $el = $(selector);
    if (!$el.length) return;
    const element = $el.get(0);
    $el.removeClass('chart-rise chart-rise-active');
    try { const pie = DevExpress.viz.dxPieChart.getInstance(element); if (pie) pie.dispose(); } catch (e) {}
    try { const chart = DevExpress.viz.dxChart.getInstance(element); if (chart) chart.dispose(); } catch (e) {}
    try { const funnel = DevExpress.viz.dxFunnel.getInstance(element); if (funnel) funnel.dispose(); } catch (e) {}
    $el.empty();
}

function activateChartRise(selector) {
    const $el = $(selector);
    if (!$el.length) return;
    $el.removeClass('chart-rise chart-rise-active');
    void $el.get(0).offsetWidth;
    $el.addClass('chart-rise chart-rise-active');
}

function repaintChartsInActiveView() {
    const selectors = ['#funnelChart','#evolutionDelayChart','#faisabiliteValidationChart','#conformiteGlobalChart','#riskDistributionChart','#commercialSummaryChart','#topClientsChart','#delayByClientChart','#faisabiliteValidationClientChart','#clientTimelineChart','#clientsPriorityMlChart','#frequentAnalysesChart','#analyseSensitivityMlChart','#predictedLateAnalyseChart','#volumeDelayAnalyseChart','#devisFacturesChart','#transformationRateChart','#commercialTrendChart','#devisStateChart','#topClientsDevisChart','#delayDevisFactureChart'];
    selectors.forEach(function (sel) {
        const el = $(sel).get(0);
        if (!el) return;
        activateChartRise(sel);
        try { const chart = DevExpress.viz.dxChart.getInstance(el); if (chart) chart.render(); } catch (e) {}
        try { const pie = DevExpress.viz.dxPieChart.getInstance(el); if (pie) pie.render(); } catch (e) {}
        try { const funnel = DevExpress.viz.dxFunnel.getInstance(el); if (funnel) funnel.render(); } catch (e) {}
    });
}

function exportGridToXlsx(grid, fileName) {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Export');
    DevExpress.excelExporter.exportDataGrid({
        component: grid,
        worksheet,
        autoFilterEnabled: true
    }).then(function () {
        return workbook.xlsx.writeBuffer();
    }).then(function (buffer) {
        saveAs(new Blob([buffer], { type: 'application/octet-stream' }), (fileName || 'export') + '.xlsx');
    });
}

function getGridExportRows(grid) {
    const ds = grid.option('dataSource');
    if (Array.isArray(ds)) return ds;
    try {
        const items = grid.getDataSource().items();
        return Array.isArray(items) ? items.map(r => r.data || r) : [];
    } catch (e) {
        return [];
    }
}

function csvEscape(value) {
    const s = String(value ?? '').replace(/\r?\n/g, ' ');
    return /[";,]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
}

function exportGridToCsv(grid, fileName) {
    const columns = grid.getVisibleColumns()
        .filter(c => c.dataField)
        .map(c => ({ field: c.dataField, caption: c.caption || c.dataField }));
    const rows = getGridExportRows(grid);
    const sep = ';';
    const content = [
        columns.map(c => csvEscape(c.caption)).join(sep),
        ...rows.map(row => columns.map(c => csvEscape(row[c.field])).join(sep))
    ].join('\r\n');
    const blob = new Blob(['\ufeff' + content], { type: 'text/csv;charset=utf-8;' });
    saveAs(blob, (fileName || 'export') + '.csv');
}

function addGridExportButtons(e, fileName) {
    e.toolbarOptions.items.unshift(
        {
            location: 'after',
            widget: 'dxButton',
            options: {
                icon: 'xlsxfile',
                hint: 'Exporter Excel',
                stylingMode: 'text',
                elementAttr: { class: EXPORT_BUTTON_CLASS },
                onClick: function () { exportGridToXlsx(e.component, fileName); }
            }
        },
        {
            location: 'after',
            widget: 'dxButton',
            options: {
                icon: 'export',
                hint: 'Exporter CSV',
                stylingMode: 'text',
                elementAttr: { class: EXPORT_BUTTON_CLASS },
                onClick: function () { exportGridToCsv(e.component, fileName); }
            }
        }
    );
}

function renderClientsSection(data) {
    const k = data?.kpis?.clients || data?.clients?.kpis || {};
    const charts = data?.charts || data?.clients?.charts || {};
    const rows = data.demandes_clients_grid || [];

    const topClients = filterKnownRows(charts.top_clients_volume || [], 'client_nom');
    const delayClients = filterKnownRows(charts.delai_moyen_par_client || [], 'client_nom');
    const fvClients = filterKnownRows(charts.faisabilite_validation_par_client || [], 'client_nom');
    const priorityClients = filterKnownRows(charts.clients_prioritaires_ml || [], 'client_nom').filter(r => toNumber(r.score_priorite_ml || r.total || r.value) > 0);
    const timeline = (charts.volume_demandes_client_temps || []).filter(r => !isUnknownLabel(r.client_nom));

    const clientLePlusActif = isUnknownLabel(k.client_le_plus_actif) ? firstKnownValue(topClients, 'client_nom', 'total_demandes') : k.client_le_plus_actif;
    const clientLePlusLent = isUnknownLabel(k.client_le_plus_lent) ? firstKnownValue(delayClients, 'client_nom', 'delai_moyen_jours') : k.client_le_plus_lent;

    $('#clientsKpiGrid').html([
        kpiCard('Clients actifs', toNumber(k.clients_actifs), 'Clients avec demandes', 'primary'),
        kpiCard('Client le plus actif', textOrDash(clientLePlusActif), 'Volume le plus élevé', 'violet'),
        kpiCard('Délai moyen client', formatDurationDays(k.delai_moyen_client_jours), 'Date demande à date réception', 'warning'),
        kpiCard('Meilleur taux validation', textOrDash(k.meilleur_taux_validation_client), 'Client le plus stable', 'success'),
        kpiCard('Client le plus lent', textOrDash(clientLePlusLent), 'Délai moyen élevé', 'danger'),
        kpiCard('Clients prioritaires ML', toNumber(k.clients_prioritaires_ml), 'Demandes à suivre en priorité', 'violet'),
        kpiCard('Retards prédits par client', toNumber(k.retards_predits_par_client), 'Alertes ML client', 'danger')
    ].join(''));

    renderHorizontalBarRising('#topClientsChart', topClients, 'client_nom', 'total_demandes', 'Demandes', COLORS.blueSoft, 'client');
    renderLollipopChart('#delayByClientChart', delayClients, 'client_nom', 'delai_moyen_jours', 'Délai moyen de traitement', COLORS.orange, 'client', true);
    renderClientFeasValidation('#faisabiliteValidationClientChart', fvClients);
    renderClientTimeline('#clientTimelineChart', timeline);
    renderHorizontalBarRising('#clientsPriorityMlChart', priorityClients, 'client_nom', 'score_priorite_ml', 'Score priorité ML', COLORS.violet, 'client');
    renderDemandesClientsGrid(rows.filter(r => !isUnknownLabel(r.client_nom)));
}

function updateContextTexts() {
    const map = {
        global: ['Vue globale', 'Synthèse des délais, conformité, transformation commerciale et indicateurs prédictifs.'],
        clients: ['Clients', 'Volumes clients, délai moyen de traitement des demandes clients, qualité des demandes et priorité ML.'],
        analyses: ['Analyses & conformité', 'Analyses fréquentes, retards prédits et sensibilité ML. La sensibilité mesure l’impact d’une analyse sur le délai ou le risque.'],
        commercial: ['Commercial', 'Devis, factures, transformation et délai de conversion.']
    };
    const selected = map[currentView] || map.global;
    $('#contextTitle').text(selected[0]);
    $('#contextSubtitle').text(selected[1]);
}












/* ==========================================================
   MINIMAL PATCH - only fixes rise effect, no-data filter state,
   and drill-down charts after filtering. No structural rewrite.
========================================================== */

function injectMinimalRiseCss() {
    if (document.getElementById('minimal-rise-css')) return;
    const style = document.createElement('style');
    style.id = 'minimal-rise-css';
    style.textContent = `
        @keyframes minimalChartRise {
            0% { opacity: 0; transform: translateY(24px) scaleY(0.94); filter: blur(3px); }
            100% { opacity: 1; transform: translateY(0) scaleY(1); filter: blur(0); }
        }
        .chart-rise-active {
            animation: minimalChartRise 720ms ease-out both !important;
            transform-origin: bottom center;
        }
    `;
    document.head.appendChild(style);
}

function clearDemandSideMetrics(data) {
    const kGlobal = data?.kpis?.global || data?.vue_globale?.kpis || {};
    const kClients = data?.kpis?.clients || data?.clients?.kpis || {};
    const kAnalyses = data?.kpis?.analyses_conformite || data?.analyses_conformite?.kpis || {};
    const charts = data?.charts || data?.vue_globale?.charts || {};

    Object.assign(kGlobal, {
        total_demandes_clients: 0,
        taux_faisabilite: 0,
        taux_validation: 0,
        delai_moyen_global_jours: 0,
        demandes_critiques_ml: 0,
        retards_predits: 0
    });

    Object.assign(kClients, {
        clients_actifs: 0,
        client_le_plus_actif: '-',
        delai_moyen_client_jours: 0,
        meilleur_taux_validation_client: '-',
        client_le_plus_lent: '-',
        clients_prioritaires_ml: 0,
        retards_predits_par_client: 0
    });

    Object.assign(kAnalyses, {
        analyses_distinctes: 0,
        analyse_la_plus_frequente: '-',
        analyse_la_plus_lente: '-',
        delai_moyen_analyse_jours: 0,
        analyse_la_plus_sensible_ml: '-',
        retards_predits_par_analyse: 0
    });

    charts.top_clients_volume = [];
    charts.delai_moyen_par_client = [];
    charts.faisabilite_validation_par_client = [];
    charts.volume_demandes_client_temps = [];
    charts.clients_prioritaires_ml = [];
    charts.analyses_frequentes = [];
    charts.delai_moyen_par_analyse = [];
    charts.analyses_sensibles_ml = [];
    charts.retards_predits_par_analyse = [];
    charts.volume_delai_par_analyse = [];
    charts.evolution_delai_moyen = [];
    charts.faisabilite_validation_globale = [];
    charts.funnel_performance = [];
}

function clearCommercialMetrics(data) {
    const kCommercial = data?.kpis?.commercial || data?.commercial?.kpis || {};
    const charts = data?.charts || data?.commercial?.charts || {};

    Object.assign(kCommercial, {
        nombre_devis: 0,
        nombre_factures: 0,
        taux_transformation: 0,
        devis_en_attente: 0,
        devis_transformes: 0,
        delai_moyen_devis_facture_jours: 0
    });

    charts.devis_vs_factures = [];
    charts.evolution_commerciale = [];
    charts.taux_transformation_mensuel = [];
    charts.delai_devis_facture = [];
    charts.devis_par_etat = [];
    charts.top_clients_par_devis = [];
}

const rebuildFilteredMetricsBeforeMinimal = rebuildFilteredMetrics;
rebuildFilteredMetrics = function (data) {
    const demandRows = data?.demandes_clients_grid || [];
    const devisRows = data?.devis_grid || [];

    if (!demandRows.length) clearDemandSideMetrics(data);
    if (!devisRows.length) clearCommercialMetrics(data);

    rebuildFilteredMetricsBeforeMinimal(data);
};

const applyCurrentDrilldownBeforeMinimal = applyCurrentDrilldown;
applyCurrentDrilldown = function (data) {
    const cloned = applyCurrentDrilldownBeforeMinimal(data);

    if (currentDrilldown && currentDrilldown.type === 'conformite') {
        const label = normalizeText(currentDrilldown.value);
        cloned.demandes_clients_grid = (cloned.demandes_clients_grid || []).filter(function (r) {
            const raw = normalizeText(r.est_conforme || r.conformite || r.taux_conformite || '');
            if (label.includes('non')) return raw.includes('non') || isNon(raw);
            return raw.includes('oui') || raw.includes('conforme') || isOui(raw);
        });
    }

    if (currentDrilldown) rebuildFilteredMetrics(cloned);
    return cloned;
};

const repaintChartsInActiveViewBeforeMinimal = repaintChartsInActiveView;
repaintChartsInActiveView = function () {
    repaintChartsInActiveViewBeforeMinimal();
    setTimeout(function () {
        [
            '#funnelChart', '#evolutionDelayChart', '#faisabiliteValidationChart',
            '#conformiteGlobalChart', '#riskDistributionChart', '#commercialSummaryChart',
            '#topClientsChart', '#delayByClientChart', '#faisabiliteValidationClientChart',
            '#clientTimelineChart', '#clientsPriorityMlChart', '#frequentAnalysesChart',
            '#analyseSensitivityMlChart', '#predictedLateAnalyseChart', '#volumeDelayAnalyseChart',
            '#devisFacturesChart', '#transformationRateChart', '#commercialTrendChart',
            '#devisStateChart', '#topClientsDevisChart', '#delayDevisFactureChart'
        ].forEach(function (selector) {
            const $el = $(selector);
            if (!$el.length || !$el.is(':visible')) return;
            $el.removeClass('chart-rise-active');
            void $el.get(0).offsetWidth;
            $el.addClass('chart-rise-active');
        });
    }, 30);
};

$(function () {
    injectMinimalRiseCss();
});


/* ==========================================================
   FINAL MINIMAL PATCH - requested only
   1) Operational-style rising chart effect
   2) Echantillon / conformité drill-down updates the chart
   No other dashboard structure is changed.
========================================================== */

const PERFORMANCE_RISING_CHART_SELECTORS = [
    '#funnelChart',
    '#evolutionDelayChart',
    '#faisabiliteValidationChart',
    '#conformiteGlobalChart',
    '#riskDistributionChart',
    '#commercialSummaryChart',
    '#topClientsChart',
    '#delayByClientChart',
    '#faisabiliteValidationClientChart',
    '#clientTimelineChart',
    '#clientsPriorityMlChart',
    '#frequentAnalysesChart',
    '#analyseSensitivityMlChart',
    '#predictedLateAnalyseChart',
    '#volumeDelayAnalyseChart',
    '#devisFacturesChart',
    '#transformationRateChart',
    '#commercialTrendChart',
    '#devisStateChart',
    '#topClientsDevisChart',
    '#delayDevisFactureChart'
];

function injectOperationalRiseCssForPerformance() {
    if (document.getElementById('operational-rise-css-performance')) return;

    const style = document.createElement('style');
    style.id = 'operational-rise-css-performance';
    style.textContent = `
        @keyframes operationalChartRisePerformance {
            0% {
                opacity: 0;
                transform: translateY(26px) scaleY(0.92);
                filter: blur(2px) saturate(.92);
            }
            62% {
                opacity: 1;
                transform: translateY(-2px) scaleY(1.018);
                filter: blur(0) saturate(1.04);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scaleY(1);
                filter: blur(0) saturate(1);
            }
        }

        .dashboard-chart-rising {
            animation: operationalChartRisePerformance 780ms cubic-bezier(.16, 1, .3, 1) both !important;
            transform-origin: bottom center;
            will-change: transform, opacity, filter;
        }
    `;
    document.head.appendChild(style);
}

function applyOperationalRiseEffect(selector) {
    injectOperationalRiseCssForPerformance();

    const $root = $(selector);
    if (!$root.length || !$root.is(':visible')) return;

    /* Same principle as dashboard opérationnel:
       wait until the DevExtreme SVG exists, then restart animation. */
    if (!$root.find('svg').length) return;

    const node = $root.get(0);
    node.classList.remove('dashboard-chart-rising');
    void node.offsetWidth;
    node.classList.add('dashboard-chart-rising');
}

function applyOperationalRiseEffects(selectors) {
    const list = selectors || PERFORMANCE_RISING_CHART_SELECTORS;

    setTimeout(function () {
        list.forEach(applyOperationalRiseEffect);
    }, 90);
}

const repaintChartsInActiveViewBeforeOperationalRise = repaintChartsInActiveView;
repaintChartsInActiveView = function () {
    repaintChartsInActiveViewBeforeOperationalRise();
    applyOperationalRiseEffects();
};

function normalizeConformiteChoice(value) {
    const raw = normalizeText(value);

    if (
        raw.includes('non') ||
        raw.includes('ko') ||
        raw === 'nc' ||
        raw.includes('non conforme') ||
        raw.includes('non-conforme') ||
        raw.includes('nonconforme')
    ) {
        return 'non';
    }

    if (
        raw.includes('oui') ||
        raw.includes('ok') ||
        raw === 'c' ||
        raw.includes('conforme')
    ) {
        return 'oui';
    }

    return '';
}

function getConformiteFieldValue(row) {
    const fields = [
        'est_conforme',
        'conformite',
        'conformite_echantillon',
        'etat_conformite',
        'qualite_echantillon',
        'statut_conformite'
    ];

    for (const field of fields) {
        if (row && row[field] !== undefined && row[field] !== null && String(row[field]).trim() !== '') {
            return row[field];
        }
    }

    return null;
}

function hasConformiteField(rows) {
    return (rows || []).some(function (row) {
        return getConformiteFieldValue(row) !== null;
    });
}

function filterRowsByConformite(rows, selectedMode) {
    if (!hasConformiteField(rows)) return rows || [];

    return (rows || []).filter(function (row) {
        const mode = normalizeConformiteChoice(getConformiteFieldValue(row));
        return selectedMode ? mode === selectedMode : true;
    });
}

function getConformiteChartRows(data) {
    return (
        data?.charts?.conformite_globale ||
        data?.vue_globale?.charts?.conformite_globale ||
        []
    );
}

function setConformiteChartRows(data, rows) {
    if (data?.charts) {
        data.charts.conformite_globale = rows;
    }

    if (data?.vue_globale?.charts) {
        data.vue_globale.charts.conformite_globale = rows;
    }
}

function keepOnlySelectedConformiteSlice(data, selectedMode, originalRows) {
    const sourceRows = originalRows || getConformiteChartRows(data);

    const selectedRows = (sourceRows || []).filter(function (row) {
        return normalizeConformiteChoice(row.label || row.conformite || row.etat || row.name || '') === selectedMode;
    });

    if (selectedRows.length) {
        setConformiteChartRows(data, selectedRows);
    }
}

const applyCurrentDrilldownBeforeEchantillonFix = applyCurrentDrilldown;
applyCurrentDrilldown = function (data) {
    if (!currentDrilldown || currentDrilldown.type !== 'conformite') {
        return applyCurrentDrilldownBeforeEchantillonFix(data);
    }

    const cloned = cloneData(data);
    const selectedMode = normalizeConformiteChoice(currentDrilldown.value);
    const originalConformiteRows = cloneData(getConformiteChartRows(cloned));

    cloned.demandes_clients_grid = filterRowsByConformite(cloned.demandes_clients_grid || [], selectedMode);
    cloned.analyses_grid = filterRowsByConformite(cloned.analyses_grid || [], selectedMode);

    rebuildFilteredMetrics(cloned);
    keepOnlySelectedConformiteSlice(cloned, selectedMode, originalConformiteRows);

    return cloned;
};

$(function () {
    injectOperationalRiseCssForPerformance();
});


