document.addEventListener("DOMContentLoaded", function () {
    // Включаем ручную сортировку ТОЛЬКО если у пользователя активирован режим "Вручную"
    // Мы понимаем это по наличию класса у кнопки сортировки
    const customSortBtn = document.getElementById('btn-sort-custom');
    if (!customSortBtn || !customSortBtn.classList.contains('bg-white')) {
        return; // Если активен другой режим сортировки — перетаскивание заблокировано
    }

    // Находим сетку-контейнер и все карточки видео
    const gridContainer = document.querySelector('.grid');
    if (!gridContainer) return;

    const cards = gridContainer.querySelectorAll('[id^="video-card-"]');

    // Делаем каждую карточку перетаскиваемой и добавляем визуальные маркеры
    cards.forEach(card => {
        card.setAttribute('draggable', 'true');
        // Меняем курсор мыши, чтобы пользователь сразу понял, что плитку можно схватить
        card.style.cursor = 'grab';
        
        // Отключаем стандартный Drag-and-Drop для картинок внутри карточки, 
        // чтобы они не мешали тащить саму плитку
        const img = card.querySelector('img');
        if (img) img.setAttribute('draggable', 'false');

        // Событие 1: Пользователь захватил карточку мышкой
        card.addEventListener('dragstart', function (e) {
            card.classList.add('opacity-40', 'scale-95'); // Эффект "полупрозрачности" как на iOS
            card.style.cursor = 'grabbing';
            e.dataTransfer.effectAllowed = 'move';
            // Запоминаем ID перетаскиваемой карточки
            e.dataTransfer.setData('text/plain', card.id);
        });

        // Событие 2: Карточку отпустили (в любом месте)
        card.addEventListener('dragend', function () {
            card.classList.remove('opacity-40', 'scale-95');
            card.style.cursor = 'grab';
            
            // Зачищаем временные стили подсветки со всех карточек
            cards.forEach(c => c.classList.remove('border-blue-500', 'border-2'));
            
            // Сохраняем получившийся порядок в базу данных
            saveNewCardsOrder();
        });

        // Событие 3: Перетаскиваемый элемент находится НАД текущей карточкой
        card.addEventListener('dragover', function (e) {
            e.preventDefault(); // Разрешаем сброс (Drop) элемента сюда
            e.dataTransfer.dropEffect = 'move';
            
            const draggingId = e.dataTransfer.getData('text/plain') || document.querySelector('.opacity-40')?.id;
            if (!draggingId || draggingId === card.id) return;

            // Вычисляем, в какую половину карточки (левую или правую) целится курсор
            const bounding = card.getBoundingClientRect();
            const offset = e.clientX - bounding.left;
            
            // Динамически переставляем карточки в DOM на лету
            if (offset > bounding.width / 2) {
                card.after(document.getElementById(draggingId));
            } else {
                card.before(document.getElementById(draggingId));
            }
        });
    });

    // Функция сбора актуального порядка плиток и отправки в API
    function saveNewCardsOrder() {
        const currentCards = gridContainer.querySelectorAll('[id^="video-card-"]');
        const idsOrder = [];

        // Вытаскиваем чистые числовые ID видео из атрибутов id="video-card-XX"
        currentCards.forEach(card => {
            const rawId = card.id.replace('video-card-', '');
            idsOrder.push(rawId);
        });

        // Формируем строку формата "4,2,7,1"
        const orderString = idsOrder.join(',');

        const formData = new FormData();
        formData.append('action', 'save_custom_order');
        formData.append('order', orderString);

        // Отправляем тихий фоновый запрос в API без перезагрузки экрана
        fetch('api.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('Не удалось сохранить порядок: ' + data.error);
            }
        })
        .catch(err => console.error('Ошибка сети при сохранении порядка:', err));
    }
});
