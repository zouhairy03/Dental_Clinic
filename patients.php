<?php
require_once 'config.php';

// Handle search and sort
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'id';
$order = ($_GET['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$page = max((int)($_GET['page'] ?? 1), 1);
$limit = 5;
$offset = ($page - 1) * $limit;

// Count total rows
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE full_name LIKE ?");
$countStmt->execute(["%$search%"]);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch paginated results
$stmt = $pdo->prepare("SELECT * FROM patients WHERE full_name LIKE ? ORDER BY $sort $order LIMIT $limit OFFSET $offset");
$stmt->execute(["%$search%"]);
$patients = $stmt->fetchAll();
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
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: url('data:image/svg+xml,<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="%232cb5a033" d="M44.6,-58.1C56.3,-49.6,62.6,-33.3,66.1,-16.8C69.6,-0.3,70.4,16.5,63.9,29.1C57.4,41.7,43.7,50.2,29.9,56.9C16.1,63.6,2.2,68.5,-12.6,67.7C-27.4,66.8,-42.9,60.2,-55.4,50.3C-67.9,40.4,-77.3,27.2,-79.9,12.6C-82.5,-2.1,-78.3,-18.2,-69.3,-31.1C-60.3,-44,-46.5,-53.7,-32.3,-61.3C-18.1,-68.9,-3.5,-74.4,12.1,-71.3C27.7,-68.2,55.4,-56.5,62.7,-42.5C70,-28.5,57,-12.3,53.9,2.1C50.8,16.5,57.6,33,55.9,47.8C54.2,62.6,44,75.7,31.8,81.8C19.6,87.9,5.3,87.1,-8.2,84.1C-21.7,81.2,-35.3,76.1,-45.6,67.3C-55.9,58.4,-62.8,45.8,-68.9,33.3C-75,20.8,-80.3,8.4,-79.8,-3.7C-79.3,-15.8,-73,-31.6,-63.3,-44.5C-53.6,-57.4,-40.5,-67.4,-26.6,-74.3C-12.7,-81.1,2,-84.8,16.4,-83.3C30.8,-81.8,45.1,-75,56.8,-65.3C68.5,-55.5,77.7,-42.7,81.2,-28.6C84.7,-14.5,82.5,0.9,76.5,13.4C70.5,25.8,60.7,35.3,49.9,44.3C39.1,53.3,27.3,61.8,14.1,64.3C0.9,66.8,-13.6,63.4,-25.4,57.5C-37.2,51.6,-46.3,43.3,-54.3,34.1C-62.3,24.9,-69.2,14.8,-71.7,3.3C-74.3,-8.3,-72.5,-21.3,-66.3,-32.2C-60.1,-43.1,-49.5,-51.9,-37.8,-60.3C-26.1,-68.7,-13,-76.6,1.1,-78.6C15.2,-80.6,30.5,-76.7,44.6,-58.1Z"/></svg>'),
                        linear-gradient(160deg, #f8f9fa 0%, #e3f2fd 100%);
            background-size: cover;
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

        .btn-outline-secondary:hover {
            background: var(--primary);
            color: white;
        }

        .badge {
            padding: 0.5em 0.8em;
            border-radius: 20px;
            font-weight: 500;
        }

        .modal-content {
            border-radius: 20px;
            border: none;
        }

        .modal-header {
            background: var(--secondary);
            border-bottom: 2px solid var(--primary);
        }

        .page-link {
            color: var(--primary);
            transition: all 0.2s ease;
        }

        .page-link:hover {
            color: #1f8e7d;
            background: var(--secondary);
        }

        .alert {
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: none;
        }
    </style>
</head>
<body class="p-4">
    <!-- Alerts -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success shadow-sm border-0 animate__animated animate__fadeInDown mb-4">
            <i class="fas fa-check-circle me-2"></i>Patient added successfully.
        </div>
    <?php elseif (isset($_GET['error'])): ?>
        <div class="alert alert-danger shadow-sm border-0 animate__animated animate__fadeInDown mb-4">
            <i class="fas fa-exclamation-circle me-2"></i>Error adding patient. Please try again.
        </div>
    <?php endif; ?>

    <div class="container">
        <!-- Breadcrumb -->
        <nav class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><i class="fas fa-home me-2"></i><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active"><i class="fas fa-user-injured me-2"></i>Patients</li>
            </ol>
        </nav>

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

        <!-- Search Bar -->
        <form method="get" class="mb-4">
            <div class="input-group">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       class="form-control border-0 py-2" placeholder="Search patients...">
                <button class="btn btn-outline-primary border-0" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>

        <!-- Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <?php
                        $columns = ['id' => 'ID', 'full_name' => 'Name', 'phone' => 'Phone', 
                                  'working_type' => 'Working Type', 'age' => 'Age', 'created_at' => 'Created'];
                        foreach ($columns as $col => $label) {
                            $newOrder = ($sort === $col && $order === 'asc') ? 'desc' : 'asc';
                            echo "<th><a href=\"?search=$search&sort=$col&order=$newOrder\" class=\"text-decoration-none text-dark\">$label <i class=\"fas fa-sort text-muted\"></i></a></th>";
                        }
                        ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <tr class="animate__animated animate__fadeIn">
                            <td>#<?= $patient['id'] ?></td>
                            <td><?= htmlspecialchars($patient['full_name']) ?></td>
                            <td><?= htmlspecialchars($patient['phone']) ?></td>
                            <td><span class="badge bg-secondary"><?= ucfirst($patient['working_type']) ?></span></td>
                            <td><?= $patient['age'] ?></td>
                            <td><small class="text-muted"><?= $patient['created_at'] ?></small></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary me-2" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
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
                           href="?search=<?= $search ?>&sort=<?= $sort ?>&order=<?= $order ?>&page=<?= $i ?>">
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
                            <label class="form-label">Working Type</label>
                            <select name="working_type" class="form-select" required>
                                <option value="student">Student</option>
                                <option value="employed">Employed</option>
                                <option value="self-employed">Self-Employed</option>
                                <option value="unemployed">Unemployed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" class="form-control" required min="1">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>