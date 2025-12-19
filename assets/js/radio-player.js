/**
 * FDTV Radio Player
 * Internet radio streaming player with analytics
 */

class RadioPlayer {
    constructor(stationData) {
        this.station = stationData;
        this.audio = document.getElementById('audioPlayer');
        this.playButton = document.getElementById('playButton');
        this.volumeSlider = document.getElementById('volumeSlider');
        this.volumeIcon = document.getElementById('volumeIcon');
        this.visualizer = document.getElementById('visualizer');
        this.trackTitle = document.getElementById('trackTitle');
        this.trackArtist = document.getElementById('trackArtist');
        this.listenerCount = document.getElementById('listenerCount');
        this.errorMessage = document.getElementById('errorMessage');

        this.isPlaying = false;
        this.isMuted = false;
        this.volume = 0.8;
        this.sessionId = null;
        this.pingInterval = null;
        this.metadataInterval = null;
        this.listenerInterval = null;

        this.init();
    }

    init() {
        // Set up audio
        this.audio.volume = this.volume;
        this.volumeSlider.value = this.volume * 100;

        // Event listeners
        this.playButton.addEventListener('click', () => this.togglePlay());

        this.volumeSlider.addEventListener('input', (e) => {
            this.setVolume(e.target.value / 100);
        });

        this.volumeIcon.addEventListener('click', () => this.toggleMute());

        // Quality selector
        document.querySelectorAll('.quality-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const url = e.target.dataset.url;
                this.changeStream(url);

                document.querySelectorAll('.quality-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
            });
        });

        // Audio events
        this.audio.addEventListener('playing', () => this.onPlaying());
        this.audio.addEventListener('pause', () => this.onPause());
        this.audio.addEventListener('error', (e) => this.onError(e));
        this.audio.addEventListener('waiting', () => this.onBuffering());
        this.audio.addEventListener('canplay', () => this.onCanPlay());

        // Start listener count updates
        this.updateListenerCount();
        this.listenerInterval = setInterval(() => this.updateListenerCount(), 30000);

        // Media session API for lock screen controls
        this.setupMediaSession();

        // Keyboard controls
        document.addEventListener('keydown', (e) => {
            if (e.code === 'Space') {
                e.preventDefault();
                this.togglePlay();
            }
        });
    }

    togglePlay() {
        if (this.isPlaying) {
            this.pause();
        } else {
            this.play();
        }
    }

    play() {
        if (!this.station.streamUrl) {
            this.showError('No stream URL configured');
            return;
        }

        this.hideError();
        this.playButton.classList.add('loading');

        // Set source if not already set
        if (!this.audio.src || this.audio.src !== this.station.streamUrl) {
            this.audio.src = this.station.streamUrl;
        }

        const playPromise = this.audio.play();

        if (playPromise !== undefined) {
            playPromise.then(() => {
                this.onPlaying();
            }).catch((error) => {
                console.error('Play error:', error);
                // Try fallback URL
                if (this.station.fallbackUrl && this.audio.src !== this.station.fallbackUrl) {
                    console.log('Trying fallback URL...');
                    this.audio.src = this.station.fallbackUrl;
                    this.audio.play().catch(e => this.showError('Unable to play stream'));
                } else {
                    this.showError('Click to play audio');
                }
            });
        }
    }

    pause() {
        this.audio.pause();
        this.onPause();
    }

    onPlaying() {
        this.isPlaying = true;
        this.playButton.classList.add('playing');
        this.playButton.classList.remove('loading');
        this.visualizer.classList.add('playing');
        this.hideError();

        // Start analytics session
        this.startSession();

        // Update media session
        if ('mediaSession' in navigator) {
            navigator.mediaSession.playbackState = 'playing';
        }
    }

    onPause() {
        this.isPlaying = false;
        this.playButton.classList.remove('playing');
        this.visualizer.classList.remove('playing');

        // End analytics session
        this.endSession();

        if ('mediaSession' in navigator) {
            navigator.mediaSession.playbackState = 'paused';
        }
    }

    onError(e) {
        console.error('Audio error:', e);
        this.playButton.classList.remove('loading');

        // Try fallback
        if (this.station.fallbackUrl && this.audio.src !== this.station.fallbackUrl) {
            console.log('Error occurred, trying fallback...');
            this.audio.src = this.station.fallbackUrl;
            this.audio.play().catch(() => {
                this.showError('Stream unavailable. Please try again later.');
            });
        } else {
            this.showError('Stream unavailable. Please try again later.');
        }
    }

    onBuffering() {
        this.playButton.classList.add('loading');
    }

    onCanPlay() {
        this.playButton.classList.remove('loading');
    }

    setVolume(value) {
        this.volume = value;
        this.audio.volume = value;
        this.isMuted = value === 0;
        this.updateVolumeIcon();
    }

    toggleMute() {
        if (this.isMuted) {
            this.audio.volume = this.volume || 0.8;
            this.volumeSlider.value = (this.volume || 0.8) * 100;
            this.isMuted = false;
        } else {
            this.audio.volume = 0;
            this.volumeSlider.value = 0;
            this.isMuted = true;
        }
        this.updateVolumeIcon();
    }

    updateVolumeIcon() {
        const icon = this.volumeIcon;
        if (this.isMuted || this.audio.volume === 0) {
            icon.innerHTML = `
                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                <line x1="23" y1="9" x2="17" y2="15" stroke="currentColor"/>
                <line x1="17" y1="9" x2="23" y2="15" stroke="currentColor"/>
            `;
        } else {
            icon.innerHTML = `
                <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
                <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/>
            `;
        }
    }

    changeStream(url) {
        const wasPlaying = this.isPlaying;

        if (wasPlaying) {
            this.audio.pause();
        }

        this.audio.src = url;
        this.station.streamUrl = url;

        if (wasPlaying) {
            this.audio.play().catch(e => this.showError('Unable to switch stream'));
        }
    }

    showError(message) {
        this.errorMessage.textContent = message;
        this.errorMessage.style.display = 'block';
    }

    hideError() {
        this.errorMessage.style.display = 'none';
    }

    // Analytics
    startSession() {
        const data = new FormData();
        data.append('action', 'start_session');
        data.append('station_id', this.station.id);
        data.append('type', 'radio');

        fetch('/api/radio-analytics.php', {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(result => {
            if (result.success && result.session_id) {
                this.sessionId = result.session_id;

                // Start ping interval
                this.pingInterval = setInterval(() => this.sendPing(), 30000);
            }
        })
        .catch(console.error);
    }

    sendPing() {
        if (!this.sessionId) return;

        const data = new FormData();
        data.append('action', 'ping');
        data.append('session_id', this.sessionId);
        data.append('station_id', this.station.id);

        if (navigator.sendBeacon) {
            navigator.sendBeacon('/api/radio-analytics.php', data);
        } else {
            fetch('/api/radio-analytics.php', {
                method: 'POST',
                body: data
            }).catch(() => {});
        }
    }

    endSession() {
        if (!this.sessionId) return;

        const data = new FormData();
        data.append('action', 'end_session');
        data.append('session_id', this.sessionId);
        data.append('station_id', this.station.id);

        if (navigator.sendBeacon) {
            navigator.sendBeacon('/api/radio-analytics.php', data);
        }

        if (this.pingInterval) {
            clearInterval(this.pingInterval);
            this.pingInterval = null;
        }

        this.sessionId = null;
    }

    updateListenerCount() {
        fetch(`/api/radio-analytics.php?action=get_listeners&station_id=${this.station.id}`)
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    this.listenerCount.textContent = result.count || 0;
                }
            })
            .catch(() => {
                this.listenerCount.textContent = '--';
            });
    }

    // Media Session API for lock screen / notification controls
    setupMediaSession() {
        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({
                title: this.station.name,
                artist: 'Live Radio',
                album: 'FDTV Radio',
                artwork: [
                    { src: '/assets/images/radio-icon-96.png', sizes: '96x96', type: 'image/png' },
                    { src: '/assets/images/radio-icon-128.png', sizes: '128x128', type: 'image/png' },
                    { src: '/assets/images/radio-icon-192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/assets/images/radio-icon-512.png', sizes: '512x512', type: 'image/png' }
                ]
            });

            navigator.mediaSession.setActionHandler('play', () => this.play());
            navigator.mediaSession.setActionHandler('pause', () => this.pause());
            navigator.mediaSession.setActionHandler('stop', () => this.pause());
        }
    }

    updateNowPlaying(title, artist) {
        if (this.trackTitle) {
            this.trackTitle.textContent = title || this.station.name;
        }
        if (this.trackArtist) {
            this.trackArtist.textContent = artist || '';
        }

        // Update media session
        if ('mediaSession' in navigator && navigator.mediaSession.metadata) {
            navigator.mediaSession.metadata.title = title || this.station.name;
            navigator.mediaSession.metadata.artist = artist || 'Live Radio';
        }
    }

    destroy() {
        this.endSession();

        if (this.listenerInterval) {
            clearInterval(this.listenerInterval);
        }
        if (this.metadataInterval) {
            clearInterval(this.metadataInterval);
        }

        this.audio.pause();
        this.audio.src = '';
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (typeof stationData !== 'undefined') {
        window.radioPlayer = new RadioPlayer(stationData);
    }
});

// Cleanup
window.addEventListener('beforeunload', function() {
    if (window.radioPlayer) {
        window.radioPlayer.destroy();
    }
});
