# 🚀 Database-Backed Display Settings - Deployment Guide

## Overview

This guide will help you deploy the new database-backed display settings system that moves all broadcast controls from localStorage (personal preferences) to the admin dashboard (station-wide settings).

---

## 📋 What Changed

### Previous System (localStorage-based):
- ❌ Each viewer had their own personal settings
- ❌ Changes made by one user didn't affect others
- ❌ No centralized control for station owners
- ❌ Settings stored in browser (lost on cache clear)

### New System (Database-backed):
- ✅ Station owner controls all display settings from admin dashboard
- ✅ All viewers see the same broadcast appearance
- ✅ Settings persist in database
- ✅ Professional broadcast control paradigm

---

## 🛠️ Deployment Steps

### Step 1: Pull Latest Code from Git

```bash
cd c:\Users\HP\Documents\xampp\htdocs\fdtv
git pull origin main
```

**What this updates:**
- `assets/js/live-tv-player.js` - Loads settings from database
- `assets/css/live-tv.css` - Removes draggable clock styling
- `dashboard/display-settings.php` - NEW admin interface
- `database/migrations/phase7_station_display_settings.sql` - NEW database schema

---

### Step 2: Run Database Migration

**Option A: Using phpMyAdmin**

1. Open phpMyAdmin in your browser
2. Select your FDTV database
3. Click the "SQL" tab
4. Copy and paste the contents of `database/migrations/phase7_station_display_settings.sql`
5. Click "Go" to execute

**Option B: Using MySQL Command Line**

```bash
mysql -u your_username -p your_database_name < database/migrations/phase7_station_display_settings.sql
```

**What this does:**
- Adds 8 new columns to the `stations` table
- Sets up default values for existing stations
- Creates JSON fields for complex data (social badges, lower thirds)
- Populates defaults for social media handles and lower third presets

**Columns added:**
```sql
ticker_color VARCHAR(20) DEFAULT 'red'
ticker_label VARCHAR(15) DEFAULT 'BREAKING'
ticker_mode ENUM('single', 'double') DEFAULT 'single'
ticker_speed INT DEFAULT 60
clock_position_x INT DEFAULT 0
clock_position_y INT DEFAULT 0
social_badges JSON DEFAULT NULL
lower_thirds_presets JSON DEFAULT NULL
display_settings_updated_at TIMESTAMP NULL
```

---

### Step 3: Access the New Admin Dashboard

**URL:** `http://your-domain.com/dashboard/display-settings.php?station=YOUR_STATION_ID`

**Navigation:**
- The page is currently standalone
- You may want to add a link to your dashboard sidebar:

```php
<!-- Add to your dashboard navigation -->
<a href="display-settings.php?station=<?php echo $station['id']; ?>" class="nav-link">
    🎨 Display Settings
</a>
```

---

### Step 4: Configure Your Station's Display Settings

In the Display Settings admin page, you can now control:

#### 1. Ticker Settings
- **Color**: Choose from 8 professional color presets (Red, Purple, Green, Blue, Orange, Pink, Teal, Indigo)
- **Label**: Customize the ticker label (max 15 characters, e.g., "BREAKING", "LIVE", "URGENT")
- **Mode**: Single-line or double-line ticker
- **Speed**: Scroll speed in seconds (20-120 seconds)

#### 2. Clock Position
- **X Offset**: Horizontal position (-500 to 500 pixels)
- **Y Offset**: Vertical position (-500 to 500 pixels)
- **Live Preview**: See clock movement in real-time as you adjust sliders

#### 3. Social Media Badges
- Add up to 4 social media platforms
- Customize icon emoji (e.g., 𝕏, 📘, 📷, ▶️)
- Set handles for each platform
- Badges cycle automatically every 5 seconds on the watch page

#### 4. Lower Thirds (Name/Title Graphics)
- Create up to 10 presenter presets
- Set name, title, and style (Modern, Bold, or News)
- Quick access during live broadcasts with keyboard shortcuts

---

### Step 5: Test the Changes

1. **Save settings** in the admin dashboard
2. **Open your station's watch page**: `watch.php?station=YOUR_STATION_ID`
3. **Verify all settings are applied**:
   - ✅ Ticker shows correct color
   - ✅ Ticker label shows your custom text
   - ✅ Clock is positioned correctly
   - ✅ Social badges cycle if enabled
   - ✅ Lower thirds available if configured

4. **Test on multiple devices/browsers**:
   - All viewers should see the same settings
   - Changes in admin should reflect immediately on watch page (after refresh)

---

## 🔧 Features Reference

### What Viewers CAN Do (Keyboard Shortcuts Still Work):

| Key | Action |
|-----|--------|
| **M** | Mute/Unmute audio |
| **F** | Toggle fullscreen |
| **↑ / ↓** | Volume up/down |
| **L** | Show/hide lower third |
| **@** | Show/hide social badges |
| **Ctrl + P** | Picture-in-Picture mode |
| **? or H** | Show keyboard shortcuts help |

### What Viewers CANNOT Do Anymore:

- ❌ Click ticker to change color (admin-controlled)
- ❌ Double-click to edit ticker label (admin-controlled)
- ❌ Drag clock to new position (admin-controlled)
- ❌ Change ticker speed with +/- keys (admin-controlled)
- ❌ Toggle ticker mode with Ctrl+T (admin-controlled)
- ❌ Edit social badges with Ctrl+@ (admin-controlled)
- ❌ Edit lower thirds with Ctrl+L (admin-controlled)

**Note**: Presenters can still show/hide elements during broadcasts (L for lower thirds, @ for social badges), but they cannot edit the content - only the admin can do that.

---

## 📊 Database Schema Reference

### Stations Table (New Columns)

```sql
-- Simple text/number fields
ticker_color: VARCHAR(20) - Values: 'red', 'purple', 'green', 'blue', 'orange', 'pink', 'teal', 'indigo'
ticker_label: VARCHAR(15) - Custom label text (e.g., "BREAKING", "LIVE")
ticker_mode: ENUM('single', 'double') - Ticker line mode
ticker_speed: INT - Scroll speed in seconds
clock_position_x: INT - Horizontal offset in pixels
clock_position_y: INT - Vertical offset in pixels

-- JSON fields
social_badges: JSON - Array of {icon, handle, platform} objects
Example:
[
  {"icon": "𝕏", "handle": "@YourStation", "platform": "Twitter"},
  {"icon": "📘", "handle": "/YourStation", "platform": "Facebook"}
]

lower_thirds_presets: JSON - Array of {name, title, style} objects
Example:
[
  {"name": "John Smith", "title": "News Anchor", "style": "modern"},
  {"name": "Jane Doe", "title": "Weather Reporter", "style": "bold"}
]

-- Timestamp
display_settings_updated_at: TIMESTAMP - Last update time
```

---

## 🐛 Troubleshooting

### Issue: "Display settings page shows 404"
**Solution**: Make sure you pulled the latest code from Git. The file should be at `dashboard/display-settings.php`

### Issue: "Database columns not found"
**Solution**: Run the migration SQL file. Check that all columns were added:
```sql
DESCRIBE stations;
```
You should see the new columns: `ticker_color`, `ticker_label`, etc.

### Issue: "Settings don't show on watch page"
**Solution**:
1. Check that settings were saved (check database directly)
2. Clear browser cache and refresh
3. Verify the station ID in the URL matches the one you configured
4. Check browser console for JavaScript errors

### Issue: "Social badges or lower thirds don't load"
**Solution**:
1. Check that the JSON data is valid in the database
2. The fields should contain valid JSON arrays
3. If empty, the system will use default values

### Issue: "Clock position is wrong"
**Solution**:
1. Reset X and Y offsets to 0 in admin dashboard
2. Adjust slowly using the sliders with live preview
3. Remember: X offset is horizontal (-500 to 500), Y offset is vertical (-500 to 500)

---

## 🔐 Security Notes

- ✅ All admin forms use CSRF protection
- ✅ User inputs are sanitized and validated
- ✅ SQL injection prevention with prepared statements
- ✅ XSS protection on ticker text and labels
- ✅ JSON encoding prevents injection attacks

---

## 📁 Files Modified/Created

### New Files:
1. `database/migrations/phase7_station_display_settings.sql` - Database schema
2. `dashboard/display-settings.php` - Admin interface (800+ lines)
3. `DEPLOYMENT_GUIDE.md` - This file

### Modified Files:
1. `assets/js/live-tv-player.js` - Removed localStorage, added database loading
2. `assets/css/live-tv.css` - Removed draggable clock styling

---

## 📈 Next Steps (Optional Enhancements)

1. **Add navigation link**: Add "Display Settings" to your dashboard sidebar
2. **Bulk edit**: Add ability to copy settings from one station to another
3. **Preset templates**: Create reusable display setting templates
4. **Schedule changes**: Time-based ticker switching (e.g., news ticker in morning, sports in evening)
5. **Analytics**: Track viewer engagement with different ticker colors

---

## ✅ Verification Checklist

After deployment, verify:

- [ ] Database migration ran successfully (all columns added)
- [ ] Display Settings page loads without errors
- [ ] Can save ticker color and see it on watch page
- [ ] Can save ticker label and see it on watch page
- [ ] Can adjust clock position and see it on watch page
- [ ] Can add social badges and see them cycle on watch page
- [ ] Can add lower thirds and show them with L key
- [ ] Settings persist after browser refresh
- [ ] Multiple browsers/devices show same settings
- [ ] Old localStorage settings no longer interfere

---

## 🎯 Summary

You've successfully migrated from a localStorage-based system to a professional database-backed broadcast control system. Station owners now have full control over their broadcast's appearance, and all viewers see a consistent, professional presentation.

**Key Benefits:**
- ✅ Centralized admin control
- ✅ Consistent viewer experience
- ✅ Professional broadcast paradigm
- ✅ Settings persist reliably
- ✅ No more per-user confusion

---

**Deployment Date**: December 30, 2025
**Migration Version**: Phase 7
**Status**: ✅ Ready for Production

For questions or issues, refer to the troubleshooting section above or check the implementation details in `IMPLEMENTED_FEATURES.md`.
