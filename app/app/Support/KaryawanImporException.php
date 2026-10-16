<?php

namespace App\Support;

use RuntimeException;

/**
 * Galat tingkat berkas pada impor karyawan: berkas tidak terbaca, kolom wajib
 * tidak ada, atau jumlah baris melewati batas.
 *
 * Dipisahkan dari galat tingkat baris (yang hanya membuat satu baris gagal)
 * supaya pemanggil bisa membedakan "berkasnya yang salah" dari "isinya yang
 * salah".
 */
class KaryawanImporException extends RuntimeException {}
