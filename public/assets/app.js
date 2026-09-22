document.addEventListener('DOMContentLoaded', () => {
    const leaveForms = Array.from(document.querySelectorAll('[data-leave-form]'));

    const initializeLeaveForm = (form) => {
        const typeSelect = form.querySelector('[data-leave-type]');
        const startDate = form.querySelector('[data-start-date]');
        const endDate = form.querySelector('[data-end-date]');
        const hiddenEndDate = form.querySelector('[data-end-date-hidden]');
        const halfDaySelect = form.querySelector('[data-half-day-select]');
        const adminDateExceptionWrap = form.querySelector('[data-admin-date-exception-wrap]');
        const adminDateException = form.querySelector('[data-admin-date-exception]');

        if (!typeSelect || !startDate || !endDate || !hiddenEndDate || !halfDaySelect) {
            return;
        }

        const selectedOption = () => typeSelect.options[typeSelect.selectedIndex] || null;

        const selectedCode = () => {
            const option = selectedOption();
            return option ? option.dataset.leaveCode || '' : '';
        };

        const selectedDeductsAnnual = () => {
            const option = selectedOption();
            return option ? option.dataset.deductsAnnual === '1' : false;
        };

        const syncAdminDateException = () => {
            if (!adminDateExceptionWrap || !adminDateException) {
                return;
            }

            const singleDate = startDate.value !== '' && startDate.value === endDate.value;
            const available = !selectedDeductsAnnual() && singleDate && selectedCode() !== 'H';
            adminDateExceptionWrap.hidden = !available;
            adminDateException.disabled = !available;

            if (!available) {
                adminDateException.checked = false;
            }
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

            syncAdminDateException();
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

        const pendingCancelForm = detailDialog.querySelector('[data-detail-pending-cancel]');
        const approvedCancelForm = detailDialog.querySelector('[data-detail-approved-cancel]');
        const pendingCancelId = detailDialog.querySelector('[data-detail-pending-cancel-id]');
        const approvedCancelId = detailDialog.querySelector('[data-detail-approved-cancel-id]');
        const cancellationWrap = detailDialog.querySelector('[data-detail-cancellation-wrap]');

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
                setText('[data-detail-deduction]', button.dataset.requestDeduction || '');
                setText('[data-detail-reason]', button.dataset.requestReason || '', '입력 없음');
                setText('[data-detail-review-note]', button.dataset.requestReviewNote || '', '입력 없음');

                const cancellationSource = button.dataset.requestCancellationSource || '';
                const cancellationSourceLabel = cancellationSource === 'user'
                    ? '사용자 취소'
                    : (cancellationSource === 'admin' ? '관리자 취소' : '');
                const cancellationParts = [
                    cancellationSourceLabel,
                    button.dataset.requestCancelledAt || '',
                    button.dataset.requestCancellationNote ? '사유: ' + button.dataset.requestCancellationNote : '',
                ].filter(Boolean);
                if (cancellationWrap) {
                    cancellationWrap.hidden = cancellationParts.length === 0;
                }
                setText('[data-detail-cancellation]', cancellationParts.join(' · '), '입력 없음');

                const owned = button.dataset.requestOwned === '1';
                const statusCode = button.dataset.requestStatusCode || '';
                if (pendingCancelForm) {
                    pendingCancelForm.hidden = !(owned && statusCode === 'pending');
                }
                if (approvedCancelForm) {
                    approvedCancelForm.hidden = !(owned && statusCode === 'approved');
                }
                if (pendingCancelId) {
                    pendingCancelId.value = button.dataset.requestId || '';
                }
                if (approvedCancelId) {
                    approvedCancelId.value = button.dataset.requestId || '';
                }

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

        const focusRequest = new URLSearchParams(window.location.search).get('focus_request');
        const focusSelector = focusRequest && /^\d+$/.test(focusRequest)
            ? '#request-' + focusRequest
            : (window.location.hash.startsWith('#request-') ? window.location.hash : '');
        const focusTarget = focusSelector !== '' ? document.querySelector(focusSelector) : null;
        if (focusTarget) {
            focusTarget.scrollIntoView({ block: 'center' });
            if (focusTarget.matches('[data-open-request-detail]')) {
                focusTarget.click();
            }
        }
    }

    const bindSimpleDialog = (dialogSelector, openSelector, closeSelector) => {
        const targetDialog = document.querySelector(dialogSelector);
        if (!(targetDialog instanceof HTMLDialogElement)) {
            return null;
        }

        document.querySelectorAll(openSelector).forEach((button) => {
            button.addEventListener('click', () => targetDialog.showModal());
        });
        targetDialog.querySelectorAll(closeSelector).forEach((button) => {
            button.addEventListener('click', () => targetDialog.close());
        });
        targetDialog.addEventListener('click', (event) => {
            if (event.target === targetDialog) {
                targetDialog.close();
            }
        });

        return targetDialog;
    };

    const userDialog = document.querySelector('[data-user-dialog]');
    if (userDialog instanceof HTMLDialogElement) {
        const form = userDialog.querySelector('[data-user-form]');
        const title = userDialog.querySelector('[data-user-dialog-title] span');
        const submitLabel = userDialog.querySelector('[data-user-submit-label]');
        const warning = userDialog.querySelector('[data-user-sole-admin-warning]');
        const fields = {
            id: userDialog.querySelector('[data-user-id]'),
            name: userDialog.querySelector('[data-user-name]'),
            department: userDialog.querySelector('[data-user-department]'),
            position: userDialog.querySelector('[data-user-position]'),
            hireDate: userDialog.querySelector('[data-user-hire-date]'),
            endDate: userDialog.querySelector('[data-user-end-date]'),
            telegramId: userDialog.querySelector('[data-user-telegram-id]'),
            role: userDialog.querySelector('[data-user-role]'),
            status: userDialog.querySelector('[data-user-status]'),
        };

        const option = (select, value) => select ? select.querySelector('option[value="' + value + '"]') : null;

        document.querySelectorAll('[data-open-user-dialog]').forEach((button) => {
            button.addEventListener('click', () => {
                const editing = button.dataset.userMode === 'edit';
                const soleAdmin = button.dataset.userSoleAdmin === '1';

                if (form) {
                    form.reset();
                }
                if (fields.id) fields.id.value = editing ? (button.dataset.userId || '') : '';
                if (fields.name) fields.name.value = editing ? (button.dataset.userName || '') : '';
                if (fields.department) fields.department.value = editing ? (button.dataset.userDepartment || '') : '';
                if (fields.position) fields.position.value = editing ? (button.dataset.userPosition || '') : '';
                if (fields.hireDate) fields.hireDate.value = editing ? (button.dataset.userHireDate || '') : '';
                if (fields.endDate) fields.endDate.value = editing ? (button.dataset.userEndDate || '') : '';
                if (fields.telegramId) fields.telegramId.value = editing ? (button.dataset.userTelegramId || '') : '';
                if (fields.role) fields.role.value = editing ? (button.dataset.userRole || 'user') : 'user';
                if (fields.status) fields.status.value = editing ? (button.dataset.userStatus || 'active') : 'active';

                const userOption = option(fields.role, 'user');
                const pendingOption = option(fields.status, 'pending');
                const inactiveOption = option(fields.status, 'inactive');
                if (userOption) userOption.disabled = soleAdmin;
                if (pendingOption) pendingOption.disabled = soleAdmin;
                if (inactiveOption) inactiveOption.disabled = soleAdmin;

                if (warning) warning.hidden = !soleAdmin;
                if (title) title.textContent = editing ? '직원 수정' : '직원 추가';
                if (submitLabel) submitLabel.textContent = editing ? '저장' : '추가';

                userDialog.showModal();
                if (fields.name) fields.name.focus();
            });
        });

        userDialog.querySelectorAll('[data-close-user-dialog]').forEach((button) => {
            button.addEventListener('click', () => userDialog.close());
        });
        userDialog.addEventListener('click', (event) => {
            if (event.target === userDialog) {
                userDialog.close();
            }
        });
    }

    const annualLeaveDialog = document.querySelector('[data-annual-leave-dialog]');
    if (annualLeaveDialog instanceof HTMLDialogElement) {
        annualLeaveDialog.querySelectorAll('[data-close-annual-leave-dialog]').forEach((button) => {
            button.addEventListener('click', () => annualLeaveDialog.close());
        });
        annualLeaveDialog.addEventListener('click', (event) => {
            if (event.target === annualLeaveDialog) {
                annualLeaveDialog.close();
            }
        });
        if (annualLeaveDialog.dataset.autoOpen === '1') {
            annualLeaveDialog.showModal();
        }
    }

    bindSimpleDialog(
        '[data-holiday-sync-dialog]',
        '[data-open-holiday-sync-dialog]',
        '[data-close-holiday-sync-dialog]'
    );
    bindSimpleDialog(
        '[data-holiday-add-dialog]',
        '[data-open-holiday-add-dialog]',
        '[data-close-holiday-add-dialog]'
    );

    document.querySelectorAll('[data-confirm-message]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.dataset.confirmMessage || '계속 진행하시겠습니까?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const completedSetupSteps = Array.from(document.querySelectorAll('.setup-step.complete'));

    completedSetupSteps.forEach((step) => {
        const main = step.querySelector('.setup-step-main');
        const header = main ? main.querySelector(':scope > .section-head') : null;
        if (!main || !header) {
            return;
        }

        const setExpanded = (expanded) => {
            step.classList.toggle('is-collapsed', !expanded);
            header.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            header.setAttribute('title', expanded ? '완료 단계 접기' : '완료 단계 펼치기');
        };

        header.classList.add('setup-step-collapse-trigger');
        header.setAttribute('role', 'button');
        header.setAttribute('tabindex', '0');
        setExpanded(false);

        header.addEventListener('click', (event) => {
            if (event.target.closest('a, button, input, select, textarea, label')) {
                return;
            }
            setExpanded(step.classList.contains('is-collapsed'));
        });

        header.addEventListener('keydown', (event) => {
            if (event.target !== header || !['Enter', ' '].includes(event.key)) {
                return;
            }

            event.preventDefault();
            setExpanded(step.classList.contains('is-collapsed'));
        });
    });

    const telegramProbeButton = document.querySelector('[data-telegram-probe]');
    const telegramProbeStatus = document.querySelector('[data-telegram-probe-status]');
    const telegramProbeStatusIcon = document.querySelector('[data-telegram-probe-status-icon]');
    const telegramProbeStatusText = document.querySelector('[data-telegram-probe-status-text]');
    const telegramChatResults = document.querySelector('[data-telegram-chat-results]');
    const setupAdminChat = document.querySelector('[data-setup-admin-chat]');
    const setupGroupChat = document.querySelector('[data-setup-group-chat]');
    const setupPrivateList = document.querySelector('[data-setup-private-list]');
    const setupGroupList = document.querySelector('[data-setup-group-list]');

    const syncDetectedSelection = () => {
        document.querySelectorAll('[data-detected-chat]').forEach((button) => {
            const id = button.dataset.chatId || '';
            const type = button.dataset.chatType || '';
            const selected = type === 'private'
                ? Boolean(setupAdminChat && setupAdminChat.value === id)
                : Boolean(setupGroupChat && setupGroupChat.value === id);

            button.classList.toggle('selected', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');

            const action = button.querySelector('.telegram-detected-action');
            if (action) {
                action.textContent = selected ? '적용됨' : '선택';
            }
        });

        if (setupAdminChat) {
            setupAdminChat.classList.toggle('selection-applied', setupAdminChat.value.trim() !== '');
        }
        if (setupGroupChat) {
            setupGroupChat.classList.toggle('selection-applied', setupGroupChat.value.trim() !== '');
        }
    };

    const chooseDetectedChat = (chatId, chatType) => {
        let target = null;
        let label = '';

        if (chatType === 'private' && setupAdminChat) {
            target = setupAdminChat;
            label = '최초 관리자 Telegram User ID';
        } else if (['group', 'supergroup'].includes(chatType) && setupGroupChat) {
            target = setupGroupChat;
            label = '회사 공용 Telegram 그룹 Chat ID';
        }

        if (!target) {
            return;
        }

        target.value = chatId;
        target.dispatchEvent(new Event('input', { bubbles: true }));
        target.dispatchEvent(new Event('change', { bubbles: true }));
        syncDetectedSelection();
        setTelegramProbeStatus(label + '에 ' + chatId + ' 값을 적용했습니다.');
        target.focus();
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
        syncDetectedSelection();
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
            } else if (['group', 'supergroup'].includes(type)) {
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

        syncDetectedSelection();
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

    if (setupAdminChat) {
        setupAdminChat.addEventListener('input', syncDetectedSelection);
    }
    if (setupGroupChat) {
        setupGroupChat.addEventListener('input', syncDetectedSelection);
    }
    syncDetectedSelection();

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

    document.querySelectorAll('[data-confirm-reset]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const input = form.querySelector('input[name="confirmation"]');
            if (!input || input.value.trim() !== 'RESET') {
                event.preventDefault();
                window.alert('확인 입력란에 RESET을 정확히 입력해 주세요.');
                if (input) {
                    input.focus();
                }
                return;
            }

            if (!window.confirm('모든 휴가관리 데이터를 삭제하고 재설치를 시작하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) {
                event.preventDefault();
            }
        });
    });

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
