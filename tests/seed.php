<?php

declare(strict_types=1);

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec('DROP DATABASE IF EXISTS test_database');
$pdo->exec('CREATE DATABASE test_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE test_database');

echo "Creating tables...\n";

$pdo->exec("
    CREATE TABLE users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        address TEXT,
        city VARCHAR(50) DEFAULT NULL,
        bio TEXT,
        notes TEXT,
        status TINYINT NOT NULL DEFAULT 1,
        balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY email_unique (email)
    ) ENGINE=InnoDB
");

$pdo->exec("
    CREATE TABLE categories (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        is_active TINYINT NOT NULL DEFAULT 1
    ) ENGINE=InnoDB
");

$pdo->exec("
    CREATE TABLE products (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        category_id INT UNSIGNED NOT NULL,
        name VARCHAR(200) NOT NULL,
        description TEXT,
        details TEXT,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        stock INT NOT NULL DEFAULT 0,
        is_active TINYINT NOT NULL DEFAULT 1,
        INDEX cat_idx (category_id),
        CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
    ) ENGINE=InnoDB
");

$pdo->exec("
    CREATE TABLE orders (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        status ENUM('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
        total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        shipping_address TEXT,
        notes TEXT,
        placed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX user_idx (user_id),
        CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB
");

$pdo->exec("
    CREATE TABLE order_items (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL,
        product_id INT UNSIGNED NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        INDEX order_idx (order_id),
        CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id),
        CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id)
    ) ENGINE=InnoDB
");

$pdo->exec("
    CREATE TABLE logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        level ENUM('debug','info','warning','error') NOT NULL DEFAULT 'info',
        message TEXT NOT NULL,
        context TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX level_idx (level)
    ) ENGINE=InnoDB
");

$pdo->exec("
    CREATE TABLE reviews (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        product_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
        title VARCHAR(200) DEFAULT NULL,
        body TEXT,
        is_approved TINYINT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX product_idx (product_id),
        INDEX user_idx (user_id),
        CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id),
        CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB
");

// Create views
$pdo->exec("
    CREATE VIEW active_categories AS
    SELECT id, name FROM categories WHERE is_active = 1
");
$pdo->exec("
    CREATE VIEW top_users AS
    SELECT id, name, balance FROM users WHERE balance > 1000 ORDER BY balance DESC
");

echo "Tables created.\n\n";

// ---- HELPERS ----
function randDate(): string {
    return date('Y-m-d H:i:s', strtotime('-' . rand(0, 365) . ' days ' . rand(0, 23) . ' hours ' . rand(0, 59) . ' minutes'));
}

function batchInsert(PDO $pdo, string $table, array $columns, array $rows): void {
    if (empty($rows)) return;
    $colList = '`' . implode('`, `', $columns) . '`';
    $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
    $values = implode(', ', array_fill(0, count($rows), $placeholders));
    $stmt = $pdo->prepare("INSERT INTO `{$table}` ({$colList}) VALUES {$values}");
    $flat = [];
    foreach ($rows as $row) {
        foreach ($columns as $col) {
            $flat[] = $row[$col] ?? null;
        }
    }
    $stmt->execute($flat);
}

// ---- WORD POOL (300 English words for long text generation) ----
$words = [
    'hello','world','today','weather','very','nice','tomorrow','rain','expected','temperature',
    'degree','will','project','team','working','deadline','deliver','client','satisfaction','quality',
    'service','provide','effort','system','update','completed','users','notified','security','patch',
    'closed','performance','improvements','ongoing','report','ready','meeting','technical','solution',
    'resolved','database','backup','finished','file','size','checked','server','maintenance','weekend',
    'scheduled','temporary','access','employees','informed','changes','applied','payment','updated',
    'faster','processing','mobile','application','released','features','added','bugs','fixed',
    'experience','improved','design','updated','menu','layout','changed','search','function','results',
    'accurate','filters','options','increased','sorting','algorithm','optimized','page','load','speed',
    'images','compressed','cache','cleared','session','expired','password','reset','request','sent',
    'email','verified','registration','successful','profile','photo','uploaded','cover','changed',
    'bio','text','added','links','edited','privacy','settings','notifications','enabled','sound',
    'level','adjusted','language','preference','changed','theme','selected','dark','mode','activated',
    'font','size','increased','screen','reader','support','added','accessibility','keyboard','shortcuts',
    'customized','reporting','module','developed','charts','included','excel','export','pdf','save',
    'table','filtered','total','amount','calculated','discount','applied','shipping','free','tax',
    'included','estimated','delivery','days','shipped','tracking','number','created','order','en',
    'route','shortest','time','customer','service','contact','return','process','started','refund',
    'stock','checked','product','supplied','supplier','notified','shipment','planned','warehouse','ready',
    'inventory','count','missing','item','reported','invoice','created','receipt','issued','tax',
    'number','e-invoice','sent','accounting','record','created','budget','approved','expense','report',
    'revenue','table','generated','profitability','analysis','growth','rate','calculated','goals',
    'set','strategy','developed','market','research','completed','competitor','analysis','customer',
    'survey','conducted','feedback','collected','satisfaction','score','calculated','net','promoter',
    'score','evaluation','done','performance','indicators','defined','action','plan','created',
    'assignees','appointed','date','set','progress','tracked','completed','tasks','reported','delayed',
    'items','listed','prioritization','done','critical','tasks','identified','urgent','solution','pending',
    'teams','coordinated','working','regular','meetings','held','decisions','made','implemented',
    'processes','documented','information','sharing','increased','communication','channels','strengthened',
    'successful','year','goals','motivation','high','future','plans','ambitious',
];

function longText(int $minWords = 100, int $maxWords = 300): string {
    global $words;
    $count = rand($minWords, $maxWords);
    $parts = [];
    for ($i = 0; $i < $count; $i++) {
        $parts[] = $words[array_rand($words)];
    }
    return implode(' ', $parts);
}

// ---- DATA POOLS ----
$firstNames = ['James','Mary','John','Patricia','Robert','Jennifer','Michael','Linda','David','Elizabeth',
    'William','Barbara','Richard','Susan','Joseph','Jessica','Thomas','Sarah','Christopher','Karen',
    'Charles','Lisa','Daniel','Nancy','Matthew','Betty','Anthony','Margaret','Mark','Sandra','Donald',
    'Ashley','Steven','Kimberly','Andrew','Emily','Paul','Donna','Joshua','Michelle','Kenneth','Carol',
    'Kevin','Amanda','Brian','Dorothy','George','Melissa','Timothy','Deborah','Ronald','Stephanie',
    'Edward','Rebecca','Jason','Sharon','Jeffrey','Laura','Ryan','Cynthia','Jacob','Kathleen','Gary',
    'Amy','Nicholas','Angela','Eric','Shirley','Jonathan','Anna','Stephen','Brenda','Larry','Pamela',
    'Justin','Emma','Scott','Nicole','Brandon','Helen','Benjamin','Samantha','Samuel','Katherine',
    'Raymond','Christine','Gregory','Debra','Frank','Rachel','Alexander','Carolyn','Patrick','Janet',
    'Jack','Catherine','Henry','Maria','Walter','Heather'];

$lastNames = ['Smith','Johnson','Williams','Brown','Jones','Garcia','Miller','Davis','Rodriguez','Martinez',
    'Hernandez','Lopez','Gonzalez','Wilson','Anderson','Thomas','Taylor','Moore','Jackson','Martin',
    'Lee','Perez','Thompson','White','Harris','Sanchez','Clark','Ramirez','Lewis','Robinson','Walker',
    'Young','Allen','King','Wright','Scott','Torres','Nguyen','Hill','Flores','Green','Adams','Nelson',
    'Baker','Hall','Rivera','Campbell','Mitchell','Carter','Roberts'];

$cities = ['New York','Los Angeles','Chicago','Houston','Phoenix','Philadelphia','San Antonio','San Diego',
    'Dallas','San Jose','Austin','Jacksonville','Fort Worth','Columbus','Charlotte','Indianapolis',
    'San Francisco','Seattle','Denver','Nashville','Oklahoma City','El Paso','Washington','Boston',
    'Las Vegas','Portland','Memphis','Louisville','Baltimore','Milwaukee','Albuquerque','Tucson',
    'Fresno','Sacramento','Mesa','Kansas City','Atlanta','Omaha','Colorado Springs','Raleigh',
    'Long Beach','Virginia Beach','Miami','Oakland','Minneapolis','Tampa','Tulsa','Arlington',
    'New Orleans','Cleveland'];

$prodNames = ['Computer','Phone','Headphone','Keyboard','Mouse','Speaker','Tablet','Monitor','Printer',
    'Scanner','Camera','Watch','Charger','Cable','Adapter','Battery','Hard Drive','USB Drive',
    'Console','Router','Microphone','Webcam','Projector','Television','Sound System','Powerbank',
    'Bluetooth','Navigation','Drone','Earbuds'];

$prodPrefixes = ['Premium','Super','Pro','Max','Lite','Ultra','Smart','Classic','Modern','Elite'];

$reviewTitles = ['Great product','Very satisfied','Good','Average','Poor','As expected','Excellent',
    'Best value','Not bad','Highly recommend','Disappointed','Outstanding','Fast shipping',
    'Terrible','Would buy again'];

$start = microtime(true);
$batch = 1000;

// ---- CATEGORIES ----
echo "Seeding categories (50)...\n";
$catNames = ['Electronics','Computers','Phones','Accessories','Office Supplies','Clothing','Books',
    'Sports','Music','Games','Software','Hardware','Networking','Security','Storage','Audio',
    'Education','Hobbies','Travel','Home','Kitchen','Garden','Health','Beauty','Automotive',
    'Baby','Toys','Jewelry','Organization','Stationery','Energy','Lighting','Furniture',
    'Decoration','Carpets','Tools','Watches','Bags','Shoes','Gifts','Cleaning','Pets',
    'Personal Care','Heating','Cooling','Tableware','Curtains','Optics','Wholesale','Outlet'];
$rows = [];
foreach ($catNames as $name) {
    $rows[] = ['name' => $name, 'description' => longText(30, 80), 'is_active' => 1];
    if (count($rows) >= $batch) { batchInsert($pdo, 'categories', ['name','description','is_active'], $rows); echo '.'; $rows = []; }
}
if ($rows) batchInsert($pdo, 'categories', ['name','description','is_active'], $rows);
echo " " . number_format(microtime(true) - $start, 2) . "s\n";

// ---- USERS (100K) ----
echo "Seeding users (100K)...\n";
$rows = [];
for ($i = 0; $i < 100000; $i++) {
    $first = $firstNames[array_rand($firstNames)];
    $last = $lastNames[array_rand($lastNames)];
    $rows[] = [
        'name' => $first . ' ' . $last,
        'email' => strtolower($first) . '.' . strtolower($last) . ($i + 1) . '@example.com',
        'phone' => '+1' . str_pad((string)rand(200000000, 999999999), 9, '0', STR_PAD_LEFT),
        'address' => longText(20, 50),
        'city' => $cities[array_rand($cities)],
        'bio' => longText(50, 150),
        'notes' => longText(80, 200),
        'status' => rand(1, 20) > 1 ? 1 : 0,
        'balance' => round(rand(0, 5000000) / 100, 2),
    ];
    if (count($rows) >= $batch) {
        batchInsert($pdo, 'users', ['name','email','phone','address','city','bio','notes','status','balance'], $rows);
        echo '.';
        $rows = [];
    }
}
if ($rows) batchInsert($pdo, 'users', ['name','email','phone','address','city','bio','notes','status','balance'], $rows);
echo " " . number_format(microtime(true) - $start, 2) . "s\n";

// ---- PRODUCTS (50K) ----
echo "Seeding products (50K)...\n";
$rows = [];
for ($i = 0; $i < 50000; $i++) {
    $rows[] = [
        'category_id' => rand(1, 50),
        'name' => $prodPrefixes[array_rand($prodPrefixes)] . ' ' . $prodNames[array_rand($prodNames)] . ' ' . ($i + 1),
        'description' => longText(50, 150),
        'details' => longText(100, 250),
        'price' => round(rand(500, 5000000) / 100, 2),
        'stock' => rand(0, 1000),
        'is_active' => rand(1, 20) > 1 ? 1 : 0,
    ];
    if (count($rows) >= $batch) {
        batchInsert($pdo, 'products', ['category_id','name','description','details','price','stock','is_active'], $rows);
        echo '.';
        $rows = [];
    }
}
if ($rows) batchInsert($pdo, 'products', ['category_id','name','description','details','price','stock','is_active'], $rows);
echo " " . number_format(microtime(true) - $start, 2) . "s\n";

// ---- ORDERS (100K) ----
echo "Seeding orders (100K)...\n";
$rows = [];
for ($i = 0; $i < 100000; $i++) {
    $rows[] = [
        'user_id' => rand(1, 100000),
        'status' => ['pending','confirmed','shipped','delivered','cancelled'][array_rand(['pending','confirmed','shipped','delivered','cancelled'])],
        'total' => round(rand(1000, 5000000) / 100, 2),
        'shipping_address' => longText(30, 80),
        'notes' => longText(50, 150),
        'placed_at' => randDate(),
    ];
    if (count($rows) >= $batch) {
        batchInsert($pdo, 'orders', ['user_id','status','total','shipping_address','notes','placed_at'], $rows);
        echo '.';
        $rows = [];
    }
}
if ($rows) batchInsert($pdo, 'orders', ['user_id','status','total','shipping_address','notes','placed_at'], $rows);
echo " " . number_format(microtime(true) - $start, 2) . "s\n";

// ---- ORDER_ITEMS (300K) ----
echo "Seeding order_items (300K)...\n";
$rows = [];
for ($i = 0; $i < 300000; $i++) {
    $rows[] = [
        'order_id' => rand(1, 100000),
        'product_id' => rand(1, 50000),
        'quantity' => rand(1, 5),
        'unit_price' => round(rand(500, 500000) / 100, 2),
    ];
    if (count($rows) >= $batch) {
        batchInsert($pdo, 'order_items', ['order_id','product_id','quantity','unit_price'], $rows);
        echo '.';
        $rows = [];
    }
}
if ($rows) batchInsert($pdo, 'order_items', ['order_id','product_id','quantity','unit_price'], $rows);
echo " " . number_format(microtime(true) - $start, 2) . "s\n";

// ---- LOGS (200K) ----
echo "Seeding logs (200K)...\n";
$rows = [];
for ($i = 0; $i < 200000; $i++) {
    $rows[] = [
        'level' => ['debug','info','warning','error'][array_rand(['debug','info','warning','error'])],
        'message' => longText(30, 100),
        'context' => longText(100, 250),
    ];
    if (count($rows) >= $batch) {
        batchInsert($pdo, 'logs', ['level','message','context'], $rows);
        echo '.';
        $rows = [];
    }
}
if ($rows) batchInsert($pdo, 'logs', ['level','message','context'], $rows);
echo " " . number_format(microtime(true) - $start, 2) . "s\n";

// ---- REVIEWS (300K) ----
echo "Seeding reviews (300K)...\n";
$rows = [];
for ($i = 0; $i < 300000; $i++) {
    $rows[] = [
        'product_id' => rand(1, 50000),
        'user_id' => rand(1, 100000),
        'rating' => [1,2,3,4,5][array_rand([1,2,3,4,5])],
        'title' => $reviewTitles[array_rand($reviewTitles)],
        'body' => longText(50, 200),
        'is_approved' => rand(0, 10) > 1 ? 1 : 0,
    ];
    if (count($rows) >= $batch) {
        batchInsert($pdo, 'reviews', ['product_id','user_id','rating','title','body','is_approved'], $rows);
        echo '.';
        $rows = [];
    }
}
if ($rows) batchInsert($pdo, 'reviews', ['product_id','user_id','rating','title','body','is_approved'], $rows);
echo " " . number_format(microtime(true) - $start, 2) . "s\n";

$elapsed = number_format(microtime(true) - $start, 2);
echo "\n========================================\n";
echo "  Seed complete! ({$elapsed}s)\n";
echo "  Tables: users(100K), products(50K), orders(100K),\n";
echo "          order_items(300K), logs(200K), reviews(300K),\n";
echo "          categories(50)\n";
echo "  Total: ~1,050,000 records (with large text fields)\n";
echo "========================================\n";
