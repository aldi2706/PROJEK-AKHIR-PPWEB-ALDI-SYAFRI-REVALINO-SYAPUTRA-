<?php
session_start();
include 'koneksi.php';

// Validasi: Pastikan user sudah login dan rolenya adalah 'admin'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo "<script>alert('Akses ditolak! Silakan login sebagai Admin.'); window.location='login.php';</script>";
    exit;
}

$admin_name = $_SESSION['name'];

// Proses jika Admin mengklik tombol "Cairkan Dana (Release Funds)"
if (isset($_GET['action']) && $_GET['action'] == 'release_funds' && isset($_GET['trx_id'])) {
    $trx_id = mysqli_real_escape_string($koneksi, $_GET['trx_id']);

    // Ambil data transaksi berdasarkan ID
    $q_trx = mysqli_query($koneksi, "SELECT * FROM transactions WHERE id = '$trx_id'");
    $trx_data = mysqli_fetch_assoc($q_trx);

    if ($trx_data) {
        $project_id = $trx_data['project_id'];

        // 1. Ubah status transaksi menjadi 'released' (Dana diteruskan ke tim)
        mysqli_query($koneksi, "UPDATE transactions SET status = 'released' WHERE id = '$trx_id'");

        // 2. Ubah status proyek menjadi 'completed' (Proyek selesai)
        mysqli_query($koneksi, "UPDATE projects SET status = 'completed' WHERE id = '$project_id'");

        echo "<script>alert('Dana berhasil dicairkan ke tim, dan komisi platform (platform fee) berhasil diamankan!'); window.location='dashboard-admin.php';</script>";
    }
}

// Ambil semua data transaksi, gabungkan dengan data proyek, perusahaan (client), dan tim talent
$q_transactions = mysqli_query($koneksi, "
    SELECT transactions.*, projects.title as project_title, clients.name as client_name, teams.team_name 
    FROM transactions 
    JOIN projects ON transactions.project_id = projects.id 
    JOIN users as clients ON projects.client_id = clients.id 
    JOIN proposals ON proposals.project_id = projects.id AND proposals.status = 'accepted'
    JOIN teams ON proposals.team_id = teams.id
    ORDER BY transactions.id DESC
");

// Hitung total pendapatan platform fee yang sudah dicairkan / masuk
$q_revenue = mysqli_query($koneksi, "SELECT SUM(platform_fee) as total_revenue FROM transactions WHERE status = 'released'");
$revenue_data = mysqli_fetch_assoc($q_revenue);
$total_revenue = $revenue_data['total_revenue'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - CollabRiset</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Navbar Admin -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">CollabRiset | Panel Administrator (Pihak Ketiga)</a>
            <div class="d-flex">
                <span class="navbar-text me-3 text-white">Admin: <strong><?php echo htmlspecialchars($admin_name); ?></strong></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        
        <!-- Kartu Ringkasan Pendapatan Platform -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="p-4 bg-white rounded shadow-sm border d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1">Panel Kontrol Transaksi & Escrow</h4>
                        <p class="text-muted mb-0">Kelola dana penampung pihak ketiga dan pantau komisi platform dari setiap proyek riset.</p>
                    </div>
                    <div class="text-end bg-light p-3 border rounded">
                        <span class="text-muted small d-block">Total Pendapatan Komisi (Fee)</span>
                        <h4 class="text-success mb-0">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Daftar Transaksi Escrow -->
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daftar Transaksi Escrow (Rekening Bersama)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No</th>
                                        <th>Proyek</th>
                                        <th>Perusahaan (Client)</th>
                                        <th>Tim Pengembang (Talent)</th>
                                        <th>Total Dana (Gross)</th>
                                        <th>Komisi Platform (10%)</th>
                                        <th>Hak Tim (Net)</th>
                                        <th>Status Escrow</th>
                                        <th>Aksi Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($q_transactions) > 0): ?>
                                        <?php $no = 1; while ($t = mysqli_fetch_assoc($q_transactions)): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><strong><?php echo htmlspecialchars($t['project_title']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($t['client_name']); ?></td>
                                                <td><?php echo htmlspecialchars($t['team_name']); ?></td>
                                                <td>Rp <?php echo number_format($t['gross_amount'], 0, ',', '.'); ?></td>
                                                <td class="text-primary fw-bold">Rp <?php echo number_format($t['platform_fee'], 0, ',', '.'); ?></td>
                                                <td class="text-success fw-bold">Rp <?php echo number_format($t['net_amount'], 0, ',', '.'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo ($t['status'] == 'released') ? 'success' : 'warning'; ?>">
                                                        <?php echo strtoupper($t['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($t['status'] == 'holding'): ?>
                                                        <a href="dashboard-admin.php?action=release_funds&trx_id=<?php echo $t['id']; ?>" 
                                                           class="btn btn-sm btn-primary"
                                                           onclick="return confirm('Cairkan dana ke tim dan potong komisi platform secara resmi?')">
                                                           Cairkan Dana
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Dana Dicairkan</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">Belum ada transaksi escrow yang tercatat.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</body>
</html>