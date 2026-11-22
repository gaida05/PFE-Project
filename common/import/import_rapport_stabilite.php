<?php
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/clean_helpers.php';
require_once __DIR__ . '/csv_helpers.php';
require_once __DIR__ . '/transform_helpers.php';

set_time_limit(0);
ini_set('memory_limit', '1024M');

function st_firstNonEmpty(array $row, array $keys){
    foreach ($keys as $k) {
        if (array_key_exists($k, $row)) {
            $v = cleanString($row[$k]);
            if ($v !== null && $v !== '') return $v;
        }
    }
    return null;
}

function st_firstInt(array $row, array $keys){
    foreach ($keys as $k) {
        if (array_key_exists($k, $row)) {
            $v = cleanInt($row[$k]);
            if ($v !== null) return $v;
        }
    }
    return null;
}

function st_buildDecisionText(array $r){
    $parts = [];
    foreach ([
        st_firstNonEmpty($r, ['critere_titre', 'label']),
        st_firstNonEmpty($r, ['des_critere', 'description']),
        st_firstNonEmpty($r, ['critere_resultat', 'commentaire', 'ra_fr_commentaire']),
        st_firstNonEmpty($r, ['critere_rule', 'code'])
    ] as $p) {
        if ($p !== null && $p !== '') $parts[] = $p;
    }
    return !empty($parts) ? implode(' | ', array_unique($parts)) : null;
}

function st_upsertCritere($conn, $r){
    $critere_id = st_firstInt($r, ['critere_id', 'critereid', 'id_critere']);
    if ($critere_id === null) return null;

    $code = safeVarchar(st_firstNonEmpty($r, ['critere_rule', 'code']), 100);
    if ($code === null) $code = 'CRIT-' . $critere_id;

    $label = safeVarchar(st_firstNonEmpty($r, ['critere_titre', 'label']), 255);
    if ($label === null) $label = 'Critere ' . $critere_id;

    $description = cleanString(st_firstNonEmpty($r, ['des_critere', 'description', 'critere_resultat', 'commentaire']));

    $sql = "INSERT INTO criteres (id, code, label, description)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                code = COALESCE(VALUES(code), code),
                label = COALESCE(VALUES(label), label),
                description = COALESCE(VALUES(description), description)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare critere stabilite : " . $conn->error);
    $stmt->bind_param("isss", $critere_id, $code, $label, $description);
    if (!$stmt->execute()) throw new Exception("Erreur execute critere stabilite : " . $stmt->error);
    $stmt->close();

    return $critere_id;
}

function st_ensureRapportExists($conn, $rapport_id){
    $rapport_id = cleanString($rapport_id);
    if ($rapport_id === null) return false;

    $sql = "SELECT id_rapport FROM rapports WHERE id_rapport = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare check rapport : " . $conn->error);
    $stmt->bind_param("s", $rapport_id);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (bool)$exists;
}

function st_upsertRapportStabilite($conn, $rapport_id, array $r){
    $norme_reference = safeVarchar(st_firstNonEmpty($r, ['norme_reference', 'norme', 'norme_stabilite']), 255) ?: 'NF V08 408-1997';
    $declaration_text = cleanString(st_firstNonEmpty($r, ['declaration_text', 'declaration', 'declaration_stabilite']));
    $note_1 = cleanString(st_firstNonEmpty($r, ['commentaire', 'ra_fr_commentaire', 'note_1']));
    $note_2 = cleanString(st_firstNonEmpty($r, ['description', 'des_critere', 'note_2']));

    $sql = "INSERT INTO rapport_stabilite
            (rapport_id, norme_reference, declaration_text, note_1, note_2)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                norme_reference = COALESCE(VALUES(norme_reference), norme_reference),
                declaration_text = COALESCE(VALUES(declaration_text), declaration_text),
                note_1 = COALESCE(VALUES(note_1), note_1),
                note_2 = COALESCE(VALUES(note_2), note_2)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare rapport_stabilite : " . $conn->error);
    $stmt->bind_param("sssss", $rapport_id, $norme_reference, $declaration_text, $note_1, $note_2);
    if (!$stmt->execute()) throw new Exception("Erreur execute rapport_stabilite : " . $stmt->error);
    $stmt->close();

    $stmt2 = $conn->prepare("SELECT id FROM rapport_stabilite WHERE rapport_id = ? LIMIT 1");
    if (!$stmt2) throw new Exception("Erreur prepare read rapport_stabilite : " . $conn->error);
    $stmt2->bind_param("s", $rapport_id);
    $stmt2->execute();
    $row = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    return $row ? (int)$row['id'] : null;
}

function st_upsertLigne($conn, $stabilite_id, array $r, $ordre_affichage){
    $parametre = safeVarchar(st_firstNonEmpty($r, ['parametre', 'nom_analyse', 'analyse_nom']), 255);
    if ($parametre === null) $parametre = 'Parametre';

    $sous_parametre = safeVarchar(st_firstNonEmpty($r, ['sous_parametre', 'critere_titre', 'label']), 255);
    $incubation_ambiante = cleanString(st_firstNonEmpty($r, ['incubation_ambiante', 'ra_incubation_ambiante']));
    $incubation_37c = cleanString(st_firstNonEmpty($r, ['incubation_37c', 'ra_incubation_37c']));
    $incubation_55c = cleanString(st_firstNonEmpty($r, ['incubation_55c', 'ra_incubation_55c']));
    $critere_decision = st_buildDecisionText($r);
    $critere_id = st_firstInt($r, ['critere_id', 'critereid', 'id_critere']);

    $sql = "INSERT INTO rapport_stabilite_ligne
            (stabilite_id, ordre_affichage, parametre, sous_parametre, incubation_ambiante, incubation_37c, incubation_55c, critere_decision, critere_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare rapport_stabilite_ligne : " . $conn->error);
    $stmt->bind_param("iissssssi", $stabilite_id, $ordre_affichage, $parametre, $sous_parametre, $incubation_ambiante, $incubation_37c, $incubation_55c, $critere_decision, $critere_id);
    if (!$stmt->execute()) throw new Exception("Erreur execute rapport_stabilite_ligne : " . $stmt->error);
    $ligne_id = $stmt->insert_id;
    $stmt->close();

    return $ligne_id;
}

function st_linkCritere($conn, $ligne_id, $critere_id){
    if ($ligne_id === null || $critere_id === null) return;

    $sql = "INSERT IGNORE INTO rapport_stabilite_critere (ligne_id, critere_id)
            VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Erreur prepare rapport_stabilite_critere : " . $conn->error);
    $stmt->bind_param("ii", $ligne_id, $critere_id);
    if (!$stmt->execute()) throw new Exception("Erreur execute rapport_stabilite_critere : " . $stmt->error);
    $stmt->close();
}

try {
    $conn->set_charset('utf8mb4');

    $csvRapports = resolveCsvPath([
        __DIR__ . '/../../data_source/rapport (2).csv',
        __DIR__ . '/rapport (2).csv',
        '/mnt/data/rapport (2).csv',
    ]);

    list($handle, $delimiter, $headers) = openCsvAssoc($csvRapports);

    echo "Début import_stabilite.php...\n";
    $conn->begin_transaction();

    $ordreByRapport = [];
    $doneStabilite = [];
    $nbStabilites = 0;
    $nbLignes = 0;
    $nbLinks = 0;

    while (($r = nextAssocRow($handle, $delimiter, $headers)) !== false) {
        $rapport_id = cleanString($r['rapport_id'] ?? null);
        if ($rapport_id === null) continue;
        if (!st_ensureRapportExists($conn, $rapport_id)) continue;

        $stabilite_id = st_upsertRapportStabilite($conn, $rapport_id, $r);
        if ($stabilite_id === null) continue;

        if (!isset($doneStabilite[$rapport_id])) {
            $doneStabilite[$rapport_id] = true;
            $nbStabilites++;
            $ordreByRapport[$rapport_id] = 1;
        }

        $critere_id = st_upsertCritere($conn, $r);
        $ligne_id = st_upsertLigne($conn, $stabilite_id, $r, $ordreByRapport[$rapport_id]);
        $nbLignes++;
        $ordreByRapport[$rapport_id]++;

        if ($critere_id !== null) {
            st_linkCritere($conn, $ligne_id, $critere_id);
            $nbLinks++;
        }
    }

    fclose($handle);
    $conn->commit();

    echo "rapport_stabilite: {$nbStabilites}\n";
    echo "rapport_stabilite_ligne: {$nbLignes}\n";
    echo "rapport_stabilite_critere: {$nbLinks}\n";
} catch (Exception $e) {
    if (isset($conn)) {
        @$conn->rollback();
    }
    echo "Erreur import_stabilite.php : " . $e->getMessage();
}
?>