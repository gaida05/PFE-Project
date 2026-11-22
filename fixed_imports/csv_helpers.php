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
?>