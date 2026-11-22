<?php
require_once('db.php');
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
try {
    $date_debut = $_GET['date_debut'] ?? '';
    $date_fin = $_GET['date_fin'] ?? '';
    $sql = "SELECT 
            ordre_reference as Ordre_id,
            date_demande as Date_demande,
            client_nom as ref_client,
            demande_analyse as demande_analyse,
            analyse_nom as analyse,
            COALESCE(laboratin, 'Laborantin anonyme') as laboratin,
            statut as statut,
            resultat as resultat_pure
        FROM ordres_analyses
        WHERE client_nom IS NOT NULL and client_nom <> '' and 1=1";
    $params = [];
    $types = "";
    if (!empty($date_debut)) {
        $sql .= " AND date_demande >= ?";
        $params[] = $date_debut;
        $types .= "s";
    }
    if (!empty($date_fin)) {
        $sql .= " AND date_demande <= ?"; 
        $params[] = $date_fin;
        $types .= "s";
    }
    $sql .= " ORDER BY date_demande DESC"; 
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['resultat_pure'] !== null) {
            $row['resultat_pure'] = trim(strip_tags($row['resultat_pure']));
        }
        $data[] = $row;
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
