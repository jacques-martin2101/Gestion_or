<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/connexion.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'] ?? '';
    $adresse = $_POST['adresse'] ?? '';
    $tel = $_POST['tel'] ?? '';
    $poids = floatval($_POST['poids'] ?? 0);
    $teneur = $_POST['teneur'] ?? '';
    $cent = floatval($_POST['cent'] ?? 0);
    $prix_total = $cent * $poids;
    $date = date('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare('INSERT INTO operations 
            (nom_complet, adresse, telephone, poids_or, teneur_or, cent_pourcent, prix_total, date_operation)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$nom, $adresse, $tel, $poids, $teneur, $cent, $prix_total, $date]);
        $message = '<span style="color:green;">Insertion réussie !</span>';
    } catch (Exception $e) {
        $message = '<span style="color:red;">Erreur SQL : ' . $e->getMessage() . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Insertion</title>
</head>
<body>
    <h2>Test d'insertion dans la base de données</h2>
    <?php if($message) echo $message; ?>
    <form method="post">
        <label>Nom complet : <input type="text" name="nom" required></label><br><br>
        <label>Adresse : <input type="text" name="adresse"></label><br><br>
        <label>Téléphone : <input type="text" name="tel"></label><br><br>
        <label>Poids de l'or (g) : <input type="number" step="0.01" name="poids" required></label><br><br>
        <label>Teneur de l'or : <input type="text" name="teneur"></label><br><br>
        <label>100 % (prix du gramme) : <input type="number" step="0.01" name="cent" required></label><br><br>
        <button type="submit">Insérer</button>
    </form>
</body>
</html>