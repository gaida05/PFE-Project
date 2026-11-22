<?php
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
function resolveCsvPath(array $candidates){
    foreach ($candidates as $file) {
        if ($file && file_exists($file)) {
            return $file;
        }
    }
    throw new Exception('Fichier CSV introuvable : ' . implode(' | ', $candidates));
}
function openCsvAssoc($file){
    $delimiter = detectDelimiter($file);
    $handle = fopen($file, 'r');
    if (!$handle) {
        throw new Exception("Impossible d'ouvrir le fichier : " . $file);
    }
    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) {
        throw new Exception("CSV vide : " . $file);
    }
    $headers = array_map(function($h){
        $h = preg_replace('/^\xEF\xBB\xBF/', '', (string)$h);
        return trim($h, " \t\n\r\0\x0B\"");
    }, $headers);

    return [$handle, $delimiter, $headers];
}
function rowIsEmpty(array $row){
    foreach ($row as $v) {
        $s = trim((string)$v);
        if ($s !== '' && $s !== '\\N') return false;
    }
    return true;
}
function rowLooksLikeHeader(array $row, array $headers){
    $row = array_slice(array_pad($row, count($headers), null), 0, count($headers));
    $normalizedRow = array_map(fn($x) => trim((string)$x), $row);
    return $normalizedRow === $headers;
}
function nextAssocRow($handle, $delimiter, $headers){
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if ($row === [null] || rowIsEmpty($row)) {
            continue;
        }
        $row = array_pad($row, count($headers), null);
        if (rowLooksLikeHeader($row, $headers)) {
            continue;
        }
        return array_combine($headers, array_slice($row, 0, count($headers)));
    }
    return false;
}
?>