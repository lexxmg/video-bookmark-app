document.addEventListener("DOMContentLoaded", function () {
    const gridContainer = document.querySelector('.grid');
    if (!gridContainer) return;

    // Сначала объявляем кнопку, чтобы JS знал её существование
    const customSortBtn = document.getElementById('btn-sort-custom');
    
        // --- УМНОЕ АДАПТИВНОЕ ОКНО ПОДСКАЗКИ НА JS ---
    const hintTrigger = document.getElementById('sort-hint-trigger');
    
    if (hintTrigger) {
        // Создаем элемент подсказки один раз в памяти
        const tooltip = document.createElement('div');
        tooltip.className = 'fixed bg-slate-900 text-white font-medium text-[11px] leading-relaxed p-3 rounded-xl shadow-xl transition-opacity duration-200 z-[60] pointer-events-none text-center w-64 box-border opacity-0 hidden';
        tooltip.innerHTML = `💡 <b>Как двигать карточки:</b> Кликните по видео правой кнопкой (или двумя пальцами) и нажмите «Переместить». Под курсором появится синяя рамка-силуэт. Ведите её в нужное место и кликните еще раз — видео перелетит туда!`;
        document.body.appendChild(tooltip);

        // Функция динамического расчета координат
        function positionTooltip() {
            tooltip.classList.remove('hidden');
            // Браузер дает нам точные координаты триггера (!) прямо сейчас
            const rect = hintTrigger.getBoundingClientRect();
            const tooltipWidth = 256; // w-64 равен 256px
            
            // Сначала рассчитываем идеальную позицию по центру над кнопкой
            let left = rect.left + (rect.width / 2) - (tooltipWidth / 2);
            let top = rect.top - tooltip.offsetHeight - 8; // 8px отступ сверху

            // Проверяем границы: упираемся ли в ЛЕВЫЙ край экрана?
            if (left < 10) {
                left = 10;
            }
            // Проверяем границы: упираемся ли в ПРАВЫЙ край экрана?
            if (left + tooltipWidth > window.innerWidth - 10) {
                left = window.innerWidth - tooltipWidth - 10;
            }
            // Проверяем границы: упираемся ли в ВЕРХНИЙ край экрана?
            if (top < 10) {
                // Если места сверху нет — разворачиваем и показываем ПОД кнопкой
                top = rect.bottom + 8;
            }

            // Применяем вычисленные координаты к стилям
            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${top}px`;
            
            // Включаем плавное проявление
            setTimeout(() => tooltip.style.opacity = '1', 10);
        }

        function hideTooltip() {
            tooltip.style.opacity = '0';
            setTimeout(() => tooltip.classList.add('hidden'), 200);
        }

        // Привязываем события наведения мыши или тача
        hintTrigger.addEventListener('mouseenter', positionTooltip);
        hintTrigger.addEventListener('mouseleave', hideTooltip);
        
        // ОСТАВЛЯЕМ НАВЕДЕНИЕ СТРОГО НА ЗНАЧОК "!"
        hintTrigger.addEventListener('mouseenter', positionTooltip);
        hintTrigger.addEventListener('mouseleave', hideTooltip);
    }

    // 1. СОЗДАЕМ МАКСИМАЛЬНО ЯРКОЕ И КОНТРАСТНОЕ МЕНЮ
    const ctxMenu = document.createElement('div');
    ctxMenu.id = 'custom-sort-menu';
    ctxMenu.className = 'hidden fixed bg-white border-2 border-gray-300 rounded-xl shadow-2xl p-1.5 z-50 min-w-[220px] flex flex-col box-border';
    ctxMenu.innerHTML = `
        <button id="activate-drag-btn" class="w-full text-left bg-gray-50 hover:bg-blue-600 text-gray-800 hover:text-white px-3 py-2.5 rounded-lg text-xs font-black border border-solid border-gray-200 hover:border-blue-600 cursor-pointer transition-all flex items-center gap-2">
            🚀 Переместить эту карточку
        </button>
    `;
    document.body.appendChild(ctxMenu);

    let menuActiveCard = null; 
    let draggingCard = null;   
    let placeholder = null;    
    let globalShield = null;   
    let cachedCardsData = []; 

    // --- ЛОВИМ ПРАВЫЙ КЛИК НА СЕТКЕ ---
    gridContainer.addEventListener('contextmenu', function (e) {
        if (!customSortBtn || !customSortBtn.classList.contains('bg-white')) return;

        const card = e.target.closest('[id^="video-card-"]');
        if (!card) return;

        e.preventDefault();
        menuActiveCard = card;

        ctxMenu.style.left = `${e.clientX}px`;
        ctxMenu.style.top = `${e.clientY}px`;
        ctxMenu.classList.remove('hidden');
    });

    document.addEventListener('click', function () {
        ctxMenu.classList.add('hidden');
    });

    // --- 2. ВКЛЮЧЕНИЕ РЕЖИМА ПЕРЕМЕЩЕНИЯ ---
    document.getElementById('activate-drag-btn').addEventListener('click', function (e) {
        e.stopPropagation();
        ctxMenu.classList.add('hidden');
        if (!menuActiveCard) return;

        draggingCard = menuActiveCard;

        placeholder = draggingCard.cloneNode(true);
        placeholder.style.opacity = '1'; 
        placeholder.style.background = '#eff6ff'; 
        placeholder.style.border = '2px dashed #2563eb'; 
        placeholder.style.boxShadow = 'none';
        placeholder.id = 'drag-placeholder';
        
        const innerContent = placeholder.children;
        for (let i = 0; i < innerContent.length; i++) {
            innerContent[i].style.opacity = '0';
        }

        draggingCard.after(placeholder);

        globalShield = document.createElement('div');
        globalShield.className = 'fixed inset-0 z-40 cursor-move bg-transparent';
        document.body.appendChild(globalShield);

        const allCards = gridContainer.querySelectorAll('[id^="video-card-"]');
        cachedCardsData = [];
        
        allCards.forEach(c => {
            if (c !== draggingCard && c.id !== 'drag-placeholder') {
                c.style.opacity = '0.5';
                c.style.transition = 'all 0.15s ease';
                
                const rect = c.getBoundingClientRect();
                cachedCardsData.push({
                    element: c,
                    left: rect.left + window.scrollX,
                    right: rect.right + window.scrollX,
                    top: rect.top + window.scrollY,
                    bottom: rect.bottom + window.scrollY,
                    midX: (rect.left + rect.right) / 2 + window.scrollX
                });
            }
        });

        draggingCard.style.border = '3px solid #2563eb';
        draggingCard.style.boxShadow = '0 25px 50px -12px rgba(37, 99, 235, 0.5)';
        draggingCard.style.transform = 'scale(1.05)';
        draggingCard.style.zIndex = '50';
        draggingCard.style.transition = 'transform 0.15s ease, box-shadow 0.15s ease';

        globalShield.addEventListener('mousemove', onMouseMove);
        globalShield.addEventListener('click', onShieldClick);
    });

    // --- 3. ТРЕКИНГ ---
    function onMouseMove(e) {
        if (!draggingCard || !placeholder) return;
        e.preventDefault();

        const mouseX = e.clientX + window.scrollX;
        const mouseY = e.clientY + window.scrollY;

        for (let i = 0; i < cachedCardsData.length; i++) {
            const data = cachedCardsData[i];

            if (mouseX >= data.left && mouseX <= data.right && mouseY >= data.top && mouseY <= data.bottom) {
                if (mouseX > data.midX) {
                    data.element.after(placeholder);
                } else {
                    data.element.before(placeholder);
                }
                break; 
            }
        }
    }

    // --- 4. КЛИК-ФИКСАЦИЯ НА ТАЧПАДЕ ---
    function onShieldClick(e) {
        e.preventDefault();
        e.stopPropagation();
        if (!draggingCard) return;

        if (placeholder && placeholder.parentNode) {
            placeholder.after(draggingCard);
            placeholder.remove();
        }

        const allCards = gridContainer.querySelectorAll('[id^="video-card-"]');
        allCards.forEach(c => {
            c.style.opacity = '';
            c.style.transition = '';
            c.style.border = '';
            c.style.boxShadow = '';
            c.style.transform = '';
            c.style.zIndex = '';
        });

        if (globalShield) {
            globalShield.removeEventListener('mousemove', onMouseMove);
            globalShield.removeEventListener('click', onShieldClick);
            globalShield.remove();
            globalShield = null;
        }

        saveNewOrder();

        draggingCard = null;
        placeholder = null;
        menuActiveCard = null;
        cachedCardsData = [];
    }

    // --- AJAX ---
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
