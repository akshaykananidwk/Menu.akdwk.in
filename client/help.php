<?php
/**
 * Client › Help & Guide.
 *
 * A complete, self-service manual for the restaurant owner: what every part of
 * the panel does, in what order to set it up, and the exact step-by-step
 * clicks — in English AND Gujarati, switchable with one button.
 *
 * CONTENT LIVES IN ONE ARRAY ($guide) so adding a chapter later is a data edit,
 * not a layout edit. Each chapter: id, icon, title, "what this is", numbered
 * steps, and optional tips/warnings. Every string is a ['en' => …, 'gu' => …]
 * pair; both are rendered and CSS shows one, so the switch is instant and the
 * page stays searchable in either language.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireClient();

$tenant   = currentTenant();
$slug     = (string)($tenant['slug'] ?? '');
$menuUrl  = $slug !== '' ? publicMenuUrl($slug) : '';
$propCode = function_exists('propertyCode') ? (string)propertyCode((int)currentTenantId()) : '';
$lang     = ($_SESSION['lang'] ?? 'en') === 'gu' ? 'gu' : 'en';

/** Shorthand for a bilingual string. */
function hlp(string $en, string $gu): array { return ["en" => $en, "gu" => $gu]; }

$B = BASE_URL;

$guide = [

// =============================================================================
[
 'id' => 'start', 'icon' => 'bi-rocket-takeoff', 'group' => hlp('Start here', 'શરૂઆત કરો'),
 'title' => hlp('Your first 20 minutes', 'તમારી પહેલી 20 મિનિટ'),
 'what'  => hlp(
   'This is the shortest path from an empty account to a working QR menu on your tables. Do these five things in order and you are live. Everything else in this guide is optional extra.',
   'ખાલી ખાતાથી ટેબલ પર ચાલતા QR મેનૂ સુધીનો સૌથી ટૂંકો રસ્તો. નીચેની પાંચ વસ્તુ ક્રમમાં કરો અને તમે લાઈવ છો. બાકીનું બધું વૈકલ્પિક છે.'),
 'steps' => [
   hlp('<b>Design &amp; Branding</b> — upload your logo, pick your colours and type your restaurant name the way you want diners to see it.',
     '<b>Design &amp; Branding</b> — તમારો લોગો અપલોડ કરો, રંગ પસંદ કરો અને રેસ્ટોરન્ટનું નામ જેવું ગ્રાહકને દેખાડવું હોય તેવું લખો.'),
   hlp('<b>Menu Management</b> — make your categories first (Starters, Punjabi, Thali…), then add dishes under each one with price and photo.',
     '<b>Menu Management</b> — પહેલા કેટેગરી બનાવો (સ્ટાર્ટર, પંજાબી, થાળી…), પછી દરેકમાં ભાવ અને ફોટો સાથે વાનગી ઉમેરો.'),
   hlp('<b>Tables</b> — add how many tables you have. Each table gets its own QR code so you know which table ordered.',
     '<b>Tables</b> — તમારા ટેબલની સંખ્યા ઉમેરો. દરેક ટેબલને પોતાનો QR મળે છે, જેથી કયા ટેબલે ઓર્ડર કર્યો તે ખબર પડે.'),
   hlp('<b>QR &amp; Standee</b> — download your QR, pick a standee design, print it and put it on every table.',
     '<b>QR &amp; Standee</b> — QR ડાઉનલોડ કરો, સ્ટેન્ડી ડિઝાઇન પસંદ કરો, પ્રિન્ટ કરાવો અને દરેક ટેબલ પર મૂકો.'),
   hlp('<b>Settings → Direct Ordering</b> — turn it ON if you want diners to place orders from their phone. Leave it OFF if you only want them to read the menu.',
     '<b>Settings → Direct Ordering</b> — ગ્રાહક પોતાના મોબાઈલથી ઓર્ડર કરે એવું જોઈએ તો ચાલુ કરો. ફક્ત મેનૂ વાંચવા દેવું હોય તો બંધ રાખો.'),
 ],
 'tips' => [
   hlp('Your public menu link is <code>' . e($menuUrl ?: BASE_URL . '/r/your-shop') . '</code> — share it on WhatsApp, Instagram and Google Maps too, not just the QR.',
     'તમારી મેનૂ લિંક <code>' . e($menuUrl ?: BASE_URL . '/r/your-shop') . '</code> છે — ફક્ત QR નહીં, WhatsApp, Instagram અને Google Maps પર પણ શેર કરો.'),
   hlp('You can do all of this from your mobile. No computer needed.',
     'આ બધું મોબાઈલથી જ થઈ શકે છે. કમ્પ્યુટરની જરૂર નથી.'),
 ],
],

// =============================================================================
[
 'id' => 'how-it-works', 'icon' => 'bi-diagram-3', 'group' => hlp('Start here', 'શરૂઆત કરો'),
 'title' => hlp('How the whole system works', 'આખી સિસ્ટમ કેવી રીતે કામ કરે છે'),
 'what'  => hlp(
   'Understanding this one picture makes every other page obvious.',
   'આ એક ચિત્ર સમજી લો તો બાકીનું દરેક પાનું આપમેળે સમજાઈ જશે.'),
 'steps' => [
   hlp('<b>You</b> manage everything from this panel on your phone or computer.',
     '<b>તમે</b> આ પેનલથી મોબાઈલ કે કમ્પ્યુટર પર બધું સંભાળો છો.'),
   hlp('<b>The diner</b> scans the table QR → your menu opens in their browser. No app to install.',
     '<b>ગ્રાહક</b> ટેબલનો QR સ્કેન કરે → તમારું મેનૂ તેના બ્રાઉઝરમાં ખુલે. કોઈ એપ ઇન્સ્ટોલ કરવાની જરૂર નથી.'),
   hlp('If Direct Ordering is on, the diner adds items and presses Place Order. The order lands instantly on your <b>Orders</b> board with a chime and a voice alert.',
     'Direct Ordering ચાલુ હોય તો ગ્રાહક વસ્તુ ઉમેરીને Place Order દબાવે. ઓર્ડર તરત તમારા <b>Orders</b> બોર્ડ પર ઘંટડી અને આવાજ સાથે આવે.'),
   hlp('<b>Your staff</b> open the Staff App on their own phone or tablet and see only their screen — Waiter, Kitchen or Reception.',
     '<b>તમારો સ્ટાફ</b> પોતાના મોબાઈલ/ટેબલેટ પર Staff App ખોલે અને ફક્ત પોતાની સ્ક્રીન જુએ — Waiter, Kitchen કે Reception.'),
   hlp('Prices are always recalculated on our server when an order is placed, so nobody can change a price in their browser and pay less.',
     'ઓર્ડર આવે ત્યારે ભાવ હંમેશા અમારા સર્વર પર ફરી ગણાય છે, એટલે કોઈ બ્રાઉઝરમાં ભાવ બદલીને ઓછા પૈસા ન આપી શકે.'),
   hlp('Behind the scenes a task runs every minute and sends your WhatsApp messages, e-mails, reminders and daily reports. You never start it — it is always on.',
     'પડદા પાછળ દર મિનિટે એક કામ ચાલે છે જે તમારા WhatsApp સંદેશા, ઈમેલ, રિમાઇન્ડર અને રોજનો રિપોર્ટ મોકલે છે. તમારે કંઈ ચાલુ કરવાનું નથી — એ હંમેશા ચાલુ છે.'),
 ],
],

// =============================================================================
[
 'id' => 'login', 'icon' => 'bi-shield-lock', 'group' => hlp('Account &amp; login', 'ખાતું અને લોગિન'),
 'title' => hlp('Login, password &amp; e-mail', 'લોગિન, પાસવર્ડ અને ઈમેલ'),
 'what'  => hlp(
   'There are three ways into your panel, and two ways to get back in if you forget your password. You never lose access.',
   'પેનલમાં આવવાના ત્રણ રસ્તા છે, અને પાસવર્ડ ભૂલી જાઓ તો પાછા આવવાના બે રસ્તા. તમારી પહોંચ કદી જતી નથી.'),
 'steps' => [
   hlp('<b>Mobile + password</b> — the normal way. Type your 10-digit mobile and your password.',
     '<b>મોબાઈલ + પાસવર્ડ</b> — સામાન્ય રસ્તો. તમારો 10 આંકડાનો મોબાઈલ અને પાસવર્ડ લખો.'),
   hlp('<b>E-mail + password</b> — the same login box also accepts your e-mail address. Use whichever you remember.',
     '<b>ઈમેલ + પાસવર્ડ</b> — એ જ લોગિન બોક્સમાં તમારું ઈમેલ પણ ચાલે. જે યાદ હોય તે વાપરો.'),
   hlp('<b>OTP (no password)</b> — open the OTP tab, type your mobile <i>or</i> e-mail, press Send OTP. A 6-digit code comes on WhatsApp (for mobile) or in your inbox (for e-mail). It is valid 5 minutes.',
     '<b>OTP (પાસવર્ડ વગર)</b> — OTP ટેબ ખોલો, મોબાઈલ <i>અથવા</i> ઈમેલ લખો, Send OTP દબાવો. 6 આંકડાનો કોડ WhatsApp પર (મોબાઈલ માટે) કે ઈમેલમાં આવશે. 5 મિનિટ ચાલે.'),
   hlp('<b>Forgot password?</b> — click that link on the login page and type your e-mail or mobile. With e-mail you get a reset link (works once, valid 60 minutes). With mobile you get a WhatsApp code and set the new password on the same screen.',
     '<b>પાસવર્ડ ભૂલી ગયા?</b> — લોગિન પાના પર એ લિંક દબાવી ઈમેલ કે મોબાઈલ લખો. ઈમેલથી રિસેટ લિંક આવશે (એક વાર ચાલે, 60 મિનિટ માન્ય). મોબાઈલથી WhatsApp પર કોડ આવશે અને એ જ સ્ક્રીન પર નવો પાસવર્ડ રાખી શકશો.'),
   hlp('Change your password any time in <b>Settings → Account</b>.',
     '<b>Settings → Account</b> માં ક્યારેય પણ પાસવર્ડ બદલી શકો છો.'),
 ],
 'tips' => [
   hlp('Add your e-mail address in <b>Settings</b> even if you log in with mobile. It is your backup key if you ever change your phone number.',
     'મોબાઈલથી લોગિન કરતા હોવ તો પણ <b>Settings</b> માં ઈમેલ ઉમેરો. નંબર બદલાય તો એ તમારી બેકઅપ ચાવી છે.'),
   hlp('We never send your password by e-mail or WhatsApp. If a message claims to contain your password, it is not from us.',
     'અમે તમારો પાસવર્ડ ઈમેલ કે WhatsApp પર કદી મોકલતા નથી. કોઈ સંદેશામાં પાસવર્ડ હોવાનો દાવો હોય તો એ અમારા તરફથી નથી.'),
 ],
 'warn' => hlp('If the reset e-mail does not arrive in 2 minutes, check your Spam/Promotions folder first, then try the mobile option.',
             'રિસેટ ઈમેલ 2 મિનિટમાં ન આવે તો પહેલા Spam/Promotions ફોલ્ડર જુઓ, પછી મોબાઈલનો વિકલ્પ વાપરો.'),
],

// =============================================================================
[
 'id' => 'menu', 'icon' => 'bi-card-list', 'group' => hlp('Your menu', 'તમારું મેનૂ'),
 'title' => hlp('Menu Management', 'મેનૂ મેનેજમેન્ટ'),
 'what'  => hlp(
   'This is the heart of the system. Categories hold dishes; dishes hold prices, photos, variants and add-ons.',
   'આ સિસ્ટમનું હૃદય છે. કેટેગરીમાં વાનગી, વાનગીમાં ભાવ, ફોટો, વેરાયટી અને એડ-ઓન.'),
 'steps' => [
   hlp('Press <b>Add Category</b> and name it (Starters, Punjabi, South Indian, Thali, Beverages…). Drag to reorder — the diner sees this exact order.',
     '<b>Add Category</b> દબાવી નામ આપો (સ્ટાર્ટર, પંજાબી, સાઉથ ઇન્ડિયન, થાળી, પીણાં…). ક્રમ બદલવા ખેંચો — ગ્રાહકને આ જ ક્રમ દેખાશે.'),
   hlp('Press <b>Add Item</b>, choose the category, type the dish name and price, and upload a photo. A photo roughly doubles how often an item is ordered.',
     '<b>Add Item</b> દબાવો, કેટેગરી પસંદ કરો, વાનગીનું નામ અને ભાવ લખો, ફોટો અપલોડ કરો. ફોટો હોય તો ઓર્ડર લગભગ બમણા થાય છે.'),
   hlp('Use <b>Variants</b> for sizes (Half / Full, Small / Large) and <b>Add-ons</b> for extras (extra cheese, extra butter). Each one can carry its own price.',
     'સાઈઝ માટે <b>Variants</b> વાપરો (હાફ/ફુલ, નાની/મોટી) અને વધારાની વસ્તુ માટે <b>Add-ons</b> (એક્સ્ટ્રા ચીઝ, બટર). દરેકનો પોતાનો ભાવ રાખી શકાય.'),
   hlp('Mark <b>Jain</b>, set the <b>spice level</b> and add tags like “Chef Special”. Diners get a one-tap Jain filter and the spice icons on the menu.',
     '<b>Jain</b> નિશાની કરો, <b>તીખાશ</b> સેટ કરો અને “Chef Special” જેવા ટેગ ઉમેરો. ગ્રાહકને Jain ફિલ્ટર અને તીખાશનાં ચિહ્ન દેખાશે.'),
   hlp('Press <b>AI Write</b> next to Description and the system writes a tasty one-line description in English and Gujarati from the dish name.',
     'Description પાસે <b>AI Write</b> દબાવો — સિસ્ટમ વાનગીના નામ પરથી અંગ્રેજી અને ગુજરાતીમાં સ્વાદિષ્ટ એક લીટીનું વર્ણન લખી આપશે.'),
   hlp('Sold out today? Switch the item <b>Off</b> instead of deleting it. It disappears from the menu and comes back with one tap tomorrow.',
     'આજે ખતમ થઈ ગયું? વસ્તુ ડિલીટ ન કરો, <b>Off</b> કરો. મેનૂમાંથી ગાયબ થશે અને કાલે એક ટેપે પાછી આવશે.'),
 ],
 'tips' => [
   hlp('Keep each category under about 15 items. Long lists make diners scroll and give up.',
     'દરેક કેટેગરીમાં લગભગ 15 થી ઓછી વસ્તુ રાખો. લાંબી યાદીમાં ગ્રાહક સ્ક્રોલ કરીને થાકી જાય છે.'),
   hlp('Put your 3 best-selling dishes at the top of the first category. That is the only screen most diners read fully.',
     'પહેલી કેટેગરીમાં સૌથી વધુ વેચાતી 3 વાનગી ઉપર રાખો. મોટાભાગના ગ્રાહક ફક્ત એ સ્ક્રીન પૂરી વાંચે છે.'),
 ],
],

// =============================================================================
[
 'id' => 'ai-import', 'icon' => 'bi-magic', 'group' => hlp('Your menu', 'તમારું મેનૂ'),
 'title' => hlp('AI Menu Import — menu from a photo', 'AI મેનૂ ઇમ્પોર્ટ — ફોટામાંથી મેનૂ'),
 'what'  => hlp(
   'Instead of typing 80 dishes, photograph your printed menu card and let the AI type it for you.',
   '80 વાનગી ટાઈપ કરવાને બદલે તમારા છપાયેલા મેનૂ કાર્ડનો ફોટો પાડો અને AI ને ટાઈપ કરવા દો.'),
 'steps' => [
   hlp('Open <b>AI Menu Import</b> and upload a clear, straight photo of your menu card (good light, no shadow, text readable).',
     '<b>AI Menu Import</b> ખોલો અને મેનૂ કાર્ડનો સાફ, સીધો ફોટો અપલોડ કરો (સારો પ્રકાશ, છાંયડો નહીં, અક્ષર વાંચી શકાય).'),
   hlp('Press Extract. In a few seconds you get a list of categories, dish names and prices that it read.',
     'Extract દબાવો. થોડી સેકન્ડમાં તેણે વાંચેલી કેટેગરી, વાનગી અને ભાવની યાદી મળશે.'),
   hlp('<b>Check every price before you save.</b> AI reads well but not perfectly — handwriting and fancy fonts can be misread.',
     '<b>સેવ કરતા પહેલા દરેક ભાવ ચેક કરો.</b> AI સારું વાંચે છે પણ પરફેક્ટ નહીં — હાથથી લખેલું અને સજાવટી ફોન્ટ ખોટા વંચાઈ શકે.'),
   hlp('Fix anything wrong, then Import. The items go straight into Menu Management where you can add photos.',
     'ખોટું હોય તે સુધારો, પછી Import કરો. વસ્તુઓ સીધી Menu Management માં જશે જ્યાં ફોટો ઉમેરી શકશો.'),
 ],
 'tips' => [
   hlp('One page at a time gives much better results than one photo of four pages.',
     'ચાર પાનાંનો એક ફોટો કરતાં એક વખતે એક પાનું ઘણું સારું પરિણામ આપે છે.'),
 ],
],

// =============================================================================
[
 'id' => 'design', 'icon' => 'bi-palette', 'group' => hlp('Your menu', 'તમારું મેનૂ'),
 'title' => hlp('Design &amp; Branding', 'ડિઝાઇન અને બ્રાન્ડિંગ'),
 'what'  => hlp(
   'Make the menu look like your restaurant, not like software.',
   'મેનૂ સોફ્ટવેર જેવું નહીં, તમારી રેસ્ટોરન્ટ જેવું દેખાય એવું બનાવો.'),
 'steps' => [
   hlp('Upload your <b>logo</b> (a square image works best) and a <b>cover photo</b> for the top of the menu.',
     'તમારો <b>લોગો</b> અપલોડ કરો (ચોરસ ફોટો સૌથી સારો) અને મેનૂની ઉપર માટે <b>કવર ફોટો</b>.'),
   hlp('Pick your <b>primary colour</b>. Buttons, prices and highlights all follow it automatically.',
     'તમારો <b>મુખ્ય રંગ</b> પસંદ કરો. બટન, ભાવ અને હાઇલાઇટ બધું આપમેળે તે રંગમાં થશે.'),
   hlp('Choose a <b>template</b>. Preview it on your own phone before you keep it — that is how your diners will see it.',
     '<b>ટેમ્પલેટ</b> પસંદ કરો. રાખતા પહેલા તમારા જ મોબાઈલ પર જોઈ લો — ગ્રાહકને આવું જ દેખાશે.'),
   hlp('Add your address, phone and opening hours. These also help you appear in Google local searches.',
     'સરનામું, ફોન અને ખુલવાનો સમય ઉમેરો. આ Google ની લોકલ શોધમાં પણ દેખાવામાં મદદ કરે છે.'),
 ],
],

// =============================================================================
[
 'id' => 'qr', 'icon' => 'bi-qr-code', 'group' => hlp('Your menu', 'તમારું મેનૂ'),
 'title' => hlp('QR codes &amp; table standees', 'QR કોડ અને ટેબલ સ્ટેન્ડી'),
 'what'  => hlp(
   'You get one QR for the whole restaurant and one QR per table. The table QR is the useful one.',
   'તમને આખી રેસ્ટોરન્ટનો એક QR અને દરેક ટેબલનો એક QR મળે છે. ટેબલનો QR વધુ કામનો છે.'),
 'steps' => [
   hlp('Add your tables in <b>Tables</b> first. Then <b>QR &amp; Standee</b> has a QR ready for each one.',
     'પહેલા <b>Tables</b> માં ટેબલ ઉમેરો. પછી <b>QR &amp; Standee</b> માં દરેક માટે તૈયાર QR હશે.'),
   hlp('Pick a standee design, and download it as an image or PDF.',
     'સ્ટેન્ડી ડિઝાઇન પસંદ કરો અને ફોટો કે PDF તરીકે ડાઉનલોડ કરો.'),
   hlp('Print on thick paper or get an acrylic stand made. Put one on every table and one at the counter.',
     'જાડા કાગળ પર પ્રિન્ટ કરાવો કે એક્રિલિક સ્ટેન્ડ બનાવો. દરેક ટેબલ પર અને કાઉન્ટર પર એક મૂકો.'),
   hlp('A table QR tells you which table ordered, so the waiter never has to ask.',
     'ટેબલનો QR જણાવે છે કે કયા ટેબલે ઓર્ડર કર્યો, એટલે વેઈટરે પૂછવું ન પડે.'),
 ],
 'warn' => hlp('Never cover a QR with glass or tape that reflects light — phones cannot read it. Test every printed standee with your own phone once.',
             'QR પર કાચ કે ચમકતી ટેપ ન લગાવો — મોબાઈલ વાંચી શકતો નથી. દરેક પ્રિન્ટેડ સ્ટેન્ડી તમારા મોબાઈલથી એક વાર ચેક કરો.'),
],

// =============================================================================
[
 'id' => 'orders', 'icon' => 'bi-receipt-cutoff', 'group' => hlp('Daily operations', 'રોજનું કામકાજ'),
 'title' => hlp('Taking orders &amp; collecting payment', 'ઓર્ડર લેવા અને પૈસા લેવા'),
 'what'  => hlp(
   'The Orders board is your counter screen. Keep it open on a tablet or a phone during service.',
   'Orders બોર્ડ તમારી કાઉન્ટર સ્ક્રીન છે. સર્વિસ દરમિયાન ટેબલેટ કે મોબાઈલ પર ખુલ્લું રાખો.'),
 'steps' => [
   hlp('A new order arrives with a chime and a Hindi voice alert. Make sure sound is ON in the top bar.',
     'નવો ઓર્ડર ઘંટડી અને હિન્દી આવાજ સાથે આવે છે. ઉપરની પટ્ટીમાં આવાજ ચાલુ હોવો જોઈએ.'),
   hlp('Orders move through <b>New → Active → Completed</b>. Cancelled orders have their own tab.',
     'ઓર્ડર <b>New → Active → Completed</b> એમ આગળ વધે છે. રદ થયેલા ઓર્ડરની અલગ ટેબ છે.'),
   hlp('When the guest is leaving, press <b>Complete &amp; Collect Payment</b>, choose Cash / UPI / Card / Bank / Online / Due. For cash, type what the guest gave and the screen shows the change.',
     'ગ્રાહક જાય ત્યારે <b>Complete &amp; Collect Payment</b> દબાવો, Cash / UPI / Card / Bank / Online / Due પસંદ કરો. રોકડમાં ગ્રાહકે આપેલા પૈસા લખો, સ્ક્રીન છૂટા દેખાડશે.'),
   hlp('Took money later? Use <b>Mark Paid</b> on any unpaid order. The payment mode shows on the printed bill.',
     'પછી પૈસા લીધા? કોઈપણ બાકી ઓર્ડર પર <b>Mark Paid</b> વાપરો. પ્રિન્ટેડ બિલમાં પેમેન્ટ મોડ દેખાશે.'),
   hlp('Print the bill from the order detail. It works with any normal or thermal printer connected to that device.',
     'ઓર્ડરની વિગતમાંથી બિલ પ્રિન્ટ કરો. એ ડિવાઇસ સાથે જોડાયેલા સામાન્ય કે થર્મલ પ્રિન્ટર સાથે ચાલે છે.'),
 ],
 'tips' => [
   hlp('<b>Due</b> is for regulars with a khata. Reports then show exactly how much is outstanding.',
     '<b>Due</b> ખાતાવાળા નિયમિત ગ્રાહક માટે છે. પછી Reports માં બરાબર કેટલું બાકી છે તે દેખાશે.'),
 ],
],

// =============================================================================
[
 'id' => 'tables', 'icon' => 'bi-grid-3x3-gap', 'group' => hlp('Daily operations', 'રોજનું કામકાજ'),
 'title' => hlp('Tables &amp; Call Waiter', 'ટેબલ અને Call Waiter'),
 'what'  => hlp(
   'Tables give each seat a number and let the guest call you without raising a hand.',
   'Tables દરેક બેઠકને નંબર આપે છે અને ગ્રાહક હાથ ઊંચો કર્યા વગર તમને બોલાવી શકે છે.'),
 'steps' => [
   hlp('In <b>Tables</b>, add every table with its number or name (T1, T2, AC-1, Family Room…).',
     '<b>Tables</b> માં દરેક ટેબલ તેના નંબર કે નામ સાથે ઉમેરો (T1, T2, AC-1, ફેમિલી રૂમ…).'),
   hlp('From the menu the guest can tap <b>Call Waiter</b> and choose: Call, Bring Bill, Water, or Clean table.',
     'મેનૂમાંથી ગ્રાહક <b>Call Waiter</b> દબાવીને પસંદ કરી શકે: બોલાવો, બિલ લાવો, પાણી, ટેબલ સાફ કરો.'),
   hlp('The request pops up with a chime on the Kitchen board and your Orders board. One tap on <b>Done</b> clears it.',
     'વિનંતી ઘંટડી સાથે Kitchen બોર્ડ અને તમારા Orders બોર્ડ પર દેખાય. <b>Done</b> પર એક ટેપે સાફ થઈ જાય.'),
 ],
],

// =============================================================================
[
 'id' => 'staffapp', 'icon' => 'bi-phone', 'group' => hlp('Daily operations', 'રોજનું કામકાજ'),
 'title' => hlp('Staff App — Waiter, Kitchen, Reception', 'સ્ટાફ એપ — વેઈટર, કિચન, રિસેપ્શન'),
 'what'  => hlp(
   'One installable app for your whole team. It asks “who are you?” and shows only that person\'s screen. No Play Store, no download charges.',
   'આખી ટીમ માટે એક ઇન્સ્ટોલ થઈ શકતી એપ. એ પૂછે છે “તમે કોણ છો?” અને ફક્ત તે વ્યક્તિની સ્ક્રીન દેખાડે. Play Store નહીં, કોઈ ચાર્જ નહીં.'),
 'steps' => [
   hlp('On the staff member\'s phone or tablet, open <code>' . e($B) . '/app</code> in Chrome.',
     'સ્ટાફના મોબાઈલ/ટેબલેટ પર Chrome માં <code>' . e($B) . '/app</code> ખોલો.'),
   hlp('Tap the browser menu → <b>Add to Home Screen</b>. It now looks and opens like a real app.',
     'બ્રાઉઝર મેનૂ → <b>Add to Home Screen</b> દબાવો. હવે એ સાચી એપ જેવી દેખાશે અને ખુલશે.'),
   hlp('They tap their role — <b>Waiter</b>, <b>Kitchen</b> or <b>Reception</b>.',
     'એ પોતાની ભૂમિકા દબાવે — <b>Waiter</b>, <b>Kitchen</b> કે <b>Reception</b>.'),
   hlp('They log in with your <b>Property Code</b>' . ($propCode !== '' ? ' (<b>' . e($propCode) . '</b>)' : '') . ' plus their own 4-digit PIN. The Property Code is the same for your whole shop; the PIN is personal.',
     'એ તમારા <b>Property Code</b>' . ($propCode !== '' ? ' (<b>' . e($propCode) . '</b>)' : '') . ' અને પોતાના 4 આંકડાના PIN થી લોગિન કરે. Property Code આખી દુકાન માટે એક જ છે; PIN દરેકનો પોતાનો.'),
   hlp('Create each staff member and their PIN in <b>Staff</b>. Find or reset the Property Code in <b>Staff → Staff App</b>.',
     'દરેક સ્ટાફ અને તેનો PIN <b>Staff</b> માં બનાવો. Property Code <b>Staff → Staff App</b> માં જોવા/બદલવા મળશે.'),
 ],
 'tips' => [
   hlp('<b>Waiter</b> takes orders for any table. <b>Kitchen</b> sees what to cook and marks it ready. <b>Reception</b> runs the floor — takes orders, moves orders along, clears waiter calls and confirms reservations — but <u>cannot</u> collect payment. Money stays with you.',
     '<b>Waiter</b> કોઈપણ ટેબલનો ઓર્ડર લે. <b>Kitchen</b> શું બનાવવું તે જુએ અને તૈયાર નિશાની કરે. <b>Reception</b> ફ્લોર સંભાળે — ઓર્ડર લે, આગળ વધારે, waiter કોલ સાફ કરે, બુકિંગ કન્ફર્મ કરે — પણ પૈસા <u>ન</u> લઈ શકે. પૈસા તમારા હાથમાં જ રહે.'),
   hlp('Give every person their own PIN. Then you can see who took which order.',
     'દરેકને પોતાનો PIN આપો. પછી કોણે કયો ઓર્ડર લીધો તે જોઈ શકશો.'),
 ],
],

// =============================================================================
[
 'id' => 'reservations', 'icon' => 'bi-calendar-check', 'group' => hlp('Daily operations', 'રોજનું કામકાજ'),
 'title' => hlp('Table reservations', 'ટેબલ બુકિંગ'),
 'what'  => hlp(
   'Guests book a table from the menu; you confirm with one tap and they are told on WhatsApp automatically.',
   'ગ્રાહક મેનૂમાંથી ટેબલ બુક કરે; તમે એક ટેપે કન્ફર્મ કરો અને તેને WhatsApp પર આપમેળે જાણ થાય.'),
 'steps' => [
   hlp('A <b>Book a Table</b> button appears on your menu. The guest gives name, mobile, date, time and party size.',
     'તમારા મેનૂ પર <b>Book a Table</b> બટન દેખાય. ગ્રાહક નામ, મોબાઈલ, તારીખ, સમય અને માણસોની સંખ્યા આપે.'),
   hlp('You get a WhatsApp alert, and the booking appears in <b>Reservations</b>.',
     'તમને WhatsApp પર જાણ થાય અને બુકિંગ <b>Reservations</b> માં દેખાય.'),
   hlp('Press Confirm or Cancel. Either way the guest is notified on WhatsApp automatically — nobody has to call.',
     'Confirm કે Cancel દબાવો. બન્ને સ્થિતિમાં ગ્રાહકને WhatsApp પર આપમેળે જાણ થાય — કોઈએ ફોન કરવાની જરૂર નહીં.'),
   hlp('Reception staff can confirm today\'s bookings from the Staff App, so you do not have to be present.',
     'Reception સ્ટાફ Staff App થી આજના બુકિંગ કન્ફર્મ કરી શકે, એટલે તમારે હાજર રહેવું ન પડે.'),
 ],
],

// =============================================================================
[
 'id' => 'growth', 'icon' => 'bi-graph-up-arrow', 'group' => hlp('Grow your sales', 'વેચાણ વધારો'),
 'title' => hlp('Coupons, Loyalty &amp; Customers', 'કૂપન, લોયલ્ટી અને ગ્રાહકો'),
 'what'  => hlp(
   'Three tools that bring the same guest back a second and third time. Repeat guests cost you nothing to get.',
   'એ જ ગ્રાહકને બીજી અને ત્રીજી વાર લાવતા ત્રણ સાધન. ફરી આવતા ગ્રાહક લાવવાનો ખર્ચ શૂન્ય છે.'),
 'steps' => [
   hlp('<b>Coupons</b> — make a code like FIRST50 (flat or percent off, minimum bill, maximum discount, usage limit, expiry date). A “Have a coupon?” box appears in the diner\'s cart. The discount is always re-checked on our server.',
     '<b>Coupons</b> — FIRST50 જેવો કોડ બનાવો (ફ્લેટ કે ટકા, ન્યૂનતમ બિલ, મહત્તમ ડિસ્કાઉન્ટ, વપરાશ મર્યાદા, મુદત). ગ્રાહકના કાર્ટમાં “Have a coupon?” બોક્સ દેખાય. ડિસ્કાઉન્ટ હંમેશા અમારા સર્વર પર ફરી ચેક થાય.'),
   hlp('<b>Loyalty</b> — set the earn rate (points per ₹100), the minimum points needed to redeem and a maximum discount cap. Guests earn by mobile number and see their balance at checkout.',
     '<b>Loyalty</b> — કમાણીનો દર (₹100 દીઠ પોઈન્ટ), વાપરવા માટે જરૂરી ન્યૂનતમ પોઈન્ટ અને મહત્તમ ડિસ્કાઉન્ટ મર્યાદા સેટ કરો. ગ્રાહક મોબાઈલ નંબરથી કમાય અને ચેકઆઉટ વખતે બેલેન્સ જુએ.'),
   hlp('<b>Customers</b> — every diner who orders is remembered: visits, total spend, average bill, last visit and favourite dishes. One tap to WhatsApp them, and a CSV export.',
     '<b>Customers</b> — ઓર્ડર કરનાર દરેક ગ્રાહક યાદ રહે: મુલાકાત, કુલ ખર્ચ, સરેરાશ બિલ, છેલ્લી મુલાકાત, પ્રિય વાનગી. એક ટેપે WhatsApp અને CSV એક્સપોર્ટ.'),
   hlp('<b>Refer &amp; Earn</b> — share your referral link; when a restaurant you invited buys a plan, you get bonus days free.',
     '<b>Refer &amp; Earn</b> — તમારી રેફરલ લિંક શેર કરો; તમે બોલાવેલી રેસ્ટોરન્ટ પ્લાન ખરીદે તો તમને મફત બોનસ દિવસ મળે.'),
 ],
 'tips' => [
   hlp('Open <b>Customers</b>, sort by last visit, and WhatsApp everyone who has not come in 30 days with a small offer. This is the single highest-return 10 minutes of your week.',
     '<b>Customers</b> ખોલો, છેલ્લી મુલાકાત પ્રમાણે ગોઠવો, અને 30 દિવસથી ન આવેલા બધાને નાની ઓફર સાથે WhatsApp કરો. અઠવાડિયાની આ 10 મિનિટ સૌથી વધુ વળતર આપે છે.'),
 ],
],

// =============================================================================
[
 'id' => 'payments', 'icon' => 'bi-credit-card', 'group' => hlp('Grow your sales', 'વેચાણ વધારો'),
 'title' => hlp('Online payment by diners', 'ગ્રાહક પાસેથી ઓનલાઇન પેમેન્ટ'),
 'what'  => hlp(
   'Let guests pay the bill by UPI, card or netbanking from their own phone. The money goes to YOUR bank account, not ours.',
   'ગ્રાહક પોતાના મોબાઈલથી UPI, કાર્ડ કે નેટબેન્કિંગથી બિલ ભરે. પૈસા <b>તમારા</b> બેંક ખાતામાં જાય, અમારા નહીં.'),
 'steps' => [
   hlp('Create your own free Razorpay account and copy its Key ID and Secret.',
     'તમારું પોતાનું મફત Razorpay ખાતું બનાવો અને તેનો Key ID અને Secret કોપી કરો.'),
   hlp('Paste both into <b>Online Payment</b> and turn it on.',
     'બન્ને <b>Online Payment</b> માં પેસ્ટ કરો અને ચાલુ કરો.'),
   hlp('A <b>Pay Online</b> button now appears right after the diner places an order, with the exact amount.',
     'ગ્રાહક ઓર્ડર કરે કે તરત ચોક્કસ રકમ સાથે <b>Pay Online</b> બટન દેખાશે.'),
   hlp('Once the payment is verified the order marks itself <b>Paid</b>. If the guest skips it, the order simply stays payable at your counter.',
     'પેમેન્ટ ચેક થતાં ઓર્ડર આપમેળે <b>Paid</b> થઈ જાય. ગ્રાહક ન ભરે તો ઓર્ડર કાઉન્ટર પર ભરવા યોગ્ય રહે.'),
 ],
 'warn' => hlp('These keys are yours alone and are used only for your restaurant\'s bills. Never share them with anyone, including over WhatsApp.',
             'આ કી ફક્ત તમારી છે અને ફક્ત તમારી રેસ્ટોરન્ટના બિલ માટે વપરાય છે. WhatsApp સહિત કોઈ સાથે શેર ન કરો.'),
],

// =============================================================================
[
 'id' => 'reports', 'icon' => 'bi-bar-chart', 'group' => hlp('Understand your business', 'ધંધો સમજો'),
 'title' => hlp('Reports, Feedback &amp; AI Advisor', 'રિપોર્ટ, અભિપ્રાય અને AI સલાહકાર'),
 'what'  => hlp(
   'Numbers you can act on, not just numbers to look at.',
   'ફક્ત જોવા માટેના નહીં, કામમાં લઈ શકાય એવા આંકડા.'),
 'steps' => [
   hlp('<b>Reports</b> — pick any date range and see sales, order count, top items, payment breakdown by mode (cash vs UPI vs bank), unpaid dues, and a <b>Busiest Hours</b> chart.',
     '<b>Reports</b> — કોઈપણ તારીખ શ્રેણી પસંદ કરી વેચાણ, ઓર્ડર સંખ્યા, ટોપ વસ્તુઓ, મોડ પ્રમાણે પેમેન્ટ (રોકડ/UPI/બેંક), બાકી રકમ અને <b>Busiest Hours</b> ચાર્ટ જુઓ.'),
   hlp('Use Busiest Hours to decide staff timings and when to send an offer. Sending an offer 1 hour before your peak works best.',
     'સ્ટાફનો સમય અને ઓફર ક્યારે મોકલવી તે Busiest Hours થી નક્કી કરો. પીક સમયથી 1 કલાક પહેલા ઓફર મોકલવી સૌથી સારી.'),
   hlp('<b>Feedback</b> — diners rate you from the menu. Read every 1- and 2-star comment the same day.',
     '<b>Feedback</b> — ગ્રાહક મેનૂમાંથી રેટિંગ આપે. 1 અને 2 સ્ટારની દરેક ટિપ્પણી એ જ દિવસે વાંચો.'),
   hlp('<b>AI Advisor</b> — press the button and it reads your last 30 days (sales, best and slow items, busiest hours and days, repeat guests) and gives 5–7 concrete suggestions in Gujarati and English.',
     '<b>AI Advisor</b> — બટન દબાવો, એ તમારા છેલ્લા 30 દિવસ વાંચે (વેચાણ, સારી અને ધીમી વસ્તુ, વ્યસ્ત કલાક અને દિવસ, ફરી આવતા ગ્રાહક) અને ગુજરાતી તથા અંગ્રેજીમાં 5–7 ચોક્કસ સૂચન આપે.'),
   hlp('A <b>daily summary</b> of the day\'s orders, revenue and top item reaches you on WhatsApp every night automatically.',
     'દિવસના ઓર્ડર, આવક અને ટોપ વસ્તુનો <b>રોજનો સારાંશ</b> દરરોજ રાત્રે WhatsApp પર આપમેળે આવે છે.'),
 ],
],

// =============================================================================
[
 'id' => 'tv-chat', 'icon' => 'bi-tv', 'group' => hlp('Understand your business', 'ધંધો સમજો'),
 'title' => hlp('TV Menu Board &amp; AI Chatbot', 'TV મેનૂ બોર્ડ અને AI ચેટબોટ'),
 'what'  => hlp(
   'Two screens that work for you without anyone operating them.',
   'કોઈ ચલાવ્યા વગર તમારા માટે કામ કરતી બે સ્ક્રીન.'),
 'steps' => [
   hlp('<b>TV Menu Board</b> — open the TV Menu Board tile on your dashboard, then open that link on the TV or screen in your shop. It scrolls your menu full-screen in your own colours and refreshes itself, so menu edits appear by themselves.',
     '<b>TV Menu Board</b> — ડેશબોર્ડ પરની TV Menu Board ટાઇલ ખોલો, પછી એ લિંક દુકાનના TV કે સ્ક્રીન પર ખોલો. તમારા જ રંગમાં આખી સ્ક્રીન પર મેનૂ ફરતું રહે અને જાતે રિફ્રેશ થાય, એટલે મેનૂમાં કરેલા ફેરફાર આપમેળે દેખાય.'),
   hlp('<b>AI Chatbot</b> — a floating assistant on your menu answers diners\' questions (“what is Jain?”, “anything under ₹200?”, “what is your bestseller?”) in English, Hindi and Gujarati. It only ever answers from <i>your</i> menu.',
     '<b>AI Chatbot</b> — તમારા મેનૂ પર તરતો સહાયક ગ્રાહકના સવાલના જવાબ આપે (“Jain શું છે?”, “₹200 થી નીચે શું?”, “સૌથી વધુ વેચાતું શું?”) અંગ્રેજી, હિન્દી અને ગુજરાતીમાં. એ ફક્ત <i>તમારા</i> મેનૂ પરથી જવાબ આપે છે.'),
 ],
],

// =============================================================================
[
 'id' => 'whatsapp', 'icon' => 'bi-whatsapp', 'group' => hlp('Messages &amp; account', 'સંદેશા અને ખાતું'),
 'title' => hlp('WhatsApp notifications', 'WhatsApp સૂચના'),
 'what'  => hlp(
   'The system can message you and your guests on WhatsApp automatically. You choose which ones.',
   'સિસ્ટમ તમને અને તમારા ગ્રાહકોને WhatsApp પર આપમેળે સંદેશો મોકલી શકે. કયા મોકલવા તે તમે નક્કી કરો.'),
 'steps' => [
   hlp('Open <b>WhatsApp</b> and switch individual notifications on or off: new order, reservation, daily summary and more.',
     '<b>WhatsApp</b> ખોલો અને દરેક સૂચના ચાલુ/બંધ કરો: નવો ઓર્ડર, બુકિંગ, રોજનો સારાંશ વગેરે.'),
   hlp('Use <b>Send menu link</b> to WhatsApp your menu to any customer number in one tap.',
     'કોઈપણ ગ્રાહકના નંબર પર એક ટેપે મેનૂ મોકલવા <b>Send menu link</b> વાપરો.'),
   hlp('Messages are queued and sent within about a minute. If one fails you will see it marked, not silently lost.',
     'સંદેશા કતારમાં જાય અને લગભગ એક મિનિટમાં જાય. કોઈ નિષ્ફળ જાય તો નિશાની દેખાશે, ચૂપચાપ ખોવાઈ નહીં જાય.'),
 ],
],

// =============================================================================
[
 'id' => 'settings', 'icon' => 'bi-gear', 'group' => hlp('Messages &amp; account', 'સંદેશા અને ખાતું'),
 'title' => hlp('Settings you should check once', 'એક વાર ચેક કરવા જેવા સેટિંગ'),
 'what'  => hlp(
   'Open Settings on day one, go through this list, and then you can forget about it.',
   'પહેલા દિવસે Settings ખોલો, આ યાદી જોઈ લો, પછી ભૂલી જઈ શકો.'),
 'steps' => [
   hlp('<b>Direct Ordering</b> — ON lets diners order from their phone; OFF makes it a read-only menu.',
     '<b>Direct Ordering</b> — ચાલુ હોય તો ગ્રાહક મોબાઈલથી ઓર્ડર કરે; બંધ હોય તો ફક્ત વાંચવાનું મેનૂ.'),
   hlp('<b>E-mail address</b> — fill this in. It is your backup login and where password-reset links go.',
     '<b>ઈમેલ</b> — આ ભરો. એ તમારું બેકઅપ લોગિન છે અને પાસવર્ડ રિસેટ લિંક ત્યાં આવે છે.'),
   hlp('<b>Language</b> — English or Gujarati for your panel.',
     '<b>ભાષા</b> — તમારી પેનલ માટે અંગ્રેજી કે ગુજરાતી.'),
   hlp('<b>Allow Google indexing</b> — ON lets your menu appear in Google searches. Leave it ON unless you want a private menu.',
     '<b>Allow Google indexing</b> — ચાલુ હોય તો તમારું મેનૂ Google શોધમાં દેખાય. ખાનગી મેનૂ જોઈતું ન હોય તો ચાલુ રાખો.'),
   hlp('<b>Change password</b> — do this if you ever shared your password with a staff member.',
     '<b>પાસવર્ડ બદલો</b> — કોઈ સ્ટાફ સાથે પાસવર્ડ શેર કર્યો હોય તો આ કરો.'),
   hlp('<b>Plans &amp; Billing</b> — see your current plan, days left, and renew online (Razorpay) or offline (UPI / scan-to-pay QR). Every payment gets a printable GST invoice.',
     '<b>Plans &amp; Billing</b> — તમારો પ્લાન, બાકી દિવસ જુઓ અને ઓનલાઇન (Razorpay) કે ઓફલાઇન (UPI / સ્કેન QR) રિન્યૂ કરો. દરેક પેમેન્ટનું પ્રિન્ટ થઈ શકતું GST ઇન્વોઇસ મળે.'),
 ],
],

// =============================================================================
[
 'id' => 'trouble', 'icon' => 'bi-tools', 'group' => hlp('If something is wrong', 'કંઈ ખોટું થાય તો'),
 'title' => hlp('Troubleshooting — the 8 common problems', 'મુશ્કેલી — 8 સામાન્ય સમસ્યા'),
 'what'  => hlp(
   'Try the fix here first. It solves most issues in under a minute.',
   'પહેલા અહીંનો ઉપાય કરો. મોટાભાગની સમસ્યા એક મિનિટમાં ઉકલી જાય છે.'),
 'steps' => [
   hlp('<b>QR scans but nothing opens</b> — the phone has no internet, or the printed QR is damaged/reflective. Test the same QR with your own phone on mobile data.',
     '<b>QR સ્કેન થાય પણ કંઈ ખુલતું નથી</b> — મોબાઈલમાં ઇન્ટરનેટ નથી, કે પ્રિન્ટેડ QR બગડેલો/ચમકતો છે. એ જ QR તમારા મોબાઈલના ડેટા પર ચેક કરો.'),
   hlp('<b>Diners cannot order, only read</b> — Direct Ordering is OFF in Settings, or your plan has expired. Check Plans &amp; Billing.',
     '<b>ગ્રાહક ઓર્ડર નથી કરી શકતા, ફક્ત વાંચે છે</b> — Settings માં Direct Ordering બંધ છે, કે પ્લાન પૂરો થયો છે. Plans &amp; Billing જુઓ.'),
   hlp('<b>No sound when an order arrives</b> — the sound icon in the top bar is off, or the phone is on silent. Browsers also need you to tap the page once before they will play sound.',
     '<b>ઓર્ડર આવે ત્યારે આવાજ નથી</b> — ઉપરની પટ્ટીમાં આવાજ બંધ છે, કે મોબાઈલ સાયલન્ટ છે. બ્રાઉઝરને આવાજ વગાડવા પહેલા પાના પર એક ટેપ જોઈએ છે.'),
   hlp('<b>A dish still shows after I removed it</b> — the diner\'s browser kept an old copy. Ask them to pull down to refresh; new visitors already see the change.',
     '<b>કાઢી નાખેલી વાનગી હજી દેખાય છે</b> — ગ્રાહકના બ્રાઉઝરે જૂની નકલ રાખી છે. નીચે ખેંચીને રિફ્રેશ કરવા કહો; નવા ગ્રાહકને ફેરફાર દેખાય જ છે.'),
   hlp('<b>Staff App says wrong code</b> — check the Property Code in Staff → Staff App and the person\'s 4-digit PIN in Staff. Both must match exactly.',
     '<b>Staff App ખોટો કોડ કહે છે</b> — Staff → Staff App માં Property Code અને Staff માં તે વ્યક્તિનો 4 આંકડાનો PIN ચેક કરો. બન્ને બરાબર મળવા જોઈએ.'),
   hlp('<b>Password reset e-mail did not arrive</b> — look in Spam/Promotions. Still nothing after 5 minutes? Use the mobile option instead and tell support.',
     '<b>પાસવર્ડ રિસેટ ઈમેલ ન આવ્યો</b> — Spam/Promotions જુઓ. 5 મિનિટ પછી પણ નહીં? મોબાઈલનો વિકલ્પ વાપરો અને સપોર્ટને જણાવો.'),
   hlp('<b>WhatsApp message did not reach the guest</b> — the number must be a real WhatsApp number. Check it in the WhatsApp page; a failed message is marked there with the reason.',
     '<b>ગ્રાહકને WhatsApp સંદેશો ન પહોંચ્યો</b> — નંબર સાચો WhatsApp નંબર હોવો જોઈએ. WhatsApp પાના પર ચેક કરો; નિષ્ફળ સંદેશો કારણ સાથે નિશાની કરેલો હોય છે.'),
   hlp('<b>Photo will not upload</b> — the file is too large or not a JPG/PNG. Take a normal camera photo instead of a screenshot of a PDF.',
     '<b>ફોટો અપલોડ નથી થતો</b> — ફાઈલ મોટી છે કે JPG/PNG નથી. PDF ના સ્ક્રીનશોટને બદલે સામાન્ય કેમેરા ફોટો લો.'),
 ],
],

];

// Group the chapters for the sidebar, preserving declaration order.
$groups = [];
foreach ($guide as $i => $ch) {
    $groups[$ch['group']['en']]['label'] = $ch['group'];
    $groups[$ch['group']['en']]['items'][] = $ch;
}

/** Render a bilingual string: both languages ship, CSS shows one. */
function hbi(array $s): string {
    return '<span class="l-en">' . $s['en'] . '</span><span class="l-gu">' . $s['gu'] . '</span>';
}

$pageTitle = 'Help & Guide';
$activeNav = 'help';
require __DIR__ . '/_header.php';
?>
<style>
/* Language switch: both strings are in the DOM; one is hidden. */
body.lang-en .l-gu, body.lang-gu .l-en { display:none; }
.help-toc { position:sticky; top:80px; max-height:calc(100vh - 110px); overflow-y:auto; }
.help-toc a { display:block; padding:5px 10px; border-radius:6px; font-size:.88rem;
              color:#475467; text-decoration:none; border-left:3px solid transparent; }
.help-toc a:hover { background:#f2f4f7; }
.help-toc a.active { background:#f2f4f7; color:var(--primary); font-weight:600; border-left-color:var(--primary); }
.help-toc .toc-group { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em;
                       color:#98a2b3; font-weight:700; margin:14px 0 4px 10px; }
.help-ch { scroll-margin-top:84px; }
.help-ch h4 { font-size:1.12rem; font-weight:700; }
.help-steps { counter-reset:hs; list-style:none; padding-left:0; margin:0; }
.help-steps > li { counter-increment:hs; position:relative; padding:0 0 0 40px; margin-bottom:14px; line-height:1.6; }
.help-steps > li::before {
  content:counter(hs); position:absolute; left:0; top:1px; width:26px; height:26px;
  border-radius:50%; background:var(--primary); color:#fff; font-size:.8rem; font-weight:700;
  display:flex; align-items:center; justify-content:center;
}
.help-ch code { background:#f2f4f7; padding:2px 6px; border-radius:4px; font-size:.85em; word-break:break-all; }
.help-tip  { background:#f0f9f4; border-left:4px solid #12b76a; padding:10px 14px; border-radius:0 8px 8px 0; margin-top:6px; }
.help-warn { background:#fff6ed; border-left:4px solid #f79009; padding:10px 14px; border-radius:0 8px 8px 0; margin-top:10px; }
.help-ch p, .help-ch li { overflow-wrap:break-word; }
#helpNoMatch { display:none; }
@media print { .sidebar,.topbar,.help-toc,#helpSearchWrap,.help-noprint { display:none !important; } }
</style>

<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
  <div>
    <h5 class="mb-1"><i class="bi bi-life-preserver"></i> <span class="l-en">Help &amp; Guide</span><span class="l-gu">મદદ અને માર્ગદર્શિકા</span></h5>
    <p class="text-muted small mb-0">
      <span class="l-en">Everything this system does, in the order you should set it up. No jargon.</span>
      <span class="l-gu">આ સિસ્ટમ જે કરે છે તે બધું, જે ક્રમમાં ગોઠવવું જોઈએ તે ક્રમમાં. કોઈ અઘરા શબ્દ નહીં.</span>
    </p>
  </div>
  <div class="btn-group help-noprint" role="group" aria-label="Language">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnEn" onclick="helpLang('en')">English</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnGu" onclick="helpLang('gu')">ગુજરાતી</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()" title="Print this guide">
      <i class="bi bi-printer"></i>
    </button>
  </div>
</div>

<div class="row g-4">
  <!-- Sidebar / table of contents -->
  <div class="col-lg-3 order-lg-1 help-noprint">
    <div class="help-toc">
      <div id="helpSearchWrap" class="mb-2">
        <input id="helpSearch" class="form-control form-control-sm" placeholder="Search this guide…"
               oninput="helpFilter(this.value)">
      </div>
      <?php foreach ($groups as $grp): ?>
        <div class="toc-group"><?= hbi($grp['label']) ?></div>
        <?php foreach ($grp['items'] as $ch): ?>
          <a href="#ch-<?= e($ch['id']) ?>" data-toc="<?= e($ch['id']) ?>">
            <i class="bi <?= e($ch['icon']) ?> me-1"></i><?= hbi($ch['title']) ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Chapters -->
  <div class="col-lg-9">
    <div id="helpNoMatch" class="alert alert-light border">
      <i class="bi bi-search"></i>
      <span class="l-en">Nothing in this guide matches that word. Try a shorter word, or clear the search.</span>
      <span class="l-gu">આ માર્ગદર્શિકામાં એ શબ્દ સાથે કંઈ મળતું નથી. ટૂંકો શબ્દ વાપરો કે શોધ સાફ કરો.</span>
    </div>

    <?php foreach ($guide as $ch): ?>
      <section class="card border-0 shadow-sm mb-3 help-ch" id="ch-<?= e($ch['id']) ?>" data-ch="<?= e($ch['id']) ?>">
        <div class="card-body">
          <h4 class="d-flex align-items-start gap-2">
            <i class="bi <?= e($ch['icon']) ?>" style="color:var(--primary)"></i>
            <span><?= hbi($ch['title']) ?></span>
          </h4>
          <p class="text-muted"><?= hbi($ch['what']) ?></p>

          <ol class="help-steps mt-3">
            <?php foreach ($ch['steps'] as $st): ?>
              <li><?= hbi($st) ?></li>
            <?php endforeach; ?>
          </ol>

          <?php foreach ($ch['tips'] ?? [] as $tip): ?>
            <div class="help-tip small">
              <strong><i class="bi bi-lightbulb"></i>
                <span class="l-en">Tip</span><span class="l-gu">સૂચન</span>:
              </strong>
              <?= hbi($tip) ?>
            </div>
          <?php endforeach; ?>

          <?php if (!empty($ch['warn'])): ?>
            <div class="help-warn small">
              <strong><i class="bi bi-exclamation-triangle"></i>
                <span class="l-en">Important</span><span class="l-gu">મહત્વનું</span>:
              </strong>
              <?= hbi($ch['warn']) ?>
            </div>
          <?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <div class="card border-0 shadow-sm mb-3 help-noprint">
      <div class="card-body text-center">
        <h6 class="mb-2"><span class="l-en">Still stuck?</span><span class="l-gu">હજી અટકી ગયા?</span></h6>
        <p class="text-muted small mb-3">
          <span class="l-en">Tell us exactly which page you were on and what you expected to happen — that gets you answered fastest.</span>
          <span class="l-gu">તમે કયા પાના પર હતા અને શું થવાની અપેક્ષા હતી તે બરાબર જણાવો — એનાથી જવાબ સૌથી જલ્દી મળે.</span>
        </p>
        <?php $sw = getSetting('support_whatsapp', ''); ?>
        <?php if ($sw !== ''): ?>
          <a class="btn btn-success" target="_blank" rel="noopener"
             href="https://wa.me/<?= e(preg_replace('/\D/', '', strlen(preg_replace('/\D/','',$sw)) === 10 ? '91' . $sw : $sw)) ?>">
            <i class="bi bi-whatsapp"></i> <span class="l-en">WhatsApp support</span><span class="l-gu">WhatsApp સપોર્ટ</span>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
// ---- Language switch --------------------------------------------------------
function helpLang(l){
  document.body.classList.toggle('lang-gu', l === 'gu');
  document.body.classList.toggle('lang-en', l !== 'gu');
  document.getElementById('btnEn').classList.toggle('active', l !== 'gu');
  document.getElementById('btnGu').classList.toggle('active', l === 'gu');
  try { localStorage.setItem('akHelpLang', l); } catch(e){}
}
(function(){
  var saved = null;
  try { saved = localStorage.getItem('akHelpLang'); } catch(e){}
  helpLang(saved || '<?= e($lang) ?>');
})();

// ---- Search: hide chapters that do not contain the word ---------------------
function helpFilter(q){
  q = (q || '').trim().toLowerCase();
  var shown = 0;
  document.querySelectorAll('.help-ch').forEach(function(sec){
    var hit = q === '' || (sec.textContent || '').toLowerCase().indexOf(q) !== -1;
    sec.style.display = hit ? '' : 'none';
    var link = document.querySelector('[data-toc="' + sec.dataset.ch + '"]');
    if (link) { link.style.display = hit ? '' : 'none'; }
    if (hit) { shown++; }
  });
  document.getElementById('helpNoMatch').style.display = shown ? 'none' : 'block';
}

// ---- Scroll-spy: highlight the chapter currently on screen ------------------
(function(){
  var links = Array.prototype.slice.call(document.querySelectorAll('[data-toc]'));
  var secs  = Array.prototype.slice.call(document.querySelectorAll('.help-ch'));
  if (!links.length) { return; }
  var queued = false;

  function sync(){
    queued = false;
    var current = secs[0];
    secs.forEach(function(s){
      if (s.style.display !== 'none' && s.getBoundingClientRect().top <= 140) { current = s; }
    });
    links.forEach(function(a){
      a.classList.toggle('active', current && a.dataset.toc === current.dataset.ch);
    });
  }
  window.addEventListener('scroll', function(){
    if (!queued) { queued = true; requestAnimationFrame(sync); }
  }, {passive:true});
  sync();
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
