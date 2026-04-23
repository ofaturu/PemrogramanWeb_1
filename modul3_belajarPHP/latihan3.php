<?php
$a="5";
$b="2.5";
$komentar="Selamat Datang";
echo ("Nilai variabel a adalah = $a <br>");
//variabel bertipe integer
echo ("Nilai variabel b adalah = $b <br>");
//variabel bertipe real
echo ("Nilai variabel komentar adalah = $komentar<br>");
//variabel bertipe string
$tambah= $a + $b;
//rumus pengurangan
$kurang = $a - $b;
//rumus perkalian
$kali = $a * $b;
//rumus pembagian
$bagi = $a / $b;
echo ("Hasil penjumlahan a dan b adalah = $tambah <br>");
echo ("Hasil pengurangan a dan b adalah = $kurang<br>");
echo ("Hasil perkalian a dan b adalah = $kali <br>");
echo ("Hasil pembagian a dan b adalah = $bagi <br>");
$nama = "UMPKU";
$garis= "=====================================";
echo "<p>";
echo $garis."<br>";
echo $komentar. " Di Lab ". $nama. "<br>Selamat Belajar Pemrograman
Web <br>";
echo $garis."<br>";
?>