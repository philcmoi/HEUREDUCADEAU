<?php
// test_fpdf.php - Version corrigée
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Nettoyer tous les buffers de sortie
while (ob_get_level()) {
    ob_end_clean();
}

echo "=== TEST FPDF ===\n\n";

// 1. Vérifier le chemin
$fpdf_path = '/var/www/sean/fpdf/fpdf.php';
echo "1. Vérification du fichier FPDF...\n";
echo "   Chemin: $fpdf_path\n";

if (!file_exists($fpdf_path)) {
    die("   ERREUR: Fichier non trouvé!\n");
}
echo "   ✅ Fichier trouvé (taille: " . filesize($fpdf_path) . " octets)\n\n";

// 2. Inclure FPDF
echo "2. Inclusion de FPDF...\n";
require_once $fpdf_path;
echo "   ✅ Inclusion réussie\n\n";

// 3. Vérifier la classe
echo "3. Vérification de la classe FPDF...\n";
if (!class_exists('FPDF')) {
    die("   ERREUR: Classe FPDF non trouvée!\n");
}
echo "   ✅ Classe FPDF trouvée (version: " . FPDF_VERSION . ")\n\n";

// 4. Tester la création PDF
echo "4. Création du PDF...\n";

try {
    // Créer le PDF
    $pdf = new FPDF();
    echo "   ✅ Instance FPDF créée\n";
    
    $pdf->AddPage();
    echo "   ✅ Page ajoutée\n";
    
    $pdf->SetFont('Arial', 'B', 16);
    echo "   ✅ Police définie\n";
    
    $pdf->Cell(40, 10, 'Test FPDF - OK !');
    echo "   ✅ Cellule ajoutée\n";
    
    // Nettoyer les buffers avant d'envoyer le PDF
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Envoyer le PDF
    $pdf->Output('I', 'test.pdf');
    exit;
    
} catch (Exception $e) {
    die("   ❌ ERREUR: " . $e->getMessage() . "\n");
}
?>