<?php
// dashboard/profile/edit.php

require_once __DIR__ . '/../../includes/auth-check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/upload.php';

requireAuth();

$db = new Database();
$userId = $_SESSION['user_id'];
$errors = [];
$success = false;

// DEBUG: Afficher l'ID utilisateur
error_log("User ID: " . $userId);

// Récupérer les données de l'utilisateur ET du profil
$user = $db->select('users', ['*'], ['id' => $userId])[0] ?? [];
$profile = $db->select('profiles', ['*'], ['user_id' => $userId])[0] ?? [];

// DEBUG: Afficher les données récupérées
error_log("User data: " . print_r($user, true));
error_log("Profile data: " . print_r($profile, true));

// Fusionner les données (profil écrasera les champs communs de user)
$userData = array_merge($user, $profile);

if (empty($userData)) {
    // Créer un utilisateur par défaut avec les données de session
    $userData = [
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'filiere' => $_SESSION['filiere'] ?? '',
        'phone' => $_SESSION['phone'] ?? '',
        'photo_url' => null
    ];
}

// Liste des filières
$filieres = [
    'Informatique de Gestion (IG)',
    'Gestion des Entreprises Rurales et Agricoles (GERA)',
    'Production et Gestion des Ressources Animales (PGRA)',
    'Sciences et Techniques de Production Végétale (STPV)',
    'Gestion de l\'Environnement et Aménagement du Territoire (GEAT)',
    'Electronique (ELN)',
    'Système Industriel (SI)',
    'Informatique Industrielle et Maintenance (IIM)',
    'Génie Télécoms et TIC (GT-TICS)',
    'Droit (DRT)',
    'Economie (ECO)',
    'Action Commerciale et Force de Vente (ACFV)',
    'Communication et Action Publicitaire (CAP)',
    'Audit et Contrôle de Gestion (ACG)',
    'Assurance (ASS)',
    'Management des Ressources Humaines (MRH)',
    'Transport & Logistique (TL)',
    'Commerce International (CI )',
];

// Initialiser les données du formulaire
$formData = [
    'first_name' => $userData['first_name'] ?? '',
    'last_name' => $userData['last_name'] ?? '',
    'email' => $userData['email'] ?? '',
    'filiere' => $userData['filiere'] ?? '',
    'phone' => $userData['phone'] ?? '',
    'bio' => $userData['bio'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation et nettoyage
    $formData['first_name'] = trim($_POST['first_name'] ?? '');
    $formData['last_name'] = trim($_POST['last_name'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['filiere'] = $_POST['filiere'] ?? '';
    $formData['phone'] = trim($_POST['phone'] ?? '');
    $formData['bio'] = trim($_POST['bio'] ?? '');
    
    // Validation
    if (empty($formData['first_name'])) {
        $errors[] = 'Le prénom est obligatoire';
    }
    
    if (empty($formData['last_name'])) {
        $errors[] = 'Le nom est obligatoire';
    }
    
    if (empty($formData['email'])) {
        $errors[] = 'L\'email est obligatoire';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format d\'email invalide';
    }
    
    // Vérifier si l'email est déjà utilisé par un autre utilisateur
    if ($formData['email'] !== ($userData['email'] ?? '')) {
        $existingUser = $db->select('users', ['id'], ['email' => $formData['email']]);
        if (!empty($existingUser)) {
            $errors[] = 'Cet email est déjà utilisé par un autre compte';
        }
    }
    
    // Validation du téléphone (optionnel mais formaté si présent)
    if (!empty($formData['phone'])) {
        $formData['phone'] = preg_replace('/[^0-9]/', '', $formData['phone']);
        if (strlen($formData['phone']) < 9) {
            $errors[] = 'Numéro de téléphone invalide';
        }
    }
    
    // Gestion de la photo de profil
    $photoUrl = $userData['photo_url'] ?? null;
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = UploadManager::uploadImage($_FILES['photo'], $userId, 'profile');
        
        if ($uploadResult['success']) {
            $photoUrl = $uploadResult['url'];
            
            // Supprimer l'ancienne photo si elle existe
            if (!empty($userData['photo_url']) && $userData['photo_url'] !== $photoUrl) {
                UploadManager::deleteFile($userData['photo_url']);
            }
        } else {
            $errors[] = 'Erreur lors de l\'upload de la photo: ' . $uploadResult['message'];
        }
    }
    
    // Vérifier si l'utilisateur veut supprimer la photo actuelle
    if (isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
        if (!empty($userData['photo_url'])) {
            UploadManager::deleteFile($userData['photo_url']);
            $photoUrl = null;
        }
    }
    
    // Mise à jour si pas d'erreurs
    if (empty($errors)) {
        $conn = $db->getConnection();
        
        // DEBUG: Vérifier la connexion
        if (!$conn) {
            $errors[] = 'Erreur de connexion à la base de données';
        } else {
            // Données pour la table users
            $userUpdate = [
                'email' => $formData['email'],
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Données pour la table profiles
            $profileUpdate = [
                'first_name' => $formData['first_name'],
                'last_name' => $formData['last_name'],
                'filiere' => $formData['filiere'],
                'phone' => $formData['phone'],
                'bio' => $formData['bio'],
                'photo_url' => $photoUrl,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // COMMENCER LA TRANSACTION
            $conn->begin_transaction();
            
            try {
                // 1. Mettre à jour la table users
                $userExists = $db->select('users', ['id'], ['id' => $userId]);
                
                if (empty($userExists)) {
                    // Insérer un nouvel utilisateur
                    $userUpdate['id'] = $userId;
                    $userUpdate['created_at'] = date('Y-m-d H:i:s');
                    
                    $sqlUser = "INSERT INTO users (id, email, created_at, updated_at, photo_url) VALUES (?, ?, ?, ?)";
                    $stmtUser = $conn->prepare($sqlUser);
                    if (!$stmtUser) {
                        throw new Exception("Erreur préparation users INSERT: " . $conn->error);
                    }
                    $stmtUser->bind_param("ssss", 
                        $userUpdate['id'],
                        $userUpdate['email'],
                        $userUpdate['created_at'],
                        $userUpdate['updated_at']
                    );
                } else {
                    // Mettre à jour l'utilisateur existant
                    $sqlUser = "UPDATE users SET email = ?, updated_at = ? WHERE id = ?";
                    $stmtUser = $conn->prepare($sqlUser);
                    if (!$stmtUser) {
                        throw new Exception("Erreur préparation users UPDATE: " . $conn->error);
                    }
                    $stmtUser->bind_param("sss", 
                        $userUpdate['email'],
                        $userUpdate['updated_at'],
                        $userId
                    );
                }
                
                if (!$stmtUser->execute()) {
                    throw new Exception("Erreur exécution users: " . $stmtUser->error);
                }
                $stmtUser->close();
                
                // 2. Mettre à jour la table profiles
                $profileExists = $db->select('profiles', ['id'], ['user_id' => $userId]);
                
                if (empty($profileExists)) {
                    // Insérer un nouveau profil
                    $profileUpdate['user_id'] = $userId;
                    $profileUpdate['created_at'] = date('Y-m-d H:i:s');
                    
                    $sqlProfile = "INSERT INTO profiles (user_id, first_name, last_name, filiere, phone, bio, photo_url, created_at, updated_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmtProfile = $conn->prepare($sqlProfile);
                    if (!$stmtProfile) {
                        throw new Exception("Erreur préparation profiles INSERT: " . $conn->error);
                    }
                    $stmtProfile->bind_param("sssssssss",
                        $profileUpdate['user_id'],
                        $profileUpdate['first_name'],
                        $profileUpdate['last_name'],
                        $profileUpdate['filiere'],
                        $profileUpdate['phone'],
                        $profileUpdate['bio'],
                        $profileUpdate['photo_url'],
                        $profileUpdate['created_at'],
                        $profileUpdate['updated_at']
                    );
                } else {
                    // Mettre à jour le profil existant
                    $sqlProfile = "UPDATE profiles SET first_name = ?, last_name = ?, filiere = ?, phone = ?, bio = ?, photo_url = ?, updated_at = ?
                                   WHERE user_id = ?";
                    $stmtProfile = $conn->prepare($sqlProfile);
                    if (!$stmtProfile) {
                        throw new Exception("Erreur préparation profiles UPDATE: " . $conn->error);
                    }
                    $stmtProfile->bind_param("ssssssss",
                        $profileUpdate['first_name'],
                        $profileUpdate['last_name'],
                        $profileUpdate['filiere'],
                        $profileUpdate['phone'],
                        $profileUpdate['bio'],
                        $profileUpdate['photo_url'],
                        $profileUpdate['updated_at'],
                        $userId
                    );
                }
                
                if (!$stmtProfile->execute()) {
                    throw new Exception("Erreur exécution profiles: " . $stmtProfile->error);
                }
                $stmtProfile->close();
                
                // VALIDER LA TRANSACTION
                $conn->commit();
                $success = true;
                
                // Mettre à jour les données de session
                $_SESSION['first_name'] = $formData['first_name'];
                $_SESSION['last_name'] = $formData['last_name'];
                $_SESSION['email'] = $formData['email'];
                $_SESSION['filiere'] = $formData['filiere'];
                $_SESSION['phone'] = $formData['phone'];
                
                if ($photoUrl) {
                    $_SESSION['profile_photo'] = $photoUrl;
                } elseif (isset($_POST['remove_photo'])) {
                    unset($_SESSION['profile_photo']);
                }
                
                // Recharger les données
                $user = $db->select('users', ['*'], ['id' => $userId])[0] ?? [];
                $profile = $db->select('profiles', ['*'], ['user_id' => $userId])[0] ?? [];
                $userData = array_merge($user, $profile);
                
            } catch (Exception $e) {
                // ANNULER LA TRANSACTION EN CAS D'ERREUR
                $conn->rollback();
                $errors[] = 'Erreur lors de la mise à jour: ' . $e->getMessage();
                error_log("Transaction error: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier mon profil - UCAO Marketplace</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .profile-edit-container {
            flex: 1;
        }
        
        /* Header */
        .navbar-brand {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .navbar-brand i {
            color: #3498db;
        }
        
        /* Main Content */
        .main-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 30px;
        }
        
        .card-header {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 25px 30px;
            border: none;
        }
        
        .card-body {
            padding: 35px;
        }
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select, .form-textarea {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 14px 18px;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .form-control:focus, .form-select:focus, .form-textarea:focus {
            border-color: #9b59b6;
            box-shadow: 0 0 0 0.25rem rgba(155, 89, 182, 0.25);
            outline: none;
        }
        
        /* Photo de profil */
        .profile-photo-container {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .profile-photo {
            width: 180px;
            height: 180px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid #f8f9fa;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        
        .profile-photo:hover {
            transform: scale(1.05);
        }
        
        .profile-photo-placeholder {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f1f3f4 0%, #e8eaed 100%);
            border: 5px solid #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .profile-photo-placeholder i {
            font-size: 4rem;
            color: #9b59b6;
        }
        
        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            border: none;
            padding: 14px 35px;
            font-weight: 600;
            font-size: 1.1rem;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(155, 89, 182, 0.3);
        }
        
        .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            padding: 14px 35px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            color: white;
            transform: translateY(-2px);
        }
        
        /* Alerts */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 18px 22px;
        }
        
        /* Progress bar pour l'upload */
        .upload-progress {
            height: 8px;
            border-radius: 4px;
            background-color: #e9ecef;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .upload-progress-bar {
            height: 100%;
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        /* Footer */
        .footer {
            background-color: #2c3e50;
            color: white;
            margin-top: auto;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .card-body {
                padding: 25px 20px;
            }
            
            .card-header {
                padding: 20px;
            }
            
            .profile-photo, .profile-photo-placeholder {
                width: 150px;
                height: 150px;
            }
            
            .btn-primary, .btn-outline-secondary {
                padding: 12px 25px;
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="bi bi-shop me-2"></i>
                UCAO Marketplace
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">
                            <i class="bi bi-house me-1"></i> Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/dashboard/">
                            <i class="bi bi-speedometer2 me-1"></i> Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/dashboard/profile/edit.php">
                            <i class="bi bi-person me-1"></i> Mon profil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/public/browse.php">
                            <i class="bi bi-search me-1"></i> Rechercher
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?= htmlspecialchars($user['first_name'] ?? 'Mon compte') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/dashboard/"><i class="bi bi-file-earmark-text me-2"></i> Mes articles</a></li>
                            <li><a class="dropdown-item" href="/dashboard/articles/add.php"><i class="bi bi-plus-circle me-2"></i> Ajouter un article</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Déconnexion</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-5 profile-edit-container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="main-card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="bi bi-person-bounding-box" style="font-size: 2.5rem;"></i>
                            </div>
                            <div>
                                <h2 class="h3 mb-1">Modifier mon profil</h2>
                                <p class="mb-0 opacity-75">Mettez à jour vos informations personnelles</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Messages -->
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show mb-4">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle-fill me-2" style="font-size: 1.3rem;"></i>
                                    <div class="flex-grow-1">
                                        <h5 class="alert-heading mb-2">Profil mis à jour avec succès !</h5>
                                        <p class="mb-2">Vos informations ont été enregistrées.</p>
                                        <div class="mt-2">
                                            <a href="/dashboard/" class="btn btn-success btn-sm me-2">
                                                <i class="bi bi-speedometer2 me-1"></i> Retour au dashboard
                                            </a>
                                            <a href="/public/view-profile.php" class="btn btn-outline-success btn-sm">
                                                <i class="bi bi-eye me-1"></i> Voir mon profil public
                                            </a>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show mb-4">
                                <div class="d-flex align-items-start">
                                    <i class="bi bi-exclamation-triangle-fill me-2 mt-1" style="font-size: 1.3rem;"></i>
                                    <div class="flex-grow-1">
                                        <h5 class="alert-heading mb-2">Des erreurs sont survenues</h5>
                                        <ul class="mb-0 ps-3">
                                            <?php foreach ($errors as $error): ?>
                                                <li><?= htmlspecialchars($error) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data" novalidate id="profileForm">
                            <!-- Photo de profil -->
                            <div class="profile-photo-container mb-5">
                                <?php if (!empty($user['photo_url'])): ?>
                                    <img src="<?= htmlspecialchars($user['photo_url']) ?>" 
                                         alt="Photo de profil" 
                                         class="profile-photo"
                                         id="profilePhotoPreview">
                                    <div class="mt-3">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="remove_photo" name="remove_photo" value="1">
                                            <label class="form-check-label text-danger" for="remove_photo">
                                                <i class="bi bi-trash me-1"></i> Supprimer cette photo
                                            </label>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="profile-photo-placeholder">
                                        <i class="bi bi-person-circle"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-4">
                                    <label for="photo" class="btn btn-outline-primary">
                                        <i class="bi bi-camera me-2"></i>
                                        <?= empty($user['photo_url']) ? 'Ajouter une photo' : 'Changer la photo' ?>
                                    </label>
                                    <input type="file" class="d-none" id="photo" name="photo" 
                                           accept="image/*" onchange="previewProfilePhoto(this)">
                                    <div class="form-text mt-2">
                                        Taille recommandée : 500x500px. Formats : JPG, PNG, GIF (max 5MB)
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Informations personnelles -->
                            <h5 class="fw-bold mb-4 text-purple">
                                <i class="bi bi-person-lines-fill me-2"></i>
                                Informations personnelles
                            </h5>
                            
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">Prénom *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($formData['first_name']) ?>" 
                                           required maxlength="50">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Nom *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($formData['last_name']) ?>" 
                                           required maxlength="50">
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($formData['email']) ?>" 
                                           required maxlength="100">
                                    <div class="form-text">
                                        Cet email sera utilisé pour les communications
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($formData['phone']) ?>" 
                                           maxlength="20" placeholder="Ex: 0123456789">
                                    <div class="form-text">Optionnel - utilisé pour les contacts directs</div>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label for="filiere" class="form-label">Filière</label>
                                    <select class="form-select" id="filiere" name="filiere">
                                        <option value="">Sélectionnez votre filière</option>
                                        <?php foreach ($filieres as $filiere): ?>
                                            <option value="<?= htmlspecialchars($filiere) ?>" 
                                                <?= $formData['filiere'] === $filiere ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($filiere) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="bio" class="form-label">Bio / Présentation</label>
                                    <textarea class="form-control form-textarea" id="bio" name="bio" 
                                              rows="3" maxlength="500" 
                                              placeholder="Parlez un peu de vous..."><?= htmlspecialchars($formData['bio']) ?></textarea>
                                    <div class="form-text">
                                        <span id="bioCounter">0</span>/500 caractères
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Informations de compte -->
                            <h5 class="fw-bold mb-4 text-purple mt-5 pt-4 border-top">
                                <i class="bi bi-shield-lock me-2"></i>
                                Sécurité du compte
                            </h5>
                            
                            <div class="alert alert-info mb-4">
                                <div class="d-flex">
                                    <i class="bi bi-info-circle-fill me-2 mt-1"></i>
                                    <div>
                                        <h6 class="alert-heading mb-2">Informations de sécurité</h6>
                                        <p class="mb-0">
                                            Pour modifier votre mot de passe, veuillez utiliser la fonction 
                                            <a href="/auth/forgot-password.php" class="text-decoration-none fw-bold">"Mot de passe oublié"</a>.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body">
                                            <h6 class="fw-bold">
                                                <i class="bi bi-calendar-check me-2"></i>
                                                Compte créé
                                            </h6>
                                            <p class="mb-0">
                                                <?= !empty($user['created_at']) 
                                                    ? date('d/m/Y', strtotime($user['created_at'])) 
                                                    : 'Non disponible' ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body">
                                            <h6 class="fw-bold">
                                                <i class="bi bi-envelope-check me-2"></i>
                                                Statut du compte
                                            </h6>
                                            <p class="mb-0">
                                                <?= ($_SESSION['email_verified'] ?? 0) 
                                                    ? '<span class="text-success">Email vérifié</span>' 
                                                    : '<span class="text-warning">Email non vérifié</span>' ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Boutons -->
                            <div class="d-grid gap-3 mt-5 pt-4 border-top">
                                <button type="submit" class="btn btn-primary btn-lg py-3">
                                    <i class="bi bi-check-circle me-2"></i>
                                    Enregistrer les modifications
                                </button>
                                <a href="/dashboard/" class="btn btn-outline-secondary py-3">
                                    <i class="bi bi-x-circle me-2"></i>
                                    Annuler et retourner au dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Section de sécurité -->
                <div class="main-card mt-4">
                    <div class="card-body">
                        <h5 class="fw-bold mb-4">
                            <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                            Actions importantes
                        </h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card border-warning">
                                    <div class="card-body">
                                        <h6 class="fw-bold text-warning">
                                            <i class="bi bi-key me-2"></i>
                                            Changer le mot de passe
                                        </h6>
                                        <p class="small text-muted mb-2">
                                            Pour des raisons de sécurité, utilisez le lien "Mot de passe oublié".
                                        </p>
                                        <a href="/auth/forgot-password.php" class="btn btn-outline-warning btn-sm">
                                            Réinitialiser mon mot de passe
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="card border-danger">
                                    <div class="card-body">
                                        <h6 class="fw-bold text-danger">
                                            <i class="bi bi-trash me-2"></i>
                                            Supprimer le compte
                                        </h6>
                                        <p class="small text-muted mb-2">
                                            Cette action supprimera définitivement votre compte et toutes vos données.
                                        </p>
                                        <button type="button" class="btn btn-outline-danger btn-sm" 
                                                onclick="confirmAccountDeletion()">
                                            Demander la suppression
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer mt-5">
        <div class="container py-4">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5 class="fw-bold mb-3">UCAO Marketplace</h5>
                    <p class="small opacity-75">Plateforme d'échange entre étudiants de l'Université Catholique de l'Afrique de l'Ouest.</p>
                </div>
                <div class="col-md-2 mb-3">
                    <h6 class="fw-bold mb-3">Navigation</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="/" class="text-light text-decoration-none">Accueil</a></li>
                        <li class="mb-2"><a href="/dashboard/" class="text-light text-decoration-none">Tableau de bord</a></li>
                        <li><a href="/public/browse.php" class="text-light text-decoration-none">Rechercher</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-3">
                    <h6 class="fw-bold mb-3">Catégories</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="/public/browse.php?categorie=livres" class="text-light text-decoration-none">Livres</a></li>
                        <li class="mb-2"><a href="/public/browse.php?categorie=materiel" class="text-light text-decoration-none">Matériel</a></li>
                        <li><a href="/public/browse.php?categorie=autres" class="text-light text-decoration-none">Autres</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-3">
                    <h6 class="fw-bold mb-3">Aide</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="/public/faq.php" class="text-light text-decoration-none">FAQ</a></li>
                        <li class="mb-2"><a href="/public/contact.php" class="text-light text-decoration-none">Contact</a></li>
                        <li><a href="/public/terms.php" class="text-light text-decoration-none">Conditions</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">
            <div class="text-center">
                <p class="small mb-0 opacity-75">&copy; <?= date('Y') ?> UCAO Marketplace. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Prévisualisation de la photo de profil
    function previewProfilePhoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const preview = document.getElementById('profilePhotoPreview');
                if (preview) {
                    preview.src = e.target.result;
                } else {
                    // Créer une nouvelle image si elle n'existe pas
                    const container = document.querySelector('.profile-photo-container');
                    const placeholder = document.querySelector('.profile-photo-placeholder');
                    if (placeholder) {
                        placeholder.style.display = 'none';
                    }
                    
                    const img = document.createElement('img');
                    img.id = 'profilePhotoPreview';
                    img.className = 'profile-photo';
                    img.src = e.target.result;
                    img.alt = 'Photo de profil';
                    
                    container.insertBefore(img, container.firstChild);
                    
                    // Ajouter l'option de suppression
                    const removeOption = document.createElement('div');
                    removeOption.className = 'mt-3';
                    removeOption.innerHTML = `
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" 
                                   id="remove_photo" name="remove_photo" value="1">
                            <label class="form-check-label text-danger" for="remove_photo">
                                <i class="bi bi-trash me-1"></i> Supprimer cette photo
                            </label>
                        </div>
                    `;
                    
                    container.appendChild(removeOption);
                }
            }
            
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // Compteur de caractères pour la bio
    const bioTextarea = document.getElementById('bio');
    const bioCounter = document.getElementById('bioCounter');
    
    if (bioTextarea && bioCounter) {
        // Initialiser le compteur
        bioCounter.textContent = bioTextarea.value.length;
        
        // Mettre à jour en temps réel
        bioTextarea.addEventListener('input', function() {
            bioCounter.textContent = this.value.length;
            
            if (this.value.length > 500) {
                bioCounter.classList.add('text-danger');
                this.classList.add('is-invalid');
            } else {
                bioCounter.classList.remove('text-danger');
                this.classList.remove('is-invalid');
            }
        });
    }
    
    // Validation du formulaire
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        const firstName = document.getElementById('first_name').value.trim();
        const lastName = document.getElementById('last_name').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const bio = document.getElementById('bio')?.value.trim() || '';
        
        let errors = [];
        
        if (!firstName) {
            errors.push('Le prénom est obligatoire');
            document.getElementById('first_name').classList.add('is-invalid');
        } else {
            document.getElementById('first_name').classList.remove('is-invalid');
        }
        
        if (!lastName) {
            errors.push('Le nom est obligatoire');
            document.getElementById('last_name').classList.add('is-invalid');
        } else {
            document.getElementById('last_name').classList.remove('is-invalid');
        }
        
        if (!email) {
            errors.push('L\'email est obligatoire');
            document.getElementById('email').classList.add('is-invalid');
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errors.push('Format d\'email invalide');
            document.getElementById('email').classList.add('is-invalid');
        } else {
            document.getElementById('email').classList.remove('is-invalid');
        }
        
        if (phone && !/^[0-9]{9,20}$/.test(phone.replace(/[^0-9]/g, ''
                if (phone && !/^[0-9]{9,20}$/.test(phone.replace(/[^0-9]/g, ''))) {
            errors.push('Numéro de téléphone invalide');
            document.getElementById('phone').classList.add('is-invalid');
        } else {
            document.getElementById('phone').classList.remove('is-invalid');
        }
        
        if (bio.length > 500) {
            errors.push('La bio ne doit pas dépasser 500 caractères');
            document.getElementById('bio')?.classList.add('is-invalid');
        } else {
            document.getElementById('bio')?.classList.remove('is-invalid');
        }
        
        if (errors.length > 0) {
            e.preventDefault();
            
            // Afficher les erreurs
            let errorHtml = `
                <div class="alert alert-danger alert-dismissible fade show mb-4">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-exclamation-triangle-fill me-2 mt-1" style="font-size: 1.3rem;"></i>
                        <div class="flex-grow-1">
                            <h5 class="alert-heading mb-2">Veuillez corriger les erreurs suivantes</h5>
                            <ul class="mb-0 ps-3">
            `;
            
            errors.forEach(error => {
                errorHtml += `<li>${error}</li>`;
            });
            
            errorHtml += `
                            </ul>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            `;
            
            // Supprimer les anciennes alertes
            const existingAlerts = document.querySelectorAll('.alert-danger, .alert-success');
            existingAlerts.forEach(alert => alert.remove());
            
            // Insérer la nouvelle alerte
            const cardBody = document.querySelector('.card-body');
            if (cardBody) {
                cardBody.insertAdjacentHTML('afterbegin', errorHtml);
                
                // Faire défiler vers le haut pour voir les erreurs
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
        }
    });
    
    // Confirmation de suppression de compte
    function confirmAccountDeletion() {
        if (confirm('⚠️ ATTENTION : Cette action est irréversible !\n\nÊtes-vous sûr de vouloir demander la suppression de votre compte ?\n\nTous vos articles, messages et données seront définitivement supprimés.')) {
            // Rediriger vers la page de demande de suppression
            window.location.href = '/dashboard/account/delete.php';
        }
    }
    
    // Formattage du téléphone
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = this.value.replace(/[^0-9]/g, '');
            
            // Ajouter des espaces pour la lisibilité
            if (value.length > 2 && value.length <= 6) {
                value = value.replace(/(\d{2})(\d+)/, '$1 $2');
            } else if (value.length > 6 && value.length <= 8) {
                value = value.replace(/(\d{2})(\d{2})(\d+)/, '$1 $2 $3');
            } else if (value.length > 8) {
                value = value.replace(/(\d{2})(\d{2})(\d{2})(\d+)/, '$1 $2 $3 $4');
            }
            
            this.value = value;
        });
    }
    
    // Validation en temps réel
    const inputs = document.querySelectorAll('input[required], textarea[required], select[required]');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value.trim() === '') {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        
        input.addEventListener('input', function() {
            if (this.value.trim() !== '') {
                this.classList.remove('is-invalid');
            }
        });
    });
    
    // Gestion de la photo
    const removePhotoCheckbox = document.getElementById('remove_photo');
    const photoInput = document.getElementById('photo');
    
    if (removePhotoCheckbox && photoInput) {
        removePhotoCheckbox.addEventListener('change', function() {
            if (this.checked) {
                photoInput.disabled = true;
                photoInput.parentElement.classList.add('opacity-50');
                
                // Masquer la prévisualisation
                const preview = document.getElementById('profilePhotoPreview');
                if (preview) {
                    preview.style.opacity = '0.3';
                }
            } else {
                photoInput.disabled = false;
                photoInput.parentElement.classList.remove('opacity-50');
                
                // Restaurer la prévisualisation
                const preview = document.getElementById('profilePhotoPreview');
                if (preview) {
                    preview.style.opacity = '1';
                }
            }
        });
    }
    
    // Afficher le nom du fichier sélectionné
    if (photoInput) {
        photoInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                const fileName = this.files[0].name;
                const label = this.previousElementSibling;
                
                if (label && label.tagName === 'LABEL') {
                    const originalText = label.textContent;
                    label.innerHTML = `<i class="bi bi-check-circle me-2"></i>${fileName}`;
                    
                    // Restaurer le texte original après 3 secondes
                    setTimeout(() => {
                        label.innerHTML = `<i class="bi bi-camera me-2"></i>${originalText.includes('Changer') ? 'Changer la photo' : 'Ajouter une photo'}`;
                    }, 3000);
                }
                
                // Décocher la case "supprimer la photo" si cochée
                if (removePhotoCheckbox && removePhotoCheckbox.checked) {
                    removePhotoCheckbox.checked = false;
                    removePhotoCheckbox.dispatchEvent(new Event('change'));
                }
            }
        });
    }
    
    // Initialiser les tooltips Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Gestion de la soumission du formulaire (animation)
    const submitButton = document.querySelector('button[type="submit"]');
    const form = document.getElementById('profileForm');
    
    if (submitButton && form) {
        form.addEventListener('submit', function() {
            if (!this.classList.contains('submitting')) {
                this.classList.add('submitting');
                submitButton.disabled = true;
                submitButton.innerHTML = `
                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    Enregistrement en cours...
                `;
            }
        });
    }
    
    // Empêcher la double soumission
    let isSubmitting = false;
    form.addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            return;
        }
        
        isSubmitting = true;
        
        // Réactiver le bouton après 5 secondes au cas où
        setTimeout(() => {
            isSubmitting = false;
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = `
                    <i class="bi bi-check-circle me-2"></i>
                    Enregistrer les modifications
                `;
            }
        }, 5000);
    });
    
    // Notifications toast pour les actions
    function showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            // Créer le conteneur de toasts
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.style.position = 'fixed';
            container.style.top = '20px';
            container.style.right = '20px';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }
        
        const toastId = 'toast-' + Date.now();
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi ${type === 'success' ? 'bi-check-circle' : type === 'warning' ? 'bi-exclamation-triangle' : 'bi-info-circle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        document.getElementById('toastContainer').insertAdjacentHTML('beforeend', toastHtml);
        
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: 3000
        });
        
        toast.show();
        
        toastElement.addEventListener('hidden.bs.toast', function () {
            this.remove();
        });
    }
    
    // Gestion des messages de session
    window.addEventListener('DOMContentLoaded', function() {
        // Vérifier s'il y a des messages flash
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            // Faire défiler vers le haut pour voir le message de succès
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
            
            // Cacher automatiquement après 5 secondes
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(successAlert);
                bsAlert.close();
            }, 5000);
        }
    });
</script>

<!-- Script pour l'upload progressif (si nécessaire) -->
<script>
    // Gestion de l'upload avec progression (exemple)
    const photoUpload = document.getElementById('photo');
    
    if (photoUpload) {
        photoUpload.addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                // Vérifier la taille du fichier (max 5MB)
                const maxSize = 5 * 1024 * 1024; // 5MB en octets
                if (file.size > maxSize) {
                    alert('Le fichier est trop volumineux. Taille maximale : 5MB');
                    this.value = '';
                    return;
                }
                
                // Vérifier le type de fichier
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                if (!validTypes.includes(file.type)) {
                    alert('Type de fichier non supporté. Utilisez JPG, PNG ou GIF.');
                    this.value = '';
                    return;
                }
                
                // Afficher une barre de progression (exemple)
                const progressHtml = `
                    <div class="upload-progress mt-2">
                        <div class="upload-progress-bar" style="width: 0%"></div>
                    </div>
                    <div class="upload-status small text-muted mt-1"></div>
                `;
                
                const container = this.closest('.profile-photo-container');
                const existingProgress = container.querySelector('.upload-progress');
                if (!existingProgress) {
                    container.insertAdjacentHTML('beforeend', progressHtml);
                }
                
                // Simuler l'upload (dans un cas réel, utiliser FormData et XMLHttpRequest)
                simulateUploadProgress(file);
            }
        });
    }
    
    function simulateUploadProgress(file) {
        const progressBar = document.querySelector('.upload-progress-bar');
        const statusText = document.querySelector('.upload-status');
        
        let progress = 0;
        const interval = setInterval(() => {
            progress += 10;
            progressBar.style.width = progress + '%';
            
            if (progress <= 30) {
                statusText.textContent = 'Préparation...';
            } else if (progress <= 70) {
                statusText.textContent = 'Upload en cours...';
            } else if (progress < 100) {
                statusText.textContent = 'Traitement...';
            }
            
            if (progress >= 100) {
                clearInterval(interval);
                progressBar.style.width = '100%';
                statusText.textContent = 'Photo uploadée avec succès !';
                progressBar.style.background = 'linear-gradient(135deg, #27ae60 0%, #2ecc71 100%)';
                
                // Nettoyer après 3 secondes
                setTimeout(() => {
                    const progressContainer = document.querySelector('.upload-progress');
                    const statusContainer = document.querySelector('.upload-status');
                    if (progressContainer) progressContainer.remove();
                    if (statusContainer) statusContainer.remove();
                }, 3000);
            }
        }, 100);
    }
</script>
</body>
</html>