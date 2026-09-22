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

    const telegramProbeButton = document.querySelector('[data-telegram-probe]');
    const telegramProbeStatus = document.querySelector('[data-telegram-probe-status]');
    const telegramProbeStatusIcon = document.querySelector('[data-telegram-probe-status-icon]');
    const telegramProbeStatusText = document.querySelector('[data-telegram-probe-status-text]');
    const telegramChatResults = document.querySelector('[data-telegram-chat-results]');
    const setupAdminChat = document.querySelector('[data-setup-admin-chat]');
    const setupGroupChat = document.querySelector('[data-setup-group-chat]');
    const setupPrivateList = document.querySelector('[data-setup-private-list]');
    const setupGroupList = document.querySelector('[data-setup-group-list]');

    const chooseDetectedChat = (chatId, chatType) => {
        if (chatType === 'private' && setupAdminChat) {
            setupAdminChat.value = chatId;
            setupAdminChat.focus();
            return;
        }

        if (['group', 'supergroup', 'channel'].includes(chatType) && setupGroupChat) {
            setupGroupChat.value = chatId;
            setupGroupChat.focus();
        }
    };

    const bindDetectedChat = (button) => {
        button.addEventListener('click', () => {
            chooseDetectedChat(button.dataset.chatId || '', button.dataset.chatType || '');
        });
    };

    document.querySelectorAll('[data-detected-chat]').forEach(bindDetectedChat);

    const renderDetectedChats = (chats) => {
        if (!telegramChatResults) {
            return;
        }

        telegramChatResults.replaceChildren();

        chats.forEach((chat) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'telegram-detected-chat';
            button.dataset.detectedChat = '';
            button.dataset.chatId = String(chat.id || '');
            button.dataset.chatType = String(chat.type || '');

            const icon = document.createElement('span');
            icon.className = 'telegram-detected-icon';
            const iconGlyph = document.createElement('i');
            iconGlyph.className = 'bi ' + (chat.type === 'private' ? 'bi-person' : 'bi-people');
            icon.appendChild(iconGlyph);

            const main = document.createElement('span');
            main.className = 'telegram-detected-main';
            const title = document.createElement('strong');
            title.textContent = String(chat.title || chat.id || '');
            const meta = document.createElement('small');
            meta.textContent = String(chat.type || 'unknown') + ' · ' + String(chat.id || '');
            main.append(title, meta);

            const action = document.createElement('span');
            action.className = 'telegram-detected-action';
            action.textContent = '선택';

            button.append(icon, main, action);
            bindDetectedChat(button);
            telegramChatResults.appendChild(button);
        });

        telegramChatResults.hidden = chats.length === 0;
    };

    const rebuildChatDatalists = (chats) => {
        if (setupPrivateList) {
            setupPrivateList.replaceChildren();
        }
        if (setupGroupList) {
            setupGroupList.replaceChildren();
        }

        const privateChats = [];
        const groupChats = [];

        chats.forEach((chat) => {
            const id = String(chat.id || '');
            const type = String(chat.type || '');
            const title = String(chat.title || id);

            if (type === 'private') {
                privateChats.push(chat);
                if (setupPrivateList) {
                    const option = document.createElement('option');
                    option.value = id;
                    option.label = title;
                    setupPrivateList.appendChild(option);
                }
            } else if (['group', 'supergroup', 'channel'].includes(type)) {
                groupChats.push(chat);
                if (setupGroupList) {
                    const option = document.createElement('option');
                    option.value = id;
                    option.label = title;
                    setupGroupList.appendChild(option);
                }
            }
        });

        if (privateChats.length === 1 && setupAdminChat && setupAdminChat.value.trim() === '') {
            setupAdminChat.value = String(privateChats[0].id || '');
        }
        if (groupChats.length === 1 && setupGroupChat && setupGroupChat.value.trim() === '') {
            setupGroupChat.value = String(groupChats[0].id || '');
        }
    };

    const setTelegramProbeStatus = (message, isError = false, isLoading = false) => {
        if (!telegramProbeStatus || !telegramProbeStatusText || !telegramProbeStatusIcon) {
            return;
        }

        telegramProbeStatus.hidden = false;
        telegramProbeStatus.classList.toggle('error', isError);
        telegramProbeStatus.classList.toggle('loading', isLoading);
        telegramProbeStatusText.textContent = message;
        telegramProbeStatusIcon.className = 'bi ' + (
            isLoading ? 'bi-arrow-repeat' : (isError ? 'bi-exclamation-triangle' : 'bi-check-circle')
        );
    };

    if (telegramProbeButton) {
        telegramProbeButton.addEventListener('click', async () => {
            const probeUrl = telegramProbeButton.dataset.probeUrl || '/setup/telegram-probe';
            const originalHtml = telegramProbeButton.innerHTML;

            telegramProbeButton.disabled = true;
            telegramProbeButton.innerHTML = '<i class="bi bi-arrow-repeat"></i><span>확인 중...</span>';
            setTelegramProbeStatus('Telegram 최근 채팅을 확인하고 있습니다.', false, true);

            try {
                const response = await fetch(probeUrl, {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                const payload = await response.json();
                const chats = Array.isArray(payload.chats) ? payload.chats : [];

                renderDetectedChats(chats);
                rebuildChatDatalists(chats);
                setTelegramProbeStatus(
                    String(payload.message || (payload.ok ? '확인을 완료했습니다.' : '확인하지 못했습니다.')),
                    payload.ok !== true,
                    false,
                );
            } catch (error) {
                renderDetectedChats([]);
                setTelegramProbeStatus(
                    '최근 채팅 확인 요청에 실패했습니다. 네트워크 상태와 서버 로그를 확인해 주세요.',
                    true,
                    false,
                );
            } finally {
                telegramProbeButton.disabled = false;
                telegramProbeButton.innerHTML = originalHtml;
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
