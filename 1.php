<?php

function findSimple(int $a, int $b): array {
    if ($a < 0 || $b < 0 || $a >= $b) {
        throw new InvalidArgumentException("Невалидные аргументы");
    }
    $arr = [];

    for ($i = $a; $i <= $b; $i++) {
        if ($i < 2) continue;

        $isSimple = true;
        for ($k = 2; $k <= sqrt($i); $k++) {
            if ($i % $k === 0) {
                $isSimple = false;
                break;
            }
        }

        if ($isSimple) {
            $arr[] = $i;
        }
    }

    return $arr;
}

function createTrapeze(array $a): array {
    $arr = [];

    if (count($a) % 3 !== 0) {
        throw new InvalidArgumentException("Количество элементов должно быть кратно 3");
    }

    $items = array_chunk($a, 3);

    foreach ($items as $item) {
        $arr[] = [
            "a" => $item[0],
            "b" => $item[1],
            "c" => $item[2]
        ];
    }

    return $arr;
}

function squareTrapeze(array &$a): void {
    for ($i = 0; $i < count($a); $i++) {
        $a[$i]["s"] = (($a[$i]["a"] + $a[$i]["b"]) / 2) *  $a[$i]["c"];
    }
}

function getSizeForLimit(array $a, float $b): array {
    $arr = null;

    foreach ($a as $item) {
        if ($item["s"] <= $b) {
            if ($arr === null || $item["s"] > $arr["s"]) {
                $arr = $item;
            }
        }
    }

    return $arr ?? [];
}

abstract class BaseMath {
    public function exp1(float $a, float $b, float $c): float {
        return $a * ($b ** $c);
    }

    public function exp2(float $a, float $b, float $c): float {
        return ($a / $b) ** $c;
    }

    abstract public function getValue(): float;
}

class F1 extends BaseMath {
    private float $a;
    private float $b;
    private float $c;

    public function __construct(float $a, float $b, float $c) {
        $this->a = $a;
        $this->b = $b;
        $this->c = $c;
    }

    public function getValue(): float {
        return $this->exp1($this->a, $this->b, $this->c) + ((fmod($this->exp2($this->a, $this->b, $this->c), 3)) ** min($this->a, $this->b, $this->c));
    }
}

?>

