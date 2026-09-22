document.addEventListener('DOMContentLoaded', () => {
    const leaveForms = Array.from(document.querySelectorAll('[data-leave-form]'));

    const initializeLeaveForm = (form) => {
        const typeSelect = form.querySelector('[data-leave-type]');
        const startDate = form.querySelector('[data-start-date]');
        const endDate = form.querySelector('[data-end-date]');
        const hiddenEndDate = form.querySelector('[data-end-date-hidden]');
        const halfDaySelect = form.querySelector('[data-half-day-select]');

        if (!typeSelect || !startDate || !endDate || !hiddenEndDate || !halfDaySelect) {
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

            const isHalfDay = selectedCode() === 'H';
            if (isHalfDay) {
                endDate.value = startDate.value;
                endDate.disabled = true;
                hiddenEndDate.value = startDate.value;
                hiddenEndDate.disabled = false;
            } else {
                endDate.disabled = false;
                hiddenEndDate.disabled = true;
            }
        };

        const syncTypeControls = () => {
            const isHalfDay = selectedCode() === 'H';
            halfDaySelect.disabled = !isHalfDay;
            halfDaySelect.required = isHalfDay;
            syncDates();
        };

        typeSelect.addEventListener('change', syncTypeControls);
        startDate.addEventListener('change', syncDates);
        endDate.addEventListener('change', syncDates);

        form.addEventListener('set-leave-date', (event) => {
            const date = event.detail && event.detail.date ? event.detail.date : '';
            if (date !== '') {
                startDate.value = date;
                if (selectedCode() !== 'H') {
                    endDate.value = date;
                }
                syncDates();
            }
        });

        syncTypeControls();
    };

    leaveForms.forEach(initializeLeaveForm);

    const dialog = document.querySelector('[data-leave-dialog]');
    if (dialog instanceof HTMLDialogElement) {
        const dialogForm = dialog.querySelector('[data-leave-form]');

        document.querySelectorAll('[data-open-leave-dialog]').forEach((button) => {
            button.addEventListener('click', () => {
                const date = button.dataset.leaveDate || '';
                if (dialogForm && date !== '') {
                    dialogForm.dispatchEvent(new CustomEvent('set-leave-date', {
                        detail: { date },
                    }));
                }
                dialog.showModal();
            });
        });

        dialog.querySelectorAll('[data-close-dialog]').forEach((button) => {
            button.addEventListener('click', () => dialog.close());
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        const params = new URLSearchParams(window.location.search);
        if (params.get('request') === '1') {
            dialog.showModal();
        }
    }
});
