<?php
require 'vendor/autoload.php';

include 'src/FeexpayClass.php'; 
   $price = 500;
   $id = "671a774c706593edb3dc4ab2";
   $token = "fp_HHNoQGt9Vn8KpZoLaBkG3uEeKpLUYBaHUZIZXJE3Xgv0OKG2tK3A7PtlytctikrJ";
   $callback_url = 'https://www.google.com';
   $mode = 'LIVE';
   $feexpayclass = new Feexpay\FeexpayPhp\FeexpayClass($id, $token, $callback_url, $mode);
   $result = $feexpayclass->init($price, "button_payee");
   ?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement FeexPay</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #333;
        }
        .product-info {
            margin-bottom: 1.5rem;
        }
        .product-info p {
            font-size: 1.1rem;
            color: #555;
        }
        #button_payee {
            margin-top: 1.5rem;
        }
        .custom-button {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .custom-button:hover {
            background-color: #0056b3;
        }
        .footer {
            margin-top: 2rem;
            font-size: 0.9rem;
            color: #777;
        }
    </style>
</head>
<body>
  <div id='button_payee'></div>
    <?php
    // Initialisation du paiement
    $result = $feexpay->init(5000, "button_payee", false, "", "Achat de T-shirt", "info_callback");
    // Pour utiliser un bouton personnalisé, décommentez la ligne suivante :
    // $result = $feexpay->init(5000, "button_payee", true, "custom_button", "Achat de T-shirt", "info_callback");
    ?>
</body>
</html>