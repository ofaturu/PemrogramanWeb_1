<?php
$saldoAwal = 2000000;
$bunga = 0.03;
$bulan = 11;
$saldoAkhir = $saldoAwal + ($saldoAwal * $bunga * $bulan); // lengkapi pada perhitungan $saldo akhir
echo "Saldo awal : Rp ". $saldoAwal. ",- <br>";
echo "Bunga : ". ($bunga * 100). "% <br>";
echo "Lama waktu : ". $bulan. " bulan <br>";
echo "Saldo akhir setelah ".$bulan." bulan adalah : Rp ". $saldoAkhir. ",-";
?>