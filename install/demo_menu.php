<?php
/**
 * Demo vegetarian menu dataset (English + Gujarati) used by the
 * "Load Demo Menu" tool (api/load_demo.php).
 *
 * Image URLs point at loremflickr (keyword placeholder photos, hotlink-safe);
 * each item's images are stable via the ?lock= id. Owners can replace any
 * photo later from Menu Management. Prices are illustrative (₹, India).
 *
 * Structure: array of categories, each with items.
 * item = name,name_gu,desc,desc_gu,price,disc,best,new,jain,variants[[label,price]]
 */

/**
 * Return a bundled local demo photo path (assets/img/demo/fNN.jpg).
 * These ship with the app so demo menus always show photos — no external
 * dependency that could 404/500 when the owner shares the link.
 * The $kw argument is kept for readability; $lock (101..) picks the photo.
 */
$img = function (string $kw, int $lock): string {
    $n = (($lock - 101) % 28) + 1;                       // 28 bundled photos
    return 'assets/img/demo/f' . str_pad((string)$n, 2, '0', STR_PAD_LEFT) . '.jpg';
};

return [
    ['name' => 'Starters', 'name_gu' => 'સ્ટાર્ટર', 'items' => [
        ['name' => 'Paneer Tikka', 'name_gu' => 'પનીર ટિક્કા', 'desc' => 'Char-grilled cottage cheese in spiced yogurt', 'desc_gu' => 'દહીં-મસાલામાં શેકેલું પનીર', 'price' => 220, 'disc' => 199, 'best' => 1, 'new' => 0, 'jain' => 0, 'img' => $img('paneer,tikka', 101), 'variants' => [['Half', 140], ['Full', 220]]],
        ['name' => 'Veg Manchurian', 'name_gu' => 'વેજ મંચૂરિયન', 'desc' => 'Fried veg balls in tangy Manchurian sauce', 'desc_gu' => 'ચટપટા સોસમાં વેજ બોલ્સ', 'price' => 180, 'disc' => 0, 'best' => 1, 'new' => 0, 'jain' => 0, 'img' => $img('manchurian', 102), 'variants' => [['Dry', 180], ['Gravy', 200]]],
        ['name' => 'Hara Bhara Kabab', 'name_gu' => 'હરા ભરા કબાબ', 'desc' => 'Spinach, peas & potato patties', 'desc_gu' => 'પાલક, વટાણા અને બટાકાની ટિક્કી', 'price' => 170, 'disc' => 0, 'best' => 0, 'new' => 1, 'jain' => 0, 'img' => $img('kebab,vegetarian', 103), 'variants' => []],
        ['name' => 'Crispy Corn', 'name_gu' => 'ક્રિસ્પી કોર્ન', 'desc' => 'Golden fried sweet corn tossed with spices', 'desc_gu' => 'મસાલા સાથે તળેલી મકાઈ', 'price' => 160, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('corn,fried', 104), 'variants' => []],
        ['name' => 'Veg Spring Roll', 'name_gu' => 'વેજ સ્પ્રિંગ રોલ', 'desc' => 'Crispy rolls stuffed with veggies', 'desc_gu' => 'શાકભાજી ભરેલા ક્રિસ્પી રોલ', 'price' => 150, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('spring,roll', 105), 'variants' => []],
        ['name' => 'Aloo Tikki Chaat', 'name_gu' => 'આલૂ ટિક્કી ચાટ', 'desc' => 'Potato patties with curd & chutneys', 'desc_gu' => 'દહીં અને ચટણી સાથે બટાકાની ટિક્કી', 'price' => 120, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('chaat', 106), 'variants' => []],
    ]],

    ['name' => 'Soups', 'name_gu' => 'સૂપ', 'items' => [
        ['name' => 'Cream of Tomato Soup', 'name_gu' => 'ટમેટા સૂપ', 'desc' => 'Classic creamy tomato soup', 'desc_gu' => 'ક્રીમી ટમેટા સૂપ', 'price' => 110, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('tomato,soup', 107), 'variants' => []],
        ['name' => 'Sweet Corn Soup', 'name_gu' => 'સ્વીટ કોર્ન સૂપ', 'desc' => 'Veg sweet corn soup', 'desc_gu' => 'વેજ સ્વીટ કોર્ન સૂપ', 'price' => 120, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('corn,soup', 108), 'variants' => []],
        ['name' => 'Hot & Sour Soup', 'name_gu' => 'હોટ એન્ડ સાવર સૂપ', 'desc' => 'Spicy tangy oriental soup', 'desc_gu' => 'તીખો-ખાટો ઓરિએન્ટલ સૂપ', 'price' => 120, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('soup', 109), 'variants' => []],
        ['name' => 'Veg Manchow Soup', 'name_gu' => 'વેજ મંચાવ સૂપ', 'desc' => 'Topped with crispy noodles', 'desc_gu' => 'ક્રિસ્પી નૂડલ્સ સાથે', 'price' => 130, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('manchow,soup', 110), 'variants' => []],
    ]],

    ['name' => 'Punjabi Sabzi', 'name_gu' => 'પંજાબી શાક', 'items' => [
        ['name' => 'Paneer Butter Masala', 'name_gu' => 'પનીર બટર મસાલા', 'desc' => 'Cottage cheese in rich buttery tomato gravy', 'desc_gu' => 'બટરવાળી ટમેટા ગ્રેવીમાં પનીર', 'price' => 260, 'disc' => 230, 'best' => 1, 'new' => 0, 'jain' => 0, 'img' => $img('paneer,curry', 111), 'variants' => [['Half', 160], ['Full', 260]]],
        ['name' => 'Kaju Curry', 'name_gu' => 'કાજુ કરી', 'desc' => 'Cashew nuts in creamy gravy', 'desc_gu' => 'ક્રીમી ગ્રેવીમાં કાજુ', 'price' => 280, 'disc' => 0, 'best' => 0, 'new' => 1, 'jain' => 0, 'img' => $img('cashew,curry', 112), 'variants' => [['Half', 170], ['Full', 280]]],
        ['name' => 'Dal Makhani', 'name_gu' => 'દાળ મખની', 'desc' => 'Slow-cooked black lentils with butter', 'desc_gu' => 'બટર સાથે ધીમે રાંધેલી કાળી દાળ', 'price' => 210, 'disc' => 0, 'best' => 1, 'new' => 0, 'jain' => 0, 'img' => $img('dal,makhani', 113), 'variants' => [['Half', 130], ['Full', 210]]],
        ['name' => 'Palak Paneer', 'name_gu' => 'પાલક પનીર', 'desc' => 'Cottage cheese in spinach gravy', 'desc_gu' => 'પાલકની ગ્રેવીમાં પનીર', 'price' => 240, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('palak,paneer', 114), 'variants' => [['Half', 150], ['Full', 240]]],
        ['name' => 'Veg Kolhapuri', 'name_gu' => 'વેજ કોલ્હાપુરી', 'desc' => 'Mixed veg in spicy Kolhapuri masala', 'desc_gu' => 'તીખા કોલ્હાપુરી મસાલામાં મિક્સ વેજ', 'price' => 220, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('vegetable,curry', 115), 'variants' => []],
        ['name' => 'Malai Kofta', 'name_gu' => 'મલાઈ કોફ્તા', 'desc' => 'Paneer-potato dumplings in creamy gravy', 'desc_gu' => 'ક્રીમી ગ્રેવીમાં કોફ્તા', 'price' => 250, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('kofta,curry', 116), 'variants' => []],
        ['name' => 'Chana Masala', 'name_gu' => 'ચણા મસાલા', 'desc' => 'Chickpeas in onion-tomato masala', 'desc_gu' => 'ડુંગળી-ટમેટા મસાલામાં ચણા', 'price' => 190, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('chana,masala', 117), 'variants' => []],
    ]],

    ['name' => 'Kathiyawadi Special', 'name_gu' => 'કાઠિયાવાડી સ્પેશિયલ', 'items' => [
        ['name' => 'Sev Tameta nu Shaak', 'name_gu' => 'સેવ ટમેટા નું શાક', 'desc' => 'Tomato curry topped with sev', 'desc_gu' => 'સેવ સાથે ટમેટાનું શાક', 'price' => 170, 'disc' => 0, 'best' => 1, 'new' => 0, 'jain' => 0, 'img' => $img('indian,curry', 118), 'variants' => []],
        ['name' => 'Ringan no Olo', 'name_gu' => 'રીંગણ નો ઓળો', 'desc' => 'Smoked mashed brinjal Kathiyawadi style', 'desc_gu' => 'કાઠિયાવાડી શેકેલા રીંગણનો ઓળો', 'price' => 180, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('baingan,bharta', 119), 'variants' => []],
        ['name' => 'Lasaniya Batata', 'name_gu' => 'લસણિયા બટાકા', 'desc' => 'Spicy garlic potatoes', 'desc_gu' => 'તીખા લસણવાળા બટાકા', 'price' => 160, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('potato,curry', 120), 'variants' => []],
        ['name' => 'Bharela Marcha', 'name_gu' => 'ભરેલા મરચાં', 'desc' => 'Stuffed chillies Kathiyawadi style', 'desc_gu' => 'મસાલા ભરેલા મરચાં', 'price' => 150, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('stuffed,chilli', 121), 'variants' => []],
    ]],

    ['name' => 'South Indian', 'name_gu' => 'સાઉથ ઇન્ડિયન', 'items' => [
        ['name' => 'Masala Dosa', 'name_gu' => 'મસાલા ઢોસા', 'desc' => 'Crispy dosa with spiced potato filling', 'desc_gu' => 'બટાકાના પૂરણવાળો ક્રિસ્પી ઢોસા', 'price' => 140, 'disc' => 0, 'best' => 1, 'new' => 0, 'jain' => 0, 'img' => $img('masala,dosa', 122), 'variants' => []],
        ['name' => 'Plain Dosa', 'name_gu' => 'પ્લેન ઢોસા', 'desc' => 'Classic crispy rice crepe', 'desc_gu' => 'ક્રિસ્પી ઢોસા', 'price' => 110, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('dosa', 123), 'variants' => []],
        ['name' => 'Idli Sambhar', 'name_gu' => 'ઇડલી સંભાર', 'desc' => 'Steamed rice cakes with sambhar', 'desc_gu' => 'સંભાર સાથે ઇડલી', 'price' => 100, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('idli', 124), 'variants' => []],
        ['name' => 'Rava Uttapam', 'name_gu' => 'રવા ઉત્તપમ', 'desc' => 'Thick pancake with onion & tomato', 'desc_gu' => 'ડુંગળી-ટમેટા સાથે ઉત્તપમ', 'price' => 130, 'disc' => 0, 'best' => 0, 'new' => 1, 'jain' => 0, 'img' => $img('uttapam', 125), 'variants' => []],
        ['name' => 'Medu Vada', 'name_gu' => 'મેદુ વડા', 'desc' => 'Crispy lentil donuts with sambhar', 'desc_gu' => 'સંભાર સાથે મેદુ વડા', 'price' => 90, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('vada', 126), 'variants' => []],
        ['name' => 'Rava Dosa', 'name_gu' => 'રવા ઢોસા', 'desc' => 'Crispy semolina dosa', 'desc_gu' => 'ક્રિસ્પી રવા ઢોસા', 'price' => 150, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('rava,dosa', 127), 'variants' => []],
    ]],

    ['name' => 'Rice & Biryani', 'name_gu' => 'રાઈસ અને બિરયાની', 'items' => [
        ['name' => 'Veg Dum Biryani', 'name_gu' => 'વેજ દમ બિરયાની', 'desc' => 'Fragrant basmati with veggies & spices', 'desc_gu' => 'મસાલા અને શાકભાજી સાથે બિરયાની', 'price' => 220, 'disc' => 199, 'best' => 1, 'new' => 0, 'jain' => 0, 'img' => $img('biryani', 128), 'variants' => [['Half', 140], ['Full', 220]]],
        ['name' => 'Jeera Rice', 'name_gu' => 'જીરા રાઈસ', 'desc' => 'Cumin tempered basmati rice', 'desc_gu' => 'જીરાવાળા બાસમતી ચોખા', 'price' => 130, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('jeera,rice', 129), 'variants' => []],
        ['name' => 'Veg Pulao', 'name_gu' => 'વેજ પુલાવ', 'desc' => 'Mildly spiced vegetable rice', 'desc_gu' => 'હળવા મસાલાવાળો વેજ પુલાવ', 'price' => 160, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('pulao', 130), 'variants' => []],
        ['name' => 'Curd Rice', 'name_gu' => 'દહીં ભાત', 'desc' => 'South-style tempered curd rice', 'desc_gu' => 'સાઉથ સ્ટાઈલ દહીં ભાત', 'price' => 120, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('curd,rice', 131), 'variants' => []],
    ]],

    ['name' => 'Chinese', 'name_gu' => 'ચાઈનીઝ', 'items' => [
        ['name' => 'Veg Hakka Noodles', 'name_gu' => 'વેજ હક્કા નૂડલ્સ', 'desc' => 'Stir-fried noodles with veggies', 'desc_gu' => 'શાકભાજી સાથે નૂડલ્સ', 'price' => 170, 'disc' => 0, 'best' => 1, 'new' => 0, 'jain' => 0, 'img' => $img('hakka,noodles', 132), 'variants' => []],
        ['name' => 'Veg Fried Rice', 'name_gu' => 'વેજ ફ્રાઈડ રાઈસ', 'desc' => 'Wok-tossed rice with vegetables', 'desc_gu' => 'શાકભાજી સાથે ફ્રાઈડ રાઈસ', 'price' => 160, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('fried,rice', 133), 'variants' => []],
        ['name' => 'Schezwan Noodles', 'name_gu' => 'શેજવાન નૂડલ્સ', 'desc' => 'Spicy Schezwan style noodles', 'desc_gu' => 'તીખા શેજવાન નૂડલ્સ', 'price' => 180, 'disc' => 0, 'best' => 0, 'new' => 1, 'jain' => 0, 'img' => $img('schezwan,noodles', 134), 'variants' => []],
        ['name' => 'Chilli Paneer', 'name_gu' => 'ચિલી પનીર', 'desc' => 'Paneer tossed in spicy chilli sauce', 'desc_gu' => 'તીખા સોસમાં પનીર', 'price' => 200, 'disc' => 0, 'best' => 1, 'new' => 0, 'jain' => 0, 'img' => $img('chilli,paneer', 135), 'variants' => [['Dry', 200], ['Gravy', 220]]],
        ['name' => 'Veg Crispy', 'name_gu' => 'વેજ ક્રિસ્પી', 'desc' => 'Crispy fried veggies in sauce', 'desc_gu' => 'સોસમાં ક્રિસ્પી શાકભાજી', 'price' => 190, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('crispy,vegetable', 136), 'variants' => []],
    ]],

    ['name' => 'Breads', 'name_gu' => 'રોટલી અને નાન', 'items' => [
        ['name' => 'Butter Naan', 'name_gu' => 'બટર નાન', 'desc' => 'Soft tandoor naan with butter', 'desc_gu' => 'બટરવાળી નરમ નાન', 'price' => 45, 'disc' => 0, 'best' => 1, 'new' => 0, 'jain' => 0, 'img' => $img('naan', 137), 'variants' => []],
        ['name' => 'Garlic Naan', 'name_gu' => 'ગાર્લિક નાન', 'desc' => 'Naan topped with garlic & coriander', 'desc_gu' => 'લસણવાળી નાન', 'price' => 55, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('garlic,naan', 138), 'variants' => []],
        ['name' => 'Tandoori Roti', 'name_gu' => 'તંદૂરી રોટલી', 'desc' => 'Whole wheat tandoor roti', 'desc_gu' => 'ઘઉંની તંદૂરી રોટલી', 'price' => 30, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('roti', 139), 'variants' => []],
        ['name' => 'Laccha Paratha', 'name_gu' => 'લચ્છા પરાઠા', 'desc' => 'Layered flaky paratha', 'desc_gu' => 'લેયરવાળો પરાઠા', 'price' => 50, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('paratha', 140), 'variants' => []],
        ['name' => 'Bajra Rotla', 'name_gu' => 'બાજરા નો રોટલો', 'desc' => 'Traditional pearl millet rotla', 'desc_gu' => 'દેશી બાજરાનો રોટલો', 'price' => 40, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('roti,bread', 141), 'variants' => []],
    ]],

    ['name' => 'Thali & Combo', 'name_gu' => 'થાળી અને કૉમ્બો', 'items' => [
        ['name' => 'Gujarati Thali', 'name_gu' => 'ગુજરાતી થાળી', 'desc' => 'Unlimited traditional Gujarati thali', 'desc_gu' => 'અનલિમિટેડ ગુજરાતી થાળી', 'price' => 280, 'disc' => 0, 'best' => 1, 'new' => 0, 'jain' => 0, 'img' => $img('thali,indian', 142), 'variants' => []],
        ['name' => 'Punjabi Thali', 'name_gu' => 'પંજાબી થાળી', 'desc' => 'Paneer sabzi, dal, rice, roti & sweet', 'desc_gu' => 'પનીર શાક, દાળ, ભાત, રોટલી અને મીઠાઈ', 'price' => 260, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('thali', 143), 'variants' => []],
    ]],

    ['name' => 'Beverages', 'name_gu' => 'પીણાં', 'items' => [
        ['name' => 'Masala Chai', 'name_gu' => 'મસાલા ચા', 'desc' => 'Indian spiced tea', 'desc_gu' => 'મસાલાવાળી ચા', 'price' => 25, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('masala,chai', 144), 'variants' => []],
        ['name' => 'Sweet Lassi', 'name_gu' => 'સ્વીટ લસ્સી', 'desc' => 'Thick sweet yogurt drink', 'desc_gu' => 'ઘટ્ટ મીઠી લસ્સી', 'price' => 70, 'disc' => 0, 'best' => 1, 'new' => 0, 'jain' => 0, 'img' => $img('lassi', 145), 'variants' => []],
        ['name' => 'Chaas', 'name_gu' => 'છાશ', 'desc' => 'Spiced buttermilk', 'desc_gu' => 'મસાલાવાળી છાશ', 'price' => 40, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('buttermilk', 146), 'variants' => []],
        ['name' => 'Cold Coffee', 'name_gu' => 'કોલ્ડ કોફી', 'desc' => 'Chilled creamy coffee', 'desc_gu' => 'ઠંડી ક્રીમી કોફી', 'price' => 90, 'disc' => 0, 'best' => 0, 'new' => 1, 'jain' => 0, 'img' => $img('cold,coffee', 147), 'variants' => []],
        ['name' => 'Fresh Lime Soda', 'name_gu' => 'ફ્રેશ લાઈમ સોડા', 'desc' => 'Sweet or salted lime soda', 'desc_gu' => 'મીઠી કે ખારી લાઈમ સોડા', 'price' => 60, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('lime,soda', 148), 'variants' => []],
    ]],

    ['name' => 'Desserts', 'name_gu' => 'મીઠાઈ', 'items' => [
        ['name' => 'Gulab Jamun', 'name_gu' => 'ગુલાબ જાંબુ', 'desc' => 'Warm milk dumplings in sugar syrup', 'desc_gu' => 'ચાસણીમાં ગરમ ગુલાબ જાંબુ', 'price' => 80, 'disc' => 0, 'best' => 1, 'new' => 0, 'jain' => 0, 'img' => $img('gulab,jamun', 149), 'variants' => []],
        ['name' => 'Gajar Halwa', 'name_gu' => 'ગાજર નો હલવો', 'desc' => 'Carrot halwa with dry fruits', 'desc_gu' => 'ડ્રાયફ્રૂટ સાથે ગાજરનો હલવો', 'price' => 110, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('gajar,halwa', 150), 'variants' => []],
        ['name' => 'Rasmalai', 'name_gu' => 'રસમલાઈ', 'desc' => 'Soft paneer discs in saffron milk', 'desc_gu' => 'કેસર દૂધમાં રસમલાઈ', 'price' => 100, 'disc' => 0, 'best' => 0, 'new' => 1, 'jain' => 0, 'img' => $img('rasmalai', 151), 'variants' => []],
        ['name' => 'Sizzling Brownie', 'name_gu' => 'સિઝલિંગ બ્રાઉની', 'desc' => 'Warm brownie with ice cream', 'desc_gu' => 'આઈસ્ક્રીમ સાથે ગરમ બ્રાઉની', 'price' => 150, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('brownie', 152), 'variants' => []],
        ['name' => 'Basundi', 'name_gu' => 'બાસુંદી', 'desc' => 'Thickened sweet milk delicacy', 'desc_gu' => 'ઘટ્ટ મીઠી બાસુંદી', 'price' => 90, 'disc' => 0, 'best' => 0, 'new' => 0, 'jain' => 0, 'img' => $img('basundi,dessert', 153), 'variants' => []],
    ]],
];
