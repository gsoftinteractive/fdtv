/**
 * FDTV Live TV Player
 * Professional broadcast-style video player with playlist and live feed support
 */

class LiveTVPlayer {
    constructor(stationData) {
        this.station = stationData;
        this.videos = stationData.videos || [];
        this.mode = stationData.mode || 'playlist';
        this.liveFeed = stationData.liveFeed || null;
        this.currentIndex = 0;
        this.player = null;
        this.hlsInstance = null;
        this.isPlaying = false;
        this.isMuted = true; // Start muted for autoplay
        this.volume = 0.8;
        this.idleTimer = null;
        this.idleTimeout = 5000; // 5 seconds
        this.viewerCount = this.generateViewerCount();

        // Playlist engine settings
        this.playlistMode = stationData.playlistMode || 'sequential';
        this.jingleEnabled = stationData.jingleEnabled || false;
        this.advertEnabled = stationData.advertEnabled || false;
        this.jingleInterval = stationData.jingleInterval || 3;
        this.advertInterval = stationData.advertInterval || 5;
        this.jingles = stationData.jingles || [];

        // Jingle tracking
        this.videosSinceJingle = 0;
        this.videosSinceAdvert = 0;
        this.currentJingleIndex = 0;
        this.isPlayingJingle = false;
        this.nextVideoIndex = 0; // Track where to resume after jingle

        // Analytics tracking
        this.sessionId = null;
        this.sessionStartTime = Date.now();
        this.pingInterval = null;
        this.analyticsEnabled = true;

        this.init();
    }

    init() {
        // Get elements
        this.player = document.getElementById('tvPlayer');
        this.tvContainer = document.getElementById('tvContainer');
        this.loadingOverlay = document.getElementById('loadingOverlay');
        this.noContentOverlay = document.getElementById('noContentOverlay');
        this.unmuteOverlay = document.getElementById('unmuteOverlay');
        this.currentProgramEl = document.getElementById('currentProgram');
        this.nextProgramEl = document.getElementById('nextProgram');
        this.timeDisplay = document.getElementById('timeDisplay');
        this.muteBtn = document.getElementById('muteBtn');
        this.volumeSlider = document.getElementById('volumeSlider');
        this.fullscreenBtn = document.getElementById('fullscreenBtn');
        this.viewerNumberEl = document.getElementById('viewerNumber');
        this.liveFeedContainer = document.getElementById('liveFeedContainer');
        this.liveFeedIframe = document.getElementById('liveFeedIframe');
        this.liveFeedVideo = document.getElementById('liveFeedVideo');
        this.tickerBar = document.getElementById('tickerBar');
        this.tickerLabel = document.querySelector('.ticker-label-text');
        this.tickerText = document.querySelector('.ticker-text');
        this.tickerTrack = document.getElementById('tickerTrack');
        this.tickerContent = document.querySelector('.ticker-content');

        // Ticker mode (loaded from station database settings)
        this.isDoubleLineTicker = (this.station.ticker_mode === 'double');

        // Ticker color presets
        this.tickerColorPresets = [
            { name: 'Red', value: 'red', bg: '#dc2626', text: '#ffffff', label: 'BREAKING' },
            { name: 'Purple', value: 'purple', bg: '#7c3aed', text: '#ffffff', label: 'EVENTS' },
            { name: 'Green', value: 'green', bg: '#059669', text: '#ffffff', label: 'SCHEDULE' },
            { name: 'Blue', value: 'blue', bg: '#2563eb', text: '#ffffff', label: 'NEWS' },
            { name: 'Orange', value: 'orange', bg: '#ea580c', text: '#ffffff', label: 'ALERT' },
            { name: 'Pink', value: 'pink', bg: '#db2777', text: '#ffffff', label: 'SPECIAL' },
            { name: 'Teal', value: 'teal', bg: '#0d9488', text: '#ffffff', label: 'UPDATE' },
            { name: 'Indigo', value: 'indigo', bg: '#4f46e5', text: '#ffffff', label: 'INFO' }
        ];

        // Set current color from station database settings
        const stationColor = this.station.ticker_color || 'red';
        this.currentColorIndex = this.tickerColorPresets.findIndex(p => p.value === stationColor);
        if (this.currentColorIndex === -1) this.currentColorIndex = 0;

        // Check mode and content availability
        if (this.mode === 'live' && this.liveFeed) {
            this.initLiveFeedMode();
        } else if (this.videos.length === 0) {
            this.showNoContent();
            return;
        } else {
            this.initPlaylistMode();
        }

        // Set up event listeners
        this.setupEventListeners();

        // Start time update
        this.startTimeUpdate();

        // Start viewer count simulation
        this.startViewerCountSimulation();

        // Initialize analytics tracking
        this.initAnalytics();

        // Initialize ticker color change on click
        this.initTickerColorChange();

        // Initialize double-line ticker mode
        this.initDoubleLineTicker();

        // Initialize additional cool features
        this.initKeyboardShortcutsHelp();
        this.initPictureInPicture();
        this.initTickerSpeedControl();
        this.initLowerThirds();
        this.initSocialMediaBadges();
    }

    initDoubleLineTicker() {
        if (!this.tickerBar || !this.tickerTrack || !this.tickerContent) return;

        // Apply saved ticker mode
        if (this.isDoubleLineTicker) {
            this.enableDoubleLineTicker();
        }

        // Add keyboard shortcut (Ctrl/Cmd + T) to toggle ticker mode
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 't') {
                e.preventDefault();
                this.toggleTickerMode();
            }
        });

        // Add button to toggle ticker mode (optional UI control)
        this.createTickerModeToggleButton();
    }

    createTickerModeToggleButton() {
        const toggleBtn = document.createElement('button');
        toggleBtn.id = 'tickerModeToggle';
        toggleBtn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
            </svg>
        `;
        toggleBtn.title = 'Toggle ticker mode (Ctrl+T)';
        toggleBtn.style.cssText = `
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 5;
        `;

        toggleBtn.addEventListener('mouseenter', () => {
            toggleBtn.style.background = 'rgba(255, 255, 255, 0.2)';
        });

        toggleBtn.addEventListener('mouseleave', () => {
            toggleBtn.style.background = 'rgba(255, 255, 255, 0.1)';
        });

        toggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleTickerMode();
        });

        this.tickerBar.style.position = 'relative';
        this.tickerBar.appendChild(toggleBtn);
    }

    toggleTickerMode() {
        this.isDoubleLineTicker = !this.isDoubleLineTicker;
        localStorage.setItem('fdtv_ticker_double_line', this.isDoubleLineTicker);

        if (this.isDoubleLineTicker) {
            this.enableDoubleLineTicker();
        } else {
            this.disableDoubleLineTicker();
        }

        this.showTickerModeNotification();
    }

    enableDoubleLineTicker() {
        // Increase ticker bar height
        this.tickerBar.style.height = '72px';

        // Make ticker content flex column with two lines
        this.tickerContent.style.flexDirection = 'column';
        this.tickerContent.style.gap = '4px';
        this.tickerContent.style.padding = '8px 0';

        // Clone the ticker track for second line
        if (!document.getElementById('tickerTrack2')) {
            const originalText = this.tickerText.textContent;

            // Split text into two lines (or duplicate for demo)
            const midPoint = Math.floor(originalText.length / 2);
            const line1Text = originalText.substring(0, midPoint);
            const line2Text = originalText.substring(midPoint);

            // Update first line
            this.tickerText.textContent = line1Text;

            // Create second track
            const track2 = this.tickerTrack.cloneNode(true);
            track2.id = 'tickerTrack2';
            const track2Text = track2.querySelector('.ticker-text');
            track2Text.textContent = line2Text;

            this.tickerContent.appendChild(track2);
        }
    }

    disableDoubleLineTicker() {
        // Reset ticker bar height
        this.tickerBar.style.height = '44px';

        // Reset ticker content
        this.tickerContent.style.flexDirection = 'row';
        this.tickerContent.style.gap = '0';
        this.tickerContent.style.padding = '0';

        // Remove second track if exists
        const track2 = document.getElementById('tickerTrack2');
        if (track2) {
            track2.remove();
        }

        // Restore original text (you might want to store the original)
        if (this.station && this.station.ticker) {
            this.tickerText.textContent = this.station.ticker;
        }
    }

    showTickerModeNotification() {
        const mode = this.isDoubleLineTicker ? 'Double Line' : 'Single Line';
        const icon = this.isDoubleLineTicker
            ? '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/>'
            : '<line x1="3" y1="12" x2="21" y2="12"/>';

        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            z-index: 9999;
            backdrop-filter: blur(10px);
            border: 2px solid #7c3aed;
            animation: tickerNotificationFade 1.5s ease-out forwards;
        `;
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    ${icon}
                </svg>
                <span>Ticker Mode: ${mode}</span>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 1500);
    }

    initTickerColorChange() {
        if (!this.tickerBar) return;

        // Apply color and label from station database settings
        this.applyTickerColor(this.currentColorIndex);

        // Apply custom label from station settings
        if (this.tickerLabel && this.station.ticker_label) {
            this.tickerLabel.textContent = this.station.ticker_label;
        }
    }

    initTickerLabelEdit() {
        // This method is kept for compatibility but no longer allows editing
        // All ticker settings are now controlled from the admin dashboard
    }

    makeTickerLabelEditable() {
        const currentText = this.tickerLabel.textContent;

        // Create input field
        const input = document.createElement('input');
        input.type = 'text';
        input.value = currentText;
        input.maxLength = 15;
        input.style.cssText = `
            background: white;
            color: #000;
            border: 2px solid #7c3aed;
            padding: 6px 12px;
            font-weight: 800;
            font-size: 0.875rem;
            letter-spacing: 2px;
            text-align: center;
            width: 120px;
            outline: none;
            text-transform: uppercase;
        `;

        // Replace label with input
        const labelParent = this.tickerLabel.parentElement;
        const placeholder = document.createElement('span');
        placeholder.style.cssText = this.tickerLabel.style.cssText;
        labelParent.replaceChild(input, this.tickerLabel);

        // Focus and select
        input.focus();
        input.select();

        // Save on blur or enter
        const saveLabel = () => {
            let newText = input.value.trim().toUpperCase();
            if (!newText) newText = 'BREAKING';

            this.tickerLabel.textContent = newText;
            labelParent.replaceChild(this.tickerLabel, input);

            // Save to localStorage
            localStorage.setItem('fdtv_ticker_label', newText);

            // Show notification
            this.showTickerLabelNotification(newText);
        };

        input.addEventListener('blur', saveLabel);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveLabel();
            } else if (e.key === 'Escape') {
                this.tickerLabel.textContent = currentText;
                labelParent.replaceChild(this.tickerLabel, input);
            }
        });

        // Prevent ticker click event
        input.addEventListener('click', (e) => e.stopPropagation());
    }

    showTickerLabelNotification(label) {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            z-index: 9999;
            backdrop-filter: blur(10px);
            border: 2px solid #7c3aed;
            animation: tickerNotificationFade 1.5s ease-out forwards;
        `;
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                <span>Label updated: "${label}"</span>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 1500);
    }

    applyTickerColor(index) {
        const preset = this.tickerColorPresets[index];

        // Apply background color to ticker bar
        this.tickerBar.style.background = preset.bg;

        // Apply text color to ticker text
        if (this.tickerText) {
            this.tickerText.style.color = preset.text;
        }

        // Apply label color and text
        if (this.tickerLabel) {
            this.tickerLabel.style.color = preset.bg;
            this.tickerLabel.textContent = preset.label;
        }
    }

    showTickerColorNotification() {
        const preset = this.tickerColorPresets[this.currentColorIndex];

        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'ticker-color-notification';
        notification.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            z-index: 9999;
            backdrop-filter: blur(10px);
            border: 2px solid ${preset.bg};
            animation: tickerNotificationFade 1.5s ease-out forwards;
        `;
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 24px; height: 24px; background: ${preset.bg}; border-radius: 4px;"></div>
                <span>${preset.name} - ${preset.label}</span>
            </div>
        `;

        document.body.appendChild(notification);

        // Remove after animation
        setTimeout(() => {
            notification.remove();
        }, 1500);
    }

    initPlaylistMode() {
        // Hide live feed container, show video player
        this.player.style.display = 'block';
        if (this.liveFeedContainer) {
            this.liveFeedContainer.style.display = 'none';
        }

        // Start playback
        this.startPlayback();
    }

    initLiveFeedMode() {
        // Hide video player, show live feed container
        this.player.style.display = 'none';
        if (this.liveFeedContainer) {
            this.liveFeedContainer.style.display = 'block';
        }

        // Load live feed based on type
        this.loadLiveFeed();
    }

    loadLiveFeed() {
        const feed = this.liveFeed;
        if (!feed) {
            this.showNoContent();
            return;
        }

        this.showLoading();

        // Update program info for live feed
        this.currentProgramEl.textContent = feed.name || 'Live Stream';
        this.nextProgramEl.textContent = 'Live Broadcast';

        switch (feed.sourceType) {
            case 'youtube':
                this.loadYouTubeEmbed(feed.sourceUrl);
                break;
            case 'facebook':
                this.loadFacebookEmbed(feed.sourceUrl);
                break;
            case 'vimeo':
                this.loadVimeoEmbed(feed.sourceUrl);
                break;
            case 'hls':
                this.loadHLSStream(feed.sourceUrl);
                break;
            case 'mp4':
                this.loadMP4Stream(feed.sourceUrl);
                break;
            case 'iframe':
            default:
                this.loadGenericIframe(feed.sourceUrl);
                break;
        }
    }

    loadYouTubeEmbed(url) {
        // Extract video ID from various YouTube URL formats
        let videoId = null;

        // Handle youtube.com/watch?v=ID
        const watchMatch = url.match(/[?&]v=([^&]+)/);
        if (watchMatch) {
            videoId = watchMatch[1];
        }

        // Handle youtu.be/ID
        const shortMatch = url.match(/youtu\.be\/([^?&]+)/);
        if (shortMatch) {
            videoId = shortMatch[1];
        }

        // Handle youtube.com/embed/ID
        const embedMatch = url.match(/youtube\.com\/embed\/([^?&]+)/);
        if (embedMatch) {
            videoId = embedMatch[1];
        }

        // Handle youtube.com/live/ID
        const liveMatch = url.match(/youtube\.com\/live\/([^?&]+)/);
        if (liveMatch) {
            videoId = liveMatch[1];
        }

        if (videoId) {
            const embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1&mute=1&enablejsapi=1&rel=0&modestbranding=1`;
            this.liveFeedIframe.src = embedUrl;
            this.liveFeedIframe.style.display = 'block';
            this.liveFeedVideo.style.display = 'none';
            this.hideLoading();
            this.showUnmuteOverlay();
        } else {
            // Fallback to generic iframe
            this.loadGenericIframe(url);
        }
    }

    loadFacebookEmbed(url) {
        // Facebook video embed
        const encodedUrl = encodeURIComponent(url);
        const embedUrl = `https://www.facebook.com/plugins/video.php?href=${encodedUrl}&show_text=false&autoplay=true&mute=true`;

        this.liveFeedIframe.src = embedUrl;
        this.liveFeedIframe.style.display = 'block';
        this.liveFeedVideo.style.display = 'none';
        this.hideLoading();
        this.showUnmuteOverlay();
    }

    loadVimeoEmbed(url) {
        // Extract Vimeo video ID
        let videoId = null;

        const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
        if (vimeoMatch) {
            videoId = vimeoMatch[1];
        }

        const playerMatch = url.match(/player\.vimeo\.com\/video\/(\d+)/);
        if (playerMatch) {
            videoId = playerMatch[1];
        }

        if (videoId) {
            const embedUrl = `https://player.vimeo.com/video/${videoId}?autoplay=1&muted=1&background=0`;
            this.liveFeedIframe.src = embedUrl;
            this.liveFeedIframe.style.display = 'block';
            this.liveFeedVideo.style.display = 'none';
            this.hideLoading();
            this.showUnmuteOverlay();
        } else {
            this.loadGenericIframe(url);
        }
    }

    loadHLSStream(url) {
        // Use HLS.js for HLS streams
        this.liveFeedIframe.style.display = 'none';
        this.liveFeedVideo.style.display = 'block';

        // Create video element for HLS
        const videoEl = document.createElement('video');
        videoEl.id = 'hlsPlayer';
        videoEl.autoplay = true;
        videoEl.muted = true;
        videoEl.playsInline = true;
        this.liveFeedVideo.innerHTML = '';
        this.liveFeedVideo.appendChild(videoEl);

        if (typeof Hls !== 'undefined' && Hls.isSupported()) {
            this.hlsInstance = new Hls({
                enableWorker: true,
                lowLatencyMode: true
            });

            this.hlsInstance.loadSource(url);
            this.hlsInstance.attachMedia(videoEl);

            this.hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => {
                videoEl.play().then(() => {
                    this.hideLoading();
                    this.showUnmuteOverlay();
                }).catch(e => {
                    console.log('HLS autoplay prevented:', e);
                    this.hideLoading();
                    this.showUnmuteOverlay();
                });
            });

            this.hlsInstance.on(Hls.Events.ERROR, (event, data) => {
                console.error('HLS Error:', data);
                if (data.fatal) {
                    this.showNoContent();
                }
            });
        } else if (videoEl.canPlayType('application/vnd.apple.mpegurl')) {
            // Native HLS support (Safari)
            videoEl.src = url;
            videoEl.addEventListener('loadedmetadata', () => {
                videoEl.play().then(() => {
                    this.hideLoading();
                    this.showUnmuteOverlay();
                }).catch(e => {
                    this.hideLoading();
                    this.showUnmuteOverlay();
                });
            });
        } else {
            console.error('HLS not supported');
            this.showNoContent();
        }
    }

    loadMP4Stream(url) {
        this.liveFeedIframe.style.display = 'none';
        this.liveFeedVideo.style.display = 'block';

        const videoEl = document.createElement('video');
        videoEl.id = 'mp4Player';
        videoEl.autoplay = true;
        videoEl.muted = true;
        videoEl.playsInline = true;
        videoEl.loop = true;
        videoEl.src = url;

        this.liveFeedVideo.innerHTML = '';
        this.liveFeedVideo.appendChild(videoEl);

        videoEl.addEventListener('canplay', () => {
            this.hideLoading();
            this.showUnmuteOverlay();
        });

        videoEl.addEventListener('error', () => {
            console.error('MP4 load error');
            this.showNoContent();
        });

        videoEl.play().catch(e => {
            console.log('MP4 autoplay prevented:', e);
            this.showUnmuteOverlay();
        });
    }

    loadGenericIframe(url) {
        this.liveFeedIframe.src = url;
        this.liveFeedIframe.style.display = 'block';
        this.liveFeedVideo.style.display = 'none';

        this.liveFeedIframe.onload = () => {
            this.hideLoading();
        };

        // Fallback hide loading after timeout
        setTimeout(() => this.hideLoading(), 3000);
    }

    setupEventListeners() {
        // Video events (for playlist mode)
        this.player.addEventListener('ended', () => this.playNext());
        this.player.addEventListener('error', (e) => this.handleError(e));
        this.player.addEventListener('playing', () => this.onPlaying());
        this.player.addEventListener('waiting', () => this.onBuffering());
        this.player.addEventListener('canplay', () => this.onCanPlay());

        // Prevent right-click context menu
        this.player.addEventListener('contextmenu', (e) => e.preventDefault());
        if (this.liveFeedContainer) {
            this.liveFeedContainer.addEventListener('contextmenu', (e) => e.preventDefault());
        }

        // Unmute overlay click
        this.unmuteOverlay.addEventListener('click', () => this.unmute());
        document.getElementById('unmuteBtn').addEventListener('click', (e) => {
            e.stopPropagation();
            this.unmute();
        });

        // Mute button
        this.muteBtn.addEventListener('click', () => this.toggleMute());

        // Volume slider
        this.volumeSlider.addEventListener('input', (e) => {
            this.setVolume(e.target.value / 100);
        });

        // Fullscreen button
        this.fullscreenBtn.addEventListener('click', () => this.toggleFullscreen());

        // Keyboard controls
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));

        // Mouse/touch activity for idle detection
        this.tvContainer.addEventListener('mousemove', () => this.resetIdleTimer());
        this.tvContainer.addEventListener('touchstart', () => this.resetIdleTimer());
        this.tvContainer.addEventListener('click', () => this.resetIdleTimer());

        // Fullscreen change event
        document.addEventListener('fullscreenchange', () => this.onFullscreenChange());
        document.addEventListener('webkitfullscreenchange', () => this.onFullscreenChange());

        // Page visibility for pause/resume
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // Page is hidden, keep playing (it's a TV station!)
            } else {
                // Page is visible again
                if (this.mode === 'playlist' && this.player.paused && this.isPlaying) {
                    this.player.play().catch(() => {});
                }
            }
        });
    }

    startPlayback() {
        // Determine starting video based on time (simulate continuous broadcast)
        this.currentIndex = this.calculateCurrentVideoIndex();
        this.loadVideo(this.currentIndex);
    }

    calculateCurrentVideoIndex() {
        // Simple approach: use current time to pick a video
        // This simulates that the station has been running continuously
        if (this.videos.length === 0) return 0;

        const now = new Date();
        const minuteOfDay = now.getHours() * 60 + now.getMinutes();
        return minuteOfDay % this.videos.length;
    }

    loadVideo(index) {
        if (index < 0 || index >= this.videos.length) {
            index = 0;
        }

        this.currentIndex = index;
        const video = this.videos[index];

        this.showLoading();

        // Update program info
        this.updateProgramInfo();

        // Track video view
        if (video.id) {
            this.trackVideoView(video.id, video.title);
        }

        // Load video
        this.player.src = video.url;
        this.player.muted = this.isMuted;
        this.player.volume = this.volume;

        // Attempt autoplay
        const playPromise = this.player.play();

        if (playPromise !== undefined) {
            playPromise.then(() => {
                this.isPlaying = true;
                if (this.isMuted) {
                    this.showUnmuteOverlay();
                }
            }).catch((error) => {
                console.log('Autoplay prevented:', error);
                // Show unmute overlay - user needs to interact
                this.showUnmuteOverlay();
            });
        }
    }

    playNext() {
        // Add transition effect
        document.getElementById('videoWrapper').classList.add('transitioning');

        setTimeout(() => {
            // If we just finished playing a jingle, go back to videos
            if (this.isPlayingJingle) {
                this.isPlayingJingle = false;
                this.loadVideo(this.nextVideoIndex);
            } else {
                // Increment video counter
                this.videosSinceJingle++;
                this.videosSinceAdvert++;

                // Check if it's time to play a jingle
                if (this.shouldPlayJingle()) {
                    this.playJingle();
                } else if (this.shouldPlayAdvert()) {
                    this.playAdvert();
                } else {
                    // Normal video progression
                    this.currentIndex = (this.currentIndex + 1) % this.videos.length;
                    this.loadVideo(this.currentIndex);
                }
            }

            setTimeout(() => {
                document.getElementById('videoWrapper').classList.remove('transitioning');
            }, 500);
        }, 100);
    }

    shouldPlayJingle() {
        if (!this.jingleEnabled || this.jingles.length === 0) return false;
        return this.videosSinceJingle >= this.jingleInterval;
    }

    shouldPlayAdvert() {
        if (!this.advertEnabled) return false;
        // Get advert jingles
        const adverts = this.jingles.filter(j => j.type === 'advert');
        if (adverts.length === 0) return false;
        return this.videosSinceAdvert >= this.advertInterval;
    }

    playJingle() {
        // Get station_id or jingle type jingles
        const jingleTypes = this.jingles.filter(j => j.type === 'station_id' || j.type === 'jingle');
        if (jingleTypes.length === 0) {
            // No jingles, just play next video
            this.currentIndex = (this.currentIndex + 1) % this.videos.length;
            this.loadVideo(this.currentIndex);
            return;
        }

        // Store next video index to resume after jingle
        this.nextVideoIndex = (this.currentIndex + 1) % this.videos.length;
        this.isPlayingJingle = true;
        this.videosSinceJingle = 0;

        // Pick a jingle (rotate through them)
        const jingle = jingleTypes[this.currentJingleIndex % jingleTypes.length];
        this.currentJingleIndex++;

        // Load and play jingle
        this.loadJingle(jingle);
    }

    playAdvert() {
        const adverts = this.jingles.filter(j => j.type === 'advert');
        if (adverts.length === 0) {
            this.currentIndex = (this.currentIndex + 1) % this.videos.length;
            this.loadVideo(this.currentIndex);
            return;
        }

        this.nextVideoIndex = (this.currentIndex + 1) % this.videos.length;
        this.isPlayingJingle = true;
        this.videosSinceAdvert = 0;

        // Pick a random advert
        const advert = adverts[Math.floor(Math.random() * adverts.length)];
        this.loadJingle(advert);
    }

    loadJingle(jingle) {
        this.showLoading();

        // Update program info to show jingle
        this.currentProgramEl.textContent = jingle.title;
        this.nextProgramEl.textContent = this.videos[this.nextVideoIndex]?.title || 'Coming Up';

        // Load jingle video
        this.player.src = jingle.url;
        this.player.muted = this.isMuted;
        this.player.volume = this.volume;

        const playPromise = this.player.play();
        if (playPromise !== undefined) {
            playPromise.then(() => {
                this.isPlaying = true;
            }).catch((error) => {
                console.log('Jingle autoplay prevented:', error);
                this.showUnmuteOverlay();
            });
        }
    }

    updateProgramInfo() {
        if (this.mode === 'live' && this.liveFeed) {
            this.currentProgramEl.textContent = this.liveFeed.name || 'Live Stream';
            this.nextProgramEl.textContent = 'Live Broadcast';
            return;
        }

        const current = this.videos[this.currentIndex];
        const nextIndex = (this.currentIndex + 1) % this.videos.length;
        const next = this.videos[nextIndex];

        this.currentProgramEl.textContent = current ? current.title : 'Unknown';
        this.nextProgramEl.textContent = next ? next.title : 'Unknown';
    }

    showLoading() {
        this.loadingOverlay.style.display = 'flex';
    }

    hideLoading() {
        this.loadingOverlay.style.display = 'none';
    }

    showNoContent() {
        this.loadingOverlay.style.display = 'none';
        this.noContentOverlay.style.display = 'flex';

        // Update message based on mode
        const noContentH2 = this.noContentOverlay.querySelector('h2');
        const noContentP = this.noContentOverlay.querySelector('p');

        if (this.mode === 'live') {
            if (noContentH2) noContentH2.textContent = 'No Live Feed';
            if (noContentP) noContentP.textContent = 'No active live stream is configured for this station.';
        } else {
            if (noContentH2) noContentH2.textContent = 'No Content Available';
            if (noContentP) noContentP.textContent = "This station hasn't uploaded any videos yet.";
        }
    }

    showUnmuteOverlay() {
        this.unmuteOverlay.style.display = 'flex';
    }

    hideUnmuteOverlay() {
        this.unmuteOverlay.style.display = 'none';
    }

    unmute() {
        this.isMuted = false;
        this.hideUnmuteOverlay();
        this.updateMuteButton();

        if (this.mode === 'playlist') {
            this.player.muted = false;
            this.player.volume = this.volume;
            if (this.player.paused) {
                this.player.play().catch(() => {});
            }
        } else {
            // For live feeds, try to unmute the embedded content
            const hlsPlayer = document.getElementById('hlsPlayer');
            const mp4Player = document.getElementById('mp4Player');

            if (hlsPlayer) {
                hlsPlayer.muted = false;
                hlsPlayer.volume = this.volume;
            }
            if (mp4Player) {
                mp4Player.muted = false;
                mp4Player.volume = this.volume;
            }

            // For iframes, we can't control mute state directly
            // User will need to unmute within the iframe
        }
    }

    toggleMute() {
        this.isMuted = !this.isMuted;

        if (this.mode === 'playlist') {
            this.player.muted = this.isMuted;
        } else {
            const hlsPlayer = document.getElementById('hlsPlayer');
            const mp4Player = document.getElementById('mp4Player');

            if (hlsPlayer) hlsPlayer.muted = this.isMuted;
            if (mp4Player) mp4Player.muted = this.isMuted;
        }

        this.updateMuteButton();
    }

    updateMuteButton() {
        const iconOn = this.muteBtn.querySelector('.icon-volume-on');
        const iconOff = this.muteBtn.querySelector('.icon-volume-off');

        if (this.isMuted) {
            iconOn.style.display = 'none';
            iconOff.style.display = 'block';
        } else {
            iconOn.style.display = 'block';
            iconOff.style.display = 'none';
        }
    }

    setVolume(value) {
        this.volume = value;

        if (this.mode === 'playlist') {
            this.player.volume = value;
        } else {
            const hlsPlayer = document.getElementById('hlsPlayer');
            const mp4Player = document.getElementById('mp4Player');

            if (hlsPlayer) hlsPlayer.volume = value;
            if (mp4Player) mp4Player.volume = value;
        }

        if (value === 0) {
            this.isMuted = true;
        } else if (this.isMuted) {
            this.isMuted = false;
            if (this.mode === 'playlist') {
                this.player.muted = false;
            } else {
                const hlsPlayer = document.getElementById('hlsPlayer');
                const mp4Player = document.getElementById('mp4Player');
                if (hlsPlayer) hlsPlayer.muted = false;
                if (mp4Player) mp4Player.muted = false;
            }
        }

        this.updateMuteButton();
    }

    toggleFullscreen() {
        if (document.fullscreenElement || document.webkitFullscreenElement) {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            }
        } else {
            if (this.tvContainer.requestFullscreen) {
                this.tvContainer.requestFullscreen();
            } else if (this.tvContainer.webkitRequestFullscreen) {
                this.tvContainer.webkitRequestFullscreen();
            }
        }
    }

    onFullscreenChange() {
        const isFullscreen = document.fullscreenElement || document.webkitFullscreenElement;
        const iconEnter = this.fullscreenBtn.querySelector('.icon-fullscreen');
        const iconExit = this.fullscreenBtn.querySelector('.icon-exit-fullscreen');

        if (isFullscreen) {
            iconEnter.style.display = 'none';
            iconExit.style.display = 'block';
        } else {
            iconEnter.style.display = 'block';
            iconExit.style.display = 'none';
        }
    }

    handleKeyboard(e) {
        switch(e.key) {
            case 'm':
            case 'M':
                this.toggleMute();
                break;
            case 'f':
            case 'F':
                this.toggleFullscreen();
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.setVolume(Math.min(1, this.volume + 0.1));
                this.volumeSlider.value = this.volume * 100;
                break;
            case 'ArrowDown':
                e.preventDefault();
                this.setVolume(Math.max(0, this.volume - 0.1));
                this.volumeSlider.value = this.volume * 100;
                break;
            // Prevent seeking with arrow keys
            case 'ArrowLeft':
            case 'ArrowRight':
                e.preventDefault();
                break;
            case ' ':
                e.preventDefault();
                // Don't allow pause - it's live TV!
                break;
        }
    }

    onPlaying() {
        this.hideLoading();
        this.isPlaying = true;
    }

    onBuffering() {
        // Could show a buffering indicator here
    }

    onCanPlay() {
        this.hideLoading();
    }

    handleError(e) {
        console.error('Video error:', e);
        // Try next video after a short delay
        setTimeout(() => this.playNext(), 2000);
    }

    // Idle timer for hiding UI
    resetIdleTimer() {
        this.tvContainer.classList.remove('hide-cursor');

        clearTimeout(this.idleTimer);

        this.idleTimer = setTimeout(() => {
            this.tvContainer.classList.add('hide-cursor');
        }, this.idleTimeout);
    }

    // Time display update
    startTimeUpdate() {
        const updateTime = () => {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            this.timeDisplay.querySelector('.time-value').textContent = `${hours}:${minutes}`;
        };

        updateTime();
        setInterval(updateTime, 1000);

        // Make time display draggable
        this.makeTimeDisplayDraggable();
    }

    makeTimeDisplayDraggable() {
        // Load clock position from station database settings
        const xOffset = parseInt(this.station.clock_position_x) || 0;
        const yOffset = parseInt(this.station.clock_position_y) || 0;

        // Apply station-configured position
        this.setTimeDisplayPosition(xOffset, yOffset);

        // Clock is no longer draggable - position controlled by admin dashboard
        // Remove grab cursor
        if (this.timeDisplay) {
            this.timeDisplay.style.cursor = 'default';
        }
    }

    setTimeDisplayPosition(x, y) {
        this.timeDisplay.style.transform = `translate(${x}px, ${y}px) translateX(-50%)`;
        this.timeDisplay.style.left = '50%';
        this.timeDisplay.style.top = '20px';
    }

    // Viewer count simulation
    generateViewerCount() {
        // Base count between 50-500
        return Math.floor(Math.random() * 450) + 50;
    }

    startViewerCountSimulation() {
        const updateViewerCount = () => {
            // Randomly adjust viewer count
            const change = Math.floor(Math.random() * 20) - 10;
            this.viewerCount = Math.max(10, this.viewerCount + change);
            this.viewerNumberEl.textContent = this.viewerCount.toLocaleString();
        };

        updateViewerCount();
        setInterval(updateViewerCount, 5000);
    }

    // Analytics tracking methods
    initAnalytics() {
        if (!this.analyticsEnabled || !this.station.id) return;

        // Start analytics session
        this.startAnalyticsSession();

        // Set up ping interval (every 30 seconds)
        this.pingInterval = setInterval(() => {
            this.sendAnalyticsPing();
        }, 30000);

        // Track when page is about to unload
        window.addEventListener('beforeunload', () => {
            this.endAnalyticsSession();
        });

        // Track visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.sendAnalyticsPing(); // Send one last ping when hidden
            }
        });
    }

    startAnalyticsSession() {
        const data = new FormData();
        data.append('action', 'start_session');
        data.append('station_id', this.station.id);

        fetch('/api/analytics.php', {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(result => {
            if (result.success && result.session_id) {
                this.sessionId = result.session_id;
                console.log('Analytics session started:', this.sessionId);
            }
        })
        .catch(error => {
            console.log('Analytics start error:', error);
        });
    }

    sendAnalyticsPing() {
        if (!this.sessionId) return;

        const data = new FormData();
        data.append('action', 'ping');
        data.append('session_id', this.sessionId);
        data.append('station_id', this.station.id);

        // Use sendBeacon for better reliability during page unload
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/api/analytics.php', data);
        } else {
            fetch('/api/analytics.php', {
                method: 'POST',
                body: data
            }).catch(() => {});
        }
    }

    endAnalyticsSession() {
        if (!this.sessionId) return;

        const data = new FormData();
        data.append('action', 'end_session');
        data.append('session_id', this.sessionId);
        data.append('station_id', this.station.id);

        // Calculate watch time
        const watchTime = Math.floor((Date.now() - this.sessionStartTime) / 1000);
        data.append('watch_time', watchTime);

        // Use sendBeacon for reliability during page unload
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/api/analytics.php', data);
        } else {
            // Fallback synchronous request (not ideal but necessary)
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/api/analytics.php', false);
            xhr.send(data);
        }

        // Clear ping interval
        if (this.pingInterval) {
            clearInterval(this.pingInterval);
            this.pingInterval = null;
        }

        this.sessionId = null;
    }

    trackVideoView(videoId, videoTitle) {
        if (!this.analyticsEnabled || !this.station.id) return;

        const data = new FormData();
        data.append('action', 'video_view');
        data.append('station_id', this.station.id);
        data.append('video_id', videoId);
        data.append('video_title', videoTitle || '');
        if (this.sessionId) {
            data.append('session_id', this.sessionId);
        }

        fetch('/api/analytics.php', {
            method: 'POST',
            body: data
        }).catch(() => {});
    }

    // Keyboard Shortcuts Help Panel
    initKeyboardShortcutsHelp() {
        // Add keyboard listener for help panel (press '?' or 'H')
        document.addEventListener('keydown', (e) => {
            if (e.key === '?' || (e.key === 'h' && !e.ctrlKey && !e.metaKey)) {
                e.preventDefault();
                this.toggleKeyboardHelp();
            }
        });
    }

    toggleKeyboardHelp() {
        let helpPanel = document.getElementById('keyboardHelpPanel');

        if (helpPanel) {
            helpPanel.remove();
            return;
        }

        helpPanel = document.createElement('div');
        helpPanel.id = 'keyboardHelpPanel';
        helpPanel.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.95);
            color: white;
            padding: 32px;
            border-radius: 16px;
            max-width: 600px;
            width: 90%;
            z-index: 10000;
            backdrop-filter: blur(20px);
            border: 2px solid rgba(124, 58, 237, 0.5);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
        `;

        helpPanel.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h2 style="margin: 0; font-size: 1.5rem;">⌨️ Keyboard Shortcuts</h2>
                <button id="closeHelpBtn" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; opacity: 0.7; transition: opacity 0.2s;">✕</button>
            </div>
            <div style="display: grid; gap: 12px; font-size: 0.95rem;">
                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <kbd style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; text-align: center;">M</kbd>
                    <span>Mute/Unmute audio</span>
                </div>
                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <kbd style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; text-align: center;">F</kbd>
                    <span>Toggle fullscreen</span>
                </div>
                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <kbd style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; text-align: center;">↑ / ↓</kbd>
                    <span>Volume up/down</span>
                </div>
                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <kbd style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; text-align: center;">Ctrl + T</kbd>
                    <span>Toggle single/double line ticker</span>
                </div>
                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <kbd style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; text-align: center;">Ctrl + P</kbd>
                    <span>Toggle Picture-in-Picture</span>
                </div>
                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <kbd style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; text-align: center;">+/-</kbd>
                    <span>Increase/decrease ticker speed</span>
                </div>
                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <kbd style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; text-align: center;">L</kbd>
                    <span>Show/hide lower third</span>
                </div>
                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <kbd style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; text-align: center;">Ctrl + L</kbd>
                    <span>Edit lower third</span>
                </div>
                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <kbd style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; text-align: center;">@</kbd>
                    <span>Show/hide social badges</span>
                </div>
                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <kbd style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; text-align: center;">Ctrl + @</kbd>
                    <span>Edit social badges</span>
                </div>
                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 16px; padding: 8px 0;">
                    <kbd style="background: rgba(255,255,255,0.1); padding: 4px 8px; border-radius: 4px; text-align: center;">? or H</kbd>
                    <span>Show this help panel</span>
                </div>
            </div>
            <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.1); text-align: center; color: rgba(255,255,255,0.6); font-size: 0.875rem;">
                Click anywhere to close
            </div>
        `;

        document.body.appendChild(helpPanel);

        // Close button
        document.getElementById('closeHelpBtn').addEventListener('click', (e) => {
            e.stopPropagation();
            helpPanel.remove();
        });

        // Close on click
        helpPanel.addEventListener('click', () => {
            helpPanel.remove();
        });

        // Prevent clicks inside from closing
        helpPanel.querySelector('div').addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    // Picture-in-Picture Mode
    initPictureInPicture() {
        // Check if PiP is supported
        if (!document.pictureInPictureEnabled) {
            return;
        }

        // Add keyboard shortcut (Ctrl/Cmd + P)
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                this.togglePictureInPicture();
            }
        });

        // Add PiP button to controls
        this.addPiPButton();
    }

    addPiPButton() {
        const pipBtn = document.createElement('button');
        pipBtn.className = 'control-btn';
        pipBtn.id = 'pipBtn';
        pipBtn.title = 'Picture-in-Picture (Ctrl+P)';
        pipBtn.innerHTML = `
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2"/>
                <rect x="13" y="11" width="7" height="6" rx="1" fill="currentColor"/>
            </svg>
        `;

        pipBtn.addEventListener('click', () => this.togglePictureInPicture());

        // Add to control bar
        const controlRight = document.querySelector('.control-right');
        if (controlRight) {
            controlRight.insertBefore(pipBtn, controlRight.firstChild);
        }
    }

    async togglePictureInPicture() {
        try {
            if (document.pictureInPictureElement) {
                await document.exitPictureInPicture();
            } else {
                if (this.mode === 'playlist' && this.player) {
                    await this.player.requestPictureInPicture();
                }
            }
        } catch (error) {
            console.log('PiP error:', error);
        }
    }

    // Ticker Speed Control
    initTickerSpeedControl() {
        if (!this.tickerTrack) return;

        // Load ticker speed from station database settings
        this.tickerSpeed = parseInt(this.station.ticker_speed) || 60;
        this.updateTickerSpeed();

        // Speed is no longer adjustable by viewers - controlled by admin dashboard
    }

    adjustTickerSpeed(change) {
        this.tickerSpeed = Math.max(20, Math.min(120, this.tickerSpeed + change));
        localStorage.setItem('fdtv_ticker_speed', this.tickerSpeed);
        this.updateTickerSpeed();
        this.showTickerSpeedNotification();
    }

    updateTickerSpeed() {
        if (this.tickerTrack) {
            this.tickerTrack.style.animationDuration = `${this.tickerSpeed}s`;
        }

        const track2 = document.getElementById('tickerTrack2');
        if (track2) {
            track2.style.animationDuration = `${this.tickerSpeed}s`;
        }
    }

    showTickerSpeedNotification() {
        const speedLabel = this.tickerSpeed <= 30 ? 'Very Fast' :
                          this.tickerSpeed <= 45 ? 'Fast' :
                          this.tickerSpeed <= 70 ? 'Normal' :
                          this.tickerSpeed <= 90 ? 'Slow' : 'Very Slow';

        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            z-index: 9999;
            backdrop-filter: blur(10px);
            border: 2px solid #7c3aed;
            animation: tickerNotificationFade 1.5s ease-out forwards;
        `;
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 12px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
                <span>Ticker Speed: ${speedLabel}</span>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 1500);
    }

    // Lower Thirds Feature
    initLowerThirds() {
        this.currentLowerThird = null;
        this.lowerThirdVisible = false;

        // Load lower thirds presets from station database settings
        try {
            this.lowerThirds = JSON.parse(this.station.lower_thirds_presets || '[]');
        } catch (e) {
            this.lowerThirds = [];
        }

        // Add default presets if empty
        if (this.lowerThirds.length === 0) {
            this.lowerThirds = [
                { name: 'John Smith', title: 'News Anchor', style: 'modern' },
                { name: 'Jane Doe', title: 'Weather Reporter', style: 'bold' },
                { name: 'Alex Johnson', title: 'Sports Analyst', style: 'news' }
            ];
        }

        // Keyboard shortcuts for lower thirds
        document.addEventListener('keydown', (e) => {
            // L key - Show/hide current lower third
            if (e.key === 'l' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                this.toggleLowerThird();
            }
            // Ctrl+L - Open lower third editor
            else if ((e.ctrlKey || e.metaKey) && e.key === 'l') {
                e.preventDefault();
                this.openLowerThirdEditor();
            }
            // Ctrl+1-5 - Quick select preset
            else if ((e.ctrlKey || e.metaKey) && e.key >= '1' && e.key <= '5') {
                e.preventDefault();
                const index = parseInt(e.key) - 1;
                if (this.lowerThirds[index]) {
                    this.currentLowerThird = this.lowerThirds[index];
                    this.showLowerThird();
                }
            }
        });
    }

    toggleLowerThird() {
        if (this.lowerThirdVisible) {
            this.hideLowerThird();
        } else {
            if (!this.currentLowerThird && this.lowerThirds.length > 0) {
                this.currentLowerThird = this.lowerThirds[0];
            }
            this.showLowerThird();
        }
    }

    showLowerThird() {
        if (!this.currentLowerThird) return;

        // Remove existing lower third
        this.hideLowerThird();

        const lt = document.createElement('div');
        lt.id = 'lowerThirdOverlay';
        lt.className = 'lower-third-container';

        const style = this.currentLowerThird.style || 'modern';

        if (style === 'modern') {
            lt.innerHTML = `
                <div class="lower-third modern-style">
                    <div class="lt-name">${this.escapeHtml(this.currentLowerThird.name)}</div>
                    <div class="lt-title">${this.escapeHtml(this.currentLowerThird.title)}</div>
                </div>
            `;
        } else if (style === 'bold') {
            lt.innerHTML = `
                <div class="lower-third bold-style">
                    <div class="lt-accent"></div>
                    <div class="lt-content">
                        <div class="lt-name">${this.escapeHtml(this.currentLowerThird.name)}</div>
                        <div class="lt-title">${this.escapeHtml(this.currentLowerThird.title)}</div>
                    </div>
                </div>
            `;
        } else if (style === 'news') {
            lt.innerHTML = `
                <div class="lower-third news-style">
                    <div class="lt-box">
                        <div class="lt-name">${this.escapeHtml(this.currentLowerThird.name)}</div>
                        <div class="lt-title">${this.escapeHtml(this.currentLowerThird.title)}</div>
                    </div>
                </div>
            `;
        }

        this.tvContainer.appendChild(lt);
        this.lowerThirdVisible = true;

        // Animate in
        setTimeout(() => {
            lt.classList.add('visible');
        }, 50);

        // Show notification
        this.showNotification(`Lower Third: ${this.currentLowerThird.name}`);
    }

    hideLowerThird() {
        const existing = document.getElementById('lowerThirdOverlay');
        if (existing) {
            existing.classList.remove('visible');
            setTimeout(() => existing.remove(), 500);
        }
        this.lowerThirdVisible = false;
    }

    openLowerThirdEditor() {
        const editor = document.createElement('div');
        editor.id = 'lowerThirdEditor';
        editor.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.95);
            color: white;
            padding: 32px;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
            z-index: 10000;
            backdrop-filter: blur(20px);
            border: 2px solid rgba(124, 58, 237, 0.5);
        `;

        const current = this.currentLowerThird || { name: '', title: '', style: 'modern' };

        editor.innerHTML = `
            <h2 style="margin: 0 0 24px 0; font-size: 1.5rem;">✏️ Edit Lower Third</h2>
            <div style="display: grid; gap: 16px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-size: 0.9rem; opacity: 0.8;">Name</label>
                    <input type="text" id="ltName" value="${this.escapeHtml(current.name)}"
                        style="width: 100%; padding: 12px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
                        border-radius: 8px; color: white; font-size: 1rem;" maxlength="50">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-size: 0.9rem; opacity: 0.8;">Title</label>
                    <input type="text" id="ltTitle" value="${this.escapeHtml(current.title)}"
                        style="width: 100%; padding: 12px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
                        border-radius: 8px; color: white; font-size: 1rem;" maxlength="100">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-size: 0.9rem; opacity: 0.8;">Style</label>
                    <select id="ltStyle" style="width: 100%; padding: 12px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
                        border-radius: 8px; color: white; font-size: 1rem;">
                        <option value="modern" ${current.style === 'modern' ? 'selected' : ''}>Modern Minimal</option>
                        <option value="bold" ${current.style === 'bold' ? 'selected' : ''}>Bold Accent</option>
                        <option value="news" ${current.style === 'news' ? 'selected' : ''}>News Style</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <button id="ltSave" style="flex: 1; padding: 12px; background: #7c3aed; border: none; border-radius: 8px;
                    color: white; font-weight: 600; cursor: pointer;">Save & Show</button>
                <button id="ltCancel" style="flex: 1; padding: 12px; background: rgba(255,255,255,0.1); border: none; border-radius: 8px;
                    color: white; font-weight: 600; cursor: pointer;">Cancel</button>
            </div>
        `;

        document.body.appendChild(editor);

        // Focus first input
        document.getElementById('ltName').focus();

        // Save button
        document.getElementById('ltSave').addEventListener('click', () => {
            const name = document.getElementById('ltName').value.trim();
            const title = document.getElementById('ltTitle').value.trim();
            const style = document.getElementById('ltStyle').value;

            if (name && title) {
                this.currentLowerThird = { name, title, style };

                // Add to presets if not exists
                const exists = this.lowerThirds.some(lt => lt.name === name && lt.title === title);
                if (!exists) {
                    this.lowerThirds.unshift({ name, title, style });
                    if (this.lowerThirds.length > 10) this.lowerThirds.pop();
                    localStorage.setItem('fdtv_lower_thirds', JSON.stringify(this.lowerThirds));
                }

                this.showLowerThird();
                editor.remove();
            }
        });

        // Cancel button
        document.getElementById('ltCancel').addEventListener('click', () => {
            editor.remove();
        });

        // Enter key to save
        editor.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                document.getElementById('ltSave').click();
            } else if (e.key === 'Escape') {
                editor.remove();
            }
        });
    }

    // Social Media Badges Feature
    initSocialMediaBadges() {
        this.socialBadgesVisible = false;
        this.currentBadgeIndex = 0;

        // Load social badges from station database settings
        try {
            this.socialBadges = JSON.parse(this.station.social_badges || '[]');
        } catch (e) {
            this.socialBadges = [];
        }

        // Add default badges if empty
        if (this.socialBadges.length === 0) {
            const stationName = this.station.name ? this.station.name.replace(/\s+/g, '') : 'YourStation';
            this.socialBadges = [
                { platform: 'Twitter', handle: `@${stationName}`, icon: '𝕏' },
                { platform: 'Facebook', handle: `/${stationName}`, icon: '📘' },
                { platform: 'Instagram', handle: `@${stationName}`, icon: '📷' },
                { platform: 'YouTube', handle: stationName, icon: '▶️' }
            ];
        }

        // Keyboard shortcut: @ key
        document.addEventListener('keydown', (e) => {
            if (e.key === '@' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                this.toggleSocialBadges();
            }
            // Ctrl+@ to edit
            else if ((e.ctrlKey || e.metaKey) && e.key === '@') {
                e.preventDefault();
                this.openSocialBadgesEditor();
            }
        });
    }

    toggleSocialBadges() {
        if (this.socialBadgesVisible) {
            this.hideSocialBadges();
        } else {
            this.showSocialBadges();
        }
    }

    showSocialBadges() {
        if (this.socialBadges.length === 0) return;

        // Create container
        const container = document.createElement('div');
        container.id = 'socialBadgesContainer';
        container.className = 'social-badges-container';

        this.tvContainer.appendChild(container);
        this.socialBadgesVisible = true;

        // Cycle through badges
        this.cycleSocialBadges();
    }

    cycleSocialBadges() {
        if (!this.socialBadgesVisible || this.socialBadges.length === 0) return;

        const container = document.getElementById('socialBadgesContainer');
        if (!container) return;

        const badge = this.socialBadges[this.currentBadgeIndex];

        // Create badge element
        const badgeEl = document.createElement('div');
        badgeEl.className = 'social-badge';
        badgeEl.innerHTML = `
            <span class="badge-icon">${badge.icon}</span>
            <span class="badge-handle">${this.escapeHtml(badge.handle)}</span>
        `;

        // Clear and add new badge
        container.innerHTML = '';
        container.appendChild(badgeEl);

        // Animate in
        setTimeout(() => badgeEl.classList.add('visible'), 50);

        // Move to next badge after 5 seconds
        this.currentBadgeIndex = (this.currentBadgeIndex + 1) % this.socialBadges.length;

        if (this.socialBadgeTimeout) clearTimeout(this.socialBadgeTimeout);
        this.socialBadgeTimeout = setTimeout(() => this.cycleSocialBadges(), 5000);
    }

    hideSocialBadges() {
        const container = document.getElementById('socialBadgesContainer');
        if (container) {
            container.remove();
        }
        if (this.socialBadgeTimeout) {
            clearTimeout(this.socialBadgeTimeout);
        }
        this.socialBadgesVisible = false;
    }

    openSocialBadgesEditor() {
        const editor = document.createElement('div');
        editor.id = 'socialBadgesEditor';
        editor.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.95);
            color: white;
            padding: 32px;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
            z-index: 10000;
            backdrop-filter: blur(20px);
            border: 2px solid rgba(124, 58, 237, 0.5);
        `;

        let badgesHtml = this.socialBadges.map((badge, i) => `
            <div style="display: grid; grid-template-columns: 60px 1fr auto; gap: 12px; align-items: center;
                padding: 12px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                <input type="text" class="badge-icon-input" data-index="${i}" value="${badge.icon}"
                    style="width: 50px; padding: 8px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
                    border-radius: 6px; color: white; text-align: center;" maxlength="2">
                <input type="text" class="badge-handle-input" data-index="${i}" value="${this.escapeHtml(badge.handle)}"
                    style="width: 100%; padding: 8px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);
                    border-radius: 6px; color: white;" placeholder="@handle" maxlength="50">
                <button class="delete-badge" data-index="${i}" style="padding: 8px 12px; background: rgba(255,0,0,0.3);
                    border: none; border-radius: 6px; color: white; cursor: pointer;">✕</button>
            </div>
        `).join('');

        editor.innerHTML = `
            <h2 style="margin: 0 0 24px 0; font-size: 1.5rem;">📱 Social Media Badges</h2>
            <div style="display: grid; gap: 12px; max-height: 400px; overflow-y: auto; margin-bottom: 16px;">
                ${badgesHtml}
            </div>
            <div style="display: flex; gap: 12px;">
                <button id="sbSave" style="flex: 1; padding: 12px; background: #7c3aed; border: none; border-radius: 8px;
                    color: white; font-weight: 600; cursor: pointer;">Save & Show</button>
                <button id="sbCancel" style="flex: 1; padding: 12px; background: rgba(255,255,255,0.1); border: none; border-radius: 8px;
                    color: white; font-weight: 600; cursor: pointer;">Cancel</button>
            </div>
        `;

        document.body.appendChild(editor);

        // Delete badge handlers
        editor.querySelectorAll('.delete-badge').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = parseInt(e.target.dataset.index);
                this.socialBadges.splice(index, 1);
                editor.remove();
                this.openSocialBadgesEditor(); // Refresh
            });
        });

        // Save button
        document.getElementById('sbSave').addEventListener('click', () => {
            const newBadges = [];
            editor.querySelectorAll('.badge-icon-input').forEach((input, i) => {
                const icon = input.value.trim();
                const handle = editor.querySelectorAll('.badge-handle-input')[i].value.trim();
                if (icon && handle) {
                    newBadges.push({ icon, handle, platform: 'Custom' });
                }
            });

            this.socialBadges = newBadges;
            localStorage.setItem('fdtv_social_badges', JSON.stringify(this.socialBadges));

            this.hideSocialBadges();
            this.showSocialBadges();
            editor.remove();

            this.showNotification('Social badges updated!');
        });

        // Cancel button
        document.getElementById('sbCancel').addEventListener('click', () => {
            editor.remove();
        });
    }

    // Helper method for escaping HTML
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Generic notification helper
    showNotification(message) {
        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
            z-index: 9999;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(124, 58, 237, 0.5);
            animation: slideInRight 0.3s ease-out;
        `;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 2000);
    }

    // Cleanup method
    destroy() {
        // End analytics session
        this.endAnalyticsSession();

        if (this.hlsInstance) {
            this.hlsInstance.destroy();
            this.hlsInstance = null;
        }
        clearTimeout(this.idleTimer);

        if (this.pingInterval) {
            clearInterval(this.pingInterval);
        }
    }
}

// Ticker animation speed adjustment based on content length
function adjustTickerSpeed() {
    const ticker = document.getElementById('tickerTrack');
    if (!ticker) return;

    const tickerText = ticker.querySelector('.ticker-text');
    if (!tickerText) return;

    const textWidth = tickerText.offsetWidth;

    // Adjust animation duration based on text length
    // Longer text = longer duration for consistent speed
    const duration = Math.max(30, textWidth / 50);
    ticker.style.animationDuration = `${duration}s`;
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Check if stationData exists
    if (typeof stationData !== 'undefined') {
        window.liveTVPlayer = new LiveTVPlayer(stationData);
    }

    // Adjust ticker speed
    setTimeout(adjustTickerSpeed, 100);

    // Re-adjust on resize
    window.addEventListener('resize', adjustTickerSpeed);
});

// Prevent pull-to-refresh on mobile
document.body.addEventListener('touchmove', function(e) {
    if (e.target.closest('.tv-container')) {
        e.preventDefault();
    }
}, { passive: false });

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (window.liveTVPlayer) {
        window.liveTVPlayer.destroy();
    }
});
