<?php
// includes/functions-produits.php

function getProduitsFiltres($filtres) {
    $db = Database::getInstance();
    
    $sql = "SELECT p.*, c.nom as categorie_nom, 
                   (SELECT url_image FROM images_produits 
                    WHERE id_produit = p.id_produit 
                    ORDER BY principale DESC, ordre ASC LIMIT 1) as image
            FROM produits p
            JOIN categories c ON p.id_categorie = c.id_categorie
            WHERE p.statut = 'actif'";
    
    $params = [];
    $conditions = [];
    
    if (!empty($filtres['recherche'])) {
        $conditions[] = "(p.nom LIKE ? OR p.description LIKE ? OR p.marque LIKE ?)";
        $searchTerm = '%' . $filtres['recherche'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($filtres['categorie'])) {
        $conditions[] = "p.id_categorie = ?";
        $params[] = $filtres['categorie'];
    }
    
    if (!empty($filtres['prix_min']) && is_numeric($filtres['prix_min'])) {
        $conditions[] = "p.prix_ttc >= ?";
        $params[] = $filtres['prix_min'];
    }
    
    if (!empty($filtres['prix_max']) && is_numeric($filtres['prix_max'])) {
        $conditions[] = "p.prix_ttc <= ?";
        $params[] = $filtres['prix_max'];
    }
    
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    
    switch ($filtres['tri']) {
        case 'prix-croissant':
            $sql .= " ORDER BY p.prix_ttc ASC";
            break;
        case 'prix-decriossant':
            $sql .= " ORDER BY p.prix_ttc DESC";
            break;
        case 'nouveaute':
            $sql .= " ORDER BY p.date_creation DESC";
            break;
        case 'meilleurs-avis':
            $sql .= " ORDER BY p.note_moyenne DESC, p.nombre_avis DESC";
            break;
        case 'plus-vendus':
            $sql .= " ORDER BY p.ventes DESC";
            break;
        default:
            if (!empty($filtres['recherche'])) {
                $sql .= " ORDER BY (
                    CASE 
                        WHEN p.nom LIKE ? THEN 3
                        WHEN p.description LIKE ? THEN 2
                        WHEN p.marque LIKE ? THEN 1
                        ELSE 0
                    END
                ) DESC";
                $searchExact = $filtres['recherche'] . '%';
                $params[] = $searchExact;
                $params[] = $searchExact;
                $params[] = $searchExact;
            } else {
                $sql .= " ORDER BY p.date_creation DESC";
            }
            break;
    }
    
    $offset = ($filtres['page'] - 1) * $filtres['limit'];
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $filtres['limit'];
    $params[] = $offset;
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur recherche produits: " . $e->getMessage());
        return [];
    }
}

function getCategoriesAvecCompteur() {
    $db = Database::getInstance();
    
    $sql = "SELECT c.*, 
                   COUNT(p.id_produit) as nb_produits
            FROM categories c
            LEFT JOIN produits p ON c.id_categorie = p.id_categorie AND p.statut = 'actif'
            WHERE c.active = 1
            GROUP BY c.id_categorie
            ORDER BY c.ordre ASC, c.nom ASC";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur catégories: " . $e->getMessage());
        return [];
    }
}
?>