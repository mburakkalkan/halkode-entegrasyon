<?php
/*
    Plugin Name: Halk Ödeme Hizmetleri Sanal Pos
    Plugin URI: https://www.parao.com.tr/
    Description: Woocommerce için Halk Ödeme Entegrasyonu
    Domain Path: /i18n/languages/
    Text Domain: halkode

    */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * HalkOde için WooCommerce logger wrapper
 * @param string $message Log mesajı
 * @param string $level Log seviyesi: 'info', 'warning', 'error', 'debug'
 * @param array $context Ek context bilgisi
 */
function halkode_log($message, $level = 'info', $context = array())
{
    if (function_exists('wc_get_logger')) {
        $logger = wc_get_logger();
        $log_message = '[HalkOde] ' . $message;

        if (!empty($context)) {
            $log_message .= ' | ' . wp_json_encode($context);
        }

        $logger->log($level, $log_message, array('source' => 'halkode'));
    } else {
        // Fallback to error_log if WooCommerce logger not available
        $log_message = '[HalkOde ' . strtoupper($level) . '] ' . $message;
        if (!empty($context)) {
            $log_message .= ' | ' . print_r($context, true);
        }
        error_log($log_message);
    }
}

// Eklenti aktivasyonu için hook
register_activation_hook(__FILE__, 'halkode_plugin_activate');

function halkode_plugin_activate()
{
    // Kart kaydetme ile ilgili kodlar kaldırıldı. Artık aktivasyonda ek işlem yapılmıyor.
}

add_action('plugins_loaded', 'halkode_pos', 0);
add_action('init', 'my_custom_public_page');
add_action('wp_ajax_get_installment', 'get_installment');
add_action('wp_ajax_get_admin_installment', 'get_admin_installment');
add_action('wp_ajax_nopriv_get_installment', 'get_installment');
add_action('wp_ajax_nopriv_get_admin_installment', 'get_admin_installment');

function halkode_pos()
{
    try {
        //if condition use to do nothin while WooCommerce is not installed
        if (!class_exists('WC_Payment_Gateway')) {
            halkode_log('WooCommerce bulunamadı', 'error');
            return;
        }

        // Dosya varlığı kontrolü
        $main_file = plugin_dir_path(__FILE__) . 'halkode-woocommerce.php';
        $recurring_file = plugin_dir_path(__FILE__) . 'halkode-woocommerce-recurring.php';

        if (!file_exists($main_file)) {
            halkode_log('Ana dosya bulunamadı: ' . $main_file, 'error');
            return;
        }

        include_once $main_file;

        if (file_exists($recurring_file)) {
            include_once $recurring_file;
        } else {
            halkode_log('Recurring dosyası bulunamadı: ' . $recurring_file, 'warning');
        }

        // class add it too WooCommerce
        add_filter('woocommerce_payment_gateways', 'halkode_gateway');
        function halkode_gateway($methods)
        {
            $methods[] = 'halkode_sanalpos';
            return $methods;
        }

        halkode_log('Plugin başarıyla yüklendi', 'info');
    } catch (Exception $e) {
        halkode_log('Plugin yükleme hatası: ' . $e->getMessage(), 'error');

        // Admin bildirim ekle
        add_action('admin_notices', function () use ($e) {
            echo '<div class="notice notice-error"><p>HalkOde Plugin Yükleme Hatası: ' . esc_html($e->getMessage()) . '</p></div>';
        });
    }
}

// Add custom action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'halkode_settings');
function halkode_settings($links)
{
    $plugin_links = ['<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=halkode_sanalpos') . '">' . __('Settings', 'halkode_sanalpos') . '</a>'];
    return array_merge($plugin_links, $links);
}

function getToken()
{
    $halkode_pay = new halkode_sanalpos();
    $api_secret = $halkode_pay->get_option('app_secret');
    $api_key = $halkode_pay->get_option('app_key');
    $merchant_key = $halkode_pay->get_option('merchant_key');
    $merchant_id = $halkode_pay->get_option('merchant_id');
    $sandbox = $halkode_pay->get_option('environment');


    $url = $sandbox == 'yes' ? 'https://testapp.halkode.com.tr/ccpayment/api/token' : 'https://app.halkode.com.tr/ccpayment/api/token';

    $array = [
        'app_id' => $api_key,
        'app_secret' => $api_secret
    ];

    return getCurl($url, 'POST', $array);
}

function checkStatus($invoice_id)
{
    $halkode_pay = new halkode_sanalpos();
    $api_secret = $halkode_pay->get_option('app_secret');
    $api_key = $halkode_pay->get_option('app_key');
    $merchant_key = $halkode_pay->get_option('merchant_key');
    $merchant_id = $halkode_pay->get_option('merchant_id');
    $sandbox = $halkode_pay->get_option('environment');

    $hash_key = generateRefundHashKey($invoice_id, $merchant_key, $api_secret);
    $token = getToken()->data->token;
    $headers = ['Accept: application/json', 'Content-Type: application/json', "Authorization: Bearer {$token}"];
    $url = $sandbox == 'yes' ? 'https://testapp.halkode.com.tr/ccpayment/api/checkstatus' : 'https://app.halkode.com.tr/ccpayment/api/checkstatus';

    $array = [
        'invoice_id' => $invoice_id,
        'merchant_key' => $merchant_key,
        'hash_key' => $hash_key,
        'include_pending_status' => "true",
    ];

    return getCurl($url, 'POST', json_encode($array), $headers);
}

function generateRefundHashKey($invoice_id, $merchant_key, $app_secret)
{
    $data = $invoice_id . '|' . $merchant_key;
    $iv = substr(sha1(mt_rand()), 0, 16);
    $password = sha1($app_secret);
    $salt = substr(sha1(mt_rand()), 0, 4);
    $saltWithPassword = hash('sha256', $password . $salt);
    $encrypted = openssl_encrypt(
        "$data",
        'aes-256-cbc',
        "$saltWithPassword",
        0,
        $iv
    );
    $msg_encrypted_bundle = "$iv:$salt:$encrypted";
    $hash_key = str_replace('/', '__', $msg_encrypted_bundle);
    return $hash_key;
}

function my_custom_public_page()
{
    try {
        // If action parameter exists and it does not belong to this plugin, exit the function
        if (isset($_GET['action'])) {
            $halkode_actions = array('get_installment', 'get_admin_installment');
            if (!in_array($_GET['action'], $halkode_actions)) {
                return;
            }
        }

        // Input sanitization
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $invoice_id = isset($_GET['invoice_id']) ? sanitize_text_field($_GET['invoice_id']) : '';
        $webhook = isset($_GET['webhook']) ? absint($_GET['webhook']) : 0;

        // HalkOde ile ilgili parametreler yoksa fonksiyonu çalıştırma
        if ($order_id == 0 && empty($invoice_id) && $webhook == 0) {
            return;
        }

        halkode_log('my_custom_public_page çağrıldı. Order ID: ' . $order_id . ', Invoice ID: ' . $invoice_id . ', Webhook: ' . $webhook, 'debug');
    } catch (Exception $e) {
        halkode_log('my_custom_public_page başlangıç hatası: ' . $e->getMessage());
        return;
    }

    if ($order_id > 0 && empty($invoice_id)) {

        // Order varlığı kontrolü
        $order = wc_get_order($order_id);
        if (!$order || !$order->get_id()) {
            halkode_log('Geçersiz order ID: ' . $order_id);
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        // HalkOde ödeme yöntemi kontrolü ekle
        $payment_method = $order->get_payment_method();
        if ($payment_method !== 'halkode_sanalpos') {
            halkode_log('Bu sipariş HalkOde ile oluşturulmamış: ' . $payment_method);
            return;
        }

        // Meta data güvenli şekilde al
        $payment_form_data = get_post_meta($order_id, 'halkode_payment_form', true);
        if (empty($payment_form_data)) {
            halkode_log('Payment form data bulunamadı. Order ID: ' . $order_id);
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $result = unserialize(base64_decode($payment_form_data));



        if (!is_array($result)) {

            echo $result;
            delete_post_meta($_GET['order_id'], 'halkode_payment_form');

            exit;
        } else {
            if (isset($result['purchase']) && $result['purchase'] == 'yes') {
                unset($result['token']);
                unset($result['is_3d']);
                unset($result['purchase']);
                unset($result['installments_number']);
                unset($result['transaction_type']);
                unset($result['hash_key']);
                unset($result['sale_web_hook_key']);

                $new_form = $result;


                $invoice['invoice_id'] = $result['invoice_id'];
                $invoice['invoice_description'] = $result['invoice_description'];
                $invoice['total'] = $result['total'];
                $invoice['return_url'] = $result['return_url'];
                $invoice['cancel_url'] = $result['cancel_url'];
                $invoice['items'] = $result['items'];

                unset($new_form['invoice_id']);
                unset($new_form['invoice_description']);
                unset($new_form['total']);
                unset($new_form['return_url']);
                unset($new_form['cancel_url']);
                unset($new_form['items']);


                $halkode_pay = new halkode_sanalpos();
                $environment = $halkode_pay->get_option('environment') == "yes" ? 'TRUE' : 'FALSE';

                $post = array(

                    'merchant_key' => $halkode_pay->get_option('merchant_key'),

                    'invoice' => json_encode($invoice),

                    'currency_code' => get_option('woocommerce_currency'),

                    'name' => $result['name'],

                    'surname' => $result['surname']

                );


                //print_r($post); exit;

                $environment_url = "FALSE" == $environment ? 'https://app.halkode.com.tr/ccpayment/purchase/link' : 'https://testapp.halkode.com.tr/ccpayment/purchase/link';
                $headers = ['Content-Type: application/json'];
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $environment_url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

                $response = json_decode(curl_exec($ch), true);

                curl_close($ch);

                if ($response['status'] == 1) {
                    echo ("<script>location.href='" . $response['link'] . "'</script>");
                    exit;
                } else {

                    wc_add_notice($response->status_description, 'error');

                    wp_redirect(wc_get_checkout_url() . '?error=' . $response->status_description);
                    exit;
                }
            }
            $response = pay2d($result['token'], $result);



            if ($response->status_code == 100) {
                $order_id = explode('WOO', $response->data->invoice_id);
                $order_id = end($order_id);
                $customer_order = new WC_Order($order_id);

                $status = checkStatus($response->data->invoice_id);

                if ($status->status_code == 100 || $status->status_code == 69) {
                    $customer_order->update_status('processing');
                    $customer_order->add_order_note(__('Sanal pos ödeme başarıyla alındı. Ödeme referans no :' . $status->order_id));
                    try {
                        WC()->mailer()->customer_invoice($customer_order);
                        $admin_email = WC()->mailer()->emails['WC_Email_New_Order'];
                        if ($admin_email) {
                            $admin_email->trigger($customer_order->get_id());
                        }
                    } catch (\Exception $e) {
                        error_log($e->getMessage());
                    }

                    delete_post_meta($order_id, 'halkode_payment_form');
                    delete_post_meta($order_id, 'halkode_response');
                    //echo $customer_order->get_checkout_order_received_url(); exit;
                    // paid order marked
                    // $customer_order->payment_complete();
                    // // this is important part for empty cart
                    // $woocommerce->cart->empty_cart();
                    // Redirect to thank you page
                    header('Location: ' . $customer_order->get_checkout_order_received_url());
                    exit;
                } else {

                    update_post_meta($order_id, 'halkode_response', $response->status_description);
                    delete_post_meta($order_id, 'halkode_payment_form');
                    wc_add_notice($response->status_description, 'error');

                    wp_redirect(wc_get_checkout_url() . '?error=' . $response->status_description);
                    exit;
                }
            } else {
                $order_id = $_GET['order_id'];

                update_post_meta($order_id, 'halkode_response', $response->status_description);
                delete_post_meta($order_id, 'halkode_payment_form');
                wc_add_notice($response->status_description, 'error');

                wp_redirect(wc_get_checkout_url() . '?error=' . $response->status_description);
                exit;
            }
        }
    }

    if ($webhook === 1) {
        try {
            // Webhook güvenlik kontrolü
            if (empty($_POST['invoice_id']) || empty($_POST['payment_status'])) {
                halkode_log('Webhook eksik parametreler', 'info');
                http_response_code(400);
                exit('Bad Request');
            }

            // Webhook imza kontrolü burada olmalı (API'den gelen secret key ile)
            $invoice_id = sanitize_text_field($_POST['invoice_id']);
            $payment_status = sanitize_text_field($_POST['payment_status']);
            $order_no = isset($_POST['order_no']) ? sanitize_text_field($_POST['order_no']) : '';

            if (!strpos($invoice_id, 'WOO')) {
                halkode_log('Geçersiz invoice_id format: ' . $invoice_id);
                http_response_code(400);
                exit('Invalid Invoice ID');
            }

            $order_id_parts = explode('WOO', $invoice_id);
            $order_id = absint(end($order_id_parts));

            $customer_order = wc_get_order($order_id);
            if (!$customer_order) {
                halkode_log('Webhook için order bulunamadı: ' . $order_id);
                http_response_code(404);
                exit('Order Not Found');
            }

            if ($payment_status == '1') {
                $customer_order->update_status('processing');
                $customer_order->add_order_note(__('Sanal pos webhook aracılığıyla ödeme onaylandı. Ödeme referans no: ' . $order_no));
                halkode_log('Webhook başarılı ödeme: Order ID ' . $order_id);
            } else {
                $customer_order->update_status('failed');
                $customer_order->add_order_note(__('Webhook aracılığıyla işlem iptal edildi'));
                halkode_log('Webhook başarısız ödeme: Order ID ' . $order_id);
            }

            http_response_code(200);
            exit('OK');
        } catch (Exception $e) {
            halkode_log('Webhook hatası: ' . $e->getMessage());
            http_response_code(500);
            exit('Internal Server Error');
        }
    }

    if (isset($_GET['invoice_id']) and strstr($_GET['invoice_id'], 'WOO'))
        $order_id = explode('WOO', $_GET['invoice_id']);

    if (isset($_GET['invoice_id']) && isset($_GET['payment_status']) && $_GET['payment_status'] == 1) {
        $order_id = end($order_id);
        $customer_order = new WC_Order($order_id);

        // HalkOde ödeme yöntemi kontrolü
        if ($customer_order->get_payment_method() !== 'halkode_sanalpos') {
            halkode_log('Bu sipariş HalkOde ile oluşturulmamış, yönlendirme yapılmıyor');
            return;
        }

        $status = checkStatus($_GET['invoice_id']);

        if ($status->status_code == 100 || $status->status_code == 69) {

            $customer_order->update_status('processing');
            $customer_order->add_order_note(__('Sanal pos ödeme başarıyla alındı. Ödeme referans no :' . $status->order_id));
            try {
                WC()->mailer()->customer_invoice($customer_order);
                $admin_email = WC()->mailer()->emails['WC_Email_New_Order'];
                if ($admin_email) {
                    $admin_email->trigger($customer_order->get_id());
                }
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }


            delete_post_meta($order_id, 'halkode_payment_form');
            delete_post_meta($order_id, 'halkode_response');
            header('Location: ' . $customer_order->get_checkout_order_received_url());
        } else {

            update_post_meta($order_id, 'halkode_response', $response->status_description);
            delete_post_meta($order_id, 'halkode_payment_form');
            wc_add_notice($response->status_description, 'error');

            wp_redirect(wc_get_checkout_url() . '?error=' . $response->status_description);
            exit;
        }
    } elseif (isset($_GET['payment_status']) && $_GET['payment_status'] == 0) {
        $order_id = end($order_id);
        $customer_order = wc_get_order($order_id);

        // HalkOde ödeme yöntemi kontrolü
        if (!$customer_order || $customer_order->get_payment_method() !== 'halkode_sanalpos') {
            halkode_log('Başarısız ödeme, ancak sipariş HalkOde ile oluşturulmamış');
            return;
        }

        update_post_meta($order_id, 'halkode_response', $_GET['error']);
        delete_post_meta($order_id, 'halkode_payment_form');
        wc_add_notice($_GET['error'], 'error');
    }
}


function get_installment()
{

    if (!empty($_POST['cc_number'])) {
        global $woocommerce;

        $halkode_pay = new halkode_sanalpos();

        /* getpos request */

        $pos_post = [
            'credit_card' => $_POST['cc_number'],
            'amount' => $woocommerce->cart->total,
            "currency_code" => get_option('woocommerce_currency'),
            "merchant_key" => $halkode_pay->get_option('merchant_key'),
            'app_id' => $halkode_pay->get_option('app_key'),
            'app_secret' => $halkode_pay->get_option('app_secret'),
        ];

        $environment = $halkode_pay->get_option('environment') == "yes" ? 'TRUE' : 'FALSE';
        $environment_url = "FALSE" == $environment ? 'https://app.halkode.com.tr/ccpayment/api/getpos' : 'https://testapp.halkode.com.tr/ccpayment/api/getpos';


        if (!empty($_POST['recurring_options']['recurring_check']) && $_POST['recurring_options']['recurring_check'] == 'yes') {
            $pos_post['is_recurring'] = 1;
        }

        $headers = ['Accept: application/json', 'Content-Type: application/json', "Authorization: Bearer {$_POST['token']}"];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $environment_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pos_post));
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $get_pos_response = json_decode(curl_exec($ch), true);

        //print_r($get_pos_response); exit;
        curl_close($ch);
        if ($get_pos_response['status_code'] == 100) {
            $html = '';
            if (!empty($get_pos_response['data'])) {
                $pos_id = '';
                $pos_amt = '';
                $currency_id = "";
                $campaign_id = "";
                $allocation_id = "";
                $installments_number = "";
                $hash_key = "";
                $currency_code = '';
                $i = 0;
                $html = "<div class='installments-wrapper'>";

                foreach ($get_pos_response['data'] as $val) {

                    if (!in_array($val['installments_number'], $halkode_pay->get_option('installments'))) {
                        $i++;
                        continue;
                    }

                    $active_cls = "";
                    $currency_code = $val['currency_code'];

                    if ($i == 0) {
                        $active_cls = 'active';
                        $pos_id = $val['pos_id'];
                        $pos_amt = $val['amount_to_be_paid'];
                        $currency_id = $val['currency_id'];
                        $campaign_id = $val['campaign_id'];
                        $allocation_id = $val['allocation_id'];
                        $installments_number = $val['installments_number'];
                        $hash_key = $val['hash_key'];
                        $inst = $halkode_pay->getLocalizationContent('single_installment', $currency_code);
                    } else {
                        $inst = $i + 1 . " " . $halkode_pay->getLocalizationContent('installment', $currency_code);
                    }

                    $single_amount = isset($val['amount_to_be_paid']) ? $val['amount_to_be_paid'] : '';
                    $single_currency = isset($val['currency_code']) ? $val['currency_code'] : '';
                    $single_posid = isset($val['pos_id']) ? $val['pos_id'] : '';
                    $single_currency_id = isset($val['currency_id']) ? $val['currency_id'] : '';
                    $single_campaign_id = isset($val['campaign_id']) ? $val['campaign_id'] : '';
                    $single_allocation_id = isset($val['allocation_id']) ? $val['allocation_id'] : '';
                    $single_installments_number = isset($val['installments_number']) ? $val['installments_number'] : '';
                    $single_hash_key = isset($val['hash_key']) ? $val['hash_key'] : '';
                    $per_installment = '';
                    $installment_index = $i + 1;
                    if ($single_amount !== '' && is_numeric($single_amount) && $installment_index > 0) {
                        $per_installment = number_format($single_amount / $installment_index, 2);
                    }

                    $html .= <<<HTML
                            <div class="single-installment {$active_cls}" data-posid="{$single_posid}" data-amount="{$single_amount}" data-currency_id="{$single_currency_id}" data-campaign_id="{$single_campaign_id}" data-allocation_id="{$single_allocation_id}" data-installments_number="{$single_installments_number}" data-hash_key="{$single_hash_key}" data-currency_code="{$single_currency}">
                                <div class="halkode_heading">{$inst}</div>
                                <div class="halkode_amount">{$single_amount} {$single_currency}</div>
                                <div class="halkode_installment_number">{$installment_index} X</div>
                                <div class="halkode_total_amount">{$per_installment} {$single_currency}</div>
                            </div>
                        HTML;

                    $i++;
                }

                $html .= "</div>";

                echo json_encode([
                    'data' => $html,
                    'pos_id' => $pos_id,
                    'pos_amt' => $pos_amt,
                    'currency_id' => $currency_id,
                    'campaign_id' => $campaign_id,
                    'allocation_id' => $allocation_id,
                    'installments_number' => $installments_number,
                    'hash_key' => $hash_key,
                    'currency_code' => $currency_code,
                ]);

                exit();
            }
        }
    }

    /* end getpos request */

    echo json_encode(['data' => '', 'pos_id' => '', 'pos_amt' => '', 'currency_id' => '', 'campaign_id' => '', 'allocation_id' => '', 'installments_number' => '', 'hash_key' => '', 'currency_code' => '']);

    exit();
}


function pay2d($token, $parameters)
{


    $halkode_pay = new halkode_sanalpos();
    $environment = $halkode_pay->get_option('environment') == "yes" ? 'TRUE' : 'FALSE';
    if ($parameters['is_2d_card'] == 'yes') {
        $environment_url = "FALSE" == $environment ? 'https://app.halkode.com.tr/ccpayment/api/payByCardTokenNonSecure' : 'https://testapp.halkode.com.tr/ccpayment/api/payByCardTokenNonSecure';
    } else {
        $environment_url = "FALSE" == $environment ? 'https://app.halkode.com.tr/ccpayment/api/paySmart2D' : 'https://testapp.halkode.com.tr/ccpayment/api/paySmart2D';
    }

    $headers = ['Accept: application/json', 'Content-Type: application/json', "Authorization: Bearer $token"];


    $options = array(
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => 1,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_VERBOSE => false,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($parameters),
        //CURLOPT_SSL_VERIFYHOST => 0,
        //CURLOPT_SSL_VERIFYPEER => 0,
    );

    $ch = curl_init($environment_url);
    curl_setopt_array($ch, $options);
    $content = curl_exec($ch);

    $err = curl_errno($ch);
    $errmsg = curl_error($ch);
    $header = curl_getinfo($ch);
    $rurl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    curl_close($ch);


    return json_decode($content);
}

function getCurl($url, $method, $array, $header = [])
{
    try {
        halkode_log('getCurl isteği: ' . $url);

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

        if ($response === false) {
            throw new Exception('CURL request failed: ' . $curl_error);
        }

        if ($http_code >= 400) {
            halkode_log('HTTP hata kodu: ' . $http_code . ' Response: ' . substr($response, 0, 500));
        }

        $decoded = json_decode($response);
        if (json_last_error() !== JSON_ERROR_NONE) {
            halkode_log('JSON decode hatası: ' . json_last_error_msg() . ' Response: ' . substr($response, 0, 500));
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        halkode_log('getCurl başarılı, HTTP Code: ' . $http_code);
        return $decoded;
    } catch (Exception $e) {
        halkode_log('getCurl hatası: ' . $e->getMessage() . ' URL: ' . $url);
        return false;
    }
}
