<?php
    $r = 7;
    $selimut = 10;
    $phi = 3.14;
    $luas_alas = $phi * $r * $r;
    $luas_permukaan = ($phi * $r * $r) + ($phi * $r * $selimut);
    echo "Luas alas kerucut dengan jari-jari $r adalah : $luas_alas cm<sup>2</sup><br>";
    echo "Luas permukaan kerucut dengan jari-jari $r dan selimut $selimut adalah : $luas_permukaan cm<sup>2</sup>";
?>

