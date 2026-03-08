<?php
echo "<h2>Test d'accès aux images</h2>";

// Chemin physique
$physical_path = '/var/www/sean/uploads/produits/';
echo "Chemin physique : " . $physical_path . "<br>";

// Lister les images
if (file_exists($physical_path)) {
    $files = scandir($physical_path);
    $images = array_filter($files, function($f) {
        return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f);
    });
    
    if (empty($images)) {
        echo "⚠ Aucune image trouvée dans le dossier<br>";
        echo "Contenu du dossier : " . implode(', ', $files) . "<br>";
    } else {
        echo "<h3>Images trouvées :</h3>";
        
        foreach ($images as $img) {
            // Tester différentes URLs possibles
            echo "<div style='margin-bottom:20px; border:1px solid #ccc; padding:10px;'>";
            echo "<strong>Fichier :</strong> " . $img . "<br>";
            
            $urls_to_test = [
                '/uploads/produits/' . $img,
                '/sean/uploads/produits/' . $img,
                '/var/www/sean/uploads/produits/' . $img,
                'uploads/produits/' . $img,
                '../uploads/produits/' . $img
            ];
            
            foreach ($urls_to_test as $url) {
                $full_url = 'http://' . $_SERVER['HTTP_HOST'] . $url;
                echo "<div>";
                echo "Test URL : " . $url . " → ";
                
                // Vérifier si l'URL existe (headers)
                $headers = @get_headers($full_url);
                if ($headers && strpos($headers[0], '200')) {
                    echo "<span style='color:green'>✓ OK</span> ";
                    echo "<a href='$full_url' target='_blank'>Voir</a>";
                } else {
                    echo "<span style='color:red'>✗ Non trouvé</span>";
                }
                echo "</div>";
            }
            echo "</div>";
        }
    }
} else {
    echo "<span style='color:red'>✗ Dossier introuvable : " . $physical_path . "</span><br>";
}

// Afficher des informations utiles
echo "<h3>Informations système :</h3>";
echo "Document Root : " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script filename : " . $_SERVER['SCRIPT_FILENAME'] . "<br>";
echo "Request URI : " . $_SERVER['REQUEST_URI'] . "<br>";
?>