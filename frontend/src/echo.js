import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    window.Pusher = Pusher;

    // Xác định API base URL cho auth endpoint (production: https://api.ocean.pro.vn/api)
    const apiBase = import.meta.env.VITE_API_URL 
        || `${import.meta.env.VITE_REVERB_SCHEME ?? 'https'}://${import.meta.env.VITE_REVERB_HOST}`;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: `${apiBase}/broadcasting/auth`,
        authorizer: (channel, options) => {
            return {
                authorize: (socketId, callback) => {
                    axios.post(`${apiBase}/broadcasting/auth`, {
                        socket_id: socketId,
                        channel_name: channel.name
                    }, {
                        headers: {
                            Authorization: `Bearer ${sessionStorage.getItem('auth_token')}`,
                            Accept: 'application/json'
                        }
                    })
                    .then(response => {
                        callback(false, response.data);
                    })
                    .catch(error => {
                        callback(true, error);
                    });
                }
            };
        }
    });
} else {
    console.warn('[Echo] VITE_REVERB_APP_KEY chưa được cấu hình. WebSocket bị tắt.');
}

