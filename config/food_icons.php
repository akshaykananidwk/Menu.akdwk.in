<?php
/**
 * AK Menu System — Auto food-photo matcher.
 *
 * Ships a bundled pool of ~95 accurate, veg-safe food photos in
 * assets/img/food/ (sourced from Wikipedia article lead images during the
 * build and served from our OWN server so they never break at display time).
 * Given an item name (and optionally its category name), guessFoodImage()
 * returns a RELATIVE path like 'assets/img/food/paneer.jpg' which mediaUrl()
 * prefixes with BASE_URL.
 *
 * Used by AI import (api/ai.php save), public signup (api/signup.php) and the
 * bulk "Auto-add photos" endpoint (api/autophoto.php) so freshly extracted
 * menus look rich immediately. Owners can still replace any photo later.
 *
 * Matching order in guessFoodImage():
 *   1) longest / most-specific keyword found in the item name (case-insensitive),
 *   2) else a category-name fallback,
 *   3) else a NEUTRAL, veg-appropriate generic chosen deterministically by
 *      crc32($itemName) so items vary but stay stable across runs.
 *
 * This file only READS from the pool; it never writes. No DB access.
 */

if (!function_exists('foodImageDir')) {

/** Absolute directory that holds the bundled food photos. */
function foodImageDir(): string {
    return (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__)) . '/assets/img/food';
}

/**
 * Keyword => filename map. Keywords are matched as substrings of the lowercased
 * item name, LONGEST keyword first (so "paneer butter masala" beats "masala").
 * Substring matching also absorbs simple plurals ("samosas" ⊃ "samosa",
 * "rotis" ⊃ "roti"). Every filename referenced here exists in the pool.
 */
function foodKeywordMap(): array {
    return [
        // ---- Beverages -----------------------------------------------------
        'masala chai'      => 'tea.jpg',
        'masala tea'       => 'tea.jpg',
        'ginger tea'       => 'tea.jpg',
        'green tea'        => 'tea.jpg',
        'lemon tea'        => 'tea.jpg',
        'cutting chai'     => 'tea.jpg',
        'chai'             => 'tea.jpg',
        'chaha'            => 'tea.jpg',
        'tea'              => 'tea.jpg',
        'cold coffee'      => 'coffee.jpg',
        'filter coffee'    => 'coffee.jpg',
        'cappuccino'       => 'coffee.jpg',
        'espresso'         => 'coffee.jpg',
        'latte'            => 'coffee.jpg',
        'coffee'           => 'coffee.jpg',
        'sweet lassi'      => 'lassi.jpg',
        'mango lassi'      => 'lassi.jpg',
        'salt lassi'       => 'lassi.jpg',
        'lassi'            => 'lassi.jpg',
        'chaas'            => 'buttermilk.jpg',
        'chhas'            => 'buttermilk.jpg',
        'chhaas'           => 'buttermilk.jpg',
        'buttermilk'       => 'buttermilk.jpg',
        'matha'            => 'buttermilk.jpg',
        'falooda'          => 'faluda.jpg',
        'faluda'           => 'faluda.jpg',
        'milkshake'        => 'shake.jpg',
        'milk shake'       => 'shake.jpg',
        'smoothie'         => 'shake.jpg',
        'shake'            => 'shake.jpg',
        'fresh juice'      => 'juice.jpg',
        'mosambi'          => 'juice.jpg',
        'orange juice'     => 'juice.jpg',
        'juice'            => 'juice.jpg',
        'lime soda'        => 'soda.jpg',
        'nimbu'            => 'soda.jpg',
        'lemon soda'       => 'soda.jpg',
        'soda'             => 'soda.jpg',
        'mineral water'    => 'water.jpg',
        'water bottle'     => 'water.jpg',

        // ---- Breads --------------------------------------------------------
        'butter naan'      => 'naan.jpg',
        'garlic naan'      => 'naan.jpg',
        'cheese naan'      => 'naan.jpg',
        'naan'             => 'naan.jpg',
        'kulcha'           => 'naan.jpg',
        'laccha paratha'   => 'paratha.jpg',
        'aloo paratha'     => 'paratha.jpg',
        'paratha'          => 'paratha.jpg',
        'parotta'          => 'paratha.jpg',
        'thepla'           => 'thepla.jpg',
        'bhakri'           => 'bhakri.jpg',
        'rotla'            => 'rotla.jpg',
        'bajra roti'       => 'rotla.jpg',
        'tandoori roti'    => 'roti.jpg',
        'butter roti'      => 'roti.jpg',
        'rotli'            => 'roti.jpg',
        'chapati'          => 'roti.jpg',
        'chapathi'         => 'roti.jpg',
        'phulka'           => 'roti.jpg',
        'roti'             => 'roti.jpg',
        'chole bhature'    => 'bhatura.jpg',
        'bhature'          => 'bhatura.jpg',
        'bhatura'          => 'bhatura.jpg',
        'poori'            => 'puri.jpg',
        'puri'             => 'puri.jpg',
        'papad'            => 'papad.jpg',
        'papadum'          => 'papad.jpg',

        // ---- Rice ----------------------------------------------------------
        'veg biryani'      => 'biryani.jpg',
        'dum biryani'      => 'biryani.jpg',
        'hyderabadi'       => 'biryani.jpg',
        'biryani'          => 'biryani.jpg',
        'biriyani'         => 'biryani.jpg',
        'jeera rice'       => 'jeera-rice.jpg',
        'cumin rice'       => 'jeera-rice.jpg',
        'veg pulao'        => 'pulao.jpg',
        'pulao'            => 'pulao.jpg',
        'pulav'            => 'pulao.jpg',
        'pilaf'            => 'pulao.jpg',
        'khichdi'          => 'khichdi.jpg',
        'khichadi'         => 'khichdi.jpg',
        'schezwan rice'    => 'fried-rice.jpg',
        'fried rice'       => 'fried-rice.jpg',
        'curd rice'        => 'rice.jpg',
        'steamed rice'     => 'rice.jpg',
        'plain rice'       => 'rice.jpg',
        'jeera'            => 'jeera-rice.jpg',
        'rice'             => 'rice.jpg',
        'chawal'           => 'rice.jpg',

        // ---- Dals ----------------------------------------------------------
        'dal makhani'      => 'dal.jpg',
        'dal fry'          => 'dal.jpg',
        'dal tadka'        => 'dal.jpg',
        'dal'              => 'dal.jpg',
        'daal'             => 'dal.jpg',
        'kadhi'            => 'kadhi.jpg',
        'kadi'             => 'kadhi.jpg',

        // ---- Paneer / curries / sabzi -------------------------------------
        'paneer tikka'     => 'tikka.jpg',
        'paneer butter'    => 'paneer.jpg',
        'butter masala'    => 'paneer.jpg',
        'shahi paneer'     => 'paneer.jpg',
        'palak paneer'     => 'paneer.jpg',
        'kadai paneer'     => 'paneer.jpg',
        'matar paneer'     => 'paneer.jpg',
        'paneer'           => 'paneer.jpg',
        'malai kofta'      => 'kofta.jpg',
        'kofta'            => 'kofta.jpg',
        'malai'            => 'malai.jpg',
        'kaju curry'       => 'curry.jpg',
        'kaju'             => 'curry.jpg',
        'navratan'         => 'curry.jpg',
        'korma'            => 'curry.jpg',
        'kolhapuri'        => 'curry.jpg',
        'chana masala'     => 'chana.jpg',
        'chole masala'     => 'chana.jpg',
        'chole'            => 'chana.jpg',
        'chana'            => 'chana.jpg',
        'rajma'            => 'rajma.jpg',
        'aloo gobi'        => 'gobi.jpg',
        'gobi manchurian'  => 'manchurian.jpg',
        'gobi'             => 'gobi.jpg',
        'cauliflower'      => 'gobi.jpg',
        'bhindi'           => 'bhindi.jpg',
        'okra'             => 'bhindi.jpg',
        'baingan'          => 'baingan.jpg',
        'ringan'           => 'baingan.jpg',
        'brinjal'          => 'baingan.jpg',
        'bharta'           => 'baingan.jpg',
        'sev tameta'       => 'sev-tameta.jpg',
        'sev tamatar'      => 'sev-tameta.jpg',
        'lasaniya'         => 'aloo.jpg',
        'aloo'             => 'aloo.jpg',
        'batata'           => 'aloo.jpg',
        'potato'           => 'aloo.jpg',
        'sabzi'            => 'sabzi.jpg',
        'sabji'            => 'sabzi.jpg',
        'shaak'            => 'sabzi.jpg',
        'bhaji'            => 'sabzi.jpg',
        'masala'           => 'curry.jpg',
        'gravy'            => 'curry.jpg',
        'kadai'            => 'curry.jpg',
        'curry'            => 'curry.jpg',

        // ---- Chinese -------------------------------------------------------
        'hakka noodles'    => 'noodles.jpg',
        'schezwan noodles' => 'noodles.jpg',
        'chow mein'        => 'noodles.jpg',
        'noodles'          => 'noodles.jpg',
        'noodle'           => 'noodles.jpg',
        'manchurian'       => 'manchurian.jpg',
        'manchow'          => 'manchurian.jpg',
        'chilli paneer'    => 'chilli.jpg',
        'chilli'           => 'chilli.jpg',
        'chili'            => 'chilli.jpg',
        'schezwan'         => 'schezwan.jpg',
        'szechuan'         => 'schezwan.jpg',
        'spring roll'      => 'spring-roll.jpg',
        'veg crispy'       => 'manchurian.jpg',

        // ---- South Indian --------------------------------------------------
        'masala dosa'      => 'dosa.jpg',
        'rava dosa'        => 'dosa.jpg',
        'plain dosa'       => 'dosa.jpg',
        'dosa'             => 'dosa.jpg',
        'idli'             => 'idli.jpg',
        'medu vada'        => 'vada.jpg',
        'vada pav'         => 'vada-pav.jpg',
        'vada'             => 'vada.jpg',
        'uttapam'          => 'uttapam.jpg',
        'uttappam'         => 'uttapam.jpg',
        'sambhar'          => 'sambhar.jpg',
        'sambar'           => 'sambhar.jpg',
        'upma'             => 'upma.jpg',

        // ---- Street food / snacks / chaat ---------------------------------
        'samosa'           => 'samosa.jpg',
        'kachori'          => 'kachori.jpg',
        'pav bhaji'        => 'pav-bhaji.jpg',
        'pavbhaji'         => 'pav-bhaji.jpg',
        'dabeli'           => 'dabeli.jpg',
        'bhel'             => 'bhel.jpg',
        'sev puri'         => 'sev-puri.jpg',
        'sevpuri'          => 'sev-puri.jpg',
        'pani puri'        => 'pani-puri.jpg',
        'panipuri'         => 'pani-puri.jpg',
        'golgappa'         => 'pani-puri.jpg',
        'puchka'           => 'pani-puri.jpg',
        'dahi puri'        => 'chaat.jpg',
        'aloo tikki'       => 'chaat.jpg',
        'tikki'            => 'chaat.jpg',
        'chaat'            => 'chaat.jpg',
        'chat'             => 'chaat.jpg',

        // ---- Gujarati specials --------------------------------------------
        'khaman'           => 'khaman.jpg',
        'dhokla'           => 'dhokla.jpg',
        'fafda'            => 'fafda.jpg',
        'gathiya'          => 'gathiya.jpg',
        'ganthiya'         => 'gathiya.jpg',
        'handvo'           => 'handvo.jpg',
        'muthiya'          => 'muthiya.jpg',
        'muthia'           => 'muthiya.jpg',
        'khandvi'          => 'khandvi.jpg',
        'patra'            => 'patra.jpg',
        'undhiyu'          => 'undhiyu.jpg',
        'undhiyo'          => 'undhiyu.jpg',

        // ---- Soups / salads / sides ---------------------------------------
        'corn soup'        => 'soup.jpg',
        'tomato soup'      => 'soup.jpg',
        'manchow soup'     => 'soup.jpg',
        'hot and sour'     => 'soup.jpg',
        'soup'             => 'soup.jpg',
        'green salad'      => 'salad.jpg',
        'salad'            => 'salad.jpg',
        'kachumber'        => 'salad.jpg',
        'raita'            => 'raita.jpg',
        'curd'             => 'raita.jpg',
        'dahi'             => 'raita.jpg',

        // ---- Thali / combo -------------------------------------------------
        'gujarati thali'   => 'thali.jpg',
        'punjabi thali'    => 'thali.jpg',
        'rajasthani thali' => 'thali.jpg',
        'thali'            => 'thali.jpg',
        'thaali'           => 'thali.jpg',
        'unlimited'        => 'thali.jpg',
        'combo'            => 'thali.jpg',
        'platter'          => 'thali.jpg',

        // ---- Fast food -----------------------------------------------------
        'pizza'            => 'pizza.jpg',
        'burger'           => 'burger.jpg',
        'sandwich'         => 'sandwich.jpg',
        'toast'            => 'sandwich.jpg',
        'grilled'          => 'sandwich.jpg',
        'pasta'            => 'pasta.jpg',
        'macaroni'         => 'pasta.jpg',
        'frankie'          => 'frankie.jpg',
        'kathi roll'       => 'frankie.jpg',
        'kati roll'        => 'frankie.jpg',
        'wrap'             => 'frankie.jpg',
        'roll'             => 'frankie.jpg',

        // ---- Desserts ------------------------------------------------------
        'gulab jamun'      => 'gulab-jamun.jpg',
        'gulab'            => 'gulab-jamun.jpg',
        'jamun'            => 'gulab-jamun.jpg',
        'jalebi'           => 'jalebi.jpg',
        'rasmalai'         => 'rasmalai.jpg',
        'ras malai'        => 'rasmalai.jpg',
        'rasgulla'         => 'rasmalai.jpg',
        'gajar halwa'      => 'halwa.jpg',
        'halwa'            => 'halwa.jpg',
        'halva'            => 'halwa.jpg',
        'sheera'           => 'halwa.jpg',
        'kheer'            => 'kheer.jpg',
        'basundi'          => 'basundi.jpg',
        'rabri'            => 'basundi.jpg',
        'rabdi'            => 'basundi.jpg',
        'sizzling brownie' => 'brownie.jpg',
        'brownie'          => 'brownie.jpg',
        'chocolate cake'   => 'cake.jpg',
        'pastry'           => 'cake.jpg',
        'cake'             => 'cake.jpg',
        'kulfi'            => 'kulfi.jpg',
        'ice cream'        => 'icecream.jpg',
        'icecream'         => 'icecream.jpg',
        'sundae'           => 'icecream.jpg',
    ];
}

/**
 * Category-name => filename fallbacks, matched (as substrings) against the
 * lowercased category name when no item-name keyword hit. All veg-appropriate.
 */
function foodCategoryMap(): array {
    return [
        'starter'    => 'samosa.jpg',
        'appetizer'  => 'samosa.jpg',
        'snack'      => 'samosa.jpg',
        'farsan'     => 'dhokla.jpg',
        'soup'       => 'soup.jpg',
        'beverage'   => 'tea.jpg',
        'drink'      => 'juice.jpg',
        'shake'      => 'shake.jpg',
        'juice'      => 'juice.jpg',
        'mocktail'   => 'juice.jpg',
        'dessert'    => 'cake.jpg',
        'sweet'      => 'gulab-jamun.jpg',
        'mithai'     => 'gulab-jamun.jpg',
        'ice cream'  => 'icecream.jpg',
        'bread'      => 'naan.jpg',
        'roti'       => 'roti.jpg',
        'naan'       => 'naan.jpg',
        'tandoor'    => 'tikka.jpg',
        'rice'       => 'rice.jpg',
        'biryani'    => 'biryani.jpg',
        'chinese'    => 'noodles.jpg',
        'oriental'   => 'noodles.jpg',
        'south'      => 'dosa.jpg',
        'sabzi'      => 'curry.jpg',
        'sabji'      => 'curry.jpg',
        'shaak'      => 'curry.jpg',
        'punjabi'    => 'paneer.jpg',
        'kathiyawadi'=> 'sev-tameta.jpg',
        'gujarati'   => 'thali.jpg',
        'thali'      => 'thali.jpg',
        'combo'      => 'thali.jpg',
        'pizza'      => 'pizza.jpg',
        'burger'     => 'burger.jpg',
        'fast food'  => 'burger.jpg',
        'italian'    => 'pasta.jpg',
        'salad'      => 'salad.jpg',
        'chaat'      => 'chaat.jpg',
        'street'     => 'pani-puri.jpg',
        'curry'      => 'curry.jpg',
        'gravy'      => 'curry.jpg',
        'main course'=> 'curry.jpg',
        'main'       => 'curry.jpg',
        'dal'        => 'dal.jpg',
        'kathi'      => 'frankie.jpg',
    ];
}

/**
 * Neutral, unmistakably-vegetarian fallbacks used when nothing else matches.
 * Chosen deterministically so items vary but stay stable.
 */
function foodGenericPool(): array {
    return ['food1.jpg', 'food2.jpg', 'food3.jpg', 'food4.jpg', 'food5.jpg',
            'food6.jpg', 'food7.jpg', 'food8.jpg', 'thali.jpg', 'curry.jpg',
            'sabzi.jpg', 'salad.jpg'];
}

/**
 * Pick a relevant bundled food photo for an item.
 *
 * @return string|null relative path 'assets/img/food/xxx.jpg', or null if the
 *                     pool is missing entirely.
 */
function guessFoodImage(string $itemName, string $categoryName = ''): ?string {
    $dir  = foodImageDir();
    $rel  = 'assets/img/food/';
    // Normalise: lowercase + collapse punctuation/whitespace to single spaces so
    // "Paneer-Tikka", "Paneer  Tikka" and "paneer tikka" all match alike.
    $norm = fn(string $s): string => trim(preg_replace('/\s+/', ' ',
        preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s))));
    $name = $norm($itemName);
    $cat  = $norm($categoryName);

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

    // 2) Category-name fallback (longest keyword first).
    if ($cat !== '') {
        $catMap = foodCategoryMap();
        uksort($catMap, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($catMap as $kw => $file) {
            if (strpos($cat, $kw) !== false && is_file($dir . '/' . $file)) {
                return $rel . $file;
            }
        }
    }

    // 3) Deterministic neutral-veg generic (stable per item name).
    $pool = array_values(array_filter(foodGenericPool(), fn($f) => is_file($dir . '/' . $f)));
    if (!$pool) { return null; }
    $idx = crc32($itemName !== '' ? $itemName : 'food') % count($pool);
    return $rel . $pool[$idx];
}

} // end function_exists guard
