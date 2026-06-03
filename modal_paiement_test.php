<?php
// Fichier à inclure avec require_once sur la page de paiement

function afficherModalCartesTest() {
    ?>
    <div id="modalCartesTest" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center;">
        <div style="background-color: #fff; width: 90%; max-width: 500px; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); position: relative; font-family: Arial, sans-serif;">
            
            <!-- Bouton fermeture -->
            <span onclick="fermerModalPaiement()" style="position: absolute; top: 10px; right: 15px; font-size: 28px; font-weight: bold; cursor: pointer; color: #999;">&times;</span>
            
            <h3 style="margin-top: 0; color: #333;">🔐 Données de test - Paiement</h3>
            <hr>
            
            <h4>💳 Cartes bancaires de test :</h4>
            <ul style="margin-bottom: 20px;">
                <li><strong>Visa :</strong> 4020 0247 2393 0788</li>
                <li><strong>Expiration :</strong> 05/29</li>
                <li><strong>CVC :</strong> 619</li>
            </ul>
            
            <h4>📧 Compte PayPal de test :</h4>
            <ul>
                <li><strong>Email :</strong> sb-lbcqf47423737@personal.example.com</li>
                <li><strong>Mot de passe :</strong> 6s4)Q3yh</li>
            </ul>
            
            <p style="font-size: 12px; color: #777; margin-top: 20px;">
                ⚠️ Ces informations sont uniquement pour les tests en environnement sandbox.
            </p>
            
            <button onclick="fermerModalPaiement()" style="background-color: #4CAF50; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; width: 100%; margin-top: 10px;">
                Fermer
            </button>
        </div>
    </div>
    
    <script>
    function ouvrirModalPaiement() {
        document.getElementById('modalCartesTest').style.display = 'flex';
    }
    
    function fermerModalPaiement() {
        document.getElementById('modalCartesTest').style.display = 'none';
    }
    
    // Fermer en cliquant à l'extérieur
    window.onclick = function(event) {
        var modal = document.getElementById('modalCartesTest');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // Afficher automatiquement la modal au chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        ouvrirModalPaiement();
    });
    
    </script>
    <?php
}
?>