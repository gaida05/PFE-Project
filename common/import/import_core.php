<?php
set_time_limit(0);
ini_set('memory_limit', '1024M');
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/db_labo_dw.php';
function runImportFile($fullPath, $label) {
    echo "----------------------------------------\n";
    echo "Lancement de : $label\n";
    echo "----------------------------------------\n";
    if (!file_exists($fullPath)) {
        echo "ERREUR : fichier introuvable -> $fullPath\n\n";
        return;
    }
    try {
        require $fullPath;
        echo "\n";
    } catch (Throwable $e) {
        echo "ERREUR dans $label : " . $e->getMessage() . "\n\n";
    }
}
function resetLaboDb($conn) {
    echo "----------------------------------------\n";
    echo "Reset labo_db1\n";
    echo "----------------------------------------\n";
    $conn->set_charset("utf8mb4");
    $conn->begin_transaction();
    try {
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $tables = [
            'rapport_stabilite_critere',
            'rapport_stabilite_ligne',
            'rapport_stabilite',
            'resultat_analyse',
            'ordres_analyses',
            'fiches_paillasse',
            'rapports',
            'echantillons',
            'demande_analyse',
            'demande_client',
            'devis',
            'criteres',
            'analyses',
            'laborantin',
            'secteur',
            'client'
        ];
        foreach ($tables as $table) {
            if (!$conn->query("TRUNCATE TABLE {$table}")) {
                throw new Exception("Erreur TRUNCATE {$table} : " . $conn->error);
            }
        }
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $conn->commit();
        echo "Reset labo_db1 terminé\n\n";
    } catch (Throwable $e) {
        @$conn->rollback();
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        throw $e;
    }
}
function resetDwDb($conn_dw) {
    echo "----------------------------------------\n";
    echo "Préparation reset dw_labo\n";
    echo "----------------------------------------\n";

    $conn_dw->set_charset("utf8mb4");
    $conn_dw->begin_transaction();

    try {
        $conn_dw->query("SET FOREIGN_KEY_CHECKS = 0");

      $tables = [
            'fait_importance_variable',
            'fait_prediction',
            'fait_facturation',
            'fait_rapport',
            'fait_resultat_analyse',
            'fait_ordre_analyse',
            'fait_demande_analyse',
            'fait_demande_client',

            'dim_variable',
            'dim_modele',
            'dim_laborantin',
            'dim_analyse',
            'dim_echantillon',
            'dim_demande_analyse',
            'dim_demande_client',
            'dim_statut',
            'dim_secteur',
            'dim_client',
            'dim_temps'
        ];
        foreach ($tables as $table) {
            if (!$conn_dw->query("TRUNCATE TABLE {$table}")) {
                throw new Exception("Erreur TRUNCATE {$table} : " . $conn_dw->error);
            }
        }
        $conn_dw->query("SET FOREIGN_KEY_CHECKS = 1");
        $conn_dw->commit();
        echo "Reset dw_labo terminé\n\n";
    } catch (Throwable $e) {
        @$conn_dw->rollback();
        $conn_dw->query("SET FOREIGN_KEY_CHECKS = 1");
        throw $e;
    }
}
echo "<pre>";
try {
    $baseDir = __DIR__;

    resetLaboDb($conn);
    runImportFile($baseDir . '/import.php', 'import.php');
    runImportFile($baseDir . '/import_file.php', 'import_file.php');
    runImportFile($baseDir . '/import_data.php', 'import_data.php');
    runImportFile($baseDir . '/import_rapport_stabilite.php', 'import_stabilite.php');
    resetDwDb($conn_dw);
    runImportFile($baseDir . '/import_dw.php', 'import_dw_dim_statut_fixed.php');
    
    echo "========================================\n";
    echo "Fin de import_core.php\n";
    echo "========================================\n";
} catch (Throwable $e) {
    echo "ERREUR GLOBALE : " . $e->getMessage() . "\n";
}
echo "</pre>";
?>