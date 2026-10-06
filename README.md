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
   // ]
   ?>
   ```

   `getPaiementStatus()` returns `false` when the reference is empty.

5. Use the web payment method for the networks that redirect the customer to a payment page:

   ```php
   <?php
   // requestToPayWeb(amount, phoneNumber, network, fullname, email, callback_info, custom_id, cancel_url, return_url)
   $response = $skeleton->requestToPayWeb(100, "2250700000000", "network", "Jon Doe", "jondoe@gmail.com", "order 123", "my-ref-124", "https://your-site.com/cancel", "https://your-site.com/return");

   if ($response === false || empty($response["payment_url"])) {
       echo "Payment could not be initialized";
   } else {
       // $response["reference"] and $response["order_id"] identify the payment
       header("Location: " . $response["payment_url"]);
       exit();
   }
   ?>
   ```

6. Use the card payment method (VISA, MASTERCARD):

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

7. You can also integrate a payment button in your PHP page:

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
