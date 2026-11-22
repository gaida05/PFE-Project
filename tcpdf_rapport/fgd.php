<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . '/tcpdf/tcpdf.php');

/* =========================================================
   CONFIG
========================================================= */
$dbHost = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "labo_db";

/* =========================================================
   DB
========================================================= */
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("Erreur connexion BD : " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

/* =========================================================
   HELPERS
========================================================= */
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

function cleanPdfText($text)
{
    $text = trim((string)$text);
    $text = str_replace(["\\r\\n", "\\n", "\\r"], "\n", $text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    return $text;
}

function formatNotes($text)
{
    $text = cleanPdfText($text);
    if ($text === '') {
        return '';
    }

    $lines = explode("\n", $text);
    $formatted = '';
    $i = 1;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        if ($i === 1) {
            $formatted .= "(*) " . $line . "\n";
        } elseif ($i === 2) {
            $formatted .= "(**) " . $line . "\n";
        } else {
            $formatted .= "- " . $line . "\n";
        }
        $i++;
    }

    return trim($formatted);
}

/**
 * input examples from SQL:
 * Produit stable si - Critére 1 : texte...
 * output:
 * « Produit stable si - Critére 1 »
 * texte...
 */
function formatCritereBlock($text)
{
    $text = cleanPdfText($text);
    if ($text === '') return '';

    $blocks = explode("\n", $text);
    $out = [];

    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') continue;

        $labelPart = $block;
        $descPart = '';

        if (strpos($block, ' : ') !== false) {
            [$labelPart, $descPart] = explode(' : ', $block, 2);
        }

        $labelPart = trim($labelPart);
        $descPart = trim($descPart);

        if ($descPart !== '') {
            $out[] = '« ' . $labelPart . ' »' . "\n" . $descPart;
        } else {
            $out[] = '« ' . $labelPart . ' »';
        }
    }

    return implode("\n", $out);
}

/* =========================================================
   PARAMS
========================================================= */
$idRapport = isset($_GET['id_rapport']) ? trim($_GET['id_rapport']) : '';
$idEchantillon = isset($_GET['id_echantillon']) ? (int)$_GET['id_echantillon'] : 0;

if ($idRapport === '') {
    die("Erreur : id_rapport manquant");
}

/* =========================================================
   LOAD RAPPORT + DEMANDE + CLIENT
========================================================= */
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
        c.email
    FROM rapports r
    LEFT JOIN demande_analyse d ON d.id_demande = r.id_demande
    LEFT JOIN client c ON c.id_client = d.id_client
    WHERE r.id_rapport = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Erreur prepare rapport : " . $conn->error);
}
$stmt->bind_param("s", $idRapport);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    die("Rapport introuvable");
}
$rapport = $res->fetch_assoc();
$stmt->close();

/* =========================================================
   LOAD ECHANTILLONS
========================================================= */
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

/* =========================================================
   LOAD RESULTATS SIMPLES
========================================================= */
$resultats = [];
$sqlR = "
    SELECT analyse, methode, resultat, unite, ordre_affichage, is_accredited, result_note, report_section
    FROM resultat_analyse
    WHERE id_demande = ?
    ORDER BY ordre_affichage ASC, id ASC
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

/* =========================================================
   LOAD STABILITE
========================================================= */
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

/* =========================================================
   PDF CLASS
========================================================= */
class LaboReportPDF extends TCPDF
{
    public $layoutCode = 'format_1';
    public $tunisDate = '';
    public $boNumber = '';
    public $duplicateLabel = '';

    public function Header()
    {
        $this->SetTextColor(0, 0, 0);

        if ($this->layoutCode === 'format_3') {
            $this->SetFont('times', '', 10);
            $this->SetXY(18, 12);
            $this->Cell(0, 5, 'Tunis le : ' . $this->tunisDate, 0, 1, 'L');

            $this->SetFont('times', 'BU', 18);
            $this->SetXY(0, 22);
            $this->Cell(210, 8, "RAPPORT D'ANALYSES", 0, 1, 'C');

            $this->SetFont('times', 'B', 11);
            $this->SetXY(0, 31);
            $this->Cell(210, 6, 'Réf B.O : ' . $this->boNumber, 0, 1, 'C');
            return;
        }

        $this->SetFont('times', '', 10);
        $this->SetXY(15, 10);
        $this->Cell(0, 5, 'Tunis le : ' . $this->tunisDate, 0, 1, 'L');

        if ($this->duplicateLabel !== '') {
            $this->SetAlpha(0.08);
            $this->StartTransform();
            $this->Rotate(45, 120, 70);
            $this->SetFont('times', 'B', 34);
            $this->Text(98, 42, $this->duplicateLabel);
            $this->StopTransform();
            $this->SetAlpha(1);
        }

        $this->SetFont('times', 'BU', 17);
        $this->SetXY(0, 18);
        $this->Cell(210, 7, "RAPPORT D'ANALYSES", 0, 1, 'C');

        $this->SetFont('times', 'B', 11);
        $this->SetXY(0, 26);
        $this->Cell(210, 5, 'Réf B.O : ' . $this->boNumber, 0, 1, 'C');
    }

    public function Footer()
    {
        $this->SetY(-10);
        $this->SetFont('times', '', 9);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 4, $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

/* =========================================================
   CONTENT HELPERS
========================================================= */
function buildAddress($rapport)
{
    $parts = [];
    if (trim(v($rapport, 'adresse')) !== '') $parts[] = v($rapport, 'adresse');
    if (trim(v($rapport, 'ville')) !== '') $parts[] = v($rapport, 'ville');
    if (trim(v($rapport, 'pays')) !== '') $parts[] = v($rapport, 'pays');
    return implode(', ', $parts);
}

function renderObservationsBlock($pdf, $rapport, $extraPrefix = '')
{
    $obs = cleanPdfText(v($rapport, 'observation'));

    if ($obs === '') {
        $obs = "Ce rapport ne concerne que l'objet soumis à l'essai pour les différents paramètres testés.\n"
             . "Les résultats s'appliquent à l'échantillon tel qu'il a été reçu.\n"
             . "Ce document est la propriété du laboratoire, il ne doit en aucun cas être reproduit sans l'autorisation écrite du propriétaire.";
    }

    if ($extraPrefix !== '') {
        $obs = trim($extraPrefix) . "\n" . $obs;
    }

    $pdf->Ln(2);
    $pdf->SetFont('times', '', 7);
    $pdf->MultiCell(0, 3.2, "Observations :\n" . $obs, 0, 'L', false, 1);
}

function topInfoTwoCols($pdf, $rapport, $echantillon, $layout = 'format_1')
{
    $pdf->SetFont('times', '', 10);

    $leftX = 18;
    $rightX = 112;
    $y = ($layout === 'format_3') ? 42 : 38;
    $lineH = ($layout === 'format_3') ? 4.5 : 5;

    $leftLines = [
        "Réf Dossier : " . v($rapport, 'ref_dossier'),
        "Réf Labo : " . v($echantillon, 'ref_labo', v($rapport, 'ref_demande')),
        "Nom du demandeur d'analyse : " . v($rapport, 'client_name'),
        "Origine : " . v($rapport, 'origine_provenance'),
        "Producteur : " . v($rapport, 'producteur'),
        "Importateur : " . v($rapport, 'importateur'),
        "Nom du produit : " . v($echantillon, 'nom_produit'),
        "T° du Produit à la réception : " . v($echantillon, 'temperature_reception'),
        "Date de production : " . formatDateFr(v($echantillon, 'date_production')),
        "N° Lot : " . v($echantillon, 'numero_lot'),
    ];

    $rightLines = [
        "Adresse: " . buildAddress($rapport),
        "Ech. Prélevé par : " . v($rapport, 'preleve_par'),
        "Date du Prélèvement : " . formatDateFr(v($rapport, 'date_prelevement')),
        "Date de réception : " . formatDateFr(v($rapport, 'date_reception')),
        "Date début analyse: " . formatDateFr(v($rapport, 'date_debut_analyse')),
        "Date fin analyse : " . formatDateFr(v($rapport, 'date_fin_analyse')),
        "D.L.C: " . formatDateFr(v($echantillon, 'dlc')),
        "Code produit donné par le demandeur : " . v($echantillon, 'code_produit_demandeur'),
    ];

    $pdf->SetXY($leftX, $y);
    foreach ($leftLines as $line) {
        $pdf->SetX($leftX);
        $pdf->MultiCell(86, $lineH, $line, 0, 'L', false, 1);
    }

    $pdf->SetXY($rightX, $y);
    foreach ($rightLines as $line) {
        $pdf->SetX($rightX);
        $pdf->MultiCell(80, $lineH, $line, 0, 'L', false, 1);
    }

    $pdf->SetY(max($pdf->GetY(), ($layout === 'format_3' ? 92 : 84)));
}

function signSimple($pdf, $rapport)
{
    $pdf->Ln(4);
    $pdf->SetFont('times', 'B', 10);
    $pdf->Cell(95, 6, "Les responsables de l'essai", 0, 0, 'L');
    $pdf->Cell(0, 6, "Responsable technique des laboratoires", 0, 1, 'R');

    $pdf->Ln(0);
    $pdf->SetFont('times', '', 10);

    $respEssai = trim(v($rapport, 'responsable_essai'));
    $coRespEssai = trim(v($rapport, 'co_responsable_essai'));
    $respTech = trim(v($rapport, 'responsable_technique'));

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

    $pdf->Cell(95, 6, $left, 0, 0, 'L');
    $pdf->Cell(0, 6, $right, 0, 1, 'R');
}

/* =========================================================
   RENDER FORMAT 1
========================================================= */
function renderFormat1($pdf, $rapport, $echantillon, $resultats)
{
    $pdf->Ln(2);
    topInfoTwoCols($pdf, $rapport, $echantillon, 'format_1');
    $pdf->Ln(3);
    $pdf->SetFont('times', '', 10);

    $html = '
    <table border="0.6" cellpadding="4" cellspacing="0" width="100%">
        <tr>
            <td width="48%" align="center"><b>Analyses</b></td>
            <td width="34%" align="center"><b>Références Méthodes</b></td>
            <td width="18%" align="center"><b>Résultats</b></td>
        </tr>';

    $last = count($resultats) - 1;

    foreach ($resultats as $idx => $r) {
        $analyse = e(v($r, 'analyse'));
        if ((int)v($r, 'is_accredited', 1) === 0) {
            $analyse .= ' (*)';
        }
        $res = trim(v($r, 'resultat') . ' ' . v($r, 'unite'));

        $bottomBorder = ($idx === $last) ? 'B' : '';

        $html .= '
        <tr>
            <td width="48%" border="LR' . $bottomBorder . '">' . $analyse . '</td>
            <td width="34%" border="LR' . $bottomBorder . '">' . e(v($r, 'methode')) . '</td>
            <td width="18%" align="center" border="LR' . $bottomBorder . '">' . e($res) . '</td>
        </tr>';
    }

    $html .= '</table>';
    $pdf->writeHTML($html, true, false, false, false, '');

    $pdf->Ln(1);
    $pdf->SetFont('times', '', 8);
    $note = formatNotes(v($rapport, 'note_bas_page'));
    if ($note !== '') {
        $pdf->MultiCell(0, 4, $note, 0, 'L', false, 1);
    }

    signSimple($pdf, $rapport);
    renderObservationsBlock($pdf, $rapport);
}

/* =========================================================
   RENDER FORMAT 2
========================================================= */
function renderFormat2($pdf, $rapport, $echantillon, $resultats)
{
    $pdf->Ln(2);
    topInfoTwoCols($pdf, $rapport, $echantillon, 'format_2');
    $pdf->Ln(3);
    $pdf->SetFont('times', '', 10);

    $html = '
    <table border="0.6" cellpadding="4" cellspacing="0" width="100%">
        <tr>
            <td width="48%" align="center"><b>Analyses</b></td>
            <td width="34%" align="center"><b>Références Méthodes</b></td>
            <td width="18%" align="center"><b>Résultats</b></td>
        </tr>';

    $last = count($resultats) - 1;

    foreach ($resultats as $idx => $r) {
        $analyse = e(v($r, 'analyse'));
        if ((int)v($r, 'is_accredited', 1) === 0) {
            $analyse .= ' (*)';
        }
        $res = trim(v($r, 'resultat') . ' ' . v($r, 'unite'));

        $bottomBorder = ($idx === $last) ? 'B' : '';

        $html .= '
        <tr>
            <td width="48%" border="LR' . $bottomBorder . '">' . $analyse . '</td>
            <td width="34%" border="LR' . $bottomBorder . '">' . e(v($r, 'methode')) . '</td>
            <td width="18%" align="center" border="LR' . $bottomBorder . '">' . e($res) . '</td>
        </tr>';
    }

    $html .= '</table>';
    $pdf->writeHTML($html, true, false, false, false, '');

    $pdf->Ln(1);
    $pdf->SetFont('times', '', 8);
    $note = formatNotes(v($rapport, 'note_bas_page'));
    if ($note !== '') {
        $pdf->MultiCell(0, 4, $note, 0, 'L', false, 1);
    }

    signSimple($pdf, $rapport);
    renderObservationsBlock($pdf, $rapport);
}

/* =========================================================
   RENDER FORMAT 3
========================================================= */
function renderFormat3($pdf, $rapport, $echantillon, $stabilite, $stabiliteLignes)
{
    $pdf->Ln(2);
    $pdf->SetFont('times', '', 10);

    $leftX = 18;
    $rightX = 112;
    $y = 42;
    $lineH = 4.5;

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

    $leftLines = [
        "Réf Dossier : " . v($rapport, 'ref_dossier'),
        "Réf labo : " . v($echantillon, 'ref_labo'),
        "Nom du demandeur d'analyse : " . v($rapport, 'client_name'),
        "Provenance : " . v($rapport, 'origine_provenance'),
        "Producteur : " . v($rapport, 'producteur'),
        "Importateur : " . v($rapport, 'importateur'),
        "Nom du produit : " . v($echantillon, 'nom_produit'),
        "\"Saveurs\"285g",
        "T° du Produit à la réception : " . v($echantillon, 'temperature_reception'),
        "Date de production : " . formatDateFr(v($echantillon, 'date_production')),
        "N° du lot : " . v($echantillon, 'numero_lot'),
        "pH avant incubation (phase solide): " . $phSolid,
    ];

    $rightLines = [
        "Adresse : " . buildAddress($rapport),
        "Ech. Prélevé par : " . v($rapport, 'preleve_par'),
        "Date du Prélèvement : " . formatDateFr(v($rapport, 'date_prelevement')),
        "Date de réception : " . formatDateFr(v($rapport, 'date_reception')),
        "Date début analyse: " . formatDateFr(v($rapport, 'date_debut_analyse')),
        "Date fin analyse : " . formatDateFr(v($rapport, 'date_fin_analyse')),
        "D.L.C: " . formatDateFr(v($echantillon, 'dlc')),
        "Code produit donné par le demandeur : " . v($echantillon, 'code_produit_demandeur'),
    ];

    $pdf->SetXY($leftX, $y);
    foreach ($leftLines as $line) {
        $pdf->SetX($leftX);
        $pdf->MultiCell(86, $lineH, $line, 0, 'L', false, 1);
    }

    $pdf->SetXY($rightX, $y);
    foreach ($rightLines as $line) {
        $pdf->SetX($rightX);
        $pdf->MultiCell(80, $lineH, $line, 0, 'L', false, 1);
    }

    $pdf->SetY(max($pdf->GetY(), 96));
    $pdf->Ln(1);

    $html = '
    <table border="0.6" cellpadding="3" cellspacing="0" width="100%">
        <tr>
            <td colspan="5" align="center">
                <b>ANALYSE</b><br/>
                Contrôle de la stabilité des produits appertisés et assimilés (' . e(v($stabilite, 'norme_reference', 'NF V08 408-1997')) . ')
            </td>
        </tr>
        <tr>
            <td width="30%" rowspan="3" align="center"><b>Paramètres</b></td>
            <td width="39%" colspan="3" align="center"><b>Résultats</b></td>
            <td width="31%" rowspan="3" align="center">
                <b>Critères de décision * (stabilité)</b><br/>
                <i>(Selon NF V08 408-1997)</i>
            </td>
        </tr>
        <tr>
            <td width="13%" align="center"><b>Incubation à</b></td>
            <td width="13%" align="center"><b>Incubation</b></td>
            <td width="13%" align="center"><b>Incubation</b></td>
        </tr>
        <tr>
            <td width="13%" align="center"><b>T° ambiante (7J)</b></td>
            <td width="13%" align="center"><b>à 37°C/7J</b></td>
            <td width="13%" align="center"><b>à 55 °C/7J</b></td>
        </tr>';

    for ($i = 0; $i < count($stabiliteLignes); $i++) {
        $row = $stabiliteLignes[$i];

        $parametre = trim(v($row, 'parametre'));
        $sousParametre = trim(v($row, 'sous_parametre'));

        $criteresText = trim(v($row, 'criteres_text'));
        if ($criteresText === '') {
            $criteresText = trim(v($row, 'critere_decision'));
        }
        $criteresHtml = nl2br(e(formatCritereBlock($criteresText)));

        /*
         * Odeur + Texture => one criteria cell for both
         */
        if (strcasecmp($parametre, 'Odeur') === 0) {
            $nextRow = isset($stabiliteLignes[$i + 1]) ? $stabiliteLignes[$i + 1] : null;

            if ($nextRow && strcasecmp(trim(v($nextRow, 'parametre')), 'Texture') === 0) {
                $paramFull1 = '<b>' . e($parametre) . '</b>';
                if ($sousParametre !== '') {
                    $paramFull1 .= '<br/>' . e($sousParametre);
                }

                $paramFull2 = '<b>' . e(v($nextRow, 'parametre')) . '</b>';
                if (trim(v($nextRow, 'sous_parametre')) !== '') {
                    $paramFull2 .= '<br/>' . e(v($nextRow, 'sous_parametre'));
                }

                $html .= '
                <tr>
                    <td width="30%">' . $paramFull1 . '</td>
                    <td width="13%" align="center">' . e(v($row, 'incubation_ambiante')) . '</td>
                    <td width="13%" align="center">' . e(v($row, 'incubation_37c')) . '</td>
                    <td width="13%" align="center">' . e(v($row, 'incubation_55c')) . '</td>
                    <td width="31%" rowspan="2">' . $criteresHtml . '</td>
                </tr>
                <tr>
                    <td width="30%">' . $paramFull2 . '</td>
                    <td width="13%" align="center">' . e(v($nextRow, 'incubation_ambiante')) . '</td>
                    <td width="13%" align="center">' . e(v($nextRow, 'incubation_37c')) . '</td>
                    <td width="13%" align="center">' . e(v($nextRow, 'incubation_55c')) . '</td>
                </tr>';

                $i++;
                continue;
            }
        }

        /*
         * pH => 2 lines under one parameter cell
         */
        if (stripos($parametre, 'pH') !== false) {
            $nextRow = isset($stabiliteLignes[$i + 1]) ? $stabiliteLignes[$i + 1] : null;

            if ($nextRow && stripos(trim(v($nextRow, 'parametre')), 'pH') !== false) {
                $paramTitle = '<b>' . e($parametre) . '</b>';

                $sub1 = ($sousParametre !== '') ? e($sousParametre) : '';
                $sub2 = e(v($nextRow, 'sous_parametre'));

                $html .= '
                <tr>
                    <td width="30%" rowspan="2">' . $paramTitle . '<br/>' . $sub1 . '</td>
                    <td width="13%" align="center">' . e(v($row, 'incubation_ambiante')) . '</td>
                    <td width="13%" align="center">' . e(v($row, 'incubation_37c')) . '</td>
                    <td width="13%" align="center">' . e(v($row, 'incubation_55c')) . '</td>
                    <td width="31%" rowspan="2">' . $criteresHtml . '</td>
                </tr>
                <tr>
                    <td width="13%" align="center">' . e(v($nextRow, 'incubation_ambiante')) . '</td>
                    <td width="13%" align="center">' . e(v($nextRow, 'incubation_37c')) . '</td>
                    <td width="13%" align="center">' . e(v($nextRow, 'incubation_55c')) . '</td>
                </tr>';

                $i++;
                continue;
            }
        }

        /*
         * Examen microscopique => 2 lines under one parameter cell
         */
        if (stripos($parametre, 'Examen microscopique') !== false) {
            $nextRow = isset($stabiliteLignes[$i + 1]) ? $stabiliteLignes[$i + 1] : null;

            if ($nextRow && stripos(trim(v($nextRow, 'parametre')), 'Examen microscopique') !== false) {
                $paramTitle = '<b>' . e($parametre) . '</b>';
                $sub1 = ($sousParametre !== '') ? e($sousParametre) : '';
                $sub2 = e(v($nextRow, 'sous_parametre'));

                $html .= '
                <tr>
                    <td width="30%" rowspan="2">' . $paramTitle . '<br/>' . $sub1 . '</td>
                    <td width="13%" align="center">' . e(v($row, 'incubation_ambiante')) . '</td>
                    <td width="13%" align="center">' . e(v($row, 'incubation_37c')) . '</td>
                    <td width="13%" align="center">' . e(v($row, 'incubation_55c')) . '</td>
                    <td width="31%" rowspan="2">' . $criteresHtml . '</td>
                </tr>
                <tr>
                    <td width="13%" align="center">' . e(v($nextRow, 'incubation_ambiante')) . '</td>
                    <td width="13%" align="center">' . e(v($nextRow, 'incubation_37c')) . '</td>
                    <td width="13%" align="center">' . e(v($nextRow, 'incubation_55c')) . '</td>
                </tr>';

                $i++;
                continue;
            }
        }

        /*
         * Normal row
         */
        $paramFull = '<b>' . e($parametre) . '</b>';
        if ($sousParametre !== '') {
            $paramFull .= '<br/>' . e($sousParametre);
        }

        $html .= '
        <tr>
            <td width="30%">' . $paramFull . '</td>
            <td width="13%" align="center">' . e(v($row, 'incubation_ambiante')) . '</td>
            <td width="13%" align="center">' . e(v($row, 'incubation_37c')) . '</td>
            <td width="13%" align="center">' . e(v($row, 'incubation_55c')) . '</td>
            <td width="31%">' . $criteresHtml . '</td>
        </tr>';
    }

    $html .= '
        <tr>
            <td colspan="5" align="center">
                <b>Déclaration : ' . e(v($stabilite, 'declaration_text', v($rapport, 'declaration_text'))) . '</b>
            </td>
        </tr>
    </table><br/>';

    $pdf->SetFont('times', '', 8.4);
    $pdf->writeHTML($html, true, false, false, false, '');

    $pdf->Ln(1);
    $pdf->SetFont('times', '', 8);

    $note1 = formatNotes(v($stabilite, 'note_1'));
    $note2 = formatNotes(v($stabilite, 'note_2'));

    if ($note1 !== '') {
        $pdf->MultiCell(0, 4, $note1, 0, 'L', false, 1);
    }
    if ($note2 !== '') {
        $pdf->MultiCell(0, 4, $note2, 0, 'L', false, 1);
    }

    signSimple($pdf, $rapport);

    $extra = "NB. Cette déclaration ne peut pas être étendue sur un lot et ne concerne que l'objet soumis à l'essai.";
    renderObservationsBlock($pdf, $rapport, $extra);
}

/* =========================================================
   INIT PDF
========================================================= */
$layout = trim(v($rapport, 'report_layout', 'format_1'));
if ($layout === '') {
    $layout = 'format_1';
}

$pdf = new LaboReportPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('TCPDF');
$pdf->SetAuthor('Laboratoire');
$pdf->SetTitle("Rapport d'analyses");
$pdf->SetSubject("Rapport d'analyses");
$pdf->SetTextColor(0, 0, 0);
$pdf->SetDrawColor(80, 80, 80);
$pdf->SetFillColor(255, 255, 255);

$pdf->layoutCode = $layout;
$pdf->tunisDate = formatDateFr(v($rapport, 'tunis_date', date('Y-m-d')));
$pdf->boNumber = v($rapport, 'ref_bo');
$pdf->duplicateLabel = v($rapport, 'duplicate_label');

if ($layout === 'format_3') {
    $pdf->SetMargins(18, 40, 18);
    $pdf->SetAutoPageBreak(true, 16);
} else {
    $pdf->SetMargins(15, 34, 15);
    $pdf->SetAutoPageBreak(true, 16);
}
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(6);
$pdf->AddPage();

/* =========================================================
   RENDER SWITCH
========================================================= */
switch ($layout) {
    case 'format_2':
        renderFormat2($pdf, $rapport, $echantillon, $resultats);
        break;

    case 'format_3':
        renderFormat3($pdf, $rapport, $echantillon, $stabilite, $stabiliteLignes);
        break;

    case 'format_1':
    default:
        renderFormat1($pdf, $rapport, $echantillon, $resultats);
        break;
}

/* =========================================================
   OUTPUT
========================================================= */
$pdf->Output('rapport_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $idRapport) . '.pdf', 'I');
$conn->close();
?>