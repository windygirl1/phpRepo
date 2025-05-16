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

function exportXml(string $filename, string $categoryCode): void {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=test_samson;charset=utf8", "windygirl");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }

    $stmt = $pdo->prepare("WITH RECURSIVE category_tree AS (
                                SELECT id, code FROM a_category WHERE code = :code
                                UNION SELECT c.id, c.code FROM a_category c
                                JOIN category_tree ct ON c.parent_id = ct.id)
                                SELECT id FROM category_tree;");
    $stmt->execute([":code" => $categoryCode]);
    $catedoryIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($categoryCode)) {
        echo "Categories not found";
        return;
    }

    $stmt = $pdo->prepare("
        SELECT p.id, p.code, p.name FROM a_product p
        JOIN a_product_category pc ON p.id = pc.product_id
        WHERE pc.category_id IN (" . implode(",", array_fill(0, count($catedoryIds), "?")). ")");

    $stmt->execute($catedoryIds);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($products)) {
        echo "Products not found";
        return;
    }

    $xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><Товары/>", 0, false, 'http://www.w3.org/2001/XMLSchema-instance', false);

    foreach ($products as $product) {
        $productEl = $xml->addChild("Товар");
        $productEl->addAttribute("Код", $product["code"]);
        $productEl->addAttribute("Название", $product["name"]);

        $stmt = $pdo->prepare("SELECT type_of_price, price FROM a_price WHERE product_id = :product_id");
        $stmt->execute([":product_id" => $product["id"]]);
        $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($prices as $price) {
            $priceEl = $productEl->addChild("Цена", $price["price"]);
            $priceEl->addAttribute("Тип", $price["type_of_price"]);
        }

        $stmt = $pdo->prepare("SELECT name, value FROM a_property WHERE product_id = :product_id");
        $stmt->execute([":product_id" => $product["id"]]);
        $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $propertiesEl = $productEl->addChild("Свойства");
        foreach ($properties as $property) {
            $propertiesEl->addChild($property["name"], $property["value"]);
        }

        $stmt = $pdo->prepare("SELECT c.name FROM a_category c
                                JOIN a_product_category pc ON c.id = pc.category_id
                                WHERE pc.product_id = :product_id");
        $stmt->execute(["product_id" => $product["id"]]);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $categoriesEl = $productEl->addChild("Разделы");
        foreach ($categories as $category) {
            $categoriesEl->addChild("Раздел", $category["name"]);
        }
    }

    $xml->asXML($filename);
    echo "Data has been exported to $filename";

    $stmt = null;
    $pdo = null;
    die();
}
