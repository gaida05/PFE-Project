<?php
require_once __DIR__ . '/clean_helpers.php';
require_once __DIR__ . '/csv_helpers.php';
require_once __DIR__ . '/transform_helpers.php';

function openCsvAssocSmart(string $file): array {
    $delimiter = detectDelimiter($file);
    $handle = fopen($file, 'r');
    if (!$handle) {
        throw new Exception("Impossible d'ouvrir le fichier : $file");
    }
    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) {
        throw new Exception("CSV vide : $file");
    }
    $headers = array_map(function ($h) {
        $h = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h);
        return trim((string) $h, " \t\n\r\0\x0B\"");
    }, $headers);
    return [$handle, $delimiter, $headers];
}

function nextAssocRowSmart($handle, string $delimiter, array $headers) {
    $row = fgetcsv($handle, 0, $delimiter);
    if ($row === false) {
        return false;
    }
    $row = array_pad($row, count($headers), null);
    return array_combine($headers, array_slice($row, 0, count($headers)));
}

function firstExistingPath(array $candidates): string {
    foreach ($candidates as $candidate) {
        if ($candidate && file_exists($candidate)) {
            return $candidate;
        }
    }
    throw new Exception('Fichier introuvable. Chemins testés : ' . implode(' | ', $candidates));
}

function normalizeConformiteValue($value) {
    $v = cleanString($value);
    if ($v === null) return null;
    $x = mb_strtolower($v, 'UTF-8');
    if (in_array($x, ['oui', 'conforme'], true)) return 'conforme';
    if (in_array($x, ['non', 'non conforme'], true)) return 'non conforme';
    return $v;
}

function normalizeResultTextValue($value) {
    $value = cleanString($value);
    if ($value === null) return null;
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function daysBetweenNullable($start, $end) {
    if (!$start || !$end) return null;
    $s = strtotime((string) $start);
    $e = strtotime((string) $end);
    if ($s === false || $e === false || $e < $s) return null;
    return round(($e - $s) / 86400, 2);
}

function upsertClientCommon(mysqli $conn, $idClientRaw, $nomClientRaw): void {
    $id_client = cleanInt($idClientRaw);
    if ($id_client === null) return;
    $client_name = buildClientName($idClientRaw, $nomClientRaw);

    $sql = "INSERT INTO client (id_client, client_name)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
                client_name = COALESCE(VALUES(client_name), client_name)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Erreur prepare client : ' . $conn->error);
    $stmt->bind_param('is', $id_client, $client_name);
    if (!$stmt->execute()) throw new Exception('Erreur execute client : ' . $stmt->error);
    $stmt->close();
}

function upsertSecteurCommon(mysqli $conn, $idSecteurRaw, $nomSecteurRaw): void {
    $id_secteur = cleanInt($idSecteurRaw);
    if ($id_secteur === null) return;
    $nom_secteur = buildSecteurName($idSecteurRaw, $nomSecteurRaw);

    $sql = "INSERT INTO secteur (id_secteur, nom_secteur)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
                nom_secteur = COALESCE(VALUES(nom_secteur), nom_secteur)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Erreur prepare secteur : ' . $conn->error);
    $stmt->bind_param('is', $id_secteur, $nom_secteur);
    if (!$stmt->execute()) throw new Exception('Erreur execute secteur : ' . $stmt->error);
    $stmt->close();
}

function upsertLaborantinCommon(mysqli $conn, $idLaborantinRaw, $nomLaborantinRaw, $idSecteurRaw = null): void {
    $id_laborantin = cleanInt($idLaborantinRaw);
    if ($id_laborantin === null) return;
    $nom_laborantin = buildLaborantinName($idLaborantinRaw, $nomLaborantinRaw);
    $id_secteur = cleanInt($idSecteurRaw);

    $sql = "INSERT INTO laborantin (id_laborantin, nom_laborantin, id_secteur)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nom_laborantin = COALESCE(VALUES(nom_laborantin), nom_laborantin),
                id_secteur = COALESCE(VALUES(id_secteur), id_secteur)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Erreur prepare laborantin : ' . $conn->error);
    $stmt->bind_param('isi', $id_laborantin, $nom_laborantin, $id_secteur);
    if (!$stmt->execute()) throw new Exception('Erreur execute laborantin : ' . $stmt->error);
    $stmt->close();
}

function upsertAnalyseCommon(mysqli $conn, $idAnalyseRaw, $nomAnalyseRaw, $idSecteurRaw = null, $codeAnalyseRaw = null, $methodeRaw = null, $uniteRaw = null): void {
    $id_analyse = cleanInt($idAnalyseRaw);
    if ($id_analyse === null) return;
    $nom_analyse = cleanString($nomAnalyseRaw) ?: ('analyse_' . $id_analyse);
    $code_analyse = cleanString($codeAnalyseRaw);
    $methode_defaut = cleanString($methodeRaw);
    $unite_defaut = cleanString($uniteRaw);
    $id_secteur = cleanInt($idSecteurRaw);

    $sql = "INSERT INTO analyses (id_analyse, nom_analyse, code_analyse, methode_defaut, unite_defaut, id_secteur)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nom_analyse = COALESCE(VALUES(nom_analyse), nom_analyse),
                code_analyse = COALESCE(VALUES(code_analyse), code_analyse),
                methode_defaut = COALESCE(VALUES(methode_defaut), methode_defaut),
                unite_defaut = COALESCE(VALUES(unite_defaut), unite_defaut),
                id_secteur = COALESCE(VALUES(id_secteur), id_secteur)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Erreur prepare analyses : ' . $conn->error);
    $stmt->bind_param('issssi', $id_analyse, $nom_analyse, $code_analyse, $methode_defaut, $unite_defaut, $id_secteur);
    if (!$stmt->execute()) throw new Exception('Erreur execute analyses : ' . $stmt->error);
    $stmt->close();
}

function upsertCritereCommon(mysqli $conn, $idCritereRaw, $labelRaw = null, $descriptionRaw = null, $ruleRaw = null): void {
    $id = cleanInt($idCritereRaw);
    if ($id === null) return;

    $label = cleanString($labelRaw);
    $description = cleanString($descriptionRaw);
    $rule = cleanString($ruleRaw);

    if ($label === null && $description !== null) {
        $label = mb_substr($description, 0, 100);
    }
    if ($label === null) {
        $label = 'Critere ' . $id;
    }

    $parts = [];
    if ($description !== null) $parts[] = $description;
    if ($rule !== null) $parts[] = 'Règle: ' . $rule;
    $fullDescription = implode("\n", $parts);
    $code = 'CRIT-' . $id;

    $sql = "INSERT INTO criteres (id, code, label, description)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                code = COALESCE(VALUES(code), code),
                label = COALESCE(VALUES(label), label),
                description = COALESCE(VALUES(description), description)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Erreur prepare criteres : ' . $conn->error);
    $stmt->bind_param('isss', $id, $code, $label, $fullDescription);
    if (!$stmt->execute()) throw new Exception('Erreur execute criteres : ' . $stmt->error);
    $stmt->close();
}
