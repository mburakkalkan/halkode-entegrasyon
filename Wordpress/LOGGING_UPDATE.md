# HalkOde Loglama Sistemi Güncelleme

## Tarih: 16 Ekim 2025

## Yapılan Değişiklikler

### ✅ Tüm `error_log()` Çağrıları WooCommerce Logger'a Dönüştürüldü

#### 1. **Yeni Global Logger Fonksiyonu Eklendi** (`halkode-woocommerce-gateway.php`)

```php
function halkode_log($message, $level = 'info', $context = array())
```

**Parametreler:**

- `$message`: Log mesajı
- `$level`: Log seviyesi ('info', 'warning', 'error', 'debug')
- `$context`: Ek context bilgisi (array)

**Özellikler:**

- WooCommerce logger'ı tercih ediyor
- WooCommerce yoksa error_log()'a fallback yapıyor
- JSON formatında context desteği
- Otomatik 'halkode' source tag'i

#### 2. **halkode-woocommerce.php - log_debug() ve log_error() Güncellendi**

**Öncesi:**

- Önce `error_log()` çağrılıyordu
- Sonra WooCommerce logger çağrılıyordu (çift loglama)
- `print_r()` ile data formatlanıyordu

**Sonrası:**

- Önce WooCommerce logger çağrılıyor
- WooCommerce yoksa error_log'a fallback
- `wp_json_encode()` ile JSON formatı
- Context array olarak ek data gönderiliyor

#### 3. **Log Seviyeleri Tanımlandı**

| Seviye    | Kullanım                                                          |
| --------- | ----------------------------------------------------------------- |
| `debug`   | Detaylı debug bilgisi (getCurl istekleri, fonksiyon çağrıları)    |
| `info`    | Bilgilendirme mesajları (başarılı işlemler, durum değişiklikleri) |
| `warning` | Uyarı mesajları (beklenen ancak önemsiz hatalar)                  |
| `error`   | Hata mesajları (işlemin başarısız olması)                         |

### 📁 Güncellenen Dosyalar

#### `halkode-woocommerce-gateway.php`

- ✅ Yeni `halkode_log()` fonksiyonu eklendi
- ✅ Tüm `error_log()` çağrıları `halkode_log()`'a dönüştürüldü
- ✅ Log seviyeler eklendi
- ✅ Hassas bilgiler (kart token) loglardan kısaltıldı

**Örnekler:**

```php
// Önce
error_log('[HalkOde] Plugin aktivasyonu başlıyor...');

// Sonra
halkode_log('Plugin aktivasyonu başlıyor...', 'info');

// Önce
error_log('[HalkOde ERROR] Kart silme hatası: ' . $wpdb->last_error);

// Sonra
halkode_log('Kart silme hatası: ' . $wpdb->last_error, 'error');

// Context ile
halkode_log('Kullanıcının kartı bulunamadı', 'error', array(
    'user_id' => $current_user_id,
    'card_token' => substr($card_token, 0, 10) . '...'
));
```

#### `halkode-woocommerce.php`

- ✅ `log_debug()` ve `log_error()` fonksiyonları güncellendi
- ✅ Çift loglama kaldırıldı (önce error_log, sonra WC logger)
- ✅ JSON formatı kullanılıyor (`wp_json_encode`)
- ✅ Context array desteği eklendi
- ✅ Tüm catch bloklarındaki `error_log()` çağrıları düzeltildi

### 📊 Log Çıktı Formatı

#### WooCommerce Logger ile:

```
2025-10-16 12:34:56 INFO [HalkOde] Plugin başarıyla yüklendi {"source":"halkode"}
2025-10-16 12:34:57 ERROR [HalkOde ERROR] Kullanıcının kartı bulunamadı {"source":"halkode","data":{"user_id":123,"card_token":"abc123..."}}
2025-10-16 12:34:58 DEBUG [HalkOde] getCurl isteği: https://api.example.com {"source":"halkode"}
```

#### Fallback (error_log) ile:

```
[HalkOde INFO] Plugin başarıyla yüklendi
[HalkOde ERROR] Kullanıcının kartı bulunamadı | {"user_id":123,"card_token":"abc123..."}
```

### 🎯 Avantajlar

#### 1. **Merkezi Log Yönetimi**

- Tüm loglar WooCommerce → Status → Logs altında
- `halkode` source ile filtrelenebilir
- Tarih/saat/seviye ile arama

#### 2. **Daha İyi Yapılandırma**

- JSON formatında structured logging
- Context bilgisi ile zenginleştirilmiş loglar
- Log seviyeleri ile önceliklendirme

#### 3. **Performans**

- Çift loglama kaldırıldı
- WP_DEBUG kapalıyken debug logları yazılmıyor
- Efektif error tracking

#### 4. **Güvenlik**

- Hassas bilgiler (kart token) kısaltılıyor
- Structured data ile injection koruması
- JSON escape ile güvenli loglama

### 📍 WooCommerce Logger Erişimi

#### Admin Panelden:

```
WooCommerce → Status → Logs → halkode-*.log
```

#### PHP ile:

```php
$logger = wc_get_logger();
$logs = $logger->get_logs('halkode');
```

### 🔧 Gelecek İyileştirmeler

- [ ] Log retention policy (eski logları temizleme)
- [ ] Log level ayarlarını admin panelinden yapılabilir hale getirme
- [ ] Email notification kritik hatalar için
- [ ] Performance monitoring (API yanıt süreleri)
- [ ] Log dosya boyutu limiti

### ⚠️ Notlar

1. **Fallback Mekanizması**: WooCommerce logger mevcut değilse otomatik olarak `error_log()`'a düşer
2. **Debug Mode**: `log_debug()` sadece `WP_DEBUG` aktifken çalışır
3. **Context Limiti**: Çok büyük context array'leri JSON encoding'de problem yaratabilir
4. **Backward Compatibility**: Eski log formatı ile uyumlu değil, yeni formatı kullanmalısınız

## Test Edilmesi Gerekenler

✅ WooCommerce logger'a yazılma
✅ Log seviyeleri doğru çalışıyor mu
✅ Context bilgisi düzgün gönderiliyor mu
✅ WooCommerce yokken fallback çalışıyor mu
✅ Debug mode açık/kapalıyken davranış
✅ Hassas bilgilerin gizlenmesi
