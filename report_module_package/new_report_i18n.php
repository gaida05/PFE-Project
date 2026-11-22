<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!file_exists(__DIR__ . '/tcpdf/tcpdf.php')) {
    die('TCPDF introuvable dans le dossier tcpdf/.');
}

require_once(__DIR__ . '/tcpdf/tcpdf.php');
require_once(__DIR__ . '/report_i18n.php');

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'labo_db';

if (!class_exists('mysqli')) {
    die('Extension mysqli non disponible sur ce serveur.');
}

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die('Erreur connexion BD : ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function v($array, $key, $default = '')
{
    return isset($array[$key]) && $array[$key] !== null ? $array[$key] : $default;
}

function formatDateFr($date): string
{
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '';
    }

    $ts = strtotime($date);
    if ($ts === false) {
        return (string)$date;
    }

    return date('d/m/Y', $ts);
}

function formatDateTimeFr($date): string
{
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '';
    }

    $ts = strtotime($date);
    if ($ts === false) {
        return (string)$date;
    }

    return date('d/m/Y H:i', $ts);
}

function cleanPdfText($text): string
{
    $text = trim((string)$text);
    $text = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    return $text;
}

function formatNotes($text): string
{
    $text = trim((string)$text);
    $text = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);
    $formatted = '';
    $i = 1;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if ($i === 1) {
            $formatted .= $line . "\n";
        } elseif ($i === 2) {
            $formatted .= '(**) ' . $line . "\n";
        } else {
            $formatted .= '- ' . $line . "\n";
        }
        $i++;
    }

    return $formatted;
}

function buildAddress(array $rapport): string
{
    $parts = [];
    if (trim(v($rapport, 'adresse')) !== '') {
        $parts[] = v($rapport, 'adresse');
    }
    if (trim(v($rapport, 'ville')) !== '') {
        $parts[] = v($rapport, 'ville');
    }
    if (trim(v($rapport, 'pays')) !== '') {
        $parts[] = v($rapport, 'pays');
    }
    return implode(', ', $parts);
}

function pdfSetFont(TCPDF $pdf, string $lang, string $style = '', float $size = 10): void
{
    $pdf->SetFont(report_font_family($lang), $style, $size);
}

function translated($value, string $lang): string
{
    return report_translate_value((string)$value, $lang);
}

$lang = report_lang_from_request($_GET['lang'] ?? 'fr');
$idRapport = isset($_GET['id_rapport']) ? trim((string)$_GET['id_rapport']) : '';
$idEchantillon = isset($_GET['id_echantillon']) ? (int)$_GET['id_echantillon'] : 0;
$requestedLayout = isset($_GET['format']) ? trim((string)$_GET['format']) : '';
$allowedLayouts = ['format_1', 'format_2', 'format_3'];

if ($idRapport === '') {
    die('Erreur : id_rapport manquant');
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
        d.statut AS demande_statut,
        d.nom_secteur,
        d.ref_dossier,
        d.ref_bo,
        d.origine_provenance,
        d.producteur,
        d.importateur,
        d.preleve_par,
        d.date_prelevement,
        d.date_reception,
        d.heure_reception,
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
    FROM rapports r
    LEFT JOIN demande_analyse d ON d.id_demande = r.id_demande
    LEFT JOIN client c ON c.id_client = d.id_client
    WHERE r.id_rapport = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Erreur prepare rapport : ' . $conn->error);
}
$stmt->bind_param('s', $idRapport);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    die('Rapport introuvable');
}
$rapport = $res->fetch_assoc();
$stmt->close();

$echantillons = [];
$sqlEAll = "
    SELECT *
    FROM echantillons
    WHERE id_demande = ?
    ORDER BY id_echantillon ASC
";
$stmtEAll = $conn->prepare($sqlEAll);
if ($stmtEAll) {
    $stmtEAll->bind_param('i', $rapport['demande_id']);
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
    SELECT analyse, methode, resultat, unite, ordre_affichage, is_accredited, result_note, report_section
    FROM resultat_analyse
    WHERE id_demande = ?
    ORDER BY ordre_affichage ASC, id ASC
";
$stmtR = $conn->prepare($sqlR);
if ($stmtR) {
    $stmtR->bind_param('i', $rapport['demande_id']);
    $stmtR->execute();
    $resR = $stmtR->get_result();
    while ($row = $resR->fetch_assoc()) {
        $resultats[] = $row;
    }
    $stmtR->close();
}

$stabilite = null;
$stabiliteLignes = [];
$sqlS = "
    SELECT *
    FROM rapport_stabilite
    WHERE rapport_id = ?
    LIMIT 1
";
$stmtS = $conn->prepare($sqlS);
if ($stmtS) {
    $stmtS->bind_param('s', $idRapport);
    $stmtS->execute();
    $resS = $stmtS->get_result();
    if ($resS && $resS->num_rows > 0) {
        $stabilite = $resS->fetch_assoc();
        $sqlSL = "
            SELECT
                l.*,
                GROUP_CONCAT(
                    CONCAT(
                        COALESCE(c.label, ''),
                        CASE
                            WHEN c.code IS NOT NULL AND c.code <> '' THEN CONCAT(' - ', c.code)
                            ELSE ''
                        END,
                        CASE
                            WHEN c.description IS NOT NULL AND c.description <> '' THEN CONCAT(' : ', c.description)
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
            $stmtSL->bind_param('i', $stabilite['id']);
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

class LaboReportPDF extends TCPDF
{
    public string $layoutCode = 'format_1';
    public string $tunisDate = '';
    public string $boNumber = '';
    public string $duplicateLabel = '';
    public string $footerObs = '';
    public string $langCode = 'fr';

    public function Header(): void
    {
        $this->SetTextColor(0, 0, 0);
        $lang = $this->langCode;

        if ($this->layoutCode === 'format_3') {
            pdfSetFont($this, $lang, '', 10);
            $this->SetXY(18, 12);
            $this->Cell(0, 5, rt('tunis_date', $lang) . ' ' . $this->tunisDate, 0, 1, report_align($lang));

            pdfSetFont($this, $lang, 'BU', 18);
            $this->SetXY(0, 22);
            $this->Cell(210, 8, rt('report_title', $lang), 0, 1, 'C');

            pdfSetFont($this, $lang, 'B', 11);
            $this->SetXY(0, 31);
            $this->Cell(210, 6, rt('bo_ref', $lang) . ' ' . translated($this->boNumber, $lang), 0, 1, 'C');
            return;
        }

        pdfSetFont($this, $lang, '', 10);
        $this->SetXY(15, 10);
        $this->Cell(0, 5, rt('tunis_date', $lang) . ' ' . $this->tunisDate, 0, 1, report_align($lang));

        if ($this->duplicateLabel !== '') {
            $this->SetAlpha(0.08);
            $this->StartTransform();
            $this->Rotate(45, 120, 70);
            pdfSetFont($this, $lang, 'B', 34);
            $this->Text(98, 42, translated($this->duplicateLabel, $lang));
            $this->StopTransform();
            $this->SetAlpha(1);
        }

        pdfSetFont($this, $lang, 'BU', 17);
        $this->SetXY(0, 18);
        $this->Cell(210, 7, rt('report_title', $lang), 0, 1, 'C');

        pdfSetFont($this, $lang, 'B', 11);
        $this->SetXY(0, 26);
        $this->Cell(210, 5, rt('bo_ref', $lang) . ' ' . translated($this->boNumber, $lang), 0, 1, 'C');
    }

    public function Footer(): void
    {
        $this->SetY(-10);
        pdfSetFont($this, $this->langCode, '', 9);
        $this->Cell(0, 4, $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

function renderObservationsBlock(TCPDF $pdf, array $rapport, string $lang, string $extraPrefix = ''): void
{
    $obs = cleanPdfText(v($rapport, 'observation'));
    if ($obs === '') {
        $obs = report_default_observation($lang);
    } else {
        $obs = translated($obs, $lang);
    }

    if ($extraPrefix !== '') {
        $obs = trim($extraPrefix) . "\n" . $obs;
    }

    $pdf->Ln(2);
    pdfSetFont($pdf, $lang, 'U', 7);
    $pdf->Cell(0, 4, rt('observations', $lang), 0, 1, report_align($lang));
    pdfSetFont($pdf, $lang, '', 7);
    $pdf->MultiCell(0, 3.2, $obs, 0, report_align($lang), false, 1);
}

function drawInfoColumn(TCPDF $pdf, float $x, float $y, float $columnW, array $lines, string $lang): float
{
    $currentY = $y;
    foreach ($lines as $item) {
        $label = isset($item[0]) ? trim((string)$item[0]) : '';
        $value = isset($item[1]) ? trim(translated($item[1], $lang)) : '';

        if ($label !== '') {
            $text = '<b>' . e($label) . '</b> ' . e($value);
        } else {
            $text = e($value);
        }

        $text = report_wrap_html($text, $lang);

        $pdf->SetXY($x, $currentY);
        pdfSetFont($pdf, $lang, '', 10);
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
            report_align($lang),
            true
        );

        $currentY = $pdf->GetY();
    }

    return $currentY;
}

function topInfoTwoCols(TCPDF $pdf, array $rapport, array $echantillon, string $lang, string $layout = 'format_1', array $extraLeftLines = [], array $extraRightLines = []): void
{
    $leftX = 18;
    $rightX = 112;
    $y = ($layout === 'format_3') ? 42 : 38;
    $leftW = 86;
    $rightW = 80;

    $leftLines = [
        [rt('dossier_ref', $lang), v($rapport, 'ref_dossier')],
        [rt('labo_ref', $lang), v($echantillon, 'ref_labo', v($rapport, 'ref_demande'))],
        [rt('client_name', $lang), v($rapport, 'client_name')],
        [$layout === 'format_3' ? rt('provenance', $lang) : rt('origin', $lang), v($rapport, 'origine_provenance')],
        [rt('producer', $lang), v($rapport, 'producteur')],
        [rt('importer', $lang), v($rapport, 'importateur')],
        [rt('product_name', $lang), v($echantillon, 'nom_produit')],
        [rt('product_temp_reception', $lang), v($echantillon, 'temperature_reception')],
        [rt('production_date', $lang), formatDateFr(v($echantillon, 'date_production'))],
        [$layout === 'format_3' ? rt('lot_no_full', $lang) : rt('lot_no', $lang), v($echantillon, 'numero_lot')],
    ];

    $rightLines = [
        [rt('address', $lang), buildAddress($rapport)],
        [rt('sampled_by', $lang), v($rapport, 'preleve_par')],
        [rt('sampling_date', $lang), formatDateFr(v($rapport, 'date_prelevement'))],
        [rt('reception_date', $lang), formatDateFr(v($rapport, 'date_reception'))],
        [rt('analysis_start', $lang), formatDateFr(v($rapport, 'date_debut_analyse'))],
        [rt('analysis_end', $lang), formatDateFr(v($rapport, 'date_fin_analyse'))],
        [rt('dlc', $lang), formatDateFr(v($echantillon, 'dlc'))],
        [rt('customer_product_code', $lang), v($echantillon, 'code_produit_demandeur')],
    ];

    if (!empty($extraLeftLines)) {
        $leftLines = array_merge($leftLines, $extraLeftLines);
    }
    if (!empty($extraRightLines)) {
        $rightLines = array_merge($rightLines, $extraRightLines);
    }

    $leftEndY = drawInfoColumn($pdf, $leftX, $y, $leftW, $leftLines, $lang);
    $rightEndY = drawInfoColumn($pdf, $rightX, $y, $rightW, $rightLines, $lang);
    $minY = ($layout === 'format_3') ? 0 : 84;
    $pdf->SetY(max($leftEndY, $rightEndY, $minY));
}

function signSimple(TCPDF $pdf, array $rapport, string $lang): void
{
    pdfSetFont($pdf, $lang, 'B', 10);
    $pdf->Cell(95, 6, rt('trial_managers', $lang), 0, 0, report_align($lang));
    $pdf->Cell(0, 6, rt('technical_manager', $lang), 0, 1, 'R');
    $pdf->Ln(0);

    pdfSetFont($pdf, $lang, '', 10);
    $respEssai = trim((string)v($rapport, 'responsable_essai'));
    $coRespEssai = trim((string)v($rapport, 'co_responsable_essai'));
    $respTech = trim((string)v($rapport, 'responsable_technique'));

    if ($respEssai !== '' && $coRespEssai !== '') {
        $left = $respEssai . ' / ' . $coRespEssai;
    } elseif ($respEssai !== '') {
        $left = $respEssai;
    } elseif ($coRespEssai !== '') {
        $left = $coRespEssai;
    } else {
        $left = '';
    }

    $right = $respTech !== '' ? $respTech : '';
    $pdf->Cell(95, 6, translated($left, $lang), 0, 0, report_align($lang));
    $pdf->Cell(0, 6, translated($right, $lang), 0, 1, 'R');
}

function renderResultTable(array $resultats, string $lang): string
{
    $tableAlign = report_text_align_css($lang);
    $dir = report_html_dir($lang);

    $html = '
    <table border="0.6" cellpadding="4" cellspacing="0" width="100%" dir="' . $dir . '">
        <tr>
            <td width="48%" align="center"><b>' . e(rt('analyses', $lang)) . '</b></td>
            <td width="34%" align="center"><b>' . e(rt('methods', $lang)) . '</b></td>
            <td width="18%" align="center"><b>' . e(rt('results', $lang)) . '</b></td>
        </tr>';

    foreach ($resultats as $r) {
        $analyse = translated(v($r, 'analyse'), $lang);
        if ((int)v($r, 'is_accredited', 1) === 0) {
            $analyse .= ' (*)';
        }

        $methode = translated(v($r, 'methode'), $lang);
        $res = trim(translated(v($r, 'resultat'), $lang) . ' ' . translated(v($r, 'unite'), $lang));

        $html .= '
        <tr>
            <td width="48%" align="' . $tableAlign . '" border="0">' . e($analyse) . '</td>
            <td width="34%" align="' . $tableAlign . '" border="0">' . e($methode) . '</td>
            <td width="18%" align="center" border="0">' . e($res) . '</td>
        </tr>';
    }

    $html .= '</table>';
    return $html;
}

function renderFormat1(TCPDF $pdf, array $rapport, array $echantillon, array $resultats, string $lang): void
{
    $pdf->Ln(2);
    topInfoTwoCols($pdf, $rapport, $echantillon, $lang, 'format_1');
    $pdf->Ln(3);
    pdfSetFont($pdf, $lang, '', 10);
    $pdf->writeHTML(renderResultTable($resultats, $lang), true, false, false, false, '');
    $pdf->Ln(1);

    $note = formatNotes(translated(v($rapport, 'note_bas_page'), $lang));
    if (trim($note) !== '') {
        pdfSetFont($pdf, $lang, '', 8);
        $pdf->MultiCell(0, 4, $note, 0, report_align($lang), false, 1);
    }

    signSimple($pdf, $rapport, $lang);
    $pdf->Ln(40);
    renderObservationsBlock($pdf, $rapport, $lang);
}

function renderFormat2(TCPDF $pdf, array $rapport, array $echantillon, array $resultats, string $lang): void
{
    $pdf->Ln(2);
    topInfoTwoCols($pdf, $rapport, $echantillon, $lang, 'format_2');
    $pdf->Ln(3);
    pdfSetFont($pdf, $lang, '', 10);
    $pdf->writeHTML(renderResultTable($resultats, $lang), true, false, false, false, '');
    $pdf->Ln(1);

    $note = formatNotes(translated(v($rapport, 'note_bas_page'), $lang));
    if (trim($note) !== '') {
        pdfSetFont($pdf, $lang, '', 8);
        $pdf->MultiCell(0, 4, $note, 0, report_align($lang), false, 1);
    }

    signSimple($pdf, $rapport, $lang);
    $pdf->Ln(40);
    renderObservationsBlock($pdf, $rapport, $lang);
}

function renderFormat3(TCPDF $pdf, array $rapport, array $echantillon, ?array $stabilite, array $stabiliteLignes, string $lang): void
{
    $pdf->Ln(2);
    pdfSetFont($pdf, $lang, '', 10);

    $phSolid = '';
    foreach ($stabiliteLignes as $line) {
        if (stripos((string)v($line, 'parametre'), 'pH') !== false && stripos((string)v($line, 'sous_parametre'), 'solide') !== false) {
            $phSolid = v($line, 'incubation_ambiante');
            break;
        }
    }

    $extraLeftLines = [];
    if (trim((string)v($echantillon, 'nom_commercial')) !== '') {
        $extraLeftLines[] = ['', v($echantillon, 'nom_commercial')];
    }
    $extraLeftLines[] = [rt('ph_before_incubation', $lang), $phSolid];

    topInfoTwoCols($pdf, $rapport, $echantillon, $lang, 'format_3', $extraLeftLines);
    $pdf->Ln(1);

    $dir = report_html_dir($lang);
    $align = report_text_align_css($lang);
    $normeReference = translated(v($stabilite, 'norme_reference', 'NF V08 408-1997'), $lang);

    $html = '
    <table border="0.6" cellpadding="3" cellspacing="0" width="100%" dir="' . $dir . '">
        <tr>
            <td colspan="6" align="center">
                <b>' . e(rt('analysis_upper', $lang)) . '</b><br/>
                ' . e(rt('stability_control', $lang)) . ' (' . e($normeReference) . ')
            </td>
        </tr>
        <tr>
            <td width="30%" rowspan="3" align="center"><b>' . e(rt('parameters', $lang)) . '</b></td>
            <td width="39%" colspan="3" align="center"><b>' . e(rt('incubation_results', $lang)) . '</b></td>
            <td width="31%" rowspan="3" align="center">
                <b>' . e(rt('decision_criteria', $lang)) . '</b><br/>
                <i>(' . e(rt('according_to', $lang)) . ' ' . e($normeReference) . ')</i>
            </td>
        </tr>
        <tr>
            <td width="13%" align="center"><b>' . e(rt('incubation_at', $lang)) . '</b></td>
            <td width="13%" align="center"><b>' . e(rt('incubation', $lang)) . '</b></td>
            <td width="13%" align="center"><b>' . e(rt('incubation', $lang)) . '</b></td>
        </tr>
        <tr>
            <td width="13%" align="center"><b>' . e(rt('ambient_temp', $lang)) . '</b></td>
            <td width="13%" align="center"><b>' . e(rt('at_37', $lang)) . '</b></td>
            <td width="13%" align="center"><b>' . e(rt('at_55', $lang)) . '</b></td>
        </tr>';

    for ($i = 0, $count = count($stabiliteLignes); $i < $count; $i++) {
        $row = $stabiliteLignes[$i];
        $parametre = trim(translated(v($row, 'parametre'), $lang));
        $sousParametre = trim(translated(v($row, 'sous_parametre'), $lang));
        $criteresText = trim(v($row, 'criteres_text'));
        if ($criteresText === '') {
            $criteresText = trim((string)v($row, 'critere_decision'));
        }
        $criteresText = translated($criteresText, $lang);

        $criteresHtml = '';
        if ($criteresText !== '') {
            $lines = explode("\n", $criteresText);
            $formatted = [];
            foreach ($lines as $line) {
                if (preg_match('/^(.*?)\s*-\s*(.*?)\s*:\s*(.*)$/', $line, $m)) {
                    $label = trim($m[1]);
                    $code = trim($m[2]);
                    $desc = trim($m[3]);
                    $formatted[] = '<b>' . e($label) . ' (' . e($code) . ') : </b><br/>' . e($desc);
                } else {
                    $formatted[] = e($line);
                }
            }
            $criteresHtml = implode('<br/>', $formatted);
        }

        $amb = e(translated(v($row, 'incubation_ambiante'), $lang));
        $c37 = e(translated(v($row, 'incubation_37c'), $lang));
        $c55 = e(translated(v($row, 'incubation_55c'), $lang));

        if (strcasecmp($parametre, translated('Odeur', $lang)) === 0) {
            $nextRow = $stabiliteLignes[$i + 1] ?? null;
            if ($nextRow && strcasecmp(trim(translated(v($nextRow, 'parametre'), $lang)), translated('Texture', $lang)) === 0) {
                $html .= '
                <tr>
                    <td width="20%" align="' . $align . '"><b>' . e($parametre) . '</b></td>
                    <td width="10%" align="' . $align . '">' . e($sousParametre) . '</td>
                    <td width="13%" align="center">' . $amb . '</td>
                    <td width="13%" align="center">' . $c37 . '</td>
                    <td width="13%" align="center">' . $c55 . '</td>
                    <td width="31%" rowspan="2" align="' . $align . '">' . $criteresHtml . '</td>
                </tr>
                <tr>
                    <td width="20%" align="' . $align . '"><b>' . e(translated(v($nextRow, 'parametre'), $lang)) . '</b></td>
                    <td width="10%" align="' . $align . '">' . e(translated(v($nextRow, 'sous_parametre'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(translated(v($nextRow, 'incubation_ambiante'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(translated(v($nextRow, 'incubation_37c'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(translated(v($nextRow, 'incubation_55c'), $lang)) . '</td>
                </tr>';
                $i++;
                continue;
            }
        }

        if (stripos($parametre, translated('pH', $lang)) !== false) {
            $nextRow = $stabiliteLignes[$i + 1] ?? null;
            if ($nextRow && stripos((string)translated(v($nextRow, 'parametre'), $lang), translated('pH', $lang)) !== false) {
                $html .= '
                <tr>
                    <td width="20%" rowspan="2" align="' . $align . '"><b>' . e($parametre) . '</b></td>
                    <td width="10%" align="' . $align . '">' . e($sousParametre) . '</td>
                    <td width="13%" align="center">' . $amb . '</td>
                    <td width="13%" align="center">' . $c37 . '</td>
                    <td width="13%" align="center">' . $c55 . '</td>
                    <td width="31%" rowspan="2" align="' . $align . '">' . $criteresHtml . '</td>
                </tr>
                <tr>
                    <td width="10%" align="' . $align . '">' . e(translated(v($nextRow, 'sous_parametre'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(translated(v($nextRow, 'incubation_ambiante'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(translated(v($nextRow, 'incubation_37c'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(translated(v($nextRow, 'incubation_55c'), $lang)) . '</td>
                </tr>';
                $i++;
                continue;
            }
        }

        if (stripos($parametre, translated('Examen microscopique', $lang)) !== false) {
            $nextRow = $stabiliteLignes[$i + 1] ?? null;
            if ($nextRow && stripos((string)translated(v($nextRow, 'parametre'), $lang), translated('Examen microscopique', $lang)) !== false) {
                $html .= '
                <tr>
                    <td width="20%" rowspan="2" align="' . $align . '"><b>' . e($parametre) . '</b></td>
                    <td width="10%" align="' . $align . '">' . e($sousParametre) . '</td>
                    <td width="13%" align="center">' . $amb . '</td>
                    <td width="13%" align="center">' . $c37 . '</td>
                    <td width="13%" align="center">' . $c55 . '</td>
                    <td width="31%" rowspan="2" align="' . $align . '">' . $criteresHtml . '</td>
                </tr>
                <tr>
                    <td width="10%" align="' . $align . '">' . e(translated(v($nextRow, 'sous_parametre'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(translated(v($nextRow, 'incubation_ambiante'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(translated(v($nextRow, 'incubation_37c'), $lang)) . '</td>
                    <td width="13%" align="center">' . e(translated(v($nextRow, 'incubation_55c'), $lang)) . '</td>
                </tr>';
                $i++;
                continue;
            }
        }

        $html .= '
        <tr>
            <td width="20%" align="' . $align . '"><b>' . e($parametre) . '</b></td>
            <td width="10%" align="' . $align . '">' . e($sousParametre) . '</td>
            <td width="13%" align="center">' . $amb . '</td>
            <td width="13%" align="center">' . $c37 . '</td>
            <td width="13%" align="center">' . $c55 . '</td>
            <td width="31%" align="' . $align . '">' . $criteresHtml . '</td>
        </tr>';
    }

    $declarationText = translated(v($stabilite, 'declaration_text', v($rapport, 'declaration_text')), $lang);
    $html .= '
        <tr>
            <td colspan="6" align="center">
                <b>' . e(rt('declaration', $lang)) . ' ' . e($declarationText) . '</b>
            </td>
        </tr>
    </table><br/>';

    pdfSetFont($pdf, $lang, '', 8.4);
    $pdf->writeHTML($html, true, false, false, false, '');

    pdfSetFont($pdf, $lang, '', 8);
    if (trim((string)v($stabilite, 'note_1')) !== '') {
        $pdf->MultiCell(0, 4, translated(v($stabilite, 'note_1'), $lang), 0, report_align($lang), false, 1);
    }
    if (trim((string)v($stabilite, 'note_2')) !== '') {
        $pdf->MultiCell(0, 4, translated(v($stabilite, 'note_2'), $lang), 0, report_align($lang), false, 1);
    }

    signSimple($pdf, $rapport, $lang);
    $pdf->Ln(3);
    renderObservationsBlock($pdf, $rapport, $lang);
}

$layout = in_array($requestedLayout, $allowedLayouts, true)
    ? $requestedLayout
    : trim((string)v($rapport, 'report_layout', 'format_1'));
if ($layout === '') {
    $layout = 'format_1';
}

$pdf = new LaboReportPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('TCPDF');
$pdf->SetAuthor('Laboratoire');
$pdf->SetTitle(rt('report_title', $lang));
$pdf->SetSubject(rt('report_title', $lang));
$pdf->SetTextColor(0, 0, 0);
$pdf->SetDrawColor(80, 80, 80);
$pdf->SetFillColor(255, 255, 255);
$pdf->layoutCode = $layout;
$pdf->langCode = $lang;
$pdf->tunisDate = formatDateFr(v($rapport, 'tunis_date', date('Y-m-d')));
$pdf->boNumber = (string)v($rapport, 'ref_bo');
$pdf->duplicateLabel = (string)v($rapport, 'duplicate_label');
$pdf->footerObs = (string)v($rapport, 'observation');

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

$filename = 'rapport_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $idRapport) . '_' . $layout . '_' . $lang . '.pdf';
$pdf->Output($filename, 'I');
$conn->close();
?>
