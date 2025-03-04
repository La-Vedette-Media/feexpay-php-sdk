
<?php
require_once 'vendor/autoload.php'; // Charger les dépendances

use Feexpay\FeexpayPhp\FeexpayClass;

$shopId = "671a774c706593edb3dc4ab2"; // Remplace par ton ID de boutique
$apiKey = "fp_HHNoQGt9Vn8KpZoLaBkG3uEeKpLUYBaHUZIZXJE3Xgv0OKG2tK3A7PtlytctikrJ"; // Remplace par ta clé API
$callbackUrl = "https://votre-site.com/success"; 
$errorCallbackUrl = "https://votre-site.com/erreur";
$mode = "LIVE"; // Ou "SANDBOX" si tu veux tester sans transactions réelles

$feexpay = new FeexpayClass($shopId, $apiKey, $callbackUrl, $mode, $errorCallbackUrl);

$montant = 100; // Montant du paiement
$description = "Achat de produit numérique";
$callbackInfo = "Transaction123"; // Info additionnelle

// Générer le bouton de paiement
$result = $feexpay->init($montant, "button_payee", false, "", $description, $callbackInfo);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Paiement FeexPay</title>
</head>
<body>
<h2>Paiement FeexPay</h2>
<div id="button_payee"></div>
</body>
</html>

