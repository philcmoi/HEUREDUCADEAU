<?php
// admin_produits.php - Gestion complète des produits avec upload d'images
// VERSION OPTIMISÉE - Présentation améliorée - GÉNÉRATION RÉFÉRENCE CORRIGÉE
// CORRECTION : Affichage des produits existants + pas de doublons

require_once 'admin_protection.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================
// CONFIGURATION DE LA BASE DE DONNÉES
// ============================================
$host = 'localhost';
$dbname = 'heureducadeau';
$username_db = 'Philippe';
$password_db = 'l@99339R';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // S'assurer que la table images_produits existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS `images_produits` (
        `id_image` int NOT NULL AUTO_INCREMENT,
        `id_produit` int NOT NULL,
        `url_image` varchar(255) NOT NULL,
        `alt_text` varchar(255) DEFAULT NULL,
        `ordre` int DEFAULT '0',
        `principale` tinyint(1) DEFAULT '0',
        `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id_image`),
        KEY `id_produit` (`id_produit`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
} catch(PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// ============================================
// FONCTIONS CRUD
// ============================================

/**
 * Récupère tous les produits avec leur image principale
 * CORRECTION : Requête SQL optimisée sans doublons
 */
function getAllProducts($pdo) {
    $sql = "SELECT p.*, c.nom as categorie_nom,
            (SELECT url_image FROM images_produits ip 
             WHERE ip.id_produit = p.id_produit 
             ORDER BY ip.principale DESC, ip.ordre ASC, ip.id_image ASC LIMIT 1) as image_url
            FROM produits p 
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
            ORDER BY p.id_produit DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Nettoyer les URLs des images (supprimer /sean/ si présent)
    foreach ($results as &$product) {
        if (!empty($product['image_url'])) {
            $product['image_url'] = preg_replace('#/sean/+#', '/', $product['image_url']);
        }
    }
    
    return $results;
}

function getProductById($pdo, $id) {
    $sql = "SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
            WHERE p.id_produit = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAllCategories($pdo) {
    $sql = "SELECT * FROM categories WHERE active = 1 ORDER BY nom";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère toutes les promotions actives de type 'pourcentage' ou 'montant_fixe'
 * pour les appliquer automatiquement aux nouveaux produits
 */
function getGlobalPromotionsForNewProducts($pdo) {
    $sql = "SELECT id_promotion, type_promotion, valeur, code_promotion 
            FROM promotions 
            WHERE actif = 1 
              AND date_debut <= NOW() 
              AND date_fin >= NOW()
              AND type_promotion IN ('pourcentage', 'montant_fixe')
            ORDER BY type_promotion, valeur DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Lie automatiquement un produit aux promotions globales
 * @param PDO $pdo Connexion PDO
 * @param int $product_id ID du produit
 * @param array $promotions Liste des promotions à lier (optionnel, par défaut toutes les promotions actives)
 * @return int Nombre de liens créés
 */
function linkProductToGlobalPromotions($pdo, $product_id, $promotions = null) {
    if ($promotions === null) {
        $promotions = getGlobalPromotionsForNewProducts($pdo);
    }
    
    if (empty($promotions)) {
        return 0;
    }
    
    $added = 0;
    
    foreach ($promotions as $promo) {
        // Vérifier si le lien existe déjà
        $stmt_check = $pdo->prepare("
            SELECT COUNT(*) FROM promotions_produits 
            WHERE id_promotion = ? AND id_produit = ?
        ");
        $stmt_check->execute([$promo['id_promotion'], $product_id]);
        
        if ($stmt_check->fetchColumn() == 0) {
            // Ajouter le lien produit-promotion
            $stmt_insert = $pdo->prepare("
                INSERT INTO promotions_produits (id_promotion, id_produit, reduction_personnalisee)
                VALUES (?, ?, NULL)
            ");
            if ($stmt_insert->execute([$promo['id_promotion'], $product_id])) {
                $added++;
                error_log("Produit ID $product_id lié à la promotion '{$promo['code_promotion']}'");
            }
        }
    }
    
    return $added;
}

function addProduct($pdo, $data) {
    $sql = "INSERT INTO produits (
                reference, nom, slug, description, description_courte, 
                prix_ht, tva, quantite_stock, seuil_alerte, id_categorie,
                marque, poids, dimensions, materiau, couleur, made_in,
                personnalisable, ecologique, made_in_france, artisanal, exclusif,
                statut
            ) VALUES (
                :reference, :nom, :slug, :description, :description_courte,
                :prix_ht, :tva, :quantite_stock, :seuil_alerte, :id_categorie,
                :marque, :poids, :dimensions, :materiau, :couleur, :made_in,
                :personnalisable, :ecologique, :made_in_france, :artisanal, :exclusif,
                :statut
            )";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($data);
}

function updateProduct($pdo, $id, $data) {
    $data['id_produit'] = $id;
    
    $sql = "UPDATE produits SET 
                reference = :reference,
                nom = :nom,
                slug = :slug,
                description = :description,
                description_courte = :description_courte,
                prix_ht = :prix_ht,
                tva = :tva,
                quantite_stock = :quantite_stock,
                seuil_alerte = :seuil_alerte,
                id_categorie = :id_categorie,
                marque = :marque,
                poids = :poids,
                dimensions = :dimensions,
                materiau = :materiau,
                couleur = :couleur,
                made_in = :made_in,
                personnalisable = :personnalisable,
                ecologique = :ecologique,
                made_in_france = :made_in_france,
                artisanal = :artisanal,
                exclusif = :exclusif,
                statut = :statut,
                date_modification = NOW()
            WHERE id_produit = :id_produit";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($data);
}

function deleteProduct($pdo, $id) {
    try {
        // Supprimer d'abord les liens avec les promotions
        $stmt = $pdo->prepare("DELETE FROM promotions_produits WHERE id_produit = :id");
        $stmt->execute(['id' => $id]);
    } catch(Exception $e) {}
    
    try {
        $sql = "DELETE FROM images_produits WHERE id_produit = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
    } catch(Exception $e) {}
    
    try {
        $sql = "DELETE FROM variants WHERE id_produit = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
    } catch(Exception $e) {}
    
    $sql = "DELETE FROM produits WHERE id_produit = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute(['id' => $id]);
}

function generateUniqueSlug($nom, $pdo, $id_exclu = null) {
    $slug = strtolower($nom);
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    if (empty($slug)) {
        $slug = 'produit';
    }
    
    $slug_original = $slug;
    $compteur = 1;
    
    while (slugExists($pdo, $slug, $id_exclu)) {
        $slug = $slug_original . '-' . $compteur;
        $compteur++;
    }
    
    return $slug;
}

function slugExists($pdo, $slug, $id_exclu = null) {
    if ($id_exclu) {
        $sql = "SELECT COUNT(*) FROM produits WHERE slug = :slug AND id_produit != :id_exclu";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['slug' => $slug, 'id_exclu' => $id_exclu]);
    } else {
        $sql = "SELECT COUNT(*) FROM produits WHERE slug = :slug";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['slug' => $slug]);
    }
    
    return $stmt->fetchColumn() > 0;
}

/**
 * Génère une référence unique au format PROD-XXXXXX
 * Version corrigée : trouve le plus grand numéro existant et ajoute 1
 */
function generateReference($pdo) {
    // Récupère la plus grande référence existante au format PROD-XXXXXX
    $sql = "SELECT reference FROM produits WHERE reference LIKE 'PROD-%' ORDER BY LENGTH(reference) DESC, reference DESC LIMIT 1";
    $stmt = $pdo->query($sql);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last && preg_match('/PROD-(\d+)/', $last['reference'], $matches)) {
        $nextNumber = intval($matches[1]) + 1;
    } else {
        $nextNumber = 1;
    }
    
    // Générer la référence
    $reference = 'PROD-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    
    // Vérification de sécurité anti-collision (au cas où)
    $counter = 0;
    while (referenceExists($pdo, $reference) && $counter < 100) {
        $nextNumber++;
        $reference = 'PROD-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
        $counter++;
    }
    
    return $reference;
}

/**
 * Vérifie si une référence existe déjà dans la base de données
 */
function referenceExists($pdo, $reference) {
    $sql = "SELECT COUNT(*) FROM produits WHERE reference = :reference";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['reference' => $reference]);
    return $stmt->fetchColumn() > 0;
}

function uploadImage($file) {
    $upload_dir = __DIR__ . '/uploads/produits/';
    $upload_url = '/uploads/produits/';
    
    if (!isset($file) || !is_array($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['error' => 'Aucun fichier sélectionné'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée (2MB)',
            UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le formulaire',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque',
            UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté l\'upload'
        ];
        return ['error' => $upload_errors[$file['error']] ?? 'Erreur inconnue'];
    }
    
    $imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($imageFileType, $allowed_types)) {
        return ['error' => 'Format non autorisé (jpg, png, gif, webp uniquement)'];
    }
    
    $check = @getimagesize($file["tmp_name"]);
    if ($check === false) {
        return ['error' => 'Le fichier n\'est pas une image valide'];
    }
    
    $max_size = 2 * 1024 * 1024;
    if ($file["size"] > $max_size) {
        return ['error' => "L'image est trop volumineuse (max 2MB)"];
    }
    
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return ['error' => "Impossible de créer le dossier d'upload"];
        }
    }
    
    if (!is_writable($upload_dir)) {
        return ['error' => "Le dossier d'upload n'est pas accessible en écriture"];
    }
    
    $new_filename = uniqid() . '_' . date('Ymd_His') . '.' . $imageFileType;
    $target_file = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ['success' => $upload_url . $new_filename];
    } else {
        return ['error' => "Erreur lors de l'upload"];
    }
}

function cleanImageUrl($url) {
    if (empty($url)) return $url;
    $clean_url = preg_replace('#/sean/+#', '/', $url);
    if (strpos($clean_url, '/uploads/') !== 0 && strpos($clean_url, 'http') !== 0) {
        $filename = basename($clean_url);
        $clean_url = '/uploads/produits/' . $filename;
    }
    return $clean_url;
}

// ============================================
// TRAITEMENT DES ACTIONS CRUD
// ============================================
$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

$categories = getAllCategories($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $slug = generateUniqueSlug($_POST['nom'], $pdo);
                
                $data = [
                    'reference' => $_POST['reference'],
                    'nom' => $_POST['nom'],
                    'slug' => $slug,
                    'description' => $_POST['description'],
                    'description_courte' => $_POST['description_courte'],
                    'prix_ht' => floatval($_POST['prix_ht']),
                    'tva' => floatval($_POST['tva']),
                    'quantite_stock' => intval($_POST['quantite_stock']),
                    'seuil_alerte' => intval($_POST['seuil_alerte']),
                    'id_categorie' => intval($_POST['id_categorie']),
                    'marque' => $_POST['marque'],
                    'poids' => !empty($_POST['poids']) ? floatval($_POST['poids']) : null,
                    'dimensions' => $_POST['dimensions'],
                    'materiau' => $_POST['materiau'],
                    'couleur' => $_POST['couleur'],
                    'made_in' => $_POST['made_in'],
                    'personnalisable' => isset($_POST['personnalisable']) ? 1 : 0,
                    'ecologique' => isset($_POST['ecologique']) ? 1 : 0,
                    'made_in_france' => isset($_POST['made_in_france']) ? 1 : 0,
                    'artisanal' => isset($_POST['artisanal']) ? 1 : 0,
                    'exclusif' => isset($_POST['exclusif']) ? 1 : 0,
                    'statut' => $_POST['statut']
                ];
                
                if (addProduct($pdo, $data)) {
                    $lastId = $pdo->lastInsertId();
                    
                    // Lier automatiquement le produit aux promotions globales
                    $promotions_liees = linkProductToGlobalPromotions($pdo, $lastId);
                    if ($promotions_liees > 0) {
                        error_log("Produit ID $lastId lié à $promotions_liees promotion(s) globale(s)");
                    }
                    
                    // Gestion de l'image
                    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $upload_result = uploadImage($_FILES['image']);
                        if (isset($upload_result['success'])) {
                            $image_url = cleanImageUrl($upload_result['success']);
                            $sql = "INSERT INTO images_produits (id_produit, url_image, alt_text, principale) 
                                    VALUES (:id_produit, :url_image, :alt_text, 1)";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([
                                'id_produit' => $lastId,
                                'url_image' => $image_url,
                                'alt_text' => $_POST['nom']
                            ]);
                            $_SESSION['message'] = 'Produit ajouté avec succès avec son image !';
                            header('Location: admin_produits.php?action=list');
                            exit();
                        } else {
                            error_log("Erreur upload image: " . ($upload_result['error'] ?? 'unknown'));
                        }
                    }
                    $_SESSION['message'] = 'Produit ajouté avec succès !';
                    header('Location: admin_produits.php?action=list');
                    exit();
                } else {
                    $error = 'Erreur lors de l\'ajout du produit.';
                }
                break;
            
            case 'edit':
                $id = intval($_POST['id_produit']);
                $existingProduct = getProductById($pdo, $id);
                if (!$existingProduct) {
                    $error = 'Produit non trouvé!';
                    break;
                }
                
                $slug = ($existingProduct['nom'] == $_POST['nom']) 
                    ? $existingProduct['slug'] 
                    : generateUniqueSlug($_POST['nom'], $pdo, $id);
                
                $data = [
                    'reference' => $_POST['reference'],
                    'nom' => $_POST['nom'],
                    'slug' => $slug,
                    'description' => $_POST['description'],
                    'description_courte' => $_POST['description_courte'],
                    'prix_ht' => floatval($_POST['prix_ht']),
                    'tva' => floatval($_POST['tva']),
                    'quantite_stock' => intval($_POST['quantite_stock']),
                    'seuil_alerte' => intval($_POST['seuil_alerte']),
                    'id_categorie' => intval($_POST['id_categorie']),
                    'marque' => $_POST['marque'],
                    'poids' => !empty($_POST['poids']) ? floatval($_POST['poids']) : null,
                    'dimensions' => $_POST['dimensions'],
                    'materiau' => $_POST['materiau'],
                    'couleur' => $_POST['couleur'],
                    'made_in' => $_POST['made_in'],
                    'personnalisable' => isset($_POST['personnalisable']) ? 1 : 0,
                    'ecologique' => isset($_POST['ecologique']) ? 1 : 0,
                    'made_in_france' => isset($_POST['made_in_france']) ? 1 : 0,
                    'artisanal' => isset($_POST['artisanal']) ? 1 : 0,
                    'exclusif' => isset($_POST['exclusif']) ? 1 : 0,
                    'statut' => $_POST['statut'],
                    'id_produit' => $id
                ];
                
                if (updateProduct($pdo, $id, $data)) {
                    // Gestion de l'image
                    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $upload_result = uploadImage($_FILES['image']);
                        if (isset($upload_result['success'])) {
                            $image_url = cleanImageUrl($upload_result['success']);
                            // Supprimer l'ancienne image principale
                            $sql = "DELETE FROM images_produits WHERE id_produit = :id_produit AND principale = 1";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute(['id_produit' => $id]);
                            
                            // Ajouter la nouvelle image principale
                            $sql = "INSERT INTO images_produits (id_produit, url_image, alt_text, principale) 
                                    VALUES (:id_produit, :url_image, :alt_text, 1)";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([
                                'id_produit' => $id,
                                'url_image' => $image_url,
                                'alt_text' => $_POST['nom']
                            ]);
                            $_SESSION['message'] = 'Produit modifié avec succès, image mise à jour !';
                            header('Location: admin_produits.php?action=list');
                            exit();
                        }
                    }
                    $_SESSION['message'] = 'Produit modifié avec succès !';
                    header('Location: admin_produits.php?action=list');
                    exit();
                } else {
                    $error = 'Erreur lors de la modification du produit.';
                }
                break;
            
            case 'delete':
                $id = intval($_POST['id_produit']);
                if (deleteProduct($pdo, $id)) {
                    $_SESSION['message'] = 'Produit supprimé avec succès !';
                    header('Location: admin_produits.php?action=list');
                    exit();
                } else {
                    $error = 'Erreur lors de la suppression du produit.';
                }
                break;
        }
    }
}

if ($action === 'delete_confirm' && $id > 0) {
    $product = getProductById($pdo, $id);
    if (!$product) {
        header('Location: admin_produits.php?action=list&error=notfound');
        exit();
    }
}

// Récupérer les messages de session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (isset($_GET['error']) && $_GET['error'] == 'notfound') {
    $error = 'Produit non trouvé !';
}

$admin_username = $_SESSION['admin_username'] ?? 'Administrateur';
$admin_role = $_SESSION['admin_role'] ?? 'Non défini';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Gestion des Produits - Heure du Cadeau</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           DESIGN MODERNE - VERSION AMÉLIORÉE
           ============================================ */
        
        /* Reset & Variables */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            /* Couleurs principales */
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --primary-bg: #eef2ff;
            
            /* Couleurs secondaires */
            --success: #10b981;
            --success-dark: #059669;
            --success-bg: #d1fae5;
            
            --warning: #f59e0b;
            --warning-dark: #d97706;
            --warning-bg: #fed7aa;
            
            --danger: #ef4444;
            --danger-dark: #dc2626;
            --danger-bg: #fee2e2;
            
            --info: #3b82f6;
            --info-dark: #2563eb;
            --info-bg: #dbeafe;
            
            /* Neutres */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            /* Ombres */
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            
            /* Arrondis */
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            
            /* Transitions */
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-base: 200ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            color: var(--gray-800);
            line-height: 1.5;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header Modern */
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: var(--radius-xl);
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 1;
        }
        
        .header h1 i {
            font-size: 32px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 8px;
        }
        
        .role-badge {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            border-radius: 100px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .role-badge i {
            font-size: 16px;
        }
        
        .superadmin-badge {
            background: rgba(239, 68, 68, 0.8);
        }
        
        /* Navigation Tabs */
        .nav-tabs {
            display: flex;
            gap: 8px;
            background: white;
            padding: 6px;
            border-radius: var(--radius-lg);
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            flex-wrap: wrap;
        }
        
        .nav-tabs a {
            padding: 12px 24px;
            text-decoration: none;
            color: var(--gray-600);
            font-weight: 500;
            border-radius: var(--radius-md);
            transition: all var(--transition-fast);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .nav-tabs a i {
            font-size: 16px;
        }
        
        .nav-tabs a:hover {
            background: var(--gray-100);
            color: var(--primary);
        }
        
        .nav-tabs a.active {
            background: var(--primary);
            color: white;
            box-shadow: var(--shadow-sm);
        }
        
        /* Alertes modernes */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: var(--success-bg);
            color: var(--success-dark);
            border-left: 4px solid var(--success);
        }
        
        .alert-danger {
            background: var(--danger-bg);
            color: var(--danger-dark);
            border-left: 4px solid var(--danger);
        }
        
        .alert i {
            font-size: 20px;
        }
        
        /* Cartes modernes */
        .card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all var(--transition-base);
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            background: var(--gray-50);
        }
        
        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--gray-800);
        }
        
        .card-header h3 i {
            color: var(--primary);
            font-size: 20px;
        }
        
        .card-body {
            padding: 24px;
        }
        
        /* Table moderne */
        .table-wrapper {
            overflow-x: auto;
            border-radius: var(--radius-md);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table thead tr {
            background: var(--gray-50);
            border-bottom: 2px solid var(--gray-200);
        }
        
        .table th {
            padding: 16px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-500);
        }
        
        .table td {
            padding: 16px;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
            font-size: 14px;
        }
        
        .table tbody tr {
            transition: background var(--transition-fast);
        }
        
        .table tbody tr:hover {
            background: var(--gray-50);
        }
        
        /* Product image */
        .product-image {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-200);
            background: var(--gray-100);
            transition: transform var(--transition-fast);
        }
        
        .product-image:hover {
            transform: scale(1.05);
        }
        
        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 500;
            gap: 6px;
            white-space: nowrap;
        }
        
        .badge-success { background: var(--success-bg); color: var(--success-dark); }
        .badge-warning { background: var(--warning-bg); color: var(--warning-dark); }
        .badge-danger { background: var(--danger-bg); color: var(--danger-dark); }
        .badge-info { background: var(--info-bg); color: var(--info-dark); }
        .badge-gray { background: var(--gray-100); color: var(--gray-600); }
        
        /* Stock badge */
        .stock-high { background: var(--success-bg); color: var(--success-dark); }
        .stock-medium { background: var(--warning-bg); color: var(--warning-dark); }
        .stock-low { background: var(--danger-bg); color: var(--danger-dark); }
        
        /* Prix */
        .price {
            font-weight: 600;
            color: var(--gray-800);
        }
        
        /* Actions buttons */
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            transition: all var(--transition-fast);
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        
        .btn-icon i {
            font-size: 14px;
        }
        
        .btn-icon-view { background: var(--info-bg); color: var(--info-dark); }
        .btn-icon-view:hover { background: var(--info); color: white; }
        .btn-icon-edit { background: var(--warning-bg); color: var(--warning-dark); }
        .btn-icon-edit:hover { background: var(--warning); color: white; }
        .btn-icon-delete { background: var(--danger-bg); color: var(--danger-dark); }
        .btn-icon-delete:hover { background: var(--danger); color: white; }
        
        /* Boutons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all var(--transition-fast);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: var(--shadow-sm); }
        
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: var(--success-dark); transform: translateY(-1px); }
        
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { background: var(--warning-dark); }
        
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: var(--danger-dark); }
        
        .btn-secondary { background: var(--gray-200); color: var(--gray-700); }
        .btn-secondary:hover { background: var(--gray-300); }
        
        .btn-sm { padding: 6px 12px; font-size: 13px; }
        
        /* Formulaire moderne */
        .form-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .form-header {
            padding: 24px;
            background: linear-gradient(135deg, var(--gray-50) 0%, white 100%);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .form-header h2 {
            font-size: 22px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .form-header h2 i {
            color: var(--primary);
        }
        
        .form-body {
            padding: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: var(--gray-700);
        }
        
        .form-group label i {
            margin-right: 8px;
            color: var(--primary);
            width: 18px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 14px;
            font-family: inherit;
            transition: all var(--transition-fast);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Grid layouts */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .form-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .form-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        
        /* Checkbox group */
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 8px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }
        
        .checkbox-item label {
            margin: 0;
            cursor: pointer;
        }
        
        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        
        .stat-card.blue::before { background: var(--info); }
        .stat-card.green::before { background: var(--success); }
        .stat-card.orange::before { background: var(--warning); }
        .stat-card.red::before { background: var(--danger); }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card h3 {
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-500);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--gray-800);
        }
        
        /* Info table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-table tr {
            border-bottom: 1px solid var(--gray-200);
        }
        
        .info-table th {
            width: 160px;
            padding: 12px 0;
            text-align: left;
            font-weight: 600;
            color: var(--gray-600);
        }
        
        .info-table td {
            padding: 12px 0;
            color: var(--gray-700);
        }
        
        /* Images grid */
        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        
        .image-item {
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            overflow: hidden;
            transition: all var(--transition-fast);
        }
        
        .image-item.principale {
            border-color: var(--success);
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }
        
        .image-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }
        
        .image-item small {
            display: block;
            text-align: center;
            padding: 8px;
            font-size: 12px;
            background: var(--gray-50);
        }
        
        /* Special tags */
        .special-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
        }
        
        .special-tag {
            background: var(--primary-bg);
            color: var(--primary-dark);
            padding: 6px 14px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal.show {
            display: flex;
            animation: fadeIn 0.2s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: white;
            border-radius: var(--radius-lg);
            max-width: 500px;
            width: 100%;
            box-shadow: var(--shadow-xl);
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .modal-header i {
            font-size: 28px;
            color: var(--danger);
        }
        
        .modal-header h3 {
            font-size: 20px;
            font-weight: 600;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        /* Info note */
        .info-note {
            background: var(--info-bg);
            color: var(--info-dark);
            padding: 16px;
            border-radius: var(--radius-md);
            margin: 20px 0;
            font-size: 13px;
            border-left: 3px solid var(--info);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: var(--gray-300);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 18px;
            color: var(--gray-500);
            margin-bottom: 8px;
        }
        
        .empty-state p {
            color: var(--gray-400);
            margin-bottom: 24px;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .form-grid-4 { grid-template-columns: repeat(2, 1fr); }
            .form-grid-3 { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .container { padding: 16px; }
            .header { padding: 20px; }
            .header h1 { font-size: 22px; }
            .nav-tabs a { padding: 10px 16px; font-size: 13px; }
            .form-grid-2, .form-grid-3, .form-grid-4 { grid-template-columns: 1fr; gap: 16px; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .stats-grid { grid-template-columns: 1fr; }
            .info-table th { width: 120px; }
        }
        
        @media (max-width: 640px) {
            .table th, .table td { padding: 12px 8px; }
            .badge { font-size: 11px; }
        }
        
        /* Utilitaires */
        .text-center { text-align: center; }
        .mt-4 { margin-top: 20px; }
        .mb-4 { margin-bottom: 20px; }
        .w-100 { width: 100%; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Modern -->
        <div class="header">
            <div>
                <h1>
                    <i class="fas fa-gift"></i>
                    Gestion des Produits
                </h1>
                <p>Bienvenue, <?php echo htmlspecialchars($admin_username); ?> · Gérez votre catalogue produits</p>
            </div>
            <div class="role-badge <?php echo $admin_role === 'superadmin' ? 'superadmin-badge' : ''; ?>">
                <i class="fas fa-shield-alt"></i>
                <?php echo htmlspecialchars(ucfirst($admin_role)); ?>
            </div>
        </div>
        
        <!-- Navigation -->
        <div class="nav-tabs">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="admin_produits.php?action=list" class="<?php echo $action == 'list' ? 'active' : ''; ?>">
                <i class="fas fa-list-ul"></i> Liste
            </a>
            <a href="admin_produits.php?action=add" class="<?php echo $action == 'add' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i> Ajouter
            </a>
            <a href="admin_produits.php?action=stats" class="<?php echo $action == 'stats' ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i> Statistiques
            </a>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Contenu selon l'action -->
        <?php if ($action == 'list'): ?>
            <?php 
            $products = getAllProducts($pdo);
            $totalProducts = count($products);
            ?>
            
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-box-open"></i>
                        Catalogue produits
                        <span class="badge badge-info"><?php echo $totalProducts; ?> produit(s)</span>
                    </h3>
                    <a href="admin_produits.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nouveau produit
                    </a>
                </div>
                
                <?php if ($totalProducts > 0): ?>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Référence</th>
                                    <th>Nom</th>
                                    <th>Catégorie</th>
                                    <th>Prix HT</th>
                                    <th>Prix TTC</th>
                                    <th>Stock</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): 
                                    $image_url = !empty($product['image_url']) ? $product['image_url'] : 'https://via.placeholder.com/48x48?text=No+Image';
                                    $prix_ttc = $product['prix_ht'] * (1 + ($product['tva'] / 100));
                                    
                                    $stockClass = 'stock-high';
                                    $stockText = 'Disponible';
                                    if ($product['quantite_stock'] == 0) {
                                        $stockClass = 'stock-low';
                                        $stockText = 'Rupture';
                                    } elseif ($product['quantite_stock'] <= $product['seuil_alerte']) {
                                        $stockClass = 'stock-medium';
                                        $stockText = 'Faible';
                                    }
                                    
                                    $statusBadge = '';
                                    switch($product['statut']) {
                                        case 'actif': $statusBadge = 'badge-success'; break;
                                        case 'inactif': $statusBadge = 'badge-gray'; break;
                                        case 'rupture': $statusBadge = 'badge-danger'; break;
                                        case 'bientot': $statusBadge = 'badge-warning'; break;
                                        default: $statusBadge = 'badge-gray';
                                    }
                                    
                                    $statusText = [
                                        'actif' => 'Actif',
                                        'inactif' => 'Inactif',
                                        'rupture' => 'Rupture',
                                        'bientot' => 'Bientôt'
                                    ][$product['statut']] ?? $product['statut'];
                                ?>
                                    <tr>
                                        <td><strong>#<?php echo $product['id_produit']; ?></strong></td>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['nom']); ?>"
                                                 class="product-image"
                                                 loading="lazy"
                                                 onerror="this.src='https://via.placeholder.com/48x48?text=Error'">
                                         </div
                                        <td><code><?php echo htmlspecialchars($product['reference']); ?></code> </div
                                        <td>
                                            <strong><?php echo htmlspecialchars($product['nom']); ?></strong>
                                         </div
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo htmlspecialchars($product['categorie_nom'] ?? 'Non catégorisé'); ?>
                                            </span>
                                        </div
                                        <td class="price"><?php echo number_format($product['prix_ht'], 2, ',', ' '); ?> €</div
                                        <td class="price"><?php echo number_format($prix_ttc, 2, ',', ' '); ?> €</div
                                        <td>
                                            <span class="badge <?php echo $stockClass; ?>">
                                                <i class="fas fa-cubes"></i> <?php echo $product['quantite_stock']; ?>
                                                <span class="hide-mobile"> (<?php echo $stockText; ?>)</span>
                                            </span>
                                         </div
                                        <td>
                                            <span class="badge <?php echo $statusBadge; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                         </div
                                        <td>
                                            <div class="actions">
                                                <a href="admin_produits.php?action=view&id=<?php echo $product['id_produit']; ?>" 
                                                   class="btn-icon btn-icon-view" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="admin_produits.php?action=edit&id=<?php echo $product['id_produit']; ?>" 
                                                   class="btn-icon btn-icon-edit" title="Modifier">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </a>
                                                <button onclick="confirmDelete(<?php echo $product['id_produit']; ?>, '<?php echo addslashes($product['nom']); ?>')" 
                                                        class="btn-icon btn-icon-delete" title="Supprimer">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                         </div
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h3>Aucun produit trouvé</h3>
                        <p>Commencez par ajouter votre premier produit</p>
                        <a href="admin_produits.php?action=add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Ajouter un produit
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
        <?php elseif ($action == 'add' || $action == 'edit'): ?>
            <?php 
            $product = null;
            if ($action == 'edit' && $id > 0) {
                $product = getProductById($pdo, $id);
                if (!$product) {
                    echo '<div class="alert alert-danger">Produit non trouvé!</div>';
                    echo '<a href="admin_produits.php?action=list" class="btn btn-secondary">Retour à la liste</a>';
                    exit();
                }
                
                $stmt = $pdo->prepare("
                    SELECT url_image FROM images_produits 
                    WHERE id_produit = ? 
                    ORDER BY principale DESC, ordre ASC, id_image ASC 
                    LIMIT 1
                ");
                $stmt->execute([$id]);
                $current_image = $stmt->fetch();
            }
            
            $defaultReference = $action == 'add' ? generateReference($pdo) : ($product['reference'] ?? '');
            ?>
            
            <div class="form-container">
                <div class="form-header">
                    <h2>
                        <i class="fas <?php echo $action == 'add' ? 'fa-plus-circle' : 'fa-edit'; ?>"></i>
                        <?php echo $action == 'add' ? 'Nouveau produit' : 'Modification produit #' . $product['id_produit']; ?>
                    </h2>
                </div>
                
                <div class="form-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?php echo $action == 'add' ? 'add' : 'edit'; ?>">
                        <?php if ($action == 'edit'): ?>
                            <input type="hidden" name="id_produit" value="<?php echo $product['id_produit']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-tag"></i> Nom du produit *</label>
                                <input type="text" name="nom" class="form-control" 
                                       value="<?php echo htmlspecialchars($product['nom'] ?? ''); ?>" required
                                       oninput="updateSlug(this.value)">
                                <small id="slug-preview" style="color: var(--gray-500); margin-top: 6px; display: block;">
                                    Slug : <?php echo $product['slug'] ?? ''; ?>
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-barcode"></i> Référence *</label>
                                <input type="text" name="reference" class="form-control" 
                                       value="<?php echo htmlspecialchars($defaultReference); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-euro-sign"></i> Prix HT (€) *</label>
                                <input type="number" name="prix_ht" class="form-control" 
                                       step="0.01" min="0" value="<?php echo $product['prix_ht'] ?? ''; ?>" required
                                       oninput="calculateTTC()">
                                <small id="ttc-preview" style="color: var(--gray-500); margin-top: 6px; display: block;"></small>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-percentage"></i> TVA (%) *</label>
                                <input type="number" name="tva" class="form-control" 
                                       step="0.01" min="0" value="<?php echo $product['tva'] ?? '20.00'; ?>" required
                                       oninput="calculateTTC()">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-folder"></i> Catégorie *</label>
                                <select name="id_categorie" class="form-control" required>
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id_categorie']; ?>"
                                            <?php echo (isset($product['id_categorie']) && $product['id_categorie'] == $category['id_categorie']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-cubes"></i> Quantité en stock *</label>
                                <input type="number" name="quantite_stock" class="form-control" 
                                       min="0" value="<?php echo $product['quantite_stock'] ?? '0'; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-exclamation-triangle"></i> Seuil d'alerte</label>
                                <input type="number" name="seuil_alerte" class="form-control" 
                                       min="0" value="<?php echo $product['seuil_alerte'] ?? '10'; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-toggle-on"></i> Statut *</label>
                                <select name="statut" class="form-control" required>
                                    <option value="actif" <?php echo (isset($product['statut']) && $product['statut'] == 'actif') ? 'selected' : ''; ?>>Actif</option>
                                    <option value="inactif" <?php echo (isset($product['statut']) && $product['statut'] == 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                                    <option value="rupture" <?php echo (isset($product['statut']) && $product['statut'] == 'rupture') ? 'selected' : ''; ?>>Rupture de stock</option>
                                    <option value="bientot" <?php echo (isset($product['statut']) && $product['statut'] == 'bientot') ? 'selected' : ''; ?>>Bientôt disponible</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-align-left"></i> Description courte</label>
                                <textarea name="description_courte" class="form-control" rows="3"><?php echo htmlspecialchars($product['description_courte'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-align-justify"></i> Description complète</label>
                                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-grid-4">
                            <div class="form-group">
                                <label><i class="fas fa-trademark"></i> Marque</label>
                                <input type="text" name="marque" class="form-control" value="<?php echo htmlspecialchars($product['marque'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-weight-hanging"></i> Poids (g)</label>
                                <input type="number" name="poids" class="form-control" step="0.01" value="<?php echo $product['poids'] ?? ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-palette"></i> Couleur</label>
                                <input type="text" name="couleur" class="form-control" value="<?php echo htmlspecialchars($product['couleur'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-globe-europe"></i> Origine</label>
                                <input type="text" name="made_in" class="form-control" value="<?php echo htmlspecialchars($product['made_in'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-ruler-combined"></i> Dimensions (cm)</label>
                                <input type="text" name="dimensions" class="form-control" placeholder="L x H x P" value="<?php echo htmlspecialchars($product['dimensions'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-cube"></i> Matériau</label>
                                <input type="text" name="materiau" class="form-control" value="<?php echo htmlspecialchars($product['materiau'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-star"></i> Caractéristiques spéciales</label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="personnalisable" name="personnalisable" 
                                           value="1" <?php echo (isset($product['personnalisable']) && $product['personnalisable'] == 1) ? 'checked' : ''; ?>>
                                    <label for="personnalisable">Personnalisable</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="ecologique" name="ecologique" 
                                           value="1" <?php echo (isset($product['ecologique']) && $product['ecologique'] == 1) ? 'checked' : ''; ?>>
                                    <label for="ecologique">Écologique</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="made_in_france" name="made_in_france" 
                                           value="1" <?php echo (isset($product['made_in_france']) && $product['made_in_france'] == 1) ? 'checked' : ''; ?>>
                                    <label for="made_in_france">Made in France</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="artisanal" name="artisanal" 
                                           value="1" <?php echo (isset($product['artisanal']) && $product['artisanal'] == 1) ? 'checked' : ''; ?>>
                                    <label for="artisanal">Artisanal</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="exclusif" name="exclusif" 
                                           value="1" <?php echo (isset($product['exclusif']) && $product['exclusif'] == 1) ? 'checked' : ''; ?>>
                                    <label for="exclusif">Exclusif</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-image"></i> Image du produit</label>
                            <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                            <small class="info-note" style="display: inline-block; margin-top: 8px; padding: 8px 12px;">
                                <i class="fas fa-info-circle"></i> Formats: JPG, PNG, GIF, WebP · Max 2MB
                            </small>
                            
                            <?php if ($action == 'edit' && !empty($current_image)): ?>
                                <div style="margin-top: 12px; display: flex; align-items: center; gap: 15px;">
                                    <img src="<?php echo htmlspecialchars($current_image['url_image']); ?>" 
                                         alt="Image actuelle" 
                                         style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid var(--gray-300);">
                                    <small style="color: var(--gray-500);">Image actuelle · Laissez vide pour conserver</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="display: flex; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--gray-200);">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Ajouter le produit' : 'Mettre à jour'; ?>
                            </button>
                            <a href="admin_produits.php?action=list" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
        <?php elseif ($action == 'stats'): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Statistiques produits</h3>
                </div>
                <div class="card-body">
                    <?php 
                    try {
                        $sql = "SELECT COUNT(*) as total FROM produits";
                        $stmt = $pdo->query($sql);
                        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        $sql = "SELECT statut, COUNT(*) as count FROM produits GROUP BY statut";
                        $stmt = $pdo->query($sql);
                        $statuts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $sql = "SELECT COUNT(*) as alertes FROM produits WHERE quantite_stock <= seuil_alerte AND statut = 'actif'";
                        $stmt = $pdo->query($sql);
                        $alertes = $stmt->fetch(PDO::FETCH_ASSOC)['alertes'];
                        
                        $sql = "SELECT COUNT(*) as rupture FROM produits WHERE quantite_stock = 0 AND statut = 'actif'";
                        $stmt = $pdo->query($sql);
                        $rupture = $stmt->fetch(PDO::FETCH_ASSOC)['rupture'];
                        
                        $sql = "SELECT SUM(prix_ht * quantite_stock * (1 + tva/100)) as valeur FROM produits WHERE statut = 'actif'";
                        $stmt = $pdo->query($sql);
                        $valeur = $stmt->fetch(PDO::FETCH_ASSOC)['valeur'];
                    ?>
                    
                    <div class="stats-grid">
                        <div class="stat-card blue">
                            <h3>Total produits</h3>
                            <div class="stat-value"><?php echo $total; ?></div>
                        </div>
                        
                        <div class="stat-card green">
                            <h3>Valeur du stock</h3>
                            <div class="stat-value"><?php echo number_format($valeur ?? 0, 0, ',', ' '); ?> €</div>
                        </div>
                        
                        <div class="stat-card orange">
                            <h3>Alertes stock</h3>
                            <div class="stat-value"><?php echo $alertes; ?></div>
                        </div>
                        
                        <div class="stat-card red">
                            <h3>Ruptures</h3>
                            <div class="stat-value"><?php echo $rupture; ?></div>
                        </div>
                    </div>
                    
                    <div class="card" style="margin-top: 24px;">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> Répartition par statut</h3>
                        </div>
                        <div class="card-body">
                            <?php foreach ($statuts as $stat): ?>
                                <?php 
                                $percentage = $total > 0 ? ($stat['count'] / $total) * 100 : 0;
                                $colors = [
                                    'actif' => '#10b981',
                                    'inactif' => '#9ca3af',
                                    'rupture' => '#ef4444',
                                    'bientot' => '#f59e0b'
                                ];
                                $color = $colors[$stat['statut']] ?? '#6366f1';
                                ?>
                                <div style="margin-bottom: 16px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                        <span style="font-weight: 500; color: <?php echo $color; ?>">
                                            <?php echo ucfirst($stat['statut']); ?>
                                        </span>
                                        <span style="color: var(--gray-500);"><?php echo $stat['count']; ?> (<?php echo number_format($percentage, 1); ?>%)</span>
                                    </div>
                                    <div style="height: 8px; background: var(--gray-200); border-radius: 4px; overflow: hidden;">
                                        <div style="height: 100%; width: <?php echo $percentage; ?>%; background: <?php echo $color; ?>; border-radius: 4px;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <?php } catch(Exception $e) { ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> Erreur lors du chargement des statistiques
                        </div>
                    <?php } ?>
                </div>
            </div>
            
        <?php elseif ($action == 'view' && $id > 0): ?>
            <?php
            $product = getProductById($pdo, $id);
            if (!$product) {
                echo '<div class="alert alert-danger">Produit non trouvé!</div>';
                echo '<a href="admin_produits.php?action=list" class="btn btn-secondary">Retour à la liste</a>';
                exit();
            }
            
            $stmt = $pdo->prepare("SELECT * FROM images_produits WHERE id_produit = ? ORDER BY principale DESC, ordre ASC");
            $stmt->execute([$id]);
            $images = $stmt->fetchAll();
            ?>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-eye"></i> Détail du produit</h3>
                    <div style="display: flex; gap: 8px;">
                        <a href="admin_produits.php?action=edit&id=<?php echo $id; ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <a href="admin_produits.php?action=list" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 32px;">
                        <!-- Section Images -->
                        <div>
                            <h4 style="margin-bottom: 16px; font-size: 16px;">
                                <i class="fas fa-images"></i> Images du produit
                            </h4>
                            <?php if (!empty($images)): ?>
                                <div class="images-grid">
                                    <?php foreach ($images as $img): ?>
                                        <div class="image-item <?php echo $img['principale'] ? 'principale' : ''; ?>">
                                            <img src="<?php echo htmlspecialchars($img['url_image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($img['alt_text']); ?>"
                                                 loading="lazy">
                                            <?php if ($img['principale']): ?>
                                                <small><i class="fas fa-star"></i> Principale</small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state" style="padding: 40px;">
                                    <i class="fas fa-image" style="font-size: 48px;"></i>
                                    <p>Aucune image pour ce produit</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Section Informations -->
                        <div>
                            <table class="info-table">
                                <tr><th>Référence</th><td><code><?php echo htmlspecialchars($product['reference']); ?></code></td></tr>
                                <tr><th>Nom</th><td><strong><?php echo htmlspecialchars($product['nom']); ?></strong></td></tr>
                                <tr><th>Slug</th><td><code><?php echo htmlspecialchars($product['slug']); ?></code></td></tr>
                                <tr><th>Catégorie</th><td><?php echo htmlspecialchars($product['categorie_nom'] ?? 'Non catégorisé'); ?></td></tr>
                                <tr><th>Prix HT</th><td><?php echo number_format($product['prix_ht'], 2); ?> €<\/td></tr>
                                <tr><th>TVA</th><td><?php echo $product['tva']; ?>%<\/td></tr>
                                <tr><th>Prix TTC</th><td><strong><?php echo number_format($product['prix_ht'] * (1 + $product['tva']/100), 2); ?> €<\/strong><\/td></tr>
                                <tr><th>Stock</th><td><?php echo $product['quantite_stock']; ?> unités (seuil: <?php echo $product['seuil_alerte']; ?>)<\/td></tr>
                                <tr><th>Statut</th>
                                    <td>
                                        <?php 
                                        $statusBadge = '';
                                        switch($product['statut']) {
                                            case 'actif': $statusBadge = 'badge-success'; break;
                                            case 'inactif': $statusBadge = 'badge-gray'; break;
                                            case 'rupture': $statusBadge = 'badge-danger'; break;
                                            case 'bientot': $statusBadge = 'badge-warning'; break;
                                            default: $statusBadge = 'badge-gray';
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusBadge; ?>">
                                            <?php echo ucfirst($product['statut']); ?>
                                        </span>
                                    <\/td>
                                </tr>
                            <\/table>
                            
                            <div style="margin-top: 24px;">
                                <h4 style="margin-bottom: 12px; font-size: 16px;">
                                    <i class="fas fa-align-left"></i> Description courte
                                </h4>
                                <div style="background: var(--gray-50); padding: 16px; border-radius: var(--radius-md);">
                                    <?php echo nl2br(htmlspecialchars($product['description_courte'] ?: 'Aucune description courte')); ?>
                                </div>
                            </div>
                            
                            <div style="margin-top: 24px;">
                                <h4 style="margin-bottom: 12px; font-size: 16px;">
                                    <i class="fas fa-align-justify"></i> Description complète
                                </h4>
                                <div style="background: var(--gray-50); padding: 16px; border-radius: var(--radius-md);">
                                    <?php echo nl2br(htmlspecialchars($product['description'] ?: 'Aucune description')); ?>
                                </div>
                            </div>
                            
                            <?php if ($product['marque'] || $product['poids'] || $product['dimensions'] || $product['materiau'] || $product['couleur'] || $product['made_in']): ?>
                            <div style="margin-top: 24px;">
                                <h4 style="margin-bottom: 12px; font-size: 16px;">
                                    <i class="fas fa-cog"></i> Caractéristiques techniques
                                </h4>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                    <?php if ($product['marque']): ?>
                                        <div><span style="color: var(--gray-500);">Marque:</span><br><strong><?php echo htmlspecialchars($product['marque']); ?></strong></div>
                                    <?php endif; ?>
                                    <?php if ($product['poids']): ?>
                                        <div><span style="color: var(--gray-500);">Poids:</span><br><strong><?php echo $product['poids']; ?> g</strong></div>
                                    <?php endif; ?>
                                    <?php if ($product['dimensions']): ?>
                                        <div><span style="color: var(--gray-500);">Dimensions:</span><br><strong><?php echo htmlspecialchars($product['dimensions']); ?> cm</strong></div>
                                    <?php endif; ?>
                                    <?php if ($product['materiau']): ?>
                                        <div><span style="color: var(--gray-500);">Matériau:</span><br><strong><?php echo htmlspecialchars($product['materiau']); ?></strong></div>
                                    <?php endif; ?>
                                    <?php if ($product['couleur']): ?>
                                        <div><span style="color: var(--gray-500);">Couleur:</span><br><strong><?php echo htmlspecialchars($product['couleur']); ?></strong></div>
                                    <?php endif; ?>
                                    <?php if ($product['made_in']): ?>
                                        <div><span style="color: var(--gray-500);">Origine:</span><br><strong><?php echo htmlspecialchars($product['made_in']); ?></strong></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php 
                            $special = [];
                            if ($product['personnalisable']) $special[] = 'Personnalisable';
                            if ($product['ecologique']) $special[] = 'Écologique';
                            if ($product['made_in_france']) $special[] = 'Made in France';
                            if ($product['artisanal']) $special[] = 'Artisanal';
                            if ($product['exclusif']) $special[] = 'Exclusif';
                            
                            if (!empty($special)): 
                            ?>
                            <div style="margin-top: 24px;">
                                <h4 style="margin-bottom: 12px; font-size: 16px;">
                                    <i class="fas fa-medal"></i> Labels & certifications
                                </h4>
                                <div class="special-tags">
                                    <?php foreach ($special as $tag): ?>
                                        <span class="special-tag">
                                            <i class="fas fa-check-circle"></i> <?php echo $tag; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>
    </div>
    
    <!-- Modal de confirmation suppression -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Confirmer la suppression</h3>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer le produit "<strong id="productName"></strong>" ?</p>
                <p style="color: var(--danger); margin-top: 12px;">
                    <i class="fas fa-exclamation-circle"></i> Cette action est irréversible !
                </p>
            </div>
            <div class="modal-footer">
                <form id="deleteForm" method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_produit" id="productId">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">Annuler</button>
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            calculateTTC();
            
            var prixHT = document.getElementById('prix_ht');
            var tva = document.getElementById('tva');
            if (prixHT && tva) {
                prixHT.addEventListener('input', calculateTTC);
                tva.addEventListener('input', calculateTTC);
            }
            
            // Gestion des erreurs d'images
            document.querySelectorAll('img').forEach(function(img) {
                img.addEventListener('error', function() {
                    if (!this.src.includes('placeholder')) {
                        this.src = 'https://via.placeholder.com/60x60?text=Error';
                    }
                });
            });
        });
        
        function generateSlug(text) {
            return text
                .toLowerCase()
                .replace(/[^a-z0-9-]/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');
        }
        
        function updateSlug(nom) {
            var slug = generateSlug(nom);
            var preview = document.getElementById('slug-preview');
            if (preview) {
                preview.innerHTML = 'Slug : ' + (slug || '(vide)');
            }
        }
        
        function calculateTTC() {
            var prixHT = parseFloat(document.getElementById('prix_ht')?.value) || 0;
            var tva = parseFloat(document.getElementById('tva')?.value) || 20;
            var prixTTC = prixHT * (1 + tva / 100);
            
            var ttcElement = document.getElementById('ttc-preview');
            if (ttcElement) {
                ttcElement.textContent = 'Prix TTC : ' + prixTTC.toFixed(2) + ' €';
            }
        }
        
        function confirmDelete(id, name) {
            document.getElementById('productId').value = id;
            document.getElementById('productName').textContent = name;
            var modal = document.getElementById('deleteModal');
            modal.style.display = 'flex';
            setTimeout(function() {
                modal.classList.add('show');
            }, 10);
        }
        
        function closeModal() {
            var modal = document.getElementById('deleteModal');
            modal.classList.remove('show');
            setTimeout(function() {
                modal.style.display = 'none';
            }, 200);
        }
        
        window.onclick = function(event) {
            var modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>