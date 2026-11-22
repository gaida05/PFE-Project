<?php
require_once "../Back_End/db.php";
header("Content-Type: application/json; charset=utf-8");

/*
|--------------------------------------------------------------------------
| Dashboard performance et qualite
|--------------------------------------------------------------------------
| Structure cible :
| 1. Vue globale
| 2. Clients
| 3. Analyses & conformite
| 4. Commercial
|
| Important : aucun chart par statut. Les dimensions utilisees sont :
| delai, faisabilite, validation, conformite, commercial et synthese ML.
*/

$warnings = [];

function validConn($conn) {
    return isset($conn) && $conn instanceof mysqli && !$conn->connect_error;
}

function getConnByNames($names) {
    foreach ($names as $name) {
        if (isset($GLOBALS[$name]) && validConn($GLOBALS[$name])) {
            return $GLOBALS[$name];
        }
    }
    return null;
}

function fetchAllRows($conn, $sql, $label = 'query', $required = true) {
    global $warnings;
    if (!validConn($conn)) {
        if ($required) {
            throw new Exception("Connexion indisponible pour {$label}.");
        }
        $warnings[] = "Connexion indisponible pour {$label}.";
        return [];
    }

    $result = $conn->query($sql);
    if (!$result) {
        $message = "Erreur SQL {$label}: " . $conn->error;
        if ($required) {
            throw new Exception($message);
        }
        $warnings[] = $message;
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

function cleanText($value, $fallback = '-') {
    $value = trim((string)($value ?? ''));
    return $value === '' ? $fallback : $value;
}

function normalizeText($value) {
    $value = strtolower(trim((string)($value ?? '')));
    $value = str_replace(
        ['é','è','ê','ë','à','â','ä','î','ï','ô','ö','ù','û','ü','ç'],
        ['e','e','e','e','a','a','a','i','i','o','o','u','u','u','c'],
        $value
    );
    return $value;
}

function isNoValue($value) {
    $v = normalizeText($value);
    if ($v === '') return false;
    if (in_array($v, ['non','no','false','0','ko','nc','non conforme','non-conforme','nonconforme','non valide','non-valide','invalide','non faisable','non-faisable','infaisable'], true)) {
        return true;
    }
    return strpos($v, 'non') !== false;
}

function isYesValue($value, $emptyIsYes = true) {
    $v = normalizeText($value);
    if ($v === '') return $emptyIsYes;
    if (isNoValue($value)) return false;
    return in_array($v, ['oui','yes','true','1','ok','c','conforme','valide','faisable'], true)
        || strpos($v, 'conform') !== false
        || strpos($v, 'valid') !== false
        || strpos($v, 'faisable') !== false;
}

function pct($num, $den, $digits = 1) {
    $den = (float)$den;
    if ($den <= 0) return 0;
    return round(((float)$num / $den) * 100, $digits);
}

function dateToTime($value) {
    if (!$value) return null;
    $ts = strtotime((string)$value);
    return $ts === false ? null : $ts;
}

function diffDays($start, $end) {
    $s = dateToTime($start);
    $e = dateToTime($end);
    if (!$s || !$e || $e < $s) return null;
    return round(($e - $s) / 86400, 2);
}

function monthKey($date) {
    $t = dateToTime($date);
    return $t ? date('Y-m', $t) : 'Non date';
}

function riskRank($risk) {
    $r = normalizeText($risk);
    if ($r === 'critique') return 4;
    if ($r === 'eleve' || $r === 'elevé') return 3;
    if ($r === 'moyen') return 2;
    if ($r === 'faible') return 1;
    return 0;
}
function riskLabel($rank) {
    if ($rank >= 4) return 'Critique';
    if ($rank === 3) return 'Élevé';
    if ($rank === 2) return 'Moyen';
    if ($rank === 1) return 'Faible';
    return 'Non défini';
}
function addNumber(&$array, $key, $field, $amount = 1) {
    if (!isset($array[$key][$field])) $array[$key][$field] = 0;
    $array[$key][$field] += $amount;
}

function safeAvg($sum, $count, $digits = 2) {
    return $count > 0 ? round($sum / $count, $digits) : null;
}


function getAllClientsRows($conn_labo) {
    $sql = "
        SELECT
            c.id_client,
            c.client_name,
            c.type_client
        FROM client c
        WHERE c.id_client IS NOT NULL
        ORDER BY c.client_name ASC
    ";
    return fetchAllRows($conn_labo, $sql, 'clients');
}

function getDemandesAnalyseClients($conn_labo) {
    $sql = "
        SELECT
            da.id_demande,
            da.ref_demande,
            da.id_client,
            da.id_secteur,
            da.date_demande,
            da.date_reception,
            da.date_validation,
            da.date_cloture,
            da.date_fin_analyse,
            da.faisabilite,
            da.validite,
            da.statut,
            c.client_name,
            c.type_client
        FROM demande_analyse da
        LEFT JOIN client c
            ON c.id_client = da.id_client
        WHERE da.id_client IS NOT NULL
        ORDER BY da.date_demande DESC, da.id_demande DESC
    ";
    return fetchAllRows($conn_labo, $sql, 'demandes_analyse_clients');
}

function getDemandesClients($conn_labo) {
    $sql = "
        SELECT
            dc.id AS id_demande_client,
            dc.demande_id,
            dc.ref_demande AS ref_demande_client,
            dc.client_id AS dc_client_id,
            dc.analyse_id,
            dc.analyse_nom AS analyse_nom_client,
            dc.date_demande AS dc_date_demande,
            dc.date_reception AS dc_date_reception,
            dc.date_verif AS dc_date_verif,
            dc.faisabilite AS dc_faisabilite,
            dc.validite AS dc_validite,

            da.id_demande,
            da.ref_demande AS ref_demande_analyse,
            da.id_client AS da_client_id,
            da.id_secteur AS da_secteur_id,
            da.date_demande AS da_date_demande,
            da.date_reception AS da_date_reception,
            da.date_validation AS da_date_validation,
            da.date_cloture AS da_date_cloture,
            da.date_fin_analyse AS da_date_fin_analyse,
            da.faisabilite AS da_faisabilite,
            da.validite AS da_validite,
            da.statut AS da_statut,

            c.client_name,
            c.type_client,
            a.nom_analyse,
            a.code_analyse,
            s_an.nom_secteur AS secteur_analyse,
            s_da.nom_secteur AS secteur_demande
        FROM demande_client dc
        LEFT JOIN demande_analyse da ON da.id_demande = dc.demande_id
        LEFT JOIN client c ON c.id_client = COALESCE(dc.client_id, da.id_client)
        LEFT JOIN analyses a ON a.id_analyse = dc.analyse_id
        LEFT JOIN secteur s_an ON s_an.id_secteur = a.id_secteur
        LEFT JOIN secteur s_da ON s_da.id_secteur = da.id_secteur
        ORDER BY dc.date_demande DESC, dc.id DESC
    ";
    return fetchAllRows($conn_labo, $sql, 'demandes_clients');
}

function getEchantillonsRows($conn_labo) {
    $sql = "
        SELECT
            e.id_echantillon,
            e.id_demande,
            e.est_conforme,
            e.validite,
            e.faisabilite,
            e.date_reception,
            e.nom_produit,
            e.type_echantillon,
            e.ref_labo
        FROM echantillons e
    ";
    return fetchAllRows($conn_labo, $sql, 'echantillons');
}

function getDevisRows($conn_labo) {
    $sql = "
        SELECT
            d.id,
            d.t_ref,
            d.client_id,
            d.date_crea,
            d.date_fact,
            d.ref_fact_erp,
            d.etat,
            c.client_name
        FROM devis d
        LEFT JOIN client c ON c.id_client = d.client_id
        ORDER BY d.date_crea DESC, d.id DESC
    ";
    return fetchAllRows($conn_labo, $sql, 'devis');
}

function getRapportsRows($conn_labo) {
    $sql = "
        SELECT
            r.id_rapport,
            r.id_demande,
            r.type_rapport,
            r.report_layout,
            r.date_creation,
            r.date_validation,
            r.date_livraison,
            r.date_livraison_estimee,
            r.statut,
            r.secteur_id
        FROM rapports r
    ";
    return fetchAllRows($conn_labo, $sql, 'rapports');
}

function getOrdresRows($conn_labo) {
    $sql = "
        SELECT
            oa.ordre_id,
            oa.demande_id,
            oa.client_id,
            oa.analyse_id,
            oa.laborantin_id,
            oa.fiche_paillasse_id,
            oa.echantillon_id,
            oa.resultat,
            oa.date_demande,
            oa.date_affectation,
            oa.date_reception,
            oa.date_fin,
            oa.date_validation,
            oa.duree_analyse_jours,
            oa.delai_prise_en_charge_jours,
            oa.statut,
            a.nom_analyse,
            a.code_analyse,
            s.nom_secteur
        FROM ordres_analyses oa
        LEFT JOIN analyses a ON a.id_analyse = oa.analyse_id
        LEFT JOIN secteur s ON s.id_secteur = a.id_secteur
    ";
    return fetchAllRows($conn_labo, $sql, 'ordres_analyses');
}

function getPredictionsRows($conn_dw) {
    if (!validConn($conn_dw)) return [];
    $sql = "
        SELECT
            dda.id_demande_analyse_bk AS id_demande,
            fp.delai_predit_jours,
            fp.jours_restants_predits,
            fp.jours_retard_predit,
            fp.retard_predit_flag,
            fp.niveau_risque
        FROM fait_prediction fp
        LEFT JOIN dim_demande_analyse dda
            ON dda.id_demande_analyse_sk = fp.id_demande_analyse_sk
        WHERE dda.id_demande_analyse_bk IS NOT NULL
    ";
    return fetchAllRows($conn_dw, $sql, 'predictions_dw', false);
}

function buildPredictionMap($predictionRows) {
    $map = [];
    foreach ($predictionRows as $p) {
        $id = (int)($p['id_demande'] ?? 0);
        if ($id <= 0) continue;
        $rank = riskRank($p['niveau_risque'] ?? null);
        $retard = (float)($p['jours_retard_predit'] ?? 0);
        $flag = (int)($p['retard_predit_flag'] ?? 0);
        if (!isset($map[$id])) {
            $map[$id] = [
                'id_demande' => $id,
                'niveau_risque' => riskLabel($rank),
                'niveau_risque_rank' => $rank,
                'delai_predit_jours' => null,
                'jours_retard_predit' => 0,
                'retard_predit_flag' => 0
            ];
        }
        if ($rank > $map[$id]['niveau_risque_rank']) {
            $map[$id]['niveau_risque_rank'] = $rank;
            $map[$id]['niveau_risque'] = riskLabel($rank);
        }
        if (isset($p['delai_predit_jours']) && $p['delai_predit_jours'] !== null) {
            $map[$id]['delai_predit_jours'] = max((float)$p['delai_predit_jours'], (float)($map[$id]['delai_predit_jours'] ?? 0));
        }
        $map[$id]['jours_retard_predit'] = max($map[$id]['jours_retard_predit'], $retard);
        $map[$id]['retard_predit_flag'] = max($map[$id]['retard_predit_flag'], $flag);
    }
    return $map;
}

function enrichDemandesClients($rows, $predMap) {
    $out = [];
    foreach ($rows as $r) {
        $idDemande = (int)($r['demande_id'] ?: $r['id_demande'] ?: 0);
        $clientId = (int)($r['dc_client_id'] ?: $r['da_client_id'] ?: 0);
        $analyseId = (int)($r['analyse_id'] ?: 0);
        $dateClient = $r['dc_date_demande'] ?: $r['da_date_demande'];
        $dateLaboStart = $r['da_date_demande'] ?: $r['dc_date_demande'];
        $dateLaboEnd = $r['da_date_reception'] ?: $r['dc_date_reception'];
        $delaiLabo = diffDays($dateLaboStart, $dateLaboEnd);

        $delaiTransformation = diffDays($r['dc_date_demande'], $r['dc_date_verif'] ?: $r['dc_date_reception'] ?: $r['da_date_demande']);
        $faisabiliteSource = $r['dc_faisabilite'] ?: $r['da_faisabilite'];
        $validiteSource = $r['dc_validite'] ?: $r['da_validite'];
        $faisable = isYesValue($faisabiliteSource, true);
        $valide = isYesValue($validiteSource, true);
        $pred = $predMap[$idDemande] ?? [
            'niveau_risque' => 'Non défini',
            'niveau_risque_rank' => 0,
            'delai_predit_jours' => null,
            'jours_retard_predit' => 0,
            'retard_predit_flag' => 0
        ];
        $scorePriorite = ($pred['niveau_risque_rank'] >= 4 ? 3 : 0)
            + ($pred['niveau_risque_rank'] === 3 ? 2 : 0)
            + ((float)$pred['jours_retard_predit'] > 0 || (int)$pred['retard_predit_flag'] === 1 ? 1 : 0);

        $out[] = [
            'id_demande_client' => (int)($r['id_demande_client'] ?? 0),
            'id_demande' => $idDemande,
            'ref_demande_client' => cleanText($r['ref_demande_client'] ?? null, 'DC-' . ($r['id_demande_client'] ?? '')),
            'reference_demande' => cleanText($r['ref_demande_analyse'] ?? null, $idDemande ? 'DA-' . $idDemande : '-'),
            'id_client' => $clientId,
            'client_nom' => cleanText($r['client_name'] ?? null, 'Client inconnu'),
            'type_client' => cleanText($r['type_client'] ?? null, '-'),
            'id_analyse' => $analyseId,
            'analyse_nom' => cleanText($r['analyse_nom_client'] ?: $r['nom_analyse'] ?: null, 'Analyse inconnue'),
            'code_analyse' => cleanText($r['code_analyse'] ?? null, ''),
            'secteur_nom' => cleanText($r['secteur_analyse'] ?: $r['secteur_demande'] ?: null, 'Secteur inconnu'),
            'date_demande' => $dateClient,
            'date_demande_analyse' => $r['da_date_demande'] ?? null,
            'faisabilite' => $faisable ? 'Oui' : 'Non',
            'validite' => $valide ? 'Oui' : 'Non',
            'statut_demande_client' => cleanText($r['da_statut'] ?? null, '-'),
            'delai_transformation_jours' => $delaiTransformation,
            'delai_labo_jours' => $delaiLabo,
            'niveau_risque_ml' => $pred['niveau_risque'],
            'niveau_risque_rank' => $pred['niveau_risque_rank'],
            'delai_predit_jours' => $pred['delai_predit_jours'],
            'retard_predit' => ((float)$pred['jours_retard_predit'] > 0 || (int)$pred['retard_predit_flag'] === 1) ? 'Oui' : 'Non',
            'jours_retard_predit' => $pred['jours_retard_predit'],
            'priorite_ml' => $scorePriorite
        ];
    }
    return $out;
}

function buildEchantillonStats($echantillons) {
    $byDemande = [];
    $total = 0; $conformes = 0; $nonConformes = 0;
    foreach ($echantillons as $e) {
        $id = (int)($e['id_demande'] ?? 0);
        $isNo = isNoValue($e['est_conforme'] ?? null);
        $isYes = !$isNo && isYesValue($e['est_conforme'] ?? null, false);
        $total++;
        if ($isNo) $nonConformes++; else $conformes++;
        if ($id > 0) {
            if (!isset($byDemande[$id])) $byDemande[$id] = ['total' => 0, 'conformes' => 0, 'non_conformes' => 0];
            $byDemande[$id]['total']++;
            if ($isNo) $byDemande[$id]['non_conformes']++; else $byDemande[$id]['conformes']++;
        }
    }
    return [
        'total' => $total,
        'conformes' => $conformes,
        'non_conformes' => $nonConformes,
        'taux_conformite' => pct($conformes, $total),
        'by_demande' => $byDemande
    ];
}

function buildGlobal($demandes, $echStats, $devis, $rapports, $predMap) {
    $total = count($demandes);
    $faisables = 0; $nonFaisables = 0; $valides = 0; $nonValides = 0;
    $delaySum = 0; $delayCount = 0;
    $retardsPredits = 0; $critiques = 0; $risqueEleve = 0; $delaiPreditSum = 0; $delaiPreditCount = 0; $joursRetardSum = 0;

    $monthlyDelay = [];
    $riskDistribution = ['Faible' => 0, 'Moyen' => 0, 'Élevé' => 0, 'Critique' => 0, 'Non défini' => 0];

    foreach ($demandes as $d) {
        if ($d['faisabilite'] === 'Oui') $faisables++; else $nonFaisables++;
        if ($d['validite'] === 'Oui') $valides++; else $nonValides++;
        if ($d['delai_labo_jours'] !== null) {
            $delaySum += $d['delai_labo_jours']; $delayCount++;
            $m = monthKey($d['date_demande']);
            if (!isset($monthlyDelay[$m])) $monthlyDelay[$m] = ['mois' => $m, 'sum' => 0, 'count' => 0];
            $monthlyDelay[$m]['sum'] += $d['delai_labo_jours']; $monthlyDelay[$m]['count']++;
        }
        $riskDistribution[$d['niveau_risque_ml']] = ($riskDistribution[$d['niveau_risque_ml']] ?? 0) + 1;
        if ($d['niveau_risque_rank'] >= 4) $critiques++;
        if ($d['niveau_risque_rank'] >= 3) $risqueEleve++;
        if ($d['retard_predit'] === 'Oui') $retardsPredits++;
        if ($d['delai_predit_jours'] !== null) { $delaiPreditSum += $d['delai_predit_jours']; $delaiPreditCount++; }
        $joursRetardSum += (float)$d['jours_retard_predit'];
    }

    ksort($monthlyDelay);
    $evolutionDelay = [];
    foreach ($monthlyDelay as $m => $row) {
        $evolutionDelay[] = ['mois' => $m, 'delai_moyen_jours' => safeAvg($row['sum'], $row['count'])];
    }

    $nbDevis = count($devis);
    $nbFactures = 0;
    foreach ($devis as $dv) {
        if (!empty($dv['date_fact']) || !empty($dv['ref_fact_erp']) || strpos(normalizeText($dv['etat'] ?? ''), 'fact') !== false) $nbFactures++;
    }

    $rapportsFinaux = 0;
    foreach ($rapports as $r) {
        $text = normalizeText(($r['type_rapport'] ?? '') . ' ' . ($r['statut'] ?? ''));
        if (strpos($text, 'final') !== false || strpos($text, 'valide') !== false || strpos($text, 'livre') !== false) $rapportsFinaux++;
    }

    return [
        'kpis' => [
            'total_demandes_clients' => $total,
            'taux_faisabilite' => pct($faisables, $total),
            'taux_validation' => pct($valides, $total),
            'delai_moyen_global_jours' => safeAvg($delaySum, $delayCount),
            'taux_conformite' => $echStats['taux_conformite'],
            'non_conformites' => $echStats['non_conformes'],
            'taux_devis_facture' => pct($nbFactures, $nbDevis),
            'rapports_finaux' => $rapportsFinaux,
            'demandes_critiques_ml' => $critiques,
            'retards_predits' => $retardsPredits
        ],
        'charts' => [
            'funnel_performance' => [
                ['etape' => 'Demandes clients', 'total' => $total],
                ['etape' => 'Faisables', 'total' => $faisables],
                ['etape' => 'Validées', 'total' => $valides],
                ['etape' => 'Devis', 'total' => $nbDevis],
                ['etape' => 'Factures', 'total' => $nbFactures],
                ['etape' => 'Rapports finaux', 'total' => $rapportsFinaux]
            ],
            'evolution_delai_moyen' => $evolutionDelay,
            'faisabilite_validation_globale' => [
                ['dimension' => 'Faisabilité', 'oui' => $faisables, 'non' => $nonFaisables],
                ['dimension' => 'Validation', 'oui' => $valides, 'non' => $nonValides]
            ],
            'conformite_globale' => [
                ['label' => 'Conformes', 'total' => $echStats['conformes']],
                ['label' => 'Non conformes', 'total' => $echStats['non_conformes']]
            ],
            'repartition_risques_ml' => array_map(function($label, $total) { return ['niveau_risque' => $label, 'total' => $total]; }, array_keys($riskDistribution), array_values($riskDistribution)),
            'statistiques_predictives' => [
                'demandes_critiques_ml' => $critiques,
                'retards_predits' => $retardsPredits,
                'taux_risque_eleve' => pct($risqueEleve, $total),
                'delai_moyen_predit_jours' => safeAvg($delaiPreditSum, $delaiPreditCount),
                'jours_retard_predits' => round($joursRetardSum, 2)
            ]
        ]
    ];
}

function buildClients($demandes, $demandesAnalyse = [], $allClients = [], $devis = [], $predMap = []) {
    $clients = [];
    $ensureClient = function($id, $name = null, $type = null) use (&$clients) {
        $key = $id ? 'ID_' . (int)$id : 'NAME_' . normalizeText($name ?: 'Client inconnu');
        if (!isset($clients[$key])) {
            $clients[$key] = [
                'id_client' => (int)($id ?: 0),
                'client_nom' => cleanText($name, 'Client inconnu'),
                'type_client' => cleanText($type, '-'),
                'total_demandes' => 0,
                'total_demandes_analyse' => 0,
                'total_demandes_client' => 0,
                'total_devis' => 0,
                'faisables' => 0,
                'non_faisables' => 0,
                'valides' => 0,
                'non_valides' => 0,
                'delai_sum' => 0,
                'delai_count' => 0,
                'retards_predits' => 0,
                'demandes_critiques' => 0,
                'score_priorite_ml' => 0,
                'monthly' => []
            ];
        }
        return $key;
    };
    foreach ($allClients as $c) {
        $ensureClient($c['id_client'] ?? 0, $c['client_name'] ?? null, $c['type_client'] ?? null);
    }
    $seenDemandeAnalyse = [];
    foreach ($demandesAnalyse as $r) {
        $idDemande = (int)($r['id_demande'] ?? 0);
        if ($idDemande > 0) $seenDemandeAnalyse[$idDemande] = true;
        $key = $ensureClient($r['id_client'] ?? 0, $r['client_name'] ?? null, $r['type_client'] ?? null);
        $clients[$key]['total_demandes']++;
        $clients[$key]['total_demandes_analyse']++;
        $faisable = isYesValue($r['faisabilite'] ?? null, true);
        $valide = isYesValue($r['validite'] ?? null, true);
        if ($faisable) $clients[$key]['faisables']++; else $clients[$key]['non_faisables']++;
        if ($valide) $clients[$key]['valides']++; else $clients[$key]['non_valides']++;
        $delai = diffDays($r['date_demande'] ?? null, $r['date_reception'] ?? null);
        if ($delai !== null) {
            $clients[$key]['delai_sum'] += $delai;
            $clients[$key]['delai_count']++;
        }

        $pred = $predMap[$idDemande] ?? null;
        if ($pred) {
            if (((float)($pred['jours_retard_predit'] ?? 0) > 0) || ((int)($pred['retard_predit_flag'] ?? 0) === 1)) {
                $clients[$key]['retards_predits']++;
            }
            if ((int)($pred['niveau_risque_rank'] ?? 0) >= 4) {
                $clients[$key]['demandes_critiques']++;
            }
            $clients[$key]['score_priorite_ml'] += ((int)($pred['niveau_risque_rank'] ?? 0) >= 4 ? 3 : 0)
                + ((int)($pred['niveau_risque_rank'] ?? 0) === 3 ? 2 : 0)
                + (((float)($pred['jours_retard_predit'] ?? 0) > 0 || (int)($pred['retard_predit_flag'] ?? 0) === 1) ? 1 : 0);
        }

        $m = monthKey($r['date_demande'] ?? null);
        if ($m) $clients[$key]['monthly'][$m] = ($clients[$key]['monthly'][$m] ?? 0) + 1;
    }
    foreach ($demandes as $d) {
        $idDemande = (int)($d['id_demande'] ?? 0);
        if ($idDemande > 0 && isset($seenDemandeAnalyse[$idDemande])) continue;

        $key = $ensureClient($d['id_client'] ?? 0, $d['client_nom'] ?? null, $d['type_client'] ?? null);
        $clients[$key]['total_demandes']++;
        $clients[$key]['total_demandes_client']++;

        if ($d['faisabilite'] === 'Oui') $clients[$key]['faisables']++; else $clients[$key]['non_faisables']++;
        if ($d['validite'] === 'Oui') $clients[$key]['valides']++; else $clients[$key]['non_valides']++;
        if ($d['delai_labo_jours'] !== null) {
            $clients[$key]['delai_sum'] += $d['delai_labo_jours'];
            $clients[$key]['delai_count']++;
        }
        if ($d['retard_predit'] === 'Oui') $clients[$key]['retards_predits']++;
        if (($d['niveau_risque_rank'] ?? 0) >= 4) $clients[$key]['demandes_critiques']++;
        $clients[$key]['score_priorite_ml'] += (int)($d['priorite_ml'] ?? 0);

        $m = monthKey($d['date_demande'] ?? null);
        if ($m) $clients[$key]['monthly'][$m] = ($clients[$key]['monthly'][$m] ?? 0) + 1;
    }

    foreach ($devis as $dv) {
        $key = $ensureClient($dv['client_id'] ?? 0, $dv['client_name'] ?? null, null);
        $clients[$key]['total_devis']++;
    }

    $rows = [];
    foreach ($clients as $c) {
        $delai = safeAvg($c['delai_sum'], $c['delai_count']);
        $activity = $c['total_demandes'] + $c['total_devis'];
        $rows[] = [
            'id_client' => $c['id_client'],
            'client_nom' => $c['client_nom'],
            'type_client' => $c['type_client'],
            'total_demandes' => $c['total_demandes'],
            'total_demandes_analyse' => $c['total_demandes_analyse'],
            'total_demandes_client' => $c['total_demandes_client'],
            'total_devis' => $c['total_devis'],
            'total_activite' => $activity,
            'delai_moyen_jours' => $delai,
            'taux_validation' => pct($c['valides'], max(1, $c['total_demandes'])),
            'faisables' => $c['faisables'],
            'non_faisables' => $c['non_faisables'],
            'valides' => $c['valides'],
            'non_valides' => $c['non_valides'],
            'retards_predits' => $c['retards_predits'],
            'demandes_critiques' => $c['demandes_critiques'],
            'score_priorite_ml' => $c['score_priorite_ml'],
            'monthly' => $c['monthly']
        ];
    }

    $activeRows = array_values(array_filter($rows, fn($r) => $r['total_activite'] > 0));
    $chartRows = array_values(array_filter($rows, fn($r) => $r['total_demandes'] > 0));

    usort($chartRows, fn($a, $b) => $b['total_demandes'] <=> $a['total_demandes']);
    $topActive = $chartRows[0] ?? ($activeRows[0] ?? null);

    $slowest = null; $bestValidation = null;
    foreach ($chartRows as $r) {
        if ($r['delai_moyen_jours'] !== null && (!$slowest || $r['delai_moyen_jours'] > $slowest['delai_moyen_jours'])) $slowest = $r;
        if (!$bestValidation || $r['taux_validation'] > $bestValidation['taux_validation']) $bestValidation = $r;
    }

    $topByDelay = $chartRows;
    usort($topByDelay, fn($a, $b) => ($b['delai_moyen_jours'] ?? -1) <=> ($a['delai_moyen_jours'] ?? -1));
    $priorityRows = array_values(array_filter($rows, fn($r) => $r['score_priorite_ml'] > 0));
    usort($priorityRows, fn($a, $b) => $b['score_priorite_ml'] <=> $a['score_priorite_ml']);

    $clientTime = [];
    foreach (array_slice($chartRows, 0, 5) as $client) {
        foreach ($client['monthly'] as $mois => $total) {
            $clientTime[] = ['mois' => $mois, 'client_nom' => $client['client_nom'], 'total_demandes' => $total];
        }
    }
    usort($clientTime, fn($a, $b) => strcmp($a['mois'], $b['mois']));

    return [
        'kpis' => [
            'clients_total' => count($rows),
            'clients_actifs' => count($activeRows),
            'client_le_plus_actif' => $topActive['client_nom'] ?? '-',
            'delai_moyen_client_jours' => safeAvg(array_sum(array_map(fn($r) => $r['delai_moyen_jours'] ?? 0, $chartRows)), count(array_filter($chartRows, fn($r) => $r['delai_moyen_jours'] !== null))),
            'meilleur_taux_validation_client' => $bestValidation['client_nom'] ?? '-',
            'client_le_plus_lent' => $slowest['client_nom'] ?? '-',
            'clients_prioritaires_ml' => count($priorityRows),
            'retards_predits_par_client' => array_sum(array_map(fn($r) => $r['retards_predits'], $rows))
        ],
        'charts' => [
            'top_clients_volume' => array_slice(array_map(fn($r) => ['client_nom' => $r['client_nom'], 'total_demandes' => $r['total_demandes']], $chartRows), 0, 10),
            'delai_moyen_par_client' => array_slice(array_map(fn($r) => ['client_nom' => $r['client_nom'], 'delai_moyen_jours' => $r['delai_moyen_jours']], $topByDelay), 0, 10),
            'faisabilite_validation_par_client' => array_slice(array_map(fn($r) => [
                'client_nom' => $r['client_nom'], 'faisables' => $r['faisables'], 'non_faisables' => $r['non_faisables'], 'valides' => $r['valides'], 'non_valides' => $r['non_valides']
            ], $chartRows), 0, 10),
            'volume_demandes_client_temps' => $clientTime,
            'clients_prioritaires_ml' => array_slice(array_map(fn($r) => ['client_nom' => $r['client_nom'], 'score_priorite_ml' => $r['score_priorite_ml'], 'retards_predits' => $r['retards_predits'], 'demandes_critiques' => $r['demandes_critiques']], $priorityRows), 0, 10)
        ],
        'demandes_clients_grid' => $demandes,
        'clients_all' => $rows
    ];
}

function buildAnalyses($demandes, $ordres, $echStats) {
    $analyses = [];
    foreach ($demandes as $d) {
        $key = $d['id_analyse'] ?: $d['analyse_nom'];
        if (!isset($analyses[$key])) {
            $analyses[$key] = [
                'id_analyse' => $d['id_analyse'], 'analyse_nom' => $d['analyse_nom'], 'code_analyse' => $d['code_analyse'], 'secteur_nom' => $d['secteur_nom'],
                'nombre_demandes' => 0, 'clients' => [], 'delai_sum' => 0, 'delai_count' => 0, 'delai_max' => null,
                'valides' => 0, 'total_validation' => 0, 'ech_total' => 0, 'ech_conformes' => 0, 'ech_non_conformes' => 0,
                'risque_sum' => 0, 'risque_count' => 0, 'retards_predits' => 0, 'score_sensibilite' => 0
            ];
        }
        $a =& $analyses[$key];
        $a['nombre_demandes']++;
        $a['clients'][$d['id_client'] ?: $d['client_nom']] = true;
        if ($d['delai_labo_jours'] !== null) { $a['delai_sum'] += $d['delai_labo_jours']; $a['delai_count']++; $a['delai_max'] = max($a['delai_max'] ?? 0, $d['delai_labo_jours']); }
        $a['total_validation']++;
        if ($d['validite'] === 'Oui') $a['valides']++;
        $demandeEch = $echStats['by_demande'][$d['id_demande']] ?? null;
        if ($demandeEch) { $a['ech_total'] += $demandeEch['total']; $a['ech_conformes'] += $demandeEch['conformes']; $a['ech_non_conformes'] += $demandeEch['non_conformes']; }
        $a['risque_sum'] += $d['niveau_risque_rank']; $a['risque_count']++;
        if ($d['retard_predit'] === 'Oui') $a['retards_predits']++;
        $a['score_sensibilite'] += ($d['niveau_risque_rank'] >= 4 ? 3 : 0) + ($d['niveau_risque_rank'] === 3 ? 2 : 0) + ($d['retard_predit'] === 'Oui' ? 1 : 0);
        unset($a);
    }

    // Enrich delay from ordres_analyses where available.
    foreach ($ordres as $o) {
        $key = (int)($o['analyse_id'] ?? 0);
        if ($key <= 0 || !isset($analyses[$key])) continue;
        $duree = isset($o['duree_analyse_jours']) ? (float)$o['duree_analyse_jours'] : null;
        if ($duree !== null && $duree >= 0) {
            $analyses[$key]['delai_sum'] += $duree;
            $analyses[$key]['delai_count']++;
            $analyses[$key]['delai_max'] = max($analyses[$key]['delai_max'] ?? 0, $duree);
        }
    }

    $rows = [];
    foreach ($analyses as $a) {
        $rows[] = [
            'id_analyse' => $a['id_analyse'],
            'analyse_nom' => $a['analyse_nom'],
            'code_analyse' => $a['code_analyse'],
            'secteur_nom' => $a['secteur_nom'],
            'nombre_demandes' => $a['nombre_demandes'],
            'nombre_clients' => count($a['clients']),
            'delai_moyen_jours' => safeAvg($a['delai_sum'], $a['delai_count']),
            'delai_max_jours' => $a['delai_max'],
            'taux_validation' => pct($a['valides'], $a['total_validation']),
            'taux_conformite' => pct($a['ech_conformes'], $a['ech_total']),
            'niveau_risque_moyen_ml' => safeAvg($a['risque_sum'], $a['risque_count']),
            'retards_predits' => $a['retards_predits'],
            'score_sensibilite' => $a['score_sensibilite'],
            'non_conformites_count' => $a['ech_non_conformes']
        ];
    }

    $popular = $rows; usort($popular, fn($a,$b) => $b['nombre_demandes'] <=> $a['nombre_demandes']);
    $slow = $rows; usort($slow, fn($a,$b) => ($b['delai_moyen_jours'] ?? -1) <=> ($a['delai_moyen_jours'] ?? -1));
    $sensitive = $rows; usort($sensitive, fn($a,$b) => $b['score_sensibilite'] <=> $a['score_sensibilite']);
    $retards = $rows; usort($retards, fn($a,$b) => $b['retards_predits'] <=> $a['retards_predits']);

    return [
        'kpis' => [
            'analyses_distinctes' => count($rows),
            'analyse_la_plus_frequente' => $popular[0]['analyse_nom'] ?? '-',
            'analyse_la_plus_lente' => $slow[0]['analyse_nom'] ?? '-',
            'delai_moyen_analyse_jours' => safeAvg(array_sum(array_map(fn($r) => $r['delai_moyen_jours'] ?? 0, $rows)), count(array_filter($rows, fn($r) => $r['delai_moyen_jours'] !== null))),
            'taux_conformite' => safeAvg(array_sum(array_map(fn($r) => $r['taux_conformite'] ?? 0, $rows)), count($rows)),
            'non_conformites' => array_sum(array_map(fn($r) => $r['non_conformites_count'] ?? 0, $rows)),
            'analyse_la_plus_sensible_ml' => $sensitive[0]['analyse_nom'] ?? '-',
            'retards_predits_par_analyse' => array_sum(array_map(fn($r) => $r['retards_predits'], $rows))
        ],
        'charts' => [
            'analyses_frequentes' => array_slice(array_map(fn($r) => ['analyse_nom' => $r['analyse_nom'], 'total' => $r['nombre_demandes']], $popular), 0, 10),
            'delai_moyen_par_analyse' => array_slice(array_map(fn($r) => ['analyse_nom' => $r['analyse_nom'], 'delai_moyen_jours' => $r['delai_moyen_jours']], $slow), 0, 10),
            'conformite_par_analyse' => array_slice(array_map(fn($r) => ['analyse_nom' => $r['analyse_nom'], 'taux_conformite' => $r['taux_conformite']], $popular), 0, 10),
            'non_conformites_par_analyse' => array_slice(array_map(fn($r) => ['analyse_nom' => $r['analyse_nom'], 'non_conformites' => $r['non_conformites_count']], array_values(array_filter($popular, fn($r) => ($r['non_conformites_count'] ?? 0) > 0))), 0, 10),
            'analyses_sensibles_ml' => array_slice(array_map(fn($r) => ['analyse_nom' => $r['analyse_nom'], 'score_sensibilite' => $r['score_sensibilite'], 'retards_predits' => $r['retards_predits']], $sensitive), 0, 10),
            'retards_predits_par_analyse' => array_slice(array_map(fn($r) => ['analyse_nom' => $r['analyse_nom'], 'retards_predits' => $r['retards_predits']], $retards), 0, 10),
            'volume_delai_par_analyse' => array_slice(array_map(fn($r) => ['analyse_nom' => $r['analyse_nom'], 'volume' => $r['nombre_demandes'], 'delai_moyen_jours' => $r['delai_moyen_jours']], $popular), 0, 10)
        ],
        'analyses_grid' => $rows
    ];
}

function buildCommercial($devis) {
    $total = count($devis); $factures = 0; $pending = 0; $delaySum = 0; $delayCount = 0;
    $monthly = []; $etatRows = []; $clientRows = [];
    foreach ($devis as $dv) {
        $facture = !empty($dv['date_fact']) || !empty($dv['ref_fact_erp']) || strpos(normalizeText($dv['etat'] ?? ''), 'fact') !== false;
        if ($facture) $factures++; else $pending++;
        $delay = diffDays($dv['date_crea'], $dv['date_fact']);
        if ($delay !== null) { $delaySum += $delay; $delayCount++; }
        $m = monthKey($dv['date_crea']);
        if (!isset($monthly[$m])) $monthly[$m] = ['mois' => $m, 'devis' => 0, 'factures' => 0, 'delai_sum' => 0, 'delai_count' => 0];
        $monthly[$m]['devis']++; if ($facture) $monthly[$m]['factures']++;
        if ($delay !== null) { $monthly[$m]['delai_sum'] += $delay; $monthly[$m]['delai_count']++; }
        $etat = cleanText($dv['etat'] ?? null, $facture ? 'Facturé' : 'En attente');
        $etatRows[$etat] = ($etatRows[$etat] ?? 0) + 1;
        $client = cleanText($dv['client_name'] ?? null, 'Client inconnu');
        $clientRows[$client] = ($clientRows[$client] ?? 0) + 1;
    }
    ksort($monthly);
    $monthlyRows = [];
    foreach ($monthly as $m => $r) {
        $monthlyRows[] = [
            'mois' => $m,
            'devis' => $r['devis'],
            'factures' => $r['factures'],
            'taux_transformation' => pct($r['factures'], $r['devis']),
            'delai_moyen_devis_facture_jours' => safeAvg($r['delai_sum'], $r['delai_count'])
        ];
    }
    arsort($clientRows);
    $topClients = [];
    foreach (array_slice($clientRows, 0, 10, true) as $client => $count) $topClients[] = ['client_nom' => $client, 'total_devis' => $count];
    $etatChart = [];
    foreach ($etatRows as $etat => $count) $etatChart[] = ['etat' => $etat, 'total' => $count];

    $grid = [];
    foreach ($devis as $dv) {
        $delay = diffDays($dv['date_crea'], $dv['date_fact']);
        $facture = !empty($dv['date_fact']) || !empty($dv['ref_fact_erp']) || strpos(normalizeText($dv['etat'] ?? ''), 'fact') !== false;
        $grid[] = [
            'id_devis' => (int)($dv['id'] ?? 0),
            'ref_devis' => cleanText($dv['t_ref'] ?? null, 'DEV-' . ($dv['id'] ?? '')),
            'client_nom' => cleanText($dv['client_name'] ?? null, 'Client inconnu'),
            'date_creation' => $dv['date_crea'] ?? null,
            'etat_devis' => cleanText($dv['etat'] ?? null, $facture ? 'Facturé' : 'En attente'),
            'facture' => $facture ? 'Oui' : 'Non',
            'date_facture' => $dv['date_fact'] ?? null,
            'ref_facture_erp' => cleanText($dv['ref_fact_erp'] ?? null, '-'),
            'delai_devis_facture_jours' => $delay,
            'demande_client_liee' => null
        ];
    }

    return [
        'kpis' => [
            'nombre_devis' => $total,
            'nombre_factures' => $factures,
            'taux_transformation' => pct($factures, $total),
            'devis_en_attente' => $pending,
            'devis_transformes' => $factures,
            'delai_moyen_devis_facture_jours' => safeAvg($delaySum, $delayCount)
        ],
        'charts' => [
            'devis_vs_factures' => $monthlyRows,
            'taux_transformation_mensuel' => array_map(fn($r) => ['mois' => $r['mois'], 'taux_transformation' => $r['taux_transformation']], $monthlyRows),
            'evolution_commerciale' => $monthlyRows,
            'devis_par_etat' => $etatChart,
            'top_clients_par_devis' => $topClients,
            'delai_devis_facture' => array_map(fn($r) => ['mois' => $r['mois'], 'delai_moyen_devis_facture_jours' => $r['delai_moyen_devis_facture_jours']], $monthlyRows)
        ],
        'devis_grid' => $grid
    ];
}

try {
    $conn_labo_perf = getConnByNames(['conn_labo', 'conn', 'mysqli']);
    $conn_dw_perf = getConnByNames(['conn_dw']);

    if (!validConn($conn_labo_perf)) {
        throw new Exception('Erreur de connexion à labo_db1. Vérifiez Back_End/db.php et la variable $conn_labo.');
    }

    $rawAllClients = getAllClientsRows($conn_labo_perf);
    $rawDemandesAnalyseClients = getDemandesAnalyseClients($conn_labo_perf);
    $rawDemandesClients = getDemandesClients($conn_labo_perf);
    $rawEchantillons = getEchantillonsRows($conn_labo_perf);
    $rawDevis = getDevisRows($conn_labo_perf);
    $rawRapports = getRapportsRows($conn_labo_perf);
    $rawOrdres = getOrdresRows($conn_labo_perf);
    $predMap = buildPredictionMap(getPredictionsRows($conn_dw_perf));

    $demandesClients = enrichDemandesClients($rawDemandesClients, $predMap);
    $echStats = buildEchantillonStats($rawEchantillons);
    $global = buildGlobal($demandesClients, $echStats, $rawDevis, $rawRapports, $predMap);
    $clients = buildClients($demandesClients, $rawDemandesAnalyseClients, $rawAllClients, $rawDevis, $predMap);
    $analyses = buildAnalyses($demandesClients, $rawOrdres, $echStats);
    $commercial = buildCommercial($rawDevis);

    $filterClients = [];
    foreach ($rawAllClients as $c) {
        $name = cleanText($c['client_name'] ?? null, null);
        if ($name) $filterClients[] = $name;
    }
    $filterClients = array_values(array_unique($filterClients));
    sort($filterClients);
    $filterAnalyses = [];
    foreach ($analyses['charts']['analyses_frequentes'] as $a) $filterAnalyses[] = $a['analyse_nom'];

    $response = [
        'status' => 'success',
        'success' => true,
        'warnings' => $warnings,
        'vue_globale' => $global,
        'clients' => $clients,
        'analyses_conformite' => $analyses,
        'commercial' => $commercial,
        'charts' => array_merge($global['charts'], $clients['charts'], $analyses['charts'], $commercial['charts']),
        'kpis' => [
            'global' => $global['kpis'],
            'clients' => $clients['kpis'],
            'analyses_conformite' => $analyses['kpis'],
            'commercial' => $commercial['kpis']
        ],
        'demandes_clients_grid' => $clients['demandes_clients_grid'],
        'analyses_grid' => $analyses['analyses_grid'],
        'devis_grid' => $commercial['devis_grid'],
        'filter_options' => [
            'clients' => $filterClients,
            'analyses' => $filterAnalyses
        ]
    ];
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'success' => false,
        'message' => $e->getMessage(),
        'warnings' => $warnings
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;









