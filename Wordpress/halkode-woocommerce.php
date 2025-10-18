<?php

if (!defined('ABSPATH')) {
    exit;
}

#[\AllowDynamicProperties]
class halkode_sanalpos extends WC_Payment_Gateway
{

    protected $is_3d = 0;
    public $headers = array(
        'Accept: application/json',
        'Content-Type: application/json'
    );

    function __construct()
    {
        try {
            // global ID
            $this->id = "halkode_sanalpos";
            // Show Title
            $this->method_title = __("Halk Ödeme Hizmetleri", 'halkode');
            // Show Description
            $this->method_description = __("Woocommerce için Halk Ödeme Hizmetleri Entegrasyonu", 'halkode');
            // vertical tab title
            $this->title = __("Halk Ödeme Hizmetleri Pos", 'halkode');
            $this->icon = null;
            $this->has_fields = true;

            // Initialize debug mode
            $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;

            // setting defines
            $this->init_form_fields();
            // load time variable setting
            $this->init_settings();

            // Turn these settings into variables we can use
            foreach ($this->settings as $setting_key => $value) {
                $this->$setting_key = $value;
            }

            // further check of SSL if you want
            add_action('admin_notices', [$this, 'do_ssl_check']);
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            // Save settings
            if (is_admin()) {
                // activate() fonksiyonunu her seferinde çağırma, sadece ayarlar kaydedilirken çalışsın
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            }

            halkode_log('HalkOde ödeme gateway başlatıldı', 'debug');
        } catch (Exception $e) {
            halkode_log('Constructor hatası: ' . $e->getMessage(), 'error', array(
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));

            // Admin bildirim ekle
            add_action('admin_notices', function () use ($e) {
                echo '<div class="notice notice-error"><p>HalkOde Ödeme Gateway Hatası: ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    } // Here is the  End __construct()

    // administration fields for specific Gateway

    public function init_form_fields()
    {

        $installments = $this->get_admin_installment();
        $this->form_fields = [

            'merchant_key' => [
                'title' => __('Merchant Key', 'halkode'),
                'type' => 'text',
                'desc_tip' => __('Merchant Key', 'halkode'),
            ],
            'app_key' => [
                'title' => __('App Key', 'halkode'),
                'type' => 'text',
                'desc_tip' => __('App key', 'halkode'),
            ],
            'app_secret' => [
                'title' => __('App Secret', 'halkode'),
                'type' => 'text',
                'desc_tip' => __('App Secret', 'halkode'),
            ],
            'merchant_id' => [
                'title' => __('Merchant ID', 'halkode'),
                'type' => 'text',
                'desc_tip' => __('Merchant ID', 'halkode'),
            ],
            'sale_webhook_key' => [
                'title' => __('Satış Webhook Anahtarı', 'halkode'),
                'type' => 'text',
                'desc_tip' => __('Satış Webhook Anahtarı', 'halkode'),
                'description' => get_site_url() . '?webhook=1',
            ],
            'recurring_sale_webhook_key' => [
                'title' => __('Yinelenen Satış Webhook Anahtarı', 'halkode'),
                'type' => 'text',
                'desc_tip' => __('Yinelenen Satış Webhook Anahtarı', 'halkode'),
                'description' => get_site_url() . '?webhook=1&recurring=1',
            ],
            'environment' => [
                'title' => __('Test Modu', 'halkode'),
                'label' => __('Etkinleştir', 'halkode'),
                'type' => 'checkbox',
                'description' => __('Test ortamında deneme yapmak için etkinleştirin', 'halkode'),
                'default' => 'no',
            ],
            'transaction_type' => array(
                'title' => __('Provizyon Türü', 'halkode'),
                'type' => 'select',
                'default' => 'Auth',
                'options' => array('Auth' => __('Auth', 'halkode'), 'PreAuth' => __('PreAuth', 'halkode'))
            ),
            'installments' => array(
                'title' => 'Taksit Sayısı',
                'type' => 'multiselect',
                'options' => $installments,
                'description' => __('Shift ile çoklu seçim yapabilirsiniz.', 'halkode'),
                'custom_attributes' => array(
                    'data-placeholder' => __('Taksit Seçiniz', 'halkode'),
                ),
            ),
            'enabled' => [
                'title' => __('Ödeme Yöntemi <br> Etkin/Pasif', 'halkode'),
                'label' => __('Bu yöntemi etkinleştir', 'halkode'),
                'type' => 'checkbox',
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Başlık', 'halkode'),
                'type' => 'text',
                'desc_tip' => __('Başlık', 'halkode'),
                'default' => __('Halk Ödeme Hizmetleri', 'halkode'),
            ],
            'description' => [
                'title' => __('Açıklama', 'halkode'),
                'type' => 'textarea',
                'desc_tip' => __('Açıklama', 'halkode'),
                'default' => __('Kredi kartıyla ödeme yap', 'halkode'),
                'css' => 'max-width:450px;',
            ],


        ];
    }

    public function getLocalizationContent($content, $language)
    {
        $language = strtoupper($language);

        $lang = [
            get_option('woocommerce_currency') => [
                'card_holder_name' => 'Kart Sahibi',

                'card_number' => 'Kart Numarası',

                'expiry' => 'Son Kullanma Tarihi',

                'cvv' => 'Güvenlik Numarası',

                'single_installment' => 'Peşin',

                'installment' => 'Taksit',

                '3D_payment' => '3D Ödeme',
            ],

            'USD' => [
                'card_holder_name' => 'Card Holder Name',

                'card_number' => 'Card Number',

                'expiry' => 'Expiry',

                'cvv' => 'CVV',

                'single_installment' => 'Single Installment',

                'installment' => 'Installment',

                '3D_payment' => '3D Payment',
            ],

            'EUR' => [
                'card_holder_name' => 'Card Holder Name',

                'card_number' => 'Card Number',

                'expiry' => 'Expiry',

                'cvv' => 'CVV',

                'single_installment' => 'Single Installment',

                'installment' => 'Installment',

                '3D_payment' => '3D Payment',
            ],
        ];

        if (!isset($lang[$language])) {
            $language = 'USD';
        }

        if (isset($lang[$language][$content])) {
            $localizeContent = $lang[$language][$content];
        } else {
            $localizeContent = $content;
        }

        return $localizeContent;
    }

    public function payment_fields()
    {
        try {
            halkode_log('Payment fields oluşturuluyor', 'debug');

            if ($description = $this->get_description()) {
                echo wpautop(wptexturize($description));
            }

            $currency = get_option('woocommerce_currency');

            // API bilgileri kontrolü
            if (empty($this->get_option('app_key')) || empty($this->get_option('app_secret'))) {
                halkode_log('API bilgileri eksik', 'error');
                echo '<div class="woocommerce-error">Ödeme gateway ayarları eksik. Lütfen yönetici ile iletişime geçin.</div>';
                return;
            }

            $post = [
                'app_id' => $this->get_option('app_key'),
                'app_secret' => $this->get_option('app_secret'),
            ];

            $environment = $this->environment == "yes" ? 'TRUE' : 'FALSE';
            $environment_url = "FALSE" == $environment ? 'https://app.halkode.com.tr/ccpayment/api/token' : 'https://testapp.halkode.com.tr/ccpayment/api/token';

            $result = $this->curl($environment_url, 'POST', $post);

            if (is_wp_error($result)) {
                halkode_log('Token alma hatası: ' . $result->get_error_message(), 'error');
                echo '<div class="woocommerce-error">Ödeme sistemi geçici olarak kullanılamıyor. Lütfen daha sonra tekrar deneyin.</div>';
                return;
            }

            if (!$result || !isset($result->status_code)) {
                halkode_log('API yanıtı geçersiz', 'error', array('response' => $result));
                echo '<div class="woocommerce-error">Ödeme sistemi yanıt vermiyor. Lütfen daha sonra tekrar deneyin.</div>';
                return;
            }
        } catch (Exception $e) {
            halkode_log('Payment fields hatası: ' . $e->getMessage(), 'error', array(
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
            echo '<div class="woocommerce-error">Ödeme formu yüklenirken hata oluştu.</div>';
            return;
        }


        if ($this->is_3d != 4 && $this->is_3d != 8) {
            echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
        }

        if ($result->status_code == 100) {


            $this->is_3d = $result->data->is_3d;

            echo "<input type='hidden' name='halkode_token' class='halkode_token' id='halkode_token' value='" . $result->data->token . "'/>";

            if (!empty(WC()->cart->get_cart())) {
                foreach (WC()->cart->get_cart() as $cart_item) {
                    $cart_product_id = $cart_item['product_id'];

                    $is_recurring_cart = get_post_meta($cart_product_id, "_recurring", true);

                    if ($is_recurring_cart == 'yes') {
                        $payment_duration = get_post_meta($cart_product_id, "payment_duration", true);

                        $payment_cycle = get_post_meta($cart_product_id, "payment_cycle", true);

                        $payment_interval = get_post_meta($cart_product_id, "payment_interval", true);

                        if (!empty($payment_duration) && !empty($payment_cycle) && !empty($payment_interval)) { ?>

                            <input class="recurring_checkbox" name="recurring_options[recurring_check]"
                                type="hidden" value="yes">

                            <input type="hidden" name="recurring_options[payment_duration]" class="payment_duration"
                                value="<?php echo $payment_duration; ?>" />

                            <input type="hidden" name="recurring_options[payment_cycle]" class="payment_cycle"
                                value="<?php echo $payment_cycle; ?>" />

                            <input type="hidden" name="recurring_options[payment_interval]" class="payment_interval"
                                value="<?php echo $payment_interval; ?>" />

            <?php }
                    }
                }
            }
        } else {
            echo "<input type='hidden' name='halkode_token' class='halkode_token' id='halkode_token' value=''/>";
        }

        echo "<input type='hidden' name='halkode_3d' class='halkode_3d' id='halkode_3d' value='" . $this->is_3d . "'/>";

        if ($this->is_3d != 4 && $this->is_3d != 8) {
            // Add this action hook if you want your custom payment gateway to support it

            do_action('woocommerce_credit_card_form_start', $this->id);
            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            ?>
            <div class="payment-form">
                <div class="form-row form-row-wide">


                    <input id="cc_holder_name" name="cc_holder_name" class="input-text cc_holder_name alpha-only"
                        type="text" autocomplete="off" placeholder="<?php echo $this->getLocalizationContent('card_holder_name', $currency); ?>">


                </div>

                <div class="form-row halkode-card-number-row">

                    <input id="cc_number" class="input-text cc_number" name="cc_number" type="number"
                        oninput="javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength);"
                        maxlength="16" autocomplete="off" placeholder="<?php echo $this->getLocalizationContent('card_number', $currency); ?>">


                    <div class="halkode_spinner_blk"></div>

                </div>

                <div class="form-row card-extra-info">

                    <input type="number" id="halkode_expiry_month" name="expiry_month" class="input-text" maxlength="2"
                        min="1" max="12" placeholder="AA">
                    <input type="number" id="halkode_expiry_year" name="expiry_year" class="input-text" maxlength="4" min="<?php echo date('Y'); ?>" max="<?php echo date('Y') + 10; ?>" placeholder="YYYY">
                    <input id="halkode_cc_cvv" class="input-text cc_cvv" name="cc_cvv" type="password" maxlength="4"
                        autocomplete="off" placeholder="CVV">


                </div>

                <input type="hidden" name="pos_id" class="pos_id" value="" />
                <input type="hidden" name="pos_amount" class="pos_amount" value="" />
                <input type="hidden" name="currency_id" class="currency_id" value="" />
                <input type="hidden" name="campaign_id" class="campaign_id" value="" />
                <input type="hidden" name="currency_code" class="currency_code" value="" />
                <input type="hidden" name="allocation_id" class="allocation_id" value="" />
                <input type="hidden" name="installments_number" class="installments_number" value="" />
                <input type="hidden" name="hash_key" class="hash_key" value="" />
                <div class="clear"></div>

                <?php
                $instllment = $this->get_option('installment_enabled');

                $dis = '';

                if ($instllment == 'yes') {
                    $dis = "style='display:none'";
                }
                ?>

                <p class="installments form-row form-row-wide" id="installments" <?php echo $dis; ?>></p>
                <div class="clear"></div>

            </div>
            <div class="clear"></div>

            <?php if ($this->is_3d == 1) { ?>

                <p class="form-row form-row-wide">

                    <input style="width:auto;" id="pay_via_3d" class="pay_via_3d" name="pay_via_3d" type="checkbox"
                        autocomplete="off"
                        value="yes" checked><strong><?php echo $this->getLocalizationContent('3D_payment', $currency); ?></strong>

                </p>

            <?php } ?>

            <?php
            /*<div class="recurring_block">

                <p class="form-row form-row-wide">

                    <input style="width:auto;" id="recurring_checkbox" class="recurring_checkbox" name="recurring_options[recurring_check]" type="checkbox" autocomplete="off" value="yes"> <strong><?php echo __('Recurring Payment', 'wc-gateway-offline')?></strong>

                </p>

                <div class="recurring_option_fields" style="display:none;">

                    <p class="form-row form-row-wide">

                        <label>No of Payments <span class="required">*</span></label>

                        <input type="number" name="recurring_options[payment_duration]" class="payment_duration" value=""/>

                    </p>

                    <p class="form-row form-row-wide">

                        <label>Order Frequency Cycle <span class="required">*</span></label>

                        <select name="recurring_options[payment_cycle]" class="payment_cycle">

                            <option value="D">Daily</option>

                            <option value="W">Weekly</option>

                            <option value="M">Monthly</option>

                            <option value="Y">Yearly</option>

                        </select>

                    </p>

                    <p class="form-row form-row-wide">

                        <label>Order Frequency Interval <span class="required">*</span></label>

                        <input type="number" name="recurring_options[payment_interval]" class="payment_interval" value=""/>

                    </p>

                </div>

            </div> */
            ?>

<?php
            do_action('woocommerce_credit_card_form_end', $this->id);


            echo '<div class="clear"></div></fieldset>';
        }
    }


    public function payment_scripts()
    {


        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {

            return;
        }
        if ('no' === $this->enabled) {

            return;
        }

        wp_register_script('woocommerce_halkode', plugins_url('js/halkode.js', __FILE__));

        wp_localize_script('woocommerce_halkode', 'halkode_var', array('spinner' => plugins_url('images/spinner.gif', __FILE__)));

        wp_enqueue_script('woocommerce_halkode');

        wp_enqueue_style('woocommerce_halkode_style', plugins_url('css/halkode.css', __FILE__));
    }

    public function curl($url, $method, $array, $header = [])
    {
        try {
            halkode_log('CURL isteği başlıyor', 'debug', array(
                'url' => $url,
                'method' => $method,
                'has_data' => !empty($array)
            ));

            if (!function_exists('curl_init')) {
                throw new Exception('CURL extension is not installed');
            }

            $curl = curl_init();
            if (!$curl) {
                throw new Exception('CURL initialization failed');
            }

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_POSTFIELDS => $array,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'HalkOde WooCommerce Plugin/1.0'
            ));

            $response = curl_exec($curl);
            $curl_error = curl_error($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curl_errno = curl_errno($curl);

            curl_close($curl);

            if ($curl_errno !== 0) {
                throw new Exception("CURL Error ({$curl_errno}): {$curl_error}");
            }

            if ($http_code >= 400) {
                halkode_log('HTTP hata kodu alındı', 'debug', array(
                    'http_code' => $http_code,
                    'response' => $response
                ));
            }

            if ($response === false) {
                throw new Exception('CURL request failed: ' . $curl_error);
            }

            $decoded = json_decode($response);
            if (json_last_error() !== JSON_ERROR_NONE) {
                halkode_log('JSON decode hatası', 'debug', array(
                    'json_error' => json_last_error_msg(),
                    'response' => substr($response, 0, 500)
                ));
                throw new Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            halkode_log('CURL isteği başarılı', 'debug', array('http_code' => $http_code));
            return $decoded;
        } catch (Exception $e) {
            halkode_log('CURL isteğinde hata: ' . $e->getMessage(), 'error', array(
                'url' => $url,
                'method' => $method
            ));

            // WP_Error döndür
            return new WP_Error('curl_error', $e->getMessage());
        }
    }


    // Response handled for payment gateway
    public function process_payment($order_id)
    {

        global $woocommerce;

        global $wp_session;
        $order = new WC_Order($order_id);


        // checking for transaction
        $environment = $this->environment == "yes" ? 'TRUE' : 'FALSE';
        // Decide which URL to post to

        if (isset($_POST['pay_via_3d']) && $_POST['pay_via_3d'] == 'yes') {
            $environment_url = "FALSE" == $environment ? 'https://app.halkode.com.tr/ccpayment/api/paySmart3D' : 'https://testapp.halkode.com.tr/ccpayment/api/paySmart3D';
        } elseif (isset($_POST['halkode_3d']) && $_POST['halkode_3d'] == 2) {
            $environment_url = "FALSE" == $environment ? 'https://app.halkode.com.tr/ccpayment/api/paySmart3D' : 'https://testapp.halkode.com.tr/ccpayment/api/paySmart3D';
        } else {
            $environment_url = "FALSE" == $environment ? 'https://app.halkode.com.tr/ccpayment/api/paySmart2D' : 'https://testapp.halkode.com.tr/ccpayment/api/paySmart2D';
        }


        // This is where the fun stuff begins
        $price = 0;
        /* Login for deduct discount amount */
        $dis_per_product_amount = 0;
        $dis_total_amount = 0;
        if ($order->get_discount_total() > 0) {
            $dis_total_amount = number_format($order->get_discount_total(), 2, ".", "");
            $item_count = count($order->get_items());
            $dis_per_product_amount = $dis_total_amount / $item_count;
        }

        $order_items = $order->get_items(array('line_item', 'fee', 'shipping'));
        foreach (WC()->cart->get_cart() as $cart_item) {
            $cart_product_id = $cart_item['product_id'];
            $is_recurring_cart = get_post_meta($cart_product_id, "_recurring", true);
        }

        foreach ($order_items as $item_id => $order_item) {


            $invoice['items'][] = [
                'name' => $order_item->get_name(),

                'price' => number_format($order_item->get_total(), 2, ".", "") / $order_item->get_quantity(),

                'qty' => $order_item->get_quantity(),

                'description' => '',
            ];

            $price = $price + (number_format(($order_item->get_total() - $dis_per_product_amount), 2, ".", ""));
        }

        if ($order->get_total_tax() > 0) {
            $invoice['items'][] = [
                'name' => 'Tax',

                'price' => number_format($order->get_total_tax(), 2, ".", ""),

                'qty' => 1,

                'description' => '',
            ];

            $price = $price + number_format($order->get_total_tax(), 2, ".", "");
        }

        $invoice['total'] = number_format($price, 2, ".", "");

        //BIlling info Optional

        $invoice['bill_address1'] = isset($_POST['billing_address_1']) ? $_POST['billing_address_1'] : '';

        $invoice['bill_address2'] = isset($_POST['billing_address_2']) ? $_POST['billing_address_2'] : '';

        $invoice['bill_city'] = isset($_POST['billing_city']) ? $_POST['billing_city'] : '';

        $invoice['bill_postcode'] = isset($_POST['billing_postcode']) ? $_POST['billing_postcode'] : '';

        $invoice['bill_state'] = isset($_POST['billing_state']) ? $_POST['billing_state'] : '';

        $invoice['bill_country'] = isset($_POST['billing_country']) ? $_POST['billing_country'] : '';

        $invoice['bill_email'] = isset($_POST['billing_email']) ? $_POST['billing_email'] : '';

        $invoice['bill_phone'] = isset($_POST['billing_phone']) ? $_POST['billing_phone'] : '';


        $return_url = $order->get_checkout_order_received_url();

        $date = explode(' / ', $_POST['halkode_sanalpos-card-expiry']);
        $month = $date[0];
        $year = strlen($date[1]) == 2 ? 20 . $date[1] : $date[1];

        $order = md5(microtime()) . 'WOO' . $order_id;

        $installment = $_POST['installments_number'] >= 1 ? $_POST['installments_number'] : 1;

        $pay_data = [

            'cc_holder_name' => $_POST['cc_holder_name'],
            'cc_no' => str_replace(array(' ', '-'), '', $_POST['cc_number']),
            'cvv' => $_POST['cc_cvv'],
            'expiry_month' => $_POST['expiry_month'],
            'expiry_year' => $_POST['expiry_year'],
            'sale_web_hook_key' => $this->get_option('sale_webhook_key'),
            'currency_code' => get_option('woocommerce_currency'),
            'installments_number' => $installment,
            'invoice_id' => $order,
            'is_3d' => isset($_POST['pay_via_3d']) ? 'yes' : 'no',
            'is_2d_card' => 'no',
            'token' => $_POST['halkode_token'],
            'invoice_description' => $order_id . " ödemesi",
            'transaction_type' => $this->get_option('transaction_type'),
            'total' => number_format(WC()->cart->total, 2, ".", ""),
            'merchant_key' => $this->get_option('merchant_key'),
            'items' => json_encode($invoice['items']),
            'name' => $_POST['billing_first_name'],
            'surname' => $_POST['billing_last_name'],
            'bill_address1' => $_POST['billing_address_1'] ?? '',
            'bill_address2' => $_POST['billing_address_2'] ?? '',
            'bill_city' => $_POST['billing_city'] ?? '',
            'bill_postcode' => $_POST['billing_postcode'] ?? '',
            'bill_state' => $_POST['billing_state'] ?? '',
            'bill_country' => $_POST['billing_country'] ?? '',
            'bill_email' => $_POST['billing_email'] ?? '',
            'bill_phone' => $_POST['billing_phone'] ?? '',
            'hash_key' => $this->generateHashKey(number_format(WC()->cart->total, 2, ".", ""), $installment, get_option('woocommerce_currency'), $this->get_option('merchant_key'), $order, $this->get_option('app_secret')),
            'return_url' => $return_url,
            'cancel_url' => wc_get_checkout_url(),


        ];

        if (isset($_POST['halkode_3d']) && ($_POST['halkode_3d'] == 4 || $_POST['halkode_3d'] == 8)) {
            $environment_url = "FALSE" == $environment ? 'https://app.halkode.com.tr/ccpayment/purchase/link' : 'https://testapp.halkode.com.tr/ccpayment/purchase/link';
            unset($pay_data['cc_holder_name']);
            unset($pay_data['cc_no']);
            unset($pay_data['card_owner']);
            unset($pay_data['expiry_month']);
            unset($pay_data['expiry_year']);
            unset($pay_data['cvv']);
        }

        if (isset($_POST['pay_via_3d'])) {
        } elseif (isset($_POST['halkode_3d']) && ($_POST['halkode_3d'] == 2)) {
        } else {
            unset($pay_data['items']);
            $pay_data['items'] = $invoice['items'];
        }


        if ($is_recurring_cart == 'yes') {
            $pay_data['recurring_web_hook_key'] = $this->get_option('recurring_sale_webhook_key');
            $pay_data['order_type'] = "1";
            $pay_data['recurring_payment_number'] = get_post_meta($cart_product_id, 'payment_duration', true);
            $pay_data['recurring_payment_cycle'] = get_post_meta($cart_product_id, 'payment_cycle', true);
            $pay_data['recurring_payment_interval'] = get_post_meta($cart_product_id, 'payment_interval', true);
        }


        $form = "<form id='halkode-form' action='" . $environment_url . "' method='POST'>";

        foreach ($pay_data as $key => $item) {

            $form .= "<input type='hidden' name='{$key}' value='" . $item . "'>";
        }


        //$form .= '<form>';
        $form .= '<form><script>document.getElementById("halkode-form").submit()</script>';
        //
        if (isset($_POST['pay_via_3d'])) {
            update_post_meta($order_id, 'halkode_payment_form', base64_encode(serialize($form)));
        } elseif (isset($_POST['halkode_3d']) && ($_POST['halkode_3d'] == 2)) {
            update_post_meta($order_id, 'halkode_payment_form', base64_encode(serialize($form)));
        } elseif (isset($_POST['halkode_3d']) && ($_POST['halkode_3d'] == 4 || $_POST['halkode_3d'] == 8)) {
            $pay_data['purchase'] = 'yes';
            unset($pay_data['is_2d_card']);

            update_post_meta($order_id, 'halkode_payment_form', base64_encode(serialize($pay_data)));
        } else {

            update_post_meta($order_id, 'halkode_payment_form', base64_encode(serialize($pay_data)));
        }

        return array(

            'result' => 'success',

            'redirect' => get_site_url() . '/?order_id=' . $order_id

        );
    }

    function get_admin_installment()
    {
        // Debug log başlangıcı
        halkode_log('get_admin_installment fonksiyonu başladı', 'debug');

        $inst = [];

        try {
            $post = [
                'app_id' => $this->get_option('app_key'),
                'app_secret' => $this->get_option('app_secret'),
            ];

            $environment = $this->get_option('environment') == "yes" ? 'TRUE' : 'FALSE';
            $environment_url = "FALSE" == $environment ? 'https://app.halkode.com.tr/ccpayment/api/token' : 'https://testapp.halkode.com.tr/ccpayment/api/token';

            // Gerekli alanların kontrolü
            if (empty($this->get_option('app_key')) || empty($this->get_option('app_secret')) || empty($this->get_option('merchant_key'))) {
                halkode_log('Gerekli API bilgileri eksik', 'debug', array(
                    'app_key' => !empty($this->get_option('app_key')),
                    'app_secret' => !empty($this->get_option('app_secret')),
                    'merchant_key' => !empty($this->get_option('merchant_key'))
                ));
                return $inst;
            }

            // 1. TOKEN AL
            halkode_log('Token alma isteği gönderiliyor', 'debug', array('url' => $environment_url));
            $tokenResponse = $this->curl($environment_url, 'POST', $post);

            // Token kontrolü
            if (!$tokenResponse || !isset($tokenResponse->data) || !isset($tokenResponse->data->token)) {
                halkode_log('TOKEN ALINAMADI', 'warning', array('response' => $tokenResponse));
                return $inst;
            }

            $token = $tokenResponse->data->token;
            halkode_log('Token başarıyla alındı', 'debug');

            // 2. INSTALLMENT URL
            $environment_url = ($environment === "FALSE")
                ? 'https://app.halkode.com.tr/ccpayment/api/installments'
                : 'https://testapp.halkode.com.tr/ccpayment/api/installments';

            $headers = [
                'Accept: application/json',
                'Content-Type: application/json',
                "Authorization: Bearer $token"
            ];

            $installment_data = array('merchant_key' => $this->get_option('merchant_key'));

            // 3. TAKSİT AL
            halkode_log('Taksit bilgileri alınıyor', 'debug', array('url' => $environment_url));
            $installments = $this->curl($environment_url, 'POST', json_encode($installment_data), $headers);

            // Installments kontrolü
            if ($installments && isset($installments->installments) && is_array($installments->installments)) {
                foreach ($installments->installments as $key => $installment) {
                    $inst[$key + 1] = $installment;
                }
                halkode_log('Taksit bilgileri başarıyla alındı', 'debug', array('count' => count($inst)));
            } else {
                halkode_log('INSTALLMENTS ALINAMADI', 'warning', array('response' => $installments));
            }
        } catch (Exception $e) {
            halkode_log('get_admin_installment fonksiyonunda hata: ' . $e->getMessage(), 'error', array(
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
        }

        return $inst;
    }

    public function generateHashKey(
        $total,
        $installment,
        $currency_code,
        $merchant_key,
        $invoice_id,
        $app_secret
    ) {

        $data = $total . '|' . $installment . '|' . $currency_code . '|' . $merchant_key . '|' . $invoice_id;

        $iv = substr(sha1(mt_rand()), 0, 16);
        $password = sha1($app_secret);

        $salt = substr(sha1(mt_rand()), 0, 4);
        $saltWithPassword = hash('sha256', $password . $salt);

        $encrypted = openssl_encrypt("$data", 'aes-256-cbc', "$saltWithPassword", null, $iv);

        $msg_encrypted_bundle = "$iv:$salt:$encrypted";
        $msg_encrypted_bundle = str_replace('/', '__', $msg_encrypted_bundle);

        return $msg_encrypted_bundle;
    }

    private function getToken()
    {

        $api_secret = $this->get_option('app_secret');
        $api_key = $this->get_option('app_key');
        $merchant_key = $this->get_option('merchant_key');
        $merchant_id = $this->get_option('merchant_id');
        $sandbox = $this->get_option('environment');


        $url = $sandbox == 'yes' ? 'https://testapp.halkode.com.tr/ccpayment/api/token' : 'https://app.halkode.com.tr/ccpayment/api/token';

        $array = [
            'app_id' => $api_key,
            'app_secret' => $api_secret
        ];

        return $this->curl($url, 'POST', $array);
    }

    // Validate fields
    public function validate_fields()
    {
        // Sadece HalkOde seçiliyse validasyon yap
        if (!isset($_POST['payment_method']) || $_POST['payment_method'] !== 'halkode_sanalpos') {
            return true;
        }
        if (isset($_POST['cc_holder_name']) && empty($_POST['cc_holder_name'])) {
            wc_add_notice('Kart sahibi alanı zorunludur!', 'error');

            return false;
        }

        if (isset($_POST['cc_number']) && empty($_POST['cc_number'])) {
            wc_add_notice('Kart numarası alanı zorunludur!', 'error');

            return false;
        }

        if (!preg_match("/^[0-9]{13,16}$/", str_replace(array(' ', '-'), '', $_POST['cc_number']))) {
            wc_add_notice('Geçersiz kart numarası!', 'error');

            return false;
        }

        if (isset($_POST['expiry_month']) && empty($_POST['expiry_month'])) {
            wc_add_notice('Kart son kullanım ayı zorunludur!', 'error');

            return false;
        }

        $month = isset($_POST['expiry_month']) ? intval($_POST['expiry_month']) : 0;
        if ($month < 1 || $month > 12) {
            wc_add_notice(__('Kart son kullanım ayı 1 ile 12 arasında olmalıdır.', 'woocommerce'), 'error');
        }

        if (isset($_POST['expiry_year']) && empty($_POST['expiry_year'])) {
            wc_add_notice('Kart son kullanım yılı zorunludur!', 'error');

            return false;
        }

        $year = isset($_POST['expiry_year']) ? intval($_POST['expiry_year']) : 0;
        $currentYear = intval(date("Y"));
        if ($year < $currentYear || $year > $currentYear + 20) {
            wc_add_notice(__('Lütfen geçerli bir son kullanma yılı girin.', 'woocommerce'), 'error');

            return false;
        }

        // Expiry geçmiş mi kontrol et
        if ($year == $currentYear && $month < intval(date("n"))) {
            wc_add_notice(__('Son kullanma tarihi geçmiş tarih olamaz!', 'woocommerce'), 'error');

            return false;
        }
        if (isset($_POST['cc_cvv']) && empty($_POST['cc_cvv'])) {
            wc_add_notice('Kart CVV zorunludur!', 'error');

            return false;
        }
        if (!preg_match("/^[0-9]{3,4}$/", $_POST['cc_cvv'])) {
            wc_add_notice('Geçersiz CVV kodu!', 'error');

            return false;
        }
    }

    public function do_ssl_check()
    {
        if ($this->enabled == "yes") {
            if (get_option('woocommerce_force_ssl_checkout') == "no") {
                echo "<div class=\"error\"><p>" .
                    sprintf(
                        __(
                            "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"
                        ),
                        $this->method_title,
                        admin_url('admin.php?page=wc-settings&tab=checkout')
                    ) .
                    "</p></div>";
            }
        }
    }
}
