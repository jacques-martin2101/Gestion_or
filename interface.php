<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/connexion.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// require_once __DIR__ . '/vendor/phpoffice/phpword/src/PhpWord/Autoloader.php';
// \PhpOffice\PhpWord\Autoloader::register();
require_once __DIR__ . '/vendor/autoload.php'; // Chemin correct vers l'autoloader Composer
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

if(isset($_POST['action']) && $_POST['action'] === 'send_rapport') {
    // Paramètres SMTP
    $smtp_host = "smtp.gmail.com";
    $smtp_port = 587;
    $smtp_user = "akilitest9@gmail.com";
    $smtp_pass = "iuowpqbcwymfreja";
    $to = "nyatembej@gmail.com";
    $type = $_POST['type'];

    // Sélection du rapport à envoyer
    
    $date = date('d/m/Y');
    $rapport = "Date : $date\n";
    $all = file_exists('Rapport.txt') ? file('Rapport.txt') : [];
    if($type === 'actuel') {
        // Dernier rapport
        $parts = explode("--------------------------------------------", implode("", $all));
        $rapport = trim(end($parts));
        $subject = "Rapport actuel";
    } elseif($type === 'journalier') {
        // Tous les rapports du jour
        $subject = "Rapport Journalier du $date";
        $rapport = "";
        $current = date('d/m/Y');
        $buffer = "";
        foreach($all as $line) {
            if(strpos($line, "Date : ") === 0) {
                // Nouvelle facture
                if(strpos($line, "Date : $current") !== false) {
                    $buffer = $line;
                    $in_current = true;
                } else {
                    $in_current = false;
                }
            } else {
                if(isset($in_current) && $in_current) $buffer .= $line;
            }
            if(trim($line) === "--------------------------------------------" && isset($in_current) && $in_current) {
                $rapport .= $buffer;
                $buffer = "";
                $in_current = false;
            }
        }
        $rapport = trim($rapport);
    } elseif($type === 'semaine') {
        // Rapports des 7 derniers jours
        $subject = "Rapport de la semaine";
        $rapport = "";
        $dates = [];
        for($i=0;$i<7;$i++) $dates[] = date('d/m/Y', strtotime("monday this week +$i days"));
        $buffer = "";
        foreach($all as $line) {
            foreach($dates as $d) {
                if(strpos($line, "Date : $d") !== false) $buffer = "";
            }
            $buffer .= $line;
            if(trim($line) === "--------------------------------------------") {
                foreach($dates as $d) {
                    if(strpos($buffer, "Date : $d") !== false) $rapport .= $buffer;
                }
                $buffer = "";
            }
        }
        $rapport = trim($rapport);
    }

    // Envoi du mail via PHPMailer
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_user;
        $mail->Password = $smtp_pass;
        $mail->SMTPSecure = 'tls';
        $mail->Port = $smtp_port;
        $mail->setFrom($smtp_user, 'Gestion Or');
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $rapport ? $rapport : "Aucun rapport trouvé pour ce critère.";
        $mail->CharSet = 'UTF-8';
        $mail->send();
        echo json_encode(['status'=>'success']);
    } catch (Exception $e) {
        if(strpos($e->getMessage(), 'getaddrinfo failed') !== false) {
            echo json_encode(['status'=>'smtp']);
        } elseif(strpos($e->getMessage(), 'Failed to connect') !== false) {
            echo json_encode(['status'=>'connect']);
        } else {
            echo json_encode(['status'=>'fail','msg'=>$e->getMessage()]);
        }
    }
    exit;
} elseif (isset($_POST['action']) && $_POST['action'] === 'voir_rapport') {
    require_once __DIR__ . '/connexion.php';
    $type = $_POST['type'];
    $html = '';
    if ($type === 'journalier') {
        $date = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT * FROM operations WHERE DATE(date_operation) = ?");
        $stmt->execute([$date]);
        $rows = $stmt->fetchAll();
        $total = 0;
        if ($rows) {
            $html .= "<h4>Rapport du jour (" . date('d/m/Y') . ")</h4><table border='1' cellpadding='6' style='border-collapse:collapse;width:100%;font-size:15px;'><tr><th>Nom</th><th>Adresse</th><th>Téléphone</th><th>Poids</th><th>Teneur</th><th>100 %</th><th>Prix total</th><th>Date</th></tr>";
            foreach ($rows as $r) {
                $html .= "<tr>
                    <td>".htmlspecialchars($r['nom_complet'])."</td>
                    <td>".htmlspecialchars($r['adresse'])."</td>
                    <td>".htmlspecialchars($r['telephone'])."</td>
                    <td>".$r['poids_or']."</td>
                    <td>".htmlspecialchars($r['teneur_or'])."</td>
                    <td>".$r['cent_pourcent']."</td>
                    <td>".$r['prix_total']."</td>
                    <td>".$r['date_operation']."</td>
                </tr>";
                $total += $r['prix_total'];
            }
            $html .= "</table>";
            $html .= "<div style='margin-top:15px;font-weight:bold;font-size:17px;'>Somme totale du jour : <span style='color:#007b00;'>$total</span> $</div>";
        } else {
            $html .= "<div style='color:#f44336;'>Aucun rapport pour aujourd'hui.</div>";
        }
    } elseif ($type === 'semaine') {
        $lundi = date('Y-m-d', strtotime('monday this week'));
        $dimanche = date('Y-m-d', strtotime('sunday this week'));
        $stmt = $pdo->prepare("SELECT * FROM operations WHERE DATE(date_operation) BETWEEN ? AND ?");
        $stmt->execute([$lundi, $dimanche]);
        $rows = $stmt->fetchAll();
        $total = 0;
        if ($rows) {
            $html .= "<h4>Rapport de la semaine (" . date('d/m/Y', strtotime($lundi)) . " au " . date('d/m/Y', strtotime($dimanche)) . ")</h4><table border='1' cellpadding='6' style='border-collapse:collapse;width:100%;font-size:15px;'><tr><th>Nom</th><th>Adresse</th><th>Téléphone</th><th>Poids</th><th>Teneur</th><th>100 %</th><th>Prix total</th><th>Date</th></tr>";
            foreach ($rows as $r) {
                $html .= "<tr>
                    <td>".htmlspecialchars($r['nom_complet'])."</td>
                    <td>".htmlspecialchars($r['adresse'])."</td>
                    <td>".htmlspecialchars($r['telephone'])."</td>
                    <td>".$r['poids_or']."</td>
                    <td>".htmlspecialchars($r['teneur_or'])."</td>
                    <td>".$r['cent_pourcent']."</td>
                    <td>".$r['prix_total']."</td>
                    <td>".$r['date_operation']."</td>
                </tr>";
                $total += $r['prix_total'];
            }
            $html .= "</table>";
            $html .= "<div style='margin-top:15px;font-weight:bold;font-size:17px;'>Somme totale de la semaine : <span style='color:#007b00;'>$total</span> $</div>";
        } else {
            $html .= "<div style='color:#f44336;'>Aucun rapport pour cette semaine.</div>";
        }
    } elseif ($type === 'mensuel') {
        $mois = date('Y-m');
        $stmt = $pdo->prepare("SELECT * FROM operations WHERE DATE_FORMAT(date_operation, '%Y-%m') = ?");
        $stmt->execute([$mois]);
        $rows = $stmt->fetchAll();
        $total = 0;
        if ($rows) {
            $html .= "<h4>Rapport du mois (" . date('m/Y') . ")</h4><table border='1' cellpadding='6' style='border-collapse:collapse;width:100%;font-size:15px;'><tr><th>Nom</th><th>Adresse</th><th>Téléphone</th><th>Poids</th><th>Teneur</th><th>100 %</th><th>Prix total</th><th>Date</th></tr>";
            foreach ($rows as $r) {
                $html .= "<tr>
                    <td>".htmlspecialchars($r['nom_complet'])."</td>
                    <td>".htmlspecialchars($r['adresse'])."</td>
                    <td>".htmlspecialchars($r['telephone'])."</td>
                    <td>".$r['poids_or']."</td>
                    <td>".htmlspecialchars($r['teneur_or'])."</td>
                    <td>".$r['cent_pourcent']."</td>
                    <td>".$r['prix_total']."</td>
                    <td>".$r['date_operation']."</td>
                </tr>";
                $total += $r['prix_total'];
            }
            $html .= "</table>";
            $html .= "<div style='margin-top:15px;font-weight:bold;font-size:17px;'>Somme totale du mois : <span style='color:#007b00;'>$total</span> $</div>";
        } else {
            $html .= "<div style='color:#f44336;'>Aucun rapport pour ce mois.</div>";
        }
    } elseif ($type === 'global') {
        $stmt = $pdo->query("SELECT * FROM operations ORDER BY date_operation DESC");
        $rows = $stmt->fetchAll();
        if ($rows) {
            $html .= "<h4>Rapport global</h4><table border='1' cellpadding='6' style='border-collapse:collapse;width:100%;font-size:15px;'><tr><th>Nom</th><th>Adresse</th><th>Téléphone</th><th>Poids</th><th>Teneur</th><th>100 %</th><th>Prix total</th><th>Date</th></tr>";
            foreach ($rows as $r) {
                $html .= "<tr>
                    <td>".htmlspecialchars($r['nom_complet'])."</td>
                    <td>".htmlspecialchars($r['adresse'])."</td>
                    <td>".htmlspecialchars($r['telephone'])."</td>
                    <td>".$r['poids_or']."</td>
                    <td>".htmlspecialchars($r['teneur_or'])."</td>
                    <td>".$r['cent_pourcent']."</td>
                    <td>".$r['prix_total']."</td>
                    <td>".$r['date_operation']."</td>
                </tr>";
            }
            $html .= "</table>";
        } else {
            $html .= "<div style='color:#f44336;'>Aucun enregistrement trouvé.</div>";
        }
    }
    echo $html;
    exit;
} elseif (isset($_POST['action']) && $_POST['action'] === 'exporter_rapport') {
    require_once 'vendor/autoload.php'; // Chemin correct vers l'autoloader Composer

    $type = $_POST['type'];
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();

    if($type === 'journalier') {
        $section->addText("Rapport journalier du " . date('d/m/Y'));

        $stmt = $pdo->prepare("SELECT * FROM operations WHERE DATE(date_operation) = ?");
        $stmt->execute([date('Y-m-d')]);
        $rows = $stmt->fetchAll();

        if ($rows) {
            $table = $section->addTable();
            $table->addRow();
            $table->addCell()->addText("Nom");
            $table->addCell()->addText("Adresse");
            $table->addCell()->addText("Téléphone");
            $table->addCell()->addText("Poids");
            $table->addCell()->addText("Teneur");
            $table->addCell()->addText("100 %");
            $table->addCell()->addText("Prix total");
            $table->addCell()->addText("Date");

            foreach ($rows as $r) {
                $table->addRow();
                $table->addCell()->addText($r['nom_complet']);
                $table->addCell()->addText($r['adresse']);
                $table->addCell()->addText($r['telephone']);
                $table->addCell()->addText($r['poids_or']);
                $table->addCell()->addText($r['teneur_or']);
                $table->addCell()->addText($r['cent_pourcent']);
                $table->addCell()->addText($r['prix_total']);
                $table->addCell()->addText($r['date_operation']);
            }
        }
    } elseif ($type === 'mensuel') {
        $mois = date('Y-m');
        $section->addText("Rapport mensuel du " . date('m/Y'));

        $stmt = $pdo->prepare("SELECT * FROM operations WHERE DATE_FORMAT(date_operation, '%Y-%m') = ?");
        $stmt->execute([$mois]);
        $rows = $stmt->fetchAll();


        if ($rows) {
            $table = $section->addTable();
            $table->addRow();
            $table->addCell()->addText("Nom");
            $table->addCell()->addText("Adresse");
            $table->addCell()->addText("Téléphone");
            $table->addCell()->addText("Poids");
            $table->addCell()->addText("Teneur");
            $table->addCell()->addText("100 %");
            $table->addCell()->addText("Prix total");
            $table->addCell()->addText("Date");

            foreach ($rows as $r) {
                $table->addRow();
                $table->addCell()->addText($r['nom_complet']);
                $table->addCell()->addText($r['adresse']);
                $table->addCell()->addText($r['telephone']);
                $table->addCell()->addText($r['poids_or']);
                $table->addCell()->addText($r['teneur_or']);
                $table->addCell()->addText($r['cent_pourcent']);
                $table->addCell()->addText($r['prix_total']);
                $table->addCell()->addText($r['date_operation']);
            }
        }
    } elseif ($type === 'semaine') {
        $lundi = date('Y-m-d', strtotime('monday this week'));
        $dimanche = date('Y-m-d', strtotime('sunday this week'));
        $section->addText("Rapport de la semaine (" . date('d/m/Y', strtotime($lundi)) . " au " . date('d/m/Y', strtotime($dimanche)) . ")");

        $stmt = $pdo->prepare("SELECT * FROM operations WHERE DATE(date_operation) BETWEEN ? AND ?");
        $stmt->execute([$lundi, $dimanche]);
        $rows = $stmt->fetchAll();

        if ($rows) {
            $table = $section->addTable();
            $table->addRow();
            $table->addCell()->addText("Nom");
            $table->addCell()->addText("Adresse");
            $table->addCell()->addText("Téléphone");
            $table->addCell()->addText("Poids");
            $table->addCell()->addText("Teneur");
            $table->addCell()->addText("100 %");
            $table->addCell()->addText("Prix total");
            $table->addCell()->addText("Date");

            foreach ($rows as $r) {
                $table->addRow();
                $table->addCell()->addText($r['nom_complet']);
                $table->addCell()->addText($r['adresse']);
                $table->addCell()->addText($r['telephone']);
                $table->addCell()->addText($r['poids_or']);
                $table->addCell()->addText($r['teneur_or']);
                $table->addCell()->addText($r['cent_pourcent']);
                $table->addCell()->addText($r['prix_total']);
                $table->addCell()->addText($r['date_operation']);
            }
        }
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment;filename="rapport_' . $type . '.docx"');
    header('Cache-Control: max-age=0');

    $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save('php://output');
    exit;
} elseif (isset($_POST['action']) && $_POST['action'] === 'recherche') {
    $q = trim($_POST['q']);
    if ($q === '') exit; // Ne rien afficher si la recherche est vide
    require_once __DIR__ . '/connexion.php';
    $qLike = '%' . $q . '%';
    $stmt = $pdo->prepare("SELECT * FROM operations WHERE nom_complet LIKE ? OR telephone LIKE ? OR adresse LIKE ?");
    $stmt->execute([$qLike, $qLike, $qLike]);
    $rows = $stmt->fetchAll();
    if ($rows) {
        echo "<table border='1'><tr><th>Nom</th><th>Téléphone</th><th>Adresse</th><th>Date</th></tr>";
        foreach ($rows as $r) {
            echo "<tr>
                <td>".htmlspecialchars($r['nom_complet'])."</td>
                <td>".htmlspecialchars($r['telephone'])."</td>
                <td>".htmlspecialchars($r['adresse'])."</td>
                <td>".$r['date_operation']."</td>
            </tr>";
        }
        echo "</table>";
    } else {
        echo "<div style='color:#f44336;'>Aucun résultat trouvé.</div>";
    }
    exit;
} elseif (isset($_POST['action']) && $_POST['action'] === 'voir_entree') {
    require_once __DIR__ . '/connexion.php';
    // Somme du jour
    $stmt = $pdo->prepare("SELECT SUM(prix_total) as total FROM operations WHERE DATE(date_operation) = ?");
    $stmt->execute([date('Y-m-d')]);
    $jour = $stmt->fetch()['total'] ?? 0;

    // Somme de la semaine courante (lundi à dimanche)
    $lundi = date('Y-m-d', strtotime('monday this week'));
    $dimanche = date('Y-m-d', strtotime('sunday this week'));
    $stmt = $pdo->prepare("SELECT SUM(prix_total) as total FROM operations WHERE DATE(date_operation) BETWEEN ? AND ?");
    $stmt->execute([$lundi, $dimanche]);
    $semaine = $stmt->fetch()['total'] ?? 0;

    // Somme du mois
    $stmt = $pdo->prepare("SELECT SUM(prix_total) as total FROM operations WHERE DATE_FORMAT(date_operation, '%Y-%m') = ?");
    $stmt->execute([date('Y-m')]);
    $mois = $stmt->fetch()['total'] ?? 0;

    echo "<table border='1' cellpadding='8' style='margin:auto;font-size:17px;'><tr>
        <th>Période</th><th>Somme d'entrée</th></tr>
        <tr><td>Aujourd'hui</td><td style='color:#007b00;font-weight:bold;'>$jour</td></tr>
        <tr><td>Cette semaine</td><td style='color:#007b00;font-weight:bold;'>$semaine</td></tr>
        <tr><td>Ce mois</td><td style='color:#007b00;font-weight:bold;'>$mois</td></tr>
    </table>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion Or</title>
    <style>
        body {
            background: url('images/fount/fount1.jpeg') no-repeat center center fixed;
            background-size: cover;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 90%;
            margin: 20px auto;
            background: rgba(205, 191, 191, 0.66);
            padding: 10px 0 20px 0;
            border-radius: 20px;
        }
        form {
            background: url('images/fount/fount1.jpeg') no-repeat center center fixed;
            display: flex;
            flex-direction: row;
            justify-content: space-between;
        }
        .left, .right {
            display: flex;
            flex-direction: column;
        }
        .left {
            width: 50%;
        }
        .form-group {
            display: flex;
            align-items: center;
            margin-bottom: 18px;
        }
        .form-group label {
            width: 200px;
            font-size: 12px;
            color: #222;
        }
        .form-group input[type="text"] {
            width: 200%;
            height: 50px;
            font-size: 14px;
            border: 1.5px solid #ccc;
            border-radius: 8px;
            padding: 5px 20px;
            background: #fff;
            box-sizing: border-box;
        }
        .right {
            width: 35%;
            align-items: flex-end;
            text-align: center;
        }
        .add-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            margin-top: 1px;
        }
        .add-group input[type="text"] {
            width: 185px;
            height: 20px;
            font-size: 18px;
            border: 1.5px solid #ccc;
            border-radius: 2px;
            padding: 5px 10px;
            background: #fff;
            margin-right: 10px;
        }
        .btn {
            background: linear-gradient(to bottom,rgb(230, 222, 65) 0%,rgb(188, 197, 102) 100%);
            border: 1px solid #a0a0a0;
            border-radius: 20px;
            color: #222;
            font-size: 13px;
            padding: 5px 50px;
            margin-right: 1px;
            cursor: pointer;
            box-shadow: 0 3px 4px #aaa;
            transition: background 0.2s;
        }
        .btn:last-child {   
            margin-right: 7px;
        }
        .btn:hover {
           background: linear-gradient(to bottom,rgba(201, 238, 68, 0.85) 0%,rgb(120, 144, 72) 100%);
        }
        .actions {
            display: flex;
            flex-direction: row;
            justify-content: flex-start;
            margin-top: 25px;
            margin-left: 55px;
            gap: 35px;
        }
        @media (max-width: 900px) {
            form {
                flex-direction: column;
            }
            .left, .right {
                width: 100%;
            }
            .right {
                align-items: flex-start;
            }
        }
        @media print {
            body * { visibility: hidden; }
            #facture, #facture * { visibility: visible; }
            #facture { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <form method="post" autocomplete="off">
            <div class="left">
                <div class="form-group">
                    <label for="nom">Nom complet :</label>
                    <input type="text" id="nom" name="nom" required value="<?php if(isset($_POST['nom'])) echo htmlspecialchars($_POST['nom']); ?>">
                </div>
                <div class="form-group">
                    <label for="adresse">Adresse :</label>
                    <input type="text" id="adresse" name="adresse" required value="<?php if(isset($_POST['adresse'])) echo htmlspecialchars($_POST['adresse']); ?>">
                </div>
                <div class="form-group">
                    <label for="tel">N° Téléphone :</label>
                    <input type="text" id="tel" name="tel" required value="<?php if(isset($_POST['tel'])) echo htmlspecialchars($_POST['tel']); ?>">
                </div>
                <div class="form-group">
                    <label for="poids">Poids de l'or :</label>
                    <input type="text" id="poids" name="poids" required value="<?php if(isset($_POST['poids'])) echo htmlspecialchars($_POST['poids']); ?>">
                </div>
                <div class="form-group">
                    <label for="teneur">Teneur de l'or :</label>
                    <input type="text" id="teneur" name="teneur" required value="<?php if(isset($_POST['teneur'])) echo htmlspecialchars($_POST['teneur']); ?>">
                </div>
                <div class="form-group">
                    <label for="cent">100 % :</label>
                    <input type="text" id="cent" name="cent" required value="<?php if(isset($_POST['cent'])) echo htmlspecialchars($_POST['cent']); ?>">
                </div>
                <div class="actions">
                    <button type="submit" class="btn">Valider</button>
                    <button type="button" class="btn" onclick="ouvrirModal()">Envoyer Rapport</button>
                    <button type="button" class="btn" onclick="ouvrirRapportModal()">Voir les Rapports</button>
                    <button type="button" class="btn" onclick="ouvrirEntreeModal()">Entrée</button>
                     
                </div>
            </div>
            <div class="right">
                <div class="add-group">
                    <input type="text" placeholder="">
                    <button type="button" class="btn">Ajouter</button>
                </div>
                <div id="facture" style="width:100%;margin-bottom:30px;">
                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['nom'])) {
                    date_default_timezone_set('Europe/Paris');
                    $date = date('Y-m-d H:i:s');
                    $nom = $_POST['nom'];
                    $adresse = $_POST['adresse'];
                    $tel = $_POST['tel'];
                    $poids = floatval($_POST['poids']);
                    $teneur = $_POST['teneur'];
                    $cent = floatval($_POST['cent']);
                    $prix_total = $poids * $teneur * $cent / 100;

                    $facture = [
                        "Nom complet" => $nom,
                        "Adresse" => $adresse,
                        "N° Téléphone" => $tel,
                        "Poids de l'or" => $poids." g",
                        "Teneur de l'or" => $teneur,
                        "100 %" => $cent." %",
                        "Prix de l'or" => $prix_total." $",
                        "Date" => $date
                    ];

                    // Générer le texte pour le rapport
                    $date_affichage = date('d/m/Y');
                    $rapport = "Date : $date_affichage\n";
                    foreach ($facture as $k => $v) {
                        if ($k !== "Date") $rapport .= "$k : $v\n";
                    }
                    $rapport .= "--------------------------------------------\n";
                    // Enregistrer dans Rapport.txt
                    file_put_contents('Rapport.txt', $rapport, FILE_APPEND);

                    // Enregistrer dans la base de données
                    if ($pdo) {
                        try {
                            $stmt = $pdo->prepare('INSERT INTO operations
                                (nom_complet, adresse, telephone, poids_or, teneur_or, cent_pourcent, prix_total, date_operation)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                            $stmt->execute([
                                $nom, $adresse, $tel, $poids, $teneur, $cent, $prix_total, $date
                            ]);
                        } catch (Exception $e) {
                            echo '<div style="color:red">Erreur SQL : ' . $e->getMessage() . '</div>';
                        }
                    } else {
                        echo '<div style="color:red">Erreur de connexion à la base de données.</div>';
                    }

                    // Affichage facture à droite
                    echo '<div style="background:#fff;padding:20px;border-radius:5px;box-shadow:0 2px 8px #bbb; font-family:\'Times New Roman\', Times, serif; font-size:9pt;">';
                    echo '<div style="text-align:center; font-weight:bold; font-size:13pt; letter-spacing:2px;">*** LWAMBA GOLD ***</div>';
                    echo '<div style="text-align:center; font-size:10pt; margin-bottom:2px;">***** ETS LWAMBA GOLD *****</div>';
                    echo '<div style="text-align:center; font-size:9pt;">N.R.C.1347-ID.NAT.6-93-N 43691 A</div>';
                    echo '<div style="text-align:center; font-size:9pt;">AV.TABORA COIN MANIEMA</div>';
                    echo '<div style="text-align:center; font-size:9pt;">'.date('d/m/Y H:i').'</div>';
                    echo '<div style="text-align:center; font-size:9pt; margin-bottom:8px;">Tel: +243 977 934 836</div>';
                    echo '<div style="margin:8px 0 8px 0; border-bottom:1px dashed #222; font-size:9pt;">-----------------------------------------</div>';
                    echo '<div style="font-size:9pt;"><strong>Nom :</strong> '.htmlspecialchars($nom).'</div>';
                    echo '<div style="font-size:9pt;"><strong>Poids d\'or :</strong> '.$poids.' g</div>';
                    echo '<div style="font-size:9pt;"><strong>Teneur de l\'or :</strong> '.$teneur.' %</div>';
                    echo '<div style="font-size:9pt;"><strong>100 % :</strong> '.$cent.' $</div>';
                    echo '<div style="margin:8px 0 8px 0; border-bottom:1px dashed #222; font-size:9pt;">---------------------------------------------</div>';
                    echo '<div style="font-size:11pt; font-weight:bold; margin-bottom:5px;">Total : '.$prix_total.' $</div>';
                    echo "<div style='font-size:8pt;color:#888;margin-top:10px;'>Enregistré le : $date</div>";
                    echo '</div>';
                    echo '</div>';
                    $_POST = [];
                }
                ?>
                <button type="button" class="btn" id="btnImprimerFacture" style="margin-top:auto;" onclick="imprimerFacture()" disabled>Imprimer facture</button>
                <button type="button" class="btn" style="margin-top:auto;" id="btnNouvelleFacture" onclick="nouvelleFacture()">Nouvelle facture</button>
                </div>
                
                
            </div>
        </form>
        <input type="text" id="recherche">
        <button onclick="rechercherOperation()">Rechercher</button>
        <div id="resultatsRecherche"></div>
    </div>
    <!-- Fenêtre modale pour envoyer rapport -->
    <div id="rapportModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);z-index:1100;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:30px 40px;border-radius:8px;box-shadow:0 2px 12px #888;min-width:350px;text-align:center;">
            <h3>Envoyer Rapport</h3>
            <button class="btn" onclick="envoyerRapport('journalier')">Rapport Journalier</button>
            <button class="btn" onclick="envoyerRapport('semaine')">Rapport de semaine</button>
            <br><br>
            <button class="btn" style="background:#eee;color:#333;margin-top:20px;" onclick="fermerModal()">Fermer</button>
        </div>
    </div>
    <!-- Fenêtre modale pour voir les rapports -->
    <div id="voirRapportModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);z-index:1100;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:30px 40px;border-radius:8px;box-shadow:0 2px 12px #888;min-width:350px;text-align:center;">
            <h3>Voir les rapports</h3>
            <button class="btn" onclick="afficherRapport('journalier')">Journalier</button>
            <button class="btn" onclick="afficherRapport('semaine')">Semaine</button>
            <button class="btn" onclick="afficherRapport('mensuel')">Mensuel</button>
            <button class="btn" onclick="afficherRapport('global')">Global</button>
            <br><br>
            <div id="contenuRapport" style="max-height:350px;overflow:auto;text-align:left;margin-top:15px;"></div>
            <button class="btn" style="background:#eee;color:#333;margin-top:20px;" onclick="fermerRapportModal()">Fermer</button>
        </div>
    </div>
    <!-- Fenêtre modale pour Entrée -->
    <div id="entreeModal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);z-index:1200;align-items:center;justify-content:center;">
        <div style="background:#fff;padding:30px 40px;border-radius:8px;box-shadow:0 2px 12px #888;min-width:350px;text-align:center;">
            <h3>Sommes des entrées</h3>
            <div id="contenuEntree" style="margin-top:15px;"></div>
            <button class="btn" style="background:#eee;color:#333;margin-top:20px;" onclick="fermerEntreeModal()">Fermer</button>
        </div>
    </div>
    <!-- Notifications -->
    <div id="notif" style="display:none;position:fixed;top:30px;right:30px;z-index:2000;padding:18px 30px;border-radius:6px;font-size:18px;font-weight:bold;"></div>
    <script>
function ouvrirModal() {
    document.getElementById('rapportModal').style.display = 'flex';
}
function fermerModal() {
    document.getElementById('rapportModal').style.display = 'none';
}
function showNotif(msg, color='#4caf50') {
    var n = document.getElementById('notif');
    n.innerText = msg;
    n.style.background = color;
    n.style.display = 'block';
    setTimeout(()=>{ n.style.display='none'; }, 4000);
}
function envoyerRapport(type) {
    fermerModal();
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'interface.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4) {
            try {
                var r = JSON.parse(xhr.responseText);
                if(r.status === 'success') showNotif('Rapport envoyé avec succès !', '#4caf50');
                else showNotif('Erreur lors de l\'envoi du rapport.', '#f44336');
            } catch(e) {
                showNotif('Erreur inattendue.', '#f44336');
            }
        }
    };
    xhr.onerror = function() {
        showNotif('Erreur de connexion réseau.', '#f44336');
    };
    xhr.send('action=send_rapport&type='+encodeURIComponent(type));
}
function ouvrirRapportModal() {
    document.getElementById('voirRapportModal').style.display = 'flex';
    document.getElementById('contenuRapport').innerHTML = '';
}
function fermerRapportModal() {
    document.getElementById('voirRapportModal').style.display = 'none';
}
function afficherRapport(type) {
    document.getElementById('contenuRapport').innerHTML = 'Chargement...';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'interface.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4) {
            document.getElementById('contenuRapport').innerHTML = xhr.responseText;
        }
    };
    xhr.send('action=voir_rapport&type='+encodeURIComponent(type));
}
function imprimerFacture() {
    var contenu = document.getElementById('facture').innerHTML.trim();
    if (!contenu) {
        alert("Veuillez d'abord remplir et valider les informations pour générer la facture.");
        return;
    }
    var fenetre = window.open('', '', 'height=600,width=800');
    fenetre.document.write('<html><head><title>Facture</title>');
    fenetre.document.write('<style>');
    fenetre.document.write('@media print { @page { size: auto; margin: 25.4mm; } body { margin: 0 !important; } }');
    fenetre.document.write('body { margin: 0; font-size:14px; }');
    fenetre.document.write('#facture { margin: 0; padding: 0; box-shadow: none; }');
    fenetre.document.write('</style>');
    fenetre.document.write('</head><body>');
    fenetre.document.write('<div id="facture">' + contenu + '</div>');
    fenetre.document.write('</body></html>');
    fenetre.document.close();
    fenetre.focus();
    fenetre.print();
    fenetre.close();
}
function rechercherOperation() {
    var q = document.getElementById('recherche').value.trim();
    var resultDiv = document.getElementById('resultatsRecherche');
    if (q === '') {
        resultDiv.innerHTML = ''; // Vide la fenêtre si rien n'est saisi
        return;
    }
    resultDiv.innerHTML = 'Recherche...';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'interface.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4) {
            resultDiv.innerHTML = xhr.responseText;
        }
    };
    xhr.send('action=recherche&q='+encodeURIComponent(q));
}
function exporterRapport(type) {
    window.open('interface.php?action=exporter_rapport&type='+encodeURIComponent(type), '_blank');
}
function ouvrirEntreeModal() {
    document.getElementById('entreeModal').style.display = 'flex';
    document.getElementById('contenuEntree').innerHTML = 'Chargement...';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'interface.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if(xhr.readyState === 4) {
            document.getElementById('contenuEntree').innerHTML = xhr.responseText;
        }
    };
    xhr.send('action=voir_entree');
}
function fermerEntreeModal() {
    document.getElementById('entreeModal').style.display = 'none';
}
function nouvelleFacture() {
    document.querySelector('form').reset();
    document.getElementById('facture').innerHTML = '';
    document.getElementById('btnNouvelleFacture').style.display = 'none';
    document.getElementById('btnImprimerFacture').disabled = true;
    window.scrollTo(0,0);
}
</script>
<?php
// ... après echo '</div>'; (fin de la facture)
echo "<script>document.getElementById('btnNouvelleFacture').style.display='inline-block';</script>";
echo "<script>document.getElementById('btnImprimerFacture').disabled = false;</script>";
?>
<a href="logout.php" style="float:right;color:#fff;margin-right:20px;">Déconnexion</a>
</body>
</html>