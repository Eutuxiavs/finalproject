(function () {
    'use strict';

    // ---------- Mobile navigation ----------
    var toggle = document.querySelector('[data-nav-toggle]');
    var panel = document.querySelector('[data-nav-panel]');
    if (toggle && panel) {
        toggle.addEventListener('click', function () {
            var open = panel.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // ---------- Confirm dialogs: <form data-confirm="Are you sure?"> ----------
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form.getAttribute && form.getAttribute('data-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // ---------- Offer form: show the fields that match the chosen mode ----------
    var offerForm = document.querySelector('[data-offer-form]');
    if (offerForm) {
        var cashField = offerForm.querySelector('[data-field="cash"]');
        var tradeField = offerForm.querySelector('[data-field="trade"]');
        var syncMode = function () {
            var checked = offerForm.querySelector('input[name="mode"]:checked');
            var mode = checked ? checked.value : 'cash';
            if (cashField) { cashField.hidden = mode === 'trade'; }
            if (tradeField) { tradeField.hidden = mode === 'cash'; }
        };
        offerForm.addEventListener('change', syncMode);
        syncMode();
    }

    // ---------- Chat: send and poll without reloading ----------
    var log = document.getElementById('chat-log');
    var form = document.getElementById('chat-form');
    if (log && form) {
        var errorBox = document.getElementById('chat-error');
        var lastId = parseInt(log.getAttribute('data-last'), 10) || 0;
        var textarea = form.querySelector('textarea');
        var sending = false;

        var showError = function (text) {
            errorBox.textContent = text || '';
            errorBox.hidden = !text;
        };

        var append = function (msg) {
            if (log.querySelector('[data-id="' + msg.id + '"]')) { return; }
            var bubble = document.createElement('div');
            bubble.className = 'bubble' + (msg.mine ? ' bubble--mine' : '');
            bubble.setAttribute('data-id', msg.id);
            var body = document.createElement('p');
            body.textContent = msg.body;
            body.style.whiteSpace = 'pre-wrap';
            var time = document.createElement('time');
            time.textContent = msg.time;
            bubble.appendChild(body);
            bubble.appendChild(time);
            log.appendChild(bubble);
            lastId = Math.max(lastId, msg.id);
            log.scrollTop = log.scrollHeight;
        };

        var poll = function () {
            if (document.hidden) { return; }
            fetch(log.getAttribute('data-poll') + '?after=' + lastId, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (data && data.ok) { data.messages.forEach(append); }
                })
                .catch(function () { /* try again on the next tick */ });
        };

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var text = textarea.value.trim();
            if (!text || sending) { return; }
            sending = true;
            showError('');

            var data = new FormData();
            data.append('body', text);
            data.append('_token', log.getAttribute('data-token'));

            fetch(log.getAttribute('data-send'), { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Could not send the message.' }; }); })
                .then(function (res) {
                    if (res.ok) {
                        append(res.message);
                        textarea.value = '';
                    } else {
                        showError(res.error || 'Could not send the message.');
                    }
                })
                .catch(function () { showError('Network problem. Please try again.'); })
                .then(function () { sending = false; textarea.focus(); });
        });

        // Enter sends, Shift+Enter adds a new line.
        textarea.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                form.requestSubmit();
            }
        });

        log.scrollTop = log.scrollHeight;
        window.setInterval(poll, 4000);
        document.addEventListener('visibilitychange', poll);
    }
})();
