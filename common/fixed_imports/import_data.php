<?php
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/clean_helpers.php';
require_once __DIR__ . '/csv_helpers.php';
require_once __DIR__ . '/transform_helpers.php';

set_time_limit(0);
ini_set('memory_limit', '1024M');

function importDataOpenCsvAssoc($file){
    $delimiter = detectDelimiter($file);
    $handle = fopen($file, 'r');
    if (!$handle) throw new Exception("Impossible d'ouvrir le fichier : " . $file);
    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) throw new Exception("CSV vide : " . $file);
    $headers = array_map(function($h){
        $h = preg_replace('/^\xEF\xBB\xBF/', '', (string)$h);
        return trim($h, " \t\n\r\0\x0B\"");
    }, $headers);
    return [$handle, $delimiter, $headers];
}

function importDataNextAssocRow($handle, $delimiter, $headers){
    $row = fgetcsv($handle, 0, $delimiter);
    if ($row === false) return false;
    $row = array_pad($row, count($headers), null);
    return array_combine($headers, array_slice($row, 0, count($headers)));
}

function importDataResolveCsvPath(array $candidates){
    foreach ($candidates as $file) {
        if ($file && file_exists($file)) return $file;
    }
    throw new Exception('Fichier CSV introuvable : ' . implode(' | ', $candidates));
}

function importDataSafeVarchar($value, $maxLen){
    $value = cleanString($value);
    if ($value === null) return null;
    return mb_substr($value, 0, $maxLen, 'UTF-8');
}

function importDataSafeDateTimeYMD($value){
    return cleanDateTimeYMDStrict($value);
}

function importDataSafeDateYMD($value){
    $dt = cleanDateTimeYMDStrict($value);
    return $dt ? date('Y-m-d', strtotime($dt)) : null;
}

function importDataNormalizeResultText($value){
    if ($value === null) return null;
    $value = trim((string)$value);
    if ($value === '' || $value === '\\N') return null;
    $enc = mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    $value = mb_convert_encoding($value, 'UTF-8', $enc ?: 'Windows-1252');
    $value = strip_tags($value);
    return mb_check_encoding($value, 'UTF-8') ? $value : null;
}

function importDataDaysBetween($start, $end){
    if (!$start || !$end) return null;
    $s = strtotime($start);
    $e = strtotime($end);
    if ($s === false || $e === false || $e < $s) return null;
    return round(($e - $s) / 86400, 2);
}

function importDataUpsertClient($conn, $idClientRaw, $nomClientRaw){
    $id_client = cleanInt($idClientRaw);
    if ($id_client === null) return;
    $client_name = buildClientName($idClientRaw, $nomClientRaw);

    $sql = "INSERT INTO client (id_client, client_name)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
                client_name = COALESCE(VALUES(client_name), client_name)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare client : " . $conn->error);
    $stmt->bind_param("is", $id_client, $client_name);
    if (!$stmt->execute()) throw new Exception("Erreur execute client : " . $stmt->error);
    $stmt->close();
}

function importDataUpsertSecteur($conn, $idSecteurRaw, $nomSecteurRaw){
    $id_secteur = cleanInt($idSecteurRaw);
    if ($id_secteur === null) return;
    $nom_secteur = buildSecteurName($idSecteurRaw, $nomSecteurRaw);

    $sql = "INSERT INTO secteur (id_secteur, nom_secteur)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
                nom_secteur = COALESCE(VALUES(nom_secteur), nom_secteur)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare secteur : " . $conn->error);
    $stmt->bind_param("is", $id_secteur, $nom_secteur);
    if (!$stmt->execute()) throw new Exception("Erreur execute secteur : " . $stmt->error);
    $stmt->close();
}

function importDataUpsertLaborantin($conn, $idLaborantinRaw, $nomLaborantinRaw, $idSecteurRaw = null){
    $id_laborantin = cleanInt($idLaborantinRaw);
    if ($id_laborantin === null) return;
    $nom_laborantin = buildLaborantinName($idLaborantinRaw, $nomLaborantinRaw);
    $id_secteur = cleanInt($idSecteurRaw);

    $sql = "INSERT INTO laborantin (id_laborantin, nom_laborantin, id_secteur)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nom_laborantin = COALESCE(VALUES(nom_laborantin), nom_laborantin),
                id_secteur = COALESCE(VALUES(id_secteur), id_secteur)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare laborantin : " . $conn->error);
    $stmt->bind_param("isi", $id_laborantin, $nom_laborantin, $id_secteur);
    if (!$stmt->execute()) throw new Exception("Erreur execute laborantin : " . $stmt->error);
    $stmt->close();
}

function importDataUpsertAnalyse($conn, $idAnalyseRaw, $nomAnalyseRaw, $idSecteurRaw = null){
    $id_analyse = cleanInt($idAnalyseRaw);
    if ($id_analyse === null) return;

    $nom_analyse = cleanString($nomAnalyseRaw) ?: ('analyse_' . $id_analyse);
    $id_secteur = cleanInt($idSecteurRaw);

    $sql = "INSERT INTO analyses (id_analyse, nom_analyse, id_secteur)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nom_analyse = COALESCE(VALUES(nom_analyse), nom_analyse),
                id_secteur = COALESCE(VALUES(id_secteur), id_secteur)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare analyses : " . $conn->error);
    $stmt->bind_param("isi", $id_analyse, $nom_analyse, $id_secteur);
    if (!$stmt->execute()) throw new Exception("Erreur execute analyses : " . $stmt->error);
    $stmt->close();
}


function ensureDemandeAnalyseExistsFromOrdre($conn, $r){
    $id_demande = cleanInt($r['demande_id'] ?? null);
    if ($id_demande === null) return;

    $id_client = cleanInt($r['client_id'] ?? null);
    $id_secteur = cleanInt($r['id_secteur'] ?? null);
    $ref_demande = importDataSafeVarchar($r['reference_demande'] ?? null, 250);
    $date_demande = importDataSafeDateTimeYMD($r['date_demande'] ?? null);
    $date_creation = importDataSafeDateTimeYMD($r['date_creation'] ?? null) ?: $date_demande;
    $date_reception = combineDateHeureYMD($r['date_reception'] ?? null, $r['heure_reception'] ?? null);
    $date_affectation = combineDateHeureYMD($r['date_affectation'] ?? null, $r['heure_affectation'] ?? null);
    $date_fin = combineDateHeureYMD($r['date_fin'] ?? null, $r['heure_fin'] ?? null);
    $date_validation = importDataSafeDateTimeYMD($r['date_validation'] ?? null);
    $date_cloture = $date_fin ?: $date_validation;
    $date_debut_analyse = $date_affectation ? date('Y-m-d', strtotime($date_affectation)) : null;
    $date_fin_analyse = $date_fin ? date('Y-m-d', strtotime($date_fin)) : null;
    $statut = importDataSafeVarchar($r['statut_libelle'] ?? null, 100);

    $sql = "INSERT INTO demande_analyse
            (id_demande, ref_demande, id_secteur, date_demande, date_creation, date_cloture, date_validation, statut, date_reception, date_affectation, date_debut_analyse, date_fin_analyse, id_client)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                ref_demande = COALESCE(ref_demande, VALUES(ref_demande)),
                id_secteur = COALESCE(id_secteur, VALUES(id_secteur)),
                date_demande = COALESCE(date_demande, VALUES(date_demande)),
                date_creation = COALESCE(date_creation, VALUES(date_creation)),
                date_cloture = COALESCE(date_cloture, VALUES(date_cloture)),
                date_validation = COALESCE(date_validation, VALUES(date_validation)),
                statut = COALESCE(statut, VALUES(statut)),
                date_reception = COALESCE(date_reception, VALUES(date_reception)),
                date_affectation = COALESCE(date_affectation, VALUES(date_affectation)),
                date_debut_analyse = COALESCE(date_debut_analyse, VALUES(date_debut_analyse)),
                date_fin_analyse = COALESCE(date_fin_analyse, VALUES(date_fin_analyse)),
                id_client = COALESCE(id_client, VALUES(id_client))";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare ensure demande_analyse(ordres) : " . $conn->error);
    $stmt->bind_param("isisssssssssi", $id_demande, $ref_demande, $id_secteur, $date_demande, $date_creation, $date_cloture, $date_validation, $statut, $date_reception, $date_affectation, $date_debut_analyse, $date_fin_analyse, $id_client);
    if (!$stmt->execute()) throw new Exception("Erreur execute ensure demande_analyse(ordres) : " . $stmt->error);
    $stmt->close();
}

function upsertOrdreAnalyse($conn, $r){
    $ordre_id = cleanInt($r['ordre_id'] ?? null);
    $ordre_cle = importDataSafeVarchar($r['ordre_cle'] ?? null, 100);
    if ($ordre_id === null || $ordre_cle === null) return false;

    importDataUpsertClient($conn, $r['client_id'] ?? null, $r['client_nom'] ?? null);
    importDataUpsertSecteur($conn, $r['id_secteur'] ?? null, $r['secteur_nom'] ?? null);
    importDataUpsertLaborantin($conn, $r['laborantin_id'] ?? null, $r['laborantin_nom'] ?? null, $r['id_secteur'] ?? null);
    importDataUpsertAnalyse($conn, $r['analyse_id'] ?? null, $r['analyse_nom'] ?? null, $r['id_secteur'] ?? null);

    $ordre_reference = importDataSafeVarchar($r['reference_ordre'] ?? null, 250);
    $client_id = cleanInt($r['client_id'] ?? null);
    $demande_id = cleanInt($r['demande_id'] ?? null);
    $analyse_id = cleanInt($r['analyse_id'] ?? null);
    $laborantin_id = cleanInt($r['laborantin_id'] ?? null);
    $fiche_paillasse_id = cleanInt($r['fiche_paillasse_id'] ?? null);
    $echantillon_id = cleanInt($r['echantillon_id'] ?? null);
    $resultat = importDataNormalizeResultText($r['resultat'] ?? null);
    $date_demande_only = importDataSafeDateYMD($r['date_demande'] ?? null);
    $date_affectation = combineDateHeureYMD($r['date_affectation'] ?? null, $r['heure_affectation'] ?? null);
    $date_reception = combineDateHeureYMD($r['date_reception'] ?? null, $r['heure_reception'] ?? null);
    $date_fin = combineDateHeureYMD($r['date_fin'] ?? null, $r['heure_fin'] ?? null);
    $date_validation = importDataSafeDateTimeYMD($r['date_validation'] ?? null);
    $date_creation = importDataSafeDateTimeYMD($r['date_creation'] ?? null);
    $duree_analyse_jours = importDataDaysBetween($date_affectation, $date_fin);
    $delai_prise_en_charge_jours = importDataDaysBetween($date_reception, $date_affectation);
    $conformite_libelle = importDataSafeVarchar($r['conformite_libelle'] ?? null, 100);
    $statut = importDataSafeVarchar($r['statut_libelle'] ?? null, 50);
    $statut_code = importDataSafeVarchar($r['statut_code'] ?? null, 50);
    $conformite_code = importDataSafeVarchar($r['conformite_code'] ?? null, 50);
    $date_extraction_only = importDataSafeDateYMD($r['date_extraction'] ?? null);

    $sql = "INSERT INTO ordres_analyses
            (ordre_cle, ordre_id, ordre_reference, client_id, demande_id, analyse_id, laborantin_id, fiche_paillasse_id, echantillon_id, resultat, date_demande, date_affectation, date_reception, date_fin, date_validation, date_creation, duree_analyse_jours, delai_prise_en_charge_jours, conformite_libelle, conformite_code, date_extraction, statut, statut_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
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
                date_extraction = COALESCE(VALUES(date_extraction), date_extraction),
                statut = COALESCE(VALUES(statut), statut),
                statut_code = COALESCE(VALUES(statut_code), statut_code)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare ordres_analyses : " . $conn->error);
    $stmt->bind_param(
        "siiiiiiiisssssssddsssss",
        $ordre_cle,
        $ordre_id,
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
        $date_extraction_only,
        $statut,
        $statut_code
    );
    if (!$stmt->execute()) throw new Exception("Erreur execute ordres_analyses : " . $stmt->error);
    $stmt->close();
    return true;
}

function upsertResultatAnalyse($conn, $r){
    $ordre_id = cleanInt($r['ordre_id'] ?? null);
    $id_demande = cleanInt($r['demande_id'] ?? null);
    $id_analyse = cleanInt($r['analyse_id'] ?? null);
    $id_laborantin = cleanInt($r['laborantin_id'] ?? null);
    $resultat = importDataNormalizeResultText($r['resultat'] ?? null);
    if ($ordre_id === null || $id_demande === null || $id_analyse === null) return false;

    $created_at = importDataSafeDateTimeYMD($r['date_creation'] ?? null) ?: date('Y-m-d H:i:s');
    $result_note = importDataSafeVarchar($r['conformite_libelle'] ?? null, 255);

    $checkSql = "SELECT id FROM resultat_analyse WHERE ordre_id = ? LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    if (!$checkStmt) throw new Exception("Erreur prepare check resultat_analyse : " . $conn->error);
    $checkStmt->bind_param("i", $ordre_id);
    if (!$checkStmt->execute()) throw new Exception("Erreur execute check resultat_analyse : " . $checkStmt->error);
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($existing) {
        $id = (int)$existing['id'];
        $sql = "UPDATE resultat_analyse
                SET id_demande = ?, id_analyse = ?, id_laborantin = ?, resultat = ?, result_note = ?, created_at = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Erreur prepare update resultat_analyse : " . $conn->error);
        $stmt->bind_param("iiisssi", $id_demande, $id_analyse, $id_laborantin, $resultat, $result_note, $created_at, $id);
    } else {
        $sql = "INSERT INTO resultat_analyse (id_demande, ordre_id, id_analyse, id_laborantin, resultat, result_note, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Erreur prepare insert resultat_analyse : " . $conn->error);
        $stmt->bind_param("iiiisss", $id_demande, $ordre_id, $id_analyse, $id_laborantin, $resultat, $result_note, $created_at);
    }

    if (!$stmt->execute()) throw new Exception("Erreur execute resultat_analyse : " . $stmt->error);
    $stmt->close();
    return true;
}

function upsertFichePaillasse($conn, $r){
    $id_fiche = cleanInt($r['fiche_paillasse_id'] ?? null);
    if ($id_fiche === null) return false;

    $id_demande = cleanInt($r['demande_id'] ?? null);
    $id_laborantin = cleanInt($r['laborantin_id'] ?? null);
    $id_secteur = cleanInt($r['id_secteur'] ?? null);

    if ($id_laborantin !== null) {
        importDataUpsertSecteur($conn, $id_secteur, $r['secteur_nom'] ?? null);
        importDataUpsertLaborantin($conn, $id_laborantin, $r['laborantin_nom'] ?? null, $id_secteur);
    }

    $checkLaborantin = null;
    if ($id_laborantin !== null) {
        $sqlCheck = "SELECT id_laborantin FROM laborantin WHERE id_laborantin = ? LIMIT 1";
        $stmtCheck = $conn->prepare($sqlCheck);
        if (!$stmtCheck) throw new Exception("Erreur prepare check laborantin : " . $conn->error);
        $stmtCheck->bind_param("i", $id_laborantin);
        if (!$stmtCheck->execute()) throw new Exception("Erreur execute check laborantin : " . $stmtCheck->error);
        $checkLaborantin = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();
        if (!$checkLaborantin) {
            $id_laborantin = null;
        }
    }

    $statut = importDataSafeVarchar($r['statut_libelle'] ?? null, 100);
    $date_affectation = combineDateHeureYMD($r['date_affectation'] ?? null, $r['heure_affectation'] ?? null);
    $date_fin = combineDateHeureYMD($r['date_fin'] ?? null, $r['heure_fin'] ?? null);
    $date_debut_analyse = $date_affectation;
    $date_validation = importDataSafeDateTimeYMD($r['date_validation'] ?? null);
    $date_creation_rapport = $date_validation;

    $sql = "INSERT INTO fiches_paillasse
            (id_fiche, id_demande, id_laborantin, statut, date_affectation, date_debut_analyse, date_fin, date_validation, date_creation_rapport, id_secteur)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_demande = COALESCE(VALUES(id_demande), id_demande),
                id_laborantin = COALESCE(VALUES(id_laborantin), id_laborantin),
                statut = COALESCE(VALUES(statut), statut),
                date_affectation = COALESCE(VALUES(date_affectation), date_affectation),
                date_debut_analyse = COALESCE(VALUES(date_debut_analyse), date_debut_analyse),
                date_fin = COALESCE(VALUES(date_fin), date_fin),
                date_validation = COALESCE(VALUES(date_validation), date_validation),
                date_creation_rapport = COALESCE(VALUES(date_creation_rapport), date_creation_rapport),
                id_secteur = COALESCE(VALUES(id_secteur), id_secteur)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare fiches_paillasse : " . $conn->error);
    $stmt->bind_param("iiissssssi", $id_fiche, $id_demande, $id_laborantin, $statut, $date_affectation, $date_debut_analyse, $date_fin, $date_validation, $date_creation_rapport, $id_secteur);
    if (!$stmt->execute()) throw new Exception("Erreur execute fiches_paillasse : " . $stmt->error);
    $stmt->close();
    return true;
}

function updateDemandeFromOrdre($conn, $r){
    $id_demande = cleanInt($r['demande_id'] ?? null);
    if ($id_demande === null) return;

    $date_affectation = combineDateHeureYMD($r['date_affectation'] ?? null, $r['heure_affectation'] ?? null);
    $date_reception = combineDateHeureYMD($r['date_reception'] ?? null, $r['heure_reception'] ?? null);

    /* date_cloture = datecompletude + heurcompletude
       dans le CSV extrait, cela correspond à date_fin + heure_fin */
    $date_cloture = combineDateHeureYMD($r['date_fin'] ?? null, $r['heure_fin'] ?? null);

    $date_validation = cleanDateTimeYMDStrict($r['date_validation'] ?? null);

    $sql = "UPDATE demande_analyse
            SET date_affectation = COALESCE(?, date_affectation),
                date_reception = COALESCE(?, date_reception),
                date_cloture = COALESCE(?, date_cloture),
                date_validation = COALESCE(?, date_validation)
            WHERE id_demande = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erreur prepare update demande_analyse(ordres) : " . $conn->error);
    }

    $stmt->bind_param(
        "ssssi",
        $date_affectation,
        $date_reception,
        $date_cloture,
        $date_validation,
        $id_demande
    );

    if (!$stmt->execute()) {
        throw new Exception("Erreur execute update demande_analyse(ordres) : " . $stmt->error);
    }

    $stmt->close();
}

try {
    $conn->set_charset('utf8mb4');
    $fichier = importDataResolveCsvPath([
        __DIR__ . '/../../data_source/ordres_csv.csv',
        __DIR__ . '/ordres_csv.csv',
        '/mnt/data/ordres_csv.csv'
    ]);
    list($handle, $delimiter, $headers) = importDataOpenCsvAssoc($fichier);

    echo "Début import_data.php...\n";
    $conn->begin_transaction();
    $nbOrdres = 0;
    $nbResultats = 0;
    $nbFiches = 0;

    while (($r = importDataNextAssocRow($handle, $delimiter, $headers)) !== false) {
        ensureDemandeAnalyseExistsFromOrdre($conn, $r);
        if (upsertFichePaillasse($conn, $r)) $nbFiches++;
        if (upsertOrdreAnalyse($conn, $r)) $nbOrdres++;
        if (upsertResultatAnalyse($conn, $r)) $nbResultats++;
        updateDemandeFromOrdre($conn, $r);
    }

    fclose($handle);
    $conn->commit();
    echo "ordres_analyses: {$nbOrdres}\n";
    echo "resultat_analyse: {$nbResultats}\n";
    echo "fiches_paillasse: {$nbFiches}\n";
} catch (Exception $e) {
    if (isset($conn) && method_exists($conn, 'rollback')) {
        @$conn->rollback();
    }
    echo "Erreur import_data.php : " . $e->getMessage();
}
?>


