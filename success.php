<?php
require 'vendor/autoload.php';

use Feexpay\FeexpayPhp\FeexpayClass;

// Configuration
$id = "671a774c706593edb3dc4ab2";
$token = "fp_HHNoQGt9Vn8KpZoLaBkG3uEeKpLUYBaHUZIZXJE3Xgv0OKG2tK3A7PtlytctikrJ";
$callback_url = ""; // inutile ici
$error_callback_url = "";
$mode = "LIVE";

// Instance de Feexpay
$feexpay = new FeexpayClass($id, $token, $callback_url, $mode, $error_callback_url);

// 1. Récupérer la référence (id_transaction, ref, ou autre clé envoyée par FeexPay)
$reference = $_GET['id_transaction'] ?? $_GET['ref'] ?? $_POST['ref'] ?? null;

if ($reference) {
    // 2. Appeler getPaiementStatus
    $status = $feexpay->getPaiementStatus($reference);

    if ($status) {
        // 3. Afficher les données
        echo "<h2>Paiement traité avec succès</h2>";
        echo "<ul>";
        echo "<li>Montant : {$status['amount']} XOF</li>";
        echo "<li>Numéro client : {$status['clientNum']}</li>";
        echo "<li>Statut : {$status['status']}</li>";
        echo "<li>Référence : {$status['reference']}</li>";
        echo "</ul>";
    } else {
        echo "Erreur lors de la récupération du statut du paiement.";
    }
} else {
    echo "Référence de transaction manquante.";
}
