<?php
require_once 'config.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: patients.php?error=1');
    exit;
}

// Fetch patient data
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();

if (!$patient) {
    header('Location: patients.php?error=1');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $cna = $_POST['cna'] ?? ''; // Added CNA field
    $address = $_POST['address'] ?? '';
    $working_type = $_POST['working_type'] ?? '';
    $age = (int)($_POST['age'] ?? 0);

    // Basic validation
    if ($full_name && $phone && $working_type && $age > 0) {
        $updateStmt = $pdo->prepare("UPDATE patients SET full_name = ?, phone = ?, cna = ?, address = ?, working_type = ?, age = ? WHERE id = ?");
        $success = $updateStmt->execute([$full_name, $phone, $cna, $address, $working_type, $age, $id]);
        if ($success) {
            header('Location: patients.php?success=updated');
            exit;
        } else {
            $error = "Failed to update patient.";
        }
    } else {
        $error = "Please fill all fields correctly.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Edit Patient - DentalCare Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <style>
        :root {
            --primary: #2cb5a0;
            --secondary: #f0f7fa;
            --accent: #ff7f50;
            --light: #f8f9fa;
            --dark: #343a40;
            --cna-color: #6f42c1;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e3f2fd 100%);
            min-height: 100vh;
            padding: 2rem 0;
            animation: fadeIn 0.5s ease-in-out both;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        
        .breadcrumb {
            background: rgba(255,255,255,0.9);
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .breadcrumb-item a:hover {
            color: #1f8e7d;
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: none;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(90deg, var(--primary), var(--accent));
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
            padding: 1.5rem;
        }
        
        .btn-primary {
            background: var(--primary);
            border: none;
            transition: all 0.2s ease;
            padding: 0.7rem 1.8rem;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: #249d8b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(44,181,160,0.3);
        }
        
        .btn-outline-secondary {
            border-color: var(--primary);
            color: var(--primary);
            padding: 0.7rem 1.8rem;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .btn-outline-secondary:hover {
            background: var(--primary);
            color: white;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select, .form-textarea {
            border-radius: 10px;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
            padding: 0.8rem 1rem;
        }
        
        .form-control:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(44,181,160,0.25);
        }
        
        .alert {
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: none;
        }
        
        .patient-info-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(44,181,160,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.2rem;
            margin-right: 1rem;
        }
        
        .form-section {
            background: rgba(255,255,255,0.7);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .form-section-title {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(44,181,160,0.2);
        }
        
        .form-section-title h5 {
            margin: 0;
            color: var(--primary);
            font-weight: 600;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .form-footer {
            display: flex;
            justify-content: space-between;
            padding: 1.5rem 0;
            margin-top: 1rem;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        
        .patient-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .patient-id-badge {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 1rem;
            display: inline-block;
        }
        
        .cna-icon {
            color: var(--cna-color);
            margin-right: 5px;
        }
        
        .cna-badge {
            background: rgba(111, 66, 193, 0.1);
            color: var(--cna-color);
            border-radius: 5px;
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
<div class="container" style="max-width: 800px;">
    <!-- Breadcrumb -->
    <nav class="mb-4" aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><i class="fas fa-home me-2"></i><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="patients.php">Patients</a></li>
            <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-edit me-2"></i>Edit Patient</li>
        </ol>
    </nav>

    <div class="card">
        <div class="card-header">
            <div class="d-flex align-items-center">
                <div class="patient-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="ms-3">
                    <h3 class="mb-0">Edit Patient</h3>
                    <p class="mb-0">Update patient information</p>
                </div>
            </div>
        </div>
        
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger mb-4" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <div class="patient-id-badge">
                <i class="fas fa-id-card me-2"></i>ID: <?= $patient['id'] ?>
            </div>

            <form action="edit_patient.php?id=<?= $patient['id'] ?>" method="POST" novalidate>
                <!-- Personal Information Section -->
                <div class="form-section">
                    <div class="form-section-title">
                        <div class="patient-info-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <h5>Personal Information</h5>
                    </div>
                    
                    <div class="form-grid">
                        <div>
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" name="full_name" id="full_name" class="form-control" 
                                   value="<?= htmlspecialchars($patient['full_name']) ?>" required />
                            <div class="form-text">Patient's full name</div>
                        </div>
                        
                        <div>
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" name="phone" id="phone" class="form-control" 
                                   value="<?= htmlspecialchars($patient['phone']) ?>" required />
                            <div class="form-text">Contact number</div>
                        </div>
                        
                        <div>
                            <label for="cna" class="form-label">Carte Nationale (CNA)</label>
                            <input type="text" name="cna" id="cna" class="form-control" 
                                   value="<?= htmlspecialchars($patient['cna'] ?? '') ?>" 
                                   placeholder="e.g. AB123456" />
                            <div class="form-text">National identity card number</div>
                        </div>
                        
                        <div>
                            <label for="age" class="form-label">Age</label>
                            <input type="number" name="age" id="age" class="form-control" 
                                   value="<?= (int)$patient['age'] ?>" min="1" max="120" required />
                            <div class="form-text">Patient's age</div>
                        </div>
                        
                        <div>
                            <label for="working_type" class="form-label">Working Type</label>
                            <select name="working_type" id="working_type" class="form-select" required>
                                <?php
                                $types = ['student' => 'Student', 'employed' => 'Employed', 
                                          'self-employed' => 'Self-Employed', 'unemployed' => 'Unemployed'];
                                foreach ($types as $value => $label) {
                                    $selected = $patient['working_type'] === $value ? 'selected' : '';
                                    echo "<option value=\"$value\" $selected>$label</option>";
                                }
                                ?>
                            </select>
                            <div class="form-text">Employment status</div>
                        </div>
                    </div>
                </div>
                
                <!-- Address Section -->
                <div class="form-section">
                    <div class="form-section-title">
                        <div class="patient-info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h5>Address Information</h5>
                    </div>
                    
                    <div>
                        <label for="address" class="form-label">Full Address</label>
                        <textarea name="address" id="address" class="form-control form-textarea" 
                                  rows="4" placeholder="Enter patient's full address"><?= htmlspecialchars($patient['address'] ?? '') ?></textarea>
                        <div class="form-text">Street, city, state, and ZIP code</div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-footer">
                    <a href="patients.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>