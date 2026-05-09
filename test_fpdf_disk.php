<?php
// test_fpdf_disk.php - Génère un fichier sur le disque au lieu de l'envoyer au navigateur
error_reporting(E_ALL);
ini_set('display_errors', 1);

$fpdf_path = '/var/www/sean/fpdf/fpdf.php';

if (!file_exists($fpdf_path)) {
    die("FPDF non trouvé");
}

require_once $fpdf_path;

try {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(40, 10, 'Test FPDF - OK !');
    
    // Sauvegarder sur le disque
    $output_file = '/var/www/sean/test_output.pdf';
    $pdf->Output('F', $output_file);
    
    echo "✅ PDF créé avec succès !<br>";
    echo "📄 Fichier : <a href='/test_output.pdf'>test_output.pdf</a>";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage();
}
?>