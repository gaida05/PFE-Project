<?php
require_once "../Back_End/db.php";
set_time_limit(0);
ini_set('memory_limit', '512M');

function normalizeValue($value){
    if ($value === null) return null;
    $value = trim($value);
    $value = trim($value, "\"");
    if ($value === '' || $value === '\N') return null;
    if (!mb_detect_encoding($value, 'UTF-8', true)) {
        $value = utf8_encode($value);
    }
    return $value;
}

function cleanString($value){
    $value = normalizeValue($value);
    if ($value === null) return null;
    return strip_tags($value);
}

function cleanInt($value){
    $value = normalizeValue($value);
    if ($value === null) return null;
    return is_numeric($value) ? (int)$value : null;
}

function cleanDateTime($value){
    $value = normalizeValue($value);
    if ($value === null) return null;
    $timestamp = strtotime($value);
    return $timestamp ? date("Y-m-d H:i:s", $timestamp) : null;
}

function detectDelimiter($file){
    $handle = fopen($file, 'r');
    if (!$handle) {
        throw new Exception("Impossible d'ouvrir le fichier : " . $file);
    }

    $firstLine = fgets($handle);
    fclose($handle);

    if ($firstLine === false) {
        throw new Exception("Fichier vide : " . $file);
    }

    $delimiters = [",", ";", "\t", "|"];
    $bestDelimiter = ",";
    $bestCount = 0;

    foreach ($delimiters as $delimiter) {
        $count = count(str_getcsv($firstLine, $delimiter));
        if ($count > $bestCount) {
            $bestCount = $count;
            $bestDelimiter = $delimiter;
        }
    }

    return $bestDelimiter;
}

function buildLaborantinName($idLaborantin, $nomLaborantin, &$laborantinMap, &$laborantinCounter){
    $nomLaborantin = cleanString($nomLaborantin);
    $idLaborantin = cleanInt($idLaborantin);

    if ($nomLaborantin !== null) return $nomLaborantin;

    if ($idLaborantin !== null) {
        if (!isset($laborantinMap[$idLaborantin])) {
            $laborantinMap[$idLaborantin] = "laborantin anonyme " . $laborantinCounter++;
        }
        return $laborantinMap[$idLaborantin];
    }

    return "laborantin anonyme";
}

function insererDemande($conn, $data){
    $id_demande = cleanInt($data['id']);
    if ($id_demande === null) return;

    $ref_demande   = cleanString($data['ref']);
    $id_secteur    = cleanInt($data['id_secteur']);
    $date_demande  = cleanDateTime($data['date_demande']);
    $date_creation = cleanDateTime($data['date_creation']);
    $date_cloture  = cleanDateTime($data['date_cloture']);
    $statut        = cleanString($data['statut']);
    $nom_secteur   = cleanString($data['nom_secteur']);

    $sql = "INSERT INTO demande_analyse
            (id_demande, ref_demande, id_secteur, date_demande, date_creation, date_cloture, statut, nom_secteur)
            VALUES (?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                ref_demande = COALESCE(VALUES(ref_demande), ref_demande),
                id_secteur = COALESCE(VALUES(id_secteur), id_secteur),
                date_demande = COALESCE(VALUES(date_demande), date_demande),
                date_creation = COALESCE(VALUES(date_creation), date_creation),
                date_cloture = COALESCE(VALUES(date_cloture), date_cloture),
                statut = COALESCE(VALUES(statut), statut),
                nom_secteur = COALESCE(VALUES(nom_secteur), nom_secteur)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erreur prepare demande_analyse : " . $conn->error);
    }

    $stmt->bind_param(
        "isisssss",
        $id_demande,
        $ref_demande,
        $id_secteur,
        $date_demande,
        $date_creation,
        $date_cloture,
        $statut,
        $nom_secteur
    );

    if (!$stmt->execute()) {
        throw new Exception("Erreur execute demande_analyse : " . $stmt->error);
    }

    $stmt->close();
}

function importerDump13($conn, $file){
    $delimiter = detectDelimiter($file);
    $handle = fopen($file, "r");
    if (!$handle) {
        throw new Exception("Impossible d'ouvrir " . $file);
    }

    $i = 0;
    $echCache = [];
    $ficheCache = [];
    $laborantinMap = [];
    $laborantinCounter = 1;

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if ($i++ === 0) continue;
        if (count($row) < 27) continue;

        $id_demande = cleanInt($row[3]);

        insererDemande($conn, [
            'id'            => $row[3],
            'ref'           => $row[6],
            'id_secteur'    => $row[18],
            'date_demande'  => $row[7],
            'date_creation' => $row[15],
            'date_cloture'  => $row[14],
            'statut'        => $row[22],
            'nom_secteur'   => $row[19]
        ]);

        $id_ech = cleanInt($row[12]);
        if ($id_ech !== null && !isset($echCache[$id_ech])) {
            $id_demande_var = $id_demande;
            $type_echantillon = cleanString($row[11]);
            $est_conforme = cleanString($row[24]);

            $sql = "INSERT IGNORE INTO echantillons
                    (id_echantillon, id_demande, type_echantillon, est_conforme)
                    VALUES (?,?,?,?)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Erreur prepare echantillons : " . $conn->error);
            }

            $stmt->bind_param("iiss", $id_ech, $id_demande_var, $type_echantillon, $est_conforme);

            if (!$stmt->execute()) {
                throw new Exception("Erreur execute echantillons : " . $stmt->error);
            }

            $stmt->close();
            $echCache[$id_ech] = true;
        }

        $id_fiche = cleanInt($row[17]);
        if ($id_fiche !== null && !isset($ficheCache[$id_fiche])) {
            $id_demande_var = $id_demande;
            $id_laborantin = cleanInt($row[16]);
            $nom_laborantin = buildLaborantinName($row[16], $row[20], $laborantinMap, $laborantinCounter);
            $statut_fiche = cleanString($row[22]);
            $date_creation = cleanDateTime($row[15]);
            $date_fin = cleanDateTime($row[14]);
            $id_secteur = cleanInt($row[18]);
            $nom_secteur = cleanString($row[19]);

            $sql = "INSERT IGNORE INTO fiches_paillasse
                    (id_fiche, id_demande, id_laborantin, nom_laborantin, statut, date_creation, date_fin, id_secteur, nom_secteur)
                    VALUES (?,?,?,?,?,?,?,?,?)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Erreur prepare fiches_paillasse : " . $conn->error);
            }

            $stmt->bind_param(
                "iiissssis",
                $id_fiche,
                $id_demande_var,
                $id_laborantin,
                $nom_laborantin,
                $statut_fiche,
                $date_creation,
                $date_fin,
                $id_secteur,
                $nom_secteur
            );

            if (!$stmt->execute()) {
                throw new Exception("Erreur execute fiches_paillasse : " . $stmt->error);
            }

            $stmt->close();
            $ficheCache[$id_fiche] = true;
        }
    }

    fclose($handle);
}

function importerDump11($conn, $file){
    $delimiter = detectDelimiter($file);
    $handle = fopen($file, "r");
    if (!$handle) {
        throw new Exception("Impossible d'ouvrir " . $file);
    }

    $i = 0;
    $provCounter = 100000;
    $finalCounter = 200000;
    $rapportCache = [];

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if ($i++ === 0) continue;
        if (count($row) < 16) continue;

        $id_demande = cleanInt($row[0]);

        insererDemande($conn, [
            'id'            => $row[0],
            'ref'           => $row[1],
            'id_secteur'    => $row[9],
            'date_demande'  => $row[15],
            'date_creation' => null,
            'date_cloture'  => null,
            'statut'        => $row[20] ?? null,
            'nom_secteur'   => $row[10] ?? null
        ]);

        $sql = "INSERT IGNORE INTO rapports
                (id_rapport, id_demande, type_rapport, rapport, date_edition, statut, secteur_id, scteur_nom)
                VALUES (?,?,?,?,?,?,?,?)";

        $id_csv = cleanString($row[11] ?? null);
        $rapport_prov = cleanString($row[12] ?? null);
        $keyProv = $id_demande . '_Provisoire';

        if (!(empty($id_csv) && empty($rapport_prov)) && !isset($rapportCache[$keyProv])) {
            $id_rapport = !empty($id_csv) ? $id_csv : "gen_" . $provCounter++;
            $id_demande_var = $id_demande;
            $type_rapport = "Provisoire";
            $rapport = $rapport_prov;
            $date_edition = null;
            $statut = cleanString($row[20] ?? null);
            $secteur_id = cleanInt($row[9] ?? null);
            $secteur_nom = cleanString($row[10] ?? null);

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Erreur prepare rapports provisoires : " . $conn->error);
            }

            $stmt->bind_param(
                "sisssiss",
                $id_rapport,
                $id_demande_var,
                $type_rapport,
                $rapport,
                $date_edition,
                $statut,
                $secteur_id,
                $secteur_nom
            );

            if (!$stmt->execute()) {
                throw new Exception("Erreur execute rapports provisoires : " . $stmt->error);
            }

            $stmt->close();
            $rapportCache[$keyProv] = true;
        }

        $id_csv_final = cleanString($row[13] ?? null);
        $rapport_final = cleanString($row[14] ?? null);
        $keyFinal = $id_demande . '_Final';

        if (!(empty($id_csv_final) && empty($rapport_final)) && !isset($rapportCache[$keyFinal])) {
            $id_rapport = !empty($id_csv_final) ? $id_csv_final : "gen_" . $finalCounter++;
            $id_demande_var = $id_demande;
            $type_rapport = "Final";
            $rapport = $rapport_final;
            $date_edition = null;
            $statut = cleanString($row[20] ?? null);
            $secteur_id = cleanInt($row[9] ?? null);
            $secteur_nom = cleanString($row[10] ?? null);

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Erreur prepare rapports finaux : " . $conn->error);
            }

            $stmt->bind_param(
                "sisssiss",
                $id_rapport,
                $id_demande_var,
                $type_rapport,
                $rapport,
                $date_edition,
                $statut,
                $secteur_id,
                $secteur_nom
            );

            if (!$stmt->execute()) {
                throw new Exception("Erreur execute rapports finaux : " . $stmt->error);
            }

            $stmt->close();
            $rapportCache[$keyFinal] = true;
        }
    }

    fclose($handle);
}

try {
    if (!file_exists("dump(11).csv")) {
        throw new Exception("dump(11).csv non trouvé");
    }

    if (!file_exists("dump(13).csv")) {
        throw new Exception("dump(13).csv non trouvé");
    }

    $conn->set_charset("utf8mb4");

    echo "Début import...<br>";
    echo "Séparateur dump11 : [" . htmlspecialchars(detectDelimiter("dump(11).csv")) . "]<br>";
    echo "Séparateur dump13 : [" . htmlspecialchars(detectDelimiter("dump(13).csv")) . "]<br>";

    importerDump11($conn, "dump(11).csv");
    importerDump13($conn, "dump(13).csv");

    echo "Import terminé avec succès";
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?>