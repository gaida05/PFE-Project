<?php
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/clean_helpers.php';
require_once __DIR__ . '/csv_helpers.php';
require_once __DIR__ . '/transform_helpers.php';
set_time_limit(0);
ini_set('memory_limit', '1024M');
function if_tryResolveCsvPath(array $candidates){
    foreach ($candidates as $file) {
        if ($file && file_exists($file)) {
            return $file;
        }
    }
    return null;
}

function if_firstDateFromRow(array $row, array $keys){
    foreach ($keys as $k) {
        if (!array_key_exists($k, $row)) continue;
        $v = cleanString($row[$k]);
        if ($v === null || $v === '') continue;
        $d = safeMysqlDateRapport($v);
        if ($d !== null) return $d;
    }
    return null;
}

function if_buildDcLivraisonMap($file){
    $map = [];
    if ($file === null || !file_exists($file)) {
        return $map;
    }

    list($handle, $delimiter, $headers) = openCsvAssoc($file);
    while (($r = nextAssocRow($handle, $delimiter, $headers)) !== false) {
        $id = cleanInt($r['demande_id'] ?? null);
        if ($id === null) continue;
        $key = (string)$id;

        $validation = if_firstDateFromRow($r, [
            'date_validation',
            'date_liv_valid',
            'date_livraison_valid',
            'date_livraison_valide',
            'date_validation_rapport',
            'date_valid'
        ]);
        $interne = if_firstDateFromRow($r, [
            'date_livraison_interne',
            'date_liv_int',
            'date_livraison_int',
            'date_livraison_interne_client'
        ]);
        $livraison = if_firstDateFromRow($r, [
            'date_livraison',
            'date_liv',
            'date_livraison_client',
            'date_livraison_finale'
        ]);

        if (!isset($map[$key])) {
            $map[$key] = [
                'date_validation' => $validation,
                'date_livraison_interne' => $interne,
                'date_livraison' => $livraison
            ];
        } else {
            if ($map[$key]['date_validation'] === null && $validation !== null) {
                $map[$key]['date_validation'] = $validation;
            }
            if ($map[$key]['date_livraison_interne'] === null && $interne !== null) {
                $map[$key]['date_livraison_interne'] = $interne;
            }
            if ($map[$key]['date_livraison'] === null && $livraison !== null) {
                $map[$key]['date_livraison'] = $livraison;
            }
        }
    }
    fclose($handle);

    return $map;
}

function if_getDcLivraisonForRapport($idDemande, array $demandeMeta, array $dcLivraisonMap){
    $idDemande = cleanInt($idDemande);
    if ($idDemande !== null && isset($demandeMeta[$idDemande])) {
        $demClient = cleanInt($demandeMeta[$idDemande]['dem_client'] ?? null);
        if ($demClient !== null && isset($dcLivraisonMap[(string)$demClient])) {
            return $dcLivraisonMap[(string)$demClient];
        }
    }

    if ($idDemande !== null && isset($dcLivraisonMap[(string)$idDemande])) {
        return $dcLivraisonMap[(string)$idDemande];
    }

    return [
        'date_validation' => null,
        'date_livraison_interne' => null,
        'date_livraison' => null
    ];
}
function if_firstNonEmpty(array $row, array $keys){
    foreach ($keys as $k) {
        if (array_key_exists($k, $row)) {
            $v = cleanString($row[$k]);
            if ($v !== null && $v !== '') return $v;
        }
    }
    return null;
}
function if_firstInt(array $row, array $keys){
    foreach ($keys as $k) {
        if (array_key_exists($k, $row)) {
            $v = cleanInt($row[$k]);
            if ($v !== null) return $v;
        }
    }
    return null;
}
function if_normalizeConformiteFromAny(array $row){
    return normalizeConformite(
        if_firstNonEmpty($row, [
            'conformite',
            'ra_conformite',
            'conformite_libelle',
            'critere_resultat'
        ])
    );
}
function if_upsertClient($conn, $idClientRaw, $nomClientRaw){
    $id_client = cleanInt($idClientRaw);
    if ($id_client === null) return;
    $client_name = buildClientName($idClientRaw, $nomClientRaw);
    $sql = "INSERT INTO client (id_client, client_name)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
                client_name = CASE
                    WHEN (client.client_name IS NULL OR client.client_name = '') AND VALUES(client_name) IS NOT NULL AND VALUES(client_name) <> ''
                    THEN VALUES(client_name)
                    ELSE client.client_name
                END";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare client : " . $conn->error);
    $stmt->bind_param("is", $id_client, $client_name);
    if (!$stmt->execute()) throw new Exception("Erreur execute client : " . $stmt->error);
    $stmt->close();
}

function if_upsertSecteur($conn, $idSecteurRaw, $nomSecteurRaw){
    $id_secteur = cleanInt($idSecteurRaw);
    if ($id_secteur === null) return;
    $nom_secteur = buildSecteurName($idSecteurRaw, $nomSecteurRaw);
    $sql = "INSERT INTO secteur (id_secteur, nom_secteur)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
                nom_secteur = CASE
                    WHEN (secteur.nom_secteur IS NULL OR secteur.nom_secteur = '') AND VALUES(nom_secteur) IS NOT NULL AND VALUES(nom_secteur) <> ''
                    THEN VALUES(nom_secteur)
                    ELSE secteur.nom_secteur
                END";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare secteur : " . $conn->error);
    $stmt->bind_param("is", $id_secteur, $nom_secteur);
    if (!$stmt->execute()) throw new Exception("Erreur execute secteur : " . $stmt->error);
    $stmt->close();
}
function if_upsertLaborantin($conn, $idLaborantinRaw, $nomLaborantinRaw, $idSecteurRaw = null){
    $id_laborantin = cleanInt($idLaborantinRaw);
    if ($id_laborantin === null) return;
    $nom_laborantin = buildLaborantinName($idLaborantinRaw, $nomLaborantinRaw);
    $id_secteur = cleanInt($idSecteurRaw);
    $sql = "INSERT INTO laborantin (id_laborantin, nom_laborantin, id_secteur)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nom_laborantin = CASE
                    WHEN (laborantin.nom_laborantin IS NULL OR laborantin.nom_laborantin = '') AND VALUES(nom_laborantin) IS NOT NULL AND VALUES(nom_laborantin) <> ''
                    THEN VALUES(nom_laborantin)
                    ELSE laborantin.nom_laborantin
                END,
                id_secteur = COALESCE(laborantin.id_secteur, VALUES(id_secteur))";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare laborantin : " . $conn->error);
    $stmt->bind_param("isi", $id_laborantin, $nom_laborantin, $id_secteur);
    if (!$stmt->execute()) throw new Exception("Erreur execute laborantin : " . $stmt->error);
    $stmt->close();
}

function if_upsertAnalyse($conn, $idAnalyseRaw, $nomAnalyseRaw, $idSecteurRaw = null, $codeAnalyseRaw = null, $methodeRaw = null, $uniteRaw = null){
    $id_analyse = cleanInt($idAnalyseRaw);
    if ($id_analyse === null) return;
    $nom_analyse = cleanString($nomAnalyseRaw) ?: ('analyse_' . $id_analyse);
    $code_analyse = safeVarchar($codeAnalyseRaw, 100);
    $methode_defaut = safeVarchar($methodeRaw, 255);
    $unite_defaut = safeVarchar($uniteRaw, 100);
    $id_secteur = cleanInt($idSecteurRaw);
    $sql = "INSERT INTO analyses (id_analyse, nom_analyse, code_analyse, methode_defaut, unite_defaut, id_secteur)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nom_analyse = CASE
                    WHEN (analyses.nom_analyse IS NULL OR analyses.nom_analyse = '') AND VALUES(nom_analyse) IS NOT NULL AND VALUES(nom_analyse) <> ''
                    THEN VALUES(nom_analyse)
                    ELSE analyses.nom_analyse
                END,
                code_analyse = CASE
                    WHEN (analyses.code_analyse IS NULL OR analyses.code_analyse = '') AND VALUES(code_analyse) IS NOT NULL AND VALUES(code_analyse) <> ''
                    THEN VALUES(code_analyse)
                    ELSE analyses.code_analyse
                END,
                methode_defaut = CASE
                    WHEN (analyses.methode_defaut IS NULL OR analyses.methode_defaut = '') AND VALUES(methode_defaut) IS NOT NULL AND VALUES(methode_defaut) <> ''
                    THEN VALUES(methode_defaut)
                    ELSE analyses.methode_defaut
                END,
                unite_defaut = CASE
                    WHEN (analyses.unite_defaut IS NULL OR analyses.unite_defaut = '') AND VALUES(unite_defaut) IS NOT NULL AND VALUES(unite_defaut) <> ''
                    THEN VALUES(unite_defaut)
                    ELSE analyses.unite_defaut
                END,
                id_secteur = COALESCE(analyses.id_secteur, VALUES(id_secteur))";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare analyses : " . $conn->error);
    $stmt->bind_param("issssi", $id_analyse, $nom_analyse, $code_analyse, $methode_defaut, $unite_defaut, $id_secteur);
    if (!$stmt->execute()) throw new Exception("Erreur execute analyses : " . $stmt->error);
    $stmt->close();
}
function if_upsertCritere($conn, $critereIdRaw, $critereTitreRaw, $critereRuleRaw = null, $descriptionRaw = null){
    $id = cleanInt($critereIdRaw);
    if ($id === null) return;
    $code = safeVarchar($critereRuleRaw, 50);
    if ($code === null) $code = 'CRIT-' . $id;
    $label = safeVarchar($critereTitreRaw, 100);
    if ($label === null) $label = safeVarchar($descriptionRaw, 100);
    if ($label === null) $label = 'Critere ' . $id;
    $description = cleanString($descriptionRaw);
    $sql = "INSERT INTO criteres (id, code, label, description)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                code = CASE
                    WHEN (criteres.code IS NULL OR criteres.code = '') AND VALUES(code) IS NOT NULL AND VALUES(code) <> ''
                    THEN VALUES(code)
                    ELSE criteres.code
                END,
                label = CASE
                    WHEN (criteres.label IS NULL OR criteres.label = '') AND VALUES(label) IS NOT NULL AND VALUES(label) <> ''
                    THEN VALUES(label)
                    ELSE criteres.label
                END,
                description = CASE
                    WHEN (criteres.description IS NULL OR criteres.description = '') AND VALUES(description) IS NOT NULL AND VALUES(description) <> ''
                    THEN VALUES(description)
                    ELSE criteres.description
                END";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare criteres : " . $conn->error);
    $stmt->bind_param("isss", $id, $code, $label, $description);
    if (!$stmt->execute()) throw new Exception("Erreur execute criteres : " . $stmt->error);
    $stmt->close();
}
function if_ensureDemandeAnalyseExists($conn, $id_demande, $r = []){
    $id_demande = cleanInt($id_demande);
    if ($id_demande === null) return;
    $ref_demande = safeVarchar($r['ref_demande'] ?? null, 250);
    $id_secteur = cleanInt($r['secteur_id'] ?? null);
    $id_client = cleanInt($r['id_client'] ?? null);
    $date_demande = safeMysqlDate($r['date_demande'] ?? null);
    $date_creation = safeMysqlDateTime($r['date_creation_da'] ?? $r['date_creation_rapport'] ?? null) ?: ($date_demande ? $date_demande . ' 00:00:00' : null);
    $date_validation = safeMysqlDate($r['date_validation'] ?? null);
    $date_cloture = null;
    $statut = safeVarchar($r['statut'] ?? null, 100) ?: ($date_validation ? 'Validé' : 'En cours');
    $preleve_par = $id_client !== null ? (string)$id_client : null;
    $sql = "INSERT INTO demande_analyse
            (id_demande, ref_demande, id_secteur, date_demande, date_creation, date_cloture, date_validation, statut, preleve_par, id_client)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                ref_demande = COALESCE(demande_analyse.ref_demande, VALUES(ref_demande)),
                id_secteur = COALESCE(demande_analyse.id_secteur, VALUES(id_secteur)),
                date_demande = COALESCE(demande_analyse.date_demande, VALUES(date_demande)),
                date_creation = COALESCE(demande_analyse.date_creation, VALUES(date_creation)),
                date_cloture = demande_analyse.date_cloture,
                date_validation = COALESCE(demande_analyse.date_validation, VALUES(date_validation)),
                statut = COALESCE(demande_analyse.statut, VALUES(statut)),
                preleve_par = COALESCE(demande_analyse.preleve_par, VALUES(preleve_par)),
                id_client = COALESCE(demande_analyse.id_client, VALUES(id_client))";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare ensure demande_analyse : " . $conn->error);
    $stmt->bind_param("isissssssi", $id_demande, $ref_demande, $id_secteur, $date_demande, $date_creation, $date_cloture, $date_validation, $statut, $preleve_par, $id_client);
    if (!$stmt->execute()) throw new Exception("Erreur execute ensure demande_analyse : " . $stmt->error);
    $stmt->close();
}
function if_upsertRapportFinalFromDemande($conn, $r){
    $id_demande = cleanInt($r['demande_id'] ?? null);
    if ($id_demande === null) return false;
    $id_rapport = safeVarchar($r['rapport_finaux_id'] ?? null, 100);
    if ($id_rapport === null) return false;
    $rapport = safeVarchar($r['rapport_finaux_contenu'] ?? null, 255) ?: $id_rapport;
    $type_rapport = 'Finale';
    $date_creation = null;
    $date_edition = null;
    $secteur_id = cleanInt($r['secteur_id'] ?? null);
    $statut = 'Validé';
    $sql = "INSERT INTO rapports
            (id_rapport, rapport, id_demande, type_rapport, date_creation, date_edition, secteur_id, statut)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                rapport = COALESCE(VALUES(rapport), rapport),
                id_demande = COALESCE(VALUES(id_demande), id_demande),
                type_rapport = COALESCE(VALUES(type_rapport), type_rapport),
                date_creation = COALESCE(VALUES(date_creation), date_creation),
                date_edition = COALESCE(VALUES(date_edition), date_edition),
                secteur_id = COALESCE(VALUES(secteur_id), secteur_id),
                statut = COALESCE(VALUES(statut), statut)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare rapport final : " . $conn->error);
    $stmt->bind_param(
        "ssisssis",
        $id_rapport,
        $rapport,
        $id_demande,
        $type_rapport,
        $date_creation,
        $date_edition,
        $secteur_id,
        $statut
    );
    if (!$stmt->execute()) throw new Exception("Erreur execute rapport final : " . $stmt->error);
    $stmt->close();
    return true;
}

function if_upsertDemandeAnalyse($conn, $r){
    $id_demande = cleanInt($r['demande_id'] ?? null);
    if ($id_demande === null) return false;
    if_upsertClient($conn, $r['id_client'] ?? null, if_firstNonEmpty($r, ['client_name', 'dem_client']));
    if_upsertSecteur($conn, $r['secteur_id'] ?? null, $r['secteur_name'] ?? null);
    if_upsertAnalyse(
        $conn,
        $r['analyse_id'] ?? null,
        $r['nom_analyse'] ?? null,
        $r['secteur_id'] ?? null,
        $r['reference_analyse'] ?? null,
        $r['methodeanalyse'] ?? null,
        $r['unite_result'] ?? null
    );
    if_upsertLaborantin($conn, $r['first_laborantin_id'] ?? null, $r['first_laborantin_nom'] ?? null, $r['secteur_id'] ?? null);
    if_upsertLaborantin($conn, $r['second_laborantin_id'] ?? null, $r['second_laborantin_nom'] ?? null, $r['secteur_id'] ?? null);
    if_upsertCritere($conn, $r['critere_id'] ?? null, $r['critere_titre'] ?? null, $r['critere_rule'] ?? null, $r['critere_resultat'] ?? null);
    $ref_demande = safeVarchar($r['ref_demande'] ?? null, 250);
    $id_secteur = cleanInt($r['secteur_id'] ?? null);
    $id_client = cleanInt($r['id_client'] ?? null);
    $date_demande = safeMysqlDate($r['date_demande'] ?? null);
    $date_creation = safeMysqlDateTime($r['date_creation_da'] ?? null) ?: ($date_demande ? $date_demande . ' 00:00:00' : null);
    $date_reception = combineDateHeure($r['date_reception'] ?? null, $r['heure_reception'] ?? null);
    $date_verif = combineDateHeure($r['date_verif'] ?? null, $r['heure_verif'] ?? null);
    $date_cloture = NULL;
    $statut = safeVarchar($r['statut'] ?? null, 100);
    $date_prelevement = safeMysqlDate($r['date_prelevement'] ?? null);
    $preleve_par = $id_client !== null ? (string)$id_client : null;
    $sql = "INSERT INTO demande_analyse
            (id_demande, ref_demande, id_secteur, date_demande, date_creation, date_cloture, statut, preleve_par, date_prelevement, date_reception, date_verif, id_client)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                ref_demande = COALESCE(VALUES(ref_demande), ref_demande),
                id_secteur = COALESCE(VALUES(id_secteur), id_secteur),
                date_demande = COALESCE(VALUES(date_demande), date_demande),
                date_creation = COALESCE(VALUES(date_creation), date_creation),
                date_cloture = date_cloture,
                statut = COALESCE(VALUES(statut), statut),
                preleve_par = COALESCE(VALUES(preleve_par), preleve_par),
                date_prelevement = COALESCE(VALUES(date_prelevement), date_prelevement),
                date_reception = COALESCE(VALUES(date_reception), date_reception),
                date_verif = COALESCE(VALUES(date_verif), date_verif),
                id_client = COALESCE(VALUES(id_client), id_client)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare demande_analyse : " . $conn->error);
    $stmt->bind_param("isissssssssi", $id_demande, $ref_demande, $id_secteur, $date_demande, $date_creation, $date_cloture, $statut, $preleve_par, $date_prelevement, $date_reception, $date_verif, $id_client);
    if (!$stmt->execute()) throw new Exception("Erreur execute demande_analyse : " . $stmt->error);
    $stmt->close();
    return true;
}
function if_upsertEchantillonFromDemandes($conn, $r){
    $id_echantillon = cleanInt($r['echantillon_id'] ?? null);
    if ($id_echantillon === null) return false;
    $id_demande = cleanInt($r['demande_id'] ?? null);
    $type_echantillon = safeVarchar($r['echantillon_type'] ?? null, 255);
    $est_conforme = normalizeConformite($r['conformite'] ?? null);
    $validite = normalizeOuiNon($r['validite'] ?? null);
    $faisabilite = normalizeOuiNon($r['faisabilite'] ?? null);
    $date_reception = combineDateHeure($r['date_reception'] ?? null, $r['heure_reception'] ?? null);
    $ref_labo = safeVarchar($r['reflabo'] ?? null, 150);
    $numero_lot = safeVarchar($r['reflot_deux'] ?? null, 150);
    $sql = "INSERT INTO echantillons
            (id_echantillon, id_demande, type_echantillon, est_conforme, validite, faisabilite, date_reception, numero_lot, ref_labo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                numero_lot = COALESCE(VALUES(numero_lot), numero_lot),
                ref_labo = COALESCE(VALUES(ref_labo), ref_labo)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare echantillons : " . $conn->error);
    $stmt->bind_param("iisssssss", $id_echantillon, $id_demande, $type_echantillon, $est_conforme, $validite, $faisabilite, $date_reception, $numero_lot, $ref_labo);
    if (!$stmt->execute()) throw new Exception("Erreur execute echantillons : " . $stmt->error);
    $stmt->close();
    return true;
}

function if_importerDemandes($conn, $file){
    list($handle, $delimiter, $headers) = openCsvAssoc($file);
    $countDemandes = 0;
    $countEchantillons = 0;
    $demandeMeta = [];
    while (($r = nextAssocRow($handle, $delimiter, $headers)) !== false) {
        if (if_upsertDemandeAnalyse($conn, $r)) $countDemandes++;
        if (if_upsertEchantillonFromDemandes($conn, $r)) $countEchantillons++;
        if_upsertRapportFinalFromDemande($conn, $r);
        $did = cleanInt($r['demande_id'] ?? null);
        if ($did !== null) {
            if (!isset($demandeMeta[$did])) {
                $demandeMeta[$did] = [
                    'secteur_id' => cleanInt($r['secteur_id'] ?? null),
                    'secteur_name' => cleanString($r['secteur_name'] ?? null),
                    'dem_client' => cleanString($r['dem_client'] ?? null),
                    'responsable_essai' => cleanInt($r['first_laborantin_id'] ?? null),
                    'co_responsable_essai' => cleanInt($r['second_laborantin_id'] ?? null),
                    'date_debut_analyse' => safeMysqlDateTime($r['date_debut_analyse'] ?? null),
                    'date_fin_analyse' => safeMysqlDateTime($r['date_fin_analyse'] ?? null),
                ];
            } else {
                if ($demandeMeta[$did]['secteur_id'] === null) {
                    $demandeMeta[$did]['secteur_id'] = cleanInt($r['secteur_id'] ?? null);
                }
                if ($demandeMeta[$did]['secteur_name'] === null) {
                    $demandeMeta[$did]['secteur_name'] = cleanString($r['secteur_name'] ?? null);
                }
                if (($demandeMeta[$did]['dem_client'] ?? null) === null) {
                    $demandeMeta[$did]['dem_client'] = cleanString($r['dem_client'] ?? null);
                }
                if ($demandeMeta[$did]['responsable_essai'] === null) {
                    $demandeMeta[$did]['responsable_essai'] = cleanInt($r['first_laborantin_id'] ?? null);
                }
                if ($demandeMeta[$did]['co_responsable_essai'] === null) {
                    $demandeMeta[$did]['co_responsable_essai'] = cleanInt($r['second_laborantin_id'] ?? null);
                }
                if ($demandeMeta[$did]['date_debut_analyse'] === null) {
                    $demandeMeta[$did]['date_debut_analyse'] = safeMysqlDateTime($r['date_debut_analyse'] ?? null);
                }
                if ($demandeMeta[$did]['date_fin_analyse'] === null) {
                    $demandeMeta[$did]['date_fin_analyse'] = safeMysqlDateTime($r['date_fin_analyse'] ?? null);
                }
            }
        }
    }
    fclose($handle);
    return [$countDemandes, $countEchantillons, $demandeMeta];
}
function if_upsertRapportEchantillon($conn, $r){
    $id_echantillon = cleanInt($r['ra_echantillon'] ?? null);
    if ($id_echantillon === null) return;
    $id_demande = cleanInt($r['demande_id'] ?? null);
    $nom_produit = safeVarchar($r['ra_produitfini'] ?? null, 255);
    $numero_lot = safeVarchar(if_firstNonEmpty($r, ['ref_lot', 'reflot_deux']), 150);
    $ref_labo = safeVarchar(if_firstNonEmpty($r, ['ref_labo', 'reflabo']), 150);
    $est_conforme = if_normalizeConformiteFromAny($r);
    $sql = "INSERT INTO echantillons
            (id_echantillon, id_demande, nom_produit, numero_lot, ref_labo, est_conforme)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_demande = COALESCE(echantillons.id_demande, VALUES(id_demande)),
                nom_produit = CASE
                    WHEN (echantillons.nom_produit IS NULL OR echantillons.nom_produit = '') AND VALUES(nom_produit) IS NOT NULL AND VALUES(nom_produit) <> ''
                    THEN VALUES(nom_produit)
                    ELSE echantillons.nom_produit
                END,
                numero_lot = CASE
                    WHEN (echantillons.numero_lot IS NULL OR echantillons.numero_lot = '') AND VALUES(numero_lot) IS NOT NULL AND VALUES(numero_lot) <> ''
                    THEN VALUES(numero_lot)
                    ELSE echantillons.numero_lot
                END,
                ref_labo = CASE
                    WHEN (echantillons.ref_labo IS NULL OR echantillons.ref_labo = '') AND VALUES(ref_labo) IS NOT NULL AND VALUES(ref_labo) <> ''
                    THEN VALUES(ref_labo)
                    ELSE echantillons.ref_labo
                END,
                est_conforme = CASE
                    WHEN VALUES(est_conforme) IS NOT NULL AND VALUES(est_conforme) <> ''
                    THEN VALUES(est_conforme)
                    ELSE echantillons.est_conforme
                END";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare enrichissement echantillon rapport : " . $conn->error);
    $stmt->bind_param("iissss", $id_echantillon, $id_demande, $nom_produit, $numero_lot, $ref_labo, $est_conforme);
    if (!$stmt->execute()) throw new Exception("Erreur execute enrichissement echantillon rapport : " . $stmt->error);
    $stmt->close();
}
function if_upsertRapport($conn, $r, $demandeMeta, $dcLivraisonMap = []){
    $id_rapport = cleanString($r['rapport_id'] ?? null);
    if ($id_rapport === null) return false;
    $id_demande = cleanInt($r['demande_id'] ?? null);
    $secteur_id_csv = cleanInt($r['secteur_id'] ?? null);
    $secteur_name_csv = cleanString($r['secteur_name'] ?? null);
    $secteur_id = $secteur_id_csv;
    $secteur_name = $secteur_name_csv;
    if ($id_demande !== null && isset($demandeMeta[$id_demande])) {
        if ($secteur_id === null) $secteur_id = $demandeMeta[$id_demande]['secteur_id'];
        if ($secteur_name === null) $secteur_name = $demandeMeta[$id_demande]['secteur_name'];
    }
    if_upsertSecteur($conn, $secteur_id, $secteur_name);
    if_upsertAnalyse(
        $conn,
        $r['analyse_id'] ?? null,
        $r['nom_analyse'] ?? null,
        $secteur_id,
        $r['reference_analyse'] ?? null,
        $r['methodeanalyse'] ?? null,
        $r['ra_unite_result'] ?? null
    );
    if_upsertLaborantin($conn, $r['validateur_id'] ?? null, $r['validateur_nom'] ?? null, $secteur_id);
    if_upsertCritere($conn, $r['critere_id'] ?? null, $r['critere_titre'] ?? null, $r['critere_rule'] ?? null, $r['des_critere'] ?? $r['critere_resultat'] ?? null);
    if_ensureDemandeAnalyseExists($conn, $id_demande, array_merge($r, ['secteur_id' => $secteur_id]));
    if_upsertRapportEchantillon($conn, $r);
    $rapport = safeVarchar($r['ref_rapport'] ?? null, 255) ?: $id_rapport;
    $dem_client = $id_demande !== null && isset($demandeMeta[$id_demande]) ? cleanString($demandeMeta[$id_demande]['dem_client'] ?? null) : null;
    $type_rapport = safeVarchar($r['type_rapport'] ?? null, 50);
    $date_creation = safeMysqlDateTimeRapport($r['date_creation_rapport'] ?? null);
    $date_edition = $date_creation;
    $date_validation = safeMysqlDateRapport($r['date_validation'] ?? null);
    $date_livraison_interne = $date_validation;
    $date_livraison = $date_validation;

    if ($date_validation === null) {
        $dcDates = if_getDcLivraisonForRapport($id_demande, $demandeMeta, $dcLivraisonMap);
        $date_validation = $dcDates['date_validation'] ?: $dcDates['date_livraison_interne'] ?: $dcDates['date_livraison'];
        $date_livraison_interne = $dcDates['date_livraison_interne'] ?: $date_validation;
        $date_livraison = $dcDates['date_livraison'] ?: $date_livraison_interne ?: $date_validation;
    }
    $laborantin_validateur = cleanInt($r['validateur_id'] ?? null);
    $manipulateur = safeVarchar($r['manipulateur_nom'] ?? $dem_client ?? null, 255);
    $statut = $date_validation ? 'Validé' : 'En cours';
    $id_csv = cleanInt($r['rapport_id'] ?? null);
    $responsable_essai = $id_demande !== null && isset($demandeMeta[$id_demande]) ? $demandeMeta[$id_demande]['responsable_essai'] : null;
    $co_responsable_essai = $id_demande !== null && isset($demandeMeta[$id_demande]) ? $demandeMeta[$id_demande]['co_responsable_essai'] : null;
    $responsable_technique = cleanInt($r['validateur_id'] ?? null);
    $observation = cleanString($r['ra_fr_commentaire'] ?? $r['commentaire'] ?? null);
    $sql = "INSERT INTO rapports
            (id_rapport, rapport, id_demande, type_rapport, date_creation, date_edition, date_validation, date_livraison_interne, date_livraison, laborantin_validateur, manipulateur, statut, secteur_id, id_csv, responsable_essai, responsable_technique, observation, co_responsable_essai)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                rapport = COALESCE(VALUES(rapport), rapport),
                id_demande = COALESCE(VALUES(id_demande), id_demande),
                type_rapport = COALESCE(VALUES(type_rapport), type_rapport),
                date_creation = COALESCE(VALUES(date_creation), date_creation),
                date_edition = COALESCE(VALUES(date_edition), date_edition),
                date_validation = COALESCE(VALUES(date_validation), date_validation),
                date_livraison_interne = COALESCE(VALUES(date_livraison_interne), date_livraison_interne),
                date_livraison = COALESCE(VALUES(date_livraison), date_livraison),
                laborantin_validateur = COALESCE(VALUES(laborantin_validateur), laborantin_validateur),
                manipulateur = COALESCE(VALUES(manipulateur), manipulateur),
                statut = COALESCE(VALUES(statut), statut),
                secteur_id = COALESCE(VALUES(secteur_id), secteur_id),
                id_csv = COALESCE(VALUES(id_csv), id_csv),
                responsable_essai = COALESCE(VALUES(responsable_essai), responsable_essai),
                responsable_technique = COALESCE(VALUES(responsable_technique), responsable_technique),
                observation = COALESCE(VALUES(observation), observation),
                co_responsable_essai = COALESCE(VALUES(co_responsable_essai), co_responsable_essai)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare rapports : " . $conn->error);
    $stmt->bind_param(
        "ssissssssisiiiissi",
        $id_rapport,
        $rapport,
        $id_demande,
        $type_rapport,
        $date_creation,
        $date_edition,
        $date_validation,
        $date_livraison_interne,
        $date_livraison,
        $laborantin_validateur,
        $manipulateur,
        $statut,
        $secteur_id,
        $id_csv,
        $responsable_essai,
        $responsable_technique,
        $observation,
        $co_responsable_essai
    );
    if (!$stmt->execute()) throw new Exception("Erreur execute rapports : " . $stmt->error);
    $stmt->close();
    return true;
}
function if_updateDemandeFromRapport($conn, $r, $demandeMeta = [], $dcLivraisonMap = []){
    $id_demande = cleanInt($r['demande_id'] ?? null);
    if ($id_demande === null) return;

    /*
     * Important:
     * rapport (2).csv contains analysis dates that are not reliable for the
     * demande lifecycle. For example, some 2026 reports contain
     * date_fin_analyse values in 2024. Therefore this importer must NOT update
     * demande_analyse.date_debut_analyse, date_fin_analyse or date_cloture.
     * Those lifecycle dates are consolidated from ordres_analyses in import_data.php.
     */
    $date_validation = safeMysqlDateRapport($r['date_validation'] ?? null);
    if ($date_validation === null) {
        $dcDates = if_getDcLivraisonForRapport($id_demande, $demandeMeta, $dcLivraisonMap);
        $date_validation = $dcDates['date_validation'] ?: $dcDates['date_livraison_interne'] ?: $dcDates['date_livraison'];
    }

    $sql = "UPDATE demande_analyse
            SET date_validation = COALESCE(?, date_validation)
            WHERE id_demande = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare update demande_analyse(rapport) : " . $conn->error);
    $stmt->bind_param("si", $date_validation, $id_demande);
    if (!$stmt->execute()) throw new Exception("Erreur execute update demande_analyse(rapport) : " . $stmt->error);
    $stmt->close();
}

function if_importerRapports($conn, $file, $demandeMeta, $dcLivraisonMap = []){
    list($handle, $delimiter, $headers) = openCsvAssoc($file);
    $count = 0;
    $seen = [];
    while (($r = nextAssocRow($handle, $delimiter, $headers)) !== false) {
        $id_rapport = cleanString($r['rapport_id'] ?? null);
        if ($id_rapport !== null && !isset($seen[$id_rapport])) {
            if (if_upsertRapport($conn, $r, $demandeMeta, $dcLivraisonMap)) {
                $seen[$id_rapport] = true;
                $count++;
            }
        }
        if_updateDemandeFromRapport($conn, $r, $demandeMeta, $dcLivraisonMap);
    }
    fclose($handle);
    return $count;
}
try {
    $conn->set_charset('utf8mb4');
    $csvDemandes = resolveCsvPath([
        __DIR__ . '/../../data_source/demande_anan(20).csv',
        __DIR__ . '/demande_anan(20).csv',
        '/mnt/data/demande_anan(20).csv',
    ]);
    $csvRapports = resolveCsvPath([
        __DIR__ . '/../../data_source/rapport (2).csv',
        __DIR__ . '/rapport (2).csv',
        '/mnt/data/rapport (2).csv',
    ]);
    $csvDc = if_tryResolveCsvPath([
        __DIR__ . '/../../data_source/dc (3).csv',
        __DIR__ . '/dc (3).csv',
        '/mnt/data/dc (3).csv',
    ]);
    $dcLivraisonMap = if_buildDcLivraisonMap($csvDc);
    echo "Début import_file.php...\n";
    echo "demandes file: " . basename($csvDemandes) . "\n";
    echo "rapports file: " . basename($csvRapports) . "\n";
    if ($csvDc !== null) {
        echo "dc fallback file: " . basename($csvDc) . "\n";
    }
    $conn->begin_transaction();
    list($nbDemandes, $nbEchantillons, $demandeMeta) = if_importerDemandes($conn, $csvDemandes);
    $nbRapports = if_importerRapports($conn, $csvRapports, $demandeMeta, $dcLivraisonMap);
    $conn->commit();
    echo "demande_analyse: $nbDemandes\n";
    echo "echantillons: $nbEchantillons\n";
    echo "rapports: $nbRapports\n";
} catch (Exception $e) {
    if (isset($conn)) {
        @$conn->rollback();
    }
    echo "Erreur import_file.php : " . $e->getMessage();
}
?>