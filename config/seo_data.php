<?php
/**
 * SEO seed data for programmatic landing pages (cities, states, cuisine types).
 * The city rows are inserted into `seo_cities` on first use (idempotent) so the
 * Super Admin can then edit them. Cuisine types are code-defined (small, stable).
 *
 * Each city carries real, distinct attributes (state, local specialty dishes,
 * a one-line hook) so the generated pages are genuinely unique, not thin doorways.
 */

/** @return array<int,array> city rows */
function seoCitySeed(): array {
    // [name, name_gu, state, slug, tier, lat, lng, [local foods], hook]
    $c = [
        ['Ahmedabad','અમદાવાદ','Gujarat','ahmedabad',1,23.0225,72.5714,['Dhokla','Fafda-Jalebi','Gujarati Thali'],'the bustling food capital of Gujarat, from Manek Chowk night stalls to heritage thali houses'],
        ['Surat','સુરત','Gujarat','surat',1,21.1702,72.8311,['Locho','Undhiyu','Ghari'],'famous for its street food energy — Locho, Khaman and late-night Ghari'],
        ['Vadodara','વડોદરા','Gujarat','vadodara',2,22.3072,73.1812,['Sev Usal','Lilva Kachori','Bhakharwadi'],'the cultural city where Sev Usal and Lilva Kachori rule the streets'],
        ['Rajkot','રાજકોટ','Gujarat','rajkot',2,22.3039,70.8022,['Chikki','Penda','Gathiya'],'the heart of Saurashtra, known for Penda, Chikki and hearty Kathiyawadi food'],
        ['Bhavnagar','ભાવનગર','Gujarat','bhavnagar',2,21.7645,72.1519,['Ganthiya','Fuljar Soda','Sev Mamra'],'a coastal Saurashtra city loved for Ganthiya and Fuljar soda'],
        ['Jamnagar','જામનગર','Gujarat','jamnagar',2,22.4707,70.0577,['Kachori','Ghughra','Bandhani Sweets'],'the brass city with a big appetite for Kachori and Ghughra'],
        ['Junagadh','જૂનાગઢ','Gujarat','junagadh',2,21.5222,70.4579,['Ghebar','Kesar Mango','Ganthiya'],'gateway to Girnar, famous for Kesar mangoes and traditional Ganthiya'],
        ['Gandhinagar','ગાંધીનગર','Gujarat','gandhinagar',2,23.2156,72.6369,['Gujarati Thali','Handvo','Muthiya'],'the green capital of Gujarat with a growing café and thali scene'],
        ['Anand','આણંદ','Gujarat','anand',3,22.5645,72.9289,['Basundi','Doodh Peda','Chaas'],'the milk capital of India — the home of Amul and rich Basundi'],
        ['Bharuch','ભરૂચ','Gujarat','bharuch',3,21.7051,72.9959,['Sing Bhajiya','Khaman','Sukhadi'],'an ancient port town known for Sing Bhajiya and Sukhadi'],
        ['Dwarka','દ્વારકા','Gujarat','dwarka',3,22.2394,68.9678,['Prasad Thali','Gota','Chai'],'the holy coastal town where pilgrims enjoy simple satvik thalis'],
        ['Porbandar','પોરબંદર','Gujarat','porbandar',3,21.6417,69.6293,['Khaja','Fish Curry','Ganthiya'],'birthplace of Gandhiji, a coastal town famous for Khaja sweets'],
        ['Morbi','મોરબી','Gujarat','morbi',3,22.8173,70.8370,['Chevdo','Penda','Fafda'],'the ceramic city of Saurashtra with a taste for Chevdo and Penda'],
        ['Mehsana','મહેસાણા','Gujarat','mehsana',3,23.5880,72.3693,['Doodhpak','Thabdi','Chaas'],'a dairy-rich North Gujarat town known for Thabdi peda'],
        ['Navsari','નવસારી','Gujarat','navsari',3,20.9467,72.9520,['Ponk','Surti Undhiyu','Nankhatai'],'a South Gujarat town famous for winter Ponk and Parsi bakes'],
        ['Valsad','વલસાડ','Gujarat','valsad',3,20.5992,72.9342,['Hafus Mango','Doodh Pak','Ponk'],'the Alphonso mango belt of South Gujarat'],
        ['Vapi','વાપી','Gujarat','vapi',3,20.3893,72.9106,['Locho','Vada Pav','Sev Khamani'],'an industrial hub on the Gujarat–Maharashtra border with mixed street food'],
        ['Bhuj','ભુજ','Gujarat','bhuj',3,23.2419,69.6669,['Dabeli','Kutchi Bhungra','Gulab Pak'],'the heart of Kutch, birthplace of the legendary Dabeli'],
        ['Gandhidham','ગાંધીધામ','Gujarat','gandhidham',3,23.0753,70.1337,['Dabeli','Pav Bhaji','Chai'],'a fast-growing Kutch port city with a strong Dabeli culture'],
        ['Palanpur','પાલનપુર','Gujarat','palanpur',3,24.1747,72.4381,['Mesub','Ghughra','Khaja'],'the diamond town of Banaskantha, known for Mesub sweets'],
        ['Patan','પાટણ','Gujarat','patan',3,23.8493,72.1266,['Patan Dhokli','Khichu','Chaas'],'a heritage town famous for Patola silk and homely Gujarati food'],
        ['Veraval','વેરાવળ','Gujarat','veraval',3,20.9159,70.3629,['Fish Curry','Ganthiya','Gota'],'a major fishing port near Somnath'],
        ['Amreli','અમરેલી','Gujarat','amreli',3,21.6032,71.2221,['Ponk','Sev Tameta','Penda'],'a Saurashtra town with rich Kathiyawadi flavours'],
        ['Surendranagar','સુરેન્દ્રનગર','Gujarat','surendranagar',3,22.7276,71.6370,['Sev Usal','Ganthiya','Chikki'],'a central Saurashtra town known for spicy Sev Usal'],
        ['Godhra','ગોધરા','Gujarat','godhra',3,22.7788,73.6143,['Kachori','Samosa','Jalebi'],'a Panchmahal town with lively snack markets'],
        ['Nadiad','નડિયાદ','Gujarat','nadiad',3,22.6939,72.8618,['Bhusu','Chavanu','Basundi'],'a Charotar town famous for Bhusu farsan'],
        ['Mumbai','मुंबई','Maharashtra','mumbai',1,19.0760,72.8777,['Vada Pav','Pav Bhaji','Bhel Puri'],'India\'s street-food megacity — Vada Pav, Pav Bhaji and everything in between'],
        ['Pune','पुणे','Maharashtra','pune',1,18.5204,73.8567,['Misal Pav','Bhakarwadi','Mastani'],'the cultural capital of Maharashtra, famous for spicy Misal Pav'],
        ['Delhi','दिल्ली','Delhi','delhi',1,28.6139,77.2090,['Chole Bhature','Parathe','Chaat'],'the national capital with legendary Chole Bhature and Old Delhi chaat'],
        ['Bengaluru','ಬೆಂಗಳೂರು','Karnataka','bengaluru',1,12.9716,77.5946,['Masala Dosa','Idli','Filter Coffee'],'India\'s tech capital, home of the crispy Masala Dosa and filter coffee'],
        ['Hyderabad','హైదరాబాద్','Telangana','hyderabad',1,17.3850,78.4867,['Biryani','Haleem','Irani Chai'],'the city of Nizami Biryani, Haleem and Irani chai'],
        ['Chennai','சென்னை','Tamil Nadu','chennai',1,13.0827,80.2707,['Idli','Dosa','Filter Coffee'],'the heart of South Indian tiffin culture'],
        ['Kolkata','কলকাতা','West Bengal','kolkata',1,22.5726,88.3639,['Kathi Roll','Mishti','Puchka'],'the city of joy, famous for Kathi rolls and Mishti'],
        ['Jaipur','जयपुर','Rajasthan','jaipur',1,26.9124,75.7873,['Dal Baati','Pyaaz Kachori','Ghewar'],'the Pink City, home of Dal Baati Churma and Pyaaz Kachori'],
        ['Lucknow','लखनऊ','Uttar Pradesh','lucknow',1,26.8467,80.9462,['Tunday Kebab','Biryani','Kulfi'],'the city of Nawabi Awadhi cuisine and melt-in-mouth kebabs'],
        ['Indore','इंदौर','Madhya Pradesh','indore',1,22.7196,75.8577,['Poha-Jalebi','Bhutte Ka Kees','Sarafa'],'India\'s cleanest city and its most legendary street-food hub'],
        ['Nagpur','नागपूर','Maharashtra','nagpur',2,21.1458,79.0882,['Saoji','Tarri Poha','Orange Barfi'],'the orange city, famous for fiery Saoji cuisine'],
        ['Bhopal','भोपाल','Madhya Pradesh','bhopal',2,23.2599,77.4126,['Poha','Bhopali Gosht','Jalebi'],'the city of lakes with a rich Nawabi and Poha culture'],
        ['Kanpur','कानपुर','Uttar Pradesh','kanpur',2,26.4499,80.3319,['Thaggu Ke Laddu','Chaat','Kulfi'],'an industrial UP city with famous laddus and chaat'],
        ['Nashik','नाशिक','Maharashtra','nashik',2,19.9975,73.7898,['Misal','Chivda','Grapes'],'the wine capital of India, also loved for spicy Misal'],
        ['Agra','आगरा','Uttar Pradesh','agra',2,27.1767,78.0081,['Petha','Bedai','Dalmoth'],'home of the Taj Mahal and the world-famous Agra Petha'],
        ['Kochi','കൊച്ചി','Kerala','kochi',2,9.9312,76.2673,['Appam','Fish Molee','Puttu'],'the queen of the Arabian Sea, famous for seafood and Appam'],
        ['Chandigarh','ਚੰਡੀਗੜ੍ਹ','Chandigarh','chandigarh',2,30.7333,76.7794,['Chole Bhature','Butter Chicken','Lassi'],'the well-planned city with hearty Punjabi food'],
        ['Coimbatore','கோயம்புத்தூர்','Tamil Nadu','coimbatore',2,11.0168,76.9558,['Dosa','Kongu Chicken','Filter Coffee'],'the Manchester of South India with rich Kongunadu cuisine'],
        ['Visakhapatnam','విశాఖపట్నం','Andhra Pradesh','visakhapatnam',2,17.6868,83.2185,['Andhra Meals','Bongulo Chicken','Bobbatlu'],'a coastal city known for spicy Andhra seafood'],
        ['Ludhiana','ਲੁਧਿਆਣਾ','Punjab','ludhiana',2,30.9010,75.8573,['Sarson Da Saag','Makki Roti','Lassi'],'the industrial hub of Punjab with classic Punjabi dhaba food'],
    ];
    $rows = [];
    foreach ($c as $x) {
        $rows[] = [
            'city_name' => $x[0], 'city_name_gu' => $x[1], 'state' => $x[2], 'slug' => $x[3],
            'population_tier' => $x[4], 'latitude' => $x[5], 'longitude' => $x[6],
            'foods' => $x[7], 'hook' => $x[8],
        ];
    }
    return $rows;
}

/** Cuisine / business-type pages: /qr-menu-for/{slug}. */
function seoCuisineTypes(): array {
    return [
        'cafe'          => ['Café', 'Cafés', '☕', 'Give your café a sleek QR menu customers scan at the table — perfect for coffee, snacks and quick bites.'],
        'dhaba'         => ['Dhaba', 'Dhabas', '🍛', 'A simple, fast QR menu for highway dhabas — no printing, instant updates, works on any phone.'],
        'bakery'        => ['Bakery', 'Bakeries', '🥐', 'Show off cakes, breads and pastries with a photo-rich digital menu and easy online ordering.'],
        'cloud-kitchen' => ['Cloud Kitchen', 'Cloud Kitchens', '🍱', 'Run a delivery-first menu with WhatsApp ordering and zero commission per order.'],
        'food-truck'    => ['Food Truck', 'Food Trucks', '🚚', 'A scannable QR menu that follows your truck anywhere — update items and prices on the go.'],
        'fine-dining'   => ['Fine Dining Restaurant', 'Fine Dining Restaurants', '🍷', 'An elegant, branded digital menu with rich descriptions and a premium look.'],
        'sweet-shop'    => ['Sweet Shop', 'Sweet Shops', '🍬', 'Display mithai by weight and piece with clear prices — ideal for festivals and gifting.'],
        'juice-bar'     => ['Juice Bar', 'Juice Bars', '🥤', 'A colourful QR menu for juices, shakes and smoothies with quick counter ordering.'],
    ];
}
