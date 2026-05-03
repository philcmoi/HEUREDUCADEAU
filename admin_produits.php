<?php
// admin_produits.php - Gestion complète des produits avec upload d'images
// VERSION CORRIGÉE - Alignement des colonnes prix résolu

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

function getAllProducts($pdo) {
    $sql = "SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
            ORDER BY p.id_produit DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    // Supprimer d'abord les images associées
    try {
        $sql = "DELETE FROM images_produits WHERE id_produit = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
    } catch(Exception $e) {
        // Ignorer si la table n'existe pas
    }
    
    // Supprimer les variants associés
    try {
        $sql = "DELETE FROM variants WHERE id_produit = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
    } catch(Exception $e) {
        // Ignorer si la table n'existe pas
    }
    
    // Supprimer le produit
    $sql = "DELETE FROM produits WHERE id_produit = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute(['id' => $id]);
}

/**
 * Génère un slug unique à partir du nom
 * @param string $nom Le nom du produit
 * @param PDO $pdo Connexion à la base de données
 * @param int|null $id_exclu ID du produit à exclure (pour l'édition)
 * @return string Slug unique
 */
function generateUniqueSlug($nom, $pdo, $id_exclu = null) {
    // Slug de base
    $slug = strtolower($nom);
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Si le slug est vide, utiliser "produit"
    if (empty($slug)) {
        $slug = 'produit';
    }
    
    $slug_original = $slug;
    $compteur = 1;
    
    // Vérifier l'unicité
    while (slugExists($pdo, $slug, $id_exclu)) {
        $slug = $slug_original . '-' . $compteur;
        $compteur++;
    }
    
    return $slug;
}

/**
 * Vérifie si un slug existe déjà
 * @param PDO $pdo Connexion à la base de données
 * @param string $slug Le slug à vérifier
 * @param int|null $id_exclu ID du produit à exclure
 * @return bool
 */
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

function generateReference($pdo) {
    $sql = "SELECT COUNT(*) as count FROM produits";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNumber = $result['count'] + 1;
    return 'PROD-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
}

// ============================================
// FONCTION D'UPLOAD
// ============================================
function uploadImage($file) {
    // Configuration
    $upload_dir = '/var/www/sean/uploads/produits/';
    $upload_url = '/uploads/produits/';
    
    error_log("=== uploadImage() appelée ===");
    error_log("Dossier upload: " . $upload_dir);
    error_log("URL upload: " . $upload_url);
    
    // 1. Vérifier si un fichier a été uploadé
    if (!isset($file) || !is_array($file)) {
        error_log("uploadImage: Aucun fichier");
        return ['error' => 'Aucun fichier n\'a été envoyé'];
    }
    
    // 2. Vérifier les erreurs d'upload
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
        $error_code = $file['error'];
        $error_msg = $upload_errors[$error_code] ?? 'Erreur inconnue (code: ' . $error_code . ')';
        error_log("uploadImage: Erreur upload - " . $error_msg);
        return ['error' => $error_msg];
    }
    
    // 3. Vérifier que le fichier a bien été uploadé
    if (!is_uploaded_file($file['tmp_name'])) {
        error_log("uploadImage: Fichier non uploadé correctement");
        return ['error' => 'Le fichier n\'a pas été uploadé correctement'];
    }
    
    // 4. Vérifier l'extension
    $imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($imageFileType, $allowed_types)) {
        error_log("uploadImage: Extension non autorisée - " . $imageFileType);
        return ['error' => 'Format non autorisé (jpg, png, gif, webp uniquement)'];
    }
    
    // 5. Vérifier que c'est une vraie image
    $check = @getimagesize($file["tmp_name"]);
    if ($check === false) {
        error_log("uploadImage: Fichier invalide - pas une image");
        return ['error' => 'Le fichier n\'est pas une image valide'];
    }
    
    // 6. Vérifier la taille (max 2MB)
    $max_size = 2 * 1024 * 1024;
    if ($file["size"] > $max_size) {
        $size_mb = round($file["size"] / 1024 / 1024, 2);
        error_log("uploadImage: Fichier trop gros - " . $size_mb . "MB");
        return ['error' => "L'image est trop volumineuse (max 2MB - Taille: {$size_mb} MB)"];
    }
    
    // 7. Vérifier que le dossier existe et est accessible
    if (!file_exists($upload_dir)) {
        error_log("uploadImage: Le dossier n'existe pas, tentative de création");
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("uploadImage: Impossible de créer le dossier");
            return ['error' => "Impossible de créer le dossier d'upload"];
        }
        error_log("uploadImage: Dossier créé avec succès");
    }
    
    if (!is_writable($upload_dir)) {
        error_log("uploadImage: Dossier non accessible en écriture");
        error_log("Permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4));
        return ['error' => "Le dossier d'upload n'est pas accessible en écriture"];
    }
    
    // 8. Générer un nom unique
    $new_filename = uniqid() . '_' . date('Ymd_His') . '.' . $imageFileType;
    $target_file = $upload_dir . $new_filename;
    
    error_log("uploadImage: Tentative d'upload vers " . $target_file);
    
    // 9. Uploader le fichier
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        error_log("uploadImage: SUCCÈS - Fichier uploadé");
        error_log("URL: " . $upload_url . $new_filename);
        
        return ['success' => $upload_url . $new_filename];
    } else {
        $error = error_get_last();
        $error_msg = $error ? $error['message'] : 'Erreur inconnue';
        error_log("uploadImage: ÉCHEC - " . $error_msg);
        return ['error' => "Erreur lors de l'upload: " . $error_msg];
    }
}

/**
 * Nettoie une URL d'image pour enlever les doubles /sean/
 */
function cleanImageUrl($url) {
    if (empty($url)) return $url;
    
    $clean_url = preg_replace('#/sean/+#', '/', $url);
    
    if (strpos($clean_url, '/uploads/') !== 0) {
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

// Récupérer les catégories pour les formulaires
$categories = getAllCategories($pdo);

// Nettoyer les URLs d'images existantes
if ($action == 'list' || $action == 'edit' || $action == 'view') {
    try {
        $stmt = $pdo->prepare("SELECT id_image, url_image FROM images_produits WHERE url_image LIKE '%/sean/%'");
        $stmt->execute();
        $images_a_nettoyer = $stmt->fetchAll();
        
        foreach ($images_a_nettoyer as $img) {
            $clean_url = cleanImageUrl($img['url_image']);
            if ($clean_url !== $img['url_image']) {
                $update = $pdo->prepare("UPDATE images_produits SET url_image = ? WHERE id_image = ?");
                $update->execute([$clean_url, $img['id_image']]);
                error_log("URL nettoyée: " . $img['url_image'] . " -> " . $clean_url);
            }
        }
    } catch(Exception $e) {
        error_log("Erreur lors du nettoyage des URLs: " . $e->getMessage());
    }
}

// Traitement des formulaires POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            
            case 'add':
                // Générer un slug unique
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
                    $image_uploaded = false;
                    $image_error = '';
                    
                    if (!empty($_FILES['image']['name'])) {
                        error_log("Tentative d'upload pour nouveau produit ID: " . $lastId);
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
                            $image_uploaded = true;
                            $message = 'Produit ajouté avec succès ! Image uploadée.';
                        } else {
                            $image_error = $upload_result['error'];
                            $message = 'Produit ajouté mais erreur image: ' . $upload_result['error'];
                            error_log("ERREUR UPLOAD (add): " . $upload_result['error']);
                        }
                    } else {
                        $message = 'Produit ajouté avec succès (sans image)';
                    }
                    
                    if (empty($image_error)) {
                        header('Location: admin_produits.php?action=list&message=' . ($image_uploaded ? 'added_with_image' : 'added'));
                        exit();
                    }
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
                
                // Générer un slug unique (en excluant ce produit)
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
                    $image_uploaded = false;
                    $image_error = '';
                    
                    if (!empty($_FILES['image']['name'])) {
                        error_log("Tentative d'upload pour produit ID: " . $id);
                        error_log("Fichier: " . print_r($_FILES['image'], true));
                        
                        $upload_result = uploadImage($_FILES['image']);
                        
                        if (isset($upload_result['success'])) {
                            $image_url = cleanImageUrl($upload_result['success']);
                            
                            $sql = "DELETE FROM images_produits WHERE id_produit = :id_produit AND principale = 1";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute(['id_produit' => $id]);
                            
                            $sql = "INSERT INTO images_produits (id_produit, url_image, alt_text, principale) 
                                    VALUES (:id_produit, :url_image, :alt_text, 1)";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([
                                'id_produit' => $id,
                                'url_image' => $image_url,
                                'alt_text' => $_POST['nom']
                            ]);
                            $image_uploaded = true;
                            $message = 'Produit modifié avec succès ! Image mise à jour.';
                            error_log("Upload réussi pour produit ID: " . $id);
                        } else {
                            $image_error = $upload_result['error'];
                            $message = 'Produit modifié mais erreur image: ' . $upload_result['error'];
                            error_log("ERREUR UPLOAD (edit): " . $upload_result['error']);
                        }
                    } else {
                        $message = 'Produit modifié avec succès !';
                    }
                    
                    if (empty($image_error)) {
                        header('Location: admin_produits.php?action=list&message=' . ($image_uploaded ? 'updated_with_image' : 'updated'));
                        exit();
                    }
                } else {
                    $error = 'Erreur lors de la modification du produit.';
                }
                break;
            
            case 'delete':
                $id = intval($_POST['id_produit']);
                
                if (deleteProduct($pdo, $id)) {
                    header('Location: admin_produits.php?action=list&message=deleted');
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

if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'added':
        case 'added_with_image':
            $message = 'Produit ajouté avec succès' . ($_GET['message'] == 'added_with_image' ? ' et image uploadée !' : ' !');
            break;
        case 'updated':
        case 'updated_with_image':
            $message = 'Produit modifié avec succès' . ($_GET['message'] == 'updated_with_image' ? ' et image mise à jour !' : ' !');
            break;
        case 'deleted':
            $message = 'Produit supprimé avec succès !';
            break;
    }
}
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'notfound':
            $error = 'Produit non trouvé !';
            break;
    }
}

$admin_username = $_SESSION['admin_username'] ?? 'Administrateur';
$admin_role = $_SESSION['admin_role'] ?? 'Non défini';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Gestion des Produits - Heure du Cadeau</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ============================================
           STYLES RESPONSIVES OPTIMISÉS - VERSION ANTI-CLIGNOTEMENT
           ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary-color: #6a11cb;
            --primary-gradient: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            --success-color: #4CAF50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --info-color: #17a2b8;
            --dark-color: #333;
            --light-bg: #f5f7fa;
            --border-color: #dee2e6;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 15px rgba(0,0,0,0.08);
            --shadow-lg: 0 4px 20px rgba(0,0,0,0.1);
            --border-radius: 10px;
            --border-radius-sm: 6px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-color);
            line-height: 1.6;
            font-size: 16px;
            opacity: 1;
            transition: opacity 0.2s ease;
        }
        
        /* Prévention du FOUC */
        body:not(.loaded) {
            opacity: 1; /* Maintenir la visibilité */
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px;
            will-change: transform; /* Optimisation des performances */
        }
        
        /* Header responsive */
        .header {
            background: var(--primary-gradient);
            color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            box-shadow: var(--shadow-lg);
            display: flex;
            flex-direction: column;
            gap: 15px;
            transform: translateZ(0); /* Accélération matérielle */
            backface-visibility: hidden;
        }
        
        .header h1 { 
            font-size: 24px; 
            font-weight: 600; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .role-badge {
            background-color: var(--success-color);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
        }
        
        .superadmin-badge { background-color: var(--danger-color); }
        
        /* Navigation responsive */
        .nav-tabs {
            display: flex;
            background-color: white;
            border-radius: var(--border-radius);
            overflow-x: auto;
            overflow-y: hidden;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            flex-wrap: nowrap;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            transform: translateZ(0);
        }
        
        .nav-tabs::-webkit-scrollbar {
            height: 4px;
        }
        
        .nav-tabs::-webkit-scrollbar-thumb {
            background-color: #ccc;
            border-radius: 4px;
        }
        
        .nav-tabs a {
            padding: 15px 20px;
            text-decoration: none;
            color: #555;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            font-size: 14px;
        }
        
        .nav-tabs a:hover { 
            background-color: #f8f9fa; 
            color: var(--primary-color); 
        }
        
        .nav-tabs a.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background-color: #f0f8ff;
        }
        
        /* Alertes */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            word-break: break-word;
            transform: translateZ(0);
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Boutons */
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            width: 100%;
            text-align: center;
            transform: translateZ(0);
        }
        
        .btn-sm { 
            padding: 8px 12px; 
            font-size: 14px; 
        }
        
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-primary:hover { background-color: #5a0cb3; }
        .btn-success { background-color: var(--success-color); color: white; }
        .btn-warning { background-color: var(--warning-color); color: white; }
        .btn-danger { background-color: var(--danger-color); color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-info { background-color: var(--info-color); color: white; }
        
        /* Table responsive */
        .table-container {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
            transform: translateZ(0);
        }
        
        .table-header {
            background-color: #f8f9fa;
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        th {
            background-color: #f1f5fd;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
            font-size: 14px;
        }
        
        td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
            font-size: 14px;
        }
        
        tr:hover { background-color: #f9f9f9; }
        
        /* Optimisation des images pour éviter le clignotement */
        .product-image { 
            width: 50px; 
            height: 50px; 
            object-fit: cover; 
            border-radius: 6px; 
            border: 1px solid #ddd;
            background-color: #f5f5f5; /* Couleur de fond pendant le chargement */
            display: block;
            opacity: 1;
            transition: opacity 0.2s ease;
            aspect-ratio: 1/1; /* Maintient les proportions */
        }
        
        .product-image.loading {
            opacity: 0.7;
        }
        
        .price { 
            font-weight: 600; 
            color: #2e7d32; 
            white-space: nowrap;
        }
        
        .quantity { 
            display: inline-block; 
            padding: 4px 8px; 
            border-radius: 20px; 
            font-size: 12px; 
            font-weight: 500;
            white-space: nowrap;
        }
        
        .quantity-low { background-color: #ffebee; color: #c62828; }
        .quantity-medium { background-color: #fff8e1; color: #f57c00; }
        .quantity-high { background-color: #e8f5e9; color: #2e7d32; }
        
        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 11px;
            border-radius: 4px;
            color: white;
            white-space: nowrap;
        }
        
        /* Formulaires */
        .form-container {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow-md);
            transform: translateZ(0);
        }
        
        .form-group { margin-bottom: 15px; }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #444;
            font-size: 15px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius-sm);
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(106, 17, 203, 0.1);
        }
        
        textarea.form-control { min-height: 100px; resize: vertical; }
        
        /* Grilles de formulaires */
        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-row-4 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 5px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 5px;
            min-width: 120px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 15px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .modal.show {
            display: flex;
            opacity: 1;
        }
        
        .modal-content {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            transform: translateZ(0);
        }
        
        .modal-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }
        
        .modal-actions form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
        }
        
        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateZ(0);
        }
        
        .stat-card h3 {
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
        }
        
        .stat-blue { border-left-color: #2196F3; }
        .stat-green { border-left-color: #4CAF50; }
        .stat-orange { border-left-color: #FF9800; }
        .stat-red { border-left-color: #F44336; }
        
        /* Images grid dans détail */
        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .image-item {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 5px;
        }
        
        .image-item.principale {
            border-color: #4CAF50;
        }
        
        .image-item img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
            background-color: #f5f5f5;
        }
        
        /* Info rows dans détail */
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-table th {
            width: 150px;
            background: #f8f9fa;
            padding: 10px;
            text-align: left;
            font-weight: 600;
        }
        
        .info-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        /* Classes utilitaires */
        .text-center { text-align: center; }
        .mt-2 { margin-top: 10px; }
        .mt-3 { margin-top: 15px; }
        .mt-4 { margin-top: 20px; }
        .mb-2 { margin-bottom: 10px; }
        .mb-3 { margin-bottom: 15px; }
        .mb-4 { margin-bottom: 20px; }
        .hide-mobile { display: none; }
        .show-mobile { display: inline; }
        
        /* Info note */
        .info-note {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #ffeeba;
            word-break: break-word;
        }
        
        /* Tags caractéristiques */
        .special-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .special-tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        /* Loader placeholder pour images */
        .image-placeholder {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* ============================================
           MEDIA QUERIES - CORRIGÉES
           ============================================ */
        @media (min-width: 480px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 768px) {
            .container { padding: 20px; }
            
            .header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 25px;
            }
            
            .header h1 { font-size: 28px; }
            
            .nav-tabs a {
                padding: 18px 25px;
                font-size: 16px;
            }
            
            .btn {
                width: auto;
                padding: 10px 20px;
            }
            
            .product-image { 
                width: 60px; 
                height: 60px; 
            }
            
            .table-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 20px;
            }
            
            .form-container {
                padding: 30px;
            }
            
            .form-row { grid-template-columns: 2fr 1fr; }
            .form-row-2 { grid-template-columns: repeat(2, 1fr); }
            .form-row-3 { grid-template-columns: repeat(3, 1fr); }
            .form-row-4 { grid-template-columns: repeat(4, 1fr); }
            
            .modal-content { padding: 30px; }
            
            .modal-actions {
                flex-direction: row;
                justify-content: flex-end;
            }
            
            .modal-actions form {
                flex-direction: row;
                justify-content: flex-end;
            }
            
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .hide-mobile { display: inline; }
            .show-mobile { display: none; }
            
            .images-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
            
            .info-table th {
                width: 150px;
            }
        }
        
        @media (min-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 767px) {
            .info-table th {
                width: 120px;
                font-size: 14px;
            }
            
            .info-table td {
                font-size: 14px;
            }
            
            .detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1><i class="fas fa-boxes"></i> Gestion des Produits</h1>
                <p>Bienvenue, <?php echo htmlspecialchars($admin_username); ?></p>
            </div>
            <div class="role-badge <?php echo $admin_role === 'superadmin' ? 'superadmin-badge' : ''; ?>">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars(ucfirst($admin_role)); ?>
            </div>
        </div>
        
        <!-- Navigation -->
        <div class="nav-tabs">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> <span class="hide-mobile">Retour Dashboard</span>
            </a>
            <a href="admin_produits.php?action=list" class="<?php echo $action == 'list' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> <span>Liste</span>
            </a>
            <a href="admin_produits.php?action=add" class="<?php echo $action == 'add' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i> <span>Ajouter</span>
            </a>
            <a href="admin_produits.php?action=stats" class="<?php echo $action == 'stats' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> <span>Stats</span>
            </a>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Contenu selon l'action -->
        <?php if ($action == 'list'): ?>
            <!-- LISTE DES PRODUITS -->
            <?php 
            $products = getAllProducts($pdo);
            $totalProducts = count($products);
            ?>
            
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-box-open"></i> Liste des produits (<?php echo $totalProducts; ?>)</h3>
                    <a href="admin_produits.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nouveau produit
                    </a>
                </div>
                
                <?php if ($totalProducts > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Réf.</th>
                                    <th>Nom</th>
                                    <th class="hide-mobile">Catégorie</th>
                                    <th>Prix HT</th>
                                    <th class="hide-mobile">Prix TTC</th>
                                    <th>Stock</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): 
                                    $stmt = $pdo->prepare("
                                        SELECT url_image FROM images_produits 
                                        WHERE id_produit = ? 
                                        ORDER BY principale DESC, ordre ASC, id_image ASC 
                                        LIMIT 1
                                    ");
                                    $stmt->execute([$product['id_produit']]);
                                    $image = $stmt->fetch();
                                    
                                    if ($image && !empty($image['url_image'])) {
                                        $image_url = $image['url_image'];
                                    } else {
                                        $image_url = 'https://via.placeholder.com/60x60?text=+';
                                    }
                                    
                                    $prix_ttc = $product['prix_ht'] * (1 + ($product['tva'] / 100));
                                    
                                    $quantityClass = 'quantity-high';
                                    if ($product['quantite_stock'] == 0) {
                                        $quantityClass = 'quantity-low';
                                        $stockStatus = 'Rupture';
                                    } elseif ($product['quantite_stock'] <= $product['seuil_alerte']) {
                                        $quantityClass = 'quantity-medium';
                                        $stockStatus = 'Faible';
                                    } else {
                                        $stockStatus = 'OK';
                                    }
                                    
                                    $statusColors = [
                                        'actif' => '#4CAF50',
                                        'inactif' => '#6c757d',
                                        'rupture' => '#f44336',
                                        'bientot' => '#ff9800'
                                    ];
                                    $statusText = [
                                        'actif' => 'Actif',
                                        'inactif' => 'Inactif',
                                        'rupture' => 'Rupture',
                                        'bientot' => 'Bientôt'
                                    ];
                                    $status = $product['statut'] ?? 'actif';
                                    $color = $statusColors[$status] ?? '#6c757d';
                                ?>
                                <tr>
                                    <td>#<?php echo $product['id_produit']; ?></td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                             alt="<?php echo htmlspecialchars($product['nom']); ?>"
                                             class="product-image"
                                             loading="lazy"
                                             decoding="async"
                                             onload="this.classList.remove('loading')"
                                             onerror="this.onerror=null; this.src='https://via.placeholder.com/60x60?text=+'; this.classList.remove('loading')">
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($product['reference']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($product['nom']); ?>
                                        <div class="show-mobile" style="font-size: 12px; color: #666;">
                                            <?php echo htmlspecialchars($product['categorie_nom'] ?? 'Non catégorisé'); ?>
                                        </div>
                                    </td>
                                    <td class="hide-mobile"><?php echo htmlspecialchars($product['categorie_nom'] ?? 'Non catégorisé'); ?></td>
                                    <td class="price"><?php echo number_format($product['prix_ht'], 0); ?> €</td>
                                    <td class="price hide-mobile"><?php echo number_format($prix_ttc, 0); ?> €</td>
                                    <td>
                                        <span class="quantity <?php echo $quantityClass; ?>">
                                            <?php echo $product['quantite_stock']; ?>
                                            <span class="hide-mobile"> (<?php echo $stockStatus; ?>)</span>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge" style="background-color: <?php echo $color; ?>;">
                                            <?php echo $statusText[$status] ?? $status; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="admin_produits.php?action=view&id=<?php echo $product['id_produit']; ?>" 
                                               class="btn btn-info btn-sm" title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="admin_produits.php?action=edit&id=<?php echo $product['id_produit']; ?>" 
                                               class="btn btn-warning btn-sm" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button onclick="confirmDelete(<?php echo $product['id_produit']; ?>, '<?php echo addslashes($product['nom']); ?>')" 
                                                    class="btn btn-danger btn-sm" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center" style="padding: 40px;">
                        <i class="fas fa-box-open" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                        <h3 style="color: #777; margin-bottom: 10px;">Aucun produit trouvé</h3>
                        <p style="color: #999; margin-bottom: 30px;">Commencez par ajouter votre premier produit.</p>
                        <a href="admin_produits.php?action=add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Ajouter un produit
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
        <?php elseif ($action == 'add' || $action == 'edit'): ?>
            <!-- FORMULAIRE AJOUT/MODIFICATION -->
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
                <h2 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <i class="fas <?php echo $action == 'add' ? 'fa-plus-circle' : 'fa-edit'; ?>"></i>
                    <?php echo $action == 'add' ? 'Ajouter un nouveau produit' : 'Modifier le produit #' . $product['id_produit']; ?>
                </h2>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo $action == 'add' ? 'add' : 'edit'; ?>">
                    <?php if ($action == 'edit'): ?>
                        <input type="hidden" name="id_produit" value="<?php echo $product['id_produit']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom"><i class="fas fa-tag"></i> Nom du produit *</label>
                            <input type="text" id="nom" name="nom" class="form-control" 
                                   value="<?php echo htmlspecialchars($product['nom'] ?? ''); ?>" required
                                   oninput="updateSlug(this.value)">
                            <small id="slug-preview" style="color: #666; margin-top: 5px; display: block; word-break: break-word;">
                                Slug : <?php echo $product['slug'] ?? ''; ?>
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="reference"><i class="fas fa-barcode"></i> Référence *</label>
                            <input type="text" id="reference" name="reference" class="form-control" 
                                   value="<?php echo htmlspecialchars($defaultReference); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="description_courte"><i class="fas fa-align-left"></i> Description courte</label>
                            <textarea id="description_courte" name="description_courte" class="form-control" rows="3"><?php echo htmlspecialchars($product['description_courte'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="description"><i class="fas fa-align-justify"></i> Description complète</label>
                            <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="prix_ht"><i class="fas fa-euro-sign"></i> Prix HT (€) *</label>
                            <input type="number" id="prix_ht" name="prix_ht" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $product['prix_ht'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="tva"><i class="fas fa-percentage"></i> TVA (%) *</label>
                            <input type="number" id="tva" name="tva" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $product['tva'] ?? '20.00'; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="id_categorie"><i class="fas fa-folder"></i> Catégorie *</label>
                            <select id="id_categorie" name="id_categorie" class="form-control" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id_categorie']; ?>"
                                        <?php echo (isset($product['id_categorie']) && $product['id_categorie'] == $category['id_categorie']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="quantite_stock"><i class="fas fa-cubes"></i> Quantité en stock *</label>
                            <input type="number" id="quantite_stock" name="quantite_stock" class="form-control" 
                                   min="0" value="<?php echo $product['quantite_stock'] ?? '0'; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="seuil_alerte"><i class="fas fa-exclamation-triangle"></i> Seuil d'alerte</label>
                            <input type="number" id="seuil_alerte" name="seuil_alerte" class="form-control" 
                                   min="0" value="<?php echo $product['seuil_alerte'] ?? '10'; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row-4">
                        <div class="form-group">
                            <label for="marque"><i class="fas fa-trademark"></i> Marque</label>
                            <input type="text" id="marque" name="marque" class="form-control" 
                                   value="<?php echo htmlspecialchars($product['marque'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="poids"><i class="fas fa-weight"></i> Poids (g)</label>
                            <input type="number" id="poids" name="poids" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $product['poids'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="couleur"><i class="fas fa-palette"></i> Couleur</label>
                            <input type="text" id="couleur" name="couleur" class="form-control" 
                                   value="<?php echo htmlspecialchars($product['couleur'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="made_in"><i class="fas fa-globe"></i> Origine</label>
                            <input type="text" id="made_in" name="made_in" class="form-control" 
                                   value="<?php echo htmlspecialchars($product['made_in'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="dimensions"><i class="fas fa-ruler-combined"></i> Dimensions (LxHxP en cm)</label>
                        <input type="text" id="dimensions" name="dimensions" class="form-control" 
                               value="<?php echo htmlspecialchars($product['dimensions'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="materiau"><i class="fas fa-cube"></i> Matériau</label>
                        <input type="text" id="materiau" name="materiau" class="form-control" 
                               value="<?php echo htmlspecialchars($product['materiau'] ?? ''); ?>">
                    </div>
                    
                    <!-- Caractéristiques spéciales -->
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
                    
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="statut"><i class="fas fa-toggle-on"></i> Statut *</label>
                            <select id="statut" name="statut" class="form-control" required>
                                <option value="actif" <?php echo (isset($product['statut']) && $product['statut'] == 'actif') ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactif" <?php echo (isset($product['statut']) && $product['statut'] == 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                                <option value="rupture" <?php echo (isset($product['statut']) && $product['statut'] == 'rupture') ? 'selected' : ''; ?>>Rupture de stock</option>
                                <option value="bientot" <?php echo (isset($product['statut']) && $product['statut'] == 'bientot') ? 'selected' : ''; ?>>Bientôt disponible</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="image"><i class="fas fa-image"></i> Image du produit</label>
                            <input type="file" id="image" name="image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                            
                            <?php if ($action == 'edit' && !empty($current_image)): ?>
                                <div style="margin-top: 10px; display: flex; flex-wrap: wrap; align-items: center; gap: 15px;">
                                    <img src="<?php echo htmlspecialchars($current_image['url_image']); ?>" 
                                         alt="Image actuelle" 
                                         style="max-width: 80px; max-height: 80px; border-radius: 8px; border: 1px solid #ddd;"
                                         loading="lazy"
                                         decoding="async">
                                    <small style="color: #666;">Image actuelle - Laissez vide pour conserver</small>
                                </div>
                            <?php else: ?>
                                <small style="color: #666;">Laissez vide pour ne pas ajouter d'image</small>
                            <?php endif; ?>
                            
                            <small style="display: block; color: #999; margin-top: 5px;">Formats : JPG, PNG, GIF, WebP (max 2MB)</small>
                        </div>
                    </div>
                    
                    <div class="info-note">
                        <strong><i class="fas fa-info-circle"></i> Note :</strong>
                        <p style="margin-top: 5px;">Les images seront stockées dans <code>/uploads/produits/</code></p>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Ajouter le produit' : 'Mettre à jour'; ?>
                        </button>
                        <a href="admin_produits.php?action=list" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
            
        <?php elseif ($action == 'stats'): ?>
            <!-- STATISTIQUES -->
            <div class="form-container">
                <h2 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-chart-bar"></i> Statistiques produits
                </h2>
                
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
                    <div class="stat-card stat-blue">
                        <h3>Total produits</h3>
                        <div class="stat-value"><?php echo $total; ?></div>
                    </div>
                    
                    <div class="stat-card stat-green">
                        <h3>Valeur stock</h3>
                        <div class="stat-value"><?php echo number_format($valeur ?? 0, 0); ?> €</div>
                    </div>
                    
                    <div class="stat-card stat-orange">
                        <h3>Alertes stock</h3>
                        <div class="stat-value"><?php echo $alertes; ?></div>
                    </div>
                    
                    <div class="stat-card stat-red">
                        <h3>Ruptures</h3>
                        <div class="stat-value"><?php echo $rupture; ?></div>
                    </div>
                </div>
                
                <div style="background-color: white; padding: 20px; border-radius: 8px;">
                    <h3 style="margin-bottom: 20px;">Répartition par statut</h3>
                    <?php foreach ($statuts as $stat): ?>
                        <?php 
                        $percentage = $total > 0 ? ($stat['count'] / $total) * 100 : 0;
                        $colors = [
                            'actif' => '#4CAF50',
                            'inactif' => '#9E9E9E',
                            'rupture' => '#F44336',
                            'bientot' => '#FF9800'
                        ];
                        $color = $colors[$stat['statut']] ?? '#333';
                        ?>
                        <div style="margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px; flex-wrap: wrap; gap: 5px;">
                                <span style="font-weight: 600; color: <?php echo $color; ?>">
                                    <?php echo ucfirst($stat['statut']); ?>
                                </span>
                                <span><?php echo $stat['count']; ?> (<?php echo number_format($percentage, 1); ?>%)</span>
                            </div>
                            <div style="height: 8px; background-color: #eee; border-radius: 4px; overflow: hidden;">
                                <div style="height: 100%; width: <?php echo $percentage; ?>%; background-color: <?php echo $color; ?>;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php } catch(Exception $e) { ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Erreur lors du chargement des statistiques
                    </div>
                <?php } ?>
            </div>
            
        <?php elseif ($action == 'view' && $id > 0): ?>
            <!-- VUE DÉTAIL PRODUIT -->
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
            
            <div class="form-container">
                <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                        <h2 style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-eye"></i> Détail du produit #<?php echo $product['id_produit']; ?>
                        </h2>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <a href="admin_produits.php?action=edit&id=<?php echo $id; ?>" class="btn btn-warning">
                                <i class="fas fa-edit"></i> Modifier
                            </a>
                            <a href="admin_produits.php?action=list" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="detail-grid" style="display: grid; grid-template-columns: 1fr; gap: 20px;">
                    <!-- Colonne images -->
                    <div>
                        <h3 style="margin-bottom: 15px;">Images</h3>
                        <?php if (!empty($images)): ?>
                            <div class="images-grid">
                                <?php foreach ($images as $img): ?>
                                    <div class="image-item <?php echo $img['principale'] ? 'principale' : ''; ?>">
                                        <img src="<?php echo htmlspecialchars($img['url_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($img['alt_text']); ?>"
                                             loading="lazy"
                                             decoding="async">
                                        <?php if ($img['principale']): ?>
                                            <small style="display: block; text-align: center; color: #4CAF50; margin-top: 5px;">
                                                <i class="fas fa-star"></i> Principale
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center" style="padding: 30px; background: #f8f9fa; border-radius: 8px;">
                                <i class="fas fa-image" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                                <p>Aucune image pour ce produit</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Colonne informations -->
                    <div>
                        <table class="info-table">
                            <tr>
                                <th>Référence</th>
                                <td><?php echo htmlspecialchars($product['reference']); ?></td>
                            </tr>
                            <tr>
                                <th>Nom</th>
                                <td><?php echo htmlspecialchars($product['nom']); ?></td>
                            </tr>
                            <tr>
                                <th>Slug</th>
                                <td><code><?php echo htmlspecialchars($product['slug']); ?></code></td>
                            </tr>
                            <tr>
                                <th>Catégorie</th>
                                <td><?php echo htmlspecialchars($product['categorie_nom'] ?? 'Non catégorisé'); ?></td>
                            </tr>
                            <tr>
                                <th>Prix HT</th>
                                <td><?php echo number_format($product['prix_ht'], 2); ?> €</td>
                            </tr>
                            <tr>
                                <th>TVA</th>
                                <td><?php echo $product['tva']; ?>%</td>
                            </tr>
                            <tr>
                                <th>Prix TTC</th>
                                <td><strong><?php echo number_format($product['prix_ht'] * (1 + $product['tva']/100), 2); ?> €</strong></td>
                            </tr>
                            <tr>
                                <th>Stock</th>
                                <td>
                                    <?php echo $product['quantite_stock']; ?> unités 
                                    (seuil: <?php echo $product['seuil_alerte']; ?>)
                                </td>
                            </tr>
                            <tr>
                                <th>Statut</th>
                                <td>
                                    <?php 
                                    $statusColors = [
                                        'actif' => '#4CAF50',
                                        'inactif' => '#6c757d',
                                        'rupture' => '#f44336',
                                        'bientot' => '#ff9800'
                                    ];
                                    $color = $statusColors[$product['statut']] ?? '#6c757d';
                                    ?>
                                    <span class="status-badge" style="background-color: <?php echo $color; ?>;">
                                        <?php echo ucfirst($product['statut']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                        
                        <div style="margin-top: 20px;">
                            <h3 style="margin-bottom: 10px;">Description courte</h3>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; word-break: break-word;">
                                <?php echo nl2br(htmlspecialchars($product['description_courte'] ?: 'Aucune description courte')); ?>
                            </div>
                            
                            <h3 style="margin-bottom: 10px;">Description complète</h3>
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; word-break: break-word;">
                                <?php echo nl2br(htmlspecialchars($product['description'] ?: 'Aucune description')); ?>
                            </div>
                        </div>
                        
                        <?php if ($product['marque'] || $product['poids'] || $product['dimensions'] || $product['materiau'] || $product['couleur'] || $product['made_in']): ?>
                        <div style="margin-top: 20px;">
                            <h3 style="margin-bottom: 10px;">Caractéristiques</h3>
                            <table class="info-table">
                                <?php if ($product['marque']): ?>
                                <tr>
                                    <th>Marque</th>
                                    <td><?php echo htmlspecialchars($product['marque']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($product['poids']): ?>
                                <tr>
                                    <th>Poids</th>
                                    <td><?php echo $product['poids']; ?> g</td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($product['dimensions']): ?>
                                <tr>
                                    <th>Dimensions</th>
                                    <td><?php echo htmlspecialchars($product['dimensions']); ?> cm</td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($product['materiau']): ?>
                                <tr>
                                    <th>Matériau</th>
                                    <td><?php echo htmlspecialchars($product['materiau']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($product['couleur']): ?>
                                <tr>
                                    <th>Couleur</th>
                                    <td><?php echo htmlspecialchars($product['couleur']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($product['made_in']): ?>
                                <tr>
                                    <th>Origine</th>
                                    <td><?php echo htmlspecialchars($product['made_in']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
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
                        <div style="margin-top: 20px;">
                            <h3 style="margin-bottom: 10px;">Caractéristiques spéciales</h3>
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
            
        <?php endif; ?>
    </div>
    
    <!-- Modal de confirmation suppression -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle" style="color: #f44336; font-size: 24px;"></i>
                <h3 style="font-size: 20px;">Confirmer la suppression</h3>
            </div>
            <div style="margin-bottom: 25px; color: #666;">
                <p>Êtes-vous sûr de vouloir supprimer le produit "<span id="productName"></span>" ?</p>
                <p style="color: #f44336; font-weight: 600; margin-top: 10px;">
                    <i class="fas fa-exclamation-circle"></i> Cette action est irréversible !
                </p>
            </div>
            <div class="modal-actions">
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
        // Optimisation du chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            // Marquer le body comme chargé
            document.body.classList.add('loaded');
            
            // Initialiser les calculs
            calculateTTC();
            
            // Ajouter le preview TTC si nécessaire
            const prixHTField = document.getElementById('prix_ht');
            if (prixHTField && !document.getElementById('ttc-preview')) {
                const ttcPreview = document.createElement('small');
                ttcPreview.id = 'ttc-preview';
                ttcPreview.style.color = '#666';
                ttcPreview.style.marginTop = '5px';
                ttcPreview.style.display = 'block';
                prixHTField.parentNode.appendChild(ttcPreview);
                calculateTTC();
            }
            
            // Gestionnaire d'erreur global pour les images
            document.querySelectorAll('img').forEach(img => {
                img.addEventListener('error', function() {
                    if (!this.src.includes('placeholder')) {
                        this.src = 'https://via.placeholder.com/60x60?text=+';
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
            const slug = generateSlug(nom);
            const preview = document.getElementById('slug-preview');
            if (preview) {
                preview.innerHTML = 'Slug : ' + (slug || '(vide)');
            }
        }
        
        function confirmDelete(id, name) {
            document.getElementById('productId').value = id;
            document.getElementById('productName').textContent = name;
            const modal = document.getElementById('deleteModal');
            modal.style.display = 'flex';
            // Petite pause pour permettre la transition CSS
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }
        
        function closeModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 200);
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        const prixHT = document.getElementById('prix_ht');
        const tva = document.getElementById('tva');
        
        if (prixHT && tva) {
            prixHT.addEventListener('input', calculateTTC);
            tva.addEventListener('input', calculateTTC);
        }
        
        function calculateTTC() {
            const prixHT = parseFloat(document.getElementById('prix_ht')?.value) || 0;
            const tva = parseFloat(document.getElementById('tva')?.value) || 20;
            const prixTTC = prixHT * (1 + tva / 100);
            
            const ttcElement = document.getElementById('ttc-preview');
            if (ttcElement) {
                ttcElement.textContent = 'Prix TTC : ' + prixTTC.toFixed(2) + ' €';
            }
        }
    </script>
</body>
</html>