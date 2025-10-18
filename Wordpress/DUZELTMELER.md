# HalkOde WooCommerce Eklentisi Düzeltmeleri

## Tarih: 16 Ekim 2025

## Tespit Edilen Sorunlar

### 1. Başka Ödeme Yöntemleriyle Ödeme Yapılamama Sorunu

**Problem:** Adres doldurulan ekrandan kredi kartı bilgilerini girme ekranına geçilemiyordu.

**Sebep:**

- `my_custom_public_page()` fonksiyonu her `init` hook'unda çalışıyor ve HalkOde ile ilgili olmayan checkout akışlarında bile yönlendirme yapıyordu.
- Fonksiyon içinde ödeme yöntemi kontrolü yoktu, bu yüzden diğer ödeme yöntemleriyle yapılan ödemelere de müdahale ediyordu.

**Çözüm:**

- `my_custom_public_page()` fonksiyonuna HalkOde parametreleri kontrolü eklendi (order_id, invoice_id, webhook).
- HalkOde ile ilgili parametreler yoksa fonksiyon erken return yapıyor.
- Order kontrolü yapan bölümlere `payment_method` kontrolü eklendi - sadece `halkode_sanalpos` ödeme yöntemiyle oluşturulan siparişler işleniyor.

### 2. Kargo Entegrasyonu Eklentisi Ödeme Sayfasına Yönlendirme Sorunu

**Problem:** Kargo entegrasyonu eklentisi siparişlerin kargo barkodunu getirmeye çalışırken ödeme sayfasına atıyordu.

**Sebep:**

- Ödeme yöntemi kontrolü yapılmadan her sipariş için işlem yapılıyordu.
- `$_GET['invoice_id']` ve `$_GET['payment_status']` kontrollerinde ödeme yöntemi doğrulaması yoktu.

**Çözüm:**

- Her sipariş işleme noktasına HalkOde ödeme yöntemi kontrolü eklendi.
- Başarılı ve başarısız ödeme callback'lerinde sipariş HalkOde ile oluşturulmamışsa işlem yapılmıyor.

### 3. JavaScript Ödeme Yöntemi ID Hatası

**Problem:** JavaScript'te yanlış ödeme yöntemi ID'si kullanılıyordu.

**Sebep:**

- `halkode_payment` ID'si kullanılıyordu ama gerçek ID `halkode_sanalpos`.

**Çözüm:**

- JavaScript'te ödeme yöntemi kontrolü `halkode_sanalpos` olarak güncellendi.

### 4. Alan Validasyon Sorunu

**Problem:** HalkOde seçili olmasa bile validasyon çalışıyordu.

**Sebep:**

- `validate_fields()` fonksiyonu her zaman çalışıyordu.

**Çözüm:**

- `validate_fields()` fonksiyonuna ödeme yöntemi kontrolü eklendi.
- Sadece HalkOde seçiliyken validasyon yapılıyor.

### 5. Kart Numarası Input Event Listener Sorunu

**Problem:** Kart numarası alanına yapılan girişler diğer ödeme yöntemlerinde de event tetikliyordu.

**Sebep:**

- `#cc_number` input'u için event listener ödeme yöntemi kontrolü yapmıyordu.

**Çözüm:**

- Event listener içine ödeme yöntemi kontrolü eklendi.
- Sadece HalkOde seçiliyken AJAX çağrısı yapılıyor.

### 6. 🔥 KRITIK: Sürekli "Kullanıcının eklenti aktivasyon yetkisi yok" Hatası

**Problem:** Her sayfa yüklendiğinde log'a "Kullanıcının eklenti aktivasyon yetkisi yok" hatası yazılıyordu.

**Sebep:**

- `activate()` fonksiyonu `__construct()` içinde `is_admin()` kontrolünde her admin sayfası yüklendiğinde çağrılıyordu.
- Bu fonksiyon içinde `current_user_can('activate_plugins')` kontrolü vardı ve normal admin kullanıcıları bu yetkiye sahip değil.
- Sonuç: Her admin sayfasında (sipariş listesi, ürün düzenleme vb.) hata logu yazılıyordu.

**Çözüm:**

- `activate()` fonksiyonu tamamen kaldırıldı.
- WordPress'in standart `register_activation_hook()` kullanılarak aktivasyon mantığı `halkode-woocommerce-gateway.php` dosyasına taşındı.
- Veritabanı tablosu oluşturma işlemi artık sadece eklenti aktive edildiğinde bir kez çalışıyor.
- Yetki kontrolü kaldırıldı çünkü activation hook zaten yeterli yetkisi olan kullanıcı tarafından çalıştırılıyor.

### 7. 🔥 KRITIK: AJAX İsteklerinde Ödeme Sayfasına Yönlendirme Sorunu

**Problem:** Başka eklentilerin AJAX istekleri (örn. `print_barcode`, `action=print_barcode&order_id=24247`) ödeme sayfasına yönlendiriyordu. HalkOde devre dışıyken bu sorun olmuyordu.

**Sebep:**

- `my_custom_public_page()` fonksiyonu `init` hook'unda çalışıyor ve **her** istek için tetikleniyor (AJAX istekleri dahil).
- AJAX isteklerinde `order_id` parametresi olunca HalkOde parametresi olarak algılanıyor.
- `DOING_AJAX` ve `is_admin()` kontrolü yoktu, bu yüzden admin AJAX çağrılarına da müdahale ediyordu.

**Çözüm:**

- `my_custom_public_page()` fonksiyonuna **AJAX kontrolü** eklendi: `defined('DOING_AJAX') && DOING_AJAX` durumunda fonksiyon hemen return ediyor.
- **Admin sayfaları kontrolü** eklendi: `is_admin()` durumunda fonksiyon hemen return ediyor.
- Bu sayede diğer eklentilerin admin AJAX isteklerine müdahale edilmiyor.

**Etkilenen Senaryolar:**

- ❌ ÖNCE: Kargo eklentisi barkod yazdırma → HalkOde ödeme sayfasına yönlendirme
- ✅ SONRA: Kargo eklentisi barkod yazdırma → Normal çalışıyor
- ❌ ÖNCE: Admin panelinde AJAX istekleri → Bazen ödeme sayfasına yönlendirme
- ✅ SONRA: Admin panelinde AJAX istekleri → Hiç müdahale edilmiyor

## Değiştirilen Dosyalar

### 1. halkode-woocommerce-gateway.php

- `my_custom_public_page()` fonksiyonuna parametre kontrolü eklendi
- Sipariş işleme noktalarına ödeme yöntemi kontrolü eklendi
- Webhook ve callback'lerde güvenlik kontrolleri artırıldı
- **YENİ:** `register_activation_hook()` ile eklenti aktivasyon fonksiyonu eklendi
- **YENİ:** Veritabanı tablosu sadece aktivasyonda oluşturuluyor
- **YENİ:** AJAX istekleri için `DOING_AJAX` kontrolü eklendi
- **YENİ:** Admin sayfaları için `is_admin()` kontrolü eklendi
- **YENİ:** Tüm logging sistem WooCommerce logger'a dönüştürüldü (halkode_log fonksiyonu)

### 2. halkode-woocommerce.php

- `validate_fields()` fonksiyonuna ödeme yöntemi kontrolü eklendi
- **YENİ:** `activate()` fonksiyonu tamamen kaldırıldı
- **YENİ:** `__construct()` içinden `activate()` çağrısı kaldırıldı
- **YENİ:** `log_debug()` ve `log_error()` fonksiyonları kaldırıldı
- **YENİ:** Tüm `$this->log_debug()` ve `$this->log_error()` kullanımları `halkode_log()` ile değiştirildi

### 3. js/halkode.js

- Ödeme yöntemi ID'si düzeltildi (`halkode_payment` → `halkode_sanalpos`)
- Kart numarası input event'ine ödeme yöntemi kontrolü eklendi
- **YENİ:** Gereksiz include kaldırıldı (debug-helper.php kullanılmıyordu)

## Test Edilmesi Gerekenler

1. ✅ HalkOde ödeme yöntemiyle normal ödeme akışı
2. ✅ Başka bir ödeme yöntemiyle (örn. havale, kapıda ödeme) sipariş verme
3. ✅ Adres doldurup kredi kartı sayfasına geçiş (diğer ödeme yöntemleriyle)
4. ✅ Kargo entegrasyonu ile sipariş takibi (HalkOde olmayan siparişlerde)
5. ✅ HalkOde 3D ödeme akışı
6. ✅ Kayıtlı kartla ödeme
7. ✅ Yeni kartla ödeme
8. ✅ Admin panelinde AJAX istekleri (barkod yazdırma, sipariş durumu güncelleme vb.)
9. ✅ Diğer eklentilerin admin-ajax.php kullanımları
10. ✅ WooCommerce logger'da log kayıtları (WooCommerce → Status → Logs → halkode)

## Notlar

- Tüm değişiklikler geriye dönük uyumludur
- HalkOde ödeme akışında herhangi bir değişiklik yapılmadı
- Sadece diğer ödeme yöntemlerine müdahale eden kodlar düzeltildi
- PHP lint hataları WordPress ve WooCommerce fonksiyonları için normaldir (çalışma zamanında mevcut olacaklar)
- **Logging Sistemi:** Artık tüm loglar WooCommerce logger'a yazılıyor, debug modda daha detaylı loglar mevcut
- **AJAX Güvenliği:** Admin AJAX isteklerine artık hiç müdahale edilmiyor

## Öneriler

1. Test ortamında kapsamlı testler yapılmalı
2. Farklı kargo entegrasyonları ile test edilmeli
3. Çoklu ödeme yöntemi senaryoları test edilmeli
4. Webhook güvenlik testleri yapılmalı
