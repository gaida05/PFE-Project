$(function() {
    $.getJSON("	../back_end/api.php", function(data) {
        console.log("Données reçues:", data);
        let totalConforme = 0;
        let totalNonConforme = 0;
        let totalTraite = 0;
        let totalEnAttente = 0;
        let totalAnnule = 0;
        let totalAutre = 0; 
        let ordresParClient = [];
        const totalClients = data.length;
        let totalOrdresGlobal = 0;
        data.forEach(client => {
            totalConforme += parseInt(client.nb_conformes) || 0;
            totalNonConforme += parseInt(client.nb_non_conformes) || 0;
            totalTraite += parseInt(client.nb_traite) || 0;
            totalEnAttente += parseInt(client.nb_en_attente) || 0;
            totalAnnule += parseInt(client.nb_annule) || 0;
            const clientOrdres = parseInt(client.total_ordres) || 0;
            totalOrdresGlobal += clientOrdres;
            
            ordresParClient.push({
                client: "Client " + client.client_id,
                ordres: clientOrdres
            });
        });
        const totalOrdres = totalConforme + totalNonConforme;
        console.log("Totaux globaux:", {
            totalClients: totalClients,
            totalOrdres: totalOrdres,
            totalOrdresGlobal: totalOrdresGlobal,
            conforme: totalConforme,
            nonConforme: totalNonConforme,
            traite: totalTraite,
            enAttente: totalEnAttente,
            annule: totalAnnule
        });
        $("#statsSummary").html(`
            <div style="display: flex; flex-wrap: wrap; justify-content: space-around; margin-top: 20px; padding: 15px; background: #f0f7ff; border-radius: 8px;">
                <div style="margin: 5px 10px; text-align: center;">
                    <strong>Nombre de clients</strong><br>
                    <span style="font-size: 1.8em; font-weight: bold; color: #007acc;">${totalClients.toLocaleString()}</span>
                </div>
                <div style="margin: 5px 10px; text-align: center;">
                    <strong>Total ordres</strong><br>
                    <span style="font-size: 1.8em; font-weight: bold; color: #007acc;">${totalOrdres.toLocaleString()}</span>
                </div>
                <div style="margin: 5px 10px; text-align: center;">
                    <strong>Conformes</strong><br>
                    <span style="font-size: 1.4em; color:#4caf50; font-weight: bold;">${totalConforme.toLocaleString()}</span>
                </div>
                <div style="margin: 5px 10px; text-align: center;">
                    <strong>Non conformes</strong><br>
                    <span style="font-size: 1.4em; color:#f44336; font-weight: bold;">${totalNonConforme.toLocaleString()}</span>
                </div>
                <div style="margin: 5px 10px; text-align: center;">
                    <strong>Taux conformité</strong><br>
                    <span style="font-size: 1.4em; font-weight: bold;">${((totalConforme / totalOrdres) * 100).toFixed(1)}%</span>
                </div>
            </div>
        `);
        $("#chartConformite").dxPieChart({
            dataSource: [
                { type: "Conforme", count: totalConforme },
                { type: "Non Conforme", count: totalNonConforme }
            ],
            series: [{
                argumentField: "type",
                valueField: "count",
                label: {
                    visible: true,
                    format: { type: "fixedPoint", precision: 0 },
                    font: { size: 14, weight: 600 },
                    backgroundColor: "rgba(255,255,255,0.8)",
                    connector: { visible: true, width: 1 }
                }
            }],
            title: {
                text: "Conformité globale des ordres",
                font: { size: 16, weight: 600 }
            },
            legend: { 
                visible: true,
                position: "bottom",
                font: { size: 14 }
            },
            height: 350,
            tooltip: { 
                enabled: true,
                format: { type: "fixedPoint", precision: 0 },
                customizeText: function(pointInfo) {
                    return pointInfo.argumentText + ": " + pointInfo.valueText + " ordres";
                }
            },
            palette: ["#4caf50", "#f44336"]
        });
        const statutData = [
            { statut: "Traité", count: totalTraite, color: "#2196f3" },
            { statut: "En attente", count: totalEnAttente, color: "#ff9800" },
            { statut: "Annulé", count: totalAnnule, color: "#9c27b0" }
        ].filter(item => item.count > 0);
        $("#chartStatut").dxChart({
            dataSource: statutData,
            series: [{
                argumentField: "statut",
                valueField: "count",
                type: "bar",
                color: function(point) {
                    return point.data.color;
                },
                label: { 
                    visible: true,
                    format: { type: "fixedPoint", precision: 0 },
                    font: { size: 12, weight: 600 },
                    backgroundColor: "rgba(255,255,255,0.8)",
                    position: "outside"
                }
            }],
            title: {
                text: "Statut global des ordres",
                font: { size: 16, weight: 600 }
            },
            valueAxis: {
                title: { text: "Nombre d'ordres" },
                label: { format: { type: "largeNumber" } }
            },
            argumentAxis: {
                title: { text: "Statut" }
            },
            legend: { visible: false },
            height: 300,
            tooltip: { 
                enabled: true,
                format: { type: "fixedPoint", precision: 0 }
            }
        });
        const topClients = ordresParClient
            .sort((a, b) => b.ordres - a.ordres)
            .slice(0, 10);
        $("#chartOrdresParClient").dxChart({
            dataSource: topClients,
            series: [{
                argumentField: "client",
                valueField: "ordres",
                type: "bar",
                color: "#007acc",
                label: { 
                    visible: true,
                    format: { type: "fixedPoint", precision: 0 },
                    font: { size: 10 },
                    position: "outside"
                }
            }],
            title: {
                text: "Top 10 clients - Nombre d'ordres",
                font: { size: 16, weight: 600 }
            },
            valueAxis: {
                title: { text: "Nombre d'ordres" },
                label: { format: { type: "largeNumber" } }
            },
            argumentAxis: {
                label: { 
                    rotationAngle: -30,
                    overlappingBehavior: "rotate"
                }
            },
            legend: { visible: false },
            height: 350,
            tooltip: { 
                enabled: true,
                format: { type: "fixedPoint", precision: 0 }
            }
        }); 
    }).fail(function(error) {
        console.error("Erreur de chargement des données:", error);
        $("#charts-global").prepend('<div style="color:red; padding:15px; background:#ffeeee; border-radius:5px; margin-bottom:15px;">❌ Erreur de chargement des données. Vérifiez la connexion à l\'API.</div>');
    });
    $("#gridContainer").dxDataGrid({
        dataSource: "../back_end/api.php",
        keyExpr: "client_id",
        showBorders: true,
        columnAutoWidth: true,
        wordWrapEnabled: true,
        height: 600,
        width: "100%",
        paging: { pageSize: 10 },
        pager: { 
            showPageSizeSelector: true, 
            allowedPageSizes: [5, 10, 20, 50, 100], 
            showInfo: true,
            showNavigationButtons: true
        },
        filterRow: { visible: true },
        searchPanel: { visible: true, width: 250, placeholder: "Rechercher un client..." },
        sorting: { mode: "multiple" },
        grouping: { autoExpandAll: true },
        export: { enabled: true, fileName: "statistiques_clients" },
        onContentReady: function(e) {
            $(".dx-datagrid .dx-datagrid-headers").css("backgroundColor", "#e3f2fd");
        },
        columns: [
            { 
                dataField: "client_id", 
                caption: "ID Client", 
                width: 100,
                alignment: "center",
                cssClass: "client-id-cell"
            },
            { 
                dataField: "total_ordres", 
                caption: "Total Ordres", 
                width: 130,
                alignment: "center",
                format: { type: "fixedPoint", precision: 0 },
                cssClass: "total-cell"
            },
            { 
                dataField: "moyenne_duree", 
                caption: "Durée Moy. (jours)", 
                width: 150,
                alignment: "center",
                format: { type: "fixedPoint", precision: 1 }
            },
            { 
                dataField: "nb_conformes", 
                caption: "Conformes", 
                width: 120,
                alignment: "center",
                format: { type: "fixedPoint", precision: 0 }
            },
            { 
                dataField: "nb_non_conformes", 
                caption: "Non Conformes", 
                width: 130,
                alignment: "center",
                format: { type: "fixedPoint", precision: 0 }
            },
            { 
                dataField: "nb_traite", 
                caption: "Traités", 
                width: 100,
                alignment: "center",
                format: { type: "fixedPoint", precision: 0 },
                visible: false
            },
            { 
                dataField: "nb_en_attente", 
                caption: "En attente", 
                width: 100,
                alignment: "center",
                format: { type: "fixedPoint", precision: 0 },
                visible: false
            },
            { 
                dataField: "nb_annule", 
                caption: "Annulés", 
                width: 100,
                alignment: "center",
                format: { type: "fixedPoint", precision: 0 },
                visible: false
            }
        ],
        masterDetail: {
            enabled: true,
            template: function(container, options) {
                const clientId = options.data.client_id;
                $("<div>")
                    .addClass("detail-grid")
                    .appendTo(container)
                    .dxDataGrid({
                        dataSource: "../back_end/api.php?id=" + clientId,
                        showBorders: true,
                        columnAutoWidth: true,
                        wordWrapEnabled: true,
                        height: 300,
                        paging: { pageSize: 5 },
                        pager: { 
                            showPageSizeSelector: true, 
                            allowedPageSizes: [5, 10, 20], 
                            showInfo: true 
                        },
                        filterRow: { visible: true },
                        sorting: { mode: "multiple" },
                        export: { enabled: true, fileName: "details_client_" + clientId },
                        columns: [
                            { 
                                dataField: "ordre_cle", 
                                caption: "N° Ordre", 
                                width: 120,
                                alignment: "center"
                            },
                            { 
                                dataField: "analyse_nom", 
                                caption: "Analyse", 
                                width: 200 
                            },
                            { 
                                dataField: "date_demande", 
                                caption: "Date demande", 
                                dataType: "date", 
                                width: 130,
                                format: "dd/MM/yyyy",
                                alignment: "center"
                            },
                            { 
                                dataField: "duree_analyse_jours", 
                                caption: "Durée (jours)", 
                                width: 120,
                                format: { type: "fixedPoint", precision: 1 },
                                alignment: "center"
                            },
                            { 
                                dataField: "statut", 
                                caption: "Statut", 
                                width: 120,
                                alignment: "center",
                                cellTemplate: function(container, options) {
                                    const statut = options.value || "";
                                    let color = "#333";
                                    if (statut === "Traité" || statut === "Terminé") color = "#2196f3";
                                    else if (statut === "En attente" || statut === "En cours" || statut === "Non évalué") color = "#ff9800";
                                    else if (statut === "Annulé") color = "#9c27b0";
                                    
                                    $("<span>")
                                        .text(statut)
                                        .css({ 
                                            color: color, 
                                            fontWeight: "bold",
                                            padding: "3px 8px",
                                            borderRadius: "12px",
                                            backgroundColor: color + "20",
                                            display: "inline-block"
                                        })
                                        .appendTo(container);
                                }
                            },
                            { 
                                dataField: "conformite_libelle", 
                                caption: "Conformité", 
                                width: 120,
                                alignment: "center",
                                cellTemplate: function(container, options) {
                                    const val = options.value || "";
                                    const color = val === "Non Conforme" || val === "Non conforme" ? "#f44336" : "#4caf50";
                                    
                                    $("<span>")
                                        .text(val)
                                        .css({ 
                                            color: color, 
                                            fontWeight: "bold",
                                            padding: "3px 8px",
                                            borderRadius: "12px",
                                            backgroundColor: color + "20",
                                            display: "inline-block"
                                        })
                                        .appendTo(container);
                                }
                            }
                        ]
                    });
            }
        }
    });
});




