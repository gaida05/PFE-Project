<?php
require_once "../Back_End/db.php";
set_time_limit(0);
ini_set('memory_limit', '512M');
function cleanString($v){
    if ($v === null) return null;
    $v = trim((string)$v);
    if ($v === '' || $v === '\N') return null;
    return $v;
}
function cleanInt($v){
    $v = cleanString($v);
    if ($v === null) return null;
    return is_numeric($v) ? intval($v) : null;
}
function cleanDateTimeFlexible($v){
    $v = cleanString($v);
    if (!$v) return null;
    $v = str_replace('/', '-', $v);
    $t = strtotime($v);
    return $t ? date("Y-m-d H:i:s", $t) : null;
}
function combineDateHeure($date, $heure){
    $date = cleanString($date);
    $heure = cleanString($heure);
    if (!$date) return null;
    $date = str_replace('/', '-', $date);
    if (!$heure) {
        $heure = "00:00:00";
    } elseif (strlen($heure) == 5) {
        $heure .= ":00";
    }
    $t = strtotime($date . " " . $heure);
    return $t ? date("Y-m-d H:i:s", $t) : null;
}
function normalizeOuiNon($v){
    $v = cleanString($v);
    if ($v === null) return null;
    $x = mb_strtolower($v, 'UTF-8');
    if (in_array($x, ['oui', 'yes', 'true', '1'])) return 'oui';
    if (in_array($x, ['non', 'no', 'false', '0'])) return 'non';
    return $v;
}
function importerDemandeClient($conn, $file){
    if (($handle = fopen($file, "r")) !== FALSE) {
        $i = 0;
        $cache = [];
        $sql = "INSERT IGNORE INTO demande_client
        (
            demande_id,
            ref_demande,
            client_id,
            client_name,
            analyse_id,
            analyse_nom,
            date_demande,
            date_reception,
            date_verif,
            faisabilite,
            validite
        )
        VALUES (?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $conn->prepare($sql);
        while (($row = fgetcsv($handle, 20000, ",")) !== FALSE) {
            if ($i++ == 0) continue;
            $demandeId = cleanInt($row[0] ?? null);
            $analyseId = cleanInt($row[5] ?? null);
            if (!$demandeId || !$analyseId) continue;
            $key = $demandeId . '_' . $analyseId;
            if (isset($cache[$key])) continue;
            $cache[$key] = true;
            $refDemande = cleanString($row[1] ?? null);
            $clientId = cleanInt($row[2] ?? null);
            $clientName = cleanString($row[3] ?? null);
            $analyseNom = cleanString($row[6] ?? null);
            $dateDemande = cleanDateTimeFlexible($row[16] ?? null);
            $dateReception = combineDateHeure(
                $row[24] ?? null,
                $row[23] ?? null
            );
            $dateVerif = combineDateHeure(
                $row[25] ?? null,
                $row[26] ?? null
            );
            $faisabilite = normalizeOuiNon($row[21] ?? null);
            $validite = normalizeOuiNon($row[22] ?? null);
            $stmt->bind_param(
                "isissssssss",
                $demandeId,
                $refDemande,
                $clientId,
                $clientName,
                $analyseId,
                $analyseNom,
                $dateDemande,
                $dateReception,
                $dateVerif,
                $faisabilite,
                $validite
            );
            $stmt->execute();
        }
        fclose($handle);
        $stmt->close();
        return count($cache);
    }
    return 0;
}
function importerDevis($conn, $file){
    if (($handle = fopen($file, "r")) !== FALSE) {
        $i = 0;
        $count = 0;
        $sql = "INSERT IGNORE INTO devis
        (
            id,
            t_ref,
            t_client,
            date_crea,
            date_fact,
            ref_fact_erp,
            etat
        )
        VALUES (?,?,?,?,?,?,?)";
        $stmt = $conn->prepare($sql);
        while (($row = fgetcsv($handle, 30000, ",")) !== FALSE) {
            if ($i++ == 0) continue;
            $id = cleanInt($row[0] ?? null);
            if (!$id) continue;
            $tRef = cleanString($row[37] ?? null);
            $tClient = cleanString($row[39] ?? null);
            $dateCrea = cleanDateTimeFlexible($row[40] ?? null);
            $dateFact = cleanDateTimeFlexible($row[65] ?? null);
            $refFactErp = cleanString($row[68] ?? null);
            $etat = cleanString($row[42] ?? null);
            $stmt->bind_param(
                "issssss",
                $id,
                $tRef,
                $tClient,
                $dateCrea,
                $dateFact,
                $refFactErp,
                $etat
            );
            $stmt->execute();
            $count++;
        }
        fclose($handle);
        $stmt->close();
        return $count;
    }
    return 0;
}
try {
    echo "Début de l'importation...\n";
    $nb1 = importerDemandeClient($conn, "dernier sql (1).csv");
    $nb2 = importerDevis($conn, "dump(15).csv");
    echo "Import terminé\n";
    echo "demande_client: $nb1\n";
    echo "devis: $nb2\n";
} catch(Exception $e){
    echo "Erreur: " . $e->getMessage();
}
?>