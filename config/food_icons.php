<?php
/**
 * AK Menu System — Auto food-photo matcher.
 *
 * Ships a bundled pool of appetizing, veg-safe food photos in assets/img/food/
 * (downloaded from TheMealDB during the build — served from our OWN server so
 * they never break at display time). Given an item name (and optionally its
 * category name), guessFoodImage() returns a RELATIVE path like
 * 'assets/img/food/paneer.jpg' which mediaUrl() will prefix with BASE_URL.
 *
 * Used by AI import (api/ai.php save), public signup (api/signup.php) and the
 * bulk "Auto-add photos" endpoint (api/autophoto.php) so freshly extracted
 * menus look rich immediately. Owners can still replace any photo later.
 *
 * This file only READS from the pool; it never writes. No DB access.
 */

if (!function_exists('foodImageDir')) {

/** Absolute directory that holds the bundled food photos. */
function foodImageDir(): string {
    return (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__)) . '/assets/img/food';
}

/**
 * Keyword => filename map (most-specific keywords listed; matched longest-first
 * so e.g. "gulab jamun" beats "jam"). Every filename here exists in the pool.
 */
function foodKeywordMap(): array {
    return [
        // Paneer / tikka / grills
        'paneer tikka'   => 'tikka.jpg',
        'paneer'         => 'paneer.jpg',
        'tikka'          => 'tikka.jpg',
        'tandoori'       => 'tikka.jpg',
        'kabab'          => 'tikka.jpg',
        'kebab'          => 'tikka.jpg',
        // Curries / sabzi / gravies
        'butter masala'  => 'paneer.jpg',
        'kaju'           => 'curry.jpg',
        'kofta'          => 'curry.jpg',
        'kolhapuri'      => 'curry.jpg',
        'makhani'        => 'dal.jpg',
        'palak'          => 'curry.jpg',
        'chana'          => 'curry.jpg',
        'chole'          => 'curry.jpg',
        'sabzi'          => 'curry.jpg',
        'shaak'          => 'curry.jpg',
        'masala'         => 'curry.jpg',
        'gravy'          => 'curry.jpg',
        'curry'          => 'curry.jpg',
        'dal'            => 'dal.jpg',
        'daal'           => 'dal.jpg',
        'kadhi'          => 'dal.jpg',
        // Rice
        'biryani'        => 'biryani.jpg',
        'biriyani'       => 'biryani.jpg',
        'pulao'          => 'pulao.jpg',
        'pulav'          => 'pulao.jpg',
        'jeera rice'     => 'rice.jpg',
        'fried rice'     => 'rice.jpg',
        'curd rice'      => 'rice.jpg',
        'rice'           => 'rice.jpg',
        // Chinese
        'hakka'          => 'noodles.jpg',
        'schezwan'       => 'noodles.jpg',
        'noodle'         => 'noodles.jpg',
        'manchurian'     => 'manchurian.jpg',
        'manchow'        => 'manchurian.jpg',
        'chilli'         => 'chilli.jpg',
        'chili'          => 'chilli.jpg',
        'crispy'         => 'manchurian.jpg',
        // South Indian
        'masala dosa'    => 'dosa.jpg',
        'rava dosa'      => 'dosa.jpg',
        'dosa'           => 'dosa.jpg',
        'idli'           => 'idli.jpg',
        'uttapam'        => 'uttapam.jpg',
        'uttappam'       => 'uttapam.jpg',
        'vada'           => 'vada.jpg',
        'medu'           => 'vada.jpg',
        'sambhar'        => 'idli.jpg',
        'sambar'         => 'idli.jpg',
        // Snacks / starters / chaat
        'samosa'         => 'samosa.jpg',
        'pakora'         => 'pakora.jpg',
        'pakoda'         => 'pakora.jpg',
        'bhaji'          => 'pakora.jpg',
        'spring roll'    => 'frankie.jpg',
        'frankie'        => 'frankie.jpg',
        'kathi'          => 'frankie.jpg',
        'wrap'           => 'frankie.jpg',
        'roll'           => 'frankie.jpg',
        'chaat'          => 'chaat.jpg',
        'bhel'           => 'chaat.jpg',
        'pani puri'      => 'chaat.jpg',
        'puri'           => 'chaat.jpg',
        'tikki'          => 'chaat.jpg',
        'corn'           => 'chaat.jpg',
        // Soups
        'soup'           => 'soup.jpg',
        // Breads
        'butter naan'    => 'naan.jpg',
        'garlic naan'    => 'naan.jpg',
        'naan'           => 'naan.jpg',
        'kulcha'         => 'naan.jpg',
        'paratha'        => 'paratha.jpg',
        'rotla'          => 'roti.jpg',
        'rotli'          => 'roti.jpg',
        'roti'           => 'roti.jpg',
        'chapati'        => 'roti.jpg',
        'bhatura'        => 'naan.jpg',
        // Fast food
        'pizza'          => 'pizza.jpg',
        'burger'         => 'burger.jpg',
        'sandwich'       => 'sandwich.jpg',
        'toast'          => 'sandwich.jpg',
        'pasta'          => 'pasta.jpg',
        'macaroni'       => 'pasta.jpg',
        'salad'          => 'salad.jpg',
        // Thali / combo
        'thali'          => 'thali.jpg',
        'combo'          => 'thali.jpg',
        'platter'        => 'thali.jpg',
        // Beverages
        'lassi'          => 'lassi.jpg',
        'chaas'          => 'lassi.jpg',
        'chhas'          => 'lassi.jpg',
        'buttermilk'     => 'lassi.jpg',
        'masala chai'    => 'tea.jpg',
        'chai'           => 'tea.jpg',
        'tea'            => 'tea.jpg',
        'coffee'         => 'coffee.jpg',
        'cappuccino'     => 'coffee.jpg',
        'latte'          => 'coffee.jpg',
        'milkshake'      => 'shake.jpg',
        'shake'          => 'shake.jpg',
        'smoothie'       => 'shake.jpg',
        'juice'          => 'juice.jpg',
        'lime soda'      => 'juice.jpg',
        'soda'           => 'juice.jpg',
        'mojito'         => 'juice.jpg',
        // Desserts
        'ice cream'      => 'icecream.jpg',
        'icecream'       => 'icecream.jpg',
        'kulfi'          => 'icecream.jpg',
        'sundae'         => 'icecream.jpg',
        'brownie'        => 'brownie.jpg',
        'cake'           => 'cake.jpg',
        'pastry'         => 'cake.jpg',
        'gulab jamun'    => 'gulab-jamun.jpg',
        'jamun'          => 'gulab-jamun.jpg',
        'halwa'          => 'halwa.jpg',
        'halva'          => 'halwa.jpg',
        'sheera'         => 'halwa.jpg',
        'rasmalai'       => 'rasmalai.jpg',
        'rasgulla'       => 'rasmalai.jpg',
        'basundi'        => 'rasmalai.jpg',
        'kheer'          => 'kheer.jpg',
        'kulfi falooda'  => 'icecream.jpg',
    ];
}

/**
 * Category-name => filename fallbacks, matched when no item keyword hit.
 * Keys are substrings tested against the lowercased category name.
 */
function foodCategoryMap(): array {
    return [
        'starter'   => 'samosa.jpg',
        'appetizer' => 'samosa.jpg',
        'snack'     => 'samosa.jpg',
        'soup'      => 'soup.jpg',
        'beverage'  => 'tea.jpg',
        'drink'     => 'juice.jpg',
        'shake'     => 'shake.jpg',
        'juice'     => 'juice.jpg',
        'dessert'   => 'cake.jpg',
        'sweet'     => 'gulab-jamun.jpg',
        'mithai'    => 'gulab-jamun.jpg',
        'bread'     => 'naan.jpg',
        'roti'      => 'roti.jpg',
        'naan'      => 'naan.jpg',
        'rice'      => 'rice.jpg',
        'biryani'   => 'biryani.jpg',
        'chinese'   => 'noodles.jpg',
        'south'     => 'dosa.jpg',
        'sabzi'     => 'curry.jpg',
        'sabji'     => 'curry.jpg',
        'shaak'     => 'curry.jpg',
        'punjabi'   => 'paneer.jpg',
        'gujarati'  => 'thali.jpg',
        'kathiyawadi'=> 'thali.jpg',
        'thali'     => 'thali.jpg',
        'combo'     => 'thali.jpg',
        'pizza'     => 'pizza.jpg',
        'burger'    => 'burger.jpg',
        'fast food' => 'burger.jpg',
        'salad'     => 'salad.jpg',
        'main'      => 'curry.jpg',
        'curry'     => 'curry.jpg',
        'tandoor'   => 'tikka.jpg',
        'chaat'     => 'chaat.jpg',
    ];
}

/** Generic, veg-safe fallbacks used when nothing else matches (deterministic pick). */
function foodGenericPool(): array {
    return ['food1.jpg', 'food2.jpg', 'food3.jpg', 'food4.jpg', 'food5.jpg',
            'food6.jpg', 'food7.jpg', 'food8.jpg', 'thali.jpg', 'curry.jpg', 'salad.jpg'];
}

/**
 * Pick a relevant bundled food photo for an item.
 *
 * Strategy: (1) longest/most-specific keyword match in the item name,
 * (2) else a category-name fallback, (3) else a deterministic generic photo
 * chosen by crc32($itemName) so items vary but stay stable across runs.
 *
 * @return string|null relative path 'assets/img/food/xxx.jpg', or null if the
 *                     pool is missing entirely.
 */
function guessFoodImage(string $itemName, string $categoryName = ''): ?string {
    $dir  = foodImageDir();
    $rel  = 'assets/img/food/';
    $name = mb_strtolower(trim($itemName));
    $cat  = mb_strtolower(trim($categoryName));

    // 1) Item-name keyword match, longest keyword first (most specific wins).
    if ($name !== '') {
        $keywords = foodKeywordMap();
        uksort($keywords, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($keywords as $kw => $file) {
            if (strpos($name, $kw) !== false && is_file($dir . '/' . $file)) {
                return $rel . $file;
            }
        }
    }

    // 2) Category-name fallback.
    if ($cat !== '') {
        foreach (foodCategoryMap() as $kw => $file) {
            if (strpos($cat, $kw) !== false && is_file($dir . '/' . $file)) {
                return $rel . $file;
            }
        }
    }

    // 3) Deterministic generic pick (stable per item name).
    $pool = array_values(array_filter(foodGenericPool(), fn($f) => is_file($dir . '/' . $f)));
    if (!$pool) { return null; }
    $idx = crc32($itemName !== '' ? $itemName : 'food') % count($pool);
    return $rel . $pool[$idx];
}

} // end function_exists guard
