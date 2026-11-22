<?php
	//addWarningMsg("before includes");
	include_once('FDL/Lib.Dir.php');
	include_once('FDL/Lib.Util.php');
	include_once("WHAT/Class.Layout.php");
	include_once("FDL/Class.Doc.php");
	// the pdf maker class
	//echo 'av' ;
		//addWarningMsg("before require_once");
 define('MYMEMORY_TRANSLATE_URL', 'https://api.mymemory.translated.net/get');
 define('MYMEMORY_CONTACT_EMAIL', '');
 require_once('tcpdf/config/lang/eng.php');
 require_once('tcpdf/tcpdf.php');
	// global stuff
	function logMe($toLog) {
		$fileName = '/var/www/html/qlaboctaadev/guifp/aa_genrapport.log';
		$handle = fopen($fileName, 'a');
		fwrite($handle, $toLog . "\n");
		fclose($handle);
	}
		//addWarningMsg("before getTitleRole");
	function getTitleRole($role){
		switch($role){
			case "resplabo":
				$val = "Vérifié par : ";
				break;
			case "dirlabo":
				$val = "Validé par : ";
				break;
		}
		
		return $val;
	}
		function e($value) {
	    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	}
	
	function naValue($value, $default = 'N/A') {
	    if (is_array($value)) {
	        return $default;
	    }
	    $value = trim((string)$value);
	    if ($value === '' || strtoupper($value) === 'NULL' || $value === '\N') {
	        return $default;
	    }
	    return $value;
	}
	
	function rawValue($value, $default = '') {
	    if (is_array($value)) {
	        return $default;
	    }
	    $value = trim((string)$value);
	    if ($value === '' || strtoupper($value) === 'NULL' || $value === '\N') {
	        return $default;
	    }
	    return $value;
	}
	
	function splitMultiValue($value) {
	    $value = trim((string)$value);
	    if ($value === '' || strtoupper($value) === 'NULL' || $value === '\N') {
	        return [];
	    }
	    $parts = preg_split("/\r\n|\n|\r/", $value);
	    $out = [];
	    foreach ($parts as $part) {
	        $part = trim((string)$part);
	        if ($part === '' || strtoupper($part) === 'NULL' || $part === '\N') {
	            continue;
	        }
	        $out[] = $part;
	    }
	    return $out;
	}
	
	function safeArrayValue(array $arr, $index, $default = '') {
	    return isset($arr[$index]) ? trim((string)$arr[$index]) : $default;
	}
	
	function formatDateFr($date) {
	    $date = trim((string)$date);
	    if ($date === '' || strtoupper($date) === 'NULL' || $date === '\N' || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
	        return 'N/A';
	    }
	    $ts = strtotime($date);
	    if ($ts === false) {
	        return $date;
	    }
	    return date('d/m/Y', $ts);
	}
	
	function formatDateFrOrEmpty($date) {
	    $date = trim((string)$date);
	    if ($date === '' || strtoupper($date) === 'NULL' || $date === '\N' || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
	        return '';
	    }
	    $ts = strtotime($date);
	    if ($ts === false) {
	        return $date;
	    }
	    return date('d/m/Y', $ts);
	}
	
	function cleanPdfText($text) {
	    $text = trim((string)$text);
	    $text = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $text);
	    $text = str_replace(["\r\n", "\r"], "\n", $text);
	    return $text;
	}
	
	function isRtl($lang) {
	    return $lang === 'ar';
	}
	
	function pdfFont($lang) {
	    return $lang === 'ar' ? 'freeserif' : 'times';
	}
	
	function pdfAlign($lang) {
	    return $lang === 'ar' ? 'R' : 'L';
	}
	
	function lrm() {
	    return "\u{200E}";
	}
	
	function rlm() {
	    return "\u{200F}";
	}
	
	function fixedMap() {
	    return [
	        'en' => [
	            "Tunis le :" => "Tunis, on:",
	            "RAPPORT D'ANALYSES" => "ANALYSIS REPORT",
	            "Réf B.O :" => "B.O Ref.:",
	            "Observations :" => "Observations:",
	            "Réf Dossier :" => "File Ref.:",
	            "Réf Labo :" => "Lab Ref.:",
	            "Nom du demandeur d'analyse :" => "Applicant name:",
	            "Origine :" => "Origin:",
	            "Provenance :" => "Source:",
	            "Producteur :" => "Producer:",
	            "Importateur :" => "Importer:",
	            "Nom du produit :" => "Product name:",
	            "T° du Produit à la réception :" => "Product temperature on receipt:",
	            "Date de production :" => "Production date:",
	            "N° Lot :" => "Lot No.:",
	            "N° du lot :" => "Lot No.:",
	            "Adresse :" => "Address:",
	            "Ech. Prélevé par :" => "Sampled by:",
	            "Date du Prélèvement :" => "Sampling date:",
	            "Date de réception :" => "Receipt date:",
	            "Date début analyse :" => "Analysis start date:",
	            "Date fin analyse :" => "Analysis end date:",
	            "D.L.C :" => "Expiry date:",
	            "Code produit donné par le demandeur :" => "Product code given by applicant:",
	            "Les responsables de l'essai" => "Test managers",
	            "Responsable technique des laboratoires" => "Technical laboratory manager",
	            "Analyses" => "Analyses",
	            "Références Méthodes" => "Method references",
	            "Résultats" => "Results",
	            "ANALYSE" => "ANALYSIS",
	            "Paramètres" => "Parameters",
	            "Critères de décision *" => "Decision criteria *",
	            "(stabilité)" => "(stability)",
	            "Critères de décision * (stabilité)" => "Decision criteria * (stability)",
	            "(Selon NF V08 408-1997)" => "(According to NF V08 408-1997)",
	            "Incubation à" => "Incubation at",
	            "Incubation" => "Incubation",
	            "T° ambiante (7J)" => "Room temp. (7D)",
	            "à 37°C/7J" => "at 37°C/7D",
	            "à 55 °C/7J" => "at 55°C/7D",
	            "Déclaration :" => "Statement:",
	            "pH avant incubation (phase solide) :" => "pH before incubation (solid phase):",
	            "Contrôle de la stabilité des produits appertisés et assimilés" => "Stability control of canned and similar products",
	            "Aucune donnée de stabilité disponible pour cette demande." => "No stability data available for this request.",
	            "N/A" => "N/A"
	        ],
	        'ar' => [
	            "Tunis le :" => "تونس في:",
	            "RAPPORT D'ANALYSES" => "تقرير التحاليل",
	            "Réf B.O :" => "مرجع ب.و:",
	            "Observations :" => "ملاحظات:",
	            "Réf Dossier :" => "مرجع الملف:",
	            "Réf Labo :" => "مرجع المخبر:",
	            "Nom du demandeur d'analyse :" => "اسم طالب التحليل:",
	            "Origine :" => "المنشأ:",
	            "Provenance :" => "المصدر:",
	            "Producteur :" => "المنتج:",
	            "Importateur :" => "المورّد:",
	            "Nom du produit :" => "اسم المنتج:",
	            "T° du Produit à la réception :" => "درجة حرارة المنتج عند الاستلام:",
	            "Date de production :" => "تاريخ الإنتاج:",
	            "N° Lot :" => "رقم الدفعة:",
	            "N° du lot :" => "رقم الدفعة:",
	            "Adresse :" => "العنوان:",
	            "Ech. Prélevé par :" => "أخذت العينة من طرف:",
	            "Date du Prélèvement :" => "تاريخ أخذ العينة:",
	            "Date de réception :" => "تاريخ الاستلام:",
	            "Date début analyse :" => "تاريخ بداية التحليل:",
	            "Date fin analyse :" => "تاريخ نهاية التحليل:",
	            "D.L.C :" => "تاريخ انتهاء الصلاحية:",
	            "Code produit donné par le demandeur :" => "رمز المنتج المقدم من طالب التحليل:",
	            "Les responsables de l'essai" => "المسؤولون عن الاختبار",
	            "Responsable technique des laboratoires" => "المسؤول التقني عن المخابر",
	            "Analyses" => "التحاليل",
	            "Références Méthodes" => "مراجع الطرق",
	            "Résultats" => "النتائج",
	            "ANALYSE" => "التحليل",
	            "Paramètres" => "المعايير",
	            "Critères de décision *" => "معايير القرار *",
	            "(stabilité)" => "(الثبات)",
	            "Critères de décision * (stabilité)" => "معايير القرار * (الثبات)",
	            "(Selon NF V08 408-1997)" => "(حسب NF V08 408-1997)",
	            "Incubation à" => "الحضانة عند",
	            "Incubation" => "الحضانة",
	            "T° ambiante (7J)" => "درجة الغرفة (7 أيام)",
	            "à 37°C/7J" => "عند 37°/7 أيام",
	            "à 55 °C/7J" => "عند 55°/7 أيام",
	            "Déclaration :" => "التصريح:",
	            "pH avant incubation (phase solide) :" => "الرقم الهيدروجيني قبل الحضانة (الطور الصلب):",
	            "Contrôle de la stabilité des produits appertisés et assimilés" => "مراقبة ثبات المنتجات المعلبة وما يماثلها",
	            "Aucune donnée de stabilité disponible pour cette demande." => "لا توجد بيانات ثبات متاحة لهذا الطلب.",
	            "N/A" => "غير متاح"
	        ]
	    ];
	}
	
	function trFixed($text, $lang) {
	    $text = (string)$text;
	    if ($lang === 'fr' || trim($text) === '') {
	        return $text;
	    }
	    $map = fixedMap();
	    return $map[$lang][$text] ?? $text;
	}
	function rtlLabelWithLtrValue($label, $value, $lang) {
	    $label = trim((string)$label);
	    $value = trim((string)$value);
	    if ($lang !== 'ar') {
	        return trFixed($label, $lang) . ' ' . $value;
	    }
	    return rlm() . trFixed($label, $lang) . ' ' . lrm() . $value . lrm();
	}
	function boHeaderHtml($label, $value, $lang, $center = false) {
	    $label = trim((string)$label);
	    $value = trim((string)$value);
	
	    if ($lang !== 'ar') {
	        return '<span><b>' . e(trFixed($label, $lang)) . '</b> ' . e($value) . '</span>';
	    }
	
	    $align = $center ? 'center' : 'right';
	    return '<div dir="rtl" style="text-align:' . $align . ';"><b>' . e(trFixed($label, $lang)) . '</b> <span dir="ltr">' . e($value) . '</span></div>';
	}
	function ltTranslateOne($text, $lang, $format = 'text') {
	    $text = cleanPdfText($text);
	    if ($lang === 'fr' || $text === '') {
	        return $text;
	    }
	    $fixed = trFixed($text, $lang);
	    if ($fixed !== $text) {
	        return $fixed;
	    }
	    static $cache = [];
	    $cacheKey = $lang . '|' . md5($text);
	    if (isset($cache[$cacheKey])) {
	        return $cache[$cacheKey];
	    }
	
	    if (!function_exists('curl_init')) {
	        return $text;
	    }
	    $targetMap = ['en' => 'en', 'ar' => 'ar'];
	    if (!isset($targetMap[$lang])) {
	        return $text;
	    }
	
	    $query = [
	        'q' => $text,
	        'langpair' => 'fr|' . $targetMap[$lang]
	    ];
	    if (trim(MYMEMORY_CONTACT_EMAIL) !== '') {
	        $query['de'] = MYMEMORY_CONTACT_EMAIL;
	    }
	
	    $url = MYMEMORY_TRANSLATE_URL . '?' . http_build_query($query);
	    $ch = curl_init($url);
	    curl_setopt_array($ch, [
	        CURLOPT_RETURNTRANSFER => true,
	        CURLOPT_HTTPGET => true,
	        CURLOPT_TIMEOUT => 20,
	        CURLOPT_HTTPHEADER => ['Accept: application/json']
	    ]);
	    $response = curl_exec($ch);
	    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	    $curlErr = curl_error($ch);
	    curl_close($ch);
	
	    if ($response === false || $curlErr || $httpCode >= 400) {
	        return $text;
	    }
	
	    $json = json_decode($response, true);
	    $translated = $json['responseData']['translatedText'] ?? '';
	    if (!is_string($translated) || trim($translated) === '') {
	        return $text;
	    }
	
	    $cache[$cacheKey] = $translated;
	    return $translated;
	}
	function trText($text, $lang) {
	    return ltTranslateOne($text, $lang, 'text');
	}
	function trCell($text, $lang) {
	    return ltTranslateOne($text, $lang, 'text');
	}
	function formatNotes($text, $lang = 'fr') {
	    $text = cleanPdfText($text);
	    if ($text === '') {
	        return '';
	    }
	    if ($lang !== 'fr') {
	        $text = trText($text, $lang);
	    }
	
	    $lines = explode("\n", $text);
	    $formatted = '';
	    $i = 1;
	    foreach ($lines as $line) {
	        $line = trim($line);
	        if ($line === '') continue;
	        if ($i === 1) {
	            $formatted .= $line . "\n";
	        } elseif ($i === 2) {
	            $formatted .= "(**) " . $line . "\n";
	        } else {
	            $formatted .= "- " . $line . "\n";
	        }
	        $i++;
	    }
	    return trim($formatted);
	}
	
	function defaultObservations($lang) {
	    $fr = "Ce rapport ne concerne que l'objet soumis à l'essai pour les différents paramètres testés.\n"
	        . "Les résultats s'appliquent à l'échantillon tel qu'il a été reçu.\n"
	        . "Ce document est la propriété du laboratoire, il ne doit en aucun cas être reproduit sans l'autorisation écrite du propriétaire.";
	    return $lang === 'fr' ? $fr : trText($fr, $lang);
	}
	
	function getTitleFromId($dbaccess, $idToUse) {
	    $idToUse = rawValue($idToUse);
	    if ($idToUse === '') {
	        return '';
	    }
	    $doc = new_Doc($dbaccess, $idToUse, true);
	    if ($doc && $doc->isAlive()) {
	        return trim((string)$doc->title);
	    }
	    return $idToUse;
	}
	function docExists($dbaccess, $id) {
	    $id = rawValue($id);
	    if ($id === '') {
	        return false;
	    }
	    $doc = new_Doc($dbaccess, $id, true);
	    return ($doc && $doc->isAlive());
	}
	function getFieldOrTitle($dbaccess, $value, $fallback = 'N/A') {
	    $value = rawValue($value);
	    if ($value === '') {
	        return $fallback;
	    }
	    if (docExists($dbaccess, $value)) {
	        $title = getTitleFromId($dbaccess, $value);
	        return naValue($title, $fallback);
	    }
	    return naValue($value, $fallback);
	}
	
	function getAnalysisMeta($dbaccess, $analysisRef) {
	    static $cache = [];
	    $analysisRef = rawValue($analysisRef);
	    if ($analysisRef === '') {
	        return [
	            'label' => 'N/A',
	            'method' => 'N/A',
	            'unit' => 'N/A',
	            'is_accredited' => 0,
	            'guide' => '',
	            'guide_expression' => ''
	        ];
	    }
	    if (isset($cache[$analysisRef])) {
	        return $cache[$analysisRef];
	    }
	
	    $label = $analysisRef;
	    $method = 'N/A';
	    $unit = 'N/A';
	    $isAccredited = 0;
	    $guide = '';
	    $guideExpression = '';
	
	    if (docExists($dbaccess, $analysisRef)) {
	        $doc = new_Doc($dbaccess, $analysisRef, true);
	        $label = naValue($doc->getValue('libelle_analyse'), naValue($doc->title, $analysisRef));
	        $methodRef = rawValue($doc->getValue('methodeanalyse'));
	        $method = $methodRef !== '' ? getFieldOrTitle($dbaccess, $methodRef, 'N/A') : 'N/A';
	        $unit = naValue($doc->getValue('unite_result'), 'N/A');
	        $isAccredited = (strtolower(rawValue($doc->getValue('accredite'))) === 'oui') ? 1 : 0;
	        $guide = rawValue($doc->getValue('valguide'));
	        $guideExpression = rawValue($doc->getValue('val_guide_expression'));
	    }
	    $cache[$analysisRef] = [
	        'label' => $label,
	        'method' => $method,
	        'unit' => $unit,
	        'is_accredited' => $isAccredited,
	        'guide' => $guide,
	        'guide_expression' => $guideExpression
	    ];
	    return $cache[$analysisRef];
	}
	function resolveClientName($dbaccess, $docDA) {
	    $nature = rawValue($docDA->getValue('naturedemandeur'));
	    if (strcasecmp($nature, 'Externe') === 0) {
	        return naValue($docDA->getValue('client_externe'));
	    }
	
	    $libClient = rawValue($docDA->getValue('lib_client'));
	    if ($libClient !== '') {
	        return $libClient;
	    }
	
	    $idClient = rawValue($docDA->getValue('id_client'));
	    if ($idClient !== '') {
	        return getFieldOrTitle($dbaccess, $idClient, 'N/A');
	    }
	
	    return 'N/A';
	}
	function buildAddressFromDA($docDA) {
	    $parts = [];
	    foreach (['adresse'] as $field) {
	        $v = rawValue($docDA->getValue($field));
	        if ($v !== '') {
	            $parts[] = $v;
	        }
	    }
	    return count($parts) ? implode(', ', $parts) : 'N/A';
	}
	function buildRapportArray($dbaccess, $docDA, $docReport, $layout = 'format_1') {
	    $clientName = resolveClientName($dbaccess, $docDA);
	    $prelevePar = $clientName;
	    $commentDA = rawValue($docDA->getValue('commentaire'));
	    $commentReport = rawValue($docReport->getValue('ra_fr_commentaire'));
	    $observation = $commentReport !== '' ? $commentReport : ($commentDA !== '' ? $commentDA : 'N/A');
	    $firstLab = getFieldOrTitle($dbaccess, $docDA->getValue('first_laborantin'), 'N/A');
	    $secondLab = getFieldOrTitle($dbaccess, $docDA->getValue('second_laborantin'), 'N/A');
	    return [
	        'id_rapport' => naValue($docReport->initid ?: $docReport->id, 'N/A'),
	        'demande_id' => naValue($docDA->initid ?: $docDA->id, 'N/A'),
	        'ref_demande' => naValue($docDA->getValue('id_da'), naValue($docDA->title, 'N/A')),
	        'ref_dossier' => 'N/A',
	        'ref_bo' => 'N/A',
	        'client_name' => $clientName,
	        'adresse' => buildAddressFromDA($docDA),
	        'ville' => 'N/A',
	        'pays' => 'N/A',
	        'telephone' => naValue($docDA->getValue('tel')),
	        'email' => naValue($docDA->getValue('mail')),
	        'origine_provenance' => 'N/A',
	        'producteur' => 'N/A',
	        'importateur' => 'N/A',
	        'preleve_par' => $prelevePar,
	        'date_prelevement' => rawValue($docDA->getValue('dateprelevement')),
	        'date_reception' => rawValue($docDA->getValue('daterecption')),
	        'date_debut_analyse' => rawValue($docReport->getValue('debanalyse')),
	        'date_fin_analyse' => rawValue($docReport->getValue('finanalyse')),
	        'responsable_essai' => $firstLab,
	        'co_responsable_essai' => $secondLab,
	        'responsable_technique' => $firstLab,
	        'report_layout' => $layout,
	        'tunis_date' => date('Y-m-d'),
	        'duplicate_label' => '',
	        'observation' => $observation,
	        'note_bas_page' => naValue($docReport->getValue('ra_fr_commentaire'), ''),
	        'declaration_text' => 'N/A'
	    ];
	}
	function buildIdentityRows($dbaccess, $docDA) {
	    $typeRows = splitMultiValue($docDA->getValue('typeechantillon'));
	    $sampleRows = splitMultiValue($docDA->getValue('idechantillon'));
	    $analysisRows = splitMultiValue($docDA->getValue('recept_idanalyse'));
	    $refLaboRows = splitMultiValue($docDA->getValue('reflabo'));
	    $refLotRows = splitMultiValue($docDA->getValue('reflot_deux'));
	    $dateRecRows = splitMultiValue($docDA->getValue('daterecption'));
	    $timeRecRows = splitMultiValue($docDA->getValue('heureception'));
	    $count = max(
	        count($typeRows), count($sampleRows), count($analysisRows),
	        count($refLaboRows), count($refLotRows), count($dateRecRows), count($timeRecRows)
	    );
	    $rows = [];
	    for ($i = 0; $i < $count; $i++) {
	        $rows[] = [
	            'typeechantillon' => getFieldOrTitle($dbaccess, safeArrayValue($typeRows, $i), 'N/A'),
	            'idechantillon' => getFieldOrTitle($dbaccess, safeArrayValue($sampleRows, $i), 'N/A'),
	            'analyse_dem' => getAnalysisMeta($dbaccess, safeArrayValue($analysisRows, $i))['label'],
	            'ref_labo' => naValue(safeArrayValue($refLaboRows, $i)),
	            'numero_lot' => naValue(safeArrayValue($refLotRows, $i)),
	            'date_reception' => formatDateFr(safeArrayValue($dateRecRows, $i)),
	            'heure_reception' => naValue(safeArrayValue($timeRecRows, $i))
	        ];
	    }
	    return $rows;
	}
	
	function buildSampleArray($dbaccess, array $identityRows) {
	    $first = isset($identityRows[0]) ? $identityRows[0] : [];
	    return [
	        'ref_labo' => isset($first['ref_labo']) ? $first['ref_labo'] : 'N/A',
	        'nom_produit' => isset($first['idechantillon']) ? $first['idechantillon'] : 'N/A',
	        'temperature_reception' => 'N/A',
	        'date_production' => '',
	        'numero_lot' => isset($first['numero_lot']) ? $first['numero_lot'] : 'N/A',
	        'dlc' => '',
	        'code_produit_demandeur' => 'N/A',
	        'nom_commercial' => 'N/A'
	    ];
	}
	function buildResultRows($dbaccess, $docReport) {
	    $sampleRows = splitMultiValue($docReport->getValue('ra_echantillon'));
	    $productRows = splitMultiValue($docReport->getValue('ra_produitfini'));
	    $analysisRows = splitMultiValue($docReport->getValue('ra_analyse'));
	    $refLotRows = splitMultiValue($docReport->getValue('ra_reflot'));
	    $refLaboRows = splitMultiValue($docReport->getValue('ra_reflabo'));
	    $resultRows = splitMultiValue($docReport->getValue('ra_resultat'));
	    $operatorRows = splitMultiValue($docReport->getValue('ra_manipulateur'));
	    $unitRows = splitMultiValue($docReport->getValue('ra_unite_result'));
	    $guideRows = splitMultiValue($docReport->getValue('ra_vg'));
	    $guideExpRows = splitMultiValue($docReport->getValue('ra_vg_exp'));
	    $debRows = splitMultiValue($docReport->getValue('debanalyse'));
	    $finRows = splitMultiValue($docReport->getValue('finanalyse'));
	    $count = max(
	        count($sampleRows), count($productRows), count($analysisRows), count($refLotRows),
	        count($refLaboRows), count($resultRows), count($operatorRows), count($unitRows),
	        count($guideRows), count($guideExpRows), count($debRows), count($finRows)
	    );
	    $rows = [];
	    for ($i = 0; $i < $count; $i++) {
	        $analysisRef = safeArrayValue($analysisRows, $i);
	        $meta = getAnalysisMeta($dbaccess, $analysisRef);
	        $operator = getFieldOrTitle($dbaccess, safeArrayValue($operatorRows, $i), safeArrayValue($operatorRows, $i, 'N/A'));
	        $sampleTitle = getFieldOrTitle($dbaccess, safeArrayValue($sampleRows, $i), safeArrayValue($sampleRows, $i, 'N/A'));
	        $productTitle = getFieldOrTitle($dbaccess, safeArrayValue($productRows, $i), safeArrayValue($productRows, $i, 'N/A'));
	        $rows[] = [
	            'echantillon' => $sampleTitle,
	            'produitfini' => $productTitle,
	            'analyse' => naValue($meta['label']),
	            'methode' => naValue($meta['method']),
	            'resultat' => naValue(safeArrayValue($resultRows, $i)),
	            'unite' => naValue(safeArrayValue($unitRows, $i), naValue($meta['unit'])),
	            'ordre_affichage' => $i + 1,
	            'is_accredited' => $meta['is_accredited'],
	            'result_note' => 'N/A',
	            'report_section' => 'N/A',
	            'reflot' => naValue(safeArrayValue($refLotRows, $i)),
	            'reflabo' => naValue(safeArrayValue($refLaboRows, $i)),
	            'manipulateur' => naValue($operator),
	            'guide' => naValue(safeArrayValue($guideRows, $i), rawValue($meta['guide'])),
	            'guide_expression' => naValue(safeArrayValue($guideExpRows, $i), rawValue($meta['guide_expression'])),
	            'debanalyse' => formatDateFrOrEmpty(safeArrayValue($debRows, $i)),
	            'finanalyse' => formatDateFrOrEmpty(safeArrayValue($finRows, $i))
	        ];
	    }
	    return $rows;
	}
	function buildValidationArray($dbaccess, $docReport) {
	    $roles = splitMultiValue($docReport->getValue('ra_role'));
	    $validators = splitMultiValue($docReport->getValue('ra_validateur_r'));
	    $dates = splitMultiValue($docReport->getValue('ra_date_v'));
	    $count = max(count($roles), count($validators), count($dates));
	    $out = [];
	    for ($i = 0; $i < $count; $i++) {
	        $out[] = [
	            'role' => safeArrayValue($roles, $i),
	            'validator' => getFieldOrTitle($dbaccess, safeArrayValue($validators, $i), safeArrayValue($validators, $i, 'N/A')),
	            'date' => formatDateFr(safeArrayValue($dates, $i))
	        ];
	    }
	    return $out;
	}
	function buildAddress($rapport) {
	    $parts = [];
	    foreach (['adresse', 'ville', 'pays'] as $field) {
	        $val = rawValue(isset($rapport[$field]) ? $rapport[$field] : '');
	        if ($val !== '') {
	            $parts[] = $val;
	        }
	    }
	    return count($parts) ? implode(', ', $parts) : 'N/A';
	}
	class LaboReportPDF extends TCPDF {
	    public $layoutCode = 'format_1';
	    public $langCode = 'fr';
	    public $tunisDate = '';
	    public $boNumber = '';
	    public $duplicateLabel = '';
	    public function Header() {
	        $font = pdfFont($this->langCode);
	        $align = pdfAlign($this->langCode);
	        $this->SetTextColor(0, 0, 0);
	        if ($this->layoutCode === 'format_3') {
	            $this->SetFont($font, '', 10);
	            $this->SetXY(18, 12);
	            $this->Cell(0, 5, rtlLabelWithLtrValue('Tunis le :', $this->tunisDate, $this->langCode), 0, 1, $align);
	            $this->SetFont($font, 'BU', 18);
	            $this->SetXY(0, 22);
	            $this->Cell(210, 8, trFixed("RAPPORT D'ANALYSES", $this->langCode), 0, 1, 'C');
	            $this->SetFont($font, 'B', 11);
	            if ($this->langCode === 'ar') {
	                $this->writeHTMLCell(210, 6, 0, 31, boHeaderHtml('Réf B.O :', $this->boNumber, $this->langCode, true), 0, 1, false, true, 'C', true);
	            } else {
	                $this->SetXY(0, 31);
	                $this->Cell(210, 6, trFixed('Réf B.O :', $this->langCode) . ' ' . $this->boNumber, 0, 1, 'C');
	            }
	            return;
	        }
	        $this->SetFont($font, '', 10);
	        $this->SetXY(15, 10);
	        $this->Cell(0, 5, rtlLabelWithLtrValue('Tunis le :', $this->tunisDate, $this->langCode), 0, 1, $align);
	        if ($this->duplicateLabel !== '') {
	            $this->SetAlpha(0.08);
	            $this->StartTransform();
	            $this->Rotate(45, 120, 70);
	            $this->SetFont($font, 'B', 34);
	            $this->Text(98, 42, trFixed($this->duplicateLabel, $this->langCode));
	            $this->StopTransform();
	            $this->SetAlpha(1);
	        }
	        $this->SetFont($font, 'BU', 17);
	        $this->SetXY(0, 18);
	        $this->Cell(210, 7, trFixed("RAPPORT D'ANALYSES", $this->langCode), 0, 1, 'C');
	        $this->SetFont($font, 'B', 11);
	
	        if ($this->langCode === 'ar') {
	            $this->writeHTMLCell(210, 5, 0, 26, boHeaderHtml('Réf B.O :', $this->boNumber, $this->langCode, true), 0, 1, false, true, 'C', true);
	        } else {
	            $this->SetXY(0, 26);
	            $this->Cell(210, 5, trFixed('Réf B.O :', $this->langCode) . ' ' . $this->boNumber, 0, 1, 'C');
	        }
	    }
	    public function Footer() {
	        $this->SetY(-10);
	        $this->SetFont(pdfFont($this->langCode), '', 9);
	        $this->Cell(0, 4, $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'R');
	    }
	}
	function renderObservationsBlock($pdf, $rapport, $lang, $extraPrefix = '') {
	    $obs = cleanPdfText(isset($rapport['observation']) ? $rapport['observation'] : '');
	    if ($obs === '' || strtoupper($obs) === 'N/A') {
	        $obs = defaultObservations($lang);
	    } else {
	        $obs = trText($obs, $lang);
	    }
	    if ($extraPrefix !== '') {
	        $obs = trim($extraPrefix) . "\n" . $obs;
	    }
	    $pdf->Ln(2);
	    $pdf->SetFont(pdfFont($lang), 'U', $lang === 'ar' ? 8 : 7);
	    $pdf->Cell(0, 4, trFixed('Observations :', $lang), 0, 1, pdfAlign($lang));
	    $pdf->SetFont(pdfFont($lang), '', $lang === 'ar' ? 8.5 : 7);
	    $pdf->MultiCell(0, 3.2, $obs, 0, pdfAlign($lang), false, 1);
	}
	function drawInfoColumn($pdf, $x, $y, $columnW, array $lines, $lang) {
	    $currentY = $y;
	    foreach ($lines as $item) {
	        $label = isset($item[0]) ? trim((string)$item[0]) : '';
	        $value = isset($item[1]) ? trim((string)$item[1]) : '';
	        if ($value === '') {
	            $value = 'N/A';
	        }
	        if ($value !== '' && $lang !== 'fr') {
	            $value = trText($value, $lang);
	        }
	        if ($label !== '') {
	            if ($lang === 'ar') {
	                $text = '<div dir="rtl"><b>' . e(trFixed($label, $lang)) . '</b> <span dir="ltr">' . e($value) . '</span></div>';
	            } else {
	                $text = '<b>' . e(trFixed($label, $lang)) . '</b> ' . e($value);
	            }
	        } else {
	            if ($lang === 'ar') {
	                $text = '<div dir="rtl">' . e($value) . '</div>';
	            } else {
	                $text = e($value);
	            }
	        }
	        $pdf->SetXY($x, $currentY);
	        $pdf->SetFont(pdfFont($lang), '', 10);
	        $pdf->writeHTMLCell($columnW, 0, $x, $currentY, $text, 0, 1, false, true, pdfAlign($lang), true);
	        $currentY = $pdf->GetY();
	    }
	    return $currentY;
	}
	function topInfoTwoCols($pdf, $rapport, $echantillon, $layout = 'format_1', $lang = 'fr', $extraLeftLines = [], $extraRightLines = []) {
	    $leftX  = 18;
	    $rightX = 112;
	    $y      = ($layout === 'format_3') ? 42 : 38;
	    $leftW  = 86;
	    $rightW = 80;
	
	    $leftLines = [
	        ["Réf Dossier :", isset($rapport['ref_dossier']) ? $rapport['ref_dossier'] : 'N/A'],
	        ["Réf Labo :", isset($echantillon['ref_labo']) ? $echantillon['ref_labo'] : (isset($rapport['ref_demande']) ? $rapport['ref_demande'] : 'N/A')],
	        ["Nom du demandeur d'analyse :", isset($rapport['client_name']) ? $rapport['client_name'] : 'N/A'],
	        [$layout === 'format_3' ? "Provenance :" : "Origine :", isset($rapport['origine_provenance']) ? $rapport['origine_provenance'] : 'N/A'],
	        ["Producteur :", isset($rapport['producteur']) ? $rapport['producteur'] : 'N/A'],
	        ["Importateur :", isset($rapport['importateur']) ? $rapport['importateur'] : 'N/A'],
	        ["Nom du produit :", isset($echantillon['nom_produit']) ? $echantillon['nom_produit'] : 'N/A'],
	        ["T° du Produit à la réception :", isset($echantillon['temperature_reception']) ? $echantillon['temperature_reception'] : 'N/A'],
	        ["Date de production :", formatDateFr(isset($echantillon['date_production']) ? $echantillon['date_production'] : '')],
	        [$layout === 'format_3' ? "N° du lot :" : "N° Lot :", isset($echantillon['numero_lot']) ? $echantillon['numero_lot'] : 'N/A'],
	    ];
	
	    $rightLines = [
	        ["Adresse :", buildAddress($rapport)],
	        ["Ech. Prélevé par :", isset($rapport['preleve_par']) ? $rapport['preleve_par'] : 'N/A'],
	        ["Date du Prélèvement :", formatDateFr(isset($rapport['date_prelevement']) ? $rapport['date_prelevement'] : '')],
	        ["Date de réception :", formatDateFr(isset($rapport['date_reception']) ? $rapport['date_reception'] : '')],
	        ["Date début analyse :", formatDateFr(isset($rapport['date_debut_analyse']) ? $rapport['date_debut_analyse'] : '')],
	        ["Date fin analyse :", formatDateFr(isset($rapport['date_fin_analyse']) ? $rapport['date_fin_analyse'] : '')],
	        ["D.L.C :", formatDateFr(isset($echantillon['dlc']) ? $echantillon['dlc'] : '')],
	        ["Code produit donné par le demandeur :", isset($echantillon['code_produit_demandeur']) ? $echantillon['code_produit_demandeur'] : 'N/A'],
	    ];
	    if (!empty($extraLeftLines)) {
	        $leftLines = array_merge($leftLines, $extraLeftLines);
	    }
	    if (!empty($extraRightLines)) {
	        $rightLines = array_merge($rightLines, $extraRightLines);
	    }
	    $leftEndY  = drawInfoColumn($pdf, $leftX, $y, $leftW, $leftLines, $lang);
	    $rightEndY = drawInfoColumn($pdf, $rightX, $y, $rightW, $rightLines, $lang);
	    $minY = ($layout === 'format_3') ? 0 : 84;
	    $pdf->SetY(max($leftEndY, $rightEndY, $minY));
	}
	function signSimple($pdf, $rapport, $lang) {
	    $respEssai = trim((string)(isset($rapport['responsable_essai']) ? $rapport['responsable_essai'] : 'N/A'));
	    $coRespEssai = trim((string)(isset($rapport['co_responsable_essai']) ? $rapport['co_responsable_essai'] : 'N/A'));
	    $respTech = trim((string)(isset($rapport['responsable_technique']) ? $rapport['responsable_technique'] : 'N/A'));
	    if ($lang !== 'fr') {
	        $respEssai = trText($respEssai, $lang);
	        $coRespEssai = trText($coRespEssai, $lang);
	        $respTech = trText($respTech, $lang);
	    }
	    $leftParts = [];
	    if ($respEssai !== '' && strtoupper($respEssai) !== 'N/A') $leftParts[] = $respEssai;
	    if ($coRespEssai !== '' && strtoupper($coRespEssai) !== 'N/A') $leftParts[] = $coRespEssai;
	    $left = count($leftParts) ? implode(' / ', $leftParts) : trFixed('N/A', $lang);
	    $right = ($respTech !== '') ? $respTech : trFixed('N/A', $lang);
	    if ($lang === 'ar') {
	        $html = '
	        <table width="100%" cellpadding="2" cellspacing="0" border="0">
	            <tr>
	                <td width="50%" align="center"><b>' . e(trFixed("Les responsables de l'essai", $lang)) . '</b></td>
	                <td width="50%" align="center"><b>' . e(trFixed("Responsable technique des laboratoires", $lang)) . '</b></td>
	            </tr>
	            <tr>
	                <td width="50%" align="center">' . e($left) . '</td>
	                <td width="50%" align="center">' . e($right) . '</td>
	            </tr>
	        </table>';
	        $pdf->writeHTML($html, true, false, false, false, '');
	        return;
	    }
	    $pdf->SetFont(pdfFont($lang), 'B', 10);
	    $pdf->Cell(95, 6, trFixed("Les responsables de l'essai", $lang), 0, 0, 'L');
	    $pdf->Cell(0, 6, trFixed("Responsable technique des laboratoires", $lang), 0, 1, 'R');
	    $pdf->SetFont(pdfFont($lang), '', 10);
	    $pdf->Cell(95, 6, $left, 0, 0, 'L');
	    $pdf->Cell(0, 6, $right, 0, 1, 'R');
	}
	function renderFormat1($pdf, $rapport, $echantillon, $resultats, $lang) {
	    $pdf->Ln(2);
	    topInfoTwoCols($pdf, $rapport, $echantillon, 'format_1', $lang);
	    $pdf->Ln(3);
	    $pdf->SetFont(pdfFont($lang), '', 10);
	    $html = '
	    <table border="0.6" cellpadding="4" cellspacing="0" width="100%">
	        <tr>
	            <td width="48%" align="center"><b>' . e(trFixed('Analyses', $lang)) . '</b></td>
	            <td width="34%" align="center"><b>' . e(trFixed('Références Méthodes', $lang)) . '</b></td>
	            <td width="18%" align="center"><b>' . e(trFixed('Résultats', $lang)) . '</b></td>
	        </tr>';
	
	    foreach ($resultats as $r) {
	        $analyse = trCell(isset($r['analyse']) ? $r['analyse'] : 'N/A', $lang);
	        if ((int)(isset($r['is_accredited']) ? $r['is_accredited'] : 0) === 0) {
	            $analyse .= ' (*)';
	        }
	        $methode = trCell(isset($r['methode']) ? $r['methode'] : 'N/A', $lang);
	        $resRaw = trim((string)(isset($r['resultat']) ? $r['resultat'] : 'N/A'));
	        $res = trim(trCell($resRaw, $lang) . ' ' . (isset($r['unite']) ? $r['unite'] : 'N/A'));
	
	        $html .= '
	        <tr>
	            <td width="48%">' . e($analyse) . '</td>
	            <td width="34%">' . e($methode) . '</td>
	            <td width="18%" align="center">' . e($res) . '</td>
	        </tr>';
	    }
	    $html .= '</table>';
	    $pdf->writeHTML($html, true, false, false, false, '');
	    $pdf->Ln(1);
	    $pdf->SetFont(pdfFont($lang), '', $lang === 'ar' ? 9 : 8);
	    $note = formatNotes(isset($rapport['note_bas_page']) ? $rapport['note_bas_page'] : '', $lang);
	    if ($note !== '') {
	        $pdf->MultiCell(0, 4, $note, 0, pdfAlign($lang), false, 1);
	    }
	    signSimple($pdf, $rapport, $lang);
	    $pdf->Ln(40);
	    renderObservationsBlock($pdf, $rapport, $lang);
	}
	function renderFormat2($pdf, $rapport, $echantillon, $resultats, $lang) {
	    renderFormat1($pdf, $rapport, $echantillon, $resultats, $lang);
	}
	
	function buildCriteriaHtml($criteresText, $lang) {
	    $criteresText = cleanPdfText($criteresText);
	    if ($criteresText === '') {
	        return '';
	    }
	    $translated = $lang === 'fr' ? $criteresText : trText($criteresText, $lang);
	    $translated = preg_replace("/\r\n|\r/u", "\n", $translated);
	    $lines = array_values(array_filter(array_map('trim', explode("\n", $translated)), function ($line) {
	        return $line !== '';
	    }));
	
	    if (empty($lines)) {
	        return '';
	    }
	    $html = '';
	    foreach ($lines as $index => $line) {
	        if ($index > 0) {
	            $html .= '<br/>';
	        }
	        $html .= e($line);
	    }
	    return $html;
	}
	function criteriaHeaderHtml($lang) {
	    return '<b>' . e(trFixed('Critères de décision *', $lang)) . '</b><br/><b>' . e(trFixed('(stabilité)', $lang)) . '</b><br/><i>' . e(trFixed('(Selon NF V08 408-1997)', $lang)) . '</i>';
	}
	function renderFormat3($pdf, $rapport, $echantillon, $stabilite, $stabiliteLignes, $lang) {
	    if (empty($stabiliteLignes)) {
	        $pdf->Ln(2);
	        topInfoTwoCols($pdf, $rapport, $echantillon, 'format_3', $lang);
	        $pdf->Ln(4);
	        $pdf->SetFont(pdfFont($lang), '', 10);
	        $pdf->MultiCell(0, 6, trFixed('Aucune donnée de stabilité disponible pour cette demande.', $lang), 0, pdfAlign($lang), false, 1);
	        $pdf->Ln(4);
	        renderObservationsBlock($pdf, $rapport, $lang);
	        return;
	    }
	    $pdf->Ln(2);
	    $pdf->SetFont(pdfFont($lang), '', 10);
	    $phSolid = '';
	    foreach ($stabiliteLignes as $line) {
	        if (
	            stripos(isset($line['parametre']) ? $line['parametre'] : '', 'pH') !== false &&
	            stripos(isset($line['sous_parametre']) ? $line['sous_parametre'] : '', 'solide') !== false
	        ) {
	            $phSolid = isset($line['incubation_ambiante']) ? $line['incubation_ambiante'] : '';
	            break;
	        }
	    }
	    $extraLeftLines = [];
	    if (trim((string)(isset($echantillon['nom_commercial']) ? $echantillon['nom_commercial'] : '')) !== '') {
	        $extraLeftLines[] = ['', trText($echantillon['nom_commercial'], $lang)];
	    }
	    $extraLeftLines[] = ["pH avant incubation (phase solide) :", $phSolid !== '' ? $phSolid : 'N/A'];
	    topInfoTwoCols($pdf, $rapport, $echantillon, 'format_3', $lang, $extraLeftLines);
	    $pdf->Ln(1);
	    $html = '
	    <table border="0.6" cellpadding="3" cellspacing="0" width="100%">
	        <tr>
	            <td colspan="6" align="center">
	                <b>' . e(trFixed('ANALYSE', $lang)) . '</b><br/>
	                ' . e(trFixed('Contrôle de la stabilité des produits appertisés et assimilés', $lang)) . ' (' . e(isset($stabilite['norme_reference']) ? $stabilite['norme_reference'] : 'NF V08 408-1997') . ')
	            </td>
	        </tr>
	        <tr>
	            <td width="30%" rowspan="3" align="center"><b>' . e(trFixed('Paramètres', $lang)) . '</b></td>
	            <td width="39%" colspan="3" align="center"><b>' . e(trFixed('Résultats', $lang)) . '</b></td>
	            <td width="31%" rowspan="3" align="center">' . criteriaHeaderHtml($lang) . '</td>
	        </tr>
	        <tr>
	            <td width="13%" align="center"><b>' . e(trFixed('Incubation à', $lang)) . '</b></td>
	            <td width="13%" align="center"><b>' . e(trFixed('Incubation', $lang)) . '</b></td>
	            <td width="13%" align="center"><b>' . e(trFixed('Incubation', $lang)) . '</b></td>
	        </tr>
	        <tr>
	            <td width="13%" align="center"><b>' . e(trFixed('T° ambiante (7J)', $lang)) . '</b></td>
	            <td width="13%" align="center"><b>' . e(trFixed('à 37°C/7J', $lang)) . '</b></td>
	            <td width="13%" align="center"><b>' . e(trFixed('à 55 °C/7J', $lang)) . '</b></td>
	        </tr>';
	
	    foreach ($stabiliteLignes as $row) {
	        $criteresText = trim((string)(isset($row['criteres_text']) ? $row['criteres_text'] : ''));
	        if ($criteresText === '') {
	            $criteresText = trim((string)(isset($row['critere_decision']) ? $row['critere_decision'] : ''));
	        }
	        $criteresHtml = buildCriteriaHtml($criteresText, $lang);
	        $html .= '
	        <tr>
	            <td width="20%"><b>' . e(trCell(isset($row['parametre']) ? $row['parametre'] : 'N/A', $lang)) . '</b></td>
	            <td width="10%">' . e(trCell(isset($row['sous_parametre']) ? $row['sous_parametre'] : 'N/A', $lang)) . '</td>
	            <td width="13%" align="center">' . e(trCell(isset($row['incubation_ambiante']) ? $row['incubation_ambiante'] : 'N/A', $lang)) . '</td>
	            <td width="13%" align="center">' . e(trCell(isset($row['incubation_37c']) ? $row['incubation_37c'] : 'N/A', $lang)) . '</td>
	            <td width="13%" align="center">' . e(trCell(isset($row['incubation_55c']) ? $row['incubation_55c'] : 'N/A', $lang)) . '</td>
	            <td width="31%">' . $criteresHtml . '</td>
	        </tr>';
	    }
	
	    $html .= '
	        <tr>
	            <td colspan="6" align="center">
	                <b>' . e(trFixed('Déclaration :', $lang)) . ' ' . e(trText(isset($stabilite['declaration_text']) ? $stabilite['declaration_text'] : 'N/A', $lang)) . '</b>
	            </td>
	        </tr>
	    </table><br/>';
	
	    $pdf->SetFont(pdfFont($lang), '', $lang === 'ar' ? 9 : 8.4);
	    $pdf->writeHTML($html, true, false, false, false, '');
	    $pdf->SetFont(pdfFont($lang), '', $lang === 'ar' ? 9 : 8);
	    if (trim((string)(isset($stabilite['note_1']) ? $stabilite['note_1'] : '')) !== '') {
	        $pdf->MultiCell(0, 4, trText($stabilite['note_1'], $lang), 0, pdfAlign($lang), false, 1);
	    }
	    if (trim((string)(isset($stabilite['note_2']) ? $stabilite['note_2'] : '')) !== '') {
	        $pdf->MultiCell(0, 4, trText($stabilite['note_2'], $lang), 0, pdfAlign($lang), false, 1);
	    }
	    signSimple($pdf, $rapport, $lang);
	    $pdf->Ln(3);
	    renderObservationsBlock($pdf, $rapport, $lang);
	}
	function chooseLayout($docReport) {
	    $raw = rawValue($docReport->getValue('ra_typerapport'));
	    if ($raw === '') {
	        return 'format_1';
	    }
	    if ($raw === '2' || stripos($raw, 'format_2') !== false) {
	        return 'format_2';
	    }
	    if ($raw === '3' || stripos($raw, 'format_3') !== false || stripos($raw, 'stabil') !== false) {
	        return 'format_3';
	    }
	    return 'format_1';
	}
	function storeToVault($fileName) {
	    include_once("VAULT/Class.VaultDiskStorage.php");
	    $dbaccess = GetParam("FREEDOM_DB");
	    $tabResult = '';
	    $coffio = new VaultDiskStorage($dbaccess, "10");
	    $coffio->Store($fileName, '', $tabResult);
	    $filName = basename($fileName);
	    return 'application/pdf|' . $tabResult . '|' . $filName;
	}
	function makePlaquette($idDA, $idRA, $tyRA = 1, $lang = 'fr') {
	    $dbaccess = GetParam("FREEDOM_DB");
	    $docDA = new_Doc($dbaccess, $idDA, true);
	    if (!$docDA || !$docDA->isAlive()) {
	        throw new Exception("DA introuvable");
	    }
	
	    if ($idRA <= 0) {
	        $idRA = rawValue($docDA->getValue('rapport_prov'));
	    }
	    $docReport = new_Doc($dbaccess, $idRA, true);
	    if (!$docReport || !$docReport->isAlive()) {
	        throw new Exception("Rapport introuvable");
	    }
	
	    $tempoNumRapport = rawValue($docReport->getValue("seqnum"));
	    if (strlen($tempoNumRapport) < 3 && method_exists($docReport, 'updateSeqNum')) {
	        $docReport->updateSeqNum();
	        $tempoNumRapport = rawValue($docReport->getValue("seqnum"));
	    }
	    if ($tempoNumRapport === '') {
	        $tempoNumRapport = (string)$idRA;
	    }
	
	    $layout = chooseLayout($docReport);
	    if ($layout === 'format_3') {
	        $layout = 'format_1';
	    }
	
	    $rapport = buildRapportArray($dbaccess, $docDA, $docReport, $layout);
	    $identityRows = buildIdentityRows($dbaccess, $docDA);
	    $echantillon = buildSampleArray($dbaccess, $identityRows);
	    $resultats = buildResultRows($dbaccess, $docReport);
	    $validations = buildValidationArray($dbaccess, $docReport);
	
	    logMe([
	        'idDA' => $idDA,
	        'idRA' => $idRA,
	        'layout' => $layout,
	        'rows' => count($resultats),
	        'lang' => $lang
	    ]);
	    $pdf = new LaboReportPDF('P', 'mm', 'A4', true, 'UTF-8', false);
	    $pdf->SetCreator('TCPDF');
	    $pdf->SetAuthor('Laboratoire');
	    $pdf->SetTitle(trFixed("RAPPORT D'ANALYSES", $lang));
	    $pdf->SetSubject(trFixed("RAPPORT D'ANALYSES", $lang));
	    $pdf->SetTextColor(0, 0, 0);
	    $pdf->SetDrawColor(80, 80, 80);
	    $pdf->SetFillColor(255, 255, 255);
	    $pdf->layoutCode = $layout;
	    $pdf->langCode = $lang;
	    $pdf->tunisDate = formatDateFrOrEmpty(isset($rapport['tunis_date']) ? $rapport['tunis_date'] : date('Y-m-d'));
	    if ($pdf->tunisDate === '') {
	        $pdf->tunisDate = date('d/m/Y');
	    }
	    $pdf->boNumber = isset($rapport['ref_bo']) ? $rapport['ref_bo'] : 'N/A';
	    $pdf->duplicateLabel = isset($rapport['duplicate_label']) ? $rapport['duplicate_label'] : '';
	
	    $pdf->setRTL(isRtl($lang));
	    if ($lang === 'ar') {
	        $lg = [];
	        $lg['a_meta_charset'] = 'UTF-8';
	        $lg['a_meta_dir'] = 'rtl';
	        $lg['a_meta_language'] = 'ar';
	        $lg['w_page'] = 'page';
	        $pdf->setLanguageArray($lg);
	    }
	    $pdf->SetMargins(15, 34, 15);
	    $pdf->SetAutoPageBreak(true, 24);
	    $pdf->SetHeaderMargin(0);
	    $pdf->SetFooterMargin(8);
	    $pdf->AddPage();
	    switch ($layout) {
	        case 'format_2':
	            renderFormat2($pdf, $rapport, $echantillon, $resultats, $lang);
	            break;
	        case 'format_1':
	        default:
	            renderFormat1($pdf, $rapport, $echantillon, $resultats, $lang);
	            break;
	    }
	    if (!is_dir(__DIR__ . '/certificats')) {
	        @mkdir(__DIR__ . '/certificats', 0777, true);
	    }
	
	    $fileName = ($tyRA == 1 ? 'RP_' : 'RD_') . preg_replace('/[^A-Za-z0-9_-]/', '_', $tempoNumRapport) . '_' . $lang . '.pdf';
	    $valRet = __DIR__ . '/certificats/' . $fileName;
	    $pdf->Output($valRet, 'F');
	    return $valRet;
	}
	function genrapport(&$action) {
	    logMe('INIT ----------------------------------------------------------------------');
	    $dbaccess = GetParam("FREEDOM_DB");
	
	    $idDA = (int)GetHttpVars("id", -1);
	    $idRA = (int)GetHttpVars("rapport", -1);
	    $tyRA = (int)GetHttpVars("tempo", 1);
	    $lang = strtolower(trim((string)GetHttpVars("lang", "fr")));
	    if (!in_array($lang, ['fr', 'en', 'ar'], true)) {
	        $lang = 'fr';
	    }
	    try {
	        if ($idDA <= 0) {
	            throw new Exception("id DA manquant");
	        }
	
	        $docDA = new_Doc($dbaccess, $idDA, true);
	        if (!$docDA || !$docDA->isAlive()) {
	            throw new Exception("DA introuvable");
	        }
	
	        if ($idRA <= 0) {
	            $idRA = (int)rawValue($docDA->getValue('rapport_prov'));
	        }
	        if ($idRA <= 0) {
	            throw new Exception("Rapport lié introuvable");
	        }
	        $lienRapport = makePlaquette($idDA, $idRA, $tyRA, $lang);
	        $inVault = storeToVault($lienRapport);
	        if ($tyRA == 1) {
	            $docDA->edition_prov = $inVault;
	        } else {
	            $docDA->edition_finale = $inVault;
	        }
	        $docDA->Modify();
	        $tabJson = [
	            'result' => 'ok',
	            'file' => $lienRapport,
	            'vault' => $inVault,
	            'lang' => $lang
	        ];
	        echo json_encode($tabJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	    } catch (Exception $e) {
	        logMe('ERROR: ' . $e->getMessage());
	        http_response_code(500);
	        echo json_encode([
	            'result' => 'error',
	            'message' => $e->getMessage()
	        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	    }
	    exit();
}
?>
	
	    
?>