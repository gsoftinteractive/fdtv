# FDTV - TV & Radio Streaming Platform

A modern, self-hosted TV and Radio streaming platform with PWA support, built with PHP and MySQL.

## Features

### TV Streaming
- Upload and manage video content for live TV channels
- Create multiple stations with custom branding
- Playlist management with scheduling
- Auto-loop playback for continuous streaming
- Support for multiple video formats (MP4, WebM, etc.)

### Radio Streaming
- **Stream Mode**: Connect to external streaming services (Shoutcast, Icecast, etc.)
- **Upload Mode**: Self-contained radio with uploaded audio files
- **Both Mode**: Combine external streams with uploaded content
- Playlist management with shuffle and auto-advance
- Lock screen controls via Media Session API
- Track metadata (artist, album, genre, duration)

### Progressive Web App (PWA)
- Installable on mobile and desktop devices
- Offline support with service worker caching
- Push notification ready
- Responsive design for all screen sizes

### Dashboard
- Station management (TV and Radio)
- Content upload and organization
- Analytics and viewer statistics
- User management
- News ticker management

### Additional Features
- News ticker overlay for live broadcasts
- Viewer analytics and tracking
- Multi-station support
- Mobile-friendly interface
- Modern glassmorphism UI design

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- mod_rewrite enabled (for Apache)

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/gsoftinteractive/fdtv.git
cd fdtv
```

### 2. Configure Database

1. Create a MySQL database for the project
2. Copy the configuration template:

```bash
cp includes/config.sample.php includes/config.php
```

3. Edit `includes/config.php` with your database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
```

### 3. Run Database Migrations

Import the migration files in order:

```bash
mysql -u your_user -p your_database < migrations/001_initial_schema.sql
mysql -u your_user -p your_database < migrations/002_ticker.sql
mysql -u your_user -p your_database < migrations/003_playlist.sql
mysql -u your_user -p your_database < migrations/004_analytics.sql
mysql -u your_user -p your_database < migrations/005_radio.sql
mysql -u your_user -p your_database < migrations/006_radio_settings.sql
mysql -u your_user -p your_database < migrations/007_radio_audio_tracks.sql
```

Or import all at once through phpMyAdmin.

### 4. Create Upload Directories

```bash
mkdir -p uploads/videos
mkdir -p uploads/audio
mkdir -p uploads/thumbnails
mkdir -p uploads/logos
chmod -R 755 uploads/
```

### 5. Configure Web Server

#### Apache (.htaccess included)
Ensure mod_rewrite is enabled and AllowOverride is set to All.

#### Nginx
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 6. Access the Dashboard

Navigate to `http://your-domain.com/dashboard` to access the admin panel.

Default credentials (change after first login):
- Username: `admin`
- Password: `admin123`

## Directory Structure

```
fdtv/
├── assets/
│   ├── css/           # Stylesheets
│   ├── js/            # JavaScript files
│   └── images/        # Static images
├── dashboard/         # Admin dashboard
├── includes/          # PHP includes and config
├── migrations/        # Database migration files
├── radio/             # Radio player pages
├── tv/                # TV player pages
├── uploads/           # User uploaded content
│   ├── videos/        # Video files
│   ├── audio/         # Audio files
│   ├── thumbnails/    # Thumbnail images
│   └── logos/         # Station logos
├── index.php          # Landing page
├── manifest.json      # PWA manifest
├── service-worker.js  # PWA service worker
└── offline.html       # Offline fallback page
```

## Usage

### Creating a TV Station

1. Log into the dashboard
2. Navigate to "Stations" > "Add New"
3. Enter station details (name, description, logo)
4. Enable "TV Enabled" option
5. Upload video content in the "Videos" tab
6. Create playlists and schedule content

### Creating a Radio Station

1. Log into the dashboard
2. Navigate to "Stations" > "Add New" or edit existing
3. Enable "Radio Enabled" option
4. Choose Radio Mode:
   - **Stream**: Enter external stream URL (Shoutcast, Icecast)
   - **Upload**: Upload audio files directly
   - **Both**: Use both methods
5. For Upload mode:
   - Go to "Audio Library" tab
   - Upload MP3/audio files
   - Manage track metadata
   - Enable shuffle/auto-advance as needed

### PWA Installation

Users can install FDTV as an app:
- **Mobile**: Tap "Add to Home Screen" in browser menu
- **Desktop**: Click install icon in address bar (Chrome/Edge)

## API Endpoints

### Public Endpoints

- `GET /tv/stream.php?station={id}` - Get TV stream data
- `GET /radio/stream.php?station={id}` - Get radio stream data
- `GET /api/ticker.php?station={id}` - Get ticker messages

### Dashboard API

- `POST /dashboard/videos.php` - Upload/manage videos
- `POST /dashboard/radio.php` - Manage radio content
- `POST /dashboard/stations.php` - Manage stations

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 11+
- Edge 79+
- Opera 47+

## Security Recommendations

1. Change default admin credentials immediately
2. Use HTTPS in production
3. Set proper file permissions (755 for directories, 644 for files)
4. Configure firewall rules
5. Keep PHP and MySQL updated
6. Use strong database passwords

## Troubleshooting

### Videos not playing
- Check file permissions in uploads/videos/
- Verify video format is supported (MP4 recommended)
- Check browser console for errors

### Radio not streaming
- For stream mode: Verify external stream URL is accessible
- For upload mode: Check audio file formats (MP3 recommended)
- Ensure radio_mode is set correctly in database

### PWA not installing
- Ensure site is served over HTTPS
- Check manifest.json is accessible
- Verify service worker is registered

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is proprietary software. All rights reserved.

## Support

For support and inquiries, contact:
- Email: support@fdtv.ng
- Website: https://fdtv.ng

---

Built with passion by GSoft Interactive
