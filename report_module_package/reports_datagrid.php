<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function frDate($value): string
{
    if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = strtotime((string)$value);
    return $ts ? date('d/m/Y', $ts) : (string)$value;
}

function compactLabel(string $text, int $max = 22): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text, 'UTF-8') <= $max) {
        return $text;
    }
    return mb_substr($text, 0, $max - 3, 'UTF-8') . '...';
}

$rows = [];
$errorMessage = '';

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'labo_db';

if (class_exists('mysqli')) {
    $conn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);
} else {
    $conn = null;
    $errorMessage = 'Extension mysqli indisponible. Affichage de données de démonstration.';
}

if ($conn && !$conn->connect_error) {
    $conn->set_charset('utf8mb4');

    $sql = "
        SELECT
            r.id_rapport,
            d.id_demande,
            d.ref_demande,
            d.date_creation,
            c.client_name,
            ra.id AS resultat_id,
            ra.analyse,
            ra.resultat,
            ra.unite,
            ra.ordre_affichage,
            COALESCE(e1.id_echantillon, 0) AS id_echantillon
        FROM rapports r
        INNER JOIN demande_analyse d ON d.id_demande = r.id_demande
        LEFT JOIN client c ON c.id_client = d.id_client
        LEFT JOIN resultat_analyse ra ON ra.id_demande = d.id_demande
        LEFT JOIN (
            SELECT id_demande, MIN(id_echantillon) AS id_echantillon
            FROM echantillons
            GROUP BY id_demande
        ) e1 ON e1.id_demande = d.id_demande
        ORDER BY d.date_creation DESC, d.id_demande DESC, ra.ordre_affichage ASC, ra.id ASC
        LIMIT 150
    ";

    $result = $conn->query($sql);
    if ($result) {
        while ($item = $result->fetch_assoc()) {
            $analyse = trim((string)($item['analyse'] ?? ''));
            $dateRaw = trim((string)($item['date_creation'] ?? ''));
            $sequence = (int)($item['ordre_affichage'] ?? 0);
            if ($sequence <= 0) {
                $sequence = (int)($item['resultat_id'] ?? 0);
            }
            if ($sequence <= 0) {
                $sequence = 1;
            }

            $resultatText = trim((string)($item['resultat'] ?? ''));
            $uniteText = trim((string)($item['unite'] ?? ''));
            $hasResult = $resultatText !== '';

            $rows[] = [
                'id_rapport' => (string)$item['id_rapport'],
                'id_echantillon' => (int)$item['id_echantillon'],
                'ordre_analyse' => ($dateRaw !== '' ? date('Y-m-d', strtotime($dateRaw)) : date('Y-m-d'))
                    . '-' . str_pad((string)$sequence, 6, '0', STR_PAD_LEFT)
                    . '*' . compactLabel($analyse !== '' ? $analyse : 'Rapport', 18),
                'reference_client' => trim((string)($item['client_name'] ?? '')) !== '' ? (string)$item['client_name'] : 'Développement produit',
                'demande_analyse' => (string)($item['ref_demande'] ?? ''),
                'date_creation' => frDate($dateRaw),
                'date_creation_raw' => $dateRaw !== '' ? date('Y-m-d', strtotime($dateRaw)) : '',
                'analyse' => $analyse !== '' ? $analyse : 'Rapport global',
                'laborantin' => 'Platform Admin',
                'statut' => $hasResult ? 'Terminé' : 'Pas Évaluée',
                'resultat' => $hasResult ? trim($resultatText . ' ' . $uniteText) : '-',
            ];
        }
        $result->free();
    } else {
        $errorMessage = 'Impossible de charger les données de la base. Affichage de données de démonstration.';
    }
    $conn->close();
} else {
    $errorMessage = 'Connexion base indisponible. Affichage de données de démonstration.';
}

if ($rows === []) {
    $rows = [
        [
            'id_rapport' => 'RPT-4007',
            'id_echantillon' => 1,
            'ordre_analyse' => '2026-03-13-000001*Staph. Co UFC/g',
            'reference_client' => 'Développement produit',
            'demande_analyse' => 'DA # 4007 #',
            'date_creation' => '13/03/2026',
            'date_creation_raw' => '2026-03-13',
            'analyse' => '1006 Dénombrement des Staphylocoques à coagulase positive par comptage des colonies à 37°C UFC/g',
            'laborantin' => 'Platform Admin',
            'statut' => 'Terminé',
            'resultat' => '5',
        ],
        [
            'id_rapport' => 'RPT-4006',
            'id_echantillon' => 1,
            'ordre_analyse' => '2026-03-13-000001*ABS-EAU',
            'reference_client' => 'Développement produit',
            'demande_analyse' => 'DA # 4006 #',
            'date_creation' => '13/03/2026',
            'date_creation_raw' => '2026-03-13',
            'analyse' => 'Absorption d\'eau',
            'laborantin' => 'Platform Admin',
            'statut' => 'Pas Évaluée',
            'resultat' => '-',
        ],
        [
            'id_rapport' => 'RPT-4006-B',
            'id_echantillon' => 1,
            'ordre_analyse' => '2026-03-13-000001*FISSURATION',
            'reference_client' => 'Développement produit',
            'demande_analyse' => 'DA # 4006 #',
            'date_creation' => '13/03/2026',
            'date_creation_raw' => '2026-03-13',
            'analyse' => 'Fissuration',
            'laborantin' => 'Platform Admin',
            'statut' => 'Pas Évaluée',
            'resultat' => '-',
        ],
        [
            'id_rapport' => 'RPT-4010',
            'id_echantillon' => 1,
            'ordre_analyse' => '2026-03-13-000001*TenAfl...',
            'reference_client' => 'Développement produit',
            'demande_analyse' => 'DA # 4010 #',
            'date_creation' => '13/03/2026',
            'date_creation_raw' => '2026-03-13',
            'analyse' => '2243 Teneur en Aflatoxines totales (B1, B2, G1 et G2) (Limite de quantification = 3 µg/kg) µg/kg',
            'laborantin' => 'Platform Admin',
            'statut' => 'Terminé',
            'resultat' => '0',
        ],
        [
            'id_rapport' => 'RPT-4001',
            'id_echantillon' => 1,
            'ordre_analyse' => '2026-03-12-000002*Cadmi...',
            'reference_client' => 'Développement produit',
            'demande_analyse' => 'DA # 4001 #',
            'date_creation' => '12/03/2026',
            'date_creation_raw' => '2026-03-12',
            'analyse' => '101 Cadmium ppm',
            'laborantin' => 'Platform Admin',
            'statut' => 'Terminé',
            'resultat' => '10',
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datagrid rapports</title>
    <style>
        :root {
            --grid-border: #d8dce1;
            --grid-header: #f1f3f5;
            --grid-header-2: #f9fafb;
            --grid-text: #243042;
            --grid-muted: #6b7280;
            --grid-row: #ffffff;
            --grid-row-alt: #fafbfc;
            --brand: #2563eb;
            --brand-soft: #e8f0ff;
            --success: #0f766e;
            --warning: #9a6700;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f5f7fa;
            color: var(--grid-text);
        }

        .page-shell {
            padding: 28px;
        }

        .grid-card {
            background: #fff;
            border: 1px solid var(--grid-border);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .grid-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--grid-border);
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfd 100%);
        }

        .grid-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .grid-subtitle {
            margin: 4px 0 0;
            font-size: 13px;
            color: var(--grid-muted);
        }

        .grid-legend {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            font-size: 12px;
            color: var(--grid-muted);
        }

        .legend-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            background: #f8fafc;
            border: 1px solid var(--grid-border);
            border-radius: 999px;
        }

        .legend-chip b {
            color: #111827;
        }

        .grid-scroll {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1450px;
        }

        thead th {
            border-right: 1px solid var(--grid-border);
            border-bottom: 1px solid var(--grid-border);
            background: var(--grid-header);
            color: #354052;
            font-weight: 600;
            text-align: left;
            padding: 10px 10px;
            vertical-align: top;
        }

        thead tr.filter-row th {
            background: var(--grid-header-2);
            padding: 8px 8px;
        }

        .th-wrap {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            min-height: 38px;
        }

        .th-label {
            line-height: 1.35;
            word-break: break-word;
        }

        .header-icon {
            width: 18px;
            height: 18px;
            flex: 0 0 18px;
            color: #b1b8c2;
        }

        .filter-box {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 22px;
        }

        .filter-box input {
            width: 100%;
            border: 0;
            outline: none;
            background: transparent;
            font-size: 13px;
            color: #374151;
            padding: 0;
        }

        .filter-box input::placeholder {
            color: #b4bac4;
        }

        .search-icon,
        .calendar-icon {
            font-size: 16px;
            line-height: 1;
            color: #818b98;
        }

        tbody td {
            border-right: 1px solid var(--grid-border);
            border-bottom: 1px solid var(--grid-border);
            background: var(--grid-row);
            padding: 12px 10px;
            vertical-align: top;
            line-height: 1.45;
            color: #243042;
        }

        tbody tr:nth-child(even) td {
            background: var(--grid-row-alt);
        }

        .cell-break {
            white-space: normal;
            word-break: break-word;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid transparent;
        }

        .status-done {
            background: #ecfdf5;
            color: var(--success);
            border-color: #bbf7d0;
        }

        .status-pending {
            background: #fff7ed;
            color: var(--warning);
            border-color: #fed7aa;
        }

        .result-value {
            font-weight: 700;
            color: #1f2937;
        }

        .report-cell {
            min-width: 280px;
        }

        .report-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 8px;
        }

        .report-btn,
        .mini-btn,
        .fr-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border-radius: 8px;
            cursor: pointer;
            transition: .18s ease;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #1f2937;
            font-size: 12px;
            font-weight: 600;
        }

        .report-btn {
            min-width: 72px;
            padding: 8px 10px;
        }

        .report-btn.is-active {
            background: var(--brand-soft);
            border-color: #93c5fd;
            color: var(--brand);
        }

        .report-btn:hover,
        .mini-btn:hover,
        .fr-link:hover {
            border-color: #94a3b8;
            transform: translateY(-1px);
        }

        .mini-btn {
            width: 34px;
            height: 30px;
            padding: 0;
            background: #f8fafc;
        }

        .mini-buttons {
            display: inline-flex;
            gap: 6px;
        }

        .fr-link {
            padding: 7px 10px;
            background: #f8fafc;
        }

        .report-note {
            display: flex;
            gap: 8px;
            align-items: center;
            color: var(--grid-muted);
            font-size: 11px;
            flex-wrap: wrap;
        }

        .empty-state {
            padding: 24px;
            color: var(--grid-muted);
            text-align: center;
        }

        .info-banner {
            margin: 14px 18px 0;
            padding: 10px 12px;
            border: 1px solid #fde68a;
            background: #fffbeb;
            color: #92400e;
            font-size: 13px;
            border-radius: 10px;
        }

        @media (max-width: 960px) {
            .page-shell {
                padding: 14px;
            }

            .grid-topbar {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <main class="page-shell">
        <section class="grid-card">
            <div class="grid-topbar">
                <div>
                    <h1 class="grid-title">Listing des rapports</h1>
                    <p class="grid-subtitle">Trois formats de rapport + deux mini-boutons de traduction (AR / EN). Le format français reste le comportement par défaut.</p>
                </div>
                <div class="grid-legend">
                    <span class="legend-chip"><b>Format 1/2/3</b> ouvrir le PDF</span>
                    <span class="legend-chip"><b>AR</b> traduction arabe</span>
                    <span class="legend-chip"><b>EN</b> traduction anglaise</span>
                </div>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="info-banner"><?= e($errorMessage) ?></div>
            <?php endif; ?>

            <div class="grid-scroll">
                <table data-report-table data-report-endpoint="new_report_i18n.php">
                    <thead>
                        <tr>
                            <th style="width: 190px;">
                                <div class="th-wrap">
                                    <span class="th-label">Ordre<br>d'analyse</span>
                                    <span class="header-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 5h18l-7 8v5l-4 2v-7L3 5z"/></svg>
                                    </span>
                                </div>
                            </th>
                            <th style="width: 180px;">
                                <div class="th-wrap">
                                    <span class="th-label">reference<br>client</span>
                                    <span class="header-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 5h18l-7 8v5l-4 2v-7L3 5z"/></svg>
                                    </span>
                                </div>
                            </th>
                            <th style="width: 170px;">
                                <div class="th-wrap">
                                    <span class="th-label">Demande<br>Analyse</span>
                                    <span class="header-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 5h18l-7 8v5l-4 2v-7L3 5z"/></svg>
                                    </span>
                                </div>
                            </th>
                            <th style="width: 150px;">
                                <div class="th-wrap">
                                    <span class="th-label">Date de<br>création</span>
                                    <span class="header-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 5h18l-7 8v5l-4 2v-7L3 5z"/></svg>
                                    </span>
                                </div>
                            </th>
                            <th style="width: 330px;">
                                <div class="th-wrap">
                                    <span class="th-label">Analyse</span>
                                    <span class="header-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 5h18l-7 8v5l-4 2v-7L3 5z"/></svg>
                                    </span>
                                </div>
                            </th>
                            <th style="width: 170px;">
                                <div class="th-wrap">
                                    <span class="th-label">Laborantin</span>
                                    <span class="header-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 5h18l-7 8v5l-4 2v-7L3 5z"/></svg>
                                    </span>
                                </div>
                            </th>
                            <th style="width: 160px;">
                                <div class="th-wrap">
                                    <span class="th-label">Statut</span>
                                    <span class="header-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 5h18l-7 8v5l-4 2v-7L3 5z"/></svg>
                                    </span>
                                </div>
                            </th>
                            <th style="width: 110px;">
                                <div class="th-wrap">
                                    <span class="th-label">Résultat</span>
                                    <span class="header-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 5h18l-7 8v5l-4 2v-7L3 5z"/></svg>
                                    </span>
                                </div>
                            </th>
                            <th style="width: 320px;">
                                <div class="th-wrap">
                                    <span class="th-label">Rapports</span>
                                    <span class="header-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 5h18l-7 8v5l-4 2v-7L3 5z"/></svg>
                                    </span>
                                </div>
                            </th>
                        </tr>
                        <tr class="filter-row">
                            <th><div class="filter-box"><span class="search-icon">⌕</span><input class="grid-filter" data-column="ordre_analyse" type="text" placeholder=""></div></th>
                            <th><div class="filter-box"><span class="search-icon">⌕</span><input class="grid-filter" data-column="reference_client" type="text" placeholder=""></div></th>
                            <th><div class="filter-box"><span class="search-icon">⌕</span><input class="grid-filter" data-column="demande_analyse" type="text" placeholder=""></div></th>
                            <th><div class="filter-box"><span class="search-icon">⌕</span><input class="grid-filter" data-column="date_creation" type="date" placeholder=""><span class="calendar-icon">📅</span></div></th>
                            <th><div class="filter-box"><span class="search-icon">⌕</span><input class="grid-filter" data-column="analyse" type="text" placeholder=""></div></th>
                            <th><div class="filter-box"><span class="search-icon">⌕</span><input class="grid-filter" data-column="laborantin" type="text" placeholder=""></div></th>
                            <th><div class="filter-box"><span class="search-icon">⌕</span><input class="grid-filter" data-column="statut" type="text" placeholder=""></div></th>
                            <th><div class="filter-box"><span class="search-icon">⌕</span><input class="grid-filter" data-column="resultat" type="text" placeholder=""></div></th>
                            <th><div class="filter-box"><span class="search-icon">⌕</span><input class="grid-filter" data-column="rapport" type="text" placeholder="Format / langue"></div></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="9" class="empty-state">Aucun rapport disponible.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $baseHref = 'new_report_i18n.php?id_rapport=' . rawurlencode((string)$row['id_rapport'])
                                    . '&id_echantillon=' . (int)$row['id_echantillon'];
                                $statusClass = $row['statut'] === 'Terminé' ? 'status-done' : 'status-pending';
                            ?>
                            <tr data-report-id="<?= e($row['id_rapport']) ?>" data-sample-id="<?= (int)$row['id_echantillon'] ?>" data-selected-format="format_1">
                                <td data-cell="ordre_analyse" data-raw="<?= e($row['ordre_analyse']) ?>" class="cell-break"><?= e($row['ordre_analyse']) ?></td>
                                <td data-cell="reference_client" data-raw="<?= e($row['reference_client']) ?>" class="cell-break"><?= e($row['reference_client']) ?></td>
                                <td data-cell="demande_analyse" data-raw="<?= e($row['demande_analyse']) ?>" class="cell-break"><?= e($row['demande_analyse']) ?></td>
                                <td data-cell="date_creation" data-raw="<?= e($row['date_creation_raw']) ?>" class="cell-break"><?= e($row['date_creation']) ?></td>
                                <td data-cell="analyse" data-raw="<?= e($row['analyse']) ?>" class="cell-break"><?= e($row['analyse']) ?></td>
                                <td data-cell="laborantin" data-raw="<?= e($row['laborantin']) ?>" class="cell-break"><?= e($row['laborantin']) ?></td>
                                <td data-cell="statut" data-raw="<?= e($row['statut']) ?>"><span class="status-pill <?= $statusClass ?>"><?= e($row['statut']) ?></span></td>
                                <td data-cell="resultat" data-raw="<?= e($row['resultat']) ?>"><span class="result-value"><?= e($row['resultat']) ?></span></td>
                                <td data-cell="rapport" data-raw="format 1 format 2 format 3 ar en fr" class="report-cell">
                                    <div class="report-buttons">
                                        <a class="report-btn js-format-btn is-active" href="<?= e($baseHref . '&format=format_1&lang=fr') ?>" data-format="format_1" target="_blank" rel="noopener">Format 1</a>
                                        <a class="report-btn js-format-btn" href="<?= e($baseHref . '&format=format_2&lang=fr') ?>" data-format="format_2" target="_blank" rel="noopener">Format 2</a>
                                        <a class="report-btn js-format-btn" href="<?= e($baseHref . '&format=format_3&lang=fr') ?>" data-format="format_3" target="_blank" rel="noopener">Format 3</a>
                                        <span class="mini-buttons">
                                            <button type="button" class="mini-btn js-lang-btn" data-lang="ar" title="Ouvrir en arabe">AR</button>
                                            <button type="button" class="mini-btn js-lang-btn" data-lang="en" title="Open in English">EN</button>
                                        </span>
                                    </div>
                                    <div class="report-note">
                                        <a class="fr-link" href="<?= e($baseHref . '&format=format_1&lang=fr') ?>" target="_blank" rel="noopener">Lien FR</a>
                                        <span>Les mini-boutons utilisent le dernier format sélectionné.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="report-actions.js"></script>
</body>
</html>
