<?php
// config/database.php

/**
 * Classe Database avec MySQLi
 */
class Database {
    private $conn;
    
    public function __construct() {
        // Essayer de charger la configuration depuis différents emplacements
        $configPaths = [
            __DIR__ . '/config.php',
            __DIR__ . '/../config/config.php',
            __DIR__ . '/../../config/config.php'
        ];
        
        $configLoaded = false;
        foreach ($configPaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $configLoaded = true;
                error_log("Configuration chargée depuis: $path");
                break;
            }
        }
        
        // Définir les constantes par défaut si elles n'existent pas
        if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
        if (!defined('DB_NAME')) define('DB_NAME', 'marketplace');
        if (!defined('DB_USER')) define('DB_USER', 'root');
        if (!defined('DB_PASS')) define('DB_PASS', '');
        
        error_log("Connexion à MySQLi - Host: " . DB_HOST . ", DB: " . DB_NAME);
        
        try {
            // Établir la connexion
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            // Vérifier les erreurs de connexion
            if ($this->conn->connect_error) {
                throw new Exception("Erreur de connexion MySQLi: " . $this->conn->connect_error);
            }
            
            // Définir le charset
            $this->conn->set_charset("utf8mb4");
            
            error_log("Connexion MySQLi établie avec succès");
            
        } catch (Exception $e) {
            error_log("ERREUR Database::__construct: " . $e->getMessage());
            throw new Exception("Impossible de se connecter à la base de données. Veuillez réessayer plus tard.");
        }
    }
    
    /**
     * Méthode select
     */
    public function select($table, $columns = ['*'], $where = [], $limit = null, $offset = null, $orderBy = null) {
        try {
            // Construire la requête SELECT
            $columnsStr = implode(', ', $columns);
            $sql = "SELECT $columnsStr FROM `$table`";
            
            // Ajouter les conditions WHERE
            $params = [];
            $types = '';
            
            if (!empty($where)) {
                $conditions = [];
                foreach ($where as $key => $value) {
                    // Gérer les valeurs NULL/vides
                    if ($value === null || $value === '') {
                        $conditions[] = "`$key` IS NULL";
                    } else {
                        $conditions[] = "`$key` = ?";
                        $params[] = $value;
                        
                        // Déterminer le type pour bind_param
                        if (is_int($value)) {
                            $types .= 'i';
                        } elseif (is_float($value)) {
                            $types .= 'd';
                        } else {
                            $types .= 's';
                        }
                    }
                }
                $sql .= " WHERE " . implode(' AND ', $conditions);
            }
            
            // Ajouter ORDER BY
            if ($orderBy) {
                $sql .= " ORDER BY $orderBy";
            }
            
            // Ajouter LIMIT et OFFSET
            if ($limit !== null) {
                $sql .= " LIMIT ?";
                $params[] = (int)$limit;
                $types .= 'i';
                
                if ($offset !== null) {
                    $sql .= " OFFSET ?";
                    $params[] = (int)$offset;
                    $types .= 'i';
                }
            }
            
            // Préparer la requête
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Erreur de préparation: " . $this->conn->error . " - SQL: $sql");
            }
            
            // Bind les paramètres si nécessaire
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            // Exécuter
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Récupérer les résultats
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            
            $stmt->close();
            
            return $rows;
            
        } catch (Exception $e) {
            error_log("Erreur Database::select: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Compte le nombre d'enregistrements dans une table
     */
    public function count($table, $conditions = []) {
        try {
            $sql = "SELECT COUNT(*) as total FROM `$table`";
            $params = [];
            $types = '';
            
            if (!empty($conditions)) {
                $whereParts = [];
                foreach ($conditions as $key => $value) {
                    if ($value === null || $value === '') {
                        $whereParts[] = "`$key` IS NULL";
                    } else {
                        $whereParts[] = "`$key` = ?";
                        $params[] = $value;
                        
                        if (is_int($value)) {
                            $types .= 'i';
                        } elseif (is_float($value)) {
                            $types .= 'd';
                        } else {
                            $types .= 's';
                        }
                    }
                }
                $sql .= " WHERE " . implode(' AND ', $whereParts);
            }
            
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Erreur de préparation: " . $this->conn->error);
            }
            
            // Bind les paramètres si nécessaire
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            $stmt->close();
            
            return (int)($row['total'] ?? 0);
            
        } catch (Exception $e) {
            error_log("Erreur Database::count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Méthode selectWithOr - Recherche avec conditions OR
     */
    public function selectWithOr($table, $columns = ['*'], $orFields = [], $searchValue, $andConditions = [], $orderBy = null, $limit = null, $offset = null) {
        try {
            $columnsStr = implode(', ', $columns);
            $sql = "SELECT $columnsStr FROM `$table`";
            
            $params = [];
            $types = '';
            
            // Construction des conditions WHERE
            $conditions = [];
            
            // Conditions AND
            if (!empty($andConditions)) {
                foreach ($andConditions as $key => $value) {
                    if ($value === null || $value === '') {
                        $conditions[] = "`$key` IS NULL";
                    } else {
                        $conditions[] = "`$key` = ?";
                        $params[] = $value;
                        
                        if (is_int($value)) $types .= 'i';
                        elseif (is_float($value)) $types .= 'd';
                        else $types .= 's';
                    }
                }
            }
            
            // Conditions OR pour la recherche
            if (!empty($orFields) && !empty($searchValue)) {
                $orConditions = [];
                foreach ($orFields as $field) {
                    $orConditions[] = "`$field` LIKE ?";
                    $params[] = "%$searchValue%";
                    $types .= 's';
                }
                $conditions[] = "(" . implode(' OR ', $orConditions) . ")";
            }
            
            // Ajout des conditions à la requête
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(' AND ', $conditions);
            }
            
            // Clause ORDER BY
            if ($orderBy) {
                $sql .= " ORDER BY $orderBy";
            }
            
            // Pagination
            if ($limit !== null) {
                $sql .= " LIMIT ?";
                $params[] = (int)$limit;
                $types .= 'i';
                
                if ($offset !== null) {
                    $sql .= " OFFSET ?";
                    $params[] = (int)$offset;
                    $types .= 'i';
                }
            }
            
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Erreur de préparation: " . $this->conn->error);
            }
            
            // Bind les paramètres si nécessaire
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            
            $stmt->close();
            
            return $rows;
            
        } catch (Exception $e) {
            error_log("Erreur Database::selectWithOr: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Compte avec conditions OR pour la recherche
     */
    public function countWithOr($table, $orFields = [], $searchValue, $andConditions = []) {
        try {
            $sql = "SELECT COUNT(*) as total FROM `$table`";
            
            $params = [];
            $types = '';
            
            // Construction des conditions WHERE
            $conditions = [];
            
            // Conditions AND
            if (!empty($andConditions)) {
                foreach ($andConditions as $key => $value) {
                    if ($value === null || $value === '') {
                        $conditions[] = "`$key` IS NULL";
                    } else {
                        $conditions[] = "`$key` = ?";
                        $params[] = $value;
                        
                        if (is_int($value)) $types .= 'i';
                        elseif (is_float($value)) $types .= 'd';
                        else $types .= 's';
                    }
                }
            }
            
            // Conditions OR pour la recherche
            if (!empty($orFields) && !empty($searchValue)) {
                $orConditions = [];
                foreach ($orFields as $field) {
                    $orConditions[] = "`$field` LIKE ?";
                    $params[] = "%$searchValue%";
                    $types .= 's';
                }
                $conditions[] = "(" . implode(' OR ', $orConditions) . ")";
            }
            
            // Ajout des conditions à la requête
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(' AND ', $conditions);
            }
            
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Erreur de préparation: " . $this->conn->error);
            }
            
            // Bind les paramètres si nécessaire
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            $stmt->close();
            
            return (int)($row['total'] ?? 0);
            
        } catch (Exception $e) {
            error_log("Erreur Database::countWithOr: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Méthode update
     */
    public function update($table, $data, $where) {
        try {
            // Construire SET
            $setParts = [];
            $params = [];
            $types = '';
            
            foreach ($data as $key => $value) {
                // Gérer les TIMESTAMP/NULL
                if ($value === null || ($key === 'updated_at' && empty($value))) {
                    $setParts[] = "`$key` = NOW()";
                } elseif ($value === '') {
                    $setParts[] = "`$key` = NULL";
                } else {
                    $setParts[] = "`$key` = ?";
                    $params[] = $value;
                    
                    if (is_int($value)) $types .= 'i';
                    elseif (is_float($value)) $types .= 'd';
                    else $types .= 's';
                }
            }
            $setStr = implode(', ', $setParts);
            
            // Construire WHERE
            $whereParts = [];
            foreach ($where as $key => $value) {
                if ($value === null || $value === '') {
                    $whereParts[] = "`$key` IS NULL";
                } else {
                    $whereParts[] = "`$key` = ?";
                    $params[] = $value;
                    
                    if (is_int($value)) $types .= 'i';
                    elseif (is_float($value)) $types .= 'd';
                    else $types .= 's';
                }
            }
            $whereStr = implode(' AND ', $whereParts);
            
            $sql = "UPDATE `$table` SET $setStr WHERE $whereStr";
            
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Erreur de préparation: " . $this->conn->error);
            }
            
            // Bind les paramètres si nécessaire
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Erreur Database::update: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Méthode insert
     */
    public function insert($table, $data) {
        try {
            $columns = array_map(function($col) {
                return "`$col`";
            }, array_keys($data));
            
            $columnsStr = implode(', ', $columns);
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            
            $sql = "INSERT INTO `$table` ($columnsStr) VALUES ($placeholders)";
            
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Erreur de préparation: " . $this->conn->error);
            }
            
            // Bind les paramètres
            $types = '';
            $params = [];
            
            foreach ($data as $value) {
                if (is_int($value)) $types .= 'i';
                elseif (is_float($value)) $types .= 'd';
                else $types .= 's';
                
                $params[] = $value;
            }
            
            $stmt->bind_param($types, ...$params);
            $result = $stmt->execute();
            
            $insertId = $stmt->insert_id;
            $stmt->close();
            
            return $insertId;
            
        } catch (Exception $e) {
            error_log("Erreur Database::insert: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Méthode delete - CORRIGÉE
     */
    public function delete($table, $where) {
        try {
            error_log("Database::delete - Table: $table");
            
            $whereParts = [];
            $params = [];
            $types = '';
            
            foreach ($where as $key => $value) {
                if ($value === null || $value === '') {
                    $whereParts[] = "`$key` IS NULL";
                } else {
                    $whereParts[] = "`$key` = ?";
                    $params[] = $value;
                    
                    if (is_int($value)) $types .= 'i';
                    elseif (is_float($value)) $types .= 'd';
                    else $types .= 's';
                }
            }
            
            if (empty($whereParts)) {
                throw new Exception("Impossible de supprimer sans condition WHERE");
            }
            
            $whereStr = implode(' AND ', $whereParts);
            $sql = "DELETE FROM `$table` WHERE $whereStr";
            
            error_log("Database::delete - SQL: $sql");
            
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Erreur de préparation: " . $this->conn->error);
            }
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $result = $stmt->execute();
            $affectedRows = $stmt->affected_rows;
            
            error_log("Database::delete - Résultat: " . ($result ? 'SUCCÈS' : 'ÉCHEC'));
            error_log("Database::delete - Lignes affectées: $affectedRows");
            
            $stmt->close();
            
            return $result && $affectedRows > 0;
            
        } catch (Exception $e) {
            error_log("ERREUR Database::delete: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Exécute une requête SQL brute
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Erreur de préparation: " . $this->conn->error);
            }
            
            if (!empty($params)) {
                $types = '';
                $preparedParams = [];
                
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } else {
                        $types .= 's';
                    }
                    $preparedParams[] = $param;
                }
                
                $stmt->bind_param($types, ...$preparedParams);
            }
            
            $stmt->execute();
            
            // Vérifier si c'est une requête SELECT
            if (stripos(trim($sql), 'SELECT') === 0) {
                $result = $stmt->get_result();
                $rows = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                return $rows;
            } else {
                $affectedRows = $stmt->affected_rows;
                $stmt->close();
                return $affectedRows;
            }
            
        } catch (Exception $e) {
            error_log("Erreur Database::query: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Échappe une chaîne pour prévenir les injections SQL
     */
    public function escape($string) {
        return $this->conn->real_escape_string($string);
    }
    
    /**
     * Obtenir la connexion directe
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Vérifier si la connexion est active
     */
    public function isConnected() {
        return $this->conn && $this->conn->ping();
    }
    
    /**
     * Méthode de test pour compatibilité
     */
    public function testConnection() {
        try {
            if (!$this->conn) {
                return false;
            }
            
            // Test avec ping
            if (!$this->conn->ping()) {
                return false;
            }
            
            // Test avec une requête simple
            $result = $this->conn->query("SELECT 1");
            return $result !== false;
            
        } catch (Exception $e) {
            error_log("Database::testConnection() erreur: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtenir la dernière erreur
     */
    public function getLastError() {
        return $this->conn ? $this->conn->error : "Pas de connexion active";
    }
    
    /**
     * Propriété publique pour compatibilité avec le code existant
     */
    public function __get($name) {
        if ($name === 'connection') {
            return $this->conn;
        }
        return null;
    }

    /* METHODE createThumbnail */
    private static function createThumbnail($sourcePath, $destinationDir, $filename) {
    // Dimensions max de la miniature
    $thumbWidth = 300;
    $thumbHeight = 300;
    
    // Obtenir les informations de l'image
    list($width, $height, $type) = getimagesize($sourcePath);
    
    // Déterminer le type d'image et créer la source
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
                return false;
            }
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Calculer les nouvelles dimensions (Correction du float en int)
    $aspectRatio = $width / $height;
    
    if ($width > $height) {
        // Image paysage
        $newWidth = $thumbWidth;
        $newHeight = (int)round($thumbWidth / $aspectRatio);
    } else {
        // Image portrait
        $newHeight = $thumbHeight;
        $newWidth = (int)round($thumbHeight * $aspectRatio);
    }
    
    // Créer le canevas de la miniature avec les dimensions entières
    $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
    
    // Gestion de la transparence (PNG, GIF, WEBP)
    if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP])) {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Redimensionner
    imagecopyresampled(
        $thumbnail, $sourceImage, 
        0, 0, 0, 0, 
        $newWidth, $newHeight, 
        $width, $height
    );
    
    // Sauvegarder la miniature
    $thumbnailName = 'thumb_' . $filename;
    $thumbnailPath = $destinationDir . '/' . $thumbnailName;
    
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
    
    // Libérer la mémoire
    imagedestroy($sourceImage);
    imagedestroy($thumbnail);
    
    return file_exists($thumbnailPath);
}
    
    /**
     * Fermer la connexion
     */
    public function close() {
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
    }
    
    /**
     * Destructeur - ferme automatiquement la connexion
     */
    public function __destruct() {
        $this->close();
    }
}
?>