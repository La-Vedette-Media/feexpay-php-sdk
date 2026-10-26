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
        $this->lastError = array(
            'message' => $this->interpretApiError($data),
            'response' => $data !== null ? $data : $raw,
        );
    }

    /**
     * True when the decoded response is an API error ({ statusCode: 4xx/5xx, ... })
     * @param mixed $data decoded API response
     */
    private function isApiError($data): bool
    {
        return is_object($data) && isset($data->statusCode) && is_int($data->statusCode) && $data->statusCode >= 400;
    }

    /**
     * Turn an API error response into a readable message for the payer.
     * Formats returned by the API:
     *  - business error   : { statusCode, message, code? }
     *  - validation error : { statusCode: 400, message: "Validation failed", errors: [{ property, constraints }] }
     *  - operator error   : { message: "Wave API Error", code: "WAVE_API_ERROR", details: <operator response> }
     *                       or { error: "Wave Sénégal API Error", message: <operator response> }
     *  - 5xx error        : { statusCode, message: "Internal Server Error" } (details hidden by the API)
     * @param mixed $data decoded API response
     */
    private function interpretApiError($data): string
    {
        $fallback = "La demande de paiement a échoué. Veuillez réessayer.";
        if (!is_object($data)) {
            return "Réponse inattendue de l'API";
        }

        $statusCode = $data->statusCode ?? null;
        if (is_int($statusCode) && $statusCode >= 500) {
            return "Service momentanément indisponible. Veuillez réessayer plus tard.";
        }

        $code = isset($data->code) && is_string($data->code) ? $data->code : '';

        if (isset($data->errors) && is_array($data->errors)) {
            $fieldMessages = array(
                'phoneNumber' => "Numéro de téléphone invalide.",
                'amount' => "Montant invalide : il doit être compris entre 10 et 2 000 000.",
                'email' => "Adresse email invalide.",
                'reseau' => "Ce réseau n'est pas pris en charge.",
                'shop' => "Identifiant de boutique manquant.",
                'description' => "La description ne doit pas dépasser 40 caractères.",
                'callback_info' => "Le champ callback_info est invalide.",
                'first_name' => "Nom invalide.",
                'otp' => "Code OTP invalide.",
            );
            $messages = array();
            foreach ($data->errors as $error) {
                $property = is_object($error) && isset($error->property) ? $error->property : '';
                if (isset($fieldMessages[$property])) {
                    $messages[] = $fieldMessages[$property];
                } elseif (is_object($error) && !empty($error->constraints) && is_array($error->constraints)) {
                    $messages[] = (string) $error->constraints[0];
                }
            }
            if (!empty($messages)) {
                return implode(' ', array_unique($messages));
            }
        }

        $error = isset($data->error) && is_string($data->error) ? $data->error : '';
        if (preg_match('/_(API|DISBURSEMENT)_ERROR$/', $code) || preg_match('/API Error$/', $error)) {
            $details = $data->details ?? (isset($data->message) && !is_string($data->message) ? $data->message : null);
            return $this->interpretOperatorError($this->operatorName($code, $error), $details);
        }
        if (preg_match('/_COMMUNICATION_ERROR$/', $code)) {
            return $this->operatorName($code, $error) . " est momentanément injoignable. Veuillez réessayer plus tard.";
        }

        $codeMessages = array(
            'UNAUTHORIZED' => "Clé API invalide.",
            'API_KEY_BLOCKED' => "La clé API est bloquée.",
            'ACCESS_DENIED' => "Ce moyen de paiement n'est pas activé pour ce marchand.",
            'NETWORK_UNAVAILABLE' => "Ce réseau est en maintenance. Veuillez réessayer plus tard.",
            'NOT_FOUND' => "Transaction introuvable.",
        );
        if (isset($codeMessages[$code])) {
            return $codeMessages[$code];
        }

        $message = $data->message ?? null;
        if (is_array($message)) {
            $message = implode(', ', $message);
        }
        if (is_string($message) && $message !== '') {
            $businessMessages = array(
                '/^Merchant or Shop not found$/i' => "Boutique introuvable : vérifiez l'identifiant de la boutique et la clé API.",
                '/^Shop is disabled$/i' => "Cette boutique est désactivée.",
                '/Wave aggregated merchant not configured/i' => "Le paiement Wave n'est pas encore activé pour ce marchand.",
                '/channel not configured/i' => "Ce réseau n'est pas disponible pour le moment.",
                '/not available yet/i' => "Ce réseau n'est pas encore disponible.",
                '/under maintenance|^Network Unavailable$/i' => "Ce réseau est en maintenance. Veuillez réessayer plus tard.",
                '/otp is required/i' => "Le code OTP est requis.",
            );
            foreach ($businessMessages as $pattern => $readable) {
                if (preg_match($pattern, $message)) {
                    return $readable;
                }
            }
            if ($message !== 'Validation failed' && $message !== 'Bad Request') {
                return $message;
            }
        }

        if (isset($data->status) && is_string($data->status) && $data->status !== '') {
            return $data->status;
        }

        return $fallback;
    }

    /**
     * Operator name from the error code (WAVE_API_ERROR) or label (Wave Sénégal API Error)
     */
    private function operatorName(string $code, string $error): string
    {
        $operators = array(
            'WAVE' => 'Wave', 'MTN' => 'MTN', 'MOOV_BJ' => 'Moov', 'MOOV_CI' => 'Moov', 'MOOV_TG' => 'Moov',
            'CELTIIS_BJ' => 'Celtiis', 'TOGOCOM' => 'Togocom',
        );
        $key = preg_replace('/_(API|COMMUNICATION)_ERROR$|_DISBURSEMENT_ERROR$/', '', $code);
        if (isset($operators[$key])) {
            return $operators[$key];
        }
        if (preg_match('/^(Wave|Orange|Free)/', $error, $match)) {
            return $match[1];
        }

        return "l'opérateur";
    }

    /**
     * Readable message from the operator response forwarded by the API (Wave, MTN...)
     * @param mixed $details
     */
    private function interpretOperatorError(string $operator, $details): string
    {
        $fallback = "Le paiement a été refusé par " . $operator . ". Veuillez réessayer.";
        if (is_string($details) && $details !== '') {
            return $operator . " : " . $details;
        }
        if (!is_object($details)) {
            return $fallback;
        }

        $validation = isset($details->details) && is_array($details->details) ? $details->details : array();
        foreach ($validation as $item) {
            $loc = is_object($item) && isset($item->loc) && is_array($item->loc) ? $item->loc : array();
            if (preg_grep('/mobile|phone|msisdn/i', array_map('strval', $loc))) {
                $phone = preg_match('/\+?\d{6,}/', (string) ($item->msg ?? ''), $match) ? " (" . $match[0] . ")" : "";
                return "Numéro de téléphone invalide pour " . $operator . $phone . ". Vérifiez le numéro saisi.";
            }
            if (in_array('amount', $loc, true)) {
                return "Montant refusé par " . $operator . ".";
            }
        }

        $operatorCodes = array(
            // Wave
            'insufficient-funds' => "Solde Wave insuffisant.",
            'blocked-account' => "Ce compte Wave est bloqué.",
            'recipient-account-blocked' => "Ce compte Wave est bloqué.",
            'recipient-account-inactive' => "Ce compte Wave n'est pas actif.",
            'recipient-limit-exceeded' => "Le plafond de ce compte Wave est atteint.",
            'recipient-minor' => "Ce compte Wave ne peut pas effectuer ce paiement.",
            'country-mismatch' => "Ce numéro n'est pas un compte Wave de ce pays.",
            'currency-mismatch' => "Cette devise n'est pas acceptée par Wave.",
            'invalid-aggregated-merchant-id' => "Le paiement Wave n'est pas activé pour ce marchand.",
            'aggregated-merchant-required' => "Le paiement Wave n'est pas activé pour ce marchand.",
            'too-many-requests' => "Trop de tentatives. Veuillez patienter quelques instants.",
            'service-unavailable' => "Wave est momentanément indisponible. Veuillez réessayer plus tard.",
            'internal-server-error' => "Wave est momentanément indisponible. Veuillez réessayer plus tard.",
            // MTN MoMo
            'PAYER_NOT_FOUND' => "Ce numéro n'a pas de compte MTN Mobile Money.",
            'NOT_ENOUGH_FUNDS' => "Solde MTN Mobile Money insuffisant.",
            'PAYER_LIMIT_REACHED' => "Le plafond de ce compte MTN Mobile Money est atteint.",
            'NOT_ALLOWED' => "Ce compte MTN Mobile Money n'est pas autorisé à payer.",
            'NOT_ALLOWED_TARGET_ENVIRONMENT' => "Ce compte MTN Mobile Money n'est pas autorisé à payer.",
            'INVALID_CURRENCY' => "Cette devise n'est pas acceptée par MTN.",
            'SERVICE_UNAVAILABLE' => "MTN est momentanément indisponible. Veuillez réessayer plus tard.",
            'INTERNAL_PROCESSING_ERROR' => "MTN est momentanément indisponible. Veuillez réessayer plus tard.",
            'COULD_NOT_PERFORM_TRANSACTION' => "MTN n'a pas pu effectuer la transaction. Veuillez réessayer.",
        );
        $operatorCode = isset($details->code) && is_string($details->code) ? $details->code : '';
        if (isset($operatorCodes[$operatorCode])) {
            return $operatorCodes[$operatorCode];
        }

        foreach (array('message', 'reason', 'error_description', 'error') as $key) {
            if (isset($details->$key) && is_string($details->$key) && $details->$key !== ''
                && strcasecmp($details->$key, 'Request invalid') !== 0) {
                return $operator . " : " . $details->$key;
            }
        }

        return $fallback;
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
            || $this->isApiError($responseCurlPostPaiementData)
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
