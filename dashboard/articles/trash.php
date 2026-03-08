<?php
    // dashboard/articles/trash.php - Gestion des articles supprimés

    require_once __DIR__ . '/../../includes/auth-check.php';
    require_once __DIR__ . '/../../config/database.php';

    requireAuth();

    $db     = new Database();
    $userId = $_SESSION['user_id'];

    // Récupérer les articles supprimés (soft deleted)
    try {
        // Requête directe pour inclure les articles supprimés
        $conn = $db->getConnection();
        $stmt = $conn->prepare("
    SELECT * FROM articles
    WHERE user_id = ?
    AND deleted_at IS NOT NULL
    ORDER BY deleted_at DESC
");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result          = $stmt->get_result();
        $deletedArticles = [];

        while ($row = $result->fetch_assoc()) {
            $deletedArticles[] = $row;
        }
        $stmt->close();

    } catch (Exception $e) {
        $error = 'Erreur lors du chargement de la corbeille: ' . $e->getMessage();
    }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corbeille - UCAO Marketplace</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        .trash-item {
            transition: all 0.3s;
        }

        .trash-item:hover {
            background-color: #f8f9fa;
        }

        .deleted-date {
            font-size: 0.85rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <h2 class="mb-4">
            <i class="bi bi-trash me-2"></i>
            Corbeille
        </h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (empty($deletedArticles)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Votre corbeille est vide
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Supprimé le</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deletedArticles as $article): ?>
                        <tr class="trash-item">
                            <td><?php echo htmlspecialchars($article['titre'] ?? 'Sans titre') ?></td>
                            <td><?php echo htmlspecialchars($article['categorie'] ?? 'Non catégorisé') ?></td>
                            <td><?php echo number_format($article['prix'] ?? 0, 2, ',', ' ') ?> FCFA</td>
                            <td class="deleted-date">
                                <?php echo date('d/m/Y H:i', strtotime($article['deleted_at'])) ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <form method="POST" action="delete.php" class="d-inline">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($article['id']) ?>">
                                        <input type="hidden" name="action" value="restore">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="bi bi-arrow-counterclockwise"></i> Restaurer
                                        </button>
                                    </form>

                                    <button type="button" class="btn btn-danger btn-sm"
                                            onclick="confirmHardDelete('<?php echo htmlspecialchars($article['id']) ?>')">
                                        <i class="bi bi-trash-fill"></i> Supprimer définitivement
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="alert alert-warning mt-3">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Note :</strong> Les articles dans la corbeille sont automatiquement supprimés définitivement après 30 jours.
            </div>
        <?php endif; ?>

        <a href="/dashboard/" class="btn btn-outline-secondary mt-3">
            <i class="bi bi-arrow-left me-2"></i>
            Retour au dashboard
        </a>
    </div>

    <script>
    function confirmHardDelete(articleId) {
        if (confirm('⚠️ ATTENTION : Cette suppression est définitive !\n\nÊtes-vous sûr de vouloir supprimer cet article de manière irréversible ?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete.php';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = articleId;

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'hard_delete';

            form.appendChild(idInput);
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>
</html>