document.addEventListener("DOMContentLoaded", function () {
    const gridContainer = document.querySelector('.grid');
    if (!gridContainer) return;

    // Проверяем, активен ли сейчас режим "Вручную"
    const customSortBtn = document.getElementById('btn-sort-custom');
    
    // Создаем элемент кастомного контекстного меню
    const ctxMenu = document.createElement('div');
    ctxMenu.id = 'custom-sort-menu';
    // Стилизуем меню строго по канонам Tailwind v4 (мягкие тени, скругления, фокус)
    ctxMenu.className = 'hidden fixed bg-white border border-gray-200 rounded-xl shadow-xl p-1.5 z-50 min-w-[180px] flex flex-col gap-0.5 box-border';
    ctxMenu.innerHTML = `
        <button data-action="top" class="w-full text-left bg-transparent hover:bg-gray-100 text-gray-700 px-3 py-2 rounded-lg text-xs font-bold border-none cursor-pointer transition flex items-center gap-2">🔝 В самое начало</button>
        <button data-action="left" class="w-full text-left bg-transparent hover:bg-gray-100 text-gray-700 px-3 py-2 rounded-lg text-xs font-bold border-none cursor-pointer transition flex items-center gap-2">⬅️ Сдвинуть влево</button>
        <button data-action="right" class="w-full text-left bg-transparent hover:bg-gray-100 text-gray-700 px-3 py-2 rounded-lg text-xs font-bold border-none cursor-pointer transition flex items-center gap-2">➡️ Сдвинуть вправо</button>
        <button data-action="bottom" class="w-full text-left bg-transparent hover:bg-gray-100 text-gray-700 px-3 py-2 rounded-lg text-xs font-bold border-none cursor-pointer transition flex items-center gap-2">🔚 В самый конец</button>
    `;
    document.body.appendChild(ctxMenu);

    let activeCard = null; // Карточка, для которой открыли меню

    // --- ДЕЛЕГИРОВАНИЕ: ЛОВИМ ПРАВЫЙ КЛИК НА СЕТКЕ ---
    gridContainer.addEventListener('contextmenu', function (e) {
        // Проверяем, включен ли режим "Вручную" (кнопка должна иметь белый фон активной вкладки)
        if (!customSortBtn || !customSortBtn.classList.contains('bg-white')) {
            return; // Если активен другой режим — работает стандартное меню браузера
        }

        // Находим карточку, по которой кликнули
        const card = e.target.closest('[id^="video-card-"]');
        if (!card) return;

        // Блокируем стандартное системное меню браузера
        e.preventDefault();
        activeCard = card;

        // Показываем наше кастомное меню ровно в месте клика курсора
        ctxMenu.style.left = `${e.clientX}px`;
        ctxMenu.style.top = `${e.clientY}px`;
        ctxMenu.classList.remove('hidden');
    });

    // --- ЗАКРЫТИЕ МЕНЮ ПРИ КЛИКЕ В ЛЮБОЕ ДРУГОЕ МЕСТО ---
    document.addEventListener('click', function (e) {
        if (!ctxMenu.classList.contains('hidden')) {
            ctxMenu.classList.add('hidden');
        }
    });

    // --- ОБРАБОТКА ВЫБОРА В МЕНЮ ---
    ctxMenu.addEventListener('click', function (e) {
        const button = e.target.closest('button');
        if (!button || !activeCard) return;

        const action = button.getAttribute('data-action');
        const allCards = Array.from(gridContainer.querySelectorAll('[id^="video-card-"]'));
        const index = allCards.indexOf(activeCard);

        // Применяем выбранное действие к DOM-узлам плиток
        switch (action) {
            case 'top':
                // Перемещаем в начало контейнера перед первой карточкой
                gridContainer.insertBefore(activeCard, gridContainer.firstChild);
                break;

            case 'left':
                // Сдвигаем влево (вставляем перед предыдущей карточкой)
                if (index > 0) {
                    allCards[index - 1].before(activeCard);
                }
                break;

            case 'right':
                // Сдвигаем вправо (вставляем после следующей карточки)
                if (index < allCards.length - 1) {
                    allCards[index + 1].after(activeCard);
                }
                break;

            case 'bottom':
                // Перемещаем в самый конец контейнера
                gridContainer.appendChild(activeCard);
                break;
        }

        // Сохраняем получившийся порядок в базу данных
        saveNewOrder();
    });

    // --- AJAX-ОТПРАВКА НОВОГО ПОРЯДКА В БД ---
    function saveNewOrder() {
        const currentCards = gridContainer.querySelectorAll('[id^="video-card-"]');
        const idsOrder = [];

        currentCards.forEach(card => {
            idsOrder.push(card.id.replace('video-card-', ''));
        });

        const formData = new FormData();
        formData.append('action', 'save_custom_order');
        formData.append('order', idsOrder.join(','));

        fetch('api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('Не удалось сохранить порядок: ' + data.error);
            }
        })
        .catch(err => console.error('Ошибка сети при сохранении порядка:', err));
    }
});
