<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Articles</h2>";

// 1. Test de la session
echo "<h3>1. Session</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// 2. Test de la connexion et de la table articles
require_once __DIR__ . '/../../config/config.php';

$mysqli = @new mysqli($host, $username, $password, $dbname);
if ($mysqli->connect_error) {
    echo "<div style='color: red;'>MySQLi Error: " . $mysqli->connect_error . "</div>";
    exit;
}
echo "<div style='color: green;'>✓ MySQLi connecté</div>";

// 3. Vérifiez spécifiquement les articles de l'utilisateur 1
echo "<h3>2. Articles pour user_id = 1</h3>";

$stmt = $mysqli->prepare("SELECT * FROM articles WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

echo "<p>Nombre d'articles pour user_id=1: " . $result->num_rows . "</p>";

if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Titre</th><th>Description</th><th>Prix</th><th>Catégorie</th><th>Statut</th><th>Créé le</th></tr>";
    
    while ($article = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $article['id'] . "</td>";
        echo "<td>" . htmlspecialchars($article['titre'] ?? 'Sans titre') . "</td>";
        echo "<td>" . substr(htmlspecialchars($article['description'] ?? ''), 0, 50) . "...</td>";
        echo "<td>" . ($article['prix'] ?? 0) . " FCFA</td>";
        echo "<td>" . ($article['categorie'] ?? 'Non catégorisé') . "</td>";
        echo "<td>" . ($article['statut'] ?? 'inconnu') . "</td>";
        echo "<td>" . ($article['created_at'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div style='color: orange; background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7;'>";
    echo "<strong>⚠ Aucun article trouvé pour user_id=1</strong><br>";
    echo "C'est pourquoi votre tableau de bord est vide !";
    echo "</div>";
    
    // Vérifiez si d'autres utilisateurs ont des articles
    echo "<h4>3. Vérification des autres utilisateurs</h4>";
    
    $result = $mysqli->query("
        SELECT 
            u.id as user_id,
            u.email,
            u.first_name,
            u.last_name,
            COUNT(a.id) as nb_articles
        FROM users u
        LEFT JOIN articles a ON u.id = a.user_id
        GROUP BY u.id
        ORDER BY nb_articles DESC
    ");
    
    if ($result->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>User ID</th><th>Nom</th><th>Email</th><th>Nombre d'articles</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            $highlight = ($row['user_id'] == 1) ? 'style="background: #fff3cd;"' : '';
            echo "<tr $highlight>";
            echo "<td>" . $row['user_id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . $row['nb_articles'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Vérifiez la structure exacte de la table articles
    echo "<h4>4. Structure de la table articles</h4>";
    $result = $mysqli->query("DESCRIBE articles");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Default</th><th>Extra</th></tr>";
    while ($col = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . $col['Default'] . "</td>";
        echo "<td>" . $col['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Fermer la connexion
$mysqli->close();
?>