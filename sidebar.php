<?php
// sidebar.php - Modern Sidebar Component (Blue Navy Theme)
$current_page = basename($_SERVER['PHP_SELF']);

// Ambil data user dari session
$user_name = $_SESSION['nama_lengkap'] ?? 'Administrator';
$user_role = $_SESSION['level'] ?? 'Admin';
$user_initial = strtoupper(substr($user_name, 0, 1));
?>

<aside 
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    class="fixed lg:translate-x-0 lg:static inset-y-0 left-0 z-50 w-72 flex flex-col shadow-2xl transition-transform duration-300 ease-in-out overflow-hidden"
    style="background: linear-gradient(135deg, #0f2744 0%, #1a365d 50%, #2c5282 100%); background-size: 200% 200%; animation: gradientShift 15s ease infinite;">
    
    <!-- Animated Background Effect -->
    <div class="absolute inset-0 opacity-30 pointer-events-none" style="background: radial-gradient(circle at 50% 50%, rgba(255,255,255,0.15) 0%, transparent 70%); animation: rotate 30s linear infinite;"></div>
    
    <!-- Floating Particles -->
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
        <div class="absolute w-1 h-1 bg-white/20 rounded-full" style="left: 10%; top: 20%; animation: float 20s infinite;"></div>
        <div class="absolute w-1.5 h-1.5 bg-white/20 rounded-full" style="left: 70%; top: 40%; animation: float 20s infinite 5s;"></div>
        <div class="absolute w-1 h-1 bg-white/20 rounded-full" style="left: 30%; top: 60%; animation: float 20s infinite 10s;"></div>
        <div class="absolute w-1.5 h-1.5 bg-white/20 rounded-full" style="left: 80%; top: 80%; animation: float 20s infinite 15s;"></div>
        <div class="absolute w-1 h-1 bg-blue-300/30 rounded-full" style="left: 50%; top: 30%; animation: float 20s infinite 7s;"></div>
        <div class="absolute w-1.5 h-1.5 bg-blue-300/30 rounded-full" style="left: 20%; top: 75%; animation: float 20s infinite 12s;"></div>
    </div>
    
    <!-- Glassmorphism Overlay -->
    <div class="relative z-10 backdrop-blur-md bg-white/5 h-full flex flex-col">
        
        <!-- User Profile Section -->
        <div class="p-6 text-center border-b border-white/20">
            <div class="relative inline-block">
                <div class="w-20 h-20 rounded-full bg-gradient-to-br from-white to-blue-100 flex items-center justify-center text-3xl font-bold text-blue-700 shadow-xl mx-auto mb-3 transition-all duration-300 hover:scale-110 hover:rotate-6">
                    <?= $user_initial ?>
                </div>
                <!-- Online Indicator -->
                <div class="absolute bottom-3 right-3 w-4 h-4 bg-emerald-500 border-2 border-white rounded-full animate-pulse shadow-lg"></div>
            </div>
            <h5 class="text-white text-base font-semibold mb-1 drop-shadow-lg"><?= htmlspecialchars($user_name) ?></h5>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-xs text-white/90 font-medium">
                <i class="fas fa-shield-alt"></i>
                <?= htmlspecialchars($user_role) ?>
            </span>
        </div>
        
        <!-- Menu Container -->
        <nav class="flex-1 overflow-y-auto py-4 custom-scrollbar">
            
            <!-- MAIN MENU Section -->
            <div class="px-5 mb-2">
                <p class="text-blue-200/60 text-[11px] font-bold uppercase tracking-wider flex items-center gap-2">
                    <span class="flex-1 h-px bg-gradient-to-r from-transparent via-blue-200/40 to-transparent"></span>
                    Main Menu
                    <span class="flex-1 h-px bg-gradient-to-r from-transparent via-blue-200/40 to-transparent"></span>
                </p>
            </div>
            
            <a href="dashboard.php" 
               class="group flex items-center px-5 py-3.5 mx-3 mb-1 rounded-xl text-white/90 text-sm font-medium transition-all duration-300 hover:bg-white/15 hover:translate-x-1 relative overflow-hidden <?= ($current_page=='dashboard.php') ? 'active bg-white/20 text-white font-semibold shadow-lg shadow-blue-900/50' : '' ?>">
                <div class="absolute inset-0 bg-gradient-to-r from-white/20 to-transparent transform -translate-x-full group-hover:translate-x-0 transition-transform duration-300"></div>
                <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-0 bg-cyan-300 rounded-r-full transition-all duration-300 shadow-lg shadow-cyan-300/50 <?= ($current_page=='dashboard.php') ? 'h-3/5' : 'group-hover:h-3/5' ?>"></div>
                <i class="fas fa-home w-6 mr-3 text-lg transition-all duration-300 group-hover:scale-125 group-hover:-rotate-12 relative z-10"></i>
                <span class="relative z-10">Dashboard</span>
            </a>
            
            <a href="ruangan.php" 
               class="group flex items-center px-5 py-3.5 mx-3 mb-1 rounded-xl text-white/90 text-sm font-medium transition-all duration-300 hover:bg-white/15 hover:translate-x-1 relative overflow-hidden <?= ($current_page=='ruangan.php') ? 'active bg-white/20 text-white font-semibold shadow-lg shadow-blue-900/50' : '' ?>">
                <div class="absolute inset-0 bg-gradient-to-r from-white/20 to-transparent transform -translate-x-full group-hover:translate-x-0 transition-transform duration-300"></div>
                <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-0 bg-cyan-300 rounded-r-full transition-all duration-300 shadow-lg shadow-cyan-300/50 <?= ($current_page=='ruangan.php') ? 'h-3/5' : 'group-hover:h-3/5' ?>"></div>
                <i class="fas fa-door-open w-6 mr-3 text-lg transition-all duration-300 group-hover:scale-125 group-hover:-rotate-12 relative z-10"></i>
                <span class="relative z-10">Manajemen Ruangan</span>
            </a>
            
            <!-- MANAGEMENT Section -->
            <div class="px-5 mb-2 mt-4">
                <p class="text-blue-200/60 text-[11px] font-bold uppercase tracking-wider flex items-center gap-2">
                    <span class="flex-1 h-px bg-gradient-to-r from-transparent via-blue-200/40 to-transparent"></span>
                    Management
                    <span class="flex-1 h-px bg-gradient-to-r from-transparent via-blue-200/40 to-transparent"></span>
                </p>
            </div>
            
            <a href="kategori_aset.php" 
               class="group flex items-center px-5 py-3.5 mx-3 mb-1 rounded-xl text-white/90 text-sm font-medium transition-all duration-300 hover:bg-white/15 hover:translate-x-1 relative overflow-hidden <?= ($current_page=='kategori_aset.php') ? 'active bg-white/20 text-white font-semibold shadow-lg shadow-blue-900/50' : '' ?>">
                <div class="absolute inset-0 bg-gradient-to-r from-white/20 to-transparent transform -translate-x-full group-hover:translate-x-0 transition-transform duration-300"></div>
                <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-0 bg-cyan-300 rounded-r-full transition-all duration-300 shadow-lg shadow-cyan-300/50 <?= ($current_page=='kategori_aset.php') ? 'h-3/5' : 'group-hover:h-3/5' ?>"></div>
                <i class="fas fa-tags w-6 mr-3 text-lg transition-all duration-300 group-hover:scale-125 group-hover:-rotate-12 relative z-10"></i>
                <span class="relative z-10">Kategori Aset</span>
            </a>
            
            <a href="tambah.php" 
               class="group flex items-center px-5 py-3.5 mx-3 mb-1 rounded-xl text-white/90 text-sm font-medium transition-all duration-300 hover:bg-white/15 hover:translate-x-1 relative overflow-hidden <?= ($current_page=='tambah.php') ? 'active bg-white/20 text-white font-semibold shadow-lg shadow-blue-900/50' : '' ?>">
                <div class="absolute inset-0 bg-gradient-to-r from-white/20 to-transparent transform -translate-x-full group-hover:translate-x-0 transition-transform duration-300"></div>
                <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-0 bg-cyan-300 rounded-r-full transition-all duration-300 shadow-lg shadow-cyan-300/50 <?= ($current_page=='tambah.php') ? 'h-3/5' : 'group-hover:h-3/5' ?>"></div>
                <i class="fas fa-plus-circle w-6 mr-3 text-lg transition-all duration-300 group-hover:scale-125 group-hover:-rotate-12 relative z-10"></i>
                <span class="relative z-10">Tambah Aset</span>
            </a>
            
            <a href="kondisi_aset.php" 
               class="group flex items-center px-5 py-3.5 mx-3 mb-1 rounded-xl text-white/90 text-sm font-medium transition-all duration-300 hover:bg-white/15 hover:translate-x-1 relative overflow-hidden <?= ($current_page=='kondisi_aset.php') ? 'active bg-white/20 text-white font-semibold shadow-lg shadow-blue-900/50' : '' ?>">
                <div class="absolute inset-0 bg-gradient-to-r from-white/20 to-transparent transform -translate-x-full group-hover:translate-x-0 transition-transform duration-300"></div>
                <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-0 bg-cyan-300 rounded-r-full transition-all duration-300 shadow-lg shadow-cyan-300/50 <?= ($current_page=='kondisi_aset.php') ? 'h-3/5' : 'group-hover:h-3/5' ?>"></div>
                <i class="fas fa-heart-pulse w-6 mr-3 text-lg transition-all duration-300 group-hover:scale-125 group-hover:-rotate-12 relative z-10"></i>
                <span class="relative z-10">Kondisi Aset</span>
            </a>
            
            <a href="tracking_aset.php" 
               class="group flex items-center px-5 py-3.5 mx-3 mb-1 rounded-xl text-white/90 text-sm font-medium transition-all duration-300 hover:bg-white/15 hover:translate-x-1 relative overflow-hidden <?= ($current_page=='tracking_aset.php') ? 'active bg-white/20 text-white font-semibold shadow-lg shadow-blue-900/50' : '' ?>">
                <div class="absolute inset-0 bg-gradient-to-r from-white/20 to-transparent transform -translate-x-full group-hover:translate-x-0 transition-transform duration-300"></div>
                <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-0 bg-cyan-300 rounded-r-full transition-all duration-300 shadow-lg shadow-cyan-300/50 <?= ($current_page=='tracking_aset.php') ? 'h-3/5' : 'group-hover:h-3/5' ?>"></div>
                <i class="fas fa-route w-6 mr-3 text-lg transition-all duration-300 group-hover:scale-125 group-hover:-rotate-12 relative z-10"></i>
                <span class="relative z-10">Tracking Aset</span>
            </a>
            
            <!-- REPORTS Section -->
            <div class="px-5 mb-2 mt-4">
                <p class="text-blue-200/60 text-[11px] font-bold uppercase tracking-wider flex items-center gap-2">
                    <span class="flex-1 h-px bg-gradient-to-r from-transparent via-blue-200/40 to-transparent"></span>
                    Reports
                    <span class="flex-1 h-px bg-gradient-to-r from-transparent via-blue-200/40 to-transparent"></span>
                </p>
            </div>
            
            <a href="export_excel.php" 
               class="group flex items-center px-5 py-3.5 mx-3 mb-1 rounded-xl text-white/90 text-sm font-medium transition-all duration-300 hover:bg-white/15 hover:translate-x-1 relative overflow-hidden <?= ($current_page=='export_excel.php') ? 'active bg-white/20 text-white font-semibold shadow-lg shadow-blue-900/50' : '' ?>">
                <div class="absolute inset-0 bg-gradient-to-r from-white/20 to-transparent transform -translate-x-full group-hover:translate-x-0 transition-transform duration-300"></div>
                <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-0 bg-cyan-300 rounded-r-full transition-all duration-300 shadow-lg shadow-cyan-300/50 <?= ($current_page=='export_excel.php') ? 'h-3/5' : 'group-hover:h-3/5' ?>"></div>
                <i class="fas fa-file-excel w-6 mr-3 text-lg transition-all duration-300 group-hover:scale-125 group-hover:-rotate-12 relative z-10"></i>
                <span class="relative z-10 flex-1">Export Excel</span>
                <span class="relative z-10 px-2 py-0.5 bg-gradient-to-r from-red-500 to-pink-500 text-white text-[10px] font-bold rounded-full shadow-lg animate-pulse">NEW</span>
            </a>
            
        </nav>
        
        <!-- Logout Button -->
        <div class="p-3 border-t border-white/20">
            <a href="logout.php" 
               @click.prevent="confirmLogout()"
               class="group flex items-center px-5 py-3 rounded-xl bg-red-500/20 border border-red-500/30 text-red-100 text-sm font-medium transition-all duration-300 hover:bg-red-500/40 hover:border-red-500/60 hover:scale-105">
                <i class="fas fa-sign-out-alt w-6 mr-3 text-lg transition-all duration-300 group-hover:scale-125"></i>
                <span>Logout</span>
            </a>
        </div>
        
        <!-- Footer -->
        <div class="p-4 border-t border-white/20 text-center">
            <p class="text-blue-200/50 text-[11px]">
                <i class="fas fa-cog animate-spin mr-1"></i>
                v2.0.0 | © <?= date('Y') ?> SDN Curug 01
            </p>
        </div>
        
    </div>
</aside>

<style>
@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes float {
    0%, 100% { 
        transform: translateY(0) translateX(0);
        opacity: 0;
    }
    10% { opacity: 1; }
    90% { opacity: 1; }
    100% { 
        transform: translateY(-100vh) translateX(50px);
        opacity: 0;
    }
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.3);
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.5);
}
</style>