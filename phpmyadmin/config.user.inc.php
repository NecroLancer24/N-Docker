<?php
// Dipakai di profil prod: tolak login root dan akun tanpa password lewat phpMyAdmin
foreach (array_keys($cfg['Servers']) as $key) {
    $cfg['Servers'][$key]['AllowRoot'] = false;
    $cfg['Servers'][$key]['AllowNoPassword'] = false;
}
