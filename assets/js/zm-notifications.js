/**
 * Zeko Mentor — Notification Bell & Toast System
 *
 * Handles real-time notification polling, dropdown, mark-as-read,
 * and toast notifications for AJAX actions.
 */
(function ($) {
    'use strict';

    var $doc = $(document);
    var pollTimer;

    // ─── Toast System ─────────────────────────────────────────

    function ensureToastContainer() {
        var $c = $('.zm-toast-container');
        if (!$c.length) {
            $c = $('<div class="zm-toast-container"></div>').appendTo('body');
        }
        return $c;
    }

    window.zmToast = function (type, message, duration) {
        duration = duration || 4000;
        var $c = ensureToastContainer();
        var icons = {
            success: '&#10003;',
            error: '&#10007;',
            info: '&#8505;'
        };

        var $toast = $(
            '<div class="zm-toast toast-' + type + '">' +
                '<div class="zm-toast-icon">' + (icons[type] || icons.info) + '</div>' +
                '<div class="zm-toast-text">' + message + '</div>' +
                '<button class="zm-toast-close" aria-label="Close">&times;</button>' +
            '</div>'
        );

        $toast.find('.zm-toast-close').on('click', function () {
            dismissToast($toast);
        });

        $c.append($toast);

        setTimeout(function () {
            dismissToast($toast);
        }, duration);
    };

    function dismissToast($toast) {
        $toast.addClass('toast-exiting');
        setTimeout(function () {
            $toast.remove();
        }, 300);
    }

    // ─── Notification Dropdown ────────────────────────────────

    $doc.on('click', '.zm-bell-trigger', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $wrapper = $(this).closest('.zm-notifications-wrapper');
        var $dropdown = $wrapper.find('.zm-notifications-dropdown');
        var wasOpen = $dropdown.hasClass('is-open');

        // Close all dropdowns first.
        $('.zm-notifications-dropdown').removeClass('is-open');

        if (!wasOpen) {
            $dropdown.addClass('is-open');
            loadNotifications($dropdown);
        }
    });

    // Close dropdown on outside click.
    $doc.on('click', function (e) {
        if (!$(e.target).closest('.zm-notifications-wrapper').length) {
            $('.zm-notifications-dropdown').removeClass('is-open');
        }
    });

    function loadNotifications($dropdown) {
        var $list = $dropdown.find('.zm-notifications-list');
        $list.html('<div class="zm-notifications-empty">Loading...</div>');

        $.post(zmNotifications.ajaxUrl, {
            action: 'zm_get_notifications',
            nonce: zmNotifications.nonce
        }).done(function (res) {
            if (res.success && res.data.notifications.length > 0) {
                $list.empty();
                $.each(res.data.notifications, function (i, n) {
                    var iconClass = 'type-' + (n.type || 'info');
                    var icons = {
                        session: '&#128197;',
                        match: '&#127919;',
                        review: '&#11088;',
                        goal: '&#127919;',
                        message: '&#9993;',
                        info: '&#8505;'
                    };

                    var html =
                        '<div class="zm-notification-item ' + (n.is_read == 0 ? 'unread' : '') + '" data-id="' + n.notification_id + '" data-link="' + (n.link || '') + '">' +
                            '<div class="zm-notification-icon ' + iconClass + '">' + (icons[n.type] || icons.info) + '</div>' +
                            '<div class="zm-notification-content">' +
                                '<p class="zm-notification-title">' + escapeHtml(n.title) + '</p>' +
                                '<p class="zm-notification-message">' + escapeHtml(n.message) + '</p>' +
                                '<span class="zm-notification-time">' + escapeHtml(n.time_ago || '') + '</span>' +
                            '</div>' +
                            (n.is_read == 0 ? '<div class="zm-notification-unread-dot"></div>' : '') +
                        '</div>';
                    $list.append(html);
                });
            } else {
                $list.html('<div class="zm-notifications-empty">' + zmNotifications.i18n.noNotifications + '</div>');
            }
        });
    }

    // Mark single notification as read on click.
    $doc.on('click', '.zm-notification-item', function () {
        var $item = $(this);
        var id = $item.data('id');
        var link = $item.data('link');

        if ($item.hasClass('unread')) {
            $.post(zmNotifications.ajaxUrl, {
                action: 'zm_mark_notification_read',
                nonce: zmNotifications.nonce,
                notification_id: id
            }).done(function () {
                $item.removeClass('unread');
                $item.find('.zm-notification-unread-dot').remove();
                updateBellCount(-1);
            });
        }

        if (link) {
            window.location.href = link;
        }
    });

    // Mark all as read.
    $doc.on('click', '.zm-mark-all-read', function (e) {
        e.preventDefault();
        $.post(zmNotifications.ajaxUrl, {
            action: 'zm_mark_all_notifications_read',
            nonce: zmNotifications.nonce
        }).done(function () {
            $('.zm-notification-item.unread').removeClass('unread');
            $('.zm-notification-unread-dot').remove();
            updateBellCount(0, true);
        });
    });

    // ─── Bell Count ──────────────────────────────────────────

    function updateBellCount(delta, reset) {
        var $counts = $('.zm-bell-count, .zm-bell-trigger .bell-count');
        $counts.each(function () {
            var $el = $(this);
            var current = parseInt($el.text(), 10) || 0;
            var next;
            if (reset) {
                next = 0;
            } else {
                next = Math.max(0, current + delta);
            }
            $el.text(next);
            if (next === 0) {
                $el.hide();
            } else {
                $el.show();
            }
        });

        // Admin bar count.
        var $abCount = $('.zm-admin-bell .zm-bell-count');
        if ($abCount.length) {
            var abCurrent = parseInt($abCount.text(), 10) || 0;
            var abNext = reset ? 0 : Math.max(0, abCurrent + delta);
            $abCount.text(abNext);
            if (abNext === 0) {
                $abCount.remove();
                $('.zm-admin-bell').removeClass('zm-bell-unread');
            }
        }
    }

    // ─── Polling ─────────────────────────────────────────────

    function pollNotifications() {
        $.post(zmNotifications.ajaxUrl, {
            action: 'zm_get_unread_count',
            nonce: zmNotifications.nonce
        }).done(function (res) {
            if (res.success) {
                var count = parseInt(res.data.count, 10) || 0;
                var $bell = $('.zm-bell-trigger .bell-count');
                if ($bell.length) {
                    $bell.text(count);
                    if (count > 0) {
                        $bell.show();
                        $('.zm-notifications-wrapper').addClass('has-unread');
                    } else {
                        $bell.hide();
                        $('.zm-notifications-wrapper').removeClass('has-unread');
                    }
                }
            }
        });
    }

    if (typeof zmNotifications !== 'undefined' && zmNotifications.pollInterval) {
        pollTimer = setInterval(pollNotifications, zmNotifications.pollInterval);
    }

    // ─── Utility ─────────────────────────────────────────────

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

})(jQuery);
