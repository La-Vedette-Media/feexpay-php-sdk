<?php

declare(strict_types=1);

namespace Feexpay\FeexpayPhp;

class FeexpayClass
{
    /**
     * Base URL of the Feexpay API (v2)
     */
    const API_BASE_URL = 'https://api-v2.feexpay.me';

    /**
     * Base URL of the Feexpay sandbox API (used when mode is SANDBOX)
     */
    const SANDBOX_API_BASE_URL = 'https://sandbox-api.feexpay.me';

    /**
     * cURL timeouts (seconds). Certificates are checked against the system CA store.
     * No read timeout on payment requests: they wait for the customer to validate on their phone.
     */
    const CONNECT_TIMEOUT = 10;
    const READ_TIMEOUT = 30;

    public $id;
    public $token;
    public $callback_url;
    public $error_callback_url;
    public $mode;

    /**
     * Last error returned by paiementLocal() / paiementCard() (null when the last call succeeded)
     * @var array|null ['message' => string, 'response' => mixed]
     */
    private $lastError = null;

    /**
     * Create a new Skeleton Instance
     * @param $id
     * @param $token
     * @param $callback_url
     * @param $error_callback_url
     * @param $mode
     */

    public function __construct($id,$token,$callback_url,$mode='LIVE', $error_callback_url = '')
    {
        // constructor body
        $this->id = $id;
        $this->token = $token;
        $this->callback_url = $callback_url;
        $this->error_callback_url = $error_callback_url;
        $this->mode = $mode;

    }

    public function init($amount, $componentId, $use_custom_button = false, $custom_button_id = "",  $description = "", $callback_info=""){
        $config = array(
            'id' => $this->id,
            'amount' => is_numeric($amount) ? $amount + 0 : null,
            'token' => $this->token,
            'callback_url' => $this->callback_url,
            'mode' => $this->mode,
            'custom_button' => (string) $use_custom_button,
            'id_custom_button' => $custom_button_id,
            'description' => $description,
            'callback_info' => $callback_info,
            'error_callback_url' => $this->error_callback_url,
        );

        // Valeurs encodées en JSON (avec <, >, ', " et & échappés) : une description ou un
        // callback_info contenant des guillemets ou </script> ne peut plus injecter de JavaScript
        $flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

        echo "
        <script src='" . self::API_BASE_URL . "/feexpay-javascript-sdk/index.js'></script>
        <script type='text/javascript'>
        FeexPayButton.init(" . json_encode((string) $componentId, $flags) . ", " . json_encode($config, $flags) . ");
        </script>";
    }

    public function getIdAndMarchanName()
    {
        $responseCurl = $this->curlGet($this->apiBaseUrl() . "/api/shop/" . rawurlencode((string) $this->id) . "/get_shop");
        if ($responseCurl === false) {
            return null;
        }

        return json_decode((string) $responseCurl);
    }

    /**
     * API host matching the configured mode (SANDBOX → sandbox API, otherwise production)
     */
    private function apiBaseUrl(): string
    {
        return strtoupper(trim((string) $this->mode)) === 'SANDBOX' ? self::SANDBOX_API_BASE_URL : self::API_BASE_URL;
    }

    /**
     * Send a GET request to the Feexpay API (authenticated with the API key)
     * @param string $url
     * @return string|false raw response body, false on network error
     */
    private function curlGet(string $url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token,
            ),
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::READ_TIMEOUT,
        ));

        $result = curl_exec($ch);
        if ($result === false) {
            $this->lastError = array('message' => curl_error($ch), 'response' => null);
        }
        curl_close($ch);

        return $result;
    }

    /**
     * Send a JSON POST request to the Feexpay API (authenticated with the API key)
     * @param string $url
     * @param array $post
     * @param array $options extra cURL options (override the defaults)
     * @return string|false raw response body, false on network error
     */
    private function curlPost(string $url, array $post, array $options = array())
    {
        $defaults = array(
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_URL => $url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_POSTFIELDS => json_encode($post),
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->token,
            ),
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
        );

        $ch = curl_init();
        curl_setopt_array($ch, ($options + $defaults));

        $result = curl_exec($ch);
        if ($result === false) {
            $this->lastError = array('message' => curl_error($ch), 'response' => null);
        }

        curl_close($ch);

        return $result;
    }

    /**
     * Remove spaces, dashes, dots, parentheses and the leading "+" / "00" from a phone number
     * e.g. "+242 06 123 45 67" => "242061234567"
     */
    private function normalizePhoneNumber(string $phoneNumber): string
    {
        $phoneNumber = preg_replace('/[\s\-\.\(\)]/', '', $phoneNumber);
        $phoneNumber = preg_replace('/^(\+|00)/', '', $phoneNumber);

        return $phoneNumber;
    }

    /**
     * Store the error returned by the API in lastError
     * @param mixed $data decoded API response
     * @param string $raw raw API response
     */
    private function setApiError($data, string $raw)
    {
        $message = "Réponse inattendue de l'API";
        if (is_object($data)) {
            foreach (array('message', 'error', 'errors', 'status') as $key) {
                if (!empty($data->$key)) {
                    $value = $data->$key;
                    $message = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
                    break;
                }
            }
        }

        $this->lastError = array(
            'message' => $message,
            'response' => $data !== null ? $data : $raw,
        );
    }

    /**
     * Error of the last paiementLocal() / paiementCard() call, null if it succeeded
     * @return array|null ['message' => string, 'response' => mixed (decoded API response)]
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Mobile money payment (MTN, MOOV, MTN CG, ...)
     * @return string|null the payment reference, or null on failure (see getLastError())
     */
    public function paiementLocal(float $amount, string $phoneNumber, string $operatorName, string $fullname, string $email, string $callback_info, string $custom_id, string $otp="")
    {
        $this->lastError = null;

        $post = array(
            "phoneNumber" => $this->normalizePhoneNumber($phoneNumber),
            "amount" => $amount,
            "reseau" => trim($operatorName),
            "shop" => $this->id,
            "first_name" => $fullname,
            "email" => $email,
            "callback_info" => $callback_info,
            "reference" => $custom_id,
            "otp" => $otp,
        );

        // No CURLOPT_TIMEOUT: the request waits for the customer to validate on their phone
        $responseCurlPostPaiement = $this->curlPost($this->apiBaseUrl() . "/api/transactions/requesttopay/integration", $post);
        if ($responseCurlPostPaiement === false) {
            return null;
        }

        $responseCurlPostPaiementData = json_decode($responseCurlPostPaiement);

        if (isset($responseCurlPostPaiementData->reference) && $responseCurlPostPaiementData->reference !== '') {
            return $responseCurlPostPaiementData->reference;
        }

        $this->setApiError($responseCurlPostPaiementData, $responseCurlPostPaiement);

        return null;
    }

    /**
     * Mobile money payment with a redirection page (Wave, Orange...)
     * @return array|false ['payment_url' => string, 'reference' => string, 'order_id' => string], false on failure (see getLastError())
     */
    public function requestToPayWeb(float $amount, string $phoneNumber, string $operatorName, string $fullname, string $email, string $callback_info, string $custom_id, string $cancel_url="https://feexpay.me/en", string $return_url="https://feexpay.me/en")
    {
        $this->lastError = null;

        $post = array("phoneNumber" => $phoneNumber, "amount" => $amount, "reseau" => $operatorName,
            "shop" => $this->id, "first_name" => $fullname, "email" => $email,
            "callback_info" => $callback_info, "reference" => $custom_id, 'return_url' => $return_url,
            'cancel_url' => $cancel_url);
        $responseCurlPostPaiement = $this->curlPost($this->apiBaseUrl() . "/api/transactions/requesttopay/integration", $post);
        if ($responseCurlPostPaiement === false) {
            return false;
        }

        $responseCurlPostPaiementData = json_decode((string) $responseCurlPostPaiement);

        if (!is_object($responseCurlPostPaiementData)
            || (isset($responseCurlPostPaiementData->status) && $responseCurlPostPaiementData->status == "FAILED")) {
            $this->setApiError($responseCurlPostPaiementData, (string) $responseCurlPostPaiement);
            return false;
        }

        $paymentUrl = $responseCurlPostPaiementData->payment_url ?? '';
        // Only https payment pages: the merchant redirects its customer to this URL
        if ($paymentUrl !== '' && strpos($paymentUrl, 'https://') !== 0) {
            $this->lastError = array('message' => 'URL de paiement refusée (https attendu)', 'response' => $responseCurlPostPaiementData);
            return false;
        }

        return array(
            'payment_url' => $paymentUrl,
            'reference' => $responseCurlPostPaiementData->reference ?? '',
            'order_id' => $responseCurlPostPaiementData->order_id ?? '',
        );
    }

    /**
     * Card payment (VISA, MASTERCARD): creates the transaction and returns the page where the customer pays
     * All arguments are required by the API.
     * @param float $amount
     * @param string $currency e.g. "XOF", "USD", "EUR"
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $phoneNumber country code + local number, e.g. "84986702980"
     * @param string $city
     * @param string $zip postal code
     * @param string $country ISO 3166-1 alpha-2 code, e.g. "BJ", "VN"
     * @return array|null ['url' => string, 'payment_url' => string, 'reference' => string], or null on failure (see getLastError())
     */
    public function paiementCard(float $amount, string $currency, string $firstName, string $lastName, string $email, string $phoneNumber, string $city, string $zip, string $country)
    {
        $this->lastError = null;

        $post = array(
            "shop" => $this->id,
            "amount" => $amount,
            "currency" => strtoupper(trim($currency)),
            "first_name" => trim($firstName),
            "last_name" => trim($lastName),
            "email" => trim($email),
            "phoneNumber" => $this->normalizePhoneNumber($phoneNumber),
            "city" => trim($city),
            "zip" => trim($zip),
            "country" => strtoupper(trim($country)),
        );

        $missing = array();
        foreach ($post as $key => $value) {
            if ($value === '' || $value === null) {
                $missing[] = $key;
            }
        }
        if ($amount <= 0) {
            $missing[] = 'amount';
        }
        if (!empty($missing)) {
            $this->lastError = array(
                'message' => 'Champs obligatoires manquants ou invalides : ' . implode(', ', $missing),
                'response' => null,
            );
            return null;
        }

        $responseCurlPostPaiement = $this->curlPost($this->apiBaseUrl() . "/api/transactions/public/requesttopay/init", $post);
        if ($responseCurlPostPaiement === false) {
            return null;
        }

        $responseCurlPostPaiementData = json_decode($responseCurlPostPaiement);

        if (isset($responseCurlPostPaiementData->payment_url) && $responseCurlPostPaiementData->payment_url !== '') {
            return array(
                'url' => $responseCurlPostPaiementData->payment_url,
                'payment_url' => $responseCurlPostPaiementData->payment_url,
                'reference' => $responseCurlPostPaiementData->reference ?? '',
            );
        }

        $this->setApiError($responseCurlPostPaiementData, $responseCurlPostPaiement);

        return null;
    }

    /**
     * Status of a payment
     * @return array|false ['amount', 'clientNum', 'status', 'reference', 'shop'], false on failure (see getLastError())
     */
    public function getPaiementStatus($paiementRef)
    {
        $this->lastError = null;

        if (!$paiementRef) {
            $this->lastError = array('message' => 'REFERENCE_INVALID', 'response' => null);
            return false;
        }

        $responseCurlStatus = $this->curlGet($this->apiBaseUrl() . "/api/transactions/public/single/status/" . rawurlencode((string) $paiementRef));
        if ($responseCurlStatus === false) {
            return false;
        }

        $statusData = json_decode((string) $responseCurlStatus);
        if (!is_object($statusData) || !isset($statusData->status)) {
            $this->setApiError($statusData, (string) $responseCurlStatus);
            return false;
        }

        // API v2 returns the payer number as "phoneNumber" (v1 used payer->partyId)
        $clientNum = $statusData->phoneNumber ?? $statusData->phone_number ?? $statusData->payer->partyId ?? '';

        return array(
            "amount" => $statusData->amount ?? null,
            "clientNum" => $clientNum,
            "status" => $statusData->status,
            "reference" => $statusData->reference ?? $paiementRef,
            "shop" => $statusData->shop ?? null,
        );
    }

    /**
     * Server-side check before delivering an order: the payment is successful, covers the
     * expected amount and belongs to this shop. The amount and the JavaScript callback come
     * from the customer's browser and must never be trusted on their own.
     */
    public function isPaymentSuccessful($paiementRef, float $expectedAmount): bool
    {
        $status = $this->getPaiementStatus($paiementRef);
        if ($status === false) {
            return false;
        }

        if (!in_array(strtoupper((string) $status['status']), array('SUCCESSFUL', 'SUCCESS'), true)) {
            $this->lastError = array('message' => 'Paiement non réussi : ' . $status['status'], 'response' => $status);
            return false;
        }

        // The API rounds the amount to the unit
        if (!is_numeric($status['amount']) || (float) $status['amount'] < floor($expectedAmount)) {
            $this->lastError = array('message' => 'Montant payé inférieur au montant attendu', 'response' => $status);
            return false;
        }

        if (!empty($status['shop']) && $status['shop'] !== $this->id) {
            $this->lastError = array('message' => 'Paiement effectué sur un autre shop', 'response' => $status);
            return false;
        }

        return true;
    }
}
