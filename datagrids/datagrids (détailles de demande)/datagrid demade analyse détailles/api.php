<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$host = '127.0.0.1';
$dbname = 'temp';
$username = 'root';
$password = '';
$table = 'my_csv_data';

function sendJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cleanValue($value): string
{
    if ($value === null) {
        return '';
    }

    return trim((string) $value);
}

function buildMysqlDateExpression(string $fieldName): string
{
    return "COALESCE(\n"
        . "    STR_TO_DATE(TRIM($fieldName), '%Y-%m-%d'),\n"
        . "    STR_TO_DATE(TRIM($fieldName), '%d/%m/%Y'),\n"
        . "    STR_TO_DATE(TRIM($fieldName), '%Y/%m/%d'),\n"
        . "    STR_TO_DATE(TRIM($fieldName), '%d-%m-%Y')\n"
        . ")";
}

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $dateDebut = isset($_GET['date_debut']) ? trim((string) $_GET['date_debut']) : '';
    $dateFin = isset($_GET['date_fin']) ? trim((string) $_GET['date_fin']) : '';

    if ($dateDebut !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut)) {
        sendJson(400, ['error' => 'Le paramètre date_debut doit être au format YYYY-MM-DD']);
    }

    if ($dateFin !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin)) {
        sendJson(400, ['error' => 'Le paramètre date_fin doit être au format YYYY-MM-DD']);
    }

    $dateReceptionExpr = buildMysqlDateExpression('date_reception');
    $dateDemandeExpr = buildMysqlDateExpression('date_demande');

    $sql = "
        SELECT
            demande_id,
            MAX(NULLIF(TRIM(ref_demande), '')) AS ref_demande,
            DATE_FORMAT(MAX($dateDemandeExpr), '%Y-%m-%d') AS date_demande,
            MAX(NULLIF(TRIM(client_id), '')) AS client_id,
            MAX(NULLIF(TRIM(num_client), '')) AS num_client,
            MAX(NULLIF(TRIM(ref_client), '')) AS ref_client,
            MAX(NULLIF(TRIM(demandeur), '')) AS demandeur,
            MAX(NULLIF(TRIM(producteur_name), '')) AS producteur_name,
            MAX(NULLIF(TRIM(importateur_name), '')) AS importateur_name,
            DATE_FORMAT(MAX(" . buildMysqlDateExpression('date_prelevement') . "), '%Y-%m-%d') AS date_prelevement,
            DATE_FORMAT(MAX(" . buildMysqlDateExpression('date_production') . "), '%Y-%m-%d') AS date_production,
            DATE_FORMAT(MAX(" . buildMysqlDateExpression('dlc') . "), '%Y-%m-%d') AS dlc,
            MAX(NULLIF(TRIM(conformite), '')) AS conformite,
            MAX(NULLIF(TRIM(faisabilite), '')) AS faisabilite,
            MAX(NULLIF(TRIM(validite), '')) AS validite,
            COUNT(DISTINCT NULLIF(TRIM(analyse_id), '')) AS nb_analyse,
            GROUP_CONCAT(DISTINCT NULLIF(TRIM(analyse_nom), '') ORDER BY analyse_nom SEPARATOR ', ') AS analyses
        FROM {$table}
        WHERE 1 = 1
    ";

    $params = [];

    if ($dateDebut !== '') {
        $sql .= " AND $dateReceptionExpr >= :date_debut ";
        $params[':date_debut'] = $dateDebut;
    }

    if ($dateFin !== '') {
        $sql .= " AND $dateReceptionExpr <= :date_fin ";
        $params[':date_fin'] = $dateFin;
    }

    $sql .= "
        GROUP BY demande_id
        ORDER BY MAX($dateDemandeExpr) DESC, demande_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $result = [];

    foreach ($rows as $row) {
        $analyses = cleanValue($row['analyses']);

        $result[] = [
            'demande_id' => cleanValue($row['demande_id']),
            'ref_demande' => cleanValue($row['ref_demande']),
            'date_demande' => cleanValue($row['date_demande']),
            'client_id' => cleanValue($row['client_id']),
            'num_client' => cleanValue($row['num_client']),
            'ref_client' => cleanValue($row['ref_client']),
            'demandeur' => cleanValue($row['demandeur']),
            'producteur_name' => cleanValue($row['producteur_name']),
            'importateur_name' => cleanValue($row['importateur_name']),
            'date_prelevement' => cleanValue($row['date_prelevement']),
            'date_production' => cleanValue($row['date_production']),
            'dlc' => cleanValue($row['dlc']),
            'conformite' => cleanValue($row['conformite']),
            'faisabilite' => cleanValue($row['faisabilite']),
            'validite' => cleanValue($row['validite']),
            'nb_analyse' => (int) ($row['nb_analyse'] ?? 0),
            'analyses' => $analyses,
        ];
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    sendJson(500, ['error' => 'Erreur base de données : ' . $e->getMessage()]);
} catch (Throwable $e) {
    sendJson(500, ['error' => 'Erreur serveur : ' . $e->getMessage()]);
}
