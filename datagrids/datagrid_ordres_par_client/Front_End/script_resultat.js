$(function() {
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
    let isLoading = false;
    const dsResultats = new DevExpress.data.CustomStore({
        key: "Ordre_id",
        load: function(loadOptions) {
            if (isLoading) {
                return $.Deferred().resolve([]).promise();
            }
            isLoading = true;
            const params = {};
            const dateDebut = dateDebutBox.option("value");
            const dateFin = dateFinBox.option("value");
            if (dateDebut) {
                params.date_debut = DevExpress.localization.formatDate(dateDebut, "yyyy-MM-dd");
            }
            if (dateFin) {
                params.date_fin = DevExpress.localization.formatDate(dateFin, "yyyy-MM-dd");
            }
            console.log("Chargement avec paramètres:", params);
            const deferred = $.Deferred();
            $.ajax({
                url: "../back_end/api_resultat.php",
                method: "GET",
                data: params,
                dataType: "json",
                timeout: 30000,
                cache: false
            })
            .done(function(response) {
                isLoading = false;
                console.log("Réponse reçue:", response);
                if (response && Array.isArray(response)) {
                    deferred.resolve(response);
                } else if (response && response.error) {
                    console.error("Erreur API:", response.error);
                    deferred.reject(response.error);
                } else {
                    console.warn("Format de réponse inattendu:", response);
                    deferred.resolve([]);
                }
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                isLoading = false;
                console.error("Erreur AJAX:", textStatus, errorThrown);
                console.error("Détails:", jqXHR.responseText);
                let errorMessage = "Erreur de connexion au serveur";
                if (textStatus === "timeout") {
                    errorMessage = "Délai d'attente dépassé";
                } else if (jqXHR.status === 404) {
                    errorMessage = "API non trouvée";
                } else if (jqXHR.status === 500) {
                    errorMessage = "Erreur serveur interne";
                }
                
                deferred.reject(errorMessage);
            });

            return deferred.promise();
        },
        errorHandler: function(error) {
            isLoading = false;
            console.error("Store error handler:", error);
            
            let errorMessage = error;
            if (error && error.message) {
                errorMessage = error.message;
            }
            DevExpress.ui.dialog.alert(errorMessage, "Erreur de chargement");
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
        wordWrapEnabled:true ,
        rowAlternationEnabled: true,
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
            width: 240
        },
        filterRow: {
            visible: true,
            applyFilter: "auto"
        },
        headerFilter: {
            visible: true
        },
        remoteOperations: false,
        scrolling: {
            mode: "standard"
        },
        columns: [
            { 
                dataField: "Ordre_id", 
                caption: "Ordre d'analyse", 
                alignment: "center",
                width: 120,
                allowGrouping: true,
                allowFiltering: true,
                allowSorting: true
            },
            { 
                dataField: "ref_client", 
                caption: "reference client",
                width: 120,
                alignment: "left",
                allowGrouping: true,
                allowFiltering: true,
                allowSorting: true
            },
            {
                dataField: "demande_analyse", 
                caption: "Demande Analyse",
                width: 120,
                alignment: "left",
                allowGrouping: true,
                allowFiltering: true,
                allowSorting: true
            },
            { 
                dataField: "Date_demande", 
                caption: "Date de création", 
                dataType: "date", 
                format: "dd/MM/yyyy", 
                alignment: "center",
                width: 120,
                allowGrouping: true,
                allowFiltering: true,
                allowSorting: true
            },
            { 
                dataField: "analyse", 
                caption: "Analyse",
                alignment: "left",
                width: 140,
                allowGrouping: true,
                allowFiltering: true,
                allowSorting: true
            },
            { 
                dataField: "laboratin", 
                caption: "Laboratin",
                alignment: "left",
                width: 160,
                allowGrouping: true,
                allowFiltering: true,
                allowSorting: true
            },
            { 
                dataField: "statut", 
                caption: "Statut",
                width: 160,
                alignment: "left",
                allowGrouping: true,
                allowFiltering: true,
                allowSorting: true
            },

            { 
                dataField: "resultat_pure", 
                caption: "Résultat",
                alignment: "left",
                cssClass: "font-weight-bold",
                allowGrouping: true,
                allowFiltering: true,
                allowSorting: true,
                cellTemplate: function(container, options) {
                    const value = options.value;
                    if (value && value !== "" && value !== null) {
                        $("<span>")
                            .css({
                                "font-weight": "bold",
                                "text-align": "center"
                            })
                            .text(value)
                            .appendTo(container);
                    } else {
                        $("<span>")
                            .text("-")
                            .css("color", "#999")
                            .appendTo(container);
                    }
                }
            }
        ],
        onContentReady: function(e) {
            const dataGrid = e.component;
            setTimeout(() => {
                const visibleRows = dataGrid.getVisibleRows();
                const $noRowsMessage = $(".dx-datagrid-norows-message");
                
                if (visibleRows.length === 0 && $noRowsMessage.length) {
                    $noRowsMessage.text("No data");
                }
            }, 300);
        },
        onDataErrorOccurred: function(e) {
            console.error("DataGrid error:", e.error);
            isLoading = false;
            e.component.cancel = true;
            
            let message = "Erreur lors du chargement des données";
            if (typeof e.error === "string") {
                message = e.error;
            } else if (e.error && e.error.message) {
                message = e.error.message;
            }
            
            DevExpress.ui.dialog.alert(message, "Erreur");
        },
        onInitialized: function(e) {
            setTimeout(() => {
                const dataSource = e.component.getDataSource();
                if (dataSource) {
                    dataSource.reload().catch(function(error) {
                        console.error("Erreur chargement initial:", error);
                    });
                }
            }, 500);
            setTimeout(function() {
                addExportButtons();
            }, 1000);
        }
    }).dxDataGrid("instance");
    function addExportButtons() {
        const $searchPanel = $(".dx-datagrid-search-panel").closest(".dx-toolbar-item");
        if ($searchPanel.length) {
            const $iconContainer = $("<div>", {
                class: "export-icon-container",
                css: {
                    "display": "flex",
                    "margin-left": "auto",
                    "margin-right": "10px",
                    "gap": "8px",
                    "align-items": "center"
                }
            });
            const $excelIcon = $("<div>", {
                class: "export-icon",
                title: "Exporter en Excel",
                css: {
                    "cursor": "pointer",
                    "width": "32px",
                    "height": "32px",
                    "display": "flex",
                    "align-items": "center",
                    "justify-content": "center",
                    "border-radius": "4px",
                    "background": "#ffffff",
                    "border": "1px solid #d0d0d0",
                    "transition": "all 0.2s",
                    "color": "#333333"
                }
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
                title: "Exporter en CSV",
                css: {
                    "cursor": "pointer",
                    "width": "32px",
                    "height": "32px",
                    "display": "flex",
                    "align-items": "center",
                    "justify-content": "center",
                    "border-radius": "4px",
                    "background": "#ffffff",
                    "border": "1px solid #d0d0d0",
                    "transition": "all 0.2s",
                    "color": "#333333",
                    "font-weight": "bold",
                    "font-size": "12px"
                }
            }).html(`
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#333333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <text x="8" y="18" font-size="10" fill="#333333" font-weight="bold">CSV</text>
                </svg>
            `);
            
            $csvIcon.on("click", function() {
                exportToCsv();
            });
            
            $excelIcon.on("click", function() {
                exportToExcel();
            });
            $excelIcon.hover(
                function() { 
                    $(this).css({
                        "background": "#f0f0f0",
                        "border-color": "#999999",
                        "transform": "scale(1.05)"
                    }); 
                },
                function() { 
                    $(this).css({
                        "background": "#ffffff",
                        "border-color": "#d0d0d0",
                        "transform": "scale(1)"
                    }); 
                }
            );
            
            $csvIcon.hover(
                function() { 
                    $(this).css({
                        "background": "#f0f0f0",
                        "border-color": "#999999",
                        "transform": "scale(1.05)"
                    }); 
                },
                function() { 
                    $(this).css({
                        "background": "#ffffff",
                        "border-color": "#d0d0d0",
                        "transform": "scale(1)"
                    }); 
                }
            );
            $iconContainer.append($excelIcon, $csvIcon);
            $searchPanel.after($iconContainer);
            
            console.log("Boutons d'export ajoutés à droite");
        } else {
            const $toolbar = $(".dx-toolbar-after");
            if ($toolbar.length) {
                const $iconContainer = $("<div>", {
                    class: "export-icon-container",
                    css: {
                        "display": "inline-flex",
                        "gap": "8px",
                        "align-items": "center",
                        "margin-right": "10px"
                    }
                });
                
                const $excelIcon = $("<div>", {
                    class: "export-icon",
                    title: "Exporter en Excel",
                    css: {
                        "cursor": "pointer",
                        "width": "32px",
                        "height": "32px",
                        "display": "inline-flex",
                        "align-items": "center",
                        "justify-content": "center",
                        "border-radius": "4px",
                        "background": "#ffffff",
                        "border": "1px solid #d0d0d0"
                    }
                }).html(`<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#333333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="18" rx="2" ry="2"></rect><line x1="8" y1="9" x2="16" y2="9"></line><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="12" y2="17"></line><path d="M16 17 L21 17 L21 21 L16 21 L16 17 Z"></path></svg>`);
                
                const $csvIcon = $("<div>", {
                    class: "export-icon",
                    title: "Exporter en CSV",
                    css: {
                        "cursor": "pointer",
                        "width": "32px",
                        "height": "32px",
                        "display": "inline-flex",
                        "align-items": "center",
                        "justify-content": "center",
                        "border-radius": "4px",
                        "background": "#ffffff",
                        "border": "1px solid #d0d0d0"
                    }
                }).html(`<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#333333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><text x="8" y="18" font-size="10" fill="#333333" font-weight="bold">CSV</text></svg>`);
                
                $excelIcon.on("click", exportToExcel);
                $csvIcon.on("click", exportToCsv);
                
                $iconContainer.append($excelIcon, $csvIcon);
                $toolbar.prepend($iconContainer);  
                console.log("Boutons d'export ajoutés à droite (fallback)");
            }
        }
    }
    
    function exportToExcel() {
    const dataGrid = grid;
    const dataSource = dataGrid.getDataSource();
    const items = dataSource.items();

    if (items.length === 0) {
        DevExpress.ui.dialog.alert("Aucune donnée à exporter", "Information");
        return;
    }

    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet("Resultats");

    DevExpress.excelExporter.exportDataGrid({
        component: dataGrid,
        worksheet: worksheet,
        autoFilterEnabled: true
    }).then(function() {
        workbook.xlsx.writeBuffer().then(function(buffer) {
            saveAs(new Blob([buffer], { type: "application/octet-stream" }), "resultats_export.xlsx");
        });
    });
}
    
    function exportToCsv() {
        const dataGrid = grid;
        const columns = dataGrid.option("columns");
        const dataSource = dataGrid.getDataSource();
        const items = dataSource.items();
        
        if (items.length === 0) {
            DevExpress.ui.dialog.alert("Aucune donnée à exporter", "Information");
            return;
        }
        
        let csvContent = columns.map(col => col.caption || col.dataField).join(',') + '\n';
        items.forEach(item => {
            const row = columns.map(col => {
                let value = item[col.dataField] || '';
                if (typeof value === 'string' && (value.includes(',') || value.includes('"'))) {
                    value = '"' + value.replace(/"/g, '""') + '"';
                }
                return value;
            }).join(',');
            csvContent += row + '\n';
        });
        
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", "resultats_export.csv");
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    $("#btnFiltrer").dxButton({
        text: "Filtrer",
        type: "success",
        icon: "find",
        width: 150,
        onClick: function() {
            isLoading = false;
            const dataSource = grid.getDataSource();
            if (dataSource) {
                dataSource.reload()
                    .done(function() {
                        console.log("Filtrage appliqué avec succès");
                    })
                    .fail(function(error) {
                        console.error("Erreur lors du filtrage:", error);
                        isLoading = false;
                    });
            }
        }
    });
    
    $("#btnCreateFilter").dxButton({
        text: "Create Filter",
        type: "normal",
        icon: "filter",
        width: 150,
        onClick: function() {
            grid.option("filterRow.visible", true);
            setTimeout(() => {
                const $firstFilterInput = $(".dx-datagrid-filter-row input:first");
                if ($firstFilterInput.length) {
                    $firstFilterInput.focus();
                }
            }, 200);
        }
    });
    
    $(window).on("beforeunload", function() {
        isLoading = false;
    });
});