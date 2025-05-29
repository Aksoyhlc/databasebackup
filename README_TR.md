# Veritabanı Yedekleme

MySQL ve MariaDB veritabanları için gelişmiş yedekleme sistemi. Bu paket, `mysqldump` kullanmadanveritabanlarını kolayca yedeklemenize, listeleyebilmenize, indirebilmenize, silebilmenize ve FTP'ye yüklemenize olanak tanır.

## Neden Bu Paketi Kullanmalısınız?

### 🚀 Güvenli ve Güvenilir
- `mysqldump` kullanmak için gerekli olan `shell_exec`/`exec` gibi tehlikeli sistem komutlarına ihtiyaç duymaz
- Tamamen PHP tabanlı, güvenli ve kontrollü bir yedekleme süreci
- Hosting sağlayıcılarının kısıtlamalarına takılmaz

### 💡 Kullanım Alanları
- Paylaşımlı hosting ortamları (mysqldump erişimi olmayan)
- VPS ve bulut sunucular
- Otomatik yedekleme sistemleri
- Web tabanlı yedekleme arayüzleri
- Çoklu veritabanı yönetimi

### ⭐ Öne Çıkan Özellikler
- `mysqldump` kullanmadan yedekleme
- Shell komutlarına gerek kalmadan tam veritabanı yedekleme
- Seçici yedekleme (belirli tabloları hariç tutma veya sadece yapı/veri)
- Otomatik sıkıştırma ve FTP yükleme
- Detaylı hata ayıklama ve günlük kaydı
- İlerleme takibi ve durum bildirimleri

### 🔒 Güvenlik Avantajları
- Sistem komutlarını çalıştırma riski yok
- Hosting sağlayıcılarının güvenlik kısıtlamalarına uyumlu
- Kontrollü ve izole edilmiş yedekleme süreci
- Güvenli FTP bağlantıları (SSL/TLS desteği)

## Özellikler

- Tam veritabanı yedeği (tablolar, görünümler, tetikleyiciler ve saklı yordamlar dahil)
- Seçili tabloların hariç tutulması veya sadece yapı/veri yedekleme
- Yedeklerin sıkıştırılması (gzip)
- Eski yedeklerin otomatik temizlenmesi (sayı ve yaşa göre)
- FTP/FTPS'e otomatik veya manuel yükleme
- İlerleme takibi
- Kapsamlı günlük kaydı

## Kurulum

```bash
composer require aksoyhlc/databasebackup
```

## Temel Kullanım

```php
<?php

use Aksoyhlc\Databasebackup\DatabaseBackupService;

// Temel yapılandırma
$dbConfig = [
    'host' => 'localhost',      // Veritabanı sunucusu
    'dbname' => 'database',     // Veritabanı adı
    'user' => 'root',           // Kullanıcı adı
    'pass' => 'password',       // Şifre
    'charset' => 'utf8mb4',     // Karakter seti (isteğe bağlı)
    'port' => 3306              // Port (isteğe bağlı)
];

// Yedekleme dizini
$backupPath = __DIR__ . '/backups';

// Yedekleme servisini başlat
$backupService = new DatabaseBackupService($dbConfig, $backupPath);

// Yedek oluştur
$result = $backupService->createBackup();

if ($result['success']) {
    echo "Yedekleme başarılı: " . $result['fileName'];
} else {
    echo "Hata: " . $result['message'];
}
```

## Gelişmiş Kullanım

```php
<?php

use Aksoyhlc\Databasebackup\DatabaseBackupService;

$dbConfig = [
    'host' => 'localhost',      // Veritabanı sunucusu
    'dbname' => 'database',     // Veritabanı adı
    'user' => 'root',           // Kullanıcı adı
    'pass' => 'password',       // Şifre
    'charset' => 'utf8mb4',     // Karakter seti
    'port' => 3306              // Port numarası
];

$backupPath = __DIR__ . '/backups';

// Gelişmiş yapılandırma seçenekleri
$options = [
    // ---- Önbellek Ayarları ----
    'cacheTime' => 3600,        // Önbellek süresi (saniye)
    
    // ---- Yedek Dosya Temizleme Ayarları ----
    'maxBackupCount' => 10,     // Maksimum yedek sayısı (fazlası otomatik silinir)
    'maxBackupAgeDays' => 30,   // Maksimum yedek yaşı (gün, daha eski olanlar otomatik silinir)
    
    // ---- İçerik Filtreleme Ayarları ----
    'excludedTables' => [       // Yedekten hariç tutulacak tablolar
        'log_table', 
        'temp_data'
    ],
    'tableModes' => [           // Tablo yedekleme modları
        // 'full': Tablo yapısı ve verileri (varsayılan)
        // 'structure_only': Sadece tablo yapısı
        // 'data_only': Sadece tablo verileri
        'large_table' => 'structure_only',
        'settings' => 'full'
    ],
    
    // ---- Yedek Format Ayarları ----
    'compressOutput' => true,   // Yedek dosyasını sıkıştır? (gzip)
    'removeDefiners' => true,   // SQL DEFINER ifadelerini kaldır?
    
    // ---- İlerleme Takibi ----
    'progressCallback' => function($status, $current, $total) {
        // İlerleme durumu için geri çağırma fonksiyonu
        echo "{$status}: {$current}/{$total}\n";
    },
    
    // ---- FTP Ayarları ----
    'ftpConfig' => [
        'enabled' => true,      // FTP yedekleme aktif mi?
        'host' => 'ftp.example.com',  // FTP sunucu adresi
        'username' => 'ftpuser',      // FTP kullanıcı adı
        'password' => 'ftppass',      // FTP şifresi
        'port' => 21,                 // FTP port numarası
        'path' => '/backups',         // Uzak dizin yolu
        'ssl' => false,               // SSL kullan?
        'passive' => true             // Pasif mod kullan?
    ]
];

$backupService = new DatabaseBackupService($dbConfig, $backupPath, $options);

// Yedek oluştur
$result = $backupService->createBackup();

// Yedekleri listele
$backups = $backupService->listBackups();
foreach ($backups as $backup) {
    echo "Dosya: {$backup['file_name']}, Boyut: {$backup['size']}, Tarih: {$backup['date']}\n";
}

// Bir yedeği indir
$downloadInfo = $backupService->prepareDownload('backup_database_2023-01-01_12-00-00.sql.gz');
if ($downloadInfo['success']) {
    // Dosyayı kullanıcıya sunmak için bilgiler
    $filePath = $downloadInfo['filePath'];
    $fileName = $downloadInfo['fileName'];
    $mimeType = $downloadInfo['mimeType'];
}

// Bir yedeği sil
$deleteResult = $backupService->deleteBackup('backup_database_2023-01-01_12-00-00.sql.gz');

// Bir yedeği FTP'ye yükle
$uploadResult = $backupService->uploadBackupToFtp('backup_database_2023-01-01_12-00-00.sql.gz');

// Eski yedekleri temizle
$backupService->cleanOldBackups();
```

## Günlük Mesajları

```php
// Farklı günlük seviyelerinde mesaj kaydet
$backupService->logMessage("Özel bilgi mesajı", "INFO");
$backupService->error("Bir hata oluştu");
$backupService->info("Bilgi mesajı");
$backupService->debug("Detaylı hata ayıklama bilgisi");
```

## Bağlantı Testleri

```php
// Veritabanı bağlantısını test et
if ($backupService->testDatabaseConnection()) {
    echo "Veritabanı bağlantısı başarılı";
}

// FTP bağlantısını test et
if ($backupService->testFtpConnection()) {
    echo "FTP bağlantısı başarılı";
}

// Veritabanı sürümünü al
echo "Veritabanı sürümü: " . $backupService->getDatabaseVersion();
```

## Yapılandırma Parametreleri

### Veritabanı Yapılandırması (`$dbConfig`)
| Parametre | Açıklama | Varsayılan |
|-----------|-------------|---------|
| `host` | Veritabanı sunucu adresi | `'localhost'` |
| `dbname` | Veritabanı adı | - |
| `user` | Veritabanı kullanıcı adı | - |
| `pass` | Veritabanı şifresi | - |
| `charset` | Veritabanı karakter seti | `'utf8mb4'` |
| `port` | Veritabanı sunucu port numarası | `3306` |

### Genel Yapılandırma Seçenekleri (`$options`)

#### Önbellek Ayarları
| Parametre | Açıklama | Varsayılan |
|-----------|-------------|---------|
| `cacheTime` | Önbellek süresi (saniye) | `3600` |

#### Yedek Dosya Temizleme Ayarları
| Parametre | Açıklama | Varsayılan |
|-----------|-------------|---------|
| `maxBackupCount` | Tutulacak maksimum yedek sayısı | `10` |
| `maxBackupAgeDays` | Tutulacak maksimum yedek yaşı (gün) | `365` |

#### İçerik Filtreleme Ayarları
| Parametre | Açıklama | Varsayılan |
|-----------|-------------|---------|
| `excludedTables` | Yedekten hariç tutulacak tablo adları dizisi | `[]` |
| `tableModes` | Tablo yedekleme modları (`'full'`, `'structure_only'`, `'data_only'`) | `[]` |

#### Yedek Format Ayarları
| Parametre | Açıklama | Varsayılan |
|-----------|-------------|---------|
| `compressOutput` | Yedek dosyasını sıkıştır? (gzip) | `false` |
| `removeDefiners` | SQL DEFINER ifadelerini kaldır? | `true` |

#### İlerleme Takibi
| Parametre | Açıklama | Varsayılan |
|-----------|-------------|---------|
| `progressCallback` | İlerleme durumu bildirim fonksiyonu `function($status, $current, $total)` | `null` |

#### FTP Yapılandırması (`ftpConfig`)
| Parametre | Açıklama | Varsayılan |
|-----------|-------------|---------|
| `enabled` | FTP yedekleme aktif mi? | `false` |
| `host` | FTP sunucu adresi | `''` |
| `username` | FTP kullanıcı adı | `''` |
| `password` | FTP şifresi | `''` |
| `port` | FTP port numarası | `21` |
| `path` | Uzak dizin yolu | `'/'` |
| `ssl` | SSL kullan? | `false` |
| `passive` | Pasif mod kullan? | `true` |

## Gereksinimler

- PHP 7.4 veya üzeri
- PDO PHP Eklentisi
- MySQL veya MariaDB veritabanı
- FTP işlemleri için: FTP PHP Eklentisi
- Sıkıştırma için: Zlib PHP Eklentisi
