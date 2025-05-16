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

function importXml(string $filename): void {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=test_samson;charset=utf8", "windygirl");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

    $xmlContent = file_get_contents($filename);

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlContent);

    if (!$xml) {
        echo "XML errors: <br>";
        foreach (libxml_get_errors() as $error) {
            echo htmlspecialchars($error->message) . "<br>";
        }
        return;
    }

    foreach ($xml->Товар as $product) {
        $code = $product["Код"];
        $name = $product["Название"];

        $stmt = $pdo->prepare("SELECT id FROM a_product WHERE code = :code;");
        $stmt->execute([":code" => $code]);

        if ($stmt->rowCount() === 0) {
            $stmt = $pdo->prepare("INSERT INTO a_product (code, name) VALUES (:code, :name);");
            $stmt->execute([":code" => $code, ":name" => $name]);
            $productId = $pdo->lastInsertId();
        } else {
            $productId = $stmt->fetchColumn();
        }

        foreach ($product->Свойства->children() as $property) {
            $propertyName = $property->getName();
            $propertyValue = (string) $property;
            $stmt = $pdo->prepare("INSERT INTO a_property (product_id, name, value) VALUES (:product_id, :name, :value);");
            $stmt->execute([":product_id" => $productId, ":name" => $propertyName, ":value" => $propertyValue]);
        }

        foreach ($product->Цена as $price) {
            $priceType = $price["Тип"];
            $priceValue = (float) $price;
            $stmt = $pdo->prepare("INSERT INTO a_price (product_id, type_of_price, price) VALUES (:product_id, :type_of_price, :price);");
            $stmt->execute([':product_id' => $productId, ':type_of_price' => $priceType, ':price' => $priceValue]);
        }

        foreach ($product->Разделы->Раздел as $category) {
            $categoryName = (string) $category;
            $categoryCode = substr(md5($categoryName . uniqid('', true)), 0, 10);

            $stmt = $pdo->prepare("SELECT id FROM a_category WHERE code = :code;");
            $stmt->execute([":code" => $categoryCode]);

            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare("INSERT INTO a_category (code, name) VALUES (:code, :name);");
                $stmt->execute([":code" => $categoryCode, ":name" => $categoryName]);
                $categoryId = $pdo->lastInsertId();
            } else {
                $categoryId = $stmt->fetchColumn();
            }

            $stmt = $pdo->prepare("INSERT INTO a_product_category (product_id, category_id) VALUES (:product_id, :category_id);");
            $stmt->execute([':product_id' => $productId, ':category_id' => $categoryId]);
        }
    }

    echo "Data has been imported";
    $stmt = null;
    $pdo = null;
    die();
}

importXml('temp.xml');