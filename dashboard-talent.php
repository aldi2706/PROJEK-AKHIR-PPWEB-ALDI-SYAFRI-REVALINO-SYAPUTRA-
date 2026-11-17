<?php
session_start();
include 'koneksi.php';

// Validasi: Pastikan user sudah login dan rolenya adalah 'talent'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'talent') {
    echo "<script>alert('Akses ditolak! Silakan login sebagai Tim Riset/Developer.'); window.location='login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'];

// Cek apakah talent ini sudah memiliki tim di tabel 'teams'
$query_team = mysqli_query($koneksi, "SELECT * FROM teams WHERE leader_id = '$user_id'");
$my_team = mysqli_fetch_assoc($query_team);

// Proses jika membuat tim baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['buat_tim'])) {
    $team_name = mysqli_real_escape_string($koneksi, $_POST['team_name']);
    $description = mysqli_real_escape_string($koneksi, $_POST['description']);

    $insert_team = "INSERT INTO teams (leader_id, team_name, description) VALUES ('$user_id', '$team_name', '$description')";
    if (mysqli_query($koneksi, $insert_team)) {
        echo "<script>alert('Tim berhasil dibentuk!'); window.location='dashboard-talent.php';</script>";
    } else {
        echo "<script>alert('Gagal membuat tim.');</script>";
    }
}

// Proses jika mengirim proposal (bidding) ke proyek
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['kirim_proposal'])) {
    if (!$my_team) {
        echo "<script>alert('Anda harus membuat tim terlebih dahulu sebelum mengajukan proposal!'); window.location='dashboard-talent.php';</script>";
        exit;
    }

    $project_id    = mysqli_real_escape_string($koneksi, $_POST['project_id']);
    $team_id       = $my_team['id'];
    $bid_amount    = mysqli_real_escape_string($koneksi, $_POST['bid_amount']);
    $proposal_text = mysqli_real_escape_string($koneksi, $_POST['proposal_text']);

    $query_proposal = "INSERT INTO proposals (project_id, team_id, bid_amount, proposal_text, status) 
                       VALUES ('$project_id', '$team_id', '$bid_amount', '$proposal_text', 'pending')";
    
    if (mysqli_query($koneksi, $query_proposal)) {
        echo "<script>alert('Proposal berhasil dikirim ke perusahaan!'); window.location='dashboard-talent.php';</script>";
    } else {
        echo "<script>alert('Gagal mengirim proposal.');</script>";
    }
}

// Ambil daftar proyek yang statusnya masih 'open'
$open_projects = mysqli_query($koneksi, "SELECT projects.*, users.name as client_name FROM projects JOIN users ON projects.client_id = users.id WHERE projects.status = 'open' ORDER BY projects.id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Talent - CollabRiset</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">CollabRiset | Panel Talent & Tim Riset</a>
            <div class="d-flex">
                <span class="navbar-text me-3 text-white">Halo, <strong><?php echo htmlspecialchars($user_name); ?></strong></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        
        <!-- Informasi Status Tim -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="p-4 bg-white rounded shadow-sm border">
                    <h4>Status Tim Anda</h4>
                    <?php if ($my_team): ?>
                        <div class="alert alert-success mb-0">
                            Anda adalah Ketua Tim dari: <strong><?php echo htmlspecialchars($my_team['team_name']); ?></strong> 
                            <p class="mb-0 mt-1 text-muted small"><?php echo htmlspecialchars($my_team['description']); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-3">
                            Anda belum memiliki tim. Silakan buat tim terlebih dahulu untuk bisa mengajukan proposal ke proyek perusahaan.
                        </div>
                        <!-- Form Buat Tim -->
                        <form action="" method="POST" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" name="team_name" class="form-control" required placeholder="Nama Tim (Contoh: Team Alpha)">
                            </div>
                            <div class="col-md-6">
                                <input type="text" name="description" class="form-control" required placeholder="Deskripsi keahlian tim (Contoh: Spesialis Laravel & UI/UX)">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" name="buat_tim" class="btn btn-primary w-100">Buat Tim</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Daftar Proyek Terbuka untuk Dilamar (Bidding) -->
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daftar Proyek Riset & Pembuatan Web Tersedia</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Perusahaan</th>
                                        <th>Judul Proyek</th>
                                        <th>Deskripsi</th>
                                        <th>Budget</th>
                                        <th>Deadline</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($open_projects) > 0): ?>
                                        <?php while ($row = mysqli_fetch_assoc($open_projects)): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['client_name']); ?></td>
                                                <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                                <td><?php echo substr(htmlspecialchars($row['description']), 0, 80); ?>...</td>
                                                <td>Rp <?php echo number_format($row['budget'], 0, ',', '.'); ?></td>
                                                <td><?php echo $row['deadline']; ?></td>
                                                <td>
                                                    <!-- Tombol Ajukan Proposal -->
                                                    <?php if ($my_team): ?>
                                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalBidding<?php echo $row['id']; ?>">Ajukan Proposal</button>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Buat tim dulu</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>

                                            <!-- Modal Form Bidding / Kirim Proposal -->
                                            <div class="modal fade" id="modalBidding<?php echo $row['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form action="" method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Kirim Proposal ke: <?php echo htmlspecialchars($row['title']); ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="project_id" value="<?php echo $row['id']; ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Tawaran Harga/Anggaran (Rp)</label>
                                                                    <input type="number" name="bid_amount" class="form-control" required value="<?php echo $row['budget']; ?>">
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label">Pesan Proposal / Metodologi Kerja</label>
                                                                    <textarea name="proposal_text" class="form-control" rows="4" required placeholder="Jelaskan mengapa tim Anda layak mengerjakan proyek ini..."></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                <button type="submit" name="kirim_proposal" class="btn btn-primary">Kirim Penawaran</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Belum ada proyek yang tersedia saat ini.</td>
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

    <!-- Script Bootstrap JS (untuk Modal) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>