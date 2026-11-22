<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'labo_db';
function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function maskValue($value, int $visible = 2): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '***';
    }
    if (mb_strlen($value) <= $visible) {
        return $value . '***';
    }

    return mb_substr($value, 0, $visible) . '***';
}
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    respond([
        'success' => false,
        'message' => 'Erreur connexion BD : ' . $conn->connect_error
    ], 500);
}
$conn->set_charset('utf8mb4');
$action = isset($_GET['action']) ? trim($_GET['action']) : 'list_demandes';
if ($action !== 'list_demandes') {
    respond([
        'success' => false,
        'message' => 'Action non supportée'
    ], 400);
}
$month = isset($_GET['month']) ? trim($_GET['month']) : '';
$whereMonth = '';
$types = '';
$params = [];
if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
    $whereMonth = " WHERE DATE_FORMAT(COALESCE(d.date_creation, d.date_demande), '%Y-%m') = ? ";
    $types .= 's';
    $params[] = $month;
}
$sql = "
    SELECT
        d.id_demande,
        MIN(r.id_rapport) AS id_rapport,
        COALESCE(MIN(r.report_layout), 'format_1') AS default_layout,
        COALESCE(d.ref_demande, '-') AS ref_demande,
        COALESCE(c.id_client, 0) AS client_id,
        COALESCE(c.client_name, MAX(dc.client_name), 'Client inconnu') AS client_name,
        COALESCE(MAX(dc.date_demande), DATE_FORMAT(COALESCE(d.date_creation, d.date_demande), '%d/%m/%Y %H:%i')) AS date_demande,
        COALESCE(MAX(dc.date_reception), DATE_FORMAT(d.date_reception, '%d/%m/%Y %H:%i')) AS date_reception,
        COALESCE(MAX(dc.date_verif), '-') AS date_verif,
        COALESCE(MAX(dc.faisabilite), '-') AS faisabilite,
        COALESCE(MAX(dc.validite), '-') AS validite,
        '-' AS delai_demande,
        COUNT(DISTINCT ra.id) AS nb_analyses,
        COALESCE(
            SUBSTRING_INDEX(
                GROUP_CONCAT(ra.analyse ORDER BY ra.ordre_affichage ASC, ra.id ASC SEPARATOR '|||'),
                '|||',
                1
            ),
            '-'
        ) AS analyse_principale,
        GROUP_CONCAT(ra.analyse ORDER BY ra.ordre_affichage ASC, ra.id ASC SEPARATOR ' || ') AS analyses_concat
    FROM demande_analyse d
    LEFT JOIN client c
        ON c.id_client = d.id_client
    LEFT JOIN rapports r
        ON r.id_demande = d.id_demande
    LEFT JOIN resultat_analyse ra
        ON ra.id_demande = d.id_demande
    LEFT JOIN demande_client dc
        ON dc.demande_id = d.id_demande
    $whereMonth
    GROUP BY
        d.id_demande,
        d.ref_demande,
        c.id_client,
        c.client_name,
        d.date_creation,
        d.date_demande,
        d.date_reception
    ORDER BY
        COALESCE(d.date_creation, d.date_demande) DESC,
        d.id_demande DESC
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    respond([
        'success' => false,
        'message' => 'Erreur SQL prepare : ' . $conn->error
    ], 500);
}
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    respond([
        'success' => false,
        'message' => 'Erreur SQL execute'
    ], 500);
}
$rows = [];
while ($row = $result->fetch_assoc()) {
    $row['row_key'] = 'D_' . $row['id_demande'];
    $row['id_demande_masked'] = maskValue($row['id_demande'], 2);
    $row['id_client_masked'] = maskValue($row['client_id'], 2);
    $rows[] = $row;
}
$stmt->close();
$conn->close();
respond([
    'success' => true,
    'data' => $rows
]);