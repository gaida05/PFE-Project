<?php
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/clean_helpers.php';
require_once __DIR__ . '/csv_helpers.php';
require_once __DIR__ . '/transform_helpers.php';
set_time_limit(0);
ini_set('memory_limit', '1024M');
function idf_csvDate($value){
    return safeMysqlDateOrdres($value);
}
function idf_csvDateTime($value){
    return safeMysqlDateTimeOrdres($value);
}
function idf_csvCombineDateHeure($date, $heure = null){
    $dt = combineDateHeure($date, $heure);
    if ($dt !== null) return $dt;
    return safeMysqlDateTimeOrdres($date);
}

function idf_compareDateTime($a, $b){
    if ($a === null && $b === null) return null;
    if ($a === null) return $b;
    if ($b === null) return $a;
    return (strtotime($a) >= strtotime($b)) ? $a : $b;

function idf_minDateTime($a, $b){
    if ($a === null && $b === null) return null;
    if ($a === null) return $b;
    if ($b === null) return $a;
    return (strtotime($a) <= strtotime($b)) ? $a : $b;
}
function idf_daysBetweenCalendar($start, $end){
    if (!$start || !$end) return null;
    $s = strtotime(substr($start, 0, 10));
    $e = strtotime(substr($end, 0, 10));
    if ($s === false || $e === false) return null;
    return (int)floor(($e - $s) / 86400);
}

function idf_isDateInsideDemandeWindow($date, $dateDemande, $dateFin = null, $maxDays = null){
    if (!$date || !$dateDemande) return false;
    if (strtotime($date) < strtotime($dateDemande)) return false;
    $daysFromDemand = idf_daysBetweenCalendar($dateDemande, $date);
    if ($daysFromDemand === null || $daysFromDemand < 0) return false;
    if ($maxDays !== null && $daysFromDemand > $maxDays) return false;
    if ($dateFin !== null && strtotime($date) > strtotime($dateFin)) return false;
    return true;
}
function idf_pickValidOrdreEndDate(array $r, $maxDays = null){
    $dateDemande = idf_csvDateTime($r['date_demande'] ?? null);
    if ($dateDemande === null) {
        $dateOnly = idf_csvDate($r['date_demande'] ?? null);
        $dateDemande = $dateOnly ? $dateOnly . ' 00:00:00' : null;
    }

    $candidates = [
        idf_csvDateTime($r['date_fin_analyse'] ?? null),
        idf_csvDateTime($r['date_fin'] ?? null)
    ];

    /*
     * Real end date of an ordre/demande:
     * - keep the greatest valid date between date_fin_analyse and date_fin;
     * - reject only impossible negative dates versus date_demande;
     * - do not cap long positive delays, because they are real delays for the KPI.
     */
    $best = null;
    foreach ($candidates as $candidate) {
        if ($candidate === null) continue;
        if ($dateDemande !== null) {
            $days = idf_daysBetweenCalendar($dateDemande, $candidate);
            if ($days === null || $days < 0) continue;
            if ($maxDays !== null && $days > $maxDays) continue;
        }
        $best = idf_compareDateTime($best, $candidate);
    }

    return $best;
}

function idf_validOrdreStepDate(array $r, $column, $dateFinOrdre = null, $maxDays = null){
    $dateDemande = idf_csvDateTime($r['date_demande'] ?? null);
    if ($dateDemande === null) {
        $dateOnly = idf_csvDate($r['date_demande'] ?? null);
        $dateDemande = $dateOnly ? $dateOnly . ' 00:00:00' : null;
    }
    $date = idf_csvDateTime($r[$column] ?? null);
    return idf_isDateInsideDemandeWindow($date, $dateDemande, $dateFinOrdre, $maxDays) ? $date : null;
}


function idf_normalizeConformiteFromOrdre($r){
    return normalizeConformite(
        safeVarchar($r['conformite_libelle'] ?? $r['conformite_code'] ?? null, 100)
    );
}

function idf_normalizeDelaiToDays($value){
    $v = cleanString($value);
    if ($v === null || $v === '' || $v === '\\N' || strtoupper($v) === 'NULL') return null;

    $txt = mb_strtolower(str_replace(',', '.', $v), 'UTF-8');
    if (!preg_match('/-?[0-9]+(?:[.][0-9]+)?/', $txt, $m)) return null;

    $n = (float)$m[0];
    $days = $n;

    if (preg_match('/heure|heures|hour|hours|\bh\b/u', $txt)) {
        $days = $n / 24.0;
    } else if (preg_match('/minute|minutes|\bmin\b/u', $txt)) {
        $days = $n / 1440.0;
    } else if (preg_match('/semaine|semaines|week|weeks/u', $txt)) {
        $days = $n * 7.0;
    } else if (preg_match('/mois|month|months/u', $txt)) {
        $days = $n * 30.0;
    }

    $out = rtrim(rtrim(number_format($days, 4, '.', ''), '0'), '.');
    return $out === '-0' ? '0' : $out;
}

function idf_delaiMapKeysFromRow(array $r){
    $keys = [];
    $demande = cleanInt($r['demande_id'] ?? null);
    $analyse = cleanInt($r['analyse_id'] ?? null);
    $ech = cleanInt($r['echantillon_id'] ?? null);
    $refAnalyse = cleanString($r['reference_analyse'] ?? $r['analyse_reference'] ?? $r['code_analyse'] ?? null);

    if ($demande !== null && $analyse !== null && $ech !== null) {
        $keys[] = $demande . '|A:' . $analyse . '|E:' . $ech;
    }
    if ($demande !== null && $analyse !== null) {
        $keys[] = $demande . '|A:' . $analyse;
    }
    if ($demande !== null && $refAnalyse !== null && $refAnalyse !== '') {
        $keys[] = $demande . '|R:' . mb_strtolower($refAnalyse, 'UTF-8');
    }
    return $keys;
}

function idf_buildDelaiPrefixeMapFromDemandes($file){
    $map = [];
    if ($file === null || !file_exists($file)) return $map;

    list($handle, $delimiter, $headers) = openCsvAssoc($file);
    while (($r = nextAssocRow($handle, $delimiter, $headers)) !== false) {
        $delaiDays = idf_normalizeDelaiToDays($r['delai'] ?? null);
        if ($delaiDays === null) continue;
        foreach (idf_delaiMapKeysFromRow($r) as $key) {
            if (!isset($map[$key])) $map[$key] = $delaiDays;
        }
    }
    fclose($handle);
    return $map;
}

function idf_findDelaiPrefixeFromMap(array $map, array $r){
    foreach (idf_delaiMapKeysFromRow($r) as $key) {
        if (isset($map[$key])) return $map[$key];
    }
    return null;
}
function idf_upsertClient($conn, $idClientRaw, $nomClientRaw){
    $id_client = cleanInt($idClientRaw);
    if ($id_client === null) return;
    $client_name = buildClientName($idClientRaw, $nomClientRaw);
    $sql = "INSERT INTO client (id_client, client_name)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
                client_name = COALESCE(client.client_name, VALUES(client_name))";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare client : " . $conn->error);
    $stmt->bind_param("is", $id_client, $client_name);
    if (!$stmt->execute()) throw new Exception("Erreur execute client : " . $stmt->error);
    $stmt->close();
}
function idf_upsertSecteur($conn, $idSecteurRaw, $nomSecteurRaw){
    $id_secteur = cleanInt($idSecteurRaw);
    if ($id_secteur === null) return;
    $nom_secteur = buildSecteurName($idSecteurRaw, $nomSecteurRaw);
    $sql = "INSERT INTO secteur (id_secteur, nom_secteur)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
                nom_secteur = COALESCE(secteur.nom_secteur, VALUES(nom_secteur))";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare secteur : " . $conn->error);
    $stmt->bind_param("is", $id_secteur, $nom_secteur);
    if (!$stmt->execute()) throw new Exception("Erreur execute secteur : " . $stmt->error);
    $stmt->close();
}
function idf_upsertLaborantin($conn, $idLaborantinRaw, $nomLaborantinRaw, $idSecteurRaw = null){
    $id_laborantin = cleanInt($idLaborantinRaw);
    if ($id_laborantin === null) return;
    $nom_laborantin = buildLaborantinName($idLaborantinRaw, $nomLaborantinRaw);
    $id_secteur = cleanInt($idSecteurRaw);
    $sql = "INSERT INTO laborantin (id_laborantin, nom_laborantin, id_secteur)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nom_laborantin = COALESCE(laborantin.nom_laborantin, VALUES(nom_laborantin)),
                id_secteur = COALESCE(laborantin.id_secteur, VALUES(id_secteur))";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare laborantin : " . $conn->error);
    $stmt->bind_param("isi", $id_laborantin, $nom_laborantin, $id_secteur);
    if (!$stmt->execute()) throw new Exception("Erreur execute laborantin : " . $stmt->error);
    $stmt->close();
}
function idf_upsertAnalyse($conn, $idAnalyseRaw, $nomAnalyseRaw, $idSecteurRaw = null){
    $id_analyse = cleanInt($idAnalyseRaw);
    if ($id_analyse === null) return;
    $nom_analyse = cleanString($nomAnalyseRaw) ?: ('analyse_' . $id_analyse);
    $id_secteur = cleanInt($idSecteurRaw);
    $sql = "INSERT INTO analyses (id_analyse, nom_analyse, id_secteur)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nom_analyse = COALESCE(analyses.nom_analyse, VALUES(nom_analyse)),
                id_secteur = COALESCE(analyses.id_secteur, VALUES(id_secteur))";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare analyses : " . $conn->error);
    $stmt->bind_param("isi", $id_analyse, $nom_analyse, $id_secteur);
    if (!$stmt->execute()) throw new Exception("Erreur execute analyses : " . $stmt->error);
    $stmt->close();
}
function idf_ensureDemandeAnalyseExists($conn, $r){
    $id_demande = cleanInt($r['demande_id'] ?? null);
    if ($id_demande === null) return;
    idf_upsertClient($conn, $r['client_id'] ?? null, $r['client_nom'] ?? null);
    idf_upsertSecteur($conn, $r['id_secteur'] ?? null, $r['secteur_nom'] ?? null);
    $client_id = cleanInt($r['client_id'] ?? null);
    $ref_demande = safeVarchar($r['reference_demande'] ?? null, 250);
    $date_demande = idf_csvDate($r['date_demande'] ?? null);
    $date_creation = idf_csvDateTime($r['date_creation'] ?? null) ?: ($date_demande ? $date_demande . ' 00:00:00' : null);
    $id_secteur = cleanInt($r['id_secteur'] ?? null);
    $preleve_par = $client_id !== null ? (string)$client_id : null;

    $sql = "INSERT INTO demande_analyse (id_demande, ref_demande, date_demande, date_creation, id_client, id_secteur, preleve_par)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                ref_demande = COALESCE(demande_analyse.ref_demande, VALUES(ref_demande)),
                date_demande = COALESCE(demande_analyse.date_demande, VALUES(date_demande)),
                date_creation = COALESCE(demande_analyse.date_creation, VALUES(date_creation)),
                id_client = COALESCE(demande_analyse.id_client, VALUES(id_client)),
                id_secteur = COALESCE(demande_analyse.id_secteur, VALUES(id_secteur)),
                preleve_par = COALESCE(demande_analyse.preleve_par, VALUES(preleve_par))";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare ensure demande_analyse : " . $conn->error);
    $stmt->bind_param("isssiis", $id_demande, $ref_demande, $date_demande, $date_creation, $client_id, $id_secteur, $preleve_par);
    if (!$stmt->execute()) throw new Exception("Erreur execute ensure demande_analyse : " . $stmt->error);
    $stmt->close();
}
function idf_ensureEchantillonExists($conn, $r){
    $id_echantillon = cleanInt($r['echantillon_id'] ?? null);
    if ($id_echantillon === null) return;
    $id_demande = cleanInt($r['demande_id'] ?? null);
    $type_echantillon = safeVarchar($r['echantillon_type'] ?? null, 255);
    $date_reception = idf_csvCombineDateHeure($r['date_reception'] ?? null, $r['heure_reception'] ?? null);
    $est_conforme = idf_normalizeConformiteFromOrdre($r);
    $sql = "INSERT INTO echantillons
            (id_echantillon, id_demande, type_echantillon, date_reception, est_conforme)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_demande = COALESCE(echantillons.id_demande, VALUES(id_demande)),
                type_echantillon = COALESCE(echantillons.type_echantillon, VALUES(type_echantillon)),
                date_reception = COALESCE(echantillons.date_reception, VALUES(date_reception)),
                est_conforme = CASE
                    WHEN (echantillons.est_conforme IS NULL OR echantillons.est_conforme = '') AND VALUES(est_conforme) IS NOT NULL AND VALUES(est_conforme) <> ''
                    THEN VALUES(est_conforme)
                    ELSE echantillons.est_conforme
                END";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare ensure echantillon : " . $conn->error);
    $stmt->bind_param("iisss", $id_echantillon, $id_demande, $type_echantillon, $date_reception, $est_conforme);
    if (!$stmt->execute()) throw new Exception("Erreur execute ensure echantillon : " . $stmt->error);
    $stmt->close();
}
function idf_upsertFichePaillasse($conn, $r){
    $id_fiche = cleanInt($r['fiche_paillasse_id'] ?? null);
    if ($id_fiche === null) return false;
    idf_ensureDemandeAnalyseExists($conn, $r);
    idf_upsertSecteur($conn, $r['id_secteur'] ?? null, $r['secteur_nom'] ?? null);
    idf_upsertLaborantin($conn, $r['laborantin_id'] ?? null, $r['laborantin_nom'] ?? null, $r['id_secteur'] ?? null);
    $id_demande = cleanInt($r['demande_id'] ?? null);
    $id_laborantin = cleanInt($r['laborantin_id'] ?? null);
    $statut = safeVarchar($r['statut_libelle'] ?? null, 100);
    $date_affectation = idf_csvCombineDateHeure($r['date_affectation'] ?? null, $r['heure_affectation'] ?? null);
    $date_debut_analyse = idf_csvDateTime($r['date_debut_analyse'] ?? null) ?: $date_affectation;
    $date_fin = idf_compareDateTime(
        idf_csvDateTime($r['date_fin_analyse'] ?? null),
        idf_csvCombineDateHeure($r['date_fin'] ?? null, $r['heure_fin'] ?? null)
    );
    $date_validation = idf_csvDateTime($r['date_validation'] ?? null);
    $date_creation_rapport = $date_validation;
    $id_secteur = cleanInt($r['id_secteur'] ?? null);
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
function idf_upsertOrdreAnalyse($conn, $r, $delaiPrefixeMap = []){
    $ordre_id = cleanInt($r['ordre_id'] ?? null);
    $ordre_cle = cleanString($r['ordre_cle'] ?? null);
    if ($ordre_id === null || $ordre_cle === null) return false;
    idf_ensureDemandeAnalyseExists($conn, $r);
    idf_ensureEchantillonExists($conn, $r);
    idf_upsertClient($conn, $r['client_id'] ?? null, $r['client_nom'] ?? null);
    idf_upsertSecteur($conn, $r['id_secteur'] ?? null, $r['secteur_nom'] ?? null);
    idf_upsertLaborantin($conn, $r['laborantin_id'] ?? null, $r['laborantin_nom'] ?? null, $r['id_secteur'] ?? null);
    idf_upsertAnalyse($conn, $r['analyse_id'] ?? null, $r['analyse_nom'] ?? null, $r['id_secteur'] ?? null);
    $ordre_reference = safeVarchar($r['reference_ordre'] ?? null, 250);
    $client_id = cleanInt($r['client_id'] ?? null);
    $demande_id = cleanInt($r['demande_id'] ?? null);
    $analyse_id = cleanInt($r['analyse_id'] ?? null);
    $delai_prefixe_analyse_heure = idf_findDelaiPrefixeFromMap($delaiPrefixeMap, $r);
    if ($delai_prefixe_analyse_heure === null) {
        $delai_prefixe_analyse_heure = idf_normalizeDelaiToDays($r['delai_prefixe_analyse_heure'] ?? $r['delaianalyse'] ?? $r['delai'] ?? null);
    }
    $delai_prefixe_analyse_heure = safeVarchar($delai_prefixe_analyse_heure, 100);
    $laborantin_id = cleanInt($r['laborantin_id'] ?? null);
    $fiche_paillasse_id = cleanInt($r['fiche_paillasse_id'] ?? null);
    $echantillon_id = cleanInt($r['echantillon_id'] ?? null);
    $resultat = normalizeResultText($r['resultat'] ?? null);
    $date_demande_only = idf_csvDate($r['date_demande'] ?? null);
    $date_affectation = idf_csvDateTime($r['date_affectation'] ?? null);
    $date_reception = idf_csvDateTime($r['date_reception'] ?? null);
    $date_fin = idf_compareDateTime(
        idf_csvDateTime($r['date_fin_analyse'] ?? null),
        idf_csvDateTime($r['date_fin'] ?? null)
    );
    $date_validation = idf_csvDateTime($r['date_validation'] ?? null);
    $date_creation = idf_csvDateTime($r['date_creation'] ?? null);
    $duree_analyse_jours = daysBetween($date_affectation, $date_fin);
    $delai_prise_en_charge_jours = daysBetween($date_reception, $date_affectation);
    $conformite_libelle = safeVarchar($r['conformite_libelle'] ?? null, 100);
    $conformite_code = safeVarchar($r['conformite_code'] ?? null, 50);
    $date_extraction_only = idf_csvDate($r['date_extraction'] ?? null);
    $statut = safeVarchar($r['statut_libelle'] ?? null, 50);
    $statut_code = safeVarchar($r['statut_code'] ?? null, 50);
    $sql = "INSERT INTO ordres_analyses
        (ordre_cle, ordre_id, ordre_reference, client_id, demande_id, analyse_id, delai_prefixe_analyse_heure, laborantin_id, fiche_paillasse_id, echantillon_id, resultat, date_demande, date_affectation, date_reception, date_fin, date_validation, date_creation, duree_analyse_jours, delai_prise_en_charge_jours, conformite_libelle, conformite_code, date_extraction, statut, statut_code)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        
        ON DUPLICATE KEY UPDATE
            ordre_reference = COALESCE(VALUES(ordre_reference), ordre_reference),
            client_id = COALESCE(VALUES(client_id), client_id),
            demande_id = COALESCE(VALUES(demande_id), demande_id),
            analyse_id = COALESCE(VALUES(analyse_id), analyse_id),
            delai_prefixe_analyse_heure = COALESCE(VALUES(delai_prefixe_analyse_heure), delai_prefixe_analyse_heure),
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
        "sisiiisiiisssssssddsssss",
        $ordre_cle,
        $ordre_id,
        $ordre_reference,
        $client_id,
        $demande_id,
        $analyse_id,
        $delai_prefixe_analyse_heure,
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
function idf_upsertResultatAnalyse($conn, $r){
    $ordre_id = cleanInt($r['ordre_id'] ?? null);
    $id_demande = cleanInt($r['demande_id'] ?? null);
    $id_analyse = cleanInt($r['analyse_id'] ?? null);
    $id_laborantin = cleanInt($r['laborantin_id'] ?? null);
    if ($ordre_id === null || $id_demande === null || $id_analyse === null) return false;
    $methode = null;
    $unite = null;
    $ordre_affichage = 1;
    $created_at = idf_csvDateTime($r['date_creation'] ?? null) ?: date('Y-m-d H:i:s');
    $is_accredited = 1;
    $result_note = safeVarchar($r['conformite_libelle'] ?? null, 255);
    $report_section = null;
    $resultat = normalizeResultText($r['resultat'] ?? null);
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
                SET id_demande = ?, id_analyse = ?, id_laborantin = ?, methode = COALESCE(methode, ?), resultat = ?, unite = COALESCE(unite, ?), ordre_affichage = COALESCE(ordre_affichage, ?), created_at = ?, is_accredited = ?, result_note = ?, report_section = COALESCE(report_section, ?)
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Erreur prepare update resultat_analyse : " . $conn->error);
        $stmt->bind_param("iiisssissisi", $id_demande, $id_analyse, $id_laborantin, $methode, $resultat, $unite, $ordre_affichage, $created_at, $is_accredited, $result_note, $report_section, $id);
    } else {
        $sql = "INSERT INTO resultat_analyse (id_demande, ordre_id, id_analyse, id_laborantin, methode, resultat, unite, ordre_affichage, created_at, is_accredited, result_note, report_section)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Erreur prepare insert resultat_analyse : " . $conn->error);
        $stmt->bind_param("iiiisssissss", $id_demande, $ordre_id, $id_analyse, $id_laborantin, $methode, $resultat, $unite, $ordre_affichage, $created_at, $is_accredited, $result_note, $report_section);
    }
    if (!$stmt->execute()) throw new Exception("Erreur execute resultat_analyse : " . $stmt->error);
    $stmt->close();
    return true;
}
function idf_updateDemandeFromOrdre($conn, $r){
    $id_demande = cleanInt($r['demande_id'] ?? null);
    if ($id_demande === null) return;
    $date_fin_ordre = idf_pickValidOrdreEndDate($r);

    $date_reception = idf_validOrdreStepDate($r, 'date_reception', $date_fin_ordre);
    $date_affectation = idf_validOrdreStepDate($r, 'date_affectation', $date_fin_ordre);
    $date_debut_analyse = idf_validOrdreStepDate($r, 'date_debut_analyse', $date_fin_ordre);
    if ($date_debut_analyse === null) {
        $date_debut_analyse = $date_affectation;
    }

    $date_cloture = $date_fin_ordre;
    $date_fin_analyse = $date_fin_ordre;
    $date_validation = idf_csvDate($r['date_validation'] ?? null);

    $sql = "UPDATE demande_analyse
            SET
                date_reception = CASE
                    WHEN ? IS NULL THEN date_reception
                    WHEN date_reception IS NULL THEN ?
                    WHEN ? < date_reception THEN ?
                    ELSE date_reception
                END,

                date_affectation = CASE
                    WHEN ? IS NULL THEN date_affectation
                    WHEN date_affectation IS NULL THEN ?
                    WHEN ? < date_affectation THEN ?
                    ELSE date_affectation
                END,

                date_debut_analyse = CASE
                    WHEN ? IS NULL THEN date_debut_analyse
                    WHEN date_debut_analyse IS NULL THEN ?
                    WHEN ? < date_debut_analyse THEN ?
                    ELSE date_debut_analyse
                END,

                date_cloture = CASE
                    WHEN ? IS NULL THEN date_cloture
                    WHEN date_cloture IS NULL THEN ?
                    WHEN ? > date_cloture THEN ?
                    ELSE date_cloture
                END,

                date_fin_analyse = CASE
                    WHEN ? IS NULL THEN date_fin_analyse
                    WHEN date_fin_analyse IS NULL THEN ?
                    WHEN ? > date_fin_analyse THEN ?
                    ELSE date_fin_analyse
                END,

                date_validation = COALESCE(?, date_validation)
            WHERE id_demande = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erreur prepare update demande_analyse(ordres) : " . $conn->error);
    }

    $stmt->bind_param(
        "sssssssssssssssssssssi",
        $date_reception,
        $date_reception,
        $date_reception,
        $date_reception,

        $date_affectation,
        $date_affectation,
        $date_affectation,
        $date_affectation,

        $date_debut_analyse,
        $date_debut_analyse,
        $date_debut_analyse,
        $date_debut_analyse,

        $date_cloture,
        $date_cloture,
        $date_cloture,
        $date_cloture,

        $date_fin_analyse,
        $date_fin_analyse,
        $date_fin_analyse,
        $date_fin_analyse,

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
    $fichier = resolveCsvPath([
        __DIR__ . '/../../data_source/ordres_analyses.csv',
        __DIR__ . '/ordres_analyses.csv',
        '/mnt/data/ordres_analyses.csv',
    ]);
    $fichierDemandesDelai = resolveCsvPath([
        __DIR__ . '/../../data_source/demande_anan(20).csv',
        __DIR__ . '/demande_anan(20).csv',
        '/mnt/data/demande_anan(20).csv',
    ]);
    $delaiPrefixeMap = idf_buildDelaiPrefixeMapFromDemandes($fichierDemandesDelai);
    list($handle, $delimiter, $headers) = openCsvAssoc($fichier);
    echo "Début import_data.php...\n";
    echo "ordres file: " . basename($fichier) . "\n";
    echo "demandes delai file: " . basename($fichierDemandesDelai) . "\n";
    $conn->begin_transaction();
    $nbOrdres = 0;
    $nbResultats = 0;
    $nbFiches = 0;
    while (($r = nextAssocRow($handle, $delimiter, $headers)) !== false) {
        if (idf_upsertFichePaillasse($conn, $r)) $nbFiches++;
        if (idf_upsertOrdreAnalyse($conn, $r, $delaiPrefixeMap)) $nbOrdres++;
        if (idf_upsertResultatAnalyse($conn, $r)) $nbResultats++;
        idf_updateDemandeFromOrdre($conn, $r);
    }
    fclose($handle);
    $conn->commit();
    echo "ordres_analyses: {$nbOrdres}\n";
    echo "resultat_analyse: {$nbResultats}\n";
    echo "fiches_paillasse: {$nbFiches}\n";
} catch (Exception $e) {
    if (isset($conn)) {
        @$conn->rollback();
    }
    echo "Erreur import_data.php : " . $e->getMessage();
}
?>