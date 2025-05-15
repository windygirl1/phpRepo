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

function mySortForKey(array $a, string $b): array {
    foreach ($a as $key => $arr) {
        if (!array_key_exists($b, $arr)) {
            throw new InvalidArgumentException("Invalid array: $key");
        }
    }

    usort($a, function($x, $y) use ($b) {
        return $x[$b] <=> $y[$b];
    });

    return $a;
}
