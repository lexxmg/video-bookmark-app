// --- ОЧЕРЕДЬ ГЕНЕРАЦИИ КАДРОВ ---
document.addEventListener("DOMContentLoaded", function() {
    const queue = [];
    <?php foreach ($videos as $v): ?>
    <?php if ((int)$v['has_thumbnail'] !== 1): ?>
    queue.push({
        id: <?= $v['id'] ?>,
        video: document.getElementById('thumb-video-<?= $v['id'] ?>'),
        canvas: document.getElementById('canvas-<?= $v['id'] ?>'),
        loader: document.getElementById('loader-<?= $v['id'] ?>')
    });
    <?php endif; ?>
    <?php endforeach; ?>

    function processNext() {
        if (queue.length === 0) return;

        const current = queue.shift();
        current.video.src = current.video.getAttribute('data-src');
        current.video.load();

        current.video.onloadedmetadata = function() {
            current.video.currentTime = 2;
        };

        current.video.onseeked = function() {
            try {
                const ctx = current.canvas.getContext('2d');
                ctx.drawImage(current.video, 0, 0, current.canvas.width, current.canvas.height);
                current.canvas.classList.remove('hidden');
                current.loader.classList.add('hidden');

                const base64Image = current.canvas.toDataURL('image/jpeg', 0.85);

                const formData = new FormData();
                formData.append('action', 'save_thumbnail');
                formData.append('id', current.id);
                formData.append('image', base64Image);

                fetch('api.php', { method: 'POST', body: formData });
            } catch (e) {
                console.error("Ошибка кадра:", e);
            }

            current.video.src = "";
            current.video.load();
            current.video.remove();

            setTimeout(processNext, 50); 
        };
    }
    processNext();
});

// --- ЛОГИКА МОДАЛКИ ПЕРЕИМЕНОВАНИЯ ---
const videoModal = document.getElementById('editVideoModal');

function openEditVideoModal(id) {
    const currentTitle = document.getElementById('video-title-' + id).innerText;
    document.getElementById('editVideoId').value = id;
    document.getElementById('editVideoTitleInput').value = currentTitle.trim();
    videoModal.classList.remove('hidden');
    document.getElementById('editVideoTitleInput').focus();
}

function closeEditVideoModal() {
    videoModal.classList.add('hidden');
}

function saveVideoTitle() {
    const id = document.getElementById('editVideoId').value;
    const title = document.getElementById('editVideoTitleInput').value.trim();

    if (!title) return alert('Введите название!');

    const formData = new FormData();
    formData.append('action', 'edit_video_title');
    formData.append('id', id);
    formData.append('title', title);

    fetch('api.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('video-title-' + id).innerText = title;
            closeEditVideoModal();
        } else {
            alert('Ошибка: ' + data.error);
        }
    });
}
