# Veritabanı Yedekleme

MySQL ve MariaDB veritabanları için yedekleme sistemi. `mysqldump` kullanmadan yedek alın, listeleyin, indirin, silin ve FTP'ye yükleyin.

## Neden Bu Paket

### Güvenli
- `shell_exec`/`exec` gerekmez (mysqldump bunları kullanır)
- PHP tabanlı, sistem komutu çalıştırmaz
- Hosting kısıtlamalarına takılmaz

### Kullanım Alanları
- Paylaşımlı hosting (mysqldump erişimi olmayan)
- VPS ve bulut sunucular
- Otomatik yedekleme sistemleri
- Web tabanlı yedekleme arayüzleri

### Öne Çıkan Özellikler
- mysqldump bağımlılığı yok
- Shell komutu gerektirmez
- Tablo hariç tutma, sadece yapı veya sadece veri seçenekleri
- Sıkıştırma ve FTP yükleme
- Hata ayıklama ve ilerleme takibi

### Güvenlik
- Sistem komutu çalıştırma riski yok
- Hosting güvenlik kurallarıyla uyumlu
- FTP için SSL/TLS desteği

## Özellikler

- Tam veritabanı yedeği (tablolar, view, trigger, stored procedure)
- Tablo hariç tutma, sadece yapı veya sadece veri
- Gzip sıkıştırma
- Sayı ve yaşa göre otomatik temizlik
- FTP/FTPS yükleme (otomatik veya manuel)
- İlerleme geri çağrısı
- Günlük kaydı

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

## Performans

DatabaseBackup, **stream mimarisi** ve **unbuffered sorgular** sayesinde her boyuttaki veritabanını minimum kaynak kullanımıyla yedekler. `mysqldump` bağımlılığı yoktur.

### Benchmark (1.050.000 kayıt, 7 tablo)

| Mod | Dosya Boyutu | Süre |
|-----|-------------|------|
| Sıkıştırmasız | 1.33 GB | 17.0 s |
| Gzip sıkıştırmalı | 314 MB | 57.5 s |

### Benchmark (Employees DB: 3.920.015 kayıt, 6 tablo + 2 view)

| Mod | Dosya Boyutu | Süre |
|-----|-------------|------|
| Gzip sıkıştırmalı | 33.7 MB | 46.6 s |

### vs `mysqldump` (1.050.000 kayıt)

| Araç | Dosya Boyutu | Süre |
|------|-------------|------|
| **DatabaseBackup** (stream) | 1.33 GB | 17.0 s |
| `mysqldump` | 1.30 GB | 11.8 s |
| **DatabaseBackup** (gzip) | 314 MB | 57.5 s |
| `mysqldump` + `gzip` (pipe) | 314 MB | 31.1 s |

### Performans Özellikleri

- **Stream yazma**: SQL doğrudan diske yazılır, bellekte birikmez.
- **Unbuffered sorgular**: Satırlar tek tek çekilir, sonuç kümesi tamponlanmaz.
- **Ayarlanabilir batch boyutu**: `batchSize` ile INSERT performansı ayarlanabilir.

## Özel Karakterler

UTF-8 karakterler, emoji, JSON, BLOB, backslash ve çok satırlı metin yedekleme ve geri yükleme sırasında korunur.

Test edilen: `😀🔥🎉💯✅`, `👨‍👩‍👧‍👦`, `JSON`, `BLOB`, çok satırlı metin, `O'Brien`, `C:\Users\path`, null değerler.

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
| `batchSize` | INSERT başına satır sayısı | `100` |

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
