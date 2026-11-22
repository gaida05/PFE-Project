<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$tcpdfCandidates = [
    __DIR__ . '/tcpdf/tcpdf.php',
    __DIR__ . '/../tcpdf_rapport/tcpdf/tcpdf.php',
    __DIR__ . '/../tcpdf/tcpdf.php'
];
$tcpdfLoaded = false;
foreach ($tcpdfCandidates as $tcpdfPath) {
    if (file_exists($tcpdfPath)) {
        require_once $tcpdfPath;
        $tcpdfLoaded = true;
        break;
    }
}
if (!$tcpdfLoaded) {
    die("Erreur : bibliothèque TCPDF introuvable. Vérifiez le chemin tcpdf/tcpdf.php.");
}

define('MYMEMORY_TRANSLATE_URL', 'https://api.mymemory.translated.net/get');
define('MYMEMORY_CONTACT_EMAIL', '');

$dbHost = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "labo_db1";

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("Erreur connexion BD : " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function v($array, $key, $default = '') {
    return isset($array[$key]) && $array[$key] !== null ? $array[$key] : $default;
}

function formatDateFr($date) {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = strtotime($date);
    if ($ts === false) return $date;
    return date('d/m/Y', $ts);
}

function formatDateTimeFr($date) {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = strtotime($date);
    if ($ts === false) return $date;
    return date('d/m/Y H:i', $ts);
}

function cleanPdfText($text) {
    $text = trim((string)$text);
    $text = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    return $text;
}

function buildAddress($rapport) {
    $parts = [];
    if (trim(v($rapport, 'adresse')) !== '') $parts[] = v($rapport, 'adresse');
    if (trim(v($rapport, 'ville')) !== '') $parts[] = v($rapport, 'ville');
    if (trim(v($rapport, 'pays')) !== '') $parts[] = v($rapport, 'pays');
    return implode(', ', $parts);
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

function boHeaderHtml($label, $value, $lang, $center = false) {
    $label = trim((string)$label);
    $value = trim((string)$value);

    if ($lang !== 'ar') {
        return '<span><b>' . e(trFixed($label, $lang)) . '</b> ' . e($value) . '</span>';
    }

    $align = $center ? 'center' : 'right';

    return '<div dir="rtl" style="text-align:' . $align . ';"><b>' . e(trFixed($label, $lang)) . '</b> <span dir="ltr">' . e($value) . '</span></div>';
}

function lrm() {
    return "\u{200E}";
}

function rlm() {
    return "\u{200F}";
}

function rtlLabelWithLtrValue($label, $value, $lang) {
    $label = trim((string)$label);
    $value = trim((string)$value);

    if ($lang !== 'ar') {
        return trFixed($label, $lang) . ' ' . $value;
    }

    return rlm() . trFixed($label, $lang) . ' ' . lrm() . $value . lrm();
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
            "Aucune donnée de stabilité disponible pour cette demande." => "No stability data available for this request."
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
            "Aucune donnée de stabilité disponible pour cette demande." => "لا توجد بيانات ثبات متاحة لهذا الطلب."
        ]
    ];
}

function trFixed($text, $lang) {
    if ($lang === 'fr' || trim((string)$text) === '') {
        return $text;
    }
    $map = fixedMap();
    return $map[$lang][$text] ?? $text;
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

    $targetMap = [
        'en' => 'en',
        'ar' => 'ar'
    ];

    if (!isset($targetMap[$lang])) {
        return $text;
    }

    $query = [
        'q' => $text,
        'langpair' => 'fr|' . $targetMap[$lang]
    ];

    if (defined('MYMEMORY_CONTACT_EMAIL') && trim(MYMEMORY_CONTACT_EMAIL) !== '') {
        $query['de'] = MYMEMORY_CONTACT_EMAIL;
    }

    $url = MYMEMORY_TRANSLATE_URL . '?' . http_build_query($query);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlErr || $httpCode >= 400) {
        return $text;
    }

    $json = json_decode($response, true);
    $translated = $json['responseData']['translatedText'] ?? '';
    error_log($url);
    error_log($response);
    error_log('HTTP=' . $httpCode . ' CURL=' . $curlErr);
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
    if ($text === '') return '';

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
    return $formatted;
}

function defaultObservations($lang) {
    $fr = "Ce rapport ne concerne que l'objet soumis à l'essai pour les différents paramètres testés.\n"
        . "Les résultats s'appliquent à l'échantillon tel qu'il a été reçu.\n"
        . "Ce document est la propriété du laboratoire, il ne doit en aucun cas être reproduit sans l'autorisation écrite du propriétaire.";

    return $lang === 'fr' ? $fr : trText($fr, $lang);
}

$idDemande = isset($_GET['id_demande']) ? (int)$_GET['id_demande'] : 0;
$idEchantillon = isset($_GET['id_echantillon']) ? (int)$_GET['id_echantillon'] : 0;
$lang = isset($_GET['lang']) ? strtolower(trim($_GET['lang'])) : 'fr';

if ($idDemande <= 0) {
    die("Erreur : id_demande manquant");
}

if (!in_array($lang, ['fr', 'en', 'ar'], true)) {
    $lang = 'fr';
}

$sql = "
    SELECT
        r.*,
        d.id_demande AS demande_id,
        d.ref_demande,
        d.id_secteur,
        d.date_demande,
        d.date_creation,
        d.date_cloture,
        d.date_validation AS demande_date_validation,
        d.statut AS demande_statut,
        s.nom_secteur,
        d.ref_dossier,
        d.ref_bo,
        d.origine_provenance,
        d.producteur,
        d.importateur,
        d.preleve_par,
        d.date_prelevement,
        d.date_reception,
        TIME(d.date_reception) AS heure_reception,
        d.date_debut_analyse,
        d.date_fin_analyse,
        c.client_name,
        c.adresse,
        c.ville,
        c.pays,
        c.telephone,
        c.email,
        r.responsable_essai,
        r.responsable_technique,
        r.co_responsable_essai
    FROM demande_analyse d
    LEFT JOIN rapports r ON r.id_demande = d.id_demande
    LEFT JOIN client c ON c.id_client = d.id_client
    LEFT JOIN secteur s ON s.id_secteur = d.id_secteur
    WHERE d.id_demande = ?
    ORDER BY r.id_rapport ASC
    LIMIT 1
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Erreur prepare rapport : " . $conn->error);
}
$stmt->bind_param("i", $idDemande);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    die("Demande introuvable");
}
$rapport = $res->fetch_assoc();
$stmt->close();

$idRapport = (string) v($rapport, 'id_rapport');

$echantillons = [];
$sqlEAll = "
    SELECT *
    FROM echantillons
    WHERE id_demande = ?
    ORDER BY id_echantillon ASC
";
$stmtEAll = $conn->prepare($sqlEAll);
if ($stmtEAll) {
    $stmtEAll->bind_param("i", $rapport['demande_id']);
    $stmtEAll->execute();
    $resEAll = $stmtEAll->get_result();
    while ($row = $resEAll->fetch_assoc()) {
        $echantillons[] = $row;
    }
    $stmtEAll->close();
}

$echantillon = [];
if ($idEchantillon > 0) {
    foreach ($echantillons as $ech) {
        if ((int)$ech['id_echantillon'] === $idEchantillon) {
            $echantillon = $ech;
            break;
        }
    }
}
if (empty($echantillon) && !empty($echantillons)) {
    $echantillon = $echantillons[0];
}

$resultats = [];
$sqlR = "
    SELECT
        COALESCE(a.nom_analyse, a.code_analyse, CONCAT('Analyse ', ra.id_analyse)) AS analyse,
        ra.methode,
        ra.resultat,
        ra.unite,
        ra.ordre_affichage,
        ra.is_accredited,
        ra.result_note,
        ra.report_section,
        oa.ordre_reference
    FROM resultat_analyse ra
    LEFT JOIN analyses a ON a.id_analyse = ra.id_analyse
    LEFT JOIN ordres_analyses oa ON oa.ordre_id = ra.ordre_id
    WHERE ra.id_demande = ?
    ORDER BY ra.ordre_affichage ASC, ra.id ASC
";
$stmtR = $conn->prepare($sqlR);
if ($stmtR) {
    $stmtR->bind_param("i", $rapport['demande_id']);
    $stmtR->execute();
    $resR = $stmtR->get_result();
    while ($row = $resR->fetch_assoc()) {
        $resultats[] = $row;
    }
    $stmtR->close();
}

$stabilite = null;
$stabiliteLignes = [];

if ($idRapport !== '') {
    $sqlS = "
        SELECT *
        FROM rapport_stabilite
        WHERE rapport_id = ?
        LIMIT 1
    ";
    $stmtS = $conn->prepare($sqlS);
    if ($stmtS) {
        $stmtS->bind_param("s", $idRapport);
        $stmtS->execute();
        $resS = $stmtS->get_result();
        if ($resS && $resS->num_rows > 0) {
            $stabilite = $resS->fetch_assoc();
            $sqlSL = "
                SELECT
                    l.*,
                    GROUP_CONCAT(
                        CONCAT(
                            CASE
                                WHEN COALESCE(c.label, '') <> '' AND COALESCE(c.code, '') <> '' THEN CONCAT(c.label, ' (', c.code, ')')
                                WHEN COALESCE(c.label, '') <> '' THEN c.label
                                WHEN COALESCE(c.code, '') <> '' THEN c.code
                                ELSE ''
                            END,
                            CASE
                                WHEN COALESCE(c.label, '') <> '' OR COALESCE(c.code, '') <> '' THEN ' :'
                                ELSE ''
                            END,
                            CASE
                                WHEN c.description IS NOT NULL AND c.description <> '' THEN CONCAT('\n', c.description)
                                ELSE ''
                            END
                        )
                        SEPARATOR '\n'
                    ) AS criteres_text
                FROM rapport_stabilite_ligne l
                LEFT JOIN rapport_stabilite_critere rc ON rc.ligne_id = l.id
                LEFT JOIN criteres c ON c.id = rc.critere_id
                WHERE l.stabilite_id = ?
                GROUP BY l.id
                ORDER BY l.ordre_affichage ASC, l.id ASC
            ";
            $stmtSL = $conn->prepare($sqlSL);
            if ($stmtSL) {
                $stmtSL->bind_param("i", $stabilite['id']);
                $stmtSL->execute();
                $resSL = $stmtSL->get_result();
                while ($row = $resSL->fetch_assoc()) {
                    $stabiliteLignes[] = $row;
                }
                $stmtSL->close();
            }
        }
        $stmtS->close();
    }
}

class LaboReportPDF extends TCPDF
{
    public $layoutCode = 'format_1';
    public $langCode = 'fr';
    public $tunisDate = '';
    public $boNumber = '';
    public $duplicateLabel = '';
    public $footerObs = '';

    public function Header()
    {
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

    public function Footer()
    {
        $this->SetY(-10);
        $this->SetFont(pdfFont($this->langCode), '', 9);
        $this->Cell(0, 4, $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

function renderObservationsBlock($pdf, $rapport, $lang, $extraPrefix = '')
{
    $obs = cleanPdfText(v($rapport, 'observation'));
    if ($obs === '') {
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

function drawInfoColumn($pdf, $x, $y, $columnW, array $lines, $lang)
{
    $currentY = $y;
    foreach ($lines as $item) {
        $label = isset($item[0]) ? trim($item[0]) : '';
        $value = isset($item[1]) ? trim($item[1]) : '';

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
        $pdf->writeHTMLCell(
            $columnW,
            0,
            $x,
            $currentY,
            $text,
            0,
            1,
            false,
            true,
            pdfAlign($lang),
            true
        );

        $currentY = $pdf->GetY();
    }
    return $currentY;
}

function topInfoTwoCols($pdf, $rapport, $echantillon, $layout = 'format_1', $lang = 'fr', $extraLeftLines = [], $extraRightLines = [])
{
    $leftX  = 18;
    $rightX = 112;
    $y      = ($layout === 'format_3') ? 42 : 38;
    $leftW  = 86;
    $rightW = 80;

    $leftLines = [
        ["Réf Dossier :", v($rapport, 'ref_dossier')],
        ["Réf Labo :", v($echantillon, 'ref_labo', v($rapport, 'ref_demande'))],
        ["Nom du demandeur d'analyse :", v($rapport, 'client_name')],
        [$layout === 'format_3' ? "Provenance :" : "Origine :", v($rapport, 'origine_provenance')],
        ["Producteur :", v($rapport, 'producteur')],
        ["Importateur :", v($rapport, 'importateur')],
        ["Nom du produit :", v($echantillon, 'nom_produit')],
        ["T° du Produit à la réception :", v($echantillon, 'temperature_reception')],
        ["Date de production :", formatDateFr(v($echantillon, 'date_production'))],
        [$layout === 'format_3' ? "N° du lot :" : "N° Lot :", v($echantillon, 'numero_lot')],
    ];

    $rightLines = [
        ["Adresse :", buildAddress($rapport)],
        ["Ech. Prélevé par :", v($rapport, 'preleve_par')],
        ["Date du Prélèvement :", formatDateFr(v($rapport, 'date_prelevement'))],
        ["Date de réception :", formatDateFr(v($rapport, 'date_reception'))],
        ["Date début analyse :", formatDateFr(v($rapport, 'date_debut_analyse'))],
        ["Date fin analyse :", formatDateFr(v($rapport, 'date_fin_analyse'))],
        ["D.L.C :", formatDateFr(v($echantillon, 'dlc'))],
        ["Code produit donné par le demandeur :", v($echantillon, 'code_produit_demandeur')],
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

function signSimple($pdf, $rapport, $lang)
{
    $respEssai = trim(v($rapport, 'responsable_essai'));
    $coRespEssai = trim(v($rapport, 'co_responsable_essai'));
    $respTech = trim(v($rapport, 'responsable_technique'));

    if ($lang !== 'fr') {
        $respEssai = trText($respEssai, $lang);
        $coRespEssai = trText($coRespEssai, $lang);
        $respTech = trText($respTech, $lang);
    }

    if ($respEssai !== '' && $coRespEssai !== '') {
        $left = $respEssai . ' / ' . $coRespEssai;
    } elseif ($respEssai !== '') {
        $left = $respEssai;
    } elseif ($coRespEssai !== '') {
        $left = $coRespEssai;
    } else {
        $left = '';
    }

    $right = ($respTech !== '') ? $respTech : '';

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
    $pdf->Ln(0);
    $pdf->SetFont(pdfFont($lang), '', 10);
    $pdf->Cell(95, 6, $left, 0, 0, 'L');
    $pdf->Cell(0, 6, $right, 0, 1, 'R');
}

function renderFormat1($pdf, $rapport, $echantillon, $resultats, $lang)
{
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
        $analyse = trCell(v($r, 'analyse'), $lang);
        if ((int)v($r, 'is_accredited', 1) === 0) {
            $analyse .= ' (*)';
        }

        $methode = trCell(v($r, 'methode'), $lang);
        $resRaw = trim(v($r, 'resultat'));
        $res = trim(trCell($resRaw, $lang) . ' ' . v($r, 'unite'));

        $html .= '
        <tr>
            <td width="48%" border="0">' . e($analyse) . '</td>
            <td width="34%" border="0">' . e($methode) . '</td>
            <td width="18%" align="center" border="0">' . e($res) . '</td>
        </tr>';
    }

    $html .= '</table>';
    $pdf->writeHTML($html, true, false, false, false, '');
    $pdf->Ln(1);
    $pdf->SetFont(pdfFont($lang), '', $lang === 'ar' ? 9 : 8);
    $note = formatNotes(v($rapport, 'note_bas_page'), $lang);
    $pdf->MultiCell(0, 4, $note, 0, pdfAlign($lang), false, 1);
    signSimple($pdf, $rapport, $lang);
    $pdf->Ln(40);
    renderObservationsBlock($pdf, $rapport, $lang);
}

function renderFormat2($pdf, $rapport, $echantillon, $resultats, $lang)
{
    $pdf->Ln(2);
    topInfoTwoCols($pdf, $rapport, $echantillon, 'format_2', $lang);
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
        $analyse = trCell(v($r, 'analyse'), $lang);
        if ((int)v($r, 'is_accredited', 1) === 0) {
            $analyse .= ' (*)';
        }

        $methode = trCell(v($r, 'methode'), $lang);
        $resRaw = trim(v($r, 'resultat'));
        $res = trim(trCell($resRaw, $lang) . ' ' . v($r, 'unite'));

        $html .= '
        <tr>
            <td width="48%" border="0">' . e($analyse) . '</td>
            <td width="34%" border="0">' . e($methode) . '</td>
            <td width="18%" align="center" border="0">' . e($res) . '</td>
        </tr>';
    }

    $html .= '</table>';
    $pdf->writeHTML($html, true, false, false, false, '');
    $pdf->Ln(1);
    $pdf->SetFont(pdfFont($lang), '', $lang === 'ar' ? 9 : 8);
    $note = formatNotes(v($rapport, 'note_bas_page'), $lang);
    $pdf->MultiCell(0, 4, $note, 0, pdfAlign($lang), false, 1);
    signSimple($pdf, $rapport, $lang);
    $pdf->Ln(40);
    renderObservationsBlock($pdf, $rapport, $lang);
}
function buildCriteriaHtml($criteresText, $lang)
{
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

function renderFormat3($pdf, $rapport, $echantillon, $stabilite, $stabiliteLignes, $lang)
{
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
            stripos(v($line, 'parametre'), 'pH') !== false &&
            stripos(v($line, 'sous_parametre'), 'solide') !== false
        ) {
            $phSolid = v($line, 'incubation_ambiante');
            break;
        }
    }

    $extraLeftLines = [];
    if (trim(v($echantillon, 'nom_commercial')) !== '') {
        $extraLeftLines[] = ['', trText(v($echantillon, 'nom_commercial'), $lang)];
    }
    $extraLeftLines[] = [trFixed("pH avant incubation (phase solide) :", $lang), $phSolid];

    topInfoTwoCols($pdf, $rapport, $echantillon, 'format_3', $lang, $extraLeftLines);
    $pdf->Ln(1);

    $html = '
    <table border="0.6" cellpadding="3" cellspacing="0" width="100%">
        <tr>
            <td colspan="6" align="center">
                <b>' . e(trFixed('ANALYSE', $lang)) . '</b><br/>
                ' . e(trFixed('Contrôle de la stabilité des produits appertisés et assimilés', $lang)) . ' (' . e(v($stabilite, 'norme_reference', 'NF V08 408-1997')) . ')
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

    for ($i = 0; $i < count($stabiliteLignes); $i++) {
        $row = $stabiliteLignes[$i];
        $parametre = trim(v($row, 'parametre'));
        $sousParametre = trim(v($row, 'sous_parametre'));
        $criteresText = trim(v($row, 'criteres_text'));
        if ($criteresText === '') {
            $criteresText = trim(v($row, 'critere_decision'));
        }
        $criteresHtml = buildCriteriaHtml($criteresText, $lang);

        if (strcasecmp($parametre, 'Odeur') === 0) {
            $nextRow = isset($stabiliteLignes[$i + 1]) ? $stabiliteLignes[$i + 1] : null;
            if ($nextRow && strcasecmp(trim(v($nextRow, 'parametre')), 'Texture') === 0) {
                $html .= '
                <tr>
                    <td width="20%"><b>' . e(trCell($parametre, $lang)) . '</b></td>
                    <td width="10%">' . e(trCell($sousParametre, $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($row, 'incubation_ambiante'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($row, 'incubation_37c'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($row, 'incubation_55c'), $lang)) . '</td>
                    <td width="31%" rowspan="2">' . $criteresHtml . '</td>
                </tr>
                <tr>
                    <td width="20%"><b>' . e(trCell(v($nextRow, 'parametre'), $lang)) . '</b></td>
                    <td width="10%">' . e(trCell(v($nextRow, 'sous_parametre'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($nextRow, 'incubation_ambiante'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($nextRow, 'incubation_37c'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($nextRow, 'incubation_55c'), $lang)) . '</td>
                </tr>';
                $i++;
                continue;
            }
        }

        if (stripos($parametre, 'pH') !== false) {
            $nextRow = isset($stabiliteLignes[$i + 1]) ? $stabiliteLignes[$i + 1] : null;
            if ($nextRow && stripos(trim(v($nextRow, 'parametre')), 'pH') !== false) {
                $html .= '
                <tr>
                    <td width="20%" rowspan="2"><b>' . e(trCell($parametre, $lang)) . '</b></td>
                    <td width="10%">' . e(trCell($sousParametre, $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($row, 'incubation_ambiante'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($row, 'incubation_37c'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($row, 'incubation_55c'), $lang)) . '</td>
                    <td width="31%" rowspan="2">' . $criteresHtml . '</td>
                </tr>
                <tr>
                    <td width="10%">' . e(trCell(v($nextRow, 'sous_parametre'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($nextRow, 'incubation_ambiante'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($nextRow, 'incubation_37c'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($nextRow, 'incubation_55c'), $lang)) . '</td>
                </tr>';
                $i++;
                continue;
            }
        }

        if (stripos($parametre, 'Examen microscopique') !== false) {
            $nextRow = isset($stabiliteLignes[$i + 1]) ? $stabiliteLignes[$i + 1] : null;
            if ($nextRow && stripos(trim(v($nextRow, 'parametre')), 'Examen microscopique') !== false) {
                $html .= '
                <tr>
                    <td width="20%" rowspan="2"><b>' . e(trCell($parametre, $lang)) . '</b></td>
                    <td width="10%">' . e(trCell($sousParametre, $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($row, 'incubation_ambiante'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($row, 'incubation_37c'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($row, 'incubation_55c'), $lang)) . '</td>
                    <td width="31%" rowspan="2">' . $criteresHtml . '</td>
                </tr>
                <tr>
                    <td width="10%">' . e(trCell(v($nextRow, 'sous_parametre'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($nextRow, 'incubation_ambiante'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($nextRow, 'incubation_37c'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(trCell(v($nextRow, 'incubation_55c'), $lang)) . '</td>
                </tr>';
                $i++;
                continue;
            }
        }

        $html .= '
        <tr>
            <td width="20%"><b>' . e(trCell($parametre, $lang)) . '</b></td>
            <td width="10%">' . e(trCell($sousParametre, $lang)) . '</td>
            <td width="13%" align="center">' . e(trCell(v($row, 'incubation_ambiante'), $lang)) . '</td>
            <td width="13%" align="center">' . e(trCell(v($row, 'incubation_37c'), $lang)) . '</td>
            <td width="13%" align="center">' . e(trCell(v($row, 'incubation_55c'), $lang)) . '</td>
            <td width="31%">' . $criteresHtml . '</td>
        </tr>';
    }

    $html .= '
        <tr>
            <td colspan="6" align="center">
                <b>' . e(trFixed('Déclaration :', $lang)) . ' ' . e(trText(v($stabilite, 'declaration_text', v($rapport, 'declaration_text')), $lang)) . '</b>
            </td>
        </tr>
    </table><br/>';

    $pdf->SetFont(pdfFont($lang), '', $lang === 'ar' ? 9 : 8.4);
    $pdf->writeHTML($html, true, false, false, false, '');
    $pdf->SetFont(pdfFont($lang), '', $lang === 'ar' ? 9 : 8);

    if (trim(v($stabilite, 'note_1')) !== '') {
        $pdf->MultiCell(0, 4, trText(v($stabilite, 'note_1'), $lang), 0, pdfAlign($lang), false, 1);
    }
    if (trim(v($stabilite, 'note_2')) !== '') {
        $pdf->MultiCell(0, 4, trText(v($stabilite, 'note_2'), $lang), 0, pdfAlign($lang), false, 1);
    }

    signSimple($pdf, $rapport, $lang);
    $pdf->ln(3);
    renderObservationsBlock($pdf, $rapport, $lang);
}

$layout = isset($_GET['format']) ? trim((string)$_GET['format']) : '';
if (!in_array($layout, ['format_1', 'format_2', 'format_3'], true)) {
    $layout = trim(v($rapport, 'report_layout', 'format_1'));
}
if (!in_array($layout, ['format_1', 'format_2', 'format_3'], true)) {
    $layout = 'format_1';
}

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
$pdf->tunisDate = formatDateFr(v($rapport, 'tunis_date', date('Y-m-d')));
$pdf->boNumber = v($rapport, 'ref_bo');
$pdf->duplicateLabel = v($rapport, 'duplicate_label');
$pdf->footerObs = v($rapport, 'observation');

$pdf->setRTL(isRtl($lang));

if ($lang === 'ar') {
    $lg = [];
    $lg['a_meta_charset'] = 'UTF-8';
    $lg['a_meta_dir'] = 'rtl';
    $lg['a_meta_language'] = 'ar';
    $lg['w_page'] = 'page';
    $pdf->setLanguageArray($lg);
}

if ($layout === 'format_3') {
    $pdf->SetMargins(12, 40, 12);
    $pdf->SetAutoPageBreak(true, 34);
} else {
    $pdf->SetMargins(15, 34, 15);
    $pdf->SetAutoPageBreak(true, 24);
}

$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(8);
$pdf->AddPage();

switch ($layout) {
    case 'format_2':
        renderFormat2($pdf, $rapport, $echantillon, $resultats, $lang);
        break;
    case 'format_3':
        renderFormat3($pdf, $rapport, $echantillon, $stabilite, $stabiliteLignes, $lang);
        break;
    case 'format_1':
    default:
        renderFormat1($pdf, $rapport, $echantillon, $resultats, $lang);
        break;
}

$pdf->Output('rapport_demande_' . $idDemande . '_' . $layout . '_' . $lang . '.pdf', 'I');
$conn->close();
?>