<?php
function combineDateHeureByParser($date, $heure, callable $dateParser){
    $date = cleanString($date);
    $heure = cleanString($heure);
    if (!$date) return null;
    $dateParsed = $dateParser($date);
    if (!$dateParsed) return null;
    $dateOnly = date('Y-m-d', strtotime($dateParsed));
    if (!$heure) {
        $heure = "00:00:00";
    } elseif (strlen($heure) === 5) {
        $heure .= ":00";
    }
    $final = $dateOnly . ' ' . $heure;
    $ts = strtotime($final);
    $result = $ts ? date('Y-m-d H:i:s', $ts) : null;
    return isInvalidBusinessDate($result) ? null : $result;
}
function combineDateHeure($date, $heure){
    return combineDateHeureByParser($date, $heure, 'cleanDateTimeFlexible');
}
function combineDateHeureYMD($date, $heure){
    return combineDateHeureByParser($date, $heure, 'cleanDateTimeYMDStrict');
}
function combineDateHeureMDY($date, $heure){
    return combineDateHeureByParser($date, $heure, 'cleanDateTimeMDYStrict');
}
function combineDateHeureDMY($date, $heure){
    return combineDateHeureByParser($date, $heure, 'cleanDateTimeDMYStrict');
}
function combineDateHeureStrict($date, $heure){
    return combineDateHeure($date, $heure);
}
function normalizeOuiNon($v){
    $v = cleanString($v);
    if ($v === null) return null;
    $x = mb_strtolower($v, 'UTF-8');
    if (in_array($x, ['oui', 'yes', 'true', '1'])) return 'oui';
    if (in_array($x, ['non', 'no', 'false', '0'])) return 'non';
    return $v;
}
function buildClientName($idClient, $nomClient){
    $nomClient = cleanString($nomClient);
    $idClient = cleanInt($idClient);
    if ($nomClient !== null) return $nomClient;
    if ($idClient !== null) return "client_" . $idClient;
    return "client_inconnu";
}
function buildLaborantinName($idLaborantin, $nomLaborantin){
    $nomLaborantin = cleanString($nomLaborantin);
    $idLaborantin = cleanInt($idLaborantin);
    if ($nomLaborantin !== null) return $nomLaborantin;
    if ($idLaborantin !== null) return "laborantin_" . $idLaborantin;
    return "laborantin_inconnu";
}
function buildSecteurName($idSecteur, $nomSecteur){
    $nomSecteur = cleanString($nomSecteur);
    $idSecteur = cleanInt($idSecteur);
    if ($nomSecteur !== null) return $nomSecteur;
    if ($idSecteur !== null) return "secteur_" . $idSecteur;
    return "secteur_inconnu";
}
?>