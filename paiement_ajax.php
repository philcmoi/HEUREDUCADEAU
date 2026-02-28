<?php
/**
 * paiement_ajax.php - API pour créer une commande avant paiement
 * VERSION CORRIGÉE
 */

require_once __DIR__ . '/session_verification.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Vérifier l'accès
if (!hasValidCart() || !hasShippingAddress()) {
    echo json_encode(['success' => false, 'message' => 'Panier vide ou adresse manquante']);
    exit;
}

$pdo = getPDOConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion BDD']);
    exit;
}

try {
    $checkout = $_SESSION[SESSION_KEY_CHECKOUT] ?? [];
    $adresse = $checkout['adresse_livraison'] ?? [];
    
    // Vérifier si le client existe
    $client_id = $checkout['client_id'] ?? null;
    
    if (!$client_id) {
        // Créer un client temporaire
        $email = $adresse['email'] ?? 'temp_' . uniqid() . '@temp.com';
        $stmt = $pdo->prepare("INSERT INTO clients (email, nom, prenom, is_temporary, date_inscription) VALUES (?, ?, ?, 1, NOW())");
        $stmt->execute([$email, $adresse['nom'] ?? 'Client', $adresse['prenom'] ?? 'Temporaire']);
        $client_id = $pdo->lastInsertId();
        
        // Créer l'adresse
        $stmt_addr = $pdo->prepare("
            INSERT INTO adresses (id_client, nom, prenom, adresse, complement, code_postal, ville, pays, telephone, type_adresse, principale)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'livraison', 1)
        ");
        $stmt_addr->execute([
            $client_id,
            $adresse['nom'] ?? '',
            $adresse['prenom'] ?? '',
            $adresse['adresse'] ?? '',
            $adresse['complement'] ?? null,
            $adresse['code_postal'] ?? '',
            $adresse['ville'] ?? '',
            $adresse['pays'] ?? 'France',
            $adresse['telephone'] ?? null
        ]);
        $adresse_id = $pdo->lastInsertId();
        
        // Sauvegarder en session
        $_SESSION[SESSION_KEY_CLIENT_ID] = $client_id;
        $checkout['client_id'] = $client_id;
        $checkout['adresse_livraison']['id'] = $adresse_id;
        $_SESSION[SESSION_KEY_CHECKOUT] = $checkout;
    } else {
        // Client existant, récupérer ou créer l'adresse
        $stmt = $pdo->prepare("SELECT id_adresse FROM adresses WHERE id_client = ? AND principale = 1 AND type_adresse = 'livraison' LIMIT 1");
        $stmt->execute([$client_id]);
        $addr = $stmt->fetch();
        
        if ($addr) {
            $adresse_id = $addr['id_adresse'];
        } else {
            // Créer une nouvelle adresse
            $stmt_addr = $pdo->prepare("
                INSERT INTO adresses (id_client, nom, prenom, adresse, complement, code_postal, ville, pays, telephone, type_adresse, principale)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'livraison', 1)
            ");
            $stmt_addr->execute([
                $client_id,
                $adresse['nom'] ?? '',
                $adresse['prenom'] ?? '',
                $adresse['adresse'] ?? '',
                $adresse['complement'] ?? null,
                $adresse['code_postal'] ?? '',
                $adresse['ville'] ?? '',
                $adresse['pays'] ?? 'France',
                $adresse['telephone'] ?? null
            ]);
            $adresse_id = $pdo->lastInsertId();
        }
        $checkout['adresse_livraison']['id'] = $adresse_id;
        $_SESSION[SESSION_KEY_CHECKOUT] = $checkout;
    }
    
    // Calculer les totaux
    $sous_total = 0;
    $items_data = [];
    
    foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
        $produit = getProductDetails($item['id_produit'], $pdo);
        $prix_ttc = floatval($produit['prix_ttc'] ?? $item['prix'] ?? 0);
        $quantite = intval($item['quantite'] ?? 1);
        $sous_total += $prix_ttc * $quantite;
        
        $items_data[] = [
            'id_produit' => $item['id_produit'],
            'reference' => $produit['reference'] ?? 'REF' . $item['id_produit'],
            'nom' => $produit['nom'] ?? 'Produit',
            'quantite' => $quantite,
            'prix_unitaire_ttc' => $prix_ttc,
            'prix_unitaire_ht' => round($prix_ttc / 1.2, 2),
            'tva' => 20.00
        ];
    }
    
    // Frais de livraison
    $mode_livraison = $checkout['mode_livraison'] ?? 'standard';
    $frais_livraison = 0;
    
    if ($mode_livraison === 'express') {
        $frais_livraison = 9.90;
    } elseif ($mode_livraison === 'relais') {
        $frais_livraison = 4.90;
    } elseif ($sous_total < 50) {
        $frais_livraison = 4.90;
    }
    
    $frais_emballage = ($checkout['emballage_cadeau'] ?? false) ? 3.90 : 0;
    $total = round($sous_total + $frais_livraison + $frais_emballage, 2);
    
    // Créer la commande
    $pdo->beginTransaction();
    
    $adresse_facturation_id = $checkout['adresse_facturation']['id'] ?? $adresse_id;
    $client_type = ($checkout['is_guest'] ?? true) ? 'guest' : 'registered';
    $instructions = $checkout['instructions'] ?? null;
    
    // IMPORTANT: On utilise 'carte' comme valeur par défaut pour mode_paiement
    // car l'ENUM n'accepte que 'carte','paypal','virement','cheque'
    $mode_paiement_par_defaut = 'carte';
    
    $stmt = $pdo->prepare("
        INSERT INTO commandes (
            id_client, id_adresse_livraison, id_adresse_facturation,
            sous_total, frais_livraison, total_ttc,
            statut, statut_paiement, mode_paiement,
            date_commande, client_type, instructions
        ) VALUES (?, ?, ?, ?, ?, ?, 'en_attente', 'en_attente', ?, NOW(), ?, ?)
    ");
    
    $stmt->execute([
        $client_id,
        $adresse_id,
        $adresse_facturation_id,
        round($sous_total, 2),
        round($frais_livraison + $frais_emballage, 2),
        $total,
        $mode_paiement_par_defaut,
        $client_type,
        $instructions
    ]);
    
    $commande_id = $pdo->lastInsertId();
    
    // Ajouter les articles
    $stmt_item = $pdo->prepare("
        INSERT INTO commande_items (id_commande, id_produit, reference_produit, nom_produit, quantite, prix_unitaire_ht, prix_unitaire_ttc, tva)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($items_data as $item) {
        $stmt_item->execute([
            $commande_id,
            $item['id_produit'],
            $item['reference'],
            $item['nom'],
            $item['quantite'],
            $item['prix_unitaire_ht'],
            $item['prix_unitaire_ttc'],
            $item['tva']
        ]);
    }
    
    $pdo->commit();
    
    // Sauvegarder en session
    $_SESSION[SESSION_KEY_COMMANDE] = [
        'id' => $commande_id,
        'montant' => $total
    ];
    
    echo json_encode([
        'success' => true,
        'commande_id' => $commande_id,
        'montant' => $total
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur création commande: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>