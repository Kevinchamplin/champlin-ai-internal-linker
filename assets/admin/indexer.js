(function () {
    'use strict';

    if (typeof window.wp === 'undefined' || typeof window.wp.apiFetch !== 'function') {
        return;
    }

    const cfg = window.chailIndexer || {};
    const startBtn = document.getElementById('chail-start-reindex');
    const bar = document.getElementById('chail-progress-bar');
    const progress = document.getElementById('chail-progress');
    const text = document.getElementById('chail-progress-text');
    const indexedCount = document.getElementById('chail-indexed-count');

    if (!startBtn || !progress) {
        return;
    }

    let polling = null;

    window.wp.apiFetch.use(window.wp.apiFetch.createNonceMiddleware(cfg.nonce));

    function poll() {
        window.wp.apiFetch({ path: '/chail/v1/index/progress' }).then((state) => {
            progress.value = state.processed;
            progress.max = Math.max(1, state.total);
            indexedCount.textContent = String(state.processed);
            text.textContent = state.processed + ' / ' + state.total;
            if (state.status === 'complete') {
                if (polling) { clearInterval(polling); polling = null; }
                startBtn.disabled = false;
                startBtn.textContent = cfg.i18nStart;
            }
        });
    }

    startBtn.addEventListener('click', function () {
        startBtn.disabled = true;
        startBtn.textContent = cfg.i18nPause;
        bar.style.display = 'block';
        window.wp.apiFetch({
            path: '/chail/v1/index/start',
            method: 'POST'
        }).then((state) => {
            progress.value = state.processed;
            progress.max = Math.max(1, state.total);
            polling = setInterval(poll, 2000);
        }).catch(() => {
            startBtn.disabled = false;
            startBtn.textContent = cfg.i18nStart;
        });
    });
})();
