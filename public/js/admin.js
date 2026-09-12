// Индикация отправки формы (показывает лоадер при загрузке тяжелых файлов)
const uploadForm = document.getElementById('uploadForm');
if (uploadForm) {
    uploadForm.addEventListener('submit', function() {
        const statusEl = document.getElementById('progressStatus');
        if (statusEl) statusEl.style.display = 'block';
    });
}

// Динамическое отображение имени выбранного файла в лейбле
const fileInput = document.getElementById('video_file_input');
if (fileInput) {
    fileInput.addEventListener('change', function(e) {
        const fileName = e.target.files[0] ? e.target.files[0].name : "Нажмите для выбора файла";
        const selectTextEl = document.getElementById('file-select-text');
        if (selectTextEl) {
            selectTextEl.innerText = fileName;
            selectTextEl.classList.add('text-blue-600');
        }
    });
}

// Асинхронное полное удаление видеоурока
function deleteVideoCompletely(id, title) {
    if (!confirm("Вы действительно хотите НАВСЕГДА удалить видео «" + title + "»?\n\nБудет физически удален .mp4 файл, превью .jpg и ВСЕ созданные закладки таймкодов!")) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_video_completely');
    formData.append('id', id);

    fetch('api.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById('video-row-' + id);
            if (row) row.remove();
        } else {
            alert("Ошибка при удалении: " + data.error);
        }
    })
    .catch(err => alert("Ошибка сети при отправке запроса в API"));
}

// Асинхронное обнуление счетчика просмотров
function resetVideoViews(id, title) {
    if (!confirm("Вы действительно хотите обнулить счетчик просмотров для видео «" + title + "»?")) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'reset_views');
    formData.append('video_id', id);

    fetch('api.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert("Счетчик просмотров успешно обнулен!");
            window.location.reload(); // Перезагружаем страницу, чтобы обновить цифры в таблице
        } else {
            alert("Ошибка при обнулении: " + data.error);
        }
    })
    .catch(err => alert("Ошибка сети при отправке запроса в API"));
}