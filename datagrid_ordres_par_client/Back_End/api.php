<?php
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
function getClientDetails($conn, $client_id) {
    $sql = "SELECT 
                ordre_cle, 
                analyse_nom, 
                date_demande,
                duree_analyse_jours, 
                statut, 
                conformite_libelle 
            FROM ordres_analyses 
            WHERE client_id = ? 
            ORDER BY date_demande DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $data;
}
function getStats($conn) {
    $sql = "SELECT 
                client_id,
                COUNT(*) AS total_ordres,
                ROUND(AVG(COALESCE(duree_analyse_jours,0)),1) AS moyenne_duree,
                SUM(CASE WHEN LOWER(conformite_libelle) = 'conforme' THEN 1 ELSE 0 END) AS nb_conformes,
                SUM(CASE 
                    WHEN LOWER(conformite_libelle) = 'non conforme' 
                    THEN 1 ELSE 0 END) AS nb_non_conformes,
                SUM(CASE 
                    WHEN statut = 'Terminé' OR statut = 'Traité' 
                    THEN 1 ELSE 0 END) AS nb_traite,
                SUM(CASE 
                    WHEN statut = 'En cours' 
                    OR statut = 'Non évalué' 
                    OR statut = 'En attente'
                    THEN 1 ELSE 0 END) AS nb_en_attente,
                SUM(CASE 
                    WHEN statut = 'Annulé' 
                    THEN 1 ELSE 0 END) AS nb_annule
            FROM ordres_analyses
            GROUP BY client_id
            ORDER BY total_ordres DESC";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception($conn->error);
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}
try {
    $client_id = isset($_GET['id']) ? (int) $_GET['id'] : null;
    if ($client_id) {
        $data = getClientDetails($conn, $client_id);
    } else {
        $data = getStats($conn);
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

}
?>
