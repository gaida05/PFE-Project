<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/db_labo_dw.php';

set_time_limit(0);
ini_set('memory_limit', '1024M');

function dw_exec($conn, $sql, $label = 'SQL') {
    if (!$conn->query($sql)) {
        throw new Exception($label . ' : ' . $conn->error);
    }
}

function dw_scalar($conn, $sql, $label = 'SCALAR') {
    $res = $conn->query($sql);
    if (!$res) {
        throw new Exception($label . ' : ' . $conn->error);
    }
    $row = $res->fetch_row();
    return $row ? (int)$row[0] : 0;
}

function dw_table_exists($conn, $table) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    if (!$stmt) {
        throw new Exception('Erreur prepare table_exists : ' . $conn->error);
    }
    $stmt->bind_param('s', $table);
    if (!$stmt->execute()) {
        throw new Exception('Erreur execute table_exists : ' . $stmt->error);
    }
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return ((int)$count) > 0;
}

function dw_column_exists($conn, $table, $column) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if (!$stmt) {
        throw new Exception('Erreur prepare column_exists : ' . $conn->error);
    }
    $stmt->bind_param('ss', $table, $column);
    if (!$stmt->execute()) {
        throw new Exception('Erreur execute column_exists : ' . $stmt->error);
    }
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return ((int)$count) > 0;
}

function dw_index_exists($conn, $table, $index) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    if (!$stmt) {
        throw new Exception('Erreur prepare index_exists : ' . $conn->error);
    }
    $stmt->bind_param('ss', $table, $index);
    if (!$stmt->execute()) {
        throw new Exception('Erreur execute index_exists : ' . $stmt->error);
    }
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return ((int)$count) > 0;
}

function dw_primary_key_columns($conn, $table) {
    $stmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION");
    if (!$stmt) {
        throw new Exception('Erreur prepare primary_key_columns : ' . $conn->error);
    }
    $stmt->bind_param('s', $table);
    if (!$stmt->execute()) {
        throw new Exception('Erreur execute primary_key_columns : ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $cols = [];
    while ($row = $res->fetch_assoc()) {
        $cols[] = $row['COLUMN_NAME'];
    }
    $stmt->close();
    return $cols;
}

function dw_ensure_single_primary_key($conn, $table, $column, $definition) {
    if (!dw_table_exists($conn, $table)) {
        return;
    }

    if (!dw_column_exists($conn, $table, $column)) {
        dw_exec($conn, "ALTER TABLE {$table} ADD COLUMN {$column} {$definition} FIRST", "ADD {$table}.{$column}");
    }

    $pkCols = dw_primary_key_columns($conn, $table);
    if (!(count($pkCols) === 1 && $pkCols[0] === $column)) {
        if (count($pkCols) > 0) {
            dw_exec($conn, "ALTER TABLE {$table} DROP PRIMARY KEY", "DROP PK {$table}");
        }
        dw_exec($conn, "ALTER TABLE {$table} ADD PRIMARY KEY ({$column})", "ADD PK {$table}.{$column}");
    }
}

function dw_delete_if_exists($conn, $table) {
    if (dw_table_exists($conn, $table)) {
        dw_exec($conn, "DELETE FROM {$table}", "DELETE {$table}");
    }
}

function dw_reset_ai_if_exists($conn, $table) {
    if (dw_table_exists($conn, $table)) {
        @$conn->query("ALTER TABLE {$table} AUTO_INCREMENT = 1");
    }
}

try {
    echo "Début import_dw.php...\n";

    $conn->set_charset('utf8mb4');
    $conn_dw->set_charset('utf8mb4');

    if (!$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_bin")) {
        throw new Exception('Erreur SET NAMES source : ' . $conn->error);
    }
    if (!$conn_dw->query("SET NAMES utf8mb4 COLLATE utf8mb4_bin")) {
        throw new Exception('Erreur SET NAMES DW : ' . $conn_dw->error);
    }

    $conn_dw->begin_transaction();
    dw_exec($conn_dw, "SET FOREIGN_KEY_CHECKS = 0", "FK OFF");
    $deleteTables = [
        'fait_prediction',
        'fait_importance_variable',
        'fait_resultat_analyse',
        'fait_rapport',
        'fait_ordre_analyse',
        'fait_facturation',
        'fait_demande_client',
        'fait_demande_analyse',
        'dim_variable',
        'dim_modele',
        'dim_laborantin',
        'dim_analyse',
        'dim_echantillon',
        'dim_demande_client',
        'dim_demande_analyse',
        'dim_statut',
        'dim_secteur',
        'dim_client',
        'dim_temps'
    ];

    foreach ($deleteTables as $table) {
        dw_delete_if_exists($conn_dw, $table);
    }
    dw_ensure_single_primary_key($conn_dw, 'fait_ordre_analyse', 'id_ordre_bk', 'INT NOT NULL');
    if (dw_table_exists($conn_dw, 'fait_ordre_analyse') && dw_column_exists($conn_dw, 'fait_ordre_analyse', 'reference_ordre') && !dw_index_exists($conn_dw, 'fait_ordre_analyse', 'idx_foa_reference_ordre')) {
        dw_exec($conn_dw, "ALTER TABLE fait_ordre_analyse ADD INDEX idx_foa_reference_ordre (reference_ordre(191))", "ADD idx_foa_reference_ordre");
    }

    dw_ensure_single_primary_key($conn_dw, 'fait_rapport', 'id_rapport_bk', 'VARCHAR(100) NOT NULL');
    if (dw_table_exists($conn_dw, 'fait_rapport') && dw_column_exists($conn_dw, 'fait_rapport', 'reference_rapport') && !dw_index_exists($conn_dw, 'fait_rapport', 'idx_fr_reference_rapport')) {
        dw_exec($conn_dw, "ALTER TABLE fait_rapport ADD INDEX idx_fr_reference_rapport (reference_rapport(191))", "ADD idx_fr_reference_rapport");
    }

    $aifTables = [
        'dim_client',
        'dim_secteur',
        'dim_statut',
        'dim_analyse',
        'dim_echantillon',
        'dim_laborantin',
        'dim_demande_client',
        'dim_demande_analyse',
        'dim_modele',
        'dim_variable'
    ];
    foreach ($aiTables as $table) {
        dw_reset_ai_if_exists($conn_dw, $table);
    }
    dw_exec($conn_dw, "SET FOREIGN_KEY_CHECKS = 1", "FK ON");
    dw_exec($conn_dw, "
        INSERT INTO dim_temps
        (
            id_temps,
            date_complete,
            jour,
            mois,
            nom_mois,
            trimestre,
            annee,
            semaine,
            mois_annee
        )
        VALUES
        (
            0,
            '1900-01-01',
            1,
            1,
            'Inconnu',
            1,
            1900,
            1,
            '1900-01'
        )
    ", "INSERT dim_temps_unknown");

    dw_exec($conn_dw, "
        INSERT INTO dim_temps
        (
            id_temps,
            date_complete,
            jour,
            mois,
            nom_mois,
            trimestre,
            annee,
            semaine,
            mois_annee
        )
        SELECT DISTINCT
            CAST(DATE_FORMAT(DATE(src.dt), '%Y%m%d') AS UNSIGNED) AS id_temps,
            DATE(src.dt) AS date_complete,
            DAY(src.dt) AS jour,
            MONTH(src.dt) AS mois,
            MONTHNAME(src.dt) AS nom_mois,
            QUARTER(src.dt) AS trimestre,
            YEAR(src.dt) AS annee,
            WEEK(src.dt, 1) AS semaine,
            DATE_FORMAT(src.dt, '%Y-%m') AS mois_annee
        FROM (
            SELECT date_demande AS dt FROM labo_db1.demande_client WHERE date_demande IS NOT NULL
            UNION SELECT date_reception AS dt FROM labo_db1.demande_client WHERE date_reception IS NOT NULL
            UNION SELECT date_verif AS dt FROM labo_db1.demande_client WHERE date_verif IS NOT NULL
            UNION SELECT date_demande AS dt FROM labo_db1.demande_analyse WHERE date_demande IS NOT NULL
            UNION SELECT date_creation AS dt FROM labo_db1.demande_analyse WHERE date_creation IS NOT NULL
            UNION SELECT date_reception AS dt FROM labo_db1.demande_analyse WHERE date_reception IS NOT NULL
            UNION SELECT date_affectation AS dt FROM labo_db1.demande_analyse WHERE date_affectation IS NOT NULL
            UNION SELECT date_debut_analyse AS dt FROM labo_db1.demande_analyse WHERE date_debut_analyse IS NOT NULL
            UNION SELECT date_fin_analyse AS dt FROM labo_db1.demande_analyse WHERE date_fin_analyse IS NOT NULL
            UNION SELECT date_validation AS dt FROM labo_db1.demande_analyse WHERE date_validation IS NOT NULL
            UNION SELECT date_cloture AS dt FROM labo_db1.demande_analyse WHERE date_cloture IS NOT NULL
            UNION SELECT date_demande AS dt FROM labo_db1.ordres_analyses WHERE date_demande IS NOT NULL
            UNION SELECT date_reception AS dt FROM labo_db1.ordres_analyses WHERE date_reception IS NOT NULL
            UNION SELECT date_affectation AS dt FROM labo_db1.ordres_analyses WHERE date_affectation IS NOT NULL
            UNION SELECT date_fin AS dt FROM labo_db1.ordres_analyses WHERE date_fin IS NOT NULL
            UNION SELECT date_validation AS dt FROM labo_db1.ordres_analyses WHERE date_validation IS NOT NULL
            UNION SELECT date_creation AS dt FROM labo_db1.ordres_analyses WHERE date_creation IS NOT NULL
            UNION SELECT created_at AS dt FROM labo_db1.resultat_analyse WHERE created_at IS NOT NULL
            UNION SELECT date_creation AS dt FROM labo_db1.rapports WHERE date_creation IS NOT NULL
            UNION SELECT date_validation AS dt FROM labo_db1.rapports WHERE date_validation IS NOT NULL
            UNION SELECT date_livraison_interne AS dt FROM labo_db1.rapports WHERE date_livraison_interne IS NOT NULL
            UNION SELECT date_livraison AS dt FROM labo_db1.rapports WHERE date_livraison IS NOT NULL
            UNION SELECT date_crea AS dt FROM labo_db1.devis WHERE date_crea IS NOT NULL
            UNION SELECT date_fact AS dt FROM labo_db1.devis WHERE date_fact IS NOT NULL
            UNION SELECT CURDATE() AS dt
        ) src
        WHERE src.dt IS NOT NULL
          AND DATE(src.dt) IS NOT NULL
          AND CAST(DATE_FORMAT(DATE(src.dt), '%Y%m%d') AS UNSIGNED) <> 0
    ", "INSERT dim_temps");

    /* =========================================================
       DESCRIPTIVE DIMENSIONS + UNKNOWN MEMBERS
       ========================================================= */
    dw_exec($conn_dw, "
        INSERT INTO dim_client
        (
            id_client_bk,
            client_name,
            adresse,
            ville,
            pays,
            telephone,
            email,
            type_client,
            contact_person
        )
        VALUES
        (0, 'Inconnu', NULL, NULL, NULL, NULL, NULL, 'Inconnu', NULL)
    ", "INSERT dim_client_unknown");

    dw_exec($conn_dw, "
        INSERT INTO dim_client
        (
            id_client_bk,
            client_name,
            adresse,
            ville,
            pays,
            telephone,
            email,
            type_client,
            contact_person
        )
        SELECT DISTINCT
            c.id_client,
            LEFT(c.client_name, 255),
            LEFT(c.adresse, 255),
            LEFT(c.ville, 100),
            LEFT(c.pays, 100),
            LEFT(c.telephone, 50),
            LEFT(c.email, 255),
            LEFT(c.type_client, 100),
            LEFT(c.contact_person, 255)
        FROM labo_db1.client c
        WHERE c.id_client IS NOT NULL
    ", "INSERT dim_client");

    dw_exec($conn_dw, "
        INSERT INTO dim_secteur (id_secteur_bk, nom_secteur)
        VALUES (0, 'Inconnu')
    ", "INSERT dim_secteur_unknown");

    dw_exec($conn_dw, "
        INSERT INTO dim_secteur (id_secteur_bk, nom_secteur)
        SELECT DISTINCT
            s.id_secteur,
            LEFT(s.nom_secteur, 255)
        FROM labo_db1.secteur s
        WHERE s.id_secteur IS NOT NULL
    ", "INSERT dim_secteur");

    dw_exec($conn_dw, "
        INSERT INTO dim_analyse
        (
            id_analyse_bk,
            nom_analyse,
            code_analyse,
            methode_defaut,
            unite_defaut
        )
        VALUES
        (0, 'Inconnu', NULL, NULL, NULL)
    ", "INSERT dim_analyse_unknown");

    dw_exec($conn_dw, "
        INSERT INTO dim_analyse
        (
            id_analyse_bk,
            nom_analyse,
            code_analyse,
            methode_defaut,
            unite_defaut
        )
        SELECT DISTINCT
            a.id_analyse,
            LEFT(a.nom_analyse, 255),
            LEFT(a.code_analyse, 100),
            LEFT(a.methode_defaut, 255),
            LEFT(a.unite_defaut, 100)
        FROM labo_db1.analyses a
        WHERE a.id_analyse IS NOT NULL
    ", "INSERT dim_analyse");

    dw_exec($conn_dw, "
        INSERT INTO dim_laborantin
        (
            id_laborantin_bk,
            nom_laborantin,
            role,
            actif
        )
        VALUES
        (0, 'Inconnu', 'Inconnu', 0)
    ", "INSERT dim_laborantin_unknown");

    dw_exec($conn_dw, "
        INSERT INTO dim_laborantin
        (
            id_laborantin_bk,
            nom_laborantin,
            role,
            actif
        )
        SELECT DISTINCT
            l.id_laborantin,
            LEFT(l.nom_laborantin, 255),
            LEFT(l.role, 100),
            l.actif
        FROM labo_db1.laborantin l
        WHERE l.id_laborantin IS NOT NULL
    ", "INSERT dim_laborantin");
    dw_exec($conn_dw, "
        INSERT INTO dim_demande_analyse
        (
            id_demande_analyse_bk,
            ref_demande
        )
        SELECT
            d.id_demande AS id_demande_analyse_bk,
            LEFT(MAX(d.ref_demande), 255) AS ref_demande
        FROM labo_db1.demande_analyse d
        WHERE d.id_demande IS NOT NULL
        GROUP BY d.id_demande
    ", "INSERT dim_demande_analyse");
    dw_exec($conn_dw, "
        INSERT INTO dim_demande_client
        (
            id_demande_client_bk,
            ref_demande_client,
            validite,
            faisabilite
        )
        SELECT
            c.id,
            LEFT(MAX(c.ref_demande), 255),
            LEFT(MAX(CASE WHEN c.validite IS NULL OR TRIM(c.validite) = '' THEN 'oui' ELSE c.validite END), 50),
            LEFT(MAX(CASE WHEN c.faisabilite IS NULL OR TRIM(c.faisabilite) = '' THEN 'oui' ELSE c.faisabilite END), 50)
        FROM labo_db1.demande_client c
        WHERE c.id IS NOT NULL
        GROUP BY c.id
    ", "INSERT dim_demande_client_from_demande_client");
    dw_exec($conn_dw, "
        INSERT INTO dim_demande_client
        (
            id_demande_client_bk,
            ref_demande_client,
            validite,
            faisabilite
        )
        SELECT
            -ABS(v.id) AS id_demande_client_bk,
            LEFT(CONCAT( COALESCE(NULLIF(TRIM(v.t_ref), ''), 'SANS-REF')), 255) AS ref_demande_client,
            NULL AS validite,
            NULL AS faisabilite
        FROM labo_db1.devis v
        WHERE v.id IS NOT NULL
    ", "INSERT dim_demande_client_from_devis");

    dw_exec($conn_dw, "
        INSERT INTO dim_echantillon
        (
            id_echantillon_bk,
            type_echantillon,
            est_conforme,
            date_reception,
            nom_produit,
            temperature_reception,
            date_production,
            dlc,
            numero_lot,
            code_produit_demandeur,
            ref_labo
        )
        VALUES
        (0, 'Inconnu', 'Inconnu', NULL, 'Inconnu', NULL, NULL, NULL, NULL, NULL, 'Inconnu')
    ", "INSERT dim_echantillon_unknown");

    dw_exec($conn_dw, "
        INSERT INTO dim_echantillon
        (
            id_echantillon_bk,
            type_echantillon,
            est_conforme,
            date_reception,
            nom_produit,
            temperature_reception,
            date_production,
            dlc,
            numero_lot,
            code_produit_demandeur,
            ref_labo
        )
        SELECT
            e.id_echantillon,
            LEFT(e.type_echantillon, 100),
            LEFT(e.est_conforme, 50),
            DATE(e.date_reception),
            LEFT(e.nom_produit, 255),
            LEFT(e.temperature_reception, 50),
            DATE(e.date_production),
            DATE(e.dlc),
            LEFT(e.numero_lot, 255),
            LEFT(COALESCE(e.code_produit_demandeur, CAST(e.produit_fini_id AS CHAR)), 255),
            LEFT(e.ref_labo, 255)
        FROM labo_db1.echantillons e
        WHERE e.id_echantillon IS NOT NULL
        GROUP BY e.id_echantillon,
                 LEFT(e.type_echantillon, 100),
                 LEFT(e.est_conforme, 50),
                 DATE(e.date_reception),
                 LEFT(e.nom_produit, 255),
                 LEFT(e.temperature_reception, 50),
                 DATE(e.date_production),
                 DATE(e.dlc),
                 LEFT(e.numero_lot, 255),
                 LEFT(COALESCE(e.code_produit_demandeur, CAST(e.produit_fini_id AS CHAR)), 255),
                 LEFT(e.ref_labo, 255)
    ", "INSERT dim_echantillon");
    dw_exec($conn_dw, "
        INSERT INTO dim_statut
        (
            statut_demande,
            statut_ordre,
            statut_rapport,
            faisabilite,
            validite,
            conformite,
            statut_prediction
        )
        VALUES
            ('Inconnu', NULL, NULL, NULL, NULL, NULL, NULL),
            (NULL, 'Inconnu', NULL, NULL, NULL, NULL, NULL),
            (NULL, NULL, 'Inconnu', NULL, NULL, NULL, NULL),
            (NULL, NULL, NULL, NULL, NULL, NULL, 'Inconnu')
    ", "INSERT dim_statut_unknowns");

    dw_exec($conn_dw, "
        INSERT INTO dim_statut
        (
            statut_demande,
            statut_ordre,
            statut_rapport,
            faisabilite,
            validite,
            conformite,
            statut_prediction
        )
        SELECT DISTINCT
            CASE WHEN d.statut IS NULL OR TRIM(d.statut) = '' THEN 'En cours' ELSE LEFT(d.statut, 100) END AS statut_demande,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL
        FROM labo_db1.demande_analyse d
        WHERE d.id_demande IS NOT NULL
    ", "INSERT dim_statut_demande");

    dw_exec($conn_dw, "
        INSERT INTO dim_statut
        (
            statut_demande,
            statut_ordre,
            statut_rapport,
            faisabilite,
            validite,
            conformite,
            statut_prediction
        )
        SELECT DISTINCT
            NULL,
            CASE
                WHEN o.statut_code IS NULL OR TRIM(o.statut_code) = '' THEN 'Pas évaluée'
                WHEN REPLACE(TRIM(o.statut_code), ' ', '') = '100%' THEN 'Terminé'
                ELSE 'En cours'
            END AS statut_ordre,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL
        FROM labo_db1.ordres_analyses o
    ", "INSERT dim_statut_ordre");

    dw_exec($conn_dw, "
        INSERT INTO dim_statut
        (
            statut_demande,
            statut_ordre,
            statut_rapport,
            faisabilite,
            validite,
            conformite,
            statut_prediction
        )
        SELECT DISTINCT
            NULL,
            NULL,
            CASE
                WHEN r.statut IS NULL OR TRIM(r.statut) = '' THEN
                    CASE
                        WHEN r.date_validation IS NOT NULL
                          OR r.date_livraison IS NOT NULL
                          OR r.date_livraison_interne IS NOT NULL
                          OR d.date_validation IS NOT NULL
                          OR d.date_cloture IS NOT NULL
                        THEN 'Validé'
                        ELSE 'En cours'
                    END
                ELSE LEFT(r.statut, 100)
            END AS statut_rapport,
            NULL,
            NULL,
            NULL,
            NULL
        FROM labo_db1.rapports r
        LEFT JOIN labo_db1.demande_analyse d
            ON d.id_demande = r.id_demande
    ", "INSERT dim_statut_rapport");

    dw_exec($conn_dw, "
        INSERT INTO dim_statut
        (
            statut_demande,
            statut_ordre,
            statut_rapport,
            faisabilite,
            validite,
            conformite,
            statut_prediction
        )
        VALUES
        (NULL, NULL, NULL, NULL, NULL, NULL, 'PREDICTION')
    ", "INSERT dim_statut_prediction_default");
    dw_exec($conn_dw, "
        INSERT INTO dim_modele
        (
            model_name,
            variant,
            stage_prediction,
            model_family,
            hyperparams_json,
            mae_mean,
            rmse_mean,
            r2_mean,
            holdout_mae,
            holdout_rmse,
            holdout_r2,
            date_entrainement
        )
        VALUES
        (
            'RandomForestRegressor',
            'v1',
            'global',
            'random_forest',
            '{}',
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            NOW()
        )
    ", "INSERT dim_modele_default");
    dw_exec($conn_dw, "
        INSERT INTO fait_demande_client
        (
            id_temps_demande,
            id_client_sk,
            id_statut_sk,
            id_demande_client_sk,
            id_demande_analyse_sk,
            nb_demandes_client,
            faisable_flag,
            valide_flag,
            delai_transformation_jours
        )
        SELECT
            COALESCE(tt.id_temps, 0) AS id_temps_demande,
            COALESCE(dc.id_client_sk, dc_unknown.id_client_sk) AS id_client_sk,
            COALESCE(ds.id_statut_sk, ds_unknown.id_statut_sk) AS id_statut_sk,
            ddc.id_demande_client_sk,
            dda.id_demande_analyse_sk,
            1 AS nb_demandes_client,
            CASE
                WHEN c.faisabilite IS NULL OR TRIM(c.faisabilite) = '' THEN 1
                WHEN LOWER(TRIM(c.faisabilite)) = 'oui' THEN 1
                ELSE 0
            END AS faisable_flag,
            CASE
                WHEN c.validite IS NULL OR TRIM(c.validite) = '' THEN 1
                WHEN LOWER(TRIM(c.validite)) = 'oui' THEN 1
                ELSE 0
            END AS valide_flag,
            CASE
                WHEN c.date_demande IS NOT NULL AND d.date_demande IS NOT NULL
                THEN TIMESTAMPDIFF(DAY, c.date_demande, d.date_demande)
                ELSE NULL
            END AS delai_transformation_jours
        FROM labo_db1.demande_client c
        LEFT JOIN labo_db1.demande_analyse d
            ON d.id_demande = c.demande_id
        LEFT JOIN dim_temps tt
            ON tt.date_complete = DATE(c.date_demande)
        LEFT JOIN dim_client dc
            ON dc.id_client_bk = c.client_id
        INNER JOIN dim_client dc_unknown
            ON dc_unknown.id_client_bk = 0
        INNER JOIN dim_demande_client ddc
            ON ddc.id_demande_client_bk = c.id
        LEFT JOIN dim_demande_analyse dda
            ON dda.id_demande_analyse_bk = c.demande_id
        LEFT JOIN dim_statut ds
            ON ds.statut_demande = CASE WHEN d.statut IS NULL OR TRIM(d.statut) = '' THEN 'En cours' ELSE LEFT(d.statut, 100) END
           AND ds.statut_ordre IS NULL
           AND ds.statut_rapport IS NULL
           AND ds.faisabilite IS NULL
           AND ds.validite IS NULL
           AND ds.conformite IS NULL
           AND ds.statut_prediction IS NULL
        INNER JOIN dim_statut ds_unknown
            ON ds_unknown.statut_demande = 'Inconnu'
           AND ds_unknown.statut_ordre IS NULL
           AND ds_unknown.statut_rapport IS NULL
           AND ds_unknown.faisabilite IS NULL
           AND ds_unknown.validite IS NULL
           AND ds_unknown.conformite IS NULL
           AND ds_unknown.statut_prediction IS NULL
        WHERE c.id IS NOT NULL
    ", "INSERT fait_demande_client");
    dw_exec($conn_dw, "
        INSERT INTO fait_demande_analyse
        (
            id_temps_demande,
            id_client_sk,
            id_secteur_sk,
            id_statut_sk,
            id_demande_analyse_sk,
            nb_demandes_analyse,
            nb_echantillons,
            nb_analyses,
            nb_ordres,
            nb_resultats,
            delai_reception_jours,
            delai_affectation_jours,
            delai_total_jours,
            delai_debut_analyse_jours
        )
        SELECT
            COALESCE(tt.id_temps, 0) AS id_temps_demande,
            COALESCE(dc.id_client_sk, dc_unknown.id_client_sk) AS id_client_sk,
            COALESCE(dsec.id_secteur_sk, dsec_unknown.id_secteur_sk) AS id_secteur_sk,
            COALESCE(ds.id_statut_sk, ds_unknown.id_statut_sk) AS id_statut_sk,
            dda.id_demande_analyse_sk,
            1 AS nb_demandes_analyse,
            COALESCE(e.nb_echantillons, 0) AS nb_echantillons,
            COALESCE(a.nb_analyses, 0) AS nb_analyses,
            COALESCE(o.nb_ordres, 0) AS nb_ordres,
            COALESCE(r.nb_resultats, 0) AS nb_resultats,
            CASE
                WHEN d.date_demande IS NOT NULL
                 AND d.date_cloture IS NOT NULL
                 AND d.date_cloture >= d.date_demande
                 AND d.date_reception IS NOT NULL
                 AND d.date_reception >= d.date_demande
                THEN TIMESTAMPDIFF(DAY, d.date_demande, d.date_reception)
                ELSE NULL
            END AS delai_reception_jours,
            CASE
                WHEN d.date_demande IS NOT NULL
                 AND d.date_cloture IS NOT NULL
                 AND d.date_cloture >= d.date_demande
                 AND d.date_reception IS NOT NULL
                 AND d.date_affectation IS NOT NULL
                 AND DATE(d.date_affectation) >= DATE(d.date_reception)
                THEN DATEDIFF(DATE(d.date_affectation), DATE(d.date_reception))
                ELSE NULL
            END AS delai_affectation_jours,
            CASE
                WHEN d.date_demande IS NOT NULL
                 AND d.date_cloture IS NOT NULL
                 AND d.date_cloture >= d.date_demande
                THEN TIMESTAMPDIFF(DAY, d.date_demande, d.date_cloture)
                ELSE NULL
            END AS delai_total_jours,
            CASE
                WHEN d.date_demande IS NOT NULL
                 AND d.date_cloture IS NOT NULL
                 AND d.date_cloture >= d.date_demande
                 AND d.date_reception IS NOT NULL
                 AND d.date_debut_analyse IS NOT NULL
                 AND d.date_debut_analyse >= d.date_reception
                THEN TIMESTAMPDIFF(DAY, d.date_reception, d.date_debut_analyse)
                ELSE NULL
            END AS delai_debut_analyse_jours
        FROM labo_db1.demande_analyse d
        LEFT JOIN dim_temps tt
            ON tt.date_complete = DATE(d.date_demande)
        LEFT JOIN dim_client dc
            ON dc.id_client_bk = d.id_client
        INNER JOIN dim_client dc_unknown
            ON dc_unknown.id_client_bk = 0
        LEFT JOIN dim_secteur dsec
            ON dsec.id_secteur_bk = d.id_secteur
        INNER JOIN dim_secteur dsec_unknown
            ON dsec_unknown.id_secteur_bk = 0
        INNER JOIN dim_demande_analyse dda
            ON dda.id_demande_analyse_bk = d.id_demande
        LEFT JOIN dim_statut ds
            ON ds.statut_demande = CASE WHEN d.statut IS NULL OR TRIM(d.statut) = '' THEN 'En cours' ELSE LEFT(d.statut, 100) END
           AND ds.statut_ordre IS NULL
           AND ds.statut_rapport IS NULL
           AND ds.faisabilite IS NULL
           AND ds.validite IS NULL
           AND ds.conformite IS NULL
           AND ds.statut_prediction IS NULL
        INNER JOIN dim_statut ds_unknown
            ON ds_unknown.statut_demande = 'Inconnu'
           AND ds_unknown.statut_ordre IS NULL
           AND ds_unknown.statut_rapport IS NULL
           AND ds_unknown.faisabilite IS NULL
           AND ds_unknown.validite IS NULL
           AND ds_unknown.conformite IS NULL
           AND ds_unknown.statut_prediction IS NULL
        LEFT JOIN (
            SELECT id_demande, COUNT(*) AS nb_echantillons
            FROM labo_db1.echantillons
            GROUP BY id_demande
        ) e ON e.id_demande = d.id_demande
        LEFT JOIN (
            SELECT demande_id, COUNT(DISTINCT analyse_id) AS nb_analyses
            FROM labo_db1.ordres_analyses
            GROUP BY demande_id
        ) a ON a.demande_id = d.id_demande
        LEFT JOIN (
            SELECT demande_id, COUNT(*) AS nb_ordres
            FROM labo_db1.ordres_analyses
            GROUP BY demande_id
        ) o ON o.demande_id = d.id_demande
        LEFT JOIN (
            SELECT id_demande, COUNT(*) AS nb_resultats
            FROM labo_db1.resultat_analyse
            GROUP BY id_demande
        ) r ON r.id_demande = d.id_demande
        WHERE d.id_demande IS NOT NULL
    ", "INSERT fait_demande_analyse");

    /* =========================================================
       FAIT FACTURATION
       Grain: one row per devis.
       ========================================================= */
    dw_exec($conn_dw, "
        INSERT INTO fait_facturation
        (
            id_temps_creation,
            id_temps_facturation,
            id_client_sk,
            id_demande_client_sk,
            nb_devis,
            facture_flag,
            reference_devis
        )
        SELECT
            COALESCE(tc.id_temps, 0) AS id_temps_creation,
            tf.id_temps AS id_temps_facturation,
            COALESCE(dc.id_client_sk, dc_unknown.id_client_sk) AS id_client_sk,
            ddc.id_demande_client_sk,
            1 AS nb_devis,
            CASE
                WHEN v.date_fact IS NOT NULL OR (v.ref_fact_erp IS NOT NULL AND TRIM(v.ref_fact_erp) <> '')
                THEN 1
                ELSE 0
            END AS facture_flag,
            LEFT(CONCAT('DEVIS-', v.id, '-', COALESCE(NULLIF(TRIM(v.t_ref), ''), 'SANS-REF')), 500) AS reference_devis
        FROM labo_db1.devis v
        LEFT JOIN dim_temps tc
            ON tc.date_complete = DATE(v.date_crea)
        LEFT JOIN dim_temps tf
            ON tf.date_complete = DATE(v.date_fact)
        LEFT JOIN dim_client dc
            ON dc.id_client_bk = v.client_id
        INNER JOIN dim_client dc_unknown
            ON dc_unknown.id_client_bk = 0
        INNER JOIN dim_demande_client ddc
            ON ddc.id_demande_client_bk = -ABS(v.id)
        WHERE v.id IS NOT NULL
    ", "INSERT fait_facturation");

    /* =========================================================
       FAIT ORDRE ANALYSE
       Grain: one row per ordre source.
       ========================================================= */
    dw_exec($conn_dw, "
        INSERT INTO fait_ordre_analyse
        (
            id_ordre_bk,
            id_temps_demande,
            id_temps_reception,
            id_temps_affectation,
            id_temps_fin,
            id_temps_validation,
            id_client_sk,
            id_secteur_sk,
            id_analyse_sk,
            id_laborantin_sk,
            id_echantillon_sk,
            id_statut_sk,
            id_demande_analyse_sk,
            nb_ordres,
            delai_prefixe_heure,
            duree_analyse_jours,
            delai_prise_en_charge_jours,
            reference_ordre
        )
        SELECT
            o.ordre_id AS id_ordre_bk,
            tdem.id_temps AS id_temps_demande,
            trec.id_temps AS id_temps_reception,
            taff.id_temps AS id_temps_affectation,
            tfin.id_temps AS id_temps_fin,
            tval.id_temps AS id_temps_validation,
            COALESCE(dc.id_client_sk, dc_unknown.id_client_sk) AS id_client_sk,
            COALESCE(dsec.id_secteur_sk, dsec_unknown.id_secteur_sk) AS id_secteur_sk,
            COALESCE(da_dim.id_analyse_sk, da_unknown.id_analyse_sk) AS id_analyse_sk,
            COALESCE(dl.id_laborantin_sk, dl_unknown.id_laborantin_sk) AS id_laborantin_sk,
            COALESCE(de.id_echantillon_sk, de_unknown.id_echantillon_sk) AS id_echantillon_sk,
            COALESCE(ds.id_statut_sk, ds_unknown.id_statut_sk) AS id_statut_sk,
            dda.id_demande_analyse_sk,
            1 AS nb_ordres,
            CASE
                WHEN REPLACE(REPLACE(TRIM(o.delai_prefixe_analyse_heure), ',', '.'), ' ', '') REGEXP '^-?[0-9]+([.][0-9]+)?$'
                THEN CAST(REPLACE(REPLACE(TRIM(o.delai_prefixe_analyse_heure), ',', '.'), ' ', '') AS DECIMAL(12,2))
                ELSE NULL
            END AS delai_prefixe_heure,
            o.duree_analyse_jours,
            o.delai_prise_en_charge_jours,
            LEFT(COALESCE(NULLIF(TRIM(o.ordre_reference), ''), NULLIF(TRIM(o.ordre_cle), ''), CAST(o.ordre_id AS CHAR)), 500) AS reference_ordre
        FROM labo_db1.ordres_analyses o
        INNER JOIN dim_demande_analyse dda
            ON dda.id_demande_analyse_bk = o.demande_id
        LEFT JOIN dim_temps tdem
            ON tdem.date_complete = DATE(o.date_demande)
        LEFT JOIN dim_temps trec
            ON trec.date_complete = DATE(o.date_reception)
        LEFT JOIN dim_temps taff
            ON taff.date_complete = DATE(o.date_affectation)
        LEFT JOIN dim_temps tfin
            ON tfin.date_complete = DATE(o.date_fin)
        LEFT JOIN dim_temps tval
            ON tval.date_complete = DATE(o.date_validation)
        LEFT JOIN dim_client dc
            ON dc.id_client_bk = o.client_id
        INNER JOIN dim_client dc_unknown
            ON dc_unknown.id_client_bk = 0
        LEFT JOIN labo_db1.analyses ax
            ON ax.id_analyse = o.analyse_id
        LEFT JOIN dim_secteur dsec
            ON dsec.id_secteur_bk = ax.id_secteur
        INNER JOIN dim_secteur dsec_unknown
            ON dsec_unknown.id_secteur_bk = 0
        LEFT JOIN dim_analyse da_dim
            ON da_dim.id_analyse_bk = o.analyse_id
        INNER JOIN dim_analyse da_unknown
            ON da_unknown.id_analyse_bk = 0
        LEFT JOIN dim_laborantin dl
            ON dl.id_laborantin_bk = o.laborantin_id
        INNER JOIN dim_laborantin dl_unknown
            ON dl_unknown.id_laborantin_bk = 0
        LEFT JOIN dim_echantillon de
            ON de.id_echantillon_bk = o.echantillon_id
        INNER JOIN dim_echantillon de_unknown
            ON de_unknown.id_echantillon_bk = 0
        LEFT JOIN dim_statut ds
            ON ds.statut_ordre = CASE
                WHEN o.statut_code IS NULL OR TRIM(o.statut_code) = '' THEN 'Pas évaluée'
                WHEN REPLACE(TRIM(o.statut_code), ' ', '') = '100%' THEN 'Terminé'
                ELSE 'En cours'
            END
           AND ds.statut_demande IS NULL
           AND ds.statut_rapport IS NULL
           AND ds.faisabilite IS NULL
           AND ds.validite IS NULL
           AND ds.conformite IS NULL
           AND ds.statut_prediction IS NULL
        INNER JOIN dim_statut ds_unknown
            ON ds_unknown.statut_ordre = 'Inconnu'
           AND ds_unknown.statut_demande IS NULL
           AND ds_unknown.statut_rapport IS NULL
           AND ds_unknown.faisabilite IS NULL
           AND ds_unknown.validite IS NULL
           AND ds_unknown.conformite IS NULL
           AND ds_unknown.statut_prediction IS NULL
        WHERE o.ordre_id IS NOT NULL
          AND o.demande_id IS NOT NULL
    ", "INSERT fait_ordre_analyse");

    /* =========================================================
       FAIT RAPPORT
       Grain: one row per source rapport. Client, secteur and statut are non-blocking.
       ========================================================= */
    dw_exec($conn_dw, "
        INSERT INTO fait_rapport
        (
            id_rapport_bk,
            id_temps_creation,
            id_temps_validation,
            id_client_sk,
            id_secteur_sk,
            id_statut_sk,
            id_demande_analyse_sk,
            nb_rapports,
            rapport_provisoire_flag,
            rapport_final_flag,
            delai_creation_validation_jours,
            delai_total_demande_livraison_jours,
            reference_rapport
        )
        SELECT
            LEFT(CAST(r.id_rapport AS CHAR), 100) AS id_rapport_bk,
            tc.id_temps AS id_temps_creation,
            tv.id_temps AS id_temps_validation,
            COALESCE(dc.id_client_sk, dc_unknown.id_client_sk) AS id_client_sk,
            COALESCE(dsec.id_secteur_sk, dsec_unknown.id_secteur_sk) AS id_secteur_sk,
            COALESCE(ds.id_statut_sk, ds_unknown.id_statut_sk) AS id_statut_sk,
            dda.id_demande_analyse_sk,
            1 AS nb_rapports,
            CASE
                WHEN LOWER(COALESCE(r.type_rapport, '')) LIKE '%provis%'
                THEN 1 ELSE 0
            END AS rapport_provisoire_flag,
            CASE
                WHEN LOWER(COALESCE(r.type_rapport, '')) LIKE '%final%'
                  OR LOWER(COALESCE(r.type_rapport, '')) LIKE '%finale%'
                THEN 1 ELSE 0
            END AS rapport_final_flag,
            CASE
                WHEN r.date_creation IS NOT NULL
                 AND COALESCE(r.date_validation, d.date_validation, d.date_cloture) IS NOT NULL
                THEN TIMESTAMPDIFF(
                    DAY,
                    r.date_creation,
                    COALESCE(r.date_validation, d.date_validation, d.date_cloture)
                )
                ELSE NULL
            END AS delai_creation_validation_jours,
            CASE
                WHEN d.date_demande IS NOT NULL
                 AND COALESCE(
                        r.date_livraison,
                        r.date_livraison_interne,
                        r.date_validation,
                        d.date_validation,
                        d.date_cloture
                     ) IS NOT NULL
                THEN TIMESTAMPDIFF(
                    DAY,
                    d.date_demande,
                    COALESCE(
                        r.date_livraison,
                        r.date_livraison_interne,
                        r.date_validation,
                        d.date_validation,
                        d.date_cloture
                    )
                )
                ELSE NULL
            END AS delai_total_demande_livraison_jours,
            LEFT(COALESCE(NULLIF(TRIM(r.rapport), ''), NULLIF(TRIM(r.id_rapport), ''), 'SANS-REF'), 500) AS reference_rapport
        FROM labo_db1.rapports r
        INNER JOIN labo_db1.demande_analyse d
            ON d.id_demande = r.id_demande
        INNER JOIN dim_demande_analyse dda
            ON dda.id_demande_analyse_bk = r.id_demande
        LEFT JOIN dim_temps tc
            ON tc.date_complete = DATE(r.date_creation)
        LEFT JOIN dim_temps tv
            ON tv.date_complete = DATE(COALESCE(r.date_validation, d.date_validation, d.date_cloture))
        LEFT JOIN dim_client dc
            ON dc.id_client_bk = d.id_client
        INNER JOIN dim_client dc_unknown
            ON dc_unknown.id_client_bk = 0
        LEFT JOIN dim_secteur dsec
            ON dsec.id_secteur_bk = COALESCE(r.secteur_id, d.id_secteur)
        INNER JOIN dim_secteur dsec_unknown
            ON dsec_unknown.id_secteur_bk = 0
        LEFT JOIN dim_statut ds
            ON ds.statut_rapport = CASE
                WHEN r.statut IS NULL OR TRIM(r.statut) = '' THEN
                    CASE
                        WHEN r.date_validation IS NOT NULL
                          OR r.date_livraison IS NOT NULL
                          OR r.date_livraison_interne IS NOT NULL
                          OR d.date_validation IS NOT NULL
                          OR d.date_cloture IS NOT NULL
                        THEN 'Validé'
                        ELSE 'En cours'
                    END
                ELSE LEFT(r.statut, 100)
            END
           AND ds.statut_demande IS NULL
           AND ds.statut_ordre IS NULL
           AND ds.faisabilite IS NULL
           AND ds.validite IS NULL
           AND ds.conformite IS NULL
           AND ds.statut_prediction IS NULL
        INNER JOIN dim_statut ds_unknown
            ON ds_unknown.statut_rapport = 'Inconnu'
           AND ds_unknown.statut_demande IS NULL
           AND ds_unknown.statut_ordre IS NULL
           AND ds_unknown.faisabilite IS NULL
           AND ds_unknown.validite IS NULL
           AND ds_unknown.conformite IS NULL
           AND ds_unknown.statut_prediction IS NULL
        WHERE r.id_rapport IS NOT NULL
          AND r.id_demande IS NOT NULL
    ", "INSERT fait_rapport");

    /* =========================================================
       FAIT RESULTAT ANALYSE
       Grain: aggregate by final fact key.
       ========================================================= */
    dw_exec($conn_dw, "
        INSERT INTO fait_resultat_analyse
        (
            id_temps_resultat,
            id_client_sk,
            id_secteur_sk,
            id_analyse_sk,
            id_laborantin_sk,
            id_echantillon_sk,
            id_statut_sk,
            id_demande_analyse_sk,
            nb_resultats,
            resultat_num,
            nom_resultat,
            is_accredited
        )
        SELECT
            x.id_temps_resultat,
            x.id_client_sk,
            x.id_secteur_sk,
            x.id_analyse_sk,
            x.id_laborantin_sk,
            x.id_echantillon_sk,
            x.id_statut_sk,
            x.id_demande_analyse_sk,
            COUNT(*) AS nb_resultats,
            MAX(x.resultat_num) AS resultat_num,
            MAX(x.nom_resultat) AS nom_resultat,
            MAX(x.is_accredited) AS is_accredited
        FROM (
            SELECT
                COALESCE(tt.id_temps, 0) AS id_temps_resultat,
                COALESCE(dc.id_client_sk, dc_unknown.id_client_sk) AS id_client_sk,
                COALESCE(dsec.id_secteur_sk, dsec_unknown.id_secteur_sk) AS id_secteur_sk,
                COALESCE(da_dim.id_analyse_sk, da_unknown.id_analyse_sk) AS id_analyse_sk,
                COALESCE(dl.id_laborantin_sk, dl_unknown.id_laborantin_sk) AS id_laborantin_sk,
                COALESCE(de.id_echantillon_sk, de_unknown.id_echantillon_sk) AS id_echantillon_sk,
                COALESCE(ds.id_statut_sk, ds_unknown.id_statut_sk) AS id_statut_sk,
                dda.id_demande_analyse_sk,
                CASE
                    WHEN ra.resultat REGEXP '^-?[0-9]+([.][0-9]+)?$'
                    THEN CAST(ra.resultat AS DECIMAL(18,4))
                    ELSE NULL
                END AS resultat_num,
                CASE
                    WHEN ra.resultat IS NOT NULL
                     AND TRIM(ra.resultat) <> ''
                     AND ra.resultat NOT REGEXP '^-?[0-9]+([.][0-9]+)?$'
                    THEN LEFT(ra.resultat, 255)
                    ELSE NULL
                END AS nom_resultat,
                ra.is_accredited
            FROM labo_db1.resultat_analyse ra
            INNER JOIN dim_demande_analyse dda
                ON dda.id_demande_analyse_bk = ra.id_demande
            LEFT JOIN labo_db1.demande_analyse d
                ON d.id_demande = ra.id_demande
            LEFT JOIN labo_db1.ordres_analyses o
                ON o.ordre_id = ra.ordre_id
            LEFT JOIN dim_temps tt
                ON tt.date_complete = DATE(ra.created_at)
            LEFT JOIN dim_client dc
                ON dc.id_client_bk = d.id_client
            INNER JOIN dim_client dc_unknown
                ON dc_unknown.id_client_bk = 0
            LEFT JOIN labo_db1.analyses ax
                ON ax.id_analyse = COALESCE(ra.id_analyse, o.analyse_id)
            LEFT JOIN dim_secteur dsec
                ON dsec.id_secteur_bk = COALESCE(ax.id_secteur, d.id_secteur)
            INNER JOIN dim_secteur dsec_unknown
                ON dsec_unknown.id_secteur_bk = 0
            LEFT JOIN dim_analyse da_dim
                ON da_dim.id_analyse_bk = COALESCE(ra.id_analyse, o.analyse_id)
            INNER JOIN dim_analyse da_unknown
                ON da_unknown.id_analyse_bk = 0
            LEFT JOIN dim_laborantin dl
                ON dl.id_laborantin_bk = ra.id_laborantin
            INNER JOIN dim_laborantin dl_unknown
                ON dl_unknown.id_laborantin_bk = 0
            LEFT JOIN dim_echantillon de
                ON de.id_echantillon_bk = o.echantillon_id
            INNER JOIN dim_echantillon de_unknown
                ON de_unknown.id_echantillon_bk = 0
            LEFT JOIN dim_statut ds
                ON ds.statut_ordre = CASE
                    WHEN o.statut_code IS NULL OR TRIM(o.statut_code) = '' THEN 'Pas évaluée'
                    WHEN REPLACE(TRIM(o.statut_code), ' ', '') = '100%' THEN 'Terminé'
                    ELSE 'En cours'
                END
               AND ds.statut_demande IS NULL
               AND ds.statut_rapport IS NULL
               AND ds.faisabilite IS NULL
               AND ds.validite IS NULL
               AND ds.conformite IS NULL
               AND ds.statut_prediction IS NULL
            INNER JOIN dim_statut ds_unknown
                ON ds_unknown.statut_ordre = 'Inconnu'
               AND ds_unknown.statut_demande IS NULL
               AND ds_unknown.statut_rapport IS NULL
               AND ds_unknown.faisabilite IS NULL
               AND ds_unknown.validite IS NULL
               AND ds_unknown.conformite IS NULL
               AND ds_unknown.statut_prediction IS NULL
            WHERE ra.id_demande IS NOT NULL
        ) x
        GROUP BY
            x.id_temps_resultat,
            x.id_client_sk,
            x.id_secteur_sk,
            x.id_analyse_sk,
            x.id_laborantin_sk,
            x.id_echantillon_sk,
            x.id_statut_sk,
            x.id_demande_analyse_sk
    ", "INSERT fait_resultat_analyse");

    /* =========================================================
       FAIT PREDICTION - optional only if source columns exist.
       Normally ML loaders fill fait_prediction later.
       ========================================================= */
    if (dw_column_exists($conn, 'demande_analyse', 'date_livraison_estimee')) {
        $joursRestantsExpr = dw_column_exists($conn, 'demande_analyse', 'jours_restants_predits')
            ? 'd.jours_restants_predits'
            : 'TIMESTAMPDIFF(DAY, CURDATE(), d.date_livraison_estimee)';
        $joursRetardExpr = dw_column_exists($conn, 'demande_analyse', 'jours_retard_predit')
            ? 'd.jours_retard_predit'
            : 'GREATEST(TIMESTAMPDIFF(DAY, d.date_livraison_estimee, CURDATE()), 0)';
        $niveauRisqueExpr = dw_column_exists($conn, 'demande_analyse', 'niveau_risque')
            ? "CASE WHEN d.niveau_risque IS NULL OR TRIM(d.niveau_risque) = '' THEN 'PREDICTION' ELSE LEFT(d.niveau_risque, 50) END"
            : "'PREDICTION'";

        dw_exec($conn_dw, "
            INSERT INTO fait_prediction
            (
                id_temps_prediction,
                id_modele_sk,
                id_client_sk,
                id_secteur_sk,
                id_statut_sk,
                id_demande_analyse_sk,
                type_prediction,
                delai_predit_jours,
                date_livraison_estimee,
                jours_restants_predits,
                jours_retard_predit,
                delai_reel_jours,
                erreur_absolue,
                erreur_signee,
                retard_predit_flag,
                retard_reel_flag,
                niveau_risque,
                date_chargement
            )
            SELECT
                COALESCE(tp.id_temps, 0) AS id_temps_prediction,
                dm.id_modele_sk,
                COALESCE(dc.id_client_sk, dc_unknown.id_client_sk) AS id_client_sk,
                COALESCE(dsec.id_secteur_sk, dsec_unknown.id_secteur_sk) AS id_secteur_sk,
                COALESCE(ds.id_statut_sk, ds_unknown.id_statut_sk) AS id_statut_sk,
                dda.id_demande_analyse_sk,
                'EN_COURS' AS type_prediction,
                CASE
                    WHEN d.date_demande IS NOT NULL AND d.date_livraison_estimee IS NOT NULL
                    THEN TIMESTAMPDIFF(DAY, d.date_demande, d.date_livraison_estimee)
                    ELSE NULL
                END AS delai_predit_jours,
                DATE(d.date_livraison_estimee) AS date_livraison_estimee,
                {$joursRestantsExpr} AS jours_restants_predits,
                {$joursRetardExpr} AS jours_retard_predit,
                CASE
                    WHEN d.date_demande IS NOT NULL AND d.date_cloture IS NOT NULL
                    THEN TIMESTAMPDIFF(DAY, d.date_demande, d.date_cloture)
                    ELSE NULL
                END AS delai_reel_jours,
                CASE
                    WHEN d.date_demande IS NOT NULL AND d.date_livraison_estimee IS NOT NULL AND d.date_cloture IS NOT NULL
                    THEN ABS(TIMESTAMPDIFF(DAY, d.date_demande, d.date_livraison_estimee) - TIMESTAMPDIFF(DAY, d.date_demande, d.date_cloture))
                    ELSE NULL
                END AS erreur_absolue,
                CASE
                    WHEN d.date_demande IS NOT NULL AND d.date_livraison_estimee IS NOT NULL AND d.date_cloture IS NOT NULL
                    THEN TIMESTAMPDIFF(DAY, d.date_demande, d.date_livraison_estimee) - TIMESTAMPDIFF(DAY, d.date_demande, d.date_cloture)
                    ELSE NULL
                END AS erreur_signee,
                CASE WHEN {$joursRetardExpr} > 0 THEN 1 ELSE 0 END AS retard_predit_flag,
                CASE
                    WHEN d.date_livraison_estimee IS NOT NULL AND d.date_cloture IS NOT NULL AND d.date_cloture > d.date_livraison_estimee
                    THEN 1 ELSE 0
                END AS retard_reel_flag,
                {$niveauRisqueExpr} AS niveau_risque,
                NOW() AS date_chargement
            FROM labo_db1.demande_analyse d
            INNER JOIN dim_temps tp
                ON tp.date_complete = CURDATE()
            INNER JOIN dim_modele dm
                ON BINARY dm.model_name = BINARY 'RandomForestRegressor'
               AND BINARY dm.variant = BINARY 'v1'
               AND BINARY dm.stage_prediction = BINARY 'global'
            INNER JOIN dim_demande_analyse dda
                ON dda.id_demande_analyse_bk = d.id_demande
            LEFT JOIN dim_client dc
                ON dc.id_client_bk = d.id_client
            INNER JOIN dim_client dc_unknown
                ON dc_unknown.id_client_bk = 0
            LEFT JOIN dim_secteur dsec
                ON dsec.id_secteur_bk = d.id_secteur
            INNER JOIN dim_secteur dsec_unknown
                ON dsec_unknown.id_secteur_bk = 0
            LEFT JOIN dim_statut ds
                ON ds.statut_prediction = 'PREDICTION'
               AND ds.statut_demande IS NULL
               AND ds.statut_ordre IS NULL
               AND ds.statut_rapport IS NULL
               AND ds.faisabilite IS NULL
               AND ds.validite IS NULL
               AND ds.conformite IS NULL
            INNER JOIN dim_statut ds_unknown
                ON ds_unknown.statut_prediction = 'Inconnu'
               AND ds_unknown.statut_demande IS NULL
               AND ds_unknown.statut_ordre IS NULL
               AND ds_unknown.statut_rapport IS NULL
               AND ds_unknown.faisabilite IS NULL
               AND ds_unknown.validite IS NULL
               AND ds_unknown.conformite IS NULL
            WHERE d.date_livraison_estimee IS NOT NULL
        ", "INSERT fait_prediction");
    }

    $conn_dw->commit();

    echo "Import DW terminé\n";
    echo "dim_temps: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM dim_temps", "COUNT dim_temps") . "\n";
    echo "dim_client: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM dim_client", "COUNT dim_client") . "\n";
    echo "dim_secteur: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM dim_secteur", "COUNT dim_secteur") . "\n";
    echo "dim_analyse: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM dim_analyse", "COUNT dim_analyse") . "\n";
    echo "dim_laborantin: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM dim_laborantin", "COUNT dim_laborantin") . "\n";
    echo "dim_demande_analyse: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM dim_demande_analyse", "COUNT dim_demande_analyse") . "\n";
    echo "dim_demande_client: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM dim_demande_client", "COUNT dim_demande_client") . "\n";
    echo "dim_echantillon: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM dim_echantillon", "COUNT dim_echantillon") . "\n";
    echo "dim_statut: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM dim_statut", "COUNT dim_statut") . "\n";
    echo "dim_modele: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM dim_modele", "COUNT dim_modele") . "\n";
    echo "dim_variable: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM dim_variable", "COUNT dim_variable") . "\n";
    echo "fait_demande_client: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM fait_demande_client", "COUNT fait_demande_client") . "\n";
    echo "fait_demande_analyse: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM fait_demande_analyse", "COUNT fait_demande_analyse") . "\n";
    echo "fait_facturation: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM fait_facturation", "COUNT fait_facturation") . "\n";
    echo "fait_ordre_analyse: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM fait_ordre_analyse", "COUNT fait_ordre_analyse") . "\n";
    echo "fait_rapport: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM fait_rapport", "COUNT fait_rapport") . "\n";
    echo "fait_resultat_analyse: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM fait_resultat_analyse", "COUNT fait_resultat_analyse") . "\n";
    echo "fait_prediction: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM fait_prediction", "COUNT fait_prediction") . "\n";
    echo "fait_importance_variable: " . dw_scalar($conn_dw, "SELECT COUNT(*) FROM fait_importance_variable", "COUNT fait_importance_variable") . "\n";

} catch (Exception $e) {
    if (isset($conn_dw)) {
        @$conn_dw->rollback();
    }
    echo "Erreur import_dw.php : " . $e->getMessage();
}
?>