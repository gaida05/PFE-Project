<?php
function normalizeValue($value){
    if ($value === null) return null;
    $value = trim($value);
    $value = trim($value, "\"");
    if ($value === '' || $value === '\N') return null;
    if (!mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');
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
function parseDateWithFormats($value, array $formats){
    $value = cleanString($value);
    if (!$value) return null;
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);
    $value = str_replace('/', '-', $value);
    if (preg_match('/^\d{4}$/', $value)) {
        return null;
    }
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            $errors = DateTime::getLastErrors();
            if (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
    }
    return null;
}
function isInvalidBusinessDate($datetime){
    if (!$datetime) return true;
    $ts = strtotime($datetime);
    if ($ts === false) return true;
    $year = (int)date('Y', $ts);
    return $year < 1980;
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
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d'
    ]);
    return isInvalidBusinessDate($dt) ? null : $dt;
}
function cleanDateTimeYMDStrict($value){
    return cleanDateTimeYMD($value);
}
function cleanDateTimeMDYStrict($value){
    return cleanDateTimeMDY($value);
}
function cleanDateTimeDMYStrict($value){
    return cleanDateTimeDMY($value);
}
function cleanDateTimeFlexible($value){
    $dt = cleanDateTimeYMD($value);
    if ($dt !== null) return $dt;
    $dt = cleanDateTimeMDY($value);
    if ($dt !== null) return $dt;
    $dt = cleanDateTimeDMY($value);
    if ($dt !== null) return $dt;
    return null;
}
?>