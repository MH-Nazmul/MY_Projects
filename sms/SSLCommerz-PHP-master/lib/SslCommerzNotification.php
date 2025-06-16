<?php

namespace SslCommerz;

require_once(__DIR__ . "/AbstractSslCommerz.php");

class SslCommerzNotification extends AbstractSslCommerz
{
    protected $data = [];
    protected $config = [];

    private $successUrl;
    private $cancelUrl;
    private $failedUrl;
    private $ipnUrl;

    /**
     * @var string
     */
    private $error;

    /**
     * SslCommerzNotification constructor.
     */
    public function __construct()
    {
        $this->config = include(__DIR__ . '/../config/config.php');

        $this->setStoreId($this->config['apiCredentials']['store_id']);
        $this->setStorePassword($this->config['apiCredentials']['store_passwd'] ?? '');
        $this->config['success_url'] = $this->config['success_url'] ?? '/SSLCommerz-PHP-master/pg_redirection/success.php';
        $this->config['failed_url'] = $this->config['failed_url'] ?? '/SSLCommerz-PHP-master/pg_redirection/fail.php';
        $this->config['cancel_url'] = $this->config['cancel_url'] ?? '/SSLCommerz-PHP-master/pg_redirection/cancel.php';
        $this->config['ipn_url'] = $this->config['ipn_url'] ?? '/SSLCommerz-PHP-master/pg_redirection/ipn.php';
        $this->config['projectPath'] = defined('PROJECT_PATH') ? PROJECT_PATH : 'https://mhnazmul.free.nf';
        $this->config['apiDomain'] = $this->config['apiDomain'] ?? 'https://sandbox.sslcommerz.com';
    }

    public function orderValidate($post_data, $trx_id = '', $amount = 0, $currency = "BDT")
    {
        error_log("Order Validate Input: " . print_r($post_data, true));
        if ($post_data == '' && $trx_id == '' && !is_array($post_data)) {
            $this->error = "Please provide valid transaction ID and post request data";
            return $this->error;
        }

        $validation = $this->validate($trx_id, $amount, $currency, $post_data);

        if ($validation) {
            return true;
        } else {
            return false;
        }
    }

    # VALIDATE SSLCOMMERZ TRANSACTION
    protected function validate($merchant_trans_id, $merchant_trans_amount, $merchant_trans_currency, $post_data)
    {
        error_log("Validate Params: merchant_trans_id=$merchant_trans_id, amount=$merchant_trans_amount, currency=$merchant_trans_currency");
        # MERCHANT SYSTEM INFO
        if ($merchant_trans_id != "" && $merchant_trans_amount != 0) {
            # CALL THE FUNCTION TO CHECK THE RESULT
            $post_data['store_id'] = $this->getStoreId();
            $post_data['store_pass'] = $this->getStorePassword();

            if ($this->SSLCOMMERZ_hash_verify($post_data, $this->getStorePassword())) {
                $val_id = urlencode($post_data['val_id']);
                $store_id = urlencode($this->getStoreId());
                $store_passwd = urlencode($this->getStorePassword());
                $requested_url = ($this->config['apiDomain'] . $this->config['apiUrl']['order_validate'] . "?val_id=" . $val_id . "&store_id=" . $store_id . "&store_passwd=" . $store_passwd . "&v=1&format=json");

                $handle = curl_init();
                curl_setopt($handle, CURLOPT_URL, $requested_url);
                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

                if ($this->config['connect_from_localhost'] ?? false) {
                    curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
                    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
                } else {
                    curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);
                    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 1);
                }

                $result = curl_exec($handle);

                $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

                if ($code == 200 && !(curl_errno($handle))) {
                    # TO CONVERT AS OBJECT
                    $result = json_decode($result);
                    $this->sslc_data = $result;
                    error_log("Validation API Response: " . print_r($result, true));

                    # TRANSACTION INFO
                    $status = $result->status ?? '';
                    $tran_date = $result->tran_date ?? '';
                    $tran_id = $result->tran_id ?? '';
                    $val_id = $result->val_id ?? '';
                    $amount = $result->amount ?? 0;
                    $store_amount = $result->store_amount ?? 0;
                    $bank_tran_id = $result->bank_tran_id ?? '';
                    $card_type = $result->card_type ?? '';
                    $currency_type = $result->currency_type ?? '';
                    $currency_amount = $result->currency_amount ?? 0;

                    # ISSUER INFO
                    $card_no = $result->card_no ?? '';
                    $card_issuer = $result->card_issuer ?? '';
                    $card_brand = $result->card_brand ?? '';
                    $card_issuer_country = $result->card_issuer_country ?? '';
                    $card_issuer_country_code = $result->card_issuer_country_code ?? '';

                    # API AUTHENTICATION
                    $APIConnect = $result->APIConnect ?? '';
                    $validated_on = $result->validated_on ?? '';
                    $gw_version = $result->gw_version ?? '';

                    # GIVE SERVICE
                    if ($status == "VALID" || $status == "VALIDATED") {
                        if ($merchant_trans_currency == "BDT") {
                            if (trim($merchant_trans_id) == trim($tran_id) && (abs($merchant_trans_amount - $amount) < 1) && trim($merchant_trans_currency) == trim('BDT')) {
                                return true;
                            } else {
                                # DATA TEMPERED
                                $this->error = "Data has been tempered";
                                return false;
                            }
                        } else {
                            if (trim($merchant_trans_id) == trim($tran_id) && (abs($merchant_trans_amount - $currency_amount) < 1) && trim($merchant_trans_currency) == trim($currency_type)) {
                                return true;
                            } else {
                                # DATA TEMPERED
                                $this->error = "Data has been tempered";
                                return false;
                            }
                        }
                    } else {
                        # FAILED TRANSACTION
                        $this->error = "Failed Transaction";
                        return false;
                    }
                } else {
                    # Failed to connect with SSLCOMMERZ
                    $this->error = "Failed to connect with SSLCOMMERZ";
                    return false;
                }
            } else {
                # Hash validation failed
                $this->error = "Hash validation failed";
                return false;
            }
        } else {
            # INVALID DATA
            $this->error = "Invalid data";
            return false;
        }
    }

    # FUNCTION TO CHECK HASH VALUE
    protected function SSLCOMMERZ_hash_verify($post_data, $store_passwd = "")
    {
        if (!isset($this->config['verify_hash']) || !$this->config['verify_hash']) {
            return true;
        }

        if (isset($post_data) && isset($post_data['verify_sign']) && isset($post_data['verify_key'])) {
            # NEW ARRAY DECLARED TO TAKE VALUE OF ALL POST
            $pre_define_key = explode(',', $post_data['verify_key']);

            $new_data = array();
            if (!empty($pre_define_key)) {
                foreach ($pre_define_key as $value) {
                    if (isset($post_data[$value])) {
                        $new_data[$value] = ($post_data[$value]);
                    }
                }
            }
            # ADD MD5 OF STORE PASSWORD
            $new_data['store_passwd'] = md5($store_passwd);

            # SORT THE KEY AS BEFORE
            ksort($new_data);

            $hash_string = "";
            foreach ($new_data as $key => $value) {
                $hash_string .= $key . '=' . ($value) . '&';
            }
            $hash_string = rtrim($hash_string, '&');

            if (md5($hash_string) == $post_data['verify_sign']) {
                return true;
            } else {
                $this->error = "Verification signature not matched";
                return false;
            }
        } else {
            $this->error = 'Required data missing. ex: verify_key, verify_sign';
            return false;
        }
    }

    /**
     * @param array $requestData
     * @param string $type
     * @param string $pattern
     * @return false|mixed|string
     */
    public function makePayment(array $requestData, $type = 'checkout', $pattern = 'json')
    {
        if (empty($requestData)) {
            return "Please provide a valid information list about transaction with transaction id, amount, success url, fail url, cancel url, store id and pass at least";
        }

        $header = [];

        $this->setApiUrl($this->config['apiDomain'] . $this->config['apiUrl']['make_payment']);

        // Set the required/additional params
        $this->setParams($requestData);

        // Set the authentication information
        $this->setAuthenticationInfo();

        // Now, call the Gateway API
        $response = $this->callToApi($this->data, $header, $this->config['connect_from_localhost'] ?? false);

        $formattedResponse = $this->formatResponse($response, $type, $pattern); // Here we will define the response pattern

        if ($type == 'hosted') {
            $formattedResponse = is_array($formattedResponse) ? $formattedResponse : [];
            if (!empty($formattedResponse['GatewayPageURL'] ?? '')) {
                $this->redirect($formattedResponse['GatewayPageURL']);
            } else {
                return $formattedResponse['failedreason'] ?? 'Failed to retrieve GatewayPageURL';
            }
        } else {
            return $formattedResponse;
        }
    }

    protected function setSuccessUrl()
    {
        $this->successUrl = $this->config['projectPath'] . '/' . $this->config['success_url'];
    }

    protected function getSuccessUrl()
    {
        return $this->successUrl;
    }

    protected function setFailedUrl()
    {
        $this->failedUrl = $this->config['projectPath'] . '/' . $this->config['failed_url'];
    }

    protected function getFailedUrl()
    {
        return $this->failedUrl;
    }

    protected function setCancelUrl()
    {
        $this->cancelUrl = $this->config['projectPath'] . '/' . $this->config['cancel_url'];
    }

    protected function getCancelUrl()
    {
        return $this->cancelUrl;
    }

    protected function setIpnUrl()
    {
        $this->ipnUrl = $this->config['projectPath'] . '/' . $this->config['ipn_url'];
    }

    protected function getIpnUrl()
    {
        return $this->ipnUrl;
    }

    public function setParams($requestData)
    {
        ## Integration Required Parameters
        $this->setRequiredInfo($requestData);

        ## Customer Information
        $this->setCustomerInfo($requestData);

        ## Shipment Information
        $this->setShipmentInfo($requestData);

        ## Product Information
        $this->setProductInfo($requestData);

        ## Customized or Additional Parameters
        $this->setAdditionalInfo($requestData);
    }

    public function setAuthenticationInfo()
    {
        $this->data['store_id'] = $this->getStoreId();
        $this->data['store_passwd'] = $this->getStorePassword();

        return $this->data;
    }

    public function setRequiredInfo(array $info)
    {
        $this->data['total_amount'] = $info['total_amount'];
        $this->data['currency'] = $info['currency'];
        $this->data['tran_id'] = $info['tran_id'];
        $this->data['product_category'] = $info['product_category'];

        // Set the SUCCESS, FAIL, CANCEL and IPN URL before setting the other parameters
        $this->setSuccessUrl();
        $this->setFailedUrl();
        $this->setCancelUrl();
        $this->setIpnUrl();

        $this->data['success_url'] = $this->getSuccessUrl();
        $this->data['fail_url'] = $this->getFailedUrl();
        $this->data['cancel_url'] = $this->getCancelUrl();
        $this->data['ipn_url'] = $this->getIpnUrl();

        $this->data['multi_card_name'] = $info['multi_card_name'] ?? null;
        $this->data['allowed_bin'] = $info['allowed_bin'] ?? null;

        ## Parameters to Handle EMI Transaction ##
        $this->data['emi_option'] = $info['emi_option'] ?? null;
        $this->data['emi_max_inst_option'] = $info['emi_max_inst_option'] ?? null;
        $this->data['emi_selected_inst'] = $info['emi_selected_inst'] ?? null;

        return $this->data;
    }

    public function setCustomerInfo(array $info)
    {
        $this->data['cus_name'] = $info['cus_name'];
        $this->data['cus_email'] = $info['cus_email'];
        $this->data['cus_add1'] = $info['cus_add1'];
        $this->data['cus_add2'] = $info['cus_add2'] ?? '';
        $this->data['cus_city'] = $info['cus_city'];
        $this->data['cus_state'] = $info['cus_state'] ?? '';
        $this->data['cus_postcode'] = $info['cus_postcode'] ?? '1200'; // Default postcode
        $this->data['cus_country'] = $info['cus_country'];
        $this->data['cus_phone'] = $info['cus_phone'];
        $this->data['cus_fax'] = $info['cus_fax'] ?? '';

        return $this->data;
    }

    public function setShipmentInfo(array $info)
    {
        $this->data['shipping_method'] = $info['shipping_method'];
        $this->data['num_of_item'] = $info['num_of_item'] ?? 1; // Default to 1 item
        $this->data['ship_name'] = $info['ship_name'] ?? $info['cus_name'];
        $this->data['ship_add1'] = $info['ship_add1'] ?? $info['cus_add1'];
        $this->data['ship_add2'] = $info['ship_add2'] ?? '';
        $this->data['ship_city'] = $info['ship_city'] ?? $info['cus_city'];
        $this->data['ship_state'] = $info['ship_state'] ?? '';
        $this->data['ship_postcode'] = $info['ship_postcode'] ?? '1200'; // Default postcode
        $this->data['ship_country'] = $info['ship_country'] ?? $info['cus_country'];

        return $this->data;
    }

    public function setProductInfo(array $info)
    {
        $this->data['product_name'] = $info['product_name'] ?? '';
        $this->data['product_category'] = $info['product_category'] ?? '';
        $this->data['product_profile'] = $info['product_profile'] ?? '';
        $this->data['hours_till_departure'] = $info['hours_till_departure'] ?? null;
        $this->data['flight_type'] = $info['flight_type'] ?? null;
        $this->data['pnr'] = $info['pnr'] ?? null;
        $this->data['journey_from_to'] = $info['journey_from_to'] ?? null;
        $this->data['third_party_booking'] = $info['third_party_booking'] ?? null;
        $this->data['hotel_name'] = $info['hotel_name'] ?? null;
        $this->data['length_of_stay'] = $info['length_of_stay'] ?? null;
        $this->data['check_in_time'] = $info['check_in_time'] ?? null;
        $this->data['hotel_city'] = $info['hotel_city'] ?? null;
        $this->data['product_type'] = $info['product_type'] ?? null;
        $this->data['topup_number'] = $info['topup_number'] ?? null;
        $this->data['country_topup'] = $info['country_topup'] ?? null;
        $this->data['cart'] = $info['cart'] ?? null;
        $this->data['product_amount'] = $info['product_amount'] ?? null;
        $this->data['vat'] = $info['vat'] ?? null;
        $this->data['discount_amount'] = $info['discount_amount'] ?? null;
        $this->data['convenience_fee'] = $info['convenience_fee'] ?? null;

        return $this->data;
    }

    public function setAdditionalInfo(array $info)
    {
        $this->data['value_a'] = $info['value_a'] ?? null;
        $this->data['value_b'] = $info['value_b'] ?? null;
        $this->data['value_c'] = $info['value_c'] ?? null;
        $this->data['value_d'] = $info['value_d'] ?? null;

        return $this->data;
    }
}