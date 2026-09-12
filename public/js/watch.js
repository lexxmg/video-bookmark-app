// --- ИНИЦИАЛИЗАЦИЯ ПЕРЕМЕННЫХ ---
const player = document.getElementById('videoPlayer');
const modal = document.getElementById('bookmarkModal');

// --- НАВИГАЦИЯ И УПРАВЛЕНИЕ ПЛЕЕРОМ ---
function goToTime(seconds) {
    player.currentTime = seconds;
    player.play();
}

// Открытие модального окна добавления метки
function openAddModal() {
    player.pause();
    const currentTime = player.currentTime;
    document.getElementById('modalTitle').innerText = "Добавить метку на " + formatTime(currentTime);
    document.getElementById('bookmarkId').value = "";
    document.getElementById('bookmarkTime').value = currentTime;
    document.getElementById('bookmarkTitleInput').value = "";
    modal.classList.remove('hidden');
    document.getElementById('bookmarkTitleInput').focus();
}

// Открытие модального окна редактирования метки
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

function formatTime(seconds) {
    const m = Math.floor(seconds / 60).toString().padStart(2, '0');
    const s = Math.floor(seconds % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
}

// Защита от XSS и поломки HTML-верстки на клиенте
function escapeHtml(text) {
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

// --- AJAX: РАБОТА С МЕТКАМИ (СОХРАНЕНИЕ / УДАЛЕНИЕ) ---
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
        formData.append('video_id', currentVideoId);
        formData.append('timestamp', timestamp);
    }
    formData.append('title', title);

    fetch('api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success || data === true) {
                closeModal();

                if (id) {
                    document.getElementById('title-text-' + id).innerText = title;
                } else {
                    const noText = document.getElementById('noBookmarksText');
                    if (noText) noText.remove();

                    const tempId = Date.now();
                    const m = Math.floor(timestamp / 60).toString().padStart(2, '0');
                    const s = Math.floor(timestamp % 60).toString().padStart(2, '0');

                    const newRow = document.createElement('div');
                    newRow.className = 'bookmark-item flex justify-between items-center p-3 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg mb-3 transition';
                    newRow.id = 'bookmark-row-' + tempId;
                    newRow.setAttribute('data-timestamp', timestamp);

                    newRow.innerHTML = `
                        <button onclick="goToTime(${timestamp})" class="bg-none border-none text-left text-blue-600 hover:text-blue-800 font-bold cursor-pointer p-0 flex-1 text-sm flex items-center">
                            <span class="bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded-md mr-3 shrink-0">${m}:${s}</span>
                            <span id="title-text-${tempId}" class="break-all">${escapeHtml(title)}</span>
                        </button>
                        <div class="flex gap-3 ml-2 shrink-0">
                            <button onclick="editBookmark(${tempId})" class="bg-none border-none text-gray-400 hover:text-yellow-600 text-xs cursor-pointer p-0">Ред.</button>
                            <button onclick="deleteBookmark(${tempId})" class="bg-none border-none text-gray-400 hover:text-red-600 text-xs cursor-pointer p-0">Уд.</button>
                        </div>
                    `;

                    const list = document.getElementById('bookmarksList');
                    const rows = Array.from(list.querySelectorAll('.bookmark-item'));

                    let inserted = false;
                    for (let i = 0; i < rows.length; i++) {
                        const rowTime = parseFloat(rows[i].getAttribute('data-timestamp') || 0);
                        if (timestamp < rowTime) {
                            list.insertBefore(newRow, rows[i]);
                            inserted = true;
                            break;
                        }
                    }
                    if (!inserted) {
                        list.appendChild(newRow);
                    }
                }
            } else {
                alert('Ошибка сохранения: ' + (data.error || 'Неизвестная ошибка'));
            }
        })
        .catch(err => alert('Ошибка сети при отправке запроса'));
}

function deleteBookmark(id) {
    if (!confirm('Вы уверены, что хотите удалить эту метку?')) return;
    const formData = new FormData();
    formData.append('action', 'delete_bookmark');
    formData.append('id', id);
    fetch('api.php', { method: 'POST', body: formData })
        .then(() => document.getElementById('bookmark-row-' + id).remove());
}

// --- ГОРЯЧИЕ КЛАВИШИ ---
window.addEventListener('keydown', function(e) {
    if (document.activeElement === document.getElementById('bookmarkTitleInput')) return;
    if (e.code === 'Space' || e.keyCode === 32) {
        e.preventDefault();
        if (player.paused) player.play();
        else player.pause();
    }
});

// --- СИНХРОНИЗАЦИЯ НАСТРОЕК (ГРОМКОСТЬ / ПРОСМОТРЫ / ПОЗИЦИЯ) ---

document.addEventListener("DOMContentLoaded", function() {
    const formData = new FormData();
    formData.append('action', 'increment_views');
    formData.append('video_id', currentVideoId);

    fetch('api.php', { method: 'POST', body: formData })
        .catch(err => console.error('Ошибка инкремента просмотров:', err));
});

const defaultVolume = player.getAttribute('data-saved-volume');
if (defaultVolume !== null) player.volume = parseFloat(defaultVolume);

let volumeTimeout;
player.addEventListener('volumechange', function() {
    clearTimeout(volumeTimeout);
    volumeTimeout = setTimeout(() => {
        const formData = new FormData();
        formData.append('action', 'save_volume');
        formData.append('video_id', currentVideoId);
        formData.append('volume', player.volume);
        fetch('api.php', { method: 'POST', body: formData });
    }, 400);
});

const savedPosition = player.getAttribute('data-saved-position');
if (savedPosition !== null && parseFloat(savedPosition) > 0) {
    player.currentTime = parseFloat(savedPosition);
}

let positionTimeout;
player.addEventListener('timeupdate', function() {
    if (player.paused) {
        saveCurrentPositionDirectly();
        return;
    }

    clearTimeout(positionTimeout);
    positionTimeout = setTimeout(() => {
        saveCurrentPositionDirectly();
    }, 1000);
});

function saveCurrentPositionDirectly() {
    const formData = new FormData();
    formData.append('action', 'save_position');
    formData.append('video_id', currentVideoId);
    formData.append('position', player.currentTime);
    
    fetch('api.php', { method: 'POST', body: formData })
        .catch(err => console.error('Ошибка сохранения позиции:', err));
}
