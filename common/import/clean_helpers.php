<?php
if (!function_exists('mb_check_encoding')) {
    function mb_check_encoding($value, $encoding = null){ return true; }
}
if (!function_exists('mb_convert_encoding')) {
    function mb_convert_encoding($string, $to_encoding, $from_encoding = null){
        if (function_exists('iconv')) {
            $converted = @iconv(is_array($from_encoding) ? implode(',', $from_encoding) : (string)$from_encoding, (string)$to_encoding . '//IGNORE', (string)$string);
            if ($converted !== false) return $converted;
        }
        return (string)$string;
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null){
        return $length === null ? substr((string)$string, $start) : substr((string)$string, $start, $length);
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null){ return strtolower((string)$string); }
}
function normalizeValue($value){
    if ($value === null) return null;
    if (is_bool($value)) return $value ? '1' : '0';
    if (is_int($value) || is_float($value)) return (string)$value;
    $value = (string)$value;
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
    $value = trim($value);
    $value = trim($value, "\"'");
    if ($value === '' || $value === '\\N' || strtoupper($value) === 'NULL' || strtoupper($value) === 'N/A') {
        return null;
    }
    if (!mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
    }
    $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim($value);
    return $value === '' ? null : $value;
}
function cleanString($value){
    $value = normalizeValue($value);
    if ($value === null) return null;
    $value = strip_tags($value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value);
    $value = trim($value);
    return $value === '' ? null : $value;
}
function safeVarchar($value, $maxLen = 255){
    $value = cleanString($value);
    if ($value === null) return null;
    if ($maxLen <= 0) return null;
    return mb_substr($value, 0, $maxLen, 'UTF-8');
}
function cleanInt($value){
    $value = normalizeValue($value);
    if ($value === null) return null;
    if (preg_match('/^-?\d+$/', $value)) return (int)$value;
    return null;
}
function cleanFloat($value){
    $value = normalizeValue($value);
    if ($value === null) return null;
    $value = str_replace(',', '.', $value);
    return is_numeric($value) ? (float)$value : null;
}
function isExcelSerialDateValue($value){
    $value = normalizeValue($value);
    if ($value === null) return false;
    if (!preg_match('/^-?\d+(?:\.\d+)?$/', $value)) return false;
    $n = (float)$value;
    return $n >= 20000 && $n <= 80000;
}
function excelSerialToDateTime($value){
    if (!isExcelSerialDateValue($value)) return null;
    $n = (float)$value;
    $base = new DateTime('1899-12-30 00:00:00');
    $days = (int)floor($n);
    $seconds = (int)round(($n - $days) * 86400);
    $base->modify(($days >= 0 ? '+' : '') . $days . ' days');
    if ($seconds !== 0) {
        $base->modify(($seconds >= 0 ? '+' : '') . $seconds . ' seconds');
    }
    return $base->format('Y-m-d H:i:s');
}
function isInvalidBusinessDate($datetime){
    if (!$datetime) return true;
    $ts = strtotime($datetime);
    if ($ts === false) return true;
    $year = (int)date('Y', $ts);
    return $year < 1980 || $year > 2100;
}
function normalizeDateCandidate($value){
    $value = cleanString($value);
    if ($value === null) return null;
    $value = preg_replace('/(\d{2}:\d{2}:\d{2})\.\d+(?:Z)?$/i', '$1', $value);
    $value = preg_replace('/Z$/i', '', $value);
    $value = str_replace(['/', '.'], '-', $value);
    $value = str_replace('T', ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    $value = preg_replace('/\s*(UTC|GMT)$/i', '', $value);
    $value = trim($value);
    return $value === '' ? null : $value;
}
function parseDateWithFormats($value, array $formats){
    $value = normalizeDateCandidate($value);
    if ($value === null) return null;.
    if (preg_match('/^(\d{4}-\d{1,2}-\d{1,2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?)(?:\s+\d{4}-\d{1,2}-\d{1,2}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?)+$/', $value, $m)) {
        $value = $m[1];
    } elseif (preg_match('/^(\d{1,2}-\d{1,2}-\d{2,4}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?)(?:\s+\d{1,2}-\d{1,2}-\d{2,4}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?)+$/', $value, $m)) {
        $value = $m[1];
    }

    if (preg_match('/^\d{4}$/', $value)) {
        return null;
    }
    $excel = excelSerialToDateTime($value);
    if ($excel !== null) {
        return $excel;
    }
    foreach ($formats as $format) {
        $formatToUse = (strpos($format, '!') === 0) ? $format : '!' . $format;
        $dt = DateTime::createFromFormat($formatToUse, $value);
        if ($dt instanceof DateTime) {
            $errors = DateTime::getLastErrors();
            $warningCount = is_array($errors) ? ($errors['warning_count'] ?? 0) : 0;
            $errorCount = is_array($errors) ? ($errors['error_count'] ?? 0) : 0;
            if ($warningCount === 0 && $errorCount === 0) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
    }
    $ts = strtotime($value);
    if ($ts !== false) {
        return date('Y-m-d H:i:s', $ts);
    }
    return null;
}

function cleanDateTimeYMD($value){
    $dt = parseDateWithFormats($value, [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d'
    ]);
    return isInvalidBusinessDate($dt) ? null : $dt;
}
function cleanDateTimeMDY($value){
    $dt = parseDateWithFormats($value, [
        'm-d-Y H:i:s',
        'm-d-Y H:i',
        'm-d-Y',
        'm-d-y H:i:s',
        'm-d-y H:i',
        'm-d-y',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d'
    ]);
    return isInvalidBusinessDate($dt) ? null : $dt;
}
function cleanDateTimeDMY($value){
    $dt = parseDateWithFormats($value, [
        'd-m-Y H:i:s',
        'd-m-Y H:i',
        'd-m-Y',
        'd-m-y H:i:s',
        'd-m-y H:i',
        'd-m-y',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d'
    ]);
    return isInvalidBusinessDate($dt) ? null : $dt;
}
function cleanDateTimeYMDStrict($value){ return cleanDateTimeYMD($value); }
function cleanDateTimeMDYStrict($value){ return cleanDateTimeMDY($value); }
function cleanDateTimeDMYStrict($value){ return cleanDateTimeDMY($value); }
function cleanDateTimeFlexible($value){
    $raw = cleanString($value);
    if ($raw === null) return null; 
    $slashDate = preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?$/', $raw);
    $value = normalizeDateCandidate($raw);
    if ($value === null) return null;
    if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}(?: \d{1,2}:\d{2}(?::\d{2})?)?$/', $value)) {
        return cleanDateTimeYMD($value);
    }
    if ($slashDate) {
        foreach (['cleanDateTimeMDY', 'cleanDateTimeDMY', 'cleanDateTimeYMD'] as $fn) {
            $dt = $fn($value);
            if ($dt !== null) return $dt;
        }
        return null;
    }
    if (preg_match('/^\d{1,2}-\d{1,2}-\d{2,4}(?: \d{1,2}:\d{2}(?::\d{2})?)?$/', $value)) {
        $parts = explode('-', preg_replace('/\s+.*/', '', $value));
        $first = (int)$parts[0];
        $second = (int)$parts[1];
        if ($first > 12) return cleanDateTimeDMY($value);
        if ($second > 12) return cleanDateTimeMDY($value);
    }
    foreach (['cleanDateTimeYMD', 'cleanDateTimeDMY', 'cleanDateTimeMDY'] as $fn) {
        $dt = $fn($value);
        if ($dt !== null) return $dt;
    }
    return null;
}
function safeMysqlDateTime($value){
    $dt = cleanDateTimeFlexible($value);
    if ($dt === null) return null;
    return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dt) ? $dt : null;
}
function safeMysqlDate($value){
    $dt = cleanDateTimeFlexible($value);
    if ($dt === null) return null;
    return date('Y-m-d', strtotime($dt));
}
function cleanDateTimeOrdresYMD($value){
    $raw = cleanString($value);
    if ($raw === null) return null;

    if (strpos($raw, '/') !== false) {
        $dt = cleanDateTimeMDY($raw);
        if ($dt !== null) return $dt;
        return cleanDateTimeDMY($raw);
    }

    $dt = cleanDateTimeYMDStrict($raw);
    if ($dt !== null) return $dt;

    return cleanDateTimeFlexible($raw);
}
function safeMysqlDateTimeOrdres($value){
    $dt = cleanDateTimeOrdresYMD($value);
    if ($dt === null) return null;
    return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dt) ? $dt : null;
}
function safeMysqlDateOrdres($value){
    $dt = cleanDateTimeOrdresYMD($value);
    if ($dt === null) return null;
    return date('Y-m-d', strtotime($dt));
}
function safeMysqlDateTimeRapport($value){
    $valueClean = cleanString($value);
    if ($valueClean === null) return null;

    if (strpos($valueClean, '/') !== false) {
        $dt = cleanDateTimeMDY($valueClean);
        if ($dt === null) return null;
        return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dt) ? $dt : null;
    }

    return safeMysqlDateTime($valueClean);
}
function safeMysqlDateRapport($value){
    $valueClean = cleanString($value);
    if ($valueClean === null) return null;

    if (strpos($valueClean, '/') !== false) {
        $dt = cleanDateTimeMDY($valueClean);
        if ($dt === null) return null;
        return date('Y-m-d', strtotime($dt));
    }

    return safeMysqlDate($valueClean);
}
function safeMysqlDateFirstToken($value){
    $valueClean = cleanString($value);
    if ($valueClean === null) return null;

    if (preg_match('/\d{4}-\d{1,2}-\d{1,2}|\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}/', $valueClean, $m)) {
        $valueClean = $m[0];
    }

    return safeMysqlDate($valueClean);
}

?>