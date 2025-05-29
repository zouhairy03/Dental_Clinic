<?php
require_once 'config.php';

// Add columns if they don't exist
try {
    $pdo->query("ALTER TABLE patients 
        ADD COLUMN IF NOT EXISTS address VARCHAR(255) DEFAULT NULL AFTER phone,
        ADD COLUMN IF NOT EXISTS cna VARCHAR(20) DEFAULT NULL AFTER address");
} catch (PDOException $e) {
    error_log("Error adding columns: " . $e->getMessage());
}

// Handle search, sort, and filters
$search = $_GET['search'] ?? '';
$workTypeFilter = $_GET['work_type'] ?? '';
$sort = $_GET['sort'] ?? 'id';
$order = ($_GET['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$page = max((int)($_GET['page'] ?? 1), 1);
$limit = 5;
$offset = ($page - 1) * $limit;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(full_name LIKE ? OR phone LIKE ? OR address LIKE ? OR cna LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($workTypeFilter) && in_array($workTypeFilter, ['student', 'employed', 'self-employed', 'unemployed'])) {
    $conditions[] = "working_type = ?";
    $params[] = $workTypeFilter;
}

$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Count total rows
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM patients $whereClause");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch paginated results
$sql = "SELECT * FROM patients $whereClause ORDER BY $sort $order LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

// Get summary statistics
$summaryStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_patients,
        AVG(age) AS average_age,
        SUM(CASE WHEN working_type = 'student' THEN 1 ELSE 0 END) AS students,
        SUM(CASE WHEN working_type = 'employed' THEN 1 ELSE 0 END) AS employed,
        SUM(CASE WHEN working_type = 'self-employed' THEN 1 ELSE 0 END) AS self_employed,
        SUM(CASE WHEN working_type = 'unemployed' THEN 1 ELSE 0 END) AS unemployed
    FROM patients
");
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
$averageAge = round($summary['average_age'], 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patients - DentalCare Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2cb5a0;
            --secondary: #f0f7fa;
            --accent: #ff7f50;
            --student: #4e73df;
            --employed: #1cc88a;
            --self-employed: #36b9cc;
            --unemployed: #f6c23e;
            --child: #ff85c0;
            --teen: #69c0ff;
            --adult: #95de64;
            --senior: #ffd666;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(160deg, #f8f9fa 0%, #e3f2fd 100%);
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

        .table {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            background: white;
        }

        .table thead th {
            background: var(--secondary);
            border-bottom: 2px solid var(--primary);
            font-weight: 600;
            color: #2a2a2a;
        }

        .table-hover tbody tr {
            transition: all 0.2s ease;
        }

        .table-hover tbody tr:hover {
            transform: translateX(8px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .btn-success {
            background: var(--primary);
            border: none;
            padding: 0.6rem 1.5rem;
            transition: all 0.2s ease;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(44,181,160,0.3);
        }

        .btn-outline-secondary {
            border-color: var(--primary);
            color: var(--primary);
        }

        .badge {
            padding: 0.5em 0.8em;
            border-radius: 20px;
            font-weight: 500;
        }

        .badge-student {
            background-color: var(--student);
            color: white;
        }

        .badge-employed {
            background-color: var(--employed);
            color: white;
        }

        .badge-self-employed {
            background-color: var(--self-employed);
            color: white;
        }

        .badge-unemployed {
            background-color: var(--unemployed);
            color: white;
        }

        .age-group {
            font-size: 0.8em;
            padding: 0.25em 0.6em;
            border-radius: 10px;
            font-weight: 500;
        }

        .age-group-child {
            background: var(--child);
            color: white;
        }

        .age-group-teen {
            background: var(--teen);
            color: white;
        }

        .age-group-adult {
            background: var(--adult);
            color: white;
        }

        .age-group-senior {
            background: var(--senior);
            color: #8a6d3b;
        }

        .modal-content {
            border-radius: 20px;
            border: none;
        }

        .modal-header {
            background: var(--secondary);
            border-bottom: 2px solid var(--primary);
        }

        .address-truncate, .cna-truncate {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
        }

        .address-truncate:hover, .cna-truncate:hover {
            white-space: normal;
            overflow: visible;
            position: absolute;
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            max-width: 300px;
        }
        
        .summary-card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: none;
            height: 100%;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .summary-card .card-body {
            padding: 1.5rem;
        }
        
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }
        
        .bg-primary-light {
            background-color: rgba(44, 181, 160, 0.15);
            color: var(--primary);
        }
        
        .bg-purple-light {
            background-color: rgba(78, 115, 223, 0.15);
            color: var(--student);
        }
        
        .bg-green-light {
            background-color: rgba(28, 200, 138, 0.15);
            color: var(--employed);
        }
        
        .bg-blue-light {
            background-color: rgba(105, 192, 255, 0.15);
            color: var(--self-employed);
        }
        
        .card-counter {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0;
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .action-buttons .btn {
            padding: 0.4rem 0.8rem;
        }
        
        .patient-info-card {
            border: none;
            background: #f8f9fa;
        }
        
        .map-marker, .id-card {
            color: var(--primary);
            margin-right: 5px;
        }
        
        .cna-badge {
            background: rgba(44, 181, 160, 0.15);
            color: var(--primary);
            border-radius: 5px;
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
    </style>
</head>
<body class="p-4">
    <!-- Alerts -->
    <?php if (isset($_GET['success'])): ?>
    <?php $successType = $_GET['success']; ?>
    <div class="alert alert-success shadow-sm border-0 mb-4 animate__animated animate__fadeInDown">
        <div class="d-flex align-items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle fa-2x"></i>
            </div>
            <div class="flex-grow-1 ms-3">
                <h5 class="alert-heading mb-1">
                    <?php switch($successType): 
                        case 'added': ?>
                            Patient Added Successfully
                        <?php break; ?>
                        <?php case 'updated': ?>
                            Patient Updated Successfully
                        <?php break; ?>
                        <?php case 'deleted': ?>
                            Patient Deleted Successfully
                        <?php break; ?>
                        <?php default: ?>
                            Operation Completed Successfully
                    <?php endswitch; ?>
                </h5>
                <p class="mb-0">
                    <?php switch($successType): 
                        case 'added': ?>
                            The patient has been added to the system.
                        <?php break; ?>
                        <?php case 'updated': ?>
                            The patient's information has been updated.
                        <?php break; ?>
                        <?php case 'deleted': ?>
                            The patient has been removed from the system.
                        <?php break; ?>
                        <?php default: ?>
                            The operation was completed successfully.
                    <?php endswitch; ?>
                </p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php elseif (isset($_GET['error'])): ?>
    <?php $errorType = $_GET['error']; ?>
    <div class="alert alert-danger shadow-sm border-0 mb-4 animate__animated animate__shakeX">
        <div class="d-flex align-items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle fa-2x"></i>
            </div>
            <div class="flex-grow-1 ms-3">
                <h5 class="alert-heading mb-1">
                    <?php switch($errorType): 
                        case 'add': ?>
                            Error Adding Patient
                        <?php break; ?>
                        <?php case 'update': ?>
                            Error Updating Patient
                        <?php break; ?>
                        <?php case 'delete': ?>
                            Error Deleting Patient
                        <?php break; ?>
                        <?php case 'validation': ?>
                            Validation Error
                        <?php break; ?>
                        <?php case 'db': ?>
                            Database Error
                        <?php break; ?>
                        <?php default: ?>
                            Operation Failed
                    <?php endswitch; ?>
                </h5>
                <p class="mb-0">
                    <?php switch($errorType): 
                        case 'add': ?>
                            We encountered an issue while adding the patient. Please try again.
                        <?php break; ?>
                        <?php case 'update': ?>
                            We couldn't update the patient information. Please verify your changes.
                        <?php break; ?>
                        <?php case 'delete': ?>
                            We couldn't delete the patient. Please try again.
                        <?php break; ?>
                        <?php case 'validation': ?>
                            Please fill all required fields correctly.
                        <?php break; ?>
                        <?php case 'db': ?>
                            A database error occurred. Technical details: <?= $_GET['message'] ?? 'No details' ?>
                        <?php break; ?>
                        <?php default: ?>
                            An unexpected error occurred. Please try again later.
                    <?php endswitch; ?>
                </p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>

<!-- Include in your head section -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><i class="fas fa-home me-2"></i><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active"><i class="fas fa-user-injured me-2"></i>Patients</li>
            </ol>
        </nav>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card summary-card border-left-primary shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Patients</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $summary['total_patients'] ?></div>
                            </div>
                            <div class="col-auto">
                                <div class="card-icon bg-primary-light">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card summary-card border-left-success shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Average Age</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $averageAge ?></div>
                            </div>
                            <div class="col-auto">
                                <div class="card-icon bg-green-light">
                                    <i class="fas fa-chart-line fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card summary-card border-left-info shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Students</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $summary['students'] ?></div>
                            </div>
                            <div class="col-auto">
                                <div class="card-icon bg-purple-light">
                                    <i class="fas fa-user-graduate fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card summary-card border-left-warning shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Employed</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $summary['employed'] + $summary['self_employed'] ?></div>
                            </div>
                            <div class="col-auto">
                                <div class="card-icon bg-blue-light">
                                    <i class="fas fa-briefcase fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header and controls -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0 text-primary"><i class="fas fa-users me-2"></i>Patient Management</h3>
            <div>
                <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addPatientModal">
                    <i class="fas fa-user-plus me-2"></i>Add New
                </button>
                <a href="export_patients.php" class="btn btn-outline-secondary">
                    <i class="fas fa-file-excel me-2"></i>Export
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <!-- Work Type Filter -->
            <div class="btn-group">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-filter me-2"></i>Filter by Work Type
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item <?= $workTypeFilter === '' ? 'active' : '' ?>" href="?work_type=">All Types</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item <?= $workTypeFilter === 'student' ? 'active' : '' ?>" href="?work_type=student">Students</a></li>
                    <li><a class="dropdown-item <?= $workTypeFilter === 'employed' ? 'active' : '' ?>" href="?work_type=employed">Employed</a></li>
                    <li><a class="dropdown-item <?= $workTypeFilter === 'self-employed' ? 'active' : '' ?>" href="?work_type=self-employed">Self-Employed</a></li>
                    <li><a class="dropdown-item <?= $workTypeFilter === 'unemployed' ? 'active' : '' ?>" href="?work_type=unemployed">Unemployed</a></li>
                </ul>
            </div>
            
            <!-- Search Bar -->
            <form method="get" class="d-flex">
                <input type="hidden" name="work_type" value="<?= $workTypeFilter ?>">
                <input type="hidden" name="sort" value="<?= $sort ?>">
                <input type="hidden" name="order" value="<?= $order ?>">
                
                <div class="input-group">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           class="form-control border-0 py-2" placeholder="Search patients...">
                    <button class="btn btn-outline-primary border-0" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <?php
                        $columns = ['id' => 'ID', 'full_name' => 'Name', 'phone' => 'Phone', 
                                  'cna' => 'CNA', 'address' => 'Address', 'working_type' => 'Working Type', 
                                  'age' => 'Age', 'created_at' => 'Created'];
                        foreach ($columns as $col => $label) {
                            $newOrder = ($sort === $col && $order === 'asc') ? 'desc' : 'asc';
                            echo "<th><a href=\"?search=$search&work_type=$workTypeFilter&sort=$col&order=$newOrder\" class=\"text-decoration-none text-dark\">$label <i class=\"fas fa-sort text-muted\"></i></a></th>";
                        }
                        ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): 
                        // Determine age group
                        $ageGroup = '';
                        $ageClass = '';
                        if ($patient['age'] < 13) {
                            $ageGroup = 'Child';
                            $ageClass = 'age-group-child';
                        } elseif ($patient['age'] >= 13 && $patient['age'] <= 19) {
                            $ageGroup = 'Teen';
                            $ageClass = 'age-group-teen';
                        } elseif ($patient['age'] >= 20 && $patient['age'] <= 59) {
                            $ageGroup = 'Adult';
                            $ageClass = 'age-group-adult';
                        } else {
                            $ageGroup = 'Senior';
                            $ageClass = 'age-group-senior';
                        }
                    ?>
                        <tr>
                            <td>#<?= $patient['id'] ?></td>
                            <td><?= htmlspecialchars($patient['full_name']) ?></td>
                            <td><?= htmlspecialchars($patient['phone']) ?></td>
                            <td>
                                <?php if (!empty($patient['cna'])): ?>
                                    <span class="cna-truncate" title="<?= htmlspecialchars($patient['cna']) ?>">
                                        <i class="fas fa-id-card id-card"></i>
                                        <?= htmlspecialchars($patient['cna']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="cna-badge">Not provided</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($patient['address'])): ?>
                                    <span class="address-truncate" title="<?= htmlspecialchars($patient['address']) ?>">
                                        <i class="fas fa-map-marker-alt map-marker"></i>
                                        <?= htmlspecialchars($patient['address']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">No address</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= str_replace(' ', '-', $patient['working_type']) ?>">
                                    <?= ucfirst($patient['working_type']) ?>
                                </span>
                            </td>
                            <td>
                                <?= $patient['age'] ?>
                                <span class="age-group ms-2 <?= $ageClass ?>"><?= $ageGroup ?></span>
                            </td>
                            <td><small class="text-muted"><?= $patient['created_at'] ?></small></td>
                            <td class="action-buttons">
                                <button class="btn btn-sm btn-outline-primary me-2 view-patient" 
                                        data-id="<?= $patient['id'] ?>" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#viewPatientModal"
                                        title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="edit_patient.php?id=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-secondary me-2" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete_patient.php?id=<?= $patient['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete" >
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" 
                           href="?search=<?= $search ?>&work_type=<?= $workTypeFilter ?>&sort=<?= $sort ?>&order=<?= $order ?>&page=<?= $i ?>">
                           <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>

    <!-- Add Patient Modal -->
    <div class="modal fade" id="addPatientModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form action="add_patient.php" method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>New Patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Carte Nationale (CNA)</label>
                            <input type="text" name="cna" class="form-control" placeholder="e.g. AB123456">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" class="form-control" required min="1">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Enter full address"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Working Type</label>
                            <select name="working_type" class="form-select" required>
                                <option value="student">Student</option>
                                <option value="employed">Employed</option>
                                <option value="self-employed">Self-Employed</option>
                                <option value="unemployed">Unemployed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button class="btn btn-primary px-4" type="submit"><i class="fas fa-save me-2"></i>Save</button>
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Patient Modal -->
    <div class="modal fade" id="viewPatientModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user me-2"></i>Patient Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Patient details will be loaded via AJAX -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3">Loading patient information...</p>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View Patient Modal Handler
        document.querySelectorAll('.view-patient').forEach(button => {
            button.addEventListener('click', function() {
                const patientId = this.getAttribute('data-id');
                const modal = document.getElementById('viewPatientModal');
                
                // Show loading state
                modal.querySelector('.modal-body').innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3">Loading patient information...</p>
                    </div>
                `;
                
                // Fetch patient data
                fetch(`get_patient.php?id=${patientId}`)
                    .then(response => response.json())
                    .then(data => {
                        // Determine age group
                        let ageGroup = '';
                        let ageClass = '';
                        if (data.age < 13) {
                            ageGroup = 'Child';
                            ageClass = 'age-group-child';
                        } else if (data.age >= 13 && data.age <= 19) {
                            ageGroup = 'Teen';
                            ageClass = 'age-group-teen';
                        } else if (data.age >= 20 && data.age <= 59) {
                            ageGroup = 'Adult';
                            ageClass = 'age-group-adult';
                        } else {
                            ageGroup = 'Senior';
                            ageClass = 'age-group-senior';
                        }
                        
                        // Format created at date
                        const createdAt = new Date(data.created_at);
                        const formattedDate = createdAt.toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                        
                        // Badge color based on working type
                        let badgeClass = 'badge-';
                        switch(data.working_type) {
                            case 'student': badgeClass += 'student'; break;
                            case 'employed': badgeClass += 'employed'; break;
                            case 'self-employed': badgeClass += 'self-employed'; break;
                            case 'unemployed': badgeClass += 'unemployed'; break;
                        }
                        
                        // Build appointments table if they exist
                        let appointmentsHtml = '';
                        if (data.appointments && data.appointments.length > 0) {
                            appointmentsHtml = `
                                <div class="mt-4">
                                    <h5><i class="fas fa-calendar-check me-2"></i>Appointments</h5>
                                    <div class="table-responsive mt-3">
                                        <table class="table appointment-table">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Time</th>
                                                    <th>Treatment</th>
                                                    <th>Status</th>
                                                    <th>Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${data.appointments.map(app => {
                                                    let statusClass = '';
                                                    switch(app.status) {
                                                        case 'scheduled': statusClass = 'badge-scheduled'; break;
                                                        case 'completed': statusClass = 'badge-completed'; break;
                                                        case 'cancelled': statusClass = 'badge-cancelled'; break;
                                                    }
                                                    
                                                    // Format appointment date
                                                    const appDate = new Date(app.appointment_date);
                                                    const formattedAppDate = appDate.toLocaleDateString('en-US', {
                                                        year: 'numeric',
                                                        month: 'short',
                                                        day: 'numeric'
                                                    });
                                                    
                                                    return `
                                                        <tr>
                                                            <td>${formattedAppDate}</td>
                                                            <td>${app.appointment_time}</td>
                                                            <td>${app.treatment_type}</td>
                                                            <td><span class="badge ${statusClass}">${app.status}</span></td>
                                                            <td>${app.notes || '—'}</td>
                                                        </tr>
                                                    `;
                                                }).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            `;
                        } else {
                            appointmentsHtml = `
                                <div class="alert alert-info mt-4">
                                    <i class="fas fa-info-circle me-2"></i>No appointments found for this patient.
                                </div>
                            `;
                        }
                        
                        // Update modal content
                        modal.querySelector('.modal-body').innerHTML = `
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="text-center mb-4">
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 120px; height: 120px;">
                                            <i class="fas fa-user fa-3x text-muted"></i>
                                        </div>
                                        <h4 class="mt-3">${data.full_name}</h4>
                                        <span class="badge ${badgeClass}">${data.working_type}</span>
                                    </div>
                                    
                                    <div class="card patient-info-card">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted text-uppercase">Patient Information</h6>
                                            <ul class="list-group list-group-flush">
                                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                    <span><i class="fas fa-id-card me-2 text-muted"></i> Patient ID</span>
                                                    <span>#${data.id}</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                    <span><i class="fas fa-phone me-2 text-muted"></i> Phone</span>
                                                    <span>${data.phone}</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                    <span><i class="fas fa-id-card me-2 text-muted"></i> Carte Nationale</span>
                                                    <span>${data.cna ? data.cna : 'Not provided'}</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                    <span><i class="fas fa-map-marker-alt me-2 text-muted"></i> Address</span>
                                                    <span>${data.address ? data.address : '—'}</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                    <span><i class="fas fa-birthday-cake me-2 text-muted"></i> Age</span>
                                                    <span>
                                                        ${data.age} 
                                                        <span class="age-group ms-2 ${ageClass}">${ageGroup}</span>
                                                    </span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                                    <span><i class="fas fa-calendar-plus me-2 text-muted"></i> Registered</span>
                                                    <span>${formattedDate}</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-8">
                                    ${appointmentsHtml}
                                </div>
                            </div>
                        `;
                    })
                    .catch(error => {
                        modal.querySelector('.modal-body').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Failed to load patient data. Please try again.
                            </div>
                        `;
                        console.error('Error fetching patient data:', error);
                    });
            });
        });
    </script>
</body>
</html>