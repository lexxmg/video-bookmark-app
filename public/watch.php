<?php
// public/watch.php
$pageTitle = "Просмотр и изучение материала";
include __DIR__ . '/includes/header.php'; // Подключаем шапку и базу $pdo

$videoId = (int)($_GET['id'] ?? 0);

// Получаем данные о видеоролике
$stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ?");
$stmt->execute([$videoId]);
$video = $stmt->fetch();

if (!$video) {
    echo '<div class="container text-center"><p class="text-gray-500">Видео не найдено.</p><a href="index.php" class="text-blue-600 underline">На главную</a></div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Получаем метки, автоматически сортируя их по времени (timestamp) прямо из базы
$stmt = $pdo->prepare("SELECT * FROM bookmarks WHERE video_id = ? ORDER BY timestamp ASC");
$stmt->execute([$videoId]);
$bookmarks = $stmt->fetchAll();
?>

<div class="container">
    <!-- Две колонки: Плеер и Списки меток -->
    <div class="player-layout">
        
        <!-- Левая колонка: Сам HTML5 Видеоплеер -->
        <div class="video-holder">
            <!-- Nginx перенаправляет /storage/ на локальную папку storage/ -->
             
            <video id="videoPlayer" controls class="w-full rounded-md" data-saved-volume="<?= (float)($video['volume'] ?? 1.0) ?>">
                <source src="/storage/videos/<?= rawurlencode($video['file_name']) ?>" type="video/mp4">
                Ваш браузер не поддерживает встроенный HTML5 плеер.
            </video>
            <h3 class="text-xl font-bold mt-4 text-gray-800" style="margin-top: 1rem;"><?= htmlspecialchars($video['title']) ?></h3>
        </div>

        <!-- Правая колонка: Инструментарий расстановки меток -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3 class="font-bold text-gray-700" style="margin: 0; font-size: 1.1rem;">Метки таймкодов</h3>
                <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md text-sm font-medium transition cursor-pointer border-none shadow-sm">
                    + Поставить метку
                </button>
            </div>

            <!-- Список меток с прокруткой -->
            <div id="bookmarksList" class="bookmark-list">
                <?php if (empty($bookmarks)): ?>
                    <p id="noBookmarksText" style="color: #9ca3af; text-align: center; padding-top: 2rem; font-size: 0.875rem;">У этого видео пока нет меток. Поставьте первую во время паузы!</p>
                <?php endif; ?>
                
                <?php foreach ($bookmarks as $b): ?>
                    <!-- ДОБАВИЛИ data-timestamp СЮДА -->
                    <div class="bookmark-item" id="bookmark-row-<?= $b['id'] ?>" data-timestamp="<?= $b['timestamp'] ?>">
                        <button onclick="goToTime(<?= $b['timestamp'] ?>)" class="bookmark-btn">
                            <span class="bookmark-time">
                                <?= sprintf('%02d:%02d', floor($b['timestamp'] / 60), (int)$b['timestamp'] % 60) ?>
                            </span>
                            <span id="title-text-<?= $b['id'] ?>"><?= htmlspecialchars($b['title']) ?></span>
                        </button>
                        <div class="action-btns">
                            <button onclick="editBookmark(<?= $b['id'] ?>)" class="btn-action edit">Ред.</button>
                            <button onclick="deleteBookmark(<?= $b['id'] ?>)" class="btn-action del">Уд.</button>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>
</div>

<!-- Модальное окно (Форма добавления / редактирования названия) -->
<div id="bookmarkModal" class="modal hidden">
    <div class="modal-content">
        <h3 id="modalTitle" class="text-xl font-bold mb-4 text-gray-800" style="margin-top: 0; margin-bottom: 1rem;">Новый фрагмент</h3>
        <input type="hidden" id="bookmarkId">
        <input type="hidden" id="bookmarkTime">
        
        <label style="font-size: 0.875rem; color: #4b5563; font-weight: 500;">Название метки:</label>
        <input type="text" id="bookmarkTitleInput" class="input-field" placeholder="Например: Разбор грамматики, Новые слова...">
        
        <div style="display: flex; justify-content: flex-end; gap: 0.5rem;">
            <button onclick="closeModal()" class="bg-gray-200 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm font-medium border-none cursor-pointer">Отмена</button>
            <button onclick="saveBookmark()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium border-none cursor-pointer">Сохранить</button>
        </div>
    </div>
</div>

<!-- Логика интерактивного взаимодействия с видео на JavaScript -->
<script>
    const player = document.getElementById('videoPlayer');
    const modal = document.getElementById('bookmarkModal');
    const videoId = <?= $videoId ?>;

    // Функция перемотки к указанной секунде
    function goToTime(seconds) {
        player.currentTime = seconds;
        player.play();
    }

    // Открытие окна для добавления новой метки
    function openAddModal() {
        player.pause(); // Ставим видео на паузу для удобства ввода
        
        const currentTime = player.currentTime;
        document.getElementById('modalTitle').innerText = "Добавить метку на " + formatTime(currentTime);
        document.getElementById('bookmarkId').value = "";
        document.getElementById('bookmarkTime').value = currentTime;
        document.getElementById('bookmarkTitleInput').value = "";
        
        modal.classList.remove('hidden');
        document.getElementById('bookmarkTitleInput').focus();
    }

    // Открытие окна для изменения существующего имени
    function editBookmark(id) {
        const currentTitle = document.getElementById('title-text-' + id).innerText;
        document.getElementById('modalTitle').innerText = "Изменить название фрагмента";
        document.getElementById('bookmarkId').value = id;
        document.getElementById('bookmarkTitleInput').value = currentTitle;
        modal.classList.remove('hidden');
        document.getElementById('bookmarkTitleInput').focus();
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    // Перевод секунд в читаемый формат (мм:сс)
    function formatTime(seconds) {
        const m = Math.floor(seconds / 60).toString().padStart(2, '0');
        const s = Math.floor(seconds % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    }

    // Сохранение метки (Без перезагрузки страницы и сброса видео!)
    function saveBookmark() {
        const id = document.getElementById('bookmarkId').value;
        const title = document.getElementById('bookmarkTitleInput').value.trim();
        const timestamp = parseFloat(document.getElementById('bookmarkTime').value);

        if (!title) return alert('Пожалуйста, введите название метки!');

        const formData = new FormData();
        if (id) {
            formData.append('action', 'edit_bookmark');
            formData.append('id', id);
        } else {
            formData.append('action', 'add_bookmark');
            formData.append('video_id', videoId);
            formData.append('timestamp', timestamp);
        }
        formData.append('title', title);

        fetch('api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success || data === true) {
                closeModal(); // Закрываем окно

                if (id) {
                    // Если мы РЕДАКТИРОВАЛИ — просто обновляем текст на экране
                    document.getElementById('title-text-' + id).innerText = title;
                } else {
                    // Если мы ДОБАВЛЯЛИ новую метку — удаляем заглушку "Нет меток", если она есть
                    const noText = document.getElementById('noBookmarksText');
                    if (noText) noText.remove();

                    // Генерируем ID для новой строки (временный случайный, до следующей перезагрузки)
                    const tempId = Date.now();

                    // Форматируем время (мм:сс)
                    const m = Math.floor(timestamp / 60).toString().padStart(2, '0');
                    const s = Math.floor(timestamp % 60).toString().padStart(2, '0');

                    // Создаем HTML-код новой метки
                    const newRow = document.createElement('div');
                    newRow.className = 'bookmark-item';
                    newRow.id = 'bookmark-row-' + tempId;
                    newRow.setAttribute('data-timestamp', timestamp); // Запоминаем время для сортировки
                    newRow.innerHTML = `
                        <button onclick="goToTime(${timestamp})" class="bookmark-btn">
                            <span class="bookmark-time">${m}:${s}</span>
                            <span id="title-text-${tempId}">${escapeHtml(title)}</span>
                        </button>
                        <div class="action-btns">
                            <button onclick="location.reload()" class="btn-action edit">Ред.</button>
                            <button onclick="location.reload()" class="btn-action del">Уд.</button>
                        </div>
                    `;

                    // --- УМНАЯ АВТОМАТИЧЕСКАЯ СОРТИРОВКА В СПИСКЕ НА JS ---
                    const list = document.getElementById('bookmarksList');
                    const rows = Array.from(list.querySelectorAll('.bookmark-item'));
                    
                    // Находим правильное место для новой метки по времени
                    let inserted = false;
                    for (let i = 0; i < rows.length; i++) {
                        const rowTime = parseFloat(rows[i].getAttribute('data-timestamp') || 0);
                        if (timestamp < rowTime) {
                            list.insertBefore(newRow, rows[i]);
                            inserted = true;
                            break;
                        }
                    }
                    // If it's the latest timestamp, just push to the end
                    if (!inserted) {
                        list.appendChild(newRow);
                    }
                }

                // Плеер НЕ сбрасывается! Снимаем с паузы и учимся дальше:
                player.play();
            } else {
                alert('Ошибка сохранения: ' + (data.error || 'Неизвестная ошибка'));
            }
        })
        .catch(err => alert('Ошибка сети при отправке запроса'));
    }

    // Маленькая вспомогательная функция, чтобы защитить текст от поломки HTML-тегов
    function escapeHtml(text) {
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }


    // Удаление метки из списка
    function deleteBookmark(id) {
        if (!confirm('Вы уверены, что хотите удалить эту метку?')) return;
        
        const formData = new FormData();
        formData.append('action', 'delete_bookmark');
        formData.append('id', id);

        fetch('api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success || data === true) {
                document.getElementById('bookmark-row-' + id).remove();
                
                // Если меток больше нет, показываем текст-заглушку
                const list = document.getElementById('bookmarksList');
                if (list.querySelectorAll('.bookmark-item').length === 0) {
                    list.innerHTML = '<p id="noBookmarksText" style="color: #9ca3af; text-align: center; padding-top: 2rem; font-size: 0.875rem;">У этого видео пока нет меток. Поставьте первую во время паузы!</p>';
                }
            } else {
                alert('Не удалось удалить метку.');
            }
        });
    }

    // ==========================================================================
    // 1. УПРАВЛЕНИЕ ВИДЕОПЛЕЕРОМ ЧЕРЕЗ ПРОБЕЛ
    // ==========================================================================
    window.addEventListener('keydown', function(event) {
        // Находим поле ввода названия метки
        const titleInput = document.getElementById('bookmarkTitleInput');
        
        // Если фокус находится в поле ввода, пробел должен писать текст, а не останавливать видео
        if (document.activeElement === titleInput) {
            return;
        }

        // Проверяем, что нажата клавиша "Пробел" (код "Space" или 32)
        if (event.code === 'Space' || event.keyCode === 32) {
            event.preventDefault(); // Отменяем стандартную прокрутку страницы вниз от пробела
            
            if (player.paused) {
                player.play(); // Если стояло на паузе — запускаем
            } else {
                player.pause(); // Если играло — ставим на паузу
            }
        }
    });

    // ==========================================================================
    // ЗАПОМИНАНИЕ УРОВНЯ ГРОМКОСТИ ЧЕРЕЗ БАЗУ ДАННЫХ (ФИКС СБРОСА)
    // ==========================================================================

    // МГНОВЕННО считываем значение из базы данных и выставляем плееру
    const savedVolumeAttr = player.getAttribute('data-saved-volume');
    if (savedVolumeAttr !== null) {
        player.volume = parseFloat(savedVolumeAttr);
    }

    // Переменная для таймера задержки (debounce), чтобы не спамить БД
    let volumeTimeout;

    // Слушаем изменение громкости пользователем
    player.addEventListener('volumechange', function() {
        clearTimeout(volumeTimeout);

        // Отправляем запрос в базу только через 400мс после того, как пользователь остановил ползунок
        volumeTimeout = setTimeout(() => {
            const formData = new FormData();
            formData.append('action', 'save_volume');
            formData.append('video_id', videoId);
            formData.append('volume', player.volume);

            fetch('api.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Динамически обновляем атрибут на странице, чтобы при повторном изменении данные были актуальны
                    player.setAttribute('data-saved-volume', player.volume);
                    console.log('Громкость успешно сохранена в БД:', player.volume);
                } else {
                    console.error('Ошибка сохранения громкости:', data.error);
                }
            })
            .catch(err => console.error('Ошибка сети при сохранении громкости'));
        }, 400);
    });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
