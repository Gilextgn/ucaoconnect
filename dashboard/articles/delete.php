<?php
// dashboard/articles/delete.php - Version complète avec options

require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../config/database.php';

requireAuth();

$db = new Database();
$userId = $_SESSION['user_id'];

// Déterminer l'action (soft delete, hard delete, restore)
$action = $_POST['action'] ?? 'soft_delete';
$articleId = trim($_POST['id'] ?? '');

if (empty($articleId)) {
    $_SESSION['error_message'] = 'ID d\'article invalide';
    header('Location: /dashboard/');
    exit();
}

try {
    // Vérifier que l'article appartient à l'utilisateur
    $articles = $db->select('articles', ['*'], ['id' => $articleId, 'user_id' => $userId]);
    
    if (empty($articles)) {
        $_SESSION['error_message'] = 'Article non trouvé ou accès non autorisé';
        header('Location: /dashboard/');
        exit();
    }
    
    $article = $articles[0];
    $conn = $db->getConnection();
    
    switch ($action) {
        case 'soft_delete':
            // SOFT DELETE: Marquer comme supprimé
            $updateData = [
                'statut' => 'supprime',
                'deleted_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $db->update('articles', $updateData, ['id' => $articleId, 'user_id' => $userId]);
            
            if ($result) {
                $_SESSION['success_message'] = 'Article "' . htmlspecialchars($article['titre']) . '" déplacé vers la corbeille';
            } else {
                throw new Exception('Impossible de marquer l\'article comme supprimé');
            }
            break;
            
        case 'restore':
            // RESTAURER: Retirer le flag de suppression
            $updateData = [
                'statut' => 'disponible', // Ou l'ancien statut si vous le stockez
                'deleted_at' => null,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $db->update('articles', $updateData, ['id' => $articleId, 'user_id' => $userId]);
            
            if ($result) {
                $_SESSION['success_message'] = 'Article "' . htmlspecialchars($article['titre']) . '" restauré avec succès';
            } else {
                throw new Exception('Impossible de restaurer l\'article');
            }
            break;
            
        case 'hard_delete':
            // HARD DELETE: Suppression définitive (seulement pour l'admin ou après X jours)
            
            // Vérifier si l'utilisateur est admin ou si l'article est dans la corbeille depuis longtemps
            $canHardDelete = false;
            
            if ($_SESSION['is_admin'] ?? false) {
                $canHardDelete = true;
            } elseif (!empty($article['deleted_at'])) {
                // Supprimer définitivement seulement si dans la corbeille depuis plus de 30 jours
                $deletedDate = new DateTime($article['deleted_at']);
                $currentDate = new DateTime();
                $interval = $deletedDate->diff($currentDate);
                
                if ($interval->days > 30) {
                    $canHardDelete = true;
                }
            }
            
            if (!$canHardDelete) {
                $_SESSION['error_message'] = 'Vous ne pouvez pas supprimer définitivement cet article';
                header('Location: /dashboard/');
                exit();
            }
            
            // Commencer une transaction pour la suppression définitive
            $conn->begin_transaction();
            
            try {
                // 1. Supprimer les images physiques
                $deleteFile = function($filePath) {
                    if (!empty($filePath) && strpos($filePath, '/') === 0) {
                        $absolutePath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
                        if (file_exists($absolutePath) && is_writable($absolutePath)) {
                            return unlink($absolutePath);
                        }
                    }
                    return false;
                };
                
                if (!empty($article['image_url'])) {
                    $deleteFile($article['image_url']);
                }
                
                // Supprimer les images supplémentaires si la table existe
                $tableCheck = $conn->query("SHOW TABLES LIKE 'article_images'");
                if ($tableCheck && $tableCheck->num_rows > 0) {
                    $stmt = $conn->prepare("SELECT image_url FROM article_images WHERE article_id = ?");
                    $stmt->bind_param("s", $articleId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    while ($row = $result->fetch_assoc()) {
                        if (!empty($row['image_url'])) {
                            $deleteFile($row['image_url']);
                        }
                    }
                    $stmt->close();
                    
                    $deleteStmt = $conn->prepare("DELETE FROM article_images WHERE article_id = ?");
                    $deleteStmt->bind_param("s", $articleId);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                }
                
                // 2. Supprimer définitivement l'article
                $deleteArticle = $conn->prepare("DELETE FROM articles WHERE id = ? AND user_id = ?");
                $deleteArticle->bind_param("ss", $articleId, $userId);
                $deleteArticle->execute();
                
                if ($deleteArticle->affected_rows === 0) {
                    throw new Exception('Aucun article supprimé');
                }
                
                $deleteArticle->close();
                
                $conn->commit();
                $_SESSION['success_message'] = 'Article définitivement supprimé';
                
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;
            
        default:
            $_SESSION['error_message'] = 'Action non reconnue';
            break;
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Erreur: ' . $e->getMessage();
    error_log("Article action error: " . $e->getMessage());
}

// Rediriger vers le dashboard
header('Location: /dashboard/');
exit();
?>