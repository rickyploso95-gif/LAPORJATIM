<?php
// 38 kabupaten/kota Jawa Timur beserta titik pusat [lat, lng] untuk memusatkan peta.
function regencies(): array {
    return [
        'Kabupaten Bangkalan'   => [-7.0450, 112.7350],
        'Kabupaten Banyuwangi'  => [-8.2192, 114.3691],
        'Kabupaten Blitar'      => [-8.1000, 112.1600],
        'Kabupaten Bojonegoro'  => [-7.1500, 111.8800],
        'Kabupaten Bondowoso'   => [-7.9130, 113.8200],
        'Kabupaten Gresik'      => [-7.1550, 112.6550],
        'Kabupaten Jember'      => [-8.1720, 113.7000],
        'Kabupaten Jombang'     => [-7.5450, 112.2330],
        'Kabupaten Kediri'      => [-7.8200, 112.0100],
        'Kabupaten Lamongan'    => [-7.1200, 112.4200],
        'Kabupaten Lumajang'    => [-8.1330, 113.2220],
        'Kabupaten Madiun'      => [-7.6200, 111.6000],
        'Kabupaten Magetan'     => [-7.6500, 111.3300],
        'Kabupaten Malang'      => [-8.1700, 112.6800],
        'Kabupaten Mojokerto'   => [-7.5000, 112.4300],
        'Kabupaten Nganjuk'     => [-7.6050, 111.9000],
        'Kabupaten Ngawi'       => [-7.4050, 111.4400],
        'Kabupaten Pacitan'     => [-8.2000, 111.1000],
        'Kabupaten Pamekasan'   => [-7.1600, 113.4800],
        'Kabupaten Pasuruan'    => [-7.6450, 112.9050],
        'Kabupaten Ponorogo'    => [-7.8650, 111.4700],
        'Kabupaten Probolinggo' => [-7.8500, 113.3000],
        'Kabupaten Sampang'     => [-7.0450, 113.2500],
        'Kabupaten Sidoarjo'    => [-7.4500, 112.7200],
        'Kabupaten Situbondo'   => [-7.7050, 114.0100],
        'Kabupaten Sumenep'     => [-7.0100, 113.8600],
        'Kabupaten Trenggalek'  => [-8.0500, 111.7100],
        'Kabupaten Tuban'       => [-6.9000, 112.0600],
        'Kabupaten Tulungagung' => [-8.0650, 111.9000],
        'Kota Batu'             => [-7.8700, 112.5300],
        'Kota Blitar'           => [-8.0950, 112.1600],
        'Kota Kediri'           => [-7.8480, 112.0170],
        'Kota Madiun'           => [-7.6300, 111.5300],
        'Kota Malang'           => [-7.9800, 112.6300],
        'Kota Mojokerto'        => [-7.4700, 112.4340],
        'Kota Pasuruan'         => [-7.6470, 112.9070],
        'Kota Probolinggo'      => [-7.7540, 113.2150],
        'Kota Surabaya'         => [-7.2575, 112.7521],
    ];
}

function is_valid_regency($name): bool {
    return is_string($name) && array_key_exists($name, regencies());
}
