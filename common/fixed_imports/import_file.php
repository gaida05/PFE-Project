<?php
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/clean_helpers.php';
require_once __DIR__ . '/csv_helpers.php';
require_once __DIR__ . '/transform_helpers.php';

set_time_limit(0);
ini_set('memory_limit', '1024M');

function openCsvAssoc($file){
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

function nextAssocRow($handle, $delimiter, $headers){
    $row = fgetcsv($handle, 0, $delimiter);
    if ($row === false) return false;
    $row = array_pad($row, count($headers), null);
    return array_combine($headers, array_slice($row, 0, count($headers)));
}

function resolveCsvPath(array $candidates){
    foreach ($candidates as $file) {
        if ($file && file_exists($file)) return $file;
    }
    throw new Exception('Fichier CSV introuvable : ' . implode(' | ', $candidates));
}

function safeVarchar($value, $maxLen){
    $value = cleanString($value);
    if ($value === null) return null;
    return mb_substr($value, 0, $maxLen, 'UTF-8');
}

function safeDateTimeYMD($value){
    return cleanDateTimeYMDStrict($value);
}

function safeDateYMD($value){
    $dt = cleanDateTimeYMDStrict($value);
    return $dt ? date('Y-m-d', strtotime($dt)) : null;
}

function normalizeConformite($value){
    $v = cleanString($value);
    if ($v === null) return null;
    $x = mb_strtolower($v, 'UTF-8');
    if (in_array($x, ['oui', 'conforme'])) return 'conforme';
    if (in_array($x, ['non', 'non conforme'])) return 'non conforme';
    return $v;
}

function upsertClient($conn, $idClientRaw, $nomClientRaw){
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

function upsertSecteur($conn, $idSecteurRaw, $nomSecteurRaw){
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

function upsertLaborantin($conn, $idLaborantinRaw, $nomLaborantinRaw, $idSecteurRaw = null){
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

function upsertAnalyse($conn, $idAnalyseRaw, $nomAnalyseRaw, $idSecteurRaw = null, $codeAnalyseRaw = null, $methodeRaw = null, $uniteRaw = null){
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
                nom_analyse = COALESCE(VALUES(nom_analyse), nom_analyse),
                code_analyse = COALESCE(VALUES(code_analyse), code_analyse),
                methode_defaut = COALESCE(VALUES(methode_defaut), methode_defaut),
                unite_defaut = COALESCE(VALUES(unite_defaut), unite_defaut),
                id_secteur = COALESCE(VALUES(id_secteur), id_secteur)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare analyses : " . $conn->error);
    $stmt->bind_param("issssi", $id_analyse, $nom_analyse, $code_analyse, $methode_defaut, $unite_defaut, $id_secteur);
    if (!$stmt->execute()) throw new Exception("Erreur execute analyses : " . $stmt->error);
    $stmt->close();
}

function upsertCritere($conn, $critereIdRaw, $critereTitreRaw, $critereRuleRaw = null, $descriptionRaw = null){
    $id = cleanInt($critereIdRaw);
    if ($id === null) return;

    $code = safeVarchar($critereRuleRaw, 50);
    if ($code === null) $code = 'CRIT-' . $id;

    $label = safeVarchar($critereTitreRaw, 100);
    if ($label === null) {
        $label = safeVarchar($descriptionRaw, 100);
    }
    if ($label === null) $label = 'Critere ' . $id;

    $description = cleanString($descriptionRaw);

    $sql = "INSERT INTO criteres (id, code, label, description)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                code = COALESCE(VALUES(code), code),
                label = COALESCE(VALUES(label), label),
                description = COALESCE(VALUES(description), description)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare criteres : " . $conn->error);
    $stmt->bind_param("isss", $id, $code, $label, $description);
    if (!$stmt->execute()) throw new Exception("Erreur execute criteres : " . $stmt->error);
    $stmt->close();
}

function upsertDemandeAnalyse($conn, $r){
    $id_demande = cleanInt($r['demande_id'] ?? null);
    if ($id_demande === null) return false;

    upsertClient($conn, $r['id_client'] ?? null, $r['client_name'] ?? null);
    upsertSecteur($conn, $r['secteur_id'] ?? null, $r['secteur_name'] ?? null);
    upsertAnalyse(
        $conn,
        $r['analyse_id'] ?? null,
        $r['nom_analyse'] ?? null,
        $r['secteur_id'] ?? null,
        $r['reference_analyse'] ?? null,
        $r['methodeanalyse'] ?? null,
        $r['unite_result'] ?? null
    );
    upsertLaborantin($conn, $r['first_laborantin_id'] ?? null, $r['first_laborantin_nom'] ?? null, $r['secteur_id'] ?? null);
    upsertLaborantin($conn, $r['second_laborantin_id'] ?? null, $r['second_laborantin_nom'] ?? null, $r['secteur_id'] ?? null);
    upsertCritere($conn, $r['critere_id'] ?? null, $r['critere_titre'] ?? null, $r['critere_rule'] ?? null, $r['critere_resultat'] ?? null);

    $ref_demande = safeVarchar($r['ref_demande'] ?? null, 250);
    $id_secteur = cleanInt($r['secteur_id'] ?? null);
    $id_client = cleanInt($r['id_client'] ?? null);
    $date_demande = safeDateTimeYMD($r['date_demande'] ?? null);
    $date_creation = safeDateTimeYMD($r['date_creation_da'] ?? null) ?: $date_demande;
    $date_reception = combineDateHeureYMD($r['date_reception'] ?? null, $r['heure_reception'] ?? null);
    $date_verif = combineDateHeureYMD($r['date_verif'] ?? null, $r['heure_verif'] ?? null);
    $date_cloture = $date_verif;
    $statut = safeVarchar($r['statut'] ?? null, 100);
    $date_prelevement = safeDateYMD($r['date_prelevement'] ?? null);
    $preleve_par = safeVarchar($r['nom_preleveur'] ?? null, 255);

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
    if (!$stmt) throw new Exception("Erreur prepare demande_analyse : " . $conn->error);
    $stmt->bind_param("isissssssssi", $id_demande, $ref_demande, $id_secteur, $date_demande, $date_creation, $date_cloture, $statut, $preleve_par, $date_prelevement, $date_reception, $date_verif, $id_client);
    if (!$stmt->execute()) throw new Exception("Erreur execute demande_analyse : " . $stmt->error);
    $stmt->close();
    return true;
}

function upsertEchantillonFromDemandes($conn, $r){
    $id_echantillon = cleanInt($r['echantillon_id'] ?? null);
    if ($id_echantillon === null) return false;

    $id_demande = cleanInt($r['demande_id'] ?? null);
    $type_echantillon = safeVarchar($r['echantillon_type'] ?? null, 255);
    $est_conforme = safeVarchar(normalizeConformite($r['conformite'] ?? null), 50);
    $date_reception = combineDateHeureYMD($r['date_reception'] ?? null, $r['heure_reception'] ?? null);
    $ref_labo = safeVarchar($r['reflabo'] ?? null, 100);
    $numero_lot = safeVarchar($r['reflot_deux'] ?? null, 100);

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
    if (!$stmt) throw new Exception("Erreur prepare echantillons : " . $conn->error);
    $stmt->bind_param("iisssss", $id_echantillon, $id_demande, $type_echantillon, $est_conforme, $date_reception, $numero_lot, $ref_labo);
    if (!$stmt->execute()) throw new Exception("Erreur execute echantillons : " . $stmt->error);
    $stmt->close();
    return true;
}

function importerDemandes($conn, $file){
    list($handle, $delimiter, $headers) = openCsvAssoc($file);
    $countDemandes = 0;
    $countEchantillons = 0;
    while (($r = nextAssocRow($handle, $delimiter, $headers)) !== false) {
        if (upsertDemandeAnalyse($conn, $r)) $countDemandes++;
        if (upsertEchantillonFromDemandes($conn, $r)) $countEchantillons++;
    }
    fclose($handle);
    return [$countDemandes, $countEchantillons];
}


function ensureDemandeAnalyseExists($conn, $id_demande, $r = []){
    $id_demande = cleanInt($id_demande);
    if ($id_demande === null) return;

    $ref_demande = safeVarchar($r['ref_demande'] ?? $r['reference_demande'] ?? null, 250);
    $id_secteur = cleanInt($r['secteur_id'] ?? null);
    $id_client = cleanInt($r['id_client'] ?? null);
    $date_demande = safeDateTimeYMD($r['date_demande'] ?? null);
    $date_creation = safeDateTimeYMD($r['date_creation_da'] ?? $r['date_creation_rapport'] ?? null) ?: $date_demande;
    $date_cloture = safeDateTimeYMD($r['date_validation'] ?? null);
    $date_validation = safeDateTimeYMD($r['date_validation'] ?? null);
    $statut = safeVarchar($r['statut'] ?? null, 100) ?: ($date_validation ? 'Validé' : 'En cours');

    $sql = "INSERT INTO demande_analyse
            (id_demande, ref_demande, id_secteur, date_demande, date_creation, date_cloture, date_validation, statut, id_client)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                ref_demande = COALESCE(ref_demande, VALUES(ref_demande)),
                id_secteur = COALESCE(id_secteur, VALUES(id_secteur)),
                date_demande = COALESCE(date_demande, VALUES(date_demande)),
                date_creation = COALESCE(date_creation, VALUES(date_creation)),
                date_cloture = COALESCE(date_cloture, VALUES(date_cloture)),
                date_validation = COALESCE(date_validation, VALUES(date_validation)),
                statut = COALESCE(statut, VALUES(statut)),
                id_client = COALESCE(id_client, VALUES(id_client))";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare ensure demande_analyse : " . $conn->error);
    $stmt->bind_param("isisssssi", $id_demande, $ref_demande, $id_secteur, $date_demande, $date_creation, $date_cloture, $date_validation, $statut, $id_client);
    if (!$stmt->execute()) throw new Exception("Erreur execute ensure demande_analyse : " . $stmt->error);
    $stmt->close();
}

function upsertRapport($conn, $r){
    $id_rapport = safeVarchar($r['rapport_id'] ?? null, 100);
    if ($id_rapport === null) return false;

    upsertSecteur($conn, $r['secteur_id'] ?? null, $r['secteur_name'] ?? null);
    upsertAnalyse(
        $conn,
        $r['analyse_id'] ?? null,
        $r['nom_analyse'] ?? null,
        $r['secteur_id'] ?? null,
        $r['reference_analyse'] ?? null,
        $r['methodeanalyse'] ?? null,
        $r['ra_unite_result'] ?? null
    );
    upsertLaborantin($conn, $r['manipulateur_id'] ?? null, $r['manipulateur_nom'] ?? null, $r['secteur_id'] ?? null);
    upsertLaborantin($conn, $r['validateur_id'] ?? null, $r['validateur_nom'] ?? null, $r['secteur_id'] ?? null);
    upsertCritere($conn, $r['critere_id'] ?? null, $r['critere_titre'] ?? null, $r['critere_rule'] ?? null, $r['des_critere'] ?? null);

    $rapport = safeVarchar($r['ref_rapport'] ?? null, 255) ?: $id_rapport;
    $id_demande = cleanInt($r['demande_id'] ?? null);
    ensureDemandeAnalyseExists($conn, $id_demande, $r);
    $type_rapport = safeVarchar($r['type_rapport_selon_demande'] ?? null, 50) ?: safeVarchar($r['type_rapport'] ?? null, 50);
    $report_layout = safeVarchar($r['type_rapport'] ?? null, 50);
    $date_edition = safeDateTimeYMD($r['date_creation_rapport'] ?? null);
    $date_validation = safeDateTimeYMD($r['date_validation'] ?? null);
    $date_livraison_interne = $date_validation;
    $date_livraison = $date_validation;
    $laborantin_validateur = cleanInt($r['validateur_id'] ?? null);
    $statut = $date_validation ? 'Validé' : 'En cours';
    $secteur_id = cleanInt($r['secteur_id'] ?? null);
    $id_csv = cleanInt($r['rapport_id'] ?? null);
    $responsable_essai = cleanInt($r['manipulateur_id'] ?? null);
    $responsable_technique = cleanInt($r['validateur_id'] ?? null);
    $co_responsable_essai = null;
    $observation = cleanString($r['ra_fr_commentaire'] ?? null);

    $sql = "INSERT INTO rapports
            (id_rapport, rapport, id_demande, type_rapport, report_layout, date_edition, date_validation, date_livraison_interne, date_livraison, laborantin_validateur, statut, secteur_id, id_csv, responsable_essai, responsable_technique, co_responsable_essai, observation)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                rapport = COALESCE(VALUES(rapport), rapport),
                id_demande = COALESCE(VALUES(id_demande), id_demande),
                type_rapport = COALESCE(VALUES(type_rapport), type_rapport),
                report_layout = COALESCE(VALUES(report_layout), report_layout),
                date_edition = COALESCE(VALUES(date_edition), date_edition),
                date_validation = COALESCE(VALUES(date_validation), date_validation),
                date_livraison_interne = COALESCE(VALUES(date_livraison_interne), date_livraison_interne),
                date_livraison = COALESCE(VALUES(date_livraison), date_livraison),
                laborantin_validateur = COALESCE(VALUES(laborantin_validateur), laborantin_validateur),
                statut = COALESCE(VALUES(statut), statut),
                secteur_id = COALESCE(VALUES(secteur_id), secteur_id),
                id_csv = COALESCE(VALUES(id_csv), id_csv),
                responsable_essai = COALESCE(VALUES(responsable_essai), responsable_essai),
                responsable_technique = COALESCE(VALUES(responsable_technique), responsable_technique),
                co_responsable_essai = COALESCE(VALUES(co_responsable_essai), co_responsable_essai),
                observation = COALESCE(VALUES(observation), observation)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare rapports : " . $conn->error);
    $stmt->bind_param("ssissssssisiiiiis", $id_rapport, $rapport, $id_demande, $type_rapport, $report_layout, $date_edition, $date_validation, $date_livraison_interne, $date_livraison, $laborantin_validateur, $statut, $secteur_id, $id_csv, $responsable_essai, $responsable_technique, $co_responsable_essai, $observation);
    if (!$stmt->execute()) throw new Exception("Erreur execute rapports : " . $stmt->error);
    $stmt->close();
    return true;
}

function updateDemandeFromRapport($conn, $r){
    $id_demande = cleanInt($r['demande_id'] ?? null);
    if ($id_demande === null) return;

    $date_debut_analyse = safeDateYMD($r['date_debut_analyse'] ?? null);
    $date_fin_analyse = safeDateYMD($r['date_fin_analyse'] ?? null);
    $date_validation = safeDateTimeYMD($r['date_validation'] ?? null);
    $date_cloture = $date_validation;

    $sql = "UPDATE demande_analyse
            SET date_debut_analyse = COALESCE(?, date_debut_analyse),
                date_fin_analyse = COALESCE(?, date_fin_analyse),
                date_validation = COALESCE(?, date_validation),
                date_cloture = COALESCE(?, date_cloture)
            WHERE id_demande = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare update demande_analyse(rapport) : " . $conn->error);
    $stmt->bind_param("ssssi", $date_debut_analyse, $date_fin_analyse, $date_validation, $date_cloture, $id_demande);
    if (!$stmt->execute()) throw new Exception("Erreur execute update demande_analyse(rapport) : " . $stmt->error);
    $stmt->close();
}

function importerRapports($conn, $file){
    list($handle, $delimiter, $headers) = openCsvAssoc($file);
    $count = 0;
    $seen = [];
    while (($r = nextAssocRow($handle, $delimiter, $headers)) !== false) {
        $id_rapport = safeVarchar($r['rapport_id'] ?? null, 100);
        if ($id_rapport !== null && !isset($seen[$id_rapport])) {
            if (upsertRapport($conn, $r)) {
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
    $csvDemandes = resolveCsvPath([
        __DIR__ . '/../../data_source/demande_anan.csv',
        __DIR__ . '/demande_anan.csv',
        '/mnt/data/demande_anan.csv'
    ]);
    $csvRapports = resolveCsvPath([
        __DIR__ . '/../../data_source/rapport.csv',
        __DIR__ . '/rapport.csv',
        '/mnt/data/rapport.csv'
    ]);

    echo "Début import_file.php...\n";
    $conn->begin_transaction();
    list($nbDemandes, $nbEchantillons) = importerDemandes($conn, $csvDemandes);
    $nbRapports = importerRapports($conn, $csvRapports);
    $conn->commit();
    echo "demande_analyse: $nbDemandes\n";
    echo "echantillons: $nbEchantillons\n";
    echo "rapports: $nbRapports\n";
} catch (Exception $e) {
    if (isset($conn) && method_exists($conn, 'rollback')) {
        @$conn->rollback();
    }
    echo "Erreur import_file.php : " . $e->getMessage();
}
?>



