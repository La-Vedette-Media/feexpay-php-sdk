# FeexpayPhp


## Feexpay SDK PHP Project - User Guide

This guide explains how to use the Feexpay PHP SDK to easily integrate mobile and card payment methods into your PHP or Laravel application. Follow these steps to get started:

> **Version 2.1.0** — the SDK now uses the Feexpay API v2 (`https://api-v2.feexpay.me`). The previous host (`https://api.feexpay.me`) is no longer available, so older versions of the SDK no longer work: please upgrade.

### Requirements

- PHP 7.1 or higher
- The `curl` and `json` PHP extensions
- Your shop's id and your API key (it starts with `fp_`), available in your Feexpay dashboard

### Installation

1. Install a local server like Xampp or Wamp etc ...

2. Install Composer if not already done.

3. Check that Composer is installed by running the following command:
   ```
   composer --version
   ```

4. Install the Feexpay package in your project:
   ```
   composer require feexpay/feexpay-php
   ```

   To upgrade an existing project:
   ```
   composer update feexpay/feexpay-php
   ```

### Usage in a Simple PHP Environment

1. Create your PHP project and install the package with Composer (see above).

   You can also download the Git repository instead:
   ```
   git clone https://github.com/La-Vedette-Media/feexpay-php-sdk.git
   ```

2. Create a PHP file, for example, `index.php`.

3. Use the SDK methods in your PHP file:

   ```php
   <?php
   require 'vendor/autoload.php';
   // Without Composer: include 'src/FeexpayClass.php';

   $skeleton = new Feexpay\FeexpayPhp\FeexpayClass("shop's id", "token key API", "callback_url", "mode (LIVE, SANDBOX)");
   // LIVE → https://api-v2.feexpay.me, SANDBOX → https://sandbox-api.feexpay.me (use your sandbox shop id and API key)

   // Using the mobile network payment method (MTN, MOOV, MTN CG, ...)
   // paiementLocal(amount, phoneNumber, network, fullname, email, callback_info, custom_id, otp = "")
   // phoneNumber: country code + local number, e.g. Benin 229 + 0166000000 => "2290166000000"
   $reference = $skeleton->paiementLocal(100, "2290166000000", "MTN", "Jon Doe", "jondoe@gmail.com", "order 123", "my-ref-123");

   if ($reference === null) {
       // The API refused the payment: show the real reason
       $error = $skeleton->getLastError();
       echo $error["message"];      // message returned by the API
       var_dump($error["response"]); // full API response, useful for support
   } else {
       $status = $skeleton->getPaiementStatus($reference);
       var_dump($status);
   }
   ?>
   ```

4. Check the status of a payment with its reference:

   ```php
   <?php
   $status = $skeleton->getPaiementStatus($reference);
   // [
   //     "amount"    => 100,
   //     "clientNum" => "2290166000000",
   //     "status"    => "SUCCESSFUL",   // or "PENDING", "FAILED"
   //     "reference" => "63ecaff1-7572-413d-93bb-8cba92bb8c2c",
   //     "shop"      => "shop's id",
   // ]
   ?>
   ```

   `getPaiementStatus()` returns `false` when the reference is empty or the request fails
   (reason in `getLastError()`).

   **Before delivering an order**, check the payment on your server. The amount and the
   JavaScript callback come from the customer's browser and can be modified:

   ```php
   <?php
   // Third argument: currency of the order. Amounts are never converted: a 5 000 NGN payment
   // (Nigeria) does not pay a 5 000 XOF order. Without it, only XOF / XAF payments are accepted.
   if ($skeleton->isPaymentSuccessful($reference, $orderTotal, "XOF")) {
       // successful, amount >= $orderTotal, in XOF and paid to this shop
   } else {
       echo $skeleton->getLastError()["message"];
   }
   ?>
   ```

5. Use the web payment method for the networks that redirect the customer to a payment page:

   ```php
   <?php
   // requestToPayWeb(amount, phoneNumber, network, fullname, email, callback_info, custom_id, cancel_url, return_url)
   $response = $skeleton->requestToPayWeb(100, "2250700000000", "network", "Jon Doe", "jondoe@gmail.com", "order 123", "my-ref-124", "https://your-site.com/cancel", "https://your-site.com/return");

   if ($response === false || empty($response["payment_url"])) {
       echo "Payment could not be initialized: " . ($skeleton->getLastError()["message"] ?? "");
   } else {
       // $response["reference"] and $response["order_id"] identify the payment
       header("Location: " . $response["payment_url"]);
       exit();
   }
   ?>
   ```

6. Use the B-MO payment method (Benin, `LIVE` mode only). The payment is done in two steps:
   B-MO sends a confirmation code by SMS to the payer, then you confirm it with this code.
   Nothing is debited before the confirmation.

   ```php
   <?php
   // paiementBmo(amount, phoneNumber, firstName, lastName, email = "", description = "", callback_info = "")
   // amount: between 200 and 2 000 000. First and last name are required for a payer who is not yet a B-MO customer
   $bmo = $skeleton->paiementBmo(1000, "2290166000000", "Jon", "Doe", "jondoe@gmail.com", "Order 123");

   if ($bmo === null || $bmo["status"] === "FAILED") {
       echo $skeleton->getLastError()["message"]; // e.g. "Solde B-MO insuffisant pour effectuer ce paiement."
   } elseif ($bmo["status"] === "PENDING") {
       // Ask the payer for the code received by SMS, then:
       $result = $skeleton->confirmPaiementBmo($bmo["reference"], $otp);

       if ($result !== null && $result["status"] === "SUCCESSFUL") {
           // paid
       } elseif ($result === null) {
           // e.g. wrong or expired code: the payment stays PENDING, the payer can try again
           echo $skeleton->getLastError()["message"];
       } else {
           echo $result["message"] ?? "";  // FAILED: readable reason ("reason" holds the raw B-MO reason)
       }

       // If the payer gives up: $skeleton->cancelPaiementBmo($bmo["reference"]);
   }
   ?>
   ```

7. Use the Djamo payment method (Côte d'Ivoire, Djamo account to Djamo account). The API returns a Djamo
   page: redirect the payer to it, they validate the payment in their Djamo app, then Djamo sends them back
   to `return_url` (or `cancel_url` if they cancel). Without payment within 1 hour, the payment becomes `FAILED`.

   ```php
   <?php
   // paiementDjamo(amount, phoneNumber, firstName = "", lastName = "", email = "", description = "",
   //               callback_info = "", return_url = "", cancel_url = "")
   // amount: between 10 and 2 000 000 XOF. phoneNumber: 225 + 10 digits.
   // return_url / cancel_url default to the callback_url / error_callback_url given to the constructor.
   $djamo = $skeleton->paiementDjamo(1000, "2250700000000", "Jon", "Doe", "jondoe@gmail.com", "Order 123");

   if ($djamo === null) {
       echo $skeleton->getLastError()["message"];
   } else {
       // Keep $djamo["reference"] to check the payment later
       header("Location: " . $djamo["payment_url"]);
       exit();
   }
   ?>
   ```

   On `return_url`, always confirm the payment server-side before delivering:
   `$skeleton->isPaymentSuccessful($reference, 1000)`.

8. Nigeria (amounts in NGN). Two methods:

   - **Bank transfer**: the API creates a temporary bank account. Show it to the payer, who transfers
     exactly `amount_expected` to it from their bank before `expires_at` (about 30 minutes).
   - **Pay with Bank**: the payer chooses their bank (e.g. Opay, PalmPay) and authorizes the payment on
     its page, then comes back to `redirect_url`.

   ```php
   <?php
   // paiementBankTransferNg(amount, firstName = "", lastName = "", email = "", description = "", callback_info = "")
   // amount: between 100 and 10 000 000 NGN.
   $transfer = $skeleton->paiementBankTransferNg(5000, "Jon", "Doe", "jondoe@gmail.com", "Order 123");

   if ($transfer === null) {
       echo $skeleton->getLastError()["message"];
   } else {
       // Keep $transfer["reference"] to check the payment later
       $account = $transfer["bank_account"];
       echo "Transfer " . $transfer["amount_expected"] . " NGN to " . $account["account_number"]
           . " (" . $account["bank_name"] . ", " . $account["account_name"] . ")";
   }

   // getPayWithBankNgBanks(): list of ['code' => ..., 'name' => ...] to let the payer choose their bank
   $banks = $skeleton->getPayWithBankNgBanks();

   // paiementPayWithBankNg(amount, bankCode, firstName = "", lastName = "", email = "", description = "",
   //                       callback_info = "", redirect_url = "")
   // amount: between 200 and 10 000 000 NGN. redirect_url defaults to the constructor's callback_url.
   $payWithBank = $skeleton->paiementPayWithBankNg(5000, $banks[0]["code"], "Jon", "Doe", "jondoe@gmail.com", "Order 123");

   if ($payWithBank === null) {
       echo $skeleton->getLastError()["message"];
   } else {
       header("Location: " . $payWithBank["payment_url"]);
       exit();
   }
   ?>
   ```

   In both cases the payment stays `PENDING` until it is received: confirm it server-side (webhook or
   `$skeleton->isPaymentSuccessful($reference, 5000, "NGN")`) before delivering. Nigeria must be enabled on your
   FeexPay account.

9. Use the card payment method (VISA, MASTERCARD):

   ```php
   <?php
   // paiementCard(amount, phoneNumber, typeCard, firstName, lastName, email, country, address, district, currency, callback_info, custom_id)
   $responseCard = $skeleton->paiementCard(100, "2290166000000", "VISA", "Jon", "Doe", "jondoe@gmail.com", "BJ", "Cotonou", "Littoral", "XOF", "order 123", "my-ref-125");

   if (isset($responseCard["url"])) {
       header("Location: " . $responseCard["url"]);
       exit();
   } else {
       echo "Card payment could not be initialized";
   }
   ?>
   ```

10. You can also integrate a payment button in your PHP page:

   ```php
   <?php
   require 'vendor/autoload.php';
   $price = 50;
   $id = "shop's id";
   $token = "token key API";
   $callback_url = 'https://www.google.com';
   $mode = 'LIVE';
   $feexpayclass = new Feexpay\FeexpayPhp\FeexpayClass($id, $token, $callback_url, $mode);
   $result = $feexpayclass->init($price, "button_payee");
   ?>
   <div id='button_payee'></div>
   ```

### Usage with Laravel

1. In a Laravel project, run the following command to install the Feexpay package:
   ```
   composer require feexpay/feexpay-php
   ```

2. Create the routes in your `web.php` file:
   ```php
   Route::controller(YourController::class)->group(function () {
       Route::get('feexpay', 'feexpay')->name('feexpay');
       Route::get('feexpay-card', 'feexpayCard')->name('feexpay-card');
   });
   ```

3. Create a controller, for example, `YourController.php`, and use the Feexpay SDK inside this controller to handle payments:

   ```php
   <?php

   namespace App\Http\Controllers;
   use Feexpay\FeexpayPhp\FeexpayClass;
   use Illuminate\Http\Request;

   class YourController extends Controller
   {
       // Using the mobile network payment method (MTN, MOOV, MTN CG, ...)
       public function feexpay()
       {
            $skeleton = new FeexpayClass("shop's id", "token key API", "callback_url", "mode (LIVE, SANDBOX)");
            $reference = $skeleton->paiementLocal(100, "2290166000000", "MTN", "Jon Doe", "jondoe@gmail.com", "order 123", "my-ref-123");
            if ($reference === null) {
                return response($skeleton->getLastError()["message"])->setStatusCode(422);
            }
            $status = $skeleton->getPaiementStatus($reference);
            return response()->json($status);
       }

       // Using the card payment method (VISA, MASTERCARD)
       public function feexpayCard()
       {
            $skeleton = new FeexpayClass("shop's id", "token key API", "callback_url", "mode (LIVE, SANDBOX)");
            $responseCard = $skeleton->paiementCard(100, "2290166000000", "VISA", "Jon", "Doe", "jondoe@gmail.com", "BJ", "Cotonou", "Littoral", "XOF", "order 123", "my-ref-125");

            if ($responseCard !== null) {
                return redirect()->away($responseCard["payment_url"]);
            } else {
                // The API refused the payment: show the real reason
                return response($skeleton->getLastError()["message"])->setStatusCode(422);
            }
       }
   }
   ```

4. Integrate the Feexpay button in a view, for example, `welcome.blade.php`:


Create a route:
```php
Route::controller(YourController::class)->group(function () {
    Route::get('payment', 'payment')->name('payment') ;
}) ;
   ```

Create a controller by example YourController.php

   ```php

namespace App\Http\Controllers;
use Feexpay\FeexpayPhp\FeexpayClass;
use Illuminate\Http\Request;

class YourController extends Controller
{
     public function payment()
    {
            $data['price']          =  $price = 50;
            $data['id']             =  $id= "shop's id";
            $data['token']          =  $token= "token key API";
            $data['callback_url']   =  $callback_url= 'https://www.google.com';
            $data['mode']           =  $mode='LIVE';
            $data['feexpayclass']   =  $feexpayclass = new FeexpayClass($id, $token, $callback_url, $mode);
            $data['result']         =  $result = $feexpayclass->init($price, "button_payee");

            return view('welcome', $data);
    }
}
   ```
Make sure you have your views file for our example is welcome.blade.php


   ```html
   <div id='button_payee'></div>
   ```

You can now access the URL defined in the route to perform payments using Feexpay.

### Security

Never commit your API key to a repository: keep it in an environment variable (for example in your `.env` file with Laravel) and read it from there.

### Documentation

The full API documentation (networks, countries, webhooks) is available at [https://docs.feexpay.me](https://docs.feexpay.me).


Make sure to adapt values like "shop's id", "token key API", addresses, amounts, and other details according to your own configuration and needs.
