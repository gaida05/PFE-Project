const API_BASE_URL = "../back_end/api.php";
const REPORT_URL = "../tcpdf_rapport/new_report.php"
const selectedFormats = {};
let monthYearFilter = null;
let selectedMonth = null;
let filterCollapsed = false;

$(function () {
    initFilters();
    initGrid();
});

function escapeHtml(value) {
    return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function getMonthParam(dateValue) {
    if (!dateValue) {
        return "";
    }

    const year = dateValue.getFullYear();
    const month = String(dateValue.getMonth() + 1).padStart(2, "0");
    return `${year}-${month}`;
}

function buildReportUrl(rowData, formatCode, langCode) {
    const params = new URLSearchParams({
        id_demande: rowData.id_demande,
        format: formatCode || "format_1",
        lang: langCode || "fr"
    });

    return `${REPORT_URL}?${params.toString()}`;
}

function openReport(rowData, formatCode, langCode) {
    window.open(buildReportUrl(rowData, formatCode, langCode), "_blank");
}

function setActiveFormatButton($container, formatCode) {
    $container.find(".btn-report").removeClass("active");
    $container.find(`.btn-report[data-format='${formatCode}']`).addClass("active");
}

function badgeYesNo(value) {
    if (value === "Oui") {
        return '<span class="badge-yes">Oui</span>';
    }

    if (value === "Non") {
        return '<span class="badge-no">Non</span>';
    }

    return '<span class="badge-empty">-</span>';
}

function buildAnalyseCell(data) {
    const first = escapeHtml(data.analyse_principale || "-");
    const count = Number(data.nb_analyses || 0);
    const extra = count - 1;

    if (count <= 0) {
        return `<div>-</div>`;
    }

    if (extra > 0) {
        return `
            <div>${first}</div>
            <div style="margin-top:6px;color:#6b7280;font-size:12px;">(+ ${extra} autres)</div>
        `;
    }

    return `<div>${first}</div>`;
}

function renderActionButtons(container, rowData) {
    const rowKey = rowData.row_key;
    const initialFormat = selectedFormats[rowKey] || rowData.default_layout || "format_1";
    selectedFormats[rowKey] = initialFormat;

    const $wrap = $("<div>").addClass("action-stack");

    const formats = [
        { code: "format_1", label: "F1", title: "Générer le rapport format 1" },
        { code: "format_2", label: "F2", title: "Générer le rapport format 2" },
        { code: "format_3", label: "F3", title: "Générer le rapport format 3" }
    ];

    formats.forEach((item) => {
        const $btn = $("<button>", {
            type: "button",
            class: "btn-report" + (initialFormat === item.code ? " active" : ""),
            text: item.label,
            title: item.title
        }).attr("data-format", item.code);

        $btn.on("click", function () {
            selectedFormats[rowKey] = item.code;
            setActiveFormatButton($wrap, item.code);
            openReport(rowData, item.code, "fr");
        });

        $wrap.append($btn);
    });

    const $btnAr = $("<button>", {
        type: "button",
        class: "btn-lang btn-lang-ar",
        text: "AR",
        title: "Ouvrir le format sélectionné en arabe"
    });

    const $btnEn = $("<button>", {
        type: "button",
        class: "btn-lang btn-lang-en",
        text: "EN",
        title: "Ouvrir le format sélectionné en anglais"
    });

    $btnAr.on("click", function () {
        const formatToUse = selectedFormats[rowKey] || "format_1";
        openReport(rowData, formatToUse, "ar");
    });

    $btnEn.on("click", function () {
        const formatToUse = selectedFormats[rowKey] || "format_1";
        openReport(rowData, formatToUse, "en");
    });

    $wrap.append($btnAr, $btnEn);
    $(container).append($wrap);
}

function showGridLoading() {
    const grid = $("#gridDemandesClient");
    if (!grid.children().length) {
        grid.html('<div class="loading-box">Chargement...</div>');
    }
}

function createStore() {
    return new DevExpress.data.CustomStore({
        key: "row_key",
        load: function () {
            const params = {
                action: "list_demandes"
            };

            const monthParam = getMonthParam(selectedMonth);
            if (monthParam) {
                params.month = monthParam;
            }

            return $.getJSON(API_BASE_URL, params)
                .then(function (response) {
                    if (!response || !response.success) {
                        DevExpress.ui.notify(
                            (response && response.message) || "Erreur de chargement",
                            "error",
                            3000
                        );
                        return [];
                    }

                    return response.data || [];
                })
                .catch(function () {
                    DevExpress.ui.notify("Impossible de charger les données", "error", 3000);
                    return [];
                });
        }
    });
}

function applyFilters() {
    const grid = $("#gridDemandesClient").dxDataGrid("instance");
    if (grid) {
        grid.refresh();
    }
}

function resetFilters() {
    selectedMonth = null;

    if (monthYearFilter) {
        monthYearFilter.option("value", null);
    }

    const grid = $("#gridDemandesClient").dxDataGrid("instance");
    if (grid) {
        grid.refresh();
    }
}

function toggleFilterCard() {
    const $filterCard = $(".filter-card");
    const $content = $filterCard.children().not("#btnToggleFilterMini");

    filterCollapsed = !filterCollapsed;

    if (filterCollapsed) {
        $content.hide();
        $("#btnToggleFilterMini").text("+");
        $filterCard.css({
            paddingTop: "8px",
            paddingBottom: "8px",
            minHeight: "40px"
        });
    } else {
        $content.show();
        $("#btnToggleFilterMini").text("−");
        $filterCard.css({
            paddingTop: "",
            paddingBottom: "",
            minHeight: ""
        });
    }
}

function initFilters() {
    monthYearFilter = $("#dashboardMonthYear").dxDateBox({
        type: "date",
        displayFormat: "MMMM yyyy",
        placeholder: "Mois / Année",
        showClearButton: true,
        openOnFieldClick: true,
        width: 405,
        value: null,
        calendarOptions: {
            zoomLevel: "year",
            maxZoomLevel: "year",
            minZoomLevel: "century"
        },
        onValueChanged: function (e) {
            selectedMonth = e.value;
        }
    }).dxDateBox("instance");

    $("#btnDashboardFiltrer").dxButton({
        text: "Filtrer",
        type: "success",
        icon: "search",
        width: 188,
        height: 44,
        onClick: function () {
            applyFilters();
        }
    });

    $("#btnDashboardReset").dxButton({
        text: "Réinitialiser",
        type: "normal",
        icon: "clear",
        width: 188,
        height: 44,
        onClick: function () {
            resetFilters();
        }
    });

    $("#btnToggleFilterMini").on("click", function () {
        toggleFilterCard();
    });
}

function initGrid() {
    showGridLoading();

    $("#gridDemandesClient").dxDataGrid({
        dataSource: createStore(),
        keyExpr: "row_key",
        showBorders: true,
        showColumnLines: true,
        showRowLines: true,
        rowAlternationEnabled: true,
        hoverStateEnabled: true,
        columnAutoWidth: false,
        wordWrapEnabled: true,
        remoteOperations: false,
        width: "100%",
        columnHidingEnabled: false,
        repaintChangesOnly: true,

        scrolling: {
            mode: "standard",
            showScrollbar: "always",
            useNative: false
        },

        groupPanel: {
            visible: true,
            emptyPanelText: "Glissez un en-tête ici pour grouper les données"
        },

        searchPanel: {
            visible: true,
            width: 220,
            placeholder: "Rechercher..."
        },

        filterRow: {
            visible: true,
            applyFilter: "auto"
        },

        loadPanel: {
            enabled: true,
            text: "Chargement..."
        },

        paging: {
            pageSize: 12
        },

        pager: {
            showInfo: true,
            showNavigationButtons: true,
            showPageSizeSelector: true,
            allowedPageSizes: [12, 24, 50]
        },

        noDataText: "Aucune donnée disponible",

        columns: [
            {
                dataField: "id_demande_masked",
                caption: "ID Demande",
                width: 110,
                allowHeaderFiltering: false,
                cellTemplate: function (container, options) {
                    $(container).html(
                        `<span class="analyse-count">${escapeHtml(options.value || "-")}</span>`
                    );
                }
            },
            {
                dataField: "ref_demande",
                caption: "Réf Demande",
                width: 150
            },
            {
                dataField: "id_client_masked",
                caption: "ID Client",
                width: 95,
                allowHeaderFiltering: false,
                cellTemplate: function (container, options) {
                    $(container).html(
                        `<span class="analyse-count">${escapeHtml(options.value || "-")}</span>`
                    );
                }
            },
            {
                dataField: "client_name",
                caption: "Client",
                width: 150
            },
            {
                dataField: "nb_analyses",
                caption: "ID Analyse",
                width: 110,
                alignment: "center",
                allowHeaderFiltering: false,
                cellTemplate: function (container, options) {
                    $(container).html(
                        `<span class="analyse-count">${escapeHtml(options.value || "0")}***</span>`
                    );
                }
            },
            {
                dataField: "analyse_principale",
                caption: "Analyse",
                width: 320,
                cellTemplate: function (container, options) {
                    $(container).html(buildAnalyseCell(options.data));
                }
            },
            {
                dataField: "date_demande",
                caption: "Date demande",
                width: 130
            },
            {
                dataField: "date_reception",
                caption: "Date réception",
                width: 130
            },
            {
                caption: "Rapports",
                width: 245,
                minwidth:170,
                allowFiltering: false,
                allowSorting: false,
                alignment: "center",
                cellTemplate: function (container, options) {
                    renderActionButtons(container, options.data);
                }
            }
        ]
    });
}