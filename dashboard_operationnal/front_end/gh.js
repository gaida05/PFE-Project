function genCertificats(idDA) {
		urlToCall = "?sole=Y&app=guifp&action=genrapport&id="+idDA;			

	   	$.ajax({
            url: urlToCall,
            type: 'POST',
            async: false,
            success: function (data) {
	            DevExpress.ui.notify({message: "Rapports générés"}, "success", 3000);
            },error: function(data) {
                console.log(data);
            },
            cache: false,
            contentType: false,
            processData: false
        });
        /*
        urlToCall = "?sole=Y&app=guifp&action=genrapport&id="+idDA+"&tempo=0";			

	   	$.ajax({
            url: urlToCall,
            type: 'POST',
            async: false,
            success: function (data) {
	            DevExpress.ui.notify({message: "Rapports générés"}, "success", 3000);
            },error: function(data) {
                console.log(data);
            },
            cache: false,
            contentType: false,
            processData: false
        });
        */
}

function closePrint () {
	document.body.removeChild(this.__container__);
}

function setPrint () {
  this.contentWindow.__container__ = this;
  this.contentWindow.onbeforeunload = closePrint;
  this.contentWindow.onafterprint = closePrint;
  this.contentWindow.focus(); // Required for IE
  this.contentWindow.print();
}

function printPage (idToPrint) {
  var sURL = 'http://localhost/qlabo_dev/?sole=Y&&app=FDL&action=FDL_CARD&id='+idToPrint;
  var oHiddFrame = document.createElement("iframe");

  var locationHref = window.location.href;
  // gave http://localhost/qlabo_dev/?sole=Y&app=exploitation&action=ql_da&id=4611
  var rootPath = locationHref.split('?');
  var rootPath=rootPath[0] + '?sole=Y&&app=FDL&action=FDL_CARD&id='+idToPrint;

  oHiddFrame.onload = setPrint;
  oHiddFrame.style.visibility = "hidden";
  oHiddFrame.style.position = "fixed";
  oHiddFrame.style.right = "0";
  oHiddFrame.style.bottom = "0";
  oHiddFrame.src = rootPath;
  document.body.appendChild(oHiddFrame);
}

function genPdfDocument(idToPrint) {
		printPage(idToPrint);
		//--
		/*
		//%S%app=da&action=PRINTME&id=%I%
		urlToCall = "?sole=Y&app=da&action=printme&id="+idToPrint;			

	   	$.ajax({
            url: urlToCall,
            type: 'POST',
            async: false,
            success: function (data) {
	            DevExpress.ui.notify({message: "Fichier généré"}, "success", 3000);
            },error: function(data) {
                console.log(data);
            },
            cache: false,
            contentType: false,
            processData: false
        });
        */
        //---
}

function makeAjaxCall(operation, idToSet) {

		//%S%app=FDL&action=GENOA&id=%I%
		var urlToCall = "?sole=Y&app="+nomModule+"&action=setdata_ql_da&opp="+operation+"&id="+idToSet;
		if (operation == 'oa') {
			urlToCall = "?sole=Y&app=FDL&action=GENOA&id="+idToSet;			
		}

	   	$.ajax({
            url: urlToCall,
            type: 'POST',
            async: false,
            success: function (data) {
	            DevExpress.ui.notify({message: "Enregistrer avec success"}, "success", 3000);
            },error: function(data) {
                console.log(data);
            },
            cache: false,
            contentType: false,
            processData: false
        });
}



function spreadChoice(lotRef) {
	var substring=lotRef.split("*")[0];
	var grid_reception=$("#grid_reception").dxDataGrid('instance');
	var ds=grid_reception.option('dataSource');
	
	$.each(ds, function( index, value ) {
        if(value.ref_labo.includes(substring)){
        	value.conformite='oui';
        }
    });
	
	grid_reception.option('dataSource', ds);
    grid_reception.refresh();
}


function setDataForm(id){
    data=getData('getdata_ql_da',id);
    form=$("#form").dxForm('instance');
    $("#title").text(data[0].title);
    
    var disabled=($("#disabled").val() == "false")?false:true ;
    disableForm(disabled);
    
    //@sY
    form.updateData('type_da', data[0].type_da);
    
    form.updateData('first_laborantin', data[0].first_laborantin);
    form.updateData('second_laborantin', data[0].second_laborantin);
    
    form.updateData('caller_da', data[0].caller_da);
	//eof @sY
    form.updateData('id_da', data[0].id_da);
    form.updateData('naturedemandeur', data[0].naturedemandeur);
    form.updateData('id_client', data[0].id_client);
    form.updateData('client_externe', data[0].client_externe);
    form.updateData('datedemande', data[0].datedemande);
    
    dataGrid= (data[0].grid_destinataire !== null)?data[0].grid_destinataire:[];
    $("#grid_destinataire").dxDataGrid('instance').option('dataSource', dataGrid);
    $("#grid_destinataire").dxDataGrid('instance').refresh();
    
    form.updateData('ordre_prelevement', data[0].ordre_prelevement);
    form.updateData('type_prelevement', data[0].type_prelevement);
    
   	form.updateData('date_heure_prel', data[0].date_heure_prel);
    
    form.updateData('id_preleveur', data[0].id_preleveur);
    form.updateData('lieuprelevement', data[0].lieuprelevement);
    form.updateData('pointprelevement', data[0].pointprelevement);
    
    dataGrid= (data[0].grid_analyse !== null)?data[0].grid_analyse:[];
    $("#grid_analyse").dxDataGrid('instance').option('dataSource', dataGrid);
    $("#grid_analyse").dxDataGrid('instance').refresh();
    
    form.updateData('makereception', (data[0].makereception === 'oui')? true:false);
    
    dataGrid= (data[0].grid_reception !== null)?data[0].grid_reception:[];
    tabGridRecep=$("#grid_reception").dxDataGrid('instance');
    tabGridRecep.option('dataSource', dataGrid);
    tabGridRecep.refresh();
    
    
    form.updateData('makecb', (data[0].makecb === 'oui')? true:false);
    
    
    form.updateData('rapport_prov', data[0].rapport_prov);
    form.updateData('receptioniste', data[0].receptioniste);
    
    form.updateData('date_heure_verif', data[0].date_heure_verif);
    
    form.updateData('commentaire', data[0].commentaire);
    form.updateData('visalabo', data[0].visalabo);
	

	if(data[0].joint_plaquette !== null){
		$('#list_plaquette').html("<a href='"+data[0].joint_plaquette.piece+"' target='_blank'>"+data[0].joint_plaquette.name+"</a>");
	}
	
	if(data[0].joint_prov !== null){
		$('#list_prov').html("<a href='"+data[0].joint_prov.piece+"' target='_blank'>"+data[0].joint_prov.name+"</a>");
	}
	
	if(data[0].joint_finale !== null){
		$('#list_finale').html("<a href='"+data[0].joint_finale.piece+"' target='_blank'>"+data[0].joint_finale.name+"</a>");
	}
	var f ='';
	if(data[0].joint_fichier.length !== 0){
	$.each(data[0].joint_fichier, function( index, value ) {
            f+="<a href='"+value.piece+"' target='_blank'>"+value.name+"</a><BR>";
        });
	}
	$('#list_fichier').html(f);
	
	//$("#form").dxForm("instance").itemOption("TabTitleHere", "disabled", true); 
	
	var idclient_Editor=form.getEditor('id_client');
	var client_externe_Editor=form.getEditor('client_externe');
	if(data[0].naturedemandeur=='Interne'){
    	idclient_Editor.option('disabled',false);
    	client_externe_Editor.option('disabled',true);
    	client_externe_Editor.option('value','');
	}else{
		idclient_Editor.option('disabled',true);
		idclient_Editor.option('value','');
    	client_externe_Editor.option('disabled',false);
    	
	}	
	
}

$( document ).ready(function() {
	
	var id=$("#id").val();
	console.log(id);
	var opp='edit';
	var loadPanel = $("#loadpanel").dxLoadPanel({
        visible: false,
        showIndicator: true,
        showPane: true,
        shading: true,
        closeOnOutsideClick: false
    }).dxLoadPanel("instance");
    
    /* load Data*/
    var service=getData('getdata_service','-1','-1','configuration');
    var userList=getData('getdata_iuser','-1','-1','configuration');
    var prelList=getData('getdata_iuser', '-1', {'type':'prel'},'configuration');
    var typeList=getData('getdata_typo', '-1', '-1');
    var availCallerDA=getData('getdata_availda', '-1', '-1');
    var catEchantList=getData('getdata_ql_catechant','-1','-1');
    var typeEchantList=getData('getdata_ql_typechant','-1','-1');
    var analyseList=getData('getdata_ql_analyses','-1','-1');
    var packList=getData('getdata_ql_pack_mgt','-1','-1','configuration');
    var ordPrelList=getData('getdata_ql_ord_prel','-1','-1');
    var rapAnalyseList=getData('getdata_ql_rap_analyse','-1','-1');
    /*EoF load Data*/
    
    var formData={"id_da":""};
    var form= $("#form").dxForm({
        validationGroup: "validData",
        showValidationSummary: true,
        formData: formData,
        items: [{
                	itemType: "group",
                	items: [{
	                    template: function(data, itemElement) {
	                    	var ch='<div class="card"><div style="width:98%;" class="container">';
	                    	ch += '<table width="98%"><tr>';
	                    	ch += '<td width="38%"><div class="div_text" id="title">Création</div></td>';	
	                    	ch += '<td align="right" width="60%"><div id="genreceptbtn"></div>&nbsp;&nbsp;&nbsp;';
	                    	ch += '<div id="genrapport"></div>&nbsp;&nbsp;&nbsp;';
	                    	ch += '<div id="genoa"></div>&nbsp;&nbsp;&nbsp;';
	                    	ch += '</tr></table>';
	                    	ch +='</div></div>';

	                        itemElement.append(ch);
	                    }
                	}]
            	},
            	{
            itemType: "tabbed",
            tabPanelOptions: {
	        deferRendering: false,
	        onItemClick: function (e) {
	          //console.log(e);
	          //alert("onItemClick" + e.itemData.id);
	        },
	        onTitleClick: function (e) {
	          //console.log(e);
        		//alert("onTitleClick");
	        },
	      },
          tabs: [
          	{
                title: "Fiche signalétique",
                items: [{
                		itemType: "group",
	                	caption: "Type demande",
	                	colCount: 2,
	                    items: [{
	                    	colSpan: 2,
		        			label :{text:"Type demande"},
			                dataField: "type_da",
			                name:'type_da',
			                disabled:false,
			                editorType: "dxSelectBox",
			                    editorOptions: {
			                        items: typeList ,
			                        valueExpr: "ID",
			                        displayExpr: "designation",
			                        searchEnabled: true,
			                        showClearButton: true,
			                        onValueChanged: function (e) {	                		
	            						var newValue = e.value;
	            						if (newValue == 'Pour réception achat') {
	            							form.getEditor('caller_da').option('disabled', false);
	            							//console.log('TO ENNABLE');
	            						} else {
	            							//console.log('TO DISABLE');
	            							form.getEditor('caller_da').option('disabled', true);
	            						}
	        						},
			                    }
		                },
		                /*{	
		                	
		        			label :{text:"Laborantin 1"},
			                dataField: "first_laborantin",
			                name:'first_laborantin',
			                disabled:false,
			                editorType: "dxSelectBox",
			                    editorOptions: {
			                        items: userList ,
			                        valueExpr: "ID",
			                        displayExpr: "designation",
			                        searchEnabled: true,
			                        showClearButton: true,  
			                    }
		                },{	
		                	
		        			label :{text:"Laborantin 2"},
			                dataField: "second_laborantin",
			                name:'second_laborantin',
			                disabled:false,
			                editorType: "dxSelectBox",
			                    editorOptions: {
			                        items: userList ,
			                        valueExpr: "ID",
			                        displayExpr: "designation",
			                        searchEnabled: true,
			                        showClearButton: true,  
			                    }
		                },
		                */
		                {	
		                	colSpan: 2,
		        			label :{text:"DA Référence"},
			                dataField: "caller_da",
			                name:'caller_da',
			                disabled:false,
			                visible :false,
			                editorType: "dxSelectBox",
			                    editorOptions: {
			                        items: availCallerDA ,
			                        valueExpr: "ID",
			                        displayExpr: "designation",
			                        searchEnabled: true,
			                        showClearButton: true,  
			                    }
		                },{	
		                	colSpan: 2,
		        			label :{text:"Numéro Demande"},
			                dataField: "id_da",
			                name:'id_da',
			                disabled:true,
		                },{	
		                	colSpan: 2,
		        			label :{text:"Nature du demandeur"},
			                dataField: "naturedemandeur",
			                name:'naturedemandeur',
			                visible :false,
			                editorType: "dxRadioGroup",
                			editorOptions: {
                    			dataSource: ['Interne','Externe'],
                    			layout: "horizontal"
                			}
		                },{	
		                	colSpan: 1,
		        			label :{text:"Réf. Client"},
			                dataField: "id_client",
			                name:'id_client',
			                disabled:false,
			                editorType: "dxSelectBox",
			                    editorOptions: {
			                        items: service ,
			                        valueExpr: "ID",
			                        displayExpr: "designation",
			                        searchEnabled: true,
			                        showClearButton: true,  
			                    },
			                //réference client requise
					        validationRules: [{
					          type: 'required',
					          message: 'La référence client est requise',
					        }]
		                },{	
		                	colSpan: 1,
		        			label :{text:" "},
			                dataField: "client_externe",
			                name:'client_externe',
		                },{	
		                	colSpan: 2,
		        			label :{text:"Date de la demande"},
			                dataField: "datedemande",
			                name:'datedemande',
			                disabled:false,
			                editorType: "dxDateBox",
				            editorOptions: { 
				            	type: "date",
				                value: null,
				                width: "100%",
				                displayFormat: "dd-MM-yyyy",
				            }
		                }]
                	},{
                		
	                    itemType: "group",
	                	caption: "Destinataire de l'analyse",
	                    items: [{
		                    template: function(data, itemElement) {
		                        itemElement.append($("<div>").attr("class","grid_to_desabled").attr("id", "grid_destinataire").dxDataGrid());
		                    }
		                }
	                	]
                		
                	
                	}]
	            },
	            {
	                title: "Prélèvements",
	                items: [{
		                    itemType: "group",
		                	caption: "Prélèvements",
		                    items: [
		                    	/*{
				                	label :{text:"Bon de prélèvement"},
				                    dataField: "ordre_prelevement",
				                    name:'ordre_prelevement',
				                    editorType: "dxSelectBox",
				                    editorOptions: {
				                        items:  ordPrelList,
				                        valueExpr: "ID",
				                        displayExpr: "designation",
				                        searchEnabled: true,
				                        showClearButton: true,  
				                    }
				                },
				                */{
				                	label :{text:"Nature du prélèvement"},
				                    dataField: "type_prelevement",
				                    name:'type_prelevement',
				                    editorType: "dxSelectBox",
				                    editorOptions: {
				                        items:  catEchantList,
				                        valueExpr: "ID",
				                        displayExpr: "designation",
				                        searchEnabled: true,
				                        showClearButton: true,  
				                    }
				                },{
				                	label :{text:"Date et heure du prélèvement"},
					                dataField: "date_heure_prel",
					                name:'date_heure_prel',
					                editorType: "dxDateBox",
						            editorOptions: { 
						            	type: "datetime",
						                value: null,
						                width: "100%",
						                displayFormat: "dd-MM-yyyy  hh:mm",
						            }
				                },{
				                	label :{text:"Préleveur"},
				                    dataField: "id_preleveur",
				                    name:'id_preleveur',
				                    editorType: "dxSelectBox",
				                    editorOptions: {
				                        items: prelList,
				                        valueExpr: "ID",
				                        displayExpr: "designation",
				                        searchEnabled: true,
				                        showClearButton: true,  
				                    }
				                },{	
			        			label :{text:"Lieu de prélèvement"},
				                dataField: "lieuprelevement",
				                name:'lieuprelevement',
			                },{	
			        			label :{text:"Point d'échantillonnage"},
				                dataField: "pointprelevement",
				                name:'pointprelevement',
			                }
			                	]
		                	}]},
		                	
	            {
	                title: "Analyses demandées",
	                items: [{
	                    itemType: "group",
	                    caption: "Analyse",
	                    items: [{
		                    template: function(data, itemElement) {
								itemElement.append($("<div>").attr("class","element_to_desabled").attr("id", "add_analyse").dxButton({
							        stylingMode: "contained",
							        text: "Ajouter des analyses",
							        type: "default",
							        onClick :function(e){
							        	showpopupAddAnalyse();
							        }
							    }));
		                    }
		                },{
		                    template: function(data, itemElement) {
		                        itemElement.append($("<div>").attr("class","grid_to_desabled").attr("id", "grid_analyse").dxDataGrid({
							        dataSource: [],
							        visible:true,
							        showBorders: true,
							        allowColumnResizing: true,
							        allowColumnReordering: true,
							        columnHidingEnabled: false,
							        columnResizingMode: "nextColumn",
							        columnAutoWidth: true,
							        showScrollbar:true,
							        wordWrapEnabled: false,
							        showColumnLines: true,
							        hoverStateEnabled: true,
							        showRowLines: true,
							        rowAlternationEnabled: true,
							        editing: {
							        allowDeleting: true,
							        useIcons: true,
							        //modifcation
							         mode: "row",
							            allowUpdating: true,
							            allowAdding: true,
							    	 },
							        paging: {
							            pageSize: 5
							        },
							        pager: {
							            showPageSizeSelector: true,
							            allowedPageSizes: [5, 10, 15]
							        },
							        columns: [
							            {
							                dataField: "type_ech",
							                caption: "Type échantillon",
							                lookup: {
								                dataSource:{
								                    store:catEchantList,
								                    paginate: true,
								                    pageSize: 10
								                },
								                searchEnabled: true,
								                showClearButton: true,
								                valueExpr: "ID",
								                displayExpr: "designation"
								            },
							            },{
						                    dataField: "prod_fini",
						                    caption: "Prod. Fini",
						                    lookup: {
								                dataSource:{
								                    store:typeEchantList,
								                    paginate: true,
								                    pageSize: 10
								                },
								                searchEnabled: true,
								                showClearButton: true,
								                valueExpr: "ID",
								                displayExpr: "designation"
								            },
						                },{
						                    dataField: "delais",
						                    caption: "Délais",
						                },{
						                    dataField: "idpack",
						                    caption: "Pack",
						                    lookup: {
								                dataSource:{
								                    store:packList,
								                    paginate: true,
								                    pageSize: 10
								                },
								                searchEnabled: true,
								                showClearButton: true,
								                valueExpr: "ID",
								                displayExpr: "designation"
								            },
						                },{
						                    dataField: "analyse",
						                    caption: "Analyses demandées",
						                    lookup: {
								                dataSource:{
								                    store:analyseList,
								                    paginate: true,
								                    pageSize: 10
								                },
								                searchEnabled: true,
								                showClearButton: true,
								                valueExpr: "ID",
								                displayExpr: "designation"
								            },
						                },{
						                    dataField: "ref_lot",
						                    caption: "Réf lot éch demandeur",
						                }
							        ]
							    }
	    					));
		                    }
		                
		                },{	
		                	label :{text:'Génération fiche réception'},
		                    dataField: "makereception",
		                    name:'makereception',
		                    editorType: "dxCheckBox"
		                    
		                }]
	                }]},
	            {
	                title: "Fiches réceptions",
	                items: [{
	                    itemType: "group",
	                    colCount: 2,
	                    items: [{	
	                    	colSpan: 2,
		                    template: function(data, itemElement) {
		                        itemElement.append($("<div>").attr("class","grid_to_desabled").attr("id", "grid_reception").dxDataGrid({
							        dataSource: [],
							        visible:true,
							        showBorders: true,
							        allowColumnResizing: true,
							        allowColumnReordering: true,
							        columnHidingEnabled: false,
							        columnResizingMode: "nextColumn",
							        columnAutoWidth: true,
							        showScrollbar:true,
							        //wordWrapEnabled: true, retour à la ligne de toutes les cellules
							        wordWrapEnabled: false,
							        showColumnLines: true,
							        hoverStateEnabled: true,
							        showRowLines: true,
							        rowAlternationEnabled: true,
							        paging: {
							            pageSize: 5
							        },
							        pager: {
							            showPageSizeSelector: true,
							            allowedPageSizes: [5, 10, 15]
							        },
							        editing: {
							            mode: "row",
							            allowUpdating: true,
							            allowAdding: false,
							            useIcons:true
							        },
							        columns: [
							            {
							                dataField: "ref_labo",
							                caption: "Réf. Laboratoire",
							                allowEditing:false,
							                fixed:true
							            },{
						                    dataField: "tit_analyse",
						                    caption: "Analyse",
						                    allowEditing:false,
						                },{
						                    dataField: "ref_client",
						                    caption: "Réf. lot client",
						                    allowEditing:false,
						                },{
						                    dataField: "tit_nat_ech",
						                    caption: "Nat. D'échantillon",
						                    allowEditing:false,
						                },{
						                    dataField: "tit_prod_fini",
						                    caption: "Produit fini",
						                    allowEditing:false,
						                },{
						                    dataField: "date_recep",
						                    caption: "Date réception",
						                    allowEditing:false,
						                },{
						                    dataField: "heure_recep",
						                    caption: "Heure réception",
						                    allowEditing:false,
						                },{
						                    dataField: "conformite",
						                    caption: "Conformité",
						                    allowEditing:true,
							                fixed:true,
							                fixedPosition: "right",
							                lookup: {
								                dataSource:[{'ID':'nf','designation':'N.F.'},
								                			{'ID':'oui','designation':'Oui'},
								                			{'ID':'non','designation':'Non'},
								                			{'ID':'dero','designation':'Dérogé'}],
								                valueExpr: "ID",
	                        					displayExpr: "designation",
								                searchEnabled: true,
								                showClearButton: true,
								            },
						                },{
						                    dataField: "tit_fiche_criteres",
						                    caption: "Fiche Critères",
						                    cellTemplate: function (container, options) {
								            	var url="?sole=Y&app=configuration&action=ql_critconf&id="+options.data.fiche_criteres+"&disabled=false";
							            		$("<div><a onclick=showpopupbyurl('"+url+"')>"+options.value+"</div>")
							                    	.appendTo(container);
							                },
						                    allowEditing:false,
						                    width: '15%' ,
							                fixed:true,
							                fixedPosition: "right"
						                },{
						                    dataField: "gendone",
						                    allowEditing:false,
						                    caption: "O.A. Généré",
							                fixed:true,
							                fixedPosition: "right"
						                }
							        ]
							        ,onRowUpdated: function(e) {
						        		if (e.data.conformite == 'oui') {
											spreadChoice(e.data.ref_labo);
										}
										
										if (e.data.conformite == 'non' || e.data.conformite == 'dero') {
											showpopupbyurl('?sole=Y&app=configuration&action=ql_critconf&id='+e.data.fiche_criteres+"&disabled=false");
										}
										
							        }
							    }
	    					));
		                    }
		                },{	
		                	label :{text:'Génération étiquette CB'},
		                    dataField: "makecb",
		                    name:'makecb',
		                    colSpan: 2,
		                    editorType: "dxCheckBox"
		                },{
		                    template: function(data, itemElement) {
								itemElement.append($("<div>").attr("class","element_to_desabled").attr("id", "plaquette").dxFileUploader({
							        multiple: false,
							        selectButtonText: "Ajouter",
							        labelText: "Plaquette CB"
							    }));
		                    }
		                },{
	                    	template: function(data, itemElement) {
	                        itemElement.append($("<div id='list_plaquette'></div>"));
	                    }
	                	}
	                ]
	                	
	                }]},
	            {
	                title: "Rapports et certificats",
	                items: [{
	                    itemType: "group",
	                    colCount: 2,
	                    items: [{	
		                	colSpan: 2,
		                	label :{text:"Provisoire"},
		                    dataField: "rapport_prov",
		                    name:'rapport_prov',
		                    editorType: "dxSelectBox",
		                    editorOptions: {
		                        items:  rapAnalyseList,
		                        valueExpr: "ID",
		                        displayExpr: "designation",
		                        searchEnabled: true,
		                        showClearButton: true,  
		                    }
		                },{
		                    template: function(data, itemElement) {
								itemElement.append($("<div>").attr("class","element_to_desabled").attr("id", "edition_prov").dxFileUploader({
							        multiple: false,
							        selectButtonText: "Ajouter",
							        labelText: "Edition provisoire"
							    }));
		                    }
		                },{
		                    template: function(data, itemElement) {
		                        itemElement.append($("<div id='list_prov'></div>"));
		                    }
		                },{
		                    template: function(data, itemElement) {
								itemElement.append($("<div>").attr("class","element_to_desabled").attr("id", "edition_finale").dxFileUploader({
							        multiple: false,
							        selectButtonText: "Ajouter",
							        labelText: "Edition finale"
							    }));
		                    }
		                },{
		                    template: function(data, itemElement) {
		                        itemElement.append($("<div id='list_finale'></div>"));
		                    }
		                }
	                ]
	                }]},
	            {
	                title: "Partie réservée au laboratoire",
	                items: [
	                	{
		                    itemType: "group",
		                	caption: "Partie réservée au laboratoire",
		                    items: [{
				                	label :{text:"Vérificateur"},
				                    dataField: "receptioniste",
				                    name:'receptioniste',
				                    editorType: "dxSelectBox",
				                    editorOptions: {
				                        items: userList,
				                        valueExpr: "ID",
				                        displayExpr: "designation",
				                        searchEnabled: true,
				                        showClearButton: true,  
				                    }
				                },{
				                	label :{text:"Date et heure"},
					                dataField: "date_heure_verif",
					                name:'date_heure_verif',
					                editorType: "dxDateBox",
						            editorOptions: { 
						            	type: "datetime",
						                width: "100%",
						                displayFormat: "dd-MM-yyyy  hh:mm",
						            }
				                },{	
			        			label :{text:"Commentaire"},
				                dataField: "commentaire",
				                name:'commentaire',
				                editorType: "dxTextArea"
			                },{	
			        			label :{text:"Visa laboratoire"},
				                dataField: "visalabo",
				                name:'visalabo',
				                editorType: "dxTextArea"
			                }
			                	]
		                		
		                	}
		                
	                	]},
	            {
	                title: "Doc. Attachés",
	                items: [
	                	{
	                    itemType: "group",
	                    colCount: 2,
	                    items: [
		                
		                {
		                    template: function(data, itemElement) {
								itemElement.append($("<div>").attr("class","element_to_desabled").attr("id", "fichier").dxFileUploader({
							        multiple: true,
							        selectButtonText: "Ajouter",
							        uploadMode: "useButtons",
							        labelText: "Fichier à joindre"
							    }));
		                    }
		                },
		                {
		                    template: function(data, itemElement) {
		                        itemElement.append($("<div id='list_fichier'></div>"));
		                    }
		                }
	                ]
	                	
	                }
	                	]
	            }
	            ]
        },{
            template: function(data, itemElement) {
                itemElement.append($("<div>").attr("class","element_to_desabled").attr("id", "btn_enregistrer").dxButton());
            }
        }],onFieldDataChanged: function (e){
        	var form=$('#form').dxForm('instance');
        	
            if(e.dataField=='makereception' && e.value != undefined){
            	if(e.value){
                	$("#add_analyse").dxButton('instance').option('visible',false);
        		   //modification here
                	$("#grid_analyse").dxDataGrid('instance').option('editing.allowAdding',false);
                	$("#grid_analyse").dxDataGrid('instance').option('editing.allowUpdating',false);
                	$("#grid_analyse").dxDataGrid('instance').option('editing.allowDeleting',false);
            	}else{
            		$("#add_analyse").dxButton('instance').option('visible',true);
            	}
            }
            
            if(e.dataField=='naturedemandeur' && e.value != undefined){
            	var idclient_Editor=form.getEditor('id_client');
            	var client_externe_Editor=form.getEditor('client_externe');
            	if(idclient_Editor != undefined){
	            	if(e.value=='Interne'){
				    	idclient_Editor.option('disabled',false);
				    	client_externe_Editor.option('disabled',true);
				    	client_externe_Editor.option('value','');
					}else{
						idclient_Editor.option('disabled',true);
						idclient_Editor.option('value','');
				    	client_externe_Editor.option('disabled',false);
				    	
					}		
            	}
            }
            /*
            if(e.dataField=='type_da' && e.value != undefined){
            	var type_Editor=form.getEditor('type_da');
            	if(type_Editor != undefined){
            		var flaborantin_Editor=form.getEditor('first_laborantin');
            		var slaborantin_Editor=form.getEditor('second_laborantin');
	            	if(e.value=='Pour contrôle interne'){
				    	flaborantin_Editor.option('disabled',false);
				    	slaborantin_Editor.option('disabled',false);
					}else{
						flaborantin_Editor.option('disabled',true);
				    	slaborantin_Editor.option('disabled',true);
						flaborantin_Editor.option('value','');
				    	slaborantin_Editor.option('value','');
				    	
					}		
            	}
            }
        	*/
        }
    }).dxForm("instance");


	// genreceptbtn.option('visible', true);
	var genreceptBtn=$("#genreceptbtn").dxButton({
        text: "Gén. réceptions",
        hint: "Modifier",
        type: "default",
        icon:"edit",
        visible:false,
        onClick: function() {
        	//CALL GEN RECEPT()
        	makeAjaxCall('reception', id);
        	setDataForm(id);
        	displayActivBttn();
        }
    }).dxButton('instance');
	
	var genRapport=$("#genrapport").dxButton({
        text: "Gén. rapports",
        hint: "Modifier",
        type: "default",
        icon:"edit",
        visible:false,
        onClick: function() {
        	//CALL GEN RECEPT()
        	genCertificats(id);
        	console.log(genCertificats(id));
        	setDataForm(id);
        }
    }).dxButton('instance');
 	
    var genOa=$("#genoa").dxButton({
        text: "Gén. Ord. analyses",
        hint: "Modifier",
        type: "default",
        icon:"edit",
        visible:false,
        onClick: function() {
        	makeAjaxCall('oa', id);
        	setDataForm(id);
        	displayActivBttn();
        }
    }).dxButton('instance');

	var grid_destinataire=$("#grid_destinataire").dxDataGrid({
        dataSource: [],
        visible:true,
        showBorders: true,
        allowColumnResizing: true,
        allowColumnReordering: true,
        columnHidingEnabled: false,
        columnResizingMode: "nextColumn",
        columnAutoWidth: true,
        showScrollbar:true,
        wordWrapEnabled: false,
        showColumnLines: true,
        hoverStateEnabled: true,
        showRowLines: true,
        rowAlternationEnabled: true,
        paging: {
            pageSize: 5
        },
        pager: {
            showPageSizeSelector: true,
            allowedPageSizes: [5, 10, 15]
        },
        editing: {
            mode: "row",
            allowUpdating: true,
            allowAdding: true,
            useIcons:true
        },
        columns: [
            {
	            dataField: "nature",
	            caption: "Nature du destinataire",
	            width: '60%',
	            lookup: {
	                dataSource:[{'ID':'interne','designation':'Interne'},{'ID':'externe','designation':'Externe'}],
	                valueExpr: "ID",
				    displayExpr: "designation",
	                searchEnabled: true,
	                showClearButton: true,
	            },
	            setCellValue: function(rowData, value) {
                    rowData.nature = value;
                    rowData.destinataire = null;
                },
	            validationRules: [{ type: "required" }]
	        },
			{
	            dataField: "destinataire",
	            caption: "Destinataire",
	            width: '60%',
	            lookup: {
	                
	                dataSource: function(options) {
                        return {
                            store: userList,
                            filter: options.data ? ["atags", "=", options.data.nature] : null
                        };
                    },
	                searchEnabled: true,
	                showClearButton: true,
	                valueExpr: "ID",
	                displayExpr: "designation"
	            },
	            
	            validationRules: [{ type: "required" }]
	        }
        ],
		  onRowUpdating: function(e) {
		  }
    });
    
    
    var btn_enregistrer =$("#btn_enregistrer").dxButton({
        validationGroup: "validData",
        stylingMode: "contained",
        text: "Enregistrer",
        type: "success",
        icon:"floppy",
        useSubmitBehavior: true,
    });


	
	if( (typeof id  !== "undefined")&& (id != -1)){
        setDataForm(id);
        opp='update';
    }
	
    $("#form-container-da").on("submit", function(e) {
    	console.log('inn')
        e.preventDefault();
        
        
        var msg='';
        var form=$("#form").dxForm("instance");
        
        var client=form.getEditor('id_client').option('value');
        //console.log(client)
    	if(client== null){
    		msg=' Vous devez choisir un client';
    	}
        /*
        var type_val=form.getEditor('type_da').option('value');
        if(type_val == "Pour contrôle interne"){
        	var flaborantin_val=form.getEditor('first_laborantin').option('value');
        	var slaborantin_val=form.getEditor('second_laborantin').option('value');
        	
        	if(flaborantin_val== null || slaborantin_val== null ){
        		msg=' Vous devez choisir deux laborantins';
        	}
        }
        */
        
        var dataToSend= new FormData();

		var files_plaquette = $("#plaquette").dxFileUploader("option", "value");
        for (var i = 0, len = files_plaquette.length; i < len; i++) {
            tit="plaquette" + i;
            dataToSend.append(tit,files_plaquette[i]);
        }
        
        var files_edition_prov = $("#edition_prov").dxFileUploader("option", "value");
        for (var i = 0, len = files_edition_prov.length; i < len; i++) {
            tit="prov" + i;
            dataToSend.append(tit,files_edition_prov[i]);
        }
        
        var files_edition_finale = $("#edition_finale").dxFileUploader("option", "value");
        for (var i = 0, len = files_edition_finale.length; i < len; i++) {
            tit="finale" + i;
            dataToSend.append(tit,files_edition_finale[i]);
        }

		
		var files_fichier = $("#fichier").dxFileUploader("option", "value");
        for (var i = 0, len = files_fichier.length; i < len; i++) {
            tit="fichier" + i;
            dataToSend.append(tit,files_fichier[i]);
        }
        
        dataToSend.append("gridData",JSON.stringify($("#grid_destinataire").dxDataGrid("instance").option("dataSource")));
        dataToSend.append("grid_analyse",JSON.stringify($("#grid_analyse").dxDataGrid("instance").option("dataSource")));
        dataToSend.append("grid_reception",JSON.stringify($("#grid_reception").dxDataGrid("instance").option("dataSource")));
		
		var formDataFromForm=form.option("formData");
		dataToSend.append("date_prel",formatDate(formDataFromForm.date_heure_prel));
		dataToSend.append("heure_prel",formatHeure(formDataFromForm.date_heure_prel));
		dataToSend.append("date_verif",formatDate(formDataFromForm.date_heure_verif));
		dataToSend.append("heure_verif",formatHeure(formDataFromForm.date_heure_verif));
		dataToSend.append("date_demande",formatDate(formDataFromForm.datedemande));
		
		
        $.each(formData, function( index, value ) {
            dataToSend.append(index,value);
        });
		
		if(msg != ''){
			 DevExpress.ui.notify({message: msg}, "error", 3000);
		}else{
	    	$.ajax({
	            url: "?sole=Y&app="+nomModule+"&action=setdata_ql_da&opp="+opp+"&id="+id,
	            type: 'POST',
	            data: dataToSend,
	            success: function (e) {
	            	if(!window.parent.location.search.includes("action") || window.parent.location.search.includes("entrypoint")){
		        		parent.updateList(1,'-1',$("#action").val());
		        		parent.$('#cancelbtn').dxButton('instance')._clickAction();
		        	}
		            DevExpress.ui.notify({message: "Enregistrer avec success"}, "success", 3000);
	            },error: function(data) {
	                console.log(data);
	            },
	            cache: false,
	            contentType: false,
	            processData: false
	        });
		}
    });
    
    
    var popupOptions = {
        width: "90%",
        height: "90%",
        showTitle: true,
        title: "Détail",
        visible: false,
        dragEnabled: true,
        resizeEnabled: true,
        closeOnOutsideClick: false,
        onHiding: function (e) {
        	$('#iframepopup').attr('src', null);
        	this.content().empty();
        },
        onHidden : function(e){
            this.option('height','90%');
    		this.option('width','90%');
        }
    };
    var popup = $("#popup").dxPopup(popupOptions).dxPopup("instance");
    
    var popupOptionsA = {
        width: "90%",
        height: "90%",
        showTitle: true,
        title: "Détail",
        visible: false,
        dragEnabled: true,
        resizeEnabled: true,
        closeOnOutsideClick: false,
        onHiding: function (e) {
        	$('#iframepopup').attr('src', null);
        	this.content().empty();
        },
        onHidden : function(e){
            this.option('height','90%');
    		this.option('width','90%');
        }
    };
    var popup_analyse = $("#popup_analyse").dxPopup(popupOptionsA).dxPopup("instance");

    function getActivBttn() {
		var id = $("#id").val();
		var parsedData = '';
		$.ajax({
            url: "?sole=Y&app=exploitation&action=getactions&id="+id,
            type: 'POST',
            async: false,
            success: function (result) {
            	parsedData = result;
            },error: function() {
                console.log('RIEN');
            },
            cache: false,
            contentType: false,
            processData: false
        });
		
		return parsedData;

	}

	function displayActivBttn() {
		var bttnToActivateRaw = getActivBttn();
		var bttnToActivate = JSON.parse(bttnToActivateRaw);

		if (bttnToActivate[0] == 'NONE') {
			$("#genreceptbtn").hide();
			$("#genrapport").hide();
			$("#genoa").hide();

		} else {
			bttnToActivate.forEach(function(item, index, array) {
				eval(item).option('visible', true);			
			});
		}
	}

    displayActivBttn();
    
});