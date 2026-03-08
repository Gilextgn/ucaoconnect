<?php
session_start();

// Chemin absolu vers config.php
$configPath = __DIR__ . '/config/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    die("Fichier config.php non trouvé à: $configPath<br>
         Placez ce fichier dans le même dossier que config/");
}

echo "<h2>Vérification User ID vs Articles</h2>";

// 1. Vérifier la session
echo "<h3>1. Session</h3>";
if (empty($_SESSION)) {
    echo "<p style='color: red;'>Session vide ! Connectez-vous d'abord.</p>";
    echo '<p><a href="/marketplace/auth/login.php">Se connecter</a></p>';
    
    // Simuler une session pour test
    $_SESSION['user_id'] = '1';
    $_SESSION['email'] = 'gituntog@gmail.com';
    $_SESSION['first_name'] = 'Bruce Jacob';
    $_SESSION['last_name'] = 'AMOUSSOU';
    
    echo "<p>Session simulée pour test:</p>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
} else {
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
}

// 2. Connexion DB - UTILISEZ LES CONSTANTES
echo "<h3>2. Connexion à la base de données</h3>";
echo "<p>DB_HOST: " . DB_HOST . "</p>";
echo "<p>DB_NAME: " . DB_NAME . "</p>";
echo "<p>DB_USER: " . DB_USER . "</p>";

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_error) {
    die("<p style='color: red;'>Erreur connexion MySQL: " . $mysqli->connect_error . "</p>");
} else {
    echo "<p style='color: green;'>✓ Connexion MySQL réussie</p>";
}

// 3. Vérifier votre user_id réel dans la table users
$email = $_SESSION['email'] ?? 'gituntog@gmail.com';
echo "<h3>3. Recherche de l'utilisateur avec email: " . $email . "</h3>";

$stmt = $mysqli->prepare("SELECT id, email, first_name, last_name FROM users WHERE email = ?");
if (!$stmt) {
    die("<p style='color: red;'>Erreur préparation requête: " . $mysqli->error . "</p>");
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p style='color: red;'>✗ Aucun utilisateur trouvé avec cet email</p>";
    
    // Lister tous les utilisateurs
    echo "<h4>Tous les utilisateurs :</h4>";
    $allUsers = $mysqli->query("SELECT id, email, first_name, last_name FROM users LIMIT 10");
    if ($allUsers) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Email</th><th>Nom</th></tr>";
        while ($user = $allUsers->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . $user['email'] . "</td>";
            echo "<td>" . $user['first_name'] . ' ' . $user['last_name'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    $user = $result->fetch_assoc();
    
    echo "<h3>4. User trouvé dans la base</h3>";
    echo "<pre>";
    print_r($user);
    echo "</pre>";

    // 4. Vérifier les articles avec CE user_id
    $real_user_id = $user['id'];
    
    echo "<h3>5. Articles pour user_id = '$real_user_id'</h3>";
    
    $stmt2 = $mysqli->prepare("SELECT COUNT(*) as total FROM articles WHERE user_id = ?");
    if (!$stmt2) {
        die("<p style='color: red;'>Erreur préparation requête articles: " . $mysqli->error . "</p>");
    }
    
    $stmt2->bind_param("s", $real_user_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    $row = $result2->fetch_assoc();
    
    echo "<p>Nombre d'articles: <strong>" . $row['total'] . "</strong></p>";
    
    if ($row['total'] > 0) {
        $stmt3 = $mysqli->prepare("SELECT id, titre, prix, statut, created_at FROM articles WHERE user_id = ? ORDER BY created_at DESC");
        $stmt3->bind_param("s", $real_user_id);
        $stmt3->execute();
        $result3 = $stmt3->get_result();
        
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Titre</th><th>Prix</th><th>Statut</th><th>Créé le</th></tr>";
        while ($article = $result3->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $article['id'] . "</td>";
            echo "<td>" . htmlspecialchars($article['titre']) . "</td>";
            echo "<td>" . $article['prix'] . " FCFA</td>";
            echo "<td>" . $article['statut'] . "</td>";
            echo "<td>" . $article['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠ Aucun article trouvé pour cet utilisateur</p>";
    }
}

// 5. Vérifier tous les user_id dans articles
echo "<h3>6. Tous les user_id dans la table articles</h3>";
$result = $mysqli->query("SELECT DISTINCT user_id, COUNT(*) as nb_articles FROM articles GROUP BY user_id ORDER BY nb_articles DESC");

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>User ID</th><th>Nombre d'articles</th><th>Type</th></tr>";
    while ($row = $result->fetch_assoc()) {
        $type = gettype($row['user_id']);
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
        echo "<td>" . $row['nb_articles'] . "</td>";
        echo "<td>" . $type . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Aucun article dans la table</p>";
}

// 6. Structure de la table articles
echo "<h3>7. Structure de la table articles</h3>";
$desc = $mysqli->query("DESCRIBE articles");
if ($desc) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Default</th></tr>";
    while ($col = $desc->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 7. Vérifier les 10 derniers articles
echo "<h3>8. 10 derniers articles (tous utilisateurs)</h3>";
$recent = $mysqli->query("SELECT id, titre, user_id, statut, created_at FROM articles ORDER BY created_at DESC LIMIT 10");
if ($recent && $recent->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Titre</th><th>User ID</th><th>Statut</th><th>Date</th></tr>";
    while ($article = $recent->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $article['id'] . "</td>";
        echo "<td>" . htmlspecialchars(substr($article['titre'], 0, 30)) . "...</td>";
        echo "<td>" . htmlspecialchars($article['user_id']) . "</td>";
        echo "<td>" . $article['statut'] . "</td>";
        echo "<td>" . $article['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$mysqli->close();
?>