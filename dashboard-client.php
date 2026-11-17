
<?php
session_start();
include 'koneksi.php';

// Validasi: Pastikan user sudah login dan rolenya adalah 'client'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'client') {
    echo "<script>alert('Akses ditolak! Silakan login sebagai Perusahaan.'); window.location='login.php';</script>";
    exit;
}

$client_id = $_SESSION['user_id'];
$client_name = $_SESSION['name'];

// Proses jika form "Posting Proyek" disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_proyek'])) {
    $title       = mysqli_real_escape_string($koneksi, $_POST['title']);
    $description = mysqli_real_escape_string($koneksi, $_POST['description']);
    $budget      = mysqli_real_escape_string($koneksi, $_POST['budget']);
    $deadline    = mysqli_real_escape_string($koneksi, $_POST['deadline']);

    $query = "INSERT INTO projects (client_id, title, description, budget, deadline, status) 
              VALUES ('$client_id', '$title', '$description', '$budget', '$deadline', 'open')";
    
    if (mysqli_query($koneksi, $query)) {
        echo "<script>alert('Proyek riset/pembuatan web berhasil diposting!'); window.location='dashboard-client.php';</script>";
    } else {
        echo "<script>alert('Gagal memposting proyek.');</script>";
    }
}
// ==========================================
// PROSES JIKA CLIENT MENERIMA PROPOSAL
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'accept_proposal' && isset($_GET['proposal_id'])) {
    $proposal_id = mysqli_real_escape_string($koneksi, $_GET['proposal_id']);

    // 1. Ambil data proposal dan proyek terkait
    $q_prop = mysqli_query($koneksi, "SELECT proposals.*, projects.budget, projects.id as proj_id FROM proposals JOIN projects ON proposals.project_id = projects.id WHERE proposals.id = '$proposal_id'");
    $prop_data = mysqli_fetch_assoc($q_prop);

    if ($prop_data) {
        $proj_id = $prop_data['proj_id'];
        $gross_amount = $prop_data['bid_amount']; // Menggunakan tawaran harga dari tim

        // Hitung otomatis potongan komisi platform (misal 10%)
        $platform_fee = $gross_amount * 0.10; 
        $net_amount   = $gross_amount - $platform_fee;

        // 2. Ubah status proposal ini menjadi 'accepted' dan yang lainnya di proyek yang sama jadi 'rejected'
        mysqli_query($koneksi, "UPDATE proposals SET status = 'accepted' WHERE id = '$proposal_id'");
        mysqli_query($koneksi, "UPDATE proposals SET status = 'rejected' WHERE project_id = '$proj_id' AND id != '$proposal_id'");

        // 3. Ubah status proyek menjadi 'in_progress'
        mysqli_query($koneksi, "UPDATE projects SET status = 'in_progress' WHERE id = '$proj_id'");

        // 4. Masukkan data ke tabel 'transactions' (Simulasi Escrow / Rekber)
        $insert_trx = "INSERT INTO transactions (project_id, gross_amount, platform_fee, net_amount, status) 
                       VALUES ('$proj_id', '$gross_amount', '$platform_fee', '$net_amount', 'holding')";
        mysqli_query($koneksi, $insert_trx);

        echo "<script>alert('Proposal berhasil diterima! Dana telah masuk ke sistem simulasi Escrow (Rekber).'); window.location='dashboard-client.php';</script>";
    }
}

// Ambil data proyek yang pernah diposting oleh client ini dari database
$projects = mysqli_query($koneksi, "SELECT * FROM projects WHERE client_id = '$client_id' ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Perusahaan - CollabRiset</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Navbar Sederhana -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">CollabRiset | Panel Perusahaan</a>
            <div class="d-flex">
                <span class="navbar-text me-3 text-white">Halo, <strong><?php echo htmlspecialchars($client_name); ?></strong></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        
        <!-- Baris Sambutan -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="p-4 bg-white rounded shadow-sm border">
                    <h4>Selamat Datang di Dashboard Perusahaan</h4>
                    <p class="text-muted mb-0">Kelola ide riset atau proyek pembuatan website perusahaan Anda di sini, dan tunggu penawaran terbaik dari tim talent/developer.</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Kolom Kiri: Form Posting Proyek Baru -->
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Posting Proyek Baru</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Judul Proyek / Riset</label>
                                <input type="text" name="title" class="form-control" required placeholder="Contoh: Pembuatan Web E-Commerce">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Deskripsi & Spesifikasi Kebutuhan</label>
                                <textarea name="description" class="form-control" rows="4" required placeholder="Jelaskan detail teknologi atau ruang lingkup riset..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Total Anggaran (Rp)</label>
                                <input type="number" name="budget" class="form-control" required placeholder="Contoh: 10000000">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tenggat Waktu (Deadline)</label>
                                <input type="date" name="deadline" class="form-control" required>
                            </div>

                            <button type="submit" name="tambah_proyek" class="btn btn-success w-100">Posting Proyek</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Daftar Proyek yang Diposting -->
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daftar Proyek Anda</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No</th>
                                        <th>Judul Proyek</th>
                                        <th>Budget</th>
                                        <th>Deadline</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($projects) > 0): ?>
                                        <?php $no = 1; while ($row = mysqli_fetch_assoc($projects)): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                                <td>Rp <?php echo number_format($row['budget'], 0, ',', '.'); ?></td>
                                                <td><?php echo $row['deadline']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo ($row['status'] == 'open') ? 'info' : 'warning'; ?>">
                                                        <?php echo strtoupper($row['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">Belum ada proyek yang diposting.</td>
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
    <!-- Bagian Tabel Proposal Masuk dari Tim Talent -->
<div class="row mt-5">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Proposal Masuk dari Tim Talent</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Judul Proyek</th>
                                <th>Nama Tim</th>
                                <th>Tawaran Harga (Bid)</th>
                                <th>Pesan Proposal / Metodologi</th>
                                <th>Status Proposal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Query mengambil proposal yang masuk ke proyek-proyek milik client ini
                            $q_proposals = mysqli_query($koneksi, "
                                SELECT proposals.*, projects.title as project_title, teams.team_name 
                                FROM proposals 
                                JOIN projects ON proposals.project_id = projects.id 
                                JOIN teams ON proposals.team_id = teams.id 
                                WHERE projects.client_id = '$client_id' 
                                ORDER BY proposals.id DESC
                            ");
                            ?>
                            <?php if (mysqli_num_rows($q_proposals) > 0): ?>
                                <?php while ($p = mysqli_fetch_assoc($q_proposals)): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($p['project_title']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($p['team_name']); ?></td>
                                        <td>Rp <?php echo number_format($p['bid_amount'], 0, ',', '.'); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($p['proposal_text'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($p['status'] == 'accepted') ? 'success' : (($p['status'] == 'rejected') ? 'danger' : 'warning'); ?>">
                                                <?php echo strtoupper($p['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($p['status'] == 'pending'): ?>
                                                <a href="dashboard-client.php?action=accept_proposal&proposal_id=<?php echo $p['id']; ?>" 
                                                   class="btn btn-sm btn-success" 
                                                   onclick="return confirm('Yakin ingin menerima proposal ini dan mengunci dana di escrow?')">
                                                   Terima Proposal
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">Selesai / Ditinjau</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Belum ada proposal yang masuk dari tim talent.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>