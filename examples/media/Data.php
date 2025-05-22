<?php

use Random\RandomException;

function random_lipsum($amount = 1, $what = 'paras', $start = 0): SimpleXMLElement|false
{
    return simplexml_load_string(file_get_contents("http://www.lipsum.com/feed/xml?amount=$amount&what=$what&start=$start"))->lipsum;
}

function random_word($len = 10): string
{
    $word = array_merge(range('a', 'z'), range('A', 'Z'), range('0', '9'));
    shuffle($word);
    return substr(implode($word), 0, $len);
}

/**
 * @throws RandomException
 */
function random_float()
{
    return (random_int(0, 10000) / 10000);
}

/**
 * @throws RandomException
 */
function random_data($mediaPath): array
{
    $pieces = getData();
    $listTitle = 'Order N° ' . str_pad(random_int(1, 999), 4, '0', STR_PAD_LEFT) . '-' . str_pad(random_int(3000, 30000), 8, '0', STR_PAD_LEFT);
    $total = random_int(100, 1000);
    $limit = random_int(100, 1000);
    $code = random_word(14);
    $out = [
        'listTitle' => $listTitle,
        'total' => $total,
        'limit' => $limit,
        'code' => $code,
        'products' => [],
        'totals' => []
    ];
    $limitProducts = random_int(5, 15);
    $limitTotals = random_int(2, 5);
    while ($limitProducts > 0) {
        $out['products'][] = [
            'id' => str_pad(random_int(50, 9999), 6, '0', STR_PAD_LEFT),
            'serial' => strtoupper(random_word(8)),
            'ordered' => random_int(5, 250)
        ];
        $limitProducts--;
    }
    while ($limitTotals > 0) {
        $out['totals'][] = [
            'product' => substr($pieces[array_rand($pieces)], 0, random_int(10, 20)),
            'qty' => random_int(100, 5000),
            'image' => $mediaPath . DIRECTORY_SEPARATOR . 'Image' . random_int(1, 10) . '.png'
        ];
        $limitTotals--;
    }

    return $out;
}

function random_data_complex()
{
    $pieces = getData();
    //echo var_export($pieces, true).PHP_EOL.array_rand($pieces).PHP_EOL;
    $data = [
        'company' => substr($pieces[array_rand($pieces)], 0, random_int(8, 15)),
        'address' => substr($pieces[array_rand($pieces)], 0, random_int(8, 12)) . ' ' . random_int(1, 5000),
        'phone' => random_int(100000000, 999999999),
        'companyQrData' => 'https://www.google.com',
        'docNumber' => str_pad(random_int(1, 100), 4, '0', STR_PAD_LEFT) . '-' . str_pad(random_int(3000, 30000), 8, '0', STR_PAD_LEFT),
        'categories' => [],
        'paymentCode' => random_word(14)
    ];
    $limitCategories = random_int(1, 5);
    while ($limitCategories > 0) {
        $cat = [
            'description' => substr($pieces[array_rand($pieces)], 0, random_int(5, 10)),
            'items' => []
        ];

        $limitItems = random_int(1, 5);
        while ($limitItems > 0) {
            $cat['items'][] = [
                'serial' => random_word(8),
                'product' => str_replace(["\n", "\r\n", "\r",], ' ', substr($pieces[array_rand($pieces)], 0, random_int(8, 25))),
                'qty' => random_int(1, 1000),
                'price' => random_float() * 100
            ];
            $limitItems--;
        }
        $data['categories'][] = $cat;
        $limitCategories--;
    }

    return $data;
}

function getData(): array|false
{
    $str = random_lipsum(3);
    $pieces = preg_split('/(?=[A-Z])/', $str);
    array_walk($pieces, static function (&$item) {
        $item = trim($item);
        if (strlen($item) > 1) {
            $item = ucfirst($item);
        }
    });
    array_filter($pieces, static function ($item) {
        return preg_replace('/\s+/u', '', $item) !== '';
    });
    return $pieces;
}
//echo var_export(random_data_complex(), true);