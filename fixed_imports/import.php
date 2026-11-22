<?php
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/common_import_helpers.php';

set_time_limit(0);
ini_set('memory_limit', '1024M');

function importerDemandeClient(mysqli $conn, string $file): int {
    [$handle, $delimiter, $headers] = openCsvAssocSmart($file);
    $count = 0;

    $sql = "INSERT INTO demande_client
    (
        demande_id,
        ref_demande,
        client_id,
        analyse_id,
        analyse_nom,
        date_demande,
        date_reception,
        date_verif,
        faisabilite,
        validite
    )
    VALUES (?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        ref_demande = COALESCE(VALUES(ref_demande), ref_demande),
        client_id = COALESCE(VALUES(client_id), client_id),
        analyse_nom = COALESCE(VALUES(analyse_nom), analyse_nom),
        date_demande = COALESCE(VALUES(date_demande), date_demande),
        date_reception = COALESCE(VALUES(date_reception), date_reception),
        date_verif = COALESCE(VALUES(date_verif), date_verif),
        faisabilite = COALESCE(VALUES(faisabilite), faisabilite),
        validite = COALESCE(VALUES(validite), validite)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Erreur prepare demande_client : ' . $conn->error);

    $seen = [];
    while (($r = nextAssocRowSmart($handle, $delimiter, $headers)) !== false) {
        $demandeId = cleanInt($r['dc_demande_id'] ?? null);
        $analyseId = cleanInt($r['analyse_id'] ?? null);
        if ($demandeId === null || $analyseId === null) continue;

        upsertClientCommon($conn, $r['dc_client_id'] ?? null, $r['client_name'] ?? null);
        upsertSecteurCommon($conn, $r['secteur_id'] ?? null, $r['secteur_nom'] ?? null);
        upsertAnalyseCommon(
            $conn,
            $r['analyse_id'] ?? null,
            $r['analyse_nom'] ?? null,
            $r['secteur_id'] ?? null,
            $r['analyse_code'] ?? null,
            $r['methodeanalyse'] ?? null,
            $r['unite_result'] ?? null
        );
        upsertCritereCommon(
            $conn,
            $r['analyse_id'] ?? null,
            $r['analyse_nom'] ?? null,
            $r['methodeanalyse'] ?? null,
            $r['unite_result'] ?? null
        );

        $key = $demandeId . '_' . $analyseId;
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        $refDemande = cleanString($r['dc_ref_demande'] ?? null);
        $clientId = cleanInt($r['dc_client_id'] ?? null);
        $analyseNom = cleanString($r['analyse_nom'] ?? null);
        $dateDemande = cleanDateTimeYMDStrict($r['dc_date_demande'] ?? null);
        $dateReception = combineDateHeureYMD($r['dc_date_reception'] ?? null, $r['dc_heure_reception'] ?? null);
        $dateVerif = combineDateHeureYMD($r['an_date_verif'] ?? null, $r['an_heure_verif'] ?? null);
        $faisabilite = normalizeOuiNon($r['dc_faisabilite'] ?? null);
        $validite = normalizeOuiNon($r['dc_validite'] ?? null);

        $stmt->bind_param(
            'isisssssss',
            $demandeId,
            $refDemande,
            $clientId,
            $analyseId,
            $analyseNom,
            $dateDemande,
            $dateReception,
            $dateVerif,
            $faisabilite,
            $validite
        );

        if (!$stmt->execute()) {
            throw new Exception('Erreur execute demande_client : ' . $stmt->error);
        }
        $count++;
    }

    fclose($handle);
    $stmt->close();
    return $count;
}

function importerDevis(mysqli $conn, string $file): int {
    [$handle, $delimiter, $headers] = openCsvAssocSmart($file);
    $count = 0;

    $sql = "INSERT INTO devis
    (
        id,
        t_ref,
        client_id,
        date_crea,
        date_fact,
        ref_fact_erp,
        etat
    )
    VALUES (?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        t_ref = COALESCE(VALUES(t_ref), t_ref),
        client_id = COALESCE(VALUES(client_id), client_id),
        date_crea = COALESCE(VALUES(date_crea), date_crea),
        date_fact = COALESCE(VALUES(date_fact), date_fact),
        ref_fact_erp = COALESCE(VALUES(ref_fact_erp), ref_fact_erp),
        etat = COALESCE(VALUES(etat), etat)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Erreur prepare devis : ' . $conn->error);

    while (($r = nextAssocRowSmart($handle, $delimiter, $headers)) !== false) {
        $id = cleanInt($r['id'] ?? null);
        if ($id === null) continue;

        $clientId = cleanInt($r['t_client'] ?? null);
        if ($clientId !== null) {
            upsertClientCommon($conn, $clientId, null);
        }

        $tRef = cleanString($r['t_ref'] ?? null);
        $dateCrea = cleanDateTimeFlexible($r['date_crea'] ?? null);
        $dateFact = cleanDateTimeFlexible($r['date_fact'] ?? null);
        $refFactErp = cleanString($r['ref_fact_erp'] ?? null);
        $etat = cleanString($r['etat'] ?? null);

        $stmt->bind_param('iisssss', $id, $tRef, $clientId, $dateCrea, $dateFact, $refFactErp, $etat);
        if (!$stmt->execute()) {
            throw new Exception('Erreur execute devis : ' . $stmt->error);
        }
        $count++;
    }

    fclose($handle);
    $stmt->close();
    return $count;
}

try {
    $conn->set_charset('utf8mb4');
    $csvDemandeClient = firstExistingPath([
        __DIR__ . '/../../data_source/dc.csv',
        __DIR__ . '/../../data_source/csv_dcclient(dc).csv',
        __DIR__ . '/dc.csv',
        '/mnt/data/dc.csv'
    ]);
    $csvDevis = firstExistingPath([
        __DIR__ . '/../../data_source/devis.csv',
        __DIR__ . '/../../data_source/csv_devis.csv',
        __DIR__ . '/devis.csv',
        '/mnt/data/devis.csv'
    ]);

    echo "Début import.php...\n";
    $conn->begin_transaction();
    $nb1 = importerDemandeClient($conn, $csvDemandeClient);
    $nb2 = importerDevis($conn, $csvDevis);
    $conn->commit();
    echo "Import terminé\n";
    echo "demande_client: $nb1\n";
    echo "devis: $nb2\n";
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    echo 'Erreur import.php : ' . $e->getMessage();
}
?>
