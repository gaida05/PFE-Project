<?php
require_once('db.php');
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    $date_debut = $_GET['date_debut'] ?? '';
    $date_fin   = $_GET['date_fin'] ?? '';

    $sql = "
        SELECT
            oa.ordre_id AS Ordre_id,
            oa.ordre_cle AS ordre_cle,
            oa.ordre_reference AS ordre_reference,
            oa.demande_id AS demande_id,
            da.ref_demande AS demande_analyse,
            COALESCE(da.date_demande, oa.date_demande) AS Date_demande,
            c.client_name AS ref_client,
            a.nom_analyse AS analyse,
            COALESCE(l.nom_laborantin, 'Laborantin non affecté') AS laboratin,
            oa.statut AS statut,
            oa.resultat AS resultat_pure,
            s.nom_secteur AS secteur,
            oa.conformite_libelle AS conformite,
            da.faisabilite AS faisabilite,
            da.validite AS validite,
            oa.date_reception AS date_reception,
            oa.date_affectation AS date_affectation,
            oa.date_fin AS date_fin,
            oa.date_validation AS date_validation
        FROM ordres_analyses oa
        LEFT JOIN demande_analyse da ON da.id_demande = oa.demande_id
        LEFT JOIN client c ON c.id_client = oa.client_id
        LEFT JOIN analyses a ON a.id_analyse = oa.analyse_id
        LEFT JOIN laborantin l ON l.id_laborantin = oa.laborantin_id
        LEFT JOIN secteur s ON s.id_secteur = COALESCE(da.id_secteur, a.id_secteur)
        WHERE 1=1
          AND c.client_name IS NOT NULL
          AND c.client_name <> ''
          AND c.client_name NOT REGEXP '^client_[0-9]+$'
    ";

    $params = [];
    $types = '';

    if ($date_debut !== '') {
        $sql .= " AND DATE(COALESCE(da.date_demande, oa.date_demande)) >= ?";
        $params[] = $date_debut;
        $types .= 's';
    }

    if ($date_fin !== '') {
        $sql .= " AND DATE(COALESCE(da.date_demande, oa.date_demande)) <= ?";
        $params[] = $date_fin;
        $types .= 's';
    }

    $sql .= " ORDER BY COALESCE(da.date_demande, oa.date_demande) DESC, oa.ordre_id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erreur préparation SQL : ' . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['resultat_pure'] !== null) {
            $row['resultat_pure'] = trim(strip_tags((string)$row['resultat_pure']));
        }
        $data[] = $row;
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>
