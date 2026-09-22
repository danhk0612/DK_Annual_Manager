document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-leave-form]');
    if (!form) {
        return;
    }

    const typeSelect = form.querySelector('[data-leave-type]');
    const startDate = form.querySelector('[data-start-date]');
    const endDate = form.querySelector('[data-end-date]');
    const halfDayField = form.querySelector('[data-half-day-field]');
    const halfDaySelect = form.querySelector('[data-half-day-select]');

    if (!typeSelect || !startDate || !endDate || !halfDayField || !halfDaySelect) {
        return;
    }

    const selectedCode = () => {
        const option = typeSelect.options[typeSelect.selectedIndex];
        return option ? option.dataset.leaveCode || '' : '';
    };

    const syncDates = () => {
        if (startDate.value !== '') {
            endDate.min = startDate.value;
            if (endDate.value === '' || endDate.value < startDate.value) {
                endDate.value = startDate.value;
            }
        }

        if (selectedCode() === 'H') {
            endDate.value = startDate.value;
            endDate.readOnly = true;
        } else {
            endDate.readOnly = false;
        }
    };

    const syncHalfDay = () => {
        const isHalfDay = selectedCode() === 'H';
        halfDayField.hidden = !isHalfDay;
        halfDaySelect.required = isHalfDay;
        syncDates();
    };

    typeSelect.addEventListener('change', syncHalfDay);
    startDate.addEventListener('change', syncDates);
    endDate.addEventListener('change', syncDates);

    syncHalfDay();
});
