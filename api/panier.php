<?php
// api/panier.php - Version complète avec codes promotionnels
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$id_produit = $_POST['id_produit'] ?? $_GET['id_produit'] ?? 0;
$quantite = $_POST['quantite'] ?? $_GET['quantite'] ?? 1;
$id_variant = $_POST['id_variant'] ?? $_GET['id_variant'] ?? null;
$options = $_POST['options'] ?? $_GET['options'] ?? null;
$code_promotion = $_POST['code_promotion'] ?? $_GET['code_promotion'] ?? '';

try {
    $db = Database::getInstance();
    
    switch ($action) {
        case 'ajouter':
            echo json_encode(ajouterAuPanier($db, $id_produit, $quantite, $id_variant, $options));
            break;
            
        case 'modifier':
            echo json_encode(modifierQuantite($db, $id_produit, $quantite, $id_variant));
            break;
            
        case 'supprimer':
            echo json_encode(supprimerDuPanier($db, $id_produit, $id_variant));
            break;
            
        case 'vider':
            echo json_encode(viderPanier($db));
            break;
            
        case 'recuperer':
            echo json_encode(recupererPanier($db, $code_promotion));
            break;
            
        case 'compter':
            echo json_encode(compterArticles($db));
            break;
            
        case 'appliquer_promo':
            echo json_encode(verifierCodePromotion($db, $code_promotion));
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action non valide']);
    }
} catch (Exception $e) {
    error_log('Erreur panier: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur interne du serveur']);
}

function ajouterAuPanier($db, $id_produit, $quantite, $id_variant = null, $options = null) {
    // Vérifier si le produit existe
    $sql = "SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            JOIN categories c ON p.id_categorie = c.id_categorie 
            WHERE p.id_produit = ? AND p.statut = 'actif'";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id_produit]);
    $produit = $stmt->fetch();
    
    if (!$produit) {
        return ['success' => false, 'message' => 'Produit non trouvé'];
    }
    
    // Vérifier le stock
    if ($produit['quantite_stock'] < $quantite) {
        return ['success' => false, 'message' => 'Stock insuffisant. Disponible : ' . $produit['quantite_stock']];
    }
    
    // Pour les variants, vérifier le stock spécifique
    if ($id_variant) {
        $sql = "SELECT * FROM variants WHERE id_variant = ? AND id_produit = ? AND actif = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id_variant, $id_produit]);
        $variant = $stmt->fetch();
        
        if (!$variant) {
            return ['success' => false, 'message' => 'Variant non disponible'];
        }
        
        if ($variant['quantite_stock'] < $quantite) {
            return ['success' => false, 'message' => 'Stock insuffisant pour ce variant. Disponible : ' . $variant['quantite_stock']];
        }
    }
    
    // Récupérer l'ID client ou session
    $id_client = isset($_SESSION['id_client']) ? $_SESSION['id_client'] : null;
    $session_id = session_id();
    
    // Utiliser la procédure stockée
    $sql = "CALL ajouter_au_panier(?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    
    $options_json = $options ? json_encode($options) : null;
    $stmt->execute([$id_client, $session_id, $id_produit, $id_variant, $quantite, $options_json]);
    $result = $stmt->fetch();
    
    // Mettre à jour les statistiques
    $sql = "INSERT INTO statistiques (date_stat, type_stat, id_produit, valeur) 
            VALUES (CURDATE(), 'panier_ajout', ?, 1)
            ON DUPLICATE KEY UPDATE valeur = valeur + 1";
    $db->prepare($sql)->execute([$id_produit]);
    
    return [
        'success' => true,
        'message' => 'Produit ajouté au panier',
        'id_panier' => $result['id_panier'],
        'total_items' => compterArticles($db)['total']
    ];
}

function modifierQuantite($db, $id_produit, $quantite, $id_variant = null) {
    // Récupérer l'ID panier
    $id_panier = getPanierId($db);
    
    if (!$id_panier) {
        return ['success' => false, 'message' => 'Panier non trouvé'];
    }
    
    // Vérifier le stock
    $sql = "SELECT quantite_stock FROM produits WHERE id_produit = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id_produit]);
    $stock = $stmt->fetchColumn();
    
    if ($stock < $quantite) {
        return ['success' => false, 'message' => 'Stock insuffisant. Disponible : ' . $stock];
    }
    
    if ($quantite <= 0) {
        return supprimerDuPanier($db, $id_produit, $id_variant);
    }
    
    $sql = "UPDATE panier_items 
            SET quantite = ?, date_ajout = CURRENT_TIMESTAMP 
            WHERE id_panier = ? AND id_produit = ? 
            AND (id_variant = ? OR (? IS NULL AND id_variant IS NULL))";
    $stmt = $db->prepare($sql);
    $stmt->execute([$quantite, $id_panier, $id_produit, $id_variant, $id_variant]);
    
    // Mettre à jour la date du panier
    $sql = "UPDATE panier SET date_modification = CURRENT_TIMESTAMP WHERE id_panier = ?";
    $db->prepare($sql)->execute([$id_panier]);
    
    return [
        'success' => true,
        'message' => 'Quantité mise à jour',
        'total_items' => compterArticles($db)['total']
    ];
}

function supprimerDuPanier($db, $id_produit, $id_variant = null) {
    $id_panier = getPanierId($db);
    
    if (!$id_panier) {
        return ['success' => false, 'message' => 'Panier non trouvé'];
    }
    
    $sql = "DELETE FROM panier_items 
            WHERE id_panier = ? AND id_produit = ? 
            AND (id_variant = ? OR (? IS NULL AND id_variant IS NULL))";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id_panier, $id_produit, $id_variant, $id_variant]);
    
    // Mettre à jour la date du panier
    $sql = "UPDATE panier SET date_modification = CURRENT_TIMESTAMP WHERE id_panier = ?";
    $db->prepare($sql)->execute([$id_panier]);
    
    return [
        'success' => true,
        'message' => 'Produit retiré du panier',
        'total_items' => compterArticles($db)['total']
    ];
}

function viderPanier($db) {
    $id_panier = getPanierId($db);
    
    if (!$id_panier) {
        return ['success' => false, 'message' => 'Panier non trouvé'];
    }
    
    $sql = "DELETE FROM panier_items WHERE id_panier = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id_panier]);
    
    // Supprimer également le panier lui-même
    $sql = "DELETE FROM panier WHERE id_panier = ?";
    $db->prepare($sql)->execute([$id_panier]);
    
    // Supprimer le code promo de la session
    unset($_SESSION['code_promotion']);
    
    return [
        'success' => true,
        'message' => 'Panier vidé avec succès',
        'total_items' => 0
    ];
}

function recupererPanier($db, $code_promotion = '') {
    $id_panier = getPanierId($db);
    
    if (!$id_panier) {
        return ['success' => true, 'items' => [], 'total' => 0, 'sous_total' => 0, 'reduction' => 0];
    }
    
    // Récupérer les items du panier
    $sql = "SELECT pi.*, p.nom, p.reference, p.slug, p.prix_ttc, 
                   p.quantite_stock, p.image, c.nom as categorie_nom,
                   v.nom_variant, v.valeur as variant_valeur, v.prix_supplement,
                   (pi.prix_unitaire * pi.quantite) as total_ligne
            FROM panier_items pi
            JOIN produits p ON pi.id_produit = p.id_produit
            JOIN categories c ON p.id_categorie = c.id_categorie
            LEFT JOIN variants v ON pi.id_variant = v.id_variant
            WHERE pi.id_panier = ?
            ORDER BY pi.date_ajout DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$id_panier]);
    $items = $stmt->fetchAll();
    
    // Calculer les totaux
    $sous_total = 0;
    $total_items = 0;
    
    foreach ($items as &$item) {
        $prix_unitaire = $item['prix_unitaire'];
        
        // Ajouter le supplément du variant s'il existe
        if ($item['prix_supplement']) {
            $prix_unitaire += $item['prix_supplement'];
        }
        
        $total_ligne = $prix_unitaire * $item['quantite'];
        $sous_total += $total_ligne;
        $total_items += $item['quantite'];
        
        // Stocker le prix unitaire ajusté
        $item['prix_unitaire_ajuste'] = $prix_unitaire;
        $item['total_ligne'] = $total_ligne;
    }
    
    // Gestion du code promotionnel
    $reduction = 0;
    $code_info = null;
    
    if ($code_promotion && !empty($code_promotion)) {
        $promo_result = verifierCodePromotion($db, $code_promotion);
        if ($promo_result['success']) {
            $code_info = $promo_result['code_info'];
            $reduction = calculerReduction($sous_total, $code_info);
            $_SESSION['code_promotion'] = $code_promotion;
        }
    } elseif (isset($_SESSION['code_promotion'])) {
        // Récupérer le code de la session
        $promo_result = verifierCodePromotion($db, $_SESSION['code_promotion']);
        if ($promo_result['success']) {
            $code_info = $promo_result['code_info'];
            $reduction = calculerReduction($sous_total, $code_info);
        } else {
            // Code invalide, le supprimer de la session
            unset($_SESSION['code_promotion']);
        }
    }
    
    // Calcul du total final
    $total = $sous_total - $reduction;
    if ($total < 0) $total = 0;
    
    return [
        'success' => true,
        'items' => $items,
        'total_items' => $total_items,
        'sous_total' => number_format($sous_total, 2, '.', ''),
        'reduction' => number_format($reduction, 2, '.', ''),
        'total' => number_format($total, 2, '.', ''),
        'code_promotion' => $code_promotion,
        'code_info' => $code_info
    ];
}

function verifierCodePromotion($db, $code_promotion) {
    $code = trim(strtoupper($code_promotion));
    
    if (empty($code)) {
        return ['success' => false, 'message' => 'Veuillez entrer un code'];
    }
    
    $sql = "SELECT * FROM promotions 
            WHERE code_promotion = ? 
            AND actif = 1 
            AND date_debut <= NOW() 
            AND date_fin >= NOW() 
            AND (utilisations_max IS NULL OR utilisations_actuelles < utilisations_max)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$code]);
    $promotion = $stmt->fetch();
    
    if (!$promotion) {
        return ['success' => false, 'message' => 'Code promotionnel invalide ou expiré'];
    }
    
    // Vérifier le montant minimum
    $sous_total = getSousTotalPanier($db);
    if ($sous_total < $promotion['montant_minimum']) {
        return [
            'success' => false, 
            'message' => 'Minimum d\'achat requis : ' . number_format($promotion['montant_minimum'], 2, ',', ' ') . ' €'
        ];
    }
    
    // Vérifier les restrictions de produits/catégories
    if (!empty($promotion['produits_ids']) || !empty($promotion['categories_ids'])) {
        if (!panierValidePourPromotion($db, $promotion)) {
            return ['success' => false, 'message' => 'Ce code ne s\'applique pas aux produits de votre panier'];
        }
    }
    
    return [
        'success' => true,
        'message' => 'Code promotionnel appliqué avec succès',
        'code_info' => $promotion
    ];
}

function calculerReduction($sous_total, $promotion) {
    $reduction = 0;
    
    switch ($promotion['type_promotion']) {
        case 'pourcentage':
            $reduction = $sous_total * ($promotion['valeur'] / 100);
            break;
            
        case 'montant_fixe':
            $reduction = $promotion['valeur'];
            break;
            
        case 'livraison_gratuite':
            // Pour la livraison gratuite, on ne réduit pas le sous-total
            // mais on le gérera au moment de la commande
            $reduction = 0;
            break;
    }
    
    // La réduction ne peut pas dépasser le sous-total
    if ($reduction > $sous_total) {
        $reduction = $sous_total;
    }
    
    return $reduction;
}

function panierValidePourPromotion($db, $promotion) {
    $id_panier = getPanierId($db);
    if (!$id_panier) return false;
    
    // Récupérer les produits du panier
    $sql = "SELECT pi.id_produit, p.id_categorie 
            FROM panier_items pi
            JOIN produits p ON pi.id_produit = p.id_produit
            WHERE pi.id_panier = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id_panier]);
    $items = $stmt->fetchAll();
    
    if (empty($items)) return false;
    
    // Vérifier les restrictions produits
    if (!empty($promotion['produits_ids'])) {
        $produits_ids = explode(',', $promotion['produits_ids']);
        $produits_ids = array_map('trim', $produits_ids);
        
        $panier_valide = false;
        foreach ($items as $item) {
            if (in_array($item['id_produit'], $produits_ids)) {
                $panier_valide = true;
                break;
            }
        }
        if (!$panier_valide) return false;
    }
    
    // Vérifier les restrictions catégories
    if (!empty($promotion['categories_ids'])) {
        $categories_ids = explode(',', $promotion['categories_ids']);
        $categories_ids = array_map('trim', $categories_ids);
        
        $panier_valide = false;
        foreach ($items as $item) {
            if (in_array($item['id_categorie'], $categories_ids)) {
                $panier_valide = true;
                break;
            }
        }
        if (!$panier_valide) return false;
    }
    
    return true;
}

function getSousTotalPanier($db) {
    $id_panier = getPanierId($db);
    if (!$id_panier) return 0;
    
    $sql = "SELECT SUM(pi.prix_unitaire * pi.quantite) as sous_total
            FROM panier_items pi
            WHERE pi.id_panier = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id_panier]);
    $result = $stmt->fetch();
    
    return $result['sous_total'] ?: 0;
}

function compterArticles($db) {
    $id_panier = getPanierId($db);
    
    if (!$id_panier) {
        return ['total' => 0];
    }
    
    $sql = "SELECT SUM(quantite) as total FROM panier_items WHERE id_panier = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id_panier]);
    $result = $stmt->fetch();
    
    return ['total' => $result['total'] ?: 0];
}

function getPanierId($db) {
    $id_client = isset($_SESSION['id_client']) ? $_SESSION['id_client'] : null;
    $session_id = session_id();
    
    // Chercher d'abord par client, puis par session
    if ($id_client) {
        $sql = "SELECT id_panier FROM panier WHERE id_client = ? ORDER BY date_creation DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id_client]);
        $result = $stmt->fetch();
        
        if ($result) {
            return $result['id_panier'];
        }
    }
    
    // Chercher par session
    if ($session_id) {
        $sql = "SELECT id_panier FROM panier WHERE session_id = ? ORDER BY date_creation DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$session_id]);
        $result = $stmt->fetch();
        
        if ($result) {
            // Si l'utilisateur se connecte, transférer le panier vers son compte
            if ($id_client && !$result['id_client']) {
                $sql = "UPDATE panier SET id_client = ? WHERE id_panier = ?";
                $db->prepare($sql)->execute([$id_client, $result['id_panier']]);
            }
            return $result['id_panier'];
        }
    }
    
    // Créer un nouveau panier si aucun n'existe
    if ($id_client || $session_id) {
        $sql = "INSERT INTO panier (id_client, session_id) VALUES (?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id_client, $session_id]);
        return $db->lastInsertId();
    }
    
    return null;
}
?>