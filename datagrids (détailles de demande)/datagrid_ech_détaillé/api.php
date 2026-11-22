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

    $dateExpr = buildMysqlDateExpression('date_reception');

    $sql = "
        SELECT
            demande_id,
            MAX(NULLIF(TRIM(ref_demande), '')) AS ref_demande,
            MAX(NULLIF(TRIM(code_echantillon), '')) AS code_echantillon,
            DATE_FORMAT(MAX($dateExpr), '%Y-%m-%d') AS date_reception,
            MAX(NULLIF(TRIM(client_name), '')) AS client_name,
            MAX(NULLIF(TRIM(nature_echantillon), '')) AS nature_echantillon,
            MAX(NULLIF(TRIM(categorie_echantillon_name), '')) AS categorie_echantillon_name,
            MAX(NULLIF(TRIM(description_echantillon), '')) AS description_echantillon,
            COUNT(DISTINCT NULLIF(TRIM(analyse_id), '')) AS nb_analyse,
            GROUP_CONCAT(DISTINCT NULLIF(TRIM(analyse_nom), '') ORDER BY analyse_nom SEPARATOR ', ') AS analyses,
            MAX(NULLIF(TRIM(temperature), '')) AS temperature,
            MAX(NULLIF(TRIM(lot), '')) AS lot,
            MAX(NULLIF(TRIM(conformite), '')) AS conformite,
            echantillon,
            fraction
        FROM {$table}
        WHERE 1 = 1
    ";

    $params = [];

    if ($dateDebut !== '') {
        $sql .= " AND $dateExpr >= :date_debut ";
        $params[':date_debut'] = $dateDebut;
    }

    if ($dateFin !== '') {
        $sql .= " AND $dateExpr <= :date_fin ";
        $params[':date_fin'] = $dateFin;
    }

    $sql .= "
        GROUP BY demande_id
        ORDER BY MAX($dateExpr) DESC, demande_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['demande_id'] = $row['demande_id'] !== null ? (string) $row['demande_id'] : '';
        $row['nb_analyse'] = isset($row['nb_analyse']) ? (int) $row['nb_analyse'] : 0;
        $row['ref_demande'] = $row['ref_demande'] ?? '';
        $row['echantillon']=$row['echantillon']??'';
        $row['code_echantillon'] = $row['code_echantillon'] ?? '';
        $row['date_reception'] = $row['date_reception'] ?? null;
        $row['client_name'] = $row['client_name'] ?? '';
        $row['nature_echantillon'] = $row['nature_echantillon'] ?? '';
        $row['categorie_echantillon_name'] = $row['categorie_echantillon_name'] ?? '';
        $row['description_echantillon'] = $row['description_echantillon'] ?? '';
        $row['analyses'] = $row['analyses'] ?? '';
        $row['temperature'] = $row['temperature'] ?? '';
        $row['lot'] = $row['lot'] ?? '';
        $row['conformite'] = $row['conformite'] ?? '';
        $row['fraction'] = $row['fraction'] ?? '';
    }
    unset($row);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    sendJson(500, ['error' => 'Erreur base de données : ' . $e->getMessage()]);
} catch (Throwable $e) {
    sendJson(500, ['error' => 'Erreur serveur : ' . $e->getMessage()]);
}
