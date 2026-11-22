<?php
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/common_import_helpers.php';

set_time_limit(0);
ini_set('memory_limit', '1024M');

function upsertOrdreAnalyseFromCsv(mysqli $conn, array $r): bool {
    $ordre_id = cleanInt($r['ordre_id'] ?? null);
    $ordre_cle = cleanString($r['ordre_cle'] ?? null);
    if ($ordre_id === null || $ordre_cle === null) return false;

    upsertClientCommon($conn, $r['client_id'] ?? null, $r['client_nom'] ?? null);
    upsertSecteurCommon($conn, $r['id_secteur'] ?? null, $r['secteur_nom'] ?? null);
    upsertLaborantinCommon($conn, $r['laborantin_id'] ?? null, $r['laborantin_nom'] ?? null, $r['id_secteur'] ?? null);
    upsertAnalyseCommon($conn, $r['analyse_id'] ?? null, $r['analyse_nom'] ?? null, $r['id_secteur'] ?? null);

    $ordre_reference = cleanString($r['reference_ordre'] ?? null);
    $client_id = cleanInt($r['client_id'] ?? null);
    $demande_id = cleanInt($r['demande_id'] ?? null);
    $analyse_id = cleanInt($r['analyse_id'] ?? null);
    $laborantin_id = cleanInt($r['laborantin_id'] ?? null);
    $fiche_paillasse_id = cleanInt($r['fiche_paillasse_id'] ?? null);
    $echantillon_id = cleanInt($r['echantillon_id'] ?? null);
    $resultat = normalizeResultTextValue($r['resultat'] ?? null);
    $date_demande = cleanDateTimeYMDStrict($r['date_demande'] ?? null);
    $date_demande_only = $date_demande ? date('Y-m-d', strtotime($date_demande)) : null;
    $date_affectation = combineDateHeureYMD($r['date_affectation'] ?? null, $r['heure_affectation'] ?? null);
    $date_reception = combineDateHeureYMD($r['date_reception'] ?? null, $r['heure_reception'] ?? null);
    $date_fin = combineDateHeureYMD($r['date_fin'] ?? null, $r['heure_fin'] ?? null);
    $date_validation = cleanDateTimeYMDStrict($r['date_validation'] ?? null);
    $date_creation = cleanDateTimeYMDStrict($r['date_creation'] ?? null);
    $duree_analyse_jours = daysBetweenNullable($date_affectation, $date_fin);
    $delai_prise_en_charge_jours = daysBetweenNullable($date_reception, $date_affectation);
    $conformite_libelle = cleanString($r['conformite_libelle'] ?? null);
    $statut = cleanString($r['statut_libelle'] ?? null);
    $statut_code = cleanString($r['statut_code'] ?? null);
    $conformite_code = cleanString($r['conformite_code'] ?? null);
    $date_extraction = cleanDateTimeYMDStrict($r['date_extraction'] ?? null);
    $date_extraction_only = $date_extraction ? date('Y-m-d', strtotime($date_extraction)) : null;

    $sql = "INSERT INTO ordres_analyses
            (ordre_id, ordre_cle, ordre_reference, client_id, demande_id, analyse_id, laborantin_id, fiche_paillasse_id, echantillon_id, resultat, date_demande, date_affectation, date_reception, date_fin, date_validation, date_creation, duree_analyse_jours, delai_prise_en_charge_jours, conformite_libelle, conformite_code, statut, statut_code, date_extraction)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                ordre_cle = VALUES(ordre_cle),
                ordre_reference = COALESCE(VALUES(ordre_reference), ordre_reference),
                client_id = COALESCE(VALUES(client_id), client_id),
                demande_id = COALESCE(VALUES(demande_id), demande_id),
                analyse_id = COALESCE(VALUES(analyse_id), analyse_id),
                laborantin_id = COALESCE(VALUES(laborantin_id), laborantin_id),
                fiche_paillasse_id = COALESCE(VALUES(fiche_paillasse_id), fiche_paillasse_id),
                echantillon_id = COALESCE(VALUES(echantillon_id), echantillon_id),
                resultat = COALESCE(VALUES(resultat), resultat),
                date_demande = COALESCE(VALUES(date_demande), date_demande),
                date_affectation = COALESCE(VALUES(date_affectation), date_affectation),
                date_reception = COALESCE(VALUES(date_reception), date_reception),
                date_fin = COALESCE(VALUES(date_fin), date_fin),
                date_validation = COALESCE(VALUES(date_validation), date_validation),
                date_creation = COALESCE(VALUES(date_creation), date_creation),
                duree_analyse_jours = COALESCE(VALUES(duree_analyse_jours), duree_analyse_jours),
                delai_prise_en_charge_jours = COALESCE(VALUES(delai_prise_en_charge_jours), delai_prise_en_charge_jours),
                conformite_libelle = COALESCE(VALUES(conformite_libelle), conformite_libelle),
                conformite_code = COALESCE(VALUES(conformite_code), conformite_code),
                statut = COALESCE(VALUES(statut), statut),
                statut_code = COALESCE(VALUES(statut_code), statut_code),
                date_extraction = COALESCE(VALUES(date_extraction), date_extraction)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Erreur prepare ordres_analyses : ' . $conn->error);
    $stmt->bind_param(
        'issiiiiiissssssddssssss',
        $ordre_id,
        $ordre_cle,
        $ordre_reference,
        $client_id,
        $demande_id,
        $analyse_id,
        $laborantin_id,
        $fiche_paillasse_id,
        $echantillon_id,
        $resultat,
        $date_demande_only,
        $date_affectation,
        $date_reception,
        $date_fin,
        $date_validation,
        $date_creation,
        $duree_analyse_jours,
        $delai_prise_en_charge_jours,
        $conformite_libelle,
        $conformite_code,
        $statut,
        $statut_code,
        $date_extraction_only
    );
    if (!$stmt->execute()) throw new Exception('Erreur execute ordres_analyses : ' . $stmt->error);
    $stmt->close();
    return true;
}

function upsertResultatAnalyseFromCsv(mysqli $conn, array $r): bool {
    $ordre_id = cleanInt($r['ordre_id'] ?? null);
    $id_demande = cleanInt($r['demande_id'] ?? null);
    $id_analyse = cleanInt($r['analyse_id'] ?? null);
    $id_laborantin = cleanInt($r['laborantin_id'] ?? null);
    $resultat = normalizeResultTextValue($r['resultat'] ?? null);
    if ($ordre_id === null || $id_demande === null || $id_analyse === null) return false;

    $created_at = cleanDateTimeYMDStrict($r['date_creation'] ?? null) ?: date('Y-m-d H:i:s');
    $result_note = cleanString($r['conformite_libelle'] ?? null);

    $checkSql = 'SELECT id FROM resultat_analyse WHERE ordre_id = ? LIMIT 1';
    $checkStmt = $conn->prepare($checkSql);
    if (!$checkStmt) throw new Exception('Erreur prepare check resultat_analyse : ' . $conn->error);
    $checkStmt->bind_param('i', $ordre_id);
    if (!$checkStmt->execute()) throw new Exception('Erreur execute check resultat_analyse : ' . $checkStmt->error);
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($existing) {
        $id = (int) $existing['id'];
        $sql = "UPDATE resultat_analyse
                SET id_demande = ?, id_analyse = ?, id_laborantin = ?, resultat = ?, result_note = ?, created_at = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('Erreur prepare update resultat_analyse : ' . $conn->error);
        $stmt->bind_param('iiisssi', $id_demande, $id_analyse, $id_laborantin, $resultat, $result_note, $created_at, $id);
    } else {
        $sql = "INSERT INTO resultat_analyse (id_demande, ordre_id, id_analyse, id_laborantin, resultat, result_note, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('Erreur prepare insert resultat_analyse : ' . $conn->error);
        $stmt->bind_param('iiiisss', $id_demande, $ordre_id, $id_analyse, $id_laborantin, $resultat, $result_note, $created_at);
    }
    if (!$stmt->execute()) throw new Exception('Erreur execute resultat_analyse : ' . $stmt->error);
    $stmt->close();
    return true;
}

function upsertFichePaillasseFromCsv(mysqli $conn, array $r): bool {
    $id_fiche = cleanInt($r['fiche_paillasse_id'] ?? null);
    if ($id_fiche === null) return false;

    $id_demande = cleanInt($r['demande_id'] ?? null);
    $id_laborantin = cleanInt($r['laborantin_id'] ?? null);
    $statut = cleanString($r['statut_libelle'] ?? null);
    $date_affectation = combineDateHeureYMD($r['date_affectation'] ?? null, $r['heure_affectation'] ?? null);
    $date_fin = combineDateHeureYMD($r['date_fin'] ?? null, $r['heure_fin'] ?? null);
    $date_validation = cleanDateTimeYMDStrict($r['date_validation'] ?? null);
    $id_secteur = cleanInt($r['id_secteur'] ?? null);

    $sql = "INSERT INTO fiches_paillasse
            (id_fiche, id_demande, id_laborantin, statut, date_affectation, date_fin, date_validation, id_secteur)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_demande = COALESCE(VALUES(id_demande), id_demande),
                id_laborantin = COALESCE(VALUES(id_laborantin), id_laborantin),
                statut = COALESCE(VALUES(statut), statut),
                date_affectation = COALESCE(VALUES(date_affectation), date_affectation),
                date_fin = COALESCE(VALUES(date_fin), date_fin),
                date_validation = COALESCE(VALUES(date_validation), date_validation),
                id_secteur = COALESCE(VALUES(id_secteur), id_secteur)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Erreur prepare fiches_paillasse : ' . $conn->error);
    $stmt->bind_param('iiissssi', $id_fiche, $id_demande, $id_laborantin, $statut, $date_affectation, $date_fin, $date_validation, $id_secteur);
    if (!$stmt->execute()) throw new Exception('Erreur execute fiches_paillasse : ' . $stmt->error);
    $stmt->close();
    return true;
}

function updateDemandeFromOrdre(mysqli $conn, array $r): void {
    $id_demande = cleanInt($r['demande_id'] ?? null);
    if ($id_demande === null) return;

    $date_affectation = combineDateHeureYMD($r['date_affectation'] ?? null, $r['heure_affectation'] ?? null);
    $date_reception = combineDateHeureYMD($r['date_reception'] ?? null, $r['heure_reception'] ?? null);
    $date_fin = combineDateHeureYMD($r['date_fin'] ?? null, $r['heure_fin'] ?? null);
    $date_validation = cleanDateTimeYMDStrict($r['date_validation'] ?? null);

    $sql = "UPDATE demande_analyse
            SET date_affectation = COALESCE(?, date_affectation),
                date_reception = COALESCE(?, date_reception),
                date_cloture = COALESCE(?, date_cloture),
                date_validation = COALESCE(?, date_validation)
            WHERE id_demande = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Erreur prepare update demande_analyse(ordres) : ' . $conn->error);
    $stmt->bind_param('ssssi', $date_affectation, $date_reception, $date_fin, $date_validation, $id_demande);
    if (!$stmt->execute()) throw new Exception('Erreur execute update demande_analyse(ordres) : ' . $stmt->error);
    $stmt->close();
}

try {
    $conn->set_charset('utf8mb4');
    $fichier = firstExistingPath([
        __DIR__ . '/../../data_source/ordres_csv.csv',
        __DIR__ . '/ordres_csv.csv',
        '/mnt/data/ordres_csv.csv'
    ]);

    [$handle, $delimiter, $headers] = openCsvAssocSmart($fichier);

    echo "Début import_data.php...\n";
    $conn->begin_transaction();
    $nbOrdres = 0;
    $nbResultats = 0;
    $nbFiches = 0;

    while (($r = nextAssocRowSmart($handle, $delimiter, $headers)) !== false) {
        if (upsertOrdreAnalyseFromCsv($conn, $r)) $nbOrdres++;
        if (upsertResultatAnalyseFromCsv($conn, $r)) $nbResultats++;
        if (upsertFichePaillasseFromCsv($conn, $r)) $nbFiches++;
        updateDemandeFromOrdre($conn, $r);
    }

    fclose($handle);
    $conn->commit();
    echo "ordres_analyses: {$nbOrdres}\n";
    echo "resultat_analyse: {$nbResultats}\n";
    echo "fiches_paillasse: {$nbFiches}\n";
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    echo 'Erreur import_data.php : ' . $e->getMessage();
}
?>
