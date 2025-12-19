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
