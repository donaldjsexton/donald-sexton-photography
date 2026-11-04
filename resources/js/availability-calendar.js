const root = document.querySelector('[data-availability-calendar]');

if (root) {
    const endpoint = root.dataset.endpoint || '';
    const minMonth = root.dataset.minMonth || '';
    const maxMonth = root.dataset.maxMonth || '';
    const timezone = root.dataset.timezone || 'America/New_York';
    const intro = root.querySelector('[data-availability-intro]');
    const panel = root.querySelector('[data-availability-panel]');
    const fallback = root.querySelector('[data-availability-fallback]');
    const loading = root.querySelector('[data-availability-loading]');
    const grid = root.querySelector('[data-availability-grid]');
    const label = root.querySelector('[data-availability-label]');
    const live = root.querySelector('[data-availability-live]');
    const previous = root.querySelector('[data-availability-prev]');
    const next = root.querySelector('[data-availability-next]');
    const dateInput = document.getElementById('event_date');

    const selectable = new Set(['open', 'limited', 'held']);
    const statusName = {
        open: 'Open',
        limited: 'Limited, partially booked',
        held: 'Held',
        booked: 'Booked',
        unavailable: 'Unavailable',
    };

    let currentMonth = root.dataset.month || minMonth;
    let selectedDate = dateInput instanceof HTMLInputElement ? dateInput.value : '';
    let focusDate = '';
    let requestSerial = 0;

    const track = (name, params) => {
        if (typeof window.gtag === 'function') {
            window.gtag('event', name, params);

            return;
        }

        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(['event', name, params]);
    };

    const shiftMonth = (month, delta) => {
        const [year, monthNumber] = month.split('-').map(Number);
        const date = new Date(Date.UTC(year, monthNumber - 1 + delta, 1));

        return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;
    };

    const addDays = (iso, days) => {
        const date = new Date(`${iso}T12:00:00Z`);
        date.setUTCDate(date.getUTCDate() + days);

        return date.toISOString().slice(0, 10);
    };

    const monthTitle = (month) => new Intl.DateTimeFormat('en-US', {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${month}-01T12:00:00Z`));

    const dayLabel = (iso) => new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${iso}T12:00:00Z`));

    const monthsAhead = (iso) => {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            year: 'numeric',
            month: 'numeric',
        }).formatToParts(new Date());
        const year = Number(parts.find((part) => part.type === 'year')?.value);
        const month = Number(parts.find((part) => part.type === 'month')?.value);
        const [selectedYear, selectedMonth] = iso.split('-').map(Number);

        return (selectedYear - year) * 12 + (selectedMonth - month);
    };

    const announce = (message) => {
        if (live) {
            live.textContent = message;
        }
    };

    const showFallback = () => {
        if (intro) {
            intro.hidden = true;
        }

        if (panel) {
            panel.hidden = true;
        }

        if (fallback) {
            fallback.hidden = false;
        }
    };

    const updateNav = () => {
        if (previous) {
            previous.disabled = currentMonth <= minMonth;
        }

        if (next) {
            next.disabled = currentMonth >= maxMonth;
        }
    };

    const paintSelection = () => {
        grid.querySelectorAll('[data-date]').forEach((button) => {
            const selected = button.dataset.date === selectedDate && selectable.has(button.dataset.status);
            button.classList.toggle('is-selected', selected);

            if (selectable.has(button.dataset.status)) {
                button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            }

            const cell = button.closest('[role="gridcell"]');

            if (cell) {
                cell.setAttribute('aria-selected', selected ? 'true' : 'false');
            }
        });
    };

    const paintFocus = () => {
        grid.querySelectorAll('[data-date]').forEach((button) => {
            button.tabIndex = button.dataset.date === focusDate ? 0 : -1;
        });
    };

    const render = (payload) => {
        const days = payload.days.filter((day) => (
            typeof day.date === 'string'
            && /^\d{4}-\d{2}-\d{2}$/.test(day.date)
            && typeof day.status === 'string'
        ));
        const first = days[0]?.date;

        if (!first) {
            throw new Error('empty');
        }

        if (!focusDate || focusDate.slice(0, 7) !== payload.month) {
            const selectedInMonth = days.find((day) => day.date === selectedDate);
            focusDate = selectedInMonth?.date || first;
        }

        if (label) {
            label.textContent = monthTitle(payload.month);
        }

        const leading = new Date(`${first}T12:00:00Z`).getUTCDay();
        const headers = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
            .map((name) => `<th scope="col"><span class="visually-hidden">${name}</span><span aria-hidden="true">${name.slice(0, 1)}</span></th>`)
            .join('');
        const cells = [];

        for (let index = 0; index < leading; index += 1) {
            cells.push('<td class="availability__pad"></td>');
        }

        days.forEach((day) => {
            const status = statusName[day.status] ? day.status : 'unavailable';
            const canSelect = selectable.has(status);
            const selected = canSelect && day.date === selectedDate;
            const number = Number(day.date.slice(8, 10));
            cells.push(
                `<td role="gridcell" aria-selected="${selected ? 'true' : 'false'}">` +
                `<button type="button" class="availability__day availability__day--${status}${selected ? ' is-selected' : ''}" ` +
                `data-date="${day.date}" data-status="${status}" ` +
                `aria-label="${dayLabel(day.date)}, ${statusName[status]}" ` +
                (canSelect ? `aria-pressed="${selected ? 'true' : 'false'}"` : 'aria-disabled="true"') +
                ` tabindex="-1"><span aria-hidden="true">${number}</span></button></td>`,
            );
        });

        while (cells.length % 7 !== 0) {
            cells.push('<td class="availability__pad"></td>');
        }

        const rows = [];

        for (let index = 0; index < cells.length; index += 7) {
            rows.push(`<tr>${cells.slice(index, index + 7).join('')}</tr>`);
        }

        grid.innerHTML = `<table class="availability__table" role="grid" aria-labelledby="availability-month-label"><thead><tr>${headers}</tr></thead><tbody>${rows.join('')}</tbody></table>`;
        paintFocus();
    };

    const load = async (month, focusAfter = null) => {
        if (!endpoint || month < minMonth || month > maxMonth) {
            return;
        }

        const serial = requestSerial + 1;
        requestSerial = serial;

        if (panel) {
            panel.hidden = false;
            panel.setAttribute('aria-busy', 'true');
        }

        if (loading) {
            loading.hidden = false;
        }

        try {
            const response = await fetch(`${endpoint}?month=${encodeURIComponent(month)}`, {
                headers: { Accept: 'application/json' },
                signal: AbortSignal.timeout(8000),
            });

            if (!response.ok) {
                throw new Error('unavailable');
            }

            const payload = await response.json();

            if (serial !== requestSerial) {
                return;
            }

            if (!payload || payload.month !== month || !Array.isArray(payload.days)) {
                throw new Error('unavailable');
            }

            currentMonth = month;
            render(payload);

            if (loading) {
                loading.hidden = true;
            }

            if (focusAfter && grid.querySelector(`[data-date="${focusAfter}"]`)) {
                focusDate = focusAfter;
                paintFocus();
                grid.querySelector(`[data-date="${focusAfter}"]`)?.focus();
            }

            updateNav();
            announce(`${monthTitle(month)}.`);
        } catch {
            if (serial !== requestSerial) {
                return;
            }

            showFallback();
        } finally {
            if (serial === requestSerial && panel) {
                panel.removeAttribute('aria-busy');
            }
        }
    };

    const selectDate = (date, status) => {
        selectedDate = date;

        if (dateInput instanceof HTMLInputElement) {
            dateInput.value = date;
        }

        paintSelection();
        announce(`${dayLabel(date)} selected. ${statusName[status]}.`);
        track('availability_date_select', {
            date_status: status,
            months_ahead: monthsAhead(date),
        });
    };

    grid.addEventListener('click', (event) => {
        const button = event.target.closest('[data-date]');

        if (!button || !grid.contains(button) || !selectable.has(button.dataset.status)) {
            return;
        }

        focusDate = button.dataset.date;
        paintFocus();
        selectDate(button.dataset.date, button.dataset.status);
    });

    grid.addEventListener('keydown', (event) => {
        const button = event.target.closest('[data-date]');

        if (!button) {
            return;
        }

        const moves = {
            ArrowLeft: -1,
            ArrowRight: 1,
            ArrowUp: -7,
            ArrowDown: 7,
        };

        if (event.key in moves) {
            event.preventDefault();
            const target = addDays(button.dataset.date, moves[event.key]);
            const month = target.slice(0, 7);

            if (month < minMonth || month > maxMonth) {
                return;
            }

            if (month !== currentMonth) {
                load(month, target);

                return;
            }

            focusDate = target;
            paintFocus();
            grid.querySelector(`[data-date="${target}"]`)?.focus();

            return;
        }

        if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            const weekday = new Date(`${button.dataset.date}T12:00:00Z`).getUTCDay();
            let target = addDays(button.dataset.date, event.key === 'Home' ? -weekday : 6 - weekday);

            if (target.slice(0, 7) !== currentMonth) {
                const [year, monthNumber] = currentMonth.split('-').map(Number);
                const lastDay = new Date(Date.UTC(year, monthNumber, 0)).getUTCDate();
                target = event.key === 'Home'
                    ? `${currentMonth}-01`
                    : `${currentMonth}-${String(lastDay).padStart(2, '0')}`;
            }

            focusDate = target;
            paintFocus();
            grid.querySelector(`[data-date="${target}"]`)?.focus();

            return;
        }

        if (event.key === 'PageUp' || event.key === 'PageDown') {
            event.preventDefault();
            const delta = event.key === 'PageUp' ? -1 : 1;
            const month = shiftMonth(currentMonth, delta);

            if (month < minMonth || month > maxMonth) {
                return;
            }

            const day = Number(button.dataset.date.slice(8, 10));
            const [year, monthNumber] = month.split('-').map(Number);
            const lastDay = new Date(Date.UTC(year, monthNumber, 0)).getUTCDate();
            const clamped = String(Math.min(day, lastDay)).padStart(2, '0');
            load(month, `${month}-${clamped}`);
        }
    });

    previous?.addEventListener('click', () => {
        load(shiftMonth(currentMonth, -1));
    });

    next?.addEventListener('click', () => {
        load(shiftMonth(currentMonth, 1));
    });

    dateInput?.addEventListener('change', () => {
        selectedDate = dateInput.value;
        paintSelection();
    });

    load(currentMonth);
}
