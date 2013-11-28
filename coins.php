<?php

// very lazy way to solve equation
// brute force ftw

$coins = [2, 3, 5, 7, 9];

function compute($a, $b, $c, $d, $e) {
    return $a + $b * pow($c, 2) + pow($d, 3) - $e;
}

foreach ($coins as $a) {
    foreach ($coins as $b) {
        foreach ($coins as $c) {
            foreach ($coins as $d) {
                foreach ($coins as $e) {
                    if (count($coins) !== count(array_unique([$a, $b, $c, $d, $e]))) {
                        continue;
                    }
                    if (399 === compute($a, $b, $c, $d, $e)) {
                        echo json_encode([$a, $b, $c, $d, $e])."\n";
                    }
                }
            }
        }
    }
}
