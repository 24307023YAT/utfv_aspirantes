<?php
header('Content-Type: application/json; charset=utf-8');

$cp = preg_replace('/\D/', '', $_GET['cp'] ?? '');

if (strlen($cp) !== 5) {
    echo json_encode(['ok' => false, 'error' => 'CP inválido']);
    exit;
}

$municipio = '';
$colonias = [];

// 1. Consulta a SEPOMEX desde PHP (sin bloqueo CORS)
$ch = curl_init("https://sepomex.icalialabs.com/api/v1/zip_codes?zip_code={$cp}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 4,
    CURLOPT_SSL_VERIFYPEER => false
]);
$respSepomex = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $respSepomex) {
    $data = json_decode($respSepomex, true);
    if (!empty($data['zip_codes'])) {
        $municipio = $data['zip_codes'][0]['d_mnpio'] ?? '';
        foreach ($data['zip_codes'] as $item) {
            if (!empty($item['d_asenta']) && !in_array($item['d_asenta'], $colonias, true)) {
                $colonias[] = $item['d_asenta'];
            }
        }
    }
}

// 2. Respaldo con Zippopotam para colonias si SEPOMEX no respondió
if (empty($colonias)) {
    $ch2 = curl_init("https://api.zippopotam.us/mx/{$cp}");
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $respZippo = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    if ($httpCode2 === 200 && $respZippo) {
        $data2 = json_decode($respZippo, true);
        foreach ($data2['places'] ?? [] as $place) {
            if (!empty($place['place name']) && !in_array($place['place name'], $colonias, true)) {
                $colonias[] = $place['place name'];
            }
        }
    }
}

// 3. municipios
if ($municipio === '') {
    $cpNum = (int)$cp;
    $rangosMunicipios = [
        [54400, 54499, 'Nicolás Romero'],
        [52900, 52999, 'Atizapán de Zaragoza'],
        [54700, 54769, 'Cuautitlán Izcalli'],
        [54800, 54879, 'Cuautitlán'],
        [54000, 54199, 'Tlalnepantla de Baz'],
        [54600, 54699, 'Tepotzotlán'],
        [54900, 54999, 'Tultitlán'],
        [55700, 55739, 'Coacalco de Berriozábal'],
        [55000, 55549, 'Ecatepec de Morelos'],
        [53000, 53999, 'Naucalpan de Juárez'],
        [52760, 52799, 'Huixquilucan'],
        [54300, 54349, 'Villa del Carbón'],
        [54350, 54399, 'Jilotzingo'],
        [54500, 54559, 'Isidro Fabela'],
        [55600, 55699, 'Zumpango'],
        [55740, 55799, 'Tecámac'],
        [56000, 56269, 'Texcoco'],
        [57000, 57999, 'Nezahualcóyotl'],
        [50000, 50299, 'Toluca'],
        [52140, 52179, 'Metepec'],
        [2000,  2999,  'Azcapotzalco'],
        [7000,  7999,  'Gustavo A. Madero'],
        [11000, 11999, 'Miguel Hidalgo'],
        [6000,  6999,  'Cuauhtémoc']
    ];

    foreach ($rangosMunicipios as [$min, $max, $nombreMun]) {
        if ($cpNum >= $min && $cpNum <= $max) {
            $municipio = $nombreMun;
            break;
        }
    }
}

echo json_encode([
    'ok' => ($municipio !== '' || !empty($colonias)),
    'municipio' => $municipio,
    'colonias' => $colonias
], JSON_UNESCAPED_UNICODE);