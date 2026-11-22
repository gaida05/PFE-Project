<?php

function report_supported_langs(): array
{
    return ['fr', 'en', 'ar'];
}

function report_lang_from_request(?string $value): string
{
    $lang = strtolower(trim((string)$value));
    return in_array($lang, report_supported_langs(), true) ? $lang : 'fr';
}

function report_is_rtl(string $lang): bool
{
    return $lang === 'ar';
}

function report_font_family(string $lang): string
{
    return report_is_rtl($lang) ? 'dejavusans' : 'times';
}

function report_align(string $lang): string
{
    return report_is_rtl($lang) ? 'R' : 'L';
}

function report_text_align_css(string $lang): string
{
    return report_is_rtl($lang) ? 'right' : 'left';
}

function report_html_dir(string $lang): string
{
    return report_is_rtl($lang) ? 'rtl' : 'ltr';
}

function report_texts(): array
{
    return [
        'fr' => [
            'report_title' => "RAPPORT D'ANALYSES",
            'tunis_date' => 'Tunis le :',
            'bo_ref' => 'Réf B.O :',
            'observations' => 'Observations :',
            'analyses' => 'Analyses',
            'methods' => 'Références Méthodes',
            'results' => 'Résultats',
            'trial_managers' => "Les responsables de l'essai",
            'technical_manager' => 'Responsable technique des laboratoires',
            'dossier_ref' => 'Réf Dossier :',
            'labo_ref' => 'Réf Labo :',
            'client_name' => "Nom du demandeur d'analyse :",
            'origin' => 'Origine :',
            'provenance' => 'Provenance :',
            'producer' => 'Producteur :',
            'importer' => 'Importateur :',
            'product_name' => 'Nom du produit :',
            'product_temp_reception' => 'T° du Produit à la réception :',
            'production_date' => 'Date de production :',
            'lot_no' => 'N° Lot :',
            'lot_no_full' => 'N° du lot :',
            'address' => 'Adresse :',
            'sampled_by' => 'Ech. Prélevé par :',
            'sampling_date' => 'Date du Prélèvement :',
            'reception_date' => 'Date de réception :',
            'analysis_start' => 'Date début analyse :',
            'analysis_end' => 'Date fin analyse :',
            'dlc' => 'D.L.C :',
            'customer_product_code' => 'Code produit donné par le demandeur :',
            'ph_before_incubation' => 'pH avant incubation (phase solide) :',
            'analysis_upper' => 'ANALYSE',
            'stability_control' => 'Contrôle de la stabilité des produits appertisés et assimilés',
            'parameters' => 'Paramètres',
            'decision_criteria' => 'Critères de décision * (stabilité)',
            'according_to' => 'Selon',
            'incubation_results' => 'Résultats',
            'incubation_at' => 'Incubation à',
            'incubation' => 'Incubation',
            'ambient_temp' => 'T° ambiante (7J)',
            'at_37' => 'à 37°C/7J',
            'at_55' => 'à 55°C/7J',
            'declaration' => 'Déclaration :',
            'default_observation_line1' => "Ce rapport ne concerne que l'objet soumis à l'essai pour les différents paramètres testés.",
            'default_observation_line2' => "Les résultats s'appliquent à l'échantillon tel qu'il a été reçu.",
            'default_observation_line3' => "Ce document est la propriété du laboratoire, il ne doit en aucun cas être reproduit sans l'autorisation écrite du propriétaire.",
        ],
        'en' => [
            'report_title' => 'ANALYSIS REPORT',
            'tunis_date' => 'Tunis on:',
            'bo_ref' => 'B.O Ref:',
            'observations' => 'Observations:',
            'analyses' => 'Analyses',
            'methods' => 'Method References',
            'results' => 'Results',
            'trial_managers' => 'Test managers',
            'technical_manager' => 'Technical laboratory manager',
            'dossier_ref' => 'File Ref:',
            'labo_ref' => 'Lab Ref:',
            'client_name' => 'Analysis requester:',
            'origin' => 'Origin:',
            'provenance' => 'Source:',
            'producer' => 'Producer:',
            'importer' => 'Importer:',
            'product_name' => 'Product name:',
            'product_temp_reception' => 'Product temperature on receipt:',
            'production_date' => 'Production date:',
            'lot_no' => 'Lot No.:',
            'lot_no_full' => 'Lot number:',
            'address' => 'Address:',
            'sampled_by' => 'Sampled by:',
            'sampling_date' => 'Sampling date:',
            'reception_date' => 'Reception date:',
            'analysis_start' => 'Analysis start date:',
            'analysis_end' => 'Analysis end date:',
            'dlc' => 'Expiry date:',
            'customer_product_code' => 'Customer product code:',
            'ph_before_incubation' => 'pH before incubation (solid phase):',
            'analysis_upper' => 'ANALYSIS',
            'stability_control' => 'Stability control of canned and similar products',
            'parameters' => 'Parameters',
            'decision_criteria' => 'Decision criteria * (stability)',
            'according_to' => 'According to',
            'incubation_results' => 'Results',
            'incubation_at' => 'Incubation at',
            'incubation' => 'Incubation',
            'ambient_temp' => 'Room temp. (7D)',
            'at_37' => 'at 37°C/7D',
            'at_55' => 'at 55°C/7D',
            'declaration' => 'Statement:',
            'default_observation_line1' => 'This report only concerns the item submitted for testing for the parameters that were tested.',
            'default_observation_line2' => 'The results apply to the sample as received by the laboratory.',
            'default_observation_line3' => 'This document is the property of the laboratory and may not be reproduced without prior written authorization from the owner.',
        ],
        'ar' => [
            'report_title' => 'تقرير التحاليل',
            'tunis_date' => 'تونس في :',
            'bo_ref' => 'مرجع B.O :',
            'observations' => 'ملاحظات :',
            'analyses' => 'التحاليل',
            'methods' => 'مراجع الطرق',
            'results' => 'النتائج',
            'trial_managers' => 'مسؤولو الاختبار',
            'technical_manager' => 'المسؤول التقني للمخابر',
            'dossier_ref' => 'مرجع الملف :',
            'labo_ref' => 'مرجع المخبر :',
            'client_name' => 'طالب التحليل :',
            'origin' => 'المنشأ :',
            'provenance' => 'المصدر :',
            'producer' => 'المنتج :',
            'importer' => 'المورّد :',
            'product_name' => 'اسم المنتج :',
            'product_temp_reception' => 'حرارة المنتج عند الاستلام :',
            'production_date' => 'تاريخ الإنتاج :',
            'lot_no' => 'رقم الدفعة :',
            'lot_no_full' => 'رقم الدفعة :',
            'address' => 'العنوان :',
            'sampled_by' => 'تم أخذ العينة بواسطة :',
            'sampling_date' => 'تاريخ أخذ العينة :',
            'reception_date' => 'تاريخ الاستلام :',
            'analysis_start' => 'تاريخ بداية التحليل :',
            'analysis_end' => 'تاريخ نهاية التحليل :',
            'dlc' => 'تاريخ الصلوحية :',
            'customer_product_code' => 'رمز المنتج المقدم من الحريف :',
            'ph_before_incubation' => 'الأس الهيدروجيني قبل الحضانة (الطور الصلب) :',
            'analysis_upper' => 'التحليل',
            'stability_control' => 'مراقبة ثبات المنتجات المعلبة وما شابهها',
            'parameters' => 'المعايير',
            'decision_criteria' => 'معايير القرار * (الثبات)',
            'according_to' => 'حسب',
            'incubation_results' => 'النتائج',
            'incubation_at' => 'الحضانة عند',
            'incubation' => 'الحضانة',
            'ambient_temp' => 'حرارة الغرفة (7 أيام)',
            'at_37' => 'عند 37°م/7 أيام',
            'at_55' => 'عند 55°م/7 أيام',
            'declaration' => 'التصريح :',
            'default_observation_line1' => 'هذا التقرير يخص فقط العينة المقدمة للاختبار بالنسبة إلى المعايير التي تم تحليلها.',
            'default_observation_line2' => 'تنطبق النتائج على العينة كما تم استلامها من قبل المخبر.',
            'default_observation_line3' => 'هذه الوثيقة ملك للمخبر ولا يجوز إعادة إنتاجها دون ترخيص كتابي مسبق من المالك.',
        ],
    ];
}

function rt(string $key, string $lang = 'fr'): string
{
    $texts = report_texts();
    return $texts[$lang][$key] ?? $texts['fr'][$key] ?? $key;
}

function report_dynamic_map(): array
{
    return [
        'en' => [
            'Développement produit' => 'Product development',
            'Absorption d\'eau' => 'Water absorption',
            'Fissuration' => 'Cracking',
            'Teneur en eau' => 'Moisture content',
            'Alvéographe' => 'Alveograph',
            'Terminé' => 'Completed',
            'Pas Évaluée' => 'Not evaluated',
            'Pas Evaluée' => 'Not evaluated',
            'Cadmium ppm' => 'Cadmium ppm',
            'Platform Admin' => 'Platform Admin',
            'Dénombrement des Staphylocoques à coagulase positive par comptage des colonies à 37°C UFC/g' => 'Enumeration of coagulase-positive staphylococci by colony counting at 37°C CFU/g',
            '2243 Teneur en Aflatoxines totales (B1, B2, G1 et G2) (Limite de quantification= 3µg/kg) µg/kg' => '2243 Total aflatoxin content (B1, B2, G1 and G2) (Limit of quantification = 3 µg/kg) µg/kg',
            'T° ambiante (7J)' => 'Room temp. (7D)',
            'à 37°C/7J' => 'at 37°C/7D',
            'à 55 °C/7J' => 'at 55°C/7D',
            'Odeur' => 'Odor',
            'Texture' => 'Texture',
            'Examen microscopique' => 'Microscopic examination',
            'phase solide' => 'solid phase',
            'phase liquide' => 'liquid phase',
            'Conforme' => 'Compliant',
            'Non conforme' => 'Non-compliant',
        ],
        'ar' => [
            'Développement produit' => 'تطوير المنتج',
            'Absorption d\'eau' => 'امتصاص الماء',
            'Fissuration' => 'التشقق',
            'Teneur en eau' => 'محتوى الماء',
            'Alvéographe' => 'الألفيوغراف',
            'Terminé' => 'مكتمل',
            'Pas Évaluée' => 'غير مقيّم',
            'Pas Evaluée' => 'غير مقيّم',
            'Cadmium ppm' => 'الكادميوم ppm',
            'Platform Admin' => 'مدير المنصة',
            'Dénombrement des Staphylocoques à coagulase positive par comptage des colonies à 37°C UFC/g' => 'عد المكورات العنقودية إيجابية التخثر بواسطة عد المستعمرات عند 37°م CFU/g',
            '2243 Teneur en Aflatoxines totales (B1, B2, G1 et G2) (Limite de quantification= 3µg/kg) µg/kg' => '2243 محتوى الأفلاتوكسينات الكلية (B1 وB2 وG1 وG2) (حد التكميم = 3 ميكروغرام/كغ) µg/kg',
            'T° ambiante (7J)' => 'حرارة الغرفة (7 أيام)',
            'à 37°C/7J' => 'عند 37°م/7 أيام',
            'à 55 °C/7J' => 'عند 55°م/7 أيام',
            'Odeur' => 'الرائحة',
            'Texture' => 'الملمس',
            'Examen microscopique' => 'الفحص المجهري',
            'phase solide' => 'الطور الصلب',
            'phase liquide' => 'الطور السائل',
            'Conforme' => 'مطابق',
            'Non conforme' => 'غير مطابق',
        ],
    ];
}

function report_translate_value($value, string $lang = 'fr'): string
{
    $text = trim((string)$value);
    if ($text === '' || $lang === 'fr') {
        return $text;
    }

    $map = report_dynamic_map()[$lang] ?? [];
    if (isset($map[$text])) {
        return $map[$text];
    }

    $keys = array_keys($map);
    usort($keys, static fn(string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));
    foreach ($keys as $search) {
        $text = str_replace($search, $map[$search], $text);
    }

    return $text;
}

function report_default_observation(string $lang = 'fr'): string
{
    return rt('default_observation_line1', $lang) . "\n"
        . rt('default_observation_line2', $lang) . "\n"
        . rt('default_observation_line3', $lang);
}

function report_wrap_html(string $html, string $lang = 'fr', ?string $textAlign = null): string
{
    $dir = report_html_dir($lang);
    $align = $textAlign ?? report_text_align_css($lang);
    return '<div dir="' . $dir . '" style="text-align:' . $align . ';">' . $html . '</div>';
}
