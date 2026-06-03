<?php
// auth.php - Helper functions untuk hak akses

/**
 * Cek apakah user adalah Admin
 */
function isAdmin() {
    return isset($_SESSION['level']) && strtolower($_SESSION['level']) === 'admin';
}

/**
 * Cek apakah user adalah Kepala Sekolah
 */
function isKepsek() {
    return isset($_SESSION['level']) && (
        strtolower($_SESSION['level']) === 'kepsek' || 
        strtolower($_SESSION['level']) === 'kepala_sekolah' ||
        strtolower($_SESSION['level']) === 'kepala sekolah'
    );
}

/**
 * Cek apakah user bisa CREATE (tambah data)
 */
function canCreate() {
    return isAdmin();
}

/**
 * Cek apakah user bisa READ (lihat data)
 */
function canRead() {
    return isAdmin() || isKepsek();
}

/**
 * Cek apakah user bisa UPDATE (edit data)
 */
function canUpdate() {
    return isAdmin();
}

/**
 * Cek apakah user bisa DELETE (hapus data)
 */
function canDelete() {
    return isAdmin();
}

/**
 * Proteksi halaman - redirect jika tidak punya akses
 * @param string $action - 'create', 'update', 'delete', 'read'
 * @param string $redirect - URL redirect jika tidak punya akses
 */
function requireAccess($action, $redirect = 'dashboard.php') {
    $allowed = false;
    
    switch(strtolower($action)) {
        case 'create':
            $allowed = canCreate();
            break;
        case 'update':
            $allowed = canUpdate();
            break;
        case 'delete':
            $allowed = canDelete();
            break;
        case 'read':
            $allowed = canRead();
            break;
    }
    
    if (!$allowed) {
        // Set flash message
        $_SESSION['flash_error'] = 'Anda tidak memiliki akses untuk melakukan tindakan ini!';
        header("Location: $redirect");
        exit;
    }
}

/**
 * Tampilkan flash message jika ada
 */
function showFlashMessage() {
    if (isset($_SESSION['flash_error'])) {
        $msg = $_SESSION['flash_error'];
        unset($_SESSION['flash_error']);
        return '<div class="fixed top-4 right-4 z-[9999] bg-red-500 text-white px-5 py-3 rounded-lg shadow-2xl flex items-center gap-3 min-w-[280px] animate-slide-in-left">
                    <i class="fas fa-exclamation-triangle text-xl"></i>
                    <span class="text-sm font-medium flex-1">' . htmlspecialchars($msg) . '</span>
                    <button onclick="this.parentElement.remove()" class="text-white/80 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>';
    }
    if (isset($_SESSION['flash_success'])) {
        $msg = $_SESSION['flash_success'];
        unset($_SESSION['flash_success']);
        return '<div class="fixed top-4 right-4 z-[9999] bg-emerald-500 text-white px-5 py-3 rounded-lg shadow-2xl flex items-center gap-3 min-w-[280px] animate-slide-in-left">
                    <i class="fas fa-check-circle text-xl"></i>
                    <span class="text-sm font-medium flex-1">' . htmlspecialchars($msg) . '</span>
                    <button onclick="this.parentElement.remove()" class="text-white/80 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>';
    }
    return '';
}

/**
 * Dapatkan label role untuk ditampilkan
 */
function getRoleLabel() {
    if (isAdmin()) return 'Administrator';
    if (isKepsek()) return 'Kepala Sekolah';
    return $_SESSION['level'] ?? 'User';
}

/**
 * Dapatkan icon role
 */
function getRoleIcon() {
    if (isAdmin()) return 'fa-user-shield';
    if (isKepsek()) return 'fa-user-tie';
    return 'fa-user';
}