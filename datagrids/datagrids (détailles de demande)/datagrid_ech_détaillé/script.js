$(function () {
    const API_URL = "api.php";
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
            .text(realValue ? "••••" : "-")
            .css({
                display: "inline-block",
                padding: "6px 14px",
                "border-radius": "20px",
                "background-color": "#e9ecef",
                color: "#2c3e50",
                "font-weight": "700",
                "font-size": "14px",
                "letter-spacing": "1px",
                cursor: realValue ? "pointer" : "default",
                "text-align": "center",
                "min-width": "54px"
            })
            .appendTo(container);

        $badge.attr("title", `${prefix}: ${realValue || "-"}`);
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

    function getConformiteMeta(rawValue) {
        const value = normalizeText(rawValue).toLowerCase();

        const defaults = {
            text: rawValue || "-",
            bgColor: "#e9ecef",
            textColor: "#495057"
        };

        const positiveValues = ["oui", "o", "conforme", "conf", "ok"];
        const negativeValues = ["non", "n", "nf", "non conforme", "non-conforme", "pas conforme"];

        if (positiveValues.includes(value)) {
            return {
                text: "Conforme",
                bgColor: "#d4edda",
                textColor: "#155724"
            };
        }

        if (negativeValues.includes(value)) {
            return {
                text: "Non conforme",
                bgColor: "#f8d7da",
                textColor: "#721c24"
            };
        }

        return defaults;
    }

    function buildConformiteCellTemplate() {
        return function (container, options) {
            const meta = getConformiteMeta(options.value);

            $("<div>")
                .text(meta.text)
                .css({
                    display: "inline-block",
                    padding: "6px 12px",
                    "border-radius": "16px",
                    "background-color": meta.bgColor,
                    color: meta.textColor,
                    "font-weight": "700",
                    "text-align": "center",
                    "min-width": "120px"
                })
                .appendTo(container);
        };
    }

    function buildConformiteFilterExpression(fieldName, filterValue) {
        const value = normalizeText(filterValue).toLowerCase();

        if (!value) {
            return null;
        }

        const statusMap = {
            "conforme": ["oui", "o", "conforme", "conf", "ok"],
            "non conforme": ["non", "n", "nf", "non conforme", "non-conforme", "pas conforme"]
        };

        let rawValues = null;

        if (value === "conforme") {
            rawValues = statusMap["conforme"];
        } else if (value === "non conforme") {
            rawValues = statusMap["non conforme"];
        }

        if (!rawValues) {
            return [fieldName, "contains", filterValue];
        }

        const expressions = rawValues.map(function (item) {
            return [fieldName, "=", item];
        });

        let result = expressions[0];
        for (let i = 1; i < expressions.length; i++) {
            result = [result, "or", expressions[i]];
        }

        return result;
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
        key: "demande_id",
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
        columnChooser: {
            enabled: true,
            mode: "select",
            title: "Choisir les colonnes",
            height: 420,
            width: 320,
            search: {
                enabled: true,
                editorOptions: {
                    placeholder: "Rechercher une colonne..."
                }
            }
        },
        columnFixing: {
            enabled: true
        },
        columns: [
            {
                dataField: "demande_id",
                caption: "Id échantillon",
                width: 110,
                alignment: "center",
                allowSorting: false,
                allowHiding: false,
                fixed: true,
                fixedPosition: "left",
                cellTemplate: function (container, options) {
                    createMaskedIdCell(container, options.value, "Id échantillon");
                }
            },
            {
                dataField: "code_echantillon",
                caption: "Code échantillon",
                width: 220,
                allowHiding: false,
                fixed: true,
                fixedPosition: "left"
            },
            {
                dataField: "date_reception",
                caption: "Date réception",
                dataType: "date",
                format: "dd/MM/yyyy",
                alignment: "center",
                width: 140
            },
            {
                dataField: "nature_echantillon",
                caption: "Type échantillon",
                width: 220
            },
            {
                dataField: "nb_analyse",
                caption: "Nombre d'analyse",
                width: 150,
                alignment: "center"
            },
            {
                dataField: "description_echantillon",
                caption: "Description échantillon",
                width: 220
            },
            {
                dataField: "conformite",
                caption: "Conformité",
                width: 140,
                alignment: "center",
                cellTemplate: buildConformiteCellTemplate(),
                calculateFilterExpression: function (filterValue) {
                    return buildConformiteFilterExpression("conformite", filterValue);
                },
                headerFilter: {
                    dataSource: [
                        { text: "Conforme", value: "conforme" },
                        { text: "Non conforme", value: "non conforme" }
                    ]
                }
            },
            {
                dataField: "ref_demande",
                caption: "Réf. demande",
                width: 160,
                visible: false
            },
            {
                dataField: "client_name",
                caption: "Client",
                width: 220,
                visible: false
            },
            {
                dataField: "categorie_echantillon_name",
                caption: "Catégorie",
                width: 180,
                visible: false
            },
            {
                dataField: "analyses",
                caption: "Analyses",
                width: 260,
                visible: false
            },
            {
                dataField: "temperature",
                caption: "Température",
                width: 120,
                alignment: "center",
                visible: false
            },
            {
                dataField: "lot",
                caption: "Lot",
                width: 120,
                visible: false
            },
            {
                dataField: "echantillon",
                caption: "Échantillon",
                width: 180,
                visible: false
            },
            {
                dataField: "fraction",
                caption: "Fraction",
                width: 120,
                visible: false
            }
        ],
        onToolbarPreparing: function (e) {
            e.toolbarOptions.items.push({
                location: "after",
                widget: "dxButton",
                options: {
                    icon: "columnchooser",
                    text: "Colonnes",
                    onClick: function () {
                        e.component.showColumnChooser();
                    }
                }
            });
        },
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

                if (col.dataField === "conformite") {
                    value = getConformiteMeta(value).text;
                }

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