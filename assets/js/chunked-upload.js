/**
 * FDTV Chunked Video Upload Handler
 * Handles large file uploads by splitting into chunks
 */

class ChunkedUploader {
    constructor(options) {
        this.file = null;
        this.title = '';
        this.metadata = {};
        this.uploadId = null;
        this.chunkSize = 2 * 1024 * 1024; // 2MB chunks
        this.currentChunk = 0;
        this.totalChunks = 0;
        this.uploadedBytes = 0;
        this.isUploading = false;
        this.isPaused = false;
        this.isCancelled = false;
        this.retryCount = 0;
        this.maxRetries = 3;

        // Callbacks
        this.onProgress = options.onProgress || function () { };
        this.onSuccess = options.onSuccess || function () { };
        this.onError = options.onError || function () { };
        this.onStateChange = options.onStateChange || function () { };

        // Endpoint
        this.endpoint = options.endpoint || '../includes/upload_handler.php';
    }

    /**
     * Start upload process
     */
    async start(file, title, metadata = {}) {
        if (this.isUploading) {
            this.onError('Upload already in progress');
            return;
        }

        this.file = file;
        this.title = title;
        this.metadata = metadata;
        this.currentChunk = 0;
        this.totalChunks = Math.ceil(file.size / this.chunkSize);
        this.uploadedBytes = 0;
        this.isUploading = true;
        this.isPaused = false;
        this.isCancelled = false;
        this.retryCount = 0;

        this.onStateChange('initializing');

        try {
            // Initialize upload on server
            const initResult = await this.initializeUpload();

            if (!initResult.success) {
                throw new Error(initResult.error || 'Failed to initialize upload');
            }

            this.uploadId = initResult.upload_id;
            this.onStateChange('uploading');

            // Start uploading chunks
            await this.uploadChunks();

        } catch (error) {
            this.isUploading = false;
            this.onError(error.message);
            this.onStateChange('error');
        }
    }

    /**
     * Initialize upload session
     */
    async initializeUpload() {
        const formData = new FormData();
        formData.append('action', 'init');
        formData.append('filename', this.file.name);
        formData.append('filesize', this.file.size);
        formData.append('title', this.title);

        // Append additional metadata
        if (this.metadata.content_type) {
            formData.append('content_type', this.metadata.content_type);
        }
        if (this.metadata.priority) {
            formData.append('priority', this.metadata.priority);
        }

        const response = await fetch(this.endpoint, {
            method: 'POST',
            body: formData
        });

        return await response.json();
    }

    /**
     * Upload all chunks
     */
    async uploadChunks() {
        while (this.currentChunk < this.totalChunks) {
            // Check if paused or cancelled
            if (this.isPaused) {
                this.onStateChange('paused');
                return;
            }

            if (this.isCancelled) {
                await this.cancelUpload();
                return;
            }

            try {
                await this.uploadChunk(this.currentChunk);
                this.currentChunk++;
                this.retryCount = 0; // Reset retry count on success

            } catch (error) {
                if (this.retryCount < this.maxRetries) {
                    this.retryCount++;
                    console.log(`Retrying chunk ${this.currentChunk}, attempt ${this.retryCount}`);
                    await this.delay(1000 * this.retryCount); // Exponential backoff
                    continue;
                } else {
                    throw new Error(`Failed to upload chunk ${this.currentChunk} after ${this.maxRetries} retries`);
                }
            }
        }

        // All chunks uploaded, finalize
        await this.finalizeUpload();
    }

    /**
     * Upload single chunk
     */
    async uploadChunk(chunkIndex) {
        const start = chunkIndex * this.chunkSize;
        const end = Math.min(start + this.chunkSize, this.file.size);
        const chunk = this.file.slice(start, end);

        const formData = new FormData();
        formData.append('action', 'upload_chunk');
        formData.append('upload_id', this.uploadId);
        formData.append('chunk_index', chunkIndex);
        formData.append('total_chunks', this.totalChunks);
        formData.append('chunk', chunk);

        const response = await fetch(this.endpoint, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Chunk upload failed');
        }

        // Update progress
        this.uploadedBytes = end;
        const progress = Math.round((this.uploadedBytes / this.file.size) * 100);
        this.onProgress(progress, this.uploadedBytes, this.file.size, chunkIndex + 1, this.totalChunks);

        return result;
    }

    /**
     * Finalize upload
     */
    async finalizeUpload() {
        this.onStateChange('finalizing');

        const formData = new FormData();
        formData.append('action', 'finalize');
        formData.append('upload_id', this.uploadId);

        const response = await fetch(this.endpoint, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to finalize upload');
        }

        this.isUploading = false;
        this.onStateChange('completed');
        this.onSuccess(result);
    }

    /**
     * Cancel upload
     */
    async cancelUpload() {
        if (this.uploadId) {
            const formData = new FormData();
            formData.append('action', 'cancel');
            formData.append('upload_id', this.uploadId);

            try {
                await fetch(this.endpoint, {
                    method: 'POST',
                    body: formData
                });
            } catch (e) {
                console.error('Failed to cancel upload on server:', e);
            }
        }

        this.isUploading = false;
        this.onStateChange('cancelled');
    }

    /**
     * Pause upload
     */
    pause() {
        if (this.isUploading && !this.isPaused) {
            this.isPaused = true;
        }
    }

    /**
     * Resume upload
     */
    async resume() {
        if (this.isPaused) {
            this.isPaused = false;
            this.onStateChange('uploading');

            try {
                await this.uploadChunks();
            } catch (error) {
                this.isUploading = false;
                this.onError(error.message);
                this.onStateChange('error');
            }
        }
    }

    /**
     * Cancel upload
     */
    cancel() {
        this.isCancelled = true;
        if (!this.isUploading) {
            this.cancelUpload();
        }
    }

    /**
     * Helper delay function
     */
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Format bytes to human readable
     */
    static formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Format time remaining
     */
    static formatTime(seconds) {
        if (seconds < 60) {
            return Math.round(seconds) + 's';
        } else if (seconds < 3600) {
            return Math.round(seconds / 60) + 'm ' + Math.round(seconds % 60) + 's';
        } else {
            return Math.round(seconds / 3600) + 'h ' + Math.round((seconds % 3600) / 60) + 'm';
        }
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ChunkedUploader;
}