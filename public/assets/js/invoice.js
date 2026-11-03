/**
 * STRYKE - XỬ LÝ IN ẤN HÓA ĐƠN & ĐIỀU HƯỚNG CHUẨN CSP
 * Tuân thủ nghiêm ngặt Content Security Policy (script-src 'self')
 */
(function () {
    'use strict';

    function initInvoiceActions() {
        // 1. Bắt sự kiện bấm nút in hóa đơn
        document.querySelectorAll('[data-action="print"], .btn-print-action').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                window.print();
            });
        });

        // 2. Bắt sự kiện bấm nút đóng tab in / quay lại
        document.querySelectorAll('[data-action="close"], .btn-close-action').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                // Thử đóng tab hiện tại nếu mở bằng tab mới
                window.close();
                // Nếu trình duyệt chặn window.close() do không phải script mở tab, quay lại trang trước
                setTimeout(function () {
                    if (window.history.length > 1) {
                        window.history.back();
                    } else {
                        window.location.href = btn.getAttribute('href') || '/admin/invoices';
                    }
                }, 100);
            });
        });

        // 3. Tự động kích hoạt hộp thoại in thực tế nếu là trang in chuyên dụng hoặc có tham số ?print=1
        var isPrintPage = window.location.pathname.indexOf('/invoices/print') !== -1 ||
                          window.location.search.indexOf('route=%2Fadmin%2Finvoices%2Fprint') !== -1 ||
                          window.location.search.indexOf('route=/admin/invoices/print') !== -1 ||
                          window.location.search.indexOf('route=%2Finvoices%2Fprint') !== -1 ||
                          window.location.search.indexOf('route=/invoices/print') !== -1 ||
                          new URLSearchParams(window.location.search).get('print') === '1' ||
                          document.body.classList.contains('page-invoice-print');

        if (isPrintPage) {
            // Đợi CSS nạp xong và gọi lệnh in trực tiếp tới máy in
            setTimeout(function () {
                window.print();
            }, 350);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initInvoiceActions);
    } else {
        initInvoiceActions();
    }
})();
