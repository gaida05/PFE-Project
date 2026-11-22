<?php
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/common_import_helpers.php';

set_time_limit(0);
ini_set('memory_limit', '1024M');

function upsertDemandeAnalyseFromCsv(mysqli $conn, array $r): bool {
    $id_demande = cleanInt($r['demande_id'] ?? null);
    if ($id_demande === null) return false;

    upsertClientCommon($conn, $r['id_client'] ?? null, $r['client_name'] ?? null);
    upsertSecteurCommon($conn, $r['secteur_id'] ?? null, $r['secteur_name'] ?? null);
    upsertAnalyseCommon(
        $conn,
        $r['analyse_id'] ?? null,
        $r['nom_analyse'] ?? null,
        $r['secteur_id'] ?? null,
        $r['reference_analyse'] ?? null,
        $r['methodeanalyse'] ?? null,
        $r['unite_result'] ?? null
    );
    upsertLaborantinCommon($conn, $r['first_laborantin_id'] ?? null, $r['first_laborantin_nom'] ?? null, $r['secteur_id'] ?? null);
    upsertLaborantinCommon($conn, $r['second_laborantin_id'] ?? null, $r['second_laborantin_nom'] ?? null, $r['secteur_id'] ?? null);
    upsertCritereCommon($conn, $r['critere_id'] ?? null, $r['critere_titre'] ?? null, $r['critere_resultat'] ?? null, $r['critere_rule'] ?? null);

    $ref_demande = cleanString($r['ref_demande'] ?? null);
    $id_secteur = cleanInt($r['secteur_id'] ?? null);
    $id_client = cleanInt($r['id_client'] ?? null);
    $date_demande = cleanDateTimeYMDStrict($r['date_demande'] ?? null);
    $date_creation = cleanDateTimeYMDStrict($r['date_creation_da'] ?? null) ?: $date_demande;
    $date_cloture = combineDateHeureYMD($r['date_verif'] ?? null, $r['heure_verif'] ?? null);
    $date_reception = combineDateHeureYMD($r['date_reception'] ?? null, $r['heure_reception'] ?? null);
    $date_verif = combineDateHeureYMD($r['date_verif'] ?? null, $r['heure_verif'] ?? null);
    $statut = cleanString($r['statut'] ?? null);
    $date_prelevement = cleanDateTimeYMDStrict($r['date_prelevement'] ?? null);
    $date_prelevement_only = $date_prelevement ? date('Y-m-d', strtotime($date_prelevement)) : null;
    $preleve_par = cleanString($r['nom_preleveur'] ?? null);

    $sql = "INSERT INTO demande_analyse
            (id_demande, ref_demande, id_secteur, date_demande, date_creation, date_cloture, statut, preleve_par, date_prelevement, date_reception, date_verif, id_client)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                ref_demande = COALESCE(VALUES(ref_demande), ref_demande),
                id_secteur = COALESCE(VALUES(id_secteur), id_secteur),
                date_demande = COALESCE(VALUES(date_demande), date_demande),
                date_creation = COALESCE(VALUES(date_creation), date_creation),
                date_cloture = COALESCE(VALUES(date_cloture), date_cloture),
                statut = COALESCE(VALUES(statut), statut),
                preleve_par = COALESCE(VALUES(preleve_par), preleve_par),
                date_prelevement = COALESCE(VALUES(date_prelevement), date_prelevement),
                date_reception = COALESCE(VALUES(date_reception), date_reception),
                date_verif = COALESCE(VALUES(date_verif), date_verif),
                id_client = COALESCE(VALUES(id_client), id_client)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Erreur prepare demande_analyse : ' . $conn->error);
    $stmt->bind_param('isissssssssi', $id_demande, $ref_demande, $id_secteur, $date_demande, $date_creation, $date_cloture, $statut, $preleve_par, $date_prelevement_only, $date_reception, $date_verif, $id_client);
    if (!$stmt->execute()) throw new Exception('Erreur execute demande_analyse : ' . $stmt->error);
    $stmt->close();
    return true;
}

function upsertEchantillonFromCsv(mysqli $conn, array $r): bool {
    $id_echantillon = cleanInt($r['echantillon_id'] ?? null);
    if ($id_echantillon === null) return false;

    $id_demande = cleanInt($r['demande_id'] ?? null);
    $type_echantillon = cleanString($r['echantillon_type'] ?? null);
    $est_conforme = normalizeConformiteValue($r['conformite'] ?? null);
    $date_reception = combineDateHeureYMD($r['date_reception'] ?? null, $r['heure_reception'] ?? null);
    $ref_labo = cleanString($r['reflabo'] ?? null);
    $numero_lot = cleanString($r['reflot_deux'] ?? null);

    $sql = "INSERT INTO echantillons
            (id_echantillon, id_demande, type_echantillon, est_conforme, date_reception, numero_lot, ref_labo)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_demande = COALESCE(VALUES(id_demande), id_demande),
                type_echantillon = COALESCE(VALUES(type_echantillon), type_echantillon),
                est_conforme = COALESCE(VALUES(est_conforme), est_conforme),
                date_reception = COALESCE(VALUES(date_reception), date_reception),
                numero_lot = COALESCE(VALUES(numero_lot), numero_lot),
                ref_labo = COALESCE(VALUES(ref_labo), ref_labo)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Erreur prepare echantillons : ' . $conn->error);
    $stmt->bind_param('iisssss', $id_echantillon, $id_demande, $type_echantillon, $est_conforme, $date_reception, $numero_lot, $ref_labo);
    if (!$stmt->execute()) throw new Exception('Erreur execute echantillons : ' . $stmt->error);
    $stmt->close();
    return true;
}

function importerDemandesAnalyse(mysqli $conn, string $file): array {
    [$handle, $delimiter, $headers] = openCsvAssocSmart($file);
    $countDemandes = 0;
    $countEchantillons = 0;

    while (($r = nextAssocRowSmart($handle, $delimiter, $headers)) !== false) {
        if (upsertDemandeAnalyseFromCsv($conn, $r)) $countDemandes++;
        if (upsertEchantillonFromCsv($conn, $r)) $countEchantillons++;
    }

    fclose($handle);
    return [$countDemandes, $countEchantillons];
}

function upsertRapportFromCsv(mysqli $conn, array $r): bool {
    $id_rapport = cleanString($r['rapport_id'] ?? null);
    if ($id_rapport === null) return false;

    upsertSecteurCommon($conn, $r['secteur_id'] ?? null, $r['secteur_name'] ?? null);
    upsertAnalyseCommon(
        $conn,
        $r['analyse_id'] ?? null,
        $r['nom_analyse'] ?? null,
        $r['secteur_id'] ?? null,
        $r['reference_analyse'] ?? null,
        $r['methodeanalyse'] ?? null,
        $r['ra_unite_result'] ?? null
    );
    upsertLaborantinCommon($conn, $r['manipulateur_id'] ?? null, $r['manipulateur_nom'] ?? null, $r['secteur_id'] ?? null);
    upsertLaborantinCommon($conn, $r['validateur_id'] ?? null, $r['validateur_nom'] ?? null, $r['secteur_id'] ?? null);
    upsertCritereCommon($conn, $r['critere_id'] ?? null, $r['critere_titre'] ?? null, $r['des_critere'] ?? null, $r['critere_rule'] ?? null);

    $rapport = cleanString($r['ref_rapport'] ?? null) ?: $id_rapport;
    $id_demande = cleanInt($r['demande_id'] ?? null);
    $type_rapport = cleanString($r['type_rapport_selon_demande'] ?? null) ?: cleanString($r['type_rapport'] ?? null);
    $date_edition = cleanDateTimeYMDStrict($r['date_creation_rapport'] ?? null);
    $date_validation = cleanDateTimeYMDStrict($r['date_validation'] ?? null);
    $date_livraison_interne = $date_validation;
    $laborantin_validateur = cleanInt($r['validateur_id'] ?? null);
    $statut = $date_validation ? 'Validé' : 'En cours';
    $secteur_id = cleanInt($r['secteur_id'] ?? null);
    $id_csv = cleanInt($r['rapport_id'] ?? null);
    $responsable_essai = cleanString($r['manipulateur_nom'] ?? null);
    $responsable_technique = cleanString($r['validateur_nom'] ?? null);
    $observation = cleanString($r['ra_fr_commentaire'] ?? null);

    $sql = "INSERT INTO rapports
            (id_rapport, rapport, id_demande, type_rapport, date_edition, date_validation, date_livraison, date_livraison_interne, laborantin_validateur, statut, secteur_id, id_csv, responsable_essai, responsable_technique, observation)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                rapport = COALESCE(VALUES(rapport), rapport),
                id_demande = COALESCE(VALUES(id_demande), id_demande),
                type_rapport = COALESCE(VALUES(type_rapport), type_rapport),
                date_edition = COALESCE(VALUES(date_edition), date_edition),
                date_validation = COALESCE(VALUES(date_validation), date_validation),
                date_livraison = COALESCE(VALUES(date_livraison), date_livraison),
                date_livraison_interne = COALESCE(VALUES(date_livraison_interne), date_livraison_interne),
                laborantin_validateur = COALESCE(VALUES(laborantin_validateur), laborantin_validateur),
                statut = COALESCE(VALUES(statut), statut),
                secteur_id = COALESCE(VALUES(secteur_id), secteur_id),
                id_csv = COALESCE(VALUES(id_csv), id_csv),
                responsable_essai = COALESCE(VALUES(responsable_essai), responsable_essai),
                responsable_technique = COALESCE(VALUES(responsable_technique), responsable_technique),
                observation = COALESCE(VALUES(observation), observation)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Erreur prepare rapports : ' . $conn->error);
    $stmt->bind_param('ssisssssisiisss', $id_rapport, $rapport, $id_demande, $type_rapport, $date_edition, $date_validation, $date_livraison_interne, $date_livraison_interne, $laborantin_validateur, $statut, $secteur_id, $id_csv, $responsable_essai, $responsable_technique, $observation);
    if (!$stmt->execute()) throw new Exception('Erreur execute rapports : ' . $stmt->error);
    $stmt->close();
    return true;
}

function updateDemandeFromRapport(mysqli $conn, array $r): void {
    $id_demande = cleanInt($r['demande_id'] ?? null);
    if ($id_demande === null) return;

    $date_debut_analyse = cleanDateTimeYMDStrict($r['date_debut_analyse'] ?? null);
    $date_fin_analyse = cleanDateTimeYMDStrict($r['date_fin_analyse'] ?? null);
    $date_validation = cleanDateTimeYMDStrict($r['date_validation'] ?? null);

    $date_debut_only = $date_debut_analyse ? date('Y-m-d', strtotime($date_debut_analyse)) : null;
    $date_fin_only = $date_fin_analyse ? date('Y-m-d', strtotime($date_fin_analyse)) : null;

    $sql = "UPDATE demande_analyse
            SET date_debut_analyse = COALESCE(?, date_debut_analyse),
                date_fin_analyse = COALESCE(?, date_fin_analyse),
                date_validation = COALESCE(?, date_validation)
            WHERE id_demande = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Erreur prepare update demande_analyse(rapport) : ' . $conn->error);
    $stmt->bind_param('sssi', $date_debut_only, $date_fin_only, $date_validation, $id_demande);
    if (!$stmt->execute()) throw new Exception('Erreur execute update demande_analyse(rapport) : ' . $stmt->error);
    $stmt->close();
}

function importerRapports(mysqli $conn, string $file): int {
    [$handle, $delimiter, $headers] = openCsvAssocSmart($file);
    $count = 0;
    $seen = [];

    while (($r = nextAssocRowSmart($handle, $delimiter, $headers)) !== false) {
        $id_rapport = cleanString($r['rapport_id'] ?? null);
        if ($id_rapport !== null && !isset($seen[$id_rapport])) {
            if (upsertRapportFromCsv($conn, $r)) {
                $seen[$id_rapport] = true;
                $count++;
            }
        }
        updateDemandeFromRapport($conn, $r);
    }

    fclose($handle);
    return $count;
}

try {
    $conn->set_charset('utf8mb4');
    $csvDemandes = firstExistingPath([
        __DIR__ . '/../../data_source/demande_anan.csv',
        __DIR__ . '/demande_anan.csv',
        '/mnt/data/demande_anan.csv'
    ]);
    $csvRapports = firstExistingPath([
        __DIR__ . '/../../data_source/rapport.csv',
        __DIR__ . '/rapport.csv',
        '/mnt/data/rapport.csv'
    ]);

    echo "Début import_file.php...\n";
    $conn->begin_transaction();
    [$nbDemandes, $nbEchantillons] = importerDemandesAnalyse($conn, $csvDemandes);
    $nbRapports = importerRapports($conn, $csvRapports);
    $conn->commit();
    echo "demande_analyse: $nbDemandes\n";
    echo "echantillons: $nbEchantillons\n";
    echo "rapports: $nbRapports\n";
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    echo 'Erreur import_file.php : ' . $e->getMessage();
}
?>
