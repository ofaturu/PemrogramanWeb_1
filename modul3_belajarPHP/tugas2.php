<?php
    $jari_jari = 7;
    $selimut = 10;
    $phi = 3.14;
    $luas_alas = $phi * $jari_jari * $jari_jari;
    $luas_permukaan = ($phi * $jari_jari * $jari_jari) + ($phi * $jari_jari * $selimut);
    echo "Luas alas kerucut dengan jari-jari $jari_jari adalah : $luas_alas <br>";
    echo "Luas permukaan kerucut dengan jari-jari $jari_jari dan selimut $selimut adalah : $luas_permukaan";
?>

