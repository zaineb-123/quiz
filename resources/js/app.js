import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

/**
 * Celebration confetti — call from Alpine: x-init="window.qfConfetti($el)"
 * Respects prefers-reduced-motion. Pieces clean themselves up.
 */
window.qfConfetti = (host, count = 70) => {
    if (!host || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const colors = ['#5eead4', '#fbbf24', '#ff9494', '#2dd4bf', '#ffe3e1'];
    for (let i = 0; i < count; i++) {
        const el = document.createElement('span');
        const size = 6 + Math.random() * 8;
        el.className = 'confetti-piece';
        el.style.cssText = `left:${Math.random() * 100}%;width:${size}px;height:${size * (Math.random() > .5 ? 1 : .5)}px;` +
            `background:${colors[i % colors.length]};border-radius:${Math.random() > .5 ? '50%' : '2px'};` +
            `--dx:${(Math.random() - .5) * 160}px;--rot:${360 + Math.random() * 720}deg;` +
            `animation-duration:${2.2 + Math.random() * 2.2}s;animation-delay:${Math.random() * .8}s;`;
        host.appendChild(el);
        el.addEventListener('animationend', () => el.remove());
    }
};
