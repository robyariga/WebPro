
<?php
session_start();

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'perpustakaan';

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$database",
        $user,
        $password
    );

    $conn->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

} catch (PDOException $e) {
    http_response_code(500);
    exit("Koneksi ke database gagal: " . $e->getMessage());
}

// Token keamanan form
if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(16));
}

// Variabel untuk menampung pesan
$pesan = '';
$jenisPesan = '';
$error = [];

// Variabel form
$id = '';
$judul = '';
$penulis = '';
$terbit = '';
$stok = '';
$modeEdit = false;

// Proses tambah, edit, dan hapus buku
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['token'], $_POST['token'] ?? '')) {
        exit("Permintaan tidak valid.");
    }

    $aksi = $_POST['aksi'] ?? '';

    // Proses hapus buku
    if ($aksi === 'hapus') {
        $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

        if ($id === false || $id <= 0) {
            exit("ID buku tidak valid.");
        }

        $stmt = $conn->prepare("DELETE FROM buku WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        header("Location: index.php?status=hapus");
        exit();
    }

    // Proses tambah dan edit buku
    if ($aksi === 'tambah' || $aksi === 'edit') {

        $id = $_POST['id'] ?? '';
        $judul = trim($_POST['judul'] ?? '');
        $penulis = trim($_POST['penulis'] ?? '');
        $terbit = trim($_POST['terbit'] ?? '');
        $stok = trim($_POST['stok'] ?? '');

        $modeEdit = ($aksi === 'edit');

        // Validasi input
        if (strlen($judul) < 3) {
            $error[] = "Judul buku minimal 3 karakter.";
        }

        if (empty($penulis)) {
            $error[] = "Penulis harus diisi.";
        }

        if (!ctype_digit($terbit) ||
            (int)$terbit < 1900 ||
            (int)$terbit > (int)date('Y')) {
            $error[] = "Tahun terbit tidak valid.";
        }

        if (!ctype_digit($stok)) {
            $error[] = "Stok harus berupa angka minimal 0.";
        }

        if ($modeEdit && (
            !filter_var($id, FILTER_VALIDATE_INT) ||
            (int)$id <= 0
        )) {
            $error[] = "ID buku tidak valid.";
        }

        if (empty($error)) {

            if ($modeEdit) {

                // Mengubah data buku
                $stmt = $conn->prepare(
                    "UPDATE buku
                     SET judul = :judul,
                         penulis = :penulis,
                         tahun = :tahun,
                         stok = :stok
                     WHERE id = :id"
                );

                $stmt->bindParam(':judul', $judul);
                $stmt->bindParam(':penulis', $penulis);
                $stmt->bindParam(':tahun', $terbit);
                $stmt->bindParam(':stok', $stok);
                $stmt->bindParam(':id', $id);
                $stmt->execute();

                header("Location: index.php?status=edit");
                exit();

            } else {

                // Menambahkan data buku
                $stmt = $conn->prepare(
                    "INSERT INTO buku
                     (judul, penulis, tahun, stok, created_at)
                     VALUES
                     (:judul, :penulis, :tahun, :stok, NOW())"
                );

                $stmt->bindParam(':judul', $judul);
                $stmt->bindParam(':penulis', $penulis);
                $stmt->bindParam(':tahun', $terbit);
                $stmt->bindParam(':stok', $stok);
                $stmt->execute();

                header("Location: index.php?status=success");
                exit();
            }

        } else {
            $pesan = implode(" ", $error);
            $jenisPesan = 'danger';
        }
    }
}

// Mengambil data buku untuk diedit
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {

    $idEdit = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if ($idEdit !== false && $idEdit > 0) {

        $stmt = $conn->prepare("SELECT * FROM buku WHERE id = :id");
        $stmt->bindParam(':id', $idEdit);
        $stmt->execute();

        $bukuEdit = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bukuEdit) {
            $modeEdit = true;
            $id = $bukuEdit['id'];
            $judul = $bukuEdit['judul'];
            $penulis = $bukuEdit['penulis'];
            $terbit = $bukuEdit['tahun'];
            $stok = $bukuEdit['stok'];
        } else {
            $pesan = "Data buku tidak ditemukan.";
            $jenisPesan = 'danger';
        }

    } else {
        $pesan = "ID buku tidak valid.";
        $jenisPesan = 'danger';
    }
}

// Pesan setelah proses
$status = $_GET['status'] ?? '';

if ($status === 'success') {
    $pesan = "Data buku berhasil ditambahkan.";
    $jenisPesan = 'success';

} else if ($status === 'edit') {
    $pesan = "Data buku berhasil diubah.";
    $jenisPesan = 'success';

} else if ($status === 'hapus') {
    $pesan = "Data buku berhasil dihapus.";
    $jenisPesan = 'success';
}

// Fungsi untuk mengambil data buku dari database
$query = $conn->query("SELECT * FROM buku ORDER BY created_at DESC, id DESC");
$list_buku = $query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <title>Profile</title>
</head>

<body>

    <header class="bg-primary text-white p-3">
        <h1 class="h3 m-0">Website Praktik</h1>
    </header>

    <nav class="navbar navbar-dark bg-info">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Navbar</a>

            <form class="d-flex" role="search">
                <input class="form-control me-2"
                       type="search"
                       placeholder="Search"
                       aria-label="Search">

                <button class="btn btn-outline-light"
                        type="submit">Search</button>
            </form>
        </div>
    </nav>

    <div class="container-fluid my-4">
        <div class="row">

            <aside class="col-md-3 mb-3">
                <div class="bg-light border rounded p-3">
                    <h2>Menu</h2>

                    <ul class="list-group">
                        <li class="list-group-item">
                            <a href="dashboard.html"
                               class="text-decoration-none text-black">
                                Dashboard
                            </a>
                        </li>

                        <li class="list-group-item">
                            <a href="index.php"
                               class="text-decoration-none text-black">
                                Daftar Buku
                            </a>
                        </li>

                        <li class="list-group-item">
                            Form Peminjaman
                        </li>
                    </ul>
                </div>
            </aside>

            <main class="col-md-9">

                <section>
                    <h2>
                        <?= $modeEdit ? 'Form Edit Buku' : 'Form Tambah Buku' ?>
                    </h2>

                    <form action="index.php"
                          method="post"
                          id="form-buku"
                          novalidate>

                        <input type="hidden"
                               name="token"
                               value="<?= htmlspecialchars($_SESSION['token']) ?>">

                        <input type="hidden"
                               name="aksi"
                               value="<?= $modeEdit ? 'edit' : 'tambah' ?>">

                        <input type="hidden"
                               name="id"
                               value="<?= htmlspecialchars((string)$id) ?>">

                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label for="judul" class="form-label">
                                    Judul Buku
                                </label>

                                <input type="text"
                                       class="form-control"
                                       name="judul"
                                       id="judul"
                                       value="<?= htmlspecialchars($judul) ?>"
                                       required minlength="3">

                                <div class="invalid-feedback">
                                    Minimal 3 karakter
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="penulis" class="form-label">
                                    Penulis
                                </label>

                                <input type="text"
                                       class="form-control"
                                       name="penulis"
                                       id="penulis"
                                       value="<?= htmlspecialchars($penulis) ?>"
                                       required>

                                <div class="invalid-feedback">
                                    Isi Penulis
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="terbit" class="form-label">
                                    Tahun Terbit
                                </label>

                                <input type="number"
                                       class="form-control"
                                       name="terbit"
                                       min="1900"
                                       max="<?= date('Y') ?>"
                                       id="terbit"
                                       value="<?= htmlspecialchars((string)$terbit) ?>"
                                       required>

                                <div class="invalid-feedback">
                                    Isi Tahun Terbit yang valid
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="stok" class="form-label">
                                    Stok
                                </label>

                                <input type="number"
                                       class="form-control"
                                       name="stok"
                                       min="0"
                                       step="1"
                                       id="stok"
                                       value="<?= htmlspecialchars((string)$stok) ?>"
                                       required>

                                <div class="invalid-feedback">
                                    Stok harus diisi
                                </div>
                            </div>

                        </div>

                        <?php if ($modeEdit): ?>
                            <button type="submit" class="btn btn-success">
                                Simpan Perubahan
                            </button>

                            <a href="index.php" class="btn btn-secondary">
                                Batal
                            </a>
                        <?php else: ?>
                            <button type="submit" class="btn btn-primary">
                                Tambah Buku
                            </button>
                        <?php endif; ?>

                    </form>
                </section>

                <section class="border rounded p-3 mt-4">
                    <h2>Daftar Buku</h2>

                    <?php if ($pesan !== ''): ?>
                        <div class="alert alert-<?= $jenisPesan ?>">
                            <?= htmlspecialchars($pesan) ?>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Judul</th>
                                    <th>Penulis</th>
                                    <th>Tahun Terbit</th>
                                    <th>Stok</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (empty($list_buku)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            Tidak ada data buku
                                        </td>
                                    </tr>

                                <?php else: ?>
                                    <?php foreach ($list_buku as $index => $buku): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= htmlspecialchars($buku['judul']) ?></td>
                                            <td><?= htmlspecialchars($buku['Penulis']) ?></td>
                                            <td><?= htmlspecialchars((string)$buku['tahun']) ?></td>
                                            <td><?= htmlspecialchars((string)$buku['stok']) ?></td>

                                            <td>
                                                <a href="index.php?edit=<?= $buku['id'] ?>"
                                                   class="btn btn-sm btn-warning">
                                                    Edit
                                                </a>

                                                <form action="index.php"
                                                      method="post"
                                                      class="d-inline"
                                                      onsubmit="return confirm('Apakah Anda yakin ingin menghapus buku ini?')">

                                                    <input type="hidden"
                                                           name="aksi"
                                                           value="hapus">

                                                    <input type="hidden"
                                                           name="id"
                                                           value="<?= $buku['id'] ?>">

                                                    <input type="hidden"
                                                           name="token"
                                                           value="<?= htmlspecialchars($_SESSION['token']) ?>">

                                                    <button type="submit"
                                                            class="btn btn-sm btn-danger">
                                                        Hapus
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

            </main>
        </div>
    </div>

    <footer class="bg-info text-black text-center p-2 m-4">
        <p class="mb-0">
            &copy; 2026 Website Praktik. All rights reserved.
        </p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const formbuku = document.getElementById("form-buku");

        formbuku.addEventListener("submit", function(event) {

            if (!formbuku.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            formbuku.classList.add("was-validated");
        });
    </script>

</body>
</html>
