<?php
require_once "../Back_End/db.php";
header("Content-Type: application/json; charset=utf-8");

function fetchAll($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("SQL error: " . $conn->error);
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

function fetchOne($conn, $sql, $default = []) {
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("SQL error: " . $conn->error);
    }
    return $result->fetch_assoc() ?: $default;
}

function normalizeInt($value) {
    return (int)($value ?? 0);
}

function normalizeFloat($value) {
    return (float)($value ?? 0);
}
function getOperationalDemandesCte() {
    return "
    WITH demande_client_agg AS (
        SELECT
            fdc.id_demande_analyse_sk,
            COUNT(*) AS nb_demande_client_liees,
            GROUP_CONCAT(DISTINCT ddc.ref_demande_client SEPARATOR ' | ') AS ref_demande_client,
            CASE
                WHEN SUM(CASE WHEN LOWER(TRIM(COALESCE(ddc.faisabilite, ''))) IN ('non','non faisable','non-faisable','infaisable','no','false','0') THEN 1 ELSE 0 END) > 0 THEN 'Non'
                WHEN SUM(CASE WHEN ddc.faisabilite IS NULL OR TRIM(ddc.faisabilite) = '' OR LOWER(TRIM(ddc.faisabilite)) IN ('oui','faisable','yes','true','1') THEN 1 ELSE 0 END) > 0 THEN 'Oui'
                ELSE NULL
            END AS faisabilite,
            CASE
                WHEN SUM(CASE WHEN LOWER(TRIM(COALESCE(ddc.validite, ''))) IN ('non','non valide','non-valide','invalide','no','false','0') THEN 1 ELSE 0 END) > 0 THEN 'Non'
                WHEN SUM(CASE WHEN ddc.validite IS NULL OR TRIM(ddc.validite) = '' OR LOWER(TRIM(ddc.validite)) IN ('oui','valide','yes','true','1') THEN 1 ELSE 0 END) > 0 THEN 'Oui'
                ELSE NULL
            END AS validite
        FROM fait_demande_client fdc
        LEFT JOIN dim_demande_client ddc
            ON ddc.id_demande_client_sk = fdc.id_demande_client_sk
        WHERE fdc.id_demande_analyse_sk IS NOT NULL
        GROUP BY fdc.id_demande_analyse_sk
    ),
    ech_agg AS (
        SELECT
            foa.id_demande_analyse_sk,
            COUNT(DISTINCT foa.id_echantillon_sk) AS nb_echantillons_distinct,
            GROUP_CONCAT(DISTINCT de.type_echantillon SEPARATOR ' | ') AS type_echantillon,
            GROUP_CONCAT(DISTINCT de.nom_produit SEPARATOR ' | ') AS nom_produit,
            GROUP_CONCAT(DISTINCT de.code_produit_demandeur SEPARATOR ' | ') AS code_produit_demandeur,
            GROUP_CONCAT(DISTINCT de.ref_labo SEPARATOR ' | ') AS ref_labo,
            GROUP_CONCAT(DISTINCT de.est_conforme SEPARATOR ' | ') AS est_conforme_brut,
            CASE
                WHEN SUM(CASE WHEN LOWER(TRIM(COALESCE(de.est_conforme, ''))) IN ('non','non conforme','non-conforme','nonconforme','nc','ko','0','false') OR LOWER(TRIM(COALESCE(de.est_conforme, ''))) LIKE '%non%conform%' THEN 1 ELSE 0 END) > 0 THEN 'Non'
                WHEN SUM(CASE WHEN LOWER(TRIM(COALESCE(de.est_conforme, ''))) IN ('oui','conforme','c','ok','1','true') THEN 1 ELSE 0 END) > 0 THEN 'Oui'
                ELSE NULL
            END AS est_conforme_decision,
            MIN(de.date_reception) AS date_reception_echantillon,
            CASE
                WHEN MIN(de.ref_labo) IS NULL OR TRIM(MIN(de.ref_labo)) = '' THEN NULL
                WHEN LOCATE('*', MIN(de.ref_labo)) > 0 THEN TRIM(SUBSTRING_INDEX(MIN(de.ref_labo), '*', -1))
                ELSE TRIM(MIN(de.ref_labo))
            END AS code_ref_labo
        FROM fait_ordre_analyse foa
        LEFT JOIN dim_echantillon de
            ON de.id_echantillon_sk = foa.id_echantillon_sk
        WHERE foa.id_demande_analyse_sk IS NOT NULL
        GROUP BY foa.id_demande_analyse_sk
    ),
    analyse_agg AS (
        SELECT
            foa.id_demande_analyse_sk,
            COUNT(DISTINCT da.id_analyse_bk) AS nb_analyses_distinct,
            GROUP_CONCAT(
                DISTINCT COALESCE(NULLIF(TRIM(da.nom_analyse), ''), 'Analyse non nommée')
                ORDER BY COALESCE(NULLIF(TRIM(da.nom_analyse), ''), 'Analyse non nommée')
                SEPARATOR ' | '
            ) AS analyse_list,
            SUBSTRING_INDEX(
                GROUP_CONCAT(
                    DISTINCT COALESCE(NULLIF(TRIM(da.nom_analyse), ''), 'Analyse non nommée')
                    ORDER BY COALESCE(NULLIF(TRIM(da.nom_analyse), ''), 'Analyse non nommée')
                    SEPARATOR ' | '
                ),
                ' | ',
                1
            ) AS analyse_principale
        FROM fait_ordre_analyse foa
        LEFT JOIN dim_analyse da
            ON da.id_analyse_sk = foa.id_analyse_sk
        WHERE foa.id_demande_analyse_sk IS NOT NULL
          AND da.id_analyse_bk IS NOT NULL
          AND da.id_analyse_bk <> 0
        GROUP BY foa.id_demande_analyse_sk
    ),
    laborantin_agg AS (
        SELECT
            foa.id_demande_analyse_sk,
            COUNT(DISTINCT dl.id_laborantin_bk) AS nb_laborantins_distinct,
            GROUP_CONCAT(DISTINCT COALESCE(NULLIF(TRIM(dl.nom_laborantin), ''), CAST(dl.id_laborantin_bk AS CHAR)) ORDER BY dl.id_laborantin_bk SEPARATOR ' | ') AS laborantin_list
        FROM fait_ordre_analyse foa
        LEFT JOIN dim_laborantin dl
            ON dl.id_laborantin_sk = foa.id_laborantin_sk
        WHERE foa.id_demande_analyse_sk IS NOT NULL
          AND dl.id_laborantin_bk IS NOT NULL
          AND dl.id_laborantin_bk <> 0
        GROUP BY foa.id_demande_analyse_sk
    ),
    rapport_agg AS (
        SELECT
            fr.id_demande_analyse_sk,
            COUNT(*) AS nb_rapports,
            MAX(COALESCE(fr.rapport_provisoire_flag, 0)) AS has_rapport_provisoire,
            MAX(COALESCE(fr.rapport_final_flag, 0)) AS has_rapport_final,
            MAX(CASE WHEN fr.delai_total_demande_livraison_jours IS NOT NULL AND fr.delai_total_demande_livraison_jours >= 0 THEN 1 ELSE 0 END) AS has_rapport_livre,
            GROUP_CONCAT(DISTINCT fr.reference_rapport SEPARATOR ' | ') AS reference_rapport
        FROM fait_rapport fr
        GROUP BY fr.id_demande_analyse_sk
    ),
    prediction_agg AS (
        SELECT
            fp.id_demande_analyse_sk,
            MAX(fp.delai_predit_jours) AS delai_predit_jours,
            MAX(fp.date_livraison_estimee) AS date_livraison_estimee,
            MAX(fp.jours_restants_predits) AS jours_restants_predits,
            MAX(fp.jours_retard_predit) AS jours_retard_predit,
            MAX(fp.retard_predit_flag) AS retard_predit_flag,
            MAX(CASE
                WHEN LOWER(TRIM(fp.niveau_risque)) = 'critique' THEN 4
                WHEN LOWER(TRIM(fp.niveau_risque)) IN ('élevé', 'eleve') THEN 3
                WHEN LOWER(TRIM(fp.niveau_risque)) = 'moyen' THEN 2
                WHEN LOWER(TRIM(fp.niveau_risque)) = 'faible' THEN 1
                ELSE 0
            END) AS niveau_risque_rank
        FROM fait_prediction fp
        GROUP BY fp.id_demande_analyse_sk
    ),
    raw_base AS (
        SELECT
            dda.id_demande_analyse_bk AS id_demande,
            fda.id_demande_analyse_sk,
            dda.ref_demande,
            dda.ref_demande AS reference_demande,
            fda.id_client_sk,
            dc.id_client_bk AS id_client,
            dc.client_name,
            dc.client_name AS client_nom,
            dc.type_client,
            fda.id_secteur_sk,
            ds.id_secteur_bk AS id_secteur,
            COALESCE(ds.nom_secteur, 'Secteur inconnu') AS secteur_nom,
            COALESCE(ds.nom_secteur, 'Secteur inconnu') AS nom_secteur,
            fda.id_statut_sk,
            dstat.statut_demande AS raw_statut_demande,
            dstat.statut_demande,
            dstat.statut_ordre,
            dstat.statut_rapport,
            dstat.statut_prediction,
            dstat.conformite,
            dt_dem.date_complete AS date_demande,
            ech.date_reception_echantillon AS date_reception,
            COALESCE(dca.faisabilite, 'Oui') AS faisabilite,
            COALESCE(dca.validite, 'Oui') AS validite,
            COALESCE(ech.est_conforme_decision, dstat.conformite) AS est_conforme,
            fda.nb_demandes_analyse,
            COALESCE(fda.nb_analyses, 0) AS nb_analyses,
            COALESCE(fda.nb_ordres, 0) AS nb_ordres,
            COALESCE(fda.nb_resultats, 0) AS nb_resultats,
            COALESCE(fda.nb_echantillons, COALESCE(ech.nb_echantillons_distinct, 0)) AS nb_echantillons,
            COALESCE(fda.nb_echantillons, COALESCE(ech.nb_echantillons_distinct, 0)) AS nb_fiches_paillasse,
            fda.delai_reception_jours AS delai_reception,
            fda.delai_affectation_jours AS delai_affectation,
            fda.delai_debut_analyse_jours AS delai_debut_analyse,
            fda.delai_total_jours AS delai_total_demande,
            fda.delai_total_jours AS delai_total_jours,
            CASE WHEN fda.delai_total_jours IS NOT NULL THEN fda.delai_total_jours * 1440 ELSE NULL END AS delai_total_minutes,
            ech.type_echantillon,
            ech.nom_produit,
            ech.code_produit_demandeur,
            ech.ref_labo,
            ech.code_ref_labo,
            ech.date_reception_echantillon,
            COALESCE(ana.nb_analyses_distinct, 0) AS nb_analyses_distinct,
            ana.analyse_list,
            ana.analyse_principale,
            COALESCE(lab.nb_laborantins_distinct, 0) AS nb_laborantins_distinct,
            lab.laborantin_list,
            dca.ref_demande_client,
            COALESCE(dca.nb_demande_client_liees, 0) AS nb_demande_client_liees,
            COALESCE(rapp.nb_rapports, 0) AS nb_rapports,
            COALESCE(rapp.has_rapport_provisoire, 0) AS has_rapport_provisoire,
            COALESCE(rapp.has_rapport_final, 0) AS has_rapport_final,
            COALESCE(rapp.has_rapport_livre, 0) AS has_rapport_livre,
            rapp.reference_rapport,
            'format_1' AS report_layout,
            'format_1' AS default_layout,
            pred.delai_predit_jours,
            pred.date_livraison_estimee,
            pred.jours_restants_predits,
            pred.jours_retard_predit,
            COALESCE(pred.retard_predit_flag, 0) AS retard_predit_flag,
            CASE pred.niveau_risque_rank
                WHEN 4 THEN 'Critique'
                WHEN 3 THEN 'Élevé'
                WHEN 2 THEN 'Moyen'
                WHEN 1 THEN 'Faible'
                ELSE NULL
            END AS niveau_risque,
            COALESCE(pred.niveau_risque_rank, 0) AS niveau_risque_rank,
            CASE
                WHEN LOWER(TRIM(COALESCE(ech.est_conforme_decision, dstat.conformite, ''))) IN ('non','non conforme','non-conforme','nonconforme','nc','ko','0','false')
                  OR LOWER(TRIM(COALESCE(ech.est_conforme_decision, dstat.conformite, ''))) LIKE '%non%conform%'
                THEN 1 ELSE 0
            END AS non_conforme_flag,
            CASE
                WHEN dstat.statut_demande IS NOT NULL
                 AND LOWER(TRIM(dstat.statut_demande)) IN ('cloture','clôturé','cloturé','cloturee','clôturée','termine','terminé','terminee','terminée','valide','validé','validee','validée','annule','annulé','rejete','rejeté','refuse','refusé')
                THEN 1 ELSE 0
            END AS raw_closed_flag,
            CASE
                WHEN COALESCE(fda.nb_ordres, 0) > 0 AND COALESCE(fda.nb_resultats, 0) >= COALESCE(fda.nb_ordres, 0)
                THEN 1 ELSE 0
            END AS results_complete_flag,
            CASE
                WHEN COALESCE(fda.nb_ordres, 0) > 0
                  OR COALESCE(fda.nb_resultats, 0) > 0
                  OR COALESCE(fda.nb_analyses, 0) > 0
                  OR COALESCE(fda.nb_echantillons, 0) > 0
                  OR fda.delai_affectation_jours IS NOT NULL
                  OR fda.delai_debut_analyse_jours IS NOT NULL
                  OR COALESCE(ana.nb_analyses_distinct, 0) > 0
                  OR COALESCE(lab.nb_laborantins_distinct, 0) > 0
                THEN 1 ELSE 0
            END AS activity_flag
        FROM fait_demande_analyse fda
        LEFT JOIN dim_demande_analyse dda
            ON dda.id_demande_analyse_sk = fda.id_demande_analyse_sk
        LEFT JOIN dim_client dc
            ON dc.id_client_sk = fda.id_client_sk
        LEFT JOIN dim_secteur ds
            ON ds.id_secteur_sk = fda.id_secteur_sk
        LEFT JOIN dim_statut dstat
            ON dstat.id_statut_sk = fda.id_statut_sk
        LEFT JOIN dim_temps dt_dem
            ON dt_dem.id_temps = fda.id_temps_demande
        LEFT JOIN demande_client_agg dca
            ON dca.id_demande_analyse_sk = fda.id_demande_analyse_sk
        LEFT JOIN ech_agg ech
            ON ech.id_demande_analyse_sk = fda.id_demande_analyse_sk
        LEFT JOIN analyse_agg ana
            ON ana.id_demande_analyse_sk = fda.id_demande_analyse_sk
        LEFT JOIN laborantin_agg lab
            ON lab.id_demande_analyse_sk = fda.id_demande_analyse_sk
        LEFT JOIN rapport_agg rapp
            ON rapp.id_demande_analyse_sk = fda.id_demande_analyse_sk
        LEFT JOIN prediction_agg pred
            ON pred.id_demande_analyse_sk = fda.id_demande_analyse_sk
        WHERE dt_dem.date_complete IS NOT NULL
          AND dda.id_demande_analyse_bk IS NOT NULL
          AND dt_dem.date_complete >= '2000-01-01'
          AND dc.id_client_bk IS NOT NULL
          AND dc.id_client_bk <> 0
    ),
    base AS (
        SELECT
            raw_base.*,
            CONCAT('DEM-', raw_base.id_demande) AS row_key,
            CONCAT(LEFT(CAST(raw_base.id_demande AS CHAR), 2), '***') AS id_demande_masked,
            CONCAT(LEFT(CAST(raw_base.id_client AS CHAR), 2), '***') AS id_client_masked,
            CASE
                WHEN raw_closed_flag = 1 OR has_rapport_final = 1 OR results_complete_flag = 1 THEN 'Terminée'
                WHEN LOWER(TRIM(COALESCE(raw_statut_demande, ''))) IN ('en cours','encours') THEN 'En cours'
                WHEN activity_flag = 1 THEN 'En cours'
                ELSE 'Ouverte'
            END AS statut_operationnel,
            CASE
                WHEN raw_closed_flag = 1 OR has_rapport_final = 1 OR results_complete_flag = 1 THEN 1
                ELSE 0
            END AS can_generate_report,
            CASE
                WHEN raw_closed_flag = 1 OR has_rapport_final = 1 OR results_complete_flag = 1 THEN 1
                ELSE 0
            END AS can_translate_report,
            CASE
                WHEN has_rapport_final = 1 THEN 'FINAL_EXISTS'
                WHEN raw_closed_flag = 1 OR results_complete_flag = 1 THEN 'READY'
                ELSE 'BLOCKED'
            END AS report_status,
            CASE
                WHEN raw_closed_flag = 1 OR has_rapport_final = 1 OR results_complete_flag = 1 THEN NULL
                WHEN COALESCE(nb_ordres, 0) = 0 THEN 'Aucun ordre d’analyse'
                WHEN COALESCE(nb_resultats, 0) < COALESCE(nb_ordres, 0) THEN 'Résultats incomplets'
                ELSE 'Rapport non prêt'
            END AS report_block_reason,
            CONCAT('../tcpdf_rapport/new_report.php?id_demande=', raw_base.id_demande, '&format=format_1&lang=fr') AS rapport_fr_url,
            CONCAT('../tcpdf_rapport/new_report.php?id_demande=', raw_base.id_demande, '&format=format_1&lang=en') AS rapport_en_url,
            CONCAT('../tcpdf_rapport/new_report.php?id_demande=', raw_base.id_demande, '&format=format_1&lang=ar') AS rapport_ar_url
        FROM raw_base
    )
    ";
}

function selectFromOperationalBase($conn_dw, $selectSql) {
    $sql = getOperationalDemandesCte() . "\n" . $selectSql;
    return fetchAll($conn_dw, $sql);
}

function getDemandesOperationnelles($conn_dw) {
    return selectFromOperationalBase($conn_dw, "
        SELECT *
        FROM base
        ORDER BY date_demande DESC, id_demande DESC
    ");
}


function getDemandesOperationnellesPreview($conn_dw, $limit = 12) {
    $limit = max(1, min(100, (int)$limit));
    return selectFromOperationalBase($conn_dw, "
        SELECT *
        FROM base
        ORDER BY date_demande DESC, id_demande DESC
        LIMIT " . $limit . "
    ");
}

function getDemandesStatusTrendLight($conn_dw) {
    return selectFromOperationalBase($conn_dw, "
        SELECT
            id_demande,
            id_demande_analyse_sk,
            reference_demande,
            date_demande,
            raw_statut_demande AS statut_brut,
            statut_operationnel
        FROM base
        WHERE date_demande IS NOT NULL
        ORDER BY date_demande ASC, id_demande ASC
    ");
}

function getAlertesSummaryLight($conn_dw) {
    $sql = getOperationalDemandesCte() . "
        SELECT
            SUM(CASE WHEN COALESCE(retard_predit_flag, 0) = 1 OR COALESCE(jours_retard_predit, 0) > 0 THEN 1 ELSE 0 END) AS retard_predit,
            SUM(CASE WHEN niveau_risque = 'Critique' THEN 1 ELSE 0 END) AS risque_critique,
            SUM(CASE WHEN niveau_risque = 'Élevé' THEN 1 ELSE 0 END) AS risque_eleve,
            SUM(CASE WHEN COALESCE(delai_total_demande, 0) >= 7 OR COALESCE(jours_retard_predit, 0) > 0 THEN 1 ELSE 0 END) AS delai_long,
            SUM(CASE WHEN non_conforme_flag = 1 THEN 1 ELSE 0 END) AS non_conformite,
            SUM(CASE WHEN COALESCE(nb_ordres, 0) > 0 AND COALESCE(nb_laborantins_distinct, 0) = 0 THEN 1 ELSE 0 END) AS sans_laborantin,
            SUM(CASE WHEN COALESCE(nb_ordres, 0) > 0 AND COALESCE(nb_resultats, 0) < COALESCE(nb_ordres, 0) AND statut_operationnel <> 'Terminée' THEN 1 ELSE 0 END) AS resultat_manquant,
            SUM(CASE WHEN can_generate_report = 1 AND has_rapport_final = 0 THEN 1 ELSE 0 END) AS rapport_manquant
        FROM base
    ";
    $row = fetchOne($conn_dw, $sql, [
        "retard_predit" => 0,
        "risque_critique" => 0,
        "risque_eleve" => 0,
        "delai_long" => 0,
        "non_conformite" => 0,
        "sans_laborantin" => 0,
        "resultat_manquant" => 0,
        "rapport_manquant" => 0
    ]);

    foreach ($row as $key => $value) {
        $row[$key] = normalizeInt($value);
    }

    $summary = [
        "total" => 0,
        "critique" => $row["risque_critique"],
        "eleve" => $row["risque_eleve"] + $row["non_conformite"] + $row["rapport_manquant"],
        "moyen" => $row["retard_predit"] + $row["delai_long"] + $row["sans_laborantin"] + $row["resultat_manquant"],
        "retard_predit" => $row["retard_predit"],
        "risque_critique" => $row["risque_critique"],
        "risque_eleve" => $row["risque_eleve"],
        "non_conformite" => $row["non_conformite"],
        "rapport_manquant" => $row["rapport_manquant"],
        "resultat_manquant" => $row["resultat_manquant"],
        "sans_laborantin" => $row["sans_laborantin"],
        "delai_long" => $row["delai_long"]
    ];
    $summary["total"] = $summary["retard_predit"] + $summary["risque_critique"] + $summary["risque_eleve"] + $summary["delai_long"] + $summary["non_conformite"] + $summary["rapport_manquant"] + $summary["resultat_manquant"] + $summary["sans_laborantin"];
    return $summary;
}

function getChartAlertesGraviteFromSummary($summary) {
    return [
        ["gravite" => "Critique", "total" => normalizeInt($summary["critique"] ?? 0)],
        ["gravite" => "Élevé", "total" => normalizeInt($summary["eleve"] ?? 0)],
        ["gravite" => "Moyen", "total" => normalizeInt($summary["moyen"] ?? 0)]
    ];
}

function getChartAlertesTypeFromSummary($summary) {
    return [
        ["type_alerte" => "RETARD_PREDIT", "total" => normalizeInt($summary["retard_predit"] ?? 0)],
        ["type_alerte" => "RISQUE_CRITIQUE", "total" => normalizeInt($summary["risque_critique"] ?? 0)],
        ["type_alerte" => "RISQUE_ELEVE", "total" => normalizeInt($summary["risque_eleve"] ?? 0)],
        ["type_alerte" => "DELAI_LONG", "total" => normalizeInt($summary["delai_long"] ?? 0)],
        ["type_alerte" => "NON_CONFORMITE", "total" => normalizeInt($summary["non_conformite"] ?? 0)],
        ["type_alerte" => "RAPPORT_MANQUANT", "total" => normalizeInt($summary["rapport_manquant"] ?? 0)],
        ["type_alerte" => "RESULTAT_MANQUANT", "total" => normalizeInt($summary["resultat_manquant"] ?? 0)],
        ["type_alerte" => "SANS_LABORANTIN", "total" => normalizeInt($summary["sans_laborantin"] ?? 0)]
    ];
}

function getKpisClean($conn_dw) {
    global $conn_labo;
    $sql = getOperationalDemandesCte() . "
        SELECT
            COUNT(*) AS total_demandes,
            SUM(CASE
                WHEN raw_statut_demande IS NULL
                  OR TRIM(raw_statut_demande) = ''
                  OR LOWER(TRIM(raw_statut_demande)) IN ('ouverte','ouvert','pas evalué','pas évalué','non evalué','non évalué')
                THEN 1 ELSE 0
            END) AS demandes_ouvertes,
            SUM(CASE WHEN LOWER(TRIM(COALESCE(raw_statut_demande, ''))) IN ('en cours','encours') THEN 1 ELSE 0 END) AS demandes_en_cours,
            SUM(CASE
                WHEN LOWER(TRIM(COALESCE(raw_statut_demande, ''))) IN ('cloture','clôturé','cloturé','cloturee','clôturée','termine','terminé','terminee','terminée','valide','validé','validee','validée','livre','livré')
                THEN 1 ELSE 0
            END) AS demandes_terminees,
            ROUND(AVG(CASE WHEN delai_total_demande IS NOT NULL AND delai_total_demande >= 0 THEN delai_total_demande ELSE NULL END), 2) AS delai_moyen_jours,
            SUM(CASE WHEN can_generate_report = 1 AND (
                    LOWER(TRIM(COALESCE(raw_statut_demande, ''))) IN ('cloture','clôturé','cloturé','cloturee','clôturée','termine','terminé','terminee','terminée','valide','validé','validee','validée','livre','livré')
                    OR statut_operationnel = 'Terminée'
                ) THEN 1 ELSE 0 END) AS rapports_prets,
            SUM(CASE WHEN non_conforme_flag = 1 THEN 1 ELSE 0 END) AS non_conformes,
            SUM(CASE
                WHEN COALESCE(retard_predit_flag, 0) = 1
                  OR COALESCE(jours_retard_predit, 0) > 0
                  OR niveau_risque_rank IN (3, 4)
                  OR COALESCE(delai_total_demande, 0) >= 7
                THEN 1 ELSE 0
            END) AS alertes_delai,
            SUM(CASE
                WHEN COALESCE(delai_total_demande, 0) >= 7
                  OR COALESCE(jours_retard_predit, 0) > 0
                THEN 1 ELSE 0
            END) AS depassement_operationnel
        FROM base
    ";
    $row = fetchOne($conn_dw, $sql, [
        "total_demandes" => 0,
        "demandes_ouvertes" => 0,
        "demandes_en_cours" => 0,
        "demandes_terminees" => 0,
        "delai_moyen_jours" => 0,
        "rapports_prets" => 0,
        "alertes_delai" => 0,
        "depassement_operationnel" => 0,
        "non_conformes" => 0
    ]);
    $row["total_affectations"] = (isset($conn_labo) && !$conn_labo->connect_error)
        ? (int)(fetchOne($conn_labo, "SELECT COUNT(DISTINCT id_fiche) AS total_affectations FROM fiches_paillasse", ["total_affectations" => 0])["total_affectations"] ?? 0)
        : 0;
    return $row;
}

function getEchantillons($conn_dw) {
    $sql = getOperationalDemandesCte() . "
        SELECT
            SUM(CASE WHEN non_conforme_flag = 0 THEN 1 ELSE 0 END) AS conformes,
            SUM(CASE WHEN non_conforme_flag = 1 THEN 1 ELSE 0 END) AS non_conformes
        FROM base
    ";
    return fetchOne($conn_dw, $sql, ["conformes" => 0, "non_conformes" => 0]);
}

function getTotalDemandes($conn_dw) {
    $row = getKpisClean($conn_dw);
    return (int)($row["total_demandes"] ?? 0);
}

function getAffectations($conn_dw) {
    global $conn_labo;

    if (isset($conn_labo) && !$conn_labo->connect_error) {
        return fetchOne(
            $conn_labo,
            "SELECT COUNT(DISTINCT id_fiche) AS total_affectations FROM fiches_paillasse",
            ["total_affectations" => 0]
        );
    }

    $sql = "
        SELECT COUNT(DISTINCT id_echantillon_sk) AS total_affectations
        FROM fait_ordre_analyse
        WHERE id_echantillon_sk IS NOT NULL
    ";
    return fetchOne($conn_dw, $sql, ["total_affectations" => 0]);
}

function getDelaiMoyen($conn_dw) {
    $sql = getOperationalDemandesCte() . "
        SELECT ROUND(AVG(CASE WHEN delai_total_demande IS NOT NULL AND delai_total_demande >= 0 THEN delai_total_demande * 1440 ELSE NULL END), 2) AS moyenne_delai_minutes
        FROM base
    ";
    return fetchOne($conn_dw, $sql, ["moyenne_delai_minutes" => 0]);
}


function getChartStatutsDemandes($conn_dw) {
    return selectFromOperationalBase($conn_dw, "
        SELECT statut_operationnel AS statut, COUNT(*) AS total
        FROM base
        GROUP BY statut_operationnel
        ORDER BY FIELD(statut_operationnel, 'Ouverte', 'En cours', 'Terminée')
    ");
}

function getDemandesSecteur($conn_dw) {
    return selectFromOperationalBase($conn_dw, "
        SELECT
            secteur_nom AS nom_secteur,
            secteur_nom AS secteur,
            COUNT(*) AS total,
            COUNT(*) AS total_demandes,
            ROUND(AVG(CASE WHEN delai_total_demande IS NOT NULL AND delai_total_demande >= 0 THEN delai_total_demande ELSE NULL END), 2) AS delai_moyen_jours,
            ROUND(AVG(CASE WHEN delai_total_demande IS NOT NULL AND delai_total_demande >= 0 THEN delai_total_demande * 1440 ELSE NULL END), 2) AS delai_moyen_minutes
        FROM base
        GROUP BY secteur_nom
        ORDER BY total DESC, secteur_nom ASC
    ");
}

function getChartConformiteEchantillons($conn_dw) {
    return selectFromOperationalBase($conn_dw, "
        SELECT
            CASE WHEN non_conforme_flag = 1 THEN 'Non conforme' ELSE 'Conforme' END AS conformite,
            COUNT(*) AS total
        FROM base
        GROUP BY CASE WHEN non_conforme_flag = 1 THEN 'Non conforme' ELSE 'Conforme' END
        ORDER BY FIELD(conformite, 'Conforme', 'Non conforme')
    ");
}

function getChartAlertesRisque($conn_dw) {
    return selectFromOperationalBase($conn_dw, "
        SELECT
            COALESCE(niveau_risque, 'Non défini') AS niveau_risque,
            COUNT(*) AS total
        FROM base
        GROUP BY COALESCE(niveau_risque, 'Non défini')
        ORDER BY FIELD(COALESCE(niveau_risque, 'Non défini'), 'Critique', 'Élevé', 'Moyen', 'Faible', 'Non défini')
    ");
}

function getChartEvolutionDelai($conn_dw) {
    $sql = "
        SELECT
            dt.mois_annee AS mois,
            dt.annee,
            dt.mois AS mois_num,
            ROUND(AVG(fda.delai_total_jours), 2) AS delai_moyen_jours
        FROM fait_demande_analyse fda
        LEFT JOIN dim_temps dt
            ON dt.id_temps = fda.id_temps_demande
        WHERE fda.delai_total_jours IS NOT NULL
          AND fda.delai_total_jours >= 0
          AND dt.date_complete IS NOT NULL
        GROUP BY dt.annee, dt.mois, dt.mois_annee
        ORDER BY dt.annee, dt.mois
    ";
    return fetchAll($conn_dw, $sql);
}

function getFichesLaborantin($conn_dw) {
    $sql = "
        SELECT
            COALESCE(dl.nom_laborantin, 'Non affecté') AS nom_laborantin,
            COALESCE(dl.nom_laborantin, 'Non affecté') AS laborantin,
            COUNT(*) AS total,
            COUNT(*) AS total_fiches,
            SUM(CASE WHEN foa.id_temps_affectation IS NOT NULL OR foa.id_laborantin_sk IS NOT NULL THEN 1 ELSE 0 END) AS fiches_affectees,
            SUM(CASE WHEN foa.id_temps_fin IS NOT NULL OR LOWER(TRIM(COALESCE(ds.statut_ordre, ''))) IN ('terminé','termine','validé','valide') THEN 1 ELSE 0 END) AS fiches_terminees,
            SUM(CASE WHEN (foa.id_temps_affectation IS NOT NULL OR foa.id_laborantin_sk IS NOT NULL) AND foa.id_temps_fin IS NULL AND LOWER(TRIM(COALESCE(ds.statut_ordre, ''))) NOT IN ('terminé','termine','validé','valide') THEN 1 ELSE 0 END) AS fiches_en_cours,
            ROUND(AVG(CASE WHEN foa.duree_analyse_jours IS NOT NULL AND foa.duree_analyse_jours >= 0 THEN foa.duree_analyse_jours ELSE NULL END), 2) AS delai_moyen_jours
        FROM fait_ordre_analyse foa
        LEFT JOIN dim_laborantin dl ON dl.id_laborantin_sk = foa.id_laborantin_sk
        LEFT JOIN dim_statut ds ON ds.id_statut_sk = foa.id_statut_sk
        GROUP BY COALESCE(dl.nom_laborantin, 'Non affecté')
        ORDER BY total_fiches DESC, laborantin ASC
    ";
    return fetchAll($conn_dw, $sql);
}

function getFichesSecteur($conn_dw) {
    $sql = "
        SELECT
            COALESCE(ds.nom_secteur, 'Secteur inconnu') AS nom_secteur,
            COALESCE(ds.nom_secteur, 'Secteur inconnu') AS secteur,
            COUNT(*) AS total,
            COUNT(*) AS total_fiches,
            ROUND(AVG(CASE WHEN foa.duree_analyse_jours IS NOT NULL AND foa.duree_analyse_jours >= 0 THEN foa.duree_analyse_jours ELSE NULL END), 2) AS delai_moyen_jours
        FROM fait_ordre_analyse foa
        LEFT JOIN dim_secteur ds ON ds.id_secteur_sk = foa.id_secteur_sk
        GROUP BY COALESCE(ds.nom_secteur, 'Secteur inconnu')
        ORDER BY total DESC, nom_secteur ASC
    ";
    return fetchAll($conn_dw, $sql);
}

function getChartOrdresStatut($conn_dw) {
    $sql = "
        SELECT
            COALESCE(NULLIF(TRIM(ds.statut_ordre), ''), 'Non défini') AS statut_ordre,
            COUNT(*) AS total
        FROM fait_ordre_analyse foa
        LEFT JOIN dim_statut ds ON ds.id_statut_sk = foa.id_statut_sk
        GROUP BY COALESCE(NULLIF(TRIM(ds.statut_ordre), ''), 'Non défini')
        ORDER BY total DESC
    ";
    return fetchAll($conn_dw, $sql);
}

function getChartDelaiLaborantin($conn_dw) {
    $sql = "
        SELECT
            COALESCE(dl.nom_laborantin, 'Non affecté') AS laborantin,
            COUNT(*) AS total_fiches,
            ROUND(AVG(CASE WHEN foa.duree_analyse_jours IS NOT NULL AND foa.duree_analyse_jours >= 0 THEN foa.duree_analyse_jours ELSE NULL END), 2) AS delai_moyen_jours
        FROM fait_ordre_analyse foa
        LEFT JOIN dim_laborantin dl ON dl.id_laborantin_sk = foa.id_laborantin_sk
        GROUP BY COALESCE(dl.nom_laborantin, 'Non affecté')
        ORDER BY delai_moyen_jours DESC, total_fiches DESC
    ";
    return fetchAll($conn_dw, $sql);
}

function getChartDelaiAnalyse($conn_dw) {
    $sql = "
        SELECT
            COALESCE(
                NULLIF(TRIM(da.nom_analyse), ''),
                'Analyse non nommée'
            ) AS analyse,
            COALESCE(
                NULLIF(TRIM(da.nom_analyse), ''),
                'Analyse non nommée'
            ) AS nom_analyse,
            da.code_analyse,
            COUNT(*) AS total_ordres,
            ROUND(AVG(CASE WHEN foa.duree_analyse_jours IS NOT NULL AND foa.duree_analyse_jours >= 0 THEN foa.duree_analyse_jours ELSE NULL END), 2) AS delai_moyen_jours
        FROM fait_ordre_analyse foa
        LEFT JOIN dim_analyse da ON da.id_analyse_sk = foa.id_analyse_sk
        GROUP BY
            COALESCE(
                NULLIF(TRIM(da.nom_analyse), ''),
                'Analyse non nommée'
            ),
            da.code_analyse
        HAVING delai_moyen_jours IS NOT NULL
        ORDER BY delai_moyen_jours DESC, total_ordres DESC
        LIMIT 10
    ";
    return fetchAll($conn_dw, $sql);
}

function getRapportsSecteur($conn_dw) {
    $sql = "
        SELECT
            COALESCE(ds.nom_secteur, 'Secteur inconnu') AS nom_secteur,
            COALESCE(ds.nom_secteur, 'Secteur inconnu') AS secteur,
            COUNT(*) AS total
        FROM fait_rapport fr
        LEFT JOIN dim_secteur ds ON ds.id_secteur_sk = fr.id_secteur_sk
        GROUP BY COALESCE(ds.nom_secteur, 'Secteur inconnu')
        ORDER BY total DESC, nom_secteur ASC
    ";
    return fetchAll($conn_dw, $sql);
}

function getChartRapportsStatut($conn_dw) {
    $sql = "
        SELECT
            CASE
                WHEN fr.delai_total_demande_livraison_jours IS NOT NULL
                 AND fr.delai_total_demande_livraison_jours >= 0
                THEN 'Livré'
                ELSE 'En attente'
            END AS statut_rapport,
            COUNT(*) AS total
        FROM fait_rapport fr
        GROUP BY CASE
            WHEN fr.delai_total_demande_livraison_jours IS NOT NULL
             AND fr.delai_total_demande_livraison_jours >= 0
            THEN 'Livré'
            ELSE 'En attente'
        END
        ORDER BY FIELD(statut_rapport, 'Livré', 'En attente')
    ";
    return fetchAll($conn_dw, $sql);
}

function getChartDelaiValidationRapport($conn_dw) {
    $sql = "
        SELECT
            dt.mois_annee AS mois,
            dt.annee,
            dt.mois AS mois_num,
            ROUND(AVG(fr.delai_creation_validation_jours), 2) AS delai_moyen_validation
        FROM fait_rapport fr
        LEFT JOIN dim_temps dt ON dt.id_temps = fr.id_temps_creation
        WHERE fr.delai_creation_validation_jours IS NOT NULL
          AND fr.delai_creation_validation_jours >= 0
          AND dt.date_complete IS NOT NULL
        GROUP BY dt.annee, dt.mois, dt.mois_annee
        ORDER BY dt.annee, dt.mois
    ";
    return fetchAll($conn_dw, $sql);
}


function getEchantillonsOperationnels($conn_dw) {
    $sql = "
        SELECT DISTINCT
            dda.id_demande_analyse_bk AS id_demande,
            dda.ref_demande AS reference_demande,
            de.id_echantillon_bk AS id_echantillon,
            de.ref_labo,
            de.ref_labo AS code_echantillon,
            de.type_echantillon,
            de.nom_produit,
            de.numero_lot,
            de.temperature_reception,
            de.date_reception,
            de.date_production,
            de.dlc,
            de.est_conforme
        FROM fait_ordre_analyse foa
        LEFT JOIN dim_demande_analyse dda ON dda.id_demande_analyse_sk = foa.id_demande_analyse_sk
        LEFT JOIN dim_echantillon de ON de.id_echantillon_sk = foa.id_echantillon_sk
        WHERE dda.id_demande_analyse_bk IS NOT NULL
        ORDER BY de.date_reception DESC, dda.id_demande_analyse_bk DESC
    ";
    return fetchAll($conn_dw, $sql);
}

function getOrdresOperationnels($conn_dw) {
    $sql = "
        WITH resultat_agg AS (
            SELECT
                fra.id_demande_analyse_sk,
                fra.id_analyse_sk,
                fra.id_echantillon_sk,
                COUNT(*) AS nb_resultats,
                GROUP_CONCAT(
                    DISTINCT COALESCE(
                        NULLIF(TRIM(fra.nom_resultat), ''),
                        NULLIF(CAST(fra.resultat_num AS CHAR), ''),
                        'Résultat'
                    )
                    SEPARATOR ' | '
                ) AS resultat,
                GROUP_CONCAT(DISTINCT NULLIF(CAST(fra.resultat_num AS CHAR), '') SEPARATOR ' | ') AS resultat_num,
                GROUP_CONCAT(DISTINCT NULLIF(TRIM(fra.nom_resultat), '') SEPARATOR ' | ') AS nom_resultat,
                MAX(fra.is_accredited) AS is_accredited
            FROM fait_resultat_analyse fra
            GROUP BY fra.id_demande_analyse_sk, fra.id_analyse_sk, fra.id_echantillon_sk
        )
        SELECT
            foa.id_ordre_bk AS id_ordre,
            foa.reference_ordre,
            foa.reference_ordre AS ordre_reference,
            dda.id_demande_analyse_bk AS id_demande,
            foa.id_demande_analyse_sk,
            dda.ref_demande AS reference_demande,
            COALESCE(ds.nom_secteur, 'Secteur inconnu') AS secteur_nom,
            de.id_echantillon_bk AS id_fiche,
            COALESCE(NULLIF(TRIM(de.ref_labo), ''), CONCAT('ECH-', de.id_echantillon_bk)) AS reference_fiche,
            COALESCE(NULLIF(CASE WHEN TRIM(COALESCE(da.nom_analyse, '')) REGEXP '^[0-9]+$' THEN '' ELSE TRIM(COALESCE(da.nom_analyse, '')) END, ''), 'Analyse non nommée') AS analyse_nom,
            COALESCE(NULLIF(CASE WHEN TRIM(COALESCE(da.nom_analyse, '')) REGEXP '^[0-9]+$' THEN '' ELSE TRIM(COALESCE(da.nom_analyse, '')) END, ''), 'Analyse non nommée') AS nom_analyse,
            da.code_analyse,
            COALESCE(NULLIF(TRIM(dl.nom_laborantin), ''), 'Non affecté') AS laborantin_nom,
            COALESCE(NULLIF(TRIM(dstat.statut_ordre), ''), 'Non défini') AS statut_ordre,
            dt_aff.date_complete AS date_affectation,
            dt_val.date_complete AS date_validation,
            foa.duree_analyse_jours,
            foa.delai_prise_en_charge_jours,
            CASE
                WHEN COALESCE(res.nb_resultats, 0) > 0 THEN 'Résultat saisi'
                ELSE 'Résultat manquant'
            END AS statut_resultat,
            res.resultat,
            res.resultat_num,
            res.nom_resultat,
            '' AS unite,
            '' AS methode,
            '' AS result_note,
            COALESCE(res.is_accredited, 0) AS is_accredited
        FROM fait_ordre_analyse foa
        LEFT JOIN dim_demande_analyse dda ON dda.id_demande_analyse_sk = foa.id_demande_analyse_sk
        LEFT JOIN dim_secteur ds ON ds.id_secteur_sk = foa.id_secteur_sk
        LEFT JOIN dim_analyse da ON da.id_analyse_sk = foa.id_analyse_sk
        LEFT JOIN dim_echantillon de ON de.id_echantillon_sk = foa.id_echantillon_sk
        LEFT JOIN dim_laborantin dl ON dl.id_laborantin_sk = foa.id_laborantin_sk
        LEFT JOIN dim_statut dstat ON dstat.id_statut_sk = foa.id_statut_sk
        LEFT JOIN dim_temps dt_aff ON dt_aff.id_temps = foa.id_temps_affectation
        LEFT JOIN dim_temps dt_val ON dt_val.id_temps = foa.id_temps_validation
        LEFT JOIN resultat_agg res
            ON res.id_demande_analyse_sk = foa.id_demande_analyse_sk
           AND res.id_analyse_sk = foa.id_analyse_sk
           AND res.id_echantillon_sk = foa.id_echantillon_sk
        ORDER BY dda.ref_demande ASC, reference_fiche ASC, foa.reference_ordre ASC
    ";
    return fetchAll($conn_dw, $sql);
}

function getRapportsOperationnels($conn_dw) {
    $sql = "
        WITH fda_demande AS (
            SELECT id_demande_analyse_sk, MIN(id_temps_demande) AS id_temps_demande
            FROM fait_demande_analyse
            GROUP BY id_demande_analyse_sk
        ),
        pred AS (
            SELECT id_demande_analyse_sk, MAX(date_livraison_estimee) AS date_livraison_estimee
            FROM fait_prediction
            GROUP BY id_demande_analyse_sk
        )
        SELECT
            fr.id_rapport_bk AS id_rapport,
            fr.reference_rapport,
            dda.id_demande_analyse_bk AS id_demande,
            fr.id_demande_analyse_sk,
            dda.ref_demande AS reference_demande,
            dc.client_name,
            dc.client_name AS client_nom,
            COALESCE(ds.nom_secteur, 'Secteur inconnu') AS secteur_nom,
            CASE
                WHEN COALESCE(fr.rapport_final_flag, 0) = 1 THEN 'Final'
                WHEN COALESCE(fr.rapport_provisoire_flag, 0) = 1 THEN 'Provisoire'
                ELSE 'Autre'
            END AS type_rapport,
            CASE
                WHEN fr.delai_total_demande_livraison_jours IS NOT NULL AND fr.delai_total_demande_livraison_jours >= 0 THEN 'Livré'
                ELSE 'En attente'
            END AS statut_livraison,
            CASE
                WHEN fr.delai_total_demande_livraison_jours IS NOT NULL AND fr.delai_total_demande_livraison_jours >= 0 THEN 'Livré'
                ELSE 'En attente'
            END AS statut_rapport,
            dt_cre.date_complete AS date_creation,
            dt_val.date_complete AS date_validation,
            pred.date_livraison_estimee,
            CASE
                WHEN fr.delai_total_demande_livraison_jours IS NOT NULL AND fr.delai_total_demande_livraison_jours >= 0 AND dt_dem.date_complete IS NOT NULL
                    THEN DATE_ADD(dt_dem.date_complete, INTERVAL fr.delai_total_demande_livraison_jours DAY)
                WHEN pred.date_livraison_estimee IS NOT NULL
                    THEN pred.date_livraison_estimee
                ELSE dt_val.date_complete
            END AS date_livraison,
            CASE
                WHEN fr.delai_total_demande_livraison_jours IS NOT NULL AND fr.delai_total_demande_livraison_jours >= 0 THEN 'green'
                WHEN pred.date_livraison_estimee IS NOT NULL AND pred.date_livraison_estimee < CURDATE() THEN 'red'
                WHEN pred.date_livraison_estimee IS NOT NULL THEN 'orange'
                ELSE 'orange'
            END AS date_livraison_color,
            CASE
                WHEN fr.delai_total_demande_livraison_jours IS NOT NULL AND fr.delai_total_demande_livraison_jours >= 0 THEN fr.delai_total_demande_livraison_jours
                WHEN dt_val.date_complete IS NOT NULL AND dt_dem.date_complete IS NOT NULL THEN DATEDIFF(dt_val.date_complete, dt_dem.date_complete)
                ELSE NULL
            END AS delai_livraison_jours,
            'format_1' AS report_layout,
            COALESCE(dstat.statut_rapport, '') AS statut_rapport_source,
            COALESCE(fr.rapport_provisoire_flag, 0) AS rapport_provisoire_flag,
            COALESCE(fr.rapport_final_flag, 0) AS rapport_final_flag,
            '' AS validateur
        FROM fait_rapport fr
        LEFT JOIN dim_demande_analyse dda ON dda.id_demande_analyse_sk = fr.id_demande_analyse_sk
        LEFT JOIN dim_client dc ON dc.id_client_sk = fr.id_client_sk
        LEFT JOIN dim_secteur ds ON ds.id_secteur_sk = fr.id_secteur_sk
        LEFT JOIN dim_statut dstat ON dstat.id_statut_sk = fr.id_statut_sk
        LEFT JOIN dim_temps dt_cre ON dt_cre.id_temps = fr.id_temps_creation
        LEFT JOIN dim_temps dt_val ON dt_val.id_temps = fr.id_temps_validation
        LEFT JOIN fda_demande fd ON fd.id_demande_analyse_sk = fr.id_demande_analyse_sk
        LEFT JOIN dim_temps dt_dem ON dt_dem.id_temps = fd.id_temps_demande
        LEFT JOIN pred ON pred.id_demande_analyse_sk = fr.id_demande_analyse_sk
        ORDER BY dt_cre.date_complete DESC, fr.reference_rapport DESC
    ";
    return fetchAll($conn_dw, $sql);
}
function getAlertesOperationnelles($conn_dw) {
    $sql = getOperationalDemandesCte() . "
        SELECT *
        FROM (
            SELECT
                id_demande,
                id_demande_analyse_sk,
                reference_demande,
                client_nom,
                secteur_nom,
                date_demande,
                statut_operationnel,
                'RETARD_PREDIT' AS type_alerte,
                CASE WHEN niveau_risque = 'Critique' THEN 'Critique' WHEN niveau_risque = 'Élevé' THEN 'Élevé' ELSE 'Moyen' END AS gravite,
                CONCAT('Retard prédit de ', COALESCE(jours_retard_predit, 0), ' jour(s).') AS message_alerte,
                'Vérifier la demande, les ordres et le rapport associé.' AS action_recommandee,
                delai_total_demande,
                delai_predit_jours,
                jours_retard_predit,
                niveau_risque
            FROM base
            WHERE COALESCE(retard_predit_flag, 0) = 1 OR COALESCE(jours_retard_predit, 0) > 0
            UNION ALL
            SELECT
                id_demande, id_demande_analyse_sk, reference_demande, client_nom, secteur_nom, date_demande, statut_operationnel,
                'RISQUE_CRITIQUE', 'Critique', 'La demande est classée en risque critique.', 'Traiter cette demande en priorité.',
                delai_total_demande, delai_predit_jours, jours_retard_predit, niveau_risque
            FROM base
            WHERE niveau_risque = 'Critique'
            UNION ALL
            SELECT
                id_demande, id_demande_analyse_sk, reference_demande, client_nom, secteur_nom, date_demande, statut_operationnel,
                'RISQUE_ELEVE', 'Élevé', 'La demande présente un risque élevé de retard.', 'Surveiller le délai et accélérer les étapes restantes.',
                delai_total_demande, delai_predit_jours, jours_retard_predit, niveau_risque
            FROM base
            WHERE niveau_risque = 'Élevé'
            UNION ALL
            SELECT
                id_demande, id_demande_analyse_sk, reference_demande, client_nom, secteur_nom, date_demande, statut_operationnel,
                'DELAI_LONG', 'Moyen', CONCAT('Délai total actuel : ', COALESCE(delai_total_demande, 0), ' jour(s).'), 'Contrôler les étapes restantes de la demande.',
                delai_total_demande, delai_predit_jours, jours_retard_predit, niveau_risque
            FROM base
            WHERE COALESCE(delai_total_demande, 0) >= 7 OR COALESCE(jours_retard_predit, 0) > 0
            UNION ALL

            SELECT
                id_demande, id_demande_analyse_sk, reference_demande, client_nom, secteur_nom, date_demande, statut_operationnel,
                'NON_CONFORMITE', 'Élevé', 'Un ou plusieurs échantillons sont non conformes.', 'Vérifier la conformité et la décision métier.',
                delai_total_demande, delai_predit_jours, jours_retard_predit, niveau_risque
            FROM base
            WHERE non_conforme_flag = 1

            UNION ALL

            SELECT
                id_demande, id_demande_analyse_sk, reference_demande, client_nom, secteur_nom, date_demande, statut_operationnel,
                'SANS_LABORANTIN', 'Moyen', 'Des ordres existent mais aucun laborantin n’est détecté.', 'Affecter un laborantin aux ordres concernés.',
                delai_total_demande, delai_predit_jours, jours_retard_predit, niveau_risque
            FROM base
            WHERE COALESCE(nb_ordres, 0) > 0 AND COALESCE(nb_laborantins_distinct, 0) = 0

            UNION ALL

            SELECT
                id_demande, id_demande_analyse_sk, reference_demande, client_nom, secteur_nom, date_demande, statut_operationnel,
                'RESULTAT_MANQUANT', 'Moyen', 'Des ordres existent mais les résultats sont incomplets.', 'Vérifier l’avancement des analyses et la saisie des résultats.',
                delai_total_demande, delai_predit_jours, jours_retard_predit, niveau_risque
            FROM base
            WHERE COALESCE(nb_ordres, 0) > 0 AND COALESCE(nb_resultats, 0) < COALESCE(nb_ordres, 0) AND statut_operationnel <> 'Terminée'

            UNION ALL

            SELECT
                id_demande, id_demande_analyse_sk, reference_demande, client_nom, secteur_nom, date_demande, statut_operationnel,
                'RAPPORT_MANQUANT', 'Élevé', 'La demande est prête mais aucun rapport final n’est détecté.', 'Générer le rapport de la demande.',
                delai_total_demande, delai_predit_jours, jours_retard_predit, niveau_risque
            FROM base
            WHERE can_generate_report = 1 AND has_rapport_final = 0
        ) alertes
        ORDER BY
            CASE gravite WHEN 'Critique' THEN 1 WHEN 'Élevé' THEN 2 WHEN 'Moyen' THEN 3 ELSE 4 END,
            date_demande DESC
    ";
    return fetchAll($conn_dw, $sql);
}

function buildAlertesSummary($alertes) {
    $summary = [
        "total" => 0,
        "critique" => 0,
        "eleve" => 0,
        "moyen" => 0,
        "retard_predit" => 0,
        "non_conformite" => 0,
        "rapport_manquant" => 0,
        "resultat_manquant" => 0,
        "sans_laborantin" => 0,
        "delai_long" => 0
    ];

    foreach ($alertes as $a) {
        $summary["total"]++;
        $gravite = strtolower(trim($a["gravite"] ?? ""));
        $type = strtoupper(trim($a["type_alerte"] ?? ""));
        if ($gravite === "critique") $summary["critique"]++;
        elseif ($gravite === "élevé" || $gravite === "eleve") $summary["eleve"]++;
        elseif ($gravite === "moyen") $summary["moyen"]++;
        if ($type === "RETARD_PREDIT") $summary["retard_predit"]++;
        if ($type === "NON_CONFORMITE") $summary["non_conformite"]++;
        if ($type === "RAPPORT_MANQUANT") $summary["rapport_manquant"]++;
        if ($type === "RESULTAT_MANQUANT") $summary["resultat_manquant"]++;
        if ($type === "SANS_LABORANTIN") $summary["sans_laborantin"]++;
        if ($type === "DELAI_LONG") $summary["delai_long"]++;
    }
    return $summary;
}

function getChartAlertesGraviteFromList($alertes) {
    $rows = [];
    foreach ($alertes as $a) {
        $key = $a["gravite"] ?: "Non défini";
        if (!isset($rows[$key])) $rows[$key] = ["gravite" => $key, "total" => 0];
        $rows[$key]["total"]++;
    }
    return array_values($rows);
}

function getChartAlertesTypeFromList($alertes) {
    $rows = [];
    foreach ($alertes as $a) {
        $key = $a["type_alerte"] ?: "Non défini";
        if (!isset($rows[$key])) $rows[$key] = ["type_alerte" => $key, "total" => 0];
        $rows[$key]["total"]++;
    }
    return array_values($rows);
}

function getChartRetardsPreditsSecteur($conn_dw) {
    return selectFromOperationalBase($conn_dw, "
        SELECT
            secteur_nom AS secteur,
            COUNT(*) AS total
        FROM base
        WHERE COALESCE(retard_predit_flag, 0) = 1 OR COALESCE(jours_retard_predit, 0) > 0 OR niveau_risque_rank IN (3, 4)
        GROUP BY secteur_nom
        ORDER BY total DESC, secteur_nom ASC
    ");
}
function getDemandesDates($conn_dw) {
    return selectFromOperationalBase($conn_dw, "
        SELECT id_demande, date_demande
        FROM base
        WHERE date_demande IS NOT NULL
        ORDER BY date_demande DESC, id_demande DESC
    ");
}

function getOperationalFilters($conn_dw) {
    $filters = [];
    $filters["clients"] = fetchAll($conn_dw, "SELECT id_client_bk AS id_client, client_name FROM dim_client WHERE id_client_bk IS NOT NULL AND id_client_bk <> 0 ORDER BY client_name ASC");
    $filters["secteurs"] = fetchAll($conn_dw, "SELECT id_secteur_bk AS id_secteur, nom_secteur FROM dim_secteur ORDER BY nom_secteur ASC");
    $filters["laborantins"] = fetchAll($conn_dw, "SELECT id_laborantin_bk AS id_laborantin, nom_laborantin FROM dim_laborantin WHERE actif IS NULL OR actif = 1 ORDER BY nom_laborantin ASC");
    $filters["analyses"] = fetchAll($conn_dw, "SELECT da.id_analyse_bk AS id_analyse, COALESCE(NULLIF(CASE WHEN TRIM(COALESCE(da.nom_analyse, '')) REGEXP '^[0-9]+$' THEN '' ELSE TRIM(COALESCE(da.nom_analyse, '')) END, ''), 'Analyse non nommée') AS analyse FROM dim_analyse da ORDER BY analyse ASC");
    return $filters;
}

/*
|--------------------------------------------------------------------------
| Backward-compatible old wrappers / names
|--------------------------------------------------------------------------
*/

function getEchantillonsList($conn_dw) {
    return getEchantillonsOperationnels($conn_dw);
}

function getDemandesList($conn_dw) {
    return getDemandesOperationnelles($conn_dw);
}

function getFichesList($conn_dw) {
    return getOrdresOperationnels($conn_dw);
}

function getRapportsList($conn_dw) {
    return getRapportsOperationnels($conn_dw);
}

function getStatutsDemandes($conn_dw) {
    return getChartStatutsDemandes($conn_dw);
}

function buildOperationnelFullDashboardResponse($conn_dw, $conn_labo = null) {
    $demandes = getDemandesOperationnelles($conn_dw);
    $ordres = getOrdresOperationnels($conn_dw);
    $rapports = getRapportsOperationnels($conn_dw);
    $echantillons = getEchantillonsOperationnels($conn_dw);
    $alertes = getAlertesOperationnelles($conn_dw);
    $kpis = getKpisClean($conn_dw);
    $echantillonsSummary = getEchantillons($conn_dw);
    $delaiMoyen = getDelaiMoyen($conn_dw);
    $affectations = getAffectations($conn_dw);

    $charts = [
        "statuts_demandes" => getChartStatutsDemandes($conn_dw),
        "volume_delai_secteur" => getDemandesSecteur($conn_dw),
        "conformite_echantillons" => getChartConformiteEchantillons($conn_dw),
        "alertes_risque" => getChartAlertesRisque($conn_dw),
        "fiches_paillasse_laborantin" => getFichesLaborantin($conn_dw, $conn_labo),
        "evolution_delai" => getChartEvolutionDelai($conn_dw),
        "fiches_paillasse_secteur" => getFichesSecteur($conn_dw, $conn_labo),
        "ordres_statut" => getChartOrdresStatut($conn_dw),
        "delai_laborantin" => getChartDelaiLaborantin($conn_dw),
        "delai_analyse" => getChartDelaiAnalyse($conn_dw),
        "rapports_statut" => getChartRapportsStatut($conn_dw),
        "delai_validation_rapport" => getChartDelaiValidationRapport($conn_dw),
        "rapports_secteur" => getRapportsSecteur($conn_dw),
        "alertes_gravite" => getChartAlertesGraviteFromList($alertes),
        "alertes_type" => getChartAlertesTypeFromList($alertes),
        "retards_predits_secteur" => getChartRetardsPreditsSecteur($conn_dw)
    ];

    return [
        "success" => true,
        "status" => "success",
        "__light_payload" => false,
        "kpis_clean" => $kpis,
        "demandes_list" => $demandes,
        "ordres_list" => $ordres,
        "fiches_list" => $ordres,
        "echantillons_list" => $echantillons,
        "rapports_list" => $rapports,
        "alertes_list" => $alertes,
        "alertes_summary" => buildAlertesSummary($alertes),
        "charts" => $charts,
        "filters" => getOperationalFilters($conn_dw),
        "echantillons" => $echantillonsSummary,
        "fiches_laborantin" => $charts["fiches_paillasse_laborantin"],
        "fiches_secteur" => $charts["fiches_paillasse_secteur"],
        "demandes_secteur" => $charts["volume_delai_secteur"],
        "affectations" => $affectations,
        "delai_moyen" => $delaiMoyen,
        "rapports_secteur" => $charts["rapports_secteur"],
        "total_demandes" => normalizeInt($kpis["total_demandes"] ?? 0),
        "demandes_dates" => getDemandesDates($conn_dw),
        "statuts_demandes" => $charts["statuts_demandes"]
    ];
}

function buildOperationnelDashboardResponse($conn_dw, $conn_labo = null) {
    $demandesPreview = getDemandesOperationnellesPreview($conn_dw, 12);
    $demandesTrend = getDemandesStatusTrendLight($conn_dw);
    $kpis = getKpisClean($conn_dw);
    $echantillonsSummary = getEchantillons($conn_dw);
    $delaiMoyen = getDelaiMoyen($conn_dw);
    $affectations = getAffectations($conn_dw);
    $alertesSummary = getAlertesSummaryLight($conn_dw);

    $charts = [
        "statuts_demandes" => getChartStatutsDemandes($conn_dw),
        "volume_delai_secteur" => getDemandesSecteur($conn_dw),
        "conformite_echantillons" => getChartConformiteEchantillons($conn_dw),
        "alertes_risque" => getChartAlertesRisque($conn_dw),
        "fiches_paillasse_laborantin" => getFichesLaborantin($conn_dw, $conn_labo),
        "evolution_delai" => getChartEvolutionDelai($conn_dw),
        "alertes_gravite" => getChartAlertesGraviteFromSummary($alertesSummary),
        "alertes_type" => getChartAlertesTypeFromSummary($alertesSummary),
        "retards_predits_secteur" => getChartRetardsPreditsSecteur($conn_dw)
    ];

    return [
        "success" => true,
        "status" => "success",
        "__light_payload" => true,
        "kpis_clean" => $kpis,
        "demandes_list" => $demandesPreview,
        "demandes_preview" => $demandesPreview,
        "demandes_status_trend" => $demandesTrend,
        "ordres_list" => [],
        "fiches_list" => [],
        "echantillons_list" => [],
        "rapports_list" => [],
        "alertes_list" => [],
        "alertes_summary" => $alertesSummary,
        "charts" => $charts,
        "filters" => getOperationalFilters($conn_dw),
        "echantillons" => $echantillonsSummary,
        "fiches_laborantin" => $charts["fiches_paillasse_laborantin"],
        "fiches_secteur" => [],
        "demandes_secteur" => $charts["volume_delai_secteur"],
        "affectations" => $affectations,
        "delai_moyen" => $delaiMoyen,
        "rapports_secteur" => [],
        "total_demandes" => normalizeInt($kpis["total_demandes"] ?? 0),
        "demandes_dates" => [],
        "statuts_demandes" => $charts["statuts_demandes"]
    ];
}


/*
|--------------------------------------------------------------------------
| JSON cache for dashboard responses
|--------------------------------------------------------------------------
| This cache does not modify the DW. It only stores already-built API payloads
| in back_end/cache/*.json. Each action creates its own cache automatically
| the first time it is called, then reuses it on later calls.
*/

function getDashboardCacheDir() {
    return __DIR__ . DIRECTORY_SEPARATOR . "cache";
}

function getDashboardCacheFile($fileName) {
    return getDashboardCacheDir() . DIRECTORY_SEPARATOR . $fileName;
}

function readDashboardCachePayload($fileName) {
    $file = getDashboardCacheFile($fileName);
    if (!is_file($file) || !is_readable($file)) {
        return null;
    }

    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === "") {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return null;
    }

    $data["cache_status"] = "hit";
    $data["cache_file"] = $fileName;
    return $data;
}

function writeDashboardCachePayload($fileName, $data) {
    $dir = getDashboardCacheDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $file = getDashboardCacheFile($fileName);
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new Exception("Impossible d'encoder le cache dashboard.");
    }

    if (file_put_contents($file, $payload) === false) {
        throw new Exception("Impossible d'écrire le cache dashboard.");
    }
}

function buildCachedDashboardPayload($fileName, $forceRefresh, $builder) {
    if (!$forceRefresh) {
        $cached = readDashboardCachePayload($fileName);
        if ($cached !== null) {
            return $cached;
        }
    }

    $response = $builder();
    if (!is_array($response)) {
        throw new Exception("Réponse cache invalide.");
    }

    $response["cache_status"] = $forceRefresh ? "refreshed" : "created";
    $response["cache_generated_at"] = date("c");
    $response["cache_file"] = $fileName;

    writeDashboardCachePayload($fileName, $response);
    return $response;
}

function buildOperationnelDashboardResponseCached($conn_dw, $forceRefresh = false) {
    return buildCachedDashboardPayload(
        "dashboard_global_cache.json",
        $forceRefresh,
        function () use ($conn_dw) {
            return buildOperationnelDashboardResponse($conn_dw);
        }
    );
}

function buildOperationnelFullDashboardResponseCached($conn_dw, $forceRefresh = false) {
    return buildCachedDashboardPayload(
        "dashboard_full_cache.json",
        $forceRefresh,
        function () use ($conn_dw) {
            return buildOperationnelFullDashboardResponse($conn_dw);
        }
    );
}

function buildListDemandesResponseCached($conn_dw, $forceRefresh = false) {
    return buildCachedDashboardPayload(
        "dashboard_demandes_cache.json",
        $forceRefresh,
        function () use ($conn_dw) {
            return [
                "success" => true,
                "status" => "success",
                "data" => getDemandesOperationnelles($conn_dw)
            ];
        }
    );
}

function buildListOrdresResponseCached($conn_dw, $forceRefresh = false) {
    return buildCachedDashboardPayload(
        "dashboard_ordres_cache.json",
        $forceRefresh,
        function () use ($conn_dw) {
            return [
                "success" => true,
                "status" => "success",
                "data" => getOrdresOperationnels($conn_dw)
            ];
        }
    );
}

function buildListEchantillonsResponseCached($conn_dw, $forceRefresh = false) {
    return buildCachedDashboardPayload(
        "dashboard_echantillons_cache.json",
        $forceRefresh,
        function () use ($conn_dw) {
            return [
                "success" => true,
                "status" => "success",
                "data" => getEchantillonsOperationnels($conn_dw)
            ];
        }
    );
}

function buildListRapportsResponseCached($conn_dw, $forceRefresh = false) {
    return buildCachedDashboardPayload(
        "dashboard_rapports_cache.json",
        $forceRefresh,
        function () use ($conn_dw) {
            return [
                "success" => true,
                "status" => "success",
                "data" => getRapportsOperationnels($conn_dw)
            ];
        }
    );
}

function buildListAlertesResponseCached($conn_dw, $forceRefresh = false) {
    return buildCachedDashboardPayload(
        "dashboard_alertes_cache.json",
        $forceRefresh,
        function () use ($conn_dw) {
            $alertes = getAlertesOperationnelles($conn_dw);
            return [
                "success" => true,
                "status" => "success",
                "data" => $alertes,
                "summary" => buildAlertesSummary($alertes)
            ];
        }
    );
}

function buildAllDashboardCaches($conn_dw) {
    return [
        "success" => true,
        "status" => "success",
        "message" => "Tous les caches dashboard ont été générés.",
        "generated_at" => date("c"),
        "global" => buildOperationnelDashboardResponseCached($conn_dw, true)["cache_file"] ?? "dashboard_global_cache.json",
        "demandes" => buildListDemandesResponseCached($conn_dw, true)["cache_file"] ?? "dashboard_demandes_cache.json",
        "ordres" => buildListOrdresResponseCached($conn_dw, true)["cache_file"] ?? "dashboard_ordres_cache.json",
        "echantillons" => buildListEchantillonsResponseCached($conn_dw, true)["cache_file"] ?? "dashboard_echantillons_cache.json",
        "rapports" => buildListRapportsResponseCached($conn_dw, true)["cache_file"] ?? "dashboard_rapports_cache.json",
        "alertes" => buildListAlertesResponseCached($conn_dw, true)["cache_file"] ?? "dashboard_alertes_cache.json",
        "full" => buildOperationnelFullDashboardResponseCached($conn_dw, true)["cache_file"] ?? "dashboard_full_cache.json"
    ];
}

/*
|--------------------------------------------------------------------------
| Output
|--------------------------------------------------------------------------
*/

$response = [];

if (isset($conn_dw) && !$conn_dw->connect_error) {
    try {
        $action = isset($_GET["action"]) ? trim((string)$_GET["action"]) : "";

        if ($action === "list_demandes") {
            $response = buildListDemandesResponseCached($conn_dw, false);
        } elseif ($action === "refresh_dashboard_cache") {
            $response = buildOperationnelDashboardResponseCached($conn_dw, true);
        } elseif ($action === "refresh_all_dashboard_cache") {
            $response = buildAllDashboardCaches($conn_dw);
        } elseif ($action === "operationnel_dashboard" || $action === "") {
            $response = buildOperationnelDashboardResponseCached($conn_dw, false);
        } elseif ($action === "operationnel_full") {
            $response = buildOperationnelFullDashboardResponseCached($conn_dw, false);
        } elseif ($action === "list_ordres") {
            $response = buildListOrdresResponseCached($conn_dw, false);
        } elseif ($action === "list_echantillons") {
            $response = buildListEchantillonsResponseCached($conn_dw, false);
        } elseif ($action === "list_rapports") {
            $response = buildListRapportsResponseCached($conn_dw, false);
        } elseif ($action === "list_alertes") {
            $response = buildListAlertesResponseCached($conn_dw, false);
        } else {
            $response = [
                "success" => false,
                "status" => "error",
                "message" => "Action API inconnue."
            ];
        }
    } catch (Exception $e) {
        $response = [
            "success" => false,
            "status" => "error",
            "message" => $e->getMessage()
        ];
    }
} else {
    $response = [
        "success" => false,
        "status" => "error",
        "message" => "Erreur de connexion à la base de données DW."
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;


