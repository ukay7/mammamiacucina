(() => {
    'use strict';
    const root = document.getElementById('helcim-checkout');
    if (!root) return;
    const button = document.getElementById('helcim-pay-button');
    const message = document.getElementById('card-payment-message');
    const token = root.dataset.checkoutToken;
    let confirming = false;
    button.addEventListener('click', () => {
        if (confirming || document.getElementById('helcimPayIframe')) return;
        if (typeof window.appendHelcimPayIframe !== 'function') {
            message.textContent = 'Secure payment could not load. Reload this page to retry the same order.';
            return;
        }
        button.disabled = true;
        window.appendHelcimPayIframe(token, true);
    });
    window.addEventListener('message', async (event) => {
        const frame = document.getElementById('helcimPayIframe');
        if (event.origin !== 'https://secure.helcim.app' || !frame || event.source !== frame.contentWindow ||
            event.data?.eventName !== 'helcim-pay-js-' + token) return;
        if (event.data.eventStatus === 'ABORTED') {
            message.textContent = 'Card payment was declined. Check your details in the secure window or check payment status below.';
            return;
        }
        if (event.data.eventStatus === 'HIDE') {
            frame.remove();
            if (!confirming) button.disabled = false;
            return;
        }
        if (event.data.eventStatus !== 'SUCCESS' || confirming) return;
        confirming = true;
        button.disabled = true;
        message.textContent = 'Confirming your payment. Please do not pay again.';
        try {
            let payload = event.data.eventMessage;
            if (typeof payload === 'string') payload = JSON.parse(payload);
            // Support both documented response envelopes. Only the transaction ID goes to our server.
            const data = payload?.data?.data ?? payload?.data ?? payload;
            const transaction = String(data?.transactionId ?? '');
            if (!/^\d{1,20}$/.test(transaction)) throw new Error('Pending confirmation');
            const response = await fetch(root.dataset.confirmUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf},
                body: JSON.stringify({transaction_id: transaction})
            });
            const result = await response.json();
            if (!response.ok || !result.paid) throw new Error('Pending confirmation');
            window.location.assign(result.redirect);
        } catch (_) {
            message.textContent = 'Your payment confirmation is pending. Do not pay again. Use Check payment status below.';
            frame.remove();
        }
    });
})();
