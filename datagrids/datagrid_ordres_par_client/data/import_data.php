<?php
require_once "../Back_End/db.php"; 
set_time_limit(0); 
ini_set('memory_limit', '512M');

$fichier = "dump(13).csv"; 

try {
    if (!file_exists($fichier)) die("ERREUR : $fichier introuvable.");
    
    $conn->set_charset("utf8mb4");
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    $handle = fopen($fichier, "r");
    fgetcsv($handle);
    $conn->begin_transaction();
    $sql = "INSERT INTO ordres_analyses 
            (ordre_cle, ordre_reference, client_id, client_nom, demande_analyse, analyse_nom, laboratin, fiche_paillasse_id, echontillon, resultat, date_demande, conformite_libelle, statut) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            ordre_reference = VALUES(ordre_reference),
            client_id = VALUES(client_id),
            client_nom = VALUES(client_nom),
            demande_analyse = VALUES(demande_analyse),
            analyse_nom = VALUES(analyse_nom),
            laboratin = VALUES(laboratin),
            fiche_paillasse_id = VALUES(fiche_paillasse_id),
            echontillon = VALUES(echontillon),
            resultat = VALUES(resultat),
            date_demande = VALUES(date_demande),
            conformite_libelle = VALUES(conformite_libelle),
            statut = VALUES(statut)";

    $stmt = $conn->prepare($sql);
    $total_lus = 0;
    $importes = 0;
    while (($row = fgetcsv($handle, 0, ",")) !== FALSE) {
        $total_lus++;
        foreach ($row as $k => $v) {
            $v = trim($v, " \t\n\r\0\x0B");
            $row[$k] = ($v === '\N' || $v === '') ? null : $v;
        }

        $ordre_cle = $row[0] ?? null;
        if (!$ordre_cle) continue;
        $ordre_reference = $row[2] ?? null;
        $client_id       = isset($row[4]) ? intval(preg_replace('/[^0-9]/', '', $row[4])) : null;
        $client_raw      = $row[5] ?? null;
        $client_nom      = null;
        if ($client_raw) {
            $enc = mb_detect_encoding($client_raw, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            $client_nom = mb_convert_encoding($client_raw, "UTF-8", $enc ? $enc : "Windows-1252");
        }
        $demande_analyse = $row[6] ?? null;
        $analyse_nom     = $row[9] ?? null;
        $laboratin       = $row[20] ?? null;
        $fiche_id        = isset($row[17]) ? intval(preg_replace('/[^0-9]/', '', $row[17])) : null;
        $echontillon     = isset($row[12]) ? intval(preg_replace('/[^0-9]/', '', $row[12])) : null;
        $res_raw         = $row[25] ?? null;
        $resultat        = null;
        if ($res_raw) {
            $enc = mb_detect_encoding($res_raw, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            $res_utf8 = mb_convert_encoding($res_raw, "UTF-8", $enc ? $enc : "Windows-1252");
            $resultat = strip_tags($res_utf8);
        }
        $date_demande        = $row[7] ?? null;
        $conformite      = strip_tags($row[24] ?? 'Non évalué');
        $statut_final    = strip_tags($row[22] ?? 'En cours');
        $stmt->bind_param("ssissssiissss", 
            $ordre_cle,
            $ordre_reference,
            $client_id,
            $client_nom,
            $demande_analyse,
            $analyse_nom,
            $laboratin,
            $fiche_id,
            $echontillon,
            $resultat,
            $date_demande,
            $conformite,
            $statut_final
        );

        if ($stmt->execute()) {
            $importes++;
        }

        if ($total_lus % 1000 == 0) {
            $conn->commit();
            $conn->begin_transaction();
        }
    }

    $conn->commit();
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    fclose($handle);
    echo "Succès : $importes lignes traitées dans la table ordres_analyses.";

} catch (Exception $e) {
    if (isset($conn)) $conn->rollback();
    echo "ERREUR : " . $e->getMessage();
}
?>