/**
 * STRYKE - VietQR Payment Form Interaction
 * Tuân thủ nghiêm ngặt Content Security Policy (script-src 'self')
 * Không sử dụng inline event handler, hỗ trợ đầy đủ responsive.
 */
(() => {
    'use strict';

    // 1. SAO CHÉP VĂN BẢN VÀO CLIPBOARD
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-copy-text]');
        if (!button) return;

        const text = button.getAttribute('data-copy-text');
        if (!text) return;

        const copyAction = navigator.clipboard && window.isSecureContext
            ? navigator.clipboard.writeText(text)
            : new Promise((resolve, reject) => {
                const tempInput = document.createElement('textarea');
                tempInput.value = text;
                tempInput.style.position = 'fixed';
                tempInput.style.left = '-9999px';
                tempInput.style.top = '-9999px';
                document.body.appendChild(tempInput);
                tempInput.focus();
                tempInput.select();
                try {
                    const ok = document.execCommand('copy');
                    document.body.removeChild(tempInput);
                    if (ok) resolve(); else reject(new Error('Copy failed'));
                } catch (err) {
                    document.body.removeChild(tempInput);
                    reject(err);
                }
            });

        copyAction.then(() => {
            const originalTitle = button.getAttribute('title') || '';
            const originalSvg = button.innerHTML;

            button.classList.add('copied');
            showToast('Đã sao chép: ' + text);

            setTimeout(() => {
                button.classList.remove('copied');
                if (originalTitle) button.setAttribute('title', originalTitle);
            }, 2000);
        }).catch(() => {
            showToast('Không thể tự động sao chép. Vui lòng chọn và chép thủ công.');
        });
    });

    // 2. TẢI ẢNH CHUYỂN KHOẢN (FILE UPLOAD & DRAG DROP PREVIEW)
    const dropzone = document.querySelector('[data-upload-dropzone]');
    const fileInput = document.querySelector('#stryke-proof-input');
    const placeholder = document.querySelector('[data-upload-placeholder]');
    const preview = document.querySelector('[data-upload-preview]');
    const previewImg = document.querySelector('[data-preview-img]');
    const previewName = document.querySelector('[data-preview-name]');
    const previewSize = document.querySelector('[data-preview-size]');
    const removeBtn = document.querySelector('[data-remove-file]');

    if (fileInput && dropzone) {
        function handleFile(file) {
            if (!file) return;

            // Kiểm tra định dạng ảnh
            if (!file.type.match(/^image\/(png|jpeg|webp)$/i)) {
                fileInput.value = ''; if (preview) preview.hidden = true; if (placeholder) placeholder.hidden = false;
                showToast('Chỉ chấp nhận file ảnh JPG, PNG hoặc WebP.');
                return;
            }

            // Kiểm tra dung lượng (tối đa 5MB)
            if (file.size > 5 * 1024 * 1024) {
                fileInput.value = ''; if (preview) preview.hidden = true; if (placeholder) placeholder.hidden = false;
                showToast('Dung lượng ảnh không được vượt quá 5MB.');
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                if (fileInput.files[0] !== file) return;
                if (previewImg) previewImg.src = e.target.result;
                if (previewName) previewName.textContent = file.name;
                if (previewSize) {
                    const sizeKb = (file.size / 1024).toFixed(1);
                    previewSize.textContent = sizeKb > 1024 ? (sizeKb / 1024).toFixed(2) + ' MB' : sizeKb + ' KB';
                }
                if (placeholder) placeholder.hidden = true;
                if (preview) preview.hidden = false;
                showToast('Ảnh chỉ được xem trước trên thiết bị, chưa gửi lên hệ thống.');
            };
            reader.readAsDataURL(file);
        }

        fileInput.addEventListener('change', () => {
            if (fileInput.files && fileInput.files[0]) {
                handleFile(fileInput.files[0]);
            }
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                fileInput.value = '';
                if (previewImg) previewImg.src = '';
                if (preview) preview.hidden = true;
                if (placeholder) placeholder.hidden = false;
            });
        }

        // Drag & drop
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
            });
        });

        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files[0]) {
                fileInput.files = files;
                handleFile(files[0]);
            }
        });
    }

    // 3. ĐÓNG / MỞ ACCORDION THÔNG TIN LỊCH ĐẶT
    const summaryCard = document.querySelector('.stryke-order-summary-card');
    const toggleSummary = document.querySelector('[data-toggle-summary]');
    if (summaryCard && toggleSummary) {
        toggleSummary.addEventListener('click', () => {
            summaryCard.classList.toggle('collapsed');
        });
    }

    // 4. MODAL PHÓNG TO MÃ VIETQR
    const qrModal = document.getElementById('stryke-qr-modal');
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-open-qr]')) {
            if (qrModal) qrModal.hidden = false;
        } else if (event.target.closest('[data-close-qr]')) {
            if (qrModal) qrModal.hidden = true;
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && qrModal && !qrModal.hidden) {
            qrModal.hidden = true;
        }
    });

    // 5. NÚT TIẾP TỤC THANH TOÁN (TH1: BẮT BUỘC CHỌN ẢNH BIÊN LAI MỚI ĐƯỢC XÁC NHẬN)
    const submitBtn = document.querySelector('[data-submit-payment]');
    const receiptForm = document.getElementById('form-submit-receipt');

    if (submitBtn) {
        submitBtn.addEventListener('click', (e) => {
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                showToast('Vui lòng chọn ảnh chụp biên lai chuyển khoản trước khi bấm xác nhận.');
                if (dropzone) {
                    dropzone.style.borderColor = '#ef4444';
                    dropzone.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => { dropzone.style.borderColor = ''; }, 2500);
                }
                return false;
            }
            if (receiptForm) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Đang tải ảnh biên lai lên...';
                receiptForm.submit();
            }
        });
    }

    // 6. TOAST TIỆN ÍCH
    function showToast(message) {
        const existing = document.querySelector('.stryke-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = 'stryke-toast';
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // 7. KIỂM TRA HẾT GIỜ ĐỂ TỰ ĐỘNG HỦY VÀ HIỂN THỊ NÚT QUAY LẠI TRANG CHỦ (TH2)
    const expiredCard = document.querySelector('[data-payment-expired]');
    const bottomBar = document.querySelector('.stryke-bottom-bar');
    const counterEl = document.querySelector('[data-countdown]');
    const waitMsgEl = document.querySelector('[data-wait-message]');
    const waitingRoot = document.querySelector('[data-booking-wait]');
    let expiredInterval = null;
    let autoExpireSent = false;

    function checkExpiredState() {
        if (!expiredCard) {
            if (expiredInterval) { clearInterval(expiredInterval); expiredInterval = null; }
            return;
        }

        const successCard = document.querySelector('[data-payment-success]');
        if (successCard && !successCard.hidden) {
            if (expiredInterval) { clearInterval(expiredInterval); expiredInterval = null; }
            return;
        }

        const receiptWaitingCard = document.querySelector('[data-receipt-waiting]');
        if (receiptWaitingCard && !receiptWaitingCard.hidden) {
            if (expiredInterval) { clearInterval(expiredInterval); expiredInterval = null; }
            return;
        }

        const timeText = counterEl ? counterEl.textContent.trim() : '';
        const msgText = (waitMsgEl ? waitMsgEl.textContent : '').toLowerCase();

        const isTimeUp = (timeText === '00:00' || timeText === '0:00');
        const isExpiredState = msgText.includes('hết') || msgText.includes('hủy');

        if (isTimeUp || isExpiredState) {
            expiredCard.hidden = false;
            if (bottomBar) bottomBar.hidden = true;

            // TH2: Tự động gửi request cập nhật Database sang trạng thái HỦY ngay lập tức
            if (!autoExpireSent && waitingRoot && waitingRoot.dataset.bookingId && waitingRoot.dataset.receiptSubmitted !== '1') {
                autoExpireSent = true;
                const fd = new FormData();
                fd.append('id', waitingRoot.dataset.bookingId);
                fetch(waitingRoot.dataset.expireUrl || '/booking/expire', {
                    method: 'POST',
                    body: fd,
                    headers: { 'Accept': 'application/json' }
                }).catch(() => {});
            }

            // Chuyển nút quay lại ở header thành link điều hướng trực tiếp nếu đang là form hủy
            const cancelFormEl = document.querySelector('form[data-booking-cancel]');
            if (cancelFormEl) {
                const backUrl = document.querySelector('[data-booking-navigation]')?.dataset.backUrl || '/';
                const link = document.createElement('a');
                link.className = 'court-flow-back';
                link.href = backUrl;
                link.setAttribute('aria-label', 'Về lịch sân');
                link.innerHTML = cancelFormEl.querySelector('button')?.innerHTML || '';
                cancelFormEl.replaceWith(link);
            }

            if (expiredInterval) {
                clearInterval(expiredInterval);
                expiredInterval = null;
            }
        }
    }

    // Điều hướng tức thì khi bấm nút quay lại trang chủ hoặc chọn lại khung giờ
    document.addEventListener('click', (event) => {
        const goHomeBtn = event.target.closest('.stryke-btn-go-home');
        if (goHomeBtn) {
            event.preventDefault();
            location.href = goHomeBtn.href;
            return;
        }
        const reselectBtn = event.target.closest('.stryke-btn-reselect');
        if (reselectBtn) {
            event.preventDefault();
            location.href = reselectBtn.href;
            return;
        }
    });

    checkExpiredState();
    expiredInterval = setInterval(checkExpiredState, 500);
})();
