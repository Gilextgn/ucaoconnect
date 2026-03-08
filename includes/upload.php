<?php
// includes/upload.php

class UploadManager {
    
    /**
     * Upload une image vers le dossier local
     */
    public static function uploadImage($file, $userId, $type = 'articles') {
        // Validation basique
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Erreur lors du téléchargement du fichier'];
        }
        
        // Vérification de la taille (5MB max)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'Fichier trop volumineux (maximum 5MB)'];
        }
        
        // Vérification du type MIME
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($file['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            return ['success' => false, 'message' => 'Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF, WebP'];
        }
        
        // Vérification de l'extension
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            return ['success' => false, 'message' => 'Extension de fichier non autorisée'];
        }
        
        // Créer le répertoire s'il n'existe pas
        $uploadDir = self::getUploadDirectory($userId, $type);
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return ['success' => false, 'message' => 'Impossible de créer le dossier de destination'];
            }
        }
        
        // Générer un nom de fichier unique et sécurisé
        $safeUserId = preg_replace('/[^a-zA-Z0-9]/', '_', $userId);
        $uniqueName = $safeUserId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $fileExtension;
        $uploadPath = $uploadDir . '/' . $uniqueName;
        
        // Déplacer le fichier téléchargé
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return ['success' => false, 'message' => 'Erreur lors du déplacement du fichier'];
        }
        
        // Créer une version miniature si c'est une image d'article
        if ($type === 'articles') {
            self::createThumbnail($uploadPath, $uploadDir, $uniqueName);
        }
        
        // Vérifier que le fichier a bien été créé
        if (!file_exists($uploadPath)) {
            return ['success' => false, 'message' => 'Fichier non créé après le déplacement'];
        }
        
        // Retourner le chemin relatif pour la base de données
        $relativePath = self::getRelativePath($uploadPath);
        
        return [
            'success' => true, 
            'url' => $relativePath,
            'filename' => $uniqueName,
            'full_path' => $uploadPath,
            'size' => filesize($uploadPath)
        ];
    }
    
    /**
     * Crée un répertoire d'upload pour l'utilisateur
     */
    private static function getUploadDirectory($userId, $type = 'articles') {
        // Nettoyer l'ID utilisateur pour le chemin
        $safeUserId = preg_replace('/[^a-zA-Z0-9]/', '_', $userId);
        
        // Déterminer le chemin en fonction du type
        $basePath = __DIR__ . '/../assets/uploads';
        
        switch ($type) {
            case 'profile':
                return $basePath . '/profiles/' . substr($safeUserId, 0, 2) . '/' . $safeUserId;
            case 'articles':
                return $basePath . '/articles/' . substr($safeUserId, 0, 2) . '/' . $safeUserId;
            default:
                return $basePath . '/misc/' . substr($safeUserId, 0, 2) . '/' . $safeUserId;
        }
    }
    
    /**
     * Obtient le chemin relatif pour la base de données
     */
    private static function getRelativePath($absolutePath) {
        // Convertir le chemin absolu en chemin relatif à partir de la racine du site
        $rootPath = realpath(__DIR__ . '/../');
        $relativePath = str_replace($rootPath, '', $absolutePath);
        
        // S'assurer que le chemin commence par "/"
        if (strpos($relativePath, '/') !== 0) {
            $relativePath = '/' . $relativePath;
        }
        
        // Remplacer les antislashs par des slashs (pour Windows)
        $relativePath = str_replace('\\', '/', $relativePath);
        
        return $relativePath;
    }
    
    /**
     * Crée une miniature pour les images d'articles
     */
    private static function createThumbnail($sourcePath, $destinationDir, $filename) {
    // 1. Définition de la taille maximale de la miniature
    $thumbWidth = 300;
    $thumbHeight = 300;
    
    // 2. Récupération des infos de l'image (Largeur, Hauteur, Type)
    list($width, $height, $type) = getimagesize($sourcePath);
    
    // 3. Création de la ressource image selon le format source
    switch ($type) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $sourceImage = imagecreatefromwebp($sourcePath);
            } else {
                return false; // WebP non supporté par le serveur
            }
            break;
        default:
            return false; // Format non reconnu
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // 4. Calcul des nouvelles dimensions proportionnelles (Ratio)
    $aspectRatio = $width / $height;
    
    if ($width > $height) {
        // Mode Paysage : on fixe la largeur, on calcule la hauteur
        $newWidth = $thumbWidth;
        $newHeight = $thumbWidth / $aspectRatio;
    } else {
        // Mode Portrait : on fixe la hauteur, on calcule la largeur
        $newHeight = $thumbHeight;
        $newWidth = $thumbHeight * $aspectRatio;
    }

    /* CORRECTION PHP 8.1 : On force les dimensions en entiers (int) 
       car imagecreatetruecolor n'accepte plus les nombres à virgule (floats).
    */
    $newWidth = (int)round($newWidth);
    $newHeight = (int)round($newHeight);
    
    // 5. Création de la zone de dessin vide
    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
    
    // 6. Gestion de la transparence pour PNG et GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // 7. Redimensionnement (Copie de la source vers la miniature)
    // On utilise (int) partout pour éviter les erreurs de précision
    imagecopyresampled(
        $thumbnail, $sourceImage, 
        0, 0, 0, 0, 
        (int)$newWidth, (int)$newHeight, 
        (int)$width, (int)$height
    );
    
    // 8. Définition du chemin de sauvegarde
    $thumbnailName = 'thumb_' . $filename;
    $thumbnailPath = $destinationDir . '/' . $thumbnailName;
    
    // 9. Enregistrement du fichier selon le format d'origine
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($thumbnail, $thumbnailPath, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($thumbnail, $thumbnailPath, 8);
            break;
        case IMAGETYPE_GIF:
            imagegif($thumbnail, $thumbnailPath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagewebp')) {
                imagewebp($thumbnail, $thumbnailPath, 85);
            }
            break;
    }
    
    // 10. Nettoyage de la mémoire vive
    imagedestroy($sourceImage);
    imagedestroy($thumbnail);
    
    // Retourne true si le fichier existe bien sur le disque
    return file_exists($thumbnailPath);
}
    
    /**
     * Supprime un fichier uploadé
     */
    public static function deleteFile($filePath) {
        // Convertir le chemin relatif en chemin absolu
        $absolutePath = realpath(__DIR__ . '/../' . ltrim($filePath, '/'));
        
        if (!$absolutePath || !file_exists($absolutePath)) {
            return ['success' => false, 'message' => 'Fichier non trouvé'];
        }
        
        // Vérifier que le fichier est dans le dossier d'uploads
        $uploadsPath = realpath(__DIR__ . '/../assets/uploads');
        if (strpos($absolutePath, $uploadsPath) !== 0) {
            return ['success' => false, 'message' => 'Chemin non autorisé'];
        }
        
        // Supprimer le fichier
        if (!unlink($absolutePath)) {
            return ['success' => false, 'message' => 'Impossible de supprimer le fichier'];
        }
        
        // Essayer de supprimer la miniature si elle existe
        $thumbnailPath = dirname($absolutePath) . '/thumb_' . basename($absolutePath);
        if (file_exists($thumbnailPath)) {
            unlink($thumbnailPath);
        }
        
        return ['success' => true, 'message' => 'Fichier supprimé avec succès'];
    }
    
    /**
     * Valide un fichier avant upload
     */
    public static function validateFile($file) {
        $errors = [];
        
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Aucun fichier téléchargé ou erreur lors du téléchargement';
            return $errors;
        }
        
        // Vérification de la taille (5MB max)
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $errors[] = 'Le fichier est trop volumineux (maximum 5MB)';
        }
        
        // Vérification du type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($file['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = 'Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF, WebP';
        }
        
        return $errors;
    }
    
    /**
     * Nettoie le dossier d'uploads des fichiers anciens
     */
    public static function cleanupOldFiles($days = 30) {
        $uploadsPath = __DIR__ . '/../assets/uploads';
        $deletedCount = 0;
        
        if (!file_exists($uploadsPath)) {
            return $deletedCount;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getMTime() < $cutoffTime) {
                if (unlink($file->getRealPath())) {
                    $deletedCount++;
                }
            }
        }
        
        return $deletedCount;
    }
    
    /**
     * Obtient l'URL complète pour un fichier uploadé
     */
    public static function getFullUrl($relativePath) {
        if (empty($relativePath)) {
            return '';
        }
        
        // Déterminer l'URL de base
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . $host;
        
        // Retourner l'URL complète
        return $baseUrl . $relativePath;
    }
    
    /**
     * Upload multiple d'images
     */
    public static function uploadMultipleImages($files, $userId, $type = 'articles') {
        $results = [];
        
        if (!is_array($files) || !isset($files['name'])) {
            return ['success' => false, 'message' => 'Format de fichiers invalide'];
        }
        
        $fileCount = count($files['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                
                $result = self::uploadImage($file, $userId, $type);
                $results[] = $result;
            }
        }
        
        return $results;
    }
}
?>