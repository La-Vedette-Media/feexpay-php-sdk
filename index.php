<?php
require 'vendor/autoload.php';

// Configuration FeexPay
$id = "671a774c706593edb3dc4ab2";
$token = "fp_HHNoQGt9Vn8KpZoLaBkG3uEeKpLUYBaHUZIZXJE3Xgv0OKG2tK3A7PtlytctikrJ";
$callback_url = "http://localhost:8000/success.php";
$error_callback_url = "http://localhost:8000/error.php";
$mode = "LIVE"; // ou "SANDBOX" pour les tests

// Initialisation de FeexPay
$feexpay = new Feexpay\FeexpayPhp\FeexpayClass($id, $token, $callback_url, $mode, $error_callback_url);
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
    <div class="container">
        <h1>Votre Panier</h1>
        
        <div class="product-info">
            <p><strong>Produit :</strong> T-shirt personnalisé</p>
            <p><strong>Prix :</strong> 10 XOF</p>
        </div>

        <!-- Bouton de paiement FeexPay -->
        <div id="button_payee"></div>

        <!-- Bouton personnalisé (optionnel) -->
        <!-- <button id="custom_button" class="custom-button">Payer avec FeexPay</button> -->

        <div class="footer">
            <p>Paiement sécurisé par FeexPay</p>
        </div>
    </div>

    <?php
    // Initialisation du paiement
    $result = $feexpay->init(10, "button_payee", false, "", "Achat de T-shirt", "info_callback");
    
    // $response = $feexpay->paiementLocal("10","2290160661600","MOOV","John Doe","adinsiabdias@gmail.com","","");
    $status = $feexpay->getPaiementStatus($result);
    
    // $status = $feexpay->getPaiementStatus($response);
    // var_dump($status);
    // Pour utiliser un bouton personnalisé, décommentez la ligne suivante :
    // $result = $feexpay->init(5000, "button_payee", true, "custom_button", "Achat de T-shirt", "info_callback");
    ?>
</body>
</html>