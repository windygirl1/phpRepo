<?php

function convertString(string $a, string $b): string {
    if (substr_count($a, $b) < 2) {
        return $a;
    }

    $firstPos = strpos($a, $b);
    $secondPos = strpos($a, $b, $firstPos + strlen($b));

    $revB = strrev($b);

    return substr_replace($a, $revB, $secondPos, strlen($b));
}
