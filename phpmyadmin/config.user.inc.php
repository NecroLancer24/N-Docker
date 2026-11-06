<?php
// Dipakai di profil prod.
// - Akun tanpa password selalu ditolak.
// - Root hanya diizinkan saat membuka phpMyAdmin dari mesin server sendiri.
//   Koneksi dari host tiba di container melalui docker-proxy, sehingga alamat
//   sumbernya adalah gateway jaringan Docker (lihat /proc/net/route), bukan
//   IP klien dari LAN. Klien LAN tetap memakai akun non-root.

if (!function_exists('pma_allowed_local_ips')) {
    function pma_allowed_local_ips(): array
    {
        $ips = ['127.0.0.1', '::1'];

        $routes = @file('/proc/net/route', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($routes !== false) {
            foreach (array_slice($routes, 1) as $line) {
                $f = preg_split('/\s+/', trim($line));
                // Kolom: 0=Iface, 1=Destination, 2=Gateway (hex little-endian), 3=Flags
                // Ambil rute yang punya gateway: bit GATEWAY (0x2) pada Flags
                // dan Gateway bukan 0.0.0.0.
                if (count($f) > 3 && (hexdec($f[3]) & 0x2) && $f[2] !== '00000000') {
                    // Gateway disimpan little-endian: balik urutan tiap 2 karakter hex.
                    $hex = implode('', array_reverse(str_split($f[2], 2)));
                    $ip  = long2ip((int) hexdec($hex));
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        $ips[] = $ip;
                    }
                }
            }
        }

        return $ips;
    }
}

$rootFromLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', pma_allowed_local_ips(), true);

foreach (array_keys($cfg['Servers']) as $key) {
    $cfg['Servers'][$key]['AllowRoot'] = $rootFromLocal;
    $cfg['Servers'][$key]['AllowNoPassword'] = false;
}
