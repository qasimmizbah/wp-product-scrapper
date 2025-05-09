<?php
// Function to fetch the content of a URL
function fetchUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}

// Function to extract URLs from the sitemap
function extractUrlsFromSitemap($sitemapUrl) {
    $sitemapContent = fetchUrl($sitemapUrl);
    $xml = simplexml_load_string($sitemapContent);
    $urls = [];
    foreach ($xml->url as $url) {
        $urlString = (string)$url->loc;
        if (strpos($urlString, '/product/') !== false) {
            $urls[] = $urlString;
        }
    }
    return $urls;
}
function sortUrlsByLastMod($urls) {
    usort($urls, function ($a, $b) {
        return strtotime($b['lastmod']) - strtotime($a['lastmod']);
    });
    return $urls;
}


// Function to scrape product details from a product page
function scrapeProductDetails($productUrl) {
    $productContent = fetchUrl($productUrl);
    $dom = new DOMDocument();
    @$dom->loadHTML($productContent);
    $xpath = new DOMXPath($dom);


     $outOfStockNode = $xpath->query('//p[contains(@class, "out-of-stock")]')->item(0);
    if ($outOfStockNode) {
        return null; // Skip out-of-stock products
    }
  
    


    // Extract product title
    $titleNode = $xpath->query('//h1[contains(@class, "product_title")]')->item(0);
    $title = $titleNode ? $titleNode->nodeValue : 'N/A';

    // Extract product short description
    $shortDescriptionNode = $xpath->query('//div[contains(@class, "short-description")]')->item(0);
    $shortDescription = $shortDescriptionNode ? $shortDescriptionNode->nodeValue : 'N/A';

    //Decription
    $descriptionNode = $xpath->query('//div[contains(@class, "woocommerce-Tabs-panel woocommerce-Tabs-panel--description")]')->item(0);
    $description = $descriptionNode ? $descriptionNode->nodeValue : 'N/A';

    // Extract product price
    $priceNode = $xpath->query('//p[contains(@class, "price")]')->item(0);
    $price = $priceNode ? $priceNode->nodeValue : '99';

    // Extract product image URL
    $imageNode = $xpath->query('//img[contains(@class, "wp-post-image")]')->item(0);
    $imageUrl = $imageNode ? $imageNode->getAttribute('src') : 'N/A';

    // Extract product categories from woocommerce-breadcrumb
    $categories = [];
    $categoryNodes = $xpath->query('//nav[contains(@class, "woocommerce-breadcrumb")]/a');
    foreach ($categoryNodes as $categoryNode) {
        $categories[] = $categoryNode->nodeValue;
    }

    // Extract product variations
    $variations = [];
    $variationNodes = $xpath->query('//table[contains(@class, "variations")]//tr');
    foreach ($variationNodes as $variationNode) {
        $variationName = $xpath->query('.//td[contains(@class, "label")]', $variationNode)->item(0);
        $variationValue = $xpath->query('.//td[contains(@class, "value")]', $variationNode)->item(0);

        if ($variationName && $variationValue) {
            $variations[] = [
                'name' => trim($variationName->nodeValue),
                'value' => trim($variationValue->nodeValue),
            ];
        }
    }

    return [
        'title' => trim($title),
        'short_description' => trim($shortDescription),
        'description' => trim($description),
        'price' => trim($price),
        'image_url' => trim($imageUrl),
        'categories' => implode(", ", $categories), // Convert categories array to a string
        'variations' => $variations,
        'product_url' => $productUrl,
    ];
}

// Main script
$sitemapUrl = $_REQUEST['xmlpath'];
$productUrls = extractUrlsFromSitemap($sitemapUrl);

// Define batch size and range
$batchSize = $_REQUEST['batchSize']; // Number of products per batch
$startIndex = $_REQUEST['startIndex']; // Starting index (e.g., 0 for 1-20, 20 for 21-40, etc.)
$endIndex = $startIndex + $batchSize - 1; // Ending index
;
// Ensure the end index does not exceed the total number of URLs
if ($endIndex >= count($productUrls)) {
    $endIndex = count($productUrls) - 1;
}


// Extract the batch of URLs
$batchUrls = array_slice($productUrls, $startIndex, $batchSize);

$products = [];
foreach ($batchUrls as $productUrl) {
    $products[] = scrapeProductDetails($productUrl);
}

// Output the scraped data
// echo "<pre>";
// print_r($products);
// echo "</pre>";


//CSV
$csvFileName = 'products.csv';
header('Content-Disposition: attachment; filename="'.$csvFileName.'";');
header('Content-Type: application/csv; charset=UTF-8');

$csvFile = fopen('php://output', 'w');

// Write CSV header
fputcsv($csvFile, [
    'ID',
    'Type',
    'SKU',
    'GTIN, UPC, EAN, or ISBN',
    'Name',
    'Published',
    'Is featured?',
    'Visibility in catalog',
    'Short description',
    'Description',
    'Date sale price starts',
    'Date sale price ends',
    'Tax status',
    'Tax class',
    'In stock?',
    'Stock',
    'Low stock amount',
    'Backorders allowed?',
    'Sold individually?',
    'Weight (kg)',
    'Length (cm)',
    'Width (cm)',
    'Height (cm)',
    'Allow customer reviews?',
    'Purchase note',
    'Sale price',
    'Regular price',
    'Categories',
    'Tags',
    'Shipping class',
    'Images',
    'Download limit',
    'Download expiry days',
    'Parent',
    'Grouped products',
    'Upsells',
    'Cross-sells',
    'External URL',
    'Button text',
    'Position',
    'Brands',
    'Attribute 1 name',
    'Attribute 1 value(s)',
    'Attribute 1 visible',
    'Attribute 1 global',
]);

// Write product data to CSV
foreach ($products as $product) {
    // Convert variations array to a string for attributes
    $attributeName = 'Variation';
    $attributeValues = [];
    foreach ($product['variations'] as $variation) {
        $attributeValues[] = $variation['name'] . ': ' . $variation['value'];
    }
    $attributeValuesString = implode(", ", $attributeValues);

    if($product['price']=="")
    {
        $price = "1";
    }
    else
    {
        $price = str_replace("$","",$product['price']);
    }
   
    fputcsv($csvFile, [
        '', // ID (leave empty)
        'simple', // Type (default to "simple")
        '', // SKU (leave empty)
        '', // GTIN, UPC, EAN, or ISBN (leave empty)
        $product['title'], // Name
        '1', // Published (default to "1")
        '0', // Is featured? (default to "0")
        'visible', // Visibility in catalog (default to "visible")
        $product['short_description'], // Short description
        $product['description'], // Description (leave empty)
        '', // Date sale price starts (leave empty)
        '', // Date sale price ends (leave empty)
        'taxable', // Tax status (default to "taxable")
        '', // Tax class (leave empty)
        '1', // In stock? (default to "1")
        '', // Stock (leave empty)
        '', // Low stock amount (leave empty)
        '0', // Backorders allowed? (default to "0")
        '0', // Sold individually? (default to "0")
        '', // Weight (kg) (leave empty)
        '', // Length (cm) (leave empty)
        '', // Width (cm) (leave empty)
        '', // Height (cm) (leave empty)
        '1', // Allow customer reviews? (default to "1")
        '', // Purchase note (leave empty)
        '', // Sale price (leave empty)
        $price, // Regular price
        $product['categories'], // Categories
        '', // Tags (leave empty)
        '', // Shipping class (leave empty)
        $product['image_url'], // Images
        '', // Download limit (leave empty)
        '', // Download expiry days (leave empty)
        '', // Parent (leave empty)
        '', // Grouped products (leave empty)
        '', // Upsells (leave empty)
        '', // Cross-sells (leave empty)
        '', // External URL (leave empty)
        '', // Button text (leave empty)
        '', // Position (leave empty)
        '', // Brands (leave empty)
        $attributeName, // Attribute 1 name
        $attributeValuesString, // Attribute 1 value(s)
        '1', // Attribute 1 visible (default to "1")
        '1', // Attribute 1 global (default to "1")
    ]);
}

fclose($csvFile);

//echo "CSV file '$csvFileName' has been generated successfully!";
?>
