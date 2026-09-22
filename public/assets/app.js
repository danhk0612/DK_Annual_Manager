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

    const detailDialog = document.querySelector('[data-request-detail-dialog]');
    if (detailDialog instanceof HTMLDialogElement) {
        const setText = (selector, value, fallback = '-') => {
            const element = detailDialog.querySelector(selector);
            if (element) {
                element.textContent = value && value.trim() !== '' ? value : fallback;
            }
        };

        document.querySelectorAll('[data-open-request-detail]').forEach((button) => {
            button.addEventListener('click', () => {
                setText('[data-detail-id]', '#' + (button.dataset.requestId || ''));
                setText('[data-detail-status]', button.dataset.requestStatus || '');
                setText('[data-detail-user]', [
                    button.dataset.requestUser || '',
                    button.dataset.requestDepartment || '',
                ].filter(Boolean).join(' · '));
                setText('[data-detail-type]', button.dataset.requestType || '');
                setText('[data-detail-period]', button.dataset.requestPeriod || '');
                setText('[data-detail-amount]', button.dataset.requestAmount || '');
                setText('[data-detail-reason]', button.dataset.requestReason || '', '입력 없음');
                setText('[data-detail-review-note]', button.dataset.requestReviewNote || '', '입력 없음');
                setText('[data-detail-created]', button.dataset.requestCreated || '');
                detailDialog.showModal();
            });
        });

        detailDialog.querySelectorAll('[data-close-detail-dialog]').forEach((button) => {
            button.addEventListener('click', () => detailDialog.close());
        });

        detailDialog.addEventListener('click', (event) => {
            if (event.target === detailDialog) {
                detailDialog.close();
            }
        });
    }

    document.querySelectorAll('[data-copy-text]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.dataset.copyText || '';
            if (value === '') {
                return;
            }

            try {
                await navigator.clipboard.writeText(value);
                const original = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check2"></i><span>복사됨</span>';
                setTimeout(() => {
                    button.innerHTML = original;
                }, 1400);
            } catch (error) {
                window.prompt('아래 값을 복사하세요.', value);
            }
        });
    });

    document.querySelectorAll('input[type="color"]').forEach((input) => {
        const row = input.closest('.color-input-row');
        const code = row ? row.querySelector('code') : null;
        if (code) {
            input.addEventListener('input', () => {
                code.textContent = input.value;
            });
        }
    });
});
