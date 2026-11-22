$(function () {
    const API_URL = "../back_end/api_resultat.php";
    let isLoading = false;
    let filterVisible = false;

    function formatDateParam(date) {
        return DevExpress.localization.formatDate(date, "yyyy-MM-dd");
    }

    function normalizeText(value) {
        return value == null ? "" : String(value).trim();
    }

    function createMaskedIdCell(container, value, prefix) {
        const realValue = normalizeText(value);

        const $badge = $("<div>")
            .text("••••")
            .css({
                display: "inline-block",
                padding: "6px 14px",
                "border-radius": "20px",
                "background-color": "#e9ecef",
                color: "#2c3e50",
                "font-weight": "700",
                "font-size": "14px",
                "letter-spacing": "1px",
                cursor: "pointer",
                "text-align": "center",
                "min-width": "54px"
            })
            .appendTo(container);

        if (realValue) {
            $badge.attr("title", `${prefix}: ${realValue}`);
        } else {
            $badge.attr("title", `${prefix}: -`);
        }
    }

    function updateFilterDisplay() {
        if ($("#filterCollapsedBar").length && $("#filterCard").length) {
            if (filterVisible) {
                $("#filterCollapsedBar").hide();
                $("#filterCard").stop(true, true).slideDown(200);
            } else {
                $("#filterCard").hide();
                $("#filterCollapsedBar").show();
            }
        }
    }

    const dateDebutBox = $("#dateDebut").dxDateBox({
        type: "date",
        displayFormat: "yyyy-MM-dd",
        placeholder: "Date début",
        showClearButton: true,
        useMaskBehavior: true,
        width: "100%",
        label: "Date début",
        labelMode: "floating"
    }).dxDateBox("instance");

    const dateFinBox = $("#dateFin").dxDateBox({
        type: "date",
        displayFormat: "yyyy-MM-dd",
        placeholder: "Date fin",
        showClearButton: true,
        useMaskBehavior: true,
        width: "100%",
        label: "Date fin",
        labelMode: "floating"
    }).dxDateBox("instance");

    const dsResultats = new DevExpress.data.CustomStore({
        key: "Ordre_id",
        load: function () {
            if (isLoading) {
                return $.Deferred().resolve([]).promise();
            }

            isLoading = true;
            const params = {};
            const dateDebut = dateDebutBox.option("value");
            const dateFin = dateFinBox.option("value");

            if (dateDebut) {
                params.date_debut = formatDateParam(dateDebut);
            }
            if (dateFin) {
                params.date_fin = formatDateParam(dateFin);
            }

            const deferred = $.Deferred();

            $.ajax({
                url: API_URL,
                method: "GET",
                data: params,
                dataType: "json",
                timeout: 30000,
                cache: false
            })
                .done(function (response) {
                    isLoading = false;

                    if (Array.isArray(response)) {
                        deferred.resolve(response);
                    } else if (response && response.error) {
                        deferred.reject(response.error);
                    } else {
                        deferred.resolve([]);
                    }
                })
                .fail(function (jqXHR, textStatus, errorThrown) {
                    isLoading = false;

                    let errorMessage = "Erreur de connexion au serveur";

                    if (textStatus === "timeout") {
                        errorMessage = "Délai d'attente dépassé";
                    } else if (jqXHR.status === 404) {
                        errorMessage = "API non trouvée";
                    } else if (jqXHR.status === 500) {
                        errorMessage = jqXHR.responseJSON && jqXHR.responseJSON.error
                            ? jqXHR.responseJSON.error
                            : "Erreur serveur interne";
                    } else if (errorThrown) {
                        errorMessage = errorThrown;
                    }

                    deferred.reject(errorMessage);
                });

            return deferred.promise();
        },
        errorHandler: function (error) {
            isLoading = false;
            const errorMessage = error && error.message ? error.message : error;
            DevExpress.ui.dialog.alert(errorMessage || "Erreur de chargement", "Erreur");
        }
    });

    const grid = $("#gridContainer").dxDataGrid({
        dataSource: {
            store: dsResultats,
            paginate: true,
            pageSize: 15
        },
        showBorders: true,
        columnAutoWidth: false,
        wordWrapEnabled: true,
        rowAlternationEnabled: true,
        remoteOperations: false,
        repaintChangesOnly: true,
        loadPanel: {
            enabled: true,
            text: "Chargement...",
            showIndicator: true,
            showPane: true,
            shading: true,
            shadingColor: "rgba(0,0,0,0.12)",
            delay: 200
        },
        paging: {
            pageSize: 15,
            enabled: true
        },
        pager: {
            showPageSizeSelector: true,
            allowedPageSizes: [10, 15, 20, 50],
            showInfo: true,
            showNavigationButtons: true,
            visible: true
        },
        grouping: {
            autoExpandAll: false,
            allowCollapsing: true
        },
        groupPanel: {
            visible: true,
            emptyPanelText: "Déplacez un en-tête de colonne ici pour grouper par cette colonne"
        },
        searchPanel: {
            visible: true,
            placeholder: "Rechercher...",
            width: 260
        },
        filterRow: {
            visible: true,
            applyFilter: "auto"
        },
        headerFilter: {
            visible: true
        },
        scrolling: {
            mode: "standard"
        },
        columns: [
            {
                dataField: "Ordre_id",
                caption: "Ordre ID",
                width: 110,
                alignment: "center",
                allowSorting: false,
                cellTemplate: function (container, options) {
                    createMaskedIdCell(container, options.value, "Ordre ID");
                }
            },
            {
                dataField: "ordre_reference",
                caption: "Ordre d'analyse",
                width: 220
            },
            {
                dataField: "ref_client",
                caption: "reference client",
                width: 150
            },
            {
                dataField: "demande_analyse",
                caption: "Demande Analyse",
                width: 150
            },
            {
                dataField: "Date_demande",
                caption: "Date de création",
                dataType: "date",
                format: "dd/MM/yyyy",
                alignment: "center",
                width: 140
            },
            {
                dataField: "analyse",
                caption: "Analyse",
                width: 220
            },
            {
                dataField: "laboratin",
                caption: "Laboratin",
                width: 170
            },
            {
                dataField: "statut",
                caption: "Statut",
                width: 160,
                cellTemplate: function (container, options) {
                    const value = normalizeText(options.value);
                    const lower = value.toLowerCase();
                    let textColor = "#555";
                    let bgColor = "#f1f3f5";

                    if (lower === "terminé" || lower === "termine") {
                        textColor = "#0a8f08";
                        bgColor = "#eef9ee";
                    } else if (lower === "pas évaluée" || lower === "pas evaluee") {
                        textColor = "#6c757d";
                        bgColor = "#f3f4f6";
                    } else if (lower === "en cours") {
                        textColor = "#b58105";
                        bgColor = "#fff8e1";
                    }

                    $("<div>")
                        .text(value || "-")
                        .css({
                            color: textColor,
                            "background-color": bgColor,
                            "font-weight": "700",
                            padding: "6px 10px",
                            "border-radius": "8px",
                            "text-align": "center",
                            "text-transform": "none"
                        })
                        .appendTo(container);
                }
            },
            {
                dataField: "resultat_pure",
                caption: "Résultat",
                minWidth: 160,
                cellTemplate: function (container, options) {
                    const value = normalizeText(options.value);

                    $("<span>")
                        .text(value || "-")
                        .css({
                            "font-weight": value ? "bold" : "normal",
                            color: value ? "#222" : "#999"
                        })
                        .appendTo(container);
                }
            }
        ],
        onContentReady: function (e) {
            const dataGrid = e.component;
            setTimeout(function () {
                const visibleRows = dataGrid.getVisibleRows();
                const $noRowsMessage = $(".dx-datagrid-norows-message");

                if (visibleRows.length === 0 && $noRowsMessage.length) {
                    $noRowsMessage.text("Aucune donnée");
                }
            }, 200);
        },
        onDataErrorOccurred: function (e) {
            isLoading = false;
            const message = typeof e.error === "string"
                ? e.error
                : (e.error && e.error.message ? e.error.message : "Erreur lors du chargement des données");

            DevExpress.ui.dialog.alert(message, "Erreur");
        },
        onInitialized: function (e) {
            setTimeout(function () {
                const dataSource = e.component.getDataSource();
                if (dataSource) {
                    dataSource.reload();
                }
            }, 300);

            setTimeout(function () {
                addExportButtons();
            }, 800);
        }
    }).dxDataGrid("instance");

    function getVisibleColumnsForExport() {
        return grid.getVisibleColumns().filter(function (col) {
            return !!col.dataField;
        });
    }

    function addExportButtons() {
        if ($(".export-icon-container").length) {
            return;
        }

        const $searchPanel = $(".dx-datagrid-search-panel").closest(".dx-toolbar-item");
        const $toolbar = $searchPanel.length ? $searchPanel.parent() : $(".dx-toolbar-after");

        if (!$toolbar.length) {
            return;
        }

        const $iconContainer = $("<div>", {
            class: "export-icon-container",
            css: {
                display: "flex",
                gap: "8px",
                "align-items": "center",
                "margin-left": "auto",
                "margin-right": "10px"
            }
        });

        const $excelIcon = $("<div>", {
            class: "export-icon",
            title: "Exporter en Excel"
        }).html(`
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#333333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="18" rx="2" ry="2"></rect>
                <line x1="8" y1="9" x2="16" y2="9"></line>
                <line x1="8" y1="13" x2="16" y2="13"></line>
                <line x1="8" y1="17" x2="12" y2="17"></line>
                <path d="M16 17 L21 17 L21 21 L16 21 L16 17 Z"></path>
            </svg>
        `);

        const $csvIcon = $("<div>", {
            class: "export-icon",
            title: "Exporter en CSV"
        }).html(`
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#333333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <text x="8" y="18" font-size="10" fill="#333333" font-weight="bold">CSV</text>
            </svg>
        `);

        $excelIcon.on("click", exportToExcel);
        $csvIcon.on("click", exportToCsv);

        $iconContainer.append($excelIcon, $csvIcon);

        if ($searchPanel.length) {
            $searchPanel.after($iconContainer);
        } else {
            $toolbar.prepend($iconContainer);
        }
    }

    function exportToExcel() {
        const items = grid.getDataSource().items();

        if (!items.length) {
            DevExpress.ui.dialog.alert("Aucune donnée à exporter", "Information");
            return;
        }

        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("Resultats");

        DevExpress.excelExporter.exportDataGrid({
            component: grid,
            worksheet: worksheet,
            autoFilterEnabled: true
        }).then(function () {
            workbook.xlsx.writeBuffer().then(function (buffer) {
                saveAs(new Blob([buffer], { type: "application/octet-stream" }), "resultats_export.xlsx");
            });
        });
    }

    function exportToCsv() {
        const items = grid.getDataSource().items();

        if (!items.length) {
            DevExpress.ui.dialog.alert("Aucune donnée à exporter", "Information");
            return;
        }

        const columns = getVisibleColumnsForExport();

        let csvContent = columns.map(function (col) {
            return '"' + (col.caption || col.dataField).replace(/"/g, '""') + '"';
        }).join(",") + "\n";

        items.forEach(function (item) {
            const row = columns.map(function (col) {
                let value = item[col.dataField];

                if (value === null || value === undefined) {
                    value = "";
                }

                value = String(value).replace(/"/g, '""');
                return '"' + value + '"';
            }).join(",");

            csvContent += row + "\n";
        });

        const blob = new Blob(["\uFEFF" + csvContent], { type: "text/csv;charset=utf-8;" });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);

        link.setAttribute("href", url);
        link.setAttribute("download", "resultats_export.csv");
        link.style.visibility = "hidden";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    $("#btnFiltrer").dxButton({
        text: "Filtrer",
        type: "success",
        icon: "find",
        width: 150,
        onClick: function () {
            isLoading = false;
            const dataSource = grid.getDataSource();
            if (dataSource) {
                dataSource.reload();
            }
        }
    });

    $("#btnCreateFilter").dxButton({
        text: "Créer filtre",
        type: "normal",
        icon: "filter",
        width: 150,
        onClick: function () {
            grid.option("filterRow.visible", true);
            setTimeout(function () {
                const $firstFilterInput = $(".dx-datagrid-filter-row input:first");
                if ($firstFilterInput.length) {
                    $firstFilterInput.focus();
                }
            }, 200);
        }
    });

    $("#btnShowFilter").on("click", function () {
        filterVisible = true;
        updateFilterDisplay();
    });

    $("#btnHideFilter").on("click", function () {
        filterVisible = false;
        updateFilterDisplay();
    });

    updateFilterDisplay();

    $(window).on("beforeunload", function () {
        isLoading = false;
    });
});