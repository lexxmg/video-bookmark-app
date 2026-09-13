<?php
$pageTitle = "Главная — Мои Видеофайлы";
include __DIR__ . '/includes/header.php';

// 1. Получаем текущую сохраненную сортировку из базы данных
$currentSort = 'id_desc';
try {
    $sortStmt = $pdo->query("SELECT value FROM settings WHERE key = 'sort_by'");
    $savedSort = $sortStmt->fetchColumn();
    if ($savedSort) {
        $currentSort = $savedSort;
    }
} catch (PDOException $e) {
    // В случае ошибки оставляем дефолтную сортировку
}

// 2. Формируем SQL-запрос в зависимости от типа сортировки
switch ($currentSort) {
    case 'custom':
        // Сортируем по нашему новому полю по возрастанию (1, 2, 3...)
        $orderBy = "ORDER BY sort_order ASC, id DESC";
        break;
    case 'title_asc':
        $orderBy = "ORDER BY title ASC";
        break;
    case 'views_desc':
        $orderBy = "ORDER BY views_count DESC";
        break;
    case 'id_desc':
    default:
        $orderBy = "ORDER BY id DESC";
        break;
}

$videos = $pdo->query("SELECT * FROM videos $orderBy")->fetchAll();

// --- НОВАЯ СВЕРХБЫСТРАЯ ЗАЩИТА ОТ ДУРАКА ---
$brokenThumbIds = [];

// Проверяем файлы на диске, используя индекс массива $index
foreach ($videos as $index => $v) {
    $imgName = pathinfo($v['file_name'], PATHINFO_FILENAME) . '.jpg';
    $thumbPath = __DIR__ . '/../storage/thumbnails/' . $imgName;

    if ((int)$v['has_thumbnail'] === 1 && !file_exists($thumbPath)) {
        $brokenThumbIds[] = $v['id'];
        $videos[$index]['has_thumbnail'] = 0; // Меняем значение в массиве для текущего рендеринга
    }
}

// Отправляем в базу данных ОДИН пакетный запрос вместо кучи мелких
if (!empty($brokenThumbIds)) {
    $placeholders = implode(',', array_fill(0, count($brokenThumbIds), '?'));
    $fixStmt = $pdo->prepare("UPDATE videos SET has_thumbnail = 0 WHERE id IN ($placeholders)");
    $fixStmt->execute($brokenThumbIds);
}
?>


<!-- Главный контейнер -->
<div class="max-w-7xl mx-auto px-6 py-8 w-full box-border">

    <!-- Шапка страницы: Заголовок и Блок Сортировки -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-8 pb-3 border-b-2 border-gray-200">
        <h2 class="text-2xl font-extrabold text-gray-800 tracking-tight m-0">
            Доступные материалы для изучения
        </h2>

        <!-- Кнопки сортировки -->
        <div class="flex items-center gap-1.5 bg-gray-200/60 p-1 rounded-xl self-start sm:self-auto">
            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider px-2 hidden md:inline">Сортировка:</span>

            <button onclick="changeSort('id_desc')" id="btn-sort-id_desc"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all border-none cursor-pointer <?= $currentSort === 'id_desc' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900 bg-transparent' ?>">
                📅 Новые
            </button>

            <button onclick="changeSort('title_asc')" id="btn-sort-title_asc"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all border-none cursor-pointer <?= $currentSort === 'title_asc' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900 bg-transparent' ?>">
                🔤 По имени
            </button>

            <button onclick="changeSort('views_desc')" id="btn-sort-views_desc"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all border-none cursor-pointer <?= $currentSort === 'views_desc' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900 bg-transparent' ?>">
                🔥 Просмотры
            </button>

            <!-- НАША НОВАЯ КНОПКА РУЧНОЙ СОРТИРОВКИ -->
            <button onclick="changeSort('custom')" id="btn-sort-custom"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all border-none cursor-pointer flex items-center gap-1.5 relative <?= $currentSort === 'custom' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900 bg-transparent' ?>">
                <span>👋 Вручную</span>

                <!-- Индикатор справки "!", теперь на него вешаем id для JS -->
                <span id="sort-hint-trigger" class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-blue-100 text-blue-600 text-[10px] font-black border border-solid border-blue-300 select-none cursor-help shrink-0">!</span>
            </button>
        </div>
    </div>

    <?php if (empty($videos)): ?>
        <div class="bg-white p-8 rounded-xl shadow-md text-center border border-gray-200 max-w-2xl mx-auto">
            <p class="text-gray-500 mb-4 text-base">В каталоге пока нет видеофайлов.</p>
            <a href="admin.php" class="text-blue-600 font-semibold hover:text-blue-800 transition duration-150">Перейти в админку для синхронизации файлов →</a>
        </div>
    <?php else: ?>
        <!-- Сетка плиток -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 w-full box-border">
            <?php foreach ($videos as $v): ?>
                <?php
                $imgName = pathinfo($v['file_name'], PATHINFO_FILENAME) . '.jpg';
                ?>

                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden flex flex-col shadow-sm hover:shadow-md transition-shadow duration-200 h-85 box-border" id="video-card-<?= $v['id'] ?>">

                    <a href="watch.php?id=<?= $v['id'] ?>" class="block relative bg-slate-900 h-40 min-h-40 overflow-hidden w-full cursor-pointer" title="Нажмите, чтобы открыть плеер">
                        <?php if ((int)$v['has_thumbnail'] === 1): ?>
                            <img src="/storage/thumbnails/<?= rawurlencode($imgName) ?>" class="w-full h-full object-cover" alt="Превью">
                        <?php else: ?>
                            <canvas id="canvas-<?= $v['id'] ?>" width="320" height="180" class="w-full h-full object-cover hidden"></canvas>
                            <div id="loader-<?= $v['id'] ?>" class="absolute inset-0 flex items-center justify-center text-4xl text-white">🎬</div>
                        <?php endif; ?>
                    </a>

                    <div class="p-4 flex-1 flex flex-col justify-between overflow-hidden box-border">
                        <div class="flex justify-between items-start gap-2 mb-2 overflow-hidden">
                            <h3 class="font-bold text-gray-800 text-sm leading-snug line-clamp-2 m-0 flex-1" id="video-title-<?= $v['id'] ?>" title="<?= htmlspecialchars($v['title']) ?>">
                                <?= htmlspecialchars($v['title']) ?>
                            </h3>
                            <button onclick="openEditVideoModal(<?= $v['id'] ?>)" class="bg-none border-none text-gray-400 hover:text-yellow-600 cursor-pointer p-1 text-xs shrink-0 transition-colors" title="Переименовать">✏️</button>
                        </div>

                        <!-- Вывод счетчика просмотров -->
                        <div class="text-xs text-gray-400 mb-3 flex items-center gap-1">
                            👁️ Просмотров: <span class="font-semibold text-gray-600"><?= (int)$v['views_count'] ?></span>
                        </div>

                        <a href="watch.php?id=<?= $v['id'] ?>" class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg font-semibold text-sm shadow-sm hover:shadow-md transition duration-150">
                            🎬 Начать просмотр
                        </a>
                    </div>

                    <?php if ((int)$v['has_thumbnail'] !== 1): ?>
                        <video id="thumb-video-<?= $v['id'] ?>" data-id="<?= $v['id'] ?>" data-src="/storage/videos/<?= rawurlencode($v['file_name']) ?>" preload="none" muted class="hidden"></video>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<!-- Модалка переименования видео -->
<div id="editVideoModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 box-border">
    <div class="bg-white p-6 rounded-xl border border-gray-200 max-w-md w-full shadow-xl box-border">
        <h3 class="text-xl font-extrabold mb-4 text-gray-800 m-0 tracking-tight">Переименовать видео</h3>
        <input type="hidden" id="editVideoId">

        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Название плитки:</label>
        <input type="text" id="editVideoTitleInput" class="w-full p-2.5 border border-gray-300 rounded-lg text-sm mb-5 box-border outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/10">

        <div class="flex justify-end gap-3">
            <button onclick="closeEditVideoModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-xs font-bold border-none cursor-pointer transition">Отмена</button>
            <button onclick="saveVideoTitle()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-xs font-bold border-none cursor-pointer transition">Сохранить</button>
        </div>
    </div>
</div>

<script>
    // Безопасно формируем JSON-массив данных для генератора превью
    const queueData = [
        <?php foreach ($videos as $v): ?>
            <?php if ((int)$v['has_thumbnail'] !== 1): ?> {
                    id: <?= (int)$v['id'] ?>,
                    src: "/storage/videos/<?= rawurlencode($v['file_name']) ?>"
                },
            <?php endif; ?>
        <?php endforeach; ?>
    ];

    // Запускаем генерацию сразу после загрузки страницы
    document.addEventListener("DOMContentLoaded", function() {
        initThumbnailQueue(queueData);
    });
</script>
<script src="/js/main.js"></script>
<script src="/js/drag.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>