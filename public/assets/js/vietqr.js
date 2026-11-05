/**
 * STRYKE - VietQR Payment Form Interaction
 * Tuân thủ nghiêm ngặt Content Security Policy (script-src 'self')
 * Không sử dụng inline event handler, hỗ trợ đầy đủ responsive.
 */
(() => {
    'use strict';

    document.querySelector('[data-download-qr]')?.addEventListener('click', async event => {
        event.preventDefault();
        const button = event.currentTarget;
        if (button.getAttribute('aria-busy') === 'true') return;
        button.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(button.href, {credentials:'same-origin', redirect:'error', cache:'no-store'});
            const type = (response.headers.get('Content-Type') || '').split(';')[0];
            const extension = {'image/png':'png','image/jpeg':'jpg','image/webp':'webp','image/svg+xml':'png'}[type];
            if (!response.ok || !extension) throw new Error('Invalid QR response');
            let blob = await response.blob();
            if (!blob.size) throw new Error('Empty QR');
            if (type === 'image/svg+xml') {
                // Render the exact provider image, not a newly generated QR.
                // data: images are already allowed by the site's CSP; blob: images are not.
                const source = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = reject;
                    reader.readAsDataURL(blob);
                });
                const image = new Image();
                image.src = source;
                await image.decode();
                if (!image.naturalWidth || !image.naturalHeight) throw new Error('Invalid SVG size');
                const canvas = document.createElement('canvas');
                canvas.width = image.naturalWidth;
                canvas.height = image.naturalHeight;
                canvas.getContext('2d').drawImage(image, 0, 0);
                blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
                if (!blob?.size) throw new Error('PNG conversion failed');
            } else {
                const bitmap = await createImageBitmap(blob);
                bitmap.close();
            }
            const fileUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            const bookingId = new URL(button.href).searchParams.get('booking_id');
            link.href = fileUrl;
            link.download = 'VietQR_DonHang_' + bookingId + '.' + extension;
            document.body.append(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(fileUrl), 60000);
        } catch {
            showToast('Không thể tải ảnh mã QR. Vui lòng kiểm tra phiên đăng nhập, thời hạn thanh toán và thử lại.');
        } finally {
            button.removeAttribute('aria-busy');
        }
    });

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
                tempInput.className = 'stryke-clipboard-temp';
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
            if (!file) return false;

            // Kiểm tra định dạng ảnh
            if (!file.type.match(/^image\/(png|jpeg|webp)$/i)) {
                fileInput.value = ''; if (preview) preview.hidden = true; if (placeholder) placeholder.hidden = false;
                showToast('Chỉ chấp nhận file ảnh JPG, PNG hoặc WebP.');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                fileInput.value = ''; if (preview) preview.hidden = true; if (placeholder) placeholder.hidden = false;
                showToast('Dung lượng ảnh không được vượt quá 5MB.');
                fileInput.value = '';
                return false;
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
            return true;
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

    // 5. XÁC NHẬN ĐÃ CHUYỂN KHOẢN
    const submitBtn = document.querySelector('[data-submit-payment]');
    const receiptForm = document.getElementById('form-submit-receipt');

    if (submitBtn && receiptForm) {
        let isSubmitting = false;

        submitBtn.addEventListener('click', (e) => {
            if (isSubmitting) {
                e.preventDefault();
                return;
            }

            const file = fileInput?.files?.[0];

            if (!file) {
                e.preventDefault();

                showToast(
                    'Vui lòng chọn ảnh chụp biên lai chuyển khoản trước khi bấm xác nhận.'
                );

                if (dropzone) {
                    dropzone.classList.add('is-invalid');
                    dropzone.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });

                    setTimeout(() => {
                        dropzone.classList.remove('is-invalid');
                    }, 2500);
                }

                return;
            }

            const allowedTypes = [
                'image/png',
                'image/jpeg',
                'image/webp'
            ];

            if (!allowedTypes.includes(file.type) ||
                file.size > 5 * 1024 * 1024) {
                e.preventDefault();
                showToast('Ảnh biên lai không hợp lệ hoặc vượt quá 5MB.');
            }
        });

        receiptForm.addEventListener('submit', (e) => {
            if (isSubmitting) {
                e.preventDefault();
                return;
            }

            isSubmitting = true;

            const textEl = submitBtn.querySelector(
                '.stryke-action-btn__text'
            );

            if (textEl) {
                textEl.textContent = 'ĐANG TẢI ẢNH BIÊN LAI LÊN...';
            }

            // Khóa nút sau khi trình duyệt xử lý submit.
            setTimeout(() => {
                submitBtn.disabled = true;
            }, 0);
        });

        window.addEventListener('pageshow', () => {
            isSubmitting = false;

            if (submitBtn) {
                submitBtn.disabled = false;

                const textEl = submitBtn.querySelector('.stryke-action-btn__text');

                if (textEl) {
                    textEl.textContent = 'XÁC NHẬN ĐÃ CHUYỂN KHOẢN';
                }
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
            toast.classList.add('is-hiding');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

})();
