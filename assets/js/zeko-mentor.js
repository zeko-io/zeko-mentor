/**
 * Zeko Mentor — Frontend JavaScript
 *
 * Handles AJAX interactions for booking, reviews, goals, availability,
 * match management, and mentor search.
 */
(function ($) {
    'use strict';

    var $doc = $(document);

    // ─── Utility ────────────────────────────────────────────────

    function zmAjax(action, data, $btn) {
        data.action = action;
        data.nonce = zekoMentor.nonce;

        var origText = $btn ? $btn.text() : '';
        if ($btn) {
            $btn.prop('disabled', true).text('...');
        }

        return $.post(zekoMentor.ajaxUrl, data)
            .always(function () {
                if ($btn) {
                    $btn.prop('disabled', false).text(origText);
                }
            });
    }

    function showNotice($el, type, msg) {
        var cls = 'success' === type ? 'zeko-notice-success' : 'zeko-notice-error';
        var $n = $('<div class="zeko-notice ' + cls + '">' + msg + '</div>');
        $el.prepend($n);
        setTimeout(function () { $n.fadeOut(300, function () { $n.remove(); }); }, 4000);
    }

    // ─── Book Session ───────────────────────────────────────────

    $doc.on('click', '.zm-book-session', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $form = $btn.closest('.zm-booking-form');

        var date  = $form.find('[name="session_date"]').val();
        var start = $form.find('[name="start_time"]').val();
        var end   = $form.find('[name="end_time"]').val();

        var msg = '';
        if (!date) {
            msg = zekoMentor.i18n.pickDate;
        } else if (!start || !end) {
            msg = zekoMentor.i18n.pickTime;
        }
        if (msg) {
            showNotice($form, 'error', msg);
            if (!start || !end) {
                var $slots = $form.find('.zm-slots-container');
                if ($slots.length) {
                    $slots.get(0).scrollIntoView({ block: 'center', behavior: 'smooth' });
                }
            }
            return;
        }

        zmAjax('zeko_mentor_book_session', {
            mentor_id:    $form.data('mentor-id'),
            session_date: date,
            start_time:   start,
            end_time:     end,
            topic:        $form.find('[name="topic"]').val(),
            session_type: $form.find('[name="session_type"]').val()
        }, $btn).done(function (res) {
            if (res.success) {
                showNotice($form, 'success', res.data.message);
                $btn.hide();
                if (res.data.redirect) {
                    setTimeout(function () {
                        window.location.href = res.data.redirect;
                    }, 900);
                }
            } else {
                showNotice($form, 'error', res.data.message);
            }
        });
    });

    // ─── Cancel Session ─────────────────────────────────────────

    $doc.on('click', '.zm-cancel-session', function (e) {
        e.preventDefault();
        if (!confirm(zekoMentor.i18n.confirm)) return;

        var $btn = $(this);
        zmAjax('zeko_mentor_cancel_session', {
            session_id: $btn.data('session-id')
        }, $btn).done(function (res) {
            if (res.success) {
                $btn.closest('.zeko-session-card').fadeOut(300);
            }
        });
    });

    // ─── Complete Session (Mentor) ──────────────────────────────

    $doc.on('click', '.zm-complete-session', function (e) {
        e.preventDefault();
        var $btn = $(this);
        zmAjax('zeko_mentor_complete_session', {
            session_id: $btn.data('session-id')
        }, $btn).done(function (res) {
            if (res.success) {
                $btn.replaceWith('<span class="zeko-session-card status-completed">Completed</span>');
            }
        });
    });

    // ─── Submit Review ──────────────────────────────────────────

    $doc.on('click', '.zm-submit-review', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $form = $btn.closest('.zm-review-form');

        zmAjax('zeko_mentor_submit_review', {
            session_id:  $form.data('session-id'),
            rating:      $form.find('[name="rating"]').val(),
            title:       $form.find('[name="title"]').val(),
            review_text: $form.find('[name="review_text"]').val()
        }, $btn).done(function (res) {
            if (res.success) {
                showNotice($form, 'success', res.data.message);
                $form.find('[name="review_text"]').val('');
            } else {
                showNotice($form, 'error', res.data.message);
            }
        });
    });

    // ─── Save Availability ──────────────────────────────────────

    $doc.on('click', '.zm-save-availability', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $form = $btn.closest('.zm-availability-form');
        var slots = [];

        $form.find('.zm-slot-row').each(function () {
            var $row = $(this);
            slots.push({
                day_of_week: $row.find('[name="day_of_week"]').val(),
                start_time:  $row.find('[name="start_time"]').val(),
                end_time:    $row.find('[name="end_time"]').val(),
                timezone:    $row.find('[name="timezone"]').val()
            });
        });

        zmAjax('zeko_mentor_save_availability', {
            slots: JSON.stringify(slots)
        }, $btn).done(function (res) {
            showNotice($form, res.success ? 'success' : 'error', res.data.message);
        });
    });

    // ─── Get Available Slots ────────────────────────────────────

    $doc.on('change', '.zm-date-picker', function () {
        var $picker = $(this);
        var mentorId = $picker.data('mentor-id');
        var date = $picker.val();

        if (!mentorId || !date) return;

        $.post(zekoMentor.ajaxUrl, {
            action: 'zeko_mentor_get_available_slots',
            nonce: zekoMentor.nonce,
            mentor_id: mentorId,
            date: date
        }).done(function (res) {
            if (res.success) {
                var $container = $picker.closest('.zm-booking-form').find('.zm-slots-container');
                $container.empty();
                if (!res.data.slots || !res.data.slots.length) {
                    $container.append('<div class="zm-slot-empty">' + zekoMentor.i18n.noSlots + '</div>');
                    return;
                }
                $.each(res.data.slots, function (i, slot) {
                    $container.append(
                        '<div class="zm-slot" data-start="' + slot.start_time + '" data-end="' + slot.end_time + '">' +
                        slot.start_time + ' - ' + slot.end_time +
                        '</div>'
                    );
                });
            }
        });
    });

    // ─── Slot Selection ─────────────────────────────────────────

    $doc.on('click', '.zm-slot', function () {
        var $slot = $(this);
        var $form = $slot.closest('.zm-booking-form');
        $form.find('.zm-slot').removeClass('selected');
        $slot.addClass('selected');
        $form.find('[name="start_time"]').val($slot.data('start'));
        $form.find('[name="end_time"]').val($slot.data('end'));
    });

    // ─── Goal CRUD ─────────────────────────────────────────────

    $doc.on('click', '.zm-save-goal', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $form = $btn.closest('.zm-goal-form');

        zmAjax('zeko_mentor_save_goal', {
            title:       $form.find('[name="title"]').val(),
            description: $form.find('[name="description"]').val(),
            target_date: $form.find('[name="target_date"]').val()
        }, $btn).done(function (res) {
            if (res.success) {
                showNotice($form, 'success', res.data.message);
                $form.find('[name="title"]').val('');
                $form.find('[name="description"]').val('');
            }
        });
    });

    $doc.on('click', '.zm-achieve-goal', function (e) {
        e.preventDefault();
        var $btn = $(this);
        zmAjax('zeko_mentor_update_goal', {
            goal_id: $btn.data('goal-id'),
            status: 'achieved'
        }, $btn).done(function (res) {
            if (res.success) {
                $btn.closest('.zeko-goal-card').find('.goal-status')
                    .removeClass('status-active').addClass('status-achieved').text('Achieved');
                $btn.hide();
            }
        });
    });

    // ─── Match Accept/Dismiss ───────────────────────────────────

    $doc.on('click', '.zm-accept-match', function (e) {
        e.preventDefault();
        var $btn = $(this);
        zmAjax('zeko_mentor_accept_match', {
            match_id: $btn.data('match-id')
        }, $btn).done(function (res) {
            if (res.success) {
                $btn.replaceWith('<span class="zeko-verified-badge">Accepted</span>');
            }
        });
    });

    $doc.on('click', '.zm-dismiss-match', function (e) {
        e.preventDefault();
        var $btn = $(this);
        zmAjax('zeko_mentor_dismiss_match', {
            match_id: $btn.data('match-id')
        }, $btn).done(function (res) {
            if (res.success) {
                $btn.closest('.zeko-match-card').fadeOut(300);
            }
        });
    });

    // ─── Mentor Search ──────────────────────────────────────────

    var searchTimer;
    $doc.on('input', '.zm-mentor-search', function () {
        var $input = $(this);
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            $.post(zekoMentor.ajaxUrl, {
                action: 'zeko_mentor_search',
                nonce: zekoMentor.nonce,
                search: $input.val(),
                page: 1
            }).done(function (res) {
                if (res.success) {
                    var $grid = $('.zeko-mentor-grid');
                    $grid.empty();
                    $.each(res.data.mentors, function (i, m) {
                        // Render mentor card — simplified
                        var profileUrl = m.mentor_slug ? zekoMentor.ajaxUrl.replace('admin-ajax.php', 'mentors/' + m.mentor_slug + '/') : '#';
                        $grid.append(
                            '<div class="zeko-mentor-card">' +
                            '<h3 class="mentor-name"><a href="' + profileUrl + '">' + (m.mentor_name || 'Mentor') + '</a></h3>' +
                            '<p>' + (m.bio || '').substring(0, 100) + '</p>' +
                            '<div class="zeko-rate">' + m.hourly_rate + ' <span class="currency">' + m.currency + '</span></div>' +
                            '</div>'
                        );
                    });
                }
            });
        }, 300);
    });

    // ─── Join Program ───────────────────────────────────────────

    $doc.on('click', '.zm-join-program', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $card = $btn.closest('.zeko-program-card, .zm-program-card').length
            ? $btn.closest('.zeko-program-card, .zm-program-card')
            : $btn.closest('div');
        zmAjax('zeko_mentor_join_program', {
            program_id: $btn.data('program-id')
        }, $btn).done(function (res) {
            if (res.success) {
                if (res.data && res.data.redirect) {
                    window.location.href = res.data.redirect;
                    return;
                }
                $btn.replaceWith('<span class="zeko-verified-badge">✓ ' + (res.data && res.data.message ? res.data.message : zekoMentor.i18n.enrolled) + '</span>');
                if ($('.zeko-program-single').length) {
                    showNotice($card, 'success', res.data && res.data.message ? res.data.message : zekoMentor.i18n.enrolled);
                    setTimeout(function () { window.location.reload(); }, 1500);
                }
            } else {
                showNotice($card, 'error', res.data && res.data.message ? res.data.message : zekoMentor.i18n.error);
            }
        }).fail(function () {
            showNotice($card, 'error', zekoMentor.i18n.error);
        });
    });

    // ─── Leave Program ──────────────────────────────────────────

    $doc.on('click', '.zm-leave-program', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $card = $btn.closest('.zeko-program-card, .zm-program-card').length
            ? $btn.closest('.zeko-program-card, .zm-program-card')
            : $btn.closest('div');
        if (!window.confirm(zekoMentor.i18n.confirm)) {
            return;
        }
        zmAjax('zeko_mentor_leave_program', {
            program_id: $btn.data('program-id')
        }, $btn).done(function (res) {
            if (res.success) {
                showNotice($card, 'success', res.data && res.data.message ? res.data.message : zekoMentor.i18n.cancelled);
                setTimeout(function () { window.location.reload(); }, 1200);
            } else {
                showNotice($card, 'error', res.data && res.data.message ? res.data.message : zekoMentor.i18n.error);
            }
        }).fail(function () {
            showNotice($card, 'error', zekoMentor.i18n.error);
        });
    });

    // ─── Send Message ───────────────────────────────────────────

    $doc.on('click', '.zm-send-message', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $form = $btn.closest('.zm-message-form');

        zmAjax('zeko_mentor_send_message', {
            receiver_id: $form.data('receiver-id'),
            session_id:  $form.data('session-id'),
            message:     $form.find('[name="message"]').val()
        }, $btn).done(function (res) {
            if (res.success) {
                $form.find('[name="message"]').val('');
            }
        });
    });

    // ─── Star Rating Picker ─────────────────────────────────────

    $doc.on('click', '.zm-star-rating .star', function () {
        var val = $(this).data('value');
        $(this).closest('.zm-star-rating').find('.star').each(function () {
            $(this).toggleClass('active', $(this).data('value') <= val);
        });
        $(this).closest('.zm-star-rating').find('[name="rating"]').val(val);
    });

    // ─── Dashboard Tab Switching ──────────────────────────────

    $doc.on('click', '.zeko-dashboard-tab, .zeko-profile-tab', function (e) {
        e.preventDefault();
        var $tab = $(this);
        var target = $tab.data('tab');
        var $container = $tab.closest('.zeko-mentor-dashboard, .zeko-mentor-profile, .wrap');

        $container.find('.zeko-dashboard-tab, .zeko-profile-tab').removeClass('active');
        $tab.addClass('active');

        $container.find('.zeko-dashboard-tab-panel').removeClass('active');
        $container.find('[data-tab-panel="' + target + '"]').addClass('active');
    });

    // ─── Match Accept/Dismiss (alternate selectors) ─────────────

    $doc.on('click', '.zeko-mentor-match-accept', function (e) {
        e.preventDefault();
        var $btn = $(this);
        zmAjax('zeko_mentor_accept_match', {
            match_id: $btn.data('match')
        }, $btn).done(function (res) {
            if (res.success) {
                $btn.replaceWith('<span style="color:#10b981;font-weight:600;">✓ Accepted</span>');
            }
        });
    });

    $doc.on('click', '.zeko-mentor-match-dismiss', function (e) {
        e.preventDefault();
        var $btn = $(this);
        if (!confirm(zekoMentor.i18n.confirm)) return;
        zmAjax('zeko_mentor_dismiss_match', {
            match_id: $btn.data('match')
        }, $btn).done(function (res) {
            if (res.success) {
                $btn.closest('.zeko-match-card, [style*="border:1px"]').fadeOut(300);
            }
        });
    });

    // ─── Log Progress (Modal) ──────────────────────────────────

    $doc.on('click', '.zeko-mentor-log-progress', function (e) {
        e.preventDefault();
        var goalId = $(this).data('goal');
        showProgressModal(goalId);
    });

    function showProgressModal(goalId) {
        var $modal = $(
            '<div class="zm-modal-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:99999;display:flex;align-items:center;justify-content:center;">' +
            '<div class="zm-modal" style="background:#fff;border-radius:12px;padding:24px;width:90%;max-width:420px;box-shadow:0 8px 32px rgba(0,0,0,0.2);">' +
                '<h3 style="margin:0 0 16px;font-size:18px;color:#111827;">Log Progress</h3>' +
                '<div style="margin-bottom:14px;">' +
                    '<label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Progress Note *</label>' +
                    '<textarea class="zm-progress-note" rows="3" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;resize:vertical;" placeholder="What did you accomplish?"></textarea>' +
                '</div>' +
                '<div style="margin-bottom:16px;">' +
                    '<label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:4px;">Rating (optional)</label>' +
                    '<div class="zm-star-rating" style="display:flex;gap:4px;">' +
                        '<span class="star" data-value="1" style="font-size:20px;cursor:pointer;color:#d1d5db;">&#9733;</span>' +
                        '<span class="star" data-value="2" style="font-size:20px;cursor:pointer;color:#d1d5db;">&#9733;</span>' +
                        '<span class="star" data-value="3" style="font-size:20px;cursor:pointer;color:#d1d5db;">&#9733;</span>' +
                        '<span class="star" data-value="4" style="font-size:20px;cursor:pointer;color:#d1d5db;">&#9733;</span>' +
                        '<span class="star" data-value="5" style="font-size:20px;cursor:pointer;color:#d1d5db;">&#9733;</span>' +
                        '<input type="hidden" name="rating" value="">' +
                    '</div>' +
                '</div>' +
                '<div style="display:flex;gap:8px;justify-content:flex-end;">' +
                    '<button class="zeko-btn zeko-btn-secondary zm-modal-close">Cancel</button>' +
                    '<button class="zeko-btn zeko-btn-primary zm-modal-submit">Save Progress</button>' +
                '</div>' +
            '</div>' +
            '</div>'
        );

        $('body').append($modal);

        // Star rating in modal.
        $modal.find('.zm-star-rating .star').on('click', function () {
            var val = $(this).data('value');
            $modal.find('.zm-star-rating .star').each(function () {
                $(this).css('color', $(this).data('value') <= val ? '#f59e0b' : '#d1d5db');
            });
            $modal.find('[name="rating"]').val(val);
        });

        $modal.find('.zm-modal-close').on('click', function () {
            $modal.remove();
        });

        $modal.on('click', function (e) {
            if ($(e.target).hasClass('zm-modal-overlay')) {
                $modal.remove();
            }
        });

        $modal.find('.zm-modal-submit').on('click', function () {
            var note = $modal.find('.zm-progress-note').val().trim();
            if (!note) {
                $modal.find('.zm-progress-note').css('border-color', '#ef4444');
                return;
            }

            var $btn = $(this);
            zmAjax('zeko_mentor_log_progress', {
                goal_id: goalId,
                note: note,
                rating: $modal.find('[name="rating"]').val() || ''
            }, $btn).done(function (res) {
                if (res.success) {
                    $modal.remove();
                    location.reload();
                }
            });
        });
    }

    // ─── Mark Goal Achieved ─────────────────────────────────────

    $doc.on('click', '.zeko-mentor-goal-achieved', function (e) {
        e.preventDefault();
        var $btn = $(this);
        if (!confirm('Mark this goal as achieved?')) return;
        zmAjax('zeko_mentor_update_goal', {
            goal_id: $btn.data('goal'),
            status: 'achieved'
        }, $btn).done(function (res) {
            if (res.success) {
                location.reload();
            }
        });
    });

    // ─── Submit Mentor Application ──────────────────────────────

    $doc.on('click', '.zm-submit-application', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $form = $btn.closest('.zm-apply-form');
        var expertise = [];
        $form.find('input[name="expertise[]"]:checked').each(function () {
            expertise.push($(this).val());
        });

        zmAjax('zeko_mentor_apply_as_mentor', {
            expertise_areas:  JSON.stringify(expertise),
            bio:              $form.find('textarea[name="bio"]').val(),
            headline:         $form.find('input[name="headline"]').val(),
            location:         $form.find('input[name="location"]').val(),
            languages:        $form.find('input[name="languages"]').val(),
            years_experience: $form.find('input[name="years_experience"]').val(),
            response_time:    $form.find('select[name="response_time"]').val(),
            linkedin_url:     $form.find('input[name="linkedin_url"]').val(),
            github_url:       $form.find('input[name="github_url"]').val(),
            twitter_url:      $form.find('input[name="twitter_url"]').val(),
            hourly_rate:      $form.find('input[name="hourly_rate"]').val(),
            currency:         $form.find('select[name="currency"]').val(),
            max_mentees:      $form.find('input[name="max_mentees"]').val()
        }, $btn).done(function (res) {
            var $notice = $form.closest('.zeko-mentor-apply-form').find('.zeko-mentor-apply-notice');
            $notice.show().css({
                background: res.success ? '#d1fae5' : '#fee2e2',
                color: res.success ? '#065f46' : '#991b1b',
                borderColor: res.success ? '#6ee7b7' : '#fca5a5'
            }).text(res.data.message);
            if (res.success) {
                $btn.prop('disabled', true).text('Submitted');
            }
        });
    });

    // ─── Verify Application (Admin) ─────────────────────────────

    $doc.on('click', '.zeko-mentor-verify-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        $.post(zekoMentor.ajaxUrl, {
            action: 'zeko_mentor_verify_application',
            nonce:  zekoMentor.nonce,
            user_id: $btn.data('user'),
            action_type: $btn.data('action')
        }).done(function (res) {
            if (res.success) {
                $btn.closest('tr').fadeOut(300);
            }
        });
    });

    // ─── Timezone Detection ──────────────────────────────────────

    if (!zekoMentor.timezone) {
        var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (tz) {
            $.post(zekoMentor.ajaxUrl, {
                action: 'zeko_mentor_save_timezone',
                timezone: tz
            });
        }
    }

    // ─── Inbox: Load Conversations ───────────────────────────────

    function loadConversations() {
        $.post(zekoMentor.ajaxUrl, {
            action: 'zeko_mentor_get_conversations',
            nonce:  zekoMentor.nonce
        }).done(function (res) {
            if (!res.success) return;
            var $list = $('#zm-conversation-list');
            $('#zm-inbox-loading').hide();
            $list.empty();
            if (!res.data.conversations.length) {
                $list.append('<div style="padding:24px;text-align:center;color:#9ca3af;">No conversations yet.</div>');
                return;
            }
            $.each(res.data.conversations, function (i, c) {
                var $item = $('<div class="zm-conv-item" data-other="' + c.other_id + '" style="display:flex;align-items:center;gap:10px;padding:12px;border-bottom:1px solid #f3f4f6;cursor:pointer;">' +
                    '<img src="' + c.avatar + '" style="width:40px;height:40px;border-radius:50%;flex-shrink:0;" />' +
                    '<div style="flex:1;min-width:0;">' +
                        '<div style="font-weight:500;font-size:14px;color:#111827;display:flex;align-items:center;gap:6px;">' +
                            (c.name) +
                            (parseInt(c.unread_count) > 0 ? '<span class="zm-unread-badge" style="background:#7c3aed;color:#fff;font-size:11px;padding:1px 6px;border-radius:10px;">' + c.unread_count + '</span>' : '') +
                        '</div>' +
                        '<div style="font-size:12px;color:#6b7280;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px;">' + (c.last_message || '') + '</div>' +
                    '</div>' +
                '</div>');
                $list.append($item);
            });
        });
    }

    $doc.on('click', '.zm-conv-item', function () {
        var otherId = $(this).data('other');
        loadThread(otherId);
        $('.zm-conv-item').removeClass('active');
        $(this).addClass('active');
    });

    function loadThread(otherId) {
        $('#zm-thread-empty').hide();
        var $content = $('#zm-thread-content').show();
        $content.data('other', otherId);

        // Set header name.
        var name = $('.zm-conv-item.active').find('div:first').text().trim();
        $('#zm-thread-header').text(name);

        $.post(zekoMentor.ajaxUrl, {
            action: 'zeko_mentor_get_messages',
            nonce:  zekoMentor.nonce,
            other_id: otherId
        }).done(function (res) {
            if (!res.success) return;
            var $msgs = $('#zm-thread-messages').empty();
            $.each(res.data.messages, function (i, m) {
                var side = m.is_mine ? 'right' : 'left';
                var bg = m.is_mine ? '#7c3aed' : '#f3f4f6';
                var color = m.is_mine ? '#fff' : '#111827';
                $msgs.append(
                    '<div style="text-align:' + side + ';margin-bottom:8px;">' +
                        '<span style="display:inline-block;background:' + bg + ';color:' + color + ';padding:8px 14px;border-radius:14px;font-size:14px;max-width:70%;word-wrap:break-word;">' + m.message + '</span>' +
                        '<div style="font-size:11px;color:#9ca3af;margin-top:2px;">' + (m.created_at || '') + '</div>' +
                    '</div>'
                );
            });
            $msgs.scrollTop($msgs[0].scrollHeight);
        });
    }

    $('#zm-msg-send').on('click', function () {
        var $input = $('#zm-msg-input');
        var msg = $input.val().trim();
        if (!msg) return;

        var otherId = $('#zm-thread-content').data('other');
        if (!otherId) return;

        $.post(zekoMentor.ajaxUrl, {
            action: 'zeko_mentor_send_message',
            nonce:  zekoMentor.nonce,
            receiver_id: otherId,
            message: msg
        }).done(function (res) {
            if (res.success) {
                $input.val('');
                loadThread(otherId);
                loadConversations();
            }
        });
    });

    // Enter to send.
    $('#zm-msg-input').on('keydown', function (e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            $('#zm-msg-send').click();
        }
    });

    // Initialize inbox.
    if ($('#zm-conversation-list').length) {
        loadConversations();
    }

    // ─── Availability Management ─────────────────────────────────

    $doc.on('click', '.zm-av-add-slot', function () {
        var $day = $(this).closest('.zm-availability-day');
        var $slots = $day.find('.zm-av-slots');
        var $row = $(
            '<div class="zm-av-slot-row" style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">' +
                '<input type="time" class="zm-av-start" style="flex:1;padding:4px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;" />' +
                '<span style="color:#6b7280;">to</span>' +
                '<input type="time" class="zm-av-end" style="flex:1;padding:4px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;" />' +
                '<button type="button" class="zm-btn zm-btn-sm zm-btn-danger zm-av-remove" style="background:transparent;color:#ef4444;border:none;cursor:pointer;">×</button>' +
            '</div>'
        );
        $slots.append($row);
    });

    $doc.on('click', '.zm-av-remove', function () {
        $(this).closest('.zm-av-slot-row').remove();
    });

    $('#zm-av-save').on('click', function () {
        var $btn = $(this);
        var $status = $('#zm-av-status');
        var slots = [];

        $('.zm-availability-day').each(function () {
            var day = $(this).data('day');
            $(this).find('.zm-av-slot-row').each(function () {
                var start = $(this).find('.zm-av-start').val();
                var end = $(this).find('.zm-av-end').val();
                if (start && end) {
                    slots.push({ day_of_week: day, start_time: start, end_time: end, timezone: '' });
                }
            });
        });

        if (!slots.length) {
            $status.text('Add at least one slot.').css('color', '#ef4444');
            return;
        }

        $status.text('Saving...').css('color', '#6b7280');
        $.post(zekoMentor.ajaxUrl, {
            action: 'zeko_mentor_save_availability',
            nonce:  zekoMentor.nonce,
            slots: JSON.stringify(slots)
        }).done(function (res) {
            $status.text(res.data.message).css('color', res.success ? '#10b981' : '#ef4444');
            if (res.success) {
                $btn.prop('disabled', false);
            }
        });
    });

    // ─── Load existing availability ──────────────────────────────

    function loadAvailability() {
        $.post(zekoMentor.ajaxUrl, {
            action: 'zeko_mentor_get_available_slots',
            nonce:  zekoMentor.nonce
        }).done(function (res) {
            if (!res.success || !res.data.slots) return;
            var grouped = {};
            $.each(res.data.slots, function (i, s) {
                if (!grouped[s.day_of_week]) grouped[s.day_of_week] = [];
                grouped[s.day_of_week].push(s);
            });
            $('.zm-availability-day').each(function () {
                var day = $(this).data('day');
                var $slots = $(this).find('.zm-av-slots').empty();
                (grouped[day] || []).forEach(function (s) {
                    $slots.append(
                        '<div class="zm-av-slot-row" style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">' +
                            '<input type="time" class="zm-av-start" style="flex:1;padding:4px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;" value="' + s.start_time + '" />' +
                            '<span style="color:#6b7280;">to</span>' +
                            '<input type="time" class="zm-av-end" style="flex:1;padding:4px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;" value="' + s.end_time + '" />' +
                            '<button type="button" class="zm-btn zm-btn-sm zm-btn-danger zm-av-remove" style="background:transparent;color:#ef4444;border:none;cursor:pointer;">×</button>' +
                        '</div>'
                    );
                });
            });
        });
    }

    if ($('#zm-av-save').length) {
        loadAvailability();
    }

    // ─── Calendar View ───────────────────────────────────────────

    if ($('.zm-calendar-wrapper').length) {
        var calDate = new Date();
        renderCalendar(calDate);
    }

    function renderCalendar(date) {
        var mentorId = $('.zm-calendar-wrapper').data('mentor-id');
        var year = date.getFullYear();
        var month = date.getMonth();
        var firstDay = new Date(year, month, 1).getDay();
        var daysInMonth = new Date(year, month + 1, 0).getDate();
        var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

        $('.zm-cal-title').text(monthNames[month] + ' ' + year);

        // Build day matrix from Mon-Sun.
        var startOffset = firstDay === 0 ? 6 : firstDay - 1; // Mon=0
        var totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;

        var html = '<div class="zm-cal-row zm-cal-header-row" style="display:grid;grid-template-columns:repeat(7,1fr);text-align:center;font-weight:600;font-size:12px;color:#6b7280;">';
        ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].forEach(function (d) {
            html += '<div style="padding:6px 0;">' + d + '</div>';
        });
        html += '</div>';

        html += '<div class="zm-cal-row zm-cal-body" style="display:grid;grid-template-columns:repeat(7,1fr);text-align:center;">';
        for (var i = 0; i < totalCells; i++) {
            var dayNum = i - startOffset + 1;
            if (dayNum < 1 || dayNum > daysInMonth) {
                html += '<div class="zm-cal-day empty"></div>';
            } else {
                var iso = year + '-' + String(month + 1).padStart(2,'0') + '-' + String(dayNum).padStart(2,'0');
                html += '<div class="zm-cal-day" data-date="' + iso + '" style="padding:8px 0;cursor:pointer;border-radius:6px;font-size:14px;color:#111827;">' + dayNum + '</div>';
            }
        }
        html += '</div>';

        $('.zm-cal-grid').html(html);

        // Load slot/session data.
        if (mentorId) {
            var ym = year + '-' + String(month + 1).padStart(2, '0');
            $.post(zekoMentor.ajaxUrl, {
                action: 'zeko_mentor_get_calendar_data',
                nonce:  zekoMentor.nonce,
                mentor_id: mentorId,
                year_month: ym
            }).done(function (res) {
                if (!res.success) return;
                // res.data has_availability: array of date strings
                // res.data.sessions: array of {session_date, start_time, ...}
                if (res.data.has_availability) {
                    $.each(res.data.has_availability, function (i, d) {
                        $('.zm-cal-day[data-date="' + d + '"]').css('background', '#dbeafe');
                    });
                }
                if (res.data.sessions) {
                    $.each(res.data.sessions, function (i, s) {
                        $('.zm-cal-day[data-date="' + s.session_date + '"]').css('background', '#7c3aed').css('color', '#fff');
                    });
                }
            });
        }
    }

    $doc.on('click', '.zm-cal-prev', function () {
        calDate.setMonth(calDate.getMonth() - 1);
        renderCalendar(calDate);
    });

    $doc.on('click', '.zm-cal-next', function () {
        calDate.setMonth(calDate.getMonth() + 1);
        renderCalendar(calDate);
    });

    $doc.on('click', '.zm-cal-day:not(.empty)', function () {
        var date = $(this).data('date');
        // Trigger slot load on linked booking form if present.
        var $picker = $('.zm-date-picker[data-mentor-id="' + $('.zm-calendar-wrapper').data('mentor-id') + '"]');
        if ($picker.length) {
            $picker.val(date).trigger('change');
            $('html, body').animate({ scrollTop: $picker.offset().top - 40 }, 400);
        }
    });

    // ─── Reschedule Session ──────────────────────────────────────

    $doc.on('click', '.zm-reschedule-session', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var sessionId = $btn.data('session-id');
        var $row = $btn.closest('.zm-reschedule-row');

        // Show date/time inputs.
        if (!$row.find('.zm-reschedule-fields').length) {
            $row.append(
                '<div class="zm-reschedule-fields" style="display:flex;gap:8px;align-items:center;margin-top:8px;">' +
                    '<input type="date" class="zm-reschedule-date" style="padding:6px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;" />' +
                    '<input type="time" class="zm-reschedule-start" style="padding:6px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;" />' +
                    '<input type="time" class="zm-reschedule-end" style="padding:6px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;" />' +
                    '<button type="button" class="zm-btn zm-btn-primary zm-confirm-reschedule" data-session-id="' + sessionId + '">Confirm</button>' +
                '</div>'
            );
        }
    });

    $doc.on('click', '.zm-confirm-reschedule', function () {
        var $btn = $(this);
        var $row = $btn.closest('.zm-reschedule-row');
        var sessionId = $btn.data('session-id');

        zmAjax('zeko_mentor_reschedule_session', {
            session_id:  sessionId,
            session_date: $row.find('.zm-reschedule-date').val(),
            start_time:   $row.find('.zm-reschedule-start').val(),
            end_time:     $row.find('.zm-reschedule-end').val()
        }, $btn).done(function (res) {
            if (res.success) {
                $row.find('.zm-reschedule-fields').remove();
                $row.closest('.zeko-session-card').find('.zm-session-datetime').html(res.data.message);
                showNotice($row, 'success', res.data.message);
            } else {
                showNotice($row, 'error', res.data.message);
            }
        });
    });

    // ─── Load session slots for mentor profile on date change ──

    $doc.on('change', '.zm-session-date-input', function () {
        var $input = $(this);
        var mentorId = $input.data('mentor-id');
        var date = $input.val();
        if (!mentorId || !date) return;

        var $slotContainer = $input.closest('.zm-booking-section').find('.zm-time-slots');
        if (!$slotContainer.length) return;

        $slotContainer.html('<div style="color:#6b7280;font-size:14px;">Loading slots...</div>');

        $.post(zekoMentor.ajaxUrl, {
            action: 'zeko_mentor_get_available_slots',
            nonce:  zekoMentor.nonce,
            mentor_id: mentorId,
            date: date,
            timezone: zekoMentor.timezone || ''
        }).done(function (res) {
            $slotContainer.empty();
            if (!res.success || !res.data.slots || !res.data.slots.length) {
                $slotContainer.html('<div style="color:#9ca3af;font-size:14px;">No available slots for this date.</div>');
                return;
            }
            $.each(res.data.slots, function (i, s) {
                $slotContainer.append(
                    '<label class="zm-slot-label" style="display:block;padding:6px 0;font-size:14px;">' +
                        '<input type="radio" name="selected_slot" value="' + s.start_time + '|' + s.end_time + '" /> ' +
                        s.start_time + ' - ' + s.end_time +
                    '</label>'
                );
            });
        });
    });

})(jQuery);
