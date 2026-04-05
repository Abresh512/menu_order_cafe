document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.message-bar').forEach((message) => {
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'message-close';
        closeBtn.setAttribute('aria-label', 'Dismiss message');
        closeBtn.innerHTML = '&times;';

        closeBtn.addEventListener('click', () => dismissMessage(message));
        message.appendChild(closeBtn);

        const timeoutId = setTimeout(() => dismissMessage(message), 4000);
        message.addEventListener('mouseenter', () => clearTimeout(timeoutId));
    });

    function dismissMessage(element) {
        if (!element || element.classList.contains('message-hidden')) {
            return;
        }
        element.classList.add('hide', 'message-hidden');
        element.addEventListener('animationend', () => {
            element.remove();
        }, { once: true });
    }
});
