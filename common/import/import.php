<?php
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/clean_helpers.php';
require_once __DIR__ . '/csv_helpers.php';
require_once __DIR__ . '/transform_helpers.php';
set_time_limit(0);
ini_set('memory_limit', '1024M');
function dc_bind_and_execute(mysqli_stmt $stmt, array $params, string $label){
    $types = '';
    $refs = [];
    foreach ($params as $k => $v) {
        if (is_int($v)) {
            $types .= 'i';
        } elseif (is_float($v)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
        $refs[$k] = &$params[$k];
    }
    array_unshift($refs, $types);
    if (!call_user_func_array([$stmt, 'bind_param'], $refs)) {
        throw new Exception("Erreur bind {$label} : " . $stmt->error);
    }
    if (!$stmt->execute()) {
        throw new Exception("Erreur execute {$label} : " . $stmt->error);
    }
}

function dc_firstNonEmpty(array $row, array $keys){
    foreach ($keys as $k) {
        if (!array_key_exists($k, $row)) continue;
        $v = cleanString($row[$k]);
        if ($v !== null && $v !== '') return $v;
    }
    return null;
}

function dc_safeUniqueCodeEchantillon($conn, $idEchantillonRaw, $codeRaw){
    $id_echantillon = cleanInt($idEchantillonRaw);
    $code = safeVarchar($codeRaw, 100);
    if ($id_echantillon === null || $code === null || $code === '') return $code;

    static $cache = [];
    $cacheKey = $code . '|' . $id_echantillon;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $sql = "SELECT id_echantillon FROM echantillons WHERE code_echantillon = ? AND id_echantillon <> ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erreur prepare check code_echantillon : " . $conn->error);
    }
    $stmt->bind_param('si', $code, $id_echantillon);
    if (!$stmt->execute()) {
        throw new Exception("Erreur execute check code_echantillon : " . $stmt->error);
    }
    $res = $stmt->get_result();
    $conflict = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $cache[$cacheKey] = $conflict ? null : $code;
    return $cache[$cacheKey];
}
function upsertClientFromDc($conn, $idClientRaw, $nomClientRaw){
    $id_client = cleanInt($idClientRaw);
    if ($id_client === null) return;

    $client_name = buildClientName($idClientRaw, $nomClientRaw);
    $sql = "INSERT INTO client (id_client, client_name)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
                client_name = COALESCE(VALUES(client_name), client_name)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare client : " . $conn->error);
    dc_bind_and_execute($stmt, [$id_client, $client_name], 'client');
    $stmt->close();
}
function upsertAnalyseFromDc($conn, $idAnalyseRaw, $nomAnalyseRaw, $idSecteurRaw = null, $codeAnalyseRaw = null, $methodeRaw = null, $uniteRaw = null){
    $id_analyse = cleanInt($idAnalyseRaw);
    if ($id_analyse === null) return;
    $nom_analyse = cleanString($nomAnalyseRaw) ?: ('analyse_' . $id_analyse);
    $code_analyse = cleanString($codeAnalyseRaw);
    $methode_defaut = cleanString($methodeRaw);
    $unite_defaut = cleanString($uniteRaw);
    $id_secteur = cleanInt($idSecteurRaw);
    $sql = "INSERT INTO analyses (id_analyse, nom_analyse, code_analyse, methode_defaut, unite_defaut, id_secteur)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nom_analyse = COALESCE(VALUES(nom_analyse), nom_analyse),
                code_analyse = COALESCE(VALUES(code_analyse), code_analyse),
                methode_defaut = COALESCE(VALUES(methode_defaut), methode_defaut),
                unite_defaut = COALESCE(VALUES(unite_defaut), unite_defaut),
                id_secteur = COALESCE(VALUES(id_secteur), id_secteur)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare analyses : " . $conn->error);
    dc_bind_and_execute(
        $stmt,
        [$id_analyse, $nom_analyse, $code_analyse, $methode_defaut, $unite_defaut, $id_secteur],
        'analyses'
    );
    $stmt->close();
}
function upsertDemandeAnalyseFromDc($conn, array $r){
    $id_demande = cleanInt($r['demande_id'] ?? null);
    if ($id_demande === null) return;
    $id_client = cleanInt($r['client_id'] ?? null);
    $ref_demande = safeVarchar($r['ref_demande'] ?? null, 250);
    $date_demande = safeMysqlDate($r['date_demande'] ?? null);
    $date_creation = cleanDateTimeFlexible($r['date_creation'] ?? null) ?: $date_demande;
    $date_reception = combineDateHeureYMD($r['date_reception'] ?? null, $r['heure_reception'] ?? null);
    $date_prelevement = safeMysqlDate($r['date_prelevement'] ?? null);
    $producteur = safeVarchar(dc_firstNonEmpty($r, ['producteur_name', 'producteur_id', 'producteur']), 255);
    $importateur = safeVarchar(dc_firstNonEmpty($r, ['importateur_name', 'importateur_id', 'importateur']), 255);
    $preleve_par = safeVarchar(dc_firstNonEmpty($r, ['demandeur_name', 'demandeur_id']), 255);
    $faisabilite = normalizeOuiNon($r['faisabilite'] ?? null);
    $validite = normalizeOuiNon($r['validite'] ?? null);
    $sql = "INSERT INTO demande_analyse
            (id_demande, ref_demande, id_client, date_demande, date_creation, date_reception, producteur, importateur, preleve_par, date_prelevement, faisabilite, validite)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                ref_demande = COALESCE(VALUES(ref_demande), ref_demande),
                id_client = COALESCE(VALUES(id_client), id_client),
                date_demande = COALESCE(VALUES(date_demande), date_demande),
                date_creation = COALESCE(VALUES(date_creation), date_creation),
                date_reception = COALESCE(VALUES(date_reception), date_reception),
                producteur = COALESCE(VALUES(producteur), producteur),
                importateur = COALESCE(VALUES(importateur), importateur),
                preleve_par = COALESCE(VALUES(preleve_par), preleve_par),
                date_prelevement = COALESCE(VALUES(date_prelevement), date_prelevement),
                faisabilite = CASE
                    WHEN (demande_analyse.faisabilite IS NULL OR demande_analyse.faisabilite = '') AND VALUES(faisabilite) IS NOT NULL AND VALUES(faisabilite) <> ''
                    THEN VALUES(faisabilite)
                    ELSE demande_analyse.faisabilite
                END,
                validite = CASE
                    WHEN (demande_analyse.validite IS NULL OR demande_analyse.validite = '') AND VALUES(validite) IS NOT NULL AND VALUES(validite) <> ''
                    THEN VALUES(validite)
                    ELSE demande_analyse.validite
                END";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare demande_analyse(dc) : " . $conn->error);
    dc_bind_and_execute(
        $stmt,
        [$id_demande, $ref_demande, $id_client, $date_demande, $date_creation, $date_reception, $producteur, $importateur, $preleve_par, $date_prelevement, $faisabilite, $validite],
        'demande_analyse(dc)'
    );
    $stmt->close();
}

function upsertEchantillonFromDc($conn, array $r){
    $id_echantillon = cleanInt($r['echantillon'] ?? null);
    if ($id_echantillon === null) return;

    $id_demande = cleanInt($r['demande_id'] ?? null);
    $type_echantillon = safeVarchar(dc_firstNonEmpty($r, ['categorie_echantillon_name', 'nature_echantillon']), 255);
    $est_conforme = normalizeConformite($r['conformite'] ?? null);
    $faisabilite = normalizeOuiNon($r['faisabilite'] ?? null);
    $validite = normalizeOuiNon($r['validite'] ?? null);
    $date_reception = combineDateHeureYMD($r['date_reception'] ?? null, $r['heure_reception'] ?? null);
    $nom_produit = safeVarchar($r['prod_fini_name'] ?? null, 255);
    $temperature_reception = safeVarchar($r['temperature'] ?? null, 50);
    $date_production = safeMysqlDate($r['date_production'] ?? null);
    $dlc = safeMysqlDateFirstToken($r['dlc'] ?? null);
    $numero_lot = safeVarchar($r['lot'] ?? null, 150);
    $produit_fini_id = cleanInt($r['prod_fini_id'] ?? null);
    $code_produit_demandeur = $produit_fini_id !== null ? (string)$produit_fini_id : null;
    $ref_labo = safeVarchar($r['ref_labo'] ?? null, 150);
    $code_echantillon = dc_safeUniqueCodeEchantillon($conn, $r['echantillon'] ?? null, $r['code_echantillon'] ?? null);
    $description_echantillon = cleanString($r['description_echantillon'] ?? null);
    $fraction = safeVarchar($r['fraction'] ?? null, 100);

    $sql = "INSERT INTO echantillons
            (
                id_echantillon,
                id_demande,
                type_echantillon,
                est_conforme,
                validite,
                faisabilite,
                date_reception,
                nom_produit,
                temperature_reception,
                date_production,
                dlc,
                numero_lot,
                code_produit_demandeur,
                ref_labo,
                code_echantillon,
                produit_fini_id,
                description_echantillon,
                fraction
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_demande = COALESCE(VALUES(id_demande), id_demande),
                type_echantillon = COALESCE(VALUES(type_echantillon), type_echantillon),
                est_conforme = CASE
                    WHEN VALUES(est_conforme) IS NOT NULL AND VALUES(est_conforme) <> ''
                    THEN VALUES(est_conforme)
                    ELSE est_conforme
                END,
                validite = CASE
                    WHEN VALUES(validite) IS NOT NULL AND VALUES(validite) <> ''
                    THEN VALUES(validite)
                    ELSE validite
                END,
                faisabilite = CASE
                    WHEN VALUES(faisabilite) IS NOT NULL AND VALUES(faisabilite) <> ''
                    THEN VALUES(faisabilite)
                    ELSE faisabilite
                END,
                date_reception = COALESCE(VALUES(date_reception), date_reception),
                nom_produit = COALESCE(VALUES(nom_produit), nom_produit),
                temperature_reception = COALESCE(VALUES(temperature_reception), temperature_reception),
                date_production = COALESCE(VALUES(date_production), date_production),
                dlc = COALESCE(VALUES(dlc), dlc),
                numero_lot = COALESCE(VALUES(numero_lot), numero_lot),
                code_produit_demandeur = COALESCE(VALUES(code_produit_demandeur), code_produit_demandeur),
                ref_labo = COALESCE(VALUES(ref_labo), ref_labo),
                code_echantillon = COALESCE(VALUES(code_echantillon), code_echantillon),
                produit_fini_id = COALESCE(VALUES(produit_fini_id), produit_fini_id),
                description_echantillon = COALESCE(VALUES(description_echantillon), description_echantillon),
                fraction = COALESCE(VALUES(fraction), fraction)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare echantillons(dc) : " . $conn->error);

    dc_bind_and_execute(
        $stmt,
        [
            $id_echantillon,
            $id_demande,
            $type_echantillon,
            $est_conforme,
            $validite,
            $faisabilite,
            $date_reception,
            $nom_produit,
            $temperature_reception,
            $date_production,
            $dlc,
            $numero_lot,
            $code_produit_demandeur,
            $ref_labo,
            $code_echantillon,
            $produit_fini_id,
            $description_echantillon,
            $fraction
        ],
        'echantillons(dc)'
    );
    $stmt->close();
}
function importerDemandeClient($conn, $file){
    list($handle, $delimiter, $headers) = openCsvAssoc($file);
    $cache = [];
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
        faisabilite = CASE
            WHEN (demande_client.faisabilite IS NULL OR demande_client.faisabilite = '') AND VALUES(faisabilite) IS NOT NULL AND VALUES(faisabilite) <> ''
            THEN VALUES(faisabilite)
            ELSE demande_client.faisabilite
        END,
        validite = CASE
            WHEN (demande_client.validite IS NULL OR demande_client.validite = '') AND VALUES(validite) IS NOT NULL AND VALUES(validite) <> ''
            THEN VALUES(validite)
            ELSE demande_client.validite
        END";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare demande_client : " . $conn->error);
    while (($r = nextAssocRow($handle, $delimiter, $headers)) !== false) {
        $demandeId = cleanInt($r['demande_id'] ?? null);
        $analyseId = cleanInt($r['analyse_id'] ?? null);
        if ($demandeId === null || $analyseId === null) continue;
        $clientId = cleanInt($r['client_id'] ?? null);
        upsertClientFromDc($conn, $r['client_id'] ?? null, $r['client_name'] ?? null);
        upsertAnalyseFromDc(
            $conn,
            $r['analyse_id'] ?? null,
            $r['analyse_nom'] ?? null,
            null,
            $r['analyse_code'] ?? null,
            null,
            null
        );
        upsertDemandeAnalyseFromDc($conn, $r);
        upsertEchantillonFromDc($conn, $r);
        $refDemande = cleanString($r['ref_demande'] ?? null);
        $analyseNom = cleanString($r['analyse_nom'] ?? null);
        $dateDemande = safeMysqlDate($r['date_demande'] ?? null);
        $dateReception = combineDateHeureYMD($r['date_reception'] ?? null, $r['heure_reception'] ?? null);
        $dateVerif = combineDateHeureYMD($r['date_verif'] ?? null, $r['heure_verif'] ?? null);
        $faisabilite = normalizeOuiNon($r['faisabilite'] ?? null);
        $validite = normalizeOuiNon($r['validite'] ?? null);
        $key = $demandeId . '_' . $analyseId;
        if (isset($cache[$key])) continue;
        $cache[$key] = true;
        dc_bind_and_execute(
            $stmt,
            [$demandeId, $refDemande, $clientId, $analyseId, $analyseNom, $dateDemande, $dateReception, $dateVerif, $faisabilite, $validite],
            'demande_client'
        );
    }
    fclose($handle);
    $stmt->close();
    return count($cache);
}
function importerDevis($conn, $file){
    list($handle, $delimiter, $headers) = openCsvAssoc($file);
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
    if (!$stmt) throw new Exception("Erreur prepare devis : " . $conn->error);
    while (($r = nextAssocRow($handle, $delimiter, $headers)) !== false) {
        $id = cleanInt($r['id'] ?? null);
        if ($id === null) continue;
        $tRef = cleanString($r['t_ref'] ?? null);
        $clientId = cleanInt($r['client_id'] ?? null) ?: cleanInt($r['t_client'] ?? null);
        $dateCrea = cleanDateTimeFlexible($r['date_crea'] ?? null);
        $dateFact = cleanDateTimeFlexible($r['date_fact'] ?? null);
        $refFactErp = cleanString($r['ref_fact_erp'] ?? null);
        $etat = cleanString($r['etat'] ?? null);
        if ($clientId !== null) {
            upsertClientFromDc($conn, $clientId, null);
        }
        dc_bind_and_execute(
            $stmt,
            [$id, $tRef, $clientId, $dateCrea, $dateFact, $refFactErp, $etat],
            'devis'
        );
        $count++;
    }
    fclose($handle);
    $stmt->close();
    return $count;
}
try {
    $conn->set_charset('utf8mb4');
    $csvDemandeClient = resolveCsvPath([
        __DIR__ . '/../../data_source/dc (3).csv',
        __DIR__ . '/dc (3).csv',
        '/mnt/data/dc (3).csv',
    ]);
    $csvDevis = resolveCsvPath([
        __DIR__ . '/../../data_source/devis (2).csv',
        __DIR__ . '/devis (2).csv',
        '/mnt/data/devis (2).csv',
    ]);
    echo "Début import.php...\n";
    echo "dc file: " . basename($csvDemandeClient) . "\n";
    echo "devis file: " . basename($csvDevis) . "\n";
    $conn->begin_transaction();
    $nb1 = importerDemandeClient($conn, $csvDemandeClient);
    $conn->commit();
    $conn->begin_transaction();
    $nb2 = importerDevis($conn, $csvDevis);
    $conn->commit();
    echo "Import terminé\n";
    echo "demande_client: $nb1\n";
    echo "devis: $nb2\n";
} catch(Exception $e){
    if (isset($conn)) {
        @$conn->rollback();
    }
    echo "Erreur import.php : " . $e->getMessage();
}
?>
