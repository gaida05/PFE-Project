<?php
set_time_limit(0);
ini_set('memory_limit', '512M');

function runImportFile(string $fullPath, string $label): void {
    echo "----------------------------------------\n";
    echo "Lancement de : $label\n";
    echo "----------------------------------------\n";

    if (!file_exists($fullPath)) {
        echo "ERREUR : fichier introuvable -> $fullPath\n\n";
        return;
    }

    try {
        include $fullPath;
        echo "\n";
    } catch (Throwable $e) {
        echo 'ERREUR dans ' . $label . ' : ' . $e->getMessage() . "\n\n";
    }
}

function syncDemandesAnalyseFromDemandeClient(): void {
    require __DIR__ . '/../config/db.php';

    echo "Synchronisation clients + dates vers demande_analyse...\n";

    $sql = "
        UPDATE demande_analyse da
        INNER JOIN (
            SELECT
                dc.demande_id,
                MAX(dc.client_id) AS client_id,
                MAX(dc.date_reception) AS date_reception_full,
                MAX(dc.date_verif) AS date_verif_full
            FROM demande_client dc
            GROUP BY dc.demande_id
        ) x ON x.demande_id = da.id_demande
        SET
            da.id_client = CASE
                WHEN (da.id_client IS NULL OR da.id_client = 0) AND x.client_id IS NOT NULL
                THEN x.client_id
                ELSE da.id_client
            END,
            da.date_creation = CASE
                WHEN da.date_creation IS NULL AND x.date_reception_full IS NOT NULL
                THEN x.date_reception_full
                ELSE da.date_creation
            END,
            da.date_cloture = CASE
                WHEN da.date_cloture IS NULL AND x.date_verif_full IS NOT NULL
                THEN x.date_verif_full
                ELSE da.date_cloture
            END,
            da.date_reception = CASE
                WHEN da.date_reception IS NULL AND x.date_reception_full IS NOT NULL
                THEN x.date_reception_full
                ELSE da.date_reception
            END,
            da.date_debut_analyse = CASE
                WHEN da.date_debut_analyse IS NULL AND x.date_reception_full IS NOT NULL
                THEN DATE(x.date_reception_full)
                ELSE da.date_debut_analyse
            END,
            da.date_fin_analyse = CASE
                WHEN da.date_fin_analyse IS NULL AND x.date_verif_full IS NOT NULL
                THEN DATE(x.date_verif_full)
                ELSE da.date_fin_analyse
            END
    ";

    if (!$conn->query($sql)) {
        throw new Exception('Erreur sync demande_analyse : ' . $conn->error);
    }

    echo 'Synchronisation terminée : ' . $conn->affected_rows . " ligne(s) mise(s) à jour.\n";
}

echo "<pre>";
$baseDir = __DIR__;
runImportFile($baseDir . '/import.php', 'import.php');
runImportFile($baseDir . '/import_file.php', 'import_file.php');

try {
    echo "----------------------------------------\n";
    echo "Lancement de : syncDemandesAnalyseFromDemandeClient\n";
    echo "----------------------------------------\n";
    syncDemandesAnalyseFromDemandeClient();
    echo "\n";
} catch (Throwable $e) {
    echo 'ERREUR dans syncDemandesAnalyseFromDemandeClient : ' . $e->getMessage() . "\n\n";
}

runImportFile($baseDir . '/import_data.php', 'import_data.php');

echo "========================================\n";
echo "Fin de import_core.php\n";
echo "========================================\n";
echo "</pre>";
?>
