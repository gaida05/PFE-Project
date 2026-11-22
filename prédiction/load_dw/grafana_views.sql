DROP VIEW IF EXISTS vue_pred_section7_importance_familles_top3;
DROP VIEW IF EXISTS vue_pred_section7_importance_analyses_top;
DROP VIEW IF EXISTS vue_pred_section7_importance_variables_dashboard;
DROP VIEW IF EXISTS vue_pred_section7_importance_variables_top3_raw;
DROP VIEW IF EXISTS vue_pred_section7_top3_modeles;
DROP VIEW IF EXISTS vue_pred_kpis_en_cours;
DROP VIEW IF EXISTS vue_pred_demandes_risque_en_cours;
DROP VIEW IF EXISTS vue_pred_axes_sensibles_top;
DROP VIEW IF EXISTS vue_pred_analyses_top_sensibles;
DROP VIEW IF EXISTS vue_pred_analyses_top_volume;
DROP VIEW IF EXISTS vue_pred_demandes_top_erreurs;
DROP VIEW IF EXISTS vue_pred_kpis_principaux;
DROP VIEW IF EXISTS vue_dash_pred_elements_favorables_sensibles;
DROP VIEW IF EXISTS vue_dash_pred_importance_familles;
DROP VIEW IF EXISTS vue_dash_pred_importance_variables;
DROP VIEW IF EXISTS vue_dash_pred_meilleurs_modeles_etapes;
DROP VIEW IF EXISTS vue_dash_pred_temps_jour;
DROP VIEW IF EXISTS vue_dash_pred_temps_mois;
DROP VIEW IF EXISTS vue_dash_pred_clients_histo;
DROP VIEW IF EXISTS vue_dash_pred_labo_histo;
DROP VIEW IF EXISTS vue_dash_pred_produits_histo;
DROP VIEW IF EXISTS vue_dash_pred_secteurs_histo;
DROP VIEW IF EXISTS vue_dash_pred_analyses_detail;
DROP VIEW IF EXISTS vue_dash_pred_analyses_histo;
DROP VIEW IF EXISTS vue_dash_pred_erreurs_detail;
DROP VIEW IF EXISTS vue_dash_pred_synthese_erreurs;
DROP VIEW IF EXISTS vue_dash_pred_base_historique;

CREATE OR REPLACE VIEW vue_dash_pred_base_historique AS
SELECT
    x.id_demande_analyse_sk,
    x.id_demande_analyse_bk,
    x.ref_demande,

    x.date_demande,
    YEAR(x.date_demande) AS annee_demande,
    MONTH(x.date_demande) AS mois_demande,

    CASE MONTH(x.date_demande)
        WHEN 1 THEN 'Janvier'
        WHEN 2 THEN 'Fevrier'
        WHEN 3 THEN 'Mars'
        WHEN 4 THEN 'Avril'
        WHEN 5 THEN 'Mai'
        WHEN 6 THEN 'Juin'
        WHEN 7 THEN 'Juillet'
        WHEN 8 THEN 'Aout'
        WHEN 9 THEN 'Septembre'
        WHEN 10 THEN 'Octobre'
        WHEN 11 THEN 'Novembre'
        WHEN 12 THEN 'Decembre'
        ELSE 'Inconnu'
    END AS nom_mois,

    QUARTER(x.date_demande) AS trimestre_demande,

    WEEKDAY(x.date_demande) AS jour_semaine_num,

    CASE WEEKDAY(x.date_demande)
        WHEN 0 THEN 'Lundi'
        WHEN 1 THEN 'Mardi'
        WHEN 2 THEN 'Mercredi'
        WHEN 3 THEN 'Jeudi'
        WHEN 4 THEN 'Vendredi'
        WHEN 5 THEN 'Samedi'
        WHEN 6 THEN 'Dimanche'
        ELSE 'Inconnu'
    END AS jour_semaine,

    x.id_client_sk,
    x.client_name,

    x.id_secteur_sk,
    x.nom_secteur,

    x.id_modele_sk,
    x.model_name,
    x.variant,
    x.stage_prediction,
    x.model_family,
    x.holdout_mae,
    x.holdout_rmse,
    x.holdout_r2,

    x.delai_predit_jours,
    GREATEST(x.delai_predit_jours, 0) AS delai_predit_corrige,
    x.delai_reel_jours,

    x.erreur_absolue,
    x.erreur_signee,

    ABS(GREATEST(x.delai_predit_jours, 0) - x.delai_reel_jours) AS erreur_absolue_corrigee,
    GREATEST(x.delai_predit_jours, 0) - x.delai_reel_jours AS erreur_signee_corrigee,

    x.retard_predit_flag,
    x.retard_reel_flag,

    x.type_erreur,
    x.niveau_erreur,
    x.diagnostic_retard,

    COALESCE(prod.type_echantillon, 'Type echantillon non renseigne') AS type_echantillon,
    COALESCE(prod.nom_produit, 'Produit non renseigne') AS nom_produit,
    COALESCE(prod.code_produit_demandeur, 'Code produit non renseigne') AS code_produit_demandeur,
    COALESCE(prod.ref_labo, 'Ref labo non renseignee') AS ref_labo,
    COALESCE(prod.code_ref_labo, 'Code ref labo non renseigne') AS code_ref_labo,

    COALESCE(ana.codes_analyse, 'Analyse non renseignee') AS codes_analyse,
    COALESCE(ana.noms_analyse, 'Analyse non renseignee') AS noms_analyse

FROM (
    SELECT
        fp.id_demande_analyse_sk,
        dda.id_demande_analyse_bk,
        dda.ref_demande,

        dt_dem.date_complete AS date_demande,

        fp.id_client_sk,
        dc.client_name,

        fp.id_secteur_sk,
        ds.nom_secteur,

        fp.id_modele_sk,
        dm.model_name,
        dm.variant,
        dm.stage_prediction,
        dm.model_family,
        dm.holdout_mae,
        dm.holdout_rmse,
        dm.holdout_r2,

        fp.delai_predit_jours,
        fp.delai_reel_jours,
        fp.erreur_absolue,
        fp.erreur_signee,
        fp.retard_predit_flag,
        fp.retard_reel_flag,
        fp.date_chargement,

        CASE
            WHEN fp.erreur_signee < 0 THEN 'Sous-estimation'
            WHEN fp.erreur_signee > 0 THEN 'Surestimation'
            ELSE 'Exact'
        END AS type_erreur,

        CASE
            WHEN fp.erreur_absolue >= 10 THEN 'Erreur elevee'
            WHEN fp.erreur_absolue >= 7 THEN 'Erreur moyenne'
            ELSE 'Erreur faible'
        END AS niveau_erreur,

        CASE
            WHEN fp.retard_predit_flag = 1 AND fp.retard_reel_flag = 1 THEN 'Retard detecte'
            WHEN fp.retard_predit_flag = 0 AND fp.retard_reel_flag = 1 THEN 'Retard non detecte'
            WHEN fp.retard_predit_flag = 1 AND fp.retard_reel_flag = 0 THEN 'Fausse alerte'
            ELSE 'Normal'
        END AS diagnostic_retard,

        ROW_NUMBER() OVER (
            PARTITION BY fp.id_demande_analyse_sk
            ORDER BY
                COALESCE(dm.holdout_mae, 999999999) ASC,
                COALESCE(dm.holdout_rmse, 999999999) ASC,
                COALESCE(dm.holdout_r2, -999999999) DESC,
                fp.date_chargement DESC,
                fp.id_modele_sk ASC
        ) AS rn

    FROM fait_prediction fp

    LEFT JOIN dim_demande_analyse dda
        ON dda.id_demande_analyse_sk = fp.id_demande_analyse_sk

    LEFT JOIN fait_demande_analyse fda
        ON fda.id_demande_analyse_sk = fp.id_demande_analyse_sk

    LEFT JOIN dim_temps dt_dem
        ON dt_dem.id_temps = fda.id_temps_demande

    LEFT JOIN dim_client dc
        ON dc.id_client_sk = fp.id_client_sk

    LEFT JOIN dim_secteur ds
        ON ds.id_secteur_sk = fp.id_secteur_sk

    LEFT JOIN dim_modele dm
        ON dm.id_modele_sk = fp.id_modele_sk

    WHERE fp.type_prediction = 'HOLDOUT'
      AND fp.delai_reel_jours IS NOT NULL
      AND fp.erreur_absolue IS NOT NULL
      AND dt_dem.date_complete IS NOT NULL
) x

LEFT JOIN (
    SELECT
        foa.id_demande_analyse_sk,

        GROUP_CONCAT(DISTINCT de.type_echantillon ORDER BY de.type_echantillon SEPARATOR ' | ') AS type_echantillon,
        GROUP_CONCAT(DISTINCT de.nom_produit ORDER BY de.nom_produit SEPARATOR ' | ') AS nom_produit,
        GROUP_CONCAT(DISTINCT de.code_produit_demandeur ORDER BY de.code_produit_demandeur SEPARATOR ' | ') AS code_produit_demandeur,
        GROUP_CONCAT(DISTINCT de.ref_labo ORDER BY de.ref_labo SEPARATOR ' | ') AS ref_labo,

        GROUP_CONCAT(
            DISTINCT
            CASE
                WHEN de.ref_labo IS NULL OR TRIM(de.ref_labo) = '' THEN NULL
                WHEN LOCATE('*', de.ref_labo) > 0 THEN TRIM(SUBSTRING_INDEX(de.ref_labo, '*', -1))
                ELSE TRIM(de.ref_labo)
            END
            ORDER BY de.ref_labo
            SEPARATOR ' | '
        ) AS code_ref_labo

    FROM fait_ordre_analyse foa

    LEFT JOIN dim_echantillon de
        ON de.id_echantillon_sk = foa.id_echantillon_sk

    GROUP BY foa.id_demande_analyse_sk
) prod
    ON prod.id_demande_analyse_sk = x.id_demande_analyse_sk

LEFT JOIN (
    SELECT
        foa.id_demande_analyse_sk,
        GROUP_CONCAT(DISTINCT da.code_analyse ORDER BY da.code_analyse SEPARATOR ' | ') AS codes_analyse,
        GROUP_CONCAT(DISTINCT da.nom_analyse ORDER BY da.nom_analyse SEPARATOR ' | ') AS noms_analyse

    FROM fait_ordre_analyse foa

    LEFT JOIN dim_analyse da
        ON da.id_analyse_sk = foa.id_analyse_sk

    GROUP BY foa.id_demande_analyse_sk
) ana
    ON ana.id_demande_analyse_sk = x.id_demande_analyse_sk

WHERE x.rn = 1;

CREATE OR REPLACE VIEW vue_dash_pred_synthese_erreurs AS
SELECT
    COUNT(*) AS nb_demandes_historiques,

    COUNT(DISTINCT id_client_sk) AS nb_clients,
    COUNT(DISTINCT id_secteur_sk) AS nb_secteurs,
    COUNT(DISTINCT nom_produit) AS nb_produits,
    COUNT(DISTINCT ref_labo) AS nb_refs_labo,

    ROUND(AVG(delai_reel_jours), 2) AS delai_reel_moyen,
    ROUND(AVG(delai_predit_corrige), 2) AS delai_predit_moyen,

    ROUND(AVG(erreur_absolue), 2) AS erreur_moyenne,
    ROUND(AVG(erreur_absolue_corrigee), 2) AS erreur_moyenne_corrigee,

    MAX(erreur_absolue) AS erreur_max,

    SUM(CASE WHEN erreur_signee < 0 THEN 1 ELSE 0 END) AS nb_sous_estimations,
    SUM(CASE WHEN erreur_signee > 0 THEN 1 ELSE 0 END) AS nb_surestimations,

    SUM(CASE WHEN retard_reel_flag = 1 THEN 1 ELSE 0 END) AS nb_retards_reels,
    SUM(CASE WHEN retard_predit_flag = 1 THEN 1 ELSE 0 END) AS nb_retards_predits,

    SUM(CASE WHEN retard_predit_flag = 0 AND retard_reel_flag = 1 THEN 1 ELSE 0 END) AS nb_retards_non_detectes,
    SUM(CASE WHEN retard_predit_flag = 1 AND retard_reel_flag = 0 THEN 1 ELSE 0 END) AS nb_fausses_alertes,

    ROUND(
        100 * SUM(CASE WHEN retard_reel_flag = 1 THEN 1 ELSE 0 END)
        / NULLIF(COUNT(*), 0),
        2
    ) AS taux_retard_reel,

    ROUND(
        100 * SUM(CASE WHEN erreur_absolue >= 15 THEN 1 ELSE 0 END)
        / NULLIF(COUNT(*), 0),
        2
    ) AS taux_erreur_elevee

FROM vue_dash_pred_base_historique;

CREATE OR REPLACE VIEW vue_dash_pred_erreurs_detail AS
SELECT
    id_demande_analyse_sk,
    id_demande_analyse_bk AS id_demande,
    ref_demande,

    date_demande,
    annee_demande,
    mois_demande,
    nom_mois,
    trimestre_demande,
    jour_semaine_num,
    jour_semaine,

    client_name,
    nom_secteur,
    nom_produit,
    type_echantillon,
    ref_labo,
    code_ref_labo,
    codes_analyse,
    noms_analyse,

    id_modele_sk,
    model_name,
    variant,
    stage_prediction,
    model_family,

    delai_reel_jours,
    delai_predit_jours,
    delai_predit_corrige,

    erreur_absolue,
    erreur_signee,
    erreur_absolue_corrigee,
    erreur_signee_corrigee,

    type_erreur,
    niveau_erreur,
    diagnostic_retard,

    retard_reel_flag,
    retard_predit_flag,

    CASE
        WHEN erreur_absolue >= 10 THEN 'Elevee'
        WHEN erreur_absolue >= 7 THEN 'Moyenne'
        ELSE 'Faible'
    END AS criticite_erreur

FROM vue_dash_pred_base_historique;

CREATE OR REPLACE VIEW vue_dash_pred_analyses_histo AS
SELECT
    z.*,

    ROUND(
        (z.nb_retards_reels * 5)
        + (z.nb_retards_non_detectes * 4)
        + (z.nb_sous_estimations * 3)
        + (z.nb_demandes_historiques * 0.5)
        + z.erreur_moyenne
        + (z.delai_reel_moyen * 0.5),
        2
    ) AS score_criticite_analyse,

    CASE
        WHEN (
            (z.nb_retards_reels * 5)
            + (z.nb_retards_non_detectes * 4)
            + (z.nb_sous_estimations * 3)
            + (z.nb_demandes_historiques * 0.5)
            + z.erreur_moyenne
            + (z.delai_reel_moyen * 0.5)
        ) >= 60 THEN 'Critique'

        WHEN (
            (z.nb_retards_reels * 5)
            + (z.nb_retards_non_detectes * 4)
            + (z.nb_sous_estimations * 3)
            + (z.nb_demandes_historiques * 0.5)
            + z.erreur_moyenne
            + (z.delai_reel_moyen * 0.5)
        ) >= 35 THEN 'A surveiller'

        ELSE 'Normal'
    END AS niveau_criticite_analyse,

    CASE
        WHEN z.nb_demandes_historiques >= 2 THEN 1
        ELSE 0
    END AS volume_fiable_flag

FROM (
    SELECT
        da.id_analyse_sk,
        COALESCE(NULLIF(TRIM(da.code_analyse), ''), 'Sans code') AS code_analyse,
        COALESCE(NULLIF(TRIM(da.nom_analyse), ''), 'Analyse non renseignee') AS nom_analyse,

        COUNT(DISTINCT base.id_demande_analyse_sk) AS nb_demandes_historiques,
        COUNT(DISTINCT foa.reference_ordre) AS nb_ordres_historiques,

        ROUND(AVG(base.delai_reel_jours), 2) AS delai_reel_moyen,
        MAX(base.delai_reel_jours) AS delai_reel_max,

        ROUND(AVG(base.delai_predit_corrige), 2) AS delai_predit_moyen,
        ROUND(AVG(base.erreur_absolue), 2) AS erreur_moyenne,
        MAX(base.erreur_absolue) AS erreur_max,

        COUNT(DISTINCT CASE WHEN base.retard_reel_flag = 1 THEN base.id_demande_analyse_sk END) AS nb_retards_reels,
        COUNT(DISTINCT CASE WHEN base.retard_predit_flag = 1 THEN base.id_demande_analyse_sk END) AS nb_retards_predits,

        COUNT(DISTINCT CASE
            WHEN base.retard_predit_flag = 0 AND base.retard_reel_flag = 1
            THEN base.id_demande_analyse_sk
        END) AS nb_retards_non_detectes,

        COUNT(DISTINCT CASE
            WHEN base.retard_predit_flag = 1 AND base.retard_reel_flag = 0
            THEN base.id_demande_analyse_sk
        END) AS nb_fausses_alertes,

        COUNT(DISTINCT CASE WHEN base.erreur_signee < 0 THEN base.id_demande_analyse_sk END) AS nb_sous_estimations,
        COUNT(DISTINCT CASE WHEN base.erreur_signee > 0 THEN base.id_demande_analyse_sk END) AS nb_surestimations,

        ROUND(
            100 * COUNT(DISTINCT CASE WHEN base.retard_reel_flag = 1 THEN base.id_demande_analyse_sk END)
            / NULLIF(COUNT(DISTINCT base.id_demande_analyse_sk), 0),
            2
        ) AS taux_retard_reel,

        ROUND(
            100 * COUNT(DISTINCT CASE WHEN base.erreur_absolue >= 15 THEN base.id_demande_analyse_sk END)
            / NULLIF(COUNT(DISTINCT base.id_demande_analyse_sk), 0),
            2
        ) AS taux_erreur_elevee

    FROM vue_dash_pred_base_historique base

    INNER JOIN fait_ordre_analyse foa
        ON foa.id_demande_analyse_sk = base.id_demande_analyse_sk

    INNER JOIN dim_analyse da
        ON da.id_analyse_sk = foa.id_analyse_sk

    GROUP BY
        da.id_analyse_sk,
        COALESCE(NULLIF(TRIM(da.code_analyse), ''), 'Sans code'),
        COALESCE(NULLIF(TRIM(da.nom_analyse), ''), 'Analyse non renseignee')
) z;



CREATE OR REPLACE VIEW vue_dash_pred_analyses_detail AS
SELECT
    da.id_analyse_sk,
    COALESCE(NULLIF(TRIM(da.code_analyse), ''), 'Sans code') AS code_analyse,
    COALESCE(NULLIF(TRIM(da.nom_analyse), ''), 'Analyse non renseignee') AS nom_analyse,

    base.id_demande_analyse_sk,
    base.id_demande_analyse_bk AS id_demande,
    base.ref_demande,

    base.date_demande,
    base.client_name,
    base.nom_secteur,
    base.nom_produit,
    base.type_echantillon,

    base.delai_reel_jours,
    base.delai_predit_corrige,
    base.erreur_absolue,
    base.erreur_signee,

    base.type_erreur,
    base.niveau_erreur,
    base.diagnostic_retard,

    base.retard_reel_flag,
    base.retard_predit_flag,

    foa.reference_ordre,
    foa.duree_analyse_jours,
    foa.delai_prise_en_charge_jours

FROM vue_dash_pred_base_historique base

INNER JOIN fait_ordre_analyse foa
    ON foa.id_demande_analyse_sk = base.id_demande_analyse_sk

INNER JOIN dim_analyse da
    ON da.id_analyse_sk = foa.id_analyse_sk;



CREATE OR REPLACE VIEW vue_dash_pred_secteurs_histo AS
SELECT
    z.*,

    ROUND(
        (z.nb_retards_reels * 4)
        + (z.nb_retards_non_detectes * 4)
        + (z.nb_sous_estimations * 2)
        + (z.nb_demandes_historiques * 0.5)
        + z.erreur_moyenne
        + (z.delai_reel_moyen * 0.5),
        2
    ) AS score_sensibilite_secteur,

    ROUND(
        LEAST(
            100,
            GREATEST(
                0,
                100 - (
                    z.erreur_moyenne * 2
                    + z.delai_reel_moyen * 0.3
                    + z.taux_retard_reel * 0.7
                    + z.nb_retards_non_detectes * 3
                )
            )
        ),
        2
    ) AS score_favorabilite_secteur,

    CASE
        WHEN (
            (z.nb_retards_reels * 4)
            + (z.nb_retards_non_detectes * 4)
            + (z.nb_sous_estimations * 2)
            + (z.nb_demandes_historiques * 0.5)
            + z.erreur_moyenne
            + (z.delai_reel_moyen * 0.5)
        ) >= 60 THEN 'Sensible'
        WHEN (
            (z.nb_retards_reels * 4)
            + (z.nb_retards_non_detectes * 4)
            + (z.nb_sous_estimations * 2)
            + (z.nb_demandes_historiques * 0.5)
            + z.erreur_moyenne
            + (z.delai_reel_moyen * 0.5)
        ) >= 35 THEN 'A surveiller'
        ELSE 'Stable'
    END AS niveau_sensibilite_secteur,

    CASE
        WHEN z.nb_demandes_historiques >= 2 THEN 1
        ELSE 0
    END AS volume_fiable_flag

FROM (
    SELECT
        id_secteur_sk,
        COALESCE(NULLIF(TRIM(nom_secteur), ''), 'Secteur non renseigne') AS nom_secteur,

        COUNT(DISTINCT id_demande_analyse_sk) AS nb_demandes_historiques,

        ROUND(AVG(delai_reel_jours), 2) AS delai_reel_moyen,
        MAX(delai_reel_jours) AS delai_reel_max,

        ROUND(AVG(delai_predit_corrige), 2) AS delai_predit_moyen,
        ROUND(AVG(erreur_absolue), 2) AS erreur_moyenne,
        MAX(erreur_absolue) AS erreur_max,

        COUNT(DISTINCT CASE WHEN retard_reel_flag = 1 THEN id_demande_analyse_sk END) AS nb_retards_reels,
        COUNT(DISTINCT CASE WHEN retard_predit_flag = 0 AND retard_reel_flag = 1 THEN id_demande_analyse_sk END) AS nb_retards_non_detectes,
        COUNT(DISTINCT CASE WHEN retard_predit_flag = 1 AND retard_reel_flag = 0 THEN id_demande_analyse_sk END) AS nb_fausses_alertes,

        COUNT(DISTINCT CASE WHEN erreur_signee < 0 THEN id_demande_analyse_sk END) AS nb_sous_estimations,
        COUNT(DISTINCT CASE WHEN erreur_signee > 0 THEN id_demande_analyse_sk END) AS nb_surestimations,

        ROUND(
            100 * COUNT(DISTINCT CASE WHEN retard_reel_flag = 1 THEN id_demande_analyse_sk END)
            / NULLIF(COUNT(DISTINCT id_demande_analyse_sk), 0),
            2
        ) AS taux_retard_reel

    FROM vue_dash_pred_base_historique

    GROUP BY
        id_secteur_sk,
        COALESCE(NULLIF(TRIM(nom_secteur), ''), 'Secteur non renseigne')
) z;




CREATE OR REPLACE VIEW vue_dash_pred_produits_histo AS
SELECT
    z.*,

    ROUND(
        (z.nb_retards_reels * 4)
        + (z.nb_retards_non_detectes * 4)
        + (z.nb_sous_estimations * 2)
        + (z.nb_demandes_historiques * 0.5)
        + z.erreur_moyenne
        + (z.delai_reel_moyen * 0.5),
        2
    ) AS score_sensibilite_produit,

    ROUND(
        LEAST(
            100,
            GREATEST(
                0,
                100 - (
                    z.erreur_moyenne * 2
                    + z.delai_reel_moyen * 0.3
                    + z.taux_retard_reel * 0.7
                    + z.nb_retards_non_detectes * 3
                )
            )
        ),
        2
    ) AS score_favorabilite_produit,

    CASE
        WHEN (
            (z.nb_retards_reels * 4)
            + (z.nb_retards_non_detectes * 4)
            + (z.nb_sous_estimations * 2)
            + (z.nb_demandes_historiques * 0.5)
            + z.erreur_moyenne
            + (z.delai_reel_moyen * 0.5)
        ) >= 60 THEN 'Sensible'
        WHEN (
            (z.nb_retards_reels * 4)
            + (z.nb_retards_non_detectes * 4)
            + (z.nb_sous_estimations * 2)
            + (z.nb_demandes_historiques * 0.5)
            + z.erreur_moyenne
            + (z.delai_reel_moyen * 0.5)
        ) >= 35 THEN 'A surveiller'
        ELSE 'Stable'
    END AS niveau_sensibilite_produit,

    CASE
        WHEN z.nb_demandes_historiques >= 2 THEN 1
        ELSE 0
    END AS volume_fiable_flag

FROM (
    SELECT
        nom_produit,
        type_echantillon,

        COUNT(DISTINCT id_demande_analyse_sk) AS nb_demandes_historiques,

        ROUND(AVG(delai_reel_jours), 2) AS delai_reel_moyen,
        ROUND(AVG(delai_predit_corrige), 2) AS delai_predit_moyen,
        ROUND(AVG(erreur_absolue), 2) AS erreur_moyenne,
        MAX(erreur_absolue) AS erreur_max,

        COUNT(DISTINCT CASE WHEN retard_reel_flag = 1 THEN id_demande_analyse_sk END) AS nb_retards_reels,
        COUNT(DISTINCT CASE WHEN retard_predit_flag = 0 AND retard_reel_flag = 1 THEN id_demande_analyse_sk END) AS nb_retards_non_detectes,
        COUNT(DISTINCT CASE WHEN retard_predit_flag = 1 AND retard_reel_flag = 0 THEN id_demande_analyse_sk END) AS nb_fausses_alertes,

        COUNT(DISTINCT CASE WHEN erreur_signee < 0 THEN id_demande_analyse_sk END) AS nb_sous_estimations,
        COUNT(DISTINCT CASE WHEN erreur_signee > 0 THEN id_demande_analyse_sk END) AS nb_surestimations,

        ROUND(
            100 * COUNT(DISTINCT CASE WHEN retard_reel_flag = 1 THEN id_demande_analyse_sk END)
            / NULLIF(COUNT(DISTINCT id_demande_analyse_sk), 0),
            2
        ) AS taux_retard_reel

    FROM vue_dash_pred_base_historique

    GROUP BY
        nom_produit,
        type_echantillon
) z;


CREATE OR REPLACE VIEW vue_dash_pred_labo_histo AS
SELECT
    z.*,

    ROUND(
        (z.nb_retards_reels * 4)
        + (z.nb_retards_non_detectes * 4)
        + (z.nb_sous_estimations * 2)
        + (z.nb_demandes_historiques * 0.5)
        + z.erreur_moyenne
        + (z.delai_reel_moyen * 0.5),
        2
    ) AS score_sensibilite_labo,

    ROUND(
        LEAST(
            100,
            GREATEST(
                0,
                100 - (
                    z.erreur_moyenne * 2
                    + z.delai_reel_moyen * 0.3
                    + z.taux_retard_reel * 0.7
                    + z.nb_retards_non_detectes * 3
                )
            )
        ),
        2
    ) AS score_favorabilite_labo,

    CASE
        WHEN (
            (z.nb_retards_reels * 4)
            + (z.nb_retards_non_detectes * 4)
            + (z.nb_sous_estimations * 2)
            + (z.nb_demandes_historiques * 0.5)
            + z.erreur_moyenne
            + (z.delai_reel_moyen * 0.5)
        ) >= 60 THEN 'Sensible'
        WHEN (
            (z.nb_retards_reels * 4)
            + (z.nb_retards_non_detectes * 4)
            + (z.nb_sous_estimations * 2)
            + (z.nb_demandes_historiques * 0.5)
            + z.erreur_moyenne
            + (z.delai_reel_moyen * 0.5)
        ) >= 35 THEN 'A surveiller'
        ELSE 'Stable'
    END AS niveau_sensibilite_labo,

    CASE
        WHEN z.nb_demandes_historiques >= 2 THEN 1
        ELSE 0
    END AS volume_fiable_flag

FROM (
    SELECT
        ref_labo,
        code_ref_labo,

        COUNT(DISTINCT id_demande_analyse_sk) AS nb_demandes_historiques,

        ROUND(AVG(delai_reel_jours), 2) AS delai_reel_moyen,
        ROUND(AVG(delai_predit_corrige), 2) AS delai_predit_moyen,
        ROUND(AVG(erreur_absolue), 2) AS erreur_moyenne,
        MAX(erreur_absolue) AS erreur_max,

        COUNT(DISTINCT CASE WHEN retard_reel_flag = 1 THEN id_demande_analyse_sk END) AS nb_retards_reels,
        COUNT(DISTINCT CASE WHEN retard_predit_flag = 0 AND retard_reel_flag = 1 THEN id_demande_analyse_sk END) AS nb_retards_non_detectes,
        COUNT(DISTINCT CASE WHEN retard_predit_flag = 1 AND retard_reel_flag = 0 THEN id_demande_analyse_sk END) AS nb_fausses_alertes,

        COUNT(DISTINCT CASE WHEN erreur_signee < 0 THEN id_demande_analyse_sk END) AS nb_sous_estimations,
        COUNT(DISTINCT CASE WHEN erreur_signee > 0 THEN id_demande_analyse_sk END) AS nb_surestimations,

        ROUND(
            100 * COUNT(DISTINCT CASE WHEN retard_reel_flag = 1 THEN id_demande_analyse_sk END)
            / NULLIF(COUNT(DISTINCT id_demande_analyse_sk), 0),
            2
        ) AS taux_retard_reel

    FROM vue_dash_pred_base_historique

    GROUP BY
        ref_labo,
        code_ref_labo
) z;


CREATE OR REPLACE VIEW vue_dash_pred_clients_histo AS
SELECT
    z.*,

    ROUND(
        (z.nb_retards_reels * 4)
        + (z.nb_retards_non_detectes * 4)
        + (z.nb_sous_estimations * 2)
        + (z.nb_demandes_historiques * 0.5)
        + z.erreur_moyenne
        + (z.delai_reel_moyen * 0.5),
        2
    ) AS score_sensibilite_client,

    ROUND(
        LEAST(
            100,
            GREATEST(
                0,
                100 - (
                    z.erreur_moyenne * 2
                    + z.delai_reel_moyen * 0.3
                    + z.taux_retard_reel * 0.7
                    + z.nb_retards_non_detectes * 3
                )
            )
        ),
        2
    ) AS score_favorabilite_client,

    CASE
        WHEN (
            (z.nb_retards_reels * 4)
            + (z.nb_retards_non_detectes * 4)
            + (z.nb_sous_estimations * 2)
            + (z.nb_demandes_historiques * 0.5)
            + z.erreur_moyenne
            + (z.delai_reel_moyen * 0.5)
        ) >= 60 THEN 'Sensible'
        WHEN (
            (z.nb_retards_reels * 4)
            + (z.nb_retards_non_detectes * 4)
            + (z.nb_sous_estimations * 2)
            + (z.nb_demandes_historiques * 0.5)
            + z.erreur_moyenne
            + (z.delai_reel_moyen * 0.5)
        ) >= 35 THEN 'A surveiller'
        ELSE 'Stable'
    END AS niveau_sensibilite_client,

    CASE
        WHEN z.nb_demandes_historiques >= 2 THEN 1
        ELSE 0
    END AS volume_fiable_flag

FROM (
    SELECT
        id_client_sk,
        COALESCE(NULLIF(TRIM(client_name), ''), 'Client non renseigne') AS client_name,

        COUNT(DISTINCT id_demande_analyse_sk) AS nb_demandes_historiques,

        ROUND(AVG(delai_reel_jours), 2) AS delai_reel_moyen,
        ROUND(AVG(delai_predit_corrige), 2) AS delai_predit_moyen,
        ROUND(AVG(erreur_absolue), 2) AS erreur_moyenne,
        MAX(erreur_absolue) AS erreur_max,

        COUNT(DISTINCT CASE WHEN retard_reel_flag = 1 THEN id_demande_analyse_sk END) AS nb_retards_reels,
        COUNT(DISTINCT CASE WHEN retard_predit_flag = 0 AND retard_reel_flag = 1 THEN id_demande_analyse_sk END) AS nb_retards_non_detectes,
        COUNT(DISTINCT CASE WHEN retard_predit_flag = 1 AND retard_reel_flag = 0 THEN id_demande_analyse_sk END) AS nb_fausses_alertes,

        COUNT(DISTINCT CASE WHEN erreur_signee < 0 THEN id_demande_analyse_sk END) AS nb_sous_estimations,
        COUNT(DISTINCT CASE WHEN erreur_signee > 0 THEN id_demande_analyse_sk END) AS nb_surestimations,

        ROUND(
            100 * COUNT(DISTINCT CASE WHEN retard_reel_flag = 1 THEN id_demande_analyse_sk END)
            / NULLIF(COUNT(DISTINCT id_demande_analyse_sk), 0),
            2
        ) AS taux_retard_reel

    FROM vue_dash_pred_base_historique

    GROUP BY
        id_client_sk,
        COALESCE(NULLIF(TRIM(client_name), ''), 'Client non renseigne')
) z;


CREATE OR REPLACE VIEW vue_dash_pred_temps_mois AS
SELECT
    z.*,

    ROUND(
        (z.nb_retards_reels * 4)
        + (z.nb_retards_non_detectes * 4)
        + (z.nb_sous_estimations * 2)
        + z.erreur_moyenne
        + (z.delai_reel_moyen * 0.5),
        2
    ) AS score_sensibilite_mois,

    ROUND(
        LEAST(
            100,
            GREATEST(
                0,
                100 - (
                    z.erreur_moyenne * 2
                    + z.delai_reel_moyen * 0.3
                    + z.taux_retard_reel * 0.7
                    + z.nb_retards_non_detectes * 3
                )
            )
        ),
        2
    ) AS score_favorabilite_mois,

    CASE
        WHEN (
            (z.nb_retards_reels * 4)
            + (z.nb_retards_non_detectes * 4)
            + (z.nb_sous_estimations * 2)
            + z.erreur_moyenne
            + (z.delai_reel_moyen * 0.5)
        ) >= 60 THEN 'Sensible'
        WHEN (
            (z.nb_retards_reels * 4)
            + (z.nb_retards_non_detectes * 4)
            + (z.nb_sous_estimations * 2)
            + z.erreur_moyenne
            + (z.delai_reel_moyen * 0.5)
        ) >= 35 THEN 'A surveiller'
        ELSE 'Stable'
    END AS niveau_sensibilite_mois,

    CASE
        WHEN z.nb_demandes >= 2 THEN 1
        ELSE 0
    END AS volume_fiable_flag

FROM (
    SELECT
        annee_demande,
        mois_demande,
        nom_mois,

        COUNT(DISTINCT id_demande_analyse_sk) AS nb_demandes,

        ROUND(AVG(delai_reel_jours), 2) AS delai_reel_moyen,
        ROUND(AVG(delai_predit_corrige), 2) AS delai_predit_moyen,
        ROUND(AVG(erreur_absolue), 2) AS erreur_moyenne,

        COUNT(DISTINCT CASE WHEN retard_reel_flag = 1 THEN id_demande_analyse_sk END) AS nb_retards_reels,
        COUNT(DISTINCT CASE WHEN retard_predit_flag = 0 AND retard_reel_flag = 1 THEN id_demande_analyse_sk END) AS nb_retards_non_detectes,
        COUNT(DISTINCT CASE WHEN retard_predit_flag = 1 AND retard_reel_flag = 0 THEN id_demande_analyse_sk END) AS nb_fausses_alertes,

        COUNT(DISTINCT CASE WHEN erreur_signee < 0 THEN id_demande_analyse_sk END) AS nb_sous_estimations,
        COUNT(DISTINCT CASE WHEN erreur_signee > 0 THEN id_demande_analyse_sk END) AS nb_surestimations,

        ROUND(
            100 * COUNT(DISTINCT CASE WHEN retard_reel_flag = 1 THEN id_demande_analyse_sk END)
            / NULLIF(COUNT(DISTINCT id_demande_analyse_sk), 0),
            2
        ) AS taux_retard_reel

    FROM vue_dash_pred_base_historique

    GROUP BY
        annee_demande,
        mois_demande,
        nom_mois
) z;


CREATE OR REPLACE VIEW vue_dash_pred_temps_jour AS
SELECT
    z.*,

    ROUND(
        (z.nb_retards_reels * 4)
        + (z.nb_retards_non_detectes * 4)
        + (z.nb_sous_estimations * 2)
        + z.erreur_moyenne
        + (z.delai_reel_moyen * 0.5),
        2
    ) AS score_sensibilite_jour,

    ROUND(
        LEAST(
            100,
            GREATEST(
                0,
                100 - (
                    z.erreur_moyenne * 2
                    + z.delai_reel_moyen * 0.3
                    + z.taux_retard_reel * 0.7
                    + z.nb_retards_non_detectes * 3
                )
            )
        ),
        2
    ) AS score_favorabilite_jour,

    CASE
        WHEN (
            (z.nb_retards_reels * 4)
            + (z.nb_retards_non_detectes * 4)
            + (z.nb_sous_estimations * 2)
            + z.erreur_moyenne
            + (z.delai_reel_moyen * 0.5)
        ) >= 60 THEN 'Sensible'
        WHEN (
            (z.nb_retards_reels * 4)
            + (z.nb_retards_non_detectes * 4)
            + (z.nb_sous_estimations * 2)
            + z.erreur_moyenne
            + (z.delai_reel_moyen * 0.5)
        ) >= 35 THEN 'A surveiller'
        ELSE 'Stable'
    END AS niveau_sensibilite_jour,

    CASE
        WHEN z.nb_demandes >= 2 THEN 1
        ELSE 0
    END AS volume_fiable_flag

FROM (
    SELECT
        jour_semaine_num,
        jour_semaine,

        COUNT(DISTINCT id_demande_analyse_sk) AS nb_demandes,

        ROUND(AVG(delai_reel_jours), 2) AS delai_reel_moyen,
        ROUND(AVG(delai_predit_corrige), 2) AS delai_predit_moyen,
        ROUND(AVG(erreur_absolue), 2) AS erreur_moyenne,

        COUNT(DISTINCT CASE WHEN retard_reel_flag = 1 THEN id_demande_analyse_sk END) AS nb_retards_reels,
        COUNT(DISTINCT CASE WHEN retard_predit_flag = 0 AND retard_reel_flag = 1 THEN id_demande_analyse_sk END) AS nb_retards_non_detectes,
        COUNT(DISTINCT CASE WHEN retard_predit_flag = 1 AND retard_reel_flag = 0 THEN id_demande_analyse_sk END) AS nb_fausses_alertes,

        COUNT(DISTINCT CASE WHEN erreur_signee < 0 THEN id_demande_analyse_sk END) AS nb_sous_estimations,
        COUNT(DISTINCT CASE WHEN erreur_signee > 0 THEN id_demande_analyse_sk END) AS nb_surestimations,

        ROUND(
            100 * COUNT(DISTINCT CASE WHEN retard_reel_flag = 1 THEN id_demande_analyse_sk END)
            / NULLIF(COUNT(DISTINCT id_demande_analyse_sk), 0),
            2
        ) AS taux_retard_reel

    FROM vue_dash_pred_base_historique

    GROUP BY
        jour_semaine_num,
        jour_semaine
) z;


CREATE OR REPLACE VIEW vue_dash_pred_meilleurs_modeles_etapes AS
SELECT
    m.id_modele_sk,
    m.model_name,
    m.variant,
    m.stage_prediction,
    m.model_family,

    m.holdout_mae,
    m.holdout_rmse,
    m.holdout_r2,

    m.mae_mean,
    m.rmse_mean,
    m.r2_mean,

    m.date_entrainement,

    CASE
        WHEN m.holdout_mae IS NULL THEN 'Non evaluable'
        WHEN m.holdout_mae <= 3 THEN 'Tres fiable'
        WHEN m.holdout_mae <= 7 THEN 'Fiable'
        WHEN m.holdout_mae <= 15 THEN 'Moyen'
        ELSE 'Faible'
    END AS niveau_fiabilite_modele

FROM (
    SELECT
        dm.*,

        ROW_NUMBER() OVER (
            PARTITION BY dm.stage_prediction
            ORDER BY
                COALESCE(dm.holdout_mae, 999999999) ASC,
                COALESCE(dm.holdout_rmse, 999999999) ASC,
                COALESCE(dm.holdout_r2, -999999999) DESC,
                dm.id_modele_sk ASC
        ) AS rn

    FROM dim_modele dm
    WHERE dm.stage_prediction IS NOT NULL
      AND dm.holdout_mae IS NOT NULL
) m
WHERE m.rn = 1;


CREATE OR REPLACE VIEW vue_dash_pred_importance_variables AS
SELECT
    fiv.id_modele_sk,
    fiv.id_variable_sk,
    fiv.rang_variable,
    fiv.importance,
    fiv.date_entrainement,

    dv.nom_variable,
    dv.type_variable,
    dv.niveau_variable,

    CASE
        WHEN dv.nom_variable LIKE 'analyse__%' THEN 'Analyse'
        WHEN dv.nom_variable LIKE 'laborantin__%' THEN 'Laborantin'
        WHEN dv.nom_variable LIKE '%client%' THEN 'Client'
        WHEN dv.nom_variable LIKE '%secteur%' THEN 'Secteur'
        WHEN dv.nom_variable LIKE '%statut%' THEN 'Statut'
        WHEN dv.nom_variable LIKE '%echantillon%' THEN 'Echantillon'
        WHEN dv.nom_variable LIKE '%produit%' THEN 'Produit'
        WHEN dv.nom_variable LIKE '%ref_labo%' THEN 'Reference labo'
        WHEN dv.nom_variable LIKE '%code_ref_labo%' THEN 'Reference labo'
        WHEN dv.nom_variable LIKE '%delai%' THEN 'Delai intermediaire'
        WHEN dv.nom_variable LIKE '%nb_%' THEN 'Volume'
        WHEN dv.nom_variable LIKE '%mois%' THEN 'Mois'
        WHEN dv.nom_variable LIKE '%trimestre%' THEN 'Trimestre'
        WHEN dv.nom_variable LIKE '%jour%' THEN 'Jour semaine'
        ELSE 'Autre'
    END AS famille_variable,

    mm.model_name,
    mm.variant,
    mm.stage_prediction,
    mm.model_family,

    mm.holdout_mae,
    mm.holdout_rmse,
    mm.holdout_r2,
    mm.niveau_fiabilite_modele,

    CASE
        WHEN fiv.rang_variable <= 10 THEN 'Top 10'
        WHEN fiv.rang_variable <= 20 THEN 'Top 20'
        WHEN fiv.rang_variable <= 50 THEN 'Top 50'
        ELSE 'Autres'
    END AS niveau_importance,

    CASE
        WHEN fiv.importance >= 0.10 THEN 'Tres forte'
        WHEN fiv.importance >= 0.05 THEN 'Forte'
        WHEN fiv.importance >= 0.01 THEN 'Moyenne'
        ELSE 'Faible'
    END AS intensite_importance

FROM fait_importance_variable fiv

INNER JOIN vue_dash_pred_meilleurs_modeles_etapes mm
    ON mm.id_modele_sk = fiv.id_modele_sk

LEFT JOIN dim_variable dv
    ON dv.id_variable_sk = fiv.id_variable_sk;


CREATE OR REPLACE VIEW vue_dash_pred_importance_familles AS
SELECT
    stage_prediction,
    model_name,
    variant,
    model_family,

    famille_variable,

    COUNT(*) AS nb_variables,

    ROUND(SUM(importance), 6) AS importance_totale,
    ROUND(AVG(importance), 6) AS importance_moyenne,

    MIN(rang_variable) AS meilleur_rang,
    MAX(importance) AS importance_max,

    GROUP_CONCAT(
        CASE
            WHEN rang_variable <= 10 THEN nom_variable
            ELSE NULL
        END
        ORDER BY rang_variable
        SEPARATOR ' | '
    ) AS top_variables_famille

FROM vue_dash_pred_importance_variables

GROUP BY
    stage_prediction,
    model_name,
    variant,
    model_family,
    famille_variable;


CREATE OR REPLACE VIEW vue_dash_pred_elements_favorables_sensibles AS

SELECT
    CAST('Secteur' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS type_element,
    CAST(nom_secteur AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS element,
    CAST(NULL AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS sous_element,
    nb_demandes_historiques AS nb_demandes,
    delai_reel_moyen,
    erreur_moyenne,
    taux_retard_reel,
    nb_retards_non_detectes,
    score_sensibilite_secteur AS score_sensibilite,
    score_favorabilite_secteur AS score_favorabilite,
    CAST(niveau_sensibilite_secteur AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS niveau_sensibilite,
    volume_fiable_flag
FROM vue_dash_pred_secteurs_histo

UNION ALL

SELECT
    CAST('Produit fini' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS type_element,
    CAST(nom_produit AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS element,
    CAST(type_echantillon AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS sous_element,
    nb_demandes_historiques AS nb_demandes,
    delai_reel_moyen,
    erreur_moyenne,
    taux_retard_reel,
    nb_retards_non_detectes,
    score_sensibilite_produit AS score_sensibilite,
    score_favorabilite_produit AS score_favorabilite,
    CAST(niveau_sensibilite_produit AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS niveau_sensibilite,
    volume_fiable_flag
FROM vue_dash_pred_produits_histo

UNION ALL

SELECT
    CAST('Labo / reference labo' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS type_element,
    CAST(ref_labo AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS element,
    CAST(code_ref_labo AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS sous_element,
    nb_demandes_historiques AS nb_demandes,
    delai_reel_moyen,
    erreur_moyenne,
    taux_retard_reel,
    nb_retards_non_detectes,
    score_sensibilite_labo AS score_sensibilite,
    score_favorabilite_labo AS score_favorabilite,
    CAST(niveau_sensibilite_labo AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS niveau_sensibilite,
    volume_fiable_flag
FROM vue_dash_pred_labo_histo

UNION ALL

SELECT
    CAST('Client' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS type_element,
    CAST(client_name AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS element,
    CAST(id_client_sk AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS sous_element,
    nb_demandes_historiques AS nb_demandes,
    delai_reel_moyen,
    erreur_moyenne,
    taux_retard_reel,
    nb_retards_non_detectes,
    score_sensibilite_client AS score_sensibilite,
    score_favorabilite_client AS score_favorabilite,
    CAST(niveau_sensibilite_client AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS niveau_sensibilite,
    volume_fiable_flag
FROM vue_dash_pred_clients_histo

UNION ALL

SELECT
    CAST('Mois' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS type_element,
    CAST(nom_mois AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS element,
    CAST(annee_demande AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS sous_element,
    nb_demandes AS nb_demandes,
    delai_reel_moyen,
    erreur_moyenne,
    taux_retard_reel,
    nb_retards_non_detectes,
    score_sensibilite_mois AS score_sensibilite,
    score_favorabilite_mois AS score_favorabilite,
    CAST(niveau_sensibilite_mois AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS niveau_sensibilite,
    volume_fiable_flag
FROM vue_dash_pred_temps_mois

UNION ALL

SELECT
    CAST('Jour de semaine' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS type_element,
    CAST(jour_semaine AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS element,
    CAST(jour_semaine_num AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS sous_element,
    nb_demandes AS nb_demandes,
    delai_reel_moyen,
    erreur_moyenne,
    taux_retard_reel,
    nb_retards_non_detectes,
    score_sensibilite_jour AS score_sensibilite,
    score_favorabilite_jour AS score_favorabilite,
    CAST(niveau_sensibilite_jour AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS niveau_sensibilite,
    volume_fiable_flag
FROM vue_dash_pred_temps_jour;

CREATE OR REPLACE VIEW vue_pred_kpis_principaux AS
SELECT
    erreur_moyenne,
    erreur_moyenne_corrigee,
    erreur_max,
    taux_retard_reel,
    taux_erreur_elevee,
    nb_retards_reels,
    nb_retards_predits,
    nb_retards_non_detectes,
    nb_fausses_alertes,
    ROUND(
        100 * nb_retards_non_detectes / NULLIF(nb_retards_reels, 0),
        2
    ) AS taux_retards_non_detectes,
    ROUND(
        100 * nb_fausses_alertes / NULLIF(nb_retards_predits, 0),
        2
    ) AS taux_fausses_alertes,
    delai_reel_moyen,
    delai_predit_moyen
FROM vue_dash_pred_synthese_erreurs;

CREATE OR REPLACE VIEW vue_pred_demandes_top_erreurs AS
SELECT
    q.*
FROM (
    SELECT
        e.*,
        ROW_NUMBER() OVER (
            ORDER BY
                e.erreur_absolue DESC,
                e.delai_reel_jours DESC,
                e.ref_demande
        ) AS rang_erreur
    FROM vue_dash_pred_erreurs_detail e
) q
WHERE q.rang_erreur <= 30;

CREATE OR REPLACE VIEW vue_pred_analyses_top_volume AS
SELECT
    q.*
FROM (
    SELECT
        a.*,
        ROW_NUMBER() OVER (
            ORDER BY
                a.nb_demandes_historiques DESC,
                a.erreur_moyenne DESC,
                a.score_criticite_analyse DESC
        ) AS rang_volume
    FROM vue_dash_pred_analyses_histo a
) q
WHERE q.rang_volume <= 15;

CREATE OR REPLACE VIEW vue_pred_analyses_top_sensibles AS
SELECT
    q.*
FROM (
    SELECT
        a.*,
        ROW_NUMBER() OVER (
            ORDER BY
                a.score_criticite_analyse DESC,
                a.nb_retards_non_detectes DESC,
                a.erreur_moyenne DESC
        ) AS rang_sensibilite
    FROM vue_dash_pred_analyses_histo a
) q
WHERE q.rang_sensibilite <= 15;

CREATE OR REPLACE VIEW vue_pred_axes_sensibles_top AS
SELECT
    q.*
FROM (
    SELECT
        efs.*,
        ROW_NUMBER() OVER (
            PARTITION BY efs.type_element
            ORDER BY
                efs.score_sensibilite DESC,
                efs.nb_demandes DESC
        ) AS rang_sensibilite
    FROM vue_dash_pred_elements_favorables_sensibles efs
) q
WHERE q.rang_sensibilite <= 10;

CREATE OR REPLACE VIEW vue_pred_demandes_risque_en_cours AS
SELECT
    q.*,

    CASE
        WHEN q.niveau_risque = 'Critique' THEN 'A risque'
        WHEN q.niveau_risque IN ('Eleve', 'Moyen') THEN 'A surveiller'
        WHEN q.niveau_risque = 'Faible' THEN 'Normal'
        ELSE 'Inconnu'
    END AS diagnostic_risque

FROM (
    SELECT
        fp.id_demande_analyse_sk,
        dda.id_demande_analyse_bk AS id_demande,
        dda.ref_demande,

        dt_dem.date_complete AS date_demande,

        fp.id_client_sk,
        COALESCE(dc.client_name, 'Client non renseigne') AS client_name,

        fp.id_secteur_sk,
        COALESCE(ds.nom_secteur, 'Secteur non renseigne') AS nom_secteur,

        dm.id_modele_sk,
        dm.stage_prediction,
        dm.model_name,
        dm.variant,
        dm.model_family,
        dm.holdout_mae,
        dm.holdout_rmse,
        dm.holdout_r2,

        fp.delai_predit_jours,
        fp.date_livraison_estimee,
        fp.jours_restants_predits,
        COALESCE(fp.jours_retard_predit, 0) AS jours_retard_predit,
        fp.retard_predit_flag,
        fp.niveau_risque AS niveau_risque_source,
        fp.date_chargement,

        CASE
            WHEN COALESCE(fp.jours_retard_predit, 0) > 15 THEN 'Critique'
            WHEN COALESCE(fp.jours_retard_predit, 0) BETWEEN 7 AND 15 THEN 'Eleve'
            WHEN COALESCE(fp.jours_retard_predit, 0) BETWEEN 1 AND 6 THEN 'Moyen'
            WHEN fp.jours_restants_predits IS NULL THEN 'Inconnu'
            WHEN fp.jours_restants_predits <= 2 THEN 'Eleve'
            WHEN fp.jours_restants_predits <= 7 THEN 'Moyen'
            ELSE 'Faible'
        END AS niveau_risque,

        CASE
            WHEN COALESCE(fp.jours_retard_predit, 0) > 15 THEN 'Critique'
            WHEN COALESCE(fp.jours_retard_predit, 0) BETWEEN 7 AND 15 THEN 'Eleve'
            WHEN COALESCE(fp.jours_retard_predit, 0) BETWEEN 1 AND 6 THEN 'Moyen'
            WHEN fp.jours_restants_predits IS NULL THEN 'Inconnu'
            WHEN fp.jours_restants_predits <= 2 THEN 'Eleve'
            WHEN fp.jours_restants_predits <= 7 THEN 'Moyen'
            ELSE 'Faible'
        END AS niveau_risque_dashboard,

        COALESCE(prod.nom_produit, 'Produit non renseigne') AS nom_produit,
        COALESCE(prod.type_echantillon, 'Type echantillon non renseigne') AS type_echantillon,
        COALESCE(prod.ref_labo, 'Ref labo non renseignee') AS ref_labo,
        COALESCE(prod.code_ref_labo, 'Code ref labo non renseigne') AS code_ref_labo,

        COALESCE(ana.codes_analyse, 'Analyse non renseignee') AS codes_analyse,
        COALESCE(ana.noms_analyse, 'Analyse non renseignee') AS noms_analyse,

        ROW_NUMBER() OVER (
            PARTITION BY fp.id_demande_analyse_sk
            ORDER BY
                CASE dm.stage_prediction
                    WHEN 'avancee' THEN 1
                    WHEN 'intermediaire' THEN 2
                    WHEN 'precoce' THEN 3
                    ELSE 4
                END,
                fp.date_chargement DESC,
                COALESCE(dm.holdout_mae, 999999999) ASC,
                fp.id_modele_sk ASC
        ) AS rn

    FROM fait_prediction fp

    LEFT JOIN dim_modele dm
        ON dm.id_modele_sk = fp.id_modele_sk

    LEFT JOIN dim_demande_analyse dda
        ON dda.id_demande_analyse_sk = fp.id_demande_analyse_sk

    LEFT JOIN fait_demande_analyse fda
        ON fda.id_demande_analyse_sk = fp.id_demande_analyse_sk

    LEFT JOIN dim_temps dt_dem
        ON dt_dem.id_temps = fda.id_temps_demande

    LEFT JOIN dim_client dc
        ON dc.id_client_sk = fp.id_client_sk

    LEFT JOIN dim_secteur ds
        ON ds.id_secteur_sk = fp.id_secteur_sk

    LEFT JOIN (
        SELECT
            foa.id_demande_analyse_sk,

            GROUP_CONCAT(DISTINCT de.nom_produit ORDER BY de.nom_produit SEPARATOR ' | ') AS nom_produit,
            GROUP_CONCAT(DISTINCT de.type_echantillon ORDER BY de.type_echantillon SEPARATOR ' | ') AS type_echantillon,
            GROUP_CONCAT(DISTINCT de.ref_labo ORDER BY de.ref_labo SEPARATOR ' | ') AS ref_labo,

            GROUP_CONCAT(
                DISTINCT
                CASE
                    WHEN de.ref_labo IS NULL OR TRIM(de.ref_labo) = '' THEN NULL
                    WHEN LOCATE('*', de.ref_labo) > 0 THEN TRIM(SUBSTRING_INDEX(de.ref_labo, '*', -1))
                    ELSE TRIM(de.ref_labo)
                END
                ORDER BY de.ref_labo
                SEPARATOR ' | '
            ) AS code_ref_labo

        FROM fait_ordre_analyse foa

        LEFT JOIN dim_echantillon de
            ON de.id_echantillon_sk = foa.id_echantillon_sk

        GROUP BY foa.id_demande_analyse_sk
    ) prod
        ON prod.id_demande_analyse_sk = fp.id_demande_analyse_sk

    LEFT JOIN (
        SELECT
            foa.id_demande_analyse_sk,
            GROUP_CONCAT(DISTINCT da.code_analyse ORDER BY da.code_analyse SEPARATOR ' | ') AS codes_analyse,
            GROUP_CONCAT(DISTINCT da.nom_analyse ORDER BY da.nom_analyse SEPARATOR ' | ') AS noms_analyse

        FROM fait_ordre_analyse foa

        LEFT JOIN dim_analyse da
            ON da.id_analyse_sk = foa.id_analyse_sk

        GROUP BY foa.id_demande_analyse_sk
    ) ana
        ON ana.id_demande_analyse_sk = fp.id_demande_analyse_sk

    WHERE fp.type_prediction = 'EN_COURS'
) q
WHERE q.rn = 1;

CREATE OR REPLACE VIEW vue_pred_kpis_en_cours AS
SELECT
    COUNT(DISTINCT id_demande_analyse_sk) AS nb_demandes_en_cours,

    SUM(CASE WHEN diagnostic_risque = 'A risque' THEN 1 ELSE 0 END) AS nb_demandes_a_risque,
    SUM(CASE WHEN diagnostic_risque = 'A surveiller' THEN 1 ELSE 0 END) AS nb_demandes_a_surveiller,
    SUM(CASE WHEN diagnostic_risque = 'Normal' THEN 1 ELSE 0 END) AS nb_demandes_normales,

    ROUND(
        100 * SUM(CASE WHEN diagnostic_risque = 'A risque' THEN 1 ELSE 0 END)
        / NULLIF(COUNT(DISTINCT id_demande_analyse_sk), 0),
        2
    ) AS taux_demandes_a_risque,

    ROUND(
        100 * SUM(CASE WHEN diagnostic_risque = 'A surveiller' THEN 1 ELSE 0 END)
        / NULLIF(COUNT(DISTINCT id_demande_analyse_sk), 0),
        2
    ) AS taux_demandes_a_surveiller,

    ROUND(
        100 * SUM(CASE WHEN diagnostic_risque IN ('A risque', 'A surveiller') THEN 1 ELSE 0 END)
        / NULLIF(COUNT(DISTINCT id_demande_analyse_sk), 0),
        2
    ) AS taux_attention_operationnelle,

    ROUND(AVG(delai_predit_jours), 2) AS delai_predit_moyen,
    ROUND(AVG(jours_restants_predits), 2) AS jours_restants_moyens,
    ROUND(AVG(jours_retard_predit), 2) AS retard_predit_moyen,

    SUM(CASE WHEN niveau_risque = 'Critique' THEN 1 ELSE 0 END) AS nb_risque_critique,
    SUM(CASE WHEN niveau_risque = 'Eleve' THEN 1 ELSE 0 END) AS nb_risque_eleve,
    SUM(CASE WHEN niveau_risque = 'Moyen' THEN 1 ELSE 0 END) AS nb_risque_moyen,
    SUM(CASE WHEN niveau_risque = 'Faible' THEN 1 ELSE 0 END) AS nb_risque_faible,
    SUM(CASE WHEN niveau_risque = 'Inconnu' THEN 1 ELSE 0 END) AS nb_risque_inconnu,

    ROUND(
        100 * SUM(CASE WHEN niveau_risque = 'Critique' THEN 1 ELSE 0 END)
        / NULLIF(COUNT(DISTINCT id_demande_analyse_sk), 0),
        2
    ) AS taux_risque_critique,

    ROUND(
        100 * SUM(CASE WHEN niveau_risque IN ('Eleve', 'Moyen') THEN 1 ELSE 0 END)
        / NULLIF(COUNT(DISTINCT id_demande_analyse_sk), 0),
        2
    ) AS taux_risque_a_surveiller

FROM vue_pred_demandes_risque_en_cours;

CREATE OR REPLACE VIEW vue_pred_section7_top3_modeles AS
SELECT
    m.id_modele_sk,
    m.stage_prediction,
    m.model_name,
    m.variant,
    m.model_family,
    m.holdout_mae,
    m.holdout_rmse,
    m.holdout_r2,
    m.mae_mean,
    m.rmse_mean,
    m.r2_mean,
    m.date_entrainement,
    m.rang_modele,
    CASE
        WHEN m.rang_modele = 1 THEN 'Meilleur modele'
        WHEN m.rang_modele = 2 THEN 'Deuxieme meilleur'
        WHEN m.rang_modele = 3 THEN 'Troisieme meilleur'
        ELSE 'Autre'
    END AS niveau_modele
FROM (
    SELECT
        dm.*,
        ROW_NUMBER() OVER (
            PARTITION BY dm.stage_prediction
            ORDER BY
                COALESCE(dm.holdout_mae, 999999999) ASC,
                COALESCE(dm.holdout_rmse, 999999999) ASC,
                COALESCE(dm.holdout_r2, -999999999) DESC,
                dm.id_modele_sk ASC
        ) AS rang_modele
    FROM dim_modele dm
    WHERE dm.stage_prediction IS NOT NULL
      AND dm.holdout_mae IS NOT NULL
) m
WHERE m.rang_modele <= 3;

CREATE OR REPLACE VIEW vue_pred_section7_importance_variables_top3_raw AS
SELECT
    fiv.id_modele_sk,
    fiv.id_variable_sk,
    fiv.rang_variable,
    fiv.importance,
    ROUND(fiv.importance * 100, 4) AS importance_pct,
    fiv.date_entrainement,
    COALESCE(dv.nom_variable, 'Variable non renseignee') AS nom_variable,
    dv.type_variable,
    dv.niveau_variable,
    CASE
        WHEN dv.nom_variable LIKE 'analyse__%' THEN 'Analyse'
        WHEN dv.nom_variable LIKE 'laborantin__%' THEN 'Laborantin'
        WHEN dv.nom_variable LIKE '%client%' THEN 'Client'
        WHEN dv.nom_variable LIKE '%secteur%' THEN 'Secteur'
        WHEN dv.nom_variable LIKE '%statut%' THEN 'Statut'
        WHEN dv.nom_variable LIKE '%echantillon%' THEN 'Echantillon'
        WHEN dv.nom_variable LIKE '%produit%' THEN 'Produit'
        WHEN dv.nom_variable LIKE '%ref_labo%' THEN 'Reference labo'
        WHEN dv.nom_variable LIKE '%code_ref_labo%' THEN 'Reference labo'
        WHEN dv.nom_variable LIKE '%delai%' THEN 'Delai intermediaire'
        WHEN dv.nom_variable LIKE '%nb_%' THEN 'Volume'
        WHEN dv.nom_variable LIKE '%mois%' THEN 'Mois'
        WHEN dv.nom_variable LIKE '%trimestre%' THEN 'Trimestre'
        WHEN dv.nom_variable LIKE '%jour%' THEN 'Jour semaine'
        ELSE 'Autre'
    END AS famille_variable,
    CASE
        WHEN dv.nom_variable LIKE 'analyse__%'
        THEN CONCAT('Analyse: ', REPLACE(SUBSTRING(dv.nom_variable, 10), '_', ' '))
        WHEN dv.nom_variable LIKE 'laborantin__%'
        THEN CONCAT('Laborantin: ', REPLACE(SUBSTRING(dv.nom_variable, 13), '_', ' '))
        ELSE COALESCE(dv.nom_variable, 'Variable non renseignee')
    END AS variable_affichee,
    t3.stage_prediction,
    t3.model_name,
    t3.variant,
    t3.model_family,
    t3.holdout_mae,
    t3.holdout_rmse,
    t3.holdout_r2,
    t3.rang_modele,
    t3.niveau_modele,
    CASE
        WHEN fiv.rang_variable <= 10 THEN 'Top 10'
        WHEN fiv.rang_variable <= 20 THEN 'Top 20'
        WHEN fiv.rang_variable <= 50 THEN 'Top 50'
        ELSE 'Autres'
    END AS niveau_importance,
    CASE
        WHEN fiv.importance >= 0.10 THEN 'Tres forte'
        WHEN fiv.importance >= 0.05 THEN 'Forte'
        WHEN fiv.importance >= 0.01 THEN 'Moyenne'
        ELSE 'Faible'
    END AS intensite_importance
FROM fait_importance_variable fiv
INNER JOIN vue_pred_section7_top3_modeles t3
    ON t3.id_modele_sk = fiv.id_modele_sk
LEFT JOIN dim_variable dv
    ON dv.id_variable_sk = fiv.id_variable_sk;

CREATE OR REPLACE VIEW vue_pred_section7_importance_variables_dashboard AS
SELECT
    q.*
FROM (
    SELECT
        r.*,
        ROW_NUMBER() OVER (
            PARTITION BY r.id_modele_sk
            ORDER BY
                r.importance DESC,
                r.rang_variable ASC
        ) AS rang_dashboard
    FROM vue_pred_section7_importance_variables_top3_raw r
    WHERE r.famille_variable <> 'Analyse'
) q
WHERE q.rang_dashboard <= 20;

CREATE OR REPLACE VIEW vue_pred_section7_importance_analyses_top AS
SELECT
    q.*
FROM (
    SELECT
        r.*,
        ROW_NUMBER() OVER (
            PARTITION BY r.id_modele_sk
            ORDER BY
                r.importance DESC,
                r.rang_variable ASC
        ) AS rang_analyse
    FROM vue_pred_section7_importance_variables_top3_raw r
    WHERE r.famille_variable = 'Analyse'
) q
WHERE q.rang_analyse <= 10;

CREATE OR REPLACE VIEW vue_pred_section7_importance_familles_top3 AS
SELECT
    id_modele_sk,
    stage_prediction,
    model_name,
    variant,
    model_family,
    rang_modele,
    niveau_modele,
    famille_variable,
    COUNT(*) AS nb_variables,
    ROUND(SUM(importance), 6) AS importance_totale,
    ROUND(AVG(importance), 6) AS importance_moyenne,
    MAX(importance) AS importance_max,
    MIN(rang_variable) AS meilleur_rang,
    GROUP_CONCAT(
        CASE
            WHEN rang_variable <= 10 THEN variable_affichee
            ELSE NULL
        END
        ORDER BY rang_variable
        SEPARATOR ' | '
    ) AS top_variables_famille
FROM vue_pred_section7_importance_variables_top3_raw
GROUP BY
    id_modele_sk,
    stage_prediction,
    model_name,
    variant,
    model_family,
    rang_modele,
    niveau_modele,
    famille_variable;
